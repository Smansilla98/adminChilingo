@extends('layouts.app')

@section('title', 'Liquidación docente')
@section('page-title', 'Liquidación docente')

@php
    $plata = fn ($n) => '$ '.number_format((float) $n, 0, ',', '.');
    $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
@endphp

@section('content')
<x-ito.shell-page title="Liquidación docente" eyebrow="Administración" subtitle="Horas dictadas (según la asistencia tomada) o porcentaje de cuotas cobradas: se muestran las dos y cada docente cobra por la modalidad elegida.">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-end mb-3" aria-label="Período">
        <div>
            <label class="form-label small mb-1" for="lq-mes">Mes</label>
            <select id="lq-mes" name="mes" class="form-select form-select-sm">@foreach($meses as $n => $t)<option value="{{ $n }}" @selected($n === $mes)>{{ $t }}</option>@endforeach</select>
        </div>
        <div>
            <label class="form-label small mb-1" for="lq-anio">Año</label>
            <select id="lq-anio" name="anio" class="form-select form-select-sm">@for($y = now()->year; $y >= now()->year - 3; $y--)<option value="{{ $y }}" @selected($y === $anio)>{{ $y }}</option>@endfor</select>
        </div>
        <button class="btn btn-sm btn-primary">Ver</button>
    </form>

    @if($puedeGestionar)
        <form method="POST" action="{{ route('liquidaciones.generar') }}" class="mb-3">
            @csrf
            <input type="hidden" name="anio" value="{{ $anio }}"><input type="hidden" name="mes" value="{{ $mes }}">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-calculator"></i> Calcular todas las de {{ $meses[$mes] }} (las aprobadas no se tocan)</button>
        </form>
    @endif

    @if($profesores->isEmpty())
        <x-ito.empty title="No hay docentes en tu alcance" />
    @else
        <div class="ito-card p-2 table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Docente</th><th>Modalidad</th><th class="text-end">Horas</th><th class="text-end">Por horas</th><th class="text-end">Por porcentaje</th><th class="text-end">A cobrar</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                    @foreach($profesores as $p)
                        @php $l = $liqs->get($p->id); @endphp
                        <tr>
                            <td>{{ $p->nombre }}</td>
                            <td>{{ ($modalidades[$p->id] ?? 'porcentaje') === 'hora' ? 'Por horas' : 'Porcentaje' }}</td>
                            <td class="text-end">{{ $l ? number_format($l->horasTotales(), 1, ',', '.') : '—' }}</td>
                            <td class="text-end">{{ $l ? $plata($l->monto_horas) : '—' }}</td>
                            <td class="text-end">{{ $l ? $plata($l->monto_porcentaje) : '—' }}</td>
                            <td class="text-end fw-bold">{{ $l ? $plata($l->monto_total) : '—' }}</td>
                            <td>{{ $l ? $l->etiquetaEstado() : 'Sin calcular' }}</td>
                            <td class="text-nowrap">
                                @if($l)<a class="btn btn-sm btn-outline-secondary" href="{{ route('liquidaciones.show', $l) }}">Ver</a>@endif
                                @if($puedeGestionar)
                                    @if(! $l || $l->estado === 'borrador')
                                        <form method="POST" action="{{ route('liquidaciones.generar') }}" class="d-inline">@csrf
                                            <input type="hidden" name="anio" value="{{ $anio }}"><input type="hidden" name="mes" value="{{ $mes }}"><input type="hidden" name="profesor_id" value="{{ $p->id }}">
                                            <button class="btn btn-sm btn-outline-primary">{{ $l ? 'Recalcular' : 'Calcular' }}</button>
                                        </form>
                                    @endif
                                    <a class="btn btn-sm btn-link" href="{{ route('liquidaciones.tarifa', $p) }}">Valor hora</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ito.shell-page>
@endsection
