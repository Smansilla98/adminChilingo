@extends('layouts.app')

@section('title', ($capitulo->exists ? $capitulo->titulo : 'Nuevo capítulo').' — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow"><a href="{{ route('archivo.gestion.capitulos.index') }}">Capítulos</a></p>
            <h1 class="ito-page-title">{{ $capitulo->exists ? $capitulo->titulo : 'Nuevo capítulo' }}</h1>
        </div>
        @if($capitulo->exists && $capitulo->publicado)
            <div class="ito-page-actions"><a class="btn btn-secondary btn-sm" href="{{ $capitulo->url() }}" target="_blank" rel="noopener">Ver en el archivo <i class="bi bi-box-arrow-up-right"></i></a></div>
        @endif
    </div>
    @include('archivo.gestion._nav')

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ $capitulo->exists ? route('archivo.gestion.capitulos.update', $capitulo) : route('archivo.gestion.capitulos.store') }}" class="ito-form">
                @csrf
                @if($capitulo->exists) @method('PUT') @endif
                <x-ito.form-section title="Capítulo" icon="bi-book">
                    <div class="mb-3">
                        <label class="form-label" for="titulo">Título</label>
                        <input class="form-control form-control-lg" id="titulo" name="titulo" required maxlength="180" value="{{ old('titulo', $capitulo->titulo) }}" placeholder="Los primeros años">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bajada">Bajada</label>
                        <input class="form-control" id="bajada" name="bajada" maxlength="300" value="{{ old('bajada', $capitulo->bajada) }}" placeholder="Una frase que abra el capítulo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="descripcion">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="6" maxlength="8000">{{ old('descripcion', $capitulo->descripcion) }}</textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="anio_desde">Año inicial</label>
                            <input class="form-control" id="anio_desde" name="anio_desde" type="number" min="1900" max="{{ now()->year }}" value="{{ old('anio_desde', $capitulo->anio_desde) }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="anio_hasta">Año final</label>
                            <input class="form-control" id="anio_hasta" name="anio_hasta" type="number" min="1900" max="{{ now()->year }}" value="{{ old('anio_hasta', $capitulo->anio_hasta) }}">
                        </div>
                    </div>
                </x-ito.form-section>

                <x-ito.form-section title="Imagen de portada" icon="bi-image" help="{{ $fotosCandidatas->isEmpty() ? 'Cuando el capítulo tenga fotos, vas a poder elegir una acá.' : 'Elegí una de las fotos del capítulo.' }}">
                    <div class="agx-portadas" role="radiogroup" aria-label="Portada">
                        <label class="agx-portada"><input type="radio" name="portada_foto_id" value="" @checked(! $capitulo->portada_foto_id)><span class="agx-portada__sin">Sin portada</span></label>
                        @foreach($fotosCandidatas as $f)
                            <label class="agx-portada"><input type="radio" name="portada_foto_id" value="{{ $f->id }}" @checked((int) $capitulo->portada_foto_id === $f->id)><img src="{{ $f->imagenUrl(400) }}" alt="{{ $f->textoAlternativo() }}" loading="lazy"></label>
                        @endforeach
                    </div>
                </x-ito.form-section>

                <div class="ito-form-actions">
                    <div class="form-check me-auto">
                        <input class="form-check-input" type="checkbox" id="publicado" name="publicado" value="1" @checked(old('publicado', $capitulo->publicado))>
                        <label class="form-check-label" for="publicado">Publicado</label>
                    </div>
                    @if($capitulo->exists)
                        @can('delete', $capitulo)<button type="submit" form="form-eliminar" class="btn btn-outline-danger">Eliminar</button>@endcan
                    @endif
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
            @if($capitulo->exists)
                <form method="POST" action="{{ route('archivo.gestion.capitulos.destroy', $capitulo) }}" id="form-eliminar" data-confirmar="¿Eliminar el capítulo? Sus acontecimientos y fotos quedan sin capítulo.">@csrf @method('DELETE')</form>
            @endif
        </div>

        @if($capitulo->exists)
            <div class="col-lg-5">
                <section class="ito-card ito-card--body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="agx-h2 mb-0">Acontecimientos</h2>
                        <a class="btn btn-sm btn-secondary" href="{{ route('archivo.gestion.eventos.create', ['capitulo' => $capitulo->id]) }}"><i class="bi bi-plus-lg"></i> Agregar</a>
                    </div>
                    @if($capitulo->acontecimientos->isEmpty())
                        <p class="text-muted small mb-0">Todavía no tiene acontecimientos.</p>
                    @else
                        <ol class="agx-lista agx-lista--compacta" data-ordenable data-url="{{ route('archivo.gestion.eventos.orden') }}">
                            @foreach($capitulo->acontecimientos as $a)
                                <li class="agx-lista__item" data-id="{{ $a->id }}" draggable="true" tabindex="0" aria-label="{{ $a->titulo }}. Usá las flechas para moverlo.">
                                    <span class="agx-lista__asa" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                                    <span class="agx-lista__texto">
                                        <a href="{{ route('archivo.gestion.eventos.edit', $a) }}">{{ $a->anio }} · {{ $a->titulo }}</a>
                                        <span class="text-muted small">{{ $a->fotos_count }} fotos · {{ $a->publicado ? 'publicado' : 'borrador' }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                        <p class="text-muted small mt-2 mb-0">Dentro del mismo año, este orden define cuál va primero.</p>
                    @endif
                </section>
                <p class="mt-3"><a href="{{ route('archivo.gestion.fotos', ['capitulo' => $capitulo->id]) }}"><i class="bi bi-images"></i> Ver y ordenar las fotos del capítulo</a></p>
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-gestion.js') }}?v=1" defer></script>
@endpush
