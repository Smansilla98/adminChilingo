@extends('layouts.app')

@section('title', $ciclo->nombre)
@section('page-title', $ciclo->nombre.' · reinscripciones')

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<p class="mb-3">
    <strong>{{ $resumen['confirmadas'] }}</strong> reinscriptos ·
    <strong>{{ $resumen['pendientes'] }}</strong> pendientes ·
    <strong>{{ $resumen['bajas'] }}</strong> dados de baja
</p>
<nav class="mb-3 d-flex gap-2" aria-label="Filtrar por estado">
    <a href="{{ route('ciclos.show', $ciclo) }}" class="btn btn-sm {{ $estado === null ? 'btn-primary' : 'btn-outline-primary' }}">Todos</a>
    <a href="{{ route('ciclos.show', [$ciclo, 'estado' => 'confirmada']) }}" class="btn btn-sm {{ $estado === 'confirmada' ? 'btn-primary' : 'btn-outline-primary' }}">Reinscriptos</a>
    <a href="{{ route('ciclos.show', [$ciclo, 'estado' => 'pendiente']) }}" class="btn btn-sm {{ $estado === 'pendiente' ? 'btn-primary' : 'btn-outline-primary' }}">Pendientes</a>
    <a href="{{ route('ciclos.show', [$ciclo, 'estado' => 'baja']) }}" class="btn btn-sm {{ $estado === 'baja' ? 'btn-primary' : 'btn-outline-primary' }}">Bajas</a>
</nav>
<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Alumno</th><th>Estado</th><th>Confirmó</th><th></th></tr></thead>
        <tbody>
        @forelse($reinscripciones as $r)
            <tr>
                <td>{{ $r->alumno?->nombre_apellido }}</td>
                <td>{{ ['pendiente' => 'Pendiente', 'confirmada' => 'Reinscripto', 'baja' => 'Dado de baja'][$r->estado] ?? $r->estado }}</td>
                <td>{{ $r->confirmada_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="text-end">
                    @if($r->estado === \App\Models\Reinscripcion::PENDIENTE)
                        <form method="POST" action="{{ route('reinscripciones.baja', $r) }}" class="d-inline" data-confirm="¿Dar de baja a {{ $r->alumno?->nombre_apellido }}? Queda inactivo; sus cuotas y asistencias no se borran.">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger">Dar de baja</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted">No hay alumnos en esta lista.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <h2 class="h6">Calendario académico</h2>
        <table class="table table-sm align-middle">
            <tbody>
            @foreach($calendario as $m)
                <tr>
                    <td>{{ $m['nombre'] }}</td>
                    <td>
                        @foreach($m['actividades'] as $act)
                            <span class="badge {{ $act['tipo'] === 'clases' ? 'bg-secondary' : 'bg-info text-dark' }} me-1">{{ $act['etiqueta'] }}</span>
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="small text-muted">Enero y febrero no son clases regulares: son taller de verano, y en paralelo la gira a Villa Gesell. Las cuotas se cobran igual todos los meses del ciclo.</p>
    </div>
    <div class="col-lg-6">
        <h2 class="h6">Módulos</h2>
        <ul class="small">
            @foreach($modulos as $mod)
                <li>{{ ['cuotas' => 'Cuotas', 'academico' => 'Académico', 'reinscripcion' => 'Reinscripción'][$mod->clave] ?? $mod->clave }}: del {{ $mod->inicio->format('d/m/Y') }} al {{ $mod->fin->format('d/m/Y') }}</li>
            @endforeach
        </ul>
        @can('cuotas.create')
            <h2 class="h6 mt-3">Generar cuotas del ciclo</h2>
            <p class="small text-muted">Crea las cuotas de marzo a febrero para cada bloque activo que no las tenga. Las cuotas que ya existen no se tocan.</p>
            <form method="POST" action="{{ route('ciclos.cuotas', $ciclo) }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-sm-6"><label class="form-label small" for="monto">Monto base</label><input id="monto" type="number" step="0.01" min="0" name="monto" class="form-control" required></div>
                <div class="col-12"><button class="btn btn-outline-primary">Generar cuotas que falten</button></div>
            </form>
        @endcan
        @can('cuotas.update')
            <h2 class="h6 mt-4">Valor de las cuotas del ciclo</h2>
            <p class="small text-muted">Cambia el monto de las cuotas de marzo a febrero que ya existen. Primero mirá la vista previa. Los pagos ya registrados no se tocan. Si dejás vacío el monto de un bloque, usa el monto base.</p>
            <form method="POST" action="{{ route('ciclos.montos', $ciclo) }}">
                @csrf
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-sm-6"><label class="form-label small" for="monto-base-ciclo">Monto base</label><input id="monto-base-ciclo" type="number" step="0.01" min="0" name="monto" class="form-control" value="{{ old('monto') }}" required></div>
                </div>
                <div class="row g-2 mb-2">
                    @foreach($bloques as $b)
                        <div class="col-sm-6"><label class="form-label small" for="monto-b{{ $b->id }}">{{ $b->nombre }} <span class="text-muted">({{ $b->sede?->nombre ?? '—' }})</span></label><input id="monto-b{{ $b->id }}" type="number" step="0.01" min="0" name="montos[{{ $b->id }}]" class="form-control form-control-sm" value="{{ old('montos.'.$b->id) }}" placeholder="Monto base"></div>
                    @endforeach
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" name="accion" value="previa">Ver vista previa</button>
                    @if(session('previa_montos'))
                        <button class="btn btn-primary" name="accion" value="aplicar" data-confirm="¿Cambiar el valor de las cuotas? Los pagos no se tocan.">Aplicar cambios</button>
                    @endif
                </div>
            </form>
            @if($previa = session('previa_montos'))
                <h3 class="h6 mt-3">Vista previa: {{ $previa['total'] }} cuotas</h3>
                <p class="small text-muted">Cambian {{ $previa['cambian'] }} · ya tienen ese valor {{ $previa['iguales'] }} · con pagos registrados {{ $previa['con_pagos'] }}</p>
                <table class="table table-sm">
                    <thead><tr><th>Bloque</th><th>Mes</th><th class="text-end">Hoy</th><th class="text-end">Nuevo</th><th class="text-end">Pagos</th></tr></thead>
                    <tbody>
                        @foreach($previa['items'] as $it)
                            <tr class="{{ $it['cambia'] ? '' : 'text-muted' }}">
                                <td>{{ $it['bloque'] }}</td>
                                <td class="ito-mono">{{ $it['periodo'] }}</td>
                                <td class="text-end">$ {{ number_format($it['monto_actual'], 2, ',', '.') }}</td>
                                <td class="text-end">$ {{ number_format($it['monto_nuevo'], 2, ',', '.') }}</td>
                                <td class="text-end">{{ $it['pagos'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endcan
    </div>
</div>
<a href="{{ route('ciclos.index') }}">Volver a ciclos</a>
@endsection
