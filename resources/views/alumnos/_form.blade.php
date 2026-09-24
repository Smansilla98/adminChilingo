@php
    $alumno = $alumno ?? null;
    $isEdit = (bool) $alumno?->exists;
@endphp

<x-ito.form-section title="Datos personales" icon="bi-person">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="nombre_apellido" class="form-label">Nombre y apellido</label>
            <input type="text" class="form-control @error('nombre_apellido') is-invalid @enderror"
                   id="nombre_apellido" name="nombre_apellido"
                   value="{{ old('nombre_apellido', $alumno?->nombre_apellido) }}" required>
            @error('nombre_apellido')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="dni" class="form-label">
                DNI
                @php $dniActual = old('dni', $alumno?->dni); @endphp
                @if($isEdit && !$dniActual)
                    <span class="badge bg-warning ms-1">Falta cargar</span>
                @endif
            </label>
            <input type="text" class="form-control @error('dni') is-invalid @enderror"
                   id="dni" name="dni" value="{{ old('dni', $alumno?->dni) }}">
            @error('dni')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="fecha_nacimiento" class="form-label">Fecha de nacimiento</label>
            <input type="date" class="form-control @error('fecha_nacimiento') is-invalid @enderror"
                   id="fecha_nacimiento" name="fecha_nacimiento"
                   value="{{ old('fecha_nacimiento', $alumno?->fecha_nacimiento?->format('Y-m-d')) }}" required>
            @error('fecha_nacimiento')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="tel" class="form-control @error('telefono') is-invalid @enderror"
                   id="telefono" name="telefono" autocomplete="tel" value="{{ old('telefono', $alumno?->telefono) }}">
            @error('telefono')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

<x-ito.form-section title="Instrumento y sede" icon="bi-music-note-beamed">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="instrumento_principal" class="form-label">Instrumento principal</label>
            <select class="form-select @error('instrumento_principal') is-invalid @enderror"
                    id="instrumento_principal" name="instrumento_principal" required>
                <option value="">Elegí…</option>
                @foreach($instrumentos as $instrumento)
                <option value="{{ $instrumento }}" {{ old('instrumento_principal', $alumno?->instrumento_principal) == $instrumento ? 'selected' : '' }}>
                    {{ $instrumento }}
                </option>
                @endforeach
            </select>
            @error('instrumento_principal')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="instrumento_secundario" class="form-label">Instrumento secundario</label>
            <select class="form-select @error('instrumento_secundario') is-invalid @enderror"
                    id="instrumento_secundario" name="instrumento_secundario">
                <option value="">Ninguno</option>
                @foreach($instrumentos as $instrumento)
                <option value="{{ $instrumento }}" {{ old('instrumento_secundario', $alumno?->instrumento_secundario) == $instrumento ? 'selected' : '' }}>
                    {{ $instrumento }}
                </option>
                @endforeach
            </select>
            @error('instrumento_secundario')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="tipo_tambor" class="form-label">Tipo de instrumento (tambor)</label>
            <select class="form-select @error('tipo_tambor') is-invalid @enderror"
                    id="tipo_tambor" name="tipo_tambor">
                <option value="">Elegí…</option>
                @foreach($tiposTambor as $tipo)
                <option value="{{ $tipo }}" {{ old('tipo_tambor', $alumno?->tipo_tambor) == $tipo ? 'selected' : '' }}>
                    {{ $tipo }}
                </option>
                @endforeach
            </select>
            @error('tipo_tambor')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="tambor_procedencia" class="form-label">Procedencia del instrumento</label>
            <select class="form-select @error('tambor_procedencia') is-invalid @enderror"
                    id="tambor_procedencia" name="tambor_procedencia">
                <option value="">Elegí…</option>
                @foreach($procedenciasTambor as $procedencia)
                <option value="{{ $procedencia }}" {{ old('tambor_procedencia', $alumno?->tambor_procedencia) == $procedencia ? 'selected' : '' }}>
                    {{ $procedencia }}
                </option>
                @endforeach
            </select>
            @error('tambor_procedencia')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="sede_id" class="form-label">Sede</label>
            <select class="form-select @error('sede_id') is-invalid @enderror"
                    id="sede_id" name="sede_id" required>
                <option value="">Elegí…</option>
                @foreach($sedes as $sede)
                <option value="{{ $sede->id }}" {{ old('sede_id', $alumno?->sede_id) == $sede->id ? 'selected' : '' }}>
                    {{ $sede->nombre }}
                </option>
                @endforeach
            </select>
            @error('sede_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</x-ito.form-section>

@include('alumnos._form_bloques', ['bloques' => $bloques, 'alumno' => $alumno])

<x-ito.form-section title="Estado y rol docente" icon="bi-toggle-on">
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1"
               @checked(old('activo', $isEdit ? $alumno->activo : true))>
        <label class="form-check-label" for="activo">Alumno activo</label>
    </div>
    @include('alumnos._form_profesor_vinculo', [
        'alumno' => $alumno,
        'profesoresSinVinculo' => $profesoresSinVinculo ?? collect(),
    ])
</x-ito.form-section>
