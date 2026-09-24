@extends('layouts.app')

@section('title', 'Pago #'.$pago->id)
@section('page-title', 'Pago #'.$pago->id)

@section('content')
@php
    $peso = fn ($n) => '$ '.number_format((float) $n, 2, ',', '.');
    $sumAbonoProf = $pago->detalles->sum(fn ($d) => (float) ($d->abono_profesor ?? 0));
    $tieneAbonoProf = $pago->detalles->contains(fn ($d) => $d->abono_profesor !== null);
    $sumRestoEscuela = $pago->detalles->sum(fn ($d) => ($d->abono_profesor === null || ! $d->cuota) ? 0 : max(0, (float) $d->cuota->monto - (float) $d->abono_profesor));
    $primeraNotaAbono = $pago->detalles->first(fn ($d) => filled($d->abono_nota))?->abono_nota;
    $alumnos = $pago->detalles->pluck('alumno.nombre_apellido')->filter()->unique();
@endphp
<x-ito.shell-page :title="'Pago del '.$pago->fecha_pago->format('d/m/Y')" :subtitle="'Pago #'.$pago->id.' · '.$alumnos->take(3)->join(', ').($alumnos->count() > 3 ? ' y '.($alumnos->count() - 3).' más' : '')" :plain="true">
    <x-slot:actions>
        @if($pago->estaAnulado())
            <x-ito.status tone="danger" label="Anulado" />
        @else
            <x-ito.status tone="success" label="Registrado" />
        @endif
        <a href="{{ route('pagos.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('reverse', $pago)
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#anular-pago" aria-expanded="false" aria-controls="anular-pago"><i class="bi bi-x-octagon" aria-hidden="true"></i> Anular</button>
        @endcan
        @can('update', $pago)
            <a href="{{ route('pagos.edit', $pago) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    @if($pago->estaAnulado())
        <div class="alert alert-danger mb-0" role="status">
            <strong>Pago anulado</strong> el {{ $pago->anulado_at->format('d/m/Y H:i') }}@if($pago->anuladoPor) por {{ $pago->anuladoPor->name }}@endif.
            Motivo: {{ $pago->motivo_anulacion }}. No cuenta para saldos ni reportes.
        </div>
    @endif

    @can('reverse', $pago)
        <div class="collapse" id="anular-pago">
            <x-ito.detail-section title="Anular este pago" icon="bi-x-octagon" help="Queda en el historial pero deja de contar. Si vino de un comprobante del alumno, el comprobante vuelve a pendiente.">
                <form method="POST" action="{{ route('pagos.anular', $pago) }}">
                    @csrf
                    <label for="motivo-anulacion" class="form-label">Motivo de la anulación</label>
                    <textarea id="motivo-anulacion" name="motivo" class="form-control mb-3" rows="2" required minlength="5" maxlength="500"></textarea>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#anular-pago">Cancelar</button>
                        <button class="btn btn-danger" type="submit">Anular pago</button>
                    </div>
                </form>
            </x-ito.detail-section>
        </div>
    @endcan

    <x-ito.facts>
        <x-ito.fact label="Total cobrado" :value="$peso($pago->monto_total)" />
        @if($tieneAbonoProf)
            <x-ito.fact label="Para el profesor" :value="$peso($sumAbonoProf)" />
            @if($sumRestoEscuela > 0)<x-ito.fact label="Queda en la escuela (ref.)" :value="$peso($sumRestoEscuela)" />@endif
        @endif
        <x-ito.fact label="Líneas" :value="$pago->detalles->count()" />
        <x-ito.fact label="Registrado por" :value="$pago->registradoPor?->name" />
    </x-ito.facts>

    <div class="ito-detail-grid">
        <x-ito.detail-section title="Detalle por alumno" icon="bi-people" :flush="true">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Cuota</th>
                            <th class="text-end">Pagó</th>
                            <th class="text-end">Profesor</th>
                            <th class="text-end">Escuela (ref.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pago->detalles as $d)
                            @php $refEscuela = ($d->abono_profesor !== null && $d->cuota) ? max(0, (float) $d->cuota->monto - (float) $d->abono_profesor) : null; @endphp
                            <tr>
                                <td><a href="{{ route('alumnos.show', $d->alumno) }}">{{ $d->alumno->nombre_apellido }}</a></td>
                                <td>
                                    {{ $d->cuota->nombre }}
                                    <span class="d-block small text-muted">Cuota {{ $peso($d->cuota->monto) }}</span>
                                </td>
                                <td class="text-end fw-semibold">{{ $peso($d->monto) }}</td>
                                <td class="text-end">
                                    @if($d->abono_profesor !== null)
                                        {{ $peso($d->abono_profesor) }}
                                        @if($d->abono_porcentaje !== null)<span class="d-block small text-muted">{{ number_format((float) $d->abono_porcentaje, 1, ',', '.') }}% de {{ $d->abono_base !== null ? $peso($d->abono_base) : '—' }}</span>@endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-end text-muted">{{ $refEscuela !== null ? $peso($refEscuela) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($primeraNotaAbono)
                <p class="small text-muted m-3 mb-3"><strong>Nota sobre el pago al profesor:</strong> {{ $primeraNotaAbono }}</p>
            @endif
        </x-ito.detail-section>

        <x-ito.detail-section title="Comprobante y notas" icon="bi-paperclip">
            @if($pago->comprobante_path)
                <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalComprobantePago" data-comprobante-src="{{ route('pagos.comprobante', $pago) }}" data-comprobante-label="Comprobante — pago #{{ $pago->id }}"><i class="bi bi-file-earmark" aria-hidden="true"></i> Ver comprobante</button>
            @else
                <p class="text-muted mb-0">Sin comprobante adjunto.</p>
            @endif
            @if($pago->notas)
                <p class="mt-3 mb-0" style="white-space: pre-line">{{ $pago->notas }}</p>
            @endif
        </x-ito.detail-section>
    </div>
</x-ito.shell-page>
@include('pagos._modal_comprobante')
@endsection
