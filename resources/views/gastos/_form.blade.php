@php
    /** @var \App\Models\Gasto|null $gasto */
    $gasto = $gasto ?? null;
@endphp
<x-ito.form-section title="El gasto" icon="bi-wallet2">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="tipo_gasto">Tipo</label>
            <select name="tipo" id="tipo_gasto" class="form-select @error('tipo') is-invalid @enderror" required>
                @foreach(\App\Models\Gasto::TIPOS as $k => $v)
                    <option value="{{ $k }}" @selected(old('tipo', $gasto?->tipo) === $k)>{{ $v }}</option>
                @endforeach
            </select>
            @error('tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="subtipo_gasto">Subtipo</label>
            <select name="subtipo" id="subtipo_gasto" class="form-select @error('subtipo') is-invalid @enderror">
                <option value="">Opcional</option>
            </select>
            @error('subtipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="gasto-monto">Monto</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" id="gasto-monto" name="monto" class="form-control @error('monto') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto', $gasto?->monto) }}" required>
            </div>
            @error('monto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="gasto-fecha">Fecha</label>
            <input type="date" id="gasto-fecha" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha', $gasto?->fecha?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-8">
            <label class="form-label" for="gasto-descripcion">Descripción</label>
            <input type="text" id="gasto-descripcion" name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" value="{{ old('descripcion', $gasto?->descripcion) }}" placeholder="Ej.: pago de luz de marzo">
            @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="A qué corresponde" icon="bi-geo-alt" help="Opcional: sirve para ver los gastos por sede en Reportes.">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="sede_id">Sede</label>
            <select name="sede_id" id="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                <option value="">Sin sede</option>
                @foreach($sedes as $s)
                    <option value="{{ $s->id }}" @selected(old('sede_id', $gasto?->sede_id) == $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
            @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="gasto-bloque">Bloque</label>
            <select name="bloque_id" id="gasto-bloque" class="form-select @error('bloque_id') is-invalid @enderror">
                <option value="">Sin bloque</option>
                @foreach($bloques as $b)
                    <option value="{{ $b->id }}" @selected(old('bloque_id', $gasto?->bloque_id) == $b->id)>{{ $b->nombre }} ({{ $b->sede?->nombre ?? '—' }})</option>
                @endforeach
            </select>
            @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="gasto-proveedor">Proveedor</label>
            <input type="text" id="gasto-proveedor" name="proveedor" class="form-control @error('proveedor') is-invalid @enderror" value="{{ old('proveedor', $gasto?->proveedor) }}">
            @error('proveedor')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="gasto-notas">Notas</label>
            <textarea id="gasto-notas" name="notas" class="form-control @error('notas') is-invalid @enderror" rows="2">{{ old('notas', $gasto?->notas) }}</textarea>
            @error('notas')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

@push('scripts')
<script>
(function () {
    const subtipos = @json(\App\Models\Gasto::SUBTIPOS ?? []);
    const tipoSelect = document.getElementById('tipo_gasto');
    const subtipoSelect = document.getElementById('subtipo_gasto');
    const actual = @json(old('subtipo', $gasto?->subtipo));
    function fillSubtipos() {
        const opts = subtipos[tipoSelect.value] || {};
        subtipoSelect.innerHTML = '<option value="">Opcional</option>';
        for (const [k, v] of Object.entries(opts)) {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = v;
            if (actual === k) opt.selected = true;
            subtipoSelect.appendChild(opt);
        }
        subtipoSelect.disabled = Object.keys(opts).length === 0;
    }
    tipoSelect.addEventListener('change', fillSubtipos);
    fillSubtipos();
})();
</script>
@endpush
