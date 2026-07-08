(function () {
    var root = document.getElementById('recordatorio-chatbot');
    if (!root) return;

    var panel = document.getElementById('recordatorio-chat-panel');
    var toggle = document.getElementById('recordatorio-chat-toggle');
    var closeBtn = document.getElementById('recordatorio-chat-close');
    var messagesEl = document.getElementById('recordatorio-chat-messages');
    var badge = document.getElementById('recordatorio-chat-badge');
    var apiUrl = root.getAttribute('data-api-url');
    var whatsappUrl = root.getAttribute('data-whatsapp-url');
    var waPreviewBtn = document.getElementById('recordatorio-chat-whatsapp-preview');
    var waSendBtn = document.getElementById('recordatorio-chat-whatsapp-send');
    var waStatus = document.getElementById('recordatorio-chat-whatsapp-status');
    var abierto = false;
    var cargado = false;

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

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

    function setWaStatus(texto, tipo) {
        if (!waStatus) return;
        waStatus.textContent = texto;
        waStatus.classList.remove('d-none', 'is-ok', 'is-error', 'is-info');
        if (tipo) waStatus.classList.add('is-' + tipo);
    }

    function setWaLoading(cargando) {
        [waPreviewBtn, waSendBtn].forEach(function (btn) {
            if (btn) btn.disabled = cargando;
        });
    }

    function enviarWhatsApp(preview) {
        if (!whatsappUrl) return;

        if (!preview && !window.confirm('¿Enviar el resumen por WhatsApp a los administradores configurados?')) {
            return;
        }

        setWaLoading(true);
        setWaStatus(preview ? 'Generando vista previa…' : 'Enviando por WhatsApp…', 'info');

        var body = preview ? 'preview=1' : '';
        fetch(whatsappUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: body,
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (res) {
                var data = res.data || {};
                if (preview && data.detalles && data.detalles[0] && data.detalles[0].preview) {
                    var previewHtml = '<div class="recordatorio-chat-bubble recordatorio-chat-bubble--bot recordatorio-chat-bubble--wa-preview">'
                        + '<div class="fw-semibold mb-1">Vista previa WhatsApp</div>'
                        + '<pre class="recordatorio-chat-wa-pre">' + escHtml(data.detalles[0].preview) + '</pre>'
                        + '</div>';
                    messagesEl.insertAdjacentHTML('beforeend', previewHtml);
                    messagesEl.scrollTop = messagesEl.scrollHeight;
                    setWaStatus(data.mensaje || 'Vista previa lista.', 'ok');
                    return;
                }

                if (res.ok && data.ok) {
                    setWaStatus(data.mensaje || 'Mensaje enviado.', 'ok');
                    return;
                }

                var err = data.mensaje || (data.detalles && data.detalles[0] && data.detalles[0].error) || 'No se pudo enviar.';
                setWaStatus(err, 'error');
            })
            .catch(function () {
                setWaStatus('Error de conexión al enviar por WhatsApp.', 'error');
            })
            .finally(function () {
                setWaLoading(false);
            });
    }

    function abrir() {
        abierto = true;
        panel.classList.remove('d-none');
        toggle.setAttribute('aria-expanded', 'true');
        cargar();
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

    if (waPreviewBtn) {
        waPreviewBtn.addEventListener('click', function () {
            enviarWhatsApp(true);
        });
    }

    if (waSendBtn) {
        waSendBtn.addEventListener('click', function () {
            enviarWhatsApp(false);
        });
    }

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
