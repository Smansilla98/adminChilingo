@extends('layouts.app')

@section('title', 'Gestión del programa')
@section('page-title', 'Programa — Gestión de toques')

@push('styles')
<style>
    .gp-fila { display: grid; grid-template-columns: 2.5rem minmax(12rem, 2fr) 8.5rem 9rem 9rem minmax(10rem, 1.5fr) auto; gap: .75rem; align-items: center; padding: .75rem 1rem; border-top: 1px solid var(--bs-border-color); }
    .gp-fila:first-of-type { border-top: 0; }
    .gp-fila.is-retirado { background: var(--bs-tertiary-bg); }
    .gp-fila.is-retirado .gp-nombre { text-decoration: line-through; text-decoration-color: var(--bs-secondary-color); }
    .gp-cabecera { font-size: .75rem; text-transform: uppercase; color: var(--bs-secondary-color); padding-top: .5rem; padding-bottom: .5rem; }
    .gp-orden { font-variant-numeric: tabular-nums; color: var(--bs-secondary-color); }
    .gp-antes { font-size: .8rem; color: var(--bs-secondary-color); margin: .25rem 0 0; }
    .gp-fila:target { outline: 2px solid var(--bs-warning); outline-offset: -2px; }
    @media (max-width: 991.98px) {
        .gp-fila { grid-template-columns: 1fr 1fr; }
        .gp-fila > .gp-orden { display: none; }
        .gp-fila > .gp-col-nombre, .gp-fila > .gp-col-nota, .gp-fila > .gp-col-acciones { grid-column: 1 / -1; }
        .gp-cabecera { display: none; }
    }
    @media (min-width: 992px) {
        .gp-etiqueta-movil { display: none; }
        /* En escritorio el encabezado de la columna ya lo dice; queda para lectores de pantalla. */
        .gp-switch-texto { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    }
</style>
@endpush

@section('content')
<div class="ito-page">
    <div class="ito-page-head">
        <div>
            <h1 class="ito-page-title">Gestión del programa</h1>
            <p class="ito-page-sub">Año de cada toque, si se sigue tocando, si forma parte del programa y su nombre.</p>
        </div>
        <a href="{{ route('programa.index') }}" class="btn btn-outline-secondary"><i class="bi bi-journal-text"></i> Ver el programa</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <div class="ito-card p-3 mb-3">
        <ul class="small text-muted mb-3 ps-3">
            <li><strong>Se sigue tocando</strong>: si lo apagás, el toque sigue en el programa marcado como «Ya no se toca».</li>
            <li><strong>En el programa</strong>: si lo apagás, sale del programa y de las partituras públicas. No se borra nada: lo podés volver a sumar acá.</li>
            <li><strong>Renombrar</strong>: el nombre viejo queda guardado y se puede seguir buscando. El enlace del toque no cambia.</li>
            <li><strong>Cambiar de año</strong>: el toque pasa al final de su nuevo año.</li>
        </ul>
        <form method="GET" action="{{ route('programa.gestion') }}" class="d-flex flex-wrap align-items-end gap-2">
            <div class="btn-group flex-wrap" role="group" aria-label="Filtrar por estado">
                @foreach($filtros as $clave => $etiqueta)
                    <a href="{{ route('programa.gestion', array_filter(['estado' => $clave === 'todos' ? null : $clave, 'q' => $busqueda ?: null])) }}"
                       class="btn btn-sm {{ $filtro === $clave ? 'btn-primary' : 'btn-outline-secondary' }}"
                       @if($filtro === $clave) aria-current="true" @endif>
                        {{ $etiqueta }} <span class="badge text-bg-light ms-1">{{ $totales[$clave] }}</span>
                    </a>
                @endforeach
            </div>
            @if($filtro !== 'todos')<input type="hidden" name="estado" value="{{ $filtro }}">@endif
            <div class="ms-auto d-flex gap-2">
                <label class="visually-hidden" for="gp-q">Buscar toque</label>
                <input id="gp-q" type="search" name="q" value="{{ $busqueda }}" class="form-control form-control-sm" placeholder="Buscar toque" title="También encuentra nombres anteriores">
                <button class="btn btn-sm btn-secondary" type="submit">Buscar</button>
            </div>
        </form>
    </div>

    @forelse($porAño as $año => $toques)
        <section class="ito-card mb-3" aria-labelledby="gp-anio-{{ $año }}">
            <h2 id="gp-anio-{{ $año }}" class="h6 px-3 pt-3 mb-0">{{ $años[$año] ?? $año.'° Año' }} <span class="text-muted fw-normal">· {{ $toques->count() }}</span></h2>
            <div class="gp-fila gp-cabecera" aria-hidden="true">
                <span>N°</span><span>Nombre</span><span>Año</span><span>Se sigue tocando</span><span>En el programa</span><span>Nota</span><span></span>
            </div>
            @foreach($toques as $t)
                @php $f = 'gp-form-'.$t->id; @endphp
                <div id="toque-{{ $t->id }}" class="gp-fila {{ $t->estaEnPrograma() ? '' : 'is-retirado' }}">
                    <form id="{{ $f }}" method="POST" action="{{ route('programa.gestion.update', array_filter(['programaRitmo' => $t->id, 'estado' => $filtro === 'todos' ? null : $filtro, 'q' => $busqueda ?: null])) }}" hidden>
                        @csrf
                        @method('PUT')
                    </form>
                    <span class="gp-orden">{{ $t->orden }}.</span>
                    <div class="gp-col-nombre">
                        <label class="gp-etiqueta-movil small text-muted" for="gp-nombre-{{ $t->id }}">Nombre</label>
                        <input id="gp-nombre-{{ $t->id }}" form="{{ $f }}" name="nombre" value="{{ $t->nombre }}" required maxlength="255"
                               class="form-control form-control-sm gp-nombre" aria-label="Nombre de {{ $t->nombre }}">
                        @if($t->historialNombres())
                            <p class="gp-antes">Antes: {{ collect($t->historialNombres())->pluck('nombre')->join(', ') }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="gp-etiqueta-movil small text-muted" for="gp-anio-sel-{{ $t->id }}">Año</label>
                        <select id="gp-anio-sel-{{ $t->id }}" form="{{ $f }}" name="año" class="form-select form-select-sm" aria-label="Año de {{ $t->nombre }}">
                            @foreach($años as $num => $etiqueta)
                                <option value="{{ $num }}" @selected((int) $t->año === $num)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" form="{{ $f }}" name="vigente" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" form="{{ $f }}" name="vigente" value="1"
                               id="gp-vigente-{{ $t->id }}" @checked($t->sigueVigente())>
                        <label class="form-check-label small gp-switch-texto" for="gp-vigente-{{ $t->id }}">Se sigue tocando</label>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" form="{{ $f }}" name="en_programa" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" form="{{ $f }}" name="en_programa" value="1"
                               id="gp-programa-{{ $t->id }}" @checked($t->estaEnPrograma())>
                        <label class="form-check-label small gp-switch-texto" for="gp-programa-{{ $t->id }}">En el programa</label>
                    </div>
                    <div class="gp-col-nota">
                        <label class="gp-etiqueta-movil small text-muted" for="gp-nota-{{ $t->id }}">Nota</label>
                        <input id="gp-nota-{{ $t->id }}" form="{{ $f }}" name="estado_nota" value="{{ $t->estado_nota }}" maxlength="500"
                               class="form-control form-control-sm" placeholder="Opcional" aria-label="Nota sobre {{ $t->nombre }}">
                    </div>
                    <div class="gp-col-acciones d-flex gap-1 justify-content-end">
                        <button type="submit" form="{{ $f }}" class="btn btn-sm btn-primary">Guardar</button>
                        @if($t->slug)
                            <a href="{{ route('programa.toque.show', $t) }}" class="btn btn-sm btn-outline-secondary" title="Abrir el toque" aria-label="Abrir {{ $t->nombre }}"><i class="bi bi-box-arrow-up-right"></i></a>
                        @endif
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <div class="ito-card p-4 text-muted">No hay toques con este filtro.</div>
    @endforelse
</div>
@endsection
