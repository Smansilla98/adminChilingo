@extends('layouts.app')

@section('title', 'Capítulos — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Archivo histórico</p>
            <h1 class="ito-page-title">Capítulos</h1>
            <p class="ito-page-sub">Los grandes períodos de la historia. En público se ordenan por año de inicio; el orden manual desempata.</p>
        </div>
        <div class="ito-page-actions">
            @can('create', \App\Models\ArchivoCapitulo::class)
                <a href="{{ route('archivo.gestion.capitulos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo capítulo</a>
            @endcan
        </div>
    </div>
    @include('archivo.gestion._nav')

    @if($capitulos->isEmpty())
        <x-ito.empty title="Todavía no hay capítulos" description="Por ejemplo: Fundación, Primeros años, Primeras giras, Nuevas sedes, 30 años." icon="bi-book" />
    @else
        <ol class="agx-lista" @can('create', \App\Models\ArchivoCapitulo::class) data-ordenable data-url="{{ route('archivo.gestion.capitulos.orden') }}" @endcan>
            @foreach($capitulos as $c)
                <li class="agx-lista__item" data-id="{{ $c->id }}" draggable="true" tabindex="0" aria-label="{{ $c->titulo }}. Usá las flechas para moverlo.">
                    <span class="agx-lista__asa" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                    <span class="agx-lista__mini" style="background: {{ $c->portada?->color ?: '#2a2725' }}">@if($c->portada)<img src="{{ $c->portada->imagenUrl(400) }}" alt="" loading="lazy">@endif</span>
                    <span class="agx-lista__texto">
                        <a href="{{ route('archivo.gestion.capitulos.edit', $c) }}" class="fw-semibold">{{ $c->titulo }}</a>
                        <span class="text-muted small">{{ $c->periodo() ?: 'Sin período' }} · {{ $c->acontecimientos_count }} acontecimientos · {{ $c->fotos_count }} fotos</span>
                    </span>
                    <span @class(['ito-status', 'ito-status--success' => $c->publicado, 'ito-status--neutral' => ! $c->publicado])>{{ $c->publicado ? 'Publicado' : 'Borrador' }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-gestion.js') }}?v=1" defer></script>
@endpush
