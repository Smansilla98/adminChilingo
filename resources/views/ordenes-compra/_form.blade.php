@php
    $orden = $orden ?? null;
    $defaults = $defaults ?? [];
@endphp

<x-ito.form-section title="La orden" icon="bi-cart">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="oc-sede">Sede</label>
            <select id="oc-sede" name="sede_id" class="form-select @error('sede_id') is-invalid @enderror" required>
                <option value="">Elegí una sede…</option>
                @foreach($sedes as $s)
                    <option value="{{ $s->id }}" @selected(old('sede_id', $orden->sede_id ?? $defaults['sede_id'] ?? null) == $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
            @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="oc-motivo">Motivo</label>
            <select id="oc-motivo" name="motivo" class="form-select @error('motivo') is-invalid @enderror" required>
                @foreach($motivos as $k => $label)
                    <option value="{{ $k }}" @selected(old('motivo', $orden->motivo ?? $defaults['motivo'] ?? 'reposicion') == $k)>{{ $label }}</option>
                @endforeach
            </select>
            @error('motivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="oc-estado">Estado</label>
            <select id="oc-estado" name="estado" class="form-select @error('estado') is-invalid @enderror" required>
                @foreach($estados as $k => $label)
                    <option value="{{ $k }}" @selected(old('estado', $orden->estado ?? $defaults['estado'] ?? 'borrador') == $k)>{{ $label }}</option>
                @endforeach
            </select>
            @error('estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="oc-fecha">Para cuándo</label>
            <input type="date" id="oc-fecha" name="fecha_objetivo" class="form-control @error('fecha_objetivo') is-invalid @enderror" value="{{ old('fecha_objetivo', $orden?->fecha_objetivo?->format('Y-m-d')) }}">
            @error('fecha_objetivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8">
            <label class="form-label" for="oc-justificacion">Por qué hace falta</label>
            <textarea id="oc-justificacion" name="justificacion" class="form-control @error('justificacion') is-invalid @enderror" rows="2">{{ old('justificacion', $orden?->justificacion) }}</textarea>
            @error('justificacion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

@php
    $oldDescs = old('item_descripcion', []);
    if (count($oldDescs) > 0) {
        $filas = collect($oldDescs)->keys()->map(fn ($i) => [
            'descripcion' => old('item_descripcion')[$i] ?? '', 'tipo' => old('item_tipo')[$i] ?? '', 'familia' => old('item_familia')[$i] ?? '',
            'marca' => old('item_marca')[$i] ?? '', 'modelo' => old('item_modelo')[$i] ?? '', 'medida' => old('item_medida')[$i] ?? '',
            'cantidad' => old('item_cantidad')[$i] ?? 1, 'unidad' => old('item_unidad')[$i] ?? 'u', 'precio' => old('item_precio')[$i] ?? '',
        ]);
    } elseif (($orden?->items ?? collect())->isNotEmpty()) {
        $filas = $orden->items->map(fn ($it) => [
            'descripcion' => $it->descripcion, 'tipo' => $it->tipo, 'familia' => $it->familia, 'marca' => $it->marca, 'modelo' => $it->modelo,
            'medida' => $it->medida, 'cantidad' => $it->cantidad ?? 1, 'unidad' => $it->unidad ?? 'u', 'precio' => $it->precio_estimado,
        ]);
    } else {
        $filas = collect([[]]);
    }
@endphp
<x-ito.form-section title="Qué hay que comprar" icon="bi-list-check" help="Una fila por cosa distinta. Solo la descripción es obligatoria.">
    @error('items')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
    <div class="table-responsive">
        <table class="table table-sm align-middle oc-items mb-2" id="tabla-items" data-ito-no-cards>
            <thead>
                <tr>
                    <th style="min-width: 220px">Descripción</th>
                    <th style="min-width: 110px">Tipo</th>
                    <th style="min-width: 110px">Familia</th>
                    <th style="min-width: 100px">Marca</th>
                    <th style="min-width: 100px">Modelo</th>
                    <th style="min-width: 90px">Medida</th>
                    <th style="min-width: 80px">Cant.</th>
                    <th style="min-width: 70px">Unidad</th>
                    <th style="min-width: 110px">Precio u.</th>
                    <th><span class="visually-hidden">Quitar</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($filas as $it)
                    @include('ordenes-compra._item_fila', ['it' => $it])
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-item"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar ítem</button>
        <span class="text-muted">Total estimado: <strong class="text-body" id="oc-total">$ 0</strong></span>
    </div>
    <template id="oc-fila-plantilla">@include('ordenes-compra._item_fila', ['it' => []])</template>
</x-ito.form-section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.querySelector('#tabla-items tbody');
    const plantilla = document.getElementById('oc-fila-plantilla');
    const total = document.getElementById('oc-total');
    if (!tbody || !plantilla) return;

    function recalcular() {
        let suma = 0;
        tbody.querySelectorAll('tr').forEach((tr) => {
            const c = parseFloat(tr.querySelector('.oc-cant')?.value || '0');
            const p = parseFloat(tr.querySelector('.oc-precio')?.value || '0');
            if (!isNaN(c) && !isNaN(p)) suma += c * p;
        });
        if (total) total.textContent = '$ ' + suma.toLocaleString('es-AR', { maximumFractionDigits: 2 });
    }

    document.getElementById('btn-add-item')?.addEventListener('click', () => {
        tbody.appendChild(plantilla.content.cloneNode(true));
        tbody.lastElementChild?.querySelector('input')?.focus();
    });
    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-remove-row');
        if (btn && tbody.rows.length > 1) {
            btn.closest('tr').remove();
            recalcular();
        }
    });
    tbody.addEventListener('input', recalcular);
    recalcular();
});
</script>
@endpush
