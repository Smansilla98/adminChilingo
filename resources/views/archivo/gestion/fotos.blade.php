@extends('layouts.app')

@section('title', 'Fotos — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Archivo histórico</p>
            <h1 class="ito-page-title">Fotografías <span class="text-muted fs-5">{{ number_format($fotos->total(), 0, ',', '.') }}</span></h1>
        </div>
        <div class="ito-page-actions">
            @can('create', \App\Models\ArchivoFoto::class)
                <a href="{{ route('archivo.gestion.subir', array_filter(['acontecimiento' => $f['acontecimiento'] ?? null, 'capitulo' => $f['capitulo'] ?? null])) }}" class="btn btn-primary btn-sm"><i class="bi bi-cloud-arrow-up"></i> Subir</a>
            @endcan
        </div>
    </div>
    @include('archivo.gestion._nav')

    <form method="GET" class="agx-filtros" role="search">
        <input type="search" name="q" class="form-control form-control-sm agx-filtros__q" value="{{ $f['q'] ?? '' }}" placeholder="Buscar título, lugar, persona, etiqueta…" aria-label="Buscar">
        <select name="estado" class="form-select form-select-sm" aria-label="Estado">
            <option value="">Todo estado</option>
            @foreach($estados as $k => $v)<option value="{{ $k }}" @selected(($f['estado'] ?? '') === $k)>{{ $v }}</option>@endforeach
        </select>
        <select name="decada" class="form-select form-select-sm" aria-label="Década">
            <option value="">Toda década</option>
            @foreach(range(intdiv(now()->year, 10) * 10, 1960, -10) as $d)<option value="{{ $d }}" @selected((string) ($f['decada'] ?? '') === (string) $d)>{{ $d }}s</option>@endforeach
        </select>
        <select name="capitulo" class="form-select form-select-sm" aria-label="Capítulo">
            <option value="">Todo capítulo</option>
            @foreach($capitulos as $c)<option value="{{ $c->id }}" @selected((string) ($f['capitulo'] ?? '') === (string) $c->id)>{{ $c->titulo }}</option>@endforeach
        </select>
        <select name="acontecimiento" class="form-select form-select-sm" aria-label="Acontecimiento">
            <option value="">Todo acontecimiento</option>
            @foreach($acontecimientos as $a)<option value="{{ $a->id }}" @selected((string) ($f['acontecimiento'] ?? '') === (string) $a->id)>{{ $a->anio }} · {{ $a->titulo }}</option>@endforeach
        </select>
        <select name="sede" class="form-select form-select-sm" aria-label="Sede">
            <option value="">Toda sede</option>
            @foreach($sedes as $s)<option value="{{ $s->id }}" @selected((string) ($f['sede'] ?? '') === (string) $s->id)>{{ $s->nombre }}</option>@endforeach
        </select>
        <select name="sin" class="form-select form-select-sm" aria-label="Datos faltantes">
            <option value="">Con o sin datos</option>
            <option value="fecha" @selected(($f['sin'] ?? '') === 'fecha')>Sin fecha</option>
            <option value="credito" @selected(($f['sin'] ?? '') === 'credito')>Sin crédito</option>
            <option value="descripcion" @selected(($f['sin'] ?? '') === 'descripcion')>Sin descripción</option>
            <option value="personas" @selected(($f['sin'] ?? '') === 'personas')>Sin personas</option>
        </select>
        <button class="btn btn-sm btn-secondary" type="submit">Filtrar</button>
        @if(array_filter($f))<a class="btn btn-sm btn-link" href="{{ route('archivo.gestion.fotos') }}">Limpiar</a>@endif
    </form>
    <nav class="agx-decadas-rapidas" aria-label="Décadas">
        @foreach([1990, 2000, 2010, 2020] as $d)
            <a href="{{ route('archivo.gestion.fotos', array_merge($f, ['decada' => $d])) }}" @class(['is-activo' => (string) ($f['decada'] ?? '') === (string) $d])>{{ $d }}s</a>
        @endforeach
    </nav>

    @if($fotos->isEmpty())
        <x-ito.empty title="No hay fotos con estos filtros" icon="bi-images" :action-href="auth()->user()->can('create', \App\Models\ArchivoFoto::class) ? route('archivo.gestion.subir') : null" action-label="Subir fotografías" />
    @else
        <form method="POST" action="{{ route('archivo.gestion.fotos.lote') }}" data-seleccion id="form-lote">
            @csrf
            <input type="hidden" name="volver" value="{{ request()->fullUrl() }}">
            @if($ordenable)
                <p class="agx-ayuda"><i class="bi bi-arrows-move" aria-hidden="true"></i> Arrastrá las fotos (o usá ← → con el foco en una) para cambiar el orden en que se muestran. Se guarda solo.</p>
            @endif
            <div class="agx-barra-sel">
                <label class="form-check-label"><input type="checkbox" class="form-check-input" data-sel-todas> Seleccionar todas</label>
                <span class="text-muted small" data-sel-cuenta></span>
            </div>
            <ul class="agx-grilla" @if($ordenable) data-ordenable data-url="{{ route('archivo.gestion.fotos.orden') }}" @endif>
                @foreach($fotos as $foto)
                    <li class="agx-item" data-id="{{ $foto->id }}" @if($ordenable) draggable="true" tabindex="0" aria-label="{{ $foto->tituloVisible() }}. Usá las flechas para moverla." @endif>
                        <label class="agx-item__check">
                            <input type="checkbox" name="ids[]" value="{{ $foto->id }}" class="form-check-input" data-sel>
                            <span class="visually-hidden">Seleccionar {{ $foto->tituloVisible() }}</span>
                        </label>
                        <a href="{{ route('archivo.gestion.fotos.edit', $foto) }}" class="agx-item__foto" style="background: {{ $foto->color ?: '#222' }}" draggable="false">
                            <img src="{{ $foto->imagenUrl(400) }}" alt="{{ $foto->textoAlternativo() }}" loading="lazy" draggable="false">
                            <span class="agx-estado agx-estado--{{ $foto->estado }}">{{ $foto->etiquetaEstado() }}</span>
                            @if($foto->destacada)<span class="agx-item__destacada" title="Destacada"><i class="bi bi-star-fill"></i></span>@endif
                        </a>
                        <div class="agx-item__texto">
                            <span class="agx-item__anio">{{ $foto->anio ?: 's/f' }}</span>
                            <a href="{{ route('archivo.gestion.fotos.edit', $foto) }}" class="agx-item__titulo">{{ $foto->tituloVisible() }}</a>
                            @if($foto->acontecimiento)<span class="text-muted small text-truncate">{{ $foto->acontecimiento->titulo }}</span>@endif
                            @if($foto->enviada_at && $foto->aportante)<span class="text-muted small">Aporte de {{ $foto->aportante->name }}</span>@endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <div class="agx-lote" data-lote hidden>
                <span class="agx-lote__cuenta" data-sel-cuenta></span>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas" data-bs-target="#panel-lote"><i class="bi bi-pencil-square"></i> Editar datos</button>
                <button type="submit" name="accion" value="publicar" class="btn btn-sm btn-success"><i class="bi bi-eye"></i> Publicar</button>
                <button type="submit" name="accion" value="ocultar" class="btn btn-sm btn-secondary"><i class="bi bi-eye-slash"></i> Ocultar</button>
                <button type="submit" name="accion" value="eliminar" class="btn btn-sm btn-outline-danger" data-confirmar="¿Eliminar las fotos seleccionadas? Se borran también los originales."><i class="bi bi-trash"></i> Eliminar</button>
            </div>

            <div class="offcanvas offcanvas-end agx-panel" tabindex="-1" id="panel-lote" aria-labelledby="panel-lote-titulo">
                <div class="offcanvas-header">
                    <h2 class="offcanvas-title h5" id="panel-lote-titulo">Editar <span data-sel-cuenta></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
                </div>
                <div class="offcanvas-body">
                    <p class="text-muted small">Solo se cambian los campos que completes.</p>
                    @include('archivo.gestion._campos-foto', ['lote' => true])
                    <button type="submit" name="accion" value="aplicar" class="btn btn-primary w-100">Aplicar a la selección</button>
                </div>
            </div>
        </form>

        <div class="mt-3">{{ $fotos->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-carga.js') }}?v=1" defer></script>
<script src="{{ asset('js/archivo-gestion.js') }}?v=1" defer></script>
@endpush
