@extends('layouts.archivo')

@section('body-class', 'archivo--historia')

@section('content')
{{-- Story Mode: una escena por pantalla. ↓ / → / espacio avanzan; ↑ / ← retroceden. --}}
<div class="ar-historia" data-historia>
    <section class="ar-toma ar-toma--apertura" data-toma>
        @if($portada)
            <img class="ar-toma__fondo" src="{{ $portada->imagenUrl(1200) }}" srcset="{{ $portada->srcset() }}" sizes="100vw" alt="" decoding="async">
        @endif
        <div class="ar-toma__texto">
            <p class="ar-sobretitulo ar-mono">Modo historia</p>
            <h1 class="ar-toma__titulo">La historia de La Chilinga</h1>
            <p class="ar-toma__bajada">Deslizá, usá las flechas o la barra espaciadora para avanzar.</p>
        </div>
    </section>

    @foreach($capitulos as $i => $capitulo)
        <section class="ar-toma ar-toma--capitulo" data-toma data-anio="{{ $capitulo->anio_desde }}">
            <div class="ar-toma__texto">
                <p class="ar-sobretitulo ar-mono">Capítulo {{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}@if($capitulo->periodo()) · {{ $capitulo->periodo() }}@endif</p>
                <h2 class="ar-toma__titulo">{{ $capitulo->titulo }}</h2>
                @if($capitulo->bajada)<p class="ar-toma__bajada">{{ $capitulo->bajada }}</p>@endif
            </div>
        </section>
        @foreach($capitulo->acontecimientos as $a)
            @php $foto = ($a->portada && $a->portada->esPublica()) ? $a->portada : $a->fotos->first(); @endphp
            <section class="ar-toma {{ $foto ? 'ar-toma--foto' : 'ar-toma--solo-texto' }}" data-toma data-anio="{{ $a->anio }}">
                @if($foto)
                    @include('archivo.partials.figura', ['foto' => $foto, 'clase' => 'ar-toma__figura', 'sizes' => '(min-width: 900px) 65vw, 100vw'])
                @endif
                <div class="ar-toma__texto">
                    <p class="ar-toma__anio ar-mono">{{ $a->fechaLegible() }}</p>
                    <h3 class="ar-toma__titulo">{{ $a->titulo }}</h3>
                    @if($a->bajada || $a->descripcion)<p class="ar-toma__bajada">{{ $a->bajada ?: \Illuminate\Support\Str::limit($a->descripcion, 320) }}</p>@endif
                    @if($a->fotos->count() > 1)
                        <div class="ar-toma__tira" aria-label="Más fotos">
                            @foreach($a->fotos->reject(fn ($f) => $f->id === $foto?->id)->take(3) as $f)
                                @include('archivo.partials.figura', ['foto' => $f, 'clase' => 'ar-toma__mini', 'sizes' => '120px'])
                            @endforeach
                        </div>
                    @endif
                    <a class="ar-enlace" href="{{ $a->url() }}">Ver la historia completa <span aria-hidden="true">→</span></a>
                </div>
            </section>
        @endforeach
    @endforeach

    <section class="ar-toma ar-toma--cierre" data-toma>
        <div class="ar-toma__texto">
            <h2 class="ar-toma__titulo">La historia sigue.</h2>
            <p class="ar-toma__bajada">El archivo crece con cada aporte.</p>
            <div class="ar-portada__acciones">
                <a class="ar-boton" href="{{ route('archivo.aportar') }}">Compartir un recuerdo</a>
                <a class="ar-boton ar-boton--linea" href="{{ route('archivo.buscar') }}">Explorar el archivo</a>
            </div>
        </div>
    </section>
</div>
<nav class="ar-historia__control" aria-label="Avanzar en la historia">
    <button type="button" class="ar-visor__btn" data-toma-paso="-1" aria-label="Escena anterior">↑</button>
    <span class="ar-mono" data-toma-contador aria-live="polite"></span>
    <button type="button" class="ar-visor__btn" data-toma-paso="1" aria-label="Escena siguiente">↓</button>
</nav>
@endsection
