@extends('layouts.publico')

@section('title', $disco->titulo.' — Discografía')
@section('publico-brand', 'Programa')

@section('content')
@php
    $temas = $disco->listaDeTemas();
    $total = $disco->duracionTotal();
    $esAdmin = auth()->user()?->isAdmin();
@endphp

<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}">Programa</a></li>
        <li class="breadcrumb-item"><a href="{{ route('programa.discos.index') }}">Discografía</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $disco->titulo }}</li>
    </ol>
</nav>

@if(! $disco->publicado)
    <div class="alert alert-warning">Este disco no está publicado: solo lo ve administración.</div>
@endif

<article class="disco-ficha mb-4">
    @include('programa.discografia.partials.tapa', ['disco' => $disco])

    <div>
        <p class="biblio-eyebrow">La Chilinga · {{ $disco->anio }}</p>
        <h1 class="h2 mb-1" style="font-family: var(--font-display); font-weight: 800; letter-spacing: -0.03em;">{{ $disco->titulo }}</h1>
        @if($disco->titulo_alternativo)
            <p class="small text-muted mb-1">También aparece como <em>{{ $disco->titulo_alternativo }}</em>.</p>
        @endif
        @if($disco->nota_anio)
            <p class="small text-muted mb-1">{{ $disco->nota_anio }}</p>
        @endif

        @if(! empty($disco->datos) || count($temas) || $total)
        <ul class="disco-datos">
            @foreach($disco->datos ?? [] as $dato)
                <li class="prog-pill">{{ $dato }}</li>
            @endforeach
            @if($total)<li class="prog-pill">{{ $total }} en total</li>@endif
            @if(count($enPrograma))<li class="prog-pill prog-pill--ok">{{ count($enPrograma) }} {{ count($enPrograma) === 1 ? 'toque' : 'toques' }} del programa</li>@endif
        </ul>
        @endif

        @if($disco->descripcion)
            <p>{{ $disco->descripcion }}</p>
        @endif

        <div class="disco-escuchar">
            @foreach($disco->dondeEscuchar() as $e)
                <a href="{{ $e['url'] }}" target="_blank" rel="noopener" class="btn btn-sm {{ $e['busqueda'] ? 'btn-outline-secondary' : 'btn-primary' }}">
                    <i class="bi {{ $e['busqueda'] ? 'bi-search' : 'bi-play-circle' }}" aria-hidden="true"></i> {{ $e['etiqueta'] }}
                </a>
            @endforeach
            @if($esAdmin)
                <a href="{{ route('programa.discos.edit', $disco) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
            @endif
        </div>
    </div>
</article>

<section class="ito-card p-3 p-md-4 mb-4" aria-labelledby="temas-titulo">
    <h2 id="temas-titulo" class="h5 mb-3">Temas</h2>
    @if($disco->nota_temas)
        <p class="small text-muted"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ $disco->nota_temas }}</p>
    @endif
    @if(count($temas))
        <ol class="disco-temas">
            @foreach($temas as $i => $tema)
                <li>
                    <span>
                        {{ $tema['titulo'] }}
                        @isset($enPrograma[$i])
                            <a class="disco-temas__toque prog-pill prog-pill--ok" href="{{ route('programa.toque.show', $enPrograma[$i]) }}">
                                Toque del programa: {{ $enPrograma[$i]->nombre }}
                            </a>
                        @endisset
                    </span>
                    <span class="disco-temas__dur">{{ $tema['duracion'] ?? '' }}</span>
                </li>
            @endforeach
        </ol>
    @else
        <p class="text-muted mb-0">Todavía no está cargada la lista de temas.</p>
    @endif
</section>

@if(! empty($disco->fuentes))
<section class="small text-muted mb-4" aria-labelledby="fuentes-titulo">
    <h2 id="fuentes-titulo" class="h6">Fuentes</h2>
    <ul class="mb-0">
        @foreach($disco->fuentes as $f)
            <li><a href="{{ $f['url'] }}" target="_blank" rel="noopener">{{ $f['etiqueta'] }}</a></li>
        @endforeach
    </ul>
</section>
@endif

<nav class="d-flex justify-content-between gap-2" aria-label="Otros discos">
    @if($anterior)
        <a href="{{ route('programa.discos.show', $anterior) }}" class="btn btn-outline-secondary btn-sm">← {{ $anterior->titulo }} ({{ $anterior->anio }})</a>
    @else <span></span> @endif
    @if($siguiente)
        <a href="{{ route('programa.discos.show', $siguiente) }}" class="btn btn-outline-secondary btn-sm">{{ $siguiente->titulo }} ({{ $siguiente->anio }}) →</a>
    @endif
</nav>
@endsection
