@extends('layouts.app')

@section('title', 'Progreso · '.$alumno->nombre_apellido)
@section('page-title', 'Progreso por toque')

@section('content')
<x-ito.shell-page :title="$alumno->nombre_apellido" eyebrow="Progreso por toque" :subtitle="'Cursa '.$datos['anio_actual'].'° año'">
    <x-slot:actions>
        <a href="{{ url()->previous() }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </x-slot:actions>

    @if($datos['propuesta'])
        <div class="ito-card p-3 mb-3 d-flex flex-wrap gap-2 align-items-center">
            <span class="flex-grow-1">Propuesta: pasar de {{ $datos['propuesta']['anio_desde'] }}° a {{ $datos['propuesta']['anio_hasta'] }}° año.</span>
            @if($puedeResolver)
                <form method="POST" action="{{ route('progreso.promociones.confirmar', $datos['propuesta']['id']) }}">@csrf<button class="btn btn-sm btn-success">Confirmar</button></form>
                <form method="POST" action="{{ route('progreso.promociones.descartar', $datos['propuesta']['id']) }}">@csrf<button class="btn btn-sm btn-outline-secondary">Todavía no</button></form>
            @endif
        </div>
    @endif

    @foreach($datos['anios'] as $bloqueAnio)
        <section class="ito-card p-3 mb-3" aria-label="{{ $bloqueAnio['anio'] }}° año">
            <h2 class="h6">{{ $bloqueAnio['anio'] }}° año · toca con el bloque {{ $bloqueAnio['tocados'] }} de {{ $bloqueAnio['total'] }}</h2>
            <table class="table table-sm mb-0">
                <thead><tr><th>Toque</th><th>En qué anda</th><th>Desde</th><th>Registró</th></tr></thead>
                <tbody>
                    @foreach($bloqueAnio['toques'] as $t)
                        <tr>
                            <td>{{ $t['nombre'] }}{{ $t['opcional'] ? ' (opcional)' : '' }}</td>
                            <td>{{ $t['estado_nombre'] }}</td>
                            <td>{{ $t['fecha'] ? \Carbon\Carbon::parse($t['fecha'])->format('d/m/Y') : '—' }}</td>
                            <td>{{ $t['docente'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    <section class="ito-card p-3 mb-3" aria-labelledby="pa-rep">
        <h2 class="h6" id="pa-rep">Repasos y devoluciones</h2>
        @forelse($datos['evaluaciones'] as $e)
            <div class="border-top py-2">
                <strong>{{ \Carbon\Carbon::parse($e['fecha'])->format('d/m/Y') }} · {{ $e['resultado_nombre'] }}</strong>
                <span class="text-muted small">{{ $e['toques']->join(', ') }}{{ $e['docente'] ? ' · '.$e['docente'] : '' }}{{ $e['visible_alumno'] ? '' : ' · solo equipo docente' }}</span>
                @if($e['devolucion'])<p class="mb-0">{{ $e['devolucion'] }}</p>@endif
            </div>
        @empty
            <p class="text-muted small mb-0">Todavía no hay repasos registrados.</p>
        @endforelse
    </section>

    <section class="ito-card p-3" aria-labelledby="pa-hist">
        <h2 class="h6" id="pa-hist">Años cursados</h2>
        @forelse($datos['historial'] as $h)
            <div class="border-top py-1">{{ $h['anio'] }}° año · desde {{ \Carbon\Carbon::parse($h['desde'])->format('d/m/Y') }}{{ $h['hasta'] ? ' hasta '.\Carbon\Carbon::parse($h['hasta'])->format('d/m/Y') : ' (lo cursa ahora)' }}{{ $h['bloque'] ? ' · '.$h['bloque'] : '' }}{{ $h['confirmado_por'] ? ' · confirmó '.$h['confirmado_por'] : '' }}</div>
        @empty
            <p class="text-muted small mb-0">Sin pasos de año confirmados todavía: cursa el año de su bloque.</p>
        @endforelse
    </section>
</x-ito.shell-page>
@endsection
