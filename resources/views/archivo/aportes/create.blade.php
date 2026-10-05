@extends('layouts.archivo')

@section('body-class', 'archivo--aporte')

@section('content')
<div class="ar-aporte">
    <header class="ar-aporte__cabecera">
        <p class="ar-sobretitulo ar-mono">Archivo colectivo</p>
        <h1 class="ar-aporte__titulo">Compartí un recuerdo</h1>
        <p class="ar-aporte__bajada">¿Tenés una foto histórica de La Chilinga? Subila al archivo y ayudanos a preservar nuestra memoria colectiva. Antes de publicarse, el equipo la revisa con vos.</p>
    </header>

    <form class="ar-form" method="POST" action="{{ route('archivo.aportes.lote') }}" enctype="multipart/form-data"
          data-cargador
          data-subir="{{ route('archivo.aportes.subir') }}"
          data-campo-ids="ids[]">
        @csrf
        <section class="ar-paso-form" aria-labelledby="paso-1">
            <h2 class="ar-paso-form__titulo" id="paso-1"><span class="ar-mono">01</span> Tus fotografías</h2>
            <div class="ar-zona" data-zona>
                <input class="ar-zona__input" type="file" id="archivos" name="archivos[]" accept="image/jpeg,image/png,image/webp" multiple data-input>
                <label for="archivos" class="ar-zona__label">
                    <span class="ar-zona__icono" aria-hidden="true">＋</span>
                    <strong>Arrastrá tus fotos acá o tocá para elegirlas</strong>
                    <span class="ar-tenue">JPG, PNG o WebP · hasta 40 MB cada una · podés subir muchas a la vez</span>
                </label>
            </div>
            <ol class="ar-cola" data-cola aria-live="polite"></ol>
            <p class="ar-cola__resumen ar-tenue" data-resumen hidden></p>
        </section>

        <section class="ar-paso-form" aria-labelledby="paso-2">
            <h2 class="ar-paso-form__titulo" id="paso-2"><span class="ar-mono">02</span> Lo que recordás</h2>
            <p class="ar-tenue ar-form__intro">Estos datos se aplican a todas las fotos que subiste ahora. Después podés corregir cada una por separado.</p>
            @include('archivo.partials.campos-aporte')
        </section>

        <div class="ar-form__acciones">
            <button type="submit" class="ar-boton" name="enviar" value="1" data-enviar>Enviar al archivo</button>
            <button type="submit" class="ar-boton ar-boton--linea" name="enviar" value="0">Guardar como borrador</button>
            <a class="ar-enlace" href="{{ route('archivo.aportes.index') }}">Ver mis aportes</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/archivo-carga.js') }}?v=1" defer></script>
@endpush
