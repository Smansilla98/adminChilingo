@extends('layouts.app')

@section('title', $d['titulo'])
@section('page-title', 'Mensaje a grupo')

@section('content')
<x-ito.shell-page :title="$d['titulo']" :subtitle="$d['destino'].' · '.$d['quienes']" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="['enviada' => 'success', 'programada' => 'info', 'cancelada' => 'neutral'][$d['estado']] ?? 'warning'" :label="$d['estado_texto']" />
        <a href="{{ route('difusion.index') }}" class="btn btn-outline-secondary">Volver</a>
        @if($d['estado'] === 'programada')
            <form method="POST" action="{{ route('difusion.cancelar', $d['id']) }}" data-confirm="¿Cancelar el envío programado?">
                @csrf
                <button class="btn btn-outline-danger"><i class="bi bi-x-circle" aria-hidden="true"></i> Cancelar envío</button>
            </form>
        @endif
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Canales" :value="$d['canales_texto']" />
        <x-ito.fact label="Escrito por" :value="$d['autor']" />
        @if($d['programada_para'])
            <x-ito.fact label="Programado para" :value="\Illuminate\Support\Carbon::parse($d['programada_para'])->format('d/m/Y H:i')" />
        @endif
        <x-ito.fact label="Enviado" :value="$d['enviada_at'] ? \Illuminate\Support\Carbon::parse($d['enviada_at'])->format('d/m/Y H:i') : null" />
        <x-ito.fact label="Destinatarios" :value="$d['destinatarios']" />
        @if($d['lecturas'])
            <x-ito.fact label="Lo recibieron en la bandeja" :value="$d['lecturas']['recibieron']" />
            <x-ito.fact label="Lo leyeron" :value="$d['lecturas']['leyeron']" />
        @endif
    </x-ito.facts>

    <div class="ito-detail-grid">
        <x-ito.detail-section title="Mensaje" icon="bi-chat-text">
            <p class="mb-2" style="white-space: pre-line">{{ $d['mensaje'] }}</p>
            @if($d['enlace'])<p class="small text-muted mb-0">Enlace: <code>{{ $d['enlace'] }}</code></p>@endif
        </x-ito.detail-section>
        <x-ito.detail-section title="Cómo salió" icon="bi-bar-chart">
            @forelse($d['resultado'] as $canal => $cantidad)
                <div class="d-flex justify-content-between"><span>{{ \App\Domain\Notificaciones\DifusionService::CANALES[$canal] ?? $canal }}</span><strong>{{ $cantidad }}</strong></div>
            @empty
                <p class="text-muted mb-0">Todavía no salió.</p>
            @endforelse
        </x-ito.detail-section>
    </div>
</x-ito.shell-page>
@endsection
