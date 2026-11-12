/* Archivo histórico — backoffice: selección múltiple, confirmaciones y orden por arrastre. */
(function () {
    'use strict';

    var doc = document;
    var token = (doc.querySelector('meta[name="csrf-token"]') || {}).content || '';
    function $(sel, raiz) { return (raiz || doc).querySelector(sel); }
    function $$(sel, raiz) { return Array.prototype.slice.call((raiz || doc).querySelectorAll(sel)); }

    function anunciar(texto) {
        var vivo = $('#itoA11yLive');
        if (vivo) { vivo.textContent = ''; setTimeout(function () { vivo.textContent = texto; }, 30); }
    }

    /* Confirmación antes de acciones destructivas (formularios o botones). */
    doc.addEventListener('submit', function (e) {
        var f = e.target;
        var boton = e.submitter;
        var mensaje = (boton && boton.getAttribute('data-confirmar')) || f.getAttribute('data-confirmar');
        if (mensaje && !window.confirm(mensaje)) e.preventDefault();
    });

    /* Selección múltiple con barra de acciones. Shift + clic selecciona un rango. */
    $$('[data-seleccion]').forEach(function (form) {
        var casillas = $$('[data-sel]', form);
        var todas = $('[data-sel-todas]', form);
        var barra = $('[data-lote]', form);
        var ultima = null;

        function refrescar() {
            var n = casillas.filter(function (c) { return c.checked; }).length;
            $$('[data-sel-cuenta]', form).forEach(function (el) {
                el.textContent = n ? (n === 1 ? '1 foto seleccionada' : n + ' fotos seleccionadas') : '';
            });
            if (barra) barra.hidden = n === 0;
            if (todas) { todas.checked = n > 0 && n === casillas.length; todas.indeterminate = n > 0 && n < casillas.length; }
            casillas.forEach(function (c) { c.closest('.agx-item').classList.toggle('is-sel', c.checked); });
        }
        casillas.forEach(function (c, i) {
            c.addEventListener('click', function (e) {
                if (e.shiftKey && ultima !== null) {
                    var desde = Math.min(ultima, i), hasta = Math.max(ultima, i);
                    for (var k = desde; k <= hasta; k++) casillas[k].checked = c.checked;
                }
                ultima = i;
                refrescar();
            });
        });
        if (todas) todas.addEventListener('change', function () { casillas.forEach(function (c) { c.checked = todas.checked; }); refrescar(); });
        form.addEventListener('submit', function (e) {
            if (!casillas.some(function (c) { return c.checked; })) { e.preventDefault(); alert('Elegí al menos una foto.'); }
        });
        refrescar();
    });

    /* Orden por arrastre (mouse) o con flechas (teclado). Se guarda al soltar. */
    $$('[data-ordenable]').forEach(function (lista) {
        var url = lista.getAttribute('data-url');
        var arrastrado = null;
        function items() { return $$(':scope > [data-id]', lista); }

        function guardar() {
            var ids = items().map(function (el) { return parseInt(el.getAttribute('data-id'), 10); });
            lista.classList.add('is-guardando');
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: JSON.stringify({ ids: ids }),
            }).then(function (r) {
                lista.classList.remove('is-guardando');
                anunciar(r.ok ? 'Orden guardado.' : 'No se pudo guardar el orden.');
                if (!r.ok) alert('No se pudo guardar el orden. Recargá la página.');
            }).catch(function () {
                lista.classList.remove('is-guardando');
                alert('Sin conexión: el orden no se guardó.');
            });
        }

        function despuesDe(y, x) {
            var candidatos = items().filter(function (el) { return el !== arrastrado; });
            var mejor = null, distancia = Infinity;
            candidatos.forEach(function (el) {
                var r = el.getBoundingClientRect();
                var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
                var d = Math.hypot(cx - x, cy - y);
                if (d < distancia) { distancia = d; mejor = el; }
            });
            if (!mejor) return null;
            var r = mejor.getBoundingClientRect();
            // Lista vertical (ítems a lo ancho): decide la altura. Grilla: decide el lado.
            var vertical = r.width > lista.getBoundingClientRect().width * 0.6;
            var antes = vertical ? y < r.top + r.height / 2 : x < r.left + r.width / 2;
            return { el: mejor, antes: antes };
        }

        lista.addEventListener('dragstart', function (e) {
            var item = e.target.closest('[data-id]');
            if (!item || item.parentNode !== lista) return;
            arrastrado = item;
            item.classList.add('is-arrastrando');
            e.dataTransfer.effectAllowed = 'move';
            try { e.dataTransfer.setData('text/plain', item.getAttribute('data-id')); } catch (err) { /* IE */ }
        });
        lista.addEventListener('dragover', function (e) {
            if (!arrastrado) return;
            e.preventDefault();
            var destino = despuesDe(e.clientY, e.clientX);
            if (!destino || destino.el === arrastrado) return;
            lista.insertBefore(arrastrado, destino.antes ? destino.el : destino.el.nextSibling);
        });
        lista.addEventListener('drop', function (e) { if (arrastrado) e.preventDefault(); });
        lista.addEventListener('dragend', function () {
            if (!arrastrado) return;
            arrastrado.classList.remove('is-arrastrando');
            arrastrado = null;
            guardar();
        });
        lista.addEventListener('keydown', function (e) {
            var item = e.target.closest('[data-id]');
            if (!item || item !== e.target || item.parentNode !== lista) return;
            var todos = items();
            var i = todos.indexOf(item);
            var paso = { ArrowLeft: -1, ArrowUp: -1, ArrowRight: 1, ArrowDown: 1 }[e.key];
            if (!paso) return;
            var j = i + paso;
            if (j < 0 || j >= todos.length) return;
            e.preventDefault();
            lista.insertBefore(item, paso < 0 ? todos[j] : todos[j].nextSibling);
            item.focus();
            anunciar('Posición ' + (j + 1) + ' de ' + todos.length + '.');
            clearTimeout(item._guardar);
            item._guardar = setTimeout(guardar, 500);
        });
    });
})();
