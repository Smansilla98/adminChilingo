@extends('layouts.app')

@section('title', 'Comprobantes de alumnos')
@section('page-title', 'Comprobantes de cuota enviados por alumnos')

@section('content')
<x-ito.list-page title="Comprobantes de alumnos" subtitle="Envíos por el link público (sin entrar al sistema)">
    <x-slot:toolbar>
        <a href="{{ route('comprobantes-cuota-alumnos.create') }}" class="btn btn-primary btn-sm">Cargar comprobante</a>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-end gap-2">
            <div class="ito-field">
                <label>Estado</label>
                <select name="estado" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="pendiente" @selected(request('estado') === 'pendiente')>Pendiente</option>
                    <option value="visto" @selected(request('estado') === 'visto')>Visto</option>
                    <option value="pagado" @selected(request('estado') === 'pagado')>Pagado</option>
                </select>
            </div>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha envío</th>
                <th>Alumno</th>
                <th>Sede / bloques</th>
                <th>Monto</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($comprobantes as $c)
                @php
                    $tone = match($c->estado) {
                        'pendiente' => 'warning',
                        'pagado' => 'success',
                        default => 'neutral',
                    };
                    $bloquesTxt = $c->items->map(function ($it) {
                        $n = $it->bloque?->nombre;
                        $s = $it->bloque?->sede?->nombre;
                        return $n ? ($s ? "$n · $s" : $n) : null;
                    })->filter()->unique()->take(2)->implode(', ');
                @endphp
                <tr>
                    <td class="ito-mono">{{ $c->created_at?->format('d/m/Y H:i') }}</td>
                    <td>
                        <x-ito.person :name="$c->alumno?->nombre_apellido ?? '—'" />
                    </td>
                    <td>
                        <div>{{ $c->sede?->nombre ?? '—' }}</div>
                        @if($bloquesTxt)
                            <div class="small text-muted">{{ $bloquesTxt }}</div>
                        @endif
                    </td>
                    <td class="ito-mono">$ {{ number_format($c->monto_total, 2, ',', '.') }}</td>
                    <td>
                        <x-ito.status :tone="$tone" :label="$c->etiquetaEstado()" />
                    </td>
                    <td>
                        <x-ito.actions :id="'comp-'.$c->id">
                            <li><a class="dropdown-item" href="{{ route('comprobantes-cuota-alumnos.show', $c->id) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            @if(auth()->user()->isAdmin() && ! $c->estaPagado())
                                <li>
                                    <form action="{{ route('comprobantes-cuota-alumnos.aprobar-pago', $c->id) }}" method="post" data-confirm="¿Registrar pago desde este comprobante?">
                                        @csrf
                                        <button type="submit" class="dropdown-item"><i class="bi bi-cash-coin"></i> Aprobar y pagar</button>
                                    </form>
                                </li>
                            @endif
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
