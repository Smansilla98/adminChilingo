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

    <form method="post" action="{{ route('apariencia.update') }}" id="aparienciaForm" class="apariencia-grid">
        @csrf

        <div class="apariencia-controls">
            <section class="ito-form-section mb-3">
                    <h2 class="h6 mb-1">Tema</h2>
                    <p class="text-muted small mb-3">Claro para el día a día, oscuro para trabajar de noche, o que siga la configuración de tu dispositivo.</p>
                    <div class="apariencia-temas" role="radiogroup" aria-label="Tema de color">
                        @foreach($temas as $clave => $info)
                            <label class="apariencia-swatch apariencia-tema {{ $tema['tema'] === $clave ? 'is-active' : '' }}">
                                <input type="radio" name="tema" value="{{ $clave }}" @checked($tema['tema'] === $clave) data-apariencia-tema>
                                <i class="bi {{ $info['icon'] }}" aria-hidden="true"></i>
                                <span class="apariencia-swatch-label">{{ $info['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
            </section>

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
                <button type="submit" class="btn btn-outline-secondary" form="aparienciaResetForm">
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

    <form id="aparienciaResetForm" method="post" action="{{ route('apariencia.reset') }}" class="d-none"
          data-confirm="¿Restablecer la apariencia? Vuelven el tema claro, el naranja de La Chilinga y la tipografía Manrope." data-confirm-ok="Restablecer">
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

    function rgb(hex) {
        hex = hex.replace('#', '');
        return [0, 2, 4].map(i => parseInt(hex.slice(i, i + 2), 16));
    }
    function toHex(c) {
        return '#' + c.map(n => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0')).join('');
    }
    function lum(hex) {
        return rgb(hex).map(v => v / 255).map(v => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4))
            .reduce((acc, v, i) => acc + v * [0.2126, 0.7152, 0.0722][i], 0);
    }
    function contrast(a, b) {
        const la = lum(a), lb = lum(b);
        return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
    }
    // Igual que AparienciaTema::ajustarContraste: oscurece (claro) o aclara (oscuro) hasta 4.5:1.
    function ajustar(hex, fondo, minimo, dir) {
        let c = hex;
        for (let i = 0; i < 40 && contrast(c, fondo) < minimo; i++) {
            c = dir < 0 ? toHex(rgb(c).map(v => v * 0.94)) : toHex(rgb(c).map(v => v + (255 - v) * 0.08));
        }
        return c;
    }
    function soft(hex, a) {
        const [r, g, b] = rgb(hex);
        return `rgba(${r}, ${g}, ${b}, ${a})`;
    }

    function applyAccent(hex) {
        const h = sanitizeHex(hex);
        if (!h) return;
        const oscuro = root.getAttribute('data-bs-theme') === 'dark';
        const primary = oscuro ? ajustar(h, '#111111', 4.5, 1) : ajustar(h, '#ffffff', 4.5, -1);
        const strong = oscuro ? ajustar(h, '#171a20', 4.5, 1) : ajustar(h, '#ffffff', 4.8, -1);
        root.style.setProperty('--accent', h);
        root.style.setProperty('--accent-soft', soft(h, oscuro ? 0.14 : 0.10));
        root.style.setProperty('--accent-soft-2', soft(h, oscuro ? 0.24 : 0.18));
        root.style.setProperty('--accent-strong', strong);
        root.style.setProperty('--primary', primary);
        root.style.setProperty('--primary-hover', oscuro ? toHex(rgb(primary).map(v => v + (255 - v) * 0.12)) : toHex(rgb(primary).map(v => v * 0.85)));
        root.style.setProperty('--primary-on', oscuro ? '#111111' : '#ffffff');
        if (picker) picker.value = h;
        if (accentInput && accentInput.value.toLowerCase() !== h) accentInput.value = h;

        presets.forEach(inp => {
            const lab = inp.closest('.apariencia-swatch');
            if (lab) lab.classList.toggle('is-active', inp.value.toLowerCase() === h);
        });
    }

    function applyTema() {
        const t = form.querySelector('[data-apariencia-tema]:checked');
        if (!t) return;
        const oscuro = t.value === 'oscuro' || (t.value === 'sistema' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        root.setAttribute('data-bs-theme', oscuro ? 'dark' : 'light');
        form.querySelectorAll('[data-apariencia-tema]').forEach(inp => {
            inp.closest('.apariencia-tema')?.classList.toggle('is-active', inp.checked);
        });
        applyAccent(accentInput?.value || '{{ $accentActual }}');
    }

    function applyFonts() {
        const d = form.querySelector('[data-apariencia-font-display]:checked');
        const b = form.querySelector('[data-apariencia-font-body]:checked');
        if (d) {
            root.style.setProperty('--font-display', `'${d.value}', 'Manrope', system-ui, sans-serif`);
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
    form.querySelectorAll('[data-apariencia-tema]').forEach(r => r.addEventListener('change', applyTema));
    bodyRadios.forEach(r => r.addEventListener('change', applyFonts));

    // Aplica preview inmediata al cargar (por si hay overrides previos)
    applyAccent(accentInput?.value || '{{ $accentActual }}');
    applyFonts();
})();
</script>
@endpush
