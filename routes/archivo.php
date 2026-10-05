<?php

use App\Http\Controllers\Archivo\AcontecimientoController;
use App\Http\Controllers\Archivo\AporteController;
use App\Http\Controllers\Archivo\CapituloController;
use App\Http\Controllers\Archivo\EtiquetaController;
use App\Http\Controllers\Archivo\GestionController;
use App\Http\Controllers\Archivo\PublicoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Archivo histórico fotográfico (docs/ARCHIVO_HISTORICO.md)
|--------------------------------------------------------------------------
| Público sin cuenta · aportes de cualquier cuenta activa · gestión con archivo.*.
| Las Policies (ArchivoFoto/Acontecimiento/Capitulo) deciden sobre cada registro.
*/

Route::prefix('archivo')->name('archivo.')->group(function () {
    // Derivados de imagen: muchas por página, se cachean un año en el navegador.
    Route::get('/img/{foto}/{ancho}', [PublicoController::class, 'imagen'])
        ->whereNumber('foto')->whereIn('ancho', ['400', '800', '1200', '2048'])
        ->middleware('throttle:600,1')->name('imagen');

    Route::middleware('throttle:90,1')->group(function () {
        Route::get('/', [PublicoController::class, 'index'])->name('index');
        Route::get('/historia', [PublicoController::class, 'historia'])->name('historia');
        Route::get('/buscar', [PublicoController::class, 'buscar'])->name('buscar');
        Route::get('/personas', [PublicoController::class, 'personas'])->name('personas');
        Route::get('/capitulos/{slug}', [PublicoController::class, 'capitulo'])->name('capitulo');
        Route::get('/eventos/{slug}', [PublicoController::class, 'acontecimiento'])->name('acontecimiento');
        Route::get('/fotos/{slug}', [PublicoController::class, 'foto'])->name('foto');
        Route::get('/{anio}', [PublicoController::class, 'anio'])->where('anio', '(19|20)[0-9]{2}')->name('anio');
    });

    Route::middleware('auth')->group(function () {
        // Aportes de la comunidad.
        Route::get('/aportar', [AporteController::class, 'create'])->name('aportar');
        Route::post('/aportes', [AporteController::class, 'subir'])->middleware('throttle:120,1')->name('aportes.subir');
        Route::post('/aportes/lote', [AporteController::class, 'lote'])->middleware('throttle:30,1')->name('aportes.lote');
        Route::get('/mis-aportes', [AporteController::class, 'index'])->name('aportes.index');
        Route::get('/mis-aportes/{foto}', [AporteController::class, 'show'])->whereNumber('foto')->name('aportes.show');
        Route::put('/mis-aportes/{foto}', [AporteController::class, 'update'])->whereNumber('foto')->name('aportes.update');
        Route::post('/mis-aportes/{foto}/enviar', [AporteController::class, 'enviar'])->whereNumber('foto')->name('aportes.enviar');
        Route::delete('/mis-aportes/{foto}', [AporteController::class, 'destroy'])->whereNumber('foto')->name('aportes.destroy');
        Route::get('/original/{foto}', [AporteController::class, 'original'])->whereNumber('foto')->name('original');

        // Gestión del equipo.
        Route::middleware('permiso:archivo.view|archivo.manage|archivo.moderate')->prefix('gestion')->name('gestion.')->group(function () {
            Route::get('/', [GestionController::class, 'tablero'])->name('tablero');
            Route::get('/fotos', [GestionController::class, 'fotos'])->name('fotos');
            Route::get('/subir', [GestionController::class, 'subirForm'])->name('subir');
            Route::post('/fotos', [GestionController::class, 'subir'])->middleware('throttle:240,1')->name('fotos.store');
            Route::post('/fotos/lote', [GestionController::class, 'lote'])->name('fotos.lote');
            Route::post('/fotos/orden', [GestionController::class, 'ordenar'])->name('fotos.orden');
            Route::get('/fotos/{foto}', [GestionController::class, 'editar'])->whereNumber('foto')->name('fotos.edit');
            Route::put('/fotos/{foto}', [GestionController::class, 'actualizar'])->whereNumber('foto')->name('fotos.update');
            Route::post('/fotos/{foto}/imagen', [GestionController::class, 'reemplazar'])->whereNumber('foto')->name('fotos.imagen');
            Route::post('/fotos/{foto}/estado', [GestionController::class, 'estado'])->whereNumber('foto')->name('fotos.estado');
            Route::delete('/fotos/{foto}', [GestionController::class, 'eliminar'])->whereNumber('foto')->name('fotos.destroy');
            Route::get('/moderacion', [GestionController::class, 'moderacion'])->name('moderacion');
            Route::get('/personas/buscar', [GestionController::class, 'buscarPersonas'])->name('personas.buscar');

            Route::post('/capitulos/orden', [CapituloController::class, 'ordenar'])->name('capitulos.orden');
            Route::resource('capitulos', CapituloController::class)->except('show')->parameters(['capitulos' => 'capitulo']);
            Route::post('/eventos/orden', [AcontecimientoController::class, 'ordenar'])->name('eventos.orden');
            Route::resource('eventos', AcontecimientoController::class)->except('show')->parameters(['eventos' => 'acontecimiento']);
            Route::get('/etiquetas', [EtiquetaController::class, 'index'])->name('etiquetas.index');
            Route::put('/etiquetas/{tag}', [EtiquetaController::class, 'update'])->whereNumber('tag')->name('etiquetas.update');
            Route::delete('/etiquetas/{tag}', [EtiquetaController::class, 'destroy'])->whereNumber('tag')->name('etiquetas.destroy');
        });
    });
});
