<?php

return [
    /*
    | Edición colaborativa del programa y las partituras sin iniciar sesión (con nombre
    | declarado y límite de envíos). Es el comportamiento histórico. Con false, editar y
    | subir archivos exige una cuenta con el permiso partituras.admin.
    */
    'edicion_publica_programa' => filter_var(env('PROGRAMA_EDICION_PUBLICA', true), FILTER_VALIDATE_BOOLEAN),
];
