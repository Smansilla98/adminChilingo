@extends('layouts.app')

@section('title', 'Plan de compras')
@section('page-title', 'Plan de compras por sede')

@section('content')
<div class="card">
    <div class="card-header py-3">
        Plan de compras sugerido
        <div class="text-muted small">
            Basado en alumnos activos, carga horaria semanal y stock actual de instrumentos de cada sede.
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted">
            Referencias usadas:
            <br>• Ratio objetivo: {{ $ratioObjetivo }} alumnxs por tambor de la escuela.
            <br>• Consumo base: {{ $parchesBase }} parche(s) por tambor y año (ajustado por carga de uso).
        </p>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
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
                        <td>{{ $d['sede']->nombre }}</td>
                        <td>{{ $d['alumnos'] }}</td>
                        <td>{{ $d['sesiones_semana'] }}</td>
                        <td>{{ $d['instrumentos_escuela'] }}</td>
                        <td>
                            @if($d['ratio_actual'] !== null && $d['ratio_actual'] > 0)
                                {{ number_format($d['ratio_actual'], 2, ',', '.') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $d['tambores_necesarios'] }}</td>
                        <td>
                            @if($d['tambores_faltantes'] > 0)
                                <strong class="text-danger">{{ $d['tambores_faltantes'] }}</strong>
                            @else
                                <span class="text-success">0</span>
                            @endif
                        </td>
                        <td>{{ $d['parches_sugeridos'] }}</td>
                        <td class="small">
                            • {{ $d['alumnos'] }} alumnxs, {{ $d['sesiones_semana'] }} sesiones/semana (factor uso {{ $d['factor_uso'] }}).<br>
                            • {{ $d['instrumentos_escuela'] }} tambores escuela &rarr; se recomiendan {{ $d['tambores_necesarios'] }}.<br>
                            • Faltan <strong>{{ $d['tambores_faltantes'] }}</strong> tambores para alcanzar el ratio objetivo.
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted">No hay sedes cargadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="small text-muted mt-3">
            Para armar la orden de compra concreta podés tomar estos números como base y
            definir, por cada sede:
            <br>• Qué familias de tambores (Repique, Surdo medio, Timbal, etc.) necesitan refuerzo.
            <br>• Cómo distribuir los parches sugeridos por medida/marca, en función del inventario actual.
        </p>
    </div>
</div>
@endsection

