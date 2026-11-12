/* Archivo histórico de La Chilinga — interacción pública (sin dependencias). */
(function () {
    'use strict';

    var doc = document;
    var html = doc.documentElement;
    var reducido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var comportamiento = reducido ? 'auto' : 'smooth';

    function $(sel, raiz) { return (raiz || doc).querySelector(sel); }
    function $$(sel, raiz) { return Array.prototype.slice.call((raiz || doc).querySelectorAll(sel)); }

    /* Imágenes: aparecen al cargar (el placeholder borroso queda debajo). */
    $$('.ar-fig__img').forEach(function (img) {
        if (img.complete && img.naturalWidth) { img.classList.add('is-cargada'); return; }
        img.addEventListener('load', function () { img.classList.add('is-cargada'); }, { once: true });
        img.addEventListener('error', function () { img.classList.add('is-cargada'); }, { once: true });
    });

    /* Revelado progresivo. */
    var revelables = $$('[data-revelar]');
    if (reducido || !('IntersectionObserver' in window)) {
        revelables.forEach(function (el) { el.classList.add('is-visible'); });
    } else {
        var io = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (e) {
                if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); }
            });
        }, { rootMargin: '0px 0px -8% 0px' });
        revelables.forEach(function (el) { io.observe(el); });
    }

    /* Cabecera sólida y barra de progreso de lectura. */
    var top = $('[data-top]');
    var progreso = $('[data-progreso]');
    var barraDecadas = $('[data-decadas-movil]');
    var relato = $('#relato') || $('.ar-relato');
    var pendiente = false;
    function alScroll() {
        pendiente = false;
        var y = window.scrollY;
        if (top) top.classList.toggle('is-solido', y > 40);
        var total = doc.documentElement.scrollHeight - window.innerHeight;
        if (progreso) progreso.style.transform = 'scaleX(' + (total > 0 ? Math.min(1, y / total) : 0) + ')';
        if (barraDecadas && relato) {
            var r = relato.getBoundingClientRect();
            barraDecadas.classList.toggle('is-visible', r.top < window.innerHeight * 0.6 && r.bottom > window.innerHeight * 0.4);
        }
    }
    window.addEventListener('scroll', function () {
        if (!pendiente) { pendiente = true; window.requestAnimationFrame(alScroll); }
    }, { passive: true });
    alScroll();

    /* Línea de tiempo: el año que se está leyendo. */
    var actual = $('[data-anio-actual]');
    var riel = $('[data-riel]');
    var listaMovil = barraDecadas ? $('.ar-decadas-movil__lista', barraDecadas) : null;
    var marcadores = $$('[data-anio]').filter(function (el) { return el.getAttribute('data-anio'); });
    var decadaActiva = null;

    function marcarAnio(anio) {
        if (!anio) return;
        var decada = String(Math.floor(anio / 10) * 10);
        if (actual) actual.textContent = anio;
        if (riel) {
            $$('.ar-riel__decadas > li', riel).forEach(function (li) {
                var a = $('[data-ir-decada]', li);
                li.classList.toggle('is-activa', a && a.getAttribute('data-ir-decada') === decada);
            });
            $$('[data-ir-anio]', riel).forEach(function (a) {
                a.classList.toggle('is-activo', a.getAttribute('data-ir-anio') === String(anio));
            });
        }
        if (listaMovil && decada !== decadaActiva) {
            var destino = $('[data-ir-decada="' + decada + '"]', listaMovil);
            if (destino) listaMovil.scrollTo({ left: destino.offsetLeft - listaMovil.offsetLeft, behavior: comportamiento });
        }
        decadaActiva = decada;
    }

    if (marcadores.length && 'IntersectionObserver' in window) {
        var ioAnio = new IntersectionObserver(function (entradas) {
            entradas.forEach(function (e) {
                if (e.isIntersecting) marcarAnio(parseInt(e.target.getAttribute('data-anio'), 10));
            });
        }, { rootMargin: '-40% 0px -55% 0px' });
        marcadores.forEach(function (el) { ioAnio.observe(el); });
        marcarAnio(parseInt(marcadores[0].getAttribute('data-anio'), 10));
    }

    /* Saltos por década / año: dentro de la página si el material está, si no navega. */
    function irA(selector, evento) {
        var destino = $(selector);
        if (!destino) return false;
        evento.preventDefault();
        destino.scrollIntoView({ behavior: comportamiento, block: 'start' });
        return true;
    }
    doc.addEventListener('click', function (e) {
        var d = e.target.closest('[data-ir-decada]');
        if (d) { irA('[data-decada="' + d.getAttribute('data-ir-decada') + '"]', e); return; }
        var a = e.target.closest('[data-ir-anio]');
        if (a) { irA('.ar-relato [data-anio="' + a.getAttribute('data-ir-anio') + '"]', e); }
    });
    $$('[data-decada-paso]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var enlaces = listaMovil ? $$('[data-ir-decada]', listaMovil) : [];
            if (!enlaces.length) return;
            var i = enlaces.findIndex(function (x) { return x.getAttribute('data-ir-decada') === decadaActiva; });
            var j = Math.max(0, Math.min(enlaces.length - 1, (i < 0 ? 0 : i) + parseInt(btn.getAttribute('data-decada-paso'), 10)));
            enlaces[j].click();
        });
    });

    /* ── Visor ─────────────────────────────────────────────────────────────── */
    var raiz = $('[data-visor-raiz]');
    var datosEl = $('#archivo-datos');
    var fotos = {};
    try {
        (datosEl ? JSON.parse(datosEl.textContent || '[]') : []).forEach(function (f) { fotos[f.id] = f; });
    } catch (err) { fotos = {}; }

    if (raiz) {
        var img = $('[data-visor-img]', raiz);
        var escena = $('[data-visor-escena]', raiz);
        var orden = [];
        var indice = 0;
        var origen = null;
        var abierto = false;
        var z = { s: 1, x: 0, y: 0 };

        function idsDePagina() {
            var vistos = {};
            return $$('[data-visor]').map(function (b) { return b.getAttribute('data-visor'); })
                .filter(function (id) { if (vistos[id] || !fotos[id]) return false; vistos[id] = true; return true; });
        }
        function texto(sel, valor) { var el = $(sel, raiz); el.textContent = valor || ''; el.hidden = !valor; }
        function aplicarZoom() {
            img.style.transform = z.s === 1 ? '' : 'translate(' + z.x + 'px,' + z.y + 'px) scale(' + z.s + ')';
            raiz.classList.toggle('is-zoom', z.s > 1);
        }
        function resetZoom() { z = { s: 1, x: 0, y: 0 }; aplicarZoom(); }

        function mostrar(i) {
            if (!orden.length) return;
            indice = (i + orden.length) % orden.length;
            var f = fotos[orden[indice]];
            resetZoom();
            img.classList.add('is-cargando');
            img.onload = function () { img.classList.remove('is-cargando'); };
            img.alt = f.alt || f.titulo || '';
            // Solo ahora se pide la versión grande; el navegador elige del srcset.
            img.sizes = '100vw';
            img.srcset = f.imagen.srcset || '';
            img.src = f.imagen.completa;
            texto('[data-visor-anio]', f.fecha || (f.anio ? String(f.anio) : ''));
            texto('[data-visor-titulo]', f.titulo);
            texto('[data-visor-desc]', f.descripcion);
            var meta = [];
            if (f.acontecimiento) meta.push(f.acontecimiento.titulo);
            if (f.sede) meta.push(f.sede);
            texto('[data-visor-meta]', meta.join(' · '));
            var enlace = $('[data-visor-enlace]', raiz);
            enlace.hidden = !f.url;
            if (f.url) enlace.href = f.url;
            $('[data-visor-contador]', raiz).textContent = (indice + 1) + ' / ' + orden.length;
            var sig = fotos[orden[(indice + 1) % orden.length]];
            if (sig && orden.length > 1) { var pre = new Image(); pre.sizes = '100vw'; pre.srcset = sig.imagen.srcset || ''; pre.src = sig.imagen.completa; }
        }

        function abrir(id, desde) {
            orden = idsDePagina();
            var i = orden.indexOf(String(id));
            if (i < 0) return false;
            origen = desde || doc.activeElement;
            raiz.hidden = false;
            abierto = true;
            doc.body.classList.add('ar-sin-scroll');
            mostrar(i);
            $('[data-visor-cerrar]', raiz).focus();
            try { history.pushState({ arVisor: true }, ''); } catch (err) { /* sin historial */ }
            return true;
        }
        function cerrar(desdeHistorial) {
            if (!abierto) return;
            abierto = false;
            raiz.hidden = true;
            raiz.classList.remove('is-info');
            doc.body.classList.remove('ar-sin-scroll');
            img.removeAttribute('src'); img.removeAttribute('srcset');
            if (origen && origen.focus) origen.focus();
            if (!desdeHistorial && history.state && history.state.arVisor) history.back();
        }
        window.addEventListener('popstate', function () { if (abierto) cerrar(true); });

        doc.addEventListener('click', function (e) {
            var b = e.target.closest('[data-visor]');
            if (b && abrir(b.getAttribute('data-visor'), b)) e.preventDefault();
        });
        $('[data-visor-cerrar]', raiz).addEventListener('click', function () { cerrar(false); });
        $$('[data-visor-paso]', raiz).forEach(function (b) {
            b.addEventListener('click', function () { mostrar(indice + parseInt(b.getAttribute('data-visor-paso'), 10)); });
        });
        var botonInfo = $('[data-visor-info]', raiz);
        botonInfo.addEventListener('click', function () {
            var on = raiz.classList.toggle('is-info');
            botonInfo.setAttribute('aria-expanded', on ? 'true' : 'false');
        });

        doc.addEventListener('keydown', function (e) {
            if (!abierto) return;
            if (e.key === 'Escape') { e.preventDefault(); if (z.s > 1) resetZoom(); else cerrar(false); }
            else if (e.key === 'ArrowRight') { e.preventDefault(); mostrar(indice + 1); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); mostrar(indice - 1); }
            else if (e.key === 'i') { botonInfo.click(); }
            else if (e.key === 'Tab') {
                // Foco atrapado dentro del visor.
                var focos = $$('button:not([hidden]), a[href]:not([hidden])', raiz).filter(function (el) { return el.offsetParent !== null; });
                if (!focos.length) return;
                var primero = focos[0], ultimo = focos[focos.length - 1];
                if (e.shiftKey && doc.activeElement === primero) { e.preventDefault(); ultimo.focus(); }
                else if (!e.shiftKey && doc.activeElement === ultimo) { e.preventDefault(); primero.focus(); }
            }
        });

        /* Gestos: deslizar para cambiar, bajar para cerrar, pellizcar o doble toque para zoom. */
        var punteros = {};
        var inicio = null;
        var pellizco = null;
        var ultimoToque = 0;
        function distancia() {
            var p = Object.keys(punteros).map(function (k) { return punteros[k]; });
            return Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
        }
        escena.addEventListener('pointerdown', function (e) {
            punteros[e.pointerId] = { x: e.clientX, y: e.clientY };
            escena.setPointerCapture(e.pointerId);
            var n = Object.keys(punteros).length;
            if (n === 1) inicio = { x: e.clientX, y: e.clientY, zx: z.x, zy: z.y, t: Date.now() };
            if (n === 2) { pellizco = { d: distancia(), s: z.s }; inicio = null; }
        });
        escena.addEventListener('pointermove', function (e) {
            if (!punteros[e.pointerId]) return;
            punteros[e.pointerId] = { x: e.clientX, y: e.clientY };
            if (pellizco && Object.keys(punteros).length === 2) {
                z.s = Math.max(1, Math.min(4, pellizco.s * distancia() / pellizco.d));
                if (z.s === 1) { z.x = 0; z.y = 0; }
                aplicarZoom();
            } else if (inicio && z.s > 1) {
                z.x = inicio.zx + (e.clientX - inicio.x);
                z.y = inicio.zy + (e.clientY - inicio.y);
                aplicarZoom();
            }
        });
        function soltar(e) {
            if (!punteros[e.pointerId]) return;
            delete punteros[e.pointerId];
            if (Object.keys(punteros).length < 2) pellizco = null;
            if (!inicio || Object.keys(punteros).length) return;
            var dx = e.clientX - inicio.x;
            var dy = e.clientY - inicio.y;
            var rapido = Date.now() - inicio.t < 600;
            if (z.s === 1 && rapido && Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
                mostrar(indice + (dx < 0 ? 1 : -1));
            } else if (z.s === 1 && rapido && dy > 90 && Math.abs(dy) > Math.abs(dx)) {
                cerrar(false);
            } else if (Math.abs(dx) < 8 && Math.abs(dy) < 8) {
                var ahora = Date.now();
                if (ahora - ultimoToque < 300) {
                    if (z.s > 1) { resetZoom(); }
                    else {
                        var r = img.getBoundingClientRect();
                        z.s = 2.2;
                        z.x = (r.left + r.width / 2 - e.clientX) * 1.2;
                        z.y = (r.top + r.height / 2 - e.clientY) * 1.2;
                        aplicarZoom();
                    }
                    ultimoToque = 0;
                } else {
                    ultimoToque = ahora;
                }
            }
            inicio = null;
        }
        escena.addEventListener('pointerup', soltar);
        escena.addEventListener('pointercancel', soltar);
        escena.addEventListener('wheel', function (e) {
            if (!abierto) return;
            e.preventDefault();
            z.s = Math.max(1, Math.min(4, z.s * (e.deltaY < 0 ? 1.12 : 0.89)));
            if (z.s === 1) { z.x = 0; z.y = 0; }
            aplicarZoom();
        }, { passive: false });
    }

    /* ── Story Mode ────────────────────────────────────────────────────────── */
    var tomas = $$('[data-toma]');
    if (tomas.length) {
        var tomaActual = 0;
        var contador = $('[data-toma-contador]');
        function irToma(i) {
            tomaActual = Math.max(0, Math.min(tomas.length - 1, i));
            tomas[tomaActual].scrollIntoView({ behavior: comportamiento, block: 'start' });
        }
        if ('IntersectionObserver' in window) {
            var ioToma = new IntersectionObserver(function (entradas) {
                entradas.forEach(function (e) {
                    if (e.isIntersecting) {
                        tomaActual = tomas.indexOf(e.target);
                        if (contador) contador.textContent = (tomaActual + 1) + ' / ' + tomas.length;
                    }
                });
            }, { threshold: 0.55 });
            tomas.forEach(function (t) { ioToma.observe(t); });
        }
        $$('[data-toma-paso]').forEach(function (b) {
            b.addEventListener('click', function () { irToma(tomaActual + parseInt(b.getAttribute('data-toma-paso'), 10)); });
        });
        doc.addEventListener('keydown', function (e) {
            if (raiz && !raiz.hidden) return;
            if (e.target.closest('input, textarea, select')) return;
            if (['ArrowDown', 'PageDown', ' ', 'ArrowRight'].indexOf(e.key) >= 0) { e.preventDefault(); irToma(tomaActual + 1); }
            if (['ArrowUp', 'PageUp', 'ArrowLeft'].indexOf(e.key) >= 0) { e.preventDefault(); irToma(tomaActual - 1); }
        });
    }

    /* ── Filtros (hoja inferior en el móvil) ─────────────────────────────── */
    var filtros = $('[data-filtros]');
    var abrirFiltros = $('[data-abrir-filtros]');
    if (filtros && abrirFiltros) {
        function alternarFiltros(on) {
            filtros.classList.toggle('is-abierto', on);
            abrirFiltros.setAttribute('aria-expanded', on ? 'true' : 'false');
            if (on) { var primero = $('input, select, button', filtros); if (primero) primero.focus(); }
            else abrirFiltros.focus();
        }
        abrirFiltros.addEventListener('click', function () { alternarFiltros(true); });
        var cerrarF = $('[data-cerrar-filtros]', filtros);
        if (cerrarF) cerrarF.addEventListener('click', function () { alternarFiltros(false); });
        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && filtros.classList.contains('is-abierto')) alternarFiltros(false);
        });
    }

    /* Atajo: "/" lleva a la búsqueda. */
    doc.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.target.closest('input, textarea, select, [contenteditable]')) return;
        var campo = $('[data-atajo-busqueda]');
        e.preventDefault();
        if (campo) campo.focus(); else window.location.href = '/archivo/buscar';
    });

    /* Compartir una ficha. */
    $$('[data-compartir]').forEach(function (b) {
        b.addEventListener('click', function () {
            var url = b.getAttribute('data-url'), titulo = b.getAttribute('data-titulo');
            if (navigator.share) { navigator.share({ title: titulo, url: url }).catch(function () {}); return; }
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    var antes = b.textContent; b.textContent = 'Enlace copiado';
                    setTimeout(function () { b.textContent = antes; }, 2200);
                });
            }
        });
    });
})();
