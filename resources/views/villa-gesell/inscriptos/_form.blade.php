@php
    /** @var \App\Models\VillaGesellInscripto $inscripto */
@endphp
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Alumno *</label>
        <select name="alumno_id" class="form-select @error('alumno_id') is-invalid @enderror" required>
            <option value="">Elegí un alumno</option>
            @foreach($alumnos as $a)
                <option value="{{ $a->id }}" @selected(old('alumno_id', $inscripto->alumno_id) == $a->id)>{{ $a->nombre_apellido }}</option>
            @endforeach
        </select>
        @error('alumno_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label">Estado de pago *</label>
        <select name="estado_pago" class="form-select" required>
            @foreach(\App\Models\VillaGesellInscripto::ESTADOS_PAGO as $k => $v)
                <option value="{{ $k }}" @selected(old('estado_pago', $inscripto->estado_pago) === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Talle de remera</label>
        <select name="talle_remera" class="form-select">
            <option value="">—</option>
            @foreach(\App\Models\VillaGesellInscripto::TALLES as $k => $v)
                <option value="{{ $k }}" @selected(old('talle_remera', $inscripto->talle_remera) === $k)>{{ $v }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Monto esperado</label>
        <input type="number" step="0.01" min="0" name="monto_esperado" class="form-control" value="{{ old('monto_esperado', $inscripto->monto_esperado) }}" required>
    </div>
    <div class="col-md-3">
        <label class="form-label">Monto pagado</label>
        <input type="number" step="0.01" min="0" name="monto_pagado" class="form-control" value="{{ old('monto_pagado', $inscripto->monto_pagado) }}" required>
    </div>
    <div class="col-md-2">
        <label class="form-label">Plaza (1–{{ $config->cupo_maximo }})</label>
        <input type="number" min="1" max="{{ $config->cupo_maximo }}" name="plaza" class="form-control @error('plaza') is-invalid @enderror" value="{{ old('plaza', $inscripto->plaza) }}">
        @error('plaza')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-end">
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="lista_espera" value="1" id="lista_espera" @checked(old('lista_espera', $inscripto->lista_espera))>
            <label class="form-check-label" for="lista_espera">Lista de espera (sin plaza)</label>
        </div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Desde (días en la gira)</label>
        <input type="date" name="fecha_desde" class="form-control" value="{{ old('fecha_desde', optional($inscripto->fecha_desde)->toDateString()) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" name="fecha_hasta" class="form-control" value="{{ old('fecha_hasta', optional($inscripto->fecha_hasta)->toDateString()) }}">
    </div>

    @foreach([
        ['tambor_principal', 'tambor_principal_origen', 'Tambor principal'],
        ['tambor_secundario', 'tambor_secundario_origen', 'Tambor secundario'],
        ['tambor_terciario', 'tambor_terciario_origen', 'Tercer tambor'],
    ] as [$campo, $origen, $label])
        <div class="col-md-4">
            <label class="form-label">{{ $label }}</label>
            <select name="{{ $campo }}" class="form-select mb-1">
                <option value="">—</option>
                @foreach(\App\Models\VillaGesellInscripto::TAMBORES as $t)
                    <option value="{{ $t }}" @selected(old($campo, $inscripto->{$campo}) === $t)>{{ $t }}</option>
                @endforeach
            </select>
            <select name="{{ $origen }}" class="form-select">
                <option value="">Origen</option>
                @foreach(\App\Models\VillaGesellInscripto::ORIGENES_TAMBOR as $k => $v)
                    <option value="{{ $k }}" @selected(old($origen, $inscripto->{$origen}) === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
    @endforeach

    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="2">{{ old('notas', $inscripto->notas) }}</textarea>
    </div>
</div>
