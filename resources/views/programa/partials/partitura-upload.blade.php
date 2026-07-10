@php
    $partitura = $partitura ?? ($medios['partitura'] ?? null);
    $inputId = $inputId ?? 'partitura_archivo';
    $previewId = $previewId ?? 'partituraUploadPreview';
    $dropzoneId = $dropzoneId ?? 'partituraDropzone';
    $tieneArchivo = ! empty($partitura['path']);
    $inlineUrl = $tieneArchivo && isset($programaRitmo)
        ? route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'partitura', 'inline' => 1])
        : null;
    $nombre = $partitura['nombre'] ?? '';
    $esPdf = (bool) preg_match('/\.pdf$/i', $nombre);
    $esImagen = (bool) preg_match('/\.(jpe?g|png|webp)$/i', $nombre);
@endphp

<div class="partitura-upload-block">
    @if($tieneArchivo && $inlineUrl)
        <div class="partitura-upload-actual mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <span class="small fw-semibold"><i class="bi bi-check-circle-fill text-success"></i> Partitura cargada</span>
                <span class="small text-muted text-truncate">{{ $nombre }}</span>
            </div>
            <div class="partitura-upload-preview-box" id="{{ $previewId }}Actual">
                @if($esImagen)
                    <img src="{{ $inlineUrl }}" alt="Partitura actual" class="partitura-upload-preview-img">
                @elseif($esPdf)
                    <iframe src="{{ $inlineUrl }}#view=FitH" title="Vista previa PDF" class="partitura-upload-preview-pdf"></iframe>
                @else
                    <p class="small text-muted mb-0 p-3">Archivo guardado. Subí otro para reemplazarlo.</p>
                @endif
            </div>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="quitar_partitura" value="1" id="quitar_partitura_{{ $inputId }}">
                <label class="form-check-label small" for="quitar_partitura_{{ $inputId }}">Quitar partitura al guardar</label>
            </div>
        </div>
    @endif

    <label class="partitura-dropzone" id="{{ $dropzoneId }}" for="{{ $inputId }}">
        <input type="file" name="partitura_archivo" id="{{ $inputId }}" accept=".pdf,.jpg,.jpeg,.png,.webp,image/*" class="d-none" data-partitura-input>
        <i class="bi bi-cloud-arrow-up"></i>
        <strong>{{ $tieneArchivo ? 'Reemplazar partitura' : 'Arrastrá o elegí un archivo' }}</strong>
        <span>PDF o imagen (JPG, PNG) — una página por toque</span>
        <small>Hasta 20 MB · Ideal: exportar la página del libro «Toques Chilinga»</small>
    </label>

    <div class="partitura-upload-preview-box d-none mt-3" id="{{ $previewId }}Nuevo">
        <div class="small text-muted mb-2">Vista previa del archivo nuevo:</div>
        <div id="{{ $previewId }}NuevoContent"></div>
    </div>
</div>
