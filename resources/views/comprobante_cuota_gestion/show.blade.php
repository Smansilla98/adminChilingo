@extends('layouts.app')

@section('title', 'Comprobante #' . $comprobanteCuotaAlumno->id)
@section('page-title', 'Comprobante enviado por alumno')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Envío #{{ $comprobanteCuotaAlumno->id }}</span>
        <div class="d-flex gap-2 flex-wrap">
            @if($comprobanteCuotaAlumno->comprobante_path)
            <a href="{{ route('comprobantes-cuota-alumnos.comprobante', $comprobanteCuotaAlumno->id) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><i class="bi bi-file-earmark"></i> Archivo</a>
            @endif
            @if($comprobanteCuotaAlumno->estaPendiente())
            <form action="{{ route('comprobantes-cuota-alumnos.visto', $comprobanteCuotaAlumno->id) }}" method="post" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Marcar como visto</button>
            </form>
            @endif
            <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="btn btn-sm btn-secondary">Volver al listado</a>
        </div>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Estado</dt>
            <dd class="col-sm-9">{{ $comprobanteCuotaAlumno->estado === 'pendiente' ? 'Pendiente de revisión' : 'Visto' }}</dd>
            <dt class="col-sm-3">Alumno</dt>
            <dd class="col-sm-9">{{ $comprobanteCuotaAlumno->alumno?->nombre_apellido ?? '—' }} @if($comprobanteCuotaAlumno->alumno?->dni)<span class="text-muted">DNI {{ $comprobanteCuotaAlumno->alumno->dni }}</span>@endif</dd>
            <dt class="col-sm-3">Sede (elegida en el formulario)</dt>
            <dd class="col-sm-9">{{ $comprobanteCuotaAlumno->sede?->nombre ?? '—' }}</dd>
            <dt class="col-sm-3">Fecha de pago declarada</dt>
            <dd class="col-sm-9">{{ $comprobanteCuotaAlumno->fecha_pago?->format('d/m/Y') }}</dd>
            <dt class="col-sm-3">Monto total</dt>
            <dd class="col-sm-9">$ {{ number_format($comprobanteCuotaAlumno->monto_total, 2, ',', '.') }}</dd>
            @if($comprobanteCuotaAlumno->notas)
            <dt class="col-sm-3">Notas del alumno/familia</dt>
            <dd class="col-sm-9">{{ $comprobanteCuotaAlumno->notas }}</dd>
            @endif
        </dl>
        <h6 class="mt-4">Cuotas / bloques incluidos</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>Bloque</th><th>Cuota</th><th>Monto</th></tr>
                </thead>
                <tbody>
                    @foreach($comprobanteCuotaAlumno->items as $it)
                    <tr>
                        <td>{{ $it->bloque?->nombre ?? '—' }}</td>
                        <td>{{ $it->cuota?->nombre ?? '—' }}</td>
                        <td>$ {{ number_format($it->monto, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">Este envío <strong>no</strong> registra aún el pago en el módulo de pagos: podés cargarlo desde «Registrar pago» cuando verifiques el comprobante.</p>
    </div>
</div>
@endsection
