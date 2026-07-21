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

    function openConfirm(message, form) {
        if (!modal || !msgEl) {
            if (window.confirm(message)) form.submit();
            return;
        }
        pendingForm = form;
        lastFocus = document.activeElement;
        msgEl.textContent = message;
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('ito-modal-open');
        okBtn?.focus();
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

    // Anunciar alertas de éxito al cargar
    const success = document.querySelector('.alert-success');
    if (success) {
        success.setAttribute('role', 'status');
        announce(success.textContent.trim());
    }
    document.querySelectorAll('.alert-danger').forEach((el) => {
        el.setAttribute('role', 'alert');
    });
})();
