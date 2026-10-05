@extends('layouts.app')

@section('title', 'Subir fotografías — Archivo histórico')
@section('page-title', 'Archivo histórico')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/archivo-gestion.css') }}?v=1">
@endpush

@section('content')
<div class="ito-page agx">
    <div class="ito-page-head">
        <div>
            <p class="hub-eyebrow">Archivo histórico</p>
            <h1 class="ito-page-title">Subir fotografías</h1>
            <p class="ito-page-sub">Arrastrá muchas a la vez. Se suben de a una (podés seguir completando datos mientras tanto) y quedan en borrador hasta publicarlas.</p>
        </div>
    </div>
    @include('archivo.gestion._nav')

    <form method="POST" action="{{ route('archivo.gestion.fotos.lote') }}" data-cargador data-subir="{{ route('archivo.gestion.fotos.store') }}" data-campo-ids="ids[]" class="agx-subir">
        @csrf
        <input type="hidden" name="accion" value="aplicar">
        <input type="hidden" name="volver" value="{{ route('archivo.gestion.fotos') }}">
        <div class="ar-zona" data-zona>
            <input class="ar-zona__input" type="file" id="archivos" accept="image/jpeg,image/png,image/webp" multiple data-input>
            <label for="archivos" class="ar-zona__label">
                <i class="bi bi-cloud-arrow-up ar-zona__icono" aria-hidden="true"></i>
                <strong>Arrastrá fotografías acá o elegí archivos</strong>
                <span class="text-muted">JPG, PNG o WebP · hasta 40 MB · el original se conserva intacto</span>
            </label>
        </div>
        <ol class="ar-cola" data-cola aria-live="polite"></ol>
        <p class="ar-cola__resumen text-muted" data-resumen hidden></p>

        <h2 class="agx-h2 mt-4">Datos para todas</h2>
        <p class="text-muted small">Solo se aplican los campos que completes. Después podés corregir cada foto.</p>
        @include('archivo.gestion._campos-foto', ['lote' => true])

        <div class="ito-form-actions">
            @can('publish', new \App\Models\ArchivoFoto(['sede_id' => null]))
                <div class="form-check me-auto">
                    <input class="form-check-input" type="checkbox" id="publicar" name="publicar" value="1">
                    <label class="form-check-label" for="publicar">Publicar al guardar</label>
                </div>
            @endcan
            <button type="submit" class="btn btn-primary" data-enviar>Guardar datos</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-carga.js') }}?v=1" defer></script>
@endpush
