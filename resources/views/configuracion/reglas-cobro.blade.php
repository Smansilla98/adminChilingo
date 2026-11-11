@extends('layouts.app')

@section('title', 'Reglas de cobro')
@section('page-title', 'Reglas de cobro')

@section('content')
<x-ito.shell-page title="Reglas de cobro" subtitle="Recargos y descuentos que se calculan en el estado de cuenta de cada alumno. Todo arranca apagado." :plain="true">
    <form method="POST" action="{{ route('configuracion.reglas-cobro.update') }}">
        @csrf
        @method('PUT')

        <x-ito.form-section title="Recargo por mora" icon="bi-exclamation-circle" help="Se aplica a la cuota que sigue sin pagarse después de los días indicados desde el vencimiento.">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="recargo-activo" name="recargo[activo]" value="1" @checked($reglas['recargo']['activo'])>
                <label class="form-check-label" for="recargo-activo">Cobrar recargo por mora</label>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="recargo-tipo">Tipo</label>
                    <select id="recargo-tipo" name="recargo[tipo]" class="form-select @error('recargo.tipo') is-invalid @enderror">
                        @foreach($tipos as $k => $v)
                            <option value="{{ $k }}" @selected($reglas['recargo']['tipo'] === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    @error('recargo.tipo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="recargo-valor">Valor</label>
                    <input type="number" step="0.01" min="0" id="recargo-valor" name="recargo[valor]" class="form-control @error('recargo.valor') is-invalid @enderror" value="{{ old('recargo.valor', $reglas['recargo']['valor']) }}">
                    <div class="form-text">Si es porcentaje, es % de la cuota. Si es monto fijo, son pesos.</div>
                    @error('recargo.valor')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="recargo-dias">Días de gracia</label>
                    <input type="number" min="0" max="365" id="recargo-dias" name="recargo[dias]" class="form-control" value="{{ old('recargo.dias', $reglas['recargo']['dias']) }}">
                    <div class="form-text">El recargo empieza después de estos días del vencimiento.</div>
                </div>
            </div>
        </x-ito.form-section>

        <x-ito.form-section title="Descuento por grupo familiar" icon="bi-people" help="Para alumnos que están en el mismo grupo familiar (Personas → grupo familiar). El primero paga completo; desde el segundo se descuenta este porcentaje.">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="familiar-activo" name="familiar[activo]" value="1" @checked($reglas['familiar']['activo'])>
                <label class="form-check-label" for="familiar-activo">Aplicar descuento por grupo familiar</label>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="familiar-pct">Porcentaje</label>
                <div class="input-group">
                    <input type="number" step="0.01" min="0" max="100" id="familiar-pct" name="familiar[porcentaje]" class="form-control @error('familiar.porcentaje') is-invalid @enderror" value="{{ old('familiar.porcentaje', $reglas['familiar']['porcentaje']) }}">
                    <span class="input-group-text">%</span>
                </div>
                @error('familiar.porcentaje')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </x-ito.form-section>

        <x-ito.form-section title="Descuento por pago adelantado" icon="bi-calendar-check" help="Si el alumno paga la cuota hasta los días indicados antes del vencimiento, se descuenta este porcentaje.">
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" role="switch" id="adelantado-activo" name="adelantado[activo]" value="1" @checked($reglas['adelantado']['activo'])>
                <label class="form-check-label" for="adelantado-activo">Aplicar descuento por pago adelantado</label>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="adelantado-pct">Porcentaje</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0" max="100" id="adelantado-pct" name="adelantado[porcentaje]" class="form-control @error('adelantado.porcentaje') is-invalid @enderror" value="{{ old('adelantado.porcentaje', $reglas['adelantado']['porcentaje']) }}">
                        <span class="input-group-text">%</span>
                    </div>
                    @error('adelantado.porcentaje')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="adelantado-dias">Días antes del vencimiento</label>
                    <input type="number" min="0" max="120" id="adelantado-dias" name="adelantado[dias]" class="form-control" value="{{ old('adelantado.dias', $reglas['adelantado']['dias']) }}">
                </div>
            </div>
        </x-ito.form-section>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-primary">Guardar reglas</button>
            <a href="{{ route('cuotas.index') }}" class="btn btn-link">Volver a cuotas</a>
        </div>
    </form>
</x-ito.shell-page>
@endsection
