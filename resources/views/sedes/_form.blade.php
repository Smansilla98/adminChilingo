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

@if(\Illuminate\Support\Facades\Schema::hasColumn('sedes', 'en_mapa'))
<x-ito.form-section title="Ubicación en el mapa" icon="bi-map"
    help="Lo que se publica en el mapa del programa: nombre, dirección y este punto. Nada más de la sede.">
    <div class="row g-3">
        <div class="col-12">
            <div class="form-check form-switch">
                <input type="hidden" name="en_mapa" value="0">
                <input type="checkbox" name="en_mapa" class="form-check-input" id="sede-en-mapa" value="1" role="switch" @checked(old('en_mapa', $sede?->en_mapa ?? false))>
                <label class="form-check-label" for="sede-en-mapa">Mostrar en el mapa público de sedes</label>
            </div>
        </div>
        <div class="col-12">
            <div class="sm-mapa" style="height: 300px" data-mapa-picker role="application" aria-label="Mapa para ubicar la sede: hacé clic donde está la puerta"></div>
            <p class="form-text mb-0" data-mapa-aviso aria-live="polite">Hacé clic en el mapa donde está la puerta (podés arrastrar el punto), o buscá por la dirección.</p>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sede-lat">Latitud</label>
            <input type="number" step="any" id="sede-lat" name="latitud" class="form-control @error('latitud') is-invalid @enderror" value="{{ old('latitud', $sede?->latitud) }}" inputmode="decimal">
            @error('latitud')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sede-lng">Longitud</label>
            <input type="number" step="any" id="sede-lng" name="longitud" class="form-control @error('longitud') is-invalid @enderror" value="{{ old('longitud', $sede?->longitud) }}" inputmode="decimal">
            @error('longitud')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
            <button type="button" class="btn btn-outline-secondary" data-mapa-buscar><i class="bi bi-search"></i> Buscar dirección</button>
            <button type="button" class="btn btn-outline-secondary" data-mapa-limpiar title="Quitar la ubicación" aria-label="Quitar la ubicación"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</x-ito.form-section>
@push('scripts')
    @vite(['resources/js/sedes-mapa.js'])
@endpush
@endif

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
