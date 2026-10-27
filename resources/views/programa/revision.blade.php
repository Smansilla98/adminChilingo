@extends('layouts.app')

@section('title', 'Partituras para revisar')
@section('page-title', 'Programa — Partituras para revisar')

@push('styles')
<style>
    .rv-toque { scroll-margin-top: 1rem; }
    .rv-toque:target { outline: 2px solid var(--bs-warning); outline-offset: 2px; }
    .rv-marca { display: grid; grid-template-columns: minmax(9rem, 14rem) 1fr auto; gap: .75rem; align-items: center; padding: .7rem 1rem; border-top: 1px solid var(--bs-border-color); }
    .rv-marca:first-child { border-top: 0; }
    .rv-donde { font-weight: 600; }
    .rv-donde small { display: block; font-weight: 400; color: var(--bs-secondary-color); }
    .rv-nota { margin: 0; }
    @media (max-width: 767.98px) {
        .rv-marca { grid-template-columns: 1fr; }
    }
</style>
@endpush

@section('content')
<div class="ito-page">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Partituras para revisar</h1>
            <p class="ito-page-sub">Pasajes que al transcribir el cuadernillo quedaron dudosos. Abrí el editor, compará con el cuadernillo y, si está bien (o ya lo corregiste), marcalo como validado.</p>
        </div>
        <a href="{{ route('programa.partituras.index') }}" class="btn btn-outline-secondary"><i class="bi bi-music-note-list"></i> Partituras</a>
    </div>

    @foreach(['success' => 'success', 'warning' => 'warning'] as $clave => $tono)
        @if(session($clave))<div class="alert alert-{{ $tono }}" role="status">{{ session($clave) }}</div>@endif
    @endforeach

    @if($total === 0)
        <div class="ito-card p-4 text-center">
            <i class="bi bi-check2-circle fs-1 text-success" aria-hidden="true"></i>
            <p class="mb-0 mt-2"><strong>No queda nada por revisar.</strong> Todas las partituras están validadas por la escuela.</p>
        </div>
    @else
        <div class="alert alert-info" role="status">
            Quedan <strong>{{ $total }}</strong> {{ $total === 1 ? 'pasaje' : 'pasajes' }} en <strong>{{ $pendientes->count() }}</strong> {{ $pendientes->count() === 1 ? 'toque' : 'toques' }}.
            Cada uno dice qué instrumento y qué tiempo hay que mirar.
        </div>

        @foreach($pendientes as $t)
        @php $r = $t['ritmo']; @endphp
        <section class="ito-card p-0 mb-3 rv-toque" id="toque-{{ $r->id }}" aria-labelledby="rv-t-{{ $r->id }}">
            <header class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-bottom">
                <h2 class="h6 mb-0" id="rv-t-{{ $r->id }}">{{ $r->nombre }} <span class="text-muted fw-normal">· {{ $r->año }}° año · {{ count($t['marcas']) }} {{ count($t['marcas']) === 1 ? 'pasaje' : 'pasajes' }}</span></h2>
                <a href="{{ route('programa.toque.editor', $r) }}" class="btn btn-sm btn-primary"><i class="bi bi-pencil-square"></i> Abrir en el editor</a>
            </header>
            <div>
                @foreach($t['marcas'] as $m)
                <div class="rv-marca">
                    <div class="rv-donde">{{ $m['seccion'] ?: 'Sección' }}<small>Compás {{ $m['compas'] }}</small></div>
                    <p class="rv-nota">{{ $m['nota'] }}</p>
                    <form method="POST" action="{{ route('programa.revision.validar', $r->id) }}">
                        @csrf
                        <input type="hidden" name="seccion" value="{{ $m['seccion'] }}">
                        <input type="hidden" name="compas" value="{{ $m['compas'] }}">
                        <input type="hidden" name="nota" value="{{ $m['nota'] }}">
                        <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check2"></i> Validado por la escuela</button>
                    </form>
                </div>
                @endforeach
            </div>
        </section>
        @endforeach
    @endif

    @if($validadas->isNotEmpty())
    <section class="ito-card p-3 mt-4" aria-labelledby="rv-hechas">
        <h2 class="h6" id="rv-hechas">Últimas validaciones</h2>
        <ul class="list-unstyled small mb-0">
            @foreach($validadas as $v)
            <li class="py-1 border-top">
                <strong>{{ $v->programaRitmo?->nombre }}</strong> · {{ $v->seccion }} compás {{ $v->compas }} — {{ $v->nota }}
                <span class="text-muted">· {{ $v->validador?->name ?? 'Alguien' }}, {{ $v->validado_at->diffForHumans() }}</span>
            </li>
            @endforeach
        </ul>
    </section>
    @endif
</div>
@endsection
