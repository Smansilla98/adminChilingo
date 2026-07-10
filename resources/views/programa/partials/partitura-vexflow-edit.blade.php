@php
    use App\Support\ProgramaRitmoMedios;
    $vex = old('partitura_vexflow_json')
        ? json_decode(old('partitura_vexflow_json'), true)
        : ($medios['partitura_vexflow'] ?? null);
    $vexJson = $vex ? json_encode($vex, JSON_UNESCAPED_UNICODE) : '';
@endphp

<div class="border-0">
    <div class="p-3 border-bottom bg-light-subtle">
        <p class="small text-muted mb-2">
            <strong>Opcional:</strong> armá una partitura nueva desde cero en la rejilla.
            Para el libro escaneado usá la carga de PDF/imagen de arriba.
        </p>
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="quitar_partitura_vexflow" value="1" id="quitar_partitura_vexflow" data-partitura-remove {{ old('quitar_partitura_vexflow') ? 'checked' : '' }}>
                <label class="form-check-label" for="quitar_partitura_vexflow">Quitar partitura digital al guardar</label>
            </div>
        </div>

        <div class="programa-partitura-editor p-3" data-partitura-editor>
            <script type="application/json" data-partitura-initial>@json($vex)</script>
            <input type="hidden" name="partitura_vexflow_json" value="{{ old('partitura_vexflow_json', $vexJson) }}" data-partitura-input>

            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <label class="small mb-0">
                    Compases:
                    <select class="form-select form-select-sm d-inline-block w-auto ms-1" data-partitura-measures>
                        @foreach([1, 2, 3, 4] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-partitura-demo">Ejemplo</button>
                <button type="button" class="btn btn-sm btn-outline-danger" data-partitura-clear">Limpiar rejilla</button>
            </div>

            <p class="small text-muted mb-2">Tambores: @foreach(ProgramaRitmoMedios::VIDEOS_BASE as $label){{ $label }}@if(!$loop->last), @endif @endforeach.</p>

            <div class="programa-partitura-repeats-wrap mb-2" data-partitura-repeats></div>

            <div class="programa-partitura-legend" aria-hidden="true">
                <span><i class="lg-on"></i> I / D activo</span>
                <span><i class="lg-d"></i> Mano derecha</span>
                <span><i class="lg-accent"></i> Acento abierto</span>
                <span>Clic: I → D → I acento → D acento → off</span>
            </div>

            <div class="programa-partitura-grid-wrap mb-3" data-partitura-grid></div>

            <div class="small fw-semibold mb-1">Vista previa</div>
            <div data-partitura-preview class="programa-partitura-preview"></div>
        </div>
</div>
