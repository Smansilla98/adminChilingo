<?php

/*
 * Operación en producción: copias de seguridad fuera del servidor y avisos de "sigo vivo".
 * Todo es opcional: si una variable no está, esa parte queda apagada (y Estado del sistema lo avisa).
 * Ver docs/DEPLOY_RAILWAY.md y docs/ROLLBACK.md.
 */
return [

    'respaldo' => [
        // Disco de config/filesystems.php donde se sube una copia cifrada de cada backup (ej. "s3", que sirve para R2).
        'disco_externo' => env('RESPALDO_DISCO_EXTERNO'),

        // Clave dedicada para cifrar la copia externa (NO es APP_KEY). Sin clave no se sube nada:
        // el backup tiene datos personales y no sale del servidor sin cifrar.
        'clave' => env('RESPALDO_CLAVE'),

        // Carpeta dentro del disco externo y cuántas copias se guardan ahí (una por día).
        'carpeta_externa' => env('RESPALDO_CARPETA_EXTERNA', 'respaldos'),
        'conservar_externo' => (int) env('RESPALDO_EXTERNO_CONSERVAR', 30),
    ],

    // URLs de monitoreo tipo "heartbeat" (Healthchecks.io, Better Stack, Cronitor...).
    // Si el ping deja de llegar, el servicio de monitoreo avisa.
    'heartbeat' => [
        'scheduler' => env('HEARTBEAT_URL_SCHEDULER'),
        'scheduler_minutos' => (int) env('HEARTBEAT_SCHEDULER_MINUTOS', 10),
        'respaldo' => env('HEARTBEAT_URL_RESPALDO'),
    ],

];
