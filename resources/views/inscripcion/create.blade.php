@extends('layouts.guest')

@section('title', 'Preinscripción')
@section('guest-title', 'Quiero inscribirme')
@section('guest-subtitle', 'Dejá tus datos. Quedás en lista de espera y la sede te contacta.')

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ route('inscripcion.store') }}" novalidate>
    @csrf
    {{-- Trampa para robots: las personas no ven este campo. --}}
    <div class="d-none" aria-hidden="true">
        <label for="sitio_web">No completar</label>
        <input type="text" name="sitio_web" id="sitio_web" value="" tabindex="-1" autocomplete="off">
    </div>
    <div class="mb-3">
        <label class="form-label" for="sede_id">Sede</label>
        <select name="sede_id" id="sede_id" class="form-select" required>
            <option value="">Elegí sede</option>
            @foreach($sedes as $s)
                <option value="{{ $s->id }}" @selected((string) old('sede_id') === (string) $s->id)>{{ $s->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label" for="bloque_id">Bloque o horario (opcional)</label>
        <select name="bloque_id" id="bloque_id" class="form-select">
            <option value="">Sin preferencia</option>
            @foreach($sedes as $s)
                <optgroup label="{{ $s->nombre }}">
                    @foreach($s->bloques as $b)
                        <option value="{{ $b->id }}" @selected((string) old('bloque_id') === (string) $b->id)>{{ $b->nombre }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
    </div>
    <div class="row mb-3">
        <div class="col-md-6"><label class="form-label" for="nombre">Nombre</label><input id="nombre" name="nombre" class="form-control" value="{{ old('nombre') }}" required maxlength="120"></div>
        <div class="col-md-6"><label class="form-label" for="apellido">Apellido</label><input id="apellido" name="apellido" class="form-control" value="{{ old('apellido') }}" required maxlength="120"></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6"><label class="form-label" for="dni">DNI (opcional)</label><input id="dni" name="dni" class="form-control" inputmode="numeric" value="{{ old('dni') }}" maxlength="20"></div>
        <div class="col-md-6"><label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label><input id="fecha_nacimiento" type="date" name="fecha_nacimiento" class="form-control" value="{{ old('fecha_nacimiento') }}" required max="{{ now()->subDay()->toDateString() }}"></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6"><label class="form-label" for="telefono">Teléfono (WhatsApp)</label><input id="telefono" name="telefono" class="form-control" value="{{ old('telefono') }}" required maxlength="30"></div>
        <div class="col-md-6"><label class="form-label" for="email">Correo (opcional)</label><input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="255"></div>
    </div>
    <fieldset class="border rounded p-3 mb-3">
        <legend class="fs-6 fw-semibold">Si sos menor de 18: datos del tutor</legend>
        <div class="row">
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_nombre">Nombre y apellido del tutor</label><input id="tutor_nombre" name="tutor_nombre" class="form-control" value="{{ old('tutor_nombre') }}" maxlength="120"></div>
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_vinculo">Vínculo</label><input id="tutor_vinculo" name="tutor_vinculo" class="form-control" value="{{ old('tutor_vinculo') }}" maxlength="40" placeholder="Madre, padre, tutor…"></div>
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_telefono">Teléfono del tutor</label><input id="tutor_telefono" name="tutor_telefono" class="form-control" value="{{ old('tutor_telefono') }}" maxlength="30"></div>
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_dni">DNI del tutor (opcional)</label><input id="tutor_dni" name="tutor_dni" class="form-control" value="{{ old('tutor_dni') }}" maxlength="20"></div>
        </div>
    </fieldset>
    <div class="mb-3">
        <label class="form-label" for="notas">Algo que quieras contarnos (opcional)</label>
        <textarea id="notas" name="notas" class="form-control" rows="2" maxlength="1000">{{ old('notas') }}</textarea>
    </div>
    <button type="submit" class="btn btn-primary">Enviar preinscripción</button>
</form>
@endsection
