@php
    use App\Support\ProgramaRitmoMedios;
    $m = $medios ?? ProgramaRitmoMedios::estructuraVacia();
    $cortes = old('cortes', $m['cortes'] ?? []);
    if ($cortes === []) {
        $cortes = [['titulo' => '', 'url' => '', 'path' => null, 'nombre' => null]];
    }
    $recursos = old('recursos', $m['recursos'] ?? []);
    if ($recursos === []) {
        $recursos = [['tipo' => 'enlace', 'titulo' => '', 'url' => '', 'contenido' => '', 'path' => null, 'nombre' => null]];
    }
    $partitura = $m['partitura'] ?? null;
@endphp

<div class="card mb-3">
    <div class="card-header">Videos, partitura y archivos del toque</div>
    <div class="card-body">
        <p class="small text-muted mb-4">Lo que cargues acá lo verán en la página del toque: partitura, videos por tambor, ensamble y otros archivos. Podés ir sumando de a poco.</p>

        <h3 class="h6 mb-3">Partitura</h3>
        @if(!empty($partitura['path']))
        <div class="alert alert-secondary py-2 small d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <span><i class="bi bi-file-earmark-pdf"></i> {{ $partitura['nombre'] ?? 'Archivo cargado' }}</span>
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="quitar_partitura" value="1" id="quitar_partitura">
                <label class="form-check-label" for="quitar_partitura">Quitar partitura actual</label>
            </div>
        </div>
        @endif
        <div class="mb-4">
            <label class="form-label">{{ !empty($partitura['path']) ? 'Reemplazar partitura' : 'Subir partitura' }}</label>
            <input type="file" name="partitura_archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
            <div class="form-text">PDF o imagen, hasta 20 MB.</div>
        </div>

        <hr>
        <h3 class="h6 mb-2">Videos de bases (por tambor)</h3>
        <p class="small text-muted mb-3">Pegá el enlace del video (YouTube, Vimeo, etc.).</p>
        <div class="row g-3 mb-4">
            @foreach($videosBase as $key => $label)
            <div class="col-md-6">
                <label class="form-label small mb-1">{{ $label }}</label>
                <input type="text" name="videos_base[{{ $key }}][url]" class="form-control form-control-sm"
                    placeholder="https://youtube.com/… o https://vimeo.com/…"
                    value="{{ old('videos_base.'.$key.'.url', $m['videos_base'][$key]['url'] ?? '') }}">
            </div>
            @endforeach
        </div>

        <hr>
        <h3 class="h6 mb-2">Ensamble y llamadas</h3>
        <div class="row g-3 mb-4">
            @foreach($videosGrupo as $key => $label)
            <div class="col-md-6">
                <label class="form-label small mb-1">{{ $label }}</label>
                <input type="text" name="videos_grupo[{{ $key }}][url]" class="form-control form-control-sm"
                    placeholder="https://…"
                    value="{{ old('videos_grupo.'.$key.'.url', $m['videos_grupo'][$key]['url'] ?? '') }}">
            </div>
            @endforeach
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0">Cortes</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-corte"><i class="bi bi-plus"></i> Añadir corte</button>
        </div>
        <p class="small text-muted">Trozos del toque: un video por enlace o un archivo (PDF, foto o video).</p>
        <div id="cortes-wrap" class="d-grid gap-3 mb-4">
            @foreach($cortes as $i => $corte)
            <div class="border rounded p-3 corte-item">
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-muted">Corte {{ $i + 1 }}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-corte">×</button>
                </div>
                <input type="text" name="cortes[{{ $i }}][titulo]" class="form-control form-control-sm mb-2" placeholder="Título del corte" value="{{ $corte['titulo'] ?? '' }}">
                <input type="text" name="cortes[{{ $i }}][url]" class="form-control form-control-sm mb-2" placeholder="URL de video (opcional)" value="{{ $corte['url'] ?? '' }}">
                @if(!empty($corte['path']))
                <input type="hidden" name="cortes[{{ $i }}][path]" value="{{ $corte['path'] }}">
                <input type="hidden" name="cortes[{{ $i }}][nombre]" value="{{ $corte['nombre'] ?? '' }}">
                <div class="small text-muted mb-2">
                    Archivo: {{ $corte['nombre'] ?? 'adjunto' }}
                    <label class="ms-2"><input type="checkbox" name="cortes[{{ $i }}][quitar_archivo]" value="1"> Quitar</label>
                </div>
                @endif
                <input type="file" name="cortes[{{ $i }}][archivo]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.mp4,.webm">
            </div>
            @endforeach
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0">Recursos adicionales</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-recurso"><i class="bi bi-plus"></i> Añadir recurso</button>
        </div>
        <p class="small text-muted">Cualquier extra: foto, PDF, video, enlace o un texto escrito.</p>
        <div id="recursos-wrap" class="d-grid gap-3">
            @foreach($recursos as $i => $rec)
            <div class="border rounded p-3 recurso-item">
                <div class="d-flex justify-content-between mb-2">
                    <span class="small text-muted">Recurso {{ $i + 1 }}</span>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-recurso">×</button>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-md-4">
                        <select name="recursos[{{ $i }}][tipo]" class="form-select form-select-sm recurso-tipo">
                            @foreach($tiposRecurso as $tk => $tl)
                            <option value="{{ $tk }}" @selected(($rec['tipo'] ?? 'enlace') === $tk)>{{ $tl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <input type="text" name="recursos[{{ $i }}][titulo]" class="form-control form-control-sm" placeholder="Título" value="{{ $rec['titulo'] ?? '' }}">
                    </div>
                </div>
                <input type="text" name="recursos[{{ $i }}][url]" class="form-control form-control-sm mb-2 recurso-url" placeholder="URL (video, web, imagen externa…)" value="{{ $rec['url'] ?? '' }}">
                <textarea name="recursos[{{ $i }}][contenido]" class="form-control form-control-sm mb-2 recurso-texto" rows="3" placeholder="Texto (solo tipo «Bloque de texto»)">{{ $rec['contenido'] ?? '' }}</textarea>
                @if(!empty($rec['path']))
                <input type="hidden" name="recursos[{{ $i }}][path]" value="{{ $rec['path'] }}">
                <input type="hidden" name="recursos[{{ $i }}][nombre]" value="{{ $rec['nombre'] ?? '' }}">
                <div class="small text-muted mb-2">
                    Archivo: {{ $rec['nombre'] ?? 'adjunto' }}
                    <label class="ms-2"><input type="checkbox" name="recursos[{{ $i }}][quitar_archivo]" value="1"> Quitar</label>
                </div>
                @endif
                <input type="file" name="recursos[{{ $i }}][archivo]" class="form-control form-control-sm recurso-archivo">
            </div>
            @endforeach
        </div>
    </div>
</div>
