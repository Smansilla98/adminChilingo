@extends('layouts.app')

@section('title', 'Orden de compra #' . $orden->id)
@section('page-title', 'Orden de compra #' . $orden->id)

@section('content')
<div class="card">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <div>Orden #{{ $orden->id }} — {{ $orden->sede?->nombre }}</div>
        <div class="d-flex gap-2">
            <a href="{{ route('ordenes-compra.edit', $orden) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('ordenes-compra.index') }}" class="btn btn-sm btn-outline-secondary">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="text-muted small">Sede</div>
                <div class="fw-semibold">{{ $orden->sede?->nombre }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Motivo</div>
                <div class="fw-semibold">{{ \App\Models\OrdenCompra::MOTIVOS[$orden->motivo] ?? $orden->motivo }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Estado</div>
                <div class="fw-semibold">{{ \App\Models\OrdenCompra::ESTADOS[$orden->estado] ?? $orden->estado }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Fecha objetivo</div>
                <div class="fw-semibold">{{ $orden->fecha_objetivo?->format('d/m/Y') ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Total estimado</div>
                <div class="fw-semibold">{{ $orden->total_estimado ? '$ ' . number_format($orden->total_estimado, 2, ',', '.') : '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Creada por</div>
                <div class="fw-semibold">{{ $orden->creador?->name ?? '—' }}</div>
            </div>
            <div class="col-md-3">
                <div class="text-muted small">Creada el</div>
                <div class="fw-semibold">{{ $orden->created_at?->format('d/m/Y H:i') }}</div>
            </div>
            @if($orden->justificacion)
            <div class="col-12">
                <div class="text-muted small">Justificación</div>
                <div class="fw-semibold">{{ $orden->justificacion }}</div>
            </div>
            @endif
        </div>

        <h6>Ítems solicitados</h6>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo / Familia</th>
                        <th>Descripción</th>
                        <th>Marca / Modelo</th>
                        <th>Medida</th>
                        <th class="text-end">Cant.</th>
                        <th>Unidad</th>
                        <th class="text-end">Precio u.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($orden->items as $i => $it)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>
                            {{ $it->tipo ?? '—' }}<br>
                            <span class="text-muted small">{{ $it->familia }}</span>
                        </td>
                        <td>{{ $it->descripcion }}</td>
                        <td>{{ $it->marca ?? '—' }} @if($it->modelo) / {{ $it->modelo }} @endif</td>
                        <td>{{ $it->medida ?? '—' }}</td>
                        <td class="text-end">{{ number_format($it->cantidad ?? 0, 2, ',', '.') }}</td>
                        <td>{{ $it->unidad ?? 'u' }}</td>
                        <td class="text-end">
                            @if($it->precio_estimado !== null)
                                {{ '$ ' . number_format($it->precio_estimado, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end">
                            @if($it->subtotal_estimado !== null)
                                {{ '$ ' . number_format($it->subtotal_estimado, 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

