@php
    /** @var \App\Models\Bloque|null $bloque */
    $bloque = $bloque ?? null;
    $tamboresElegidos = old('tambores', $bloque?->tambores ?? []);
@endphp
<x-ito.form-section title="Datos del bloque" icon="bi-collection" help="Un bloque es un grupo de clase dentro de una sede.">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control @error('nombre') is-invalid @enderror" id="nombre" name="nombre" value="{{ old('nombre', $bloque?->nombre) }}" required>
            @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label for="año" class="form-label">Año</label>
            <input type="number" class="form-control @error('año') is-invalid @enderror" id="año" name="año" value="{{ old('año', $bloque?->año ?? 1) }}" min="1" max="6" required>
            <div class="form-text">Del 1 al 6.</div>
            @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-3">
            <label for="cantidad_max_alumnos" class="form-label">Cupo máximo</label>
            <input type="number" class="form-control @error('cantidad_max_alumnos') is-invalid @enderror" id="cantidad_max_alumnos" name="cantidad_max_alumnos" value="{{ old('cantidad_max_alumnos', $bloque?->cantidad_max_alumnos ?? 20) }}" min="1" required>
            <div class="form-text">Personas por grupo.</div>
            @error('cantidad_max_alumnos')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="sede_id" class="form-label">Sede</label>
            <select class="form-select @error('sede_id') is-invalid @enderror" id="sede_id" name="sede_id" required>
                @unless($bloque)<option value="">Elegí una sede…</option>@endunless
                @foreach($sedes as $sede)
                    <option value="{{ $sede->id }}" @selected(old('sede_id', $bloque?->sede_id) == $sede->id)>{{ $sede->nombre }}</option>
                @endforeach
            </select>
            @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="corresponde_a" class="form-label">Para quién es</label>
            <input type="text" class="form-control @error('corresponde_a') is-invalid @enderror" id="corresponde_a" name="corresponde_a" value="{{ old('corresponde_a', $bloque?->corresponde_a) }}" placeholder="Ej.: adultos, niños…">
            @error('corresponde_a')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Docente e instrumentos" icon="bi-music-note-beamed">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="profesor_id" class="form-label">Profesor titular</label>
            <select class="form-select @error('profesor_id') is-invalid @enderror" id="profesor_id" name="profesor_id">
                <option value="">Sin asignar</option>
                @foreach($profesores as $p)
                    <option value="{{ $p->id }}" @selected(old('profesor_id', $bloque?->profesor_id) == $p->id)>{{ $p->nombre }}</option>
                @endforeach
            </select>
            @error('profesor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <fieldset>
                <legend class="form-label">Tambores del bloque</legend>
                <div class="ito-check-grid">
                    @foreach($tamboresDisponibles as $t)
                        <label class="ito-check-chip" for="tambor_{{ $loop->index }}">
                            <input class="form-check-input" type="checkbox" name="tambores[]" value="{{ $t }}" id="tambor_{{ $loop->index }}" @checked(in_array($t, $tamboresElegidos))>
                            <span>{{ $t }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="form-text">Tildá los instrumentos que tocan en este grupo.</div>
            </fieldset>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" @checked(old('activo', $bloque?->activo ?? true))>
                <label class="form-check-label" for="activo">Bloque activo</label>
            </div>
        </div>
    </div>
</x-ito.form-section>
