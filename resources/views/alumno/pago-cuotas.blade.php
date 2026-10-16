@extends('layouts.app')

@section('title', 'Pago '.$pago->referencia)
@section('page-title', 'Pago '.$pago->referencia)

@php
    $pesos = fn ($n) => '$'.number_format((float) $n, 2, ',', '.');
    $esMP = $pago->origen === 'mercadopago';
@endphp

@section('content')
<div class="ito-page" style="max-width: 760px">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Pago <span class="ito-mono">{{ $pago->referencia }}</span></h1>
            <p class="ito-page-sub">{{ $pago->alumno?->nombre_apellido }} · {{ $pago->items->count() }} {{ $pago->items->count() === 1 ? 'cuota' : 'cuotas' }} · <strong>{{ $pesos($pago->monto_total) }}</strong></p>
        </div>
        <a href="{{ route('alumno.cuota.pagar') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($pago->estaPagado())
        <div class="alert alert-success" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i> <strong>¡Pago registrado!</strong> Tus cuotas ya figuran como pagas.</div>
    @elseif($pago->estado === 'pendiente' || $pago->estado === 'visto')
        <div class="alert alert-info" role="status">
            @if($esMP)
                Mercado Pago avisó el pago. Administración lo está revisando.
            @else
                Recibimos el comprobante. Cuando administración lo confirme, las cuotas quedan registradas.
            @endif
        </div>
    @elseif($esMP)
        <div class="ito-card p-3 mb-3">
            @if($vuelveDeMercadoPago && in_array($estadoMercadoPago, ['approved', 'pending', 'in_process'], true))
                <p class="mb-0"><strong>{{ $estadoMercadoPago === 'approved' ? 'Mercado Pago aprobó el pago.' : 'El pago está en proceso.' }}</strong> En unos minutos queda registrado acá; no hace falta mandar comprobante. Si recargás la página vas a ver el estado.</p>
            @else
                @if($vuelveDeMercadoPago)<p class="text-danger">El pago no se completó.</p>@endif
                <p>Todavía no recibimos el pago de Mercado Pago.</p>
                <form method="POST" action="{{ route('alumno.cuota.pago.cancelar', $pago->referencia) }}" data-confirm="¿Cancelar este pago? Las cuotas vuelven a quedar para pagar." data-confirm-ok="Cancelar pago">
                    @csrf
                    <button class="btn btn-outline-secondary">Cancelar y elegir otra forma de pago</button>
                </form>
            @endif
        </div>
    @else
        {{-- Transferencia: a quién y cuánto --}}
        <section class="ito-card p-3 mb-3" aria-labelledby="pg-datos">
            <h2 class="h6 mb-3" id="pg-datos"><span class="badge rounded-pill text-bg-dark me-1">1</span> Transferí {{ $pesos($pago->monto_total) }}</h2>
            <dl class="row mb-0 g-2">
                @foreach(['alias' => 'Alias', 'cbu' => 'CBU / CVU', 'titular' => 'Titular', 'cuit' => 'CUIT', 'banco' => 'Banco'] as $clave => $nombre)
                    @if(! empty($transferencia[$clave]))
                        <dt class="col-4 text-muted fw-normal">{{ $nombre }}</dt>
                        <dd class="col-8 mb-0 d-flex align-items-center gap-2">
                            <span class="ito-mono fw-semibold text-break">{{ $transferencia[$clave] }}</span>
                            @if(in_array($clave, ['alias', 'cbu'], true))
                                <button type="button" class="btn btn-sm btn-outline-secondary pg-copiar" data-copiar="{{ $transferencia[$clave] }}">Copiar</button>
                            @endif
                        </dd>
                    @endif
                @endforeach
                <dt class="col-4 text-muted fw-normal">Monto</dt>
                <dd class="col-8 mb-0 d-flex align-items-center gap-2"><span class="ito-mono fw-semibold">{{ $pesos($pago->monto_total) }}</span><button type="button" class="btn btn-sm btn-outline-secondary pg-copiar" data-copiar="{{ number_format((float) $pago->monto_total, 2, ',', '') }}">Copiar</button></dd>
                <dt class="col-4 text-muted fw-normal">Concepto</dt>
                <dd class="col-8 mb-0 d-flex align-items-center gap-2"><span class="ito-mono fw-semibold">{{ $pago->referencia }}</span><button type="button" class="btn btn-sm btn-outline-secondary pg-copiar" data-copiar="{{ $pago->referencia }}">Copiar</button></dd>
            </dl>
            <p class="small text-muted mt-3 mb-2">Poné <strong>{{ $pago->referencia }}</strong> en el concepto o la descripción: así sabemos de quién es la transferencia.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="https://www.mercadopago.com.ar/home" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Abrir Mercado Pago</a>
                <a href="https://www.modo.com.ar/" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Abrir MODO</a>
                <a href="https://www.uala.com.ar/" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Abrir Ualá</a>
            </div>
        </section>

        <form method="POST" action="{{ route('alumno.cuota.pago.comprobante', $pago->referencia) }}" enctype="multipart/form-data" class="ito-card p-3 mb-3">
            @csrf
            <h2 class="h6 mb-2"><span class="badge rounded-pill text-bg-dark me-1">2</span> Subí el comprobante</h2>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="pg-fecha">¿Qué día transferiste?</label>
                    <input type="date" id="pg-fecha" name="fecha_pago" class="form-control" value="{{ old('fecha_pago', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="pg-archivo">Foto o PDF</label>
                    <input type="file" id="pg-archivo" name="comprobante" class="form-control" accept="image/*,application/pdf" required>
                </div>
            </div>
            <button class="btn btn-primary btn-lg w-100 mt-3"><i class="bi bi-send" aria-hidden="true"></i> Enviar comprobante</button>
        </form>
        <form method="POST" action="{{ route('alumno.cuota.pago.cancelar', $pago->referencia) }}" class="text-center mb-3" data-confirm="¿Cancelar este pago? Las cuotas vuelven a quedar para pagar." data-confirm-ok="Cancelar pago">
            @csrf
            <button class="btn btn-link text-muted">Cancelar este pago</button>
        </form>
    @endif

    <section class="ito-card p-3" aria-labelledby="pg-detalle">
        <h2 class="h6 mb-2" id="pg-detalle">Qué incluye</h2>
        @foreach($pago->items as $it)
            <div class="d-flex justify-content-between border-top py-2 gap-3">
                <div>
                    <div>{{ $it->etiqueta() }}</div>
                    <div class="text-muted small">{{ collect([$it->bloque?->nombre, $it->bloque?->sede?->nombre, $it->bloque?->nombreTitular() ? 'Profe: '.$it->bloque->nombreTitular() : null])->filter()->implode(' · ') }}</div>
                </div>
                <div class="fw-semibold text-nowrap">{{ $pesos($it->monto) }}</div>
            </div>
        @endforeach
    </section>
</div>

<script>
document.querySelectorAll('.pg-copiar').forEach((b) => b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.copiar); b.textContent = '¡Copiado!'; } catch { b.textContent = 'No se pudo copiar'; }
    setTimeout(() => { b.textContent = 'Copiar'; }, 1800);
}));
</script>
@endsection
