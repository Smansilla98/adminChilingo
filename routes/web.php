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
use App\Http\Controllers\AyudaController;
use App\Http\Controllers\AccesosController;
use App\Http\Controllers\RecordatorioChatbotController;
use App\Http\Controllers\ComprobanteCuotaAlumnoPublicController;
use App\Http\Controllers\ComprobanteCuotaAlumnoGestionController;
use App\Http\Controllers\ProfesorPagoCuotaController;
use App\Models\Bloque;

// Carga pública de comprobante de cuota (sin sesión)
Route::prefix('pagar-cuota')->middleware('throttle:30,1')->group(function () {
    Route::get('/comprobante', [ComprobanteCuotaAlumnoPublicController::class, 'create'])->name('comprobante-cuota-public.create');
    Route::post('/comprobante', [ComprobanteCuotaAlumnoPublicController::class, 'store'])->name('comprobante-cuota-public.store');
    Route::get('/api/periodos', [ComprobanteCuotaAlumnoPublicController::class, 'apiPeriodos'])->name('comprobante-cuota-public.api.periodos');
    Route::get('/api/bloques', [ComprobanteCuotaAlumnoPublicController::class, 'apiBloques'])->name('comprobante-cuota-public.api.bloques');
    Route::get('/api/alumnos', [ComprobanteCuotaAlumnoPublicController::class, 'apiAlumnos'])->name('comprobante-cuota-public.api.alumnos');
    Route::get('/api/alumno-otros-bloques', [ComprobanteCuotaAlumnoPublicController::class, 'apiOtrosBloquesAlumno'])->name('comprobante-cuota-public.api.alumno-otros-bloques');
});

// Rutas públicas
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/recordatorios/chat', RecordatorioChatbotController::class)->name('recordatorios.chat');

    // Ayuda (guía de uso)
    Route::get('/ayuda', [AyudaController::class, 'index'])->middleware('modulo:ayuda')->name('ayuda');

    // Programa oficial — accesible para todos; edición solo admin
    Route::get('/programa', [ProgramaController::class, 'index'])->middleware('modulo:programa')->name('programa.index');
    Route::get('/programa/toque/{programaRitmo:slug}', [ProgramaController::class, 'showToque'])->middleware('modulo:programa')->name('programa.toque.show');
    Route::get('/programa/toque/{programaRitmo:slug}/archivo', [ProgramaController::class, 'descargarMedio'])->middleware('modulo:programa')->name('programa.toque.archivo');

    // Calendario (accesible para todos)
    Route::get('/calendario', [CalendarioController::class, 'index'])->middleware('modulo:calendario')->name('calendario.index');
    Route::get('/calendario/eventos', [CalendarioController::class, 'eventos'])->middleware('modulo:calendario')->name('calendario.eventos');

    // Comprobantes de cuota enviados por alumnos (admin o profesor)
    Route::middleware(['profesor_o_admin', 'modulo:comprobantes'])->group(function () {
        Route::get('/comprobantes-cuota-alumnos', [ComprobanteCuotaAlumnoGestionController::class, 'index'])->name('comprobantes-cuota-alumnos.index');
        Route::get('/comprobantes-cuota-alumnos/{id}', [ComprobanteCuotaAlumnoGestionController::class, 'show'])->name('comprobantes-cuota-alumnos.show')->whereNumber('id');
        Route::get('/comprobantes-cuota-alumnos/{id}/comprobante', [ComprobanteCuotaAlumnoGestionController::class, 'comprobante'])->name('comprobantes-cuota-alumnos.comprobante')->whereNumber('id');
        Route::post('/comprobantes-cuota-alumnos/{id}/visto', [ComprobanteCuotaAlumnoGestionController::class, 'marcarVisto'])->name('comprobantes-cuota-alumnos.visto')->whereNumber('id');
    });

    // Rutas de Admin
    Route::middleware(['role:admin'])->group(function () {
        // Matriz de accesos por usuario
        Route::get('/accesos', [AccesosController::class, 'index'])->name('accesos.index');
        Route::post('/accesos', [AccesosController::class, 'update'])->name('accesos.update');

        Route::get('/programa/toque/{programaRitmo:slug}/editar', [ProgramaController::class, 'editToque'])->name('programa.toque.edit');
        Route::match(['put', 'post'], '/programa/toque/{programaRitmo:slug}', [ProgramaController::class, 'updateToque'])->name('programa.toque.update');
        Route::get('/programa/seccion/{programaSeccion:slug}/editar', [ProgramaController::class, 'editSeccion'])->name('programa.seccion.edit');
        Route::put('/programa/seccion/{programaSeccion:slug}', [ProgramaController::class, 'updateSeccion'])->name('programa.seccion.update');

        // Alumnos
        Route::get('/alumnos/import', [AlumnoController::class, 'importForm'])->name('alumnos.import.form');
        Route::post('/alumnos/import', [AlumnoController::class, 'importStore'])->name('alumnos.import.store');
        Route::resource('alumnos', AlumnoController::class);
        Route::get('/alumnos/export/excel', [AlumnoController::class, 'export'])->name('alumnos.export');

        // Profesores — el segmento plural evita que Str::singular() genere "profesore" en la URL
        Route::resource('profesores', ProfesorController::class)
            ->parameters(['profesores' => 'profesor']);

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

        // Asistencias (ruta store con nombre explícito por uso en create.blade.php)
        Route::post('asistencias/matrix', [AsistenciaController::class, 'matrixUpdate'])->name('asistencias.matrix.update');
        Route::post('asistencias', [AsistenciaController::class, 'store'])->name('asistencias.store');
        Route::resource('asistencias', AsistenciaController::class)->except(['store']);

        // Cuotas
        Route::resource('cuotas', CuotaController::class);

        // Pagos (trazabilidad: quién paga, cuándo, varios alumnos, PDF)
        Route::get('/pagos', [PagoController::class, 'index'])->name('pagos.index');
        Route::get('/pagos/crear', [PagoController::class, 'create'])->name('pagos.create');
        Route::post('/pagos', [PagoController::class, 'store'])->name('pagos.store');
        Route::get('/pagos/{pago}', [PagoController::class, 'show'])->name('pagos.show');
        Route::get('/pagos/{pago}/editar', [PagoController::class, 'edit'])->name('pagos.edit');
        Route::put('/pagos/{pago}', [PagoController::class, 'update'])->name('pagos.update');
        Route::get('/pagos/{pago}/comprobante', [PagoController::class, 'downloadComprobante'])->name('pagos.comprobante');
        Route::get('/pagos/api/alumnos-por-cuota', [PagoController::class, 'alumnosParaCuota'])->name('pagos.api.alumnos-cuota');

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

        // Dashboard: "ver todo" profesores (pantalla dedicada)
        Route::get('/reportes/profesores', [ReportesController::class, 'profesores'])->name('reportes.profesores');

        // Dashboard: acceso rápido a asistencia por bloque (usa el create existente con query string)
        Route::get('/asistencias/bloque/{bloque}', function (Bloque $bloque) {
            return redirect()->route('asistencias.create', ['bloque_id' => $bloque->id]);
        })->name('asistencias.bloque');
    });

    // Rutas de Profesor
    Route::middleware(['role:profesor'])->group(function () {
        // Profesores pueden ver sus bloques y alumnos
        Route::get('/mis-bloques', [BloqueController::class, 'index'])->middleware('modulo:profesor.mis_bloques')->name('profesor.bloques');
        Route::get('/mis-alumnos', [AlumnoController::class, 'index'])->middleware('modulo:profesor.mis_alumnos')->name('profesor.alumnos');
        Route::get('/profesor/alumnos/{alumno}', [AlumnoController::class, 'show'])->middleware('modulo:profesor.mis_alumnos')->name('profesor.alumnos.show');
        Route::get('/profesor/pagos-cuotas', [ProfesorPagoCuotaController::class, 'index'])->middleware('modulo:profesor.pagos_cuotas')->name('profesor.pagos-cuotas.index');
        Route::get('/mis-eventos', [EventoController::class, 'index'])->middleware('modulo:profesor.mis_eventos')->name('profesor.eventos');
        Route::get('/profesor/asistencias/crear', [AsistenciaController::class, 'create'])->middleware('modulo:profesor.asistencia')->name('profesor.asistencias.create');
        Route::post('/profesor/asistencias', [AsistenciaController::class, 'store'])->middleware('modulo:profesor.asistencia')->name('profesor.asistencias.store');
    });
});
