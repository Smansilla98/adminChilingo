@extends('layouts.app')

@section('title', 'Pendientes de hoy')
@section('page-title', 'Pendientes')

@section('content')
<div class="hub hub--operativo">
    <div class="hub-hero">
        <div class="hub-hero-main">
            <p class="hub-eyebrow">Operativo · La Chilinga</p>
            <h1 class="hub-greeting">Qué <em>necesita</em> tu atención</h1>
            <p class="hub-lead">Comprobantes, asistencias de hoy{{ $esAdmin ? ' y cuotas del mes' : '' }}.</p>
        </div>
        <div class="ito-page-actions d-flex flex-wrap gap-2 align-self-start">
            @if($esAdmin && auth()->user()->isAdmin())
                <a href="{{ route('operativo.cierre-mes') }}" class="btn btn-secondary btn-sm">Cierre de mes</a>
            @endif
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Inicio</a>
        </div>
    </div>

    <div class="hub-panels">
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div>
                    <div class="hub-panel-title">Comprobantes por revisar</div>
                    <div class="hub-panel-sub">Cobranza pendiente de aprobación</div>
                </div>
                <a href="{{ route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']) }}" class="hub-panel-link">Ver todos →</a>
            </div>
            @forelse($comprobantes as $c)
                <a href="{{ route('comprobantes-cuota-alumnos.show', $c->id) }}" class="hub-list-item hub-list-item--link">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $c->alumno?->nombre_apellido ?? 'Alumno' }}</div>
                        <div class="small text-muted">
                            #{{ $c->id }} · $ {{ number_format($c->monto_total, 2, ',', '.') }}
                            · {{ $c->items->pluck('bloque.nombre')->filter()->unique()->take(2)->implode(', ') }}
                        </div>
                    </div>
                    <span class="hub-kpi-badge is-alert">Pendiente</span>
                </a>
            @empty
                <x-ito.empty
                    title="Nada por revisar"
                    description="No hay comprobantes pendientes. Cuando lleguen, aparecen acá."
                    icon="bi-check2-circle"
                    :action-href="route('comprobantes-cuota-alumnos.index')"
                    action-label="Ir a comprobantes"
                />
            @endforelse
        </div>

        <div class="hub-panel">
            <div class="hub-panel-head">
                <div>
                    <div class="hub-panel-title">Asistencias de hoy</div>
                    <div class="hub-panel-sub">{{ now()->locale('es')->translatedFormat('l d/m') }}</div>
                </div>
            </div>
            @forelse($asistenciasHoy as $row)
                @php
                    $b = $row['bloque'];
                    $href = auth()->user()->puedeGestionarOperativo()
                        ? route('asistencias.create', ['bloque_id' => $b->id, 'fecha' => now()->toDateString()])
                        : route('profesor.asistencias.create', ['bloque_id' => $b->id, 'fecha' => now()->toDateString()]);
                @endphp
                <a href="{{ $href }}" class="hub-list-item hub-list-item--link">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">{{ $b->nombre }}</div>
                        <div class="small text-muted">{{ $b->sede?->nombre }} · {{ $row['marcadas'] }}/{{ $row['alumnos'] }} marcados</div>
                    </div>
                    <span class="hub-kpi-badge is-alert">{{ $row['pendientes'] }} faltan</span>
                </a>
            @empty
                <x-ito.empty
                    title="Lista al día"
                    description="No hay clases pendientes de asistencia para hoy."
                    icon="bi-calendar-check"
                    :action-href="auth()->user()->puedeGestionarOperativo() ? route('asistencias.index') : route('profesor.asistencias.create')"
                    action-label="Abrir asistencias"
                />
            @endforelse
        </div>
    </div>

    @if($esAdmin)
    <section class="hub-section" aria-labelledby="pend-cuotas">
        <header class="hub-section-head">
            <h2 id="pend-cuotas" class="hub-section-title"><span class="hub-section-code">Mes</span> Cuotas (referencia)</h2>
            <a href="{{ route('cuotas.index') }}" class="hub-panel-link">Ver cuotas →</a>
        </header>
        <div class="hub-panel mb-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Cuota</th><th>Bloque / sede</th><th>Pagos registrados</th></tr>
                    </thead>
                    <tbody>
                        @forelse($cuotasPendientes as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['cuota']->nombre }}</td>
                                <td class="small text-muted">
                                    {{ $row['cuota']->bloque?->nombre ?? '—' }}
                                    @if($row['cuota']->bloque?->sede || $row['cuota']->sede)
                                        · {{ $row['cuota']->bloque?->sede?->nombre ?? $row['cuota']->sede?->nombre }}
                                    @endif
                                </td>
                                <td>{{ $row['pagados'] }} alumno(s)</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <x-ito.empty
                                        class="py-3"
                                        title="Sin cuotas este mes"
                                        description="Cuando emitas cuotas, vas a ver el avance de cobro acá."
                                        icon="bi-cash-stack"
                                        :action-href="route('cuotas.create')"
                                        action-label="Nueva cuota"
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif
</div>
@endsection
