@extends('layouts.publico')

@section('title', 'Discografía — Programa')
@section('publico-brand', 'Programa')

@section('content')
<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}">Programa</a></li>
        <li class="breadcrumb-item active" aria-current="page">Discografía</li>
    </ol>
</nav>

<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">La banda de la escuela</p>
        <h1>Discografía de La Chilinga</h1>
        <p class="biblio-lead">Los discos de la banda, dirigida por Dani Buira. Ahí suenan muchos de los toques del programa: en cada disco marcamos cuáles podés estudiar con su partitura.</p>
    </div>
    <div class="prog-hero-actions">
        <a href="{{ route('programa.index') }}#contenido-programa" class="btn btn-outline-secondary">Volver al programa</a>
    </div>
</div>

@if($discos->isEmpty())
    <div class="alert alert-info">Todavía no hay discos cargados.</div>
@else
<div class="disco-grid">
    @foreach($discos as $disco)
        @php
            $temas = $disco->listaDeTemas();
            $total = $disco->duracionTotal();
            $suenan = count($disco->toquesDelPrograma($toques));
        @endphp
        <a href="{{ route('programa.discos.show', $disco) }}" class="disco-card">
            @include('programa.discografia.partials.tapa', ['disco' => $disco])
            <div>
                <h2>{{ $disco->titulo }}</h2>
                <p class="disco-card__meta">
                    {{ $disco->anio }}@if(count($temas)) · {{ count($temas) }} temas @endif @if($total) · {{ $total }} @endif
                </p>
                @if($suenan)
                    <p class="disco-card__meta"><i class="bi bi-music-note-beamed" aria-hidden="true"></i> {{ $suenan }} {{ $suenan === 1 ? 'toque' : 'toques' }} del programa</p>
                @endif
            </div>
        </a>
    @endforeach
</div>
@endif
@endsection
