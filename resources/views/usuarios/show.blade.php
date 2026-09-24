@extends('layouts.app')

@section('title', 'Cuenta · '.($usuario->persona?->nombre_completo ?? $usuario->name))
@section('page-title', 'Usuarios y permisos')

@section('content')
@php
    $p = $usuario->persona;
    $pestanas = ['roles' => 'Roles y alcance', 'permisos' => 'Qué puede hacer'];
    if ($puedeEditar) { $pestanas['cuenta'] = 'Cuenta'; }
@endphp
<x-ito.shell-page :title="$p?->nombre_completo ?? $usuario->name" :subtitle="'@'.$usuario->username.' · Última actividad: '.($usuario->ultimo_acceso_at?->diffForHumans() ?? 'sin registro')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$usuario->activo ? 'success' : 'neutral'" :label="$usuario->activo ? 'Cuenta activa' : 'Desactivada'" />
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Volver</a>
        @if($p)<a href="{{ route('personas.show', $p) }}" class="btn btn-outline-secondary"><i class="bi bi-person-vcard" aria-hidden="true"></i> Ficha de la persona</a>@endif
    </x-slot:actions>

    @if($esSuperadmin)
        <div class="alert alert-warning mb-0">Superadministrador: tiene todos los permisos, incluida la gestión de administradores.</div>
    @endif

    <x-ito.tabs id="usuarioTabs" :tabs="$pestanas" :active="$errors->hasAny(['username', 'email', 'password']) ? 'cuenta' : 'roles'">
    <x-ito.tab tabs="usuarioTabs" name="roles" :active="! $errors->hasAny(['username', 'email', 'password'])">

    <x-ito.detail-section title="Roles y alcance" icon="bi-diagram-3">
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
                                            <button class="btn btn-sm btn-ghost text-danger">Quitar</button>
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
            <details class="ito-details mt-3" @if($errors->hasAny(['nombre', 'ambito', 'sede_id', 'bloque_id', 'asignacion'])) open @endif>
                <summary>Agregar rol o permiso</summary>
                <form method="POST" action="{{ route('usuarios.asignaciones.store', $usuario) }}" class="row g-2 mt-2" data-asignacion-form>
                    @csrf
                    <div class="col-md-2">
                        <label class="form-label" for="tipo">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select" data-tipo>
                            <option value="rol">Rol</option>
                            <option value="permiso" @selected(old('tipo') === 'permiso')>Permiso suelto</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nombre-rol">Rol o permiso</label>
                        <select id="nombre-rol" name="nombre" class="form-select" data-opciones="rol">
                            @foreach($roles as $clave => $nombre)<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach
                        </select>
                        <select name="nombre" class="form-select d-none" data-opciones="permiso" disabled aria-label="Permiso">
                            @foreach($gruposPermisos as $grupo => $lista)
                                <optgroup label="{{ $grupo }}">
                                    @foreach($lista as $clave => $etiqueta)<option value="{{ $clave }}">{{ $etiqueta }} ({{ $clave }})</option>@endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('nombre')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="ambito">Alcance</label>
                        <select id="ambito" name="ambito" class="form-select">
                            <option value="global">Toda la escuela</option>
                            <option value="sede">Una sede</option>
                            <option value="bloque">Un bloque</option>
                        </select>
                        @error('ambito')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="sede_id">Sede</label>
                        <select id="sede_id" name="sede_id" class="form-select">
                            <option value="">—</option>
                            @foreach($sedes as $s)<option value="{{ $s->id }}">{{ $s->nombre }}</option>@endforeach
                        </select>
                        @error('sede_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="bloque_id">Bloque</label>
                        <select id="bloque_id" name="bloque_id" class="form-select">
                            <option value="">—</option>
                            @foreach($bloques as $b)<option value="{{ $b->id }}">{{ $b->nombre }} ({{ $b->sede?->nombre }})</option>@endforeach
                        </select>
                        @error('bloque_id')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2"><label class="form-label" for="desde">Desde</label><input id="desde" type="date" name="desde" class="form-control"></div>
                    <div class="col-md-2"><label class="form-label" for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label" for="notas">Nota</label><input id="notas" name="notas" maxlength="500" class="form-control" placeholder="Ej.: contador externo, suplencia de marzo"></div>
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
    </x-ito.detail-section>
    </x-ito.tab>

    <x-ito.tab tabs="usuarioTabs" name="permisos">
    <x-ito.detail-section title="Qué puede hacer" icon="bi-shield-check" help="Permisos efectivos: qué puede hacer, dónde y por qué rol.">
        @if($permisos === [])
            <p class="text-muted">Ningún permiso de gestión.</p>
        @else
            @include('usuarios.partials.permisos', ['permisos' => $permisos])
        @endif
    </x-ito.detail-section>
    </x-ito.tab>

    @if($puedeEditar)
    <x-ito.tab tabs="usuarioTabs" name="cuenta" :active="$errors->hasAny(['username', 'email', 'password'])">
        <div class="ito-detail-grid">
        <x-ito.detail-section title="Datos de la cuenta" icon="bi-person-gear">
            <form method="POST" action="{{ route('usuarios.update', $usuario) }}" class="row g-3">
                @csrf @method('PUT')
                <div class="col-md-6"><label class="form-label" for="c-username">Usuario</label><input id="c-username" name="username" value="{{ old('username', $usuario->username) }}" class="form-control @error('username') is-invalid @enderror">@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="c-email">Email</label><input id="c-email" type="email" name="email" value="{{ old('email', $usuario->email) }}" class="form-control @error('email') is-invalid @enderror">@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label" for="c-tel">Teléfono (avisos)</label><input id="c-tel" name="telefono" value="{{ old('telefono', $usuario->telefono) }}" class="form-control"></div>
                <div class="col-md-6 d-flex align-items-end justify-content-end"><button class="btn btn-primary">Guardar datos</button></div>
            </form>
        </x-ito.detail-section>

        <div class="ito-detail-col">
            <x-ito.detail-section title="Contraseña" icon="bi-key">
                <form method="POST" action="{{ route('usuarios.resetear', $usuario) }}" class="row g-3" autocomplete="off">
                    @csrf
                    <div class="col-md-5"><label class="form-label" for="r-pass">Nueva contraseña</label><input id="r-pass" type="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4"><label class="form-label" for="r-pass2">Repetir</label><input id="r-pass2" type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-outline-warning w-100">Resetear acceso</button></div>
                </form>
            </x-ito.detail-section>
            @unless(auth()->user()->is($usuario))
            <x-ito.detail-section title="Estado de la cuenta" icon="bi-power" :help="$usuario->activo ? 'Al desactivarla se cierran sus sesiones en la web y en la app.' : 'La cuenta está desactivada: no puede entrar.'">
                    <form method="POST" action="{{ route('usuarios.estado', $usuario) }}" data-confirm="{{ $usuario->activo ? '¿Desactivar la cuenta? Se cierran sus sesiones y la app.' : '¿Reactivar la cuenta?' }}">
                        @csrf
                        <button class="btn {{ $usuario->activo ? 'btn-outline-danger' : 'btn-outline-success' }}">{{ $usuario->activo ? 'Desactivar cuenta' : 'Activar cuenta' }}</button>
                    </form>
            </x-ito.detail-section>
            @endunless
        </div>
        </div>
    </x-ito.tab>
    @endif
    </x-ito.tabs>
</x-ito.shell-page>
@endsection
