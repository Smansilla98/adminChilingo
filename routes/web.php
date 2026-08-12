<?php

use App\Http\Controllers\AccesosController;
use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\AparienciaController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AyudaController;
use App\Http\Controllers\BibliotecaAdminController;
use App\Http\Controllers\BibliotecaPublicController;
use App\Http\Controllers\BloqueController;
use App\Http\Controllers\BloqueHorarioController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ComprobanteCuotaAlumnoGestionController;
use App\Http\Controllers\ComprobanteCuotaAlumnoPublicController;
use App\Http\Controllers\CuotaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisenoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\FacturacionMensualController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\HubSearchController;
use App\Http\Controllers\InventarioItemController;
use App\Http\Controllers\OperativoController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PlanComprasController;
use App\Http\Controllers\ProfesorController;
use App\Http\Controllers\ProfesorPagoCuotaController;
use App\Http\Controllers\PartituraController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\RecordatorioChatbotController;
use App\Http\Controllers\RecordatorioMailController;
use App\Http\Controllers\RecordatorioWhatsAppController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\ShowController;
use App\Models\Bloque;
use Illuminate\Support\Facades\Route;

// Carga pública de comprobante de cuota (sin sesión)
Route::prefix('pagar-cuota')->middleware('throttle:30,1')->group(function () {
    Route::get('/comprobante', [ComprobanteCuotaAlumnoPublicController::class, 'create'])->name('comprobante-cuota-public.create');
    Route::post('/comprobante', [ComprobanteCuotaAlumnoPublicController::class, 'store'])->name('comprobante-cuota-public.store');
    Route::get('/api/periodos', [ComprobanteCuotaAlumnoPublicController::class, 'apiPeriodos'])->name('comprobante-cuota-public.api.periodos');
    Route::get('/api/bloques', [ComprobanteCuotaAlumnoPublicController::class, 'apiBloques'])->name('comprobante-cuota-public.api.bloques');
    Route::get('/api/alumnos', [ComprobanteCuotaAlumnoPublicController::class, 'apiAlumnos'])->name('comprobante-cuota-public.api.alumnos');
    Route::get('/api/alumno-otros-bloques', [ComprobanteCuotaAlumnoPublicController::class, 'apiOtrosBloquesAlumno'])->name('comprobante-cuota-public.api.alumno-otros-bloques');
});

// Biblioteca pública (sin login)
Route::prefix('biblioteca')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [BibliotecaPublicController::class, 'index'])->name('biblioteca.index');
    Route::get('/subir', [BibliotecaPublicController::class, 'create'])->name('biblioteca.create');
    Route::post('/', [BibliotecaPublicController::class, 'store'])->middleware('throttle:10,1')->name('biblioteca.store');
    Route::get('/{bibliotecaItem}/archivo', [BibliotecaPublicController::class, 'archivo'])->name('biblioteca.archivo')->whereNumber('bibliotecaItem');
    Route::get('/{bibliotecaItem}/miniatura', [BibliotecaPublicController::class, 'miniatura'])->name('biblioteca.miniatura')->whereNumber('bibliotecaItem');
    Route::get('/{bibliotecaItem}', [BibliotecaPublicController::class, 'show'])->name('biblioteca.show')->whereNumber('bibliotecaItem');
});

// Programa y partituras públicos (lectura, como la biblioteca)
Route::prefix('programa')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [ProgramaController::class, 'index'])->name('programa.index');
    Route::get('/partituras', [ProgramaController::class, 'partiturasIndex'])->name('programa.partituras.index');
    Route::get('/toque/{programaRitmo:slug}', [ProgramaController::class, 'showToque'])->name('programa.toque.show');
    Route::get('/toque/{programaRitmo:slug}/archivo', [ProgramaController::class, 'descargarMedio'])->name('programa.toque.archivo');
    Route::get('/toque/{programaRitmo:slug}/parte/{instrumento}', [PartituraController::class, 'parte'])->name('programa.toque.parte');
    Route::get('/toque/{programaRitmo:slug}/editor', [PartituraController::class, 'editor'])->name('programa.toque.editor');
    Route::post('/toque/{programaRitmo:slug}/editor', [PartituraController::class, 'guardar'])
        ->middleware('throttle:20,1')
        ->name('programa.toque.editor.guardar');
    Route::post('/toque/{programaRitmo:slug}/editor/referencia', [PartituraController::class, 'subirReferencia'])
        ->middleware('throttle:10,1')
        ->name('programa.toque.editor.referencia');
    Route::get('/toque/{programaRitmo:slug}/editar', [ProgramaController::class, 'editToque'])->name('programa.toque.edit');
    Route::match(['put', 'post'], '/toque/{programaRitmo:slug}', [ProgramaController::class, 'updateToque'])
        ->middleware('throttle:20,1')
        ->name('programa.toque.update');
});

// Rutas públicas
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
if (filter_var(env('ALLOW_PUBLIC_REGISTER', false), FILTER_VALIDATE_BOOLEAN)) {
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
} else {
    Route::get('/register', fn () => redirect()->route('login')->with('error', 'El registro público está deshabilitado. Pedile acceso a administración.'))->name('register');
    Route::post('/register', fn () => abort(403, 'Registro público deshabilitado.'));
}
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Rutas protegidas
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/recordatorios/chat', RecordatorioChatbotController::class)->name('recordatorios.chat');
    Route::post('/recordatorios/whatsapp', [RecordatorioWhatsAppController::class, 'enviar'])->name('recordatorios.whatsapp.enviar');
    Route::post('/recordatorios/mail', [RecordatorioMailController::class, 'enviar'])->name('recordatorios.mail.enviar');

    // Ayuda (guía de uso)
    Route::get('/ayuda', [AyudaController::class, 'index'])->middleware('modulo:ayuda')->name('ayuda');

    // Apariencia (preferencia visual por usuario)
    Route::get('/apariencia', [AparienciaController::class, 'edit'])->name('apariencia.edit');
    Route::post('/apariencia', [AparienciaController::class, 'update'])->name('apariencia.update');
    Route::post('/apariencia/restablecer', [AparienciaController::class, 'reset'])->name('apariencia.reset');

    // Operativo diario
    Route::get('/pendientes', [OperativoController::class, 'pendientes'])->name('operativo.pendientes');
    Route::get('/api/hub-search', HubSearchController::class)->name('hub.search');

    // Calendario (accesible para todos)
    Route::get('/calendario', [CalendarioController::class, 'index'])->middleware('modulo:calendario')->name('calendario.index');
    Route::get('/calendario/eventos', [CalendarioController::class, 'eventos'])->middleware('modulo:calendario')->name('calendario.eventos');

    // Comprobantes de cuota enviados por alumnos (admin o profesor)
    Route::middleware(['profesor_o_admin', 'modulo:comprobantes'])->group(function () {
        Route::get('/comprobantes-cuota-alumnos', [ComprobanteCuotaAlumnoGestionController::class, 'index'])->name('comprobantes-cuota-alumnos.index');
        Route::get('/comprobantes-cuota-alumnos/{id}', [ComprobanteCuotaAlumnoGestionController::class, 'show'])->name('comprobantes-cuota-alumnos.show')->whereNumber('id');
        Route::get('/comprobantes-cuota-alumnos/{id}/comprobante', [ComprobanteCuotaAlumnoGestionController::class, 'comprobante'])->name('comprobantes-cuota-alumnos.comprobante')->whereNumber('id');
        Route::post('/comprobantes-cuota-alumnos/{id}/visto', [ComprobanteCuotaAlumnoGestionController::class, 'marcarVisto'])->name('comprobantes-cuota-alumnos.visto')->whereNumber('id');
        Route::post('/comprobantes-cuota-alumnos/{id}/aprobar-pago', [ComprobanteCuotaAlumnoGestionController::class, 'aprobarYRegistrarPago'])->name('comprobantes-cuota-alumnos.aprobar-pago')->whereNumber('id');
    });

    // Gestión operativa: dirección/admin + coordinadores
    Route::middleware(['role:admin,coordinador_sede,coordinador_area'])->group(function () {
        // Alumnos
        Route::get('/alumnos/import', [AlumnoController::class, 'importForm'])->middleware('role:admin')->name('alumnos.import.form');
        Route::post('/alumnos/import', [AlumnoController::class, 'importStore'])->middleware('role:admin')->name('alumnos.import.store');
        Route::resource('alumnos', AlumnoController::class);
        Route::get('/alumnos/export/excel', [AlumnoController::class, 'export'])->name('alumnos.export');

        // Bloques / sedes / eventos / shows / asistencias
        Route::resource('bloques', BloqueController::class);
        Route::post('bloques/{bloque}/horarios', [BloqueHorarioController::class, 'store'])->name('bloques.horarios.store');
        Route::delete('bloque-horarios/{bloqueHorario}', [BloqueHorarioController::class, 'destroy'])->name('bloque-horarios.destroy');
        Route::resource('shows', ShowController::class);
        Route::resource('sedes', SedeController::class);
        Route::resource('eventos', EventoController::class);
        Route::post('asistencias/matrix', [AsistenciaController::class, 'matrixUpdate'])->name('asistencias.matrix.update');
        Route::post('asistencias', [AsistenciaController::class, 'store'])->name('asistencias.store');
        Route::resource('asistencias', AsistenciaController::class)->except(['store']);
        Route::get('/asistencias/bloque/{bloque}', function (Bloque $bloque) {
            return redirect()->route('asistencias.create', ['bloque_id' => $bloque->id]);
        })->name('asistencias.bloque');
    });

    // Reportes: dirección/admin + coordinador de sede (no coordinador de área)
    Route::middleware(['role:admin,coordinador_sede'])->group(function () {
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/export/excel', [ReportesController::class, 'exportExcel'])->name('reportes.export.excel');
        Route::get('/reportes/export/pdf', [ReportesController::class, 'exportPdf'])->name('reportes.export.pdf');
        Route::get('/reportes/profesores', [ReportesController::class, 'profesores'])->name('reportes.profesores');
    });

    // Solo dirección / admin
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/accesos', [AccesosController::class, 'index'])->name('accesos.index');
        Route::post('/accesos', [AccesosController::class, 'update'])->name('accesos.update');

        Route::get('/programa/toque/{programaRitmo:slug}/partitura', [ProgramaController::class, 'editPartitura'])->name('programa.toque.partitura.edit');
        Route::post('/programa/toque/{programaRitmo:slug}/partitura', [ProgramaController::class, 'updatePartitura'])->name('programa.toque.partitura.update');
        Route::post('/programa/partituras/cargar-cuadernillo', [ProgramaController::class, 'importarCuadernillo'])->name('programa.partituras.importar-cuadernillo');
        Route::get('/programa/seccion/{programaSeccion:slug}/editar', [ProgramaController::class, 'editSeccion'])->name('programa.seccion.edit');
        Route::put('/programa/seccion/{programaSeccion:slug}', [ProgramaController::class, 'updateSeccion'])->name('programa.seccion.update');

        Route::get('/biblioteca/admin', [BibliotecaAdminController::class, 'index'])->name('biblioteca.admin.index');
        Route::post('/biblioteca/admin/{bibliotecaItem}/toggle', [BibliotecaAdminController::class, 'toggle'])->name('biblioteca.admin.toggle')->whereNumber('bibliotecaItem');
        Route::delete('/biblioteca/admin/{bibliotecaItem}', [BibliotecaAdminController::class, 'destroy'])->name('biblioteca.admin.destroy')->whereNumber('bibliotecaItem');

        Route::resource('profesores', ProfesorController::class)
            ->parameters(['profesores' => 'profesor']);

        Route::resource('disenos', DisenoController::class)->middleware('modulo:admin.disenos');

        Route::resource('cuotas', CuotaController::class);

        Route::get('/pagos', [PagoController::class, 'index'])->name('pagos.index');
        Route::get('/pagos/crear', [PagoController::class, 'create'])->name('pagos.create');
        Route::post('/pagos', [PagoController::class, 'store'])->name('pagos.store');
        Route::get('/pagos/{pago}', [PagoController::class, 'show'])->name('pagos.show');
        Route::get('/pagos/{pago}/editar', [PagoController::class, 'edit'])->name('pagos.edit');
        Route::put('/pagos/{pago}', [PagoController::class, 'update'])->name('pagos.update');
        Route::get('/pagos/{pago}/comprobante', [PagoController::class, 'downloadComprobante'])->name('pagos.comprobante');
        Route::get('/pagos/api/alumnos-por-cuota', [PagoController::class, 'alumnosParaCuota'])->name('pagos.api.alumnos-cuota');

        Route::get('/facturacion-mensual', [FacturacionMensualController::class, 'index'])->name('facturacion-mensual.index');
        Route::get('/facturacion-mensual/crear', [FacturacionMensualController::class, 'create'])->name('facturacion-mensual.create');
        Route::post('/facturacion-mensual', [FacturacionMensualController::class, 'store'])->name('facturacion-mensual.store');
        Route::get('/facturacion-mensual/{facturacionMensual}/editar', [FacturacionMensualController::class, 'edit'])->name('facturacion-mensual.edit');
        Route::put('/facturacion-mensual/{facturacionMensual}', [FacturacionMensualController::class, 'update'])->name('facturacion-mensual.update');

        Route::resource('inventarios', InventarioItemController::class);
        Route::get('/plan-compras', [PlanComprasController::class, 'index'])->name('plan-compras.index');
        Route::resource('ordenes-compra', OrdenCompraController::class);
        Route::resource('gastos', GastoController::class);

        Route::get('/cierre-de-mes', [OperativoController::class, 'cierreMes'])->name('operativo.cierre-mes');
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
        Route::get('/profesor/asistencias/matriz', [AsistenciaController::class, 'index'])->middleware('modulo:profesor.asistencia')->name('profesor.asistencias.matrix');
        Route::post('/profesor/asistencias/matriz', [AsistenciaController::class, 'matrixUpdate'])->middleware('modulo:profesor.asistencia')->name('profesor.asistencias.matrix.update');
    });
});
