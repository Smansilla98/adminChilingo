@extends('layouts.app')

@section('title', 'Programa de la escuela')
@section('page-title', 'Programa de ritmos')

@section('content')
@php
    $catLabels = \App\Models\ProgramaSeccion::categorias();
    $seccionActiva = request('seccion');
@endphp

<div class="programa-hero card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <h2 class="h4 mb-1"><i class="bi bi-music-note-list text-warning"></i> Programa oficial La Chilinga</h2>
                <p class="text-muted mb-0 small">Ritmos prácticos y teóricos · Toques por año · Material de cada toque</p>
            </div>
            @if(auth()->user()?->isAdmin())
            <span class="badge bg-secondary align-self-start">Edición disponible en cada toque y sección</span>
            @endif
        </div>
    </div>
</div>

@if(($estadoPrograma ?? 'ok') === 'sin_tabla')
<div class="alert alert-warning">Falta migrar la base: <code>php artisan migrate --force</code></div>
@elseif(($estadoPrograma ?? '') === 'vacio')
<div class="alert alert-info">No hay ritmos cargados. <code>php artisan migrate --force</code></div>
@elseif(($estadoPrograma ?? '') === 'error')
<div class="alert alert-danger">Error al cargar el programa.</div>
@else

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card programa-nav-card sticky-lg-top" style="top: 1rem; z-index: 2;">
            <div class="card-header py-2">
                <strong class="small text-uppercase">Índice del programa</strong>
            </div>
            <div class="list-group list-group-flush programa-nav-list">
                <a href="#toques-por-anio" class="list-group-item list-group-item-action">Toques por año</a>
                @foreach($catLabels as $catKey => $catLabel)
                    @php $items = $seccionesPorCategoria->get($catKey, collect()); @endphp
                    @if($items->isNotEmpty())
                    <div class="list-group-item programa-nav-cat small text-uppercase text-muted py-1">{{ $catLabel }}</div>
                    @foreach($items as $sec)
                    <a href="{{ route('programa.index', ['seccion' => $sec->slug]) }}#sec-{{ $sec->slug }}"
                       class="list-group-item list-group-item-action ps-4 py-2 {{ $seccionActiva === $sec->slug ? 'active' : '' }}">
                        {{ $sec->titulo }}
                    </a>
                    @endforeach
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        @foreach($catLabels as $catKey => $catLabel)
            @php $items = $seccionesPorCategoria->get($catKey, collect()); @endphp
            @foreach($items as $sec)
            <section id="sec-{{ $sec->slug }}" class="card programa-seccion-card mb-3 {{ $seccionActiva === $sec->slug ? 'border-warning' : '' }}">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h3 class="h6 mb-0">{{ $sec->titulo }}</h3>
                        @if($sec->subtitulo)
                        <p class="text-muted small mb-0 mt-1">{{ $sec->subtitulo }}</p>
                        @endif
                    </div>
                    @if(auth()->user()?->isAdmin())
                    <a href="{{ route('programa.seccion.edit', $sec) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Editar</a>
                    @endif
                </div>
                <div class="card-body programa-contenido">
                    {!! $sec->cuerpo !!}
                </div>
            </section>
            @endforeach
        @endforeach

        <section id="toques-por-anio" class="card">
            <div class="card-header">
                <h3 class="h6 mb-0">Toques por año</h3>
                <p class="text-muted small mb-0 mt-1">Cada toque puede tener su página con textos, videos y archivos.</p>
            </div>
            <div class="card-body">
                @foreach([1, 2, 3, 4, 5, 6] as $año)
                    @php $ritmos = $porAño->get($año, collect()); @endphp
                    @if($ritmos->isNotEmpty())
                    <div class="programa-anio-block mb-4">
                        <h4 class="h6 border-bottom pb-2 mb-3">{{ $años[$año] ?? $año.'° Año' }}</h4>
                        <div class="row g-2">
                            @foreach($ritmos as $r)
                            <div class="col-md-6">
                                <a href="{{ $r->slug ? route('programa.toque.show', $r) : '#' }}" class="programa-toque-card {{ $r->slug ? '' : 'disabled' }}">
                                    <span class="programa-toque-orden">{{ $r->orden }}.</span>
                                    <span class="programa-toque-nombre">{{ $r->nombre }}</span>
                                    @if($r->opcional)
                                    <span class="badge bg-secondary ms-1">Opcional</span>
                                    @endif
                                    @if($r->tieneProfundizacion())
                                    <i class="bi bi-journal-text text-warning ms-auto" title="Con profundización"></i>
                                    @endif
                                    @if($r->autor)
                                    <span class="programa-toque-meta d-block">{{ $r->autor }}</span>
                                    @endif
                                </a>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>
        </section>
    </div>
</div>

@endif

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/programa.css') }}?v=3">
@endpush
