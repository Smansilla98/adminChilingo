@extends('layouts.app')

@section('title', 'Comprobante #'.$comprobanteCuotaAlumno->id)
@section('page-title', 'Comprobante #'.$comprobanteCuotaAlumno->id)

@section('content')
@php
    $c = $comprobanteCuotaAlumno;
    $tono = match ($c->estado) { 'pendiente' => 'warning', 'pagado' => 'success', default => 'neutral' };
    $puedeAprobar = auth()->user()->isAdmin() && ! $c->estaPagado();
@endphp
<x-ito.shell-page :title="$c->alumno?->nombre_apellido ?? 'Comprobante #'.$c->id" :subtitle="'Envío #'.$c->id.($c->alumno?->dni ? ' · DNI '.$c->alumno->dni : '').($c->created_at ? ' · recibido '.$c->created_at->locale('es')->diffForHumans() : '')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$tono" :label="$c->etiquetaEstado()" />
        <a href="{{ route('comprobantes-cuota-alumnos.index') }}" class="btn btn-outline-secondary">Volver</a>
        @if($c->pago_id)
            <a href="{{ route('pagos.show', $c->pago_id) }}" class="btn btn-outline-secondary"><i class="bi bi-receipt" aria-hidden="true"></i> Ver pago #{{ $c->pago_id }}</a>
        @endif
        @if($c->estaPendiente())
            <form action="{{ route('comprobantes-cuota-alumnos.visto', $c->id) }}" method="post" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-secondary">Marcar como visto</button>
            </form>
        @endif
        @if($puedeAprobar)
            <form action="{{ route('comprobantes-cuota-alumnos.aprobar-pago', $c->id) }}" method="post" class="d-inline"
                  data-confirm="¿Registrar el pago? Se crea el cobro con estas cuotas, se copia el archivo y el comprobante queda como pagado." data-confirm-ok="Registrar pago">
                @csrf
                <button type="submit" class="btn btn-primary"><i class="bi bi-cash-coin" aria-hidden="true"></i> Aprobar y registrar pago</button>
            </form>
        @endif
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Monto declarado">$ {{ number_format($c->monto_total, 2, ',', '.') }}</x-ito.fact>
        <x-ito.fact label="Fecha de pago" :value="$c->fecha_pago?->format('d/m/Y')" />
        <x-ito.fact label="Sede" :value="$c->sede?->nombre" />
        <x-ito.fact label="Cuotas" :value="$c->items->count()" />
    </x-ito.facts>

    <div class="ito-detail-grid">
        <div class="ito-detail-col">
            <x-ito.detail-section title="Cuotas incluidas" icon="bi-cash-stack" :flush="true">
                <div class="table-responsive">
                    <table class="table align-middle mb-0" data-ito-no-cards>
                        <thead><tr><th>Cuota</th><th>Bloque</th><th class="text-end">Monto</th></tr></thead>
                        <tbody>
                            @forelse($c->items as $it)
                                <tr>
                                    <td class="fw-semibold">{{ $it->cuota?->nombre ?? '—' }}</td>
                                    <td>{{ $it->bloque?->nombre ?? '—' }} <span class="d-block small text-muted">{{ $it->bloque?->sede?->nombre }}</span></td>
                                    <td class="text-end">$ {{ number_format($it->monto, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="ito-empty">Sin cuotas asociadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ito.detail-section>

            @if($c->notas)
                <x-ito.detail-section title="Nota de la familia" icon="bi-chat-left-text">
                    <p class="mb-0" style="white-space: pre-line">{{ $c->notas }}</p>
                </x-ito.detail-section>
            @endif

            <p class="small text-muted mb-0">
                @if($puedeAprobar)
                    <strong>Aprobar y registrar pago</strong> crea el cobro con estas cuotas y cierra el comprobante. <strong>Marcar como visto</strong> no registra dinero.
                @elseif(! auth()->user()->isAdmin())
                    Podés marcarlo como visto. El pago lo registra administración.
                @endif
            </p>
        </div>

        <x-ito.detail-section title="Archivo" icon="bi-file-earmark">
            @if($c->comprobante_path)
                <x-slot:actions>
                    <a href="{{ route('comprobantes-cuota-alumnos.comprobante', $c->id) }}" class="btn btn-sm btn-ghost" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Abrir</a>
                </x-slot:actions>
                <iframe src="{{ route('comprobantes-cuota-alumnos.comprobante', $c->id) }}" title="Comprobante enviado" class="ito-file-preview" loading="lazy"></iframe>
            @else
                <p class="text-muted mb-0">No se adjuntó archivo.</p>
            @endif
        </x-ito.detail-section>
    </div>
</x-ito.shell-page>
@endsection
