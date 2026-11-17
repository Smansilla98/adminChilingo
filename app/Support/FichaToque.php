<?php

namespace App\Support;

use App\Models\ProgramaRitmo;

/**
 * Ficha del toque, redactada por la escuela: de dónde sale, compás, tempo sugerido, instrumentos
 * que tocan y una nota corta. Vive en medios.ficha y se muestra en la web y en la app.
 */
final class FichaToque
{
    public const MAX_ORIGEN = 120;

    public const MAX_NOTA = 500;

    /**
     * @return array{origen: ?string, compas: ?string, tempo_sugerido: ?int, instrumentos: list<string>, nota: ?string}|null
     */
    public static function normalizar(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $origen = mb_substr(trim((string) ($raw['origen'] ?? '')), 0, self::MAX_ORIGEN);
        $compasRaw = trim((string) ($raw['compas'] ?? ''));
        $compas = preg_match('#^\d{1,2}/(2|4|8|16)$#', $compasRaw) === 1 ? $compasRaw : null;
        $tempo = isset($raw['tempo_sugerido']) && is_numeric($raw['tempo_sugerido'])
            ? max(30, min(260, (int) round((float) $raw['tempo_sugerido'])))
            : null;
        $instrumentos = [];
        foreach ((array) ($raw['instrumentos'] ?? []) as $id) {
            $id = (string) $id;
            if ($id !== 'todos' && array_key_exists($id, PartituraScore::INSTRUMENTOS) && ! in_array($id, $instrumentos, true)) {
                $instrumentos[] = $id;
            }
        }
        $nota = mb_substr(trim((string) ($raw['nota'] ?? '')), 0, self::MAX_NOTA);

        if ($origen === '' && $compas === null && $tempo === null && $instrumentos === [] && $nota === '') {
            return null;
        }

        return [
            'origen' => $origen !== '' ? $origen : null,
            'compas' => $compas,
            'tempo_sugerido' => $tempo,
            'instrumentos' => $instrumentos,
            'nota' => $nota !== '' ? $nota : null,
        ];
    }

    /** Guarda la ficha en el toque (sin tocar la partitura ni crear versiones). */
    public static function guardar(ProgramaRitmo $toque, array $raw): ?array
    {
        $medios = ProgramaRitmoMedios::normalizar($toque->medios);
        $medios['ficha'] = self::normalizar($raw);
        $toque->update(['medios' => $medios]);

        return $medios['ficha'];
    }
}
