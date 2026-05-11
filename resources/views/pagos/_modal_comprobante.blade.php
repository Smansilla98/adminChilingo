{{-- Modal para previsualizar comprobante (PDF/imagen) sin salir de la página --}}
<div class="modal fade" id="modalComprobantePago" tabindex="-1" aria-labelledby="modalComprobantePagoLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down">
        <div class="modal-content bg-dark border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalComprobantePagoLabel">Comprobante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0 bg-black" style="min-height: 70vh;">
                <iframe id="iframeComprobantePago" title="Vista del comprobante" class="d-block w-100 border-0" style="min-height: 70vh; height: 75vh;"></iframe>
            </div>
            <div class="modal-footer border-secondary py-2">
                <a id="linkComprobanteNuevaPestana" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-light">Abrir en nueva pestaña</a>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('modalComprobantePago');
    const iframe = document.getElementById('iframeComprobantePago');
    const titleEl = document.getElementById('modalComprobantePagoLabel');
    const linkNueva = document.getElementById('linkComprobanteNuevaPestana');
    if (!modalEl || !iframe) return;
    modalEl.addEventListener('show.bs.modal', function (e) {
        const t = e.relatedTarget;
        const src = t && t.getAttribute('data-comprobante-src');
        const label = t && t.getAttribute('data-comprobante-label');
        iframe.src = src || 'about:blank';
        if (linkNueva && src) {
            linkNueva.href = src;
        }
        if (titleEl) {
            titleEl.textContent = label || 'Comprobante';
        }
    });
    modalEl.addEventListener('hidden.bs.modal', function () {
        iframe.src = 'about:blank';
    });
});
</script>
@endpush
@endonce
