@extends('layouts.app')

@section('title', 'Comprobantes de alumnos')
@section('page-title', 'Comprobantes de cuota enviados por alumnos')

@section('content')
<x-ito.list-page title="Comprobantes de alumnos" subtitle="Envíos por el link público (sin entrar al sistema)">
    <x-slot:toolbar>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-end gap-2">
            <div class="ito-field">
                <label>Estado</label>
                <select name="estado" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option>
                    <option value="visto" @selected(request('estado') === 'visto')>Visto</option>
                </select>
            </div>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
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
                    <td class="ito-mono">{{ $c->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <x-ito.person :name="$c->alumno?->nombre_apellido ?? '—'" />
                    </td>
                    <td>{{ $c->sede?->nombre ?? '—' }}</td>
                    <td class="ito-mono">$ {{ number_format($c->monto_total, 2, ',', '.') }}</td>
                    <td>
                        <x-ito.status
                            :tone="$c->estado === 'pendiente' ? 'warning' : 'neutral'"
                            :label="$c->estado === 'pendiente' ? 'Pendiente' : 'Visto'"
                        />
                    </td>
                    <td>
                        <x-ito.actions :id="'comp-'.$c->id">
                            <li><a class="dropdown-item" href="{{ route('comprobantes-cuota-alumnos.show', $c->id) }}"><i class="bi bi-eye"></i> Ver</a></li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="ito-empty">No hay envíos.</td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($comprobantes, 'total'))
            {{ $comprobantes->total() }} registros
        @endif
        </div>
        {{ $comprobantes->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
