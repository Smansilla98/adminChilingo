@php
    /** @var \App\Models\Evento|null $evento */
    $evento = $evento ?? null;
@endphp
<x-ito.form-section title="Qué es" icon="bi-calendar-event">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="evento-titulo">Título</label>
            <input type="text" id="evento-titulo" name="titulo" class="form-control @error('titulo') is-invalid @enderror" value="{{ old('titulo', $evento?->titulo) }}" required>
            @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="evento-tipo">Tipo</label>
            <select id="evento-tipo" name="tipo_evento" class="form-select @error('tipo_evento') is-invalid @enderror" required>
                @foreach($tiposEvento as $t)
                    <option value="{{ $t }}" @selected(old('tipo_evento', $evento?->tipo_evento) === $t)>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                @endforeach
            </select>
            @error('tipo_evento')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="evento-descripcion">Descripción</label>
            <textarea id="evento-descripcion" name="descripcion" class="form-control @error('descripcion') is-invalid @enderror" rows="3">{{ old('descripcion', $evento?->descripcion) }}</textarea>
            @error('descripcion')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Cuándo" icon="bi-clock">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="evento-fecha">Fecha</label>
            <input type="date" id="evento-fecha" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha', $evento?->fecha?->format('Y-m-d') ?? date('Y-m-d')) }}" required>
            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="evento-desde">Desde</label>
            <input type="time" id="evento-desde" name="hora_inicio" class="form-control @error('hora_inicio') is-invalid @enderror" value="{{ old('hora_inicio', $evento?->hora_inicio?->format('H:i')) }}">
            @error('hora_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="evento-hasta">Hasta</label>
            <input type="time" id="evento-hasta" name="hora_fin" class="form-control @error('hora_fin') is-invalid @enderror" value="{{ old('hora_fin', $evento?->hora_fin?->format('H:i')) }}">
            @error('hora_fin')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="evento-personas">Personas</label>
            <input type="number" id="evento-personas" name="cantidad_personas" class="form-control @error('cantidad_personas') is-invalid @enderror" min="0" value="{{ old('cantidad_personas', $evento?->cantidad_personas) }}">
            @error('cantidad_personas')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Dónde y con quién" icon="bi-geo-alt" help="Opcional. Si elegís sede, se avisa a las familias de esa sede el día anterior.">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="evento-sede">Sede</label>
            <select id="evento-sede" name="sede_id" class="form-select @error('sede_id') is-invalid @enderror">
                <option value="">Todas / sin sede</option>
                @foreach($sedes as $s)
                    <option value="{{ $s->id }}" @selected(old('sede_id', $evento?->sede_id) == $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
            @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="evento-profesor">Profesor a cargo</label>
            <select id="evento-profesor" name="profesor_id" class="form-select @error('profesor_id') is-invalid @enderror">
                <option value="">Sin profesor</option>
                @foreach($profesores as $p)
                    <option value="{{ $p->id }}" @selected(old('profesor_id', $evento?->profesor_id) == $p->id)>{{ $p->nombre }}</option>
                @endforeach
            </select>
            @error('profesor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="evento-bloque">Bloque</label>
            <select id="evento-bloque" name="bloque_id" class="form-select @error('bloque_id') is-invalid @enderror">
                <option value="">Sin bloque</option>
                @foreach($bloques as $b)
                    <option value="{{ $b->id }}" @selected(old('bloque_id', $evento?->bloque_id) == $b->id)>{{ $b->nombre }} ({{ $b->sede?->nombre ?? '—' }})</option>
                @endforeach
            </select>
            @error('bloque_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>
