@extends('layouts.biblioteca')

@section('title', 'Explorar biblioteca')

@section('content')
<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Biblioteca dinámica</p>
        <h1>Material compartido</h1>
        <p class="biblio-lead">Fotos, videos, audios, PDFs y enlaces. Filtrá por texto o por #hashtag.</p>
    </div>
    <a href="{{ route('biblioteca.create') }}" class="btn btn-primary">
        <i class="bi bi-cloud-upload"></i> Subir material
    </a>
</div>

<form method="GET" action="{{ route('biblioteca.index') }}" class="biblio-search" role="search">
    <div class="biblio-search-field">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" name="q" value="{{ $q }}" placeholder="Buscar por título, descripción o #tag…" aria-label="Buscar">
    </div>
    <select name="tipo" class="form-select" aria-label="Tipo de contenido">
        <option value="">Todos los tipos</option>
        @foreach(\App\Models\BibliotecaItem::TIPOS as $valor => $etiqueta)
            <option value="{{ $valor }}" @selected($tipo === $valor)>{{ $etiqueta }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary">Buscar</button>
    @if($q !== '' || $tipo !== '' || $tag)
        <a href="{{ route('biblioteca.index') }}" class="btn btn-link">Limpiar</a>
    @endif
</form>

@if($tagsPopulares->isNotEmpty())
<div class="biblio-tags" aria-label="Hashtags populares">
    @foreach($tagsPopulares as $t)
        <a href="{{ route('biblioteca.index', ['tag' => $t->slug]) }}"
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
        <p>No hay material todavía{{ $q || $tag || $tipo ? ' con ese filtro' : '' }}.</p>
        <a href="{{ route('biblioteca.create') }}" class="btn btn-primary btn-sm">Sé el primero en subir</a>
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
@endsection
