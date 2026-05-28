@extends('layouts.app')

@section('title', 'Ayuda')
@section('page-title', 'Ayuda — Guía de uso')

@section('content')
<div class="card">
    <div class="card-body">
        <h2 class="h5 mb-1">Guía del sistema</h2>
        <p class="text-muted mb-3">Todo explicado con palabras simples. Si tenés una duda puntual, escribila en el buscador de abajo (por ejemplo: <em>pago</em>, <em>alumno</em>, <em>cuota</em>).</p>

        <div class="mb-3">
            <label for="ayudaBuscar" class="form-label">Buscar en esta guía</label>
            <input type="text" id="ayudaBuscar" class="form-control" placeholder="Una palabra alcanza…">
            <div class="form-text">Tip: probá con una sola palabra, como “guardar” o “comprobante”.</div>
        </div>

        <div class="accordion" id="ayudaAccordion">
            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-menu">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#c-menu" aria-expanded="true" aria-controls="c-menu">
                        1) Menú de la izquierda
                    </button>
                </h3>
                <div id="c-menu" class="accordion-collapse collapse show" aria-labelledby="h-menu" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-2">Todos ven</div>
                                    <ul class="mb-0">
                                        <li><strong>Inicio</strong>: resumen y accesos rápidos.</li>
                                        <li><strong>Programa</strong>: toques y material para practicar.</li>
                                        <li><strong>Calendario</strong>: fechas y actividades.</li>
                                        <li><strong>Guía de uso</strong>: esta página.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-2">Según quién entraste</div>
                                    @if(auth()->user()->isAdmin())
                                        <div class="text-muted small mb-2">Si administrás la escuela, también tenés:</div>
                                        <ul class="mb-0">
                                            <li><strong>Alumnos</strong> e <strong>Importar</strong> (lista masiva).</li>
                                            <li><strong>Cuotas</strong>, <strong>Comprobantes</strong> y <strong>Reportes</strong>.</li>
                                            <li>Desde <strong>Inicio</strong> entrás a Pagos, Sedes, Bloques y más.</li>
                                        </ul>
                                    @else
                                        <div class="text-muted small mb-2">Si sos profesor/a, también tenés:</div>
                                        <ul class="mb-0">
                                            <li><strong>Asistencia</strong> — marcar quién vino.</li>
                                            <li><strong>Mis alumnos</strong> — ver tu grupo.</li>
                                            <li><strong>Pagos cuotas</strong> — solo mirar, no cargar.</li>
                                            <li><strong>Comprobantes</strong> — lo que envían los alumnos.</li>
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
                        2) Botones y campos (lo esencial)
                    </button>
                </h3>
                <div id="c-botones" class="accordion-collapse collapse" aria-labelledby="h-botones" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">Guardar / Actualizar</div>
                                    <div class="text-muted small">Guarda lo que escribiste. Completá lo indispensable y tocá una sola vez.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">Cancelar / Volver</div>
                                    <div class="text-muted small">Salís sin guardar. Si te equivocaste, usalo tranquilo.</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded">
                                    <div class="fw-semibold mb-1">Editar (lápiz)</div>
                                    <div class="text-muted small">Sirve para corregir algo ya guardado. Mejor cambiar de a una cosa y guardar.</div>
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
                                    <div class="text-muted small">Suma otra fila (otro pago, otro video, otra sección). Solo si hace falta.</div>
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
                        <div class="fw-semibold mb-2">Tipos de campo</div>
                        <ul class="mb-0">
                            <li><strong>Cuadro para escribir</strong>: nombre, notas, montos.</li>
                            <li><strong>Lista para elegir</strong>: sede, instrumento, cuota.</li>
                            <li><strong>Casilla ✓</strong>: sí o no (activo, publicado, abono al profe).</li>
                            <li><strong>Círculo</strong>: elegís una sola opción (bloque principal).</li>
                            <li><strong>Adjuntar archivo</strong>: PDF o foto (comprobante, partitura).</li>
                        </ul>
                        <div class="alert alert-secondary py-2 small mt-3 mb-0">
                            Si no ves un botón, <strong>bajá</strong> la pantalla. Si falta un nombre en una lista, mirá si hay un <strong>filtro</strong> arriba. Con dudas, <strong>Cancelar</strong> y volvé a empezar.
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-entrar">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-campos" aria-expanded="false" aria-controls="c-campos">
                        3) Entrar, salir y usar el celular
                    </button>
                </h3>
                <div id="c-campos" class="accordion-collapse collapse" aria-labelledby="h-entrar" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <ul class="mb-0">
                            <li><strong>Entrar</strong>: tu usuario o correo y la contraseña → botón “Entrar”.</li>
                            <li><strong>Salir</strong>: abajo a la izquierda, en tu nombre → “Cerrar sesión”.</li>
                            <li><strong>En el celular</strong>: el ícono <strong>☰</strong> (arriba) abre y cierra el menú.</li>
                            <li><strong>¿No ves algo?</strong> Bajá la pantalla o quitá filtros que estén puestos.</li>
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
                        <p class="mb-2">Acá están los toques con textos, videos y partituras para estudiar.</p>
                        <div class="fw-semibold mb-2">Cómo usarlo</div>
                        <ol class="mb-0">
                            <li>Tocá <strong>Programa</strong> en el menú.</li>
                            <li>Elegí el toque que quieras.</li>
                            <li>Los videos se abren al tocarlos.</li>
                        </ol>
                        @if(auth()->user()->isAdmin())
                        <hr class="my-3">
                        <div class="fw-semibold mb-2">Si cargás material (admin)</div>
                        <ol class="mb-0">
                            <li><strong>Editar página del toque</strong>.</li>
                            <li>Primero: un <strong>resumen</strong>, el <strong>texto</strong> y un <strong>video</strong> — alcanza para empezar.</li>
                            <li>Después, si querés: partitura, videos por tambor, cortes y más archivos.</li>
                            <li><strong>Guardar</strong> al terminar.</li>
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
                        <p class="mb-2">Fechas de clases, muestras y otras actividades.</p>
                        <div class="fw-semibold mb-2">Cómo usarlo</div>
                        <ol class="mb-0">
                            <li><strong>Calendario</strong> en el menú.</li>
                            <li>Cambiá de mes si hace falta.</li>
                            <li>Si está vacío, actualizá la página (F5 o recargar).</li>
                        </ol>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-pagos">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-pagos" aria-expanded="false" aria-controls="c-pagos">
                        6) Para profesores — Asistencia y alumnos
                    </button>
                </h3>
                <div id="c-pagos" class="accordion-collapse collapse" aria-labelledby="h-pagos" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="alert alert-secondary py-2 small">
                            Estas opciones solo aparecen si entraste como profesor/a.
                        </div>
                        <div class="fw-semibold mb-2">Asistencia</div>
                        <ol>
                            <li><strong>Asistencia</strong> en el menú.</li>
                            <li>Elegí tu bloque o clase.</li>
                            <li>Marcá quién vino y quién faltó.</li>
                            <li><strong>Guardar</strong>.</li>
                        </ol>
                        <div class="fw-semibold mb-2">Mis alumnos</div>
                        <ul>
                            <li>Lista de tu grupo; tocá un nombre para ver su ficha.</li>
                            <li>En la ficha: instrumento, bloques y si está al día con las cuotas.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Pagos de cuotas</div>
                        <ul>
                            <li>Solo para <strong>mirar</strong> pagos ya cargados (no para registrar nuevos).</li>
                        </ul>
                        <div class="fw-semibold mb-2">Comprobantes</div>
                        <ul class="mb-0">
                            <li>Lo que mandaron los alumnos (foto o PDF del pago).</li>
                            <li>Podés abrirlo y marcarlo como visto.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-cuotas">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-cuotas" aria-expanded="false" aria-controls="c-cuotas">
                        7) Para administración — Inicio y botones rápidos
                    </button>
                </h3>
                <div id="c-cuotas" class="accordion-collapse collapse" aria-labelledby="h-cuotas" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="alert alert-secondary py-2 small">
                            Solo si administrás la escuela.
                        </div>
                        <div class="fw-semibold mb-2">Pantalla Inicio</div>
                        <ul>
                            <li>Arriba: números resumidos (cuántos alumnos, cuotas pagadas, pendientes).</li>
                            <li>Abajo: accesos a Alumnos, Profesores, Sedes, Pagos y el resto.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Botones de arriba a la derecha</div>
                        <ul class="mb-0">
                            <li><strong>+ Alumno</strong> — alta rápida.</li>
                            <li><strong>+ Profesor</strong> — alta rápida.</li>
                            <li><strong>+ Bloque</strong> — nuevo grupo de clase.</li>
                            <li><strong>Registrar pago</strong> — cargar un pago al toque.</li>
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
                        <div class="fw-semibold mb-2">Dar de alta un alumno</div>
                        <ol>
                            <li><strong>Alumnos</strong> → <strong>Nuevo</strong>.</li>
                            <li>Lo imprescindible: <strong>nombre</strong>, <strong>fecha de nacimiento</strong>, <strong>instrumento</strong> y <strong>sede</strong>.</li>
                            <li>En <strong>Bloques</strong>: tildá en qué clases va y marcá cuál es la principal (círculo).</li>
                            <li><strong>Guardar</strong>.</li>
                        </ol>
                        <div class="fw-semibold mb-2">Ver la ficha</div>
                        <ul class="mb-0">
                            <li>Bloques, instrumentos, pagos y si debe algo.</li>
                            <li>Si la misma persona también da clases, verás un enlace a su perfil de profesor.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-reglas">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-reglas" aria-expanded="false" aria-controls="c-reglas">
                        9) Para administración — Profesores, bloques y sedes
                    </button>
                </h3>
                <div id="c-reglas" class="accordion-collapse collapse" aria-labelledby="h-reglas" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <div class="fw-semibold mb-2">Profesores</div>
                        <ul>
                            <li>Indicá en qué <strong>bloques</strong> da clase y con qué <strong>rol</strong> (titular, ayudante, etc.).</li>
                            <li>En <strong>Roles por sede</strong>: si es profe, encargado o coordinador de esa sede.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Bloques</div>
                        <ul>
                            <li>Cada bloque es un grupo de alumnos en una sede (ej. “Lunes 18 hs”).</li>
                            <li>Sirve para listas, cuotas y asistencia.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Sedes y pago al profesor</div>
                        <ul class="mb-0">
                            <li>En cada sede podés poner cuánto se queda la escuela y qué parte va al profe.</li>
                            <li>Al registrar un pago, si dejás vacío el monto del profe, el sistema lo calcula solo con esas reglas.</li>
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
                            <li>Necesitás: <strong>nombre</strong> (ej. “Marzo 2026”), <strong>mes</strong>, <strong>año</strong> y <strong>monto</strong>.</li>
                            <li><strong>Para quién es</strong>: toda la escuela, una sede o un bloque puntual.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Registrar un pago</div>
                        <ol>
                            <li><strong>Pagos</strong> → <strong>Registrar pago</strong>.</li>
                            <li>Fecha, luego cada fila: alumno + cuota + monto. El <strong>total</strong> debe coincidir con la suma de las filas.</li>
                            <li>Podés adjuntar el comprobante (foto o PDF), no es obligatorio.</li>
                        </ol>
                        <div class="fw-semibold mb-2">Parte del pago para el profesor</div>
                        <ul>
                            <li>Si dejás el monto en blanco, se calcula según lo que configuraste en la sede.</li>
                            <li>Si escribís un monto a mano, se reparte entre las filas del pago.</li>
                        </ul>
                        <div class="fw-semibold mb-2">Comprobantes</div>
                        <ul class="mb-0">
                            <li>Revisá lo que mandaron los alumnos y marcá “visto”.</li>
                            <li>El <strong>link público</strong> sirve para que suban el comprobante sin entrar al sistema.</li>
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
                            <li><strong>Eventos</strong> — cargar y ver actividades.</li>
                            <li><strong>Asistencias</strong> — quién vino a clase.</li>
                            <li><strong>Shows</strong>, <strong>Facturación</strong>, <strong>Inventario</strong>, <strong>Compras</strong> y <strong>Gastos</strong>: según lo que use la escuela.</li>
                            <li><strong>Reportes</strong> — listados y números para revisar.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-accesos">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-accesos" aria-expanded="false" aria-controls="c-accesos">
                        12) Quién puede ver qué (administración)
                    </button>
                </h3>
                <div id="c-accesos" class="accordion-collapse collapse" aria-labelledby="h-accesos" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <p class="mb-2">En <strong>Accesos</strong> (menú de administración) elegís qué partes del sistema puede usar cada persona.</p>
                        <ol class="mb-0">
                            <li>Elegí el usuario en la lista.</li>
                            <li>Tildá lo que puede ver (Programa, Alumnos, Pagos, etc.).</li>
                            <li><strong>Guardar accesos</strong>.</li>
                        </ol>
                        <div class="alert alert-warning py-2 small mt-3 mb-0">
                            Si alguien no ve una opción del menú, puede ser porque no tiene ese permiso o porque está desactivado en Accesos. Los administradores siempre ven todo.
                        </div>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-carga">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-carga" aria-expanded="false" aria-controls="c-carga">
                        13) Consejos al cargar datos
                    </button>
                </h3>
                <div id="c-carga" class="accordion-collapse collapse" aria-labelledby="h-carga" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <ul class="mb-0">
                            <li><strong>De a poco</strong>: completá lo importante primero y guardá; después podés editar y sumar más.</li>
                            <li><strong>Un cambio, un guardado</strong>: así es más fácil saber qué salió bien.</li>
                            <li><strong>Los textos grises</strong> debajo de los campos son ayudas — léelos si algo no se entiende.</li>
                            <li><strong>Importar alumnos</strong>: usá Excel o CSV con las columnas que indica la pantalla; elegí la sede antes de subir el archivo.</li>
                            <li><strong>Pagos con varias cuotas</strong>: cada fila es un alumno y una cuota; el total debe ser la suma de todas las filas.</li>
                            <li><strong>Programa / videos</strong>: podés pegar un enlace de YouTube; no hace falta subir el video al sistema.</li>
                            <li><strong>Si algo falla</strong>: mirá el mensaje en rojo, corregí ese campo y volvé a guardar.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="accordion-item" data-ayuda-item>
                <h3 class="accordion-header" id="h-faq">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-faq" aria-expanded="false" aria-controls="c-faq">
                        14) Preguntas frecuentes
                    </button>
                </h3>
                <div id="c-faq" class="accordion-collapse collapse" aria-labelledby="h-faq" data-bs-parent="#ayudaAccordion">
                    <div class="accordion-body ayuda-body">
                        <dl class="mb-0">
                            <dt class="fw-semibold">No encuentro un alumno en el pago</dt>
                            <dd class="text-muted mb-2">Quitá el filtro de bloque o revisá que el alumno esté activo y en esa sede.</dd>
                            <dt class="fw-semibold">El total del pago no me deja guardar</dt>
                            <dd class="text-muted mb-2">Sumá los montos de cada fila: tienen que dar exactamente el mismo número que el total.</dd>
                            <dt class="fw-semibold">¿Puedo poner a un alumno en dos bloques?</dt>
                            <dd class="text-muted mb-2">Sí. En su ficha, tildá todos los bloques y marcá cuál es el principal.</dd>
                            <dt class="fw-semibold">Olvidé mi contraseña</dt>
                            <dd class="text-muted mb-2">Pedile a quien administra el sistema que te la reinicie.</dd>
                            <dt class="fw-semibold">En el celular no veo el menú</dt>
                            <dd class="text-muted mb-0">Tocá las tres rayitas ☰ arriba a la izquierda.</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 small text-muted">
            ¿Algo no te cierra? Volvé al menú, entrá de nuevo a la pantalla o buscá una palabra arriba en el buscador.
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/ayuda.css') }}?v=2">
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

