<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\ProfesorController;
use App\Http\Controllers\BloqueController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CuotaController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\FacturacionMensualController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\BloqueHorarioController;
use App\Http\Controllers\InventarioItemController;
use App\Http\Controllers\PlanComprasController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\ProgramaController;

// Rutas públicas
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Programa oficial (toques por año) — accesible para todos
    Route::get('/programa', [ProgramaController::class, 'index'])->name('programa.index');

    // Calendario (accesible para todos)
    Route::get('/calendario', [CalendarioController::class, 'index'])->name('calendario.index');
    Route::get('/calendario/eventos', [CalendarioController::class, 'eventos'])->name('calendario.eventos');

    // Rutas de Admin
    Route::middleware(['role:admin'])->group(function () {
        // Alumnos
        Route::resource('alumnos', AlumnoController::class);
        Route::get('/alumnos/export/excel', [AlumnoController::class, 'export'])->name('alumnos.export');

        // Profesores
        Route::resource('profesores', ProfesorController::class);

        // Bloques
        Route::resource('bloques', BloqueController::class);
        Route::post('bloques/{bloque}/horarios', [BloqueHorarioController::class, 'store'])->name('bloques.horarios.store');
        Route::delete('bloque-horarios/{bloqueHorario}', [BloqueHorarioController::class, 'destroy'])->name('bloque-horarios.destroy');

        // Shows (próximos shows, bloques o convocatoria abierta)
        Route::resource('shows', ShowController::class);

        // Sedes
        Route::resource('sedes', SedeController::class);

        // Eventos
        Route::resource('eventos', EventoController::class);

        // Asistencias
        Route::resource('asistencias', AsistenciaController::class);

        // Cuotas
        Route::resource('cuotas', CuotaController::class);

        // Pagos (trazabilidad: quién paga, cuándo, varios alumnos, PDF)
        Route::get('/pagos', [PagoController::class, 'index'])->name('pagos.index');
        Route::get('/pagos/crear', [PagoController::class, 'create'])->name('pagos.create');
        Route::post('/pagos', [PagoController::class, 'store'])->name('pagos.store');
        Route::get('/pagos/{pago}', [PagoController::class, 'show'])->name('pagos.show');
        Route::get('/pagos/{pago}/comprobante', [PagoController::class, 'downloadComprobante'])->name('pagos.comprobante');

        // Facturación por mes
        Route::get('/facturacion-mensual', [FacturacionMensualController::class, 'index'])->name('facturacion-mensual.index');
        Route::get('/facturacion-mensual/crear', [FacturacionMensualController::class, 'create'])->name('facturacion-mensual.create');
        Route::post('/facturacion-mensual', [FacturacionMensualController::class, 'store'])->name('facturacion-mensual.store');
        Route::get('/facturacion-mensual/{facturacionMensual}/editar', [FacturacionMensualController::class, 'edit'])->name('facturacion-mensual.edit');
        Route::put('/facturacion-mensual/{facturacionMensual}', [FacturacionMensualController::class, 'update'])->name('facturacion-mensual.update');

        // Inventarios por sede (instrumentos, herramientas, repuestos, etc.)
        Route::resource('inventarios', InventarioItemController::class);

        // Plan de compras (sugerencias por sede, sin generar aún una orden formal)
        Route::get('/plan-compras', [PlanComprasController::class, 'index'])->name('plan-compras.index');

        // Órdenes de compra formales (justificadas por los datos de plan de compras / inventarios)
        Route::resource('ordenes-compra', OrdenCompraController::class);

        // Gastos (sueldos, alquiler, servicios, reparaciones, etc.) — alimenta Reportes
        Route::resource('gastos', GastoController::class);

        // Reportes (solo admin: ingresos, egresos, alumnos x profesor/bloque, etc.)
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
    });

    // Rutas de Profesor
    Route::middleware(['role:profesor'])->group(function () {
        // Profesores pueden ver sus bloques y alumnos
        Route::get('/mis-bloques', [BloqueController::class, 'index'])->name('profesor.bloques');
        Route::get('/mis-alumnos', [AlumnoController::class, 'index'])->name('profesor.alumnos');
        Route::get('/mis-eventos', [EventoController::class, 'index'])->name('profesor.eventos');
        Route::get('/asistencias/crear', [AsistenciaController::class, 'create'])->name('profesor.asistencias.create');
        Route::post('/asistencias', [AsistenciaController::class, 'store'])->name('profesor.asistencias.store');
    });
});
