@extends('layouts.app')

@section('title', $foto->tituloVisible().' — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow"><a href="{{ route('archivo.gestion.fotos') }}">Fotografías</a></p>
            <h1 class="ito-page-title">{{ $foto->tituloVisible() }}</h1>
            <p class="ito-page-sub">
                <span class="agx-estado agx-estado--{{ $foto->estado }} agx-estado--linea">{{ $foto->etiquetaEstado() }}</span>
                @if($foto->esPublica()) · <a href="{{ $foto->url() }}" target="_blank" rel="noopener">Ver publicada <i class="bi bi-box-arrow-up-right"></i></a>@endif
            </p>
        </div>
        <div class="ito-page-actions">
            @if($puede['publicar'] && in_array($foto->estado, ['borrador', 'oculta'], true))
                <form method="POST" action="{{ route('archivo.gestion.fotos.estado', $foto) }}">@csrf<button class="btn btn-success btn-sm" name="accion" value="publicar"><i class="bi bi-eye"></i> Publicar</button></form>
            @elseif($puede['publicar'] && $foto->estado === 'publicada')
                <form method="POST" action="{{ route('archivo.gestion.fotos.estado', $foto) }}">@csrf<button class="btn btn-secondary btn-sm" name="accion" value="ocultar"><i class="bi bi-eye-slash"></i> Ocultar</button></form>
            @endif
        </div>
    </div>
    @include('archivo.gestion._nav')

    <div class="agx-ficha">
        <aside class="agx-ficha__foto">
            <a href="{{ $foto->imagenUrl(2048) }}" target="_blank" rel="noopener" class="agx-ficha__img" style="background: {{ $foto->color ?: '#222' }}">
                <img src="{{ $foto->imagenUrl(800) }}" alt="{{ $foto->textoAlternativo() }}">
            </a>

            @if($puede['moderar'] && in_array($foto->estado, ['pendiente', 'cambios', 'rechazada', 'borrador'], true) && $foto->enviada_at)
                <section class="ito-card ito-card--body agx-moderar">
                    <h2 class="agx-h2">Moderación</h2>
                    @include('archivo.gestion._acciones-moderacion', ['foto' => $foto])
                </section>
            @endif

            @if($duplicados->isNotEmpty())
                <div class="alert alert-warning small mt-3">
                    <strong>Posible duplicado:</strong> la misma imagen está en
                    @foreach($duplicados as $d)<a href="{{ route('archivo.gestion.fotos.edit', $d) }}">#{{ $d->id }} {{ $d->tituloVisible() }}</a>@if(! $loop->last), @endif @endforeach.
                    Puede tener otra procedencia: no se borra sola.
                </div>
            @endif

            <dl class="agx-tecnico">
                <div><dt>Archivo original</dt><dd>{{ $foto->nombre_original ?: '—' }}</dd></div>
                <div><dt>Dimensiones</dt><dd>{{ $foto->ancho }} × {{ $foto->alto }} px</dd></div>
                <div><dt>Peso</dt><dd>{{ $foto->bytes ? number_format($foto->bytes / 1048576, 1, ',', '.').' MB' : '—' }}</dd></div>
                <div><dt>Tipo</dt><dd>{{ $foto->mime }}</dd></div>
                @foreach(['DateTimeOriginal' => 'Fecha EXIF', 'Make' => 'Marca', 'Model' => 'Cámara', 'Orientation' => 'Orientación'] as $k => $l)
                    @if(! empty($foto->exif[$k]))<div><dt>{{ $l }}</dt><dd>{{ $foto->exif[$k] }}</dd></div>@endif
                @endforeach
                <div><dt>Aportada por</dt><dd>{{ $foto->aportante?->name ?: '—' }}@if($foto->aportada_por) · {{ $foto->mostrar_aportante ? 'muestra su nombre' : 'anónimo en público' }}@endif</dd></div>
                @if($foto->revisor)<div><dt>Revisada por</dt><dd>{{ $foto->revisor->name }} · {{ $foto->revisada_at?->format('d/m/Y') }}</dd></div>@endif
                <div><dt>Derivados web</dt><dd>{{ collect($foto->derivados ?? [])->keys()->implode(' · ') ?: 'sin generar' }}</dd></div>
            </dl>
            <p class="small"><a href="{{ route('archivo.original', $foto) }}"><i class="bi bi-download"></i> Descargar original</a></p>

            @if($puede['editar'])
                <form method="POST" action="{{ route('archivo.gestion.fotos.imagen', $foto) }}" enctype="multipart/form-data" class="agx-reemplazar">
                    @csrf
                    <label class="form-label small" for="reemplazo">Reemplazar imagen (conserva todos los datos)</label>
                    <div class="input-group input-group-sm">
                        <input class="form-control" type="file" id="reemplazo" name="archivo" accept="image/jpeg,image/png,image/webp" required>
                        <button class="btn btn-outline-secondary" type="submit">Reemplazar</button>
                    </div>
                </form>
            @endif

            @if($foto->notas_aportante)
                <section class="ito-card ito-card--body mt-3">
                    <h2 class="agx-h2">Lo que contó quien la aportó</h2>
                    <p class="mb-0">{!! nl2br(e($foto->notas_aportante)) !!}</p>
                </section>
            @endif

            @if($foto->revisiones->isNotEmpty())
                <section class="mt-3">
                    <h2 class="agx-h2">Historial</h2>
                    <ol class="agx-historial">
                        @foreach($foto->revisiones as $r)
                            <li><span class="text-muted">{{ $r->created_at?->format('d/m/Y H:i') }}</span> {{ $r->etiqueta() }} @if($r->user)<span class="text-muted">· {{ $r->user->name }}</span>@endif @if($r->notas)<div class="small">{{ $r->notas }}</div>@endif</li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </aside>

        <div class="agx-ficha__form">
            @if($puede['editar'])
                <form method="POST" action="{{ route('archivo.gestion.fotos.update', $foto) }}" class="ito-form">
                    @csrf @method('PUT')
                    @include('archivo.gestion._campos-foto', ['foto' => $foto])
                    <x-ito.form-section title="Presentación" icon="bi-stars">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label" for="orden">Orden</label>
                                <input class="form-control" id="orden" name="orden" type="number" min="0" value="{{ $foto->orden }}">
                            </div>
                            <div class="col-md-8 d-flex flex-wrap gap-3">
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="destacada" name="destacada" value="1" @checked($foto->destacada)><label class="form-check-label" for="destacada">Destacada (portada del archivo)</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" id="mostrar_aportante" name="mostrar_aportante" value="1" @checked($foto->mostrar_aportante)><label class="form-check-label" for="mostrar_aportante">Mostrar el nombre del aportante</label></div>
                            </div>
                        </div>
                    </x-ito.form-section>
                    <div class="ito-form-actions">
                        @if($puede['eliminar'])
                            <button type="submit" form="form-eliminar" class="btn btn-outline-danger me-auto">Eliminar</button>
                        @endif
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </form>
            @else
                <div class="alert alert-info">Podés ver esta foto pero no editarla (fuera de tu alcance).</div>
            @endif
            @if($puede['eliminar'])
                <form method="POST" action="{{ route('archivo.gestion.fotos.destroy', $foto) }}" id="form-eliminar" data-confirmar="¿Eliminar esta foto? Se borran el original y los derivados.">
                    @csrf @method('DELETE')
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-carga.js') }}?v=1" defer></script>
<script src="{{ asset('js/archivo-gestion.js') }}?v=1" defer></script>
@endpush
