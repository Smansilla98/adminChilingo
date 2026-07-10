import Embed from 'flat-embed';

function buildEmbedParams(app, mode) {
    const params = {
        appId: app.dataset.appId || '',
        mode,
        controlsPosition: 'bottom',
        branding: false,
    };
    if (app.dataset.userId) {
        params.userId = String(app.dataset.userId);
    }
    return params;
}

function hideLoading(loadingEl) {
    if (loadingEl) {
        loadingEl.classList.add('is-hidden');
        setTimeout(() => loadingEl.remove(), 250);
    }
}

async function initEditor() {
    const app = document.getElementById('compositorApp');
    const container = document.getElementById('flatEmbedContainer');
    const form = document.getElementById('compositorForm');
    const input = document.getElementById('partitura_flat_musicxml');
    const saveBtn = document.getElementById('compositorSaveBtn');
    const loading = document.getElementById('compositorLoading');

    if (!app || !container || !form || !input) {
        return;
    }

    const embed = new Embed(container, {
        score: 'blank',
        width: '100%',
        height: '100%',
        embedParams: buildEmbedParams(app, 'edit'),
    });

    try {
        await embed.ready();
        const initialScript = document.getElementById('compositorInitialXml');
        const initialXml = initialScript ? JSON.parse(initialScript.textContent || '""') : '';
        if (initialXml) {
            await embed.loadMusicXML(initialXml);
        }
    } catch (err) {
        console.error('Flat embed error:', err);
    } finally {
        hideLoading(loading);
    }

    form.addEventListener('submit', async (event) => {
        if (form.dataset.submitting === '1') {
            return;
        }

        event.preventDefault();
        form.dataset.submitting = '1';

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Guardando…';
        }

        try {
            if (form.querySelector('[name="quitar_partitura_flat"]')?.checked) {
                input.value = '';
            } else {
                const xml = await embed.getMusicXML();
                input.value = typeof xml === 'string' ? xml : '';
            }
            form.submit();
        } catch (err) {
            console.error('Error al exportar MusicXML:', err);
            form.dataset.submitting = '0';
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="bi bi-cloud-check"></i> Guardar';
            }
            alert('No se pudo exportar la partitura. Intentá de nuevo.');
        }
    });
}

async function initViewers() {
    const wraps = document.querySelectorAll('[data-flat-viewer]');
    if (!wraps.length) {
        return;
    }

    for (const wrap of wraps) {
        const container = wrap.querySelector('[data-flat-container]');
        const loading = wrap.querySelector('.programa-flat-viewer-loading');
        if (!container) {
            continue;
        }

        const embed = new Embed(container, {
            score: 'blank',
            width: '100%',
            height: '100%',
            embedParams: buildEmbedParams(wrap, 'view'),
        });

        try {
            await embed.ready();
            const xmlScript = wrap.querySelector('.programa-flat-musicxml');
            const xml = xmlScript ? JSON.parse(xmlScript.textContent || '""') : '';
            if (xml) {
                await embed.loadMusicXML(xml);
            }
            wrap.classList.add('is-ready');
        } catch (err) {
            console.error('Flat viewer error:', err);
            if (loading) {
                loading.textContent = 'No se pudo cargar la partitura interactiva.';
            }
        } finally {
            if (loading && wrap.classList.contains('is-ready')) {
                loading.remove();
            }
        }
    }
}

if (document.getElementById('compositorApp')) {
    initEditor();
} else {
    initViewers();
}
