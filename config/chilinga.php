<?php

return [
    /*
    | Edición colaborativa del programa y las partituras sin iniciar sesión (con nombre
    | declarado y límite de envíos). Es el comportamiento histórico. Con false, editar y
    | subir archivos exige una cuenta con el permiso partituras.admin.
    */
    'edicion_publica_programa' => filter_var(env('PROGRAMA_EDICION_PUBLICA', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Cobro de cuotas desde el sistema (CobroCuotasService).
    | Transferencia: los datos que se le muestran al alumno para que pague desde su banco o
    | billetera. Sin alias ni CBU, la opción no aparece.
    */
    // Si hay un volumen persistente para archivos (lo lee Estado del sistema).
    'almacenamiento_persistente' => filled(env('PERSISTENT_STORAGE_PATH')),

    'cobro' => [
        'alias' => env('COBRO_ALIAS'),
        'cbu' => env('COBRO_CBU'),
        'titular' => env('COBRO_TITULAR'),
        'cuit' => env('COBRO_CUIT'),
        'banco' => env('COBRO_BANCO'),
    ],
];
