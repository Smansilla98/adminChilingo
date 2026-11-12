/**
 * ITO accesibilidad: confirmación modal, preferencias UI, formularios.
 */
(function () {
    const root = document.documentElement;

    function applyPrefs() {
        try {
            if (localStorage.getItem('ito-a11y-lg') === '1') root.classList.add('ito-text-lg');
            else root.classList.remove('ito-text-lg');
            if (localStorage.getItem('ito-a11y-hc') === '1') root.classList.add('ito-contrast');
            else root.classList.remove('ito-contrast');
        } catch (e) { /* private mode */ }
    }
    applyPrefs();

    document.querySelectorAll('[data-ito-pref]').forEach((btn) => {
        const key = btn.getAttribute('data-ito-pref');
        const sync = () => {
            const on = localStorage.getItem(key) === '1';
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.classList.toggle('is-on', on);
        };
        sync();
        btn.addEventListener('click', () => {
            const next = localStorage.getItem(key) === '1' ? '0' : '1';
            localStorage.setItem(key, next);
            applyPrefs();
            sync();
            announce(next === '1' ? (btn.dataset.onMsg || 'Preferencia activada') : (btn.dataset.offMsg || 'Preferencia desactivada'));
        });
    });

    const live = document.getElementById('itoA11yLive');
    function announce(msg) {
        if (!live) return;
        live.textContent = '';
        requestAnimationFrame(() => { live.textContent = msg; });
    }
    window.itoAnnounce = announce;

    // —— Modal de confirmación (reemplaza confirm nativo) ——
    const modal = document.getElementById('itoConfirmModal');
    const msgEl = document.getElementById('itoConfirmMessage');
    const okBtn = document.getElementById('itoConfirmOk');
    const cancelBtn = document.getElementById('itoConfirmCancel');
    const closeBtn = document.getElementById('itoConfirmClose');
    let pendingForm = null;
    let lastFocus = null;

    const titleEl = document.getElementById('itoConfirmTitle');
    const DESTRUCTIVOS = ['eliminar', 'borrar', 'quitar', 'desactivar', 'sacar', 'anular', 'descartar', 'vaciar'];

    /** "¿Eliminar este alumno?" → título, descripción y botón coherentes. */
    function describir(message, form) {
        const texto = String(message || '').trim();
        const m = texto.match(/¿[^?]+\?/);
        let titulo = m ? m[0] : '¿Confirmar acción?';
        let desc = m ? texto.replace(m[0], '').trim() : texto;
        const verbo = ((titulo.match(/¿\s*([A-Za-zÁÉÍÓÚáéíóúñÑ]+)/) || [])[1] || '').toLowerCase();
        const metodo = (form.querySelector('input[name="_method"]')?.value || form.getAttribute('method') || '').toUpperCase();
        const destructivo = form.dataset.confirmTone === 'danger' || metodo === 'DELETE' || DESTRUCTIVOS.indexOf(verbo) !== -1;
        if (!desc) {
            desc = destructivo ? 'Esta acción no se puede deshacer.' : 'Revisá antes de continuar.';
        }
        let ok = form.dataset.confirmOk || '';
        if (!ok) {
            ok = /(ar|er|ir)$/.test(verbo) ? verbo.charAt(0).toUpperCase() + verbo.slice(1) : 'Confirmar';
        }
        return { titulo: form.dataset.confirmTitle || titulo, desc: form.dataset.confirmText || desc, ok: ok, destructivo: destructivo };
    }

    function openConfirm(message, form) {
        if (!modal || !msgEl) {
            if (window.confirm(message)) form.submit();
            return;
        }
        const d = describir(message, form);
        pendingForm = form;
        lastFocus = document.activeElement;
        if (titleEl) titleEl.textContent = d.titulo;
        msgEl.textContent = d.desc;
        if (okBtn) {
            okBtn.textContent = d.ok;
            okBtn.className = 'btn ' + (d.destructivo ? 'btn-danger' : 'btn-primary');
        }
        modal.classList.toggle('is-neutral', !d.destructivo);
        const icon = modal.querySelector('.ito-confirm-icon i');
        if (icon) icon.className = 'bi ' + (d.destructivo ? 'bi-exclamation-triangle' : 'bi-question-circle');
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('ito-modal-open');
        (d.destructivo ? cancelBtn : okBtn)?.focus();
        trapFocus(modal);
    }

    function closeConfirm() {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.hidden = true;
        document.body.classList.remove('ito-modal-open');
        pendingForm = null;
        releaseTrap();
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    let trapHandler = null;
    function trapFocus(container) {
        releaseTrap();
        trapHandler = (e) => {
            if (e.key !== 'Tab') return;
            const focusables = container.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
            const list = Array.from(focusables).filter((el) => !el.disabled && el.offsetParent !== null);
            if (!list.length) return;
            const first = list[0];
            const last = list[list.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', trapHandler);
    }
    function releaseTrap() {
        if (trapHandler) document.removeEventListener('keydown', trapHandler);
        trapHandler = null;
    }

    okBtn?.addEventListener('click', () => {
        const form = pendingForm;
        closeConfirm();
        if (form) {
            form.dataset.itoConfirmed = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    });
    cancelBtn?.addEventListener('click', closeConfirm);
    closeBtn?.addEventListener('click', closeConfirm);
    modal?.addEventListener('click', (e) => {
        if (e.target === modal || e.target.classList.contains('ito-confirm-backdrop')) closeConfirm();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal && !modal.hidden) {
            e.preventDefault();
            closeConfirm();
        }
    });

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.itoConfirmed === '1') {
            delete form.dataset.itoConfirmed;
            return;
        }
        const msg = form.getAttribute('data-confirm');
        if (!msg) return;
        e.preventDefault();
        openConfirm(msg, form);
    });

    // Migrate legacy onsubmit="return confirm(...)"
    document.querySelectorAll('form[onsubmit*="confirm"]').forEach((form) => {
        const attr = form.getAttribute('onsubmit') || '';
        const m = attr.match(/confirm\(['"](.+?)['"]\)/);
        if (m) {
            form.setAttribute('data-confirm', m[1]);
            form.removeAttribute('onsubmit');
        }
    });

    // —— Formularios: etiquetas asociadas, obligatorios marcados y errores bajo cada campo ——
    const CONTROL = 'input:not([type=hidden]):not([type=checkbox]):not([type=radio]):not([type=submit]):not([type=button]), select, textarea';
    let autoId = 0;
    function controlDe(label) {
        if (label.htmlFor) return document.getElementById(label.htmlFor);
        if (label.querySelector(CONTROL)) return label.querySelector(CONTROL);
        let el = label.nextElementSibling;
        while (el && el.tagName !== 'LABEL') {
            const c = el.matches(CONTROL) ? el : el.querySelector(CONTROL);
            if (c) return c;
            el = el.nextElementSibling;
        }
        return null;
    }
    document.querySelectorAll('label.form-label, .ito-field > label').forEach((label) => {
        const control = controlDe(label);
        if (control && !label.htmlFor && !label.contains(control)) {
            if (!control.id) control.id = 'ito-campo-' + (++autoId);
            label.htmlFor = control.id;
        }
        // "Nombre *" escrito a mano → misma marca visual que los required.
        const ultimo = label.lastChild;
        if (ultimo && ultimo.nodeType === 3 && /\s*\*\s*$/.test(ultimo.textContent)) {
            ultimo.textContent = ultimo.textContent.replace(/\s*\*\s*$/, '');
            label.classList.add('required');
        } else if (control && control.required) {
            label.classList.add('required');
        }
    });

    // Errores del servidor que la vista no muestra junto al campo: se ubican debajo de él.
    const erroresEl = document.getElementById('itoErrores');
    if (erroresEl) {
        let errores = {};
        try { errores = JSON.parse(erroresEl.textContent || '{}'); } catch (e) { errores = {}; }
        let primero = null;
        Object.keys(errores).forEach((clave) => {
            const partes = clave.split('.');
            const nombre = partes[0] + partes.slice(1).map((p) => '[' + p + ']').join('');
            const control = document.querySelector('[name="' + nombre + '"], [name="' + nombre + '[]"]');
            if (!control || control.type === 'hidden') return;
            primero = primero || control;
            if (control.classList.contains('is-invalid')) return;
            control.classList.add('is-invalid');
            const ancla = control.closest('.input-group') || control;
            if (!ancla.parentElement.querySelector('.invalid-feedback')) {
                const fb = document.createElement('div');
                fb.className = 'invalid-feedback d-block';
                fb.textContent = (errores[clave] || [])[0] || '';
                ancla.insertAdjacentElement('afterend', fb);
            }
        });
        if (primero && !document.querySelector('[data-ito-form-steps]')) {
            primero.focus({ preventScroll: true });
        }
    }

    // Formularios: aria-invalid / describedby
    document.querySelectorAll('.is-invalid').forEach((el, i) => {
        el.setAttribute('aria-invalid', 'true');
        const feedback = el.parentElement?.querySelector('.invalid-feedback')
            || el.closest('.mb-3, .ito-field, .col-md-6, .col-md-4')?.querySelector('.invalid-feedback');
        if (feedback) {
            if (!feedback.id) feedback.id = 'ito-err-' + i;
            el.setAttribute('aria-describedby', feedback.id);
        }
    });

    // Sidebar: aria-current
    document.querySelectorAll('.side-link.active').forEach((a) => {
        a.setAttribute('aria-current', 'page');
    });

    document.querySelectorAll('.alert-danger').forEach((el) => {
        el.setAttribute('role', 'alert');
    });
})();
