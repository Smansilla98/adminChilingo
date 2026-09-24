@extends('layouts.app')

@php($p = $usuario->persona)
@section('title', 'Cuenta · '.($p?->nombre_completo ?? $usuario->name))
@section('page-title', 'Usuarios y permisos')

@section('content')
<x-ito.shell-page :title="$p?->nombre_completo ?? $usuario->name" eyebrow="Usuarios y permisos" :subtitle="'@'.$usuario->username.' · '.($usuario->activo ? 'Cuenta activa' : 'Cuenta desactivada').' · Última actividad: '.($usuario->ultimo_acceso_at?->diffForHumans() ?? 'sin registro')">
    <x-slot:actions>
        @if($p)<a href="{{ route('personas.show', $p) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-person-vcard"></i> Ficha de la persona</a>@endif
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </x-slot:actions>

    @if($esSuperadmin)
        <div class="alert alert-warning">Superadministrador: tiene todos los permisos, incluida la gestión de administradores.</div>
    @endif

    {{-- 1. Roles y alcance --}}
    <section class="mb-4" aria-labelledby="roles-titulo">
        <h2 id="roles-titulo" class="h5">Roles y alcance</h2>
        @if($funciones === [])
            <p class="text-muted">Sin roles. Solo puede usar funciones públicas.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Rol / permiso</th><th>Dónde</th><th>De dónde sale</th><th></th></tr></thead>
                    <tbody>
                        @foreach($funciones as $f)
                            <tr>
                                <td class="fw-semibold">{{ $f['rol_nombre'] }}</td>
                                <td>{{ $f['ambito_nombre'] }}</td>
                                <td class="small text-muted">{{ $f['origen_etiqueta'] }}</td>
                                <td class="text-end">
                                    @if($f['editable'] && $puedeGestionarPermisos && $f['asignacion_id'])
                                        <form method="POST" action="{{ route('usuarios.asignaciones.destroy', [$usuario, $f['asignacion_id']]) }}" data-confirm="¿Quitar {{ $f['rol_nombre'] }} ({{ $f['ambito_nombre'] }})?">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-link btn-sm text-danger p-0">Quitar</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small text-muted">Los roles de docente, alumno y coordinación de sede salen de las fichas: se cambian desde la ficha docente o la inscripción.</p>
        @endif

        @if($puedeGestionarPermisos)
            <details class="mt-2" @if($errors->hasAny(['nombre', 'ambito', 'sede_id', 'bloque_id', 'asignacion'])) open @endif>
                <summary class="btn btn-outline-primary btn-sm">Agregar rol o permiso</summary>
                <form method="POST" action="{{ route('usuarios.asignaciones.store', $usuario) }}" class="row g-2 mt-2" data-asignacion-form>
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label small" for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select form-select-sm" data-tipo>
                            <option value="rol">Rol</option>
                            <option value="permiso" @selected(old('tipo') === 'permiso')>Permiso suelto</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small" for="nombre-rol">Rol o permiso</label>
                        <select id="nombre-rol" name="nombre" class="form-select form-select-sm" data-opciones="rol">
                            @foreach($roles as $clave => $nombre)<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                        </select>
                        <select name="nombre" class="form-select form-select-sm d-none" data-opciones="permiso" disabled aria-label="Permiso">
                            @foreach($gruposPermisos as $grupo => $lista)
                                <optgroup label="{{ $grupo }}">
                                    @foreach($lista as $clave => $etiqueta)<option value="{{ $clave }}">{{ $etiqueta }} ({{ $clave }})</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('nombre')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="ambito">Alcance</label>
                        <select id="ambito" name="ambito" class="form-select form-select-sm">
                            <option value="global">Toda la escuela</option>
                            <option value="sede">Una sede</option>
                            <option value="bloque">Un bloque</option>
                        </select>
                        @error('ambito')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="sede_id">Sede</label>
                        <select id="sede_id" name="sede_id" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach($sedes as $s)<option value="{{ $s->id }}">{{ $s->nombre }}</option>@endforeach
                        </select>
                        @error('sede_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small" for="bloque_id">Bloque</label>
                        <select id="bloque_id" name="bloque_id" class="form-select form-select-sm">
                            <option value="">—</option>
                            @foreach($bloques as $b)<option value="{{ $b->id }}">{{ $b->nombre }} ({{ $b->sede?->nombre }})</option>@endforeach
                        </select>
                        @error('bloque_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2"><label class="form-label small" for="desde">Desde</label><input id="desde" type="date" name="desde" class="form-control form-control-sm"></div>
                    <div class="col-md-2"><label class="form-label small" for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" class="form-control form-control-sm"></div>
                    <div class="col-md-6"><label class="form-label small" for="notas">Nota</label><input id="notas" name="notas" maxlength="500" class="form-control form-control-sm" placeholder="Ej.: contador externo, suplencia de marzo"></div>
                    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">Agregar</button></div>
                </form>
            </details>
            <script>
                document.querySelectorAll('[data-asignacion-form]').forEach(function (form) {
                    var tipo = form.querySelector('[data-tipo]');
                    function sync() {
                        form.querySelectorAll('[data-opciones]').forEach(function (sel) {
                            var activo = sel.dataset.opciones === tipo.value;
                            sel.classList.toggle('d-none', !activo);
                            sel.disabled = !activo;
                        });
                    }
                    tipo.addEventListener('change', sync);
                    sync();
                });
            </script>
        @endif
    </section>

    {{-- 2. Permisos efectivos --}}
    <section class="mb-4" aria-labelledby="efectivos-titulo">
        <h2 id="efectivos-titulo" class="h5">Qué puede hacer</h2>
        @if($permisos === [])
            <p class="text-muted">Ningún permiso de gestión.</p>
        @else
            @include('usuarios.partials.permisos', ['permisos' => $permisos])
        @endif
    </section>

    {{-- 3. Cuenta --}}
    @if($puedeEditar)
        <section class="row g-4" aria-labelledby="cuenta-titulo">
            <h2 id="cuenta-titulo" class="h5 col-12 mb-0">Cuenta</h2>
            <form method="POST" action="{{ route('usuarios.update', $usuario) }}" class="col-lg-6 row g-2">
                @csrf @method('PUT')
                <div class="col-md-6"><label class="form-label small" for="c-username">Usuario</label><input id="c-username" name="username" value="{{ old('username', $usuario->username) }}" class="form-control form-control-sm @error('username') is-invalid @enderror">@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label small" for="c-email">Email</label><input id="c-email" type="email" name="email" value="{{ old('email', $usuario->email) }}" class="form-control form-control-sm @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label small" for="c-tel">Teléfono (avisos)</label><input id="c-tel" name="telefono" value="{{ old('telefono', $usuario->telefono) }}" class="form-control form-control-sm"></div>
                <div class="col-md-6 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm">Guardar</button></div>
            </form>

            <div class="col-lg-6 d-grid gap-3">
                <form method="POST" action="{{ route('usuarios.resetear', $usuario) }}" class="row g-2" autocomplete="off">
                    @csrf
                    <div class="col-md-5"><label class="form-label small" for="r-pass">Nueva contraseña</label><input id="r-pass" type="password" name="password" class="form-control form-control-sm @error('password') is-invalid @enderror" autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4"><label class="form-label small" for="r-pass2">Repetir</label><input id="r-pass2" type="password" name="password_confirmation" class="form-control form-control-sm" autocomplete="new-password"></div>
                    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-warning btn-sm w-100">Resetear acceso</button></div>
                </form>
                @unless(auth()->user()->is($usuario))
                    <form method="POST" action="{{ route('usuarios.estado', $usuario) }}" data-confirm="{{ $usuario->activo ? '¿Desactivar la cuenta? Se cierran sus sesiones y la app.' : '¿Reactivar la cuenta?' }}">
                        @csrf
                        <button class="btn btn-sm {{ $usuario->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $usuario->activo ? 'Desactivar cuenta' : 'Activar cuenta' }}</button>
                    </form>
                @endunless
            </div>
        </section>
    @endif
</x-ito.shell-page>
@endsection
