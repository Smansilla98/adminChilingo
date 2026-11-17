@extends('layouts.guest')

@section('title', 'Preinscripción')
@section('guest-title', 'Quiero inscribirme')
@section('guest-subtitle', 'Son cuatro pasos. Podés volver a este mismo link para seguir donde quedaste.')

@push('styles')
<style>
    .ins-progreso { display: flex; gap: 6px; margin-bottom: 18px; }
    .ins-progreso span { flex: 1; height: 6px; border-radius: 3px; background: #e3e3e0; }
    .ins-progreso span.on { background: #0d6efd; }
</style>
@endpush

@section('content')
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="ins-progreso" aria-label="Paso {{ $paso }} de {{ \App\Models\Preinscripcion::PASOS }}">
    @for($i = 1; $i <= \App\Models\Preinscripcion::PASOS; $i++)
        <span class="{{ $i <= $paso ? 'on' : '' }}"></span>
    @endfor
</div>
<p class="text-muted small mb-3">Paso {{ $paso }} de {{ \App\Models\Preinscripcion::PASOS }}</p>

<form method="POST" action="{{ route('inscripcion.paso', $paso) }}" novalidate>
    @csrf
    @if($pre)
        <input type="hidden" name="p" value="{{ $pre->token }}">
    @endif
    {{-- Trampa para robots: las personas no ven este campo. --}}
    <div class="d-none" aria-hidden="true">
        <label for="sitio_web">No completar</label>
        <input type="text" name="sitio_web" id="sitio_web" value="" tabindex="-1" autocomplete="off">
    </div>

    @if($paso === 1)
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
    @elseif($paso === 2)
        <div class="mb-3">
            <label class="form-label" for="dni">DNI (opcional)</label>
            <input id="dni" name="dni" class="form-control" inputmode="numeric" value="{{ old('dni') }}" maxlength="20">
        </div>
        <div class="mb-3">
            <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
            <input id="fecha_nacimiento" type="date" name="fecha_nacimiento" class="form-control" value="{{ old('fecha_nacimiento') }}" required max="{{ now()->subDay()->toDateString() }}">
        </div>
    @elseif($paso === 3)
        <div class="row mb-3">
            <div class="col-md-6"><label class="form-label" for="telefono">Teléfono (WhatsApp)</label><input id="telefono" name="telefono" class="form-control" value="{{ old('telefono') }}" required maxlength="30"></div>
            <div class="col-md-6"><label class="form-label" for="email">Correo (opcional)</label><input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" maxlength="255"></div>
        </div>
        @if($pre?->esMenor())
            <fieldset class="border rounded p-3 mb-3">
                <legend class="fs-6 fw-semibold">Como sos menor de 18: datos del tutor</legend>
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="form-label" for="tutor_nombre">Nombre y apellido del tutor</label><input id="tutor_nombre" name="tutor_nombre" class="form-control" value="{{ old('tutor_nombre') }}" maxlength="120" required></div>
                    <div class="col-md-6 mb-2"><label class="form-label" for="tutor_vinculo">Vínculo</label><input id="tutor_vinculo" name="tutor_vinculo" class="form-control" value="{{ old('tutor_vinculo') }}" maxlength="40" placeholder="Madre, padre, tutor…" required></div>
                    <div class="col-md-6 mb-2"><label class="form-label" for="tutor_telefono">Teléfono del tutor</label><input id="tutor_telefono" name="tutor_telefono" class="form-control" value="{{ old('tutor_telefono') }}" maxlength="30" required></div>
                    <div class="col-md-6 mb-2"><label class="form-label" for="tutor_dni">DNI del tutor (opcional)</label><input id="tutor_dni" name="tutor_dni" class="form-control" value="{{ old('tutor_dni') }}" maxlength="20"></div>
                </div>
            </fieldset>
        @endif
    @else
        <div class="mb-3">
            <label class="form-label" for="notas">¿Algo que quieras contarnos? (opcional)</label>
            <textarea id="notas" name="notas" class="form-control" rows="3" maxlength="1000">{{ old('notas') }}</textarea>
        </div>
        <p class="small text-muted">Al enviar, la sede recibe tu preinscripción y queda en lista de espera hasta que te confirmen.</p>
    @endif

    <button type="submit" class="btn btn-primary">{{ $paso === \App\Models\Preinscripcion::PASOS ? 'Enviar preinscripción' : 'Continuar' }}</button>
</form>
@endsection

@push('scripts')
<script>
// Embebido en otra web (iframe): avisa la altura al sitio que lo contiene para que el marco no tenga barra.
(function () {
    if (window.parent === window) return;
    function avisarAltura() {
        window.parent.postMessage({ tipo: 'chilinga:inscripcion:altura', altura: document.documentElement.scrollHeight }, '*');
    }
    window.addEventListener('load', avisarAltura);
    window.addEventListener('resize', avisarAltura);
    if ('ResizeObserver' in window) new ResizeObserver(avisarAltura).observe(document.body);
})();
</script>
@endpush
