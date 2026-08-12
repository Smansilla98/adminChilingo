@extends('layouts.publico')

@section('title', 'Partitura: '.$programaRitmo->nombre)
@section('publico-brand', 'Partituras')

@push('styles')
<style>
.partitura-upload-block { max-width: 720px; }
.partitura-dropzone {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; padding: 36px 24px; border: 2px dashed var(--border, #3a2e24);
    border-radius: 16px; background: color-mix(in srgb, var(--brass, #d1a054) 6%, var(--surface-2, #1d160f));
    color: var(--muted, #b6a488); text-align: center; cursor: pointer; transition: border-color .15s, background .15s;
}
.partitura-dropzone:hover, .partitura-dropzone.dragover {
    border-color: var(--brass, #d1a054); background: color-mix(in srgb, var(--brass, #d1a054) 12%, var(--surface-2));
    color: var(--skin, #f3e9d8);
}
.partitura-dropzone i { font-size: 2.2rem; color: var(--brass, #d1a054); }
.partitura-dropzone strong { color: var(--skin, #f3e9d8); font-size: 15px; }
.partitura-dropzone span { font-size: 13px; }
.partitura-dropzone small { font-size: 11px; opacity: .85; }
.partitura-upload-preview-box {
    border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: #fff;
}
.partitura-upload-preview-img { width: 100%; height: auto; display: block; }
.partitura-upload-preview-pdf { width: 100%; height: min(70vh, 520px); border: none; display: block; }
.partitura-pasos { counter-reset: paso; list-style: none; padding: 0; margin: 0; }
.partitura-pasos li {
    position: relative; padding-left: 2rem; margin-bottom: 10px; font-size: 14px; color: var(--muted);
}
.partitura-pasos li::before {
    counter-increment: paso; content: counter(paso);
    position: absolute; left: 0; width: 1.4rem; height: 1.4rem; border-radius: 50%;
    background: var(--brass-soft); color: var(--brass); font-size: 11px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
}
</style>
@endpush

@section('content')
@php $añoLabel = $años[$programaRitmo->año] ?? $programaRitmo->año.'° Año'; @endphp

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.partituras.index') }}">Partituras</a></li>
        <li class="breadcrumb-item"><a href="{{ route('programa.toque.show', $programaRitmo) }}">{{ $programaRitmo->nombre }}</a></li>
        <li class="breadcrumb-item active">Cargar partitura</li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card border-warning">
            <div class="card-header">
                <h2 class="h5 mb-0"><i class="bi bi-file-earmark-music"></i> {{ $programaRitmo->nombre }}</h2>
                <span class="small text-muted">{{ $añoLabel }} · Toque {{ $programaRitmo->orden }}</span>
            </div>
            <div class="card-body">
                <form action="{{ route('programa.toque.partitura.update', $programaRitmo) }}" method="POST" enctype="multipart/form-data" id="partituraUploadForm">
                    @csrf
                    @include('programa.partials.partitura-upload', [
                        'medios' => $medios,
                        'programaRitmo' => $programaRitmo,
                    ])

                    @error('partitura_archivo')
                        <div class="alert alert-danger py-2 small mt-2">{{ $message }}</div>
                    @enderror

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="abrir_editor" value="1" id="abrir_editor" checked>
                        <label class="form-check-label small" for="abrir_editor">Después de guardar, abrir el editor con el original al lado</label>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-check-lg"></i> Guardar y pasar al editor
                        </button>
                        <a href="{{ route('programa.partituras.index') }}" class="btn btn-outline-secondary">Volver al catálogo</a>
                        <a href="{{ route('programa.toque.editor', $programaRitmo) }}" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-music-note-list"></i> Editor de partitura
                        </a>
                        <a href="{{ route('programa.toque.edit', $programaRitmo) }}#partitura-recursos" class="btn btn-outline-secondary btn-sm ms-auto">
                            Videos y más recursos →
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><strong class="small">Cómo cargar desde el libro PDF</strong></div>
            <div class="card-body">
                <p class="small text-muted">Un PDF o foto del cuadernillo <strong>no se convierte solo en notas</strong> (es un dibujo). El editor lo pone al lado para transcribirlo. Si lo escribiste en MuseScore, exportá <strong>MusicXML</strong> e importalo: ahí sí se vuelcan las notas.</p>
                <ol class="partitura-pasos">
                    <li>Subí la página del toque <strong>{{ $programaRitmo->nombre }}</strong> (PDF o foto).</li>
                    <li>Abrí el editor: el original queda a la izquierda.</li>
                    <li>Pasá las figuras al pentagrama digital y guardá.</li>
                    <li>O importá un <strong>.musicxml</strong> exportado de MuseScore.</li>
                </ol>
            </div>
        </div>
        <div class="card">
            <div class="card-body small text-muted">
                <p class="mb-2"><i class="bi bi-lightbulb text-warning"></i> <strong>Tip:</strong> En muchos lectores PDF podés «Imprimir → Guardar como PDF» eligiendo solo la página del toque.</p>
                <p class="mb-0">Para partituras nuevas usá el <a href="{{ route('programa.toque.editor', $programaRitmo) }}">Editor de partitura</a> (notación del cuadernillo, reproducción y partes por tambor).</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.querySelector('[data-partitura-input]');
    const dropzone = document.getElementById('partituraDropzone');
    const previewWrap = document.getElementById('partituraUploadPreviewNuevo');
    const previewContent = document.getElementById('partituraUploadPreviewNuevoContent');
    if (!input || !dropzone) return;

    function showPreview(file) {
        if (!file || !previewWrap || !previewContent) return;
        previewWrap.classList.remove('d-none');
        previewContent.innerHTML = '';
        const url = URL.createObjectURL(file);
        if (file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf')) {
            previewContent.innerHTML = '<iframe src="' + url + '#view=FitH" class="partitura-upload-preview-pdf" title="Vista previa"></iframe>';
        } else if (file.type.startsWith('image/')) {
            previewContent.innerHTML = '<img src="' + url + '" alt="Vista previa" class="partitura-upload-preview-img">';
        } else {
            previewContent.innerHTML = '<p class="small p-3 mb-0">Archivo seleccionado: ' + file.name + '</p>';
        }
    }

    input.addEventListener('change', function () {
        if (input.files && input.files[0]) showPreview(input.files[0]);
    });

    ['dragenter', 'dragover'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropzone.classList.add('dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        dropzone.addEventListener(ev, function (e) {
            e.preventDefault();
            dropzone.classList.remove('dragover');
        });
    });
    dropzone.addEventListener('drop', function (e) {
        const files = e.dataTransfer?.files;
        if (!files || !files.length) return;
        input.files = files;
        showPreview(files[0]);
    });
})();
</script>
@endpush
