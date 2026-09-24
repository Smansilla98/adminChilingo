@php
    /** @var \App\Models\Show|null $show */
    $show = $show ?? null;
    $bloquesElegidos = array_map('intval', old('bloque_ids', $show?->bloques->pluck('id')->all() ?? []));
@endphp
<x-ito.form-section title="El show" icon="bi-mic">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="show-titulo">Título</label>
            <input type="text" id="show-titulo" name="titulo" class="form-control @error('titulo') is-invalid @enderror" value="{{ old('titulo', $show?->titulo) }}" required>
            @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="show-lugar">Lugar</label>
            <input type="text" id="show-lugar" name="lugar" class="form-control @error('lugar') is-invalid @enderror" value="{{ old('lugar', $show?->lugar) }}">
            @error('lugar')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="show-fecha">Fecha</label>
            <input type="date" id="show-fecha" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha', $show?->fecha?->format('Y-m-d')) }}" required>
            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="show-desde">Desde</label>
            <input type="time" id="show-desde" name="hora_inicio" class="form-control @error('hora_inicio') is-invalid @enderror" value="{{ old('hora_inicio', $show?->hora_inicio?->format('H:i')) }}">
            @error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="show-hasta">Hasta</label>
            <input type="time" id="show-hasta" name="hora_fin" class="form-control @error('hora_fin') is-invalid @enderror" value="{{ old('hora_fin', $show?->hora_fin?->format('H:i')) }}">
            @error('hora_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="show-descripcion">Descripción</label>
            <textarea id="show-descripcion" name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" rows="3">{{ old('descripcion', $show?->descripcion) }}</textarea>
            @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Quiénes participan" icon="bi-people">
    <div class="form-check form-switch mb-3">
        <input type="checkbox" role="switch" name="convocatoria_abierta" value="1" class="form-check-input" id="convocatoria_abierta" @checked(old('convocatoria_abierta', $show?->convocatoria_abierta))>
        <label class="form-check-label" for="convocatoria_abierta">Convocatoria abierta (sin bloques asignados)</label>
    </div>
    <fieldset id="bloques-wrap">
        <legend class="form-label">Bloques que participan</legend>
        <div class="ito-check-grid">
            @foreach($bloques as $b)
                <label class="ito-check-chip" for="show-bloque-{{ $b->id }}">
                    <input class="form-check-input" type="checkbox" name="bloque_ids[]" value="{{ $b->id }}" id="show-bloque-{{ $b->id }}" @checked(in_array($b->id, $bloquesElegidos, true))>
                    <span>{{ $b->nombre }} <span class="text-muted">· {{ $b->sede->nombre ?? '' }}</span></span>
                </label>
            @endforeach
        </div>
        @error('bloque_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </fieldset>
</x-ito.form-section>

@push('scripts')
<script>
(function () {
    var abierta = document.getElementById('convocatoria_abierta');
    var wrap = document.getElementById('bloques-wrap');
    if (!abierta || !wrap) return;
    function sync() { wrap.disabled = abierta.checked; wrap.style.opacity = abierta.checked ? .5 : 1; }
    abierta.addEventListener('change', sync);
    sync();
})();
</script>
@endpush
