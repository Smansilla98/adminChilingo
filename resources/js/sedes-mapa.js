/**
 * Mapas de sedes (Leaflet + OpenStreetMap).
 *
 * - [data-mapa-sedes]: mapa público. Lee las sedes de data-sedes (JSON) y sincroniza
 *   la lista: [data-sede-foco="id"] centra el mapa y abre la ficha.
 * - [data-mapa-picker]: ficha de la sede (administración). Un clic en el mapa fija la
 *   ubicación en los campos latitud/longitud; "Buscar dirección" la ubica con OSM.
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/sedes-mapa.css';

const CENTRO_AMBA = [-34.64, -58.45];
const TESELAS = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATRIBUCION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';

function icono(etiqueta = '') {
    return L.divIcon({
        className: 'sm-pin',
        html: `<span class="sm-pin__punto" aria-hidden="true"></span>${etiqueta ? `<span class="sm-pin__nombre">${esc(etiqueta)}</span>` : ''}`,
        iconSize: [22, 22],
        iconAnchor: [11, 11],
        popupAnchor: [0, -12],
    });
}

function mapaBase(el, centro = CENTRO_AMBA, zoom = 10) {
    const mapa = L.map(el, { scrollWheelZoom: false, zoomControl: true }).setView(centro, zoom);
    L.tileLayer(TESELAS, { maxZoom: 19, attribution: ATRIBUCION }).addTo(mapa);
    // La rueda hace zoom recién después de hacer clic en el mapa (no secuestra el scroll de la página).
    mapa.once('focus', () => mapa.scrollWheelZoom.enable());
    mapa.on('click', () => mapa.scrollWheelZoom.enable());
    return mapa;
}

function iniciarMapaSedes(el) {
    let sedes = [];
    try { sedes = JSON.parse(el.dataset.sedes || '[]'); } catch { sedes = []; }
    const conPunto = sedes.filter((s) => Number.isFinite(s.lat) && Number.isFinite(s.lng));
    const mapa = mapaBase(el);
    const marcadores = new Map();

    conPunto.forEach((s) => {
        const m = L.marker([s.lat, s.lng], { icon: icono(el.dataset.etiquetas === '1' ? s.nombre : ''), title: s.nombre, alt: s.nombre, keyboard: true })
            .addTo(mapa)
            .bindPopup(`<strong>${esc(s.nombre)}</strong>${s.direccion ? `<br>${esc(s.direccion)}` : ''}${s.como_llegar ? `<br><a href="${esc(s.como_llegar)}" target="_blank" rel="noopener">Cómo llegar</a>` : ''}`);
        m.on('click', () => marcarEnLista(s.id));
        marcadores.set(String(s.id), m);
    });

    if (conPunto.length > 1) {
        mapa.fitBounds(L.latLngBounds(conPunto.map((s) => [s.lat, s.lng])), { padding: [36, 36], maxZoom: 13 });
    } else if (conPunto.length === 1) {
        mapa.setView([conPunto[0].lat, conPunto[0].lng], 14);
    }

    function marcarEnLista(id) {
        document.querySelectorAll('[data-sede-foco]').forEach((b) => b.closest('[data-sede-item]')?.classList.toggle('is-activa', b.dataset.sedeFoco === String(id)));
    }

    document.querySelectorAll('[data-sede-foco]').forEach((b) => {
        const m = marcadores.get(b.dataset.sedeFoco);
        if (!m) { b.disabled = true; return; }
        b.addEventListener('click', () => {
            mapa.flyTo(m.getLatLng(), 15, { duration: 0.6 });
            m.openPopup();
            marcarEnLista(b.dataset.sedeFoco);
            if (window.matchMedia('(max-width: 767.98px)').matches) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    // Si el mapa nace oculto o cambia de tamaño (pestañas, sidebar), recalcula.
    new ResizeObserver(() => mapa.invalidateSize()).observe(el);
}

function iniciarPicker(el) {
    const form = el.closest('form') || document;
    const lat = form.querySelector('[name="latitud"]');
    const lng = form.querySelector('[name="longitud"]');
    const dir = form.querySelector('[name="direccion"]');
    const aviso = el.parentElement.querySelector('[data-mapa-aviso]');
    const inicial = lat.value && lng.value ? [Number(lat.value), Number(lng.value)] : null;
    const mapa = mapaBase(el, inicial || CENTRO_AMBA, inicial ? 16 : 10);
    let marca = inicial ? L.marker(inicial, { icon: icono(), draggable: true }).addTo(mapa) : null;

    const fijar = (latlng, centrar = false) => {
        lat.value = latlng.lat.toFixed(7);
        lng.value = latlng.lng.toFixed(7);
        if (!marca) {
            marca = L.marker(latlng, { icon: icono(), draggable: true }).addTo(mapa);
            marca.on('dragend', () => fijar(marca.getLatLng()));
        } else {
            marca.setLatLng(latlng);
        }
        if (centrar) mapa.setView(latlng, 17);
    };
    marca?.on('dragend', () => fijar(marca.getLatLng()));
    mapa.on('click', (e) => fijar(e.latlng));

    const sincronizar = () => {
        const a = Number(lat.value);
        const b = Number(lng.value);
        if (lat.value !== '' && lng.value !== '' && Number.isFinite(a) && Number.isFinite(b)) fijar(L.latLng(a, b), true);
    };
    lat.addEventListener('change', sincronizar);
    lng.addEventListener('change', sincronizar);

    form.querySelector('[data-mapa-limpiar]')?.addEventListener('click', () => {
        lat.value = '';
        lng.value = '';
        marca?.remove();
        marca = null;
    });

    form.querySelector('[data-mapa-buscar]')?.addEventListener('click', async (e) => {
        const btn = e.currentTarget;
        const q = (dir?.value || '').trim();
        if (!q) { decir('Escribí la dirección primero.'); return; }
        btn.disabled = true;
        decir('Buscando…');
        try {
            const url = `https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&countrycodes=ar&q=${encodeURIComponent(q)}`;
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            const [r] = res.ok ? await res.json() : [];
            if (!r) { decir('No la encontramos. Hacé clic en el mapa para ubicarla.'); return; }
            fijar(L.latLng(Number(r.lat), Number(r.lon)), true);
            decir(r.addresstype === 'road'
                ? 'Encontramos la calle pero no la altura: mové el punto hasta la puerta.'
                : 'Ubicada. Si no es exacta, mové el punto.');
        } catch {
            decir('No se pudo buscar. Hacé clic en el mapa para ubicarla.');
        } finally {
            btn.disabled = false;
        }
    });

    function decir(texto) { if (aviso) aviso.textContent = texto; }
    new ResizeObserver(() => mapa.invalidateSize()).observe(el);
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

document.querySelectorAll('[data-mapa-sedes]').forEach(iniciarMapaSedes);
document.querySelectorAll('[data-mapa-picker]').forEach(iniciarPicker);
