@php
    /** @var \App\Models\Cuota|null $cuota */
    $cuota = $cuota ?? null;
    $alcance = old('alcance', $cuota?->alcance ?? 'bloque');
@endphp
<x-ito.form-section title="Período y monto" icon="bi-cash-stack">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="cuota-nombre">Nombre</label>
            <input type="text" id="cuota-nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $cuota?->nombre) }}" placeholder="Ej.: Cuota mayo 2026" required>
            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="cuota-mes">Mes</label>
            <select id="cuota-mes" name="mes" class="form-select @error('mes') is-invalid @enderror" required>
                @foreach(\App\Models\FacturacionMensual::nombresMeses() as $n => $nombre)
                    <option value="{{ $n }}" @selected((string) old('mes', (string) ($cuota?->mes ?? now()->month)) === (string) $n)>{{ $nombre }}</option>
                @endforeach
            </select>
            @error('mes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="cuota-anio">Año</label>
            <input type="number" id="cuota-anio" name="año" class="form-control @error('año') is-invalid @enderror" value="{{ old('año', $cuota?->año ?? date('Y')) }}" min="2020" max="2030" required>
            @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="cuota-monto">Monto</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" id="cuota-monto" name="monto" class="form-control @error('monto') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto', $cuota?->monto) }}" required>
            </div>
            @error('monto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="cuota-vence">Vence el</label>
            <input type="date" id="cuota-vence" name="fecha_vencimiento" class="form-control @error('fecha_vencimiento') is-invalid @enderror" value="{{ old('fecha_vencimiento', $cuota?->fecha_vencimiento?->format('Y-m-d')) }}">
            <div class="form-text">Opcional. Después de esta fecha la cuota figura como vencida y se avisa a la familia.</div>
            @error('fecha_vencimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-9">
            <label class="form-label" for="cuota-descripcion">Descripción</label>
            <input type="text" id="cuota-descripcion" name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" value="{{ old('descripcion', $cuota?->descripcion) }}">
            @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3 d-flex align-items-center pt-md-4">
            <div class="form-check form-switch">
                <input type="checkbox" role="switch" name="activo" class="form-check-input" id="activo" value="1" @checked(old('activo', $cuota?->activo ?? true))>
                <label class="form-check-label" for="activo">Cuota activa</label>
            </div>
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Para quién es" icon="bi-people" help="Si hay varias cuotas para el mismo mes, al cobrar se usa la más específica: bloque, después sede, después general.">
    <fieldset class="mb-3">
        <legend class="visually-hidden">Alcance de la cuota</legend>
        <div class="btn-group ito-segmented" role="group">
            <input class="btn-check" type="radio" name="alcance" id="alc_general" value="general" @checked($alcance === 'general')>
            <label class="btn btn-outline-secondary" for="alc_general"><i class="bi bi-building" aria-hidden="true"></i> Toda la escuela</label>
            <input class="btn-check" type="radio" name="alcance" id="alc_sede" value="sede" @checked($alcance === 'sede')>
            <label class="btn btn-outline-secondary" for="alc_sede"><i class="bi bi-geo-alt" aria-hidden="true"></i> Una sede</label>
            <input class="btn-check" type="radio" name="alcance" id="alc_bloque" value="bloque" @checked($alcance === 'bloque')>
            <label class="btn btn-outline-secondary" for="alc_bloque"><i class="bi bi-collection" aria-hidden="true"></i> Un bloque</label>
        </div>
        @error('alcance')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </fieldset>
    <div class="row g-3">
        <div class="col-md-6" id="rowSede" style="display: none;">
            <label class="form-label" id="lblSede" for="sede_id">Sede</label>
            <select name="sede_id" id="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                <option value="">Elegí una sede…</option>
                @foreach($sedes as $s)
                    <option value="{{ $s->id }}" @selected((string) old('sede_id', $cuota?->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
            @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6" id="rowBloque">
            <label class="form-label" id="lblBloque" for="bloque_id">Bloque</label>
            <select name="bloque_id" id="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror">
                <option value="">Elegí un bloque…</option>
                @foreach($bloques as $b)
                    <option value="{{ $b->id }}" @selected((string) old('bloque_id', $cuota?->bloque_id) === (string) $b->id) data-sede-id="{{ $b->sede_id ?? '' }}">
                        {{ $b->nombre }} @if($b->sede) ({{ $b->sede->nombre }}) @endif
                    </option>
                @endforeach
            </select>
            @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12" id="wrapAlumnos" style="display: none;">
            <label class="form-label" for="alumno_ids">Solo estos alumnos</label>
            <select name="alumno_ids[]" id="alumno_ids" class="form-select @error('alumno_ids') is-invalid @enderror" multiple size="8"></select>
            <div class="form-text">Opcional. Si no elegís a nadie, vale para todo el grupo. Ctrl + clic para elegir varios.</div>
            @error('alumno_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

@push('scripts')
<script type="application/json" id="bloquesAlumnosJson">
{!! $bloques->map(function ($b) {
    return [
        'id' => $b->id,
        'sede_id' => $b->sede_id,
        'alumnos' => $b->alumnos->map(function ($a) {
            return ['id' => $a->id, 'nombre_apellido' => $a->nombre_apellido, 'sede_id' => $a->sede_id];
        })->values(),
    ];
})->keyBy('id')->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
</script>
<script type="application/json" id="todosAlumnosJson">
{!! $alumnosActivos->map(fn ($a) => ['id' => $a->id, 'nombre_apellido' => $a->nombre_apellido, 'sede_id' => $a->sede_id])->values()->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var bloquesAlumnos = JSON.parse(document.getElementById('bloquesAlumnosJson').textContent || '{}');
    var todosAlumnos = JSON.parse(document.getElementById('todosAlumnosJson').textContent || '[]');
    var radios = document.querySelectorAll('input[name="alcance"]');
    var rowSede = document.getElementById('rowSede');
    var rowBloque = document.getElementById('rowBloque');
    var sedeSel = document.getElementById('sede_id');
    var selectBloque = document.getElementById('bloque_id');
    var wrapAlumnos = document.getElementById('wrapAlumnos');
    var selectAlumnos = document.getElementById('alumno_ids');
    var lblBloque = document.getElementById('lblBloque');
    var selectedAlumnoIds = @json(array_values(array_map('intval', (array) old('alumno_ids', $cuota?->alumnos->pluck('id')->all() ?? []))));

    function applySelectedAlumnos() {
        if (!selectAlumnos || !selectedAlumnoIds.length) return;
        for (var i = 0; i < selectAlumnos.options.length; i++) {
            var o = selectAlumnos.options[i];
            if (selectedAlumnoIds.indexOf(parseInt(o.value, 10)) !== -1) o.selected = true;
        }
    }

    function alcance() {
        var r = document.querySelector('input[name="alcance"]:checked');
        return r ? r.value : 'bloque';
    }

    function actualizarLayout() {
        var a = alcance();
        if (rowSede) rowSede.style.display = a === 'sede' ? '' : 'none';
        if (rowBloque) rowBloque.style.display = a === 'bloque' ? '' : 'none';
        if (sedeSel) sedeSel.required = a === 'sede';
        var lblSede = document.getElementById('lblSede');
        if (lblSede) lblSede.classList.toggle('required', a === 'sede');
        if (selectBloque) {
            selectBloque.required = a === 'bloque';
            if (lblBloque) lblBloque.classList.toggle('required', a === 'bloque');
        }
        actualizarAlumnos();
    }

    function llenarSelect(opts) {
        selectAlumnos.innerHTML = '';
        opts.forEach(function(a) {
            var opt = document.createElement('option');
            opt.value = a.id;
            opt.textContent = a.nombre_apellido;
            selectAlumnos.appendChild(opt);
        });
    }

    function actualizarAlumnos() {
        var a = alcance();
        selectAlumnos.innerHTML = '';
        wrapAlumnos.style.display = 'block';
        if (a === 'general') {
            llenarSelect(todosAlumnos);
            applySelectedAlumnos();
            return;
        }
        if (a === 'sede') {
            var sid = sedeSel && sedeSel.value ? parseInt(sedeSel.value, 10) : 0;
            var set = {};
            Object.keys(bloquesAlumnos).forEach(function(bid) {
                var row = bloquesAlumnos[bid];
                if (!row || parseInt(row.sede_id, 10) !== sid) return;
                (row.alumnos || []).forEach(function(al) {
                    set[al.id] = al;
                });
            });
            todosAlumnos.forEach(function(al) {
                if (parseInt(al.sede_id, 10) === sid) set[al.id] = al;
            });
            llenarSelect(Object.keys(set).map(function(k) { return set[k]; }).sort(function(x, y) {
                return (x.nombre_apellido || '').localeCompare(y.nombre_apellido || '');
            }));
            applySelectedAlumnos();
            return;
        }
        var bloqueId = selectBloque.value;
        wrapAlumnos.style.display = bloqueId ? 'block' : 'none';
        if (!bloqueId) return;
        var data = bloquesAlumnos[bloqueId];
        if (data && data.alumnos && data.alumnos.length) {
            llenarSelect(data.alumnos);
        }
        applySelectedAlumnos();
    }

    radios.forEach(function(r) { r.addEventListener('change', actualizarLayout); });
    if (sedeSel) sedeSel.addEventListener('change', actualizarAlumnos);
    if (selectBloque) selectBloque.addEventListener('change', actualizarAlumnos);
    actualizarLayout();
});
</script>
@endpush
