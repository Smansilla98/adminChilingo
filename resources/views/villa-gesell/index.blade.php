@extends('layouts.app')

@section('title', 'Villa Gesell')
@section('page-title', 'Villa Gesell')

@section('content')
@include('villa-gesell.partials.nav')

<x-ito.list-page
    title="Gira Villa Gesell 2027"
    subtitle="Segunda quincena de enero y primera de febrero · costa atlántica"
    eyebrow="La Chilinga · Gira"
    :show-table-hint="false"
>
    <x-slot:actions>
        <a href="{{ route('villa-gesell.inscriptos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Inscribir alumno</a>
    </x-slot:actions>

    <div class="p-3">
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="ito-stat">
                    <span class="text-muted d-block">Cupo</span>
                    <strong>{{ $plan['plazas_ocupadas'] }} / {{ $plan['cupo'] }}</strong>
                    <small class="d-block">{{ $plan['lista_espera'] }} en lista de espera</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ito-stat">
                    <span class="text-muted d-block">Días de gira</span>
                    <strong>{{ $plan['dias'] }}</strong>
                    <small class="d-block">{{ $config->fecha_inicio->format('d/m') }} – {{ $config->fecha_fin->format('d/m/Y') }}</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ito-stat">
                    <span class="text-muted d-block">Cobrado</span>
                    <strong>$ {{ number_format($plan['ingresos_pagados'], 0, ',', '.') }}</strong>
                    <small class="d-block">esperado $ {{ number_format($plan['ingresos_esperados'], 0, ',', '.') }} · $ {{ number_format($plan['valor_por_dia'], 0, ',', '.') }}/día</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ito-stat">
                    <span class="text-muted d-block">Balance (cupo 100%)</span>
                    <strong>$ {{ number_format($plan['balance_cupo_lleno'], 0, ',', '.') }}</strong>
                    <small class="d-block">{{ $plan['cupo'] }} × ${{ number_format($plan['valor_por_dia'], 0, ',', '.') }}/día × {{ $plan['dias'] }} días</small>
                </div>
            </div>
        </div>

        <h2 class="h5">Datos de la gira</h2>
        <p class="text-muted">El aporte se carga <strong>por día</strong> y se multiplica por la cantidad de días. Podés cambiar fechas y cupo aunque ya haya inscriptos. Si una plaza está ocupada, no se puede bajar el cupo por debajo de ese número.</p>
        <form action="{{ route('villa-gesell.config') }}" method="POST" class="row g-3 mb-4">
            @csrf
            @method('PUT')
            <div class="col-md-3">
                <label class="form-label">Inicio</label>
                <input type="date" name="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror" value="{{ old('fecha_inicio', $config->fecha_inicio->toDateString()) }}" required>
                @error('fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Fin</label>
                <input type="date" name="fecha_fin" class="form-control @error('fecha_fin') is-invalid @enderror" value="{{ old('fecha_fin', $config->fecha_fin->toDateString()) }}" required>
                @error('fecha_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-2">
                <label class="form-label">Cupo máximo</label>
                <input type="number" name="cupo_maximo" min="1" class="form-control @error('cupo_maximo') is-invalid @enderror" value="{{ old('cupo_maximo', $config->cupo_maximo) }}" required>
                @error('cupo_maximo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Valor por día (aporte)</label>
                <input type="number" step="0.01" min="0" name="aporte_esperado" class="form-control" value="{{ old('aporte_esperado', $config->aporte_esperado) }}" required>
                <div class="form-text">Ese valor × {{ $config->cantidadDias() }} días × cupo = escenario 100%.</div>
            </div>
            <div class="col-md-12">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="2">{{ old('notas', $config->notas) }}</textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">Guardar datos de la gira</button>
            </div>
        </form>

        <h2 class="h5">Últimos inscriptos</h2>
        <table class="ito-table">
            <thead>
                <tr>
                    <th>Plaza</th>
                    <th>Alumno</th>
                    <th>Pago</th>
                    <th>Talle</th>
                    <th>Tambores</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($inscriptos->take(12) as $row)
                    <tr>
                        <td class="ito-mono">{{ $row->lista_espera ? 'Espera' : ($row->plaza ?? '—') }}</td>
                        <td>{{ $row->alumno?->nombre_apellido ?? '—' }}</td>
                        <td>{{ $row->etiquetaPago() }} · $ {{ number_format($row->monto_pagado, 0, ',', '.') }}</td>
                        <td>{{ $row->talle_remera ?: '—' }}</td>
                        <td>{{ collect([$row->tambor_principal, $row->tambor_secundario, $row->tambor_terciario])->filter()->implode(' / ') ?: '—' }}</td>
                        <td><a href="{{ route('villa-gesell.inscriptos.edit', $row) }}">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6">Todavía no hay inscriptos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ito.list-page>
@endsection
