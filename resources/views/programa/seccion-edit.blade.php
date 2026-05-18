@extends('layouts.app')

@section('title', 'Editar: ' . $seccion->titulo)
@section('page-title', 'Editar sección del programa')

@section('content')
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}">Programa</a></li>
        <li class="breadcrumb-item active">{{ $seccion->titulo }}</li>
    </ol>
</nav>

<form action="{{ route('programa.seccion.update', $seccion) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between">
            <span>{{ $categorias[$seccion->categoria] ?? $seccion->categoria }}</span>
            <code class="small">{{ $seccion->slug }}</code>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Título *</label>
                <input type="text" name="titulo" class="form-control" value="{{ old('titulo', $seccion->titulo) }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Subtítulo</label>
                <input type="text" name="subtitulo" class="form-control" value="{{ old('subtitulo', $seccion->subtitulo) }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Contenido (HTML permitido)</label>
                <textarea name="cuerpo" class="form-control font-monospace" rows="16">{{ old('cuerpo', $seccion->cuerpo) }}</textarea>
                <div class="form-text">Usá &lt;p&gt;, &lt;ul&gt;, &lt;strong&gt;, etc. El texto proviene del PDF oficial del programa.</div>
            </div>
            <div class="form-check">
                <input type="hidden" name="activo" value="0">
                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" {{ old('activo', $seccion->activo) ? 'checked' : '' }}>
                <label class="form-check-label" for="activo">Visible en el programa</label>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Guardar sección</button>
    <a href="{{ route('programa.index', ['seccion' => $seccion->slug]) }}" class="btn btn-secondary">Cancelar</a>
</form>
@endsection
