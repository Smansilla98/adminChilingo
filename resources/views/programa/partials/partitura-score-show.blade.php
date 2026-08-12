@php
    use App\Support\PartituraScore;
    $score = $medios['partitura_score'] ?? null;
    $tieneScore = is_array($score) && ! empty($score['sections']) && PartituraScore::tieneGolpes($score);
    $resumen = $tieneScore ? PartituraScore::resumen($score) : null;
    $instrumentosScore = $tieneScore
        ? collect($score['instruments'] ?? [])->pluck('id')->filter()->values()->all()
        : [];
@endphp

@if($tieneScore)
<div class="card mb-3 border-warning">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h3 class="h6 mb-0"><i class="bi bi-music-note-list"></i> Partitura interactiva</h3>
        <div class="d-flex flex-wrap gap-2">
            @if(auth()->user()?->isAdmin())
                <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-sm btn-outline-warning">
                    <i class="bi bi-pencil-square"></i> Editor de partitura
                </a>
            @endif
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Escuchá el toque, cambiá el tempo y silenciá tambores para estudiar tu parte.
            @if($resumen)
                <span class="d-block mt-1">
                    {{ $resumen['compases'] ?? 0 }} compases ·
                    {{ $resumen['partes'] ?? 0 }} partes ·
                    {{ $score['tempo'] ?? 100 }} BPM ·
                    {{ ($score['timeSignature']['num'] ?? 4) }}/{{ ($score['timeSignature']['den'] ?? 4) }}
                </span>
            @endif
        </p>

        <div
            data-partitura-viewer
            data-score="{{ json_encode($score, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
            data-controles="1"
        ></div>

        @if(count($instrumentosScore) > 0)
        <div class="mt-3">
            <p class="small text-muted mb-2">Partes separadas por tambor (para imprimir):</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach($instrumentosScore as $instId)
                    @if(array_key_exists($instId, PartituraScore::INSTRUMENTOS))
                    <a
                        href="{{ route('programa.toque.parte', ['programaRitmo' => $programaRitmo, 'instrumento' => $instId]) }}"
                        class="btn btn-sm btn-outline-secondary"
                        target="_blank"
                        rel="noopener"
                    >
                        <i class="bi bi-file-earmark-music"></i> {{ PartituraScore::INSTRUMENTOS[$instId] }}
                    </a>
                    @endif
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endif
