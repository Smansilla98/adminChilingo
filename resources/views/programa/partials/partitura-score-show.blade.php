@php
    use App\Support\PartituraScore;
    use App\Support\ProgramaRitmoMedios;
    $score = $medios['partitura_score'] ?? null;
    $tieneScore = is_array($score) && ! empty($score['sections']) && PartituraScore::tieneGolpes($score);
    $resumen = $tieneScore ? PartituraScore::resumen($score) : null;
    $ultimaEdicion = ProgramaRitmoMedios::ultimaEdicion(is_array($medios) ? $medios : []);
    $instrumentosScore = $tieneScore
        ? collect($score['instruments'] ?? [])->pluck('id')->filter()->values()->all()
        : [];
@endphp

    @php
        $ficha = $programaRitmo->mediosNormalizados()['ficha'] ?? null;
        $puedeEditarFicha = (bool) auth()->user()?->can('partituras.admin');
        $instrumentosFicha = \App\Support\PartituraScore::INSTRUMENTOS;
    @endphp
    @if($ficha || $puedeEditarFicha)
    <section class="prog-ficha mb-3" aria-label="Ficha del toque">
        @if($ficha)
        <dl class="row small mb-2">
            @if(!empty($ficha['origen']))
                <dt class="col-sm-3">Origen</dt><dd class="col-sm-9">{{ $ficha['origen'] }}</dd>
            @endif
            @if(!empty($ficha['compas']))
                <dt class="col-sm-3">Compás</dt><dd class="col-sm-9">{{ $ficha['compas'] }}</dd>
            @endif
            @if(!empty($ficha['tempo_sugerido']))
                <dt class="col-sm-3">Tempo sugerido</dt><dd class="col-sm-9">{{ $ficha['tempo_sugerido'] }} bpm</dd>
            @endif
            @if(!empty($ficha['instrumentos']))
                <dt class="col-sm-3">Instrumentos</dt>
                <dd class="col-sm-9">{{ collect($ficha['instrumentos'])->map(fn ($id) => $instrumentosFicha[$id] ?? $id)->implode(', ') }}</dd>
            @endif
            @if(!empty($ficha['nota']))
                <dt class="col-sm-3">Nota de la escuela</dt><dd class="col-sm-9">{{ $ficha['nota'] }}</dd>
            @endif
        </dl>
        @endif
        @if($puedeEditarFicha)
        <details>
            <summary class="small">Editar la ficha del toque</summary>
            <form method="POST" action="{{ route('programa.toque.ficha', $programaRitmo) }}" class="mt-2">
                @csrf
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small" for="ficha-origen">Origen</label>
                        <input id="ficha-origen" class="form-control form-control-sm" name="origen" maxlength="120" value="{{ $ficha['origen'] ?? '' }}" placeholder="Ej. Ensayo de Ciudad Jardín, 2019">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="ficha-compas">Compás</label>
                        <input id="ficha-compas" class="form-control form-control-sm" name="compas" maxlength="8" value="{{ $ficha['compas'] ?? '' }}" placeholder="4/4">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small" for="ficha-tempo">Tempo sugerido (bpm)</label>
                        <input id="ficha-tempo" class="form-control form-control-sm" type="number" min="30" max="260" name="tempo_sugerido" value="{{ $ficha['tempo_sugerido'] ?? '' }}">
                    </div>
                    <div class="col-12">
                        <span class="form-label small d-block">Instrumentos que tocan</span>
                        @foreach($instrumentosFicha as $idInst => $nombreInst)
                            @if($idInst !== 'todos')
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="instrumentos[]" value="{{ $idInst }}" id="ficha-inst-{{ $idInst }}" @checked(in_array($idInst, $ficha['instrumentos'] ?? [], true))>
                                <label class="form-check-label small" for="ficha-inst-{{ $idInst }}">{{ $nombreInst }}</label>
                            </div>
                            @endif
                        @endforeach
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="ficha-nota">Nota de la escuela</label>
                        <textarea id="ficha-nota" class="form-control form-control-sm" name="nota" rows="2" maxlength="500">{{ $ficha['nota'] ?? '' }}</textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-sm btn-primary mt-2">Guardar ficha</button>
            </form>
        </details>
        @endif
    </section>
    @endif
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
                @if($ultimaEdicion)
                    <span class="d-block mt-1">Última edición: {{ $ultimaEdicion['nombre'] }}
                        @if(!empty($ultimaEdicion['at']))
                            · {{ \Illuminate\Support\Carbon::parse($ultimaEdicion['at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        @endif
                    </span>
                @endif
            </p>
        </div>
        <div class="prog-cta-row">
            <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square"></i> Editar
            </a>
        </div>
    </div>
    <div class="prog-score-card__body">
        <div
            data-partitura-viewer
            data-samples-base="{{ parse_url(asset('sounds/perc'), PHP_URL_PATH) ?: '/sounds/perc' }}"
            data-score="{{ json_encode($score, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
            data-controles="1"
            data-audio-base="{{ url('/programa/toque/'.$programaRitmo->slug.'/audio') }}"
            data-exportar-midi="{{ route('programa.toque.exportar', [$programaRitmo, 'midi']) }}"
            data-exportar-xml="{{ route('programa.toque.exportar', [$programaRitmo, 'musicxml']) }}"
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
