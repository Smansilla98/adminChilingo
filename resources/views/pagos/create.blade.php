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
                        @php
                            $sedeCuota = $c->bloque?->sede;
                            $liqRet = $sedeCuota?->liquidacion_retencion_escuela;
                            $liqPorc = $sedeCuota?->liquidacion_porc_docente;
                        @endphp
                        <option value="{{ $c->id }}" data-bloque-id="{{ $c->bloque_id ?? '' }}"
                            data-sede="{{ $sedeCuota?->nombre ?? '' }}"
                            data-profesor="{{ $c->bloque?->profesor?->nombre ?? '' }}"
                            data-monto-cuota="{{ $c->monto }}"
                            data-sede-liq-ret="{{ $liqRet ?? 0 }}"
                            data-sede-liq-porc="{{ $liqPorc ?? 40 }}"
                            {{ old('cuota_id') == $c->id ? 'selected' : '' }}>
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

            <p id="ctx-cuota-pago" class="form-text text-muted mb-3" aria-live="polite"></p>

            <div class="mb-3">
                <label class="form-label">Alumnos que pagan (seleccionar uno o varios) *</label>
                <p class="text-muted small">Solo se listan alumnos del bloque de la cuota que <strong>aún no tienen</strong> pago registrado para esa cuota. Ctrl+clic para elegir varios. El monto total se repartirá entre los seleccionados.</p>
                <input type="search" id="buscar_alumnos_pago" class="form-control form-control-sm mb-2" placeholder="Buscar por nombre, sede o bloque…" autocomplete="off" disabled aria-label="Filtrar lista de alumnos">
                <select name="alumno_ids[]" id="alumno_ids_pago" class="form-select @error('alumno_ids') is-invalid @enderror" multiple size="12" disabled></select>
                <p id="alumnos_pago_ayuda" class="form-text text-muted">Elegí la cuota para cargar la lista de alumnos.</p>
                @error('alumno_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="border rounded p-3 mb-3">
                <div class="fw-semibold mb-2">Liquidación al profesor <span class="text-muted fw-normal small">(por cada alumno que paga)</span></div>
                <p class="text-muted small mb-3">Sobre el <strong>monto de referencia de la cuota</strong> (X), primero podés apartar una suma fija para la escuela (<strong>retención</strong>); sobre lo que queda (<strong>base</strong>) se aplica el <strong>% al docente</strong>. Así se ve claro: <strong>docente = base × %</strong> y <strong>resto de X para la escuela</strong> (referencia: cuota − abono docente). Los valores por defecto salen de la <a href="{{ route('sedes.index') }}">configuración de la sede</a> del bloque; podés ajustarlos acá antes de guardar.</p>
                <div class="form-check mb-3">
                    <input type="hidden" name="liquidar_profesor" value="0">
                    <input class="form-check-input" type="checkbox" name="liquidar_profesor" value="1" id="liquidar_profesor" {{ (string) old('liquidar_profesor', '1') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="liquidar_profesor">Calcular y guardar abono al profesor</label>
                </div>
                <div class="row g-2 align-items-end" id="wrap_liquidacion_prof">
                    <div class="col-md-4">
                        <label class="form-label" for="prof_abono_base">Base para liquidar al prof. ($)</label>
                        <input type="number" name="prof_abono_base" id="prof_abono_base" class="form-control @error('prof_abono_base') is-invalid @enderror" step="0.01" min="0" value="{{ old('prof_abono_base') }}" placeholder="Según sede: cuota − retención">
                        @error('prof_abono_base')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Por defecto: <strong>monto cuota − retención escuela</strong> (según la sede). Podés corregir el importe si hace falta.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="prof_abono_porcentaje">% al profesor</label>
                        <input type="number" name="prof_abono_porcentaje" id="prof_abono_porcentaje" class="form-control @error('prof_abono_porcentaje') is-invalid @enderror" step="0.1" min="0" max="100" value="{{ old('prof_abono_porcentaje', 40) }}">
                        @error('prof_abono_porcentaje')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">Por defecto según la sede del bloque.</div>
                    </div>
                    <div class="col-md-12">
                        <div id="prof_abono_preview" class="small mt-2 text-muted" aria-live="polite"></div>
                    </div>
                </div>
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
    const ctxCuota = document.getElementById('ctx-cuota-pago');
    const profBase = document.getElementById('prof_abono_base');
    const profPct = document.getElementById('prof_abono_porcentaje');
    const profPreview = document.getElementById('prof_abono_preview');
    const liqProf = document.getElementById('liquidar_profesor');
    const wrapLiq = document.getElementById('wrap_liquidacion_prof');
    const montoTotalInp = document.querySelector('input[name="monto_total"]');
    {{-- Ruta relativa: evita fetch al host de APP_URL si difiere del dominio real (p. ej. Railway). --}}
    const urlApi = @json(route('pagos.api.alumnos-cuota', [], false));
    const oldAlumnoIds = @json(array_values(array_map('intval', old('alumno_ids', []))));
    const oldCuotaId = @json((int) old('cuota_id', 0));

    function setAyuda(text) {
        if (ayuda) ayuda.textContent = text;
    }

    function actualizarContextoCuota() {
        if (!ctxCuota || !cuotaSel) return;
        const opt = cuotaSel.selectedOptions[0];
        if (!opt || !opt.value) {
            ctxCuota.textContent = '';
            return;
        }
        const sede = (opt.dataset.sede || '').trim() || '—';
        const prof = (opt.dataset.profesor || '').trim() || '—';
        const raw = opt.dataset.montoCuota;
        let montoTxt = '';
        if (raw !== undefined && raw !== '') {
            const n = parseFloat(String(raw).replace(',', '.'));
            if (!isNaN(n)) {
                montoTxt = ' · monto de cuota (referencia) $' + n.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
            }
        }
        let liqTxt = '';
        const ret = (function () {
            const v = opt.dataset.sedeLiqRet;
            if (v === undefined || v === '') return 0;
            const n = parseFloat(String(v).replace(',', '.'));
            return isNaN(n) ? 0 : n;
        })();
        const porcCfg = (function () {
            const v = opt.dataset.sedeLiqPorc;
            if (v === undefined || v === '') return 40;
            const n = parseFloat(String(v).replace(',', '.'));
            return isNaN(n) ? 40 : n;
        })();
        if (ret > 0) {
            liqTxt += ' Retención escuela (config. sede): $' + ret.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + '.';
        }
        liqTxt += ' % docente por defecto (sede): ' + porcCfg + '%.';
        ctxCuota.textContent = 'Contexto del registro: sede ' + sede + ' · profesor titular del bloque ' + prof + montoTxt + '.' + liqTxt;
    }

    function aplicarLiquidacionDesdeSede() {
        if (!profBase || !profPct || !cuotaSel) return;
        const opt = cuotaSel.selectedOptions[0];
        if (!opt || !opt.value) {
            return;
        }
        const rawM = opt.dataset.montoCuota;
        if (rawM === undefined || rawM === '') return;
        const montoCuota = parseFloat(String(rawM).replace(',', '.'));
        if (isNaN(montoCuota)) return;
        const ret = (function () {
            const v = opt.dataset.sedeLiqRet;
            if (v === undefined || v === '') return 0;
            const n = parseFloat(String(v).replace(',', '.'));
            return isNaN(n) ? 0 : n;
        })();
        const porcCfg = (function () {
            const v = opt.dataset.sedeLiqPorc;
            if (v === undefined || v === '') return 40;
            const n = parseFloat(String(v).replace(',', '.'));
            return isNaN(n) ? 40 : n;
        })();
        const base = Math.max(0, Math.round((montoCuota - (isNaN(ret) ? 0 : ret)) * 100) / 100);
        profBase.value = String(base);
        profPct.value = String(isNaN(porcCfg) ? 40 : porcCfg);
    }

    function contarAlumnosSeleccionados() {
        if (!alumnoSel) return 0;
        return Array.from(alumnoSel.selectedOptions).filter(function (o) { return o.value && !o.hidden; }).length;
    }

    function actualizarPreviewAbono() {
        if (!profPreview) return;
        if (!liqProf || !liqProf.checked) {
            profPreview.textContent = 'No se guardará abono al profesor en este registro.';
            if (wrapLiq) wrapLiq.classList.add('opacity-50');
            return;
        }
        if (wrapLiq) wrapLiq.classList.remove('opacity-50');
        const opt = cuotaSel && cuotaSel.selectedOptions[0];
        const montoCuota = opt && opt.dataset.montoCuota ? parseFloat(String(opt.dataset.montoCuota).replace(',', '.')) : NaN;
        let base = profBase && profBase.value !== '' ? parseFloat(String(profBase.value).replace(',', '.')) : NaN;
        if (isNaN(base) && !isNaN(montoCuota)) {
            base = montoCuota;
        }
        const pct = profPct && profPct.value !== '' ? parseFloat(String(profPct.value).replace(',', '.')) : 40;
        if (isNaN(base) || isNaN(pct)) {
            profPreview.textContent = 'Completá base y % para ver el cálculo.';
            return;
        }
        const porAlumno = Math.round(base * (pct / 100) * 100) / 100;
        const n = contarAlumnosSeleccionados();
        const total = Math.round(porAlumno * n * 100) / 100;
        const fmt = function (x) {
            return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };
        let escuelaHtml = '';
        if (!isNaN(montoCuota) && !isNaN(base)) {
            const refEscuela = Math.max(0, Math.round((montoCuota - base) * 100) / 100);
            escuelaHtml = ' Sobre la cuota de referencia <strong>$' + fmt(montoCuota) + '</strong>, la base para el prof. es <strong>$' + fmt(base) + '</strong> (retención escuela ref. <strong>$' + fmt(refEscuela) + '</strong>).';
        }
        const restoEscuela = !isNaN(montoCuota) ? Math.max(0, Math.round((montoCuota - porAlumno) * 100) / 100) : NaN;
        if (!isNaN(restoEscuela)) {
            escuelaHtml += ' <strong>Docente</strong> (por alumno): $' + fmt(porAlumno) + ' · <strong>Resto para escuela</strong> (ref. cuota − docente): $' + fmt(restoEscuela) + '.';
        }
        profPreview.innerHTML = escuelaHtml + ' ' +
            (n > 0 ? 'Con <strong>' + n + '</strong> alumno(s), total abono prof.: <strong>$' + fmt(total) + '</strong>.' : 'Seleccioná al menos un alumno para ver el total.');
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
            actualizarContextoCuota();
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
            actualizarPreviewAbono();
        } catch (e) {
            setAyuda('No se pudo cargar la lista. Reintentá.');
        }
    }

    if (cuotaSel) {
        cuotaSel.addEventListener('change', function () {
            aplicarLiquidacionDesdeSede();
            actualizarContextoCuota();
            actualizarPreviewAbono();
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
            actualizarContextoCuota();
            if (profBase && cuotaSel && cuotaSel.value && (profBase.value === '' || profBase.value === null)) {
                aplicarLiquidacionDesdeSede();
            }
            actualizarPreviewAbono();
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
    if (profBase) {
        profBase.addEventListener('input', actualizarPreviewAbono);
    }
    if (profPct) {
        profPct.addEventListener('input', actualizarPreviewAbono);
    }
    if (montoTotalInp) {
        montoTotalInp.addEventListener('input', actualizarPreviewAbono);
    }
    if (liqProf) {
        liqProf.addEventListener('change', actualizarPreviewAbono);
    }
    if (alumnoSel) {
        alumnoSel.addEventListener('change', actualizarPreviewAbono);
    }
})();
</script>
@endpush
