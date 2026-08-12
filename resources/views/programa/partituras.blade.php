@extends('layouts.publico')

@section('title', 'Partituras')
@section('publico-brand', 'Partituras')

@section('content')
@php
    $esAdmin = auth()->user()?->isAdmin();
    $hayFiltro = ($busqueda ?? '') !== '' || ($pendientes ?? false) || ($digital ?? false);
@endphp

<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Escuchá · estudiá · tocá</p>
        <h1>Partituras del cuadernillo</h1>
        <p class="biblio-lead">Elegí un toque, escuchalo y silenciá los tambores que no son tuyos. Si sos docencia, también podés editar la partitura.</p>
    </div>
    <div class="prog-hero-actions">
        <a href="{{ route('programa.index') }}" class="btn btn-outline-secondary">Programa</a>
        @if($esAdmin)
        <form action="{{ route('programa.partituras.importar-cuadernillo') }}" method="POST" class="d-inline"
              data-confirm="¿Asignar a cada toque su PDF del Cuadernillo de Toques? Reemplaza el archivo de partitura actual.">
            @csrf
            <button type="submit" class="btn btn-outline-warning btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Cargar PDFs
            </button>
        </form>
        @endif
    </div>
</div>

<form method="GET" action="{{ route('programa.partituras.index') }}" class="biblio-search" role="search">
    <div class="biblio-search-field">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $busqueda ?? '' }}" placeholder="Buscar toque o autor…" aria-label="Buscar toque">
    </div>
    <label class="prog-chip {{ ($digital ?? false) ? 'is-on' : '' }}">
        <input type="checkbox" name="digital" value="1" class="d-none" @checked($digital ?? false) onchange="this.form.submit()">
        Con partitura interactiva
    </label>
    @if($esAdmin)
    <label class="prog-chip {{ ($pendientes ?? false) ? 'is-on' : '' }}">
        <input type="checkbox" name="pendientes" value="1" class="d-none" @checked($pendientes ?? false) onchange="this.form.submit()">
        Sin PDF
    </label>
    @endif
    <button type="submit" class="btn btn-sm btn-outline-secondary">Buscar</button>
    @if($hayFiltro)
        <a href="{{ route('programa.partituras.index') }}" class="btn btn-sm btn-link">Limpiar</a>
    @endif
</form>

@if($porAño->isNotEmpty())
<nav class="prog-year-chips" aria-label="Años">
    @foreach($porAño as $num => $grupo)
        <a class="prog-chip" href="#part-anio-{{ $num }}">{{ $años[$num] ?? $num.'°' }} · {{ $grupo->count() }}</a>
    @endforeach
</nav>
@endif

@if($estadoPrograma === 'sin_tabla')
    <div class="alert alert-warning">Falta ejecutar las migraciones del módulo programa.</div>
@elseif($estadoPrograma === 'error')
    <div class="alert alert-danger">No se pudo cargar el listado de toques.</div>
@elseif($estadoPrograma === 'vacio')
    <div class="alert alert-secondary">No hay toques que coincidan. Probá limpiar el filtro.</div>
@else
    @foreach($años as $num => $label)
        @php $grupo = $porAño->get($num, collect()); @endphp
        @if($grupo->isNotEmpty())
            <section id="part-anio-{{ $num }}" class="mb-4">
                <div class="prog-anio-head">
                    <h2>{{ $label }}</h2>
                    <span class="text-muted small">{{ $grupo->count() }} toques</span>
                </div>
                <div class="prog-toque-grid">
                    @foreach($grupo as $toque)
                        @php $rm = $toque->resumen_medios ?? []; @endphp
                        <article class="prog-toque-tile" style="cursor: default;">
                            <div class="prog-toque-tile__top">
                                <span class="prog-toque-tile__n">Toque {{ $toque->orden }}</span>
                            </div>
                            <h3>{{ $toque->nombre }}</h3>
                            @if($toque->autor)
                                <p class="prog-toque-tile__meta">{{ Str::limit($toque->autor, 52) }}</p>
                            @endif
                            <div class="prog-pills">
                                @if($rm['digital'] ?? false)
                                    <span class="prog-pill prog-pill--ok">Interactiva</span>
                                @endif
                                @if($rm['partitura'] ?? false)
                                    <span class="prog-pill">PDF</span>
                                @endif
                                @if(($rm['videos'] ?? 0) > 0)
                                    <span class="prog-pill">{{ $rm['videos'] }} videos</span>
                                @endif
                            </div>
                            <div class="prog-cta-row mt-1">
                                <a href="{{ route('programa.toque.show', $toque) }}#partitura" class="btn btn-sm btn-primary">
                                    <i class="bi bi-play-fill"></i> {{ ($rm['digital'] ?? false) ? 'Escuchar' : 'Abrir' }}
                                </a>
                                @if($esAdmin)
                                    <a href="{{ route('programa.toque.editor', $toque) }}" class="btn btn-sm btn-warning">
                                        <i class="bi bi-pencil-square"></i> Editar
                                    </a>
                                    <a href="{{ route('programa.toque.partitura.edit', $toque) }}" class="btn btn-sm btn-outline-secondary" title="PDF">
                                        <i class="bi bi-cloud-upload"></i>
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    const form = el.closest('form') || (el.tagName === 'FORM' ? el : null);
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (!confirm(form.getAttribute('data-confirm') || el.getAttribute('data-confirm'))) {
            e.preventDefault();
        }
    });
});
</script>
@endpush
