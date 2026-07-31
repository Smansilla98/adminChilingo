(function () {
    const modal = document.getElementById('biblioModal');
    if (!modal) return;

    const media = modal.querySelector('[data-biblio-media]');
    const titleEl = modal.querySelector('[data-biblio-title]');
    const descEl = modal.querySelector('[data-biblio-desc]');
    const tagsEl = modal.querySelector('[data-biblio-tags]');
    const autorEl = modal.querySelector('[data-biblio-autor]');
    const rawLink = modal.querySelector('[data-biblio-open-raw]');

    function stopMedia() {
        media.querySelectorAll('video, audio').forEach(function (el) {
            try { el.pause(); el.removeAttribute('src'); el.load(); } catch (e) {}
        });
        media.innerHTML = '';
        media.classList.remove('is-png');
    }

    function close() {
        stopMedia();
        modal.hidden = true;
        document.body.classList.remove('biblio-modal-open');
    }

    function openFromCard(card) {
        const tipo = card.getAttribute('data-biblio-tipo') || '';
        const src = card.getAttribute('data-biblio-src') || '';
        const title = card.getAttribute('data-biblio-title') || '';
        const desc = card.getAttribute('data-biblio-desc') || '';
        const tags = card.getAttribute('data-biblio-tags') || '';
        const autor = card.getAttribute('data-biblio-autor') || '';
        const isPng = card.getAttribute('data-biblio-png') === '1';

        if (!src) return;

        stopMedia();
        titleEl.textContent = title;

        if (desc) {
            descEl.textContent = desc;
            descEl.hidden = false;
        } else {
            descEl.textContent = '';
            descEl.hidden = true;
        }

        tagsEl.innerHTML = '';
        tags.split(/\s+/).filter(Boolean).forEach(function (t) {
            const span = document.createElement('span');
            span.className = 'biblio-tag is-active';
            span.textContent = t;
            tagsEl.appendChild(span);
        });

        if (autor) {
            autorEl.textContent = autor;
            autorEl.hidden = false;
        } else {
            autorEl.hidden = true;
        }

        rawLink.href = src;

        if (tipo === 'imagen') {
            if (isPng) media.classList.add('is-png');
            const img = document.createElement('img');
            img.src = src;
            img.alt = title;
            media.appendChild(img);
        } else if (tipo === 'video') {
            const video = document.createElement('video');
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            video.src = src;
            media.appendChild(video);
        } else if (tipo === 'audio') {
            const wrap = document.createElement('div');
            wrap.className = 'biblio-modal-audio';
            wrap.innerHTML = '<i class="bi bi-music-note-beamed" aria-hidden="true"></i>';
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.autoplay = true;
            audio.src = src;
            wrap.appendChild(audio);
            media.appendChild(wrap);
        } else if (tipo === 'pdf') {
            const iframe = document.createElement('iframe');
            iframe.src = src + '#view=FitH';
            iframe.title = title;
            media.appendChild(iframe);
        } else if (tipo === 'enlace') {
            const box = document.createElement('div');
            box.className = 'biblio-modal-link';
            box.innerHTML = '<i class="bi bi-link-45deg" aria-hidden="true"></i><p>Este material es un enlace externo.</p>';
            const a = document.createElement('a');
            a.href = src;
            a.target = '_blank';
            a.rel = 'noopener';
            a.className = 'btn btn-primary';
            a.textContent = 'Abrir enlace';
            box.appendChild(a);
            media.appendChild(box);
        } else {
            const box = document.createElement('div');
            box.className = 'biblio-modal-link';
            box.innerHTML = '<i class="bi bi-file-earmark" aria-hidden="true"></i>';
            const a = document.createElement('a');
            a.href = src;
            a.target = '_blank';
            a.rel = 'noopener';
            a.className = 'btn btn-primary';
            a.textContent = 'Descargar / abrir';
            box.appendChild(a);
            media.appendChild(box);
        }

        modal.hidden = false;
        document.body.classList.add('biblio-modal-open');
    }

    document.addEventListener('click', function (e) {
        const ignore = e.target.closest('[data-biblio-ignore]');
        if (ignore) return;

        const closeBtn = e.target.closest('[data-biblio-close]');
        if (closeBtn) {
            e.preventDefault();
            close();
            return;
        }

        const card = e.target.closest('[data-biblio-open]');
        if (card) {
            e.preventDefault();
            openFromCard(card);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.hidden) close();
        if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-biblio-open]')) {
            e.preventDefault();
            openFromCard(e.target);
        }
    });
})();
