<?php

/*
| Servicio de transcripción automática de percusión (carpeta transcriptor/ del repo).
| Sin TRANSCRIPTOR_URL la función queda apagada y la interfaz lo explica.
*/
return [
    'url' => env('TRANSCRIPTOR_URL'),
    // Secreto compartido: firma HMAC-SHA256 de los avisos del servicio y de los pedidos.
    'secreto' => env('TRANSCRIPTOR_SECRETO'),
    'timeout' => (int) env('TRANSCRIPTOR_TIMEOUT', 20),
    // Horas que vale el enlace firmado para que el servicio baje la grabación.
    'horas_enlace' => (int) env('TRANSCRIPTOR_HORAS_ENLACE', 3),
    // Debajo de esta confianza, el compás queda marcado «Revisar:».
    'confianza_minima' => (float) env('TRANSCRIPTOR_CONFIANZA_MINIMA', 0.5),
    // Separar voces con Demucs antes de transcribir (lo hace el servicio; pesado).
    'separar_fuentes' => (bool) env('TRANSCRIPTOR_SEPARAR_FUENTES', false),
];
