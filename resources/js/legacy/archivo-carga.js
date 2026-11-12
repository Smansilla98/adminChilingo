/*
 * Archivo histórico — carga de fotos y formularios (aportes y gestión).
 *
 * [data-cargador]: sube cada imagen por separado (progreso, reintento, aviso de
 * duplicado) y deja su id en un input oculto; el formulario después aplica los
 * datos comunes a todas. Sin JS, el mismo formulario envía los archivos juntos.
 */
(function () {
    'use strict';

    var doc = document;
    var token = (doc.querySelector('meta[name="csrf-token"]') || {}).content || '';
    function $(sel, raiz) { return (raiz || doc).querySelector(sel); }
    function $$(sel, raiz) { return Array.prototype.slice.call((raiz || doc).querySelectorAll(sel)); }
    function el(tag, clase, texto) {
        var n = doc.createElement(tag);
        if (clase) n.className = clase;
        if (texto != null) n.textContent = texto;
        return n;
    }
    function tamano(bytes) { return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.round(bytes / 1024) + ' KB'; }

    /* ── Cargador ─────────────────────────────────────────────────────────── */
    $$('[data-cargador]').forEach(function (form) {
        var zona = $('[data-zona]', form);
        var input = $('[data-input]', form);
        var cola = $('[data-cola]', form);
        var resumen = $('[data-resumen]', form);
        var urlSubir = form.getAttribute('data-subir');
        var campoIds = form.getAttribute('data-campo-ids') || 'ids[]';
        var MAX = 40 * 1024 * 1024;
        var TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
        var trabajos = [];
        var activos = 0;
        var CONCURRENCIA = 3;

        if (!zona || !input || !urlSubir) return;
        // Con JS, las imágenes viajan de a una; el formulario solo lleva los ids.
        input.removeAttribute('name');

        function actualizarResumen() {
            var listas = trabajos.filter(function (t) { return t.estado === 'ok'; }).length;
            var enCurso = trabajos.filter(function (t) { return t.estado === 'subiendo' || t.estado === 'espera'; }).length;
            resumen.hidden = trabajos.length === 0;
            resumen.textContent = listas + ' de ' + trabajos.length + ' fotos subidas' + (enCurso ? ' · subiendo…' : '');
            form.classList.toggle('is-subiendo', enCurso > 0);
            var evento = new CustomEvent('archivo:cola', { detail: { listas: listas, enCurso: enCurso } });
            form.dispatchEvent(evento);
        }

        function agregar(archivos) {
            Array.prototype.forEach.call(archivos, function (archivo) {
                var t = { archivo: archivo, estado: 'espera', confirmar: false, nodo: null, id: null };
                t.nodo = crearFila(t);
                cola.appendChild(t.nodo);
                if (TIPOS.indexOf(archivo.type) < 0) { fallar(t, 'Formato no aceptado (usá JPG, PNG o WebP).', false); }
                else if (archivo.size > MAX) { fallar(t, 'Pesa ' + tamano(archivo.size) + ': el máximo es 40 MB.', false); }
                trabajos.push(t);
            });
            actualizarResumen();
            siguiente();
        }

        function crearFila(t) {
            var li = el('li', 'ar-cola__item');
            var mini = el('span', 'ar-cola__mini');
            try {
                var url = URL.createObjectURL(t.archivo);
                var im = el('img'); im.alt = ''; im.src = url; im.onload = function () { URL.revokeObjectURL(url); };
                mini.appendChild(im);
            } catch (e) { /* sin vista previa */ }
            var txt = el('span', 'ar-cola__texto');
            txt.appendChild(el('span', 'ar-cola__nombre', t.archivo.name));
            var estado = el('span', 'ar-cola__estado', 'En espera · ' + tamano(t.archivo.size));
            txt.appendChild(estado);
            var barra = el('span', 'ar-cola__barra'); barra.appendChild(el('span'));
            txt.appendChild(barra);
            var acciones = el('span', 'ar-cola__acciones');
            li.appendChild(mini); li.appendChild(txt); li.appendChild(acciones);
            t.ui = { estado: estado, barra: barra.firstChild, acciones: acciones };
            return li;
        }

        function boton(texto, fn, clase) {
            var b = el('button', clase || 'ar-boton ar-boton--linea ar-boton--chico', texto);
            b.type = 'button';
            b.addEventListener('click', fn);
            return b;
        }

        function fallar(t, mensaje, reintentable) {
            t.estado = 'error';
            t.nodo.classList.add('is-error');
            t.ui.estado.textContent = mensaje;
            t.ui.acciones.innerHTML = '';
            if (reintentable) t.ui.acciones.appendChild(boton('Reintentar', function () { t.estado = 'espera'; t.nodo.classList.remove('is-error'); siguiente(); }));
            t.ui.acciones.appendChild(boton('Quitar', function () { quitar(t); }, 'ar-enlace'));
        }

        function quitar(t) {
            if (t.oculto) t.oculto.remove();
            t.nodo.remove();
            trabajos = trabajos.filter(function (x) { return x !== t; });
            actualizarResumen();
        }

        function siguiente() {
            while (activos < CONCURRENCIA) {
                var t = trabajos.find(function (x) { return x.estado === 'espera'; });
                if (!t) break;
                subir(t);
            }
            actualizarResumen();
        }

        function subir(t) {
            activos++;
            t.estado = 'subiendo';
            t.ui.estado.textContent = 'Subiendo…';
            t.ui.acciones.innerHTML = '';
            var datos = new FormData();
            datos.append('archivo', t.archivo);
            if (t.confirmar) datos.append('confirmar_duplicado', '1');
            var xhr = new XMLHttpRequest();
            xhr.open('POST', urlSubir);
            xhr.setRequestHeader('X-CSRF-TOKEN', token);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) t.ui.barra.style.width = Math.round(e.loaded / e.total * 100) + '%';
            });
            xhr.addEventListener('load', function () {
                activos--;
                var r = {};
                try { r = JSON.parse(xhr.responseText || '{}'); } catch (e) { r = {}; }
                if (xhr.status === 201 && r.data) {
                    t.estado = 'ok';
                    t.id = r.data.id;
                    t.nodo.classList.add('is-ok');
                    t.ui.estado.textContent = '✓ Subida';
                    t.ui.barra.style.width = '100%';
                    t.oculto = el('input'); t.oculto.type = 'hidden'; t.oculto.name = campoIds; t.oculto.value = r.data.id;
                    form.appendChild(t.oculto);
                    if (r.data.editar) {
                        var a = el('a', 'ar-enlace', 'Editar'); a.href = r.data.editar; a.target = '_blank'; a.rel = 'noopener';
                        t.ui.acciones.appendChild(a);
                    }
                } else if (xhr.status === 409) {
                    duplicado(t, r);
                } else if (xhr.status === 422) {
                    var errores = r.errors ? Object.keys(r.errors).map(function (k) { return r.errors[k][0]; }) : [];
                    fallar(t, errores[0] || r.message || 'La imagen no es válida.', false);
                } else if (xhr.status === 419) {
                    fallar(t, 'La sesión venció. Recargá la página.', false);
                } else {
                    fallar(t, 'No se pudo subir (' + (xhr.status || 'sin conexión') + ').', true);
                }
                siguiente();
            });
            xhr.addEventListener('error', function () { activos--; fallar(t, 'Se cortó la conexión.', true); siguiente(); });
            xhr.send(datos);
        }

        function duplicado(t, r) {
            t.estado = 'duplicado';
            t.nodo.classList.add('is-aviso');
            t.ui.estado.textContent = 'Esta fotografía podría ya formar parte del archivo.';
            t.ui.acciones.innerHTML = '';
            (r.duplicados || []).slice(0, 1).forEach(function (d) {
                if (d.url) { var a = el('a', 'ar-enlace', 'Ver la existente'); a.href = d.url; a.target = '_blank'; a.rel = 'noopener'; t.ui.acciones.appendChild(a); }
            });
            t.ui.acciones.appendChild(boton('Subir igual', function () { t.confirmar = true; t.estado = 'espera'; t.nodo.classList.remove('is-aviso'); siguiente(); }));
            t.ui.acciones.appendChild(boton('Descartar', function () { quitar(t); }, 'ar-enlace'));
        }

        input.addEventListener('change', function () { agregar(input.files); input.value = ''; });
        ['dragenter', 'dragover'].forEach(function (ev) {
            zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.add('is-encima'); });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            zona.addEventListener(ev, function (e) { e.preventDefault(); zona.classList.remove('is-encima'); });
        });
        zona.addEventListener('drop', function (e) { if (e.dataTransfer && e.dataTransfer.files) agregar(e.dataTransfer.files); });

        form.addEventListener('submit', function (e) {
            var enCurso = trabajos.some(function (t) { return t.estado === 'subiendo' || t.estado === 'espera'; });
            var listas = trabajos.some(function (t) { return t.estado === 'ok'; });
            if (enCurso) { e.preventDefault(); alert('Esperá a que terminen de subir las fotos.'); return; }
            if (!listas) { e.preventDefault(); alert('Primero subí al menos una foto.'); }
        });
    });

    /* ── Personas que aparecen ────────────────────────────────────────────── */
    $$('[data-personas-picker]').forEach(function (picker) {
        var lista = $('[data-lista]', picker);
        var nombre = $('[data-nombre]', picker);
        var detalle = $('[data-detalle]', picker);
        var datalist = $('datalist', picker);
        var url = picker.getAttribute('data-buscar');
        var campo = picker.getAttribute('data-campo') || 'personas';
        var sugeridas = {};
        var personas = [];
        try { personas = JSON.parse(picker.getAttribute('data-inicial') || '[]'); } catch (e) { personas = []; }

        function pintar() {
            lista.innerHTML = '';
            personas.forEach(function (p, i) {
                var li = el('li', 'ar-personas-picker__item');
                li.appendChild(el('span', null, p.nombre + (p.detalle ? ' — ' + p.detalle : '')));
                if (p.persona_id) li.appendChild(el('span', 'ar-personas-picker__vinculo', 'en el sistema'));
                [['persona_id', p.persona_id || ''], ['nombre', p.persona_id ? '' : p.nombre], ['detalle', p.detalle || '']].forEach(function (par) {
                    var h = el('input'); h.type = 'hidden'; h.name = campo + '[' + i + '][' + par[0] + ']'; h.value = par[1];
                    li.appendChild(h);
                });
                var b = el('button', 'ar-personas-picker__quitar', '✕');
                b.type = 'button';
                b.setAttribute('aria-label', 'Quitar a ' + p.nombre);
                b.addEventListener('click', function () { personas.splice(i, 1); pintar(); nombre.focus(); });
                li.appendChild(b);
                lista.appendChild(li);
            });
        }

        function agregar() {
            var n = nombre.value.trim();
            if (!n) return;
            var s = sugeridas[n.toLowerCase()];
            var p = { persona_id: s && s.persona_id ? s.persona_id : null, nombre: s ? s.nombre : n, detalle: detalle.value.trim() };
            var repetida = personas.some(function (x) { return (p.persona_id && x.persona_id === p.persona_id) || (!p.persona_id && !x.persona_id && x.nombre.toLowerCase() === p.nombre.toLowerCase()); });
            if (!repetida) personas.push(p);
            nombre.value = ''; detalle.value = '';
            pintar();
            nombre.focus();
        }

        var espera = null;
        nombre.addEventListener('input', function () {
            clearTimeout(espera);
            var q = nombre.value.trim();
            if (q.length < 2 || !url) return;
            espera = setTimeout(function () {
                fetch(url + (url.indexOf('?') < 0 ? '?' : '&') + 'q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : { data: [] }; })
                    .then(function (r) {
                        datalist.innerHTML = '';
                        (r.data || []).forEach(function (p) {
                            sugeridas[p.nombre.toLowerCase()] = p;
                            var o = el('option'); o.value = p.nombre;
                            if (p.fotos) o.label = p.fotos + ' fotos';
                            datalist.appendChild(o);
                        });
                    }).catch(function () {});
            }, 220);
        });
        [nombre, detalle].forEach(function (c) {
            c.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); agregar(); } });
        });
        $('[data-agregar]', picker).addEventListener('click', agregar);
        pintar();
    });

    /* Fecha exacta solo si se eligió esa precisión. */
    $$('select[name="precision"]').forEach(function (sel) {
        var form = sel.form;
        function aplicar() {
            $$('[data-si-precision]', form).forEach(function (bloque) {
                bloque.hidden = bloque.getAttribute('data-si-precision') !== sel.value;
            });
        }
        sel.addEventListener('change', aplicar);
        aplicar();
    });
})();
