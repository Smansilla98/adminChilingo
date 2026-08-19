@php
    /** @var \App\Models\VillaGesellInsumo $insumo */
@endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Nombre *</label>
        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $insumo->nombre) }}" required>
        @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Categoría *</label>
        <select name="categoria" class="form-select" required>
            @foreach(\App\Models\VillaGesellInsumo::CATEGORIAS as $k => $v)
                <option value="{{ $k }}" @selected(old('categoria', $insumo->categoria) === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Unidad</label>
        <input type="text" name="unidad" class="form-control" value="{{ old('unidad', $insumo->unidad) }}" placeholder="u, kg, lts…">
    </div>
    <div class="col-md-3">
        <label class="form-label">Cantidad *</label>
        <input type="number" step="0.01" min="0" name="cantidad" class="form-control" value="{{ old('cantidad', $insumo->cantidad) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Costo unitario *</label>
        <input type="number" step="0.01" min="0" name="costo_unitario" class="form-control" value="{{ old('costo_unitario', $insumo->costo_unitario) }}" required>
    </div>
    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2">{{ old('notas', $insumo->notas) }}</textarea>
    </div>
</div>
