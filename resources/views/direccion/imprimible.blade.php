<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tablero de dirección</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 6px; }
        h2 { font-size: 13px; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; }
        th { background: #f3f3f3; }
        .num { text-align: right; }
        .muted { color: #555; }
        .actions { margin-bottom: 16px; }
        @media print { .actions { display: none; } body { margin: 12px; } }
    </style>
</head>
<body>
@php
    $plata = fn ($n) => '$ '.number_format((float) $n, 0, ',', '.');
    $pct = fn ($n) => $n === null ? '—' : number_format((float) $n, 1, ',', '.').' %';
    // Mismo alcance explícito que la pantalla web (direccion/index).
    $alcanceTexto = $d['alcance'] === 'global'
        ? 'Toda la escuela.'
        : 'Solo '.($sedeId ? $sedes->firstWhere('id', $sedeId)?->nombre : $sedes->pluck('nombre')->join(', ')).'.';
@endphp
    @if($imprimir ?? false)<div class="actions"><button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button></div>@endif
    <h1>La Chilinga · Tablero de dirección</h1>
    <p class="muted">{{ $alcanceTexto }} · últimos 12 meses · generado {{ now()->format('d/m/Y H:i') }}</p>

    <h2>Resumen</h2>
    <table>
        <tr><th>Alumnos activos</th><td class="num">{{ $d['alumnos']['activos'] }}</td><th>Altas / bajas (12 meses)</th><td class="num">{{ $d['alumnos']['altas_12m'] }} / {{ $d['alumnos']['bajas_12m'] }}</td></tr>
        <tr><th>Retención</th><td class="num">{{ $pct($d['alumnos']['retencion']) }}</td><th>Deuda vencida</th><td class="num">{{ $plata($d['morosidad']['total']) }} ({{ $d['morosidad']['alumnos'] }} alumnos)</td></tr>
        <tr><th>Ocupación de bloques</th><td class="num">{{ $pct($d['ocupacion']['porcentaje']) }}</td><th>Asistencia promedio</th><td class="num">{{ $pct($d['asistencia_promedio']) }}</td></tr>
        <tr><th>Proyección {{ $d['proyeccion']['etiqueta'] }}</th><td class="num" colspan="3">Ingresos {{ $plata($d['proyeccion']['ingresos']) }} · gastos {{ $plata($d['proyeccion']['gastos']) }} · resultado {{ $plata($d['proyeccion']['resultado']) }} <span class="muted">({{ $d['proyeccion']['metodo'] }})</span></td></tr>
    </table>

    <h2>Mes a mes</h2>
    <table>
        <thead><tr><th>Mes</th><th class="num">Emitido</th><th class="num">Cobrado</th><th class="num">Cobranza</th><th class="num">Gastos</th><th class="num">Resultado</th><th class="num">Margen</th><th class="num">Altas</th><th class="num">Bajas</th><th class="num">Asistencia</th></tr></thead>
        <tbody>
            @foreach($d['serie'] as $p)
                <tr><td>{{ $p['etiqueta'] }}</td><td class="num">{{ $plata($p['emitido']) }}</td><td class="num">{{ $plata($p['cobrado']) }}</td><td class="num">{{ $pct($p['cobranza']) }}</td><td class="num">{{ $plata($p['gastos']) }}</td><td class="num">{{ $plata($p['resultado']) }}</td><td class="num">{{ $pct($p['margen']) }}</td><td class="num">{{ $p['altas'] }}</td><td class="num">{{ $p['bajas'] }}</td><td class="num">{{ $pct($p['asistencia']) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Deuda vencida por sede</h2>
    <table><thead><tr><th>Sede</th><th class="num">Deuda</th><th class="num">Alumnos</th></tr></thead><tbody>
        @forelse($d['morosidad']['por_sede'] as $m)<tr><td>{{ $m['sede'] }}</td><td class="num">{{ $plata($m['total']) }}</td><td class="num">{{ $m['alumnos'] }}</td></tr>@empty<tr><td colspan="3">Sin deuda vencida.</td></tr>@endforelse
    </tbody></table>
</body>
</html>
