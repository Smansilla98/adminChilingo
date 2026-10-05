{{--
    Metadatos de una foto para el equipo. Con $lote = true, todos los campos son
    opcionales y solo se aplican los que se completan.
    $foto (opcional) · $lote · $sedes · $capitulos · $acontecimientos · $tipos · $fuentes · $precisiones
--}}
@php
    $f = $foto ?? null;
    $lote = $lote ?? false;
    $vacio = $lote ? '— sin cambios —' : '—';
    $personasActuales = $f ? $f->personas->map(fn ($p) => ['persona_id' => $p->persona_id, 'nombre' => $p->nombreVisible(), 'detalle' => $p->detalle])->values() : collect();
@endphp
<x-ito.form-section title="Qué es" icon="bi-card-text">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="titulo">Título</label>
            <input class="form-control" id="titulo" name="titulo" maxlength="180" value="{{ old('titulo', $f?->titulo) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="tipo">Tipo</label>
            <select class="form-select" id="tipo" name="tipo">
                <option value="">{{ $vacio }}</option>
                @foreach($tipos as $k => $v)<option value="{{ $k }}" @selected(old('tipo', $f?->tipo) === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-12">
            <label class="form-label" for="descripcion">Epígrafe</label>
            <textarea class="form-control" id="descripcion" name="descripcion" rows="2" maxlength="3000">{{ old('descripcion', $f?->descripcion) }}</textarea>
        </div>
        <div class="col-12">
            <label class="form-label" for="contexto">Contexto histórico</label>
            <textarea class="form-control" id="contexto" name="contexto" rows="{{ $lote ? 2 : 4 }}" maxlength="8000">{{ old('contexto', $f?->contexto) }}</textarea>
        </div>
        @unless($lote)
            <div class="col-12">
                <label class="form-label" for="alt_text">Texto alternativo <span class="text-muted small">(para lectores de pantalla)</span></label>
                <input class="form-control" id="alt_text" name="alt_text" maxlength="300" value="{{ old('alt_text', $f?->alt_text) }}" placeholder="Describí lo que se ve: personas, lugar, acción">
            </div>
        @endunless
    </div>
</x-ito.form-section>

<x-ito.form-section title="Cuándo y dónde" icon="bi-geo-alt">
    <div class="row g-3">
        <div class="col-6 col-md-3">
            <label class="form-label" for="anio">Año</label>
            <input class="form-control" id="anio" name="anio" type="number" min="1900" max="{{ now()->year }}" value="{{ old('anio', $f?->anio) }}">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="precision">Precisión</label>
            <select class="form-select" id="precision" name="precision">
                @if($lote)<option value="">{{ $vacio }}</option>@endif
                @foreach($precisiones as $k => $v)<option value="{{ $k }}" @selected(old('precision', $f?->precision ?? ($lote ? null : 'anio')) === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3" data-si-precision="dia">
            <label class="form-label" for="fecha">Fecha exacta</label>
            <input class="form-control" id="fecha" name="fecha" type="date" max="{{ now()->toDateString() }}" value="{{ old('fecha', $f?->fecha?->toDateString()) }}">
        </div>
        <div class="col-6 col-md-3" data-si-precision="mes">
            <label class="form-label" for="mes">Mes</label>
            <select class="form-select" id="mes" name="mes">
                <option value="">—</option>
                @foreach(['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'] as $i => $m)
                    <option value="{{ $i + 1 }}" @selected((int) old('mes', $f?->mes) === $i + 1)>{{ ucfirst($m) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="lugar">Lugar</label>
            <input class="form-control" id="lugar" name="lugar" maxlength="180" value="{{ old('lugar', $f?->lugar) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="ciudad">Ciudad / barrio</label>
            <input class="form-control" id="ciudad" name="ciudad" maxlength="120" value="{{ old('ciudad', $f?->ciudad) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="pais">País</label>
            <input class="form-control" id="pais" name="pais" maxlength="80" value="{{ old('pais', $f?->pais) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="sede_id">Sede</label>
            <select class="form-select" id="sede_id" name="sede_id">
                <option value="">{{ $vacio }}</option>
                @foreach($sedes as $s)<option value="{{ $s->id }}" @selected((string) old('sede_id', $f?->sede_id) === (string) $s->id)>{{ $s->nombre }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="capitulo_id">Capítulo</label>
            <select class="form-select" id="capitulo_id" name="capitulo_id">
                <option value="">{{ $vacio }}</option>
                @foreach($capitulos as $c)<option value="{{ $c->id }}" @selected((string) old('capitulo_id', $f?->capitulo_id ?? ($preCapitulo ?? null)) === (string) $c->id)>{{ $c->titulo }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="acontecimiento_id">Acontecimiento</label>
            <select class="form-select" id="acontecimiento_id" name="acontecimiento_id">
                <option value="">{{ $vacio }}</option>
                @foreach($acontecimientos as $a)<option value="{{ $a->id }}" @selected((string) old('acontecimiento_id', $f?->acontecimiento_id ?? ($preAcontecimiento ?? null)) === (string) $a->id)>{{ $a->anio }} · {{ $a->titulo }}</option>@endforeach
            </select>
        </div>
        @unless($lote)
            <div class="col-6 col-md-3">
                <label class="form-label" for="latitud">Latitud</label>
                <input class="form-control" id="latitud" name="latitud" inputmode="decimal" value="{{ old('latitud', $f?->latitud) }}" placeholder="-34.76">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="longitud">Longitud</label>
                <input class="form-control" id="longitud" name="longitud" inputmode="decimal" value="{{ old('longitud', $f?->longitud) }}" placeholder="-58.40">
            </div>
        @endunless
    </div>
</x-ito.form-section>

<x-ito.form-section title="Quiénes aparecen" icon="bi-people" help="{{ $lote ? 'Se suman a las personas que cada foto ya tenga.' : 'Personas del sistema o nombres libres para quien no está cargado.' }}">
    <div class="ar-personas-picker" data-personas-picker data-buscar="{{ route('archivo.gestion.personas.buscar') }}" data-inicial='@json($personasActuales)'>
        <ul class="ar-personas-picker__lista" data-lista></ul>
        <div class="ar-personas-picker__nuevo">
            <input class="form-control" type="text" data-nombre list="personas-sugeridas" placeholder="Buscar persona o escribir un nombre" aria-label="Persona" autocomplete="off">
            <input class="form-control" type="text" data-detalle placeholder="Ubicación en la foto (opcional)" aria-label="Ubicación en la foto">
            <button type="button" class="btn btn-secondary" data-agregar>Agregar</button>
        </div>
        <datalist id="personas-sugeridas"></datalist>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Autoría y procedencia" icon="bi-camera" help="Fotógrafo/a (quién la tomó), fuente (de dónde viene) y aportante (quién la subió) son datos distintos.">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="fotografo">Fotógrafo/a</label>
            <input class="form-control" id="fotografo" name="fotografo" maxlength="150" value="{{ old('fotografo', $f?->fotografo) }}">
        </div>
        <div class="col-md-4">
            <label class="form-label" for="fuente">Fuente</label>
            <select class="form-select" id="fuente" name="fuente">
                <option value="">{{ $vacio }}</option>
                @foreach($fuentes as $k => $v)<option value="{{ $k }}" @selected(old('fuente', $f?->fuente) === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="fuente_detalle">Detalle de la fuente</label>
            <input class="form-control" id="fuente_detalle" name="fuente_detalle" maxlength="255" value="{{ old('fuente_detalle', $f?->fuente_detalle) }}">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="credito">Crédito</label>
            <input class="form-control" id="credito" name="credito" maxlength="200" value="{{ old('credito', $f?->credito) }}" placeholder="Archivo La Chilinga">
        </div>
        <div class="col-md-6">
            <label class="form-label" for="licencia">Licencia / derechos</label>
            <input class="form-control" id="licencia" name="licencia" maxlength="120" value="{{ old('licencia', $f?->licencia) }}" placeholder="Todos los derechos reservados">
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Etiquetas" icon="bi-hash">
    <input class="form-control" name="tags" value="{{ old('tags', $f ? $f->tags->pluck('nombre')->map(fn ($t) => '#'.$t)->implode(' ') : '') }}" placeholder="#ensayo #show #gira" aria-label="Etiquetas">
    @if($lote)
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="reemplazar_tags" name="reemplazar_tags" value="1">
            <label class="form-check-label" for="reemplazar_tags">Reemplazar las etiquetas (si no, se suman)</label>
        </div>
    @endif
</x-ito.form-section>
