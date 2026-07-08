<div id="recordatorio-chatbot" class="recordatorio-chatbot" aria-live="polite" data-api-url="{{ route('recordatorios.chat') }}">
    <div id="recordatorio-chat-panel" class="recordatorio-chat-panel d-none" role="dialog" aria-labelledby="recordatorio-chat-title" aria-modal="true">
        <div class="recordatorio-chat-header">
            <div>
                <div id="recordatorio-chat-title" class="recordatorio-chat-title">
                    <i class="bi bi-bell"></i> Recordatorios
                </div>
                <div class="recordatorio-chat-sub small text-muted">Asistencias y cuotas del mes</div>
            </div>
            <button type="button" class="btn btn-sm btn-link text-muted p-0" id="recordatorio-chat-close" aria-label="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div id="recordatorio-chat-messages" class="recordatorio-chat-messages">
            <div class="recordatorio-chat-bubble recordatorio-chat-bubble--bot">
                <span class="recordatorio-chat-typing">Revisando pendientes…</span>
            </div>
        </div>
        <div class="recordatorio-chat-footer small text-muted">
            Se actualiza cada vez que abrís este panel.
        </div>
    </div>

    <button
        type="button"
        id="recordatorio-chat-toggle"
        class="recordatorio-chat-fab"
        aria-expanded="false"
        aria-controls="recordatorio-chat-panel"
        title="Recordatorios"
    >
        <i class="bi bi-chat-dots-fill" aria-hidden="true"></i>
        <span id="recordatorio-chat-badge" class="recordatorio-chat-badge d-none" aria-hidden="true">0</span>
    </button>
</div>
