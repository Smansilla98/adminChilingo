@extends('layouts.app')

@section('title', 'Detalle inventario')
@section('page-title', 'Inventario — Detalle')

@section('content')
<x-ito.shell-page
    title="{{ $item->nombre }}"
    subtitle="Detalle de inventario"
    eyebrow="Inventario"
>
    <x-slot:actions>
        <a href="{{ route('inventarios.edit', $item) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('inventarios.index') }}" class="btn btn-sm btn-outline-secondary">Volver</a>
    </x-slot:actions>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">Sede</div>
                <div class="fw-semibold">{{ $item->sede?->nombre }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Tipo</div>
                <div class="fw-semibold">{{ $item->tipo_label }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Propiedad</div>
                <div class="fw-semibold">
                    {{ $item->propietario_label }}
                    @if($item->propietario_tipo === 'alumno' && $item->alumno)
                        <span class="text-muted">— {{ $item->alumno->nombre_apellido }}</span>
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Código / etiqueta</div>
                <div class="fw-semibold font-monospace">{{ $item->codigo ?? '—' }}</div>
                @if($item->codigo)
                    @php $urlPublica = route('inventario.publico', $item->codigo); @endphp
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&amp;data={{ urlencode($urlPublica) }}"
                            alt="QR de {{ $item->codigo }}"
                            width="120"
                            height="120"
                            class="border rounded bg-white p-1"
                            loading="lazy"
                        >
                        <div class="d-flex flex-column gap-1">
                            <a class="small" href="{{ $urlPublica }}">Ficha pública</a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-url="{{ $urlPublica }}">Copiar URL</button>
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Cantidad</div>
                <div class="fw-semibold">
                    @if($item->es_consumible)
                        {{ number_format((float)$item->cantidad, 2, ',', '.') }} {{ $item->unidad ?? '' }}
                    @else
                        1 u
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Estado</div>
                <div class="fw-semibold">{{ \App\Models\InventarioItem::ESTADOS[$item->estado] ?? $item->estado }}</div>
            </div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-3"><div class="text-muted small">Marca</div><div class="fw-semibold">{{ $item->marca ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Modelo</div><div class="fw-semibold">{{ $item->modelo ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Línea</div><div class="fw-semibold">{{ $item->linea ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Material</div><div class="fw-semibold">{{ $item->material ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Color</div><div class="fw-semibold">{{ $item->color ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Medida</div><div class="fw-semibold">{{ $item->medida ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Diámetro (pulg.)</div><div class="fw-semibold">{{ $item->diametro_pulgadas ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Torres</div><div class="fw-semibold">{{ $item->torres ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Año fabricación</div><div class="fw-semibold">{{ $item->anio_fabricacion ?? '—' }}</div></div>

            <div class="col-12"><hr class="my-1"></div>

            <div class="col-md-3"><div class="text-muted small">Origen</div><div class="fw-semibold">{{ $item->origen_adquisicion ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Fecha adquisición</div><div class="fw-semibold">{{ $item->fecha_adquisicion?->format('d/m/Y') ?? '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Precio</div><div class="fw-semibold">{{ $item->precio !== null ? '$ ' . number_format($item->precio, 2, ',', '.') : '—' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Utilitario</div><div class="fw-semibold">{{ $item->utilitario ? 'Sí' : 'No' }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Reparado en</div><div class="fw-semibold">{{ $item->reparado_en?->format('d/m/Y') ?? '—' }}</div></div>
            <div class="col-md-9"><div class="text-muted small">Detalle reparación</div><div class="fw-semibold">{{ $item->detalle_reparacion ?? '—' }}</div></div>

            @if($item->notas)
            <div class="col-12"><div class="text-muted small">Notas</div><div class="fw-semibold">{{ $item->notas }}</div></div>
            @endif
        </div>

        @if(\Illuminate\Support\Facades\Schema::hasTable('inventario_movimientos'))
        <hr>
        <h2 class="h6" id="mov-rapido">Actualizar ahora</h2>
        <p class="small text-muted">Flujo típico tras escanear: estado → sede → registrar.</p>
        <form method="POST" action="{{ route('inventarios.movimientos.store', $item) }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-3">
                <label class="form-label" for="mov-tipo">Movimiento</label>
                <select id="mov-tipo" name="tipo" class="form-select" required>
                    @foreach(\App\Models\InventarioMovimiento::TIPOS as $k => $lab)
                        <option value="{{ $k }}">{{ $lab }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="mov-estado">Estado</label>
                <select id="mov-estado" name="estado" class="form-select">
                    <option value="">Sin cambio</option>
                    @foreach(\App\Models\InventarioItem::ESTADOS as $k => $lab)
                        <option value="{{ $k }}" @selected($item->estado === $k)>{{ $lab }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="mov-sede">Sede (ubicación)</label>
                <select id="mov-sede" name="sede_id" class="form-select">
                    <option value="">—</option>
                    @foreach($sedes ?? [] as $s)
                        <option value="{{ $s->id }}" @selected($item->sede_id == $s->id)>{{ $s->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="mov-nota">Nota</label>
                <input id="mov-nota" type="text" name="nota" class="form-control" maxlength="400" placeholder="Ej. roto en ensayo">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit">Registrar</button>
            </div>
        </form>
        @if(($item->movimientos ?? collect())->isNotEmpty())
        <ul class="ito-feed">
            @foreach($item->movimientos as $mov)
            <li>
                <strong>{{ $mov->etiquetaTipo() }}</strong>
                · {{ $mov->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                @if($mov->sede) · {{ $mov->sede->nombre }}@endif
                @if($mov->nota) · {{ $mov->nota }}@endif
                <span class="text-muted"> · {{ $mov->autor->name ?? $mov->autor->username ?? 'Sistema' }}</span>
            </li>
            @endforeach
        </ul>
        @else
            <p class="text-muted small mb-0">Todavía no hay movimientos. El alta ya quedó registrada al crear el ítem.</p>
        @endif
        @endif
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

