@extends('layouts.app')

@section('title', $item->nombre)
@section('page-title', $item->nombre)

@section('content')
@php
    $tonoEstado = ['nuevo' => 'success', 'bueno' => 'success', 'regular' => 'warning', 'reparacion' => 'warning', 'baja' => 'danger'];
    $estadoLabel = \App\Models\InventarioItem::ESTADOS[$item->estado] ?? $item->estado;
    $cantidad = $item->es_consumible ? number_format((float) $item->cantidad, 2, ',', '.').' '.($item->unidad ?? '') : '1 u';
    $propiedad = $item->propietario_label.($item->propietario_tipo === 'alumno' && $item->alumno ? ' · '.$item->alumno->nombre_apellido : '');
    $conMovimientos = \Illuminate\Support\Facades\Schema::hasTable('inventario_movimientos');
@endphp
<x-ito.shell-page :title="$item->nombre" :subtitle="collect([$item->codigo, $item->tipo_label, $item->sede?->nombre])->filter()->join(' · ')" :plain="true">
    <x-slot:actions>
        <x-ito.status :tone="$tonoEstado[$item->estado] ?? 'neutral'" :label="$estadoLabel" />
        <a href="{{ route('inventarios.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('update', $item)
            <a href="{{ route('inventarios.edit', $item) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Sede" :value="$item->sede?->nombre" />
        <x-ito.fact label="Tipo" :value="$item->tipo_label" />
        <x-ito.fact label="Propiedad" :value="$propiedad" />
        <x-ito.fact label="Cantidad" :value="$cantidad" />
        <x-ito.fact label="Marca / modelo" :value="collect([$item->marca, $item->modelo])->filter()->join(' ')" />
    </x-ito.facts>

    <div class="ito-detail-grid">
        <div class="ito-detail-col">
            @if($conMovimientos)
                <x-ito.detail-section title="Actualizar ahora" icon="bi-arrow-repeat" help="Después de escanear el código: elegí el movimiento, el estado y dónde queda.">
                    <form method="POST" action="{{ route('inventarios.movimientos.store', $item) }}" class="row g-3 align-items-end" id="mov-rapido">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label" for="mov-tipo">Movimiento</label>
                            <select id="mov-tipo" name="tipo" class="form-select" required>
                                @foreach(\App\Models\InventarioMovimiento::TIPOS as $k => $lab)
                                    <option value="{{ $k }}">{{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mov-estado">Estado</label>
                            <select id="mov-estado" name="estado" class="form-select">
                                <option value="">Sin cambio</option>
                                @foreach(\App\Models\InventarioItem::ESTADOS as $k => $lab)
                                    <option value="{{ $k }}" @selected($item->estado === $k)>{{ $lab }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mov-sede">Queda en la sede</label>
                            <select id="mov-sede" name="sede_id" class="form-select">
                                <option value="">Sin cambio</option>
                                @foreach($sedes ?? [] as $s)
                                    <option value="{{ $s->id }}" @selected($item->sede_id == $s->id)>{{ $s->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="mov-nota">Nota</label>
                            <input id="mov-nota" type="text" name="nota" class="form-control" maxlength="400" placeholder="Ej.: se rompió en el ensayo">
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Registrar movimiento</button>
                        </div>
                    </form>
                </x-ito.detail-section>

                <x-ito.detail-section title="Historial" icon="bi-clock-history">
                    @if(($item->movimientos ?? collect())->isNotEmpty())
                        <ol class="ito-timeline">
                            @foreach($item->movimientos as $mov)
                                <li>
                                    <div class="fw-semibold">{{ $mov->etiquetaTipo() }}</div>
                                    <div class="small text-muted">
                                        {{ $mov->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                                        @if($mov->sede) · {{ $mov->sede->nombre }}@endif
                                        · {{ $mov->autor->name ?? $mov->autor->username ?? 'Sistema' }}
                                    </div>
                                    @if($mov->nota)<div class="small mt-1">{{ $mov->nota }}</div>@endif
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="text-muted mb-0">Todavía no hay movimientos. El alta quedó registrada al crear el ítem.</p>
                    @endif
                </x-ito.detail-section>
            @endif

            <x-ito.detail-section title="Especificaciones" icon="bi-rulers">
                <dl class="ito-dl">
                    <div><dt>Línea</dt><dd>{{ $item->linea ?? '—' }}</dd></div>
                    <div><dt>Material</dt><dd>{{ $item->material ?? '—' }}</dd></div>
                    <div><dt>Color</dt><dd>{{ $item->color ?? '—' }}</dd></div>
                    <div><dt>Medida</dt><dd>{{ $item->medida ?? '—' }}</dd></div>
                    <div><dt>Diámetro</dt><dd>{{ $item->diametro_pulgadas ? $item->diametro_pulgadas.'"' : '—' }}</dd></div>
                    <div><dt>Torres</dt><dd>{{ $item->torres ?? '—' }}</dd></div>
                    <div><dt>Fabricación</dt><dd>{{ $item->anio_fabricacion ?? '—' }}</dd></div>
                    <div><dt>Utilitario</dt><dd>{{ $item->utilitario ? 'Sí, repuesto' : 'No' }}</dd></div>
                </dl>
            </x-ito.detail-section>
        </div>

        <div class="ito-detail-col">
            @if($item->codigo)
                @php $urlPublica = route('inventario.publico', $item->codigo); @endphp
                <x-ito.detail-section title="Etiqueta" icon="bi-qr-code">
                    <div class="d-flex align-items-center gap-3">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&amp;data={{ urlencode($urlPublica) }}"
                             alt="Código QR de {{ $item->codigo }}" width="120" height="120" class="ito-qr" loading="lazy">
                        <div class="d-flex flex-column gap-2">
                            <span class="ito-mono fs-6 fw-semibold">{{ $item->codigo }}</span>
                            <a class="small" href="{{ $urlPublica }}">Ver ficha pública</a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-url="{{ $urlPublica }}">Copiar enlace</button>
                        </div>
                    </div>
                </x-ito.detail-section>
            @endif

            <x-ito.detail-section title="Adquisición" icon="bi-bag">
                <dl class="ito-dl">
                    <div><dt>Origen</dt><dd>{{ \App\Models\InventarioItem::ORIGENES[$item->origen_adquisicion] ?? ($item->origen_adquisicion ?: '—') }}</dd></div>
                    <div><dt>Fecha</dt><dd>{{ $item->fecha_adquisicion?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt>Precio</dt><dd>{{ $item->precio !== null ? '$ '.number_format($item->precio, 2, ',', '.') : '—' }}</dd></div>
                    <div><dt>Reparado</dt><dd>{{ $item->reparado_en?->format('d/m/Y') ?? '—' }}</dd></div>
                    @if($item->detalle_reparacion)<div><dt>Reparación</dt><dd>{{ $item->detalle_reparacion }}</dd></div>@endif
                </dl>
                @if($item->notas)
                    <p class="small mt-3 mb-0" style="white-space: pre-line">{{ $item->notas }}</p>
                @endif
            </x-ito.detail-section>
        </div>
    </div>
</x-ito.shell-page>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-copy-url]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const url = btn.getAttribute('data-copy-url');
            if (!url || !navigator.clipboard) return;
            navigator.clipboard.writeText(url).then(function () {
                const prev = btn.textContent;
                btn.textContent = 'Copiado';
                setTimeout(function () { btn.textContent = prev; }, 1600);
            });
        });
    });
})();
</script>
@endpush

