@extends('layouts.app')

@section('title', $cuota->nombre)
@section('page-title', $cuota->nombre)

@section('content')
<x-ito.shell-page
    title="{{ $cuota->nombre }}"
    eyebrow="Cuotas"
    subtitle="Detalle de la cuota"
>

        <dl class="row">
            <dt class="col-sm-3">Nombre</dt>
            <dd class="col-sm-9">{{ $cuota->nombre }}</dd>
            @if(\Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance'))
            <dt class="col-sm-3">Para quién</dt>
            <dd class="col-sm-9">
                @if(($cuota->alcance ?? 'bloque') === \App\Models\Cuota::ALCANCE_GENERAL)
                    General (toda la escuela)
                @elseif(($cuota->alcance ?? 'bloque') === \App\Models\Cuota::ALCANCE_SEDE)
                    Diferencial — sede {{ $cuota->sede?->nombre ?? '—' }}
                @else
                    Bloque: {{ $cuota->bloque?->nombre ?? '—' }} @if($cuota->bloque?->sede) ({{ $cuota->bloque->sede->nombre }}) @endif
                @endif
            </dd>
            @elseif($cuota->bloque)
            <dt class="col-sm-3">Bloque</dt>
            <dd class="col-sm-9">{{ $cuota->bloque->nombre }} @if($cuota->bloque->sede)({{ $cuota->bloque->sede->nombre }})@endif</dd>
            @endif
            <dt class="col-sm-3">Año / Mes</dt>
            <dd class="col-sm-9">{{ $cuota->año }} {{ $cuota->nombre_mes ? '- ' . $cuota->nombre_mes : '' }}</dd>
            <dt class="col-sm-3">Monto</dt>
            <dd class="col-sm-9">$ {{ number_format($cuota->monto, 2, ',', '.') }}</dd>
            <dt class="col-sm-3">Alumnos que pueden pagar</dt>
            <dd class="col-sm-9">@if($cuota->alumnos->isEmpty())
                @if(\Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance') && ($cuota->alcance ?? 'bloque') === \App\Models\Cuota::ALCANCE_GENERAL)
                    Todos los alumnos con bloque
                @elseif(\Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance') && ($cuota->alcance ?? 'bloque') === \App\Models\Cuota::ALCANCE_SEDE)
                    Todos los alumnos de la sede (o lista abajo)
                @else
                    Todos los del bloque
                @endif
            @else {{ $cuota->alumnos->pluck('nombre_apellido')->join(', ') }} @endif</dd>
            <dt class="col-sm-3">Registros de pago</dt>
            <dd class="col-sm-9">{{ $cuota->pago_detalles_count }}</dd>
        </dl>

        <h2 class="h5 mt-4">Recordatorios WhatsApp</h2>
        <p class="text-muted small">Twilio aceptó el envío no significa que WhatsApp lo haya entregado. El estado real llega por callback.</p>
        @if(($recordatoriosWhatsapp ?? collect())->isEmpty())
            <p class="mb-3">Aún no hay envíos de recordatorio para esta cuota.</p>
        @else
            <table class="ito-table mb-3">
                <thead>
                    <tr>
                        <th>Alumno</th>
                        <th>Teléfono</th>
                        <th>Estado</th>
                        <th>SID</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recordatoriosWhatsapp as $msg)
                        <tr>
                            <td>{{ $msg->alumno?->nombre_apellido ?? '—' }}</td>
                            <td>{{ $msg->telefono }}</td>
                            <td>{{ $msg->etiquetaEstado() }}</td>
                            <td><code>{{ $msg->twilio_sid }}</code></td>
                            <td>
                                @if($msg->error_code || $msg->error_message)
                                    {{ $msg->error_code }} {{ $msg->error_message }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <a href="{{ route('cuotas.edit', $cuota) }}" class="btn btn-warning">Editar</a>
        <a href="{{ route('cuotas.index') }}" class="btn btn-secondary">Volver</a>
</x-ito.shell-page>

@endsection
