@extends('layouts.guest')

@section('title', 'Cargar comprobante de cuota')
@section('guest-title', 'Cargar comprobante de cuota')
@section('guest-subtitle', 'No necesitás iniciar sesión. Completá los datos y adjuntá el archivo.')

@section('content')
<form id="form-comprobante" action="{{ route('comprobante-cuota-public.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
        <label class="form-label">Sede *</label>
        <select name="sede_id" id="sede_id" class="form-select" required>
            <option value="">Elegí sede</option>
            @foreach($sedes as $s)
                <option value="{{ $s->id }}" {{ (string) old('sede_id') === (string) $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Mes a pagar *</label>
        <select id="periodo" class="form-select" required disabled>
            <option value="">Primero elegí sede</option>
        </select>
        <input type="hidden" name="año" id="año" value="{{ old('año') }}">
        <input type="hidden" name="mes" id="mes" value="{{ old('mes') }}">
    </div>
    <div class="mb-3">
        <label class="form-label">Bloque(s) *</label>
        <p class="text-muted small mb-1">Podés elegir varios bloques (Ctrl+clic) si en todos cursa el mismo alumno y querés declarar el pago de varias cuotas juntas.</p>
        <select name="bloque_ids[]" id="bloque_ids" class="form-select" multiple size="8" required disabled></select>
    </div>
    <div class="mb-3" id="wrap-alumno">
        <label class="form-label">Alumno *</label>
        <select name="alumno_id" id="alumno_id" class="form-select" required disabled>
            <option value="">Elegí bloque(s) y período</option>
        </select>
        <div id="hint-alumnos" class="form-text"></div>
    </div>
    <div class="mb-3 border rounded p-3 d-none" id="panel-extra-bloques">
        <div class="fw-semibold mb-2">También podés sumar otros bloques de la misma sede</div>
        <p class="text-muted small">Si el alumno cursa en más bloques y hay cuota para el mismo mes, podés marcarlos para incluirlos en este mismo envío.</p>
        <div id="extra-bloques-checks"></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Fecha en que pagaste *</label>
            <input type="date" name="fecha_pago" class="form-control" value="{{ old('fecha_pago', date('Y-m-d')) }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Comprobante *</label>
            <input type="file" name="comprobante" class="form-control" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Notas (opcional)</label>
        <textarea name="notas" class="form-control" rows="2" placeholder="Ej.: transferencia, alias, a nombre de…">{{ old('notas') }}</textarea>
    </div>
    <button type="submit" class="btn btn-primary" id="btn-enviar">Enviar comprobante</button>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const sede = document.getElementById('sede_id');
    const periodo = document.getElementById('periodo');
    const año = document.getElementById('año');
    const mes = document.getElementById('mes');
    const bloquesSel = document.getElementById('bloque_ids');
    const alumno = document.getElementById('alumno_id');
    const hintAlumnos = document.getElementById('hint-alumnos');
    const panelExtra = document.getElementById('panel-extra-bloques');
    const extraChecks = document.getElementById('extra-bloques-checks');

    async function getJson(url) {
        const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        if (!r.ok) throw new Error(await r.text());
        return r.json();
    }

    function resetPeriodo() {
        periodo.innerHTML = '<option value="">Cargando…</option>';
        periodo.disabled = true;
        año.value = '';
        mes.value = '';
    }
    function resetBloques() {
        bloquesSel.innerHTML = '';
        bloquesSel.disabled = true;
    }
    function resetAlumno() {
        alumno.innerHTML = '<option value="">Elegí bloque(s)</option>';
        alumno.disabled = true;
        hintAlumnos.textContent = '';
        panelExtra.classList.add('d-none');
        extraChecks.innerHTML = '';
    }

    sede.addEventListener('change', async () => {
        resetBloques();
        resetAlumno();
        if (!sede.value) {
            periodo.innerHTML = '<option value="">Primero elegí sede</option>';
            periodo.disabled = true;
            return;
        }
        resetPeriodo();
        try {
            const data = await getJson('{{ url('/pagar-cuota/api/periodos') }}?sede_id=' + encodeURIComponent(sede.value));
            periodo.innerHTML = '<option value="">Elegí mes</option>';
            (data.periodos || []).forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.año + '-' + p.mes;
                opt.textContent = p.label;
                periodo.appendChild(opt);
            });
            periodo.disabled = false;
        } catch (e) {
            periodo.innerHTML = '<option value="">No se pudieron cargar períodos</option>';
        }
    });

    periodo.addEventListener('change', async () => {
        resetBloques();
        resetAlumno();
        const v = periodo.value;
        if (!v || !sede.value) return;
        const [y, m] = v.split('-');
        año.value = y;
        mes.value = m;
        bloquesSel.innerHTML = '<option value="">Cargando…</option>';
        try {
            const u = new URL('{{ url('/pagar-cuota/api/bloques') }}', window.location.origin);
            u.searchParams.set('sede_id', sede.value);
            u.searchParams.set('año', y);
            u.searchParams.set('mes', m);
            const data = await getJson(u.toString());
            bloquesSel.innerHTML = '';
            (data.bloques || []).forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.nombre + ' — $ ' + (b.monto != null ? b.monto.toLocaleString('es-AR') : '');
                bloquesSel.appendChild(opt);
            });
            bloquesSel.disabled = (data.bloques || []).length === 0;
        } catch (e) {
            bloquesSel.innerHTML = '';
        }
    });

    async function cargarAlumnos() {
        resetAlumno();
        const selected = Array.from(bloquesSel.selectedOptions).map(o => o.value).filter(Boolean);
        if (!sede.value || !año.value || !mes.value || selected.length === 0) return;
        alumno.innerHTML = '<option value="">Cargando…</option>';
        try {
            const u = new URL('{{ url('/pagar-cuota/api/alumnos') }}', window.location.origin);
            u.searchParams.set('sede_id', sede.value);
            u.searchParams.set('año', año.value);
            u.searchParams.set('mes', mes.value);
            selected.forEach(id => u.searchParams.append('bloque_ids[]', id));
            const data = await getJson(u.toString());
            alumno.innerHTML = '<option value="">Elegí alumno</option>';
            (data.alumnos || []).forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.nombre_apellido + (a.dni ? ' — DNI ' + a.dni : '');
                alumno.appendChild(opt);
            });
            alumno.disabled = (data.alumnos || []).length === 0;
            hintAlumnos.textContent = data.nota_multibloque || '';
        } catch (e) {
            alumno.innerHTML = '<option value="">Error al cargar alumnos</option>';
        }
    }

    bloquesSel.addEventListener('change', cargarAlumnos);

    alumno.addEventListener('change', async () => {
        extraChecks.innerHTML = '';
        if (!alumno.value || !sede.value || !año.value || !mes.value) {
            panelExtra.classList.add('d-none');
            return;
        }
        const selected = Array.from(bloquesSel.selectedOptions).map(o => parseInt(o.value, 10));
        try {
            const u = new URL('{{ url('/pagar-cuota/api/alumno-otros-bloques') }}', window.location.origin);
            u.searchParams.set('alumno_id', alumno.value);
            u.searchParams.set('sede_id', sede.value);
            u.searchParams.set('año', año.value);
            u.searchParams.set('mes', mes.value);
            const data = await getJson(u.toString());
            const rows = (data.bloques_cuotas || []).filter(x => !selected.includes(x.bloque_id));
            if (rows.length === 0) {
                panelExtra.classList.add('d-none');
                return;
            }
            rows.forEach(x => {
                const id = 'xb_' + x.bloque_id;
                const wrap = document.createElement('div');
                wrap.className = 'form-check';
                wrap.innerHTML = '<input class="form-check-input" type="checkbox" name="bloque_ids[]" id="' + id + '" value="' + x.bloque_id + '">' +
                    '<label class="form-check-label" for="' + id + '">' + x.bloque_nombre + ' — ' + x.cuota_nombre + ' ($ ' + x.monto.toLocaleString('es-AR') + ')</label>';
                extraChecks.appendChild(wrap);
            });
            panelExtra.classList.remove('d-none');
        } catch (e) {
            panelExtra.classList.add('d-none');
        }
    });
})();
</script>
@endpush
