@extends('layouts.partitura-editor')

@section('title', $instrumentoLabel.' · '.$programaRitmo->nombre)

@section('content')
    <div class="partitura-parte-page">
        <header class="partitura-parte-head">
            <div>
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
