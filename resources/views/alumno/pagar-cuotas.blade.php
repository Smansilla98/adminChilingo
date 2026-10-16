@extends('layouts.app')

@section('title', 'Pagar cuotas')
@section('page-title', 'Pagar cuotas')

@php
    $pesos = fn ($n) => '$'.number_format((float) $n, 0, ',', '.');
    $pagables = $pendientes->whereNull('en_curso');
@endphp

@section('content')
<div class="ito-page" style="max-width: 760px">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Pagar cuotas</h1>
            <p class="ito-page-sub">Marcá lo que querés pagar, de todos tus bloques juntos, y elegí cómo. Si ya pagaste por tu cuenta, <a href="{{ route('alumno.cuota') }}">mandá el comprobante acá</a>.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($enCurso->isNotEmpty())
        <section class="ito-card p-3 mb-3" aria-labelledby="pc-curso">
            <h2 class="h6 mb-2" id="pc-curso">Pagos que empezaste</h2>
            @foreach($enCurso as $p)
                <a href="{{ route('alumno.cuota.pago', $p->referencia) }}" class="d-flex justify-content-between align-items-center border-top py-2 text-decoration-none">
                    <span><strong class="ito-mono">{{ $p->referencia }}</strong> <span class="text-muted">· {{ $p->origen === 'mercadopago' ? 'Mercado Pago' : 'Transferencia' }} · {{ $p->created_at?->format('d/m') }}</span></span>
                    <span class="fw-semibold">{{ $pesos($p->monto_total) }} <i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                </a>
            @endforeach
        </section>
    @endif

    @if(! $transferencia && ! $mercadoPago)
        <div class="alert alert-info" role="status">Todavía no está habilitado el pago desde el sistema. Por ahora pagá como siempre y <a href="{{ route('alumno.cuota') }}">mandá el comprobante</a>.</div>
    @else
    <form method="POST" action="{{ route('alumno.cuota.pagar.store') }}" class="ito-card p-3" id="pc-form">
        @csrf
        @if($fichas->count() > 1)
            <div class="mb-3">
                <label class="form-label" for="pc-alumno">¿De quién son las cuotas?</label>
                <select id="pc-alumno" class="form-select" onchange="location.search = '?alumno=' + this.value">
                    @foreach($fichas as $f)<option value="{{ $f->id }}" @selected($f->id === $alumno->id)>{{ $f->nombre_apellido }}</option>@endforeach
                </select>
            </div>
        @endif
        <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">

        <h2 class="h6 mb-2"><span class="badge rounded-pill text-bg-dark me-1">1</span> ¿Qué cuotas pagás?</h2>
        @forelse($pendientes as $c)
            <div class="form-check border rounded p-3 mb-2 ps-5 {{ $c['en_curso'] ? 'bg-light' : '' }}">
                <input class="form-check-input pc-cuota" type="checkbox" name="cuota_ids[]" value="{{ $c['cuota_id'] }}" id="pc-c{{ $c['cuota_id'] }}"
                       data-monto="{{ $c['monto'] }}" @checked(in_array($c['cuota_id'], old('cuota_ids', []))) @disabled($c['en_curso'])>
                <label class="form-check-label w-100" for="pc-c{{ $c['cuota_id'] }}">
                    <span class="d-flex justify-content-between gap-2">
                        <strong>{{ $c['cuota'] }}</strong>
                        <strong class="text-nowrap">{{ $pesos($c['monto']) }}</strong>
                    </span>
                    <span class="d-block text-muted small">
                        {{ collect([$c['bloque'], $c['sede'], $c['profesor'] ? 'Profe: '.$c['profesor'] : null])->filter()->implode(' · ') }}
                        @if($c['beca']) · {{ $c['beca'] }} @endif
                    </span>
                    @if($c['vencida'])<span class="badge text-bg-warning mt-1">Vencida</span>@endif
                    @if($c['en_curso'])<span class="badge text-bg-secondary mt-1">Pago en curso {{ $c['en_curso'] }}</span>@endif
                </label>
            </div>
        @empty
            @if($resumen['emitidas'] === 0)
                <div class="alert alert-warning mb-0" role="status">
                    <strong>No encontramos cuotas emitidas para tus bloques en {{ $resumen['anios'] }}.</strong>
                    Si sabés que debés, podés pagar igual desde «Pagá igual», acá abajo.
                </div>
            @else
                <div class="alert alert-success mb-0" role="status">
                    <strong>No hay cuotas para pagar desde acá.</strong>
                    De {{ $resumen['emitidas'] }} {{ $resumen['emitidas'] === 1 ? 'cuota' : 'cuotas' }} de {{ $resumen['anios'] }}:
                    {{ collect([
                        $resumen['pagadas'] ? $resumen['pagadas'].' '.($resumen['pagadas'] === 1 ? 'paga' : 'pagas') : null,
                        $resumen['becadas'] ? $resumen['becadas'].' con beca completa' : null,
                        $resumen['parciales'] ? $resumen['parciales'].' con pago parcial' : null,
                    ])->filter()->implode(', ') ?: 'ninguna con saldo' }}.
                    @if($resumen['parciales'])<br>Lo que falta de un pago parcial se arregla con administración.@endif
                </div>
            @endif
        @endforelse

        @if($fichas->count() > 1)
            <p class="small text-muted mt-2 mb-0">Estás viendo las cuotas de <strong>{{ $alumno->nombre_apellido }}</strong> ({{ $alumno->bloques->pluck('nombre')->join(', ') ?: 'sin bloque' }}).</p>
        @endif

        @if($pagables->isNotEmpty())
            @if($pagables->count() > 1)
                <button type="button" class="btn btn-link btn-sm px-0" id="pc-todas">Marcar todas</button>
            @endif

            <h2 class="h6 mt-3 mb-2"><span class="badge rounded-pill text-bg-dark me-1">2</span> ¿Cómo pagás?</h2>
            <div class="d-grid gap-2">
                @if($mercadoPago)
                    <label class="border rounded p-3 d-flex gap-3 align-items-start">
                        <input class="form-check-input mt-1" type="radio" name="medio" value="mercadopago" @checked(old('medio', 'mercadopago') === 'mercadopago')>
                        <span><strong>Mercado Pago</strong><span class="d-block text-muted small">Con tu cuenta, dinero en la app, tarjeta o débito. Queda registrado solo, sin mandar comprobante.</span></span>
                    </label>
                @endif
                @if($transferencia)
                    <label class="border rounded p-3 d-flex gap-3 align-items-start">
                        <input class="form-check-input mt-1" type="radio" name="medio" value="transferencia" @checked(old('medio', $mercadoPago ? null : 'transferencia') === 'transferencia')>
                        <span><strong>Transferencia</strong><span class="d-block text-muted small">Desde tu banco o billetera (Mercado Pago, Ualá, MODO, Cuenta DNI…) al alias {{ $transferencia['alias'] ?? 'de la escuela' }}. Después subís el comprobante.</span></span>
                    </label>
                @endif
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                <span class="text-muted">Total</span>
                <strong class="fs-4" id="pc-total" aria-live="polite">$0</strong>
            </div>
            <button type="submit" class="btn btn-primary btn-lg w-100 mt-2" id="pc-enviar" disabled>Continuar</button>
        @endif
    </form>

    @if(count($opcionesExtra))
        @php
            $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            $abrirExtra = $pagables->isEmpty() || $errors->hasAny(['bloque_id', 'monto', 'mes', 'anio']);
        @endphp
        <details class="ito-card p-3 mt-3" @if($abrirExtra) open @endif>
            <summary class="fw-semibold" style="cursor: pointer">¿No figura lo que querés pagar? Pagá igual</summary>
            <p class="text-muted small mt-2">Elegí el bloque y el mes. Si la cuota todavía no está cargada, administración la engancha cuando confirme el pago.</p>
            <form method="POST" action="{{ route('alumno.cuota.pagar.extra') }}" class="row g-3" id="px-form">
                @csrf
                <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">
                <div class="col-12">
                    <label class="form-label" for="px-bloque">Bloque</label>
                    <select id="px-bloque" name="bloque_id" class="form-select" required>
                        @foreach($opcionesExtra as $o)
                            <option value="{{ $o['id'] }}" data-monto="{{ $o['monto'] }}" @selected((int) old('bloque_id') === $o['id'])>
                                {{ collect([$o['nombre'], $o['sede'], $o['profesor'] ? 'Profe: '.$o['profesor'] : null])->filter()->implode(' · ') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-7">
                    <label class="form-label" for="px-mes">Mes</label>
                    <select id="px-mes" name="mes" class="form-select">
                        @foreach($meses as $n => $nombre)<option value="{{ $n }}" @selected((int) old('mes', now()->month) === $n)>{{ $nombre }}</option>@endforeach
                    </select>
                </div>
                <div class="col-5">
                    <label class="form-label" for="px-anio">Año</label>
                    <select id="px-anio" name="anio" class="form-select">
                        @foreach(range(now()->year - 1, now()->year + 1) as $a)<option value="{{ $a }}" @selected((int) old('anio', now()->year) === $a)>{{ $a }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="px-monto">Monto</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" id="px-monto" name="monto" class="form-control" min="1" step="0.01" inputmode="decimal" value="{{ old('monto') }}" required aria-describedby="px-monto-ayuda">
                    </div>
                    <div id="px-monto-ayuda" class="form-text">Te sugerimos el valor de la última cuota de ese bloque.</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="px-concepto">Aclaración <span class="text-muted">(opcional)</span></label>
                    <input type="text" id="px-concepto" name="concepto" class="form-control" maxlength="200" value="{{ old('concepto') }}" placeholder="Ej.: cuota de octubre">
                </div>
                <div class="col-12 d-grid gap-2">
                    @if($mercadoPago)
                        <label class="border rounded p-2 d-flex gap-2 align-items-center"><input class="form-check-input m-0" type="radio" name="medio" value="mercadopago" @checked(old('medio', 'mercadopago') === 'mercadopago')> Mercado Pago</label>
                    @endif
                    @if($transferencia)
                        <label class="border rounded p-2 d-flex gap-2 align-items-center"><input class="form-check-input m-0" type="radio" name="medio" value="transferencia" @checked(old('medio', $mercadoPago ? null : 'transferencia') === 'transferencia')> Transferencia a {{ $transferencia['alias'] ?? 'la cuenta de la escuela' }}</label>
                    @endif
                </div>
                <div class="col-12"><button type="submit" class="btn btn-primary btn-lg w-100">Continuar</button></div>
            </form>
        </details>
    @endif
    @endif
</div>

<script>
(() => {
    const bloque = document.getElementById('px-bloque');
    const monto = document.getElementById('px-monto');
    if (bloque && monto) {
        const sugerir = () => { const m = bloque.selectedOptions[0]?.dataset.monto; if (m && !monto.dataset.tocado) monto.value = Math.round(Number(m)); };
        monto.addEventListener('input', () => { monto.dataset.tocado = '1'; });
        bloque.addEventListener('change', sugerir);
        if (!monto.value) sugerir();
    }
})();
(() => {
    const checks = [...document.querySelectorAll('.pc-cuota:not(:disabled)')];
    const total = document.getElementById('pc-total');
    const enviar = document.getElementById('pc-enviar');
    if (!total) return;
    const pesos = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 });
    const actualizar = () => {
        const suma = checks.filter((c) => c.checked).reduce((s, c) => s + Number(c.dataset.monto), 0);
        total.textContent = pesos.format(suma);
        enviar.disabled = suma <= 0;
    };
    checks.forEach((c) => c.addEventListener('change', actualizar));
    document.getElementById('pc-todas')?.addEventListener('click', () => { checks.forEach((c) => { c.checked = true; }); actualizar(); });
    actualizar();
})();
</script>
@endsection
