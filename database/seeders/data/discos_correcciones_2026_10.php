<?php

/**
 * Correcciones a la discografía tras la investigación de prensa de octubre de 2026.
 * Cada campo: [valor original sembrado, valor corregido]. La migración solo aplica la
 * corrección si el campo sigue igual al original (no pisa ediciones de la escuela).
 */
return [
    'percusion' => [
        // Ninguna fuente respalda 2003; La Nación (17/12/2000) confirma 1998.
        'nota_anio' => ['Algunas ediciones figuran como de 2003.', null],
    ],
    'viejos-dioses' => [
        'descripcion' => [
            'Segundo disco de la banda, publicado en diciembre de 2001. Catorce temas, entre ellos En tres candombe, Un saludo a Cuba y Carioca.',
            'Segundo disco de la banda, publicado en diciembre de 2001. Catorce temas, entre ellos En tres candombe, Un saludo a Cuba y Carioca. En diciembre de 2000 La Nación anticipaba el disco nuevo de la banda con Jaime Roos, Ariel Prat, Pablo Guerra, el trompetista Carlos Huerta y Peteco Carabajal en violín como invitados (que sea este disco se deduce por las fechas).',
        ],
        'fuentes' => [
            [
                ['etiqueta' => 'Spotify — Viejos Dioses', 'url' => 'https://open.spotify.com/album/01HKLOCsVb7M8NR2p67OEZ'],
                ['etiqueta' => 'Tagtuner — Viejos Dioses', 'url' => 'https://www.tagtuner.com/music/albums/La-Chilinga/Viejos-Dioses/album-v264e74'],
            ],
            [
                ['etiqueta' => 'Spotify — Viejos Dioses', 'url' => 'https://open.spotify.com/album/01HKLOCsVb7M8NR2p67OEZ'],
                ['etiqueta' => 'Tagtuner — Viejos Dioses', 'url' => 'https://www.tagtuner.com/music/albums/La-Chilinga/Viejos-Dioses/album-v264e74'],
                ['etiqueta' => 'La Nación — La Chilinga, banda y escuela (17/12/2000)', 'url' => 'https://www.lanacion.com.ar/espectaculos/la-chilinga-banda-y-escuela-nid45311/'],
            ],
        ],
    ],
    'munequitos-del-tambor' => [
        'nota_anio' => [
            'Otras fuentes lo fechan en 2005.',
            'Fechado en 2004; en diciembre de 2006 la prensa todavía lo presentaba como disco nuevo.',
        ],
        'descripcion' => [
            'Tercer disco de la banda. Dieciséis temas que van de los cantos a los orixás (Oxum, Yemanyá, Oxosi) al afrotango, con Muñequitos I y Muñequitos II.',
            'Tercer disco de la banda, de concepto afrolatinoamericano: ritmos "de Colombia, de Perú, Uruguay, Brasil y Argentina" (Ciudad.com, 2006). Dieciséis temas que van de los cantos a los orixás (Oxum, Yemanyá, Oxosi) al afrotango, con Muñequitos I y Muñequitos II.',
        ],
        'fuentes' => [
            [
                ['etiqueta' => 'CMTV — Muñequitos del tambor', 'url' => 'https://www.cmtv.com.ar/discos_letras/letra.php?bnid=158&tmid=26093'],
            ],
            [
                ['etiqueta' => 'CMTV — Muñequitos del tambor', 'url' => 'https://www.cmtv.com.ar/discos_letras/letra.php?bnid=158&tmid=26093'],
                ['etiqueta' => 'Ciudad.com — Papá chilingo (20/12/2006)', 'url' => 'https://www.ciudad.com.ar/espectaculos/papa-chilingo_329'],
            ],
        ],
    ],
    'raices' => [
        'descripcion' => [
            'Cuarto disco de la banda, grabado con cerca de doscientos alumnos de la escuela. Abre con Chinga Chilinga e incluye Makuta, Golpes y Cuando oigo sonar la caja.',
            'Cuarto disco de la banda, grabado por más de 250 alumnos de la escuela (otras fuentes dicen unos 200). "Es impresionante que el 90 por ciento de los que grabaron no son músicos profesionales", dijo Dani Buira. Abre con Chinga Chilinga e incluye Makuta, Golpes y Cuando oigo sonar la caja.',
        ],
        'datos' => [
            ['Cuarto disco de la banda', 'Grabado con cerca de 200 alumnos', '16 temas'],
            ['Cuarto disco de la banda', 'Grabado por más de 250 alumnos', 'El 90 % no eran músicos profesionales', '16 temas'],
        ],
        'fuentes' => [
            [
                ['etiqueta' => 'CritiqueBrainz — Raíces', 'url' => 'https://critiquebrainz.org/release-group/ec6871b9-83fb-3de7-a700-4fdcea411582'],
                ['etiqueta' => 'CMTV — La Chilinga', 'url' => 'https://www.cmtv.com.ar/discos_letras/show.php?banda=La_Chilinga&bnid=158'],
                ['etiqueta' => 'Wikipedia — La Chilinga', 'url' => 'https://es.wikipedia.org/wiki/La_Chilinga'],
            ],
            [
                ['etiqueta' => 'Ciudad.com — ¡Tambores a la calle! (22/08/2007)', 'url' => 'https://www.ciudad.com.ar/espectaculos/56466/%C2%A1tambores-la-calle'],
                ['etiqueta' => 'CritiqueBrainz — Raíces', 'url' => 'https://critiquebrainz.org/release-group/ec6871b9-83fb-3de7-a700-4fdcea411582'],
                ['etiqueta' => 'CMTV — La Chilinga', 'url' => 'https://www.cmtv.com.ar/discos_letras/show.php?banda=La_Chilinga&bnid=158'],
                ['etiqueta' => 'Wikipedia — La Chilinga', 'url' => 'https://es.wikipedia.org/wiki/La_Chilinga'],
            ],
        ],
    ],
    'banda-fantasma' => [
        'nota_anio' => [null, 'Last.fm lo lista dos veces, como «Banda Fantasma» y como «Fantasma»: el título oficial está sin confirmar.'],
    ],
];
