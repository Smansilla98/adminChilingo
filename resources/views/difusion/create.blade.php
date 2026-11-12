@extends('layouts.app')

@section('title', 'Nuevo mensaje a un grupo')
@section('page-title', 'Nuevo mensaje a un grupo')

@section('content')
@php
    $alcanceElegido = old('alcance', array_key_first($catalogo['alcances']));
    $canalesElegidos = old('canales', ['interna', 'push']);
@endphp
<x-ito.shell-page title="Nuevo mensaje a un grupo" subtitle="Elegí a quiénes, por dónde y qué decirles. Antes de mandar, mirá a cuántas personas les llega." :plain="true">
    <form method="POST" action="{{ route('difusion.store') }}" id="form-difusion" style="max-width: 900px">
        @csrf

        <x-ito.form-section title="A quiénes" icon="bi-people">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="alcance">Grupo</label>
                    <select class="form-select @error('alcance') is-invalid @enderror" name="alcance" id="alcance">
                        @foreach($catalogo['alcances'] as $clave => $etiqueta)
                            <option value="{{ $clave }}" @selected($alcanceElegido === $clave)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    @error('alcance')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12" data-alcance="sedes">
                    <span class="form-label d-block">Sedes</span>
                    <div class="ito-check-grid">
                        @foreach($catalogo['sedes'] as $s)
                            <label class="ito-check-chip"><input class="form-check-input" type="checkbox" name="sede_ids[]" value="{{ $s['id'] }}" @checked(in_array($s['id'], array_map('intval', old('sede_ids', [])), true))> <span>{{ $s['nombre'] }}</span></label>
                        @endforeach
                    </div>
                    @error('sede_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12" data-alcance="bloques">
                    <span class="form-label d-block">Bloques</span>
                    <div class="ito-check-grid">
                        @foreach($catalogo['bloques'] as $b)
                            <label class="ito-check-chip"><input class="form-check-input" type="checkbox" name="bloque_ids[]" value="{{ $b['id'] }}" @checked(in_array($b['id'], array_map('intval', old('bloque_ids', [])), true))> <span>{{ $b['nombre'] }} <span class="text-muted">· {{ $b['sede'] }}</span></span></label>
                        @endforeach
                    </div>
                    @error('bloque_ids')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6" data-alcance="show">
                    <label class="form-label" for="show_id">Show</label>
                    <select class="form-select @error('show_id') is-invalid @enderror" name="show_id" id="show_id">
                        <option value="">Elegí un show</option>
                        @foreach($catalogo['shows'] as $s)
                            <option value="{{ $s['id'] }}" @selected((int) old('show_id') === $s['id'])>{{ \Illuminate\Support\Carbon::parse($s['fecha'])->format('d/m') }} · {{ $s['titulo'] }}</option>
                        @endforeach
                    </select>
                    @error('show_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 d-flex flex-wrap gap-4">
                    <div class="form-check form-switch">
                        <input type="hidden" name="alumnos" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="alumnos" name="alumnos" value="1" @checked(old('alumnos', true))>
                        <label class="form-check-label" for="alumnos">Alumnos</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="solo_con_deuda" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="solo_con_deuda" name="solo_con_deuda" value="1" @checked(old('solo_con_deuda'))>
                        <label class="form-check-label" for="solo_con_deuda">Solo alumnos con cuotas vencidas</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="docentes" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="docentes" name="docentes" value="1" @checked(old('docentes'))>
                        <label class="form-check-label" for="docentes">Docentes</label>
                    </div>
                </div>
                @error('alumnos')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
            </div>
        </x-ito.form-section>

        <x-ito.form-section title="Por dónde" icon="bi-broadcast" help="WhatsApp tiene costo por mensaje: si son muchos te vamos a pedir que confirmes.">
            <div class="d-flex flex-wrap gap-3">
                @foreach($catalogo['canales'] as $clave => $etiqueta)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="canales[]" value="{{ $clave }}" id="canal_{{ $clave }}" @checked(in_array($clave, $canalesElegidos, true))>
                        <label class="form-check-label" for="canal_{{ $clave }}">{{ $etiqueta }}</label>
                    </div>
                @endforeach
            </div>
            @error('canales')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </x-ito.form-section>

        <x-ito.form-section title="Qué decir" icon="bi-chat-text">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="titulo">Título</label>
                    <input class="form-control @error('titulo') is-invalid @enderror" id="titulo" name="titulo" maxlength="120" value="{{ old('titulo') }}" required>
                    @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="mensaje">Mensaje</label>
                    <textarea class="form-control @error('mensaje') is-invalid @enderror" id="mensaje" name="mensaje" rows="4" maxlength="1000" required>{{ old('mensaje') }}</textarea>
                    @error('mensaje')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="enlace">Enlace (opcional)</label>
                    <input class="form-control @error('enlace') is-invalid @enderror" id="enlace" name="enlace" value="{{ old('enlace') }}" placeholder="app://mis-shows o https://…">
                    <div class="form-text">Al tocar el aviso en la app se abre esa pantalla o página.</div>
                    @error('enlace')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="programada_para">Mandar más tarde (opcional)</label>
                    <input type="datetime-local" class="form-control @error('programada_para') is-invalid @enderror" id="programada_para" name="programada_para" value="{{ old('programada_para') }}">
                    <div class="form-text">Vacío: sale apenas lo mandás.</div>
                    @error('programada_para')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </x-ito.form-section>

        <x-ito.form-section title="Vista previa" icon="bi-eye">
            <div id="vista-previa" class="small text-muted" aria-live="polite">Tocá «Ver a cuántos les llega».</div>
            <div class="form-check mt-2 @if(! $errors->has('confirmar_whatsapp')) d-none @endif" id="confirmar-wa-wrap">
                <input class="form-check-input" type="checkbox" name="confirmar_whatsapp" value="1" id="confirmar_whatsapp">
                <label class="form-check-label" for="confirmar_whatsapp">Entiendo que cada WhatsApp tiene costo y quiero mandarlos igual.</label>
                @error('confirmar_whatsapp')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btn-vista-previa"><i class="bi bi-people" aria-hidden="true"></i> Ver a cuántos les llega</button>
        </x-ito.form-section>

        <x-ito.form-actions :cancel="route('difusion.index')" submit="Mandar" icon="bi-send" />
    </form>
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('form-difusion');
    var alcance = document.getElementById('alcance');
    function sync() {
        document.querySelectorAll('[data-alcance]').forEach(function (el) {
            el.style.display = el.getAttribute('data-alcance') === alcance.value ? '' : 'none';
        });
    }
    alcance.addEventListener('change', sync);
    sync();

    var salida = document.getElementById('vista-previa');
    document.getElementById('btn-vista-previa').addEventListener('click', function () {
        salida.textContent = 'Calculando…';
        fetch(@json(route('difusion.vista-previa')), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: new FormData(form),
            credentials: 'same-origin'
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
          .then(function (res) {
            if (!res.ok) { salida.textContent = res.j.message || 'Revisá los datos.'; return; }
            var p = res.j;
            var texto = 'Le llega a ' + p.cantidad + (p.cantidad === 1 ? ' persona' : ' personas') +
                ' (' + p.con_app + ' con la app, ' + p.con_email + ' con correo, ' + p.con_telefono + ' con teléfono).';
            if (p.muestra.length) texto += ' Por ejemplo: ' + p.muestra.join(', ') + '.';
            if (p.whatsapp.cantidad > 0) {
                texto += ' WhatsApp: ' + p.whatsapp.cantidad + ' mensajes.';
                if (p.whatsapp.supera_maximo) texto += ' Supera el máximo de ' + p.whatsapp.maximo + ': achicá el grupo o sacá WhatsApp.';
            }
            salida.textContent = texto;
            document.getElementById('confirmar-wa-wrap').classList.toggle('d-none', !p.whatsapp.requiere_confirmacion);
          }).catch(function () { salida.textContent = 'No se pudo calcular. Probá de nuevo.'; });
    });
})();
</script>
@endpush
