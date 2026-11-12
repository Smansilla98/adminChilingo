@extends('layouts.app')

@section('title', 'Legajo de '.$persona->nombre_completo)
@section('page-title', 'Legajo de '.$persona->nombre_completo)

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@if($legajo->apto_medico_vence)
    @php $dias = $legajo->diasParaVencerApto(); @endphp
    @if($dias < 0)
        <div class="alert alert-danger" role="alert">El apto médico venció el {{ $legajo->apto_medico_vence->format('d/m/Y') }}. Pedí el certificado nuevo.</div>
    @elseif($dias <= 30)
        <div class="alert alert-warning" role="alert">El apto médico vence en {{ $dias }} días ({{ $legajo->apto_medico_vence->format('d/m/Y') }}).</div>
    @endif
@elseif($esMenor)
    <div class="alert alert-warning" role="alert">Falta cargar el apto médico.</div>
@endif

<form method="POST" action="{{ route('personas.legajo.update', $persona) }}" enctype="multipart/form-data" class="ito-card p-4" style="max-width: 760px">
    @csrf
    @method('PUT')
    <fieldset class="mb-4">
        <legend class="fs-6 fw-semibold">Tutor responsable{{ $esMenor ? '' : ' (opcional)' }}</legend>
        <div class="row">
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_nombre">Nombre y apellido</label><input id="tutor_nombre" name="tutor_nombre" class="form-control" value="{{ old('tutor_nombre', $legajo->tutor_nombre) }}" maxlength="120" @if($esMenor) required @endif></div>
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_vinculo">Vínculo</label><input id="tutor_vinculo" name="tutor_vinculo" class="form-control" value="{{ old('tutor_vinculo', $legajo->tutor_vinculo) }}" maxlength="40" placeholder="Madre, padre, tutor…" @if($esMenor) required @endif></div>
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_telefono">Teléfono</label><input id="tutor_telefono" name="tutor_telefono" class="form-control" value="{{ old('tutor_telefono', $legajo->tutor_telefono) }}" maxlength="30" @if($esMenor) required @endif></div>
            <div class="col-md-6 mb-2"><label class="form-label" for="tutor_dni">DNI</label><input id="tutor_dni" name="tutor_dni" class="form-control" value="{{ old('tutor_dni', $legajo->tutor_dni) }}" maxlength="20"></div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend class="fs-6 fw-semibold">Uso de imagen</legend>
        <p class="form-text">Autoriza la escuela a usar fotos y videos del alumno en redes y difusión.</p>
        <div class="form-check"><input class="form-check-input" type="radio" name="uso_imagen" id="imagen_si" value="1" @checked(old('uso_imagen', $legajo->uso_imagen) === true || old('uso_imagen', $legajo->uso_imagen) === '1')><label class="form-check-label" for="imagen_si">Sí, autoriza</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="uso_imagen" id="imagen_no" value="0" @checked(old('uso_imagen', $legajo->uso_imagen) === false || old('uso_imagen', $legajo->uso_imagen) === '0')><label class="form-check-label" for="imagen_no">No autoriza</label></div>
        <div class="form-check"><input class="form-check-input" type="radio" name="uso_imagen" id="imagen_nr" value="" @checked(old('uso_imagen', $legajo->uso_imagen) === null)><label class="form-check-label" for="imagen_nr">Sin responder</label></div>
        <label class="form-label mt-2" for="uso_imagen_fecha">Fecha de la autorización</label>
        <input id="uso_imagen_fecha" type="date" name="uso_imagen_fecha" class="form-control w-auto" value="{{ old('uso_imagen_fecha', $legajo->uso_imagen_fecha?->toDateString()) }}">
    </fieldset>

    <fieldset class="mb-4">
        <legend class="fs-6 fw-semibold">Apto médico</legend>
        @if($legajo->apto_medico_path)
            <p class="mb-2">Archivo actual: <a href="{{ route('personas.legajo.apto', $persona) }}" target="_blank" rel="noopener">{{ $legajo->apto_medico_nombre }}</a></p>
        @endif
        <label class="form-label" for="apto_medico_vence">Vence el</label>
        <input id="apto_medico_vence" type="date" name="apto_medico_vence" class="form-control w-auto mb-2" value="{{ old('apto_medico_vence', $legajo->apto_medico_vence?->toDateString()) }}">
        <label class="form-label" for="apto_medico">Certificado nuevo (PDF o imagen, opcional)</label>
        <input id="apto_medico" type="file" name="apto_medico" class="form-control" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
        <div class="form-text">El archivo se guarda privado y solo lo ve el personal con acceso a la ficha.</div>
    </fieldset>

    <button class="btn btn-primary">Guardar legajo</button>
</form>
<p class="mt-3"><a href="{{ route('personas.show', $persona) }}">Volver a la ficha</a></p>
@endsection
