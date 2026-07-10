<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen diario — {{ $app_name }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e5e7eb;">
                <tr>
                    <td style="background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);padding:28px 32px;">
                        <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.85);">Resumen diario</div>
                        <div style="font-size:26px;font-weight:700;color:#111827;margin-top:6px;">{{ $app_name }}</div>
                        <div style="font-size:14px;color:rgba(17,24,39,.85);margin-top:8px;">{{ $fecha_larga }}</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px 32px 8px;">
                        <p style="margin:0 0 18px;font-size:16px;line-height:1.5;color:#374151;">{{ $saludo }}</p>

                        @if($todo_ok)
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:12px;margin-bottom:20px;">
                                <tr>
                                    <td style="padding:18px 20px;">
                                        <div style="font-size:15px;font-weight:700;color:#065f46;margin-bottom:4px;">Todo al día</div>
                                        <div style="font-size:14px;line-height:1.5;color:#047857;">No hay asistencias atrasadas ni cuotas del mes sin registrar en el panel.</div>
                                    </td>
                                </tr>
                            </table>
                        @else
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:20px;">
                                <tr>
                                    <td width="50%" style="padding-right:8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;">
                                            <tr>
                                                <td style="padding:16px 18px;">
                                                    <div style="font-size:12px;font-weight:700;color:#9a3412;text-transform:uppercase;letter-spacing:.04em;">Asistencias</div>
                                                    <div style="font-size:28px;font-weight:700;color:#c2410c;margin-top:4px;">{{ $resumen['asistencias'] ?? 0 }}</div>
                                                    <div style="font-size:12px;color:#9a3412;margin-top:2px;">clases pendientes o incompletas</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td width="50%" style="padding-left:8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;">
                                            <tr>
                                                <td style="padding:16px 18px;">
                                                    <div style="font-size:12px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.04em;">Cuotas</div>
                                                    <div style="font-size:28px;font-weight:700;color:#b91c1c;margin-top:4px;">{{ $resumen['cuotas'] ?? 0 }}</div>
                                                    <div style="font-size:12px;color:#991b1b;margin-top:2px;">sin pago registrado</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        @endif
                    </td>
                </tr>

                @if(!empty($asistencias))
                <tr>
                    <td style="padding:8px 32px 0;">
                        <div style="font-size:13px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Asistencias a revisar</div>
                        @foreach($asistencias as $item)
                            @php
                                $border = match($item['prioridad'] ?? 'baja') {
                                    'alta' => '#dc2626',
                                    'media' => '#f59e0b',
                                    default => '#d1d5db',
                                };
                            @endphp
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:10px;background:#fffbeb;border:1px solid #fde68a;border-left:4px solid {{ $border }};border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <div style="font-size:14px;line-height:1.45;color:#374151;">{{ $item['texto'] }}</div>
                                        @if(!empty($item['url']) && !empty($item['accion']))
                                            <div style="margin-top:8px;">
                                                <a href="{{ $item['url'] }}" style="font-size:13px;font-weight:700;color:#b45309;text-decoration:none;">{{ $item['accion'] }} →</a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @endforeach
                    </td>
                </tr>
                @endif

                @if(!empty($cuotas))
                <tr>
                    <td style="padding:18px 32px 0;">
                        <div style="font-size:13px;font-weight:700;color:#991b1b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:10px;">Cuotas del mes</div>
                        @foreach($cuotas as $item)
                            @php
                                $border = match($item['prioridad'] ?? 'baja') {
                                    'alta' => '#dc2626',
                                    'media' => '#f59e0b',
                                    default => '#d1d5db',
                                };
                            @endphp
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-bottom:10px;background:#fef2f2;border:1px solid #fecaca;border-left:4px solid {{ $border }};border-radius:10px;">
                                <tr>
                                    <td style="padding:14px 16px;">
                                        <div style="font-size:14px;line-height:1.45;color:#374151;">{{ $item['texto'] }}</div>
                                        @if(!empty($item['url']) && !empty($item['accion']))
                                            <div style="margin-top:8px;">
                                                <a href="{{ $item['url'] }}" style="font-size:13px;font-weight:700;color:#b91c1c;text-decoration:none;">{{ $item['accion'] }} →</a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @endforeach
                    </td>
                </tr>
                @endif

                <tr>
                    <td style="padding:28px 32px 12px;">
                        <table role="presentation" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="border-radius:10px;background:#111827;">
                                    <a href="{{ $panel_url }}" style="display:inline-block;padding:14px 22px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;">Ir al panel</a>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top:14px;font-size:13px;line-height:1.6;">
                            <a href="{{ $asistencias_url }}" style="color:#b45309;text-decoration:none;margin-right:14px;">Asistencias</a>
                            <a href="{{ $pagos_url }}" style="color:#b45309;text-decoration:none;">Pagos</a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:16px 32px 28px;border-top:1px solid #e5e7eb;">
                        <div style="font-size:12px;line-height:1.5;color:#9ca3af;">
                            Este mail se envía automáticamente de lunes a viernes a las 10:00 (hora Argentina).
                            <br>Generado el {{ $fecha_corta }}.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
