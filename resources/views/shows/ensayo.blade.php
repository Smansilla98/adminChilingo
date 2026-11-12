@extends('layouts.app')

@section('title', 'Ensayo · '.$show->titulo)
@section('page-title', 'Ensayo')

@section('content')
@php
    $puedeAsistencia = auth()->user()->can('asistencia', $show);
    $horario = collect([$ensayo->hora_inicio?->format('H:i'), $ensayo->hora_fin?->format('H:i')])->filter()->join(' a ');
@endphp
<x-ito.shell-page :title="'Ensayo para '.$show->titulo" :subtitle="$ensayo->fecha->format('d/m/Y').($horario ? ' · '.$horario.' hs' : '').($ensayo->lugar ? ' · '.$ensayo->lugar : '')" :plain="true">
    <x-slot:actions>
        <a href="{{ route('shows.show', $show) }}" class="btn btn-outline-secondary">Volver al show</a>
        @can('update', $show)
            <form method="POST" action="{{ route('shows.ensayos.destroy', $ensayo) }}" data-confirm="¿Eliminar este ensayo?">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
            </form>
        @endcan
    </x-slot:actions>

    @if($ensayo->notas)<p class="text-muted">{{ $ensayo->notas }}</p>@endif

    <x-ito.detail-section title="Asistencia al ensayo" icon="bi-check2-square">
        @if($planilla === [])
            <p class="text-muted mb-0">No hay alumnos convocados.</p>
        @else
            <form method="POST" action="{{ route('shows.ensayos.asistencia', $ensayo) }}">
                @csrf
                <div class="ito-check-grid">
                    @foreach($planilla as $f)
                        <label class="ito-check-chip">
                            <input class="form-check-input" type="checkbox" name="marcas[{{ $f['alumno_id'] }}]" value="1" @checked($f['presente']) @disabled(! $puedeAsistencia)>
                            <span>{{ $f['nombre'] }}</span>
                        </label>
                    @endforeach
                </div>
                @if($puedeAsistencia)
                    <button class="btn btn-primary btn-sm mt-3">Guardar asistencia</button>
                @endif
            </form>
        @endif
    </x-ito.detail-section>
</x-ito.shell-page>
@endsection
