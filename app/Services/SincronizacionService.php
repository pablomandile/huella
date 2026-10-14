<?php

namespace App\Services;

use App\Http\Controllers\DiarioController;
use App\Http\Controllers\MedicacionController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Requests\GuardarBanoRequest;
use App\Http\Requests\GuardarEntradaDiarioRequest;
use App\Http\Requests\GuardarPesoRequest;
use App\Http\Requests\MarcarTomaRequest;
use App\Models\Mascota;
use App\Models\Sincronizacion;
use App\Models\TomaMedicamento;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Aplica lo que el teléfono guardó en su cola mientras no tenía conexión.
 *
 * Solo las cuatro escrituras de todos los días: marcar una toma, registrar un
 * peso, un baño o una nota del diario. Lo demás —visitas, tratamientos,
 * vacunas, fotos— pide conexión: sus efectos los calcula el servidor (las tomas
 * de un tratamiento, los recordatorios) y la app no podría mostrarlos antes de
 * sincronizar sin inventarlos.
 *
 * **No reimplementa ninguna regla.** Cada operación pasa por el mismo
 * FormRequest —autorización y validación— y el mismo método del controlador que
 * usa el formulario con conexión. Lo cargado sin red cumple exactamente lo
 * mismo: un peso por día y por balanza, nada a futuro, nada en una ficha
 * fallecida ni en una ajena. Una regla nueva en el FormRequest vale acá sola.
 *
 * Idempotente por `op_id` (tabla `sincronizaciones`): reenviar lo ya aplicado
 * contesta "ok" sin repetirlo.
 */
class SincronizacionService
{
    /**
     * entidad => qué acción acepta, con qué FormRequest se valida, qué modelo
     * resuelve la ruta (y con qué nombre de parámetro) y qué método la aplica.
     *
     * @var array<string, array{accion: string, request: class-string<FormRequest>, modelo: class-string<Model>, parametro: string, controlador: array{class-string, string}}>
     */
    private const ENTIDADES = [
        'toma' => [
            'accion' => 'marcar',
            'request' => MarcarTomaRequest::class,
            'modelo' => TomaMedicamento::class,
            'parametro' => 'toma',
            'controlador' => [MedicacionController::class, 'update'],
        ],
        'peso' => [
            'accion' => 'crear',
            'request' => GuardarPesoRequest::class,
            'modelo' => Mascota::class,
            'parametro' => 'mascota',
            'controlador' => [SeguimientoController::class, 'guardarPeso'],
        ],
        'bano' => [
            'accion' => 'crear',
            'request' => GuardarBanoRequest::class,
            'modelo' => Mascota::class,
            'parametro' => 'mascota',
            'controlador' => [SeguimientoController::class, 'guardarBano'],
        ],
        'entrada' => [
            'accion' => 'crear',
            'request' => GuardarEntradaDiarioRequest::class,
            'modelo' => Mascota::class,
            'parametro' => 'mascota',
            'controlador' => [DiarioController::class, 'store'],
        ],
    ];

    /**
     * @param  array<mixed>  $op  {op_id, entidad, accion, id, datos}
     * @return array{op_id: string, estado: 'ok'|'error', errores?: array<string, array<int, string>>, repetida?: bool}
     */
    public function aplicar(User $usuario, array $op): array
    {
        $opId = is_string($op['op_id'] ?? null) ? $op['op_id'] : '';
        $entidad = $op['entidad'] ?? null;
        $config = is_string($entidad) ? (self::ENTIDADES[$entidad] ?? null) : null;

        if (
            ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $opId)
            || $config === null
            || ($op['accion'] ?? null) !== $config['accion']
            || ! is_int($op['id'] ?? null)
            || ! is_array($op['datos'] ?? null)
        ) {
            return $this->error($opId, 'Este cambio llegó incompleto y no se pudo aplicar.');
        }

        if ($this->yaAplicada($usuario, $opId)) {
            return ['op_id' => $opId, 'estado' => 'ok', 'repetida' => true];
        }

        try {
            DB::transaction(fn () => $this->ejecutar($usuario, $op, $config));
        } catch (ValidationException $e) {
            return ['op_id' => $opId, 'estado' => 'error', 'errores' => $e->errors()];
        } catch (AuthorizationException) {
            // Fallecida (modo lectura), o le quitaron el acceso mientras tanto.
            return $this->error($opId, 'Ya no se puede registrar en esta ficha: está en modo lectura o no tenés acceso.');
        } catch (UniqueConstraintViolationException) {
            // El mismo envío entró dos veces a la vez (dos pestañas): la otra
            // lo aplicó, y eso es lo que se quería.
            return ['op_id' => $opId, 'estado' => 'ok', 'repetida' => true];
        } catch (Throwable $e) {
            /*
             * Cualquier otra cosa, en ESTA operación y no en el lote. Un 500 del
             * lote entero haría que la app reintente cada 30 segundos para
             * siempre, con todo lo de atrás trabado detrás de una sola, y sin
             * avisarle nada a nadie. Así la operación queda fallida y visible, y
             * el error llega al log.
             */
            report($e);

            return $this->error($opId, 'No se pudo guardar por un error del servidor. Probá de nuevo más tarde.');
        }

        return ['op_id' => $opId, 'estado' => 'ok'];
    }

    /**
     * @param  array<mixed>  $op
     * @param  array{accion: string, request: class-string<FormRequest>, modelo: class-string<Model>, parametro: string, controlador: array{class-string, string}}  $config
     */
    private function ejecutar(User $usuario, array $op, array $config): void
    {
        $destino = $config['modelo']::query()->find($op['id']);

        if (! $destino instanceof Model) {
            throw ValidationException::withMessages([
                'op' => 'Ya no existe: se borró desde otro dispositivo.',
            ]);
        }

        /** @var array<string, mixed> $datos */
        $datos = $op['datos'];
        $request = $this->formRequest($config['request'], $usuario, $config['parametro'], $destino, $datos);

        // Autoriza y valida: lanza AuthorizationException o ValidationException.
        $request->validateResolved();

        [$clase, $metodo] = $config['controlador'];
        app($clase)->{$metodo}($request, $destino);

        Sincronizacion::create([
            'usuario_id' => $usuario->id,
            'op_id' => $op['op_id'],
            'entidad' => $op['entidad'],
            'accion' => $op['accion'],
            'destino_id' => $destino->getKey(),
        ]);
    }

    /**
     * El FormRequest armado como lo armaría Laravel para la ruta de verdad.
     *
     * La ruta se arma a mano con el **modelo** ya resuelto: los `authorize()`
     * preguntan `$this->route('mascota') instanceof Mascota`, y con el id crudo
     * contestarían que no. Es la misma trampa que documenta
     * `ResuelveMascotaDeLaRuta`, del otro lado.
     *
     * @param  class-string<FormRequest>  $clase
     * @param  array<string, mixed>  $datos
     */
    private function formRequest(string $clase, User $usuario, string $parametro, Model $destino, array $datos): FormRequest
    {
        $request = $clase::create('/sincronizar', 'POST', $this->normalizar($datos));
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $usuario);

        $ruta = new Route('POST', '/sincronizar', []);
        $ruta->parameters = [$parametro => $destino];
        $request->setRouteResolver(fn () => $ruta);

        return $request;
    }

    /**
     * Lo que harían `TrimStrings` y `ConvertEmptyStringsToNull`.
     *
     * Por HTTP no hace falta: esos middleware globales limpian también el JSON
     * anidado de `POST /sincronizar` antes de que llegue acá. Está para que el
     * servicio no dependa de eso: el FormRequest se arma a mano y no pasa por
     * ningún middleware, y si algún día se lo llama desde otro lado (un import,
     * un comando), el campo opcional vacío —la condición corporal que nadie
     * completa— llegaría como `''`. La validación lo deja pasar (las reglas no
     * obligatorias se saltean con un vacío) y MySQL lo rechaza en una columna
     * entera; SQLite, el de los tests, lo acepta callado.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function normalizar(array $datos): array
    {
        return array_map(function (mixed $valor): mixed {
            if (! is_string($valor)) {
                return $valor;
            }

            $valor = trim($valor);

            return $valor === '' ? null : $valor;
        }, $datos);
    }

    private function yaAplicada(User $usuario, string $opId): bool
    {
        return Sincronizacion::query()
            ->where('usuario_id', $usuario->id)
            ->where('op_id', $opId)
            ->exists();
    }

    /**
     * @return array{op_id: string, estado: 'error', errores: array<string, array<int, string>>}
     */
    private function error(string $opId, string $mensaje): array
    {
        return ['op_id' => $opId, 'estado' => 'error', 'errores' => ['op' => [$mensaje]]];
    }
}
