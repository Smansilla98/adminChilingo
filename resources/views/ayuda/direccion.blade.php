@extends('layouts.app')

@section('title', 'Guía para dirección')
@section('page-title', 'Guía para dirección')

@push('styles')
<style>
    .gd-indice { display: flex; flex-wrap: wrap; gap: .5rem; }
    .gd-tarea { border: 1px solid var(--border); border-radius: .75rem; padding: 1rem 1.1rem; background: var(--s1); }
    .gd-tarea h3 { font-size: 1.05rem; margin: 0 0 .15rem; }
    .gd-donde { font-size: .9rem; color: var(--muted); margin: 0 0 .6rem; }
    .gd-pasos { margin: 0 0 .5rem; padding-left: 1.4rem; }
    .gd-pasos li { margin-bottom: .35rem; line-height: 1.5; }
    .gd-ojo { font-size: .9rem; background: var(--accent-soft); border-radius: .5rem; padding: .5rem .75rem; margin: .5rem 0 0; }
    .gd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: .9rem; }
    @media print {
        .sidebar, .topbar, .recordatorio-chatbot, .gd-noprint, .ito-skip { display: none !important; }
        .gd-tarea { break-inside: avoid; }
        main.content { padding: 0 !important; }
    }
</style>
@endpush

@section('content')
<div class="ito-page">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Guía para dirección</h1>
            <p class="ito-page-sub">Todo lo que podés hacer en el sistema, explicado paso a paso. Buscá lo que querés hacer y tocá «Abrir» para ir directo.</p>
        </div>
        <button type="button" class="btn btn-outline-secondary gd-noprint" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Imprimir</button>
    </div>

    <div class="ito-card p-3 mb-3 gd-noprint">
        <label class="form-label" for="gd-buscar">¿Qué querés hacer?</label>
        <input type="search" id="gd-buscar" class="form-control form-control-lg" placeholder="Una palabra alcanza: cuota, comprobante, profe, mapa…" autocomplete="off">
        <p class="form-text mb-2" id="gd-resultado" aria-live="polite"></p>
        <nav class="gd-indice" aria-label="Temas de la guía">
            @foreach($secciones as $s)
                <a href="#{{ $s['clave'] }}" class="btn btn-sm btn-outline-secondary">{{ $s['titulo'] }}</a>
            @endforeach
        </nav>
    </div>

    @foreach($secciones as $s)
        <section id="{{ $s['clave'] }}" class="mb-4" data-gd-seccion aria-labelledby="gd-t-{{ $s['clave'] }}">
            <h2 class="h5 mb-3" id="gd-t-{{ $s['clave'] }}">{{ $s['titulo'] }}</h2>
            <div class="gd-grid">
                @foreach($s['items'] as $i => $t)
                    <article class="gd-tarea" data-gd-tarea>
                        <h3>Quiero {{ lcfirst($t['quiero']) }}</h3>
                        <p class="gd-donde"><i class="bi bi-signpost" aria-hidden="true"></i> {{ $t['donde'] }}</p>
                        <ol class="gd-pasos">
                            @foreach($t['pasos'] as $paso)<li>{{ $paso }}</li>@endforeach
                        </ol>
                        @if(! empty($t['ojo']))<p class="gd-ojo"><strong>Ojo:</strong> {{ $t['ojo'] }}</p>@endif
                        @if($t['ruta'] && Route::has($t['ruta']))
                            <a href="{{ route($t['ruta']) }}" class="btn btn-sm btn-primary mt-2 gd-noprint">Abrir <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endforeach

    <p class="text-muted small">¿Falta algo en esta guía o algo no coincide con lo que ves? Avisale a quien administra el sistema para corregirla.</p>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var input = document.getElementById('gd-buscar');
    var salida = document.getElementById('gd-resultado');
    var sinTildes = function (t) { return t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
    input.addEventListener('input', function () {
        var q = sinTildes(input.value.trim());
        var visibles = 0;
        document.querySelectorAll('[data-gd-tarea]').forEach(function (t) {
            var ok = !q || sinTildes(t.textContent).indexOf(q) !== -1;
            t.hidden = !ok;
            if (ok) visibles++;
        });
        document.querySelectorAll('[data-gd-seccion]').forEach(function (s) {
            s.hidden = !s.querySelector('[data-gd-tarea]:not([hidden])');
        });
        salida.textContent = q ? (visibles ? visibles + (visibles === 1 ? ' tarea encontrada.' : ' tareas encontradas.') : 'No encontramos nada con esa palabra. Probá con otra.') : '';
    });
})();
</script>
@endpush
