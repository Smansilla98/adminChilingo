@extends('layouts.app')

@section('title', $programaRitmo->nombre . ' — Programa')
@section('page-title', $programaRitmo->nombre)

@section('content')
@php
    $añoLabel = $años[$programaRitmo->año] ?? $programaRitmo->año.'° Año';
    $secciones = $programaRitmo->seccionesProfundizacion();
    $enlaces = is_array($programaRitmo->enlaces) ? $programaRitmo->enlaces : [];
@endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}">Programa</a></li>
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}#toques-por-anio">{{ $añoLabel }}</a></li>
        <li class="breadcrumb-item active">{{ $programaRitmo->nombre }}</li>
    </ol>
</nav>

<div class="card programa-toque-header mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
                <span class="badge bg-warning text-dark mb-2">{{ $añoLabel }} · Toque {{ $programaRitmo->orden }}</span>
                <h2 class="h4 mb-1">{{ $programaRitmo->nombre }}</h2>
                @if($programaRitmo->autor)
                <p class="text-muted mb-0">{{ $programaRitmo->autor }}</p>
                @endif
                @if($programaRitmo->opcional && $programaRitmo->notas)
                <p class="small text-muted mb-0 mt-1"><i class="bi bi-info-circle"></i> {{ $programaRitmo->notas }}</p>
                @elseif($programaRitmo->opcional)
                <span class="badge bg-secondary mt-1">Opcional</span>
                @endif
            </div>
            @if(auth()->user()?->isAdmin())
            <a href="{{ route('programa.toque.partitura.edit', $programaRitmo) }}" class="btn btn-sm btn-warning me-1"><i class="bi bi-cloud-upload"></i> Cambiar PDF</a>
            <a href="{{ route('programa.toque.edit', $programaRitmo) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Editar página</a>
            @endif
        </div>
        @if($programaRitmo->resumen)
        <p class="lead mt-3 mb-0">{{ $programaRitmo->resumen }}</p>
        @endif
    </div>
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

@if(filled($programaRitmo->contenido))
<div class="card mb-3">
    <div class="card-body programa-contenido">
        {!! nl2br(e($programaRitmo->contenido)) !!}
    </div>
</div>
@endif

@include('programa.partials.medios-show', ['programaRitmo' => $programaRitmo, 'medios' => $medios ?? []])

{{-- Material de la comunidad (biblioteca pública asociado a este toque) --}}
@php $bibliotecaItems = $bibliotecaItems ?? collect(); @endphp
<section class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong class="small mb-0">Material de la comunidad</strong>
        <div class="d-flex flex-wrap gap-2">
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
            <p class="text-muted small mb-0">Todavía no hay aportes asociados a <strong>{{ $programaRitmo->nombre }}</strong>. Podés subir un video, audio o foto y marcarlo con el toque (y el instrumento, si aplica).</p>
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
            <p class="text-muted mb-0 small">Sin contenido aún. @if(auth()->user()?->isAdmin())<a href="{{ route('programa.toque.edit', $programaRitmo) }}">Agregar material</a>@endif</p>
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

@if(!$programaRitmo->tieneProfundizacion())
<div class="alert alert-secondary">
    <p class="mb-0">Esta página del toque todavía no tiene contenido cargado.
    @if(auth()->user()?->isAdmin())
    <a href="{{ route('programa.toque.edit', $programaRitmo) }}">Cargar contenido</a> (solo administración).
    @else
    Consultá con tu docente o la coordinación.
    @endif
    </p>
</div>
@endif

<div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
    @if($anterior && $anterior->slug)
    <a href="{{ route('programa.toque.show', $anterior) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-chevron-left"></i> {{ $anterior->nombre }}</a>
    @else
    <span></span>
    @endif
    <a href="{{ route('programa.index') }}#toques-por-anio" class="btn btn-outline-secondary btn-sm">Volver al programa</a>
    @if($siguiente && $siguiente->slug)
    <a href="{{ route('programa.toque.show', $siguiente) }}" class="btn btn-outline-secondary btn-sm">{{ $siguiente->nombre }} <i class="bi bi-chevron-right"></i></a>
    @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/programa.css') }}?v=3">
<link rel="stylesheet" href="{{ asset('css/biblioteca.css') }}?v=3">
@endpush

@if(($bibliotecaItems ?? collect())->isNotEmpty())
@include('biblioteca.partials.modal')
@push('scripts')
<script src="{{ asset('js/biblioteca-modal.js') }}?v=2"></script>
@endpush
@endif

@push('vite')
@vite(['resources/js/programa-partitura.js'])
@endpush
