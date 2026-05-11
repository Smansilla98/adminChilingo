<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Alumno</th>
                <th>Bloque</th>
                <th>Tipo de asistencia</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse($asistencias as $a)
            <tr>
                <td>{{ $a->fecha->format('d/m/Y') }}</td>
                <td>{{ $a->alumno->nombre_apellido ?? '-' }}</td>
                <td>{{ $a->bloque->nombre ?? '-' }}</td>
                <td>
                    <span class="badge bg-{{ $a->tipo_asistencia === 'presente' || $a->tipo_asistencia === 'tarde' ? 'success' : (in_array($a->tipo_asistencia, ['ausencia_justificada', 'justificado']) ? 'info' : 'secondary') }}">
                        {{ $tiposAsistencia[$a->tipo_asistencia] ?? $a->tipo_asistencia }}
                    </span>
                </td>
                <td>
                    <a href="{{ route('asistencias.show', $a) }}" class="btn btn-sm btn-info"><i class="bi bi-eye"></i></a>
                    <a href="{{ route('asistencias.edit', $a) }}" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                    <form action="{{ route('asistencias.destroy', $a) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar registro?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center">No hay asistencias. <a href="{{ route('asistencias.create') }}">Cargar asistencias</a></td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $asistencias->withQueryString()->links('pagination::bootstrap-5') }}
