@extends('layouts.app')

@section('title', 'Portal familia')
@section('page-title', 'Portal familia')

@section('content')
@php
    $nombre = trim(auth()->user()->name ?: auth()->user()->username ?: 'Familia');
    $primer = explode(' ', $nombre)[0] ?: 'Familia';
    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $cuota = $estadoCuota ?? null;
    $pagarHref = $cuota['pagar_url'] ?? route('comprobante-cuota-public.create');
@endphp

<div class="hub hub--familia">
    <div class="hub-hero">
        <div class="hub-hero-main">
            <p class="hub-eyebrow">Portal familia · La Chilinga</p>
            <h1 class="hub-greeting">{{ $saludo }}, <em>{{ $primer }}</em>.</h1>
            <p class="hub-lead">
                @if($alumno)
                    Información de <strong>{{ $alumno->nombre_apellido }}</strong>
                    @if($alumno->sede)<span> · {{ $alumno->sede->nombre }}</span>@endif.
                    Solo datos de este alumno: sin listar compañeros.
                @else
                    Programa, partituras y pago de cuota. Para enviar el comprobante usá el DNI (no se muestra el padrón).
                @endif
            </p>
        </div>
        <dl class="hub-meta">
            <div>
                <dt>Espacio</dt>
                <dd>Familia / alumno</dd>
            </div>
        </dl>
    </div>

    @if($cuota)
    <section class="hub-section hub-section--priority" aria-labelledby="fam-cuota">
        <header class="hub-section-head">
            <h2 id="fam-cuota" class="hub-section-title"><span class="hub-section-code">Cuota</span> {{ $cuota['periodo'] }}</h2>
        </header>
        <div class="hub-modules">
            <a class="hub-module {{ in_array($cuota['estado'], ['pendiente', 'vencida'], true) ? 'hub-module--alert' : '' }}" href="{{ $pagarHref }}">
                <span class="hub-module-icon" aria-hidden="true">
                    <i class="bi {{ $cuota['estado'] === 'al_dia' ? 'bi-check2-circle' : ($cuota['estado'] === 'en_revision' ? 'bi-hourglass-split' : 'bi-cash-coin') }}"></i>
                </span>
                <span class="hub-module-body">
                    <span class="hub-module-title">{{ $cuota['label'] }}</span>
                    <span class="hub-module-desc">{{ $cuota['hint'] }}</span>
                    @if(in_array($cuota['estado'], ['pendiente', 'vencida', 'sin_cuota'], true))
                        <span class="hub-kpi-badge {{ $cuota['estado'] === 'vencida' ? 'is-alert' : '' }}">Enviar comprobante</span>
                    @elseif($cuota['estado'] === 'al_dia')
                        <span class="hub-kpi-badge is-ok">Al día</span>
                    @else
                        <span class="hub-kpi-badge">En revisión</span>
                    @endif
                </span>
            </a>
        </div>
    </section>
    @endif

    @if(!empty($proximaClase))
    <section class="hub-section" aria-labelledby="fam-clase">
        <header class="hub-section-head">
            <h2 id="fam-clase" class="hub-section-title"><span class="hub-section-code">Clase</span> Próxima clase</h2>
        </header>
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

    <section class="hub-section" aria-labelledby="fam-hacer">
        <header class="hub-section-head">
            <h2 id="fam-hacer" class="hub-section-title"><span class="hub-section-code">Hacer</span> Accesos</h2>
        </header>
        <div class="hub-modules hub-modules--compact">
            <a class="hub-module" href="{{ $pagarHref }}">
                <span class="hub-module-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Pagar cuota</span>
                    <span class="hub-module-desc">Enviar comprobante</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('programa.partituras.index') }}">
                <span class="hub-module-icon"><i class="bi bi-file-earmark-music" aria-hidden="true"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Partituras</span>
                    <span class="hub-module-desc">Para practicar</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('programa.index') }}">
                <span class="hub-module-icon"><i class="bi bi-journal-text" aria-hidden="true"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Programa</span>
                    <span class="hub-module-desc">Toques y repertorio</span>
                </span>
            </a>
            <a class="hub-module" href="{{ route('biblioteca.index') }}">
                <span class="hub-module-icon"><i class="bi bi-images" aria-hidden="true"></i></span>
                <span class="hub-module-body">
                    <span class="hub-module-title">Biblioteca</span>
                    <span class="hub-module-desc">Material compartido</span>
                </span>
            </a>
        </div>
    </section>

    @if(!empty($proximoPaso))
    <section class="hub-section" aria-labelledby="fam-practica">
        <h2 class="hub-section-title" id="fam-practica">Para practicar</h2>
        @if($proximoPaso->toque)<p class="mb-1"><strong>{{ $proximoPaso->toque }}</strong></p>@endif
        <p class="mb-0">{{ $proximoPaso->proximo_paso ?: $proximoPaso->cuerpo }}</p>
    </section>
    @endif

    <div class="hub-panels">
        @if(($asistencias ?? collect())->isNotEmpty())
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div class="hub-panel-title">Últimas asistencias</div>
            </div>
            @foreach($asistencias as $a)
                <div class="hub-list-item">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $a->fecha?->format('d/m/Y') }}</div>
                        <div class="small text-muted">
                            {{ \App\Models\Asistencia::TIPOS_ASISTENCIA[$a->tipo_asistencia] ?? ($a->presente ? 'Presente' : 'Ausente') }}
                        </div>
                    </div>
                    <span class="hub-kpi-badge" aria-hidden="true">{{ \App\Models\Asistencia::letraTipo($a->tipo_asistencia) }}</span>
                </div>
            @endforeach
        </div>
        @endif

        @if(($eventos ?? collect())->isNotEmpty())
        <div class="hub-panel">
            <div class="hub-panel-head">
                <div class="hub-panel-title">Próximos eventos</div>
            </div>
            @foreach($eventos as $ev)
                <div class="hub-list-item">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $ev->titulo ?? $ev->nombre ?? 'Evento' }}</div>
                        <div class="small text-muted">{{ $ev->fecha?->format('d/m/Y') }}</div>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
