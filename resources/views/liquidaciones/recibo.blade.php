<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo {{ $l->numeroRecibo() }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ddd; padding: 5px 7px; text-align: left; }
        th { background: #f3f3f3; }
        .num { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        .muted { color: #555; }
        .firma { margin-top: 48px; display: flex; gap: 48px; }
        .firma div { flex: 1; border-top: 1px solid #333; padding-top: 4px; text-align: center; }
        .actions { margin-bottom: 16px; }
        @media print { .actions { display: none; } body { margin: 12px; } }
    </style>
</head>
<body>
@php
    $plata = fn ($n) => '$ '.number_format((float) $n, 2, ',', '.');
    $d = $l->detalle ?? [];
@endphp
    @if($imprimir ?? false)
        <div class="actions"><button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button></div>
    @endif
    @if($logo ?? null)<img src="{{ $logo }}" alt="La Chilinga" style="height: 40px; margin-bottom: 6px;">@endif
    <h1>La Chilinga · Recibo de liquidación docente</h1>
    <p class="muted">N° {{ $l->numeroRecibo() }} · Período {{ $l->periodo() }} · {{ $l->etiquetaEstado() }}@if($l->pagada_at) el {{ $l->pagada_at->format('d/m/Y') }}@endif</p>

    <table>
        <tr><th>Docente</th><td>{{ $l->profesor->nombre ?? '—' }}</td></tr>
        <tr><th>Modalidad</th><td>{{ \App\Models\DocenteTarifa::MODALIDADES[$l->modalidad] ?? $l->modalidad }}</td></tr>
        <tr><th>Horas</th><td>{{ number_format($l->horas_dictadas, 2, ',', '.') }} dictadas · {{ number_format($l->horas_ajuste, 2, ',', '.') }} de ajustes · {{ number_format($l->horasTotales(), 2, ',', '.') }} en total</td></tr>
    </table>

    @if($l->modalidad === 'hora')
        <h2>Detalle por sede</h2>
        <table>
            <thead><tr><th>Sede</th><th class="num">Horas</th><th class="num">Valor hora</th><th class="num">Monto</th></tr></thead>
            <tbody>
                @foreach($d['por_sede'] ?? [] as $s)
                    <tr><td>{{ $s['sede'] }}</td><td class="num">{{ number_format($s['horas'], 2, ',', '.') }}</td><td class="num">{{ $plata($s['valor_hora']) }}</td><td class="num">{{ $plata($s['monto']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @else
        <h2>Detalle</h2>
        <p>Abono por porcentaje de las cuotas cobradas en el período: {{ $plata($l->monto_porcentaje) }}.</p>
    @endif

    <p class="total">Total: {{ $plata($l->monto_total) }}</p>
    @if($l->aprobadaPor)<p class="muted">Aprobó: {{ $l->aprobadaPor->name }}{{ $l->aprobada_at ? ' el '.$l->aprobada_at->format('d/m/Y') : '' }}</p>@endif

    <div class="firma"><div>Firma del docente</div><div>Por La Chilinga</div></div>
    <p class="muted" style="margin-top:16px">Generado {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
