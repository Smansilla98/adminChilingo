@extends('layouts.app')

@section('title', $persona->nombre_completo)
@section('page-title', 'Ficha de persona')

@php
    $u = auth()->user();
    $estadoTono = ['pagada' => 'success', 'becada' => 'info', 'parcial' => 'warning', 'vencida' => 'danger', 'pendiente' => 'neutral'];
    $estadoTexto = ['pagada' => 'Pagada', 'becada' => 'Becada', 'parcial' => 'Pago parcial', 'vencida' => 'Vencida', 'pendiente' => 'Pendiente'];
@endphp

@section('content')
<x-ito.shell-page
    :title="$persona->nombre_completo"
    eyebrow="Personas"
    :subtitle="collect([$persona->dni ? 'DNI '.$persona->dni : null, $persona->edad ? $persona->edad.' años' : null, \App\Models\Persona::ESTADOS[$persona->estado] ?? null])->filter()->join(' · ')"
>
    <x-slot:actions>
        @can('update', $persona)
            <a href="{{ route('personas.edit', $persona) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Editar datos</a>
        @endcan
        @if($persona->alumnos->isEmpty())
            @can('alumnos.create')
                <a href="{{ route('alumnos.create', ['persona_id' => $persona->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-mortarboard"></i> Inscribir como alumno</a>
            @endcan
        @endif
        @if(! $persona->profesor)
            @can('profesores.create')
                <a href="{{ route('profesores.create', ['persona_id' => $persona->id]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-video3"></i> Sumar al plantel docente</a>
            @endcan
        @endif
        @if($persona->user)
            @can('view', $persona->user)
                <a href="{{ route('usuarios.show', $persona->user) }}" class="btn btn-primary btn-sm"><i class="bi bi-shield-lock"></i> Cuenta y permisos</a>
            @endcan
        @else
            @can('usuarios.create')
                <a href="{{ route('usuarios.create', ['persona_id' => $persona->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-key"></i> Crear cuenta de acceso</a>
            @endcan
        @endif
    </x-slot:actions>

    @if($persona->fusionadaEn)
        <div class="alert alert-warning">Esta ficha se fusionó en <a href="{{ route('personas.show', $persona->fusionadaEn) }}">{{ $persona->fusionadaEn->nombre_completo }}</a>.</div>
    @endif

    {{-- ¿Qué hace esta persona? --}}
    <section class="mb-4" aria-labelledby="funciones-titulo">
        <h2 id="funciones-titulo" class="h5">Funciones</h2>
        @if($funciones === [])
            <p class="text-muted">Todavía no tiene funciones en la escuela.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
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
    </section>

    <div class="row g-4">
        <section class="col-lg-6" aria-labelledby="datos-titulo">
            <h2 id="datos-titulo" class="h5">Datos personales</h2>
            <dl class="ito-dl">
                <dt>Teléfono</dt><dd>{{ $persona->telefono ?? '—' }}</dd>
                <dt>Email</dt><dd>{{ $persona->email ?? '—' }}</dd>
                <dt>Nacimiento</dt><dd>{{ $persona->fecha_nacimiento?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Dirección</dt><dd>{{ $persona->direccion ?? '—' }}</dd>
                <dt>Emergencia</dt><dd>{{ trim(($persona->contacto_emergencia_nombre ?? '').' '.($persona->contacto_emergencia_telefono ?? '')) ?: '—' }}</dd>
                @if($persona->observaciones)<dt>Observaciones</dt><dd>{{ $persona->observaciones }}</dd>@endif
            </dl>
        </section>

        <section class="col-lg-6" aria-labelledby="cuenta-titulo">
            <h2 id="cuenta-titulo" class="h5">Cuenta de acceso</h2>
            @if($persona->user)
                <dl class="ito-dl">
                    <dt>Usuario</dt><dd class="ito-mono">{{ $persona->user->username }}</dd>
                    <dt>Estado</dt><dd><x-ito.status :tone="$persona->user->activo ? 'success' : 'neutral'" :label="$persona->user->activo ? 'Activa' : 'Desactivada'" /></dd>
                    <dt>Última actividad</dt><dd>{{ $persona->user->ultimo_acceso_at?->diffForHumans() ?? 'Sin registro' }}</dd>
                </dl>
            @else
                <p class="text-muted">No tiene cuenta. Puede existir como alumno o docente sin ingresar al sistema.</p>
            @endif
        </section>
    </div>

    @if($permisos)
        <section class="mt-4" aria-labelledby="permisos-titulo">
            <h2 id="permisos-titulo" class="h5">Qué puede hacer en el sistema</h2>
            @include('usuarios.partials.permisos', ['permisos' => $permisos])
        </section>
    @endif

    {{-- Perfil docente --}}
    @if($persona->profesor)
        <section class="mt-4" aria-labelledby="docente-titulo">
            <h2 id="docente-titulo" class="h5">Docente
                @can('profesores.view')<a class="small ms-2" href="{{ route('profesores.show', $persona->profesor) }}">ver ficha docente</a>@endcan
            </h2>
            <ul class="list-unstyled mb-0">
                @forelse($persona->profesor->bloques as $b)
                    <li>{{ $b->nombre }} <span class="text-muted">· {{ $b->sede?->nombre }} · {{ $b->pivot->rol }}</span></li>
                @empty
                    <li class="text-muted">Sin bloques asignados.</li>
                @endforelse
            </ul>
        </section>
    @endif

    {{-- Perfiles de alumno: inscripción, cuotas, becas, pagos --}}
    @foreach($persona->alumnos as $alumno)
        <section class="mt-4" aria-labelledby="alumno-{{ $alumno->id }}-titulo">
            <h2 id="alumno-{{ $alumno->id }}-titulo" class="h5">Alumno
                @can('view', $alumno)<a class="small ms-2" href="{{ route('alumnos.show', $alumno) }}">ver ficha de alumno</a>@endcan
            </h2>
            <p class="mb-2">
                {{ $alumno->bloques->map(fn ($b) => $b->nombre.' ('.($b->sede?->nombre ?? 's/sede').')')->join(', ') ?: 'Sin bloque' }}
                @unless($alumno->activo)<span class="badge text-bg-secondary">inactivo</span>@endunless
            </p>

            @isset($cuentas[$alumno->id])
                @php($cuenta = $cuentas[$alumno->id])
                <div class="d-flex flex-wrap gap-3 mb-2">
                    <div><span class="text-muted small d-block">Cuotas {{ $cuenta['anio'] }}</span><strong>$ {{ number_format($cuenta['totales']['neto'], 0, ',', '.') }}</strong></div>
                    <div><span class="text-muted small d-block">Pagado</span><strong>$ {{ number_format($cuenta['totales']['pagado'], 0, ',', '.') }}</strong></div>
                    <div><span class="text-muted small d-block">Saldo</span><strong @class(['text-danger' => $cuenta['totales']['saldo'] > 0])>$ {{ number_format($cuenta['totales']['saldo'], 0, ',', '.') }}</strong></div>
                    @if($cuenta['totales']['descuento'] > 0)
                        <div><span class="text-muted small d-block">Descuento por beca</span><strong>$ {{ number_format($cuenta['totales']['descuento'], 0, ',', '.') }}</strong></div>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Período</th><th>Cuota</th><th class="text-end">Importe</th><th class="text-end">Pagado</th><th class="text-end">Saldo</th><th>Estado</th></tr></thead>
                        <tbody>
                            @forelse($cuenta['items'] as $item)
                                <tr>
                                    <td>{{ $item['periodo'] }}</td>
                                    <td>{{ $item['nombre'] }} @if($item['beca'])<span class="badge text-bg-info">{{ $item['beca']['etiqueta'] }}</span>@endif</td>
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
                    <details class="mt-2">
                        <summary class="btn btn-outline-primary btn-sm">Otorgar beca</summary>
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
        </section>
    @endforeach

    <div class="row g-4 mt-1">
        @if($asistencias->isNotEmpty())
            <section class="col-lg-4" aria-labelledby="asist-titulo">
                <h2 id="asist-titulo" class="h6">Asistencias recientes</h2>
                <ul class="list-unstyled small mb-0">
                    @foreach($asistencias as $a)
                        <li>{{ $a->fecha->format('d/m') }} · {{ $a->bloque?->nombre }} · <strong>{{ \App\Models\Asistencia::TIPOS_ASISTENCIA[$a->tipo_asistencia] ?? ($a->presente ? 'Presente' : 'Ausente') }}</strong></li>
                    @endforeach
                </ul>
            </section>
        @endif
        <section class="col-lg-4" aria-labelledby="eventos-titulo">
            <h2 id="eventos-titulo" class="h6">Próximos eventos</h2>
            <ul class="list-unstyled small mb-0">
                @forelse($eventos as $e)
                    <li>{{ $e->fecha->format('d/m') }} · {{ $e->titulo }}</li>
                @empty
                    <li class="text-muted">Sin eventos próximos.</li>
                @endforelse
            </ul>
        </section>
        @if($inventario->isNotEmpty())
            <section class="col-lg-4" aria-labelledby="inv-titulo">
                <h2 id="inv-titulo" class="h6">Instrumentos a su nombre</h2>
                <ul class="list-unstyled small mb-0">
                    @foreach($inventario as $i)
                        <li><span class="ito-mono">{{ $i->codigo }}</span> {{ $i->nombre }} · {{ $i->sede?->nombre }}</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>

    @if($puedeFusionar && ! $persona->fusionadaEn)
        <details class="mt-4">
            <summary class="small text-muted">¿Esta persona está duplicada?</summary>
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
</x-ito.shell-page>
@endsection
