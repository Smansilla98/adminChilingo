<div
    id="biblioModal"
    class="biblio-modal"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="biblioModalTitle"
>
    <div class="biblio-modal-backdrop" data-biblio-close tabindex="-1"></div>
    <div class="biblio-modal-dialog">
        <button type="button" class="biblio-modal-close" data-biblio-close aria-label="Cerrar">&times;</button>
        <div class="biblio-modal-media" data-biblio-media></div>
        <div class="biblio-modal-info">
            <h2 id="biblioModalTitle" data-biblio-title></h2>
            <p class="biblio-modal-toque" data-biblio-toque hidden></p>
            <p class="biblio-modal-desc" data-biblio-desc hidden></p>
            <div class="biblio-modal-tags" data-biblio-tags></div>
            <div class="biblio-modal-meta">
                <span data-biblio-autor hidden></span>
                <div class="biblio-modal-actions">
                    <button type="button" class="btn btn-sm btn-primary" data-biblio-share data-biblio-ignore hidden
                            data-share-url="" data-share-title="" data-share-text="">
                        <i class="bi bi-share" aria-hidden="true"></i> Compartir
                    </button>
                    <a href="#" data-biblio-toque-link class="btn btn-sm btn-outline-secondary" hidden>Ver toque</a>
                    <a href="#" target="_blank" rel="noopener" data-biblio-open-raw class="btn btn-sm btn-secondary">Abrir original</a>
                </div>
            </div>
        </div>
    </div>
</div>
