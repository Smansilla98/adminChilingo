@extends('layouts.app')

@section('title', 'Nueva cuenta')
@section('page-title', 'Nueva cuenta')

@section('content')
<x-ito.shell-page title="Nueva cuenta de acceso" eyebrow="Usuarios y permisos" subtitle="1) Persona · 2) Cuenta · 3) Primer rol. Después podés sumar más roles, alcances y permisos.">
    <form method="POST" action="{{ route('usuarios.store') }}" class="d-grid gap-4">
        @csrf

        <fieldset>
            <legend class="h6">1. Persona</legend>
            @if($persona)
                <input type="hidden" name="persona_id" value="{{ $persona->id }}">
                <p class="mb-0">La cuenta se crea para <strong>{{ $persona->nombre_completo }}</strong>{{ $persona->dni ? ' (DNI '.$persona->dni.')' : '' }}.
                    @if($persona->user)<span class="text-danger">Ya tiene cuenta: {{ $persona->user->username }}.</span>@endif
                </p>
            @else
                <p class="small text-muted">Si la persona ya existe (por ejemplo como alumno o docente), abrí su ficha en <a href="{{ route('personas.index') }}">Personas</a> y usá «Crear cuenta de acceso» para no duplicarla.</p>
                <div class="row g-2">
                    <div class="col-md-4"><label class="form-label" for="nombre">Nombre *</label><input id="nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}">@error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-4"><label class="form-label" for="apellido">Apellido</label><input id="apellido" name="apellido" class="form-control" value="{{ old('apellido') }}"></div>
                    <div class="col-md-2"><label class="form-label" for="dni">DNI</label><input id="dni" name="dni" class="form-control @error('dni') is-invalid @enderror" value="{{ old('dni') }}">@error('dni')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-2"><label class="form-label" for="telefono">Teléfono</label><input id="telefono" name="telefono" class="form-control" value="{{ old('telefono') }}"></div>
                </div>
            @endif
            @error('persona_id')<div class="text-danger small">{{ $message }}</div>@enderror
        </fieldset>

        <fieldset>
            <legend class="h6">2. Cuenta</legend>
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label" for="username">Usuario *</label><input id="username" name="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" required autocomplete="off">@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="email">Email *</label><input id="email" type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $persona?->email) }}" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="password">Contraseña *</label><input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-3"><label class="form-label" for="password_confirmation">Repetir *</label><input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password"></div>
            </div>
        </fieldset>

        <fieldset>
            <legend class="h6">3. Primer rol (opcional)</legend>
            <p class="small text-muted">Alumno y docente no hace falta asignarlos: salen de su inscripción y de su ficha docente.</p>
            <div class="row g-2">
                <div class="col-md-5">
                    <label class="form-label" for="rol">Rol</label>
                    <select id="rol" name="rol" class="form-select">
                        <option value="">— Sin rol adicional —</option>
                        @foreach($roles as $clave => $nombre)<option value="{{ $clave }}" @selected(old('rol') === $clave)>{{ $nombre }}</option>@endforeach
                    </select>
                    @error('nombre')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="ambito">Alcance</label>
                    <select id="ambito" name="ambito" class="form-select">
                        <option value="global">Toda la escuela</option>
                        <option value="sede" @selected(old('ambito') === 'sede')>Una sede</option>
                    </select>
                    @error('ambito')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sede_id">Sede</label>
                    <select id="sede_id" name="sede_id" class="form-select">
                        <option value="">—</option>
                        @foreach($sedes as $s)<option value="{{ $s->id }}" @selected((int) old('sede_id') === $s->id)>{{ $s->nombre }}</option>@endforeach
                    </select>
                    @error('sede_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
        </fieldset>

        <div class="d-flex gap-2">
            <button class="btn btn-primary">Crear cuenta</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</x-ito.shell-page>
@endsection
