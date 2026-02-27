@extends('layouts.app')

@section('title', 'Reportes')
@section('page-title', 'Reportes')

@section('content')
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#rep-alumnos" type="button" role="tab">
            Alumnos
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#rep-ingresos" type="button" role="tab">
            Ingresos / Egresos por sede
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#rep-global" type="button" role="tab">
            Resumen global
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="rep-alumnos" role="tabpanel">
        <div class="card mb-3">
            <div class="card-header py-3">Alumnos por profesor</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Profesor</th>
                                <th>Sede(s)</th>
                                <th>Bloques</th>
                                <th>Alumnxs activos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($alumnosPorProfesor as $row)
                            <tr>
                                <td>{{ $row['profesor']->nombre }}</td>
                                <td>{{ $row['sedes']->join(', ') ?: '—' }}</td>
                                <td>{{ $row['bloques_count'] }}</td>
                                <td>{{ $row['alumnos_count'] }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" class="text-center text-muted">No hay profesores con alumnos asignados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-3">Alumnos por bloque (con ingresos asociados)</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Sede</th>
                                <th>Bloque</th>
                                <th>Profesor</th>
                                <th>Alumnxs activos</th>
                                <th>Ingresos asociados (aprox.)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($alumnosPorBloque as $row)
                            <tr>
                                <td>{{ $row['sede']?->nombre ?? '—' }}</td>
                                <td>{{ $row['bloque']->nombre }}</td>
                                <td>{{ $row['profesor']?->nombre ?? '—' }}</td>
                                <td>{{ $row['alumnos_count'] }}</td>
                                <td>
                                    @php $ing = $ingresosPorBloque[$row['bloque']->id] ?? 0; @endphp
                                    {{ $ing ? '$ ' . number_format($ing, 2, ',', '.') : '—' }}
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted">No hay bloques activos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2">
                    Nota: los ingresos por bloque se estiman a partir de los pagos de los alumnos cuyo bloque principal es ese bloque.
                </p>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="rep-ingresos" role="tabpanel">
        <div class="card">
            <div class="card-header py-3">Ingresos y egresos por sede</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Sede</th>
                                <th>Propiedad</th>
                                <th>Ingresos</th>
                                <th>Sueldos</th>
                                <th>Alquiler</th>
                                <th>Luz</th>
                                <th>Agua</th>
                                <th>Reparaciones edilicias</th>
                                <th>Reparaciones tambores</th>
                                <th>Insumos</th>
                                <th>Servicios externos</th>
                                <th>Otros</th>
                                <th>Total gastos</th>
                                <th>Resultado</th>
                                <th>Reposición insumos (promedio)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resumenFinanciero as $row)
                            @php
                                $s = $row['sede'];
                                $g = $row['gastos_detalle'];
                            @endphp
                            <tr>
                                <td>{{ $s->nombre }}</td>
                                <td>
                                    {{ $s->tipo_propiedad === 'propia' ? 'Propia' : ($s->tipo_propiedad === 'alquilada' ? 'Alquilada' : ucfirst($s->tipo_propiedad ?? '')) }}
                                    @if($s->costo_alquiler_mensual && $s->tipo_propiedad === 'alquilada')
                                        <div class="text-muted small">Alquiler: $ {{ number_format($s->costo_alquiler_mensual, 2, ',', '.') }}/mes</div>
                                    @endif
                                </td>
                                <td>{{ $row['ingresos'] ? '$ ' . number_format($row['ingresos'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['sueldos'] ? '$ ' . number_format($g['sueldos'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['alquiler'] ? '$ ' . number_format($g['alquiler'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['luz'] ? '$ ' . number_format($g['luz'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['agua'] ? '$ ' . number_format($g['agua'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['reparaciones_edilicias'] ? '$ ' . number_format($g['reparaciones_edilicias'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['reparaciones_tambores'] ? '$ ' . number_format($g['reparaciones_tambores'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['insumos'] ? '$ ' . number_format($g['insumos'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['servicios_externos'] ? '$ ' . number_format($g['servicios_externos'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $g['otros'] ? '$ ' . number_format($g['otros'], 2, ',', '.') : '—' }}</td>
                                <td>{{ $row['total_gastos'] ? '$ ' . number_format($row['total_gastos'], 2, ',', '.') : '—' }}</td>
                                <td class="{{ $row['resultado'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $row['resultado'] ? '$ ' . number_format($row['resultado'], 2, ',', '.') : '—' }}
                                </td>
                                <td>
                                    @if($row['frecuencia_insumos_dias'])
                                        Cada ~{{ $row['frecuencia_insumos_dias'] }} días
                                    @else
                                        Sin datos suficientes
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="15" class="text-center text-muted">No hay sedes cargadas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2">
                    Notas:
                    <br>• Los gastos se toman de la tabla de <code>gastos</code> (sueldo, alquiler, luz, agua, reparaciones edilicias/tambores, insumos y servicios externos como electricista, plomero, cortador de pasto, pintor, etc.).
                    <br>• La frecuencia de reposición de insumos se estima con la media de días entre gastos de tipo <strong>insumo</strong> por sede.
                </p>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="rep-global" role="tabpanel">
        <div class="card">
            <div class="card-header py-3">Resumen global de inversión vs recuperación</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card border-start-primary shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Ingresos totales (pagos)</div>
                                <div class="h5 mb-0 fw-bold">{{ $ingresosTotales ? '$ ' . number_format($ingresosTotales, 2, ',', '.') : '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-start-danger shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Gastos totales</div>
                                <div class="h5 mb-0 fw-bold">{{ $gastosTotales ? '$ ' . number_format($gastosTotales, 2, ',', '.') : '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-start-success shadow-sm h-100">
                            <div class="card-body">
                                <div class="text-muted small">Resultado global (ingresos - gastos)</div>
                                <div class="h5 mb-0 fw-bold {{ $resultadoGlobal >= 0 ? 'text-success' : 'text-danger' }}">
                                    {{ $resultadoGlobal ? '$ ' . number_format($resultadoGlobal, 2, ',', '.') : '—' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="small text-muted mt-3">
                    Este resumen te permite ver cuánto dinero se invirtió en sueldos, alquileres, servicios, reparaciones e insumos, 
                    y cuánto se recuperó vía pagos/cuotas. A partir de aquí podés ajustar cuotas, abrir nuevos talleres o justificar 
                    inversiones adicionales en base a datos reales.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

