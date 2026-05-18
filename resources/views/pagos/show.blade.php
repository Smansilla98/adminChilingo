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
            @php
                $sumAbonoProf = $pago->detalles->sum(fn ($d) => (float) ($d->abono_profesor ?? 0));
                $tieneAbonoProf = $pago->detalles->contains(fn ($d) => $d->abono_profesor !== null);
            @endphp
            @if($tieneAbonoProf)
            <dt class="col-sm-3">Total abono profesor</dt>
            <dd class="col-sm-9">$ {{ number_format($sumAbonoProf, 2, ',', '.') }} <span class="text-muted small">(suma de lo liquidado por alumno en este pago)</span></dd>
            @php
                $sumRestoEscuela = $pago->detalles->sum(function ($d) {
                    if ($d->abono_profesor === null || ! $d->cuota) {
                        return 0;
                    }

                    return max(0, (float) $d->cuota->monto - (float) $d->abono_profesor);
                });
            @endphp
            @if($sumRestoEscuela > 0)
            <dt class="col-sm-3">Ref. resto escuela</dt>
            <dd class="col-sm-9">$ {{ number_format($sumRestoEscuela, 2, ',', '.') }} <span class="text-muted small">(por línea: monto cuota de referencia − abono al docente; suma si hay varios alumnos)</span></dd>
            @endif
            @endif
            <dt class="col-sm-3">Comprobante</dt>
            <dd class="col-sm-9">
                @if($pago->comprobante_path)
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalComprobantePago" data-comprobante-src="{{ route('pagos.comprobante', $pago) }}" data-comprobante-label="Comprobante — pago #{{ $pago->id }}"><i class="bi bi-file-earmark"></i> Ver comprobante</button>
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
                        <th>Monto alumno</th>
                        <th>Cuota (ref.)</th>
                        <th>Ref. cuota (liq.)</th>
                        <th>Resto escuela (ref.)</th>
                        <th>%</th>
                        <th>Abono prof.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pago->detalles as $d)
                    @php
                        $refEscuelaFila = ($d->abono_profesor !== null && $d->cuota)
                            ? max(0, (float) $d->cuota->monto - (float) $d->abono_profesor)
                            : null;
                    @endphp
                    <tr>
                        <td>{{ $d->alumno->nombre_apellido }}</td>
                        <td>{{ $d->cuota->nombre }}</td>
                        <td>$ {{ number_format($d->monto, 2, ',', '.') }}</td>
                        <td>$ {{ number_format((float) $d->cuota->monto, 2, ',', '.') }}</td>
                        <td>@if($d->abono_base !== null) $ {{ number_format((float) $d->abono_base, 2, ',', '.') }} @else — @endif</td>
                        <td>@if($refEscuelaFila !== null) $ {{ number_format($refEscuelaFila, 2, ',', '.') }} @else — @endif</td>
                        <td>@if($d->abono_porcentaje !== null) {{ number_format((float) $d->abono_porcentaje, 2, ',', '.') }}% @else — @endif</td>
                        <td>@if($d->abono_profesor !== null) $ {{ number_format((float) $d->abono_profesor, 2, ',', '.') }} @else — @endif</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @php $primeraNotaAbono = $pago->detalles->first(fn ($d) => filled($d->abono_nota))?->abono_nota; @endphp
        @if($primeraNotaAbono)
        <p class="small text-muted mb-0"><strong>Nota liquidación:</strong> {{ $primeraNotaAbono }}</p>
        @endif
        <a href="{{ route('pagos.edit', $pago) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('pagos.index') }}" class="btn btn-secondary">Volver</a>
    </div>
</div>
@include('pagos._modal_comprobante')
@endsection
