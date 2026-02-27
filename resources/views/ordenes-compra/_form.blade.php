@php
    $orden = $orden ?? null;
    $defaults = $defaults ?? [];
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Sede *</label>
        <select name="sede_id" class="form-select @error('sede_id') is-invalid @enderror" required>
            <option value="">Seleccionar sede</option>
            @foreach($sedes as $s)
            <option value="{{ $s->id }}" {{ old('sede_id', $orden->sede_id ?? $defaults['sede_id'] ?? null) == $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
            @endforeach
        </select>
        @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Motivo *</label>
        <select name="motivo" class="form-select @error('motivo') is-invalid @enderror" required>
            @foreach($motivos as $k => $label)
            <option value="{{ $k }}" {{ old('motivo', $orden->motivo ?? $defaults['motivo'] ?? 'reposicion') == $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('motivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Estado *</label>
        <select name="estado" class="form-select @error('estado') is-invalid @enderror" required>
            @foreach($estados as $k => $label)
            <option value="{{ $k }}" {{ old('estado', $orden->estado ?? $defaults['estado'] ?? 'borrador') == $k ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
        @error('estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">Fecha objetivo</label>
        <input type="date" name="fecha_objetivo" class="form-control" value="{{ old('fecha_objetivo', $orden->fecha_objetivo?->format('Y-m-d')) }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">Justificación</label>
        <textarea name="justificacion" class="form-control" rows="2">{{ old('justificacion', $orden->justificacion) }}</textarea>
    </div>
</div>

<hr class="my-3">

<h6>Ítems de la orden</h6>
<p class="small text-muted">
Describí qué se quiere comprar (ej: “10 repiques 12&quot; marca X línea Y”, “20 parches 12&quot; Remo Ambassador”).  
Podés agregar varias filas con el botón “Agregar ítem”.
</p>

@error('items')<div class="alert alert-danger py-1 mb-2 small">{{ $message }}</div>@enderror

<div class="table-responsive">
    <table class="table table-sm align-middle" id="tabla-items">
        <thead>
            <tr>
                <th style="width: 10%">Tipo</th>
                <th style="width: 12%">Familia</th>
                <th>Descripción *</th>
                <th style="width: 12%">Marca</th>
                <th style="width: 12%">Modelo</th>
                <th style="width: 10%">Medida</th>
                <th style="width: 8%">Cant.</th>
                <th style="width: 8%">Unidad</th>
                <th style="width: 10%">Precio u.</th>
                <th style="width: 5%"></th>
            </tr>
        </thead>
        <tbody>
            @php
                $oldTipos = old('item_tipo', []);
                $oldFamilias = old('item_familia', []);
                $oldDescs = old('item_descripcion', []);
                $oldMarcas = old('item_marca', []);
                $oldModelos = old('item_modelo', []);
                $oldMedidas = old('item_medida', []);
                $oldCants = old('item_cantidad', []);
                $oldUnidades = old('item_unidad', []);
                $oldPrecios = old('item_precio', []);

                $rowsFromOld = count($oldDescs);
                $items = $orden?->items ?? collect();
                $renderFromOrden = $rowsFromOld === 0 && $items->count() > 0;
            @endphp

            @if($renderFromOrden)
                @foreach($items as $i => $it)
                <tr>
                    <td><input type="text" name="item_tipo[]" class="form-control form-control-sm" value="{{ $it->tipo }}" placeholder="instrumento"></td>
                    <td><input type="text" name="item_familia[]" class="form-control form-control-sm" value="{{ $it->familia }}" placeholder="Repique"></td>
                    <td><input type="text" name="item_descripcion[]" class="form-control form-control-sm" value="{{ $it->descripcion }}" required></td>
                    <td><input type="text" name="item_marca[]" class="form-control form-control-sm" value="{{ $it->marca }}"></td>
                    <td><input type="text" name="item_modelo[]" class="form-control form-control-sm" value="{{ $it->modelo }}"></td>
                    <td><input type="text" name="item_medida[]" class="form-control form-control-sm" value="{{ $it->medida }}"></td>
                    <td><input type="number" name="item_cantidad[]" class="form-control form-control-sm" step="0.01" min="0" value="{{ $it->cantidad ?? 1 }}"></td>
                    <td><input type="text" name="item_unidad[]" class="form-control form-control-sm" value="{{ $it->unidad ?? 'u' }}"></td>
                    <td><input type="number" name="item_precio[]" class="form-control form-control-sm" step="0.01" min="0" value="{{ $it->precio_estimado }}"></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">&times;</button>
                    </td>
                </tr>
                @endforeach
            @else
                @php $rows = max(1, $rowsFromOld); @endphp
                @for($i = 0; $i < $rows; $i++)
                <tr>
                    <td><input type="text" name="item_tipo[]" class="form-control form-control-sm" value="{{ $oldTipos[$i] ?? '' }}" placeholder="instrumento"></td>
                    <td><input type="text" name="item_familia[]" class="form-control form-control-sm" value="{{ $oldFamilias[$i] ?? '' }}" placeholder="Repique"></td>
                    <td><input type="text" name="item_descripcion[]" class="form-control form-control-sm" value="{{ $oldDescs[$i] ?? '' }}"></td>
                    <td><input type="text" name="item_marca[]" class="form-control form-control-sm" value="{{ $oldMarcas[$i] ?? '' }}"></td>
                    <td><input type="text" name="item_modelo[]" class="form-control form-control-sm" value="{{ $oldModelos[$i] ?? '' }}"></td>
                    <td><input type="text" name="item_medida[]" class="form-control form-control-sm" value="{{ $oldMedidas[$i] ?? '' }}"></td>
                    <td><input type="number" name="item_cantidad[]" class="form-control form-control-sm" step="0.01" min="0" value="{{ $oldCants[$i] ?? 1 }}"></td>
                    <td><input type="text" name="item_unidad[]" class="form-control form-control-sm" value="{{ $oldUnidades[$i] ?? 'u' }}"></td>
                    <td><input type="number" name="item_precio[]" class="form-control form-control-sm" step="0.01" min="0" value="{{ $oldPrecios[$i] ?? '' }}"></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">&times;</button>
                    </td>
                </tr>
                @endfor
            @endif
        </tbody>
    </table>
</div>
<button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-item">
    <i class="bi bi-plus"></i> Agregar ítem
</button>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('tabla-items')?.getElementsByTagName('tbody')[0];
    const btnAdd = document.getElementById('btn-add-item');

    function addRow() {
        if (!table) return;
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="item_tipo[]" class="form-control form-control-sm" placeholder="instrumento"></td>
            <td><input type="text" name="item_familia[]" class="form-control form-control-sm" placeholder="Repique"></td>
            <td><input type="text" name="item_descripcion[]" class="form-control form-control-sm"></td>
            <td><input type="text" name="item_marca[]" class="form-control form-control-sm"></td>
            <td><input type="text" name="item_modelo[]" class="form-control form-control-sm"></td>
            <td><input type="text" name="item_medida[]" class="form-control form-control-sm"></td>
            <td><input type="number" name="item_cantidad[]" class="form-control form-control-sm" step="0.01" min="0" value="1"></td>
            <td><input type="text" name="item_unidad[]" class="form-control form-control-sm" value="u"></td>
            <td><input type="number" name="item_precio[]" class="form-control form-control-sm" step="0.01" min="0"></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">&times;</button>
            </td>
        `;
        table.appendChild(tr);
    }

    btnAdd?.addEventListener('click', addRow);

    table?.addEventListener('click', (e) => {
        const target = e.target;
        if (target.classList.contains('btn-remove-row') || target.closest('.btn-remove-row')) {
            const btn = target.closest('.btn-remove-row');
            const tr = btn.closest('tr');
            if (tr && table.rows.length > 1) {
                tr.remove();
            }
        }
    });
});
</script>
@endpush

