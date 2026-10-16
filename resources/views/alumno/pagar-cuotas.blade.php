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
            <div class="alert alert-success mb-0" role="status">¡Estás al día! No hay cuotas para pagar.</div>
        @endforelse

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
    @endif
</div>

<script>
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
