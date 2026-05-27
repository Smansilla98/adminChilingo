@extends('layouts.app')

@section('title', 'Ayuda')
@section('page-title', 'Ayuda — Guía de uso')

@section('content')
<div class="card">
    <div class="card-body">
        <h2 class="h5 mb-1">¿Qué hace cada botón?</h2>
        <p class="text-muted mb-3">Guía simple del sistema, ordenada por módulos y pensada para uso fácil. Si buscás algo específico, escribilo abajo (por ejemplo: <em>pago</em>, <em>alumno</em>, <em>cuota</em>, <em>comprobante</em>, <em>asistencia</em>).</p>

        <div class="mb-3">
            <label for="ayudaBuscar" class="form-label">Buscar en la guía</label>
            <input type="text" id="ayudaBuscar" class="form-control" placeholder="Escribí una palabra…">
            <div class="form-text">Consejo: probá palabras cortas. Ej.: “guardar”, “editar”, “abono”, “comprobante”.</div>
        </div>

        <div class="accordion" id="ayudaAccordion">
            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-menu">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#c-menu" aria-expanded="true" aria-controls="c-menu">
                        1) Menú lateral (orden real de módulos)
                    </button>
                </h3>
                <div id="c-menu" class="accordion-collapse collapse show" aria-labelledby="h-menu" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-2">Para todos</div>
                                    <ul class="mb-0">
                                        <li><strong>Inicio</strong>: pantalla principal.</li>
                                        <li><strong>Programa</strong>: material de estudio.</li>
                                        <li><strong>Calendario</strong>: actividades y fechas.</li>
                                        <li><strong>Guía de uso</strong>: esta ayuda.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-2">Según tu rol</div>
                                    @if(auth()->user()->isAdmin())
                                        <div class="text-muted small mb-2">Si sos administración, además vas a ver:</div>
                                        <ul class="mb-0">
                                            <li><strong>Alumnos</strong> y <strong>Importar</strong>.</li>
                                            <li><strong>Cuotas</strong>, <strong>Comprobantes</strong> y <strong>Reportes</strong>.</li>
                                            <li>Y más módulos dentro del <strong>Panel (Inicio)</strong> (ej.: Pagos, Sedes, Bloques, etc.).</li>
                                        </ul>
                                    @else
                                        <div class="text-muted small mb-2">Si sos profesor, además vas a ver:</div>
                                        <ul class="mb-0">
                                            <li><strong>Asistencia</strong>.</li>
                                            <li><strong>Mis alumnos</strong>.</li>
                                            <li><strong>Pagos cuotas</strong> (solo lectura).</li>
                                            <li><strong>Comprobantes</strong>.</li>
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-botones">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-botones" aria-expanded="false" aria-controls="c-botones">
                        2) Botones y campos (lo básico)
                    </button>
                </h3>
                <div id="c-botones" class="accordion-collapse collapse" aria-labelledby="h-botones" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">Guardar / Actualizar</div>
                                    <div class="text-muted small">Guarda lo que completaste. Recomendación: completá lo mínimo, tocá Guardar una sola vez.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">Cancelar / Volver</div>
                                    <div class="text-muted small">Vuelve atrás sin guardar. Si te confundiste, usalo sin miedo.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">Editar (lápiz)</div>
                                    <div class="text-muted small">Permite cambiar datos ya cargados. Recomendación: cambiá 1 cosa por vez y guardá.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">Ver</div>
                                    <div class="text-muted small">Muestra la ficha completa. Sirve para “mirar”, no modifica.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">+ Añadir…</div>
                                    <div class="text-muted small">Agrega una fila (sección, enlace, línea, recurso). Úsalo solo si necesitás “otra”.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">× Quitar</div>
                                    <div class="text-muted small">Elimina esa fila de la pantalla. Útil si agregaste algo de más.</div>
                                </div>
                            </div>
                        </div>
                        <hr class="my-3">
                        <div class="fw-semibold mb-2">Campos (qué significa cada uno)</div>
                        <ul class="mb-0">
                            <li><strong>Caja de texto</strong>: escribís una palabra o frase.</li>
                            <li><strong>Lista desplegable</strong>: elegís una opción (Sede, Instrumento, Cuota).</li>
                            <li><strong>Casilla ✓</strong>: activa/desactiva (Activo, Visible, Registrar abono al profesor).</li>
                            <li><strong>Botón redondo</strong>: elige uno solo (por ejemplo “Bloque principal”).</li>
                            <li><strong>Adjuntar archivo</strong>: sube PDF o imagen (Comprobante, Partitura).</li>
                        </ul>
                        <div class="alert alert-secondary py-2 small mt-3 mb-0">
                            Consejos: si no encontrás un botón, <strong>bajá</strong>. Si “no aparece” alguien, revisá <strong>filtros</strong>. Si dudás, tocá <strong>Cancelar</strong>.
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-entrar">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-campos" aria-expanded="false" aria-controls="c-campos">
                        3) Entrar, salir y usar en celular
                    </button>
                </h3>
                <div id="c-campos" class="accordion-collapse collapse" aria-labelledby="h-entrar" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <ul class="mb-0">
                            <li><strong>Entrar (login)</strong>: escribí tu correo/usuario y contraseña, y tocá “Entrar”.</li>
                            <li><strong>Cerrar sesión</strong>: abajo a la izquierda (en tu usuario) → “Cerrar sesión”.</li>
                            <li><strong>En celular</strong>: el botón <strong>☰</strong> (arriba) abre/cierra el menú lateral.</li>
                            <li><strong>Si no ves algo</strong>: bajá hasta el final o sacá filtros.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-alumnos">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-alumnos" aria-expanded="false" aria-controls="c-alumnos">
                        4) Para todos — Programa
                    </button>
                </h3>
                <div id="c-alumnos" class="accordion-collapse collapse" aria-labelledby="h-alumnos" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="fw-semibold mb-2">¿Para qué sirve?</div>
                        <p class="mb-2">El <strong>Programa</strong> tiene los toques y su material: texto, videos, partitura y recursos.</p>
                        <div class="fw-semibold mb-2">Uso simple</div>
                        <ol class="mb-0">
                            <li>Entrá a <strong>Programa</strong>.</li>
                            <li>Elegí el toque.</li>
                            <li>Si hay videos, podés tocarlos para verlos.</li>
                        </ol>
                        @if(auth()->user()->isAdmin())
                        <hr class="my-3">
                        <div class="fw-semibold mb-2">Si sos admin: cargar material (pasos cortos)</div>
                        <ol class="mb-0">
                            <li>Tocar <strong>Editar profundización</strong>.</li>
                            <li>Empezá por: <strong>Resumen</strong> + <strong>Texto principal</strong> + <strong>1 video</strong>.</li>
                            <li>Después agregá: partitura, videos por tambor, cortes y recursos.</li>
                            <li>Tocar <strong>Guardar</strong>.</li>
                        </ol>
                        @endif
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-profesores">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-profesores" aria-expanded="false" aria-controls="c-profesores">
                        5) Para todos — Calendario
                    </button>
                </h3>
                <div id="c-profesores" class="accordion-collapse collapse" aria-labelledby="h-profesores" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="fw-semibold mb-2">¿Para qué sirve?</div>
                        <p class="mb-2">El <strong>Calendario</strong> muestra eventos y fechas importantes.</p>
                        <div class="fw-semibold mb-2">Uso simple</div>
                        <ol class="mb-0">
                            <li>Entrá a <strong>Calendario</strong>.</li>
                            <li>Elegí el mes y mirá los eventos.</li>
                            <li>Si no ves nada, probá recargar la página.</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-pagos">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-pagos" aria-expanded="false" aria-controls="c-pagos">
                        6) Para profesores — Asistencia, Mis alumnos, Pagos cuotas y Comprobantes
                    </button>
                </h3>
                <div id="c-pagos" class="accordion-collapse collapse" aria-labelledby="h-pagos" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="alert alert-secondary py-2 small">
                            Si no sos profesor, puede que estas opciones no te aparezcan en el menú.
                        </div>
                        <div class="fw-semibold mb-2">Asistencia</div>
                        <ol>
                            <li>Entrá a <strong>Asistencia</strong>.</li>
                            <li>Elegí el bloque o la clase (si corresponde).</li>
                            <li>Marcá <strong>Presente</strong> o <strong>Ausente</strong>.</li>
                            <li>Tocá <strong>Guardar</strong>.</li>
                        </ol>
                        <div class="fw-semibold mb-2">Mis alumnos</div>
                        <ul>
                            <li>Sirve para ver tus alumnos por bloque y entrar a su ficha.</li>
                            <li>En la ficha se ve instrumentos, bloques y estado de cuenta.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Pagos cuotas (solo lectura)</div>
                        <ul>
                            <li>Sirve para ver pagos registrados por cuota (no para cargar nuevos).</li>
                        </ul>
                        <div class="fw-semibold mb-2">Comprobantes</div>
                        <ul class="mb-0">
                            <li>Sirve para revisar comprobantes enviados por alumnos.</li>
                            <li>Podés abrir el comprobante y marcar “visto”.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-cuotas">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-cuotas" aria-expanded="false" aria-controls="c-cuotas">
                        7) Para administración — Panel (Inicio) y “Acciones” de arriba
                    </button>
                </h3>
                <div id="c-cuotas" class="accordion-collapse collapse" aria-labelledby="h-cuotas" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="alert alert-secondary py-2 small">
                            Esta sección aplica principalmente a usuarios de administración.
                        </div>
                        <div class="fw-semibold mb-2">Panel (Inicio)</div>
                        <ul>
                            <li>Las tarjetas de arriba son un <strong>resumen</strong> (alumnos activos, cuotas abonadas, pendientes).</li>
                            <li>Más abajo hay <strong>botones</strong> para entrar a listados (Alumnos, Profesores, Sedes, Pagos, etc.).</li>
                        </ul>
                        <div class="fw-semibold mb-2">Acciones (arriba a la derecha)</div>
                        <ul class="mb-0">
                            <li><strong>+ Alumno</strong>: crear alumno rápido.</li>
                            <li><strong>+ Profesor</strong>: crear profesor rápido.</li>
                            <li><strong>+ Bloque</strong>: crear bloque rápido.</li>
                            <li><strong>Registrar pago</strong>: ir directo a cargar un pago.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-programa">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-programa" aria-expanded="false" aria-controls="c-programa">
                        8) Para administración — Alumnos
                    </button>
                </h3>
                <div id="c-programa" class="accordion-collapse collapse" aria-labelledby="h-programa" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="fw-semibold mb-2">Crear alumno (modo simple)</div>
                        <ol>
                            <li>Ir a <strong>Alumnos</strong> → <strong>Nuevo</strong>.</li>
                            <li>Completar lo mínimo: <strong>Nombre</strong>, <strong>Fecha de nacimiento</strong>, <strong>Instrumento principal</strong>, <strong>Sede</strong>.</li>
                            <li>En <strong>Bloques</strong>: marcar los que corresponden y elegir el <strong>principal</strong>.</li>
                            <li>Tocar <strong>Guardar</strong>.</li>
                        </ol>
                        <div class="fw-semibold mb-2">Ficha del alumno</div>
                        <ul class="mb-0">
                            <li>Vas a ver: bloques, instrumentos, historial de pagos y estado de cuenta.</li>
                            <li>Si también es profesor, aparece un aviso con enlace al perfil docente.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-reglas">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-reglas" aria-expanded="false" aria-controls="c-reglas">
                        9) Para administración — Profesores, Bloques, Sedes (incl. liquidación)
                    </button>
                </h3>
                <div id="c-reglas" class="accordion-collapse collapse" aria-labelledby="h-reglas" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="fw-semibold mb-2">Profesores</div>
                        <ul>
                            <li>Asigná <strong>bloques</strong> y <strong>rol</strong> (Titular/Ayudante/Suplente/Coordinador de clase).</li>
                            <li>En <strong>Roles por sede</strong>: profesor/encargado/coordinador.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Bloques</div>
                        <ul>
                            <li>Un bloque es un grupo de clase en una sede.</li>
                            <li>Se usa para ordenar alumnos, cuotas y asistencias.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Sedes y liquidación docente</div>
                        <ul class="mb-0">
                            <li>En Sede podés definir: <strong>Retención escuela</strong> y <strong>% docente</strong>.</li>
                            <li>Esto permite que en Pagos el <strong>abono docente</strong> se calcule automático si el total queda vacío.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-admin-pagos">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-admin-pagos" aria-expanded="false" aria-controls="c-admin-pagos">
                        10) Para administración — Cuotas, Pagos y Comprobantes
                    </button>
                </h3>
                <div id="c-admin-pagos" class="accordion-collapse collapse" aria-labelledby="h-admin-pagos" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="fw-semibold mb-2">Cuotas</div>
                        <ul>
                            <li>Campos mínimos: <strong>Nombre</strong>, <strong>Mes</strong>, <strong>Año</strong>, <strong>Monto</strong>.</li>
                            <li>Si aparece <strong>Alcance</strong>: bloque / sede / general.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Pagos (registrar)</div>
                        <ol>
                            <li>Ir a <strong>Pagos</strong> → <strong>Registrar pago</strong>.</li>
                            <li>Completar <strong>Fecha</strong> + líneas (Cuota + Alumno + Monto) + <strong>Total</strong>.</li>
                            <li>Comprobante: opcional (PDF o imagen).</li>
                        </ol>
                        <div class="fw-semibold mb-2">Abono al profesor (automático)</div>
                        <ul>
                            <li>Si dejás vacío el total de abono, se usa la regla de la sede.</li>
                            <li>Si completás un total manual, se reparte proporcionalmente por líneas.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Comprobantes (interno)</div>
                        <ul class="mb-0">
                            <li>Revisar comprobantes enviados y marcar “visto”.</li>
                            <li><strong>Formulario público</strong>: es un link externo para que el alumno suba su comprobante sin iniciar sesión.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-admin-resto">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-admin-resto" aria-expanded="false" aria-controls="c-admin-resto">
                        11) Para administración — Eventos, Asistencias, Shows, Inventario y Reportes
                    </button>
                </h3>
                <div id="c-admin-resto" class="accordion-collapse collapse" aria-labelledby="h-admin-resto" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <ul class="mb-0">
                            <li><strong>Eventos</strong>: crear y ver actividades.</li>
                            <li><strong>Asistencias</strong>: registrar asistencia (si se usa desde administración).</li>
                            <li><strong>Shows</strong>: programación de shows (si aplica).</li>
                            <li><strong>Facturación mensual</strong>: resumen mensual (si aplica).</li>
                            <li><strong>Inventarios</strong>: stock por sede (si aplica).</li>
                            <li><strong>Plan de compras</strong> y <strong>Órdenes de compra</strong>: compras (si aplica).</li>
                            <li><strong>Gastos</strong>: registrar egresos.</li>
                            <li><strong>Reportes</strong>: ver resúmenes y listados.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-accesos">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-accesos" aria-expanded="false" aria-controls="c-accesos">
                        12) Accesos por usuario (administración)
                    </button>
                </h3>
                <div id="c-accesos" class="accordion-collapse collapse" aria-labelledby="h-accesos" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <p class="mb-2">Como admin podés decidir qué módulos ve cada usuario en la <strong>Matriz de accesos</strong> (<code>/accesos</code>).</p>
                        <ol class="mb-0">
                            <li>Elegí el usuario.</li>
                            <li>Marcá qué módulos puede ver/usar.</li>
                            <li>Tocá <strong>Guardar accesos</strong>.</li>
                        </ol>
                        <div class="alert alert-warning py-2 small mt-3 mb-0">
                            Si un usuario “no ve” un módulo, puede ser por permisos o por accesos desactivados.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 small text-muted">
            ¿Seguís con dudas? Probá volver al menú y entrar de nuevo. Si algo no aparece, revisá filtros y bajá hasta el final.
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ayuda.css') }}?v=1">
@endpush

@push('scripts')
<script>
(function () {
    const input = document.getElementById('ayudaBuscar');
    const items = Array.from(document.querySelectorAll('[data-ayuda-item]'));
    if (!input || items.length === 0) return;

    function norm(s) {
        return (s || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
    }

    function filtrar() {
        const q = norm(input.value);
        items.forEach(function (it) {
            const text = norm(it.textContent);
            const ok = q === '' || text.includes(q);
            it.classList.toggle('d-none', !ok);
        });
    }

    input.addEventListener('input', filtrar);
})();
</script>
@endpush

