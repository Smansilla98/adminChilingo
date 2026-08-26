@extends('layouts.guest')

@section('title', 'Cargar comprobante de cuota')
@section('guest-title', 'Cargar comprobante de cuota')
@section('guest-subtitle', 'No hace falta iniciar sesión. Buscá al alumno por DNI (no se muestra el padrón).')

@section('content')
<form id="form-comprobante" action="{{ route('comprobante-cuota-public.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="mb-3">
        <label class="form-label" for="sede_id">Sede</label>
        <select name="sede_id" id="sede_id" class="form-select" required>
            <option value="">Elegí sede</option>
            @foreach($sedes as $s)
                <option value="{{ $s->id }}" {{ (string) ($prefill['sede_id'] ?? old('sede_id')) === (string) $s->id ? 'selected' : '' }}>{{ $s->nombre }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label" for="periodo">Mes a pagar</label>
        <select id="periodo" class="form-select" required disabled>
            <option value="">Primero elegí sede</option>
        </select>
        <input type="hidden" name="año" id="año" value="{{ old('año') }}">
        <input type="hidden" name="mes" id="mes" value="{{ old('mes') }}">
    </div>
    <div class="mb-3">
        <label class="form-label" for="bloque_ids">Bloque(s)</label>
        <div class="form-text mb-1">Si va a varios grupos, elegí más de uno (Ctrl + clic).</div>
        <select name="bloque_ids[]" id="bloque_ids" class="form-select" multiple size="6" required disabled></select>
    </div>
    <div class="mb-3">
        <label class="form-label" for="dni">DNI del alumno</label>
        <div class="d-flex flex-wrap gap-2">
            <input type="text" name="dni" id="dni" class="form-control" inputmode="numeric" autocomplete="off" required minlength="6" maxlength="20" placeholder="Solo números" value="{{ $prefill['dni'] ?? old('dni') }}">
            <button type="button" class="btn btn-outline-primary" id="btn-buscar-alumno">Buscar</button>
        </div>
        <div id="hint-alumnos" class="form-text" role="status"></div>
    </div>
    <div class="mb-3" id="wrap-alumno">
        <label class="form-label" for="alumno_id">Confirmá el nombre</label>
        <select name="alumno_id" id="alumno_id" class="form-select" required disabled>
            <option value="">Buscá con el DNI</option>
        </select>
        <input type="hidden" name="lookup_token" id="lookup_token" value="">
    </div>
    <div class="mb-3 border rounded p-3 d-none" id="panel-extra-bloques">
        <div class="fw-semibold mb-2">También podés sumar otros bloques de la misma sede</div>
        <div class="form-text">Si cursa en otro bloque del mismo mes, tildalo para mandar todo junto.</div>
        <div id="extra-bloques-checks"></div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label" for="fecha_pago">Fecha en que pagaste</label>
            <input id="fecha_pago" type="date" name="fecha_pago" class="form-control" value="{{ old('fecha_pago', date('Y-m-d')) }}" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="comprobante">Comprobante (PDF o imagen)</label>
            <input id="comprobante" type="file" name="comprobante" class="form-control" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label" for="notas">Notas (opcional)</label>
        <textarea id="notas" name="notas" class="form-control" rows="2" placeholder="Ej.: transferencia, alias, a nombre de…">{{ old('notas') }}</textarea>
    </div>
    <button type="submit" class="btn btn-primary" id="btn-enviar">Enviar comprobante</button>
</form>
@endsection

@push('scripts')
<script>
(function () {
    const sede = document.getElementById('sede_id');
    const periodo = document.getElementById('periodo');
    const año = document.getElementById('año');
    const mes = document.getElementById('mes');
    const bloquesSel = document.getElementById('bloque_ids');
    const alumno = document.getElementById('alumno_id');
    const dni = document.getElementById('dni');
    const hintAlumnos = document.getElementById('hint-alumnos');
    const panelExtra = document.getElementById('panel-extra-bloques');
    const extraChecks = document.getElementById('extra-bloques-checks');
    const btnBuscar = document.getElementById('btn-buscar-alumno');
    const lookupToken = document.getElementById('lookup_token');

    async function getJson(url) {
        const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const data = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(data.error || 'No se pudo completar la búsqueda');
        return data;
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
        alumno.innerHTML = '<option value="">Buscá con el DNI</option>';
        alumno.disabled = true;
        if (lookupToken) lookupToken.value = '';
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

    async function buscarAlumno() {
        resetAlumno();
        const selected = Array.from(bloquesSel.selectedOptions).map(o => o.value).filter(Boolean);
        if (!sede.value || !año.value || !mes.value || selected.length === 0) {
            hintAlumnos.textContent = 'Elegí sede, mes y bloque primero.';
            return;
        }
        if ((dni.value || '').replace(/\D/g, '').length < 6) {
            hintAlumnos.textContent = 'Ingresá el DNI completo.';
            return;
        }
        try {
            const u = new URL('{{ url('/pagar-cuota/api/alumnos') }}', window.location.origin);
            u.searchParams.set('sede_id', sede.value);
            u.searchParams.set('año', año.value);
            u.searchParams.set('mes', mes.value);
            u.searchParams.set('dni', dni.value);
            selected.forEach(id => u.searchParams.append('bloque_ids[]', id));
            const data = await getJson(u.toString());
            alumno.innerHTML = '<option value="">Confirmá el nombre</option>';
            (data.alumnos || []).forEach(a => {
                const opt = document.createElement('option');
                opt.value = a.id;
                opt.textContent = a.nombre_apellido;
                if (a.lookup_token) opt.dataset.token = a.lookup_token;
                alumno.appendChild(opt);
            });
            alumno.disabled = (data.alumnos || []).length === 0;
            if ((data.alumnos || []).length === 1) {
                alumno.value = data.alumnos[0].id;
                alumno.dispatchEvent(new Event('change'));
            }
            hintAlumnos.textContent = data.nota_multibloque || ((data.alumnos || []).length ? 'Confirmá que es la persona correcta.' : '');
        } catch (e) {
            hintAlumnos.textContent = e.message || 'No se pudo buscar.';
        }
    }

    btnBuscar.addEventListener('click', buscarAlumno);
    dni.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            buscarAlumno();
        }
    });

    alumno.addEventListener('change', async () => {
        extraChecks.innerHTML = '';
        const opt = alumno.selectedOptions[0];
        if (lookupToken) lookupToken.value = opt?.dataset?.token || '';
        if (!alumno.value || !sede.value || !año.value || !mes.value) {
            panelExtra.classList.add('d-none');
            return;
        }
        const selected = Array.from(bloquesSel.selectedOptions).map(o => parseInt(o.value, 10));
        try {
            const u = new URL('{{ url('/pagar-cuota/api/alumno-otros-bloques') }}', window.location.origin);
            u.searchParams.set('alumno_id', alumno.value);
            u.searchParams.set('lookup_token', lookupToken?.value || '');
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
