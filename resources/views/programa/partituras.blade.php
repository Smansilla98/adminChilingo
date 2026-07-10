@extends('layouts.app')

@section('title', 'Partituras y recursos')
@section('page-title', 'Partituras y recursos')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
        <h2 class="h4 mb-1">Partituras por toque</h2>
        <p class="text-muted mb-0">
            Subí el PDF o la foto de cada toque (una página del libro «Toques Chilinga»)
            o creá partituras nuevas con el <strong>Compositor digital</strong> (estilo Flat.io).
        </p>
    </div>
    <a href="{{ route('programa.index') }}" class="btn btn-outline-secondary btn-sm">Programa completo</a>
</div>

<div class="alert alert-warning py-2 small mb-4">
    <i class="bi bi-info-circle"></i>
    <strong>Formato del libro:</strong> las partituras del PDF son imágenes escaneadas.
    Exportá o fotografiá la página del toque y subila con <strong>«Subir partitura»</strong>.
    Para crear partituras nuevas usá <strong>«Compositor»</strong> (editor tipo Flat.io).
</div>

<form method="GET" class="row g-2 align-items-end mb-4">
    <div class="col-md-6">
        <label class="form-label small mb-1" for="buscarToque">Buscar toque</label>
        <input type="search" name="q" id="buscarToque" class="form-control form-control-sm"
               value="{{ $busqueda ?? '' }}" placeholder="Nombre o autor…">
    </div>
    <div class="col-auto">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="pendientes" value="1" id="soloPendientes"
                @checked($pendientes ?? false) onchange="this.form.submit()">
            <label class="form-check-label small" for="soloPendientes">Solo sin partitura</label>
        </div>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Buscar</button>
        @if(($busqueda ?? '') !== '' || ($pendientes ?? false))
            <a href="{{ route('programa.partituras.index') }}" class="btn btn-sm btn-link">Limpiar</a>
        @endif
    </div>
</form>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if($estadoPrograma === 'sin_tabla')
    <div class="alert alert-warning">Falta ejecutar las migraciones del módulo programa.</div>
@elseif($estadoPrograma === 'error')
    <div class="alert alert-danger">No se pudo cargar el listado de toques.</div>
@elseif($estadoPrograma === 'vacio')
    <div class="alert alert-secondary">No hay toques que coincidan con la búsqueda.</div>
@else
    @foreach($años as $num => $label)
        @php $grupo = $porAño->get($num, collect()); @endphp
        @if($grupo->isNotEmpty())
            <div class="panel mb-3">
                <div class="panel-h">
                    <div class="panel-h-title">{{ $label }}</div>
                    <span class="muted small">{{ $grupo->count() }} toques</span>
                </div>
                <div class="panel-b p-0">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 partituras-toques-table">
                            <thead>
                                <tr>
                                    <th>Toque</th>
                                    <th>Partitura</th>
                                    <th class="text-center">Videos</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($grupo as $toque)
                                    @php $rm = $toque->resumen_medios ?? []; @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $toque->nombre }}</div>
                                            @if($toque->autor)
                                                <div class="small text-muted">{{ Str::limit($toque->autor, 48) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($rm['partitura'] ?? false)
                                                <span class="badge bg-success-subtle text-success">
                                                    <i class="bi bi-file-earmark-check"></i>
                                                    {{ Str::limit($rm['partitura_nombre'] ?? 'Cargada', 28) }}
                                                </span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-muted">Sin cargar</span>
                                            @endif
                                            @if($rm['digital'] ?? false)
                                                <span class="badge bg-info-subtle text-info ms-1" title="Partitura digital (Flat o rejilla)">+ digital</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(($rm['videos'] ?? 0) > 0)
                                                <span class="badge bg-success-subtle text-success">{{ $rm['videos'] }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-nowrap">
                                            @if(auth()->user()->isAdmin())
                                                <a href="{{ route('programa.toque.compositor.edit', $toque) }}" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-music-note-beamed"></i>
                                                    {{ ($rm['flat'] ?? false) ? 'Compositor' : 'Crear digital' }}
                                                </a>
                                                <a href="{{ route('programa.toque.partitura.edit', $toque) }}" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-cloud-upload"></i>
                                                    {{ ($rm['partitura'] ?? false) ? 'Cambiar' : 'Subir partitura' }}
                                                </a>
                                            @endif
                                            <a href="{{ route('programa.toque.show', $toque) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
@endif
@endsection
