@php
    $ultima = $ultimaEdicion ?? null;
    $nombreSugerido = $nombreSugerido ?? '';
    $volverUrl = $volverUrl ?? route('programa.index');
@endphp
<div id="pt-nombre-modal" class="pt-nombre-modal" role="dialog" aria-modal="true" aria-labelledby="pt-nombre-title">
    <form id="pt-nombre-form" class="pt-nombre-card" autocomplete="name">
        <p class="biblio-eyebrow">Antes de editar</p>
        <h2 id="pt-nombre-title">¿Cómo te llamás?</h2>
        <p class="pt-nombre-lead">Queda registro de quién suma o cambia material. No hace falta crear una cuenta.</p>
        @if($ultima)
            <p class="pt-nombre-last">Última edición: <strong>{{ $ultima['nombre'] }}</strong>
                @if(!empty($ultima['at']))
                    · {{ \Illuminate\Support\Carbon::parse($ultima['at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                @endif
            </p>
        @endif
        <label class="pt-nombre-label" for="pt-nombre-input">Tu nombre</label>
        <input id="pt-nombre-input" class="form-control" type="text" maxlength="80" minlength="2" required
               placeholder="Ej. Lucía Pérez" value="{{ $nombreSugerido }}" autocomplete="name">
        <p id="pt-nombre-error" class="pt-nombre-error" hidden>Escribí al menos 2 caracteres.</p>
        <div class="pt-nombre-actions">
            <a class="btn btn-outline-secondary" href="{{ $volverUrl }}">Cancelar</a>
            <button type="submit" class="btn btn-primary">Continuar</button>
        </div>
    </form>
</div>
<script>
(function () {
    const KEY = 'chilinga.partituraEditorNombre';
    const modal = document.getElementById('pt-nombre-modal');
    const form = document.getElementById('pt-nombre-form');
    const input = document.getElementById('pt-nombre-input');
    const error = document.getElementById('pt-nombre-error');
    const hidden = document.getElementById('editor_nombre');
    if (!modal || !form || !input) return;
    const guardado = (sessionStorage.getItem(KEY) || '').trim();
    if (guardado.length >= 2 && !input.value) input.value = guardado;
    function aplicar(n) {
        if (hidden) hidden.value = n;
        modal.hidden = true;
        modal.classList.add('is-done');
    }
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const n = input.value.trim();
        if (n.length < 2) { error.hidden = false; input.focus(); return; }
        error.hidden = true;
        try { sessionStorage.setItem(KEY, n); } catch (err) {}
        aplicar(n);
    });
    modal.hidden = false;
    modal.classList.remove('is-done');
    setTimeout(function () { input.focus(); }, 40);
})();
</script>
