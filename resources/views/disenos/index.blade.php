@extends('layouts.app')

@section('title', 'Diseños')
@section('page-title', 'Diseño')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="h4 mb-1">Diseños guardados</h2>
        <p class="text-muted mb-0">Flyers, historias, afiches y banners con la paleta de marca.</p>
    </div>
    <a href="{{ route('disenos.create') }}" class="btn btn-primary">+ Nuevo diseño</a>
</div>

<div class="row g-3">
    @forelse($disenos as $diseno)
        <div class="col-md-4 col-lg-3">
            <div class="card h-100">
                @if($diseno->previewUrl())
                    <img src="{{ $diseno->previewUrl() }}" class="card-img-top" alt="Vista previa de {{ $diseno->titulo }}" style="aspect-ratio:4/5;object-fit:cover;">
                @else
                    <div class="card-img-top bg-secondary-subtle d-flex align-items-center justify-content-center text-muted" style="aspect-ratio:4/5;">Sin vista previa</div>
                @endif
                <div class="card-body">
                    <h3 class="h6 mb-1">{{ $diseno->titulo }}</h3>
                    <p class="small text-muted mb-2">{{ $diseno->ancho }}×{{ $diseno->alto }} px</p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('disenos.edit', $diseno) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                        <form method="POST" action="{{ route('disenos.destroy', $diseno) }}" onsubmit="return confirm('¿Eliminar este diseño?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">
            <div class="alert alert-secondary mb-0">Todavía no hay diseños. Creá el primero.</div>
        </div>
    @endforelse
</div>

<div class="mt-3">{{ $disenos->links() }}</div>
@endsection
