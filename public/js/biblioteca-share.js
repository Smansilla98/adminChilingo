(function () {
    function toast(msg) {
        let el = document.getElementById('biblioShareToast');
        if (!el) {
            el = document.createElement('div');
            el.id = 'biblioShareToast';
            el.className = 'biblio-share-toast';
            el.setAttribute('role', 'status');
            document.body.appendChild(el);
        }
        el.textContent = msg;
        el.classList.add('is-on');
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.classList.remove('is-on'); }, 2400);
    }

    async function copiar(url) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(url);
            } else {
                const ta = document.createElement('textarea');
                ta.value = url;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                ta.remove();
            }
            toast('Link copiado');
            return true;
        } catch (e) {
            toast('No se pudo copiar. Copiá el enlace a mano.');
            return false;
        }
    }

    async function compartir(btn) {
        const url = btn.getAttribute('data-share-url') || '';
        const title = btn.getAttribute('data-share-title') || 'Biblioteca';
        const text = btn.getAttribute('data-share-text') || title;
        if (!url) return;

        if (navigator.share) {
            try {
                await navigator.share({ title: title, text: text, url: url });
                return;
            } catch (e) {
                if (e && e.name === 'AbortError') return;
            }
        }
        await copiar(url);
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-biblio-share]');
        if (!btn) return;
        e.preventDefault();
        e.stopPropagation();
        compartir(btn);
    });
})();
