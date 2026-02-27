@php
    $item = $item ?? null;
    $values = $values ?? [];
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Sede *</label>
        <select name="sede_id" class="form-select @error('sede_id') is-invalid @enderror" required>
            <option value="">Seleccionar sede</option>
            @foreach($sedes as $s)
            <option value="{{ $s->id }}" {{ old('sede_id', $values['sede_id'] ?? $item?->sede_id) == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
            @endforeach
        </select>
        @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Tipo *</label>
        <select name="tipo" class="form-select @error('tipo') is-invalid @enderror" required>
            @foreach($tipos as $k => $label)
            <option value="{{ $k }}" {{ old('tipo', $values['tipo'] ?? $item?->tipo ?? 'instrumento') == $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Nombre *</label>
        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $item?->nombre) }}" placeholder="Ej: Repique 12&quot;, Parches 12&quot; Remo, Llave afinación" required>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">Código/etiqueta</label>
        <input type="text" name="codigo" class="form-control" value="{{ old('codigo', $item?->codigo) }}" placeholder="Ej: BAN-REP-001">
    </div>
    <div class="col-md-3">
        <label class="form-label">Propiedad *</label>
        <select name="propietario_tipo" id="propietario_tipo" class="form-select @error('propietario_tipo') is-invalid @enderror" required>
            @foreach($propietarios as $k => $label)
            <option value="{{ $k }}" {{ old('propietario_tipo', $item?->propietario_tipo ?? 'escuela') == $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('propietario_tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6" id="alumno_wrap">
        <label class="form-label">Alumno (si es propio)</label>
        <select name="alumno_id" class="form-select @error('alumno_id') is-invalid @enderror">
            <option value="">Seleccionar alumno</option>
            @foreach($alumnos as $a)
            <option value="{{ $a->id }}" {{ old('alumno_id', $item?->alumno_id) == $a->id ? 'selected' : '' }}>{{ $a->nombre_apellido }}</option>
            @endforeach
        </select>
        @error('alumno_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="es_consumible" id="es_consumible" value="1" {{ old('es_consumible', $item?->es_consumible) ? 'checked' : '' }}>
            <label class="form-check-label" for="es_consumible">Es consumible (cantidad)</label>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Cantidad *</label>
        <input type="number" name="cantidad" class="form-control @error('cantidad') is-invalid @enderror" step="0.01" min="0" value="{{ old('cantidad', $item?->cantidad ?? 1) }}" required>
        @error('cantidad')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Unidad</label>
        <input type="text" name="unidad" class="form-control" value="{{ old('unidad', $item?->unidad) }}" placeholder="u, pares, mts, kg...">
    </div>
    <div class="col-md-3">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="utilitario" id="utilitario" value="1" {{ old('utilitario', $item?->utilitario) ? 'checked' : '' }}>
            <label class="form-check-label" for="utilitario">Utilitario / repuesto</label>
        </div>
    </div>

    <div class="col-12"><hr></div>

    <div class="col-md-3">
        <label class="form-label">Marca</label>
        <input type="text" name="marca" class="form-control" value="{{ old('marca', $item?->marca) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Modelo</label>
        <input type="text" name="modelo" class="form-control" value="{{ old('modelo', $item?->modelo) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Línea</label>
        <input type="text" name="linea" class="form-control" value="{{ old('linea', $item?->linea) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Material</label>
        <input type="text" name="material" class="form-control" value="{{ old('material', $item?->material) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Color</label>
        <input type="text" name="color" class="form-control" value="{{ old('color', $item?->color) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Medida (texto)</label>
        <input type="text" name="medida" class="form-control" value="{{ old('medida', $item?->medida) }}" placeholder="Ej: 12&quot; 10 torres">
    </div>
    <div class="col-md-3">
        <label class="form-label">Diámetro (pulgadas)</label>
        <input type="number" name="diametro_pulgadas" class="form-control" step="0.01" min="0" max="99.99" value="{{ old('diametro_pulgadas', $item?->diametro_pulgadas) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Torres</label>
        <input type="number" name="torres" class="form-control" min="0" max="999" value="{{ old('torres', $item?->torres) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Año fabricación</label>
        <input type="number" name="anio_fabricacion" class="form-control" min="1900" max="2100" value="{{ old('anio_fabricacion', $item?->anio_fabricacion) }}">
    </div>

    <div class="col-12"><hr></div>

    <div class="col-md-3">
        <label class="form-label">Origen</label>
        <select name="origen_adquisicion" class="form-select @error('origen_adquisicion') is-invalid @enderror">
            <option value="">—</option>
            @foreach($origenes as $k => $label)
            <option value="{{ $k }}" {{ old('origen_adquisicion', $item?->origen_adquisicion) == $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('origen_adquisicion')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Fecha adquisición</label>
        <input type="date" name="fecha_adquisicion" class="form-control" value="{{ old('fecha_adquisicion', $item?->fecha_adquisicion?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Precio</label>
        <input type="number" name="precio" class="form-control" step="0.01" min="0" value="{{ old('precio', $item?->precio) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Estado *</label>
        <select name="estado" class="form-select @error('estado') is-invalid @enderror" required>
            @foreach($estados as $k => $label)
            <option value="{{ $k }}" {{ old('estado', $item?->estado ?? 'bueno') == $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Reparado en</label>
        <input type="date" name="reparado_en" class="form-control" value="{{ old('reparado_en', $item?->reparado_en?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-9">
        <label class="form-label">Detalle reparación</label>
        <input type="text" name="detalle_reparacion" class="form-control" value="{{ old('detalle_reparacion', $item?->detalle_reparacion) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2">{{ old('notas', $item?->notas) }}</textarea>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const propietario = document.getElementById('propietario_tipo');
    const wrap = document.getElementById('alumno_wrap');
    function sync() {
        if (!propietario || !wrap) return;
        const isAlumno = propietario.value === 'alumno';
        wrap.style.display = isAlumno ? '' : 'none';
    }
    propietario?.addEventListener('change', sync);
    sync();
});
</script>
@endpush

