@extends('layouts.app')

@section('title', 'Comprobantes de alumnos')
@section('page-title', 'Comprobantes de cuota enviados por alumnos')

@section('content')
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Lo que mandaron los alumnos por el link público (sin entrar al sistema)</span>
        <form method="get" class="d-flex gap-2 align-items-center">
            <select name="estado" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">Todos los estados</option>
                <option value="pendiente" {{ request('estado') === 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="visto" {{ request('estado') === 'visto' ? 'selected' : '' }}>Visto</option>
            </select>
        </form>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Fecha envío</th>
                        <th>Alumno</th>
                        <th>Sede</th>
                        <th>Monto</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($comprobantes as $c)
                    <tr>
                        <td>{{ $c->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $c->alumno?->nombre_apellido ?? '—' }}</td>
                        <td>{{ $c->sede?->nombre ?? '—' }}</td>
                        <td>$ {{ number_format($c->monto_total, 2, ',', '.') }}</td>
                        <td>
                            @if($c->estado === 'pendiente')
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            @else
                                <span class="badge bg-secondary">Visto</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('comprobantes-cuota-alumnos.show', $c->id) }}" class="btn btn-sm btn-primary">Ver</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted p-4">No hay envíos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">{{ $comprobantes->withQueryString()->links() }}</div>
</div>
@endsection
