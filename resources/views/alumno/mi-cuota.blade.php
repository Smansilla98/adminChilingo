@extends('layouts.app')

@section('title', 'Pagar mi cuota')
@section('page-title', 'Pagar mi cuota')

@php
    $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $pagables = collect($opciones)->filter(fn ($o) => $o['cuota_id'] && ! $o['ya_pagada']);
@endphp

@section('content')
<div class="ito-page" style="max-width: 760px">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Pagar mi cuota</h1>
            <p class="ito-page-sub">Si ya pagaste (transferencia o depósito), mandá acá la foto o el PDF del comprobante. Son tres pasos.</p>
        </div>
    </div>

    @if(config('chilinga.cobro.alias') || config('chilinga.cobro.cbu') || config('services.mercadopago.access_token'))
        <a href="{{ route('alumno.cuota.pagar') }}" class="ito-card p-3 mb-3 d-flex align-items-center gap-3 text-decoration-none">
            <i class="bi bi-wallet2 fs-3" aria-hidden="true"></i>
            <span class="flex-grow-1"><strong class="d-block">¿Todavía no pagaste?</strong><span class="text-muted">Pagá todas tus cuotas juntas desde acá, por transferencia o Mercado Pago.</span></span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
    @endif

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Revisá esto antes de enviar:</strong>
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    {{-- Paso 1: el mes (cambia la lista de cuotas) --}}
    <form method="GET" action="{{ route('alumno.cuota') }}" class="ito-card p-3 mb-3">
        <h2 class="h6 mb-2"><span class="badge rounded-pill text-bg-dark me-1">1</span> ¿Qué mes estás pagando?</h2>
        <div class="row g-2 align-items-end">
            @if($fichas->count() > 1)
            <div class="col-12">
                <label class="form-label" for="mc-alumno">¿De quién es la cuota?</label>
                <select id="mc-alumno" name="alumno" class="form-select" onchange="this.form.submit()">
                    @foreach($fichas as $f)
                        <option value="{{ $f->id }}" @selected($f->id === $alumno->id)>{{ $f->nombre_apellido }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-7">
                <label class="form-label" for="mc-mes">Mes</label>
                <select id="mc-mes" name="mes" class="form-select" onchange="this.form.submit()">
                    @foreach($meses as $n => $nombre)
                        <option value="{{ $n }}" @selected($n === $mes)>{{ $nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-5">
                <label class="form-label" for="mc-anio">Año</label>
                <select id="mc-anio" name="anio" class="form-select" onchange="this.form.submit()">
                    @foreach(range(now()->year - 1, now()->year + 1) as $a)
                        <option value="{{ $a }}" @selected($a === $anio)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <noscript><div class="col-12"><button class="btn btn-outline-secondary btn-sm">Ver ese mes</button></div></noscript>
        </div>
    </form>

    <form method="POST" action="{{ route('alumno.cuota.store') }}" enctype="multipart/form-data" class="ito-card p-3 mb-4">
        @csrf
        <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">
        <input type="hidden" name="anio" value="{{ $anio }}">
        <input type="hidden" name="mes" value="{{ $mes }}">

        {{-- Paso 2: qué cuota --}}
        <h2 class="h6 mb-2"><span class="badge rounded-pill text-bg-dark me-1">2</span> Marcá la cuota de {{ $meses[$mes] }} {{ $anio }}</h2>
        @forelse($opciones as $o)
            <div class="form-check border rounded p-3 mb-2 ps-5">
                <input class="form-check-input" type="checkbox" name="bloque_ids[]" value="{{ $o['id'] }}" id="mc-b{{ $o['id'] }}"
                       @checked($pagables->count() === 1 && $o['cuota_id'] && ! $o['ya_pagada'])
                       @disabled(! $o['cuota_id'] || $o['ya_pagada'])>
                <label class="form-check-label w-100" for="mc-b{{ $o['id'] }}">
                    <strong>{{ $o['nombre'] }}</strong>
                    @if($o['ya_pagada'])
                        <span class="badge text-bg-success ms-1">Ya está paga</span>
                    @elseif($o['cuota_id'])
                        <br><span class="text-muted">{{ $o['cuota'] }} · ${{ number_format((float) $o['monto'], 0, ',', '.') }}</span>
                    @else
                        <br><span class="text-muted">Este mes todavía no tiene cuota cargada. Si pagaste igual, avisale a tu profe.</span>
                    @endif
                </label>
            </div>
        @empty
            <p class="text-muted">No figurás en ningún bloque activo. Avisale a tu profe o a administración.</p>
        @endforelse

        @if($pagables->isEmpty() && collect($opciones)->isNotEmpty())
            <div class="alert alert-success mt-2 mb-0" role="status">No tenés nada para pagar de {{ $meses[$mes] }}. Si querés, elegí otro mes arriba.</div>
        @else
            {{-- Paso 3: el comprobante --}}
            <h2 class="h6 mt-3 mb-2"><span class="badge rounded-pill text-bg-dark me-1">3</span> Subí el comprobante</h2>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="mc-fecha">¿Qué día pagaste?</label>
                    <input type="date" id="mc-fecha" name="fecha_pago" class="form-control" value="{{ old('fecha_pago', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="mc-archivo">Foto o PDF del comprobante</label>
                    <input type="file" id="mc-archivo" name="comprobante" class="form-control" accept="image/*,application/pdf" required aria-describedby="mc-archivo-ayuda">
                    <div id="mc-archivo-ayuda" class="form-text">Desde el celular podés sacarle una foto a la pantalla del pago. Hasta 10 MB.</div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="mc-notas">¿Querés aclarar algo? <span class="text-muted">(opcional)</span></label>
                    <textarea id="mc-notas" name="notas" class="form-control" rows="2" maxlength="1000" placeholder="Ej.: pagué dos meses juntos">{{ old('notas') }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-3">
                <i class="bi bi-send" aria-hidden="true"></i> Enviar comprobante
            </button>
        @endif
    </form>

    <section class="ito-card p-3" aria-labelledby="mc-enviados">
        <h2 class="h6 mb-2" id="mc-enviados">Lo que ya mandaste</h2>
        @forelse($enviados as $c)
            <div class="d-flex justify-content-between align-items-center border-top py-2">
                <div>
                    <div>Pagado el {{ $c->fecha_pago?->format('d/m/Y') }}</div>
                    <div class="text-muted small">${{ number_format((float) $c->monto_total, 0, ',', '.') }} · enviado el {{ $c->created_at?->format('d/m/Y') }}</div>
                </div>
                <span class="badge {{ $c->estado === 'pagado' ? 'text-bg-success' : 'text-bg-secondary' }}">
                    {{ $c->estado === 'pagado' ? 'Registrado' : ($estados[$c->estado] ?? $c->estado) }}
                </span>
            </div>
        @empty
            <p class="text-muted mb-0">Todavía no mandaste ningún comprobante desde tu cuenta.</p>
        @endforelse
    </section>
</div>
@endsection
