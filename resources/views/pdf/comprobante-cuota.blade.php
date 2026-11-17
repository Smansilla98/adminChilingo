<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante {{ $numero }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        .muted { color: #6b7280; }
        .cabecera { width: 100%; border-bottom: 3px solid #2BA8E0; padding-bottom: 8px; margin-bottom: 14px; }
        .logo { height: 40px; }
        .escuela { color: #15110E; font-size: 10px; margin-top: 4px; }
        .num { font-size: 14px; font-weight: bold; color: #F26A1B; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 4px; text-align: left; }
        th { background: #F5ECDD; color: #15110E; font-size: 10px; text-transform: uppercase; }
        .der { text-align: right; }
        .total { font-size: 14px; font-weight: bold; color: #15110E; }
        .datos td { border: 0; padding: 2px 4px; }
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
                <h1>Comprobante de pago de cuota</h1>
                <div class="num">{{ $numero }}</div>
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr><td class="muted">Alumno</td><td>{{ $c->alumno?->nombre_apellido ?? '—' }}@if($c->alumno?->dni) · DNI {{ $c->alumno->dni }}@endif</td></tr>
        <tr><td class="muted">Sede</td><td>{{ $c->sede?->nombre ?? '—' }}</td></tr>
        <tr><td class="muted">Fecha de pago</td><td>{{ $c->fecha_pago?->format('d/m/Y') ?? '—' }}</td></tr>
        <tr><td class="muted">Estado</td><td>{{ $c->etiquetaEstado() }}@if($c->referencia) · Referencia {{ $c->referencia }}@endif</td></tr>
        @if($c->pago_id)<tr><td class="muted">Pago registrado</td><td>N° {{ $c->pago_id }}</td></tr>@endif
    </table>

    <table>
        <thead>
            <tr>
                <th>Cuota</th>
                <th>Bloque</th>
                <th class="der">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($c->items as $i)
                <tr>
                    <td>{{ $i->etiqueta() }}</td>
                    <td>{{ $i->bloque?->nombre ?? '—' }}</td>
                    <td class="der">$ {{ number_format((float) $i->monto, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="2" class="der total">Total</td>
                <td class="der total">$ {{ number_format((float) $c->monto_total, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
</body>
</html>
