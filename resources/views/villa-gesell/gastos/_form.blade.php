@php
    /** @var \App\Models\VillaGesellGasto $gasto */
@endphp
<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">Tipo *</label>
        <select name="tipo" class="form-select" required>
            @foreach(\App\Models\VillaGesellGasto::TIPOS as $k => $v)
                <option value="{{ $k }}" @selected(old('tipo', $gasto->tipo) === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Modo *</label>
        <select name="modo" class="form-select" required>
            @foreach(\App\Models\VillaGesellGasto::MODOS as $k => $v)
                <option value="{{ $k }}" @selected(old('modo', $gasto->modo) === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <small class="text-muted">El valor que cargás es <strong>por día</strong>. Si el modo es “por día” (o el tipo es gasto diario), el plan hace valor × {{ $diasGira ?? 'N' }} días.</small>
    </div>
    <div class="col-md-4">
        <label class="form-label">Fecha (opcional)</label>
        <input type="date" name="fecha" class="form-control" value="{{ old('fecha', optional($gasto->fecha)->toDateString()) }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">Concepto *</label>
        <input type="text" name="concepto" class="form-control" value="{{ old('concepto', $gasto->concepto) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Valor *</label>
        <input type="number" step="0.01" min="0" name="monto" class="form-control" value="{{ old('monto', $gasto->monto) }}" required>
        <div class="form-text">Si es por día, este número se multiplica por los días de la gira.</div>
    </div>
    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2">{{ old('notas', $gasto->notas) }}</textarea>
    </div>
</div>
