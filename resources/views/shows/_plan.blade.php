{{-- Preparación del show: confirmaciones, asistencia, temas, ensayos, logística e instrumentos. --}}
@php
    $puedeEditar = auth()->user()->can('update', $show);
    $puedeAsistencia = auth()->user()->can('asistencia', $show);
    $tonos = ['voy' => 'success', 'tal_vez' => 'warning', 'no_voy' => 'danger'];
@endphp

<x-ito.facts class="mt-3">
    <x-ito.fact label="Convocados" :value="$resumen['convocados']" />
    <x-ito.fact label="Van" :value="$resumen['voy']" />
    <x-ito.fact label="Tal vez" :value="$resumen['tal_vez']" />
    <x-ito.fact label="No van" :value="$resumen['no_voy']" />
    <x-ito.fact label="Sin responder" :value="$resumen['sin_respuesta']" />
    <x-ito.fact label="Fueron al show" :value="$resumen['asistieron']" />
</x-ito.facts>

<div class="ito-detail-grid">
    <x-ito.detail-section title="Confirmaciones y asistencia" icon="bi-people" help="Cada alumno convocado responde desde la app o la web. Después del show, marcá quién fue.">
        @if($planilla === [])
            <p class="text-muted mb-0">No hay alumnos convocados todavía.</p>
        @else
            <form method="POST" action="{{ route('shows.asistencia', $show) }}">
                @csrf
                <table class="ito-table">
                    <thead><tr><th>Alumno</th><th>Respuesta</th><th>¿Fue?</th></tr></thead>
                    <tbody>
                    @foreach($planilla as $f)
                        <tr>
                            <td>{{ $f['nombre'] }} <span class="small text-muted">{{ $f['bloque'] }}</span>
                                @if($f['comentario'])<div class="small text-muted">«{{ $f['comentario'] }}»</div>@endif
                            </td>
                            <td><x-ito.status :tone="$tonos[$f['respuesta']] ?? 'neutral'" :label="$f['respuesta_texto']" /></td>
                            <td>
                                @if($puedeAsistencia)
                                    <select name="marcas[{{ $f['alumno_id'] }}]" class="form-select form-select-sm" aria-label="¿Fue {{ $f['nombre'] }}?">
                                        <option value="" @selected($f['asistio'] === null)>—</option>
                                        <option value="1" @selected($f['asistio'] === true)>Fue</option>
                                        <option value="0" @selected($f['asistio'] === false)>No fue</option>
                                    </select>
                                @else
                                    {{ $f['asistio'] === null ? '—' : ($f['asistio'] ? 'Fue' : 'No fue') }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($puedeAsistencia)
                    <button class="btn btn-primary btn-sm mt-2"><i class="bi bi-check2-square" aria-hidden="true"></i> Guardar asistencia</button>
                @endif
            </form>
        @endif
    </x-ito.detail-section>

    <x-ito.detail-section title="Lista de temas" icon="bi-music-note-list">
        @forelse($plan['temas'] as $t)
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="text-muted ito-mono">{{ $loop->iteration }}.</span>
                <span class="fw-semibold">{{ $t['nombre'] }}</span>
                @if($t['notas'])<span class="small text-muted">· {{ $t['notas'] }}</span>@endif
                @if($puedeEditar)
                    <form method="POST" action="{{ route('shows.temas.destroy', [$show, $t['id']]) }}" class="ms-auto">
                        @csrf @method('DELETE')
                        <button class="btn btn-link btn-sm text-danger p-0" aria-label="Quitar {{ $t['nombre'] }}"><i class="bi bi-x-lg"></i></button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-muted">Todavía no hay temas.</p>
        @endforelse
        @if($puedeEditar)
            <form method="POST" action="{{ route('shows.temas.store', $show) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-md-5">
                    <select name="programa_ritmo_id" class="form-select form-select-sm" aria-label="Toque del programa">
                        <option value="">Toque del programa…</option>
                        @foreach($catalogoPlan['toques'] as $tq)
                            <option value="{{ $tq['id'] }}">{{ $tq['nombre'] }}@if($tq['anio']) ({{ $tq['anio'] }}°)@endif</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><input name="titulo" class="form-control form-control-sm" placeholder="u otro tema" aria-label="Otro tema"></div>
                <div class="col-md-3"><button class="btn btn-outline-primary btn-sm w-100">Agregar</button></div>
                @error('titulo')<div class="col-12 text-danger small">Elegí un toque o escribí el tema.</div>@enderror
            </form>
        @endif
    </x-ito.detail-section>

    <x-ito.detail-section title="Ensayos" icon="bi-calendar-week">
        @forelse($plan['ensayos'] as $e)
            <a class="hub-list-item hub-list-item--link" href="{{ route('shows.ensayos.show', $e['id']) }}">
                <span class="fw-semibold">{{ \Illuminate\Support\Carbon::parse($e['fecha'])->format('d/m') }} {{ $e['hora_inicio'] }}</span>
                <span class="small text-muted">{{ $e['lugar'] }}</span>
                <span class="small ms-auto">{{ $e['presentes'] }} presentes</span>
            </a>
        @empty
            <p class="text-muted">Sin ensayos cargados.</p>
        @endforelse
        @if($puedeEditar)
            <form method="POST" action="{{ route('shows.ensayos.store', $show) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-md-4"><input type="date" name="fecha" class="form-control form-control-sm" required aria-label="Fecha del ensayo"></div>
                <div class="col-md-3"><input type="time" name="hora_inicio" class="form-control form-control-sm" aria-label="Hora del ensayo"></div>
                <div class="col-md-5"><input name="lugar" class="form-control form-control-sm" placeholder="Lugar" aria-label="Lugar del ensayo"></div>
                <div class="col-12"><button class="btn btn-outline-primary btn-sm">Agregar ensayo</button></div>
            </form>
        @endif
    </x-ito.detail-section>

    <x-ito.detail-section title="Logística" icon="bi-bus-front">
        @if($puedeEditar)
            <form method="POST" action="{{ route('shows.logistica', $show) }}" class="row g-2">
                @csrf @method('PUT')
                <div class="col-md-8"><label class="form-label small" for="punto_encuentro">Punto de encuentro</label><input id="punto_encuentro" name="punto_encuentro" class="form-control form-control-sm" value="{{ $plan['logistica']['punto_encuentro'] }}"></div>
                <div class="col-md-4"><label class="form-label small" for="hora_encuentro">Hora</label><input type="time" id="hora_encuentro" name="hora_encuentro" class="form-control form-control-sm" value="{{ $plan['logistica']['hora_encuentro'] }}"></div>
                <div class="col-12"><label class="form-label small" for="transporte">Transporte</label><input id="transporte" name="transporte" class="form-control form-control-sm" value="{{ $plan['logistica']['transporte'] }}" placeholder="Micro, combis, cada uno por su cuenta…"></div>
                <div class="col-12"><label class="form-label small" for="logistica_notas">Notas</label><textarea id="logistica_notas" name="logistica_notas" rows="2" class="form-control form-control-sm">{{ $plan['logistica']['notas'] }}</textarea></div>
                <div class="col-12"><button class="btn btn-outline-primary btn-sm">Guardar logística</button></div>
            </form>
        @else
            <x-ito.facts>
                <x-ito.fact label="Punto de encuentro" :value="$plan['logistica']['punto_encuentro']" />
                <x-ito.fact label="Hora" :value="$plan['logistica']['hora_encuentro']" />
                <x-ito.fact label="Transporte" :value="$plan['logistica']['transporte']" />
            </x-ito.facts>
            @if($plan['logistica']['notas'])<p class="mb-0 small" style="white-space: pre-line">{{ $plan['logistica']['notas'] }}</p>@endif
        @endif
    </x-ito.detail-section>

    <x-ito.detail-section title="Instrumentos que van" icon="bi-box-seam">
        @forelse($plan['instrumentos'] as $i)
            <div class="d-flex align-items-center gap-2 mb-1">
                <span>{{ $i['cantidad'] }} × {{ $i['nombre'] }}</span>
                @if($puedeEditar)
                    <form method="POST" action="{{ route('shows.instrumentos.destroy', [$show, $i['id']]) }}" class="ms-auto">
                        @csrf @method('DELETE')
                        <button class="btn btn-link btn-sm text-danger p-0" aria-label="Quitar {{ $i['nombre'] }}"><i class="bi bi-x-lg"></i></button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-muted">Sin instrumentos anotados.</p>
        @endforelse
        @if($puedeEditar)
            <form method="POST" action="{{ route('shows.instrumentos.store', $show) }}" class="row g-2 mt-2">
                @csrf
                <div class="col-md-5">
                    <select name="inventario_item_id" class="form-select form-select-sm" aria-label="Instrumento del inventario">
                        <option value="">Del inventario (disponibles)…</option>
                        @foreach($catalogoPlan['instrumentos'] as $it)
                            <option value="{{ $it['id'] }}">{{ $it['nombre'] }} {{ $it['codigo'] ? '('.$it['codigo'].')' : '' }} · {{ $it['sede'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4"><input name="descripcion" class="form-control form-control-sm" placeholder="u otro (ej. 4 surdos)" aria-label="Otro instrumento"></div>
                <div class="col-md-1"><input type="number" name="cantidad" min="1" value="1" class="form-control form-control-sm" aria-label="Cantidad"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100">Agregar</button></div>
            </form>
        @endif
    </x-ito.detail-section>
</div>
