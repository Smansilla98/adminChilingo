@extends('layouts.app')

@section('title', 'Archivo histórico — gestión')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Memoria de La Chilinga</p>
            <h1 class="ito-page-title agx-titulo">Archivo histórico</h1>
        </div>
        <div class="ito-page-actions">
            @can('create', \App\Models\ArchivoCapitulo::class)
                <a href="{{ route('archivo.gestion.capitulos.create') }}" class="btn btn-secondary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo capítulo</a>
            @endcan
            @can('create', \App\Models\ArchivoAcontecimiento::class)
                <a href="{{ route('archivo.gestion.eventos.create') }}" class="btn btn-secondary btn-sm"><i class="bi bi-plus-lg"></i> Nuevo acontecimiento</a>
            @endcan
            @can('create', \App\Models\ArchivoFoto::class)
                <a href="{{ route('archivo.gestion.subir') }}" class="btn btn-primary btn-sm"><i class="bi bi-cloud-arrow-up"></i> Subir fotografías</a>
            @endcan
        </div>
    </div>
    @include('archivo.gestion._nav')

    <dl class="agx-cifras">
        <div><dt>Fotografías</dt><dd>{{ number_format($totales['fotos'], 0, ',', '.') }}</dd><span class="text-muted small">{{ number_format($totales['publicadas'], 0, ',', '.') }} publicadas</span></div>
        <div><dt>Capítulos</dt><dd>{{ $totales['capitulos'] }}</dd></div>
        <div><dt>Acontecimientos</dt><dd>{{ $totales['acontecimientos'] }}</dd></div>
        <div><dt>Personas</dt><dd>{{ $totales['personas'] }}</dd></div>
        <div><dt>Etiquetas</dt><dd>{{ $totales['etiquetas'] }}</dd></div>
    </dl>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="ito-card ito-card--body h-100">
                <h2 class="agx-h2">Moderación</h2>
                <div class="agx-mod">
                    <a href="{{ route('archivo.gestion.moderacion') }}"><strong>{{ $moderacion['pendiente'] }}</strong><span>Pendientes</span></a>
                    <a href="{{ route('archivo.gestion.moderacion', ['estado' => 'cambios']) }}"><strong>{{ $moderacion['cambios'] }}</strong><span>Cambios solicitados</span></a>
                    <span><strong>{{ $moderacion['aprobadas_hoy'] }}</strong><span>Aprobadas hoy</span></span>
                    <a href="{{ route('archivo.gestion.moderacion', ['estado' => 'rechazada']) }}"><strong>{{ $moderacion['rechazada'] }}</strong><span>Rechazadas</span></a>
                </div>
            </section>
        </div>
        <div class="col-lg-6">
            <section class="ito-card ito-card--body h-100">
                <h2 class="agx-h2">Para ordenar el archivo</h2>
                <ul class="agx-pendientes">
                    <li><a href="{{ route('archivo.gestion.fotos', ['sin' => 'fecha']) }}">Fotos sin fecha</a><span @class(['badge', 'text-bg-warning' => $calidad['sin_fecha'], 'text-bg-light' => ! $calidad['sin_fecha']])>{{ $calidad['sin_fecha'] }}</span></li>
                    <li><a href="{{ route('archivo.gestion.fotos', ['sin' => 'credito']) }}">Fotos sin fotógrafo ni crédito</a><span class="badge text-bg-light">{{ $calidad['sin_credito'] }}</span></li>
                    <li><a href="{{ route('archivo.gestion.fotos', ['sin' => 'descripcion']) }}">Fotos sin descripción</a><span class="badge text-bg-light">{{ $calidad['sin_descripcion'] }}</span></li>
                    <li><a href="{{ route('archivo.gestion.eventos.index', ['sin' => 'portada']) }}">Acontecimientos sin portada</a><span class="badge text-bg-light">{{ $calidad['sin_portada'] }}</span></li>
                    <li><a href="{{ route('archivo.gestion.fotos', ['estado' => 'borrador']) }}">Material sin publicar (borradores)</a><span class="badge text-bg-light">{{ $calidad['borradores'] }}</span></li>
                </ul>
            </section>
        </div>
    </div>

    @if($porDecada->isNotEmpty())
        <section class="ito-card ito-card--body mt-3">
            <h2 class="agx-h2">Línea temporal</h2>
            @php $max = max(1, $porDecada->max()); @endphp
            <ol class="agx-decadas">
                @foreach($porDecada as $decada => $n)
                    <li>
                        <a href="{{ route('archivo.gestion.fotos', ['decada' => $decada]) }}" title="{{ $n }} fotos">
                            <span class="agx-decadas__barra" style="--alto: {{ round($n / $max * 100) }}%"></span>
                            <span class="agx-decadas__n">{{ $n }}</span>
                            <span class="agx-decadas__etiqueta">{{ $decada }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if($recientes->isNotEmpty())
        <section class="mt-4">
            <div class="d-flex justify-content-between align-items-baseline">
                <h2 class="agx-h2">Últimas cargas</h2>
                <a href="{{ route('archivo.gestion.fotos') }}" class="small">Ver todas</a>
            </div>
            <div class="agx-grilla agx-grilla--chica">
                @foreach($recientes as $f)
                    <a class="agx-mini" href="{{ route('archivo.gestion.fotos.edit', $f) }}">
                        <img src="{{ $f->imagenUrl(400) }}" alt="{{ $f->textoAlternativo() }}" loading="lazy" style="background: {{ $f->color ?: '#222' }}">
                        <span class="agx-estado agx-estado--{{ $f->estado }}">{{ $f->etiquetaEstado() }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
