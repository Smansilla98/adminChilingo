@extends('layouts.app')

@section('title', 'Nuevo usuario')
@section('page-title', 'Nuevo usuario')

@section('content')
<x-ito.shell-page
    title="Nuevo usuario"
    eyebrow="Accesos"
    subtitle="Alta de cuenta para entrar al sistema"
>
    <x-slot:actions>
        <a href="{{ route('accesos.index') }}" class="btn btn-secondary btn-sm">Volver a accesos</a>
    </x-slot:actions>
            @include('partials.form-ayuda-intro', ['text' => 'Creá la cuenta de login. Si es docente, podés generar la ficha de profesor o engancharla a alguien que ya está en el plantel.'])
            <form action="{{ route('accesos.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="name">Nombre *</label>
                        <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if($hasUsername)
                    <div class="col-md-6">
                        <label class="form-label" for="username">Usuario de ingreso *</label>
                        <input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" required autocomplete="username">
                        @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label" for="email">Correo *</label>
                        <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="telefono">Teléfono</label>
                        <input type="text" name="telefono" id="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono') }}">
                        @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password">Contraseña *</label>
                        <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        <div class="form-text">Mínimo 8 caracteres.</div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirmation">Confirmar contraseña *</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="role">Rol *</label>
                        <select name="role" id="role" class="form-select @error('role') is-invalid @enderror" required>
                            @foreach(\App\Http\Controllers\AccesosController::ROLES_ALTA as $k => $v)
                                <option value="{{ $k }}" @selected(old('role', 'profesor') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="profesor_vinculo">Plantel docente</label>
                        <select name="profesor_vinculo" id="profesor_vinculo" class="form-select @error('profesor_vinculo') is-invalid @enderror" required>
                            <option value="ninguno" @selected(old('profesor_vinculo') === 'ninguno')>Sin ficha de profesor</option>
                            <option value="nuevo" @selected(old('profesor_vinculo', 'nuevo') === 'nuevo')>Crear ficha de profesor nueva</option>
                            <option value="existente" @selected(old('profesor_vinculo') === 'existente')>Asociar a un profesor ya cargado</option>
                        </select>
                        @error('profesor_vinculo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12" id="box-profesor-existente" hidden>
                        <label class="form-label" for="profesor_id">Profesor *</label>
                        <select name="profesor_id" id="profesor_id" class="form-select @error('profesor_id') is-invalid @enderror">
                            <option value="">Elegí del plantel</option>
                            @foreach($profesores as $p)
                                <option value="{{ $p->id }}" @selected(old('profesor_id') == $p->id) @disabled($p->user_id)>
                                    {{ $p->nombre }}
                                    @if($p->user_id)
                                        — ya tiene cuenta
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('profesor_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Crear usuario</button>
                    <a href="{{ route('accesos.index') }}" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
(function () {
    const vinculo = document.getElementById('profesor_vinculo');
    const box = document.getElementById('box-profesor-existente');
    const sel = document.getElementById('profesor_id');
    function sync() {
        const show = vinculo.value === 'existente';
        box.hidden = !show;
        sel.required = show;
    }
    vinculo.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
