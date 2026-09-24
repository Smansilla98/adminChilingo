@extends('layouts.app')

@section('title', $cuota->nombre)
@section('page-title', $cuota->nombre)

@section('content')
@php
    $conAlcance = \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance');
    $alcance = $conAlcance ? ($cuota->alcance ?? 'bloque') : 'bloque';
    $paraQuien = match ($alcance) {
        \App\Models\Cuota::ALCANCE_GENERAL => 'Toda la escuela',
        \App\Models\Cuota::ALCANCE_SEDE => 'Sede '.($cuota->sede?->nombre ?? '—'),
        default => $cuota->bloque ? $cuota->bloque->nombre.($cuota->bloque->sede ? ' ('.$cuota->bloque->sede->nombre.')' : '') : '—',
    };
    $vencida = $cuota->fecha_vencimiento && $cuota->fecha_vencimiento->isPast();
    $recordatorios = $recordatoriosWhatsapp ?? collect();
    $tonoWa = [
        \App\Models\WhatsappMensaje::STATUS_READ => 'success',
        \App\Models\WhatsappMensaje::STATUS_DELIVERED => 'success',
        \App\Models\WhatsappMensaje::STATUS_SENT => 'info',
        \App\Models\WhatsappMensaje::STATUS_FAILED => 'danger',
        \App\Models\WhatsappMensaje::STATUS_UNDELIVERED => 'danger',
    ];
@endphp
<x-ito.shell-page :title="$cuota->nombre" :subtitle="trim(($cuota->nombre_mes ? ucfirst($cuota->nombre_mes).' ' : '').$cuota->año).' · '.$paraQuien" :plain="true">
    <x-slot:actions>
        @if(! $cuota->activo)
            <x-ito.status tone="neutral" label="Inactiva" />
        @elseif($vencida)
            <x-ito.status tone="danger" label="Vencida" />
        @else
            <x-ito.status tone="success" label="Vigente" />
        @endif
        <a href="{{ route('cuotas.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('update', $cuota)
            <a href="{{ route('cuotas.edit', $cuota) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Monto">$ {{ number_format($cuota->monto, 2, ',', '.') }}</x-ito.fact>
        <x-ito.fact label="Vence" :value="$cuota->fecha_vencimiento?->format('d/m/Y') ?? 'Sin vencimiento'" />
        <x-ito.fact label="Para quién" :value="$paraQuien" />
        <x-ito.fact label="Pagos registrados" :value="$cuota->pago_detalles_count" />
    </x-ito.facts>

    <div class="ito-detail-grid">
        <x-ito.detail-section title="Recordatorios por WhatsApp" icon="bi-whatsapp" help="“Enviado” quiere decir que Twilio lo aceptó; la entrega real se confirma después." :flush="true">
            @if($recordatorios->isEmpty())
                <x-ito.empty icon="bi-chat-dots" title="Sin recordatorios" description="Todavía no se enviaron recordatorios para esta cuota." />
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Alumno</th><th>Teléfono</th><th>Estado</th><th>Error</th></tr></thead>
                        <tbody>
                            @foreach($recordatorios as $msg)
                                <tr>
                                    <td>{{ $msg->alumno?->nombre_apellido ?? '—' }}</td>
                                    <td class="ito-mono">{{ $msg->telefono }}</td>
                                    <td><x-ito.status :tone="$tonoWa[$msg->status] ?? 'warning'" :label="$msg->etiquetaEstado()" /></td>
                                    <td class="small text-muted">{{ trim(($msg->error_code ?? '').' '.($msg->error_message ?? '')) ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ito.detail-section>

        <x-ito.detail-section title="Quiénes la pagan" icon="bi-people">
            @if($cuota->alumnos->isEmpty())
                <p class="mb-0">
                    @if($alcance === \App\Models\Cuota::ALCANCE_GENERAL)
                        Todos los alumnos con bloque.
                    @elseif($alcance === \App\Models\Cuota::ALCANCE_SEDE)
                        Todos los alumnos de la sede.
                    @else
                        Todos los alumnos del bloque.
                    @endif
                </p>
            @else
                @foreach($cuota->alumnos as $al)
                    <div class="hub-list-item"><x-ito.person :name="$al->nombre_apellido" /></div>
                @endforeach
            @endif
            @if($cuota->descripcion)
                <p class="small text-muted mt-3 mb-0">{{ $cuota->descripcion }}</p>
            @endif
        </x-ito.detail-section>
    </div>
</x-ito.shell-page>
@endsection
