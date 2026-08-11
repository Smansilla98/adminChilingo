@extends('layouts.app')

@section('title', 'Pendientes de hoy')
@section('page-title', 'Pendientes')

@section('content')
<div class="ito-page">
    <div class="ito-page-head mb-3">
        <div>
            <p class="hub-eyebrow">Operativo</p>
            <h1 class="ito-page-title">Qué tenés pendiente</h1>
            <p class="ito-page-sub">Comprobantes, asistencias de hoy{{ $esAdmin ? ' y cuotas del mes' : '' }}.</p>
        </div>
        <div class="ito-page-actions d-flex flex-wrap gap-2">
            @if($esAdmin)
                <a href="{{ route('operativo.cierre-mes') }}" class="btn btn-secondary btn-sm">Cierre de mes</a>
            @endif
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Inicio</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Comprobantes por revisar</strong>
                    <a href="{{ route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']) }}" class="small">Ver todos</a>
                </div>
                <div class="card-body p-0">
                    @forelse($comprobantes as $c)
                        <a href="{{ route('comprobantes-cuota-alumnos.show', $c->id) }}" class="operativo-row">
                            <div>
                                <div class="fw-semibold">{{ $c->alumno?->nombre_apellido ?? 'Alumno' }}</div>
                                <div class="small text-muted">
                                    #{{ $c->id }} · $ {{ number_format($c->monto_total, 2, ',', '.') }}
                                    · {{ $c->items->pluck('bloque.nombre')->filter()->unique()->take(2)->implode(', ') }}
                                </div>
                            </div>
                            <span class="badge bg-warning text-dark">Pendiente</span>
                        </a>
                    @empty
                        <p class="text-muted small p-3 mb-0">No hay comprobantes pendientes. Bien.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Asistencias de hoy</strong>
                    <span class="small text-muted">{{ now()->locale('es')->translatedFormat('l d/m') }}</span>
                </div>
                <div class="card-body p-0">
                    @forelse($asistenciasHoy as $row)
                        @php
                            $b = $row['bloque'];
                            $href = $esAdmin
                                ? route('asistencias.create', ['bloque_id' => $b->id, 'fecha' => now()->toDateString()])
                                : route('profesor.asistencias.create', ['bloque_id' => $b->id, 'fecha' => now()->toDateString()]);
                        @endphp
                        <a href="{{ $href }}" class="operativo-row">
                            <div>
                                <div class="fw-semibold">{{ $b->nombre }}</div>
                                <div class="small text-muted">{{ $b->sede?->nombre }} · {{ $row['marcadas'] }}/{{ $row['alumnos'] }} marcados</div>
                            </div>
                            <span class="badge bg-info text-dark">{{ $row['pendientes'] }} faltan</span>
                        </a>
                    @empty
                        <p class="text-muted small p-3 mb-0">Nada pendiente para hoy (o no hay clase).</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if($esAdmin)
        <div class="col-12">
            <div class="card">
                <div class="card-header"><strong>Cuotas del mes (referencia)</strong></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr><th>Cuota</th><th>Bloque / sede</th><th>Pagos registrados</th></tr>
                            </thead>
                            <tbody>
                                @forelse($cuotasPendientes as $row)
                                    <tr>
                                        <td>{{ $row['cuota']->nombre }}</td>
                                        <td class="small text-muted">
                                            {{ $row['cuota']->bloque?->nombre ?? '—' }}
                                            @if($row['cuota']->bloque?->sede || $row['cuota']->sede)
                                                · {{ $row['cuota']->bloque?->sede?->nombre ?? $row['cuota']->sede?->nombre }}
                                            @endif
                                        </td>
                                        <td>{{ $row['pagados'] }} alumno(s)</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted text-center">Sin cuotas este mes.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
