@extends('layouts.app')

@section('title', ($a->exists ? $a->titulo : 'Nuevo acontecimiento').' — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow"><a href="{{ route('archivo.gestion.eventos.index') }}">Acontecimientos</a></p>
            <h1 class="ito-page-title">{{ $a->exists ? $a->titulo : 'Nuevo acontecimiento' }}</h1>
        </div>
        @if($a->exists && $a->publicado)
            <div class="ito-page-actions"><a class="btn btn-secondary btn-sm" href="{{ $a->url() }}" target="_blank" rel="noopener">Ver en el archivo <i class="bi bi-box-arrow-up-right"></i></a></div>
        @endif
    </div>
    @include('archivo.gestion._nav')

    <form method="POST" action="{{ $a->exists ? route('archivo.gestion.eventos.update', $a) : route('archivo.gestion.eventos.store') }}" class="ito-form">
        @csrf
        @if($a->exists) @method('PUT') @endif
        <div class="row g-4">
            <div class="col-lg-7">
                <x-ito.form-section title="Acontecimiento" icon="bi-calendar2-event">
                    <div class="mb-3">
                        <label class="form-label" for="titulo">Título</label>
                        <input class="form-control form-control-lg" id="titulo" name="titulo" required maxlength="180" value="{{ old('titulo', $a->titulo) }}" placeholder="Primeros ensayos">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-4">
                            <label class="form-label" for="anio">Año</label>
                            <input class="form-control" id="anio" name="anio" type="number" min="1900" max="{{ now()->year }}" value="{{ old('anio', $a->anio) }}">
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="precision">Precisión</label>
                            <select class="form-select" id="precision" name="precision">
                                @foreach($precisiones as $k => $v)<option value="{{ $k }}" @selected(old('precision', $a->precision ?? 'anio') === $k)>{{ $v }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="fecha">Fecha</label>
                            <input class="form-control" id="fecha" name="fecha" type="date" value="{{ old('fecha', $a->fecha?->toDateString()) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="bajada">Bajada</label>
                        <input class="form-control" id="bajada" name="bajada" maxlength="300" value="{{ old('bajada', $a->bajada) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="descripcion">Descripción breve</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="3000">{{ old('descripcion', $a->descripcion) }}</textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="relato">Relato</label>
                        <textarea class="form-control" id="relato" name="relato" rows="10" maxlength="30000" placeholder="La historia completa: qué pasó, quiénes estuvieron, por qué importa.">{{ old('relato', $a->relato) }}</textarea>
                    </div>
                </x-ito.form-section>

                <x-ito.form-section title="Dónde" icon="bi-geo-alt" help="Lugar, ciudad y coordenadas preparan el futuro mapa histórico.">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="lugar">Lugar</label><input class="form-control" id="lugar" name="lugar" maxlength="180" value="{{ old('lugar', $a->lugar) }}"></div>
                        <div class="col-md-3"><label class="form-label" for="ciudad">Ciudad</label><input class="form-control" id="ciudad" name="ciudad" maxlength="120" value="{{ old('ciudad', $a->ciudad) }}"></div>
                        <div class="col-md-3"><label class="form-label" for="pais">País</label><input class="form-control" id="pais" name="pais" maxlength="80" value="{{ old('pais', $a->pais) }}"></div>
                        <div class="col-6 col-md-3"><label class="form-label" for="latitud">Latitud</label><input class="form-control" id="latitud" name="latitud" inputmode="decimal" value="{{ old('latitud', $a->latitud) }}"></div>
                        <div class="col-6 col-md-3"><label class="form-label" for="longitud">Longitud</label><input class="form-control" id="longitud" name="longitud" inputmode="decimal" value="{{ old('longitud', $a->longitud) }}"></div>
                        <div class="col-md-6"><label class="form-label" for="sede_id">Sede</label>
                            <select class="form-select" id="sede_id" name="sede_id"><option value="">—</option>@foreach($sedes as $s)<option value="{{ $s->id }}" @selected((string) old('sede_id', $a->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>@endforeach</select>
                        </div>
                    </div>
                </x-ito.form-section>
            </div>

            <div class="col-lg-5">
                <x-ito.form-section title="Dentro de la historia" icon="bi-diagram-3">
                    <div class="mb-3">
                        <label class="form-label" for="capitulo_id">Capítulo</label>
                        <select class="form-select" id="capitulo_id" name="capitulo_id"><option value="">—</option>@foreach($capitulos as $c)<option value="{{ $c->id }}" @selected((string) old('capitulo_id', $a->capitulo_id) === (string) $c->id)>{{ $c->titulo }}</option>@endforeach</select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="evento_id">Evento de la agenda (opcional)</label>
                        <select class="form-select" id="evento_id" name="evento_id"><option value="">—</option>@foreach($eventos as $e)<option value="{{ $e->id }}" @selected((string) old('evento_id', $a->evento_id) === (string) $e->id)>{{ $e->fecha?->format('d/m/Y') }} · {{ $e->titulo }}</option>@endforeach</select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="show_id">Show (opcional)</label>
                        <select class="form-select" id="show_id" name="show_id"><option value="">—</option>@foreach($shows as $s)<option value="{{ $s->id }}" @selected((string) old('show_id', $a->show_id) === (string) $s->id)>{{ $s->fecha?->format('d/m/Y') }} · {{ $s->titulo }}</option>@endforeach</select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="relacionados">Acontecimientos relacionados</label>
                        <select class="form-select" id="relacionados" name="relacionados[]" multiple size="6">
                            @php $rel = collect(old('relacionados', $a->exists ? $a->relacionados->pluck('id')->all() : []))->map('intval'); @endphp
                            @foreach($otros as $o)<option value="{{ $o->id }}" @selected($rel->contains($o->id))>{{ $o->anio }} · {{ $o->titulo }}</option>@endforeach
                        </select>
                        <div class="form-text">Ctrl/Cmd + clic para elegir varios.</div>
                    </div>
                </x-ito.form-section>

                @if($a->exists)
                    <x-ito.form-section title="Fotografías" icon="bi-images" help="Elegí la portada. Arrastrá para ordenar.">
                        @if($fotosAcontecimiento->isEmpty())
                            <p class="text-muted small">Todavía no tiene fotos.</p>
                        @else
                            <ul class="agx-portadas agx-portadas--orden" data-ordenable data-url="{{ route('archivo.gestion.fotos.orden') }}">
                                <li><label class="agx-portada"><input type="radio" name="portada_foto_id" value="" @checked(! $a->portada_foto_id)><span class="agx-portada__sin">Sin portada</span></label></li>
                                @foreach($fotosAcontecimiento as $f)
                                    <li data-id="{{ $f->id }}" draggable="true" tabindex="0" aria-label="{{ $f->tituloVisible() }}. Usá las flechas para moverla.">
                                        <label class="agx-portada"><input type="radio" name="portada_foto_id" value="{{ $f->id }}" @checked((int) $a->portada_foto_id === $f->id)><img src="{{ $f->imagenUrl(400) }}" alt="{{ $f->textoAlternativo() }}" loading="lazy" draggable="false"></label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="d-flex gap-2 mt-2">
                            @can('create', \App\Models\ArchivoFoto::class)<a class="btn btn-sm btn-secondary" href="{{ route('archivo.gestion.subir', ['acontecimiento' => $a->id]) }}"><i class="bi bi-plus-lg"></i> Agregar fotos</a>@endcan
                            <a class="btn btn-sm btn-link" href="{{ route('archivo.gestion.fotos', ['acontecimiento' => $a->id]) }}">Editar en la grilla</a>
                        </div>
                    </x-ito.form-section>
                @endif
            </div>
        </div>

        <div class="ito-form-actions">
            <div class="form-check me-auto">
                <input class="form-check-input" type="checkbox" id="publicado" name="publicado" value="1" @checked(old('publicado', $a->publicado))>
                <label class="form-check-label" for="publicado">Publicado</label>
            </div>
            @if($a->exists)
                @can('delete', $a)<button type="submit" form="form-eliminar" class="btn btn-outline-danger">Eliminar</button>@endcan
            @endif
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
    @if($a->exists)
        <form method="POST" action="{{ route('archivo.gestion.eventos.destroy', $a) }}" id="form-eliminar" data-confirmar="¿Eliminar el acontecimiento? Sus fotos siguen en el archivo.">@csrf @method('DELETE')</form>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-gestion.js') }}?v=1" defer></script>
@endpush
