@php
    /** @var \App\Models\Sede|null $sede */
    $sede = $sede ?? null;
@endphp
<x-ito.form-section title="Datos de la sede" icon="bi-geo-alt">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="sede-nombre">Nombre</label>
            <input type="text" id="sede-nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $sede?->nombre) }}" required autofocus>
            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="sede-direccion">Dirección</label>
            <input type="text" id="sede-direccion" name="direccion" class="form-control @error('direccion') is-invalid @enderror" value="{{ old('direccion', $sede?->direccion) }}" autocomplete="street-address">
            @error('direccion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sede-tipo">Tipo de propiedad</label>
            @php $tipo = old('tipo_propiedad', $sede?->tipo_propiedad ?? 'alquilada'); @endphp
            <select id="sede-tipo" name="tipo_propiedad" class="form-select @error('tipo_propiedad') is-invalid @enderror">
                @foreach(['alquilada' => 'Alquilada', 'propia' => 'Propia', 'compartida' => 'Compartida', 'otro' => 'Otro'] as $valor => $etiqueta)
                    <option value="{{ $valor }}" @selected($tipo === $valor)>{{ $etiqueta }}</option>
                @endforeach
            </select>
            @error('tipo_propiedad')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sede-alquiler">Alquiler mensual</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" id="sede-alquiler" name="costo_alquiler_mensual" class="form-control @error('costo_alquiler_mensual') is-invalid @enderror" step="0.01" min="0" value="{{ old('costo_alquiler_mensual', $sede?->costo_alquiler_mensual) }}">
            </div>
            <div class="form-text">Solo si la sede es alquilada.</div>
            @error('costo_alquiler_mensual')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 d-flex align-items-center pt-md-4">
            <div class="form-check form-switch">
                <input type="checkbox" name="activo" class="form-check-input" id="activo" value="1" role="switch" @checked(old('activo', $sede?->activo ?? true))>
                <label class="form-check-label" for="activo">Sede activa</label>
            </div>
        </div>
    </div>
</x-ito.form-section>

@if(\Illuminate\Support\Facades\Schema::hasColumn('sedes', 'liquidacion_porc_docente'))
<x-ito.form-section title="Reparto de la cuota" icon="bi-pie-chart"
    help="Cuánto se queda la escuela y qué porcentaje va al profe. Al registrar un pago de esta sede se usan estos valores si no escribís otro monto.">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="sede-retencion">Lo que se queda la escuela</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" id="sede-retencion" name="liquidacion_retencion_escuela" class="form-control @error('liquidacion_retencion_escuela') is-invalid @enderror" step="0.01" min="0" value="{{ old('liquidacion_retencion_escuela', $sede?->liquidacion_retencion_escuela ?? 0) }}">
            </div>
            <div class="form-text">Ej.: si la cuota es $25.000 y la escuela se queda $1.000, el % del profe se calcula sobre $24.000.</div>
            @error('liquidacion_retencion_escuela')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="sede-porc">Porcentaje para el profesor</label>
            <div class="input-group">
                <input type="number" id="sede-porc" name="liquidacion_porc_docente" class="form-control @error('liquidacion_porc_docente') is-invalid @enderror" step="0.1" min="0" max="100" value="{{ old('liquidacion_porc_docente', $sede?->liquidacion_porc_docente ?? 40) }}">
                <span class="input-group-text">%</span>
            </div>
            @error('liquidacion_porc_docente')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>
@endif
