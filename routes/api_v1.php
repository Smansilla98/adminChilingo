<?php

use App\Http\Controllers\Api\V1\AccesosController;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AsistenciaController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BecaController;
use App\Http\Controllers\Api\V1\BloqueController;
use App\Http\Controllers\Api\V1\FinanzasController;
use App\Http\Controllers\Api\V1\InicioController;
use App\Http\Controllers\Api\V1\InventarioController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\PartituraController;
use App\Http\Controllers\Api\V1\PersonaController;
use App\Http\Controllers\Api\V1\ProfesorController;
use App\Http\Controllers\Api\V1\SedeController;
use Illuminate\Support\Facades\Route;

/*
| /api/v1 — ver docs/API.md
*/

Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
Route::get('salud', fn () => response()->json(['ok' => true, 'version' => 'v1', 'hora' => now()->toIso8601String()]))->name('salud');

Route::middleware(['auth:sanctum', 'activo', 'throttle:api'])->group(function () {
    Route::post('auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

    Route::get('me', MeController::class)->name('me');
    Route::get('inicio', InicioController::class)->name('inicio');

    Route::get('sedes', [SedeController::class, 'index'])->name('sedes.index');
    Route::get('bloques', [BloqueController::class, 'index'])->name('bloques.index');
    Route::get('bloques/{bloque}', [BloqueController::class, 'show'])->name('bloques.show');
    Route::get('bloques/{bloque}/alumnos', [BloqueController::class, 'alumnos'])->name('bloques.alumnos');

    Route::get('bloques/{bloque}/asistencia', [AsistenciaController::class, 'planilla'])->name('asistencia.planilla');
    Route::post('bloques/{bloque}/asistencia', [AsistenciaController::class, 'guardar'])->name('asistencia.guardar');

    // Personas (ficha central) y becas
    Route::get('personas', [PersonaController::class, 'index'])->name('personas.index');
    Route::post('personas', [PersonaController::class, 'store'])->middleware('permiso:personas.create')->name('personas.store');
    Route::get('personas/{persona}', [PersonaController::class, 'show'])->name('personas.show');
    Route::put('personas/{persona}', [PersonaController::class, 'update'])->middleware('permiso:personas.update')->name('personas.update');
    Route::post('personas/{persona}/fusionar', [PersonaController::class, 'fusionar'])->middleware('permiso:personas.merge')->name('personas.fusionar');
    Route::post('personas/{persona}/becas', [BecaController::class, 'store'])->middleware('permiso:becas.manage')->name('becas.store');
    Route::get('becas', [BecaController::class, 'index'])->name('becas.index');
    Route::get('becas/{beca}', [BecaController::class, 'show'])->name('becas.show');
    Route::put('becas/{beca}', [BecaController::class, 'update'])->middleware('permiso:becas.manage')->name('becas.update');
    // Profesores (plantel docente)
    Route::get('profesores/catalogo', [ProfesorController::class, 'catalogo'])->name('profesores.catalogo');
    Route::get('profesores/usuarios-disponibles', [ProfesorController::class, 'usuariosDisponibles'])->name('profesores.usuarios');
    Route::get('profesores', [ProfesorController::class, 'index'])->middleware('permiso:profesores.view')->name('profesores.index');
    Route::post('profesores', [ProfesorController::class, 'store'])->middleware('permiso:profesores.create')->name('profesores.store');
    Route::get('profesores/{profesor}', [ProfesorController::class, 'show'])->whereNumber('profesor')->name('profesores.show');
    Route::put('profesores/{profesor}', [ProfesorController::class, 'update'])->whereNumber('profesor')->middleware('permiso:profesores.update')->name('profesores.update');
    Route::delete('profesores/{profesor}', [ProfesorController::class, 'destroy'])->whereNumber('profesor')->middleware('permiso:profesores.delete')->name('profesores.destroy');

    Route::get('alumnos', [AlumnoController::class, 'index'])->name('alumnos.index');
    Route::get('alumnos/{alumno}', [AlumnoController::class, 'show'])->name('alumnos.show');
    Route::get('alumnos/{alumno}/estado-cuenta', [AlumnoController::class, 'estadoCuenta'])->name('alumnos.estado-cuenta');
    Route::get('mi/estado-cuenta', [AlumnoController::class, 'miEstadoCuenta'])->name('mi.estado-cuenta');

    Route::get('cuotas', [FinanzasController::class, 'cuotas'])->name('cuotas.index');
    Route::get('pagos', [FinanzasController::class, 'pagos'])->name('pagos.index');
    Route::get('pagos/{pago}', [FinanzasController::class, 'pago'])->name('pagos.show');
    Route::post('pagos/{pago}/anular', [FinanzasController::class, 'anular'])->name('pagos.anular');

    Route::get('eventos', [AgendaController::class, 'eventos'])->name('eventos.index');
    Route::get('calendario', [AgendaController::class, 'calendario'])->name('calendario');

    Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('inventario/catalogos', [InventarioController::class, 'catalogos'])->name('inventario.catalogos');
    Route::get('inventario/codigo/{codigo}', [InventarioController::class, 'porCodigo'])->where('codigo', '.+')->name('inventario.codigo');
    Route::post('inventario', [InventarioController::class, 'store'])->name('inventario.store');
    Route::get('inventario/{item}', [InventarioController::class, 'show'])->whereNumber('item')->name('inventario.show');
    Route::put('inventario/{item}', [InventarioController::class, 'update'])->whereNumber('item')->name('inventario.update');
    Route::post('inventario/{item}/movimientos', [InventarioController::class, 'movimiento'])->whereNumber('item')->name('inventario.movimiento');

    Route::get('partituras', [PartituraController::class, 'index'])->name('partituras.index');
    Route::get('partituras/{slug}', [PartituraController::class, 'show'])->name('partituras.show');

    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::post('notificaciones/leer-todas', [NotificacionController::class, 'leerTodas'])->name('notificaciones.leer-todas');
    Route::post('notificaciones/{id}/leer', [NotificacionController::class, 'leer'])->name('notificaciones.leer');
    Route::post('dispositivos', [NotificacionController::class, 'registrarDispositivo'])->name('dispositivos.store');
    Route::delete('dispositivos', [NotificacionController::class, 'quitarDispositivo'])->name('dispositivos.destroy');

    // Auditoría (solo lectura)
    Route::middleware('permiso:auditoria.view')->group(function () {
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
        Route::get('auditoria/catalogo', [AuditoriaController::class, 'catalogo'])->name('auditoria.catalogo');
        Route::get('auditoria/{auditoria}', [AuditoriaController::class, 'show'])->whereNumber('auditoria')->name('auditoria.show');
    });

    Route::get('accesos/catalogo', [AccesosController::class, 'catalogo'])->name('accesos.catalogo');
    Route::get('usuarios', [AccesosController::class, 'usuarios'])->name('usuarios.index');
    Route::get('usuarios/{usuario}', [AccesosController::class, 'usuario'])->name('usuarios.show');
    Route::post('usuarios/{usuario}/asignaciones', [AccesosController::class, 'asignar'])->name('usuarios.asignar');
    Route::delete('usuarios/{usuario}/asignaciones/{asignacion}', [AccesosController::class, 'quitar'])->name('usuarios.quitar');
});
