@props([
    'telefono' => null,
    'nombre' => null,
    'email' => null,
    'mensaje' => null,
])
@php
    $tel = trim((string) ($telefono ?? ''));
    $telDigits = preg_replace('/\D+/', '', $tel);
    if ($telDigits !== '') {
        if (str_starts_with($telDigits, '0')) {
            $telDigits = '54'.ltrim($telDigits, '0');
        } elseif (strlen($telDigits) === 10) {
            $telDigits = '54'.$telDigits;
        }
    }
    $plainMsg = $mensaje ?: (
        'Hola, te escribimos de La Chilinga'.
        ($nombre ? ' por '.$nombre : '').
        '. Si necesitás enviar el comprobante de cuota: '.route('comprobante-cuota-public.create')
    );
    $waHref = $telDigits !== ''
        ? 'https://wa.me/'.$telDigits.'?text='.rawurlencode($plainMsg)
        : null;
    $mailHref = $email
        ? 'mailto:'.rawurlencode($email).'?subject='.rawurlencode('La Chilinga').'&body='.rawurlencode($plainMsg)
        : null;
@endphp
<div {{ $attributes->merge(['class' => 'ito-contact-actions']) }}>
    <div class="ito-contact-meta">
        @if($tel !== '')
            <span><i class="bi bi-telephone" aria-hidden="true"></i> {{ $tel }}</span>
        @else
            <span class="text-muted">Sin teléfono cargado</span>
        @endif
        @if($email)
            <span><i class="bi bi-envelope" aria-hidden="true"></i> {{ $email }}</span>
        @endif
    </div>
    <div class="ito-contact-btns">
        @if($waHref)
            <a class="btn btn-sm btn-success" href="{{ $waHref }}" target="_blank" rel="noopener">
                <i class="bi bi-whatsapp" aria-hidden="true"></i> WhatsApp
            </a>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-copy-text="{{ $plainMsg }}">
                Copiar mensaje
            </button>
        @endif
        @if($mailHref)
            <a class="btn btn-sm btn-outline-secondary" href="{{ $mailHref }}">
                <i class="bi bi-envelope" aria-hidden="true"></i> Email
            </a>
        @endif
    </div>
    <p class="ito-contact-note">
        No hay historial de mensajes en el sistema: el botón abre WhatsApp o el correo con una plantilla.
        Lo que sí queda registrado acá abajo son pagos, asistencias y el cuaderno pedagógico.
    </p>
</div>
@once
@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-copy-text]').forEach(function (btn) {
        if (btn.dataset.copyBound) return;
        btn.dataset.copyBound = '1';
        btn.addEventListener('click', function () {
            var text = btn.getAttribute('data-copy-text');
            if (!text || !navigator.clipboard) return;
            navigator.clipboard.writeText(text).then(function () {
                var prev = btn.textContent;
                btn.textContent = 'Copiado';
                setTimeout(function () { btn.textContent = prev; }, 1600);
            });
        });
    });
})();
</script>
@endpush
@endonce
