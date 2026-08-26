@extends('layouts.app')

@section('title', 'Editar facturación')
@section('page-title', 'Editar facturación — ' . $facturacionMensual->nombre_mes . ' ' . $facturacionMensual->año)

@section('content')
<x-ito.shell-page
    title="Editar facturación mensual"
    eyebrow="Facturación"
    subtitle="{{ $facturacionMensual->nombre_mes }} {{ $facturacionMensual->año }}"
>

        @include('partials.form-ayuda-intro', ['text' => 'Corregí los números de este mes.'])
        <p class="text-muted small mb-3">Mes: {{ $facturacionMensual->nombre_mes }} {{ $facturacionMensual->año }} — {{ $facturacionMensual->sede?->nombre ?? 'Toda la escuela' }}</p>
        <form action="{{ route('facturacion-mensual.update', $facturacionMensual) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Cantidad de alumnxs *</label>
                    <input type="number" name="cantidad_alumnos" class="form-control" min="0" value="{{ old('cantidad_alumnos', $facturacionMensual->cantidad_alumnos) }}" required>
                    @error('cantidad_alumnos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto facturado *</label>
                    <input type="number" name="monto_facturado" class="form-control" step="0.01" min="0" value="{{ old('monto_facturado', $facturacionMensual->monto_facturado) }}" required>
                    @error('monto_facturado')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Monto previsto</label>
                    <input type="number" name="monto_previsto" class="form-control" step="0.01" min="0" value="{{ old('monto_previsto', $facturacionMensual->monto_previsto) }}">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="2">{{ old('notas', $facturacionMensual->notas) }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="{{ route('facturacion-mensual.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
</x-ito.shell-page>

@endsection
