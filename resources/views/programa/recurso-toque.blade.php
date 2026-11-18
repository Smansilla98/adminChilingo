@extends('layouts.publico')

@section('title', $toque->nombre.' · Recursos')
@section('publico-brand', 'Toques y recursos')

@section('content')
<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">{{ $resumen['anio'] }}° año · orden {{ $resumen['orden'] }}</p>
        <h1>{{ $toque->nombre }}</h1>
        <p class="biblio-lead">
            {{ $resumen['videos'] > 0 ? $resumen['videos'].' '.($resumen['videos'] === 1 ? 'video' : 'videos') : 'Sin videos' }}
            · {{ $resumen['partitura'] ? 'con partitura' : 'sin partitura' }}
            · {{ $resumen['audio'] ? 'con grabación de referencia' : 'sin grabación' }}
        </p>
    </div>
    <div class="prog-hero-actions">
        <a href="{{ url('/programa/recursos') }}" class="btn btn-outline-secondary">Todos los recursos</a>
        <a href="{{ route('programa.toque.show', $toque) }}" class="btn btn-outline-primary">Ver el toque</a>
    </div>
</div>

@if($totalVideos === 0)
    <p class="text-muted">Este toque no tiene videos por instrumento todavía.</p>
@elseif(! $puedeVer)
    <div class="alert alert-light border" role="status">
        @auth
            Este material es de {{ $resumen['anio'] }}° año: se abre cuando tu bloque llegue a ese año.
        @else
            Para ver los videos entrá con tu cuenta. Si sos alumno y no tenés, podés crearla con tu DNI.
            <a href="{{ route('login') }}" class="alert-link">Entrar</a>
        @endauth
    </div>
@else
    <section class="mt-3" aria-label="Reproductor de videos">
        <div data-videos-multipista data-videos="{{ json_encode($videosReproducir, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"></div>
        <p class="small text-muted mt-2">«Ejecutar todos» reproduce los videos juntos y sincronizados. «Ejecutar por separado» muestra uno solo: elegilo en la lista.</p>
    </section>
@endif
@endsection
