<?php

namespace Database\Seeders;

use App\Enums\Animo;
use App\Enums\CategoriaEntrada;
use App\Enums\Especie;
use App\Enums\IntensidadCelo;
use App\Enums\OrigenPeso;
use App\Enums\TipoDesparasitacion;
use App\Enums\TipoVisita;
use App\Enums\ViaAdministracion;
use App\Models\Alergia;
use App\Models\Alimento;
use App\Models\Mascota;
use App\Models\Medicamento;
use App\Models\User;
use App\Models\Vacuna;
use App\Models\Veterinaria;
use App\Models\Veterinario;
use App\Models\Visita;
use App\Services\DietaService;
use App\Services\GeneradorTomasService;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Contenido realista para mirar la app con datos: una mascota con su
     * historia completa y otra castrada, para ver el módulo de celo oculto.
     *
     * OJO: acá NO va WithoutModelEvents — los observers tienen que correr, y son
     * los que crean el pivote de propietario y los recordatorios.
     */
    public function run(): void
    {
        /*
         * Un usuario de demo en producción es una cuenta real que nadie creó, con
         * un email que no existe y mascotas inventadas mezcladas con las de
         * verdad. Corta con una excepción y no con un aviso a propósito: un
         * mensaje en la consola de un deploy no lo lee nadie.
         *
         * En producción se corre `db:seed --class=CatalogosSeeder`, que es la
         * única semilla que va allá. DatabaseSeeder tampoco llama a este.
         */
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DemoSeeder no corre en producción. Usá --class=CatalogosSeeder.',
            );
        }

        $usuario = User::factory()->create([
            'name' => 'Demo',
            'email' => 'demo@huella.test',
        ]);

        $greta = Mascota::factory()
            ->for($usuario, 'propietario')
            ->hembra()
            ->entera()
            ->create([
                'nombre' => 'Greta',
                'especie' => 'perro',
                'raza' => 'Mestiza',
                'fecha_nacimiento' => now()->subYears(3)->subMonths(2)->toDateString(),
                'fecha_nacimiento_estimada' => true,
            ]);

        Alergia::factory()->for($greta)->create([
            'tipo' => 'alimentaria',
            'agente' => 'Pollo',
            'severidad' => 'moderada',
            'sintomas' => 'Vómitos y picazón en las orejas',
        ]);

        Mascota::factory()
            ->for($usuario, 'propietario')
            ->castrada()
            ->create([
                'nombre' => 'Simón',
                'especie' => 'gato',
                'sexo' => 'macho',
                'raza' => 'Común europeo',
                'fecha_nacimiento' => now()->subYears(6)->toDateString(),
            ]);

        // Agenda propia del usuario demo: acá no hay semilla compartida.
        $veterinaria = Veterinaria::factory()->create([
            'usuario_id' => $usuario->id,
            'nombre' => 'Veterinaria del Parque',
            'direccion' => 'Av. Rivadavia 5200',
            'localidad' => 'Caballito',
            'telefono' => '11-4903-0000',
            'urgencias_24h' => true,
            'horarios' => 'Lunes a viernes de 9 a 20, sábados de 9 a 13',
        ]);

        $veterinario = Veterinario::factory()->create([
            'usuario_id' => $usuario->id,
            'veterinaria_id' => $veterinaria->id,
            'nombre' => 'Laura Giménez',
            'matricula' => 'MP 12345',
            'especialidad' => 'Clínica general',
        ]);

        // Una consulta con lo que salió de ella, para que la app se vea con
        // contenido real: es el caso del criterio de la fase 4.
        $visita = Visita::factory()->for($greta)->create([
            'veterinaria_id' => $veterinaria->id,
            'veterinario_id' => $veterinario->id,
            'fecha_hora' => now()->subDay()->setTime(18, 30),
            'tipo' => TipoVisita::Urgencia,
            'motivo' => 'Gastroenteritis',
            'diagnostico' => 'Cuadro gastroentérico agudo, sin signos de deshidratación.',
            'indicaciones' => 'Dieta blanda 3 días. Volver si sigue con vómitos.',
            'temperatura' => 39.2,
            'costo' => 42000,
            'proximo_control' => now()->addDays(7)->toDateString(),
        ]);

        $tomas = app(GeneradorTomasService::class);

        $recetados = [
            ['Cefalexina', '1 comprimido', 8, 7, '08:00', 'Dar con comida'],
            ['Metronidazol', '2,5 ml', 12, 5, '09:00', null],
        ];

        foreach ($recetados as [$droga, $dosis, $cada, $dias, $hora, $notas]) {
            $tratamiento = $greta->tratamientos()->create([
                'visita_id' => $visita->id,
                'medicamento_id' => Medicamento::whereNull('usuario_id')
                    ->where('nombre_comercial', $droga)
                    ->value('id'),
                'medicamento_libre' => $droga,
                'dosis' => $dosis,
                'via' => ViaAdministracion::Oral,
                'frecuencia_horas' => $cada,
                'duracion_dias' => $dias,
                'fecha_inicio' => now()->subDay()->toDateString(),
                'hora_primera_toma' => $hora,
                'notas' => $notas,
            ]);

            $tratamiento->setRelation('mascota', $greta);
            $tomas->generar($tratamiento);
        }

        // Preventivo: los observers crean sus recordatorios al guardar.
        // La antirrábica queda por vencer, para ver el semáforo en ámbar.
        $greta->vacunasAplicadas()->create([
            'vacuna_id' => Vacuna::whereNull('usuario_id')
                ->where('nombre', 'Antirrábica')
                ->where('especie', Especie::Perro)
                ->value('id'),
            'fecha' => now()->subYear()->addDays(10)->toDateString(),
            'proxima_dosis' => now()->addDays(10)->toDateString(),
            'dosis_nro' => 3,
            'marca' => 'Nobivac',
            'lote' => 'L4523',
            'veterinaria_id' => $veterinaria->id,
            'veterinario_id' => $veterinario->id,
        ]);

        $greta->vacunasAplicadas()->create([
            'vacuna_id' => Vacuna::whereNull('usuario_id')
                ->where('nombre', 'Quíntuple')
                ->value('id'),
            'fecha' => now()->subMonths(8)->toDateString(),
            'proxima_dosis' => now()->addMonths(4)->toDateString(),
            'dosis_nro' => 2,
        ]);

        // Curva de peso: nueve meses de datos, con dos tomados en la
        // veterinaria para que el gráfico muestre los dos tipos de punto.
        $pesos = [
            ['-9 months', 15.8, OrigenPeso::Casa],
            ['-8 months', 16.4, OrigenPeso::Casa],
            ['-7 months', 16.9, OrigenPeso::Casa],
            ['-6 months', 17.6, OrigenPeso::Veterinaria],
            ['-4 months', 18.1, OrigenPeso::Casa],
            ['-3 months', 18.6, OrigenPeso::Casa],
            ['-2 months', 19.2, OrigenPeso::Veterinaria],
            ['-1 month', 18.8, OrigenPeso::Casa],
            ['-5 days', 18.4, OrigenPeso::Casa],
        ];

        foreach ($pesos as [$cuando, $kilos, $origen]) {
            $greta->pesos()->create([
                'fecha' => now()->modify($cuando)->toDateString(),
                'peso_kg' => $kilos,
                'origen' => $origen,
            ]);
        }

        // Dos dietas: la anterior se cierra sola al iniciar la nueva.
        $dietas = app(DietaService::class);

        $dietas->iniciar($greta, [
            'alimento_id' => Alimento::whereNull('usuario_id')
                ->where('nombre', 'Medium Adult')
                ->value('id'),
            'fecha_inicio' => now()->subMonths(9)->toDateString(),
            'racion_diaria_g' => 320,
            'tomas_por_dia' => 2,
        ]);

        $dietas->iniciar($greta, [
            'alimento_id' => Alimento::whereNull('usuario_id')
                ->where('nombre', 'Gastrointestinal')
                ->value('id'),
            'fecha_inicio' => now()->subDay()->toDateString(),
            'racion_diaria_g' => 280,
            'tomas_por_dia' => 3,
            'motivo' => 'Indicada tras la gastroenteritis',
            'prescripta' => true,
            'veterinario_id' => $veterinario->id,
        ]);

        // Tres ciclos de celo: con eso la estimación usa el promedio real y no
        // el valor de referencia. Es el criterio de la fase 6.
        foreach (['-21 months', '-14 months', '-4 months'] as $cuando) {
            $inicio = now()->modify($cuando);

            $greta->ciclosCelo()->create([
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $inicio->copy()->addDays(17)->toDateString(),
                'intensidad' => IntensidadCelo::Normal,
            ]);
        }

        // Notas del diario: la mitad cotidiana de la línea de tiempo, que es la
        // que hace que se lea como la vida de la mascota y no como una planilla.
        $notas = [
            ['-4 days', CategoriaEntrada::Sintoma, 'Vomitó dos veces',
                'Vomitó a la mañana y otra vez al mediodía. Después tomó agua y comió poco.',
                Animo::Bajo],
            ['-2 days', CategoriaEntrada::General, null,
                'Mejor. Comió toda la ración y salió a caminar sin problema.', Animo::Normal],
            ['-15 days', CategoriaEntrada::Hito, 'Se subió sola al auto',
                'Primera vez que sube sola, sin que la alcemos.', Animo::Excelente],
            ['-1 month', CategoriaEntrada::Higiene, 'Baño y uñas',
                'La bañamos en casa y le cortamos las uñas. Se portó bien.', null],
            ['-2 months', CategoriaEntrada::Paseo, null,
                'Fuimos a la costanera. Corrió como media hora y volvió agotada.',
                Animo::Bueno],
        ];

        foreach ($notas as [$cuando, $categoria, $titulo, $contenido, $animo]) {
            $greta->entradasDiario()->create([
                'fecha' => now()->modify($cuando)->toDateString(),
                'titulo' => $titulo,
                'contenido' => $contenido,
                'categoria' => $categoria,
                'animo' => $animo,
            ]);
        }

        $greta->desparasitaciones()->create([
            'medicamento_id' => Medicamento::whereNull('usuario_id')
                ->where('nombre_comercial', 'Drontal Plus')
                ->value('id'),
            'tipo' => TipoDesparasitacion::Interna,
            'fecha' => now()->subMonths(3)->toDateString(),
            'proxima_fecha' => now()->addDays(3)->toDateString(),
            'dosis' => '1 comprimido',
            'peso_al_momento' => 18.4,
        ]);
    }
}
