@extends('layouts.app')

@section('title', 'Cuotas por lote')
@section('page-title', 'Cuotas por lote')

@section('content')
<x-ito.shell-page title="Generar cuotas por lote" subtitle="Armá varios meses de una vez para bloques, sedes o toda la escuela. Primero mirá la vista previa: lo que ya existe no se duplica." :plain="true">
    <form action="{{ route('cuotas.lote.store') }}" method="POST">
        @csrf
        <x-ito.form-section title="Qué cuotas" icon="bi-calendar3">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="lote-nombre">Nombre</label>
                    <input type="text" id="lote-nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ $valores['nombre'] ?? 'Cuota mensual' }}" required>
                    @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lote-anio">Año</label>
                    <input type="number" id="lote-anio" name="año" class="form-control @error('año') is-invalid @enderror" value="{{ $valores['año'] ?? now()->year }}" min="2020" max="2030" required>
                    @error('año')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lote-desde">Desde (mes)</label>
                    <select id="lote-desde" name="mes_desde" class="form-select">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) ($valores['mes_desde'] ?? 1) === $m)>{{ $m }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lote-hasta">Hasta (mes)</label>
                    <select id="lote-hasta" name="mes_hasta" class="form-select">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}" @selected((int) ($valores['mes_hasta'] ?? 12) === $m)>{{ $m }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="lote-monto">Monto</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" id="lote-monto" name="monto" class="form-control @error('monto') is-invalid @enderror" step="0.01" min="0" value="{{ $valores['monto'] ?? '' }}" required>
                    </div>
                    @error('monto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="lote-dia">Día de vencimiento</label>
                    <input type="number" id="lote-dia" name="dia_vencimiento" class="form-control" min="1" max="31" value="{{ $valores['dia_vencimiento'] ?? '' }}" placeholder="Opcional">
                    <div class="form-text">Si el mes no tiene ese día, vence el último día del mes.</div>
                </div>
                <div class="col-md-9">
                    <label class="form-label" for="lote-desc">Descripción</label>
                    <input type="text" id="lote-desc" name="descripcion" class="form-control" value="{{ $valores['descripcion'] ?? '' }}">
                </div>
            </div>
        </x-ito.form-section>

        <x-ito.form-section title="Para quién" icon="bi-people">
            <div class="mb-3">
                <label class="form-label" for="lote-alcance">Alcance</label>
                <select id="lote-alcance" name="alcance" class="form-select" style="max-width: 320px">
                    <option value="bloque" @selected(($valores['alcance'] ?? '') === 'bloque')>Bloques elegidos</option>
                    <option value="sede" @selected(($valores['alcance'] ?? '') === 'sede')>Sedes elegidas</option>
                    <option value="general" @selected(($valores['alcance'] ?? '') === 'general')>Toda la escuela (una cuota por mes)</option>
                </select>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="fw-semibold mb-1">Bloques</div>
                    @foreach($bloques as $b)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="bloque_ids[]" value="{{ $b->id }}" id="lb-{{ $b->id }}" @checked(in_array($b->id, $valores['bloque_ids'] ?? []))>
                            <label class="form-check-label" for="lb-{{ $b->id }}">{{ $b->nombre }} <span class="text-muted">({{ $b->sede?->nombre ?? '—' }})</span></label>
                        </div>
                    @endforeach
                </div>
                <div class="col-md-6">
                    <div class="fw-semibold mb-1">Sedes</div>
                    @foreach($sedes as $s)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="sede_ids[]" value="{{ $s->id }}" id="ls-{{ $s->id }}" @checked(in_array($s->id, $valores['sede_ids'] ?? []))>
                            <label class="form-check-label" for="ls-{{ $s->id }}">{{ $s->nombre }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        </x-ito.form-section>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" name="accion" value="previa" class="btn btn-outline-primary">Ver vista previa</button>
            @if($previa && $previa['crear'] > 0)
                <button type="submit" name="accion" value="generar" class="btn btn-primary" data-confirm="¿Generar {{ $previa['crear'] }} cuotas?">Generar {{ $previa['crear'] }} cuotas</button>
            @endif
            <a href="{{ route('cuotas.index') }}" class="btn btn-link">Volver a cuotas</a>
        </div>
    </form>

    @if($previa)
        <h3 class="h6 mt-4">Vista previa: {{ $previa['total'] }} cuotas</h3>
        <p class="small text-muted">Se crean {{ $previa['crear'] }} · ya existen {{ $previa['existen'] }} · sin permiso {{ $previa['sin_permiso'] }}</p>
        <table class="ito-table">
            <thead><tr><th>Período</th><th>Destino</th><th>Nombre</th><th class="text-end">Monto</th><th>Vence</th><th>Resultado</th></tr></thead>
            <tbody>
                @foreach($previa['items'] as $it)
                    <tr>
                        <td class="ito-mono">{{ $it['periodo'] }}</td>
                        <td>{{ $it['destino'] }}</td>
                        <td>{{ $it['nombre'] }}</td>
                        <td class="ito-mono text-end">$ {{ number_format($it['monto'], 2, ',', '.') }}</td>
                        <td class="ito-mono">{{ $it['vencimiento'] ? \Carbon\Carbon::parse($it['vencimiento'])->format('d/m/Y') : '—' }}</td>
                        <td>
                            @switch($it['estado'])
                                @case('crear') <span class="badge text-bg-success">Se crea</span> @break
                                @case('existe') <span class="badge text-bg-secondary">Ya existe</span> @break
                                @default <span class="badge text-bg-warning">Sin permiso</span>
                            @endswitch
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ito.shell-page>
@endsection
