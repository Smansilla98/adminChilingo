@extends('layouts.publico')

@section('title', 'Toques y recursos')
@section('publico-brand', 'Toques y recursos')

@section('content')
<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Mirá · escuchá · practicá</p>
        <h1>Toques y recursos</h1>
        <p class="biblio-lead">Los toques del programa que tienen videos por instrumento, partitura o grabación de referencia. Filtrá por año o por tipo de recurso.</p>
    </div>
    <div class="prog-hero-actions">
        <a href="{{ route('programa.index') }}" class="btn btn-outline-secondary">Programa</a>
    </div>
</div>

@if($items->isEmpty())
    <p class="text-muted">Todavía no hay toques con recursos publicados.</p>
@else
<section class="mt-3" data-recursos-filtro aria-label="Filtros">
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
        <div>
            <label class="form-label small" for="recursos-anio">Año</label>
            <select id="recursos-anio" class="form-select form-select-sm" data-filtro="anio">
                <option value="">Todos los años</option>
                @foreach($anios as $anio)
                    <option value="{{ $anio }}">{{ $anio }}° año</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label small" for="recursos-tipo">Recurso</label>
            <select id="recursos-tipo" class="form-select form-select-sm" data-filtro="tipo">
                <option value="">Todos</option>
                <option value="videos">Videos por instrumento</option>
                <option value="partitura">Partitura</option>
                <option value="audio">Grabación de referencia</option>
            </select>
        </div>
        <p class="small text-muted mb-0" data-filtro-cuenta aria-live="polite">{{ $items->count() }} toques</p>
    </div>

    <div class="row g-3" data-recursos-lista>
        @foreach($items as $it)
        <div class="col-12 col-sm-6 col-lg-4" data-recurso data-anio="{{ $it['anio'] }}" data-videos="{{ $it['videos'] }}" data-partitura="{{ $it['partitura'] ? 1 : 0 }}" data-audio="{{ $it['audio'] ? 1 : 0 }}">
            <article class="card h-100 shadow-sm">
                <div class="card-body">
                    <p class="small text-muted mb-1">{{ $it['anio'] }}° año · orden {{ $it['orden'] }}</p>
                    <h2 class="h5 card-title">{{ $it['nombre'] }}</h2>
                    <ul class="list-unstyled small mb-3">
                        <li>{{ $it['videos'] > 0 ? $it['videos'].' '.($it['videos'] === 1 ? 'video' : 'videos') : 'Sin videos' }}</li>
                        <li>{{ $it['partitura'] ? 'Con partitura' : 'Sin partitura' }}</li>
                        <li>{{ $it['audio'] ? 'Con grabación de referencia' : 'Sin grabación' }}</li>
                    </ul>
                    <a class="btn btn-sm btn-primary" href="{{ url('/programa/recursos/'.$it['slug']) }}">Abrir</a>
                </div>
            </article>
        </div>
        @endforeach
    </div>
    <p class="text-muted mt-3" data-filtro-vacio hidden>No hay toques con ese filtro.</p>
</section>
@endif
@endsection
