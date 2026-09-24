@php
    /** @var \App\Models\FacturacionMensual|null $facturacion */
    $facturacion = $facturacion ?? null;
@endphp
@unless($facturacion)
<x-ito.form-section title="Período" icon="bi-calendar-month">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="fm-sede">Sede</label>
            <select id="fm-sede" name="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                <option value="">Toda la escuela</option>
                @foreach($sedes as $s)
                    <option value="{{ $s->id }}" @selected(old('sede_id') == $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
            @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="fm-mes">Mes</label>
            <select id="fm-mes" name="mes" class="form-select @error('mes') is-invalid @enderror" required>
                @foreach($meses as $n => $nombre)
                    <option value="{{ $n }}" @selected((string) old('mes', now()->month) === (string) $n)>{{ $nombre }}</option>
                @endforeach
            </select>
            @error('mes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-4">
            <label class="form-label" for="fm-anio">Año</label>
            <input type="number" id="fm-anio" name="año" class="form-control @error('año') is-invalid @enderror" value="{{ old('año', date('Y')) }}" min="2020" max="2030" required>
            @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>
@endunless

<x-ito.form-section title="Números del mes" icon="bi-calculator">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="fm-alumnos">Cantidad de alumnos</label>
            <input type="number" id="fm-alumnos" name="cantidad_alumnos" class="form-control @error('cantidad_alumnos') is-invalid @enderror" min="0" value="{{ old('cantidad_alumnos', $facturacion?->cantidad_alumnos ?? 0) }}" required>
            @error('cantidad_alumnos')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="fm-facturado">Monto facturado</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" id="fm-facturado" name="monto_facturado" class="form-control @error('monto_facturado') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto_facturado', $facturacion?->monto_facturado) }}" required>
            </div>
            @error('monto_facturado')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="fm-previsto">Monto previsto</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" id="fm-previsto" name="monto_previsto" class="form-control @error('monto_previsto') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto_previsto', $facturacion?->monto_previsto) }}">
            </div>
            <div class="form-text">Opcional, para comparar con lo facturado.</div>
            @error('monto_previsto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="fm-notas">Notas</label>
            <textarea id="fm-notas" name="notas" class="form-control" rows="2">{{ old('notas', $facturacion?->notas) }}</textarea>
        </div>
    </div>
</x-ito.form-section>
