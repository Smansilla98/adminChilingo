<div class="ito-table-wrap">
    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Alumno</th>
                <th>Bloque</th>
                <th>Tipo de asistencia</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($asistencias as $a)
                @php
                    $tone = match (true) {
                        in_array($a->tipo_asistencia, ['presente', 'tarde'], true) => 'success',
                        in_array($a->tipo_asistencia, ['ausencia_justificada', 'justificado'], true) => 'info',
                        default => 'neutral',
                    };
                @endphp
                <tr>
                    <td class="ito-mono">{{ $a->fecha->format('d/m/Y') }}</td>
                    <td>
                        <x-ito.person :name="$a->alumno->nombre_apellido ?? '—'" />
                    </td>
                    <td>{{ $a->bloque->nombre ?? '—' }}</td>
                    <td>
                        <x-ito.status :tone="$tone" :label="$tiposAsistencia[$a->tipo_asistencia] ?? $a->tipo_asistencia" />
                    </td>
                    <td>
                        <x-ito.actions :id="'asist-'.$a->id">
                            <li><a class="dropdown-item" href="{{ route('asistencias.show', $a) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('asistencias.edit', $a) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form action="{{ route('asistencias.destroy', $a) }}" method="POST" data-confirm="¿Eliminar registro?">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                </form>
                            </li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="ito-empty">No hay asistencias. <a href="{{ route('asistencias.create') }}">Cargar asistencias</a></td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="ito-footer mt-0 border-0 bg-transparent px-0">
    <div class="ito-footer-meta">@if(method_exists($asistencias, 'total'))
            {{ $asistencias->total() }} registros
        @endif
        </div>
    {{ $asistencias->withQueryString()->links('pagination::bootstrap-5') }}
</div>
