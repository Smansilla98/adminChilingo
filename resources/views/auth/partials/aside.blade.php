{{-- Panel de marca del login y el registro (se oculta en celular). --}}
<aside class="auth-aside" aria-label="La Chilinga">
    <a class="auth-aside-brand" href="{{ route('login') }}">
        <img src="{{ asset('images/brand/logo.png') }}" alt="" width="44" height="44">
        La Chilinga
    </a>
    <div>
        <h2 class="auth-aside-title">Escuela de <span>percusión</span>.</h2>
        <p class="auth-aside-text">Alumnos, bloques, asistencias, cuotas, eventos e inventario de todas las sedes en un mismo lugar.</p>
    </div>
    <nav class="auth-aside-links" aria-label="Accesos sin cuenta">
        <a href="{{ route('comprobante-cuota-public.create') }}">Cargar comprobante de cuota</a>
        <a href="{{ route('programa.index') }}">Programa</a>
        <a href="{{ route('programa.partituras.index') }}">Partituras</a>
        <a href="{{ route('biblioteca.index') }}">Biblioteca</a>
    </nav>
</aside>
