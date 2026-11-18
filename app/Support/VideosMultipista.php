<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Videos multipista de un toque: uno por instrumento o voz, grabados por separado y vistos en
 * simultáneo. Viven en medios.videos_multipista (archivos subidos). No son los links de
 * videos_base / videos_grupo.
 *
 * Forma de cada video: {id, instrumento, titulo, archivo, offset_seg, orden}
 *   - instrumento: una clave de PartituraScore::INSTRUMENTOS (sin «todos») o «voz».
 *   - offset_seg: cuánto después del inicio del grupo arranca este video (negativo = antes).
 */
final class VideosMultipista
{
    public const MAX = 6;

    public const CARPETA = 'partituras/videos';

    public const EXTENSIONES = ['mp4', 'webm', 'mov'];

    /** Tope de subida en KB (100 MB). */
    public const MAX_KB = 102400;

    public const OFFSET_LIMITE = 10.0;

    public const INSTRUMENTO_VOZ = 'voz';

    /** @return array<int, array{id: string, instrumento: string, titulo: string, archivo: string, offset_seg: float, orden: int}> */
    public static function normalizar(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        $ids = [];
        foreach ($raw as $v) {
            if (! is_array($v)) {
                continue;
            }
            $archivo = (string) ($v['archivo'] ?? '');
            if (preg_match('#^'.preg_quote(self::CARPETA, '#').'/[0-9]+-[a-z0-9]{16}\.('.implode('|', self::EXTENSIONES).')$#', $archivo) !== 1) {
                continue;
            }
            $id = (string) ($v['id'] ?? '');
            if (preg_match('/^v[a-z0-9]{8}$/', $id) !== 1 || in_array($id, $ids, true)) {
                $id = 'v'.Str::lower(Str::random(8));
            }
            $ids[] = $id;
            $instrumento = (string) ($v['instrumento'] ?? self::INSTRUMENTO_VOZ);
            if ($instrumento !== self::INSTRUMENTO_VOZ && ($instrumento === 'todos' || ! array_key_exists($instrumento, PartituraScore::INSTRUMENTOS))) {
                $instrumento = self::INSTRUMENTO_VOZ;
            }
            $titulo = mb_substr(trim((string) ($v['titulo'] ?? '')), 0, 80);
            $offset = is_numeric($v['offset_seg'] ?? null) ? (float) $v['offset_seg'] : 0.0;
            $out[] = [
                'id' => $id,
                'instrumento' => $instrumento,
                'titulo' => $titulo !== '' ? $titulo : self::nombreDe($instrumento),
                'archivo' => $archivo,
                'offset_seg' => round(max(-self::OFFSET_LIMITE, min(self::OFFSET_LIMITE, $offset)), 3),
                'orden' => (int) ($v['orden'] ?? 0),
            ];
        }
        usort($out, fn ($a, $b) => $a['orden'] <=> $b['orden']);
        $out = array_slice($out, 0, self::MAX);
        foreach ($out as $i => $v) {
            $out[$i]['orden'] = $i + 1;
        }

        return $out;
    }

    public static function nombreDe(string $instrumento): string
    {
        return $instrumento === self::INSTRUMENTO_VOZ
            ? 'Voz'
            : (PartituraScore::INSTRUMENTOS[$instrumento] ?? $instrumento);
    }

    /** Extensión permitida a partir del nombre de archivo, o null. */
    public static function extension(string $nombre): ?string
    {
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSIONES, true) ? $ext : null;
    }
}
