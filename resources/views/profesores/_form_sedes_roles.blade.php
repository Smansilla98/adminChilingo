@php
    $rolesSede = \App\Models\Profesor::ROLES_SEDE;
    $sedesLista = $sedes ?? collect();
@endphp
@if($sedesLista->isNotEmpty())
<div class="card mb-3">
    <div class="card-header">Roles por sede</div>
    <div class="card-body">
        <p class="text-muted small mb-3">Un profesor puede ser docente, encargado o coordinador en una o varias sedes. El rol <strong>Coordinador de sede</strong> actualiza el coordinador en la ficha de la sede.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Sede</th>
                        @foreach($rolesSede as $rk => $rl)
                        <th class="text-center">{{ $rl }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($sedesLista as $sede)
                    <tr>
                        <td>{{ $sede->nombre }}</td>
                        @foreach($rolesSede as $rk => $rl)
                        @php
                            $rel = isset($profesor) ? $profesor->sedesConRol->where('id', $sede->id)->contains(fn ($x) => ($x->pivot->rol ?? '') === $rk) : false;
                            $checked = (bool) old('sede_roles.'.$sede->id.'.'.$rk, $rel);
                        @endphp
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input" name="sede_roles[{{ $sede->id }}][{{ $rk }}]" value="1" {{ $checked ? 'checked' : '' }}>
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
