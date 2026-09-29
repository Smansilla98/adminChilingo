<?php

namespace App\Support;

/**
 * Catálogo de one-shots del reproductor. El nombre es el contrato:
 * reemplazar el WAV (misma ruta) cambia el timbre sin tocar la app.
 *
 * Espejo de MAPA_SAMPLES + ARTICULACION_SAMPLE en instruments.js.
 */
class PartituraMuestras
{
    /**
     * archivo => [instrumento, golpe del modelo].
     * El golpe pleno se llama `nota` en el modelo y `normal` en el archivo.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const ARCHIVOS = [
        'surdo_grave_normal.wav' => ['surdo_grave', 'nota'],
        'surdo_grave_chapa.wav' => ['surdo_grave', 'chapa'],
        'surdo_grave_tapado.wav' => ['surdo_grave', 'tapado'],
        'surdo_medio_normal.wav' => ['surdo_medio', 'nota'],
        'surdo_medio_chapa.wav' => ['surdo_medio', 'chapa'],
        'surdo_medio_tapado.wav' => ['surdo_medio', 'tapado'],
        'surdo_agudo_normal.wav' => ['surdo_agudo', 'nota'],
        'surdo_agudo_chapa.wav' => ['surdo_agudo', 'chapa'],
        'surdo_agudo_tapado.wav' => ['surdo_agudo', 'tapado'],
        'redoblante_normal.wav' => ['redoblante', 'nota'],
        'redoblante_acentuado.wav' => ['redoblante', 'acentuado'],
        'redoblante_chapa.wav' => ['redoblante', 'chapa'],
        'redoblante_agudo.wav' => ['redoblante', 'agudo'],
        'repique_normal.wav' => ['repique', 'nota'],
        'repique_acentuado.wav' => ['repique', 'acentuado'],
        'repique_chapa.wav' => ['repique', 'chapa'],
        'repique_agudo.wav' => ['repique', 'agudo'],
        'timbal_abierto.wav' => ['timbal', 'abierto'],
        'timbal_slap.wav' => ['timbal', 'slap'],
        'timbal_palma.wav' => ['timbal', 'palma'],
        'timbal_presionado.wav' => ['timbal', 'presionado'],
        'timbal_dedo.wav' => ['timbal', 'dedo'],
        'agogo_normal.wav' => ['agogo', 'nota'],
        'agogo_acentuado.wav' => ['agogo', 'acentuado'],
        'agogo_tapado.wav' => ['agogo', 'tapado'],
        'palmas_normal.wav' => ['palmas', 'nota'],
        'palmas_acentuado.wav' => ['palmas', 'acentuado'],
    ];

    /**
     * @return list<array{instrumento: string, golpe: string, archivo: string, presente: bool, bytes: int, mtime: int}>
     */
    public static function listar(): array
    {
        $out = [];
        foreach (self::ARCHIVOS as $archivo => [$instrumento, $golpe]) {
            $ruta = self::ruta($archivo);
            $out[] = [
                'instrumento' => $instrumento,
                'golpe' => $golpe,
                'archivo' => $archivo,
                'presente' => $ruta !== null,
                'bytes' => $ruta !== null ? (int) filesize($ruta) : 0,
                'mtime' => $ruta !== null ? (int) filemtime($ruta) : 0,
            ];
        }

        return $out;
    }

    public static function ruta(string $archivo): ?string
    {
        if (! isset(self::ARCHIVOS[$archivo])) {
            return null;
        }
        $path = public_path('sounds/perc/'.$archivo);
        if (! is_file($path)) {
            return null;
        }
        $real = realpath($path);
        $dir = realpath(public_path('sounds/perc'));
        if ($real === false || $dir === false || ! str_starts_with($real, $dir.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }
}
