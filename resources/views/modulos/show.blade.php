@extends('layouts.app')

@section('title', $modulo['titulo'].' · La Chilinga')
@section('page-title', $modulo['titulo'])

@section('content')
<nav aria-label="Migas de pan" class="modulo-migas">
    <a href="{{ route('dashboard') }}">Inicio</a>
    <span aria-hidden="true">/</span>
    <span aria-current="page">{{ $modulo['titulo'] }}</span>
</nav>

<header class="modulo-page-head">
    <span class="modulo-page-icono" aria-hidden="true"><i class="bi {{ $modulo['icono'] }}"></i></span>
    <div class="modulo-page-texto">
        <h1 class="modulo-page-titulo">{{ $modulo['titulo'] }}</h1>
        <p class="modulo-page-resumen">{{ $modulo['resumen'] }}</p>
    </div>
    @if($modulo['accion'])
        <a class="btn btn-primary modulo-page-accion" href="{{ $modulo['accion']['href'] }}">{{ $modulo['accion']['label'] }}</a>
    @endif
</header>

<h2 class="modulo-page-subtitulo">Pantallas <span class="modulo-page-cuenta">{{ count($modulo['links']) }}</span></h2>

<div class="modulo-tiles">
    @foreach($modulo['links'] as $link)
        <a href="{{ $link['href'] }}" class="modulo-tile{{ $link['active'] ? ' is-active' : '' }}" @if($link['active']) aria-current="page" @endif>
            <span class="modulo-tile-icono" aria-hidden="true"><i class="bi {{ $link['icon'] }}"></i></span>
            <span class="modulo-tile-texto">{{ $link['label'] }}</span>
            <i class="bi bi-chevron-right modulo-tile-flecha" aria-hidden="true"></i>
        </a>
    @endforeach
</div>
@endsection
