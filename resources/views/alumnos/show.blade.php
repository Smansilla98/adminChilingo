@extends('layouts.app')

@section('title', $alumno->nombre_apellido)
@section('page-title', $alumno->nombre_apellido)

@section('content')
@php
    $isAdmin = auth()->user()->isAdmin();
    $estadoCuenta = $estadoCuenta ?? collect();
    $historialPagos = $historialPagos ?? collect();
    $vencidas = $estadoCuenta->where('estado', 'Vencida')->count();
    $pendientes = $estadoCuenta->where('estado', 'Pendiente')->count();
    $tonoCuota = ['success' => 'success', 'warning' => 'warning', 'danger' => 'danger'];
    $bloquePrincipal = $alumno->bloques->firstWhere('pivot.es_principal', true) ?? $alumno->bloques->first() ?? $alumno->bloque;
    $alumno->loadMissing("asistencias.bloque");
    $asistencias = $alumno->asistencias->sortByDesc("fecha")->take(20);
    $puedeBitacora = auth()->user() && (auth()->user()->isAdmin() || auth()->user()->isProfesor() || auth()->user()->isCoordinadorSede() || auth()->user()->isCoordinadorArea());
    $conCuaderno = $puedeBitacora && \Illuminate\Support\Facades\Schema::hasTable('observaciones_pedagogicas');
    $pestanas = [
        'resumen' => 'Resumen',
        'cuenta' => 'Cuenta'.($vencidas ? ' <span class="badge bg-danger">'.$vencidas.'</span>' : ''),
        'asistencias' => 'Asistencias',
    ];
    if ($conCuaderno) {
        $pestanas['cuaderno'] = 'Cuaderno';
    }
@endphp
<x-ito.shell-page :title="$alumno->nombre_apellido" :subtitle="collect([$alumno->dni ? 'DNI '.$alumno->dni : 'DNI sin cargar', $alumno->edad !== null ? $alumno->edad.' años' : null, $alumno->sede?->nombre])->filter()->join(' · ')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$alumno->activo ? 'success' : 'neutral'" :label="$alumno->activo ? 'Activo' : 'Inactivo'" />
        @if($isAdmin)
            <a href="{{ route('alumnos.index') }}" class="btn btn-outline-secondary">Volver</a>
            @if($alumno->persona_id)
                <a href="{{ route('personas.show', $alumno->persona_id) }}" class="btn btn-outline-secondary"><i class="bi bi-person-vcard" aria-hidden="true"></i> Ficha</a>
            @endif
            <a href="{{ route('alumnos.edit', $alumno) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @else
            <a href="{{ route('profesor.alumnos') }}" class="btn btn-outline-secondary">Volver a mis alumnos</a>
        @endif
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Bloque principal" :value="$bloquePrincipal?->nombre" />
        <x-ito.fact label="Instrumento" :value="$alumno->instrumento_principal" />
        <x-ito.fact label="Cuotas">
            @if($vencidas)
                <x-ito.status tone="danger" :label="$vencidas.' vencida'.($vencidas > 1 ? 's' : '')" />
            @elseif($pendientes)
                <x-ito.status tone="warning" :label="$pendientes.' pendiente'.($pendientes > 1 ? 's' : '')" />
            @else
                <x-ito.status tone="success" label="Al día" />
            @endif
        </x-ito.fact>
        <x-ito.fact label="Teléfono" :value="$alumno->telefono" />
    </x-ito.facts>

    @if(!empty($profesorPerfil))
        <div class="alert alert-info mb-0">
            <i class="bi bi-person-badge" aria-hidden="true"></i> También es <strong>profesor</strong>:
            <a href="{{ route('profesores.show', $profesorPerfil) }}">{{ $profesorPerfil->nombre }}</a>
        </div>
    @endif

    <x-ito.tabs id="alumnoTabs" :tabs="$pestanas">
        <x-ito.tab tabs="alumnoTabs" name="resumen" :active="true">
            <div class="ito-detail-grid">
                <div class="ito-detail-col">
                    <x-ito.detail-section title="Clases" icon="bi-collection" :flush="true">
                        @if($alumno->bloques->isNotEmpty())
                            <div class="table-responsive">
                                <table class="table align-middle mb-0" data-ito-no-cards>
                                    <thead><tr><th>Bloque</th><th>Sede</th><th>Profesor</th></tr></thead>
                                    <tbody>
                                        @foreach($alumno->bloques as $bloque)
                                            <tr>
                                                <td>
                                                    <a href="{{ $isAdmin ? route('bloques.show', $bloque) : '#' }}">{{ $bloque->nombre ?? 'Bloque' }}</a>
                                                    @if($bloque->pivot->es_principal ?? false)<span class="badge bg-primary ms-1">Principal</span>@endif
                                                </td>
                                                <td class="text-muted">{{ $bloque->sede?->nombre ?? '—' }}</td>
                                                <td class="text-muted">{{ $bloque->profesor?->nombre ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif($alumno->bloque)
                            <p class="m-3">{{ $alumno->bloque->nombre }} @if($alumno->bloque->profesor)({{ $alumno->bloque->profesor->nombre }})@endif</p>
                        @else
                            <x-ito.empty icon="bi-collection" title="Sin clases" description="Todavía no está inscripto en ningún bloque." />
                        @endif
                    </x-ito.detail-section>

                    <x-ito.detail-section title="Datos e instrumento" icon="bi-person">
                        <dl class="ito-dl">
                            <div><dt>Nacimiento</dt><dd>{{ $alumno->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</dd></div>
                            <div><dt>Instrumento principal</dt><dd>{{ $alumno->instrumento_principal ?? '—' }}</dd></div>
                            <div><dt>Secundario</dt><dd>{{ $alumno->instrumento_secundario ?? '—' }}</dd></div>
                            <div><dt>Tipo de tambor</dt><dd>{{ $alumno->tipo_tambor ?? '—' }}</dd></div>
                            <div><dt>Procedencia</dt><dd>{{ $alumno->tambor_procedencia ?? '—' }}</dd></div>
                        </dl>
                    </x-ito.detail-section>
                </div>
                <div class="ito-detail-col">
                    <x-ito.detail-section title="Contacto" icon="bi-telephone">
                        <x-ito.contact-actions :telefono="$alumno->telefono" :nombre="$alumno->nombre_apellido" />
                    </x-ito.detail-section>
                </div>
            </div>
        </x-ito.tab>

        <x-ito.tab tabs="alumnoTabs" name="cuenta">
            <x-ito.detail-section title="Estado de cuenta" icon="bi-cash-stack" help="Cuotas que le corresponden y si están pagadas." :flush="true">
                @if($estadoCuenta->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Período</th><th>Cuota</th><th>Pagada el</th><th class="text-end">Estado</th></tr></thead>
                            <tbody>
                                @foreach($estadoCuenta as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['periodo'] }}</td>
                                        <td>{{ $row['monto'] ? '$ '.number_format($row['monto'], 2, ',', '.') : '—' }}</td>
                                        <td>{{ $row['fecha_pago'] ? \Carbon\Carbon::parse($row['fecha_pago'])->format('d/m/Y') : '—' }}</td>
                                        <td class="text-end"><x-ito.status :tone="$tonoCuota[$row['estado_color']] ?? 'neutral'" :label="$row['estado']" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-ito.empty icon="bi-cash-stack" title="Sin cuotas" description="No hay cuotas que apliquen a este alumno." />
                @endif
            </x-ito.detail-section>

            <x-ito.detail-section title="Pagos registrados" icon="bi-receipt" :flush="true">
                <x-slot:actions>
                    @if($isAdmin)
                        <a href="{{ route('pagos.create') }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Registrar pago</a>
                    @endif
                </x-slot:actions>
                @if($historialPagos->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>Fecha</th><th>Cuota</th><th>Monto</th><th>Abono docente</th>@if($isAdmin)<th><span class="visually-hidden">Acciones</span></th>@endif</tr></thead>
                            <tbody>
                                @foreach($historialPagos as $det)
                                    <tr>
                                        <td>{{ $det->pago?->fecha_pago ? \Carbon\Carbon::parse($det->pago->fecha_pago)->format('d/m/Y') : '—' }}</td>
                                        <td>
                                            {{ $det->cuota?->nombre ?? 'Cuota' }}
                                            @if($det->cuota?->mes && $det->cuota?->año)
                                                <span class="text-muted small">({{ str_pad((string) $det->cuota->mes, 2, '0', STR_PAD_LEFT) }}/{{ $det->cuota->año }})</span>
                                            @endif
                                        </td>
                                        <td class="fw-semibold">$ {{ number_format((float) $det->monto, 2, ',', '.') }}</td>
                                        <td class="text-muted">{{ $det->abono_profesor !== null ? '$ '.number_format((float) $det->abono_profesor, 2, ',', '.') : '—' }}</td>
                                        @if($isAdmin)
                                            <td class="text-end">@if($det->pago)<a href="{{ route('pagos.show', $det->pago) }}" class="btn btn-sm btn-ghost">Ver pago</a>@endif</td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-ito.empty icon="bi-receipt" title="Sin pagos" description="Todavía no hay pagos registrados para este alumno." />
                @endif
            </x-ito.detail-section>
        </x-ito.tab>

        <x-ito.tab tabs="alumnoTabs" name="asistencias">
            <x-ito.detail-section title="Últimas asistencias" icon="bi-check2-square" :flush="true">
                @if($asistencias->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" data-ito-no-cards>
                            <thead><tr><th>Fecha</th><th>Bloque</th><th class="text-end">Asistencia</th></tr></thead>
                            <tbody>
                                @foreach($asistencias as $a)
                                    @php
                                        $tipoA = $a->tipo_asistencia;
                                        $tonoA = match (true) {
                                            in_array($tipoA, ['presente'], true) => 'success',
                                            in_array($tipoA, ['tarde'], true) => 'warning',
                                            in_array($tipoA, ['ausencia_justificada', 'justificado'], true) => 'info',
                                            in_array($tipoA, ['ausencia_injustificada', 'ausente'], true) => 'danger',
                                            default => $a->presente ? 'success' : 'neutral',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $a->fecha ? \Carbon\Carbon::parse($a->fecha)->format('d/m/Y') : '—' }}</td>
                                        <td class="text-muted">{{ $a->bloque?->nombre ?? '—' }}</td>
                                        <td class="text-end"><x-ito.status :tone="$tonoA" :label="\App\Models\Asistencia::TIPOS_ASISTENCIA[$tipoA] ?? ($a->presente ? 'Presente' : 'Ausente')" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <x-ito.empty icon="bi-check2-square" title="Sin asistencias" description="Todavía no hay asistencias cargadas." />
                @endif
            </x-ito.detail-section>
        </x-ito.tab>

        @if($conCuaderno)
        <x-ito.tab tabs="alumnoTabs" name="cuaderno">
            <x-ito.detail-section title="Cuaderno pedagógico" icon="bi-journal-text" help="Qué se trabajó y el siguiente paso. Si lo compartís, el alumno lo ve en su espacio.">
        <form action="{{ route('seguimiento.store') }}" method="POST" class="ito-inline-create mb-3">
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
                        @if($nota->eje)<span class="badge">{{ $nota->etiquetaEje() }}</span>@endif
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
                    <form action="{{ route('seguimiento.destroy', $nota) }}" method="POST" data-confirm="¿Eliminar esta nota del cuaderno?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-ghost text-danger" aria-label="Eliminar nota"><i class="bi bi-trash" aria-hidden="true"></i></button>
                    </form>
                    @endif
                </div>
            </li>
            @empty
            <li class="list-group-item text-muted">Todavía no hay notas en el cuaderno.</li>
            @endforelse
        </ul>
            </x-ito.detail-section>
        </x-ito.tab>
        @endif
    </x-ito.tabs>
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
// Abre la pestaña indicada en la URL (#cuenta, #asistencias, #cuaderno).
(function () {
    var clave = location.hash.replace('#', '');
    var btn = clave && document.getElementById('alumnoTabs-' + clave + '-tab');
    if (btn && window.bootstrap) bootstrap.Tab.getOrCreateInstance(btn).show();
})();
</script>
@endpush
