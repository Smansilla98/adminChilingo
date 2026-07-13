@php
    $vex = $medios['partitura_vexflow'] ?? null;
    $tieneVex = ! empty($medios['partitura_vexflow']['sections']) || ! empty($medios['partitura_vexflow']['hits']);
@endphp

@if($tieneVex)
<div class="card mb-3 border-warning">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="h6 mb-0"><i class="bi bi-music-note-list"></i> Partitura digital</h3>
        <button
            type="button"
            class="btn btn-sm btn-outline-secondary"
            data-bs-toggle="collapse"
            data-bs-target="#partituraLecturaEnVex"
            aria-expanded="false"
        >
            <i class="bi bi-book"></i> Leyenda de lectura
        </button>
    </div>
    <div class="card-body">
        <div
            class="programa-partitura-preview mb-3"
            data-partitura-viewer
            data-partitura-json="{{ e(json_encode($vex, JSON_UNESCAPED_UNICODE)) }}"
        ></div>
        <div class="collapse" id="partituraLecturaEnVex">
            @include('programa.partials.partitura-lectura', ['collapseId' => 'partituraLecturaVexInner', 'expanded' => true])
        </div>
        <p class="form-text mb-0">Generada desde el editor de la escuela. Si también hay PDF o imagen, conviene practicar con ambas.</p>
    </div>
</div>
@endif
