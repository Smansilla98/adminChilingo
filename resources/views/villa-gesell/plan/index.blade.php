@extends('layouts.app')

@section('title', 'Plan de gastos Villa Gesell')
@section('page-title', 'Plan de gastos')

@section('content')
@include('villa-gesell.partials.nav')

<x-ito.list-page
    title="Plan: valor por día × cantidad de días"
    subtitle="{{ $plan['dias'] }} días ({{ $config->fecha_inicio->format('d/m/Y') }} – {{ $config->fecha_fin->format('d/m/Y') }}). Cupo {{ $plan['cupo'] }}. Aporte ${{ number_format($plan['valor_por_dia'], 0, ',', '.') }} por día."
>
    <div class="p-3">
        <h2 class="h5">Ingresos</h2>
        <table class="ito-table mb-4">
            <tbody>
                <tr>
                    <td>Cobrado hasta ahora</td>
                    <td class="ito-mono text-end">$ {{ number_format($plan['ingresos_pagados'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Esperado (suma de aportes de inscriptos)</td>
                    <td class="ito-mono text-end">$ {{ number_format($plan['ingresos_esperados'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Si se llena el cupo ({{ $plan['cupo'] }} personas × ${{ number_format($plan['valor_por_dia'], 0, ',', '.') }}/día × {{ $plan['dias'] }} días)</td>
                    <td class="ito-mono text-end">$ {{ number_format($plan['ingresos_si_cupo_lleno'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>

        <h2 class="h5">Egresos proyectados (todos los días)</h2>
        <table class="ito-table mb-4">
            <tbody>
                @foreach(\App\Models\VillaGesellGasto::TIPOS as $k => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="ito-mono text-end">$ {{ number_format($plan['gastos_por_tipo'][$k] ?? 0, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td>Insumos (total del ABM)</td>
                    <td class="ito-mono text-end">$ {{ number_format($plan['insumos_totales'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Total egresos</th>
                    <th class="ito-mono text-end">$ {{ number_format($plan['gastos_totales'] + $plan['insumos_totales'], 0, ',', '.') }}</th>
                </tr>
            </tbody>
        </table>

        <h2 class="h5">Balances</h2>
        <table class="ito-table">
            <tbody>
                <tr>
                    <td>Cobrado − egresos</td>
                    <td class="ito-mono text-end">$ {{ number_format($plan['balance_pagado'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td>Esperado de inscriptos − egresos</td>
                    <td class="ito-mono text-end">$ {{ number_format($plan['balance_esperado'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Cupo lleno − egresos (escenario 100%)</th>
                    <th class="ito-mono text-end">$ {{ number_format($plan['balance_cupo_lleno'], 0, ',', '.') }}</th>
                </tr>
            </tbody>
        </table>
        <p class="text-muted mt-3 mb-0">Regla: el número que cargás es el valor de un día; el total es ese valor × {{ $plan['dias'] }} días (gastos diarios y aportes). Los fijos, traslados y nafta en modo “valor único” no se multiplican. Plazas ocupadas: {{ $plan['plazas_ocupadas'] }} · lista de espera: {{ $plan['lista_espera'] }}.</p>
    </div>
</x-ito.list-page>
@endsection
