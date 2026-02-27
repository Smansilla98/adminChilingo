@extends('layouts.app')

@section('title', 'Detalle inventario')
@section('page-title', 'Inventario — Detalle')

@section('content')
<div class="card">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <div>{{ $item->nombre }}</div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventarios.edit', $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('inventarios.index') }}" class="btn btn-sm btn-outline-secondary">Volver</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">Sede</div>
                <div class="fw-semibold">{{ $item->sede?->nombre }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Tipo</div>
                <div class="fw-semibold">{{ $item->tipo_label }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Propiedad</div>
                <div class="fw-semibold">
                    {{ $item->propietario_label }}
                    @if($item->propietario_tipo === 'alumno' && $item->alumno)
                        <span class="text-muted">— {{ $item->alumno->nombre_apellido }}</span>
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Código</div>
                <div class="fw-semibold">{{ $item->codigo ?? '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Cantidad</div>
                <div class="fw-semibold">
                    @if($item->es_consumible)
                        {{ number_format((float)$item->cantidad, 2, ',', '.') }} {{ $item->unidad ?? '' }}
                    @else
                        1 u
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Estado</div>
                <div class="fw-semibold">{{ \\App\\Models\\InventarioItem::ESTADOS[$item->estado] ?? $item->estado }}</div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-3"><div class="text-muted small">Marca</div><div class="fw-semibold">{{ $item->marca ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Modelo</div><div class="fw-semibold">{{ $item->modelo ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Línea</div><div class="fw-semibold">{{ $item->linea ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Material</div><div class="fw-semibold">{{ $item->material ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Color</div><div class="fw-semibold">{{ $item->color ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Medida</div><div class="fw-semibold">{{ $item->medida ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Diámetro (pulg.)</div><div class="fw-semibold">{{ $item->diametro_pulgadas ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Torres</div><div class="fw-semibold">{{ $item->torres ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Año fabricación</div><div class="fw-semibold">{{ $item->anio_fabricacion ?? '—' }}</div></div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-3"><div class="text-muted small">Origen</div><div class="fw-semibold">{{ $item->origen_adquisicion ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Fecha adquisición</div><div class="fw-semibold">{{ $item->fecha_adquisicion?->format('d/m/Y') ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Precio</div><div class="fw-semibold">{{ $item->precio !== null ? '$ ' . number_format($item->precio, 2, ',', '.') : '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Utilitario</div><div class="fw-semibold">{{ $item->utilitario ? 'Sí' : 'No' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Reparado en</div><div class="fw-semibold">{{ $item->reparado_en?->format('d/m/Y') ?? '—' }}</div></div>
            <div class="col-md-9"><div class="text-muted small">Detalle reparación</div><div class="fw-semibold">{{ $item->detalle_reparacion ?? '—' }}</div></div>

            @if($item->notas)
            <div class="col-12"><div class="text-muted small">Notas</div><div class="fw-semibold">{{ $item->notas }}</div></div>
            @endif
        </div>
    </div>
</div>
@endsection

