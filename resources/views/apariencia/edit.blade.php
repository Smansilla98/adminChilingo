@extends('layouts.app')

@section('title', 'Apariencia')
@section('page-title', 'Apariencia')

@push('styles')
<link rel="stylesheet" href="{{ \App\Support\AparienciaTema::googleFontsUrlCompleta() }}">
@endpush

@section('content')
@php
    $accentActual = $tema['accent'];
    $esCurado = collect($acentos)->contains(fn ($a) => strtolower($a['hex']) === strtolower($accentActual));
@endphp

<x-ito.shell-page
    title="Apariencia"
    eyebrow="Preferencias"
    subtitle="Colores y tipografías de tu sesión. El resto del equipo no cambia."
    :flush="true"
>
<div class="apariencia-page p-3 p-md-4">

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('apariencia.update') }}" id="aparienciaForm" class="apariencia-grid">
        @csrf

        <div class="apariencia-controls">
            <section class="ito-form-section mb-3">
                    <h2 class="h6 mb-1">Color de acento</h2>
                    <p class="text-muted small mb-3">Botones, enlaces activos y detalles. Elegí de la paleta o un hex propio.</p>

                    <div class="apariencia-swatches" role="listbox" aria-label="Paleta de acentos">
                        @foreach($acentos as $key => $acento)
                            @php $checked = strtolower($acento['hex']) === strtolower($accentActual); @endphp
                            <label class="apariencia-swatch {{ $checked ? 'is-active' : '' }}" title="{{ $acento['label'] }}">
                                <input type="radio" name="accent_preset" value="{{ $acento['hex'] }}"
                                       @checked($checked) data-apariencia-accent-preset>
                                <span class="apariencia-swatch-chip" style="--swatch: {{ $acento['hex'] }}"></span>
                                <span class="apariencia-swatch-label">{{ $acento['label'] }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-3 row g-2 align-items-end">
                        <div class="col-sm-auto">
                            <label class="form-label mb-1" for="accentHex">Hex personalizado</label>
                            <div class="input-group" style="max-width: 220px;">
                                <span class="input-group-text p-1">
                                    <input type="color" id="accentPicker" value="{{ $accentActual }}"
                                           aria-label="Selector de color" style="width:40px;height:34px;border:0;background:transparent;padding:0;">
                                </span>
                                <input type="text" class="form-control font-monospace" id="accentHex"
                                       name="accent" value="{{ $accentActual }}" maxlength="7"
                                       pattern="^#[0-9A-Fa-f]{6}$" required autocomplete="off"
                                       data-apariencia-accent>
                            </div>
                        </div>
                        @unless($esCurado)
                            <div class="col-sm-auto">
                                <span class="badge bg-info">Personalizado</span>
                            </div>
                        @endunless
                    </div>
            </section>

            <section class="ito-form-section mb-3">
                    <h2 class="h6 mb-1">Tipografía de títulos</h2>
                    <p class="text-muted small mb-3">Encabezados y nombres de módulo.</p>
                    <div class="apariencia-fonts" role="radiogroup" aria-label="Fuente de títulos">
                        @foreach($fuentesTitulo as $name => $meta)
                            <label class="apariencia-font-opt {{ $tema['font_display'] === $name ? 'is-active' : '' }}">
                                <input type="radio" name="font_display" value="{{ $name }}"
                                       @checked($tema['font_display'] === $name) data-apariencia-font-display>
                                <span class="apariencia-font-name">{{ $name }}</span>
                                <span class="apariencia-font-sample" style="font-family: '{{ $name }}', system-ui, sans-serif; font-weight: 700;">
                                    {{ $meta['sample'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
            </section>

            <section class="ito-form-section mb-3">
                    <h2 class="h6 mb-1">Tipografía de cuerpo</h2>
                    <p class="text-muted small mb-3">Párrafos, tablas y formularios.</p>
                    <div class="apariencia-fonts" role="radiogroup" aria-label="Fuente de cuerpo">
                        @foreach($fuentesCuerpo as $name => $meta)
                            <label class="apariencia-font-opt {{ $tema['font_body'] === $name ? 'is-active' : '' }}">
                                <input type="radio" name="font_body" value="{{ $name }}"
                                       @checked($tema['font_body'] === $name) data-apariencia-font-body>
                                <span class="apariencia-font-name">{{ $name }}</span>
                                <span class="apariencia-font-sample" style="font-family: '{{ $name }}', system-ui, sans-serif; font-weight: 400;">
                                    {{ $meta['sample'] }}
                                </span>
                            </label>
                        @endforeach
                    </div>
            </section>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <button type="submit" class="btn btn-outline-secondary" form="aparienciaResetForm"
                        onclick="return confirm('¿Volver a la apariencia por defecto del sistema?');">
                    Restablecer
                </button>
            </div>
        </div>

        <aside class="apariencia-preview-col">
            <div class="ito-card apariencia-preview-card sticky-lg-top" style="top: 1rem; padding: 16px;" id="aparienciaPreview" data-apariencia-preview>
                    <div class="text-muted small text-uppercase mb-2" style="letter-spacing:.06em;">Vista previa</div>
                    <h3 class="apariencia-preview-title mb-2" data-preview-title>Resumen del mes</h3>
                    <p class="small text-muted mb-3" data-preview-body>Así se ven títulos, texto y el botón principal con tu elección.</p>

                    <div class="apariencia-preview-kpi mb-3">
                        <div class="apariencia-preview-kpi-label">Alumnos activos</div>
                        <div class="apariencia-preview-kpi-value" data-preview-kpi>128</div>
                        <div class="apariencia-preview-kpi-meta text-muted small">+6 este mes</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary" data-preview-btn disabled tabindex="-1">Guardar cambios</button>
                        <button type="button" class="btn btn-outline-secondary" disabled tabindex="-1">Cancelar</button>
                    </div>
                    <a href="#" class="d-inline-block mt-3 small" data-preview-link onclick="return false;">Ver detalle →</a>
            </div>
        </aside>
    </form>

    <form id="aparienciaResetForm" method="post" action="{{ route('apariencia.reset') }}" class="d-none">
        @csrf
    </form>
</div>
</x-ito.shell-page>

@endsection

@push('scripts')
<script>
(function () {
    const root = document.documentElement;
    const form = document.getElementById('aparienciaForm');
    if (!form) return;

    const accentInput = form.querySelector('[data-apariencia-accent]');
    const picker = document.getElementById('accentPicker');
    const presets = form.querySelectorAll('[data-apariencia-accent-preset]');
    const displayRadios = form.querySelectorAll('[data-apariencia-font-display]');
    const bodyRadios = form.querySelectorAll('[data-apariencia-font-body]');

    function sanitizeHex(v) {
        let h = (v || '').trim();
        if (!h) return null;
        if (h[0] !== '#') h = '#' + h;
        if (!/^#[0-9A-Fa-f]{6}$/.test(h)) return null;
        return h.toLowerCase();
    }

    function darken(hex, factor) {
        hex = hex.replace('#', '');
        const r = Math.round(parseInt(hex.slice(0, 2), 16) * factor);
        const g = Math.round(parseInt(hex.slice(2, 4), 16) * factor);
        const b = Math.round(parseInt(hex.slice(4, 6), 16) * factor);
        return '#' + [r, g, b].map(n => Math.min(255, n).toString(16).padStart(2, '0')).join('');
    }

    function soft(hex, a) {
        hex = hex.replace('#', '');
        const r = parseInt(hex.slice(0, 2), 16);
        const g = parseInt(hex.slice(2, 4), 16);
        const b = parseInt(hex.slice(4, 6), 16);
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    }

    function onAccent(hex) {
        hex = hex.replace('#', '');
        const r = parseInt(hex.slice(0, 2), 16) / 255;
        const g = parseInt(hex.slice(2, 4), 16) / 255;
        const b = parseInt(hex.slice(4, 6), 16) / 255;
        const luma = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        return luma > 0.62 ? '#0a0e1a' : '#ffffff';
    }

    function applyAccent(hex) {
        const h = sanitizeHex(hex);
        if (!h) return;
        const hover = darken(h, 0.82);
        const soft16 = soft(h, 0.16);
        const soft15 = soft(h, 0.15);
        const on = onAccent(h);
        root.style.setProperty('--accent', h);
        root.style.setProperty('--accent-hover', hover);
        root.style.setProperty('--accent-soft', soft16);
        root.style.setProperty('--accent-on', on);
        root.style.setProperty('--blue', h);
        root.style.setProperty('--brick', h);
        root.style.setProperty('--brick-soft', soft15);
        root.style.setProperty('--brass', h);
        root.style.setProperty('--brass-soft', soft16);
        root.style.setProperty('--accent2', h);
        if (picker) picker.value = h;
        if (accentInput && accentInput.value.toLowerCase() !== h) accentInput.value = h;

        presets.forEach(inp => {
            const lab = inp.closest('.apariencia-swatch');
            if (lab) lab.classList.toggle('is-active', inp.value.toLowerCase() === h);
        });
    }

    function applyFonts() {
        const d = form.querySelector('[data-apariencia-font-display]:checked');
        const b = form.querySelector('[data-apariencia-font-body]:checked');
        if (d) {
            root.style.setProperty('--font-display', `'${d.value}', 'Inter', system-ui, sans-serif`);
            form.querySelectorAll('[data-apariencia-font-display]').forEach(inp => {
                inp.closest('.apariencia-font-opt')?.classList.toggle('is-active', inp.checked);
            });
        }
        if (b) {
            root.style.setProperty('--font-body', `'${b.value}', system-ui, sans-serif`);
            form.querySelectorAll('[data-apariencia-font-body]').forEach(inp => {
                inp.closest('.apariencia-font-opt')?.classList.toggle('is-active', inp.checked);
            });
        }
    }

    presets.forEach(inp => {
        inp.addEventListener('change', () => {
            if (inp.checked) applyAccent(inp.value);
        });
    });

    if (picker) {
        picker.addEventListener('input', () => applyAccent(picker.value));
    }

    if (accentInput) {
        accentInput.addEventListener('input', () => {
            const h = sanitizeHex(accentInput.value);
            if (h) applyAccent(h);
        });
        accentInput.addEventListener('change', () => {
            const h = sanitizeHex(accentInput.value);
            if (h) {
                accentInput.value = h;
                applyAccent(h);
            }
        });
    }

    displayRadios.forEach(r => r.addEventListener('change', applyFonts));
    bodyRadios.forEach(r => r.addEventListener('change', applyFonts));

    // Aplica preview inmediata al cargar (por si hay overrides previos)
    applyAccent(accentInput?.value || '{{ $accentActual }}');
    applyFonts();
})();
</script>
@endpush
