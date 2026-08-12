@extends('layouts.publico')

@section('title', $programaRitmo->nombre.' — Programa')
@section('publico-brand', 'Programa')

@section('content')
@php
    $añoLabel = $años[$programaRitmo->año] ?? $programaRitmo->año.'° Año';
    $secciones = $programaRitmo->seccionesProfundizacion();
    $enlaces = is_array($programaRitmo->enlaces) ? $programaRitmo->enlaces : [];
    $esAdmin = auth()->user()?->isAdmin();
    $score = $medios['partitura_score'] ?? null;
    $tieneScore = is_array($score) && \App\Support\PartituraScore::tieneGolpes($score);
    $tienePdf = ! empty(($medios['partitura']['path'] ?? null));
    $ultimaPagina = \App\Support\ProgramaRitmoMedios::ultimaEdicion(is_array($medios ?? null) ? $medios : [], 'pagina_ediciones');
@endphp

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}">Programa</a></li>
        <li class="breadcrumb-item"><a href="{{ route('programa.partituras.index') }}">Partituras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}#anio-{{ $programaRitmo->año }}">{{ $añoLabel }}</a></li>
        <li class="breadcrumb-item active">{{ $programaRitmo->nombre }}</li>
    </ol>
</nav>

<div class="biblio-hero biblio-hero--compact">
    <div>
        <p class="biblio-eyebrow">{{ $añoLabel }} · Toque {{ $programaRitmo->orden }}</p>
        <h1>{{ $programaRitmo->nombre }}</h1>
        @if($programaRitmo->autor)
            <p class="biblio-lead">{{ $programaRitmo->autor }}</p>
        @endif
        @if($programaRitmo->resumen)
            <p class="biblio-lead">{{ $programaRitmo->resumen }}</p>
        @endif
        @if($ultimaPagina)
            <p class="small text-muted mb-0">Última edición de la ficha: {{ $ultimaPagina['nombre'] }}
                @if(!empty($ultimaPagina['at']))
                    · {{ \Illuminate\Support\Carbon::parse($ultimaPagina['at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                @endif
            </p>
        @endif
    </div>
    <div class="prog-hero-actions">
        @if($tieneScore)
            <a href="#partitura" class="btn btn-primary"><i class="bi bi-play-fill"></i> Escuchar</a>
        @endif
        <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-warning"><i class="bi bi-pencil-square"></i> Editar partitura</a>
        <a href="{{ route('programa.toque.edit', $programaRitmo) }}" class="btn btn-outline-warning"><i class="bi bi-plus-lg"></i> Sumar material</a>
        @if($esAdmin)
            <a href="{{ route('programa.toque.partitura.edit', $programaRitmo) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-cloud-upload"></i> PDF</a>
        @endif
    </div>
</div>

<nav class="prog-path" aria-label="En esta página">
    <a class="prog-chip" href="#partitura">Partitura</a>
    <a class="prog-chip" href="#material">Material</a>
    <a class="prog-chip" href="#comunidad">Comunidad</a>
    @if(filled($programaRitmo->contenido) || collect($secciones)->contains(fn ($s) => filled($s['titulo'] ?? null) || filled($s['contenido'] ?? null)))
        <a class="prog-chip" href="#textos">Textos</a>
    @endif
</nav>

<div id="partitura">
    @include('programa.partials.partitura-score-show', ['programaRitmo' => $programaRitmo, 'medios' => $medios ?? []])
    @unless($tieneScore)
        <div class="prog-score-empty">
            <p class="biblio-eyebrow mb-1">Partitura</p>
            <h2 class="h5 mb-2">Todavía no hay partitura interactiva</h2>
            <p class="text-muted small mb-3">Podés ver el PDF del cuadernillo más abajo, o sumar un video en la biblioteca.</p>
            <div class="prog-cta-row">
                @if($tienePdf)
                    <a href="#material" class="btn btn-sm btn-primary">Ver PDF</a>
                @endif
                <a href="{{ route('biblioteca.create', ['toque' => $programaRitmo->slug]) }}" class="btn btn-sm btn-outline-secondary">Subir material</a>
                <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-sm btn-warning">Crear partitura</a>
            </div>
        </div>
    @endunless
</div>

@if($objetivosAnio)
<div class="card mb-3 border-secondary">
    <div class="card-header py-2">
        <strong class="small">Objetivos del {{ $añoLabel }}</strong>
    </div>
    <div class="card-body programa-contenido small">
        {!! $objetivosAnio->cuerpo !!}
    </div>
</div>
@endif

<div id="material">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <span class="small text-muted mb-0">Videos, fotos, ensayos y apuntes de clase</span>
        <a href="{{ route('programa.toque.edit', $programaRitmo) }}" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-pencil"></i> Editar material
        </a>
    </div>
    @include('programa.partials.medios-show', ['programaRitmo' => $programaRitmo, 'medios' => $medios ?? []])
</div>

@php $bibliotecaItems = $bibliotecaItems ?? collect(); @endphp
<section id="comunidad" class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong class="small mb-0">Material de la comunidad</strong>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('programa.toque.edit', $programaRitmo) }}" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-pencil"></i> Editar ficha
            </a>
            <a href="{{ route('biblioteca.create', ['toque' => $programaRitmo->slug]) }}" class="btn btn-sm btn-primary">
                <i class="bi bi-cloud-upload"></i> Subir para este toque
            </a>
            <a href="{{ route('biblioteca.index', ['toque' => $programaRitmo->slug]) }}" class="btn btn-sm btn-outline-secondary">
                Ver en biblioteca
            </a>
        </div>
    </div>
    <div class="card-body">
        @if($bibliotecaItems->isEmpty())
            <p class="text-muted small mb-0">Todavía no hay aportes asociados a <strong>{{ $programaRitmo->nombre }}</strong>. Subí un video, audio o foto y asociá el toque.</p>
        @else
            <div class="biblio-toque-grid">
                @foreach($bibliotecaItems as $item)
                    @include('biblioteca.partials.card', ['item' => $item])
                @endforeach
            </div>
            @if($bibliotecaItems->count() >= 24)
                <div class="mt-3">
                    <a href="{{ route('biblioteca.index', ['toque' => $programaRitmo->slug]) }}" class="small">Ver todos los aportes de este toque →</a>
                </div>
            @endif
        @endif
    </div>
</section>

<div id="textos">
@if(filled($programaRitmo->contenido))
<div class="card mb-3">
    <div class="card-body programa-contenido">
        {!! nl2br(e($programaRitmo->contenido)) !!}
    </div>
</div>
@endif

@foreach($secciones as $i => $sec)
@if(filled($sec['titulo'] ?? null) || filled($sec['contenido'] ?? null))
<section class="card mb-3 programa-seccion-profund">
    <div class="card-header">
        <h3 class="h6 mb-0">{{ $sec['titulo'] ?: 'Sección '.($i + 1) }}</h3>
    </div>
    <div class="card-body programa-contenido">
        @if(filled($sec['contenido'] ?? null))
            {!! nl2br(e($sec['contenido'])) !!}
        @else
            <p class="text-muted mb-0 small">Sin contenido aún. @if($esAdmin)<a href="{{ route('programa.toque.edit', $programaRitmo) }}">Agregar material</a>@endif</p>
        @endif
    </div>
</section>
@endif
@endforeach

@if(count($enlaces) > 0)
<div class="card mb-3">
    <div class="card-header"><strong class="small">Enlaces y material</strong></div>
    <ul class="list-group list-group-flush">
        @foreach($enlaces as $enlace)
        <li class="list-group-item">
            <a href="{{ $enlace['url'] }}" target="_blank" rel="noopener noreferrer">
                <i class="bi bi-box-arrow-up-right"></i> {{ $enlace['etiqueta'] ?: $enlace['url'] }}
            </a>
        </li>
        @endforeach
    </ul>
</div>
@endif
</div>

<div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
    @if($anterior && $anterior->slug)
    <a href="{{ route('programa.toque.show', $anterior) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i> {{ $anterior->nombre }}</a>
    @else
    <span></span>
    @endif
    <a href="{{ route('programa.partituras.index') }}" class="btn btn-outline-secondary btn-sm">Todas las partituras</a>
    @if($siguiente && $siguiente->slug)
    <a href="{{ route('programa.toque.show', $siguiente) }}" class="btn btn-outline-secondary btn-sm">{{ $siguiente->nombre }} <i class="bi bi-chevron-right"></i></a>
    @endif
</div>
@endsection

@if(($bibliotecaItems ?? collect())->isNotEmpty())
@include('biblioteca.partials.modal')
@push('scripts')
<script src="{{ asset('js/biblioteca-modal.js') }}?v=3"></script>
<script src="{{ asset('js/biblioteca-share.js') }}?v=1"></script>
@endpush
@endif

@push('vite')
@vite(['resources/js/partitura.js'])
@endpush
