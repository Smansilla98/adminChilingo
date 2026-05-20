@php
    $bloquesSeleccionados = old('bloque_ids', isset($alumno)
        ? $alumno->bloques->pluck('id')->all()
        : []);
    $bloquePrincipal = old('bloque_principal_id', isset($alumno)
        ? ($alumno->bloques->firstWhere('pivot.es_principal', true)?->id ?? $alumno->bloque_id)
        : null);
@endphp
<div class="card mb-3">
    <div class="card-header">Bloques</div>
    <div class="card-body">
        <p class="text-muted small mb-3">Un alumno puede participar en varios bloques. Marcá todos los que correspondan y elegí cuál es el principal.</p>
        @if($bloques->isEmpty())
        <p class="text-muted mb-0">No hay bloques activos.</p>
        @else
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th style="width:2.5rem"></th>
                        <th>Bloque</th>
                        <th>Sede</th>
                        <th style="width:6rem" class="text-center">Principal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bloques as $bloque)
                    @php
                        $checked = in_array($bloque->id, array_map('intval', (array) $bloquesSeleccionados), true)
                            || (empty($bloquesSeleccionados) && isset($alumno) && (int) $alumno->bloque_id === (int) $bloque->id);
                    @endphp
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input bloque-check" name="bloque_ids[]" value="{{ $bloque->id }}" id="bloque_cb_{{ $bloque->id }}" {{ $checked ? 'checked' : '' }}>
                        </td>
                        <td><label class="mb-0" for="bloque_cb_{{ $bloque->id }}">{{ $bloque->nombre }}</label></td>
                        <td class="text-muted">{{ $bloque->sede->nombre ?? '—' }}</td>
                        <td class="text-center">
                            <input type="radio" class="form-check-input bloque-principal" name="bloque_principal_id" value="{{ $bloque->id }}" {{ (int) $bloquePrincipal === (int) $bloque->id ? 'checked' : '' }} {{ $checked ? '' : 'disabled' }}>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('bloque_ids')<div class="text-danger small">{{ $message }}</div>@enderror
        @error('bloque_principal_id')<div class="text-danger small">{{ $message }}</div>@enderror
        @endif
    </div>
</div>
@push('scripts')
<script>
(function () {
    function syncPrincipalRadios() {
        document.querySelectorAll('.bloque-principal').forEach(function (radio) {
            const bid = radio.value;
            const cb = document.getElementById('bloque_cb_' + bid);
            radio.disabled = !(cb && cb.checked);
            if (radio.disabled && radio.checked) radio.checked = false;
        });
        const checked = document.querySelectorAll('.bloque-check:checked');
        if (checked.length === 1) {
            const r = document.querySelector('.bloque-principal[value="' + checked[0].value + '"]');
            if (r) { r.disabled = false; r.checked = true; }
        }
    }
    document.querySelectorAll('.bloque-check').forEach(function (cb) {
        cb.addEventListener('change', syncPrincipalRadios);
    });
    syncPrincipalRadios();
})();
</script>
@endpush
