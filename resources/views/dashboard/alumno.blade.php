@extends('layouts.app')

@section('title', 'Mi espacio')
@section('page-title', 'Mi espacio')

@section('content')
@php
    $nombre = trim(auth()->user()->name ?: auth()->user()->username ?: 'Alumno');
    $primer = explode(' ', $nombre)[0] ?: 'Alumno';
    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp

<div class="hub">
    <div class="hub-hero">
        <div class="hub-hero-main">
            <p class="hub-eyebrow">Espacio del alumno</p>
            <h1 class="hub-greeting">{{ $saludo }}, <em>{{ $primer }}</em>.</h1>
            <p class="hub-lead">
                @if($alumno)
                    {{ $alumno->nombre_apellido }}
                    @if($alumno->sede)<span> · {{ $alumno->sede->nombre }}</span>@endif
                @else
                    Acá encontrás el programa, las partituras y el pago de cuota (por DNI, sin listar a toda la escuela).
                @endif
            </p>
        </div>
        <dl class="hub-meta">
            <div>
                <dt>Rol</dt>
                <dd>{{ auth()->user()->etiquetaRol() }}</dd>
            </div>
        </dl>
    </div>

    <div class="hub-modules">
        <a class="hub-module" href="{{ route('programa.index') }}">
            <span class="hub-module-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
            <span class="hub-module-body">
                <span class="hub-module-title">Programa</span>
                <span class="hub-module-desc">Toques y repertorio de la escuela</span>
            </span>
        </a>
        <a class="hub-module" href="{{ route('programa.partituras.index') }}">
            <span class="hub-module-icon"><i class="bi bi-file-earmark-music" aria-hidden="true"></i></span>
            <span class="hub-module-body">
                <span class="hub-module-title">Partituras</span>
                <span class="hub-module-desc">Leer y practicar</span>
            </span>
        </a>
        <a class="hub-module" href="{{ route('biblioteca.index') }}">
            <span class="hub-module-icon"><i class="bi bi-images" aria-hidden="true"></i></span>
            <span class="hub-module-body">
                <span class="hub-module-title">Biblioteca</span>
                <span class="hub-module-desc">Material compartido</span>
            </span>
        </a>
        <a class="hub-module" href="{{ route('comprobante-cuota-public.create') }}">
            <span class="hub-module-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
            <span class="hub-module-body">
                <span class="hub-module-title">Pagar cuota</span>
                <span class="hub-module-desc">Enviar comprobante</span>
            </span>
        </a>
    </div>

    @if(!empty($proximaClase))
    <section class="hub-section" aria-labelledby="alu-clase">
        <h2 class="hub-section-title" id="alu-clase">Tu próxima clase</h2>
        <p class="lead mb-0">
            {{ $proximaClase['bloque'] }}
            @if($proximaClase['sede']) · {{ $proximaClase['sede'] }}@endif
            · {{ $proximaClase['dia'] }}
            @if($proximaClase['hora']) {{ $proximaClase['hora'] }}@endif
            @if(!empty($proximaClase['es_hoy']))
                <strong> — es hoy</strong>
            @endif
        </p>
    </section>
    @endif

    @if(!empty($proximoPaso))
    <section class="hub-section" aria-labelledby="alu-practica">
        <h2 class="hub-section-title" id="alu-practica">Para practicar</h2>
        @if($proximoPaso->toque)<p class="mb-1"><strong>{{ $proximoPaso->toque }}</strong></p>@endif
        <p class="mb-0">{{ $proximoPaso->proximo_paso ?: $proximoPaso->cuerpo }}</p>
    </section>
    @endif

    @if(($asistencias ?? collect())->isNotEmpty())
    <section class="hub-section" aria-labelledby="alu-asist">
        <h2 class="hub-section-title" id="alu-asist">Tus últimas asistencias</h2>
        <ul class="ito-feed">
            @foreach($asistencias as $a)
            <li>
                {{ $a->fecha?->format('d/m/Y') }}
                —
                <span aria-hidden="true">{{ \App\Models\Asistencia::letraTipo($a->tipo_asistencia) }}</span>
                {{ \App\Models\Asistencia::TIPOS_ASISTENCIA[$a->tipo_asistencia] ?? ($a->presente ? 'Presente' : 'Ausente') }}
            </li>
            @endforeach
        </ul>
    </section>
    @endif

    @if(($eventos ?? collect())->isNotEmpty())
    <section class="hub-section" aria-labelledby="alu-ev">
        <h2 class="hub-section-title" id="alu-ev">Próximos eventos</h2>
        <ul class="ito-feed">
            @foreach($eventos as $ev)
            <li>{{ $ev->titulo ?? $ev->nombre ?? 'Evento' }} · {{ $ev->fecha?->format('d/m/Y') }}</li>
            @endforeach
        </ul>
    </section>
    @endif
</div>
@endsection
