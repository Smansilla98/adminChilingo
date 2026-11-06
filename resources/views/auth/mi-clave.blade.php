@extends('layouts.app')

@section('title', 'Mi contraseña')
@section('page-title', 'Mi contraseña')

@section('content')
<x-ito.shell-page title="Mi contraseña" :subtitle="$obligatoria ? 'Entraste con una contraseña provisoria. Elegí la tuya para seguir.' : 'Cambiá la contraseña con la que entrás a la web y a la app.'" :plain="true">
    <form method="POST" action="{{ route('mi-clave.update') }}" style="max-width: 520px">
        @csrf @method('PUT')
        @if($obligatoria)
            <div class="alert alert-info small">Tu usuario es <strong class="ito-mono">{{ auth()->user()->username }}</strong>. No uses tu DNI como contraseña: cualquiera que lo conozca podría entrar.</div>
        @endif
        <x-ito.form-section title="Contraseña" icon="bi-key">
            <div class="mb-3">
                <label class="form-label" for="actual">{{ $obligatoria ? 'Contraseña provisoria' : 'Contraseña actual' }}</label>
                <input type="password" class="form-control @error('actual') is-invalid @enderror" id="actual" name="actual" autocomplete="current-password" required>
                @error('actual')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Contraseña nueva</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" autocomplete="new-password" minlength="6" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Al menos 6 letras o números.</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Repetila</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required>
            </div>
        </x-ito.form-section>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Guardar contraseña</button>
            @if($obligatoria)
                <button type="submit" form="salir" class="btn btn-outline-secondary">Salir</button>
            @endif
        </div>
    </form>
    @if($obligatoria)
        <form id="salir" method="POST" action="{{ route('logout') }}" class="d-none">@csrf</form>
    @endif
</x-ito.shell-page>
@endsection
