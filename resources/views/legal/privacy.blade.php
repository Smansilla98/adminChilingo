<!DOCTYPE html>
<html lang="es">
<head>
    @php($headTitle = 'Política de privacidad — ITO')
    @include('layouts.partials.head-ito')
    <meta name="description" content="Política de privacidad de ITO, sistema de gestión.">
    <style>
        :root {
            --ito-orange: #f26422;
            --ito-ink: #151515;
            --ito-muted: #59616b;
            --ito-bg: #f4f5f6;
        }
        body { background: var(--ito-bg); color: var(--ito-ink); }
        .privacy-shell { max-width: 880px; margin: 0 auto; padding: 2rem 1rem 4rem; }
        .privacy-card { background: #fff; border-radius: 1rem; box-shadow: 0 1rem 3rem rgba(21, 21, 21, .08); padding: clamp(1.5rem, 4vw, 3rem); }
        .privacy-brand { color: var(--ito-orange); font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .privacy-card h1 { margin: .5rem 0 1rem; font-weight: 800; }
        .privacy-card h2 { margin-top: 2rem; font-size: 1.25rem; font-weight: 800; }
        .privacy-card p, .privacy-card li { color: var(--ito-muted); line-height: 1.7; }
        .privacy-updated { color: var(--ito-muted); font-size: .9rem; }
        .privacy-footer { margin-top: 1.5rem; text-align: center; }
        .privacy-footer a { color: var(--ito-orange); font-weight: 700; text-decoration: none; }
    </style>
</head>
<body>
<main class="privacy-shell">
    <article class="privacy-card">
        <div class="privacy-brand">ITO</div>
        <h1>Política de privacidad</h1>
        <p class="privacy-updated">Última actualización: {{ now()->format('d/m/Y') }}</p>

        <p>
            ITO es un sistema de gestión para organizaciones educativas y culturales.
            Esta política explica qué información se utiliza para prestar el servicio,
            administrar usuarios y mantener la seguridad de las cuentas.
        </p>

        <h2>Información que utilizamos</h2>
        <p>
            Podemos procesar datos de identificación y contacto, credenciales de acceso,
            información de asistencia, actividades, cuotas, pagos, inventario y registros
            operativos que la organización cargue en el sistema.
        </p>

        <h2>Finalidad</h2>
        <ul>
            <li>Autenticar usuarios y controlar permisos de acceso.</li>
            <li>Gestionar alumnos, sedes, bloques, asistencias, actividades y comunicaciones.</li>
            <li>Emitir reportes y comprobantes solicitados por la organización.</li>
            <li>Proteger el servicio, prevenir abusos y mantener registros técnicos.</li>
        </ul>

        <h2>Acceso y conservación</h2>
        <p>
            El acceso está limitado a usuarios autorizados por la organización que utiliza
            ITO. Conservamos la información mientras la cuenta y la relación con la
            organización estén activas, o durante el plazo necesario para cumplir
            obligaciones legales y resolver reclamos.
        </p>

        <h2>Compartición de información</h2>
        <p>
            No vendemos datos personales. Solo compartimos información con proveedores
            técnicos necesarios para operar el servicio, bajo medidas de seguridad
            razonables, o cuando exista una obligación legal válida.
        </p>

        <h2>Seguridad</h2>
        <p>
            Aplicamos controles de autenticación, permisos por rol, conexiones cifradas
            y medidas técnicas destinadas a proteger la información contra acceso,
            modificación o divulgación no autorizados.
        </p>

        <h2>Derechos y contacto</h2>
        <p>
            Para solicitar acceso, corrección o eliminación de datos, contactá a la
            organización administradora de tu cuenta
            @if(config('mail.admin_resumen_email'))
                o escribí a
                <a href="mailto:{{ config('mail.admin_resumen_email') }}">{{ config('mail.admin_resumen_email') }}</a>
            @endif.
        </p>

        <h2>Cambios en esta política</h2>
        <p>
            Podemos actualizar esta política cuando cambien el servicio o los requisitos
            legales. La versión vigente estará disponible en esta misma dirección.
        </p>
    </article>
    <div class="privacy-footer">
        <a href="{{ route('login') }}">Volver al acceso de ITO</a>
    </div>
</main>
</body>
</html>
