@extends('layouts.app')

@php
    $editando = isset($pago);
    $sumAbonoEdit = $editando ? $pago->detalles->sum(fn ($d) => (float) ($d->abono_profesor ?? 0)) : 0;
    $hayAbonoEdit = $editando && $pago->detalles->contains(fn ($d) => $d->abono_profesor !== null);
    if (old('lineas') !== null) {
        $initialLineas = array_values((array) old('lineas'));
    } elseif ($editando) {
        $initialLineas = $pago->detalles->map(fn ($d) => [
            'cuota_id' => $d->cuota_id,
            'alumno_id' => $d->alumno_id,
            'monto' => $d->monto,
        ])->values()->all();
    } else {
        $initialLineas = [];
    }
@endphp

@section('title', $editando ? 'Editar pago #' . $pago->id : 'Registrar pago')
@section('page-title', $editando ? 'Editar pago' : 'Registrar pago')

@section('content')
<x-ito.shell-page
    :title="$editando ? 'Editar pago #'.$pago->id : 'Registrar pago'"
    :subtitle="$editando ? 'Corregí las líneas, el reparto o el comprobante.' : 'Cada línea es alumno + cuota + monto. El total es la suma de las líneas.'"
    :plain="true"
>
        <form action="{{ $editando ? route('pagos.update', $pago) : route('pagos.store') }}" method="POST" enctype="multipart/form-data" id="form-pago-lineas">
            @csrf
            @if($editando) @method('PUT') @endif

            <x-ito.form-section title="Cuotas que se pagan" icon="bi-receipt">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label" for="pago-fecha">Fecha del pago</label>
                        <input type="date" id="pago-fecha" name="fecha_pago" class="form-control @error('fecha_pago') is-invalid @enderror" value="{{ old('fecha_pago', $editando ? $pago->fecha_pago->format('Y-m-d') : date('Y-m-d')) }}" required>
                        @error('fecha_pago')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="filtro_bloque_pagos">Mostrar cuotas del bloque</label>
                        <select id="filtro_bloque_pagos" class="form-select" aria-describedby="help-filtro-bloque">
                            <option value="">Todos los bloques</option>
                            @foreach($bloquesFiltro as $b)
                                <option value="{{ $b->id }}" data-sede-id="{{ $b->sede_id ?? '' }}">{{ $b->nombre }}@if($b->sede) — {{ $b->sede->nombre }}@endif</option>
                            @endforeach
                        </select>
                        <div id="help-filtro-bloque" class="form-text">Opcional: acota la lista de cuotas de cada línea a ese grupo.</div>
                    </div>
                </div>

                @error('lineas')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

                <div class="table-responsive mb-2">
                    <table class="table table-sm align-middle pago-lineas mb-0" id="tabla-lineas-pago" data-ito-no-cards>
                        <thead>
                            <tr>
                                <th style="min-width:220px">Cuota</th>
                                <th style="min-width:200px">Alumno</th>
                                <th style="width:140px">Monto</th>
                                <th style="width:48px"><span class="visually-hidden">Quitar</span></th>
                            </tr>
                        </thead>
                        <tbody id="lineas-pago-body"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-add-linea-pago"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar línea</button>

                <div class="pago-total mt-3">
                    <div>
                        <label class="form-label" for="monto_total_pago">Monto total cobrado</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="monto_total" id="monto_total_pago" class="form-control form-control-lg @error('monto_total') is-invalid @enderror" step="0.01" min="0.01" value="{{ old('monto_total', $editando ? $pago->monto_total : '') }}" required>
                        </div>
                        @error('monto_total')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="pago-total-check" id="resumen-suma-lineas" aria-live="polite">
                        <span class="text-muted">Suma de las líneas</span>
                        <strong id="suma-lineas-val">0,00</strong>
                        <span id="suma-lineas-ok" class="ito-status ito-status--success d-none"><span class="ito-status-dot" aria-hidden="true"></span>Coincide</span>
                        <span id="suma-lineas-warn" class="ito-status ito-status--warning d-none"><span class="ito-status-dot" aria-hidden="true"></span>No coincide con el total</span>
                    </div>
                </div>
            </x-ito.form-section>

            <x-ito.form-section title="Parte para el profesor" icon="bi-person-badge" help="En blanco se calcula solo con el reparto configurado en la sede. Si escribís un monto, se reparte entre las líneas.">
                <div class="form-check form-switch mb-3">
                    <input type="hidden" name="liquidar_profesor" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="liquidar_profesor" value="1" id="liquidar_profesor" {{ (string) old('liquidar_profesor', $editando ? ($hayAbonoEdit ? '1' : '0') : '1') === '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="liquidar_profesor">Registrar lo que va al profesor</label>
                </div>
                <div class="row g-2 align-items-end" id="wrap_liquidacion_prof">
                    <div class="col-md-6">
                        <label class="form-label" for="monto_abono_profesor">Total para el profesor</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="monto_abono_profesor" id="monto_abono_profesor" class="form-control @error('monto_abono_profesor') is-invalid @enderror" step="0.01" min="0" value="{{ old('monto_abono_profesor', $editando && $hayAbonoEdit ? $sumAbonoEdit : '') }}" placeholder="En blanco = automático">
                        </div>
                        @error('monto_abono_profesor')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div id="prof_abono_preview" class="small mt-2 text-muted" aria-live="polite"></div>
                    </div>
                </div>
            </x-ito.form-section>

            <x-ito.form-section title="Comprobante y notas" icon="bi-paperclip">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="pago-comprobante">Comprobante</label>
                        @if($editando && $pago->comprobante_path)
                        <div class="mb-2 d-flex flex-wrap align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalComprobantePago" data-comprobante-src="{{ route('pagos.comprobante', $pago) }}" data-comprobante-label="Comprobante actual — pago #{{ $pago->id }}"><i class="bi bi-file-earmark" aria-hidden="true"></i> Ver el actual</button>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="quitar_comprobante" value="1" id="quitar_comprobante" {{ old('quitar_comprobante') ? 'checked' : '' }}>
                                <label class="form-check-label" for="quitar_comprobante">Quitarlo al guardar</label>
                            </div>
                        </div>
                        @endif
                        <input type="file" id="pago-comprobante" name="comprobante" class="form-control @error('comprobante') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                        <div class="form-text">{{ $editando ? 'Subí otro archivo solo si querés reemplazarlo.' : 'Opcional. Foto o PDF (JPG, PNG o PDF).' }}</div>
                        @error('comprobante')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="pago-notas">Notas</label>
                        <textarea id="pago-notas" name="notas" class="form-control" rows="2">{{ old('notas', $editando ? $pago->notas : '') }}</textarea>
                    </div>
                </div>
            </x-ito.form-section>

            <x-ito.form-actions :cancel="$editando ? route('pagos.show', $pago) : route('pagos.index')" :submit="$editando ? 'Guardar cambios' : 'Registrar pago'" />
        </form>
</x-ito.shell-page>
@if($editando)
@include('pagos._modal_comprobante')
@endif
@endsection

@push('scripts')
<script type="application/json" id="cuotas-meta-json">{!! json_encode($cuotasMeta ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>
(function () {
    const cuotasMeta = JSON.parse(document.getElementById('cuotas-meta-json').textContent || '[]');
    const filtroBloque = document.getElementById('filtro_bloque_pagos');
    const tbody = document.getElementById('lineas-pago-body');
    const btnAdd = document.getElementById('btn-add-linea-pago');
    const montoTotalInp = document.getElementById('monto_total_pago');
    const sumaVal = document.getElementById('suma-lineas-val');
    const sumaOk = document.getElementById('suma-lineas-ok');
    const sumaWarn = document.getElementById('suma-lineas-warn');
    const montoAbonoProf = document.getElementById('monto_abono_profesor');
    const profPreview = document.getElementById('prof_abono_preview');
    const liqProf = document.getElementById('liquidar_profesor');
    const wrapLiq = document.getElementById('wrap_liquidacion_prof');
    const urlApi = @json(route('pagos.api.alumnos-cuota', [], false));
    const pagoId = @json($editando ? $pago->id : null);
    const oldLineas = @json(array_values((array) $initialLineas));

    function filtroBloqueVal() {
        if (!filtroBloque || !filtroBloque.value) return { bid: '', sid: '' };
        const o = filtroBloque.selectedOptions[0];
        return { bid: String(filtroBloque.value), sid: String(o && o.dataset ? (o.dataset.sedeId || '') : '') };
    }

    function cuotaVisible(c, fb) {
        if (!fb.bid) return true;
        const alc = (c.alcance || 'bloque').trim();
        if (alc === 'general') return true;
        if (alc === 'sede') {
            const sid = c.sede_id != null ? String(c.sede_id) : '';
            return Boolean(sid && fb.sid && sid === fb.sid);
        }
        return String(c.bloque_id || '') === fb.bid;
    }

    function reindexLineas() {
        if (!tbody) return;
        tbody.querySelectorAll('tr').forEach(function (tr, i) {
            tr.dataset.lineIndex = String(i);
            const sc = tr.querySelector('.linea-cuota');
            const sa = tr.querySelector('.linea-alumno');
            const sm = tr.querySelector('.linea-monto');
            if (sc) sc.name = 'lineas[' + i + '][cuota_id]';
            if (sa) sa.name = 'lineas[' + i + '][alumno_id]';
            if (sm) sm.name = 'lineas[' + i + '][monto]';
        });
    }

    function llenarCuotaSelect(select, selectedId) {
        const fb = filtroBloqueVal();
        const prev = selectedId != null ? String(selectedId) : String(select.value || '');
        select.innerHTML = '<option value="">— Elegir cuota —</option>';
        cuotasMeta.forEach(function (c) {
            if (!cuotaVisible(c, fb)) return;
            const o = document.createElement('option');
            o.value = String(c.id);
            o.textContent = c.label;
            o.dataset.montoCuota = String(c.monto);
            o.dataset.abonoDocenteRef = String(c.abono_docente_ref != null ? c.abono_docente_ref : 0);
            if (c.liquidacion_resumen) o.dataset.liquidacionResumen = c.liquidacion_resumen;
            select.appendChild(o);
        });
        if (prev && Array.from(select.options).some(function (o) { return o.value === prev; })) {
            select.value = prev;
        }
    }

    function parseNum(v) {
        if (v === '' || v === null || v === undefined) return NaN;
        const n = parseFloat(String(v).replace(',', '.'));
        return isNaN(n) ? NaN : n;
    }

    function sumaMontosLineas() {
        let s = 0;
        if (!tbody) return 0;
        tbody.querySelectorAll('.linea-monto').forEach(function (inp) {
            const n = parseNum(inp.value);
            if (!isNaN(n)) s += n;
        });
        return Math.round(s * 100) / 100;
    }

    function actualizarSumaVista() {
        const s = sumaMontosLineas();
        if (sumaVal) sumaVal.textContent = s.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const tot = montoTotalInp ? parseNum(montoTotalInp.value) : NaN;
        const ok = !isNaN(tot) && Math.abs(s - tot) < 0.03;
        if (sumaOk) sumaOk.classList.toggle('d-none', !ok);
        if (sumaWarn) sumaWarn.classList.toggle('d-none', ok || isNaN(tot) || tot <= 0);
    }

    function distribuirPreviewCentavos(montos, centTot) {
        const n = montos.length;
        if (n === 0 || centTot <= 0) return montos.map(function () { return 0; });
        const sumM = montos.reduce(function (a, b) { return a + b; }, 0);
        if (sumM <= 0) return montos.map(function () { return 0; });
        const out = [];
        let asign = 0;
        for (let i = 0; i < n; i++) {
            let c;
            if (i === n - 1) c = centTot - asign;
            else {
                c = Math.floor((centTot * montos[i] / sumM) + 1e-9);
                asign += c;
            }
            out.push(c / 100);
        }
        return out;
    }

    function abonoPresetPorLineas() {
        const items = [];
        let sum = 0;
        if (!tbody) return { sum: 0, items: [] };
        tbody.querySelectorAll('tr').forEach(function (tr) {
            const sel = tr.querySelector('.linea-cuota');
            const opt = sel && sel.selectedOptions[0];
            const ab = opt && opt.dataset.abonoDocenteRef ? parseNum(opt.dataset.abonoDocenteRef) : 0;
            const resumen = opt && opt.dataset.liquidacionResumen ? opt.dataset.liquidacionResumen : '';
            if (!isNaN(ab) && ab > 0) {
                sum += ab;
                const al = tr.querySelector('.linea-alumno');
                const nom = al && al.selectedOptions[0] ? al.selectedOptions[0].textContent.trim() : '—';
                items.push({ nom: nom, ab: ab, resumen: resumen });
            }
        });
        return { sum: Math.round(sum * 100) / 100, items: items };
    }

    function actualizarPreviewAbono() {
        if (!profPreview) return;
        if (!liqProf || !liqProf.checked) {
            profPreview.textContent = 'No se guardará abono al profesor en este registro.';
            if (wrapLiq) wrapLiq.classList.add('opacity-50');
            return;
        }
        if (wrapLiq) wrapLiq.classList.remove('opacity-50');
        const fmt = function (x) { return x.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        const totalAbono = montoAbonoProf && montoAbonoProf.value !== '' ? parseNum(montoAbonoProf.value) : NaN;
        const preset = abonoPresetPorLineas();

        if ((isNaN(totalAbono) || montoAbonoProf.value === '') && preset.items.length > 0) {
            let html = '<p class="mb-1"><strong>Automático (regla por sede):</strong> total docente $' + fmt(preset.sum) + '</p><ul class="mb-0 ps-3">';
            preset.items.forEach(function (it) {
                html += '<li class="mb-1"><strong>' + it.nom + '</strong>: $' + fmt(it.ab);
                if (it.resumen) html += ' <span class="text-muted">(' + it.resumen + ')</span>';
                html += '</li>';
            });
            html += '</ul>';
            profPreview.innerHTML = html;
            return;
        }

        if (isNaN(totalAbono) || totalAbono < 0) {
            profPreview.textContent = 'Elegí cuotas en las líneas para ver el abono sugerido por sede, o ingresá un total manual.';
            return;
        }
        const montos = [];
        const metas = [];
        if (!tbody) return;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            const m = parseNum(tr.querySelector('.linea-monto') && tr.querySelector('.linea-monto').value);
            const sel = tr.querySelector('.linea-cuota');
            const opt = sel && sel.selectedOptions[0];
            const mc = opt && opt.dataset.montoCuota ? parseNum(opt.dataset.montoCuota) : NaN;
            montos.push(isNaN(m) ? 0 : m);
            metas.push({ montoCuota: mc });
        });
        const sumM = montos.reduce(function (a, b) { return a + b; }, 0);
        if (sumM <= 0) {
            profPreview.textContent = 'Completá montos en las líneas para calcular el reparto del abono docente.';
            return;
        }
        const centTot = Math.round(totalAbono * 100);
        const partes = distribuirPreviewCentavos(montos, centTot);
        let html = '<ul class="mb-0 ps-3">';
        tbody.querySelectorAll('tr').forEach(function (tr, i) {
            const ab = partes[i] || 0;
            const mc = metas[i] && !isNaN(metas[i].montoCuota) ? metas[i].montoCuota : NaN;
            let pct = '';
            if (!isNaN(mc) && mc > 0) {
                pct = ' · % sobre cuota ref.: ' + fmt(Math.round(10000 * ab / mc) / 100) + '%';
            }
            const al = tr.querySelector('.linea-alumno');
            const nom = al && al.selectedOptions[0] ? al.selectedOptions[0].textContent.trim() : '—';
            html += '<li class="mb-1"><strong>' + nom + '</strong>: abono ~$' + fmt(ab) + pct + '</li>';
        });
        html += '</ul>';
        profPreview.innerHTML = html;
    }

    function bindLinea(tr) {
        const sc = tr.querySelector('.linea-cuota');
        const sa = tr.querySelector('.linea-alumno');
        const sm = tr.querySelector('.linea-monto');
        const btn = tr.querySelector('.btn-quitar-linea');
        if (sc) {
            sc.addEventListener('change', function () {
                cargarAlumnosFila(tr);
                if (sm && (sm.value === '' || sm.value === null)) {
                    const opt = sc.selectedOptions[0];
                    const mc = opt && opt.dataset.montoCuota ? parseNum(opt.dataset.montoCuota) : NaN;
                    if (!isNaN(mc)) sm.value = String(mc);
                }
                actualizarSumaVista();
                actualizarPreviewAbono();
            });
        }
        if (sa) {
            sa.addEventListener('change', function () {
                actualizarPreviewAbono();
            });
        }
        if (sm) {
            sm.addEventListener('input', function () {
                actualizarSumaVista();
                actualizarPreviewAbono();
            });
        }
        if (btn) {
            btn.addEventListener('click', function () {
                if (tbody.querySelectorAll('tr').length <= 1) return;
                tr.remove();
                reindexLineas();
                actualizarSumaVista();
                actualizarPreviewAbono();
                actualizarBotonesQuitar();
            });
        }
    }

    function actualizarBotonesQuitar() {
        if (!tbody) return;
        const n = tbody.querySelectorAll('tr').length;
        tbody.querySelectorAll('.btn-quitar-linea').forEach(function (b) {
            b.disabled = n <= 1;
        });
    }

    async function cargarAlumnosFila(tr, preselectAlumnoId) {
        const sc = tr.querySelector('.linea-cuota');
        const sa = tr.querySelector('.linea-alumno');
        if (!sc || !sa) return;
        const cuotaId = sc.value;
        sa.innerHTML = '<option value="">—</option>';
        sa.disabled = true;
        if (!cuotaId) {
            sa.innerHTML = '<option value="">Primero elegí la cuota</option>';
            return;
        }
        try {
            const u = new URL(urlApi, window.location.origin);
            u.searchParams.set('cuota_id', cuotaId);
            if (pagoId) u.searchParams.set('pago_id', String(pagoId));
            const r = await fetch(u.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) throw new Error('err');
            const data = await r.json();
            const lista = data.alumnos || [];
            lista.forEach(function (a) {
                const o = document.createElement('option');
                o.value = String(a.id);
                const sede = a.sede_nombre ? ' (' + a.sede_nombre + ')' : '';
                const bn = a.bloque_nombre || '';
                o.textContent = (a.nombre_apellido || '') + sede + (bn ? ' — ' + bn : '');
                sa.appendChild(o);
            });
            sa.disabled = lista.length === 0;
            if (preselectAlumnoId) {
                const ps = String(preselectAlumnoId);
                if (Array.from(sa.options).some(function (o) { return o.value === ps; })) {
                    sa.value = ps;
                }
            }
        } catch (e) {
            sa.innerHTML = '<option value="">Error al cargar</option>';
        }
        actualizarPreviewAbono();
    }

    function crearFila() {
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td><select class="form-select form-select-sm linea-cuota" required aria-label="Cuota"><option value="">— Elegir cuota —</option></select></td>' +
            '<td><select class="form-select form-select-sm linea-alumno" required disabled aria-label="Alumno"><option value="">Primero la cuota</option></select></td>' +
            '<td><input type="number" class="form-control form-control-sm linea-monto" step="0.01" min="0.01" value="" required aria-label="Monto"></td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-ghost text-danger btn-quitar-linea" aria-label="Quitar línea"><i class="bi bi-x-lg" aria-hidden="true"></i></button></td>';
        const sel = tr.querySelector('.linea-cuota');
        llenarCuotaSelect(sel, null);
        return tr;
    }

    async function addLinea(initial) {
        if (!tbody) return;
        const tr = crearFila();
        tbody.appendChild(tr);
        reindexLineas();
        bindLinea(tr);
        actualizarBotonesQuitar();
        if (initial && initial.cuota_id) {
            const sc = tr.querySelector('.linea-cuota');
            const sm = tr.querySelector('.linea-monto');
            sc.value = String(initial.cuota_id);
            await cargarAlumnosFila(tr, initial.alumno_id);
            if (sm && initial.monto != null && initial.monto !== '') {
                sm.value = String(initial.monto);
            }
        }
        actualizarSumaVista();
        actualizarPreviewAbono();
    }

    function refrescarSelectsCuotaTodos() {
        if (!tbody) return;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            const sel = tr.querySelector('.linea-cuota');
            const prev = sel.value;
            llenarCuotaSelect(sel, prev);
            if (prev && sel.value !== prev) {
                sel.value = '';
                cargarAlumnosFila(tr, null);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', async function () {
        if (!tbody) return;
        tbody.innerHTML = '';
        const arr = Array.isArray(oldLineas) && oldLineas.length ? oldLineas : [{}];
        for (let j = 0; j < arr.length; j++) {
            await addLinea(arr[j]);
        }
    });

    if (btnAdd) {
        btnAdd.addEventListener('click', function () {
            void addLinea(null);
        });
    }
    if (filtroBloque) {
        filtroBloque.addEventListener('change', function () {
            refrescarSelectsCuotaTodos();
            actualizarSumaVista();
            actualizarPreviewAbono();
        });
    }
    if (montoTotalInp) {
        montoTotalInp.addEventListener('input', actualizarSumaVista);
    }
    if (montoAbonoProf) {
        montoAbonoProf.addEventListener('input', actualizarPreviewAbono);
    }
    if (liqProf) {
        liqProf.addEventListener('change', actualizarPreviewAbono);
    }
})();
</script>
@endpush
