<?php

namespace Database\Seeders;

use App\Enums\Animo;
use App\Enums\CategoriaEntrada;
use App\Enums\Especie;
use App\Enums\IntensidadCelo;
use App\Enums\LugarBano;
use App\Enums\OrigenPeso;
use App\Enums\TipoAdjunto;
use App\Enums\TipoDesparasitacion;
use App\Enums\TipoVisita;
use App\Enums\ViaAdministracion;
use App\Models\Alimento;
use App\Models\Mascota;
use App\Models\Medicamento;
use App\Models\User;
use App\Models\Vacuna;
use App\Models\Veterinaria;
use App\Models\Veterinario;
use App\Models\Visita;
use App\Services\ArchivoService;
use App\Services\DietaService;
use App\Services\GeneradorTomasService;
use App\Services\ImagenService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\Ilustraciones;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /** Pelajes de los dibujos. */
    private const CARAMELO = [196, 140, 84];

    private const GRIS = [150, 150, 156];

    private CarbonImmutable $hoy;

    private Ilustraciones $dibujos;

    /**
     * Dos mascotas con un año de historia, para recorrer la app con datos: Greta,
     * perra entera, con todo (una urgencia de ayer y su medicación de hoy, celos,
     * dieta, curva de peso, papeles); y Simón, gato castrado, para ver el módulo
     * de celo oculto.
     *
     * Es la demo de huella.pablomandile.com.ar (`huella:demo-reset`, todas las
     * noches) y lo que ve quien clona el repo. Por eso:
     *
     * - **Sin factories.** En el server `composer install --no-dev` deja afuera
     *   a Faker, y una factory revienta en el primer reset aunque los tests pasen.
     * - **Fechas relativas a hoy.** La demo se resiembra cada noche y siempre
     *   tiene que verse al día: una toma para hoy, una vacuna por vencer.
     * - **Sin `WithoutModelEvents`.** Los observers escriben el pivote de
     *   propietario y los recordatorios: sin ellos no se ve ninguna mascota.
     * - **Las imágenes pasan por `ImagenService` y `ArchivoService`**, como una
     *   subida: WebP, miniaturas y disco privado, el mismo camino que la gente.
     */
    public function run(): void
    {
        /*
         * Un usuario de demo en la productiva es una cuenta real que nadie creó,
         * mezclada con las de verdad. Corta con una excepción y no con un aviso:
         * un mensaje en la consola de un deploy no lo lee nadie. Solo pasa en
         * producción si es la demo (APP_DEMO), que es justamente su lugar.
         */
        if (app()->isProduction() && ! config('demo.enabled')) {
            throw new RuntimeException(
                'DemoSeeder no corre en producción. Usá --class=CatalogosSeeder.',
            );
        }

        $this->hoy = CarbonImmutable::today();
        $this->dibujos = new Ilustraciones;

        try {
            $this->sembrar();
        } finally {
            $this->dibujos->limpiar();
        }
    }

    private function sembrar(): void
    {
        $usuario = User::create([
            'name' => 'Cuenta demo',
            'email' => config('demo.email'),
            'password' => config('demo.password'),
            'zona_horaria' => 'America/Argentina/Buenos_Aires',
        ]);
        $usuario->forceFill(['email_verified_at' => now()])->save();
        $usuario->aceptarTerminos();

        [$veterinaria, $veterinario] = $this->agenda($usuario);

        $greta = $this->mascota($usuario, [
            'nombre' => 'Greta',
            'especie' => Especie::Perro,
            'raza' => 'Mestiza',
            'sexo' => 'hembra',
            'fecha_nacimiento' => $this->dia('-3 years -2 months'),
            'fecha_nacimiento_estimada' => true,
            'fecha_adopcion' => $this->dia('-3 years'),
            'color' => 'Caramelo',
            'tipo_pelaje' => 'corto',
            'senias_particulares' => 'Mancha blanca en el pecho',
            'descripcion' => 'Adoptada en un refugio de Morón. Le encanta la costanera.',
            'microchip' => '032100000451207',
            'libreta_sanitaria' => 'LS-004512',
            'castrado' => false,
            // El papel vence antes que la dosis: dos avisos distintos, como en la vida real.
            'rabia_vencimiento' => $this->dia('+45 days'),
        ]);

        $simon = $this->mascota($usuario, [
            'nombre' => 'Simón',
            'especie' => Especie::Gato,
            'raza' => 'Común europeo',
            'sexo' => 'macho',
            'fecha_nacimiento' => $this->dia('-6 years -5 months'),
            'fecha_adopcion' => $this->dia('-6 years -3 months'),
            'color' => 'Gris atigrado',
            'tipo_pelaje' => 'corto',
            'castrado' => true,
            'fecha_castracion' => $this->dia('-5 years -9 months'),
        ]);

        $greta->alergias()->create([
            'tipo' => 'alimentaria',
            'agente' => 'Pollo',
            'severidad' => 'moderada',
            'fecha_deteccion' => $this->dia('-2 years'),
            'sintomas' => 'Vómitos y picazón en las orejas',
        ]);

        $this->historiaDeGreta($greta, $veterinaria, $veterinario);
        $this->historiaDeSimon($simon, $veterinaria, $veterinario);

        $this->fotos($greta, 'perro', self::CARAMELO, [
            ['cachorro', '-3 years', 'El primer día en casa'],
            ['playa', '-1 year -4 months', 'Primera vez en el mar'],
            ['cumple', $this->ultimoCumple($greta), '¡Tres años!'],
            ['plaza', '-10 days', null],
        ]);

        $this->fotos($simon, 'gato', self::GRIS, [
            ['cachorro', '-6 years -3 months', 'Recién llegado'],
            ['cumple', $this->ultimoCumple($simon), null],
            ['casa', '-3 months', 'Su lugar en el sillón'],
        ]);

        $this->documentos($greta);
    }

    /** @return array{0: Veterinaria, 1: Veterinario} */
    private function agenda(User $usuario): array
    {
        // Agenda propia del usuario: veterinarias y veterinarios nunca tienen semilla.
        $veterinaria = new Veterinaria([
            'nombre' => 'Veterinaria del Parque',
            'direccion' => 'Av. Rivadavia 5200',
            'localidad' => 'Caballito',
            'telefono' => '11-4903-0000',
            'urgencias_24h' => true,
            'horarios' => 'Lunes a viernes de 9 a 20, sábados de 9 a 13',
        ]);
        $veterinaria->asignarPropietario($usuario);
        $veterinaria->save();

        $veterinario = new Veterinario([
            'veterinaria_id' => $veterinaria->id,
            'nombre' => 'Laura Giménez',
            'matricula' => 'MP 12345',
            'especialidad' => 'Clínica general',
        ]);
        $veterinario->asignarPropietario($usuario);
        $veterinario->save();

        return [$veterinaria, $veterinario];
    }

    /** @param  array<string, mixed>  $datos */
    private function mascota(User $usuario, array $datos): Mascota
    {
        // `usuario_id` no es fillable: se asigna a mano, como en MascotaController.
        $mascota = new Mascota($datos);
        $mascota->usuario_id = $usuario->id;
        $mascota->save();

        return $mascota;
    }

    private function historiaDeGreta(Mascota $greta, Veterinaria $veterinaria, Veterinario $veterinario): void
    {
        $donde = ['veterinaria_id' => $veterinaria->id, 'veterinario_id' => $veterinario->id];

        $greta->visitas()->create($donde + [
            'fecha_hora' => $this->hoy->modify('-11 months')->setTime(10, 30),
            'tipo' => TipoVisita::Vacunacion,
            'motivo' => 'Vacunación anual',
            'diagnostico' => 'Buen estado general.',
            'indicaciones' => 'Antirrábica y quíntuple aplicadas.',
            'temperatura' => 38.6,
            'costo' => 38000,
        ]);

        $greta->visitas()->create($donde + [
            'fecha_hora' => $this->hoy->modify('-5 months')->setTime(17, 0),
            'tipo' => TipoVisita::Control,
            'motivo' => 'Picazón en las orejas',
            'diagnostico' => 'Otitis externa leve. Se sospecha relación con la alergia al pollo.',
            'indicaciones' => 'Limpieza con Pervinal Ótico 7 días. Revisar el alimento.',
            'temperatura' => 38.8,
            'costo' => 29000,
        ]);

        // La urgencia de ayer: lo que hace que «Medicación de hoy» tenga tomas.
        $urgencia = $greta->visitas()->create($donde + [
            'fecha_hora' => $this->hoy->subDay()->setTime(18, 30),
            'tipo' => TipoVisita::Urgencia,
            'motivo' => 'Gastroenteritis',
            'diagnostico' => 'Cuadro gastroentérico agudo, sin signos de deshidratación.',
            'indicaciones' => 'Dieta blanda 3 días. Volver si sigue con vómitos.',
            'temperatura' => 39.2,
            'costo' => 42000,
            'proximo_control' => $this->dia('+7 days'),
        ]);

        $tomas = app(GeneradorTomasService::class);

        foreach ([
            ['Cefalexina', '1 comprimido', 8, 7, '08:00', 'Dar con comida'],
            ['Metronidazol', '2,5 ml', 12, 5, '09:00', null],
        ] as [$droga, $dosis, $cada, $dias, $hora, $notas]) {
            $tratamiento = $greta->tratamientos()->create([
                'visita_id' => $urgencia->id,
                'medicamento_id' => $this->medicamento($droga),
                'medicamento_libre' => $droga,
                'dosis' => $dosis,
                'via' => ViaAdministracion::Oral,
                'frecuencia_horas' => $cada,
                'duracion_dias' => $dias,
                'fecha_inicio' => $this->dia('-1 day'),
                'hora_primera_toma' => $hora,
                'notas' => $notas,
            ]);

            $tratamiento->setRelation('mascota', $greta);
            $tomas->generar($tratamiento);
        }

        // El hemograma de la urgencia, como PDF adjunto a la visita.
        $this->adjuntarPdf(
            $urgencia,
            TipoAdjunto::Analisis,
            'hemograma.pdf',
            'Hemograma',
            $this->htmlAnalisis('Greta'),
            "mascotas/{$greta->id}/adjuntos",
        );

        // Preventivo: los observers crean sus recordatorios al guardar. La
        // antirrábica queda por vencer, para ver el semáforo en ámbar.
        $greta->vacunasAplicadas()->create($donde + [
            'vacuna_id' => $this->vacuna('Antirrábica', Especie::Perro),
            'fecha' => $this->dia('-11 months'),
            'proxima_dosis' => $this->dia('+1 month'),
            'dosis_nro' => 3,
            'marca' => 'Nobivac',
            'lote' => 'L4523',
        ]);

        $greta->vacunasAplicadas()->create($donde + [
            'vacuna_id' => $this->vacuna('Quíntuple', Especie::Perro),
            'fecha' => $this->dia('-11 months'),
            'proxima_dosis' => $this->dia('+1 month'),
            'dosis_nro' => 3,
            'marca' => 'Vanguard',
            'lote' => 'Q8812',
        ]);

        // Curva de peso: un año, con tres de la balanza de la veterinaria para
        // que el gráfico muestre los dos tipos de punto.
        foreach ([
            ['-12 months', 16.1, OrigenPeso::Casa],
            ['-11 months', 16.4, OrigenPeso::Veterinaria],
            ['-9 months', 16.9, OrigenPeso::Casa],
            ['-7 months', 17.6, OrigenPeso::Casa],
            ['-5 months', 18.3, OrigenPeso::Veterinaria],
            ['-4 months', 18.6, OrigenPeso::Casa],
            ['-2 months', 19.2, OrigenPeso::Casa],
            ['-1 month', 18.8, OrigenPeso::Casa],
            ['-1 day', 18.2, OrigenPeso::Veterinaria],
        ] as [$cuando, $kilos, $origen]) {
            $greta->pesos()->create(['fecha' => $this->dia($cuando), 'peso_kg' => $kilos, 'origen' => $origen]);
        }

        // Dos dietas: la anterior se cierra sola al iniciar la nueva (regla 1).
        $dietas = app(DietaService::class);

        $dietas->iniciar($greta, [
            'alimento_id' => $this->alimento('Hypoallergenic'),
            'fecha_inicio' => $this->dia('-5 months'),
            'racion_diaria_g' => 300,
            'tomas_por_dia' => 2,
            'motivo' => 'Sin pollo, por la otitis',
            'prescripta' => true,
            'veterinario_id' => $veterinario->id,
        ]);

        $dietas->iniciar($greta, [
            'alimento_id' => $this->alimento('Gastrointestinal'),
            'fecha_inicio' => $this->dia('-1 day'),
            'racion_diaria_g' => 280,
            'tomas_por_dia' => 3,
            'motivo' => 'Indicada tras la gastroenteritis',
            'prescripta' => true,
            'veterinario_id' => $veterinario->id,
        ]);

        // Tres ciclos de celo: con eso la estimación usa el promedio real y no
        // el valor de referencia.
        foreach (['-20 months', '-13 months', '-6 months'] as $cuando) {
            $inicio = $this->hoy->modify($cuando);

            $greta->ciclosCelo()->create([
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $inicio->addDays(17)->toDateString(),
                'intensidad' => IntensidadCelo::Normal,
            ]);
        }

        $greta->desparasitaciones()->create([
            'medicamento_id' => $this->medicamento('Drontal Plus'),
            'tipo' => TipoDesparasitacion::Interna,
            'fecha' => $this->dia('-3 months'),
            'proxima_fecha' => $this->dia('+3 days'),
            'dosis' => '1 comprimido',
            'peso_al_momento' => 18.6,
        ]);

        $greta->desparasitaciones()->create([
            'medicamento_id' => $this->medicamento('NexGard Spectra'),
            'tipo' => TipoDesparasitacion::Externa,
            'fecha' => $this->dia('-20 days'),
            'proxima_fecha' => $this->dia('+10 days'),
            'dosis' => '1 comprimido masticable',
            'peso_al_momento' => 18.8,
        ]);

        foreach ([['-1 month', LugarBano::Casa, 'Con champú de avena'], ['-3 months', LugarBano::Salon, 'Peluquería canina del barrio']] as [$cuando, $lugar, $notas]) {
            $greta->banos()->create(['fecha' => $this->dia($cuando), 'lugar' => $lugar, 'notas' => $notas]);
        }

        // La mitad cotidiana del diario: es la que hace que se lea como la vida
        // de la mascota y no como una planilla.
        foreach ([
            ['-1 day', CategoriaEntrada::Sintoma, 'Vomitó dos veces',
                'Vomitó a la mañana y otra vez al mediodía. A la tarde la llevamos a la guardia.', Animo::Bajo],
            ['-15 days', CategoriaEntrada::Hito, 'Se subió sola al auto',
                'Primera vez que sube sola, sin que la alcemos.', Animo::Excelente],
            ['-2 months', CategoriaEntrada::Paseo, null,
                'Fuimos a la costanera. Corrió como media hora y volvió agotada.', Animo::Bueno],
            ['-4 months', CategoriaEntrada::Entrenamiento, 'Aprendió «quieta»',
                'Aguanta diez segundos sentada aunque pase otro perro.', Animo::Bueno],
            ['-1 year -4 months', CategoriaEntrada::Viaje, 'Vacaciones en Villa Gesell',
                'Primera vez en la playa. Le tuvo miedo a las olas el primer día.', Animo::Excelente],
        ] as [$cuando, $categoria, $titulo, $contenido, $animo]) {
            $greta->entradasDiario()->create([
                'fecha' => $this->dia($cuando),
                'titulo' => $titulo,
                'contenido' => $contenido,
                'categoria' => $categoria,
                'animo' => $animo,
            ]);
        }
    }

    private function historiaDeSimon(Mascota $simon, Veterinaria $veterinaria, Veterinario $veterinario): void
    {
        $donde = ['veterinaria_id' => $veterinaria->id, 'veterinario_id' => $veterinario->id];

        $simon->visitas()->create($donde + [
            'fecha_hora' => $this->hoy->modify('-4 months')->setTime(11, 0),
            'tipo' => TipoVisita::Rutina,
            'motivo' => 'Control anual',
            'diagnostico' => 'Sano. Sarro leve.',
            'indicaciones' => 'Evaluar limpieza dental el año que viene.',
            'temperatura' => 38.4,
            'costo' => 30000,
        ]);

        $simon->vacunasAplicadas()->create($donde + [
            'vacuna_id' => $this->vacuna('Triple felina', Especie::Gato),
            'fecha' => $this->dia('-4 months'),
            'proxima_dosis' => $this->dia('+8 months'),
            'dosis_nro' => 6,
        ]);

        $simon->desparasitaciones()->create([
            'medicamento_id' => $this->medicamento('Milbemax'),
            'tipo' => TipoDesparasitacion::Interna,
            'fecha' => $this->dia('-2 months'),
            'proxima_fecha' => $this->dia('+1 month'),
            'dosis' => '1 comprimido',
            'peso_al_momento' => 5.1,
        ]);

        foreach ([['-1 year', 5.4], ['-8 months', 5.3], ['-4 months', 5.1], ['-1 month', 5.0]] as [$cuando, $kilos]) {
            $simon->pesos()->create(['fecha' => $this->dia($cuando), 'peso_kg' => $kilos, 'origen' => OrigenPeso::Casa]);
        }

        app(DietaService::class)->iniciar($simon, [
            'alimento_id' => $this->alimento('Sterilised'),
            'fecha_inicio' => $this->dia('-1 year'),
            'racion_diaria_g' => 60,
            'tomas_por_dia' => 2,
        ]);

        $simon->entradasDiario()->create([
            'fecha' => $this->dia('-6 days'),
            'titulo' => null,
            'contenido' => 'Pasó toda la tarde al sol en la ventana del living.',
            'categoria' => CategoriaEntrada::General,
            'animo' => Animo::Excelente,
        ]);
    }

    /**
     * @param  'perro'|'gato'  $especie
     * @param  array{0: int, 1: int, 2: int}  $pelaje
     * @param  list<array{0: 'plaza'|'playa'|'casa'|'cumple'|'cachorro', 1: string, 2: ?string}>  $fotos  la última es la de perfil
     */
    private function fotos(Mascota $mascota, string $especie, array $pelaje, array $fotos): void
    {
        $imagenes = app(ImagenService::class);

        foreach ($fotos as $i => [$escena, $cuando, $epigrafe]) {
            $archivo = $this->subida(
                $this->dibujos->retrato($especie, $pelaje, $escena, $mascota->id * 10 + $i),
                "{$escena}.png",
                'image/png',
            );

            $rutas = $imagenes->guardar($archivo, "mascotas/{$mascota->id}");

            $mascota->fotos()->create($rutas + [
                'fecha' => $this->dia($cuando),
                'epigrafe' => $epigrafe,
            ]);

            if ($i === array_key_last($fotos)) {
                $mascota->forceFill(['foto_perfil' => $rutas['ruta']])->save();
            }
        }
    }

    /** La libreta (dos hojas escaneadas) y el certificado de rabia. */
    private function documentos(Mascota $greta): void
    {
        $archivos = app(ArchivoService::class);
        $f = fn (string $cuando) => $this->hoy->modify($cuando)->format('d/m/Y');

        $hojas = [
            ['Vacunas', [
                [$f('-2 years -11 months'), 'Quíntuple', 'Q1204', $f('-2 years -10 months')],
                [$f('-2 years -10 months'), 'Quíntuple', 'Q1377', $f('-1 year -11 months')],
                [$f('-2 years -10 months'), 'Antirrábica', 'L2210', $f('-1 year -11 months')],
                [$f('-1 year -11 months'), 'Quíntuple', 'Q5530', $f('-11 months')],
                [$f('-1 year -11 months'), 'Antirrábica', 'L3381', $f('-11 months')],
                [$f('-11 months'), 'Quíntuple', 'Q8812', $f('+1 month')],
                [$f('-11 months'), 'Antirrábica', 'L4523', $f('+1 month')],
            ]],
            ['Desparasitaciones', [
                [$f('-9 months'), 'Drontal Plus', '—', $f('-6 months')],
                [$f('-6 months'), 'Drontal Plus', '—', $f('-3 months')],
                [$f('-3 months'), 'Drontal Plus', '—', $f('+3 days')],
            ]],
        ];

        foreach ($hojas as $i => [$titulo, $filas]) {
            $archivos->adjuntar(
                $greta->adjuntos(),
                $this->subida($this->dibujos->hojaLibreta('Greta', $titulo, $filas, $i + 1), 'libreta-hoja-'.($i + 1).'.jpg', 'image/jpeg'),
                TipoAdjunto::LibretaSanitaria,
                null,
                "mascotas/{$greta->id}/documentos",
            );
        }

        $this->adjuntarPdf(
            $greta,
            TipoAdjunto::CertificadoRabia,
            'certificado-antirrabico.pdf',
            null,
            $this->htmlCertificado('Greta', $f('-11 months'), $f('+45 days')),
            "mascotas/{$greta->id}/documentos",
        );
    }

    private function adjuntarPdf(Mascota|Visita $duenio, TipoAdjunto $tipo, string $nombre, ?string $descripcion, string $html, string $directorio): void
    {
        $ruta = tempnam(sys_get_temp_dir(), 'huella-demo-');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal.');
        }

        try {
            file_put_contents($ruta, Pdf::loadHTML($html)->setPaper('a4')->output());

            app(ArchivoService::class)->adjuntar(
                $duenio->adjuntos(),
                $this->subida($ruta, $nombre, 'application/pdf'),
                $tipo,
                $descripcion,
                $directorio,
            );
        } finally {
            if (is_file($ruta)) {
                @unlink($ruta);
            }
        }
    }

    private function htmlCertificado(string $mascota, string $aplicada, string $vence): string
    {
        return <<<HTML
            <html><body style="font-family: DejaVu Sans, sans-serif; color: #223; padding: 40px">
            <h1 style="font-size: 22px; text-align: center; letter-spacing: 2px">CERTIFICADO DE VACUNACIÓN ANTIRRÁBICA</h1>
            <p style="text-align: center; color: #667">Documento de ejemplo — demo de Huella</p>
            <table style="margin-top: 40px; width: 100%; font-size: 14px; border-collapse: collapse">
              <tr><td style="padding: 8px; width: 40%; color: #667">Nombre</td><td style="padding: 8px">{$mascota}</td></tr>
              <tr><td style="padding: 8px; color: #667">Especie</td><td style="padding: 8px">Canino</td></tr>
              <tr><td style="padding: 8px; color: #667">Microchip</td><td style="padding: 8px">032100000451207</td></tr>
              <tr><td style="padding: 8px; color: #667">Vacuna</td><td style="padding: 8px">Nobivac Rabies — lote L4523</td></tr>
              <tr><td style="padding: 8px; color: #667">Fecha de aplicación</td><td style="padding: 8px">{$aplicada}</td></tr>
              <tr><td style="padding: 8px; color: #667">Válido hasta</td><td style="padding: 8px"><strong>{$vence}</strong></td></tr>
            </table>
            <p style="margin-top: 80px; text-align: right">Dra. Laura Giménez — MP 12345<br><span style="color: #667">Veterinaria del Parque</span></p>
            </body></html>
            HTML;
    }

    private function htmlAnalisis(string $mascota): string
    {
        $filas = '';
        foreach ([
            ['Hematocrito', '46 %', '37 – 55'],
            ['Hemoglobina', '15,8 g/dl', '12 – 18'],
            ['Glóbulos blancos', '17.400 /µl', '6.000 – 17.000'],
            ['Neutrófilos', '81 %', '60 – 77'],
            ['Plaquetas', '310.000 /µl', '200.000 – 500.000'],
            ['Urea', '38 mg/dl', '20 – 50'],
            ['Creatinina', '1,1 mg/dl', '0,5 – 1,5'],
        ] as [$analito, $valor, $referencia]) {
            $filas .= "<tr><td style='padding:6px;border-bottom:1px solid #ddd'>{$analito}</td><td style='padding:6px;border-bottom:1px solid #ddd'>{$valor}</td><td style='padding:6px;border-bottom:1px solid #ddd;color:#667'>{$referencia}</td></tr>";
        }

        return <<<HTML
            <html><body style="font-family: DejaVu Sans, sans-serif; color: #223; padding: 30px">
            <h1 style="font-size: 20px">Laboratorio Veterinario Caballito</h1>
            <p style="color: #667">Documento de ejemplo — demo de Huella</p>
            <p>Paciente: <strong>{$mascota}</strong> · Canino · Hembra</p>
            <h2 style="font-size: 16px; margin-top: 24px">Hemograma y química básica</h2>
            <table style="width: 100%; font-size: 13px; border-collapse: collapse">
              <tr><th style="text-align:left;padding:6px">Determinación</th><th style="text-align:left;padding:6px">Resultado</th><th style="text-align:left;padding:6px">Referencia</th></tr>
              {$filas}
            </table>
            </body></html>
            HTML;
    }

    /** Un archivo temporal presentado como lo que llega de un formulario. */
    private function subida(string $ruta, string $nombre, string $mime): UploadedFile
    {
        return new UploadedFile($ruta, $nombre, $mime, null, true);
    }

    private function ultimoCumple(Mascota $mascota): string
    {
        $nacimiento = CarbonImmutable::parse($mascota->fecha_nacimiento);
        $cumple = $nacimiento->setYear($this->hoy->year);

        if ($cumple->isAfter($this->hoy)) {
            $cumple = $cumple->subYear();
        }

        return $cumple->toDateString();
    }

    /** Una fecha relativa a hoy («-3 months»), o una fecha ya armada. */
    private function dia(string $cuando): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $cuando)
            ? $cuando
            : $this->hoy->modify($cuando)->toDateString();
    }

    private function vacuna(string $nombre, Especie $especie): int
    {
        return (int) (Vacuna::whereNull('usuario_id')->where('nombre', $nombre)->where('especie', $especie)->value('id')
            ?? throw new RuntimeException("Falta la vacuna «{$nombre}» en la semilla: corré CatalogosSeeder antes."));
    }

    private function medicamento(string $nombre): ?int
    {
        return Medicamento::whereNull('usuario_id')->where('nombre_comercial', $nombre)->value('id');
    }

    private function alimento(string $nombre): ?int
    {
        return Alimento::whereNull('usuario_id')->where('nombre', $nombre)->value('id');
    }
}
