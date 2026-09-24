@extends('layouts.app')

@section('title', $persona->nombre_completo)
@section('page-title', 'Ficha de persona')

@php
    $u = auth()->user();
    $estadoTono = ['pagada' => 'success', 'becada' => 'info', 'parcial' => 'warning', 'vencida' => 'danger', 'pendiente' => 'neutral'];
    $estadoTexto = ['pagada' => 'Pagada', 'becada' => 'Becada', 'parcial' => 'Pago parcial', 'vencida' => 'Vencida', 'pendiente' => 'Pendiente'];
@endphp

@section('content')
@php
    $pestanas = ['resumen' => 'Resumen'];
    if ($persona->alumnos->isNotEmpty()) { $pestanas['alumno'] = 'Alumno y cuenta'; }
    if ($permisos) { $pestanas['permisos'] = 'Permisos'; }
@endphp
<x-ito.shell-page
    :title="$persona->nombre_completo"
    :subtitle="collect([$persona->dni ? 'DNI '.$persona->dni : null, $persona->edad ? $persona->edad.' años' : null, $persona->telefono])->filter()->join(' · ')"
    :plain="true"
>
    <x-slot:actions>
        <x-ito.status :tone="$persona->estado === 'activo' ? 'success' : 'neutral'" :label="\App\Models\Persona::ESTADOS[$persona->estado] ?? ucfirst($persona->estado ?? '')" />
        @can('update', $persona)
            <a href="{{ route('personas.edit', $persona) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar datos</a>
        @endcan
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Más</button>
            <ul class="dropdown-menu dropdown-menu-end">
                @if($persona->alumnos->isEmpty())
                    @can('alumnos.create')
                        <li><a class="dropdown-item" href="{{ route('alumnos.create', ['persona_id' => $persona->id]) }}"><i class="bi bi-mortarboard" aria-hidden="true"></i> Inscribir como alumno</a></li>
                    @endcan
                @endif
                @if(! $persona->profesor)
                    @can('profesores.create')
                        <li><a class="dropdown-item" href="{{ route('profesores.create', ['persona_id' => $persona->id]) }}"><i class="bi bi-person-video3" aria-hidden="true"></i> Sumar al plantel docente</a></li>
                    @endcan
                @endif
                <li><a class="dropdown-item" href="{{ route('personas.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al listado</a></li>
            </ul>
        </div>
        @if($persona->user)
            @can('view', $persona->user)
                <a href="{{ route('usuarios.show', $persona->user) }}" class="btn btn-primary"><i class="bi bi-shield-lock" aria-hidden="true"></i> Cuenta y permisos</a>
            @endcan
        @else
            @can('usuarios.create')
                <a href="{{ route('usuarios.create', ['persona_id' => $persona->id]) }}" class="btn btn-primary"><i class="bi bi-key" aria-hidden="true"></i> Crear cuenta de acceso</a>
            @endcan
        @endif
    </x-slot:actions>

    @if($persona->fusionadaEn)
        <div class="alert alert-warning mb-0">Esta ficha se fusionó en <a href="{{ route('personas.show', $persona->fusionadaEn) }}">{{ $persona->fusionadaEn->nombre_completo }}</a>.</div>
    @endif

    <x-ito.tabs id="personaTabs" :tabs="$pestanas">
        <x-ito.tab tabs="personaTabs" name="resumen" :active="true">
            <x-ito.detail-section title="Funciones" icon="bi-diagram-3" :flush="true">
                @if($funciones === [])
                    <x-ito.empty icon="bi-diagram-3" title="Sin funciones" description="Todavía no tiene funciones en la escuela." />
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0" data-ito-no-cards>
                            <thead><tr><th>Rol</th><th>Dónde</th><th>Origen</th></tr></thead>
                            <tbody>
                                @foreach($funciones as $f)
                                    <tr>
                                        <td class="fw-semibold">{{ $f['rol_nombre'] }}</td>
                                        <td>{{ $f['ambito_nombre'] }}</td>
                                        <td class="small text-muted">{{ $f['origen_etiqueta'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ito.detail-section>

            <div class="ito-detail-grid">
                <div class="ito-detail-col">
                    <x-ito.detail-section title="Datos personales" icon="bi-person">
                        <dl class="ito-dl">
                            <div><dt>Teléfono</dt><dd>{{ $persona->telefono ?? '—' }}</dd></div>
                            <div><dt>Email</dt><dd>{{ $persona->email ?? '—' }}</dd></div>
                            <div><dt>Nacimiento</dt><dd>{{ $persona->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</dd></div>
                            <div><dt>Dirección</dt><dd>{{ $persona->direccion ?? '—' }}</dd></div>
                            <div><dt>Emergencia</dt><dd>{{ trim(($persona->contacto_emergencia_nombre ?? '').' '.($persona->contacto_emergencia_telefono ?? '')) ?: '—' }}</dd></div>
                            @if($persona->observaciones)<div><dt>Observaciones</dt><dd>{{ $persona->observaciones }}</dd></div>@endif
                        </dl>
                    </x-ito.detail-section>

                    @if($persona->profesor)
                        <x-ito.detail-section title="Docente" icon="bi-person-badge">
                            <x-slot:actions>
                                @can('profesores.view')<a class="btn btn-sm btn-ghost" href="{{ route('profesores.show', $persona->profesor) }}">Ver ficha docente</a>@endcan
                            </x-slot:actions>
                            @forelse($persona->profesor->bloques as $b)
                                <div class="hub-list-item">
                                    <span class="fw-semibold">{{ $b->nombre }}</span>
                                    <span class="small text-muted ms-auto">{{ $b->sede?->nombre }} · {{ $b->pivot->rol }}</span>
                                </div>
                            @empty
                                <p class="text-muted mb-0">Sin bloques asignados.</p>
                            @endforelse
                        </x-ito.detail-section>
                    @endif
                </div>

                <div class="ito-detail-col">
                    <x-ito.detail-section title="Cuenta de acceso" icon="bi-key">
                        @if($persona->user)
                            <dl class="ito-dl">
                                <div><dt>Usuario</dt><dd class="ito-mono">{{ $persona->user->username }}</dd></div>
                                <div><dt>Estado</dt><dd><x-ito.status :tone="$persona->user->activo ? 'success' : 'neutral'" :label="$persona->user->activo ? 'Activa' : 'Desactivada'" /></dd></div>
                                <div><dt>Última actividad</dt><dd>{{ $persona->user->ultimo_acceso_at?->diffForHumans() ?? 'Sin registro' }}</dd></div>
                            </dl>
                        @else
                            <p class="text-muted mb-0">No tiene cuenta. Puede ser alumno o docente sin entrar al sistema.</p>
                        @endif
                    </x-ito.detail-section>

                    <x-ito.detail-section title="Próximos eventos" icon="bi-calendar-event">
                        @forelse($eventos as $e)
                            <div class="hub-list-item">
                                <span class="agenda-date" aria-hidden="true"><span class="agenda-date-d">{{ $e->fecha->format('d') }}</span><span class="agenda-date-m">{{ $e->fecha->locale('es')->translatedFormat('M') }}</span></span>
                                <span class="fw-semibold">{{ $e->titulo }}</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Sin eventos próximos.</p>
                        @endforelse
                    </x-ito.detail-section>

                    @if($asistencias->isNotEmpty())
                        <x-ito.detail-section title="Asistencias recientes" icon="bi-check2-square">
                            @foreach($asistencias as $a)
                                <div class="hub-list-item small">
                                    <span>{{ $a->fecha->format('d/m') }} · {{ $a->bloque?->nombre }}</span>
                                    <strong class="ms-auto">{{ \App\Models\Asistencia::TIPOS_ASISTENCIA[$a->tipo_asistencia] ?? ($a->presente ? 'Presente' : 'Ausente') }}</strong>
                                </div>
                            @endforeach
                        </x-ito.detail-section>
                    @endif

                    @if($inventario->isNotEmpty())
                        <x-ito.detail-section title="Instrumentos a su nombre" icon="bi-box-seam">
                            @foreach($inventario as $i)
                                <div class="hub-list-item small"><span class="ito-mono">{{ $i->codigo }}</span> {{ $i->nombre }} <span class="text-muted ms-auto">{{ $i->sede?->nombre }}</span></div>
                            @endforeach
                        </x-ito.detail-section>
                    @endif
                </div>
            </div>

    @if($puedeFusionar && ! $persona->fusionadaEn)
        <details class="ito-details">
            <summary>¿Esta persona está duplicada?</summary>
            <form method="POST" action="{{ route('personas.fusionar', $persona) }}" class="d-flex flex-wrap gap-2 align-items-end mt-2" data-confirm="Se van a unificar fichas, cuenta y asignaciones en esta persona. ¿Continuar?">
                @csrf
                <input type="hidden" name="persona_id" value="{{ $persona->id }}">
                <div>
                    <label class="form-label small" for="duplicada_id">N° de la persona duplicada (se absorbe en esta)</label>
                    <input id="duplicada_id" type="number" name="duplicada_id" class="form-control form-control-sm" required min="1">
                </div>
                <button class="btn btn-outline-danger btn-sm">Fusionar en esta ficha</button>
            </form>
            <p class="small text-muted mt-1">El número figura en la URL de cada ficha. <code>php artisan chilinga:diagnose</code> lista posibles duplicados.</p>
        </details>
    @endif
        </x-ito.tab>

        @if($persona->alumnos->isNotEmpty())
        <x-ito.tab tabs="personaTabs" name="alumno">
    @foreach($persona->alumnos as $alumno)
        <section class="ito-detail-section" aria-labelledby="alumno-{{ $alumno->id }}-titulo"><div class="ito-detail-section-body">
            <h2 id="alumno-{{ $alumno->id }}-titulo" class="ito-detail-section-title mb-2"><i class="bi bi-mortarboard" aria-hidden="true"></i> Alumno
                @can('view', $alumno)<a class="small ms-2" href="{{ route('alumnos.show', $alumno) }}">ver ficha de alumno</a>@endcan
            </h2>
            <p class="mb-2">
                {{ $alumno->bloques->map(fn ($b) => $b->nombre.' ('.($b->sede?->nombre ?? 's/sede').')')->join(', ') ?: 'Sin bloque' }}
                @unless($alumno->activo)<x-ito.status tone="neutral" label="Inactivo" />@endunless
            </p>

            @isset($cuentas[$alumno->id])
                @php $cuenta = $cuentas[$alumno->id]; @endphp
                <div class="ito-facts mb-3">
                    <div class="ito-fact"><dt>Cuotas {{ $cuenta['anio'] }}</dt><dd><strong>$ {{ number_format($cuenta['totales']['neto'], 0, ',', '.') }}</strong></dd></div>
                    <div class="ito-fact"><dt>Pagado</dt><dd><strong>$ {{ number_format($cuenta['totales']['pagado'], 0, ',', '.') }}</strong></dd></div>
                    <div class="ito-fact"><dt>Saldo</dt><dd><strong @class(['text-danger' => $cuenta['totales']['saldo'] > 0])>$ {{ number_format($cuenta['totales']['saldo'], 0, ',', '.') }}</strong></dd></div>
                    @if($cuenta['totales']['descuento'] > 0)
                        <div class="ito-fact"><dt>Descuento por beca</dt><dd><strong>$ {{ number_format($cuenta['totales']['descuento'], 0, ',', '.') }}</strong></dd></div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" data-ito-no-cards>
                        <thead><tr><th>Período</th><th>Cuota</th><th class="text-end">Importe</th><th class="text-end">Pagado</th><th class="text-end">Saldo</th><th>Estado</th></tr></thead>
                        <tbody>
                            @forelse($cuenta['items'] as $item)
                                <tr>
                                    <td>{{ $item['periodo'] }}</td>
                                    <td>{{ $item['nombre'] }} @if($item['beca'])<span class="badge bg-info">{{ $item['beca']['etiqueta'] }}</span>@endif</td>
                                    <td class="text-end ito-mono">$ {{ number_format($item['neto'], 0, ',', '.') }}@if($item['descuento'] > 0)<div class="small text-muted text-decoration-line-through">$ {{ number_format($item['bruto'], 0, ',', '.') }}</div>@endif</td>
                                    <td class="text-end ito-mono">$ {{ number_format($item['pagado'], 0, ',', '.') }}</td>
                                    <td class="text-end ito-mono">$ {{ number_format($item['saldo'], 0, ',', '.') }}</td>
                                    <td><x-ito.status :tone="$estadoTono[$item['estado']] ?? 'neutral'" :label="$estadoTexto[$item['estado']] ?? $item['estado']" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">No hay cuotas cargadas para {{ $cuenta['anio'] }}.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h3 class="h6 mt-3">Becas</h3>
                @forelse($alumno->becas as $beca)
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="fw-semibold">{{ $beca->etiqueta() }}</span>
                        <span class="text-muted small">desde {{ $beca->fecha_inicio->format('d/m/Y') }}{{ $beca->fecha_fin ? ' hasta '.$beca->fecha_fin->format('d/m/Y') : '' }} · {{ \App\Models\Beca::ESTADOS[$beca->estado] ?? $beca->estado }}</span>
                        @if($beca->motivo)<span class="small">· {{ $beca->motivo }}</span>@endif
                        @can('gestionarBecas', $alumno)
                            @if($beca->estado === 'activa')
                                <form method="POST" action="{{ route('becas.update', $beca) }}" class="d-inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="estado" value="finalizada">
                                    <input type="hidden" name="fecha_fin" value="{{ now()->toDateString() }}">
                                    <button class="btn btn-link btn-sm p-0">Finalizar hoy</button>
                                </form>
                            @endif
                        @endcan
                    </div>
                @empty
                    <p class="text-muted small">Sin becas.</p>
                @endforelse

                @can('gestionarBecas', $alumno)
                    <details class="ito-details mt-2">
                        <summary>Otorgar beca</summary>
                        <form method="POST" action="{{ route('becas.store', $persona) }}" class="row g-2 mt-2">
                            @csrf
                            <input type="hidden" name="alumno_id" value="{{ $alumno->id }}">
                            <div class="col-md-3">
                                <label class="form-label small" for="beca-tipo-{{ $alumno->id }}">Tipo</label>
                                <select id="beca-tipo-{{ $alumno->id }}" name="tipo" class="form-select form-select-sm">
                                    @foreach($tiposBeca as $v => $t)<option value="{{ $v }}">{{ $t }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small" for="beca-porc-{{ $alumno->id }}">%</label>
                                <input id="beca-porc-{{ $alumno->id }}" type="number" name="porcentaje" min="1" max="100" step="0.01" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small" for="beca-monto-{{ $alumno->id }}">Monto fijo</label>
                                <input id="beca-monto-{{ $alumno->id }}" type="number" name="monto" min="1" step="0.01" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small" for="beca-desde-{{ $alumno->id }}">Desde</label>
                                <input id="beca-desde-{{ $alumno->id }}" type="date" name="fecha_inicio" value="{{ now()->startOfMonth()->toDateString() }}" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small" for="beca-hasta-{{ $alumno->id }}">Hasta (opcional)</label>
                                <input id="beca-hasta-{{ $alumno->id }}" type="date" name="fecha_fin" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small" for="beca-motivo-{{ $alumno->id }}">Motivo</label>
                                <input id="beca-motivo-{{ $alumno->id }}" name="motivo" class="form-control form-control-sm" maxlength="255">
                            </div>
                            <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">Guardar beca</button></div>
                        </form>
                    </details>
                @endcan
            @endisset
        </div></section>
    @endforeach

        </x-ito.tab>
        @endif

        @if($permisos)
        <x-ito.tab tabs="personaTabs" name="permisos">
            <x-ito.detail-section title="Qué puede hacer en el sistema" icon="bi-shield-check">
                @include('usuarios.partials.permisos', ['permisos' => $permisos])
            </x-ito.detail-section>
        </x-ito.tab>
        @endif
    </x-ito.tabs>
</x-ito.shell-page>
@endsection
