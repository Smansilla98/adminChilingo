@extends('layouts.app')

@section('title', 'Liquidación · '.$profesor->nombre)
@section('page-title', 'Liquidación docente')

@php
    $plata = fn ($n) => '$ '.number_format((float) $n, 2, ',', '.');
    $d = $l->detalle ?? [];
    $fecha = fn ($f) => \Carbon\Carbon::parse($f)->format('d/m/Y');
@endphp

@section('content')
<x-ito.shell-page :title="$profesor->nombre" eyebrow="Liquidación docente" :subtitle="$l->periodo().' · '.$l->etiquetaEstado().' · '.$l->numeroRecibo()">
    <x-slot:actions>
        <a href="{{ route('liquidaciones.index', ['anio' => $l->anio, 'mes' => $l->mes]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Período</a>
        <a href="{{ route('liquidaciones.recibo', $l) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener"><i class="bi bi-printer"></i> Recibo</a>
    </x-slot:actions>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="ito-card p-3 h-100 {{ $l->modalidad === 'hora' ? 'border border-2 border-primary' : '' }}">
            <div class="small text-muted">Por horas{{ $l->modalidad === 'hora' ? ' · elegida' : '' }}</div>
            <div class="fs-4 fw-bold">{{ $plata($l->monto_horas) }}</div>
            <div class="small">{{ number_format($l->horas_dictadas, 2, ',', '.') }} h dictadas {{ $l->horas_ajuste >= 0 ? '+' : '−' }} {{ number_format(abs($l->horas_ajuste), 2, ',', '.') }} h de ajustes</div>
        </div></div>
        <div class="col-md-4"><div class="ito-card p-3 h-100 {{ $l->modalidad === 'porcentaje' ? 'border border-2 border-primary' : '' }}">
            <div class="small text-muted">Porcentaje de cuotas cobradas{{ $l->modalidad === 'porcentaje' ? ' · elegida' : '' }}</div>
            <div class="fs-4 fw-bold">{{ $plata($l->monto_porcentaje) }}</div>
            <div class="small">Abono por sede de los pagos del mes (como hasta ahora).</div>
        </div></div>
        <div class="col-md-4"><div class="ito-card p-3 h-100">
            <div class="small text-muted">A cobrar</div>
            <div class="fs-3 fw-bold">{{ $plata($l->monto_total) }}</div>
            <div class="small">{{ $l->etiquetaEstado() }}</div>
        </div></div>
    </div>

    @if(($d['sin_horario'] ?? 0) > 0)
        <div class="alert alert-warning">{{ $d['sin_horario'] }} clase(s) se contaron con {{ \App\Domain\Docentes\HorasDictadasService::HORAS_SIN_HORARIO }} h porque el bloque no tiene horario cargado. Revisalo o cargá un ajuste.</div>
    @endif

    @if($puedeGestionar)
        <div class="d-flex flex-wrap gap-2 mb-3">
            @if($l->estado === 'borrador')
                <form method="POST" action="{{ route('liquidaciones.generar') }}">@csrf<input type="hidden" name="anio" value="{{ $l->anio }}"><input type="hidden" name="mes" value="{{ $l->mes }}"><input type="hidden" name="profesor_id" value="{{ $profesor->id }}"><button class="btn btn-sm btn-outline-primary">Recalcular</button></form>
                <form method="POST" action="{{ route('liquidaciones.aprobar', $l) }}">@csrf<button class="btn btn-sm btn-success">Aprobar</button></form>
            @elseif($l->estado === 'aprobada')
                <form method="POST" action="{{ route('liquidaciones.pagar', $l) }}" class="d-flex gap-2 align-items-center">@csrf
                    <label class="form-check-label small"><input type="checkbox" class="form-check-input me-1" name="registrar_gasto" value="1" checked>Registrar el gasto de sueldo</label>
                    <button class="btn btn-sm btn-success">Marcar pagada</button>
                </form>
                <form method="POST" action="{{ route('liquidaciones.reabrir', $l) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Volver a preparación</button></form>
            @endif
        </div>
    @endif

    <section class="ito-card p-3 mb-3">
        <h2 class="h6">Por sede</h2>
        <table class="table table-sm mb-0">
            <thead><tr><th>Sede</th><th class="text-end">Clases</th><th class="text-end">Horas dictadas</th><th class="text-end">Ajustes</th><th class="text-end">Valor hora</th><th class="text-end">Monto</th></tr></thead>
            <tbody>
                @forelse($d['por_sede'] ?? [] as $s)
                    <tr><td>{{ $s['sede'] }}</td><td class="text-end">{{ $s['clases'] }}</td><td class="text-end">{{ number_format($s['horas_dictadas'], 2, ',', '.') }}</td><td class="text-end">{{ number_format($s['horas_ajuste'], 2, ',', '.') }}</td><td class="text-end">{{ $plata($s['valor_hora']) }}</td><td class="text-end">{{ $plata($s['monto']) }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No hay clases con asistencia tomada en el mes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="ito-card p-3 mb-3">
        <h2 class="h6">Clases dictadas (hubo asistencia ese día)</h2>
        <ul class="small mb-0">
            @forelse($d['clases'] ?? [] as $c)
                <li>{{ $fecha($c['fecha']) }} · {{ $c['bloque'] }} · {{ number_format($c['horas'], 2, ',', '.') }} h{{ $c['sin_horario'] ? ' (sin horario cargado)' : '' }}</li>
            @empty
                <li class="text-muted">Ninguna.</li>
            @endforelse
        </ul>
    </section>

    <section class="ito-card p-3">
        <h2 class="h6">Ajustes manuales</h2>
        <ul class="small">
            @forelse($d['ajustes'] ?? [] as $a)
                <li class="d-flex gap-2 align-items-center">
                    <span>{{ $fecha($a['fecha']) }} · {{ $a['tipo_nombre'] }} · {{ $a['horas'] > 0 ? '+' : '' }}{{ number_format($a['horas'], 2, ',', '.') }} h{{ $a['bloque'] ? ' · '.$a['bloque'] : '' }}{{ $a['motivo'] ? ' · '.$a['motivo'] : '' }}</span>
                    @if($puedeGestionar && $l->estado === 'borrador')
                        <form method="POST" action="{{ route('liquidaciones.ajustes.destroy', $a['id']) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0">Quitar</button></form>
                    @endif
                </li>
            @empty
                <li class="text-muted">Sin ajustes.</li>
            @endforelse
        </ul>
        @if($puedeGestionar && $l->estado === 'borrador')
            <form method="POST" action="{{ route('liquidaciones.ajustes.store') }}" class="row g-2">
                @csrf
                <input type="hidden" name="profesor_id" value="{{ $profesor->id }}">
                <div class="col-md-2"><label class="form-label small" for="aj-fe">Fecha</label><input id="aj-fe" type="date" name="fecha" class="form-control form-control-sm" value="{{ \Carbon\Carbon::create($l->anio, $l->mes, 1)->toDateString() }}" required></div>
                <div class="col-md-2"><label class="form-label small" for="aj-ti">Tipo</label><select id="aj-ti" name="tipo" class="form-select form-select-sm">@foreach($tiposAjuste as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                <div class="col-md-2"><label class="form-label small" for="aj-ho">Horas</label><input id="aj-ho" type="number" step="0.25" min="0.25" max="24" name="horas" class="form-control form-control-sm" required></div>
                <div class="col-md-3"><label class="form-label small" for="aj-bl">Bloque</label><select id="aj-bl" name="bloque_id" class="form-select form-select-sm"><option value="">—</option>@foreach($bloques as $b)<option value="{{ $b->id }}">{{ $b->nombre }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label small" for="aj-mo">Motivo</label><input id="aj-mo" name="motivo" maxlength="255" class="form-control form-control-sm"></div>
                <div class="col-12"><button class="btn btn-sm btn-primary">Agregar ajuste</button></div>
            </form>
        @endif
    </section>
</x-ito.shell-page>
@endsection
