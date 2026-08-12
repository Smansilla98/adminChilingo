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
<div class="prog-score-card">
    <div class="prog-score-card__head">
        <div>
            <p class="biblio-eyebrow mb-1">Partitura interactiva</p>
            <h2>Escuchá y estudia tu parte</h2>
            <p class="small text-muted mb-0">
                Reproducí el toque, cambiá el tempo y silenciá tambores.
                @if($resumen)
                    {{ $resumen['compases'] ?? 0 }} compases ·
                    {{ $resumen['partes'] ?? 0 }} partes ·
                    {{ $score['tempo'] ?? 100 }} BPM ·
                    {{ ($score['timeSignature']['num'] ?? 4) }}/{{ ($score['timeSignature']['den'] ?? 4) }}
                @endif
            </p>
        </div>
        <div class="prog-cta-row">
            @if(auth()->user()?->isAdmin())
                <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-sm btn-warning">
                    <i class="bi bi-pencil-square"></i> Editar
                </a>
            @endif
        </div>
    </div>
    <div class="prog-score-card__body">
        <div
            data-partitura-viewer
            data-score="{{ json_encode($score, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
            data-controles="1"
        ></div>

        @if(count($instrumentosScore) > 0)
        <div class="mt-3">
            <p class="small text-muted mb-2">Imprimí o estudiá un tambor solo:</p>
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
