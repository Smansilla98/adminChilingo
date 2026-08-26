@extends('layouts.app')

@section('title', 'Pagos de cuotas — mis alumnos')
@section('page-title', 'Pagos de cuotas')

@section('content')
<x-ito.shell-page
    title="Pagos de cuotas"
    eyebrow="Docente"
    subtitle="Solo lectura: pagos ya cargados de tus bloques."
>
        @php $hasAlcanceCuota = \Illuminate\Support\Facades\Schema::hasColumn('cuotas', 'alcance'); @endphp
        <form method="GET" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Alumno</label>
                    <select name="alumno_id" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        @foreach($alumnosFiltro as $a)
                        <option value="{{ $a->id }}" {{ (string) request('alumno_id') === (string) $a->id ? 'selected' : '' }}>{{ $a->nombre_apellido }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Desde</label>
                    <input type="date" name="desde" class="form-control form-control-sm" value="{{ request('desde') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small">Hasta</label>
                    <input type="date" name="hasta" class="form-control form-control-sm" value="{{ request('hasta') }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="ito-table table table-sm">
                <thead>
                    <tr>
                        <th>Fecha pago</th>
                        <th>Alumno</th>
                        <th>Cuota</th>
                        <th>Para quién / bloque</th>
                        <th>Monto línea</th>
                        <th>Abono prof.</th>
                        <th>Pago #</th>
                        <th>Registró</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($detalles as $d)
                    @php
                        $c = $d->cuota;
                        $alc = $hasAlcanceCuota ? ($c?->alcanceNormalizado() ?? 'bloque') : 'bloque';
                        $ctx = '—';
                        if ($c) {
                            if ($alc === \App\Models\Cuota::ALCANCE_GENERAL) {
                                $ctx = 'General';
                            } elseif ($alc === \App\Models\Cuota::ALCANCE_SEDE) {
                                $ctx = 'Sede: ' . ($c->sede?->nombre ?? '—');
                            } else {
                                $ctx = $c->bloque?->nombre ?? '—';
                            }
                        }
                    @endphp
                    <tr>
                        <td>{{ $d->pago?->fecha_pago?->format('d/m/Y') ?? '—' }}</td>
                        <td>
                            @if($d->alumno)
                            <a href="{{ route('profesor.alumnos.show', $d->alumno) }}">{{ $d->alumno->nombre_apellido }}</a>
                            @else
                            —
                            @endif
                        </td>
                        <td>{{ $c?->nombre ?? '—' }}</td>
                        <td class="small text-muted">{{ $ctx }}</td>
                        <td>$ {{ number_format((float) $d->monto, 2, ',', '.') }}</td>
                        <td>@if($d->abono_profesor !== null) $ {{ number_format((float) $d->abono_profesor, 2, ',', '.') }} @else — @endif</td>
                        <td><span class="text-muted">#{{ $d->pago_id }}</span></td>
                        <td class="small">{{ $d->pago?->registradoPor?->name ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted">No hay pagos que coincidan. Cuando la administración registre un pago de tus alumnos, aparecerá acá.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $detalles->links() }}
</x-ito.shell-page>
@endsection
