@php
    $rolesSede = \App\Models\Profesor::ROLES_SEDE;
    $sedesLista = $sedes ?? collect();
@endphp
@if($sedesLista->isNotEmpty())
<x-ito.form-section title="Roles por sede" icon="bi-geo-alt" help="Marcá en qué sedes participa y con qué función. La coordinación de sede le da acceso a gestionar esa sede.">
    <div>
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
</x-ito.form-section>
@endif
