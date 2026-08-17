<?php

use App\Http\Controllers\AdjuntoController;
use App\Http\Controllers\AlergiaController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Catalogos\AlimentoController;
use App\Http\Controllers\Catalogos\MedicamentoController;
use App\Http\Controllers\Catalogos\PanelCatalogosController;
use App\Http\Controllers\Catalogos\VacunaController;
use App\Http\Controllers\Catalogos\VeterinariaController;
use App\Http\Controllers\Catalogos\VeterinarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiarioController;
use App\Http\Controllers\ExportacionController;
use App\Http\Controllers\FotoMascotaController;
use App\Http\Controllers\MascotaActivaController;
use App\Http\Controllers\MascotaController;
use App\Http\Controllers\MedicacionController;
use App\Http\Controllers\PreventivoController;
use App\Http\Controllers\RecordatorioController;
use App\Http\Controllers\SeguimientoController;
use App\Http\Controllers\TratamientoController;
use App\Http\Controllers\VisitaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Respaldo que sirve el service worker cuando no hay conexión. Es una vista
// suelta, no Inertia: tiene que poder mostrarse sin los assets compilados.
Route::view('offline', 'offline')->name('offline');

/*
 * Ingreso con Google. Van en inglés y bajo /auth como el resto de las rutas de
 * autenticación, que las publica Fortify; el español es para el dominio.
 *
 * `guest`: alguien con la sesión abierta que llega acá ya está adentro, y
 * rehacer el flujo solo puede terminar cambiándole la cuenta sin querer.
 */
Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleController::class, 'redirigir'])
        ->name('google.redirect');

    Route::get('auth/google/callback', [GoogleController::class, 'volver'])
        ->name('google.callback');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('mascotas', MascotaController::class)
        ->parameters(['mascotas' => 'mascota']);

    // Imágenes servidas tras verificar propiedad; nunca por URL pública.
    Route::get('mascotas/{mascota}/foto-perfil', [FotoMascotaController::class, 'fotoPerfil'])
        ->name('mascotas.foto-perfil');
    Route::get('mascotas/{mascota}/fotos/{foto}', [FotoMascotaController::class, 'mostrar'])
        ->name('mascotas.fotos.mostrar');

    Route::post('mascotas/{mascota}/fotos', [FotoMascotaController::class, 'store'])
        ->name('mascotas.fotos.store');
    Route::delete('mascotas/{mascota}/fotos/{foto}', [FotoMascotaController::class, 'destroy'])
        ->name('mascotas.fotos.destroy');

    Route::post('mascotas/{mascota}/alergias', [AlergiaController::class, 'store'])
        ->name('mascotas.alergias.store');
    Route::delete('mascotas/{mascota}/alergias/{alergia}', [AlergiaController::class, 'destroy'])
        ->name('mascotas.alergias.destroy');

    Route::patch('mascota-activa/{mascota}', [MascotaActivaController::class, 'update'])
        ->name('mascota-activa.update');

    /*
     * Núcleo clínico. Las visitas cuelgan de la mascota; los tratamientos
     * también, porque uno de rutina puede no venir de ninguna consulta.
     */
    Route::resource('mascotas.visitas', VisitaController::class)
        ->parameters(['mascotas' => 'mascota', 'visitas' => 'visita']);

    Route::post('mascotas/{mascota}/tratamientos', [TratamientoController::class, 'store'])
        ->name('mascotas.tratamientos.store');
    Route::put('mascotas/{mascota}/tratamientos/{tratamiento}', [TratamientoController::class, 'update'])
        ->name('mascotas.tratamientos.update');
    Route::delete('mascotas/{mascota}/tratamientos/{tratamiento}', [TratamientoController::class, 'destroy'])
        ->name('mascotas.tratamientos.destroy');

    Route::post('mascotas/{mascota}/visitas/{visita}/adjuntos', [AdjuntoController::class, 'store'])
        ->name('mascotas.visitas.adjuntos.store');

    /*
     * Los adjuntos se sirven y se borran por su id, sin la mascota en la ruta:
     * la propiedad la resuelve la Policy subiendo por la relación polimórfica,
     * y así vale igual para lo que cuelgue de una visita, un tratamiento o —en
     * las fases que vienen— una vacuna.
     */
    Route::get('adjuntos/{adjunto}', [AdjuntoController::class, 'mostrar'])
        ->name('adjuntos.mostrar');
    Route::delete('adjuntos/{adjunto}', [AdjuntoController::class, 'destroy'])
        ->name('adjuntos.destroy');

    // Lo que hay que dar hoy, de todas las mascotas juntas.
    Route::get('medicacion', [MedicacionController::class, 'index'])->name('medicacion.index');
    Route::patch('medicacion/{toma}', [MedicacionController::class, 'update'])
        ->name('medicacion.update');

    /*
     * Preventivo: vacunas y desparasitaciones van juntas porque para el usuario
     * son lo mismo —cosas que se aplican cada tanto y hay que volver a dar—, y
     * las dos generan su recordatorio por observer.
     */
    Route::get('mascotas/{mascota}/preventivo', [PreventivoController::class, 'index'])
        ->name('mascotas.preventivo.index');

    Route::post('mascotas/{mascota}/vacunas', [PreventivoController::class, 'guardarVacuna'])
        ->name('mascotas.vacunas.store');
    Route::put('mascotas/{mascota}/vacunas/{aplicacion}', [PreventivoController::class, 'actualizarVacuna'])
        ->name('mascotas.vacunas.update');
    Route::delete('mascotas/{mascota}/vacunas/{aplicacion}', [PreventivoController::class, 'eliminarVacuna'])
        ->name('mascotas.vacunas.destroy');

    Route::post('mascotas/{mascota}/desparasitaciones', [PreventivoController::class, 'guardarDesparasitacion'])
        ->name('mascotas.desparasitaciones.store');
    Route::put('mascotas/{mascota}/desparasitaciones/{desparasitacion}', [PreventivoController::class, 'actualizarDesparasitacion'])
        ->name('mascotas.desparasitaciones.update');
    Route::delete('mascotas/{mascota}/desparasitaciones/{desparasitacion}', [PreventivoController::class, 'eliminarDesparasitacion'])
        ->name('mascotas.desparasitaciones.destroy');

    /*
     * Seguimiento: peso, dieta y celo. Van juntos porque son las tres cosas que
     * se leen en tendencia y no en un momento.
     */
    Route::get('mascotas/{mascota}/seguimiento', [SeguimientoController::class, 'index'])
        ->name('mascotas.seguimiento.index');

    Route::post('mascotas/{mascota}/pesos', [SeguimientoController::class, 'guardarPeso'])
        ->name('mascotas.pesos.store');
    Route::put('mascotas/{mascota}/pesos/{peso}', [SeguimientoController::class, 'actualizarPeso'])
        ->name('mascotas.pesos.update');
    Route::delete('mascotas/{mascota}/pesos/{peso}', [SeguimientoController::class, 'eliminarPeso'])
        ->name('mascotas.pesos.destroy');

    Route::post('mascotas/{mascota}/dietas', [SeguimientoController::class, 'guardarDieta'])
        ->name('mascotas.dietas.store');
    Route::put('mascotas/{mascota}/dietas/{dieta}', [SeguimientoController::class, 'actualizarDieta'])
        ->name('mascotas.dietas.update');
    Route::delete('mascotas/{mascota}/dietas/{dieta}', [SeguimientoController::class, 'eliminarDieta'])
        ->name('mascotas.dietas.destroy');

    Route::post('mascotas/{mascota}/celos', [SeguimientoController::class, 'guardarCiclo'])
        ->name('mascotas.celos.store');
    Route::put('mascotas/{mascota}/celos/{ciclo}', [SeguimientoController::class, 'actualizarCiclo'])
        ->name('mascotas.celos.update');
    Route::delete('mascotas/{mascota}/celos/{ciclo}', [SeguimientoController::class, 'eliminarCiclo'])
        ->name('mascotas.celos.destroy');

    /*
     * El diario: la línea de tiempo unificada, que es la pantalla principal de
     * una mascota. `mas` contesta JSON porque el scroll infinito suma eventos a
     * la lista que ya está en pantalla, y una navegación Inertia la reemplazaría
     * entera perdiendo la posición del scroll.
     */
    Route::get('mascotas/{mascota}/diario', [DiarioController::class, 'index'])
        ->name('mascotas.diario.index');
    Route::get('mascotas/{mascota}/diario/mas', [DiarioController::class, 'mas'])
        ->name('mascotas.diario.mas');

    Route::post('mascotas/{mascota}/entradas', [DiarioController::class, 'store'])
        ->name('mascotas.entradas.store');
    Route::put('mascotas/{mascota}/entradas/{entrada}', [DiarioController::class, 'update'])
        ->name('mascotas.entradas.update');
    Route::delete('mascotas/{mascota}/entradas/{entrada}', [DiarioController::class, 'destroy'])
        ->name('mascotas.entradas.destroy');

    // Las dos salidas: el PDF para el veterinario y el JSON con todo lo cargado.
    Route::get('mascotas/{mascota}/historia-clinica', [ExportacionController::class, 'historiaClinica'])
        ->name('mascotas.historia-clinica');
    Route::get('mis-datos', [ExportacionController::class, 'datos'])
        ->name('exportacion.datos');

    // La bandeja: todo lo que hay que agendar, de todas las mascotas juntas.
    Route::get('recordatorios', [RecordatorioController::class, 'index'])
        ->name('recordatorios.index');
    Route::post('mascotas/{mascota}/recordatorios', [RecordatorioController::class, 'store'])
        ->name('mascotas.recordatorios.store');
    Route::put('recordatorios/{recordatorio}', [RecordatorioController::class, 'update'])
        ->name('recordatorios.update');
    Route::patch('recordatorios/{recordatorio}/resolver', [RecordatorioController::class, 'resolver'])
        ->name('recordatorios.resolver');
    Route::patch('recordatorios/{recordatorio}/reabrir', [RecordatorioController::class, 'reabrir'])
        ->name('recordatorios.reabrir');
    Route::delete('recordatorios/{recordatorio}', [RecordatorioController::class, 'destroy'])
        ->name('recordatorios.destroy');

    /*
     * Catálogos. No hay `create`, `show` ni `edit`: el alta y la edición se
     * resuelven en un sheet sobre el propio listado, que en el celular es una
     * pantalla menos y una navegación menos.
     *
     * `store` sirve dos cosas a la vez: el sheet del listado (Inertia) y el
     * combo de otro formulario que crea al vuelo (JSON). Ver CatalogoBaseController.
     */
    Route::prefix('catalogos')->name('catalogos.')->group(function () {
        Route::get('/', PanelCatalogosController::class)->name('index');

        $catalogos = [
            'veterinarias' => [VeterinariaController::class, 'veterinaria', false],
            'veterinarios' => [VeterinarioController::class, 'veterinario', false],
            'medicamentos' => [MedicamentoController::class, 'medicamento', true],
            'vacunas' => [VacunaController::class, 'vacuna', true],
            'alimentos' => [AlimentoController::class, 'alimento', true],
        ];

        foreach ($catalogos as $ruta => [$controlador, $parametro, $tieneSemilla]) {
            Route::resource($ruta, $controlador)
                ->only(['index', 'store', 'update', 'destroy'])
                ->parameters([$ruta => $parametro]);

            // Solo los catálogos con semilla del sistema necesitan duplicar:
            // es la salida cuando un registro compartido no se puede editar.
            if ($tieneSemilla) {
                Route::post("{$ruta}/{{$parametro}}/duplicar", [$controlador, 'duplicar'])
                    ->name("{$ruta}.duplicar");
            }
        }
    });
});

require __DIR__.'/settings.php';
