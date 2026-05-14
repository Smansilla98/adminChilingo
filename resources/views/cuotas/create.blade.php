@extends('layouts.app')

@section('title', 'Nueva cuota')
@section('page-title', 'Nueva cuota')

@section('content')
<div class="card">
    <div class="card-header">Nueva cuota</div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            <strong>General:</strong> un mismo monto para toda la escuela (todas las sedes y bloques), salvo que exista una cuota <strong>por sede</strong> (más barata, ej. Tacheles, F. Varela) o <strong>por bloque</strong> (caso particular), que tienen prioridad al cobrar.
        </p>
        <form action="{{ route('cuotas.store') }}" method="POST" id="formCuota">
            @csrf
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Alcance *</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="alcance" id="alc_general" value="general" {{ old('alcance', 'bloque') === 'general' ? 'checked' : '' }}>
                            <label class="form-check-label" for="alc_general">General (toda la escuela)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="alcance" id="alc_sede" value="sede" {{ old('alcance') === 'sede' ? 'checked' : '' }}>
                            <label class="form-check-label" for="alc_sede">Diferencial por sede</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="alcance" id="alc_bloque" value="bloque" {{ old('alcance', 'bloque') === 'bloque' ? 'checked' : '' }}>
                            <label class="form-check-label" for="alc_bloque">Solo un bloque</label>
                        </div>
                    </div>
                    @error('alcance')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3" id="rowSede" style="display: none;">
                <div class="col-md-12">
                    <label class="form-label">Sede (cuota diferencial) *</label>
                    <select name="sede_id" id="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                        <option value="">— Elegir sede —</option>
                        @foreach($sedes as $s)
                        <option value="{{ $s->id }}" {{ (string) old('sede_id') === (string) $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                    @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3" id="rowBloque">
                <div class="col-md-12">
                    <label class="form-label" id="lblBloque">Bloque *</label>
                    <select name="bloque_id" id="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror">
                        <option value="">Seleccionar bloque</option>
                        @foreach($bloques as $b)
                        <option value="{{ $b->id }}" {{ (string) old('bloque_id') === (string) $b->id ? 'selected' : '' }} data-sede-id="{{ $b->sede_id ?? '' }}">
                            {{ $b->nombre }} @if($b->sede) ({{ $b->sede->nombre }}) @endif
                        </option>
                        @endforeach
                    </select>
                    @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3" id="wrapAlumnos" style="display: none;">
                <div class="col-md-12">
                    <label class="form-label">Alumnos que pueden pagar esta cuota</label>
                    <p class="text-muted small mb-1">Opcional. Sin selección: aplica a todos los alumnos del alcance (todos en la escuela, todos de la sede o todos del bloque).</p>
                    <select name="alumno_ids[]" id="alumno_ids" class="form-select @error('alumno_ids') is-invalid @enderror" multiple size="10">
                    </select>
                    @error('alumno_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}" placeholder="Ej. Cuota Mayo 2026" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Año *</label>
                    <input type="number" name="año" class="form-control" value="{{ old('año', date('Y')) }}" min="2020" max="2030" required>
                    @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mes *</label>
                    <select name="mes" class="form-select @error('mes') is-invalid @enderror" required>
                        @foreach(\App\Models\FacturacionMensual::nombresMeses() as $n => $nombre)
                        <option value="{{ $n }}" {{ (string) old('mes', (string) now()->month) === (string) $n ? 'selected' : '' }}>{{ $nombre }}</option>
                        @endforeach
                    </select>
                    @error('mes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Monto *</label>
                    <input type="number" name="monto" class="form-control" step="0.01" min="0" value="{{ old('monto') }}" required>
                    @error('monto')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label">Fecha de vencimiento (opcional)</label>
                    <input type="date" name="fecha_vencimiento" class="form-control @error('fecha_vencimiento') is-invalid @enderror" value="{{ old('fecha_vencimiento') }}">
                    @error('fecha_vencimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion') }}">
                </div>
            </div>
            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" checked>
                    <label class="form-check-label" for="activo">Activa</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('cuotas.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>

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
    var selectedAlumnoIds = @json(array_values(array_map('intval', (array) old('alumno_ids', []))));

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
        if (selectBloque) {
            selectBloque.required = a === 'bloque';
            if (lblBloque) lblBloque.textContent = a === 'bloque' ? 'Bloque *' : 'Bloque';
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
@endsection
