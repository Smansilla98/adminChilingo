@extends('layouts.app')

@section('title', 'Plan de compras')
@section('page-title', 'Plan de compras por sede')

@section('content')
<x-ito.list-page
    title="Plan de compras sugerido"
    subtitle="Según alumnos, horas de clase e inventario. Ratio objetivo: {{ $ratioObjetivo }} alumnxs/tambor · Consumo base: {{ $parchesBase }} parche(s)/tambor/año"
>
    <table class="ito-table">
        <thead>
            <tr>
                <th>Sede</th>
                <th>Alumnxs activos</th>
                <th>Sesiones/semana</th>
                <th>Tambores escuela</th>
                <th>Ratio actual<br><small>(alumnxs / tambor)</small></th>
                <th>Tambores recomendados<br><small>(según ratio objetivo)</small></th>
                <th>Tambores a comprar<br><small>(sugerido)</small></th>
                <th>Parches/año sugeridos<br><small>(toda la sede)</small></th>
                <th>Justificación breve</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sedesDatos as $d)
                <tr>
                    <td class="fw-semibold">{{ $d['sede']->nombre }}</td>
                    <td class="ito-mono">{{ $d['alumnos'] }}</td>
                    <td class="ito-mono">{{ $d['sesiones_semana'] }}</td>
                    <td class="ito-mono">{{ $d['instrumentos_escuela'] }}</td>
                    <td class="ito-mono">
                        @if($d['ratio_actual'] !== null && $d['ratio_actual'] > 0)
                            {{ number_format($d['ratio_actual'], 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="ito-mono">{{ $d['tambores_necesarios'] }}</td>
                    <td>
                        @if($d['tambores_faltantes'] > 0)
                            <x-ito.status tone="danger" :label="(string) $d['tambores_faltantes']" />
                        @else
                            <x-ito.status tone="success" label="0" />
                        @endif
                    </td>
                    <td class="ito-mono">{{ $d['parches_sugeridos'] }}</td>
                    <td class="small">
                        • {{ $d['alumnos'] }} alumnxs, {{ $d['sesiones_semana'] }} sesiones/semana (factor uso {{ $d['factor_uso'] }}).<br>
                        • {{ $d['instrumentos_escuela'] }} tambores escuela &rarr; se recomiendan {{ $d['tambores_necesarios'] }}.<br>
                        • Faltan <strong>{{ $d['tambores_faltantes'] }}</strong> tambores para alcanzar el ratio objetivo.
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="ito-empty">No hay sedes cargadas.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">
            Para armar la orden concreta: definir familias de tambores por sede y distribuir parches según inventario.
        </div>
    </x-slot:footer>
</x-ito.list-page>
@endsection
