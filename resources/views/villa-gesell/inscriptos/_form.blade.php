@php
    /** @var \App\Models\VillaGesellInscripto $inscripto */
    $aporteAuto = abs((float) $inscripto->monto_esperado - $inscripto->aporteSegunDias($config->valorPorDia())) < 0.05;
    $calcularAporte = old('calcular_aporte', $inscripto->exists ? $aporteAuto : true);
    $sedes = $sedes ?? collect();
@endphp
<div class="row g-3">
    <div class="col-12">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
            <label class="form-label mb-0">Alumno *</label>
            <div class="btn-group btn-group-sm" role="group" aria-label="Origen del alumno">
                <input type="radio" class="btn-check" name="alumno_modo_ui" id="vg_modo_existente" value="existente" autocomplete="off" checked>
                <label class="btn btn-outline-secondary" for="vg_modo_existente">Del padrón</label>
                <input type="radio" class="btn-check" name="alumno_modo_ui" id="vg_modo_nuevo" value="nuevo" autocomplete="off">
                <label class="btn btn-outline-primary" for="vg_modo_nuevo">Alumno nuevo</label>
            </div>
        </div>

        <div id="vg_box_existente">
            <select name="alumno_id" id="vg_alumno_id" class="form-select @error('alumno_id') is-invalid @enderror" required>
                <option value="">Elegí un alumno</option>
                @foreach($alumnos as $a)
                    <option value="{{ $a->id }}" @selected(old('alumno_id', $inscripto->alumno_id) == $a->id)>{{ $a->nombre_apellido }}@if($a->dni) · DNI {{ $a->dni }}@endif</option>
                @endforeach
            </select>
            @error('alumno_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Si no está en la lista, pasá a <strong>Alumno nuevo</strong>.</div>
        </div>

        <div id="vg_box_nuevo" class="border rounded p-3 mt-2" style="border-color: var(--border) !important; background: var(--s2);" hidden>
            <p class="mb-2 small text-muted">Se crea en el padrón y queda listo para inscribir a la gira. Después podés completar sede o bloques en Alumnos.</p>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#vgModalAlumnoNuevo">
                <i class="bi bi-person-plus"></i> Abrir formulario de alta
            </button>
            <span id="vg_nuevo_status" class="ms-2 small text-success" hidden></span>
        </div>
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
        <label class="form-label">Desde (días en la gira)</label>
        <input type="date" name="fecha_desde" id="vg_fecha_desde" class="form-control" value="{{ old('fecha_desde', optional($inscripto->fecha_desde)->toDateString()) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Hasta</label>
        <input type="date" name="fecha_hasta" id="vg_fecha_hasta" class="form-control" value="{{ old('fecha_hasta', optional($inscripto->fecha_hasta)->toDateString()) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Aporte esperado (total)</label>
        <input type="number" step="0.01" min="0" name="monto_esperado" id="vg_monto_esperado" class="form-control" value="{{ old('monto_esperado', $inscripto->monto_esperado) }}" required>
        <div class="form-text" id="vg_aporte_hint">Valor por día × días de esta persona.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Monto pagado</label>
        <input type="number" step="0.01" min="0" name="monto_pagado" class="form-control" value="{{ old('monto_pagado', $inscripto->monto_pagado) }}" required>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="calcular_aporte" value="1" id="calcular_aporte" @checked($calcularAporte)>
            <label class="form-check-label" for="calcular_aporte">
                Calcular el aporte: ${{ number_format($config->valorPorDia(), 0, ',', '.') }} por día × cantidad de días
            </label>
        </div>
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
