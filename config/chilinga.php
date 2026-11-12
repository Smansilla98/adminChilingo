<?php

return [
    /*
    | Edición del programa y las partituras. Por defecto (false) editar y subir archivos
    | exige una cuenta con el permiso partituras.edit (profesores, coordinación,
    | dirección) o partituras.admin; el autor queda con el nombre de la cuenta.
    | Con true vuelve el comportamiento histórico: cualquier cuenta edita.
    */
    'edicion_publica_programa' => filter_var(env('PROGRAMA_EDICION_PUBLICA', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Formulario público /pagar-cuota/comprobante: mandar el comprobante de una cuota sin
    | cuenta, buscando al alumno por DNI (solo muestra iniciales). La app y la web con
    | cuenta ya cubren el mismo flujo; con false el formulario queda cerrado y muestra
    | cómo entrar con la cuenta.
    */
    'formulario_publico_comprobantes' => filter_var(env('ACTIVAR_FORMULARIO_PUBLICO_COMPROBANTES', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Registro de alumnos desde la app o la web: además de DNI y fecha de nacimiento, pedir
    | el código de alta que genera la escuela. Valor inicial: se cambia en Configuración > Cuentas.
    */
    'registro_requiere_codigo' => filter_var(env('REGISTRO_REQUIERE_CODIGO', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Content-Security-Policy (app/Http/Middleware/SecurityHeaders.php). Con false (por
    | defecto) se aplica la política mínima (frame-ancestors, object-src, base-uri) y la
    | completa va como Report-Only. Con true la completa pasa a obligatoria.
    */
    'csp_estricta' => filter_var(env('CSP_ESTRICTA', false), FILTER_VALIDATE_BOOLEAN),

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

    /*
    | Mensajes a grupos (difusión). WhatsApp tiene costo por mensaje: desde esta cantidad
    | de destinatarios con teléfono se pide confirmar, y nunca se mandan más que el máximo.
    */
    'difusion' => [
        'whatsapp_confirmar_desde' => (int) env('DIFUSION_WHATSAPP_CONFIRMAR', 25),
        'whatsapp_maximo' => (int) env('DIFUSION_WHATSAPP_MAXIMO', 300),
    ],
];
