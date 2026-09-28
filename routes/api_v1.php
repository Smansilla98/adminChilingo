<?php

use App\Http\Controllers\Api\V1\AccesosController;
use App\Http\Controllers\Api\V1\AgendaController;
use App\Http\Controllers\Api\V1\AlumnoController;
use App\Http\Controllers\Api\V1\AsistenciaController;
use App\Http\Controllers\Api\V1\AuditoriaController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BecaController;
use App\Http\Controllers\Api\V1\BibliotecaController;
use App\Http\Controllers\Api\V1\BloqueController;
use App\Http\Controllers\Api\V1\CompraController;
use App\Http\Controllers\Api\V1\ComprobanteController;
use App\Http\Controllers\Api\V1\CuotaController;
use App\Http\Controllers\Api\V1\EventoController;
use App\Http\Controllers\Api\V1\FacturacionController;
use App\Http\Controllers\Api\V1\GastoController;
use App\Http\Controllers\Api\V1\InicioController;
use App\Http\Controllers\Api\V1\InventarioController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\NotificacionController;
use App\Http\Controllers\Api\V1\PagoController;
use App\Http\Controllers\Api\V1\PartituraController;
use App\Http\Controllers\Api\V1\PersonaController;
use App\Http\Controllers\Api\V1\ProfesorController;
use App\Http\Controllers\Api\V1\ReporteController;
use App\Http\Controllers\Api\V1\SedeController;
use App\Http\Controllers\Api\V1\SeguimientoController;
use App\Http\Controllers\Api\V1\ShowController;
use App\Http\Controllers\Api\V1\UsuarioController;
use App\Http\Controllers\Api\V1\VillaGesellController;
use App\Http\Controllers\DisenoEditorController;
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

    // Sedes
    Route::get('sedes', [SedeController::class, 'index'])->name('sedes.index');
    Route::get('sedes/catalogo', [SedeController::class, 'catalogo'])->name('sedes.catalogo');
    Route::post('sedes', [SedeController::class, 'store'])->middleware('permiso:sedes.manage')->name('sedes.store');
    Route::get('sedes/{sede}', [SedeController::class, 'show'])->whereNumber('sede')->middleware('permiso:sedes.view')->name('sedes.show');
    Route::put('sedes/{sede}', [SedeController::class, 'update'])->whereNumber('sede')->middleware('permiso:sedes.manage')->name('sedes.update');
    Route::delete('sedes/{sede}', [SedeController::class, 'destroy'])->whereNumber('sede')->middleware('permiso:sedes.delete')->name('sedes.destroy');
    Route::get('bloques', [BloqueController::class, 'index'])->name('bloques.index');
    Route::get('bloques/catalogo', [BloqueController::class, 'catalogo'])->name('bloques.catalogo');
    Route::post('bloques', [BloqueController::class, 'store'])->middleware('permiso:bloques.manage')->name('bloques.store');
    Route::get('bloques/{bloque}', [BloqueController::class, 'show'])->whereNumber('bloque')->name('bloques.show');
    Route::put('bloques/{bloque}', [BloqueController::class, 'update'])->whereNumber('bloque')->middleware('permiso:bloques.manage')->name('bloques.update');
    Route::delete('bloques/{bloque}', [BloqueController::class, 'destroy'])->whereNumber('bloque')->middleware('permiso:bloques.delete')->name('bloques.destroy');
    Route::post('bloques/{bloque}/horarios', [BloqueController::class, 'agregarHorario'])->whereNumber('bloque')->middleware('permiso:bloques.manage')->name('bloques.horarios.store');
    Route::delete('bloque-horarios/{horario}', [BloqueController::class, 'quitarHorario'])->whereNumber('horario')->middleware('permiso:bloques.manage')->name('bloques.horarios.destroy');
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

    // Comprobantes de cuota: gestión y envío propio del alumno
    Route::get('comprobantes/opciones', [ComprobanteController::class, 'opciones'])->name('comprobantes.opciones');
    Route::get('comprobantes/{comprobante}', [ComprobanteController::class, 'show'])->whereNumber('comprobante')->name('comprobantes.show');
    Route::get('comprobantes/{comprobante}/archivo', [ComprobanteController::class, 'archivo'])->whereNumber('comprobante')->name('comprobantes.archivo');
    Route::middleware(['permiso:comprobantes.view', 'modulo:comprobantes'])->group(function () {
        Route::get('comprobantes', [ComprobanteController::class, 'index'])->name('comprobantes.index');
        Route::post('comprobantes', [ComprobanteController::class, 'store'])->middleware('permiso:comprobantes.create')->name('comprobantes.store');
        Route::post('comprobantes/{comprobante}/visto', [ComprobanteController::class, 'visto'])->whereNumber('comprobante')->name('comprobantes.visto');
        Route::post('comprobantes/{comprobante}/aprobar', [ComprobanteController::class, 'aprobar'])->whereNumber('comprobante')->middleware('permiso:comprobantes.approve')->name('comprobantes.aprobar');
    });
    Route::get('mi/comprobantes', [ComprobanteController::class, 'mios'])->name('mi.comprobantes');
    Route::post('mi/comprobantes', [ComprobanteController::class, 'enviarPropio'])->middleware('throttle:10,1')->name('mi.comprobantes.store');

    Route::get('alumnos', [AlumnoController::class, 'index'])->name('alumnos.index');
    Route::get('alumnos/catalogo', [AlumnoController::class, 'catalogo'])->name('alumnos.catalogo');
    Route::post('alumnos', [AlumnoController::class, 'store'])->middleware('permiso:alumnos.create')->name('alumnos.store');
    Route::get('alumnos/{alumno}', [AlumnoController::class, 'show'])->whereNumber('alumno')->name('alumnos.show');
    Route::put('alumnos/{alumno}', [AlumnoController::class, 'update'])->whereNumber('alumno')->middleware('permiso:alumnos.update')->name('alumnos.update');
    Route::delete('alumnos/{alumno}', [AlumnoController::class, 'destroy'])->whereNumber('alumno')->middleware('permiso:alumnos.delete')->name('alumnos.destroy');
    Route::get('alumnos/{alumno}/seguimiento', [SeguimientoController::class, 'index'])->whereNumber('alumno')->name('seguimiento.index');
    Route::post('seguimiento', [SeguimientoController::class, 'store'])->middleware('permiso:seguimiento.create')->name('seguimiento.store');
    Route::delete('seguimiento/{observacion}', [SeguimientoController::class, 'destroy'])->whereNumber('observacion')->middleware('permiso:seguimiento.create')->name('seguimiento.destroy');
    Route::get('alumnos/{alumno}/estado-cuenta', [AlumnoController::class, 'estadoCuenta'])->name('alumnos.estado-cuenta');
    Route::get('mi/estado-cuenta', [AlumnoController::class, 'miEstadoCuenta'])->name('mi.estado-cuenta');

    // Cuotas
    Route::get('cuotas/catalogo', [CuotaController::class, 'catalogo'])->name('cuotas.catalogo');
    Route::get('cuotas', [CuotaController::class, 'index'])->name('cuotas.index');
    Route::post('cuotas', [CuotaController::class, 'store'])->middleware('permiso:cuotas.create')->name('cuotas.store');
    Route::get('cuotas/{cuota}', [CuotaController::class, 'show'])->whereNumber('cuota')->name('cuotas.show');
    Route::put('cuotas/{cuota}', [CuotaController::class, 'update'])->whereNumber('cuota')->middleware('permiso:cuotas.update')->name('cuotas.update');
    Route::delete('cuotas/{cuota}', [CuotaController::class, 'destroy'])->whereNumber('cuota')->middleware('permiso:cuotas.delete')->name('cuotas.destroy');
    // Facturación mensual y cierre de mes
    Route::middleware('permiso:facturacion.view')->group(function () {
        Route::get('facturacion', [FacturacionController::class, 'index'])->name('facturacion.index');
        Route::get('facturacion/catalogo', [FacturacionController::class, 'catalogo'])->name('facturacion.catalogo');
        Route::get('facturacion/cierre-mes', [FacturacionController::class, 'cierreMes'])->name('facturacion.cierre');
        Route::get('facturacion/{facturacion}', [FacturacionController::class, 'show'])->whereNumber('facturacion')->name('facturacion.show');
        Route::post('facturacion', [FacturacionController::class, 'store'])->middleware('permiso:facturacion.manage')->name('facturacion.store');
        Route::put('facturacion/{facturacion}', [FacturacionController::class, 'update'])->whereNumber('facturacion')->middleware('permiso:facturacion.manage')->name('facturacion.update');
    });

    // Compras
    Route::middleware('permiso:compras.view')->group(function () {
        Route::get('compras/catalogo', [CompraController::class, 'catalogo'])->name('compras.catalogo');
        Route::get('compras/plan', [CompraController::class, 'plan'])->name('compras.plan');
        Route::get('compras', [CompraController::class, 'index'])->name('compras.index');
        Route::get('compras/{orden}', [CompraController::class, 'show'])->whereNumber('orden')->name('compras.show');
        Route::middleware('permiso:compras.create')->group(function () {
            Route::post('compras', [CompraController::class, 'store'])->name('compras.store');
            Route::put('compras/{orden}', [CompraController::class, 'update'])->whereNumber('orden')->name('compras.update');
            Route::post('compras/{orden}/estado', [CompraController::class, 'estado'])->whereNumber('orden')->name('compras.estado');
            Route::delete('compras/{orden}', [CompraController::class, 'destroy'])->whereNumber('orden')->name('compras.destroy');
        });
    });

    // Gira a Villa Gesell
    Route::middleware(['permiso:villa_gesell.manage', 'modulo:admin.villa_gesell'])->prefix('villa-gesell')->name('villa-gesell.')->group(function () {
        Route::get('/', [VillaGesellController::class, 'resumen'])->name('resumen');
        Route::put('config', [VillaGesellController::class, 'actualizarConfig'])->name('config');
        Route::get('catalogo', [VillaGesellController::class, 'catalogo'])->name('catalogo');
        Route::get('inscriptos', [VillaGesellController::class, 'inscriptos'])->name('inscriptos.index');
        Route::get('inscriptos/nueva', [VillaGesellController::class, 'nuevaInscripcion'])->name('inscriptos.nueva');
        Route::post('inscriptos', [VillaGesellController::class, 'inscribir'])->name('inscriptos.store');
        Route::get('inscriptos/{inscripto}', [VillaGesellController::class, 'verInscripto'])->whereNumber('inscripto')->name('inscriptos.show');
        Route::put('inscriptos/{inscripto}', [VillaGesellController::class, 'actualizarInscripcion'])->whereNumber('inscripto')->name('inscriptos.update');
        Route::delete('inscriptos/{inscripto}', [VillaGesellController::class, 'eliminarInscripcion'])->whereNumber('inscripto')->name('inscriptos.destroy');
        Route::get('alumnos-disponibles', [VillaGesellController::class, 'alumnosDisponibles'])->name('alumnos-disponibles');
        Route::post('alumnos-rapidos', [VillaGesellController::class, 'alumnoRapido'])->name('alumnos-rapidos');
        Route::post('profesores-rapidos', [VillaGesellController::class, 'profesorRapido'])->name('profesores-rapidos');
        Route::post('bloques-rapidos', [VillaGesellController::class, 'bloqueRapido'])->name('bloques-rapidos');
        Route::get('calendario', [VillaGesellController::class, 'calendario'])->name('calendario');
        Route::post('dias/generar', [VillaGesellController::class, 'generarDias'])->name('dias.generar');
        Route::put('dias/{dia}', [VillaGesellController::class, 'actualizarDia'])->whereNumber('dia')->name('dias.update');
        Route::post('dias/{dia}/slots', [VillaGesellController::class, 'generarTocadas'])->whereNumber('dia')->name('dias.slots');
        Route::post('dias/{dia}/tocadas', [VillaGesellController::class, 'agregarTocada'])->whereNumber('dia')->name('tocadas.store');
        Route::put('tocadas/{tocada}', [VillaGesellController::class, 'actualizarTocada'])->whereNumber('tocada')->name('tocadas.update');
        Route::delete('tocadas/{tocada}', [VillaGesellController::class, 'eliminarTocada'])->whereNumber('tocada')->name('tocadas.destroy');
        Route::get('gastos', [VillaGesellController::class, 'gastos'])->name('gastos.index');
        Route::post('gastos', [VillaGesellController::class, 'guardarGasto'])->name('gastos.store');
        Route::put('gastos/{gasto}', [VillaGesellController::class, 'guardarGasto'])->whereNumber('gasto')->name('gastos.update');
        Route::delete('gastos/{gasto}', [VillaGesellController::class, 'eliminarGasto'])->whereNumber('gasto')->name('gastos.destroy');
        Route::get('insumos', [VillaGesellController::class, 'insumos'])->name('insumos.index');
        Route::post('insumos', [VillaGesellController::class, 'guardarInsumo'])->name('insumos.store');
        Route::put('insumos/{insumo}', [VillaGesellController::class, 'guardarInsumo'])->whereNumber('insumo')->name('insumos.update');
        Route::delete('insumos/{insumo}', [VillaGesellController::class, 'eliminarInsumo'])->whereNumber('insumo')->name('insumos.destroy');
    });

    // Diseño: la misma API JSON que usa el editor web (DisenoEditorController + DisenoPolicy)
    Route::middleware(['permiso:disenos.manage', 'modulo:admin.disenos', 'throttle:300,1'])->prefix('disenos')->name('disenos.')->group(function () {
        Route::get('/', [DisenoEditorController::class, 'index'])->name('index');
        Route::post('/', [DisenoEditorController::class, 'store'])->name('store');
        Route::get('plantillas', [DisenoEditorController::class, 'templates'])->name('plantillas');
        Route::get('plantillas/{id}', [DisenoEditorController::class, 'template'])->name('plantilla');
        Route::get('marca', [DisenoEditorController::class, 'marca'])->name('marca');
        Route::post('marca/kit', [DisenoEditorController::class, 'kitStore'])->middleware('throttle:30,1')->name('kit.store');
        Route::delete('marca/kit/{kit}', [DisenoEditorController::class, 'kitDestroy'])->whereNumber('kit')->name('kit.destroy');
        Route::post('imagenes', [DisenoEditorController::class, 'upload'])->middleware('throttle:60,1')->name('imagenes');
        Route::get('{diseno}', [DisenoEditorController::class, 'show'])->whereNumber('diseno')->name('show');
        Route::put('{diseno}', [DisenoEditorController::class, 'update'])->whereNumber('diseno')->name('update');
        Route::delete('{diseno}', [DisenoEditorController::class, 'destroy'])->whereNumber('diseno')->name('destroy');
        Route::post('{diseno}/paginas', [DisenoEditorController::class, 'storePage'])->whereNumber('diseno')->name('paginas.store');
        Route::post('paginas/{pagina}/duplicar', [DisenoEditorController::class, 'duplicatePage'])->whereNumber('pagina')->name('paginas.duplicar');
        Route::put('paginas/{pagina}', [DisenoEditorController::class, 'updatePage'])->whereNumber('pagina')->name('paginas.update');
        Route::delete('paginas/{pagina}', [DisenoEditorController::class, 'destroyPage'])->whereNumber('pagina')->name('paginas.destroy');
    });

    // Biblioteca (consulta y publicación para todos; moderación con permiso)
    Route::get('biblioteca', [BibliotecaController::class, 'index'])->name('biblioteca.index');
    Route::get('biblioteca/catalogo', [BibliotecaController::class, 'catalogo'])->name('biblioteca.catalogo');
    Route::post('biblioteca', [BibliotecaController::class, 'store'])->middleware('throttle:10,1')->name('biblioteca.store');
    Route::get('biblioteca/{item}', [BibliotecaController::class, 'show'])->whereNumber('item')->name('biblioteca.show');
    Route::get('biblioteca/{item}/archivo', [BibliotecaController::class, 'archivo'])->whereNumber('item')->name('biblioteca.archivo');
    Route::post('biblioteca/{item}/visibilidad', [BibliotecaController::class, 'visibilidad'])->whereNumber('item')->middleware('permiso:biblioteca.admin')->name('biblioteca.visibilidad');
    Route::delete('biblioteca/{item}', [BibliotecaController::class, 'destroy'])->whereNumber('item')->middleware('permiso:biblioteca.admin')->name('biblioteca.destroy');

    // Reportes
    Route::middleware('permiso:reportes.view')->group(function () {
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('reportes/excel', [ReporteController::class, 'excel'])->middleware('throttle:10,1')->name('reportes.excel');
        Route::get('reportes/imprimible', [ReporteController::class, 'imprimible'])->middleware('throttle:10,1')->name('reportes.imprimible');
        Route::get('reportes/profesores', [ReporteController::class, 'profesores'])->name('reportes.profesores');
    });

    // Gastos
    Route::get('gastos/catalogo', [GastoController::class, 'catalogo'])->name('gastos.catalogo');
    Route::get('gastos', [GastoController::class, 'index'])->name('gastos.index');
    Route::post('gastos', [GastoController::class, 'store'])->middleware('permiso:gastos.create')->name('gastos.store');
    Route::get('gastos/{gasto}', [GastoController::class, 'show'])->whereNumber('gasto')->name('gastos.show');
    Route::put('gastos/{gasto}', [GastoController::class, 'update'])->whereNumber('gasto')->middleware('permiso:gastos.update')->name('gastos.update');
    Route::delete('gastos/{gasto}', [GastoController::class, 'destroy'])->whereNumber('gasto')->middleware('permiso:gastos.delete')->name('gastos.destroy');
    Route::post('gastos/{gasto}/decision', [GastoController::class, 'decidir'])->whereNumber('gasto')->middleware('permiso:gastos.approve')->name('gastos.decidir');

    // Pagos
    Route::get('pagos/cuotas-para-cobrar', [PagoController::class, 'cuotasParaCobrar'])->name('pagos.cuotas');
    Route::get('pagos/cuotas/{cuota}/alumnos', [PagoController::class, 'alumnosParaCuota'])->whereNumber('cuota')->name('pagos.alumnos-cuota');
    Route::get('mi/pagos-docente', [PagoController::class, 'misPagosDocente'])->name('mi.pagos-docente');
    Route::get('pagos', [PagoController::class, 'index'])->name('pagos.index');
    Route::post('pagos', [PagoController::class, 'store'])->middleware('permiso:pagos.create')->name('pagos.store');
    Route::get('pagos/{pago}', [PagoController::class, 'show'])->whereNumber('pago')->name('pagos.show');
    Route::put('pagos/{pago}', [PagoController::class, 'update'])->whereNumber('pago')->middleware('permiso:pagos.update')->name('pagos.update');
    Route::post('pagos/{pago}/anular', [PagoController::class, 'anular'])->whereNumber('pago')->name('pagos.anular');
    Route::get('pagos/{pago}/comprobante', [PagoController::class, 'comprobante'])->whereNumber('pago')->name('pagos.comprobante');

    // Eventos y shows
    Route::get('eventos/catalogo', [EventoController::class, 'catalogo'])->middleware('permiso:eventos.view')->name('eventos.catalogo');
    Route::get('eventos', [EventoController::class, 'index'])->name('eventos.index');
    Route::post('eventos', [EventoController::class, 'store'])->middleware('permiso:eventos.create')->name('eventos.store');
    Route::get('eventos/{evento}', [EventoController::class, 'show'])->whereNumber('evento')->name('eventos.show');
    Route::put('eventos/{evento}', [EventoController::class, 'update'])->whereNumber('evento')->middleware('permiso:eventos.update')->name('eventos.update');
    Route::delete('eventos/{evento}', [EventoController::class, 'destroy'])->whereNumber('evento')->middleware('permiso:eventos.delete')->name('eventos.destroy');
    Route::get('shows', [ShowController::class, 'index'])->middleware('permiso:shows.view')->name('shows.index');
    Route::post('shows', [ShowController::class, 'store'])->middleware('permiso:shows.manage')->name('shows.store');
    Route::get('shows/{show}', [ShowController::class, 'show'])->whereNumber('show')->middleware('permiso:shows.view')->name('shows.show');
    Route::put('shows/{show}', [ShowController::class, 'update'])->whereNumber('show')->middleware('permiso:shows.manage')->name('shows.update');
    Route::delete('shows/{show}', [ShowController::class, 'destroy'])->whereNumber('show')->middleware('permiso:shows.manage')->name('shows.destroy');
    Route::get('calendario', [AgendaController::class, 'calendario'])->name('calendario');

    Route::get('inventario', [InventarioController::class, 'index'])->name('inventario.index');
    Route::get('inventario/catalogos', [InventarioController::class, 'catalogos'])->name('inventario.catalogos');
    Route::get('inventario/codigo/{codigo}', [InventarioController::class, 'porCodigo'])->where('codigo', '.+')->name('inventario.codigo');
    Route::post('inventario', [InventarioController::class, 'store'])->name('inventario.store');
    Route::get('inventario/{item}', [InventarioController::class, 'show'])->whereNumber('item')->name('inventario.show');
    Route::put('inventario/{item}', [InventarioController::class, 'update'])->whereNumber('item')->name('inventario.update');
    Route::delete('inventario/{item}', [InventarioController::class, 'destroy'])->whereNumber('item')->middleware('permiso:inventario.delete')->name('inventario.destroy');
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
    Route::get('usuarios/catalogo', [UsuarioController::class, 'catalogo'])->name('usuarios.catalogo');
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('usuarios', [UsuarioController::class, 'store'])->middleware('permiso:usuarios.create')->name('usuarios.store');
    Route::get('usuarios/{usuario}', [UsuarioController::class, 'show'])->whereNumber('usuario')->name('usuarios.show');
    Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])->whereNumber('usuario')->middleware('permiso:usuarios.update')->name('usuarios.update');
    Route::post('usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->whereNumber('usuario')->middleware('permiso:usuarios.update')->name('usuarios.estado');
    Route::post('usuarios/{usuario}/resetear-acceso', [UsuarioController::class, 'resetear'])->whereNumber('usuario')->middleware('permiso:usuarios.update')->name('usuarios.resetear');
    Route::post('usuarios/{usuario}/asignaciones', [AccesosController::class, 'asignar'])->name('usuarios.asignar');
    Route::delete('usuarios/{usuario}/asignaciones/{asignacion}', [AccesosController::class, 'quitar'])->name('usuarios.quitar');
});
