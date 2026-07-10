{{ $app_name }} — Resumen diario
Fecha: {{ $fecha_corta }}

{{ $saludo }}

@if($todo_ok)
Todo al día: no hay asistencias atrasadas ni cuotas del mes sin registrar.
@else
@if(!empty($asistencias))
ASISTENCIAS ({{ $resumen['asistencias'] ?? 0 }})
@foreach($asistencias as $item)
- {{ $item['texto'] }}
@endforeach

@endif
@if(!empty($cuotas))
CUOTAS ({{ $resumen['cuotas'] ?? 0 }})
@foreach($cuotas as $item)
- {{ $item['texto'] }}
@endforeach

@endif
@endif

Panel: {{ $panel_url }}
Asistencias: {{ $asistencias_url }}
Pagos: {{ $pagos_url }}
