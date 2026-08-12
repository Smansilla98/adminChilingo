@extends('layouts.publico')

@section('title', 'Explorar biblioteca')
@section('publico-brand', 'Biblioteca')

@section('content')
@php
    $años = \App\Models\ProgramaRitmo::años();
    $hayFiltro = $q !== '' || $tipo !== '' || $tag || $toqueSlug !== '' || $instrumento !== '';
@endphp
<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Biblioteca dinámica</p>
        <h1>Material compartido</h1>
        <p class="biblio-lead">Fotos, videos, audios y PDFs asociados a toques del programa. Filtrá por toque, instrumento o #hashtag.</p>
    </div>
    <a href="{{ route('biblioteca.create', array_filter(['toque' => $toqueSlug ?: null, 'instrumento' => $instrumento ?: null])) }}" class="btn btn-primary">
        <i class="bi bi-cloud-upload"></i> Subir material
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="GET" action="{{ route('biblioteca.index') }}" class="biblio-search" role="search">
    <div class="biblio-search-field">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por título, toque o #tag…" aria-label="Buscar">
    </div>
    <select name="toque" class="form-select" aria-label="Toque">
        <option value="">Todos los toques</option>
        @foreach($toques->groupBy('año') as $anio => $grupo)
            <optgroup label="{{ $años[$anio] ?? ($anio.'° Año') }}">
                @foreach($grupo as $t)
                    <option value="{{ $t->slug }}" @selected($toqueSlug === $t->slug)>{{ $t->nombre }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <select name="instrumento" class="form-select" aria-label="Instrumento">
        <option value="">Todo instrumento</option>
        @foreach($instrumentos as $clave => $etiqueta)
            <option value="{{ $clave }}" @selected($instrumento === $clave)>{{ $etiqueta }}</option>
        @endforeach
    </select>
    <select name="tipo" class="form-select" aria-label="Tipo de contenido">
        <option value="">Todos los tipos</option>
        @foreach(\App\Models\BibliotecaItem::TIPOS as $valor => $etiqueta)
            <option value="{{ $valor }}" @selected($tipo === $valor)>{{ $etiqueta }}</option>
        @endforeach
    </select>
    @if($tag)
        <input type="hidden" name="tag" value="{{ $tag->slug }}">
    @endif
    <button type="submit" class="btn btn-secondary">Buscar</button>
    @if($hayFiltro)
        <a href="{{ route('biblioteca.index') }}" class="btn btn-link">Limpiar</a>
    @endif
</form>

@if($toqueFiltro)
<div class="biblio-filter-chip">
    <span>Toque: <strong>{{ $toqueFiltro->nombre }}</strong>
        @if($instrumento)
            · {{ \App\Models\BibliotecaItem::etiquetaInstrumento($instrumento) }}
        @endif
    </span>
    <a href="{{ route('programa.toque.show', $toqueFiltro) }}">Ver ficha del toque</a>
    <a href="{{ route('biblioteca.create', ['toque' => $toqueFiltro->slug, 'instrumento' => $instrumento ?: null]) }}">Subir para este toque</a>
</div>
@endif

@if($tagsPopulares->isNotEmpty())
<div class="biblio-tags" aria-label="Hashtags populares">
    @foreach($tagsPopulares as $t)
        <a href="{{ route('biblioteca.index', array_filter(['tag' => $t->slug, 'toque' => $toqueSlug ?: null, 'instrumento' => $instrumento ?: null, 'tipo' => $tipo ?: null])) }}"
           class="biblio-tag {{ ($tag && $tag->id === $t->id) ? 'is-active' : '' }}">
            #{{ $t->nombre }}
        </a>
    @endforeach
</div>
@endif

@if(!empty($sinTabla))
    <div class="alert alert-warning">Falta migrar las tablas de la biblioteca.</div>
@elseif($items->isEmpty())
    <div class="biblio-empty">
        <i class="bi bi-images" aria-hidden="true"></i>
        <p>No hay material todavía{{ $hayFiltro ? ' con ese filtro' : '' }}.</p>
        <a href="{{ route('biblioteca.create', array_filter(['toque' => $toqueSlug ?: null, 'instrumento' => $instrumento ?: null])) }}" class="btn btn-primary btn-sm">Sé el primero en subir</a>
    </div>
@else
    <div class="biblio-masonry">
        @foreach($items as $item)
            @include('biblioteca.partials.card', ['item' => $item])
        @endforeach
    </div>
    <div class="biblio-pager">
        {{ $items->links() }}
    </div>
@endif

@include('biblioteca.partials.modal')
@endsection

@push('scripts')
<script src="{{ asset('js/biblioteca-modal.js') }}?v=2"></script>
@endpush
