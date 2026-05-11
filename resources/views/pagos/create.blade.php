@extends('layouts.app')

@section('title', 'Registrar pago')
@section('page-title', 'Registrar pago')

@section('content')
<div class="card">
    <div class="card-header">Nuevo pago (varios alumnos, una cuota, comprobante opcional)</div>
    <div class="card-body">
        <form action="{{ route('pagos.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha de pago *</label>
                    <input type="date" name="fecha_pago" class="form-control @error('fecha_pago') is-invalid @enderror" value="{{ old('fecha_pago', date('Y-m-d')) }}" required>
                    @error('fecha_pago')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="filtro_bloque_pagos">Bloque <span class="text-muted fw-normal">(opcional)</span></label>
                    <select id="filtro_bloque_pagos" class="form-select" aria-describedby="help-filtro-bloque">
                        <option value="">Todos los bloques</option>
                        @foreach($bloquesFiltro as $b)
                            <option value="{{ $b->id }}">{{ $b->nombre }}@if($b->sede) — {{ $b->sede->nombre }}@endif</option>
                        @endforeach
                    </select>
                    <div id="help-filtro-bloque" class="form-text">Acota el listado de cuotas al bloque elegido.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Cuota que se paga *</label>
                    <select name="cuota_id" id="cuota_id_pago" class="form-select @error('cuota_id') is-invalid @enderror" required>
                        <option value="">Seleccionar cuota</option>
                        @foreach($cuotas as $c)
                        @php
                            $periodo = $c->nombre_mes ? ($c->nombre_mes . ' ' . $c->año) : null;
                            $etiqueta = $periodo ? ($periodo . ' — ' . $c->nombre) : $c->nombre;
                            $inactiva = isset($c->activo) && !$c->activo;
                        @endphp
                        <option value="{{ $c->id }}" data-bloque-id="{{ $c->bloque_id ?? '' }}" {{ old('cuota_id') == $c->id ? 'selected' : '' }}>
                            {{ $etiqueta }} — $ {{ number_format($c->monto, 2, ',', '.') }}@if($inactiva) (Retroactiva)@endif
                        </option>
                        @endforeach
                    </select>
                    @error('cuota_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monto total *</label>
                    <input type="number" name="monto_total" class="form-control" step="0.01" min="0" value="{{ old('monto_total') }}" required>
                    @error('monto_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Alumnos que pagan (seleccionar uno o varios) *</label>
                <p class="text-muted small">Solo se listan alumnos del bloque de la cuota que <strong>aún no tienen</strong> pago registrado para esa cuota. Ctrl+clic para elegir varios. El monto total se repartirá entre los seleccionados.</p>
                <input type="search" id="buscar_alumnos_pago" class="form-control form-control-sm mb-2" placeholder="Buscar por nombre, sede o bloque…" autocomplete="off" disabled aria-label="Filtrar lista de alumnos">
                <select name="alumno_ids[]" id="alumno_ids_pago" class="form-select @error('alumno_ids') is-invalid @enderror" multiple size="12" disabled></select>
                <p id="alumnos_pago_ayuda" class="form-text text-muted">Elegí la cuota para cargar la lista de alumnos.</p>
                @error('alumno_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Comprobante (PDF, JPG, PNG)</label>
                <input type="file" name="comprobante" class="form-control @error('comprobante') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                @error('comprobante')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="2">{{ old('notas') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar pago</button>
            <a href="{{ route('pagos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const cuotaSel = document.getElementById('cuota_id_pago');
    const filtroBloque = document.getElementById('filtro_bloque_pagos');
    const alumnoSel = document.getElementById('alumno_ids_pago');
    const buscarAlumnos = document.getElementById('buscar_alumnos_pago');
    const ayuda = document.getElementById('alumnos_pago_ayuda');
    const urlApi = @json(route('pagos.api.alumnos-cuota'));
    const oldAlumnoIds = @json(array_values(array_map('intval', old('alumno_ids', []))));
    const oldCuotaId = @json((int) old('cuota_id', 0));

    function setAyuda(text) {
        if (ayuda) ayuda.textContent = text;
    }

    function aplicarFiltroCuotasPorBloque() {
        if (!cuotaSel || !filtroBloque) return;
        const bid = filtroBloque.value;
        Array.from(cuotaSel.options).forEach(function (opt) {
            if (!opt.value) {
                opt.hidden = false;
                return;
            }
            const ob = String(opt.getAttribute('data-bloque-id') || '');
            const show = !bid || ob === String(bid);
            opt.hidden = !show;
        });
        const sel = cuotaSel.selectedOptions[0];
        if (sel && sel.hidden) {
            cuotaSel.value = '';
            cargarAlumnos();
        }
    }

    function aplicarBusquedaAlumnos() {
        if (!alumnoSel || !buscarAlumnos) return;
        const q = (buscarAlumnos.value || '').trim().toLowerCase();
        Array.from(alumnoSel.options).forEach(function (opt) {
            const bloque = (opt.dataset.bloqueNombre || '').toLowerCase();
            const texto = (opt.textContent || '').toLowerCase();
            const ok = !q || texto.indexOf(q) !== -1 || bloque.indexOf(q) !== -1;
            opt.hidden = !ok;
            if (!ok && opt.selected) {
                opt.selected = false;
            }
        });
    }

    async function cargarAlumnos() {
        const cuotaId = cuotaSel && cuotaSel.value;
        if (!cuotaSel || !alumnoSel) return;
        alumnoSel.innerHTML = '';
        alumnoSel.disabled = true;
        if (!cuotaId) {
            setAyuda('Elegí la cuota para cargar la lista de alumnos.');
            if (buscarAlumnos) {
                buscarAlumnos.value = '';
                buscarAlumnos.disabled = true;
            }
            return;
        }
        setAyuda('Cargando…');
        try {
            const u = new URL(urlApi, window.location.origin);
            u.searchParams.set('cuota_id', cuotaId);
            const r = await fetch(u.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) throw new Error('Error');
            const data = await r.json();
            const lista = data.alumnos || [];
            const cuotaInt = parseInt(cuotaId, 10);
            const restaurarSeleccion = oldCuotaId > 0 && cuotaInt === oldCuotaId;
            lista.forEach(function (a) {
                const opt = document.createElement('option');
                opt.value = a.id;
                const sede = a.sede_nombre ? ' (' + a.sede_nombre + ')' : '';
                const bn = a.bloque_nombre || '';
                if (bn) {
                    opt.dataset.bloqueNombre = bn;
                    opt.textContent = (a.nombre_apellido || '') + sede + ' — ' + bn;
                } else {
                    opt.textContent = (a.nombre_apellido || '') + sede;
                }
                if (restaurarSeleccion && oldAlumnoIds.indexOf(parseInt(a.id, 10)) !== -1) {
                    opt.selected = true;
                }
                alumnoSel.appendChild(opt);
            });
            if (buscarAlumnos) {
                buscarAlumnos.value = '';
                buscarAlumnos.disabled = lista.length === 0;
            }
            aplicarBusquedaAlumnos();
            alumnoSel.disabled = lista.length === 0;
            if (lista.length === 0) {
                setAyuda('No hay alumnos disponibles: todos los del bloque ya pagaron esta cuota, o la cuota no tiene bloque asignado.');
            } else {
                setAyuda(lista.length + ' alumno(s) disponible(s) para esta cuota.');
            }
        } catch (e) {
            setAyuda('No se pudo cargar la lista. Reintentá.');
        }
    }

    if (cuotaSel) {
        cuotaSel.addEventListener('change', function () {
            cargarAlumnos();
        });
        document.addEventListener('DOMContentLoaded', function () {
            if (oldCuotaId > 0 && cuotaSel && filtroBloque) {
                const optOld = cuotaSel.querySelector('option[value="' + String(oldCuotaId) + '"]');
                if (optOld) {
                    const bidOld = optOld.getAttribute('data-bloque-id');
                    if (bidOld) {
                        filtroBloque.value = bidOld;
                    }
                }
            }
            aplicarFiltroCuotasPorBloque();
            if (cuotaSel.value) {
                cargarAlumnos();
            }
        });
    }
    if (filtroBloque) {
        filtroBloque.addEventListener('change', function () {
            aplicarFiltroCuotasPorBloque();
        });
    }
    if (buscarAlumnos) {
        buscarAlumnos.addEventListener('input', aplicarBusquedaAlumnos);
    }
})();
</script>
@endpush
