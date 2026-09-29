{{-- Guía para reemplazar los WAV del reproductor. Solo se muestra en la web. --}}
@if(empty($enAyuda))
<section class="ito-card p-3 mb-4" id="grabar-sonidos">
    <h2 class="h5 mb-2">Grabar los sonidos de los tambores</h2>
@endif
    <p class="mb-2">El reproductor no elige el archivo por cómo suena: lo elige por el <strong>nombre</strong>. Reemplazar <code>surdo_grave_normal.wav</code> por una toma del surdo de la escuela cambia ese golpe en la web y en la app. Los archivos viven en <code>public/sounds/perc/</code>.</p>
    <p class="mb-3">Los que están hoy son un reemplazo (librerías libres recortadas). No son la batería de La Chilinga. El surdo grave de la escuela es corto, cerca de 0,20 s, no un boom largo.</p>

    <h3 class="h6">Qué hay que reemplazar</h3>
    <p class="small text-muted">Un golpe por archivo. El flam y el acento del surdo no se graban: salen del golpe pleno. El acento de caja, repique, agogó y palmas sí tiene archivo propio.</p>
    <div class="table-responsive mb-3">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr><th>Archivo</th><th>Qué grabar</th></tr>
            </thead>
            <tbody>
                <tr><td><code>surdo_grave_normal.wav</code></td><td>Pleno, mano al centro. Corto, cerca de 0,20 s.</td></tr>
                <tr><td><code>surdo_grave_tapado.wav</code></td><td>La misma mano, apagando la piel. Cerca de 0,10 s.</td></tr>
                <tr><td><code>surdo_grave_chapa.wav</code></td><td>Aro o chapa, seco.</td></tr>
                <tr><td><code>surdo_medio_normal.wav</code> <code>_tapado</code> <code>_chapa</code></td><td>Lo mismo en el surdo medio.</td></tr>
                <tr><td><code>surdo_agudo_normal.wav</code> <code>_tapado</code> <code>_chapa</code></td><td>Lo mismo en el agudo, más corto.</td></tr>
                <tr><td><code>redoblante_normal.wav</code></td><td>Golpe pleno de caja, con el bordón del bloque.</td></tr>
                <tr><td><code>redoblante_acentuado.wav</code></td><td>El mismo golpe, más marcado.</td></tr>
                <tr><td><code>redoblante_chapa.wav</code></td><td>Aro.</td></tr>
                <tr><td><code>redoblante_agudo.wav</code></td><td>Ping del borde (el triángulo del cuadernillo, el de Oxosi).</td></tr>
                <tr><td><code>repique_normal.wav</code> <code>_acentuado</code> <code>_chapa</code> <code>_agudo</code></td><td>Igual que la caja, más agudo. El ping de borde, un poco más alto.</td></tr>
                <tr><td><code>timbal_abierto.wav</code></td><td>Abierto, con el ataque de metal del timbal de la escuela.</td></tr>
                <tr><td><code>timbal_slap.wav</code> <code>_palma</code> <code>_presionado</code> <code>_dedo</code></td><td>Slap, palma, presionado (corto) y dedos.</td></tr>
                <tr><td><code>agogo_normal.wav</code> <code>_acentuado</code> <code>_tapado</code></td><td>Campana grave, aguda y tapada. Bajo, para que no pinche el tutti.</td></tr>
                <tr><td><code>palmas_normal.wav</code> <code>_acentuado</code></td><td>Una palmada seca, y la misma más marcada.</td></tr>
            </tbody>
        </table>
    </div>

    <h3 class="h6">Paso a paso</h3>
    <ol>
        <li>Elegí un cuarto quieto. El micrófono (el del teléfono sirve para empezar) a unos 20 o 40 cm, apuntando al centro de la piel.</li>
        <li>Grabá un solo golpe. Un segundo de silencio antes y después, para poder cortar. Hacé tres o cuatro tomas y quedate con la que suena como en el bloque.</li>
        <li>El pico tiene que quedar debajo del rojo. Si satura, alejá el micrófono y repetí.</li>
        <li>Cortá justo antes del ataque y cuando el sonido ya murió. No dejes la cola de la sala.</li>
        <li>Exportá WAV, 44.1 kHz, 16 bits. Desde el teléfono: <code>ffmpeg -i toma.m4a -ar 44100 -acodec pcm_s16le surdo_grave_normal.wav</code></li>
        <li>Copiá el archivo a <code>public/sounds/perc/</code> con el nombre exacto de la tabla, en minúscula, pisando el que está. Los que todavía no grabaste pueden seguir como están.</li>
        <li>Creá un archivo vacío llamado <code>PROPIO</code> (sin extensión) en esa misma carpeta, y en el servidor dejá <code>PERC_KIT_REBUILD=0</code>. Si no, el próximo arranque vuelve a generar los sonidos de relleno y pisa los tuyos.</li>
        <li>Abrí un toque y dale a reproducir. La app baja de nuevo un WAV cuando cambia su fecha o su tamaño.</li>
    </ol>
    <p class="small text-muted mb-0">No renombres los archivos y no agregues otros: un nombre que no está en la tabla no suena.</p>
@if(empty($enAyuda))
</section>
@endif
