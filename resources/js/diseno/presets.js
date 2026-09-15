/**
 * Presets de lienzo, plantillas con contenido y assets de marca — ITO Diseño.
 * Fuente única: Blade y el editor consumen esto (no duplicar dims en HTML).
 * Los logos oficiales se inyectan desde el servidor (catalogBrand) y se fusionan aquí.
 */

/** Paleta oficial de marca (impresión / merchandising). */
export const BRAND_COLORS = [
    { hex: '#f26422', name: 'Naranja' },
    { hex: '#000000', name: 'Negro' },
    { hex: '#ffffff', name: 'Blanco' },
    { hex: '#3daf3a', name: 'Verde' },
    { hex: '#3ec8ea', name: 'Celeste' },
    { hex: '#e31b23', name: 'Rojo' },
];

/** Presets de tamaño: redes, print y merchandising (px a 72/150/300 dpi). */
export const CANVAS_PRESETS = [
    { formato: 'flyer_feed', w: 1080, h: 1350, label: 'Flyer feed', group: 'redes', dpiHint: 72, mm: null },
    { formato: 'historia', w: 1080, h: 1920, label: 'Historia / Story', group: 'redes', dpiHint: 72, mm: null },
    { formato: 'post_cuadrado', w: 1080, h: 1080, label: 'Post cuadrado', group: 'redes', dpiHint: 72, mm: null },
    { formato: 'banner_web', w: 1200, h: 628, label: 'Banner web', group: 'web', dpiHint: 72, mm: null },
    { formato: 'afiche_a4', w: 1240, h: 1754, label: 'Afiche A4 (150 dpi)', group: 'print', dpiHint: 150, mm: { w: 210, h: 297 } },
    { formato: 'flyer_a5', w: 874, h: 1240, label: 'Flyer A5 (150 dpi)', group: 'print', dpiHint: 150, mm: { w: 148, h: 210 } },
    { formato: 'hoodie_20x40', w: 2362, h: 4724, label: 'Hoodie 20×40 cm (300 dpi)', group: 'merch', dpiHint: 300, mm: { w: 200, h: 400 } },
    { formato: 'parche_10x10', w: 1181, h: 1181, label: 'Parche 10×10 cm (300 dpi)', group: 'merch', dpiHint: 300, mm: { w: 100, h: 100 } },
    { formato: 'custom', w: 1080, h: 1080, label: 'Personalizado', group: 'custom', dpiHint: 150, mm: null },
];

export function presetPorFormato(formato) {
    return CANVAS_PRESETS.find((p) => p.formato === formato) || CANVAS_PRESETS[0];
}

/** Fallback si Blade no inyecta el catálogo. */
export const BRAND_ASSETS_FALLBACK = [
    {
        id: 'logo-oficial',
        label: 'Logo La Chilinga',
        kind: 'image',
        url: '/images/diseno/brand/logo.png',
        thumb: '/images/diseno/brand/logo.png',
    },
    {
        id: 'logo-30',
        label: 'Logo 30 años',
        kind: 'image',
        url: '/images/diseno/brand/chilinga-30.png',
        thumb: '/images/diseno/brand/chilinga-30.png',
    },
    {
        id: 'wordmark',
        label: 'Wordmark tipográfico',
        kind: 'text-badge',
        text: 'LA CHILINGA',
        fontSize: 48,
        fontWeight: '800',
    },
    {
        id: 'bloque-naranja',
        label: 'Bloque naranja',
        kind: 'shape-rect',
        fill: '#f26422',
    },
    {
        id: 'mockup-hoodie',
        label: 'Mockup hoodie',
        kind: 'mockup',
        url: '/images/diseno/mockups/hoodie.svg',
    },
    {
        id: 'mockup-campera',
        label: 'Mockup campera',
        kind: 'mockup',
        url: '/images/diseno/mockups/campera.svg',
    },
    {
        id: 'mockup-jersey',
        label: 'Mockup jersey',
        kind: 'mockup',
        url: '/images/diseno/mockups/jersey.svg',
    },
    {
        id: 'mockup-tote',
        label: 'Mockup tote',
        kind: 'mockup',
        url: '/images/diseno/mockups/tote.svg',
    },
];

/** @deprecated usar resolveBrandAssets() */
export const BRAND_ASSETS = BRAND_ASSETS_FALLBACK;

export function resolveBrandAssets(fromServer) {
    if (Array.isArray(fromServer) && fromServer.length) return fromServer;
    return BRAND_ASSETS_FALLBACK;
}

export const MOCKUP_HOODIE_URL = '/images/diseno/mockups/hoodie.svg';

/**
 * Plantillas con contenido (JSON parcial Fabric).
 */
export function contenidoPlantilla(id, w, h) {
    const accent = '#f26422';
    const ink = '#111111';
    const paper = '#ffffff';
    const common = { version: '6.0.0', background: paper };

    if (id === 'show-flyer') {
        return {
            ...common,
            objects: [
                {
                    type: 'rect', left: 0, top: 0, width: w, height: h * 0.38,
                    fill: accent, selectable: true, name: 'Banner superior',
                },
                {
                    type: 'textbox', left: w * 0.08, top: h * 0.1, width: w * 0.84,
                    text: 'LA CHILINGA', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.07),
                    fontWeight: '800', fill: '#ffffff', name: 'Marca',
                },
                {
                    type: 'textbox', left: w * 0.08, top: h * 0.45, width: w * 0.84,
                    text: 'Toque en vivo', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.09),
                    fontWeight: '700', fill: ink, name: 'Título',
                },
                {
                    type: 'textbox', left: w * 0.08, top: h * 0.62, width: w * 0.84,
                    text: 'Fecha · Sede · Entrada', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.035),
                    fontWeight: '500', fill: '#555555', name: 'Detalle',
                },
                {
                    type: 'rect', left: w * 0.08, top: h * 0.78, width: w * 0.4, height: h * 0.08,
                    fill: accent, rx: 8, ry: 8, name: 'CTA fondo',
                },
                {
                    type: 'textbox', left: w * 0.1, top: h * 0.795, width: w * 0.36,
                    text: 'Reservá', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.04),
                    fontWeight: '700', fill: '#ffffff', textAlign: 'center', name: 'CTA',
                },
            ],
        };
    }

    if (id === 'story-promo') {
        return {
            ...common,
            objects: [
                {
                    type: 'rect', left: 0, top: 0, width: w, height: h, fill: ink, name: 'Fondo',
                },
                {
                    type: 'circle', left: w * 0.15, top: h * 0.18, radius: w * 0.35,
                    fill: accent, opacity: 0.9, name: 'Acento',
                },
                {
                    type: 'textbox', left: w * 0.08, top: h * 0.55, width: w * 0.84,
                    text: 'NUEVA\nCLASE', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.12),
                    fontWeight: '800', fill: '#ffffff', lineHeight: 0.95, name: 'Headline',
                },
                {
                    type: 'textbox', left: w * 0.08, top: h * 0.82, width: w * 0.84,
                    text: 'Inscripciones abiertas', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.045),
                    fontWeight: '500', fill: accent, name: 'Sub',
                },
            ],
        };
    }

    if (id === 'merch-simple') {
        return {
            ...common,
            objects: [
                {
                    type: 'textbox', left: w * 0.1, top: h * 0.35, width: w * 0.8,
                    text: 'LA CHILINGA', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.08),
                    fontWeight: '800', fill: ink, textAlign: 'center', name: 'Logo texto',
                },
                {
                    type: 'rect', left: w * 0.2, top: h * 0.52, width: w * 0.6, height: Math.max(8, h * 0.012),
                    fill: accent, name: 'Barra',
                },
                {
                    type: 'textbox', left: w * 0.1, top: h * 0.58, width: w * 0.8,
                    text: '30 años', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.045),
                    fontWeight: '600', fill: '#666666', textAlign: 'center', name: 'Tagline',
                },
            ],
        };
    }

    if (id === 'clase-abierta') {
        return {
            ...common,
            objects: [
                {
                    type: 'rect', left: 0, top: 0, width: w, height: h, fill: '#0a0a0a', name: 'Fondo',
                },
                {
                    type: 'rect', left: 0, top: 0, width: w * 0.08, height: h, fill: accent, name: 'Barra lateral',
                },
                {
                    type: 'textbox', left: w * 0.14, top: h * 0.12, width: w * 0.78,
                    text: 'CLASE ABIERTA', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.055),
                    fontWeight: '800', fill: accent, name: 'Eyebrow',
                },
                {
                    type: 'textbox', left: w * 0.14, top: h * 0.22, width: w * 0.78,
                    text: 'Percusión\nbrasileña', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.1),
                    fontWeight: '800', fill: '#ffffff', lineHeight: 0.95, name: 'Título',
                },
                {
                    type: 'textbox', left: w * 0.14, top: h * 0.55, width: w * 0.78,
                    text: 'Sábado 18 hs · Todas las sedes\nSin experiencia previa', fontFamily: 'Manrope, sans-serif',
                    fontSize: Math.round(w * 0.035), fontWeight: '500', fill: '#c8c8c8', name: 'Info',
                },
                {
                    type: 'rect', left: w * 0.14, top: h * 0.78, width: w * 0.5, height: h * 0.09,
                    fill: accent, rx: 10, ry: 10, name: 'CTA fondo',
                },
                {
                    type: 'textbox', left: w * 0.14, top: h * 0.795, width: w * 0.5,
                    text: 'Anotate', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.04),
                    fontWeight: '700', fill: '#ffffff', textAlign: 'center', name: 'CTA',
                },
            ],
        };
    }

    if (id === 'redes-quote') {
        return {
            ...common,
            objects: [
                {
                    type: 'rect', left: 0, top: 0, width: w, height: h, fill: accent, name: 'Fondo',
                },
                {
                    type: 'textbox', left: w * 0.1, top: h * 0.28, width: w * 0.8,
                    text: '“El ritmo\nnos une”', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.1),
                    fontWeight: '800', fill: '#ffffff', textAlign: 'center', lineHeight: 1.05, name: 'Quote',
                },
                {
                    type: 'textbox', left: w * 0.1, top: h * 0.72, width: w * 0.8,
                    text: 'LA CHILINGA', fontFamily: 'Manrope, sans-serif', fontSize: Math.round(w * 0.045),
                    fontWeight: '700', fill: '#111111', textAlign: 'center', name: 'Firma',
                },
            ],
        };
    }

    return { ...common, objects: [] };
}

export const CONTENT_TEMPLATES = [
    { id: 'show-flyer', label: 'Flyer de show', formatoPreferido: 'flyer_feed' },
    { id: 'story-promo', label: 'Historia promo', formatoPreferido: 'historia' },
    { id: 'clase-abierta', label: 'Clase abierta', formatoPreferido: 'flyer_feed' },
    { id: 'redes-quote', label: 'Cita para redes', formatoPreferido: 'post_cuadrado' },
    { id: 'merch-simple', label: 'Estampa simple', formatoPreferido: 'hoodie_20x40' },
];

/** Fuentes disponibles en el editor de texto (cargadas vía document.fonts). */
export const FONT_FAMILIES = [
    { id: 'Manrope, sans-serif', label: 'Manrope', google: 'Manrope:wght@400;500;600;700;800', weights: ['400', '500', '600', '700', '800'] },
    { id: '"Space Grotesk", sans-serif', label: 'Space Grotesk', google: 'Space+Grotesk:wght@400;500;600;700', weights: ['400', '500', '600', '700'] },
    { id: '"Bebas Neue", sans-serif', label: 'Bebas Neue', google: 'Bebas+Neue', weights: ['400'] },
    { id: '"Oswald", sans-serif', label: 'Oswald', google: 'Oswald:wght@400;500;600;700', weights: ['400', '500', '600', '700'] },
    { id: '"Playfair Display", serif', label: 'Playfair Display', google: 'Playfair+Display:wght@400;600;700;800', weights: ['400', '600', '700', '800'] },
    { id: '"Source Sans 3", sans-serif', label: 'Source Sans 3', google: 'Source+Sans+3:wght@400;600;700', weights: ['400', '600', '700'] },
    { id: '"IBM Plex Mono", monospace', label: 'IBM Plex Mono', google: 'IBM+Plex+Mono:wght@400;500;600', weights: ['400', '500', '600'] },
    { id: 'Georgia, serif', label: 'Georgia', google: null, weights: ['400', '700'] },
    { id: 'Arial, sans-serif', label: 'Arial', google: null, weights: ['400', '700'] },
    { id: '"Courier New", monospace', label: 'Courier', google: null, weights: ['400', '700'] },
];

export function weightsForFont(fontId) {
    const f = FONT_FAMILIES.find((x) => x.id === fontId);
    return f?.weights || ['400', '500', '600', '700', '800'];
}

export function ensureEditorFonts() {
    const needed = FONT_FAMILIES.filter((f) => f.google).map((f) => f.google);
    if (!needed.length) return Promise.resolve();
    const id = 'diseno-google-fonts';
    if (!document.getElementById(id)) {
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?${needed.map((f) => `family=${f}`).join('&')}&display=swap`;
        document.head.appendChild(link);
    }
    if (!document.fonts?.load) return Promise.resolve();
    return Promise.all([
        document.fonts.load('700 48px Manrope'),
        document.fonts.load('600 32px "Space Grotesk"'),
        document.fonts.load('400 48px "Bebas Neue"'),
        document.fonts.load('700 40px Oswald'),
        document.fonts.load('700 40px "Playfair Display"'),
        document.fonts.load('600 32px "Source Sans 3"'),
        document.fonts.load('500 28px "IBM Plex Mono"'),
    ]).catch(() => {});
}

/**
 * px de export a partir de mm y DPI.
 */
export function pixelesParaDpi(mm, dpi, fallbackPx) {
    if (mm?.w && mm?.h) {
        return {
            w: Math.round((mm.w / 25.4) * dpi),
            h: Math.round((mm.h / 25.4) * dpi),
        };
    }
    const base = fallbackPx.dpiHint || 72;
    const scale = dpi / base;
    return {
        w: Math.round(fallbackPx.w * scale),
        h: Math.round(fallbackPx.h * scale),
    };
}
