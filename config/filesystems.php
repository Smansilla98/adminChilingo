<?php

$s3 = [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION', 'auto'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'),
    'use_path_style_endpoint' => (bool) env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    'throw' => false,
    'report' => false,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Comprobantes de pago (PDF, JPG, PNG; persistente en Railway si se configura PERSISTENT_STORAGE_PATH).
         * En prod, seteá PERSISTENT_STORAGE_PATH a un volumen (ej: /data/storage).
         */
        'comprobantes' => [
            'driver' => 'local',
            'root' => env('PERSISTENT_STORAGE_PATH', storage_path('app/persistent')),
            'throw' => false,
            'report' => false,
        ],

        /*
         * S3 o compatible (Cloudflare R2, Backblaze B2, MinIO). Para R2:
         *   AWS_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
         *   AWS_DEFAULT_REGION=auto  AWS_USE_PATH_STYLE_ENDPOINT=true  AWS_BUCKET=<bucket>
         *   AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY = token de API de R2 (permiso de lectura y escritura del bucket).
         * Lo usan la copia externa de backups (RESPALDO_DISCO_EXTERNO=s3) y el disco `archivos` (ARCHIVOS_DISCO=s3).
         */
        's3' => $s3,

        /*
         * Archivos de usuarios compartidos entre servicios (web, worker, transcriptor de audio...).
         * ARCHIVOS_DISCO=local (default): carpeta `archivos/` dentro del volumen persistente; solo la ve
         *   el servicio que tiene montado el volumen.
         * ARCHIVOS_DISCO=s3: prefijo `archivos/` en el bucket; lo ven todos los servicios con las mismas AWS_*.
         * Usar siempre Storage::disk('archivos'), nunca rutas absolutas. Los archivos existentes no se migraron.
         */
        'archivos' => env('ARCHIVOS_DISCO', 'local') === 's3'
            ? ['root' => env('ARCHIVOS_PREFIJO', 'archivos'), 'visibility' => 'private', 'throw' => true] + $s3
            : [
                'driver' => 'local',
                'root' => rtrim((string) env('PERSISTENT_STORAGE_PATH', storage_path('app/persistent')), '/').'/archivos',
                'throw' => true,
                'report' => false,
            ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
