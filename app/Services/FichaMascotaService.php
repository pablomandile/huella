<?php

namespace App\Services;

use App\Enums\EstadoTratamiento;
use App\Enums\RolCuidador;
use App\Enums\TipoAdjunto;
use App\Enums\TipoAlergia;
use App\Enums\VigenciaEnlace;
use App\Http\Resources\AdjuntoResource;
use App\Http\Resources\MascotaResource;
use App\Http\Resources\RecordatorioResource;
use App\Http\Resources\TratamientoResource;
use App\Http\Resources\VisitaResource;
use App\Models\Mascota;
use App\Models\User;

/**
 * Todo lo que muestra la ficha de una mascota, armado en un solo lugar.
 *
 * Lo usan dos pantallas: la de la mascota y el inicio, que muestra la ficha
 * completa de la activa. Si cada una armara lo suyo, un dato nuevo de la ficha
 * aparecería en una y en la otra no, sin ningún error que lo delate.
 *
 * Las escrituras que salen de la ficha (alergias, fotos, documentos) vuelven con
 * `back()`, así que andan igual desde las dos pantallas: para eso las dos tienen
 * que recibir exactamente los mismos datos, que es lo que garantiza esto.
 *
 * No autoriza: eso le toca al controlador, con la Policy, antes de llamarlo.
 */
class FichaMascotaService
{
    public function __construct(private readonly EstadoVacunacionService $vacunacion) {}

    /**
     * @return array<string, mixed>
     */
    public function datos(Mascota $mascota, User $usuario): array
    {
        $mascota->load(['fotos', 'alergias', 'adjuntos']);

        $puedeCompartir = $usuario->can('compartir', $mascota);

        // Las últimas visitas y lo que está tomando ahora: es lo que se busca
        // al abrir la ficha, y el resto del historial vive en su propia pantalla.
        $visitas = $mascota->visitas()
            ->with(['veterinaria', 'veterinario', 'tratamientos.medicamento', 'adjuntos'])
            ->take(3)
            ->get();

        $enCurso = $mascota->tratamientos()
            ->where('estado', EstadoTratamiento::Activo)
            ->with(['medicamento', 'tomas'])
            ->get();

        // Lo que hay que agendar, que es lo que la ficha tiene que gritar.
        $recordatorios = $mascota->recordatorios()
            ->abiertos()
            ->with('mascota')
            ->take(5)
            ->get();

        return [
            'mascota' => MascotaResource::make($mascota)->resolve(),
            'visitas' => VisitaResource::collection($visitas)->resolve(),
            'totalVisitas' => $mascota->visitas()->count(),
            'tratamientosEnCurso' => TratamientoResource::collection($enCurso)->resolve(),
            'recordatorios' => RecordatorioResource::collection($recordatorios)->resolve(),
            'estadoVacunacion' => $this->vacunacion->para($mascota),
            'fotos' => $mascota->fotos->map(fn ($foto) => [
                'id' => $foto->id,
                'fecha' => $foto->fecha->toDateString(),
                'epigrafe' => $foto->epigrafe,
                'url' => route('mascotas.fotos.mostrar', [$mascota, $foto]),
                'miniatura_url' => route('mascotas.fotos.mostrar', [$mascota, $foto, 'min' => 1]),
            ]),
            'alergias' => $mascota->alergias->map(fn ($alergia) => [
                'id' => $alergia->id,
                'tipo' => $alergia->tipo->value,
                'tipo_etiqueta' => $alergia->tipo->etiqueta(),
                'agente' => $alergia->agente,
                'severidad' => $alergia->severidad?->value,
                'severidad_etiqueta' => $alergia->severidad?->etiqueta(),
                'fecha_deteccion' => $alergia->fecha_deteccion?->toDateString(),
                'sintomas' => $alergia->sintomas,
                'notas' => $alergia->notas,
            ]),
            /*
             * Los documentos, agrupados por tipo y con la clave siempre presente
             * aunque esté vacía: la tarjeta tiene que dibujarse igual para poder
             * ofrecer el botón de subir, y un `?.` de más en el front por cada
             * tipo es lo que se evita acá.
             */
            'documentos' => collect(TipoAdjunto::documentosDeMascota())
                ->mapWithKeys(fn (TipoAdjunto $tipo) => [
                    $tipo->value => AdjuntoResource::collection(
                        $mascota->adjuntos->where('tipo', $tipo)->values(),
                    )->resolve(),
                ])
                ->all(),
            'vencimientoRabia' => $mascota->rabia_vencimiento?->toDateString(),
            'estadoRabia' => $mascota->estado_rabia,
            'puedeEditar' => $usuario->can('update', $mascota),
            'puedeRegistrar' => $usuario->can('registrarEventos', $mascota),
            'puedeCompartir' => $puedeCompartir,
            /*
             * Quiénes tienen acceso y qué enlaces hay, **solo para el
             * propietario**: los correos de los otros cuidadores no tienen por
             * qué viajar al front de un lector, y el token de un enlace menos.
             */
            'accesos' => $puedeCompartir
                ? $mascota->cuidadores
                    ->map(fn ($cuidador) => [
                        'id' => $cuidador->id,
                        'nombre' => $cuidador->name,
                        'email' => $cuidador->email,
                        'rol' => $cuidador->getRelationValue('pivot')?->getAttribute('rol'),
                    ])
                    ->values()
                    ->all()
                : [],
            'enlaces' => $puedeCompartir
                ? $mascota->enlaces()->vigentes()->get()
                    ->map(fn ($enlace) => [
                        'id' => $enlace->id,
                        'nombre' => $enlace->nombre,
                        'url' => route('compartido.ficha', $enlace->token),
                        'incluye_adjuntos' => $enlace->incluye_adjuntos,
                        'vence' => $mascota->propietario
                            ->enSuZona($enlace->expira_en)
                            ?->translatedFormat('j \d\e F'),
                        'visitas' => $enlace->visitas,
                    ])
                    ->all()
                : [],
            'rolesInvitables' => RolCuidador::opcionesParaInvitar(),
            'vigencias' => VigenciaEnlace::opciones(),
            'vigenciaPorDefecto' => VigenciaEnlace::porDefecto()->value,
            'tiposAlergia' => TipoAlergia::opciones(),
        ];
    }
}
