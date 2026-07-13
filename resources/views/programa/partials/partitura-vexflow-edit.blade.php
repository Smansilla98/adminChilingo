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
            <strong>Rejilla Chilinga</strong> según el <em>Cuadernillo de Toques</em>
            (Nomenclatura pág. 2, Toque de Chilinga pág. 3).
            Para el libro escaneado usá la carga de PDF/imagen.
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
            <button type="button" class="btn btn-sm btn-outline-secondary" data-partitura-add-section>
                <i class="bi bi-plus-lg"></i> Sección
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-partitura-demo>Ejemplo (Toque de Chilinga)</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-partitura-clear>Limpiar sección</button>
        </div>

        <div class="mb-2" data-partitura-sections></div>

        <p class="small text-muted mb-2">
            Instrumentos opcionales (solo en toques que los usen, ej. Iyesá):
        </p>
        <div class="mb-3" data-partitura-optional></div>

        <div class="programa-partitura-legend" aria-hidden="true">
            <span><i class="lg-on"></i> Golpe activo</span>
            <span>× Chapa / dedo</span>
            <span>&gt; Acento (abajo)</span>
            <span>◇ Palma (timbal)</span>
            <span>○ Slap / abierto</span>
            <span>△ Agudo (repique)</span>
            <span>— Tapado / presionado</span>
            <span>Clic cicla: vacío → golpes válidos del instrumento</span>
        </div>

        <div class="programa-partitura-grid-wrap mb-3" data-partitura-grid></div>

        <div class="small fw-semibold mb-1">Vista previa</div>
        <div data-partitura-preview class="programa-partitura-preview"></div>
    </div>
</div>
