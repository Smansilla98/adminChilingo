@extends('layouts.app')

@section('title', 'Mis shows')
@section('page-title', 'Mis shows')

@section('content')
<x-ito.shell-page title="Mis shows" subtitle="Los shows a los que te convocaron. Contanos si vas, y mirá los temas, los ensayos y cómo llegar." :plain="true">
    @forelse($items as $i)
        @php
            $show = $i['show'];
            $conf = $i['confirmacion'];
            $plan = $i['plan'];
        @endphp
        <x-ito.detail-section :title="$show->titulo" icon="bi-mic" class="mb-3">
            <p class="mb-2">
                {{ ucfirst($show->fecha->locale('es')->translatedFormat('l j \d\e F')) }}
                @if($show->hora_inicio) · {{ $show->hora_inicio->format('H:i') }} hs @endif
                @if($show->lugar) · {{ $show->lugar }} @endif
            </p>
            <form method="POST" action="{{ route('alumno.shows.confirmar', $show) }}" class="d-flex flex-wrap gap-2 align-items-center mb-3">
                @csrf
                <span class="small text-muted">¿Vas?</span>
                @foreach(\App\Models\ShowConfirmacion::RESPUESTAS as $valor => $etiqueta)
                    <button name="respuesta" value="{{ $valor }}" class="btn btn-sm {{ $conf?->respuesta === $valor ? 'btn-primary' : 'btn-outline-secondary' }}" aria-pressed="{{ $conf?->respuesta === $valor ? 'true' : 'false' }}">{{ $etiqueta }}</button>
                @endforeach
                <input name="comentario" class="form-control form-control-sm" style="max-width: 260px" placeholder="Comentario (opcional)" value="{{ $conf?->comentario }}" aria-label="Comentario">
            </form>
            @if($plan['logistica']['punto_encuentro'] || $plan['logistica']['transporte'])
                <p class="small mb-2"><strong>Encuentro:</strong> {{ $plan['logistica']['punto_encuentro'] ?? '—' }} {{ $plan['logistica']['hora_encuentro'] ? '· '.$plan['logistica']['hora_encuentro'].' hs' : '' }}
                    @if($plan['logistica']['transporte'])· <strong>Transporte:</strong> {{ $plan['logistica']['transporte'] }}@endif</p>
            @endif
            @if($plan['temas'] !== [])
                <p class="small mb-2"><strong>Temas:</strong> {{ collect($plan['temas'])->pluck('nombre')->join(' · ') }}</p>
            @endif
            @if($plan['ensayos'] !== [])
                <p class="small mb-0"><strong>Ensayos:</strong>
                    {{ collect($plan['ensayos'])->map(fn ($e) => \Illuminate\Support\Carbon::parse($e['fecha'])->format('d/m').($e['hora_inicio'] ? ' '.$e['hora_inicio'] : '').($e['lugar'] ? ' ('.$e['lugar'].')' : ''))->join(' · ') }}</p>
            @endif
        </x-ito.detail-section>
    @empty
        <x-ito.empty title="No tenés shows por delante" description="Cuando convoquen a tu bloque a un show, aparece acá." icon="bi-mic" />
    @endforelse
</x-ito.shell-page>
@endsection
