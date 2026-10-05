@extends('layouts.archivo')

@section('content')
<div class="ar-cuerpo">
    @include('archivo.partials.linea', ['linea' => $linea])
    <div class="ar-relato">
        <header class="ar-pagina-cabecera" data-anio="{{ $anio }}" data-decada="{{ intdiv($anio, 10) * 10 }}">
            <p class="ar-sobretitulo ar-mono"><a href="{{ route('archivo.index') }}#relato">Archivo</a> · {{ intdiv($anio, 10) * 10 }}s</p>
            <h1 class="ar-anio-grande">{{ $anio }}</h1>
            <p class="ar-tenue">{{ $fotos->count() }} {{ $fotos->count() === 1 ? 'fotografía' : 'fotografías' }}@if($acontecimientos->isNotEmpty()) · {{ $acontecimientos->count() }} {{ $acontecimientos->count() === 1 ? 'acontecimiento' : 'acontecimientos' }}@endif</p>
        </header>

        @foreach($acontecimientos as $a)
            @include('archivo.partials.acontecimiento', ['a' => $a])
        @endforeach

        @if($fotos->isNotEmpty())
            <section aria-labelledby="fotos-anio" class="ar-bloque">
                <h2 class="ar-subtitulo" id="fotos-anio">Todas las fotos de {{ $anio }}</h2>
                <div class="ar-mosaico">
                    @foreach($fotos as $f)
                        @include('archivo.partials.figura', ['foto' => $f, 'sizes' => '(min-width: 900px) 30vw, 50vw', 'epigrafe' => true])
                    @endforeach
                </div>
            </section>
        @endif

        <nav class="ar-paso" aria-label="Años">
            @if($anterior)<a href="{{ route('archivo.anio', $anterior) }}" rel="prev"><span class="ar-tenue">← Antes</span> <strong>{{ $anterior }}</strong></a>@else<span></span>@endif
            @if($siguiente)<a href="{{ route('archivo.anio', $siguiente) }}" rel="next"><span class="ar-tenue">Después →</span> <strong>{{ $siguiente }}</strong></a>@endif
        </nav>
    </div>
</div>
@endsection
