@extends('layouts.app')

@section('title', 'Editar '.$disco->titulo)
@section('page-title', 'Discografía — '.$disco->titulo)

@php
    $temasTexto = collect($disco->listaDeTemas())->map(fn ($t) => $t['titulo'].(filled($t['duracion'] ?? null) ? ' — '.$t['duracion'] : ''))->join("\n");
    $lineasUrl = fn ($lista) => collect($lista ?? [])->map(fn ($e) => ($e['etiqueta'] ?? '').' | '.($e['url'] ?? ''))->join("\n");
@endphp

@section('content')
<div class="ito-page">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Editar disco</h1>
            <p class="ito-page-sub">{{ $disco->titulo }} ({{ $disco->anio }})</p>
        </div>
        <a href="{{ route('programa.discos.show', $disco) }}" class="btn btn-outline-secondary"><i class="bi bi-eye"></i> Ver la ficha</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('programa.discos.update', $disco) }}" enctype="multipart/form-data" class="ito-card p-3 p-md-4">
        @csrf
        @method('PUT')
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="d-titulo">Título</label>
                <input id="d-titulo" name="titulo" class="form-control" required maxlength="255" value="{{ old('titulo', $disco->titulo) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="d-alt">También conocido como</label>
                <input id="d-alt" name="titulo_alternativo" class="form-control" maxlength="255" value="{{ old('titulo_alternativo', $disco->titulo_alternativo) }}" placeholder="Opcional">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="d-anio">Año</label>
                <input id="d-anio" name="anio" type="number" class="form-control" required min="1995" max="{{ now()->year + 1 }}" value="{{ old('anio', $disco->anio) }}">
            </div>
            <div class="col-md-7">
                <label class="form-label" for="d-nota-anio">Aclaración del año</label>
                <input id="d-nota-anio" name="nota_anio" class="form-control" maxlength="255" value="{{ old('nota_anio', $disco->nota_anio) }}" placeholder="Ej. algunas ediciones figuran como de 2003">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="d-color">Color de la tapa</label>
                <input id="d-color" name="color" type="color" class="form-control form-control-color w-100" value="{{ old('color', $disco->color) }}">
                <div class="form-text">Se usa si no hay portada.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="d-desc">Descripción</label>
                <textarea id="d-desc" name="descripcion" class="form-control" rows="3" maxlength="5000">{{ old('descripcion', $disco->descripcion) }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="d-temas">Temas</label>
                <textarea id="d-temas" name="temas" class="form-control font-monospace" rows="14" aria-describedby="d-temas-ayuda">{{ old('temas', $temasTexto) }}</textarea>
                <div id="d-temas-ayuda" class="form-text">Un tema por línea, en orden. La duración es opcional: «Sacateca — 1:53».</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="d-datos">Datos destacados</label>
                <textarea id="d-datos" name="datos" class="form-control" rows="4" aria-describedby="d-datos-ayuda">{{ old('datos', implode("\n", $disco->datos ?? [])) }}</textarea>
                <div id="d-datos-ayuda" class="form-text">Uno por línea. Ej.: «Grabado con cerca de 200 alumnos».</div>

                <label class="form-label mt-3" for="d-nota-temas">Aclaración sobre los temas</label>
                <input id="d-nota-temas" name="nota_temas" class="form-control" maxlength="255" value="{{ old('nota_temas', $disco->nota_temas) }}" placeholder="Ej. faltan las duraciones">

                <label class="form-label mt-3" for="d-enlaces">Dónde escucharlo</label>
                <textarea id="d-enlaces" name="enlaces" class="form-control" rows="3" aria-describedby="d-enlaces-ayuda">{{ old('enlaces', $lineasUrl($disco->enlaces)) }}</textarea>
                <div id="d-enlaces-ayuda" class="form-text">«Spotify | https://…», uno por línea. Si falta Spotify o YouTube, la ficha ofrece buscarlo.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="d-fuentes">Fuentes</label>
                <textarea id="d-fuentes" name="fuentes" class="form-control" rows="3">{{ old('fuentes', $lineasUrl($disco->fuentes)) }}</textarea>
                <div class="form-text">De dónde salen los datos: «Wikipedia | https://…».</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="d-portada">Portada</label>
                <input id="d-portada" name="portada" type="file" class="form-control" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG o WebP, hasta 4 MB. Cuadrada se ve mejor.</div>
                @if($disco->portada_path)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="quitar_portada" value="1" id="d-quitar">
                        <label class="form-check-label" for="d-quitar">Quitar la portada actual</label>
                    </div>
                @endif
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="publicado" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="publicado" value="1" id="d-publicado" @checked(old('publicado', $disco->publicado))>
                    <label class="form-check-label" for="d-publicado">Publicado (visible en la discografía)</label>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('programa.discos.show', $disco) }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </form>
</div>
@endsection
