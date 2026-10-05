{{-- Visor a pantalla completa (archivo.js). Carga la versión grande solo al abrir. --}}
<div class="ar-visor" data-visor-raiz role="dialog" aria-modal="true" aria-labelledby="visor-titulo" hidden>
    <div class="ar-visor__escena" data-visor-escena>
        <img class="ar-visor__img" data-visor-img alt="">
    </div>
    <div class="ar-visor__barra">
        <span class="ar-visor__contador ar-mono" data-visor-contador></span>
        <div class="ar-visor__acciones">
            <button type="button" class="ar-visor__btn" data-visor-info aria-expanded="false" aria-controls="visor-ficha">Info</button>
            <button type="button" class="ar-visor__btn" data-visor-cerrar aria-label="Cerrar visor (Esc)">✕</button>
        </div>
    </div>
    <button type="button" class="ar-visor__nav ar-visor__nav--ant" data-visor-paso="-1" aria-label="Foto anterior">←</button>
    <button type="button" class="ar-visor__nav ar-visor__nav--sig" data-visor-paso="1" aria-label="Foto siguiente">→</button>
    <aside class="ar-visor__ficha" id="visor-ficha" data-visor-ficha>
        <p class="ar-visor__anio ar-mono" data-visor-anio></p>
        <h2 class="ar-visor__titulo" id="visor-titulo" data-visor-titulo></h2>
        <p class="ar-visor__desc" data-visor-desc></p>
        <p class="ar-visor__meta ar-tenue" data-visor-meta></p>
        <a class="ar-enlace" data-visor-enlace href="#">Ver ficha completa <span aria-hidden="true">→</span></a>
    </aside>
</div>
