@extends('layouts.app')

@section('title', 'Biblioteca — moderación')
@section('page-title', 'Biblioteca')

@section('content')
<div class="ito-page">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Contenido público</p>
            <h1 class="ito-page-title">Moderar biblioteca</h1>
            <p class="ito-page-sub">Ocultá o eliminá aportes. La exploración pública está en /biblioteca</p>
        </div>
        <div class="ito-page-actions">
            <a href="{{ route('biblioteca.index') }}" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">Ver pública</a>
            <a href="{{ route('biblioteca.create') }}" class="btn btn-primary btn-sm" target="_blank" rel="noopener">Subir</a>
        </div>
    </div>

    @if(!empty($sinTabla))
        <div class="alert alert-warning">Falta migrar las tablas de la biblioteca.</div>
    @else
        <form method="GET" class="ito-toolbar-filters d-flex flex-wrap gap-2 align-items-end mb-3">
            <div class="ito-field">
                <label>Buscar</label>
                <input type="search" name="q" class="form-control form-control-sm" value="{{ $q }}">
            </div>
            <div class="ito-field">
                <label>Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="publicado" @selected($estado === 'publicado')>Publicado</option>
                    <option value="oculto" @selected($estado === 'oculto')>Oculto</option>
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-secondary">Filtrar</button>
        </form>

        <div class="ito-card">
            <div class="ito-table-wrap">
                <table class="ito-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Tags</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->titulo }}</div>
                                    @if($item->autor_nombre)
                                        <div class="small text-muted">{{ $item->autor_nombre }}</div>
                                    @endif
                                </td>
                                <td>{{ \App\Models\BibliotecaItem::TIPOS[$item->tipo] ?? $item->tipo }}</td>
                                <td class="small">
                                    @forelse($item->tags as $t)
                                        #{{ $t->nombre }}@if(!$loop->last) @endif
                                    @empty
                                        —
                                    @endforelse
                                </td>
                                <td>
                                    <span class="badge {{ $item->estado === 'publicado' ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-muted' }}">
                                        {{ $item->estado }}
                                    </span>
                                </td>
                                <td class="ito-mono small">{{ $item->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-end text-nowrap">
                                    @if($item->archivoUrl())
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ $item->archivoUrl() }}" target="_blank" rel="noopener">Ver</a>
                                    @endif
                                    <form action="{{ route('biblioteca.admin.toggle', $item) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-secondary">{{ $item->estado === 'publicado' ? 'Ocultar' : 'Publicar' }}</button>
                                    </form>
                                    <form action="{{ route('biblioteca.admin.destroy', $item) }}" method="POST" class="d-inline" data-confirm="¿Eliminar este material?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted text-center">Sin aportes todavía.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($items, 'links'))
                <div class="ito-footer">{{ $items->links() }}</div>
            @endif
        </div>
    @endif
</div>
@endsection
