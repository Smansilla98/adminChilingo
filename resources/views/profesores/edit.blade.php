@extends('layouts.app')

@section('title', 'Editar profesor')
@section('page-title', 'Editar profesor')

@section('content')
<x-ito.shell-page
    title="Editar profesor"
    subtitle="{{ $profesor->nombre }}"
    eyebrow="Profesores"
>
    <x-slot:actions>
        <a href="{{ route('profesores.show', $profesor) }}" class="btn btn-outline-secondary btn-sm">Ver ficha</a>
    </x-slot:actions>

    <form action="{{ route('profesores.update', $profesor) }}" method="POST" class="ito-form">
        @csrf
        @method('PUT')
        <x-ito.form-steps
            :steps="['Datos', 'Cuenta', 'Bloques', 'Sedes']"
            submit-label="Guardar cambios"
        >
            <x-slot:cancel>
                <a href="{{ route('profesores.show', $profesor) }}" class="btn btn-outline-secondary">Cancelar</a>
            </x-slot:cancel>

            <x-ito.form-step :index="0" title="Datos de contacto" help="Nombre y cómo contactarlo.">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $profesor->nombre) }}" required>
                        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control @error('telefono') is-invalid @enderror" value="{{ old('telefono', $profesor->telefono) }}">
                        @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $profesor->email) }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" {{ old('activo', $profesor->activo) ? 'checked' : '' }}>
                            <label class="form-check-label" for="activo">Activo</label>
                        </div>
                    </div>
                </div>
            </x-ito.form-step>

            <x-ito.form-step :index="1" title="Cuenta de ingreso" help="Opcional: usuario y contraseña para el sistema.">
                @include('profesores._form_usuario')
            </x-ito.form-step>

            <x-ito.form-step :index="2" title="Bloques" help="En qué clases da.">
                @include('profesores._form_bloques', ['bloquesParaAsignar' => $bloquesParaAsignar, 'profesor' => $profesor])
            </x-ito.form-step>

            <x-ito.form-step :index="3" title="Sedes y roles" help="Sedes donde coordina o enseña.">
                @include('profesores._form_sedes_roles', ['sedes' => $sedes ?? collect(), 'profesor' => $profesor])
            </x-ito.form-step>
        </x-ito.form-steps>
    </form>
</x-ito.shell-page>
@endsection

@push('scripts')
@include('profesores._form_usuario_script')
@endpush
