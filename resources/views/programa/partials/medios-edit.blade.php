@php
    use App\Support\ProgramaRitmoMedios;
    $m = $medios ?? ProgramaRitmoMedios::estructuraVacia();
    $cortes = old('cortes', $m['cortes'] ?? []);
    if ($cortes === []) {
        $cortes = [['titulo' => '', 'url' => '', 'path' => null, 'nombre' => null]];
    }
    $recursos = old('recursos', $m['recursos'] ?? []);
    if ($recursos === []) {
        $recursos = [['tipo' => 'imagen', 'titulo' => '', 'url' => '', 'contenido' => '', 'path' => null, 'nombre' => null]];
    }
    $tieneScore = ! empty($m['partitura_score']['sections']);
@endphp

<div class="card mb-3" id="partitura-recursos">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Videos, fotos, ensayos y archivos</span>
        @if(isset($programaRitmo))
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-music-note-list"></i> Editor de partitura
                </a>
                @if($esAdmin ?? auth()->user()?->isAdmin())
                <a href="{{ route('programa.toque.partitura.edit', $programaRitmo) }}" class="btn btn-sm btn-warning">
                    <i class="bi bi-cloud-upload"></i> Subir PDF/imagen
                </a>
                @endif
            </div>
        @endif
    </div>
    <div class="card-body">
        @if($esAdmin ?? auth()->user()?->isAdmin())
        <div class="card border-warning mb-4">
            <div class="card-header bg-transparent">
                <h3 class="h6 mb-0"><i class="bi bi-file-earmark-music"></i> Partitura (recomendado)</h3>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    Subí el PDF o la imagen de la página del toque (por ejemplo, del libro «Toques Chilinga»).
                    Es la forma más simple y fiel al material original.
                </p>
                @include('programa.partials.partitura-upload', [
                    'medios' => $m,
                    'programaRitmo' => $programaRitmo ?? null,
                    'inputId' => 'partitura_archivo_edit',
                    'dropzoneId' => 'partituraDropzoneEdit',
                    'previewId' => 'partituraUploadPreviewEdit',
                ])
            </div>
        </div>
        @endif

        <div class="card border-primary mb-4">
            <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h3 class="h6 mb-0"><i class="bi bi-music-note-list"></i> Editor de partitura</h3>
                @if($tieneScore)
                    <span class="badge bg-success">Con partitura</span>
                @endif
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3">
                    En el editor: <strong>Importar</strong> un PDF/foto (queda de referencia al lado) o un
                    <strong>MusicXML de MuseScore</strong> para volcar las notas. También exporta PDF, PNG, MusicXML y MIDI.
                </p>
                @if(isset($programaRitmo))
                    <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-primary btn-sm">
                        <i class="bi bi-pencil-square"></i>
                        {{ $tieneScore ? 'Abrir editor de partitura' : 'Crear / importar partitura' }}
                    </a>
                @endif
            </div>
        </div>

        <hr>
        <h3 class="h6 mb-2">Videos de bases (por tambor)</h3>
        <p class="small text-muted mb-3">Pegá el enlace (YouTube, Vimeo, Drive…). Sirve para estudiar cada voz en clase.</p>
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
        <h3 class="h6 mb-2">Ensamble, llamadas y ensayos</h3>
        <p class="small text-muted mb-3">Videos del grupo completo, llamadas y tomas de ensayo o show.</p>
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
            <h3 class="h6 mb-0">Cortes y fragmentos</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-corte"><i class="bi bi-plus"></i> Añadir corte</button>
        </div>
        <p class="small text-muted">Un video, foto o PDF por fragmento (llamada, puente, final…).</p>
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
                <input type="file" name="cortes[{{ $i }}][archivo]" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,image/*,video/*">
            </div>
            @endforeach
        </div>

        <hr>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="h6 mb-0">Fotos, apuntes y material de clase</h3>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-add-recurso"><i class="bi bi-plus"></i> Añadir recurso</button>
        </div>
        <p class="small text-muted">Fotos de pizarra, videos de ensayo, PDFs, detalles de oído o un texto para la clase.</p>
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
                <textarea name="recursos[{{ $i }}][contenido]" class="form-control form-control-sm mb-2 recurso-texto" rows="3" placeholder="Detalle, apunte o nota para la clase (opcional)">{{ $rec['contenido'] ?? '' }}</textarea>
                @if(!empty($rec['path']))
                <input type="hidden" name="recursos[{{ $i }}][path]" value="{{ $rec['path'] }}">
                <input type="hidden" name="recursos[{{ $i }}][nombre]" value="{{ $rec['nombre'] ?? '' }}">
                <div class="small text-muted mb-2">
                    Archivo: {{ $rec['nombre'] ?? 'adjunto' }}
                    <label class="ms-2"><input type="checkbox" name="recursos[{{ $i }}][quitar_archivo]" value="1"> Quitar</label>
                </div>
                @endif
                <input type="file" name="recursos[{{ $i }}][archivo]" class="form-control form-control-sm recurso-archivo" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,.pdf,image/*,video/*,application/pdf">
            </div>
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const input = document.querySelector('#partitura_archivo_edit');
    const dropzone = document.getElementById('partituraDropzoneEdit');
    const previewWrap = document.getElementById('partituraUploadPreviewEditNuevo');
    const previewContent = document.getElementById('partituraUploadPreviewEditNuevoContent');
    if (!input || !dropzone) return;

    function showPreview(file) {
        if (!file || !previewWrap || !previewContent) return;
        previewWrap.classList.remove('d-none');
        const url = URL.createObjectURL(file);
        if (file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) {
            previewContent.innerHTML = '<iframe src="' + url + '#view=FitH" class="partitura-upload-preview-pdf" style="width:100%;height:min(400px,50vh);border:none"></iframe>';
        } else if (file.type.startsWith('image/')) {
            previewContent.innerHTML = '<img src="' + url + '" alt="Vista previa" style="width:100%;height:auto">';
        }
    }

    input.addEventListener('change', function () {
        if (input.files && input.files[0]) showPreview(input.files[0]);
    });
    dropzone.addEventListener('dragover', function (e) { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone.addEventListener('dragleave', function () { dropzone.classList.remove('dragover'); });
    dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer?.files?.length) {
            input.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0]);
        }
    });
})();
</script>
@endpush
