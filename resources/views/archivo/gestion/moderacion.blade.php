@extends('layouts.app')

@section('title', 'Moderación — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Archivo histórico</p>
            <h1 class="ito-page-title">Moderación de aportes</h1>
            <p class="ito-page-sub">Lo que la comunidad envía. Al aprobar, la foto se publica y le avisamos a quien la aportó.</p>
        </div>
    </div>
    @include('archivo.gestion._nav')

    <nav class="agx-mod agx-mod--tabs" aria-label="Estado">
        <a href="{{ route('archivo.gestion.moderacion') }}" @class(['is-activo' => $estado === 'pendiente'])><strong>{{ $conteos['pendiente'] }}</strong><span>Pendientes</span></a>
        <a href="{{ route('archivo.gestion.moderacion', ['estado' => 'cambios']) }}" @class(['is-activo' => $estado === 'cambios'])><strong>{{ $conteos['cambios'] }}</strong><span>Cambios solicitados</span></a>
        <span><strong>{{ $conteos['aprobadas_hoy'] }}</strong><span>Aprobadas hoy</span></span>
        <a href="{{ route('archivo.gestion.moderacion', ['estado' => 'rechazada']) }}" @class(['is-activo' => $estado === 'rechazada'])><strong>{{ $conteos['rechazada'] }}</strong><span>Rechazadas</span></a>
    </nav>

    @if($cola->isEmpty())
        <x-ito.empty title="{{ $estado === 'pendiente' ? 'No hay aportes esperando revisión' : 'Nada en este estado' }}" icon="bi-shield-check" />
    @else
        <ul class="agx-cola">
            @foreach($cola as $foto)
                <li class="agx-cola__item">
                    <a class="agx-cola__foto" href="{{ route('archivo.gestion.fotos.edit', $foto) }}" style="background: {{ $foto->color ?: '#222' }}">
                        <img src="{{ $foto->imagenUrl(800) }}" alt="{{ $foto->textoAlternativo() }}" loading="lazy">
                    </a>
                    <div class="agx-cola__texto">
                        <h2 class="agx-cola__titulo"><a href="{{ route('archivo.gestion.fotos.edit', $foto) }}">{{ $foto->tituloVisible() }}</a></h2>
                        <dl class="agx-cola__datos">
                            <div><dt>Aportada por</dt><dd>{{ $foto->aportante?->name ?? '—' }}</dd></div>
                            <div><dt>Año declarado</dt><dd>{{ $foto->fechaLegible() ?? 'Sin año' }}</dd></div>
                            @if($foto->lugar || $foto->ciudad || $foto->sede)<div><dt>Lugar</dt><dd>{{ collect([$foto->lugar, $foto->ciudad, $foto->sede?->nombre])->filter()->implode(' · ') }}</dd></div>@endif
                            @if($foto->acontecimiento)<div><dt>Momento</dt><dd>{{ $foto->acontecimiento->titulo }}</dd></div>@endif
                            @if($foto->personas->isNotEmpty())<div><dt>Personas</dt><dd>{{ $foto->personas->map->nombreVisible()->implode(', ') }}</dd></div>@endif
                            @if($foto->fotografo || $foto->fuente)<div><dt>Procedencia</dt><dd>{{ collect([$foto->fotografo ? 'Foto: '.$foto->fotografo : null, $foto->etiquetaFuente(), $foto->fuente_detalle])->filter()->implode(' · ') }}</dd></div>@endif
                            <div><dt>Enviada</dt><dd>{{ $foto->enviada_at?->diffForHumans() ?? '—' }}</dd></div>
                        </dl>
                        @if($foto->descripcion)<p class="mb-1">{{ $foto->descripcion }}</p>@endif
                        @if($foto->notas_aportante)<p class="small text-muted mb-2">“{{ \Illuminate\Support\Str::limit($foto->notas_aportante, 280) }}”</p>@endif
                        @if(isset($repetidos[$foto->hash]))<p class="small text-warning-emphasis mb-2"><i class="bi bi-exclamation-triangle"></i> La misma imagen ya está en el archivo ({{ $repetidos[$foto->hash] - 1 }}).</p>@endif
                        @if($foto->estado === 'cambios' && $foto->notas_revision)<p class="small mb-2"><strong>Pedido:</strong> {{ $foto->notas_revision }}</p>@endif
                        @if($foto->estado === 'rechazada' && $foto->motivo_rechazo)<p class="small mb-2"><strong>Motivo:</strong> {{ $foto->motivo_rechazo }}</p>@endif
                        <div class="d-flex flex-wrap gap-2 align-items-start">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('archivo.gestion.fotos.edit', $foto) }}"><i class="bi bi-pencil"></i> Ver y editar</a>
                            @can('moderate', $foto)
                                @include('archivo.gestion._acciones-moderacion', ['foto' => $foto, 'volver' => request()->fullUrl()])
                            @endcan
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="mt-3">{{ $cola->links() }}</div>
    @endif
</div>
@endsection
