@extends('layouts.publico')

@section('title', 'Programa de la escuela')
@section('publico-brand', 'Programa')

@section('content')
@php
    $catLabels = \App\Models\ProgramaSeccion::categorias();
    $seccionActiva = request('seccion');
    $esAdmin = auth()->user()?->isAdmin();
@endphp

<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Abierto · sin cuenta</p>
        <h1>Programa oficial</h1>
        <p class="biblio-lead">Toques por año, textos de la escuela y la partitura de cada ritmo. Entrá, escuchá y estudiá tu parte.</p>
    </div>
    <div class="prog-hero-actions">
        <a href="{{ route('programa.partituras.index') }}" class="btn btn-primary">
            <i class="bi bi-music-note-beamed"></i> Ir a partituras
        </a>
        <a href="#toques-por-anio" class="btn btn-outline-secondary">Ver toques</a>
    </div>
</div>

@if(($estadoPrograma ?? 'ok') === 'sin_tabla')
<div class="alert alert-warning">Falta migrar la base: <code>php artisan migrate --force</code></div>
@elseif(($estadoPrograma ?? '') === 'vacio')
<div class="alert alert-info">Todavía no hay toques publicados.</div>
@elseif(($estadoPrograma ?? '') === 'error')
<div class="alert alert-danger">Error al cargar el programa.</div>
@else

@if($porAño->isNotEmpty())
<nav class="prog-year-chips" aria-label="Años del programa">
    <a class="prog-chip" href="#toques-por-anio">Todos los toques</a>
    @foreach($porAño as $año => $ritmos)
        <a class="prog-chip" href="#anio-{{ $año }}">{{ $años[$año] ?? $año.'°' }} · {{ $ritmos->count() }}</a>
    @endforeach
    @if($seccionesPorCategoria->isNotEmpty())
        <a class="prog-chip" href="#contenido-programa">Contenido</a>
    @endif
</nav>
@endif

<section id="toques-por-anio" class="mb-4">
    @foreach([1, 2, 3, 4, 5, 6] as $año)
        @php $ritmos = $porAño->get($año, collect()); @endphp
        @if($ritmos->isNotEmpty())
        <div id="anio-{{ $año }}" class="mb-4">
            <div class="prog-anio-head">
                <h2>{{ $años[$año] ?? $año.'° Año' }}</h2>
                <span class="text-muted small">{{ $ritmos->count() }} toques</span>
            </div>
            <div class="prog-toque-grid">
                @foreach($ritmos as $r)
                    @php
                        $m = $r->mediosNormalizados();
                        $tieneScore = \App\Support\PartituraScore::tieneGolpes($m['partitura_score'] ?? null);
                        $tienePdf = ! empty($m['partitura']['path']);
                    @endphp
                    <a href="{{ $r->slug ? route('programa.toque.show', $r) : '#' }}" class="prog-toque-tile {{ $r->slug ? '' : 'disabled' }}">
                        <div class="prog-toque-tile__top">
                            <span class="prog-toque-tile__n">{{ $r->orden }}.</span>
                            @if($r->opcional)<span class="prog-pill">Opcional</span>@endif
                        </div>
                        <h3>{{ $r->nombre }}</h3>
                        @if($r->autor)
                            <p class="prog-toque-tile__meta">{{ $r->autor }}</p>
                        @endif
                        <div class="prog-pills">
                            @if($tieneScore)<span class="prog-pill prog-pill--ok">Partitura</span>@endif
                            @if($tienePdf)<span class="prog-pill">PDF</span>@endif
                            @if($r->tieneProfundizacion())<span class="prog-pill">Textos</span>@endif
                            @if($esAdmin && isset($r->publicado) && ! $r->publicado)<span class="prog-pill">Borrador</span>@endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
        @endif
    @endforeach
</section>

@if($seccionesPorCategoria->isNotEmpty())
<div id="contenido-programa" class="row g-3">
    <div class="col-lg-4">
        <div class="card programa-nav-card sticky-lg-top" style="top: 4.5rem; z-index: 2;">
            <div class="card-header py-2">
                <strong class="small text-uppercase">Contenido de la escuela</strong>
            </div>
            <div class="list-group list-group-flush programa-nav-list">
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
                    @if($esAdmin)
                    <a href="{{ route('programa.seccion.edit', $sec) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Editar</a>
                    @endif
                </div>
                <div class="card-body programa-contenido">
                    {!! $sec->cuerpo !!}
                </div>
            </section>
            @endforeach
        @endforeach
    </div>
</div>
@endif

@endif
@endsection
