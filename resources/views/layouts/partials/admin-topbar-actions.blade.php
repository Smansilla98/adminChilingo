{{-- Acciones rápidas admin: escritorio en fila; móvil en menú desplegable --}}
<div class="topbar-actions topbar-actions--desktop d-none d-lg-flex">
    <a href="{{ route('alumnos.create') }}" class="btn btn-pill">+ Alumno</a>
    <a href="{{ route('profesores.create') }}" class="btn btn-pill">+ Profesor</a>
    <a href="{{ route('bloques.create') }}" class="btn btn-pill">+ Bloque</a>
    <a href="{{ route('pagos.create') }}" class="btn btn-pill btn-pill-wide">Registrar pago</a>
</div>
<div class="dropdown topbar-actions--mobile d-lg-none">
    <button type="button" class="btn btn-pill dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="topbarActionsMenu">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> Acciones
    </button>
    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg topbar-actions-menu" aria-labelledby="topbarActionsMenu">
        <li><a class="dropdown-item" href="{{ route('alumnos.create') }}"><i class="bi bi-person-plus me-2"></i>Nuevo alumno</a></li>
        <li><a class="dropdown-item" href="{{ route('profesores.create') }}"><i class="bi bi-person-badge me-2"></i>Nuevo profesor</a></li>
        <li><a class="dropdown-item" href="{{ route('bloques.create') }}"><i class="bi bi-collection me-2"></i>Nuevo bloque</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item fw-semibold" href="{{ route('pagos.create') }}"><i class="bi bi-cash-coin me-2"></i>Registrar pago</a></li>
    </ul>
</div>
