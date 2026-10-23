<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito', ['headTitle' => 'La Chilinga · Sistema de gestión de la escuela'])
    <meta name="description" content="El sistema de La Chilinga reúne alumnos, bloques, asistencias, cuotas, eventos, inventario, programa y partituras de todas las sedes de la escuela en un mismo lugar.">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v=1">
</head>
<body class="landing-body">
<a class="ito-skip" href="#contenido-principal">Ir al contenido</a>

<header class="landing-nav">
    <a href="{{ route('home') }}" class="landing-nav__brand">
        <img src="{{ asset('images/brand/logo.png') }}" alt="" width="36" height="36">
        <span>La Chilinga</span>
    </a>
    <nav class="landing-nav__links" aria-label="Secciones">
        <a href="#que-es">Qué es</a>
        <a href="#para-quien">Para quién</a>
        <a href="#abierto">Sin cuenta</a>
    </nav>
    <a href="{{ route('login') }}" class="btn btn-primary landing-nav__cta">Iniciar sesión</a>
</header>

<main id="contenido-principal" tabindex="-1">
    <section class="landing-hero">
        <div>
            <p class="landing-kicker">Escuela de percusión · fundada por Dani Buira</p>
            <h1>Toda la escuela, <span>en un mismo lugar</span>.</h1>
            <p class="landing-lead">
                El sistema de La Chilinga organiza alumnos, bloques, asistencias, cuotas, eventos e inventario
                de todas las sedes, y abre el programa, las partituras y la biblioteca a toda la comunidad.
            </p>
            <div class="landing-hero__actions">
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Iniciar sesión</a>
                <a href="{{ route('registro.alumno') }}" class="btn btn-outline-secondary btn-lg">Crear mi cuenta de alumno</a>
            </div>
            <ul class="landing-sedes" aria-label="Sedes">
                <li>Palomar</li><li>Saavedra</li><li>Varela</li><li>Quilmes</li><li>Banfield</li><li>Tacheles</li>
            </ul>
        </div>
        <div class="landing-hero__art">
            <img src="{{ asset('images/brand/v3/sello-tricolor.png') }}" alt="Sello de La Chilinga" width="360" height="360">
        </div>
    </section>

    <section id="que-es" class="landing-section">
        <p class="landing-kicker">Qué es</p>
        <h2>Una persona, una cuenta, todas sus funciones</h2>
        <p class="landing-intro">
            En la escuela una misma persona puede ser alumna en Banfield, profe en Palomar y coordinar en Quilmes.
            El sistema le da <strong>una sola cuenta</strong> y le muestra solo lo que le toca, según sus funciones y sus sedes.
        </p>
        <div class="landing-grid">
            <article class="landing-card">
                <i class="bi bi-people" aria-hidden="true"></i>
                <h3>Alumnos y bloques</h3>
                <p>Inscripciones por año (1° a 6°), bloques con su profe, sede y horarios, y becas.</p>
            </article>
            <article class="landing-card">
                <i class="bi bi-check2-square" aria-hidden="true"></i>
                <h3>Asistencias</h3>
                <p>Toma rápida por bloque, también desde el celular y sin conexión.</p>
            </article>
            <article class="landing-card">
                <i class="bi bi-wallet2" aria-hidden="true"></i>
                <h3>Cuotas y pagos</h3>
                <p>Estado de cuenta, pago con Mercado Pago o transferencia, comprobantes y recordatorios.</p>
            </article>
            <article class="landing-card">
                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                <h3>Eventos y shows</h3>
                <p>Calendario de shows, talleres, muestras y giras, con convocatorias por bloque.</p>
            </article>
            <article class="landing-card">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <h3>Inventario</h3>
                <p>Tambores, parches y herramientas por sede, con QR, plan y órdenes de compra.</p>
            </article>
            <article class="landing-card">
                <i class="bi bi-music-note-beamed" aria-hidden="true"></i>
                <h3>Programa y partituras</h3>
                <p>Los toques de cada año con su partitura, sus videos y su material.</p>
            </article>
            <article class="landing-card">
                <i class="bi bi-graph-up" aria-hidden="true"></i>
                <h3>Administración</h3>
                <p>Facturación mensual, gastos, aprobaciones y auditoría de cada movimiento.</p>
            </article>
            <article class="landing-card">
                <i class="bi bi-palette" aria-hidden="true"></i>
                <h3>Diseño</h3>
                <p>Editor de piezas con las plantillas y el manual de marca de la escuela.</p>
            </article>
        </div>
    </section>

    <section id="para-quien" class="landing-section">
        <p class="landing-kicker">Para quién</p>
        <h2>Cada uno entra a lo suyo</h2>
        <div class="landing-roles">
            <article>
                <h3>Alumnos</h3>
                <p>Ven sus cuotas y las pagan, consultan su asistencia, la agenda y el material de los toques del año que cursan.</p>
            </article>
            <article>
                <h3>Profes</h3>
                <p>Toman asistencia de sus bloques, siguen a sus alumnos, arman convocatorias y ven su parte de las cuotas.</p>
            </article>
            <article>
                <h3>Coordinación y administración</h3>
                <p>Gestionan sedes, bloques, pagos, gastos, inventario y permisos, con alcance global, por sede o por bloque.</p>
            </article>
        </div>
        <p class="landing-note"><i class="bi bi-phone" aria-hidden="true"></i> También hay app móvil para Android y iOS con la misma cuenta.</p>
    </section>

    <section id="abierto" class="landing-section">
        <p class="landing-kicker">Sin cuenta</p>
        <h2>Abierto a toda la comunidad</h2>
        <div class="landing-links">
            <a href="{{ route('programa.index') }}"><i class="bi bi-journal-text" aria-hidden="true"></i><span><strong>Programa</strong>Toques por año y textos de la escuela</span></a>
            <a href="{{ route('programa.partituras.index') }}"><i class="bi bi-music-note-list" aria-hidden="true"></i><span><strong>Partituras</strong>El índice de partituras del programa</span></a>
            <a href="{{ route('comunidad.agenda') }}"><i class="bi bi-calendar3" aria-hidden="true"></i><span><strong>Agenda</strong>Próximos shows y actividades</span></a>
            <a href="{{ route('biblioteca.index') }}"><i class="bi bi-images" aria-hidden="true"></i><span><strong>Biblioteca</strong>Fotos, videos y material compartido</span></a>
            <a href="{{ route('archivo.index') }}"><i class="bi bi-archive" aria-hidden="true"></i><span><strong>Archivo histórico</strong>La memoria de la escuela</span></a>
            <a href="{{ route('programa.sedes') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i><span><strong>Sedes</strong>Dónde estamos, en el mapa</span></a>
            <a href="{{ route('comprobante-cuota-public.create') }}"><i class="bi bi-receipt" aria-hidden="true"></i><span><strong>Cargar comprobante</strong>Informá el pago de una cuota</span></a>
            <a href="{{ route('registro.alumno') }}"><i class="bi bi-person-plus" aria-hidden="true"></i><span><strong>Crear cuenta de alumno</strong>Con tu DNI, en un minuto</span></a>
        </div>
    </section>

    <section class="landing-cta">
        <h2>¿Ya tenés cuenta?</h2>
        <p>Si sos alumno, tu usuario es tu DNI.</p>
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Iniciar sesión</a>
    </section>
</main>

<footer class="landing-footer">
    © {{ date('Y') }} La Chilinga · Escuela de percusión · <a href="{{ route('privacy') }}">Privacidad</a>
</footer>
<script src="{{ asset('js/ito-a11y.js') }}?v=1"></script>
</body>
</html>
