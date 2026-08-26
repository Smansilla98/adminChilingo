@extends('layouts.app')

@section('title', 'Comprobante #' . $comprobanteCuotaAlumno->id)
@section('page-title', 'Comprobante enviado por alumno')

@section('content')
@php
    $estado = $comprobanteCuotaAlumno->estado;
    $badgeClass = match($estado) {
        'pendiente' => 'bg-warning text-dark',
        'pagado' => 'bg-success',
        default => 'bg-secondary',
    };
@endphp
<x-ito.shell-page
    title="Envío #{{ $comprobanteCuotaAlumno->id }}"
    eyebrow="Comprobantes"
    subtitle="{{ $comprobanteCuotaAlumno->etiquetaEstado() }}"
>
    <x-slot:actions>
            @if($comprobanteCuotaAlumno->comprobante_path)
            <a href="{{ route('comprobantes-cuota-alumnos.comprobante', $comprobanteCuotaAlumno->id) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><i class="bi bi-file-earmark"></i> Archivo</a>
            @endif
            @if(auth()->user()->isAdmin() && ! $comprobanteCuotaAlumno->estaPagado())
            <form action="{{ route('comprobantes-cuota-alumnos.aprobar-pago', $comprobanteCuotaAlumno->id) }}" method="post" class="d-inline"
                  onsubmit="return confirm('¿Registrar el pago con las cuotas de este comprobante y marcarlo como pagado?');">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-cash-coin"></i> Aprobar y registrar pago
                </button>
            </form>
            @endif
            @if($comprobanteCuotaAlumno->estaPendiente())
            <form action="{{ route('comprobantes-cuota-alumnos.visto', $comprobanteCuotaAlumno->id) }}" method="post" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">Solo marcar visto</button>
            </form>
            @endif
            @if($comprobanteCuotaAlumno->pago_id)
            <a href="{{ route('pagos.show', $comprobanteCuotaAlumno->pago_id) }}" class="btn btn-sm btn-success">Ver pago #{{ $comprobanteCuotaAlumno->pago_id }}</a>
            @endif
            <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="btn btn-sm btn-outline-secondary">Volver al listado</a>
    </x-slot:actions>
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        <dl class="row mb-0">
            <dt class="col-sm-3">Estado</dt>
            <dd class="col-sm-9">{{ $comprobanteCuotaAlumno->etiquetaEstado() }}</dd>
            <dt class="col-sm-3">Alumno</dt>
            <dd class="col-sm-9">
                {{ $comprobanteCuotaAlumno->alumno?->nombre_apellido ?? '—' }}
                @if($comprobanteCuotaAlumno->alumno?->dni)
                    <span class="text-muted">DNI {{ $comprobanteCuotaAlumno->alumno->dni }}</span>
                @endif
            </dd>
            <dt class="col-sm-3">Sede (formulario)</dt>
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
                    <tr><th>Bloque</th><th>Sede</th><th>Cuota</th><th>Monto</th></tr>
                </thead>
                <tbody>
                    @foreach($comprobanteCuotaAlumno->items as $it)
                    <tr>
                        <td>{{ $it->bloque?->nombre ?? '—' }}</td>
                        <td class="text-muted small">{{ $it->bloque?->sede?->nombre ?? '—' }}</td>
                        <td>{{ $it->cuota?->nombre ?? '—' }}</td>
                        <td>$ {{ number_format($it->monto, 2, ',', '.') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if(auth()->user()->isAdmin() && ! $comprobanteCuotaAlumno->estaPagado())
            <p class="text-muted small mb-0">
                <strong>Aprobar y registrar pago</strong> crea el cobro con estas líneas, copia el archivo al pago y cierra el comprobante.
                “Solo marcar visto” no registra dinero.
            </p>
        @elseif(! auth()->user()->isAdmin())
            <p class="text-muted small mb-0">Podés marcar como visto. El registro del pago lo hace administración.</p>
        @endif
</x-ito.shell-page>
@endsection
