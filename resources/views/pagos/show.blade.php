@extends('layouts.app')

@section('title', 'Pago #' . $pago->id)
@section('page-title', 'Detalle del pago')

@section('content')
<div class="card">
    <div class="card-header">Pago del {{ $pago->fecha_pago->format('d/m/Y') }}</div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-3">Monto total</dt>
            <dd class="col-sm-9">$ {{ number_format($pago->monto_total, 2, ',', '.') }}</dd>
            <dt class="col-sm-3">Comprobante</dt>
            <dd class="col-sm-9">
                @if($pago->comprobante_path)
                <a href="{{ route('pagos.comprobante', $pago) }}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-file-pdf"></i> Ver PDF</a>
                @else
                —
                @endif
            </dd>
            <dt class="col-sm-3">Registrado por</dt>
            <dd class="col-sm-9">{{ $pago->registradoPor?->name ?? '-' }}</dd>
            @if($pago->notas)
            <dt class="col-sm-3">Notas</dt>
            <dd class="col-sm-9">{{ $pago->notas }}</dd>
            @endif
        </dl>
        <h6 class="mt-3">Detalle por alumno (trazabilidad)</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Cuota</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pago->detalles as $d)
                    <tr>
                        <td>{{ $d->alumno->nombre_apellido }}</td>
                        <td>{{ $d->cuota->nombre }}</td>
                        <td>$ {{ number_format($d->monto, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <a href="{{ route('pagos.index') }}" class="btn btn-secondary">Volver</a>
    </div>
</div>
@endsection
