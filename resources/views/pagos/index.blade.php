@extends('layouts.app')

@section('title', 'Pagos')
@section('page-title', 'Pagos (trazabilidad)')

@section('content')
<x-ito.list-page title="Pagos" subtitle="Registro y trazabilidad de pagos">
    <x-slot:actions>
        <a href="{{ route('pagos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Registrar pago</a>
    </x-slot:actions>

    <x-slot:toolbar>
        <form method="GET" class="ito-toolbar-filters w-100 d-flex flex-wrap align-items-end gap-2">
            <div class="ito-field">
                <label>Alumno</label>
                <select name="alumno_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach($alumnos as $a)
                        <option value="{{ $a->id }}" @selected(request('alumno_id') == $a->id)>{{ $a->nombre_apellido }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ito-field">
                <label>Cuota</label>
                <select name="cuota_id" class="form-select">
                    <option value="">Todas</option>
                    @foreach($cuotas as $c)
                        <option value="{{ $c->id }}" @selected(request('cuota_id') == $c->id)>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ito-field">
                <label>Desde</label>
                <input type="date" name="desde" class="form-control" value="{{ request('desde') }}">
            </div>
            <div class="ito-field">
                <label>Hasta</label>
                <input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </form>
    </x-slot:toolbar>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cuota</th>
                <th>Alumnos</th>
                <th>Monto total</th>
                <th>Abono prof.</th>
                <th>Comprobante</th>
                <th>Registrado por</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($pagos as $p)
                @php
                    $sumAbono = $p->detalles->sum(fn ($d) => (float) ($d->abono_profesor ?? 0));
                    $hayAbono = $p->detalles->contains(fn ($d) => $d->abono_profesor !== null);
                    $alumnosNombres = $p->detalles->pluck('alumno.nombre_apellido')->unique()->join(', ');
                @endphp
                <tr>
                    <td class="ito-mono">{{ $p->fecha_pago->format('d/m/Y') }}</td>
                    <td>{{ $p->detalles->pluck('cuota.nombre')->filter()->unique()->implode(', ') ?: '—' }}</td>
                    <td>
                        <x-ito.person :name="$alumnosNombres ?: '—'" />
                    </td>
                    <td class="ito-mono">$ {{ number_format($p->monto_total, 2, ',', '.') }}</td>
                    <td class="ito-mono">
                        @if($hayAbono)
                            $ {{ number_format($sumAbono, 2, ',', '.') }}
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if($p->comprobante_path)
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#modalComprobantePago" data-comprobante-src="{{ route('pagos.comprobante', $p) }}" data-comprobante-label="Comprobante — pago #{{ $p->id }}"><i class="bi bi-file-earmark"></i> Ver</button>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $p->registradoPor?->name ?? '—' }}</td>
                    <td>
                        <x-ito.actions :id="'pago-'.$p->id">
                            <li><a class="dropdown-item" href="{{ route('pagos.show', $p) }}"><i class="bi bi-eye"></i> Ver</a></li>
                            <li><a class="dropdown-item" href="{{ route('pagos.edit', $p) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                        </x-ito.actions>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="ito-empty">No hay pagos. <a href="{{ route('pagos.create') }}">Registrar uno</a></td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">@if(method_exists($pagos, 'total')){{ $pagos->total() }} registros@endif</div>
        {{ $pagos->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@include('pagos._modal_comprobante')
@endsection
