@php
    $vex = $medios['partitura_vexflow'] ?? null;
    $tieneVex = ! empty($medios['partitura_vexflow']['sections']) || ! empty($medios['partitura_vexflow']['hits']);
@endphp

@if($tieneVex)
<div class="card mb-3 border-warning">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="h6 mb-0"><i class="bi bi-grid-3x3-gap"></i> Mapa del toque</h3>
        @if(auth()->user()?->isAdmin())
            <a href="{{ route('programa.toque.compositor.edit', $programaRitmo) }}" class="btn btn-sm btn-outline-warning">
                <i class="bi bi-pencil"></i> Editar
            </a>
        @endif
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Cada fila es un tambor. Los casilleros marcados son golpes a lo largo del tiempo.
            No hace falta saber leer partitura.
        </p>
        <div
            class="programa-didactic-viewer mb-3"
            data-didactic-viewer
            data-partitura-json="{{ e(json_encode($vex, JSON_UNESCAPED_UNICODE)) }}"
        ></div>

        <details class="programa-staff-details">
            <summary class="small text-muted">Ver pentagrama musical (opcional / avanzado)</summary>
            <div
                class="programa-partitura-preview mt-3"
                data-partitura-viewer
                data-partitura-json="{{ e(json_encode($vex, JSON_UNESCAPED_UNICODE)) }}"
            ></div>
        </details>
    </div>
</div>
@endif
