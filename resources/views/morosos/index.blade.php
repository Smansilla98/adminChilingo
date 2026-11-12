@extends('layouts.app')

@section('title', 'Morosidad')
@section('page-title', 'Morosidad')

@section('content')
<x-ito.shell-page title="Morosidad" subtitle="Alumnos con cuotas vencidas, según cuánto hace que deben. Recordá a uno o a varios a la vez." :plain="true">
    <form method="GET" action="{{ route('morosos.index') }}" class="row g-2 align-items-end mb-3">
        <div class="col-md-3">
            <label class="form-label" for="mor-sede">Sede</label>
            <select id="mor-sede" name="sede_id" class="form-select">
                <option value="">Todas</option>
                @foreach($sedes as $s)
                    <option value="{{ $s->id }}" @selected((string) ($filtros['sede_id'] ?? '') === (string) $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="mor-bloque">Bloque</label>
            <select id="mor-bloque" name="bloque_id" class="form-select">
                <option value="">Todos</option>
                @foreach($bloques as $b)
                    <option value="{{ $b->id }}" @selected((string) ($filtros['bloque_id'] ?? '') === (string) $b->id)>{{ $b->nombre }} ({{ $b->sede?->nombre ?? '—' }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="mor-tramo">Antigüedad</label>
            <select id="mor-tramo" name="tramo" class="form-select">
                <option value="">Todas</option>
                @foreach($tramos as $k => $v)
                    <option value="{{ $k }}" @selected(($filtros['tramo'] ?? '') === $k)>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
        </div>
    </form>

    <p class="small text-muted">{{ $deudores->count() }} alumnos con deuda vencida · total $ {{ number_format($total, 0, ',', '.') }}</p>

    <form method="POST" action="{{ route('morosos.recordatorios') }}" id="form-morosos-masivo">
        @csrf
        <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
            <span class="small fw-semibold">Avisar por:</span>
            @foreach(['interna' => 'Aviso en la app', 'push' => 'Notificación', 'mail' => 'Mail', 'whatsapp' => 'WhatsApp'] as $k => $v)
                <label class="form-check-label small"><input class="form-check-input me-1" type="checkbox" name="canales[]" value="{{ $k }}" @checked(in_array($k, ['interna', 'push']))>{{ $v }}</label>
            @endforeach
            <button type="submit" class="btn btn-outline-primary btn-sm" data-confirm="¿Enviar recordatorio a los alumnos marcados?">Recordar a los marcados</button>
        </div>

        <table class="ito-table">
            <thead>
                <tr>
                    <th style="width: 32px"><input type="checkbox" class="form-check-input" aria-label="Marcar todos" onclick="document.querySelectorAll('.mor-check').forEach(c => c.checked = this.checked)"></th>
                    <th>Alumno</th>
                    <th>Sede / bloque</th>
                    <th>Antigüedad</th>
                    <th class="text-end">Saldo vencido</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($deudores as $d)
                    <tr>
                        <td><input type="checkbox" class="form-check-input mor-check" name="alumno_ids[]" value="{{ $d['alumno_id'] }}"></td>
                        <td>{{ $d['alumno'] }}<div class="small text-muted">{{ $d['cuotas_vencidas'] }} cuota(s) · desde {{ \Carbon\Carbon::parse($d['vencida_desde'])->format('d/m/Y') }}</div></td>
                        <td>{{ $d['sede'] ?? '—' }}<div class="small text-muted">{{ implode(', ', $d['bloques']) ?: '—' }}</div></td>
                        <td><span class="badge text-bg-{{ $d['tramo'] === '+90' ? 'danger' : ($d['tramo'] === '61-90' ? 'warning' : 'secondary') }}">{{ \App\Domain\Finanzas\MorosidadService::TRAMOS[$d['tramo']] }}</span> <span class="small text-muted">({{ $d['dias_mora'] }} días)</span></td>
                        <td class="ito-mono text-end">$ {{ number_format($d['saldo_vencido'], 0, ',', '.') }}</td>
                        <td>
                            @if($d['tiene_cuenta'])
                                <button type="submit" class="btn btn-link btn-sm p-0" form="form-recordar-{{ $d['alumno_id'] }}">Recordar</button>
                            @else
                                <span class="small text-muted">Sin cuenta</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay alumnos con cuotas vencidas con estos filtros.</td></tr>
                @endforelse
            </tbody>
        </table>
    </form>

    {{-- Un formulario por alumno para el recordatorio individual (fuera del masivo). --}}
    @foreach($deudores as $d)
        @if($d['tiene_cuenta'])
            <form id="form-recordar-{{ $d['alumno_id'] }}" method="POST" action="{{ route('morosos.recordar', $d['alumno_id']) }}" class="d-none">
                @csrf
                <input type="hidden" name="canales[]" value="interna">
                <input type="hidden" name="canales[]" value="push">
            </form>
        @endif
    @endforeach
</x-ito.shell-page>
@endsection
