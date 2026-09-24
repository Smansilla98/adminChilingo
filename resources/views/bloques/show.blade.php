@extends('layouts.app')

@section('title', $bloque->nombre)
@section('page-title', $bloque->nombre)

@section('content')
@php
    $activos = $bloque->alumnos->where('activo', true);
    $cupo = max(1, (int) $bloque->cantidad_max_alumnos);
    $horarios = $bloque->horarios()->orderBy('dia_semana')->get();
    $pestanas = [
        'alumnos' => 'Alumnos <span class="badge">'.$activos->count().'</span>',
        'equipo' => 'Equipo docente',
        'eventos' => 'Eventos',
    ];
@endphp
<x-ito.shell-page :title="$bloque->nombre" :subtitle="collect([$bloque->sede?->nombre, $bloque->año ? $bloque->año.'° año' : null, $bloque->corresponde_a])->filter()->join(' · ')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$bloque->activo ? 'success' : 'neutral'" :label="$bloque->activo ? 'Activo' : 'Inactivo'" />
        <a href="{{ route('bloques.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('verAsistencias', $bloque)
            <a href="{{ route('asistencias.index', ['bloque_id' => $bloque->id]) }}" class="btn btn-outline-secondary"><i class="bi bi-grid-3x3" aria-hidden="true"></i> Planilla</a>
        @endcan
        @can('update', $bloque)
            <a href="{{ route('bloques.edit', $bloque) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Alumnos activos">{{ $activos->count() }} <span class="text-muted fw-normal">/ {{ $cupo }}</span></x-ito.fact>
        <x-ito.fact label="Profesor titular" :value="$bloque->profesor?->nombre" />
        <x-ito.fact label="Horarios" :value="$horarios->map(fn ($h) => mb_substr(\App\Models\BloqueHorario::DIAS_SEMANA[$h->dia_semana] ?? '', 0, 3).' '.\Carbon\Carbon::parse($h->hora_inicio)->format('H:i'))->join(', ') ?: null" />
        <x-ito.fact label="Tambores" :value="$bloque->tambores ? implode(', ', $bloque->tambores) : null" />
    </x-ito.facts>

    <x-ito.tabs id="bloqueTabs" :tabs="$pestanas">
        <x-ito.tab tabs="bloqueTabs" name="alumnos" :active="true">
            <x-ito.detail-section :flush="true">
                @if($bloque->alumnos->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Alumno</th><th>Instrumento</th><th class="text-end">Estado</th></tr></thead>
                            <tbody>
                                @foreach($bloque->alumnos->sortBy('nombre_apellido') as $alumno)
                                    <tr>
                                        <td><a href="{{ route('alumnos.show', $alumno) }}">{{ $alumno->nombre_apellido }}</a></td>
                                        <td class="text-muted">{{ $alumno->instrumento_principal ?: '—' }}</td>
                                        <td class="text-end"><x-ito.status :tone="$alumno->activo ? 'success' : 'neutral'" :label="$alumno->activo ? 'Activo' : 'Inactivo'" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-ito.empty icon="bi-people" title="Sin alumnos" description="Todavía no hay alumnos inscriptos en este bloque." />
                @endif
            </x-ito.detail-section>
        </x-ito.tab>

        <x-ito.tab tabs="bloqueTabs" name="equipo">
            <x-ito.detail-section>
                @forelse($bloque->profesores as $p)
                    <div class="hub-list-item">
                        <x-ito.person :name="$p->nombre" :sub="ucfirst($p->pivot->rol ?? 'docente')" />
                    </div>
                @empty
                    <x-ito.empty icon="bi-person-badge" title="Sin equipo docente" description="Asigná un profesor titular desde Editar." />
                @endforelse
            </x-ito.detail-section>
        </x-ito.tab>

        <x-ito.tab tabs="bloqueTabs" name="eventos">
            <x-ito.detail-section>
                @forelse($bloque->eventos->sortByDesc('fecha') as $evento)
                    <a class="hub-list-item hub-list-item--link" href="{{ route('eventos.show', $evento) }}">
                        <span class="agenda-date" aria-hidden="true">
                            <span class="agenda-date-d">{{ $evento->fecha?->format('d') }}</span>
                            <span class="agenda-date-m">{{ $evento->fecha?->locale('es')->translatedFormat('M') }}</span>
                        </span>
                        <span class="fw-semibold">{{ $evento->titulo }}</span>
                    </a>
                @empty
                    <x-ito.empty icon="bi-calendar-event" title="Sin eventos" description="Este bloque no tiene eventos cargados." />
                @endforelse
            </x-ito.detail-section>
        </x-ito.tab>
    </x-ito.tabs>
</x-ito.shell-page>
@endsection
