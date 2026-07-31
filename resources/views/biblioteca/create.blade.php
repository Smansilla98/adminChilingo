@extends('layouts.biblioteca')

@section('title', 'Subir material')

@section('content')
<div class="biblio-hero biblio-hero--compact">
    <div>
        <p class="biblio-eyebrow">Aporte abierto</p>
        <h1>Subir a la biblioteca</h1>
        <p class="biblio-lead">No necesitás cuenta. Agregá hashtags para que otros lo encuentren (#samba #murga).</p>
    </div>
    <a href="{{ route('biblioteca.index') }}" class="btn btn-secondary btn-sm">← Volver a explorar</a>
</div>

<form action="{{ route('biblioteca.store') }}" method="POST" enctype="multipart/form-data" class="biblio-form">
    @csrf
    {{-- honeypot --}}
    <div class="biblio-hp" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
    </div>

    <div class="biblio-form-grid">
        <div class="biblio-form-main">
            <div class="mb-3">
                <label class="form-label" for="titulo">Título *</label>
                <input type="text" name="titulo" id="titulo" class="form-control" required maxlength="180" value="{{ old('titulo') }}" placeholder="Ej. Ensayo bloque lunes">
            </div>
            <div class="mb-3">
                <label class="form-label" for="descripcion">Descripción</label>
                <textarea name="descripcion" id="descripcion" class="form-control" rows="3" maxlength="2000" placeholder="Contexto opcional…">{{ old('descripcion') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="archivo">Archivo</label>
                <input type="file" name="archivo" id="archivo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mp3,.wav,.ogg,.m4a,.pdf,image/*,video/*,audio/*,application/pdf">
                <div class="form-text">Imagen, video, audio o PDF · máx. 20 MB</div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="url">O un enlace</label>
                <input type="url" name="url" id="url" class="form-control" value="{{ old('url') }}" placeholder="https://…">
            </div>
        </div>
        <aside class="biblio-form-side">
            <div class="mb-3">
                <label class="form-label" for="hashtags">Hashtags</label>
                <input type="text" name="hashtags" id="hashtags" class="form-control" value="{{ old('hashtags') }}" placeholder="#samba #chilinga #ensayo">
                <div class="form-text">Separá con espacios. Máx. 12 tags.</div>
            </div>
            @if($tagsPopulares->isNotEmpty())
            <div class="mb-3">
                <div class="form-label">Sugeridos</div>
                <div class="biblio-tags">
                    @foreach($tagsPopulares as $t)
                        <button type="button" class="biblio-tag" data-tag-suggest="#{{ $t->nombre }}">#{{ $t->nombre }}</button>
                    @endforeach
                </div>
            </div>
            @endif
            <div class="mb-3">
                <label class="form-label" for="autor_nombre">Tu nombre (opcional)</label>
                <input type="text" name="autor_nombre" id="autor_nombre" class="form-control" maxlength="120" value="{{ old('autor_nombre') }}">
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg"></i> Publicar
            </button>
        </aside>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('hashtags');
    if (!input) return;
    document.querySelectorAll('[data-tag-suggest]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const tag = btn.getAttribute('data-tag-suggest');
            const cur = (input.value || '').trim();
            if (cur.toLowerCase().indexOf(tag.toLowerCase()) !== -1) return;
            input.value = cur ? (cur + ' ' + tag) : tag;
            input.focus();
        });
    });
})();
</script>
@endpush
