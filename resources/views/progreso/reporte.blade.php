@extends('layouts.app')

@section('title', 'Progreso por sede')
@section('page-title', 'Progreso por toque — Reporte')

@section('content')
<x-ito.shell-page title="Progreso por bloque" eyebrow="Coordinación" subtitle="Cuánto de los toques obligatorios de su año ya toca cada bloque y qué pasos de año esperan confirmación.">
    <x-slot:actions>
        <a href="{{ route('progreso.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Bloques</a>
    </x-slot:actions>

    @if($sedes->count() > 1)
        <form method="GET" class="d-flex gap-2 align-items-end mb-3">
            <div>
                <label class="form-label small mb-1" for="pr-sede">Sede</label>
                <select id="pr-sede" name="sede_id" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach($sedes as $s)<option value="{{ $s->id }}" @selected($sedeId === $s->id)>{{ $s->nombre }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-sm btn-primary">Ver</button>
        </form>
    @endif

    @if($filas->isEmpty())
        <x-ito.empty title="Sin bloques para mostrar" />
    @else
        <div class="ito-card p-2 table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Sede</th><th>Bloque</th><th>Año</th><th class="text-end">Alumnos</th><th class="text-end">Toques obligatorios</th><th class="text-end">Ya lo tocan</th><th class="text-end">Trabajando</th><th class="text-end">Avance</th><th class="text-end">Paso de año a confirmar</th></tr></thead>
                <tbody>
                    @foreach($filas as $f)
                        <tr>
                            <td>{{ $f['bloque']->sede->nombre ?? '—' }}</td>
                            <td><a href="{{ route('progreso.bloque', $f['bloque']) }}">{{ $f['bloque']->nombre }}</a></td>
                            <td>{{ $f['anio'] }}°</td>
                            <td class="text-end">{{ $f['alumnos'] }}</td>
                            <td class="text-end">{{ $f['obligatorios'] }}</td>
                            <td class="text-end">{{ $f['tocados'] }}</td>
                            <td class="text-end">{{ $f['en_proceso'] }}</td>
                            <td class="text-end">{{ $f['porcentaje'] !== null ? $f['porcentaje'].' %' : '—' }}</td>
                            <td class="text-end">{{ $f['propuestas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="small text-muted mt-2">«Avance» = toques obligatorios que los alumnos ya tocan con el bloque, sobre el total posible (alumnos × toques). Es una guía para acompañar, no una nota.</p>
    @endif
</x-ito.shell-page>
@endsection
