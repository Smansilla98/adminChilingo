@extends('layouts.publico')

@section('title', $instrumentoLabel.' · '.$programaRitmo->nombre)
@section('publico-brand', 'Partituras')
@section('body-class', 'pt-shell')
@section('main-class', 'biblio-main--flush')

@section('content')
    <div class="partitura-parte-page">
        <header class="partitura-parte-head">
            <div>
                <p class="biblio-eyebrow">Parte para imprimir</p>
                <h1>{{ $programaRitmo->nombre }}</h1>
                <p class="partitura-parte-sub">
                    Parte de <strong>{{ $instrumentoLabel }}</strong>
                    @if ($programaRitmo->autor)
                        · {{ $programaRitmo->autor }}
                    @endif
                </p>
            </div>
            <a class="partitura-parte-back no-print" href="{{ route('programa.toque.show', $programaRitmo) }}">
                <i class="bi bi-arrow-left"></i> Volver al toque
            </a>
        </header>

        <div
            data-partitura-viewer
            data-score="{{ json_encode($score, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
            data-instrumento="{{ $instrumento }}"
            data-controles="1"
        ></div>
    </div>
@endsection

@push('vite')
@vite(['resources/js/partitura.js'])
@endpush
