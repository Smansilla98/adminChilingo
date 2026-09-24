<?php

use App\Http\Controllers\AccesosController;
use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\AparienciaController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AyudaController;
use App\Http\Controllers\BecaController;
use App\Http\Controllers\BibliotecaAdminController;
use App\Http\Controllers\BibliotecaPublicController;
use App\Http\Controllers\BloqueController;
use App\Http\Controllers\BloqueHorarioController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\ComprobanteCuotaAlumnoGestionController;
use App\Http\Controllers\ComprobanteCuotaAlumnoPublicController;
use App\Http\Controllers\ComunidadAgendaController;
use App\Http\Controllers\ContextoController;
use App\Http\Controllers\CuotaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisenoController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\FacturacionMensualController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\HubSearchController;
use App\Http\Controllers\InventarioItemController;
use App\Http\Controllers\InventarioPublicoController;
use App\Http\Controllers\OperativoController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PartituraController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\PlanComprasController;
use App\Http\Controllers\ProfesorController;
use App\Http\Controllers\ProfesorPagoCuotaController;
use App\Http\Controllers\ProgramaController;
use App\Http\Controllers\RecordatorioChatbotController;
use App\Http\Controllers\RecordatorioMailController;
use App\Http\Controllers\RecordatorioWhatsAppController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\SedeController;
use App\Http\Controllers\SeguimientoPedagogicoController;
use App\Http\Controllers\ShowController;
use App\Http\Controllers\TwilioWhatsAppStatusController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VillaGesellCalendarioController;
use App\Http\Controllers\VillaGesellController;
use App\Http\Controllers\VillaGesellGastoController;
use App\Http\Controllers\VillaGesellInscriptoController;
use App\Http\Controllers\VillaGesellInsumoController;
use App\Models\Bloque;
use Illuminate\Support\Facades\Route;

// Carga pública de comprobante de cuota (sin sesión)
Route::prefix('pagar-cuota')->middleware('throttle:20,1')->group(function () {
    Route::get('/comprobante', [ComprobanteCuotaAlumnoPublicController::class, 'create'])->name('comprobante-cuota-public.create');
    Route::post('/comprobante', [ComprobanteCuotaAlumnoPublicController::class, 'store'])->name('comprobante-cuota-public.store');
    Route::get('/api/periodos', [ComprobanteCuotaAlumnoPublicController::class, 'apiPeriodos'])->name('comprobante-cuota-public.api.periodos');
    Route::get('/api/bloques', [ComprobanteCuotaAlumnoPublicController::class, 'apiBloques'])->name('comprobante-cuota-public.api.bloques');
    Route::get('/api/alumnos', [ComprobanteCuotaAlumnoPublicController::class, 'apiAlumnos'])->middleware('throttle:8,1')->name('comprobante-cuota-public.api.alumnos');
    Route::get('/api/alumno-otros-bloques', [ComprobanteCuotaAlumnoPublicController::class, 'apiOtrosBloquesAlumno'])->middleware('throttle:12,1')->name('comprobante-cuota-public.api.alumno-otros-bloques');
});

Route::get('/agenda', [ComunidadAgendaController::class, 'index'])->middleware('throttle:60,1')->name('comunidad.agenda');
Route::get('/tambor/{codigo}', [InventarioPublicoController::class, 'show'])
    ->middleware('throttle:40,1')
    ->where('codigo', '[A-Za-z0-9._-]{2,40}')
    ->name('inventario.publico');

Route::post('/webhooks/twilio/whatsapp-status', TwilioWhatsAppStatusController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.twilio.whatsapp-status');

Route::get('/salud', function () {
    $db = true;
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
    } catch (\Throwable) {
        $db = false;
    }

    return response()->json([
        'ok' => $db,
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ], $db ? 200 : 503);
})->name('salud');

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
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
if (filter_var(env('ALLOW_PUBLIC_REGISTER', false), FILTER_VALIDATE_BOOLEAN)) {
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:login');
} else {
    Route::get('/register', fn () => redirect()->route('login')->with('error', 'El registro público está deshabilitado. Pedile acceso a administración.'))->name('register');
    Route::post('/register', fn () => abort(403, 'Registro público deshabilitado.'));
}
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Rutas protegidas
|--------------------------------------------------------------------------
| Cada ruta exige un permiso granular (`permiso:*`). El alcance (sede / bloque)
| de cada registro lo verifican las Policies dentro de los controladores.
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/contexto', [ContextoController::class, 'cambiar'])->name('contexto.cambiar');

    Route::get('/recordatorios/chat', RecordatorioChatbotController::class)->name('recordatorios.chat');
    Route::post('/recordatorios/whatsapp', [RecordatorioWhatsAppController::class, 'enviar'])->middleware('permiso:notificaciones.send|pagos.view')->name('recordatorios.whatsapp.enviar');
    Route::post('/recordatorios/mail', [RecordatorioMailController::class, 'enviar'])->middleware('permiso:notificaciones.send|pagos.view')->name('recordatorios.mail.enviar');

    // Ayuda (guía de uso)
    Route::get('/ayuda', [AyudaController::class, 'index'])->middleware('modulo:ayuda')->name('ayuda');

    // Apariencia (preferencia visual por usuario)
    Route::get('/apariencia', [AparienciaController::class, 'edit'])->name('apariencia.edit');
    Route::post('/apariencia', [AparienciaController::class, 'update'])->name('apariencia.update');
    Route::post('/apariencia/restablecer', [AparienciaController::class, 'reset'])->name('apariencia.reset');

    // Operativo diario
    Route::get('/pendientes', [OperativoController::class, 'pendientes'])->name('operativo.pendientes');
    Route::get('/api/hub-search', HubSearchController::class)->name('hub.search');

    Route::post('/seguimiento', [SeguimientoPedagogicoController::class, 'store'])->middleware('permiso:seguimiento.create')->name('seguimiento.store');
    Route::delete('/seguimiento/{observacionPedagogica}', [SeguimientoPedagogicoController::class, 'destroy'])->middleware('permiso:seguimiento.create')->name('seguimiento.destroy');

    // Calendario
    Route::get('/calendario', [CalendarioController::class, 'index'])->middleware('modulo:calendario')->name('calendario.index');
    Route::get('/calendario/eventos', [CalendarioController::class, 'eventos'])->middleware('modulo:calendario')->name('calendario.eventos');

    // Comprobantes de cuota enviados por alumnos
    Route::middleware(['permiso:comprobantes.view', 'modulo:comprobantes'])->group(function () {
        Route::get('/comprobantes-cuota-alumnos', [ComprobanteCuotaAlumnoGestionController::class, 'index'])->name('comprobantes-cuota-alumnos.index');
        Route::get('/comprobantes-cuota-alumnos/cargar', [ComprobanteCuotaAlumnoGestionController::class, 'create'])->middleware('permiso:comprobantes.create')->name('comprobantes-cuota-alumnos.create');
        Route::post('/comprobantes-cuota-alumnos/cargar', [ComprobanteCuotaAlumnoGestionController::class, 'store'])->middleware('permiso:comprobantes.create')->name('comprobantes-cuota-alumnos.store');
        Route::get('/comprobantes-cuota-alumnos/{id}', [ComprobanteCuotaAlumnoGestionController::class, 'show'])->name('comprobantes-cuota-alumnos.show')->whereNumber('id');
        Route::get('/comprobantes-cuota-alumnos/{id}/comprobante', [ComprobanteCuotaAlumnoGestionController::class, 'comprobante'])->name('comprobantes-cuota-alumnos.comprobante')->whereNumber('id');
        Route::post('/comprobantes-cuota-alumnos/{id}/visto', [ComprobanteCuotaAlumnoGestionController::class, 'marcarVisto'])->name('comprobantes-cuota-alumnos.visto')->whereNumber('id');
        Route::post('/comprobantes-cuota-alumnos/{id}/aprobar-pago', [ComprobanteCuotaAlumnoGestionController::class, 'aprobarYRegistrarPago'])->middleware('permiso:comprobantes.approve')->name('comprobantes-cuota-alumnos.aprobar-pago')->whereNumber('id');
    });

    // Personas (ficha central) y administración de accesos
    Route::get('/personas', [PersonaController::class, 'index'])->middleware('permiso:personas.view')->name('personas.index');
    Route::get('/personas/crear', [PersonaController::class, 'create'])->middleware('permiso:personas.create')->name('personas.create');
    Route::post('/personas', [PersonaController::class, 'store'])->middleware('permiso:personas.create')->name('personas.store');
    Route::get('/personas/{persona}', [PersonaController::class, 'show'])->name('personas.show');
    Route::get('/personas/{persona}/editar', [PersonaController::class, 'edit'])->middleware('permiso:personas.update')->name('personas.edit');
    Route::put('/personas/{persona}', [PersonaController::class, 'update'])->middleware('permiso:personas.update')->name('personas.update');
    Route::post('/personas/{persona}/fusionar', [PersonaController::class, 'fusionar'])->middleware('permiso:personas.merge')->name('personas.fusionar');
    Route::post('/personas/{persona}/becas', [BecaController::class, 'store'])->middleware('permiso:becas.manage')->name('becas.store');
    Route::put('/becas/{beca}', [BecaController::class, 'update'])->middleware('permiso:becas.manage')->name('becas.update');

    Route::middleware('permiso:usuarios.view')->prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/crear', [UsuarioController::class, 'create'])->middleware('permiso:usuarios.create')->name('create');
        Route::post('/', [UsuarioController::class, 'store'])->middleware('permiso:usuarios.create')->name('store');
        Route::get('/{usuario}', [UsuarioController::class, 'show'])->name('show');
        Route::put('/{usuario}', [UsuarioController::class, 'update'])->middleware('permiso:usuarios.update')->name('update');
        Route::post('/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->middleware('permiso:usuarios.update')->name('estado');
        Route::post('/{usuario}/resetear-acceso', [UsuarioController::class, 'resetearAcceso'])->middleware('permiso:usuarios.update')->name('resetear');
        Route::post('/{usuario}/asignaciones', [UsuarioController::class, 'asignar'])->middleware('permiso:usuarios.permissions')->name('asignaciones.store');
        Route::delete('/{usuario}/asignaciones/{asignacion}', [UsuarioController::class, 'quitarAsignacion'])->middleware('permiso:usuarios.permissions')->name('asignaciones.destroy');
    });
    Route::get('/auditoria', [AuditoriaController::class, 'index'])->middleware('permiso:auditoria.view')->name('auditoria.index');

    // Alumnos
    Route::get('/alumnos/import', [AlumnoController::class, 'importForm'])->middleware('permiso:alumnos.import')->name('alumnos.import.form');
    Route::post('/alumnos/import', [AlumnoController::class, 'importStore'])->middleware('permiso:alumnos.import')->name('alumnos.import.store');
    Route::get('/alumnos/export/excel', [AlumnoController::class, 'export'])->middleware('permiso:alumnos.export')->name('alumnos.export');
    Route::resource('alumnos', AlumnoController::class)
        ->middlewareFor(['index', 'show'], 'permiso:alumnos.view')
        ->middlewareFor(['create', 'store'], 'permiso:alumnos.create')
        ->middlewareFor(['edit', 'update'], 'permiso:alumnos.update')
        ->middlewareFor('destroy', 'permiso:alumnos.delete');

    // Bloques / sedes / eventos / shows
    Route::resource('bloques', BloqueController::class)
        ->middlewareFor(['index', 'show'], 'permiso:bloques.view')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'permiso:bloques.manage')
        ->middlewareFor('destroy', 'permiso:bloques.delete');
    Route::post('bloques/{bloque}/horarios', [BloqueHorarioController::class, 'store'])->middleware('permiso:bloques.manage')->name('bloques.horarios.store');
    Route::delete('bloque-horarios/{bloqueHorario}', [BloqueHorarioController::class, 'destroy'])->middleware('permiso:bloques.manage')->name('bloque-horarios.destroy');
    Route::resource('shows', ShowController::class)
        ->middlewareFor(['index', 'show'], 'permiso:shows.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permiso:shows.manage');
    Route::resource('sedes', SedeController::class)
        ->middlewareFor(['index', 'show'], 'permiso:sedes.view')
        ->middlewareFor(['create', 'store', 'edit', 'update'], 'permiso:sedes.manage')
        ->middlewareFor('destroy', 'permiso:sedes.delete');
    Route::resource('eventos', EventoController::class)
        ->middlewareFor(['index', 'show'], 'permiso:eventos.view')
        ->middlewareFor(['create', 'store'], 'permiso:eventos.create')
        ->middlewareFor(['edit', 'update'], 'permiso:eventos.update')
        ->middlewareFor('destroy', 'permiso:eventos.delete');

    // Asistencias
    Route::post('asistencias/matrix', [AsistenciaController::class, 'matrixUpdate'])->middleware('permiso:asistencias.create|asistencias.update')->name('asistencias.matrix.update');
    Route::post('asistencias', [AsistenciaController::class, 'store'])->middleware('permiso:asistencias.create')->name('asistencias.store');
    Route::resource('asistencias', AsistenciaController::class)->except(['store'])
        ->middlewareFor(['index', 'show'], 'permiso:asistencias.view')
        ->middlewareFor('create', 'permiso:asistencias.create')
        ->middlewareFor(['edit', 'update'], 'permiso:asistencias.update')
        ->middlewareFor('destroy', 'permiso:asistencias.delete');
    Route::get('/asistencias/bloque/{bloque}', function (Bloque $bloque) {
        return redirect()->route('asistencias.create', ['bloque_id' => $bloque->id]);
    })->middleware('permiso:asistencias.create')->name('asistencias.bloque');

    // Reportes
    Route::middleware('permiso:reportes.view')->group(function () {
        Route::get('/reportes', [ReportesController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/export/excel', [ReportesController::class, 'exportExcel'])->name('reportes.export.excel');
        Route::get('/reportes/export/pdf', [ReportesController::class, 'exportPdf'])->name('reportes.export.pdf');
        Route::get('/reportes/profesores', [ReportesController::class, 'profesores'])->name('reportes.profesores');
    });

    Route::middleware(['permiso:villa_gesell.manage', 'modulo:admin.villa_gesell'])->prefix('villa-gesell')->name('villa-gesell.')->group(function () {
        Route::get('/', [VillaGesellController::class, 'index'])->name('index');
        Route::put('/config', [VillaGesellController::class, 'updateConfig'])->name('config');
        Route::post('/dias/generar', [VillaGesellController::class, 'generarDias'])->name('dias.generar');
        Route::get('/calendario', [VillaGesellCalendarioController::class, 'index'])->name('calendario');
        Route::put('/dias/{dia}', [VillaGesellCalendarioController::class, 'updateDia'])->name('dias.update');
        Route::post('/dias/{dia}/slots', [VillaGesellCalendarioController::class, 'generarSlots'])->name('dias.slots');
        Route::post('/dias/{dia}/tocadas', [VillaGesellCalendarioController::class, 'storeTocada'])->name('tocadas.store');
        Route::put('/tocadas/{tocada}', [VillaGesellCalendarioController::class, 'updateTocada'])->name('tocadas.update');
        Route::delete('/tocadas/{tocada}', [VillaGesellCalendarioController::class, 'destroyTocada'])->name('tocadas.destroy');
        Route::get('/plan', [VillaGesellGastoController::class, 'plan'])->name('plan');
        Route::post('/alumnos-rapidos', [VillaGesellInscriptoController::class, 'storeAlumnoRapido'])->name('alumnos-rapidos.store');
        Route::post('/profesores-rapidos', [VillaGesellInscriptoController::class, 'storeProfesorRapido'])->name('profesores-rapidos.store');
        Route::post('/bloques-rapidos', [VillaGesellInscriptoController::class, 'storeBloqueRapido'])->name('bloques-rapidos.store');
        Route::resource('inscriptos', VillaGesellInscriptoController::class)->except(['show'])->parameters(['inscriptos' => 'inscripto']);
        Route::resource('insumos', VillaGesellInsumoController::class)->except(['show'])->parameters(['insumos' => 'insumo']);
        Route::resource('gastos', VillaGesellGastoController::class)->except(['show'])->parameters(['gastos' => 'gasto']);
    });

    // Visibilidad de módulos del menú (matriz heredada) y alta rápida de cuentas
    Route::middleware('permiso:usuarios.permissions')->group(function () {
        Route::get('/accesos', [AccesosController::class, 'index'])->name('accesos.index');
        Route::post('/accesos', [AccesosController::class, 'update'])->name('accesos.update');
    });
    Route::get('/accesos/crear', fn () => redirect()->route('usuarios.create'))->middleware('permiso:usuarios.create')->name('accesos.create');
    Route::post('/accesos/crear', [AccesosController::class, 'store'])->middleware('permiso:usuarios.create')->name('accesos.store');

    // Programa y partituras (administración)
    Route::middleware('permiso:partituras.admin')->group(function () {
        Route::get('/programa/toque/{programaRitmo:slug}/partitura', [ProgramaController::class, 'editPartitura'])->name('programa.toque.partitura.edit');
        Route::post('/programa/toque/{programaRitmo:slug}/partitura', [ProgramaController::class, 'updatePartitura'])->name('programa.toque.partitura.update');
        Route::post('/programa/partituras/cargar-cuadernillo', [ProgramaController::class, 'importarCuadernillo'])->name('programa.partituras.importar-cuadernillo');
        Route::post('/programa/partituras/toques', [ProgramaController::class, 'storeToque'])->name('programa.partituras.toques.store');
        Route::get('/programa/seccion/{programaSeccion:slug}/editar', [ProgramaController::class, 'editSeccion'])->name('programa.seccion.edit');
        Route::put('/programa/seccion/{programaSeccion:slug}', [ProgramaController::class, 'updateSeccion'])->name('programa.seccion.update');
    });

    Route::middleware('permiso:biblioteca.admin')->group(function () {
        Route::get('/biblioteca/admin', [BibliotecaAdminController::class, 'index'])->name('biblioteca.admin.index');
        Route::post('/biblioteca/admin/{bibliotecaItem}/toggle', [BibliotecaAdminController::class, 'toggle'])->name('biblioteca.admin.toggle')->whereNumber('bibliotecaItem');
        Route::delete('/biblioteca/admin/{bibliotecaItem}', [BibliotecaAdminController::class, 'destroy'])->name('biblioteca.admin.destroy')->whereNumber('bibliotecaItem');
    });

    // Profesores
    Route::resource('profesores', ProfesorController::class)
        ->parameters(['profesores' => 'profesor'])
        ->middlewareFor(['index', 'show'], 'permiso:profesores.view')
        ->middlewareFor(['create', 'store'], 'permiso:profesores.create')
        ->middlewareFor(['edit', 'update'], 'permiso:profesores.update')
        ->middlewareFor('destroy', 'permiso:profesores.delete');

    // Diseño
    Route::middleware(['permiso:disenos.manage', 'modulo:admin.disenos'])->group(function () {
        Route::post('disenos/medios', [DisenoController::class, 'storeMedio'])->name('disenos.medios.store');
        Route::get('disenos/kit', [DisenoController::class, 'kitIndex'])->middleware('throttle:60,1')->name('disenos.kit.index');
        Route::post('disenos/kit', [DisenoController::class, 'kitStore'])->middleware('throttle:30,1')->name('disenos.kit.store');
        Route::delete('disenos/kit/{kit}', [DisenoController::class, 'kitDestroy'])->name('disenos.kit.destroy');
        Route::get('disenos/biblioteca/items', [BibliotecaPublicController::class, 'apiItems'])->middleware('throttle:60,1')->name('disenos.biblioteca.items');
        Route::resource('disenos', DisenoController::class);
    });

    // Finanzas
    Route::resource('cuotas', CuotaController::class)
        ->middlewareFor(['index', 'show'], 'permiso:cuotas.view')
        ->middlewareFor(['create', 'store'], 'permiso:cuotas.create')
        ->middlewareFor(['edit', 'update'], 'permiso:cuotas.update')
        ->middlewareFor('destroy', 'permiso:cuotas.delete');

    Route::get('/pagos', [PagoController::class, 'index'])->middleware('permiso:pagos.view')->name('pagos.index');
    Route::get('/pagos/crear', [PagoController::class, 'create'])->middleware('permiso:pagos.create')->name('pagos.create');
    Route::post('/pagos', [PagoController::class, 'store'])->middleware('permiso:pagos.create')->name('pagos.store');
    Route::get('/pagos/api/alumnos-por-cuota', [PagoController::class, 'alumnosParaCuota'])->middleware('permiso:pagos.create')->name('pagos.api.alumnos-cuota');
    Route::get('/pagos/{pago}', [PagoController::class, 'show'])->middleware('permiso:pagos.view')->name('pagos.show');
    Route::get('/pagos/{pago}/editar', [PagoController::class, 'edit'])->middleware('permiso:pagos.update')->name('pagos.edit');
    Route::put('/pagos/{pago}', [PagoController::class, 'update'])->middleware('permiso:pagos.update')->name('pagos.update');
    Route::post('/pagos/{pago}/anular', [PagoController::class, 'anular'])->middleware('permiso:pagos.reverse')->name('pagos.anular');
    Route::get('/pagos/{pago}/comprobante', [PagoController::class, 'downloadComprobante'])->middleware('permiso:pagos.view')->name('pagos.comprobante');

    Route::middleware('permiso:facturacion.view')->group(function () {
        Route::get('/facturacion-mensual', [FacturacionMensualController::class, 'index'])->name('facturacion-mensual.index');
        Route::get('/facturacion-mensual/crear', [FacturacionMensualController::class, 'create'])->middleware('permiso:facturacion.manage')->name('facturacion-mensual.create');
        Route::post('/facturacion-mensual', [FacturacionMensualController::class, 'store'])->middleware('permiso:facturacion.manage')->name('facturacion-mensual.store');
        Route::get('/facturacion-mensual/{facturacionMensual}/editar', [FacturacionMensualController::class, 'edit'])->middleware('permiso:facturacion.manage')->name('facturacion-mensual.edit');
        Route::put('/facturacion-mensual/{facturacionMensual}', [FacturacionMensualController::class, 'update'])->middleware('permiso:facturacion.manage')->name('facturacion-mensual.update');
        Route::get('/cierre-de-mes', [OperativoController::class, 'cierreMes'])->name('operativo.cierre-mes');
    });

    Route::resource('gastos', GastoController::class)
        ->middlewareFor(['index', 'show'], 'permiso:gastos.view')
        ->middlewareFor(['create', 'store'], 'permiso:gastos.create')
        ->middlewareFor(['edit', 'update'], 'permiso:gastos.update')
        ->middlewareFor('destroy', 'permiso:gastos.delete');
    Route::post('/gastos/{gasto}/aprobar', [GastoController::class, 'aprobar'])->middleware('permiso:gastos.approve')->name('gastos.aprobar');

    // Inventario y compras
    Route::resource('inventarios', InventarioItemController::class)
        ->middlewareFor(['index', 'show'], 'permiso:inventario.view')
        ->middlewareFor(['create', 'store'], 'permiso:inventario.create')
        ->middlewareFor(['edit', 'update'], 'permiso:inventario.update')
        ->middlewareFor('destroy', 'permiso:inventario.delete');
    Route::post('inventarios/{inventario}/movimientos', [InventarioItemController::class, 'registrarMovimiento'])->middleware('permiso:inventario.update')->name('inventarios.movimientos.store');
    Route::get('/plan-compras', [PlanComprasController::class, 'index'])->middleware('permiso:compras.view')->name('plan-compras.index');
    Route::resource('ordenes-compra', OrdenCompraController::class)
        ->middlewareFor(['index', 'show'], 'permiso:compras.view')
        ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permiso:compras.create');

    // Espacio docente (las mismas pantallas, acotadas a sus bloques)
    Route::get('/mis-bloques', [BloqueController::class, 'index'])->middleware(['permiso:bloques.view', 'modulo:profesor.mis_bloques'])->name('profesor.bloques');
    Route::get('/mis-alumnos', [AlumnoController::class, 'index'])->middleware(['permiso:alumnos.view', 'modulo:profesor.mis_alumnos'])->name('profesor.alumnos');
    Route::get('/profesor/alumnos/{alumno}', [AlumnoController::class, 'show'])->middleware(['permiso:alumnos.view', 'modulo:profesor.mis_alumnos'])->name('profesor.alumnos.show');
    Route::get('/profesor/pagos-cuotas', [ProfesorPagoCuotaController::class, 'index'])->middleware(['permiso:pagos.view', 'modulo:profesor.pagos_cuotas'])->name('profesor.pagos-cuotas.index');
    Route::get('/mis-eventos', [EventoController::class, 'index'])->middleware(['permiso:eventos.view', 'modulo:profesor.mis_eventos'])->name('profesor.eventos');
    Route::get('/profesor/asistencias/crear', [AsistenciaController::class, 'create'])->middleware(['permiso:asistencias.create', 'modulo:profesor.asistencia'])->name('profesor.asistencias.create');
    Route::post('/profesor/asistencias', [AsistenciaController::class, 'store'])->middleware(['permiso:asistencias.create', 'modulo:profesor.asistencia'])->name('profesor.asistencias.store');
    Route::get('/profesor/asistencias/matriz', [AsistenciaController::class, 'index'])->middleware(['permiso:asistencias.view', 'modulo:profesor.asistencia'])->name('profesor.asistencias.matrix');
    Route::post('/profesor/asistencias/matriz', [AsistenciaController::class, 'matrixUpdate'])->middleware(['permiso:asistencias.create|asistencias.update', 'modulo:profesor.asistencia'])->name('profesor.asistencias.matrix.update');
});
