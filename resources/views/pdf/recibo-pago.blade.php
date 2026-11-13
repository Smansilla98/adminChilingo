<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $numero }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        .muted { color: #6b7280; }
        .cabecera { width: 100%; border-bottom: 3px solid #2BA8E0; padding-bottom: 8px; margin-bottom: 14px; }
        .logo { height: 40px; }
        .escuela { color: #15110E; font-size: 10px; margin-top: 4px; }
        .num { font-size: 14px; font-weight: bold; text-align: right; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 4px; text-align: left; }
        th { background: #F5ECDD; color: #15110E; font-size: 10px; text-transform: uppercase; }
        .der { text-align: right; }
        .total { font-size: 14px; font-weight: bold; color: #15110E; }
        .acento { color: #F26A1B; }
        .anulado { color: #b91c1c; font-weight: bold; margin-top: 10px; }
        .notas { margin-top: 14px; white-space: pre-line; }
    </style>
</head>
<body>
    <table class="cabecera">
        <tr>
            <td style="border: 0; padding: 0;">
                @if($logo)<img src="{{ $logo }}" class="logo" alt="La Chilinga">@endif
                <div class="escuela">Escuela Popular del Tambor · Asociación Civil La Chilinga</div>
            </td>
            <td style="border: 0; padding: 0; text-align: right;">
                <h1>Recibo de pago</h1>
                <div class="muted">Pago del {{ $pago->fecha_pago->format('d/m/Y') }}</div>
                <div class="num acento">{{ $numero }}</div>
            </td>
        </tr>
    </table>

    @if($anulado)
        <div class="anulado">PAGO ANULADO el {{ $pago->anulado_at?->format('d/m/Y') }}. No cuenta para saldos.</div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Alumno</th>
                <th>Cuota</th>
                <th class="der">Pagado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($pago->detalles as $d)
                <tr>
                    <td>{{ $d->alumno?->nombre_apellido ?? '—' }}</td>
                    <td>{{ $d->cuota?->nombre ?? '—' }}@if($d->cuota?->mes) ({{ str_pad((string) $d->cuota->mes, 2, '0', STR_PAD_LEFT) }}/{{ $d->cuota->año }})@endif</td>
                    <td class="der">$ {{ number_format((float) $d->monto, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" class="der total">Total</td>
                <td class="der total">$ {{ number_format((float) $pago->monto_total, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <p class="muted">Registrado por {{ $pago->registradoPor?->name ?? '—' }}.</p>

    @if($pago->notas)
        <div class="notas">{{ $pago->notas }}</div>
    @endif
</body>
</html>
