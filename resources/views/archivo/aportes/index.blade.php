@extends('layouts.archivo')

@section('body-class', 'archivo--aporte')

@php
    $estilos = ['borrador' => 'gris', 'pendiente' => 'amarillo', 'cambios' => 'naranja', 'rechazada' => 'rojo', 'publicada' => 'verde', 'oculta' => 'gris'];
@endphp

@section('content')
<div class="ar-aporte">
    <header class="ar-aporte__cabecera ar-aporte__cabecera--fila">
        <div>
            <p class="ar-sobretitulo ar-mono">Archivo colectivo</p>
            <h1 class="ar-aporte__titulo">Mis aportes</h1>
        </div>
        <a class="ar-boton" href="{{ route('archivo.aportar') }}">Compartir otro recuerdo</a>
    </header>

    <nav class="ar-chips ar-aportes__filtro" aria-label="Filtrar por estado">
        <a href="{{ route('archivo.aportes.index') }}" @class(['ar-chip-enlace', 'is-activo' => ! $estado])>Todos <span class="ar-tenue">{{ $conteos->sum() }}</span></a>
        @foreach(['pendiente', 'cambios', 'publicada', 'rechazada', 'borrador'] as $e)
            @if(($conteos[$e] ?? 0) > 0)
                <a href="{{ route('archivo.aportes.index', ['estado' => $e]) }}" @class(['ar-chip-enlace', 'is-activo' => $estado === $e])>
                    <span class="ar-estado ar-estado--{{ $estilos[$e] }}" aria-hidden="true"></span>{{ \App\Models\ArchivoFoto::ESTADOS[$e] }} <span class="ar-tenue">{{ $conteos[$e] }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    @if($fotos->isEmpty())
        <div class="ar-vacio">
            <h2>Todavía no compartiste fotos.</h2>
            <p>Cada foto que sumes ayuda a contar la historia de La Chilinga.</p>
            <a class="ar-boton" href="{{ route('archivo.aportar') }}">Compartir un recuerdo</a>
        </div>
    @else
        <ul class="ar-aportes">
            @foreach($fotos as $foto)
                <li class="ar-aporte-tarjeta">
                    <a href="{{ route('archivo.aportes.show', $foto) }}" class="ar-aporte-tarjeta__enlace">
                        <span class="ar-aporte-tarjeta__foto" style="--ar-color: {{ $foto->color ?: '#1d1b19' }}">
                            <img src="{{ $foto->imagenUrl(400) }}" alt="" loading="lazy">
                        </span>
                        <span class="ar-aporte-tarjeta__texto">
                            <strong>{{ $foto->tituloVisible() }}</strong>
                            <span class="ar-tenue">{{ $foto->anio ?: 'Sin año' }}</span>
                            <span class="ar-aporte-tarjeta__estado"><span class="ar-estado ar-estado--{{ $estilos[$foto->estado] ?? 'gris' }}" aria-hidden="true"></span>{{ $foto->etiquetaEstado() }}</span>
                            @if($foto->estado === 'cambios')<span class="ar-acento">Te pedimos más información →</span>@endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
        @if($fotos->hasPages())
            <nav class="ar-paso" aria-label="Páginas">
                @if($fotos->previousPageUrl())<a href="{{ $fotos->previousPageUrl() }}" rel="prev">← Anteriores</a>@else<span></span>@endif
                @if($fotos->nextPageUrl())<a href="{{ $fotos->nextPageUrl() }}" rel="next">Siguientes →</a>@endif
            </nav>
        @endif
    @endif
</div>
@endsection
