<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes {{ $mes }}/{{ $año }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 18px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f3f3f3; }
        .muted { color: #666; margin-bottom: 16px; }
        .actions { margin-bottom: 16px; }
        @media print {
            .actions { display: none; }
            body { margin: 12px; }
        }
    </style>
</head>
<body>
    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a href="{{ route('reportes.index', ['mes' => $mes, 'año' => $año]) }}">Volver</a>
    </div>
    <h1>Reportes ITO — {{ $mes }}/{{ $año }}</h1>
    <p class="muted">Generado {{ now()->format('d/m/Y H:i') }}@if(!empty($sedeScope)) · filtrado por sede@endif</p>

    <h2>Alumnos por profesor</h2>
    <table>
        <thead><tr><th>Profesor</th><th>Sedes</th><th>Bloques</th><th>Alumnos</th></tr></thead>
        <tbody>
            @foreach($alumnosPorProfesor as $row)
                <tr>
                    <td>{{ $row['profesor']->nombre }}</td>
                    <td>{{ $row['sedes']->join(', ') ?: '—' }}</td>
                    <td>{{ $row['bloques_count'] }}</td>
                    <td>{{ $row['alumnos_count'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Ingresos por profesor</h2>
    <table>
        <thead><tr><th>Profesor</th><th>Emitido</th><th>Cobrado</th><th>%</th></tr></thead>
        <tbody>
            @foreach($ingresosPorProfesor as $row)
                <tr>
                    <td>{{ $row['profesor']->nombre }}</td>
                    <td>$ {{ number_format($row['emitido'], 2, ',', '.') }}</td>
                    <td>$ {{ number_format($row['cobrado'], 2, ',', '.') }}</td>
                    <td>{{ $row['porcentaje_cobrado'] }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Financiero por sede</h2>
    <table>
        <thead><tr><th>Sede</th><th>Ingresos</th><th>Gastos</th><th>Resultado</th></tr></thead>
        <tbody>
            @foreach($resumenFinanciero as $row)
                <tr>
                    <td>{{ $row['sede']->nombre }}</td>
                    <td>$ {{ number_format($row['ingresos'], 2, ',', '.') }}</td>
                    <td>$ {{ number_format($row['total_gastos'], 2, ',', '.') }}</td>
                    <td>$ {{ number_format($row['resultado'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Resumen global</h2>
    <table>
        <tbody>
            <tr><th>Ingresos</th><td>$ {{ number_format($ingresosTotales, 2, ',', '.') }}</td></tr>
            <tr><th>Gastos</th><td>$ {{ number_format($gastosTotales, 2, ',', '.') }}</td></tr>
            <tr><th>Resultado</th><td>$ {{ number_format($resultadoGlobal, 2, ',', '.') }}</td></tr>
        </tbody>
    </table>
</body>
</html>
