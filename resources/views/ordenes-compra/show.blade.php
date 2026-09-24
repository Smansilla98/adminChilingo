@extends('layouts.app')

@section('title', 'Orden de compra #'.$orden->id)
@section('page-title', 'Orden de compra #'.$orden->id)

@section('content')
@php
    $tonoEstado = ['borrador' => 'neutral', 'enviada' => 'info', 'aprobada' => 'warning', 'recibida' => 'success', 'cancelada' => 'danger'];
    $peso = fn ($n) => $n !== null ? '$ '.number_format($n, 2, ',', '.') : '—';
@endphp
<x-ito.shell-page :title="'Orden #'.$orden->id" :subtitle="collect([$orden->sede?->nombre, \App\Models\OrdenCompra::MOTIVOS[$orden->motivo] ?? $orden->motivo])->filter()->join(' · ')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$tonoEstado[$orden->estado] ?? 'neutral'" :label="\App\Models\OrdenCompra::ESTADOS[$orden->estado] ?? $orden->estado" />
        <a href="{{ route('ordenes-compra.index') }}" class="btn btn-outline-secondary">Volver</a>
        <a href="{{ route('ordenes-compra.edit', $orden) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Total estimado" :value="$orden->total_estimado ? $peso($orden->total_estimado) : null" />
        <x-ito.fact label="Ítems" :value="$orden->items->count()" />
        <x-ito.fact label="Para cuándo" :value="$orden->fecha_objetivo?->format('d/m/Y')" />
        <x-ito.fact label="Pedida por" :value="$orden->creador?->name" />
        <x-ito.fact label="Creada" :value="$orden->created_at?->format('d/m/Y')" />
    </x-ito.facts>

    @if($orden->justificacion)
        <x-ito.detail-section title="Por qué hace falta" icon="bi-chat-left-text">
            <p class="mb-0" style="white-space: pre-line">{{ $orden->justificacion }}</p>
        </x-ito.detail-section>
    @endif

    <x-ito.detail-section title="Ítems" icon="bi-list-check" :flush="true">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Marca / modelo</th>
                        <th>Medida</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Precio u.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orden->items as $it)
                        <tr>
                            <td class="fw-semibold">{{ $it->descripcion }}</td>
                            <td class="text-muted">{{ collect([$it->tipo, $it->familia])->filter()->join(' · ') ?: '—' }}</td>
                            <td>{{ collect([$it->marca, $it->modelo])->filter()->join(' / ') ?: '—' }}</td>
                            <td>{{ $it->medida ?? '—' }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($it->cantidad ?? 0, 2, ',', '.'), '0'), ',') }} {{ $it->unidad ?? 'u' }}</td>
                            <td class="text-end">{{ $peso($it->precio_estimado) }}</td>
                            <td class="text-end fw-semibold">{{ $peso($it->subtotal_estimado) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="ito-empty">La orden no tiene ítems.</td></tr>
                    @endforelse
                </tbody>
                @if($orden->total_estimado)
                    <tfoot>
                        <tr><td colspan="6" class="text-end fw-semibold">Total estimado</td><td class="text-end fw-bold">{{ $peso($orden->total_estimado) }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-ito.detail-section>
</x-ito.shell-page>
@endsection
