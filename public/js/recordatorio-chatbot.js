(function () {
    var root = document.getElementById('recordatorio-chatbot');
    if (!root) return;

    var panel = document.getElementById('recordatorio-chat-panel');
    var toggle = document.getElementById('recordatorio-chat-toggle');
    var closeBtn = document.getElementById('recordatorio-chat-close');
    var messagesEl = document.getElementById('recordatorio-chat-messages');
    var badge = document.getElementById('recordatorio-chat-badge');
    var apiUrl = root.getAttribute('data-api-url');
    var abierto = false;
    var cargado = false;

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function clasePrioridad(p) {
        if (p === 'alta') return 'recordatorio-chat-bubble--alta';
        if (p === 'media') return 'recordatorio-chat-bubble--media';
        if (p === 'ok') return 'recordatorio-chat-bubble--ok';
        return '';
    }

    function render(data) {
        if (!messagesEl || !data) return;
        var html = '';
        html += '<div class="recordatorio-chat-bubble recordatorio-chat-bubble--bot recordatorio-chat-bubble--saludo">'
            + escHtml(data.saludo || 'Hola.') + '</div>';

        (data.mensajes || []).forEach(function (m) {
            var cls = 'recordatorio-chat-bubble recordatorio-chat-bubble--bot ' + clasePrioridad(m.prioridad);
            html += '<div class="' + cls + '">' + escHtml(m.texto || '');
            if (m.accion_url && m.accion_texto) {
                html += ' <a class="recordatorio-chat-action" href="' + escHtml(m.accion_url) + '">'
                    + escHtml(m.accion_texto) + ' →</a>';
            }
            html += '</div>';
        });

        messagesEl.innerHTML = html;

        var total = data.resumen && typeof data.resumen.total === 'number' ? data.resumen.total : 0;
        if (badge) {
            if (total > 0 && !data.todo_ok) {
                badge.textContent = total > 9 ? '9+' : String(total);
                badge.classList.remove('d-none');
            } else {
                badge.classList.add('d-none');
            }
        }
    }

    function cargar() {
        if (!apiUrl) return;
        fetch(apiUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (data) {
                render(data);
                cargado = true;
            })
            .catch(function () {
                if (messagesEl) {
                    messagesEl.innerHTML = '<div class="recordatorio-chat-bubble recordatorio-chat-bubble--bot">'
                        + 'No pude cargar los recordatorios. Probá recargar la página.</div>';
                }
            });
    }

    function abrir() {
        abierto = true;
        panel.classList.remove('d-none');
        toggle.setAttribute('aria-expanded', 'true');
        if (!cargado) cargar();
        else cargar();
    }

    function cerrar() {
        abierto = false;
        panel.classList.add('d-none');
        toggle.setAttribute('aria-expanded', 'false');
    }

    toggle.addEventListener('click', function () {
        if (abierto) cerrar();
        else abrir();
    });
    closeBtn.addEventListener('click', cerrar);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && abierto) cerrar();
    });

    document.addEventListener('click', function (e) {
        if (!abierto) return;
        if (root.contains(e.target)) return;
        cerrar();
    });

    cargar();
})();
