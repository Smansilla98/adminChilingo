@php
    $roles = \App\Models\Profesor::ROLES_BLOQUE;
    $labelsRol = [
        'titular' => 'Titular',
        'ayudante' => 'Ayudante',
        'suplente' => 'Suplente',
        'coordinador_clase' => 'Coordinador de clase',
    ];
@endphp
<div class="card mb-3">
    <div class="card-header">Bloques y rol</div>
    <div class="card-body">
        <p class="text-muted small mb-3">Tildá en qué bloques da clase y qué rol tiene en cada uno. <strong>Titular</strong> es quien figura como profe principal de ese bloque.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th style="width:2.5rem"></th>
                        <th>Bloque</th>
                        <th>Sede</th>
                        <th style="width:12rem">Rol</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bloquesParaAsignar as $b)
                        @php
                            $rel = isset($profesor) ? $profesor->bloques->firstWhere('id', $b->id) : null;
                            $checked = (bool) old('asignaciones.'.$b->id.'.asignado', $rel !== null);
                            $rolSel = old('asignaciones.'.$b->id.'.rol', $rel?->pivot?->rol ?? 'ayudante');
                        @endphp
                        <tr>
                            <td>
                                <input type="hidden" name="asignaciones[{{ $b->id }}][bloque_id]" value="{{ $b->id }}">
                                <input type="checkbox" class="form-check-input" name="asignaciones[{{ $b->id }}][asignado]" value="1" id="asig_{{ $b->id }}" {{ $checked ? 'checked' : '' }}>
                            </td>
                            <td><label class="mb-0" for="asig_{{ $b->id }}">{{ $b->nombre }}</label></td>
                            <td class="text-muted">{{ $b->sede?->nombre ?? '—' }}</td>
                            <td>
                                <select class="form-select form-select-sm" name="asignaciones[{{ $b->id }}][rol]" aria-label="Rol en {{ $b->nombre }}">
                                    @foreach($roles as $r)
                                        <option value="{{ $r }}" {{ $rolSel === $r ? 'selected' : '' }}>{{ $labelsRol[$r] ?? $r }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($bloquesParaAsignar->isEmpty())
            <p class="text-muted mb-0">No hay bloques activos. Creá bloques primero.</p>
        @endif
    </div>
</div>
