@extends('layouts.diseno-editor')

@section('title', 'Diseños')

@push('vite')
@vite(['resources/css/diseno-canvas.css'])
@endpush

@section('content')
<div class="diseno-home">
    <header class="diseno-home-hero">
        <h1><i class="bi bi-palette-fill"></i> ITO Diseño</h1>
        <p>Diseño de flyers y publicidad: plantillas, Biblioteca de la escuela, export PNG/JPG/PDF/WebP y perfiles DTF/sablón.</p>
        <div class="diseno-home-actions">
            <a href="{{ route('disenos.create') }}" class="diseno-home-btn diseno-home-btn-primary">
                <i class="bi bi-plus-lg"></i> Proyecto nuevo
            </a>
            <a href="{{ route('disenos.create') }}?formato=historia" class="diseno-home-btn">
                <i class="bi bi-phone"></i> Historia 9:16
            </a>
            <a href="{{ route('disenos.create') }}?formato=hoodie_20x40" class="diseno-home-btn">
                <i class="bi bi-person-bounding-box"></i> Hoodie 20×40
            </a>
            <a href="{{ route('disenos.create') }}?formato=banner_web" class="diseno-home-btn">
                <i class="bi bi-easel"></i> Banner web
            </a>
        </div>
    </header>

    <section class="diseno-home-templates">
        <h2>Plantillas rápidas</h2>
        <div class="diseno-home-grid mb-5">
            <a href="{{ route('disenos.create') }}?formato=flyer_feed" class="diseno-home-new-card">
                <i class="bi bi-instagram"></i>
                <strong>Flyer feed</strong>
                <small>1080 × 1350</small>
            </a>
            <a href="{{ route('disenos.create') }}?formato=historia" class="diseno-home-new-card">
                <i class="bi bi-phone"></i>
                <strong>Historia</strong>
                <small>1080 × 1920</small>
            </a>
            <a href="{{ route('disenos.create') }}?formato=post_cuadrado" class="diseno-home-new-card">
                <i class="bi bi-square"></i>
                <strong>Post cuadrado</strong>
                <small>1080 × 1080</small>
            </a>
            <a href="{{ route('disenos.create') }}?formato=afiche_a4" class="diseno-home-new-card">
                <i class="bi bi-file-earmark"></i>
                <strong>Afiche A4</strong>
                <small>150 dpi · 210×297 mm</small>
            </a>
            <a href="{{ route('disenos.create') }}?formato=flyer_a5" class="diseno-home-new-card">
                <i class="bi bi-file-earmark-text"></i>
                <strong>Flyer A5</strong>
                <small>150 dpi · 148×210 mm</small>
            </a>
            <a href="{{ route('disenos.create') }}?formato=banner_web" class="diseno-home-new-card">
                <i class="bi bi-window"></i>
                <strong>Banner web</strong>
                <small>1200 × 628</small>
            </a>
            <a href="{{ route('disenos.create') }}?formato=hoodie_20x40" class="diseno-home-new-card">
                <i class="bi bi-person-bounding-box"></i>
                <strong>Hoodie 20×40</strong>
                <small>300 dpi · DTF / sablón</small>
            </a>
            <a href="{{ route('disenos.create') }}?formato=parche_10x10" class="diseno-home-new-card">
                <i class="bi bi-hexagon"></i>
                <strong>Parche 10×10</strong>
                <small>300 dpi</small>
            </a>
        </div>

        <h2>Mis diseños guardados</h2>
        <div class="diseno-home-grid">
            @forelse($disenos as $diseno)
                @php
                    $puedeEditar = auth()->user()->can('update', $diseno);
                    $puedeBorrar = auth()->user()->can('delete', $diseno);
                    $esPlantilla = $diseno->user_id === null;
                @endphp
                <article class="diseno-home-card-wrap">
                    <a href="{{ route('disenos.edit', $diseno) }}" class="diseno-home-card">
                        <div class="diseno-home-card-thumb">
                            @if($diseno->previewUrl())
                                <img src="{{ $diseno->previewUrl() }}" alt="Vista previa de {{ $diseno->titulo }}">
                            @else
                                <i class="bi bi-image text-muted" style="font-size:2.5rem;"></i>
                            @endif
                        </div>
                        <div class="diseno-home-card-body">
                            <h3>{{ $diseno->titulo }}
                                @if($esPlantilla)
                                    <span class="diseno-home-badge">Estudio</span>
                                @elseif(!$puedeEditar)
                                    <span class="diseno-home-badge">Lectura</span>
                                @endif
                            </h3>
                            <p>{{ $diseno->ancho }}×{{ $diseno->alto }} px
                                @if($diseno->user)
                                    · {{ $diseno->user->name }}
                                @endif
                            </p>
                        </div>
                    </a>
                    @if($puedeBorrar)
                    <form method="POST"
                          action="{{ route('disenos.destroy', $diseno) }}"
                          class="diseno-home-card-delete"
                          onsubmit="return confirm('¿Eliminar «{{ addslashes($diseno->titulo) }}»? No se puede deshacer.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="diseno-btn diseno-btn-ghost diseno-btn-sm" title="Eliminar diseño">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    </form>
                    @endif
                </article>
            @empty
                <p class="diseno-hint" style="grid-column:1/-1;">Todavía no hay diseños. Empezá con «Proyecto nuevo».</p>
            @endforelse
        </div>

        @if($disenos->hasPages())
            <div class="mt-4 d-flex justify-content-center">
                {{ $disenos->links() }}
            </div>
        @endif
    </section>

    <p class="text-center mt-5">
        <a href="{{ route('dashboard') }}" class="diseno-hint" style="color:#888;text-decoration:none;">← Volver al panel ITO</a>
    </p>
</div>
@endsection
