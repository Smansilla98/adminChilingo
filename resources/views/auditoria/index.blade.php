@extends('layouts.app')

@section('title', 'Auditoría')
@section('page-title', 'Auditoría')

@section('content')
<x-ito.list-page title="Auditoría" eyebrow="Administración" subtitle="Quién cambió qué y cuándo (pagos, cuotas, gastos, inventario, usuarios, permisos, personas).">
    <x-slot:toolbar>
        <form method="GET" class="d-flex flex-wrap gap-2 w-100">
            <select name="entidad" class="form-select form-select-sm" style="max-width: 220px" aria-label="Entidad">
                <option value="">Todas las entidades</option>
                @foreach($entidades as $e)<option value="{{ $e }}" @selected(request('entidad') === $e)>{{ $e }}</option>@endforeach
            </select>
            <input type="number" name="entidad_id" value="{{ request('entidad_id') }}" class="form-control form-control-sm" style="max-width: 120px" placeholder="ID" aria-label="ID de la entidad">
            <select name="accion" class="form-select form-select-sm" style="max-width: 160px" aria-label="Acción">
                <option value="">Todas</option>
                @foreach(['created' => 'Alta', 'updated' => 'Cambio', 'deleted' => 'Baja', 'anulado' => 'Anulación', 'fusionada' => 'Fusión'] as $v => $t)
                    <option value="{{ $v }}" @selected(request('accion') === $v)>{{ $t }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-secondary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Entidad</th><th>Cambios</th><th>Origen</th></tr></thead>
        <tbody>
            @forelse($registros as $r)
                <tr>
                    <td class="small">{{ $r->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="small">{{ $r->user?->name ?? 'Sistema' }}</td>
                    <td>{{ $r->accion }}</td>
                    <td class="ito-mono small">{{ $r->entidad_tipo }} #{{ $r->entidad_id }}</td>
                    <td class="small">
                        @foreach(($r->datos_nuevos ?? $r->datos_anteriores ?? []) as $campo => $valor)
                            <div><span class="text-muted">{{ $campo }}:</span>
                                @if($r->accion === 'updated' && is_array($r->datos_anteriores) && array_key_exists($campo, $r->datos_anteriores))
                                    <s>{{ \Illuminate\Support\Str::limit(is_scalar($r->datos_anteriores[$campo]) ? (string) $r->datos_anteriores[$campo] : json_encode($r->datos_anteriores[$campo]), 40) }}</s> →
                                @endif
                                {{ \Illuminate\Support\Str::limit(is_scalar($valor) ? (string) $valor : json_encode($valor), 60) }}
                            </div>
                        @endforeach
                    </td>
                    <td class="small">{{ $r->origen }} {{ $r->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="ito-empty">Sin registros.</td></tr>
            @endforelse
        </tbody>
    </table>
    <x-slot:footer>{{ $registros->links() }}</x-slot:footer>
</x-ito.list-page>
@endsection
