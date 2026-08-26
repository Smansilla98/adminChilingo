@extends('layouts.app')

@section('title', 'Alumno')
@section('page-title', 'Alumno')

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<x-ito.shell-page
    title="{{ $alumno->nombre_apellido }}"
    subtitle="Ficha, contacto y seguimiento"
    eyebrow="Alumnos"
>
    <x-slot:actions>
        @if($isAdmin)
            <a href="{{ route('alumnos.edit', $alumno) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
            <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
        @else
            <a href="{{ route('profesor.alumnos') }}" class="btn btn-outline-secondary btn-sm">Volver a mis alumnos</a>
        @endif
    </x-slot:actions>

    @if(!empty($profesorPerfil))
        <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-person-badge"></i> También tiene perfil de <strong>profesor</strong>:
            <a href="{{ route('profesores.show', $profesorPerfil) }}">{{ $profesorPerfil->nombre }}</a>
        </div>
    @endif

    <div class="ito-show-grid">
        <section class="ito-show-block">
            <h2 class="ito-show-title">Datos</h2>
            <dl class="ito-dl">
                <div><dt>DNI</dt><dd>{{ $alumno->dni ?? '—' }}</dd></div>
                <div><dt>Nacimiento</dt><dd>{{ $alumno->fecha_nacimiento ? $alumno->fecha_nacimiento->format('d/m/Y') : '—' }}</dd></div>
                <div><dt>Edad</dt><dd>{{ $alumno->edad !== null ? $alumno->edad.' años' : '—' }}</dd></div>
                <div><dt>Sede</dt><dd>{{ $alumno->sede->nombre ?? '—' }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $alumno->activo ? 'Activo' : 'Inactivo' }}</dd></div>
            </dl>
        </section>

        <section class="ito-show-block">
            <h2 class="ito-show-title">Contacto</h2>
            <x-ito.contact-actions
                :telefono="$alumno->telefono"
                :nombre="$alumno->nombre_apellido"
            />
        </section>
    </div>

    <section class="ito-show-block mt-3">
        <h2 class="ito-show-title">Instrumentos</h2>
        <ul class="list-group list-group-flush ito-list-flush">
            <li class="list-group-item d-flex justify-content-between"><span>Principal</span><strong>{{ $alumno->instrumento_principal ?? '—' }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Secundario</span><strong>{{ $alumno->instrumento_secundario ?? '—' }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Tipo de tambor</span><strong>{{ $alumno->tipo_tambor ?? '—' }}</strong></li>
            <li class="list-group-item d-flex justify-content-between"><span>Procedencia</span><strong>{{ $alumno->tambor_procedencia ?? '—' }}</strong></li>
        </ul>
    </section>

    <section class="ito-show-block mt-3">
        <h2 class="ito-show-title">Bloques</h2>
        @if($alumno->bloques->isNotEmpty())
        <ul class="list-group list-group-flush ito-list-flush">
            @foreach($alumno->bloques as $bloque)
            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>
                    {{ $bloque->nombre ?? 'Bloque' }}
                    @if($bloque->pivot->es_principal ?? false)
                    <span class="badge bg-warning text-dark ms-1">Principal</span>
                    @endif
                </span>
                <span>
                    @if($bloque->sede ?? null)
                    <span class="badge bg-secondary">{{ $bloque->sede->nombre }}</span>
                    @endif
                    @if($bloque->profesor ?? null)
                    <span class="badge bg-primary">{{ $bloque->profesor->nombre }}</span>
                    @endif
                </span>
            </li>
            @endforeach
        </ul>
        @elseif($alumno->bloque)
        <p class="mb-0">{{ $alumno->bloque->nombre }} @if($alumno->bloque->profesor)({{ $alumno->bloque->profesor->nombre }})@endif</p>
        @else
        <p class="text-muted mb-0">Sin bloques asignados.</p>
        @endif
    </section>

    <section class="ito-show-block mt-4">
        <h2 class="ito-show-title">Historial de pagos de cuotas</h2>
        <div class="table-responsive">
            <table class="ito-table">
                <thead>
                    <tr>
                        <th>Fecha pago</th>
                        <th>Cuota / período</th>
                        <th>Monto pagado</th>
                        <th>Abono docente</th>
                        @if($isAdmin)<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse(($historialPagos ?? collect()) as $det)
                    <tr>
                        <td>{{ $det->pago?->fecha_pago ? \Carbon\Carbon::parse($det->pago->fecha_pago)->format('d/m/Y') : '—' }}</td>
                        <td>
                            {{ $det->cuota?->nombre ?? 'Cuota' }}
                            @if($det->cuota?->mes && $det->cuota?->año)
                            <span class="text-muted small">({{ str_pad((string) $det->cuota->mes, 2, '0', STR_PAD_LEFT) }}/{{ $det->cuota->año }})</span>
                            @endif
                        </td>
                        <td>$ {{ number_format((float) $det->monto, 2, ',', '.') }}</td>
                        <td>
                            @if($det->abono_profesor !== null)
                            $ {{ number_format((float) $det->abono_profesor, 2, ',', '.') }}
                            @else
                            —
                            @endif
                        </td>
                        @if($isAdmin && $det->pago)
                        <td><a href="{{ route('pagos.show', $det->pago) }}" class="btn btn-sm btn-outline-secondary">Ver pago</a></td>
                        @elseif($isAdmin)
                        <td></td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $isAdmin ? 5 : 4 }}" class="ito-empty">Aún no hay pagos registrados para este alumno.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="ito-show-block mt-4">
        <h2 class="ito-show-title">Estado de cuenta</h2>
        <div class="table-responsive">
            <table class="ito-table">
                <thead>
                    <tr>
                        <th>Período</th>
                        <th>Monto cuota</th>
                        <th>Fecha de pago</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse(($estadoCuenta ?? collect()) as $row)
                    <tr>
                        <td>{{ $row['periodo'] }}</td>
                        <td>{{ $row['monto'] ? '$ ' . number_format($row['monto'], 2, ',', '.') : '—' }}</td>
                        <td>
                            @if($row['fecha_pago'])
                                {{ \Carbon\Carbon::parse($row['fecha_pago'])->format('d/m/Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <span class="badge bg-{{ $row['estado_color'] }} {{ $row['estado_color'] === 'warning' ? 'text-dark' : '' }}">
                                {{ $row['estado'] }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="ito-empty">No hay cuotas asociadas para este alumno.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @php $puedeBitacora = auth()->user() && (auth()->user()->isAdmin() || auth()->user()->isProfesor() || auth()->user()->isCoordinadorSede() || auth()->user()->isCoordinadorArea()); @endphp
    @if($puedeBitacora && \Illuminate\Support\Facades\Schema::hasTable('observaciones_pedagogicas'))
    <section class="ito-show-block mt-4">
        <h2 class="ito-show-title">Cuaderno pedagógico</h2>
        <p class="text-muted small">Registrá qué competencia se trabajó y el siguiente paso. Si lo compartís, el alumno lo ve en su espacio.</p>
        <form action="{{ route('seguimiento.store') }}" method="POST" class="ito-form-section mb-3">
            @csrf
            <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">
            <input type="hidden" name="fecha" value="{{ now()->toDateString() }}">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="ped-tipo">Tipo</label>
                    <select id="ped-tipo" name="tipo" class="form-select" required>
                        @foreach(\App\Models\ObservacionPedagogica::TIPOS as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ped-eje">Eje</label>
                    <select id="ped-eje" name="eje" class="form-select">
                        <option value="">Sin eje</option>
                        @foreach(\App\Models\ObservacionPedagogica::EJES as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="ped-bloque">Bloque (opcional)</label>
                    <select id="ped-bloque" name="bloque_id" class="form-select">
                        <option value="">Sin bloque</option>
                        @foreach($alumno->bloques as $bl)
                        <option value="{{ $bl->id }}">{{ $bl->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ped-toque">Toque / rudimento / obra</label>
                    <input id="ped-toque" type="text" name="toque" class="form-control" maxlength="160" placeholder="Ej. paradiddle, Malamakua, lectura 4/4">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ped-proximo">Próximo paso</label>
                    <input id="ped-proximo" type="text" name="proximo_paso" class="form-control" maxlength="400" placeholder="Ej. independizar mano izquierda a 80 BPM">
                </div>
                <div class="col-12">
                    <label class="form-label" for="ped-cuerpo">Qué pasó hoy</label>
                    <textarea id="ped-cuerpo" name="cuerpo" class="form-control" rows="3" required maxlength="4000" placeholder="Qué se trabajó, qué costó, cómo respondió."></textarea>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="visible_alumno" value="1" id="ped-visible">
                        <label class="form-check-label" for="ped-visible">Mostrar el próximo paso en el espacio del alumno</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Guardar en el cuaderno</button>
                </div>
            </div>
        </form>
        <ul class="list-group list-group-flush ito-list-flush">
            @forelse($alumno->observacionesPedagogicas as $nota)
            <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                    <div>
                        <strong>{{ $nota->etiquetaTipo() }}</strong>
                        @if($nota->eje)<span class="badge text-bg-secondary">{{ $nota->etiquetaEje() }}</span>@endif
                        <span class="text-muted"> · {{ $nota->fecha?->format('d/m/Y') }}</span>
                        @if($nota->toque)<span> · {{ $nota->toque }}</span>@endif
                        @if($nota->bloque)<span class="text-muted"> · {{ $nota->bloque->nombre }}</span>@endif
                        <div class="mt-1">{{ $nota->cuerpo }}</div>
                        @if($nota->proximo_paso)
                        <div class="mt-1"><strong>Sigue:</strong> {{ $nota->proximo_paso }}</div>
                        @endif
                        <div class="small text-muted mt-1">
                            {{ $nota->autor->name ?? $nota->autor->username ?? 'Docente' }}
                            @if($nota->visible_alumno) · visible para el alumno @endif
                        </div>
                    </div>
                    @if(auth()->id() === $nota->user_id || auth()->user()->isAdmin())
                    <form action="{{ route('seguimiento.destroy', $nota) }}" method="POST" onsubmit="return confirm('¿Eliminar esta nota?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                    </form>
                    @endif
                </div>
            </li>
            @empty
            <li class="list-group-item text-muted">Todavía no hay notas en el cuaderno.</li>
            @endforelse
        </ul>
    </section>
    @endif

    @if(isset($alumno->asistencias) && $alumno->asistencias->isNotEmpty())
    <section class="ito-show-block mt-4">
        <h2 class="ito-show-title">Últimas asistencias</h2>
        <ul class="list-group list-group-flush ito-list-flush">
            @foreach($alumno->asistencias->take(10) as $a)
            <li class="list-group-item">
                {{ $a->fecha ? \Carbon\Carbon::parse($a->fecha)->format('d/m/Y') : '' }}
                —
                <span aria-hidden="true">{{ \App\Models\Asistencia::letraTipo($a->tipo_asistencia) }}</span>
                {{ \App\Models\Asistencia::TIPOS_ASISTENCIA[$a->tipo_asistencia] ?? ($a->presente ? 'Presente' : 'Ausente') }}
            </li>
            @endforeach
        </ul>
    </section>
    @endif
</x-ito.shell-page>
@endsection
