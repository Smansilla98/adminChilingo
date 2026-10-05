{{--
    Preguntas del aporte, en tono de conversación. Lo usan el alta múltiple y la
    corrección de una foto. $foto (opcional) · $sedes · $acontecimientos · $tipos · $fuentes · $precisiones
--}}
@php
    $f = $foto ?? null;
    $personasActuales = $f ? $f->personas->map(fn ($p) => ['persona_id' => $p->persona_id, 'nombre' => $p->nombreVisible(), 'detalle' => $p->detalle])->values() : collect();
@endphp
<div class="ar-form__grupo">
    <label class="ar-form__pregunta" for="titulo">¿Cómo la llamarías?</label>
    <input class="ar-input" id="titulo" name="titulo" maxlength="180" value="{{ old('titulo', $f?->titulo) }}" placeholder="Ej.: Ensayo en la plaza de Banfield">
</div>
<div class="ar-form__grupo">
    <label class="ar-form__pregunta" for="descripcion">¿Qué recordás de esta fotografía?</label>
    <textarea class="ar-input" id="descripcion" name="descripcion" rows="3" maxlength="3000" placeholder="Qué estaba pasando, quiénes estaban, qué se tocaba…">{{ old('descripcion', $f?->descripcion) }}</textarea>
</div>
<div class="ar-form__fila">
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="anio">¿De qué año es, aproximadamente?</label>
        <input class="ar-input" id="anio" name="anio" type="number" inputmode="numeric" min="1900" max="{{ now()->year }}" value="{{ old('anio', $f?->anio) }}" placeholder="1998">
    </div>
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="precision">¿Qué tan seguro/a estás?</label>
        <select class="ar-input" id="precision" name="precision">
            <option value="anio" @selected(old('precision', $f?->precision) === 'anio')>Seguro/a del año</option>
            <option value="aprox" @selected(old('precision', $f?->precision) === 'aprox')>Es aproximado</option>
            <option value="dia" @selected(old('precision', $f?->precision) === 'dia')>Sé la fecha exacta</option>
        </select>
    </div>
    <div class="ar-form__grupo" data-si-precision="dia">
        <label class="ar-form__pregunta" for="fecha">Fecha</label>
        <input class="ar-input" id="fecha" name="fecha" type="date" max="{{ now()->toDateString() }}" value="{{ old('fecha', $f?->fecha?->toDateString()) }}">
    </div>
</div>
<div class="ar-form__fila">
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="lugar">¿Dónde fue?</label>
        <input class="ar-input" id="lugar" name="lugar" maxlength="180" value="{{ old('lugar', $f?->lugar) }}" placeholder="Plaza, teatro, calle…">
    </div>
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="ciudad">Ciudad o barrio</label>
        <input class="ar-input" id="ciudad" name="ciudad" maxlength="120" value="{{ old('ciudad', $f?->ciudad) }}" placeholder="Banfield">
    </div>
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="sede_id">¿Es de una sede?</label>
        <select class="ar-input" id="sede_id" name="sede_id">
            <option value="">No sé / ninguna</option>
            @foreach($sedes as $s)<option value="{{ $s->id }}" @selected((string) old('sede_id', $f?->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>@endforeach
        </select>
    </div>
</div>
@if($acontecimientos->isNotEmpty())
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="acontecimiento_id">¿Es de alguno de estos momentos?</label>
        <select class="ar-input" id="acontecimiento_id" name="acontecimiento_id">
            <option value="">Ninguno / no sé</option>
            @foreach($acontecimientos as $a)<option value="{{ $a->id }}" @selected((string) old('acontecimiento_id', $f?->acontecimiento_id) === (string) $a->id)>{{ $a->anio }} · {{ $a->titulo }}</option>@endforeach
        </select>
    </div>
@endif
<div class="ar-form__grupo">
    <span class="ar-form__pregunta" id="personas-lbl">¿Quiénes aparecen?</span>
    <div class="ar-personas-picker" data-personas-picker data-buscar="{{ route('archivo.personas') }}" data-inicial='@json($personasActuales)' aria-labelledby="personas-lbl">
        <ul class="ar-personas-picker__lista" data-lista></ul>
        <div class="ar-personas-picker__nuevo">
            <input class="ar-input" type="text" data-nombre list="personas-sugeridas" placeholder="Nombre y apellido" aria-label="Nombre de la persona" autocomplete="off">
            <input class="ar-input" type="text" data-detalle placeholder="Dónde está (opcional)" aria-label="Dónde está en la foto">
            <button type="button" class="ar-boton ar-boton--linea ar-boton--chico" data-agregar>Agregar</button>
        </div>
        <datalist id="personas-sugeridas"></datalist>
        <p class="ar-form__ayuda">Ej.: “tercera desde la izquierda”. Si no sabés, dejalo vacío.</p>
    </div>
</div>
<div class="ar-form__grupo">
    <label class="ar-form__pregunta" for="tipo">¿Qué muestra?</label>
    <select class="ar-input" id="tipo" name="tipo">
        <option value="">Elegí una opción</option>
        @foreach($tipos as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $f?->tipo) === $k)>{{ $v }}</option>@endforeach
    </select>
</div>
<div class="ar-form__grupo">
    <label class="ar-form__pregunta" for="contexto">¿Querés contarnos algo más sobre esta foto?</label>
    <textarea class="ar-input" id="contexto" name="notas_aportante" rows="3" maxlength="4000" placeholder="Anécdotas, cómo llegó a tus manos, qué pasó después…">{{ old('notas_aportante', $f?->notas_aportante) }}</textarea>
</div>
<details class="ar-form__mas" @if($f?->fotografo || $f?->fuente) open @endif>
    <summary>Autoría y procedencia</summary>
    <div class="ar-form__fila">
        <div class="ar-form__grupo">
            <label class="ar-form__pregunta" for="fotografo">¿Quién sacó la foto?</label>
            <input class="ar-input" id="fotografo" name="fotografo" maxlength="150" value="{{ old('fotografo', $f?->fotografo) }}" placeholder="Si no sabés, dejalo vacío">
        </div>
        <div class="ar-form__grupo">
            <label class="ar-form__pregunta" for="fuente">¿De dónde viene?</label>
            <select class="ar-input" id="fuente" name="fuente">
                <option value="">Elegí una opción</option>
                @foreach($fuentes as $k => $v)<option value="{{ $k }}" @selected(old('fuente', $f?->fuente) === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="fuente_detalle">Detalle de la procedencia</label>
        <input class="ar-input" id="fuente_detalle" name="fuente_detalle" maxlength="255" value="{{ old('fuente_detalle', $f?->fuente_detalle) }}" placeholder="Ej.: álbum familiar, revista, Facebook de…">
    </div>
    <div class="ar-form__grupo">
        <label class="ar-form__pregunta" for="tags">Etiquetas</label>
        <input class="ar-input" id="tags" name="tags" value="{{ old('tags', $f ? $f->tags->pluck('nombre')->map(fn ($t) => '#'.$t)->implode(' ') : '') }}" placeholder="#ensayo #calle #tambores">
    </div>
</details>
<label class="ar-check">
    <input type="checkbox" name="mostrar_aportante" value="1" @checked(old('mostrar_aportante', $f?->mostrar_aportante))>
    <span>Mostrar mi nombre como aportante. Si no, figura “Aportante anónimo”.</span>
</label>
