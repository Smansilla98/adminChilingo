@extends('layouts.app')

@section('title', 'Inscriptos Villa Gesell')
@section('page-title', 'Inscriptos')

@section('content')
@include('villa-gesell.partials.nav')

<x-ito.list-page
    title="{{ ($estado ?? '') === 'sena' ? 'Seña · Villa Gesell' : 'Inscriptos' }}"
    subtitle="{{ ($estado ?? '') === 'sena' ? 'Quienes dejaron seña para la gira.' : 'Pagos, plaza, días y tambores. El aporte es valor por día × los días que está cada persona.' }}"
>
    <x-slot:actions>
        <a href="{{ route('villa-gesell.inscriptos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Inscribir alumno</a>
    </x-slot:actions>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Plaza</th>
                <th>Alumno</th>
                <th>Pago</th>
                <th>Saldo</th>
                <th>Días</th>
                <th>Talle</th>
                <th>Tambores</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($inscriptos as $row)
                <tr>
                    <td class="ito-mono">{{ $row->lista_espera ? 'Espera' : ($row->plaza ?? '—') }}</td>
                    <td>{{ $row->alumno?->nombre_apellido ?? '—' }}</td>
                    <td>{{ $row->etiquetaPago() }}<br><small>$ {{ number_format($row->monto_pagado, 0, ',', '.') }} / {{ number_format($row->monto_esperado, 0, ',', '.') }}</small></td>
                    <td class="ito-mono">$ {{ number_format($row->saldo(), 0, ',', '.') }}</td>
                    <td>
                        @if($row->fecha_desde && $row->fecha_hasta)
                            {{ $row->fecha_desde->format('d/m') }}–{{ $row->fecha_hasta->format('d/m') }}
                            <small class="d-block">{{ $row->diasUtilizados() }} días · ${{ number_format($config->valorPorDia(), 0, ',', '.') }}/día</small>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $row->talle_remera ?: '—' }}</td>
                    <td>
                        {{ collect([
                            $row->tambor_principal,
                            $row->tambor_secundario,
                            $row->tambor_terciario,
                        ])->filter()->implode(' · ') ?: '—' }}
                    </td>
                    <td>
                        <x-ito.actions :id="'vg-ins-'.$row->id">
                            <li><a class="dropdown-item" href="{{ route('villa-gesell.inscriptos.edit', $row) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li>
                                <form action="{{ route('villa-gesell.inscriptos.destroy', $row) }}" method="POST" data-confirm="¿Sacar a esta persona de la gira?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="dropdown-item text-danger" type="submit">Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">{{ ($estado ?? '') === 'sena' ? 'Nadie con seña todavía.' : 'Nadie inscripto todavía. El padrón de la escuela no se duplica: se elige un alumno existente.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</x-ito.list-page>
@endsection
