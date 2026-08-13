<?php

namespace App\Support;

/**
 * Modelo de partitura v4 (editor tipo MuseScore) — validación y normalización server-side.
 *
 * Espejo de resources/js/partitura/model.js. Unidad: ticks, TPQ = 48.
 */
class PartituraScore
{
    public const VERSION = 4;

    public const TPQ = 48;

    /** Duraciones válidas => ticks base. */
    public const DURACIONES = [
        'w' => self::TPQ * 4,
        'h' => self::TPQ * 2,
        'q' => self::TPQ,
        '8' => 24,
        '16' => 12,
        '32' => 6,
    ];

    /** Instrumentos disponibles (id => etiqueta). Espejo de instruments.js. */
    public const INSTRUMENTOS = [
        // Voz "Todos" del cuadernillo: unísono estricto en un solo pentagrama.
        // No es un instrumento real; al reproducir se expande a todos los del toque.
        'todos' => 'Todos',
        'surdo_grave' => 'Surdo Grave',
        'surdo_agudo' => 'Surdo Agudo',
        'surdo_medio' => 'Surdo Medio',
        'redoblante' => 'Redoblante',
        'repique' => 'Repique',
        'timbal' => 'Timbal',
        'agogo' => 'Agogó',
        'palmas' => 'Palmas',
    ];

    public const INSTRUMENTOS_DEFAULT = ['surdo_grave', 'surdo_agudo', 'surdo_medio', 'redoblante', 'repique', 'timbal'];

    /** Golpes válidos. */
    public const GOLPES = [
        'nota', 'acentuado', 'chapa', 'tapado', 'presionado', 'abierto', 'slap', 'palma', 'dedo', 'agudo', 'flam',
    ];

    public const DINAMICAS = ['pp', 'p', 'mp', 'mf', 'f', 'ff'];

    public const GOLPE_DEFAULT = [
        'timbal' => 'abierto',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function vacia(string $titulo = 'Toque nuevo', string $autor = ''): array
    {
        $ts = ['num' => 4, 'den' => 4];

        return [
            'version' => self::VERSION,
            'title' => $titulo,
            'autor' => $autor,
            'tempo' => 100,
            'timeSignature' => $ts,
            'instruments' => array_map(
                fn (string $id) => ['id' => $id, 'volume' => 0.9, 'mute' => false, 'solo' => false, 'visible' => true],
                self::INSTRUMENTOS_DEFAULT
            ),
            'sections' => [
                self::seccionVacia('Llamada', self::INSTRUMENTOS_DEFAULT, $ts, 1),
                self::seccionVacia('Toque', self::INSTRUMENTOS_DEFAULT, $ts, 2),
            ],
        ];
    }

    /**
     * @param  string[]  $instrumentos
     * @param  array{num:int,den:int}  $ts
     * @return array<string, mixed>
     */
    public static function seccionVacia(string $nombre, array $instrumentos, array $ts, int $compases = 1): array
    {
        $measures = [];
        for ($i = 0; $i < max(1, $compases); $i++) {
            $measures[] = self::compasVacio($instrumentos, $ts, $i);
        }

        return [
            'id' => 's'.substr(md5($nombre.$compases.microtime()), 0, 8),
            'name' => $nombre,
            'repeatX' => 1,
            'measures' => $measures,
        ];
    }

    /**
     * @param  string[]  $instrumentos
     * @param  array{num:int,den:int}  $ts
     * @return array<string, mixed>
     */
    public static function compasVacio(array $instrumentos, array $ts, int $i = 0): array
    {
        $voces = [];
        foreach ($instrumentos as $id) {
            $voces[$id] = self::silenciosPara(self::ticksDeCompas($ts));
        }

        return [
            'id' => 'm'.substr(md5($i.microtime().implode(',', $instrumentos)), 0, 8),
            'repeatBegin' => false,
            'repeatEnd' => false,
            'ending' => null,
            'texto' => null,
            'voces' => $voces,
        ];
    }

    /**
     * @param  array{num:int,den:int}  $ts
     */
    public static function ticksDeCompas(array $ts): int
    {
        $num = max(1, (int) ($ts['num'] ?? 4));
        $den = (int) ($ts['den'] ?? 4);
        $den = in_array($den, [2, 4, 8, 16], true) ? $den : 4;

        return (int) round(($num * self::TPQ * 4) / $den);
    }

    /**
     * @param  array<string, mixed>  $nota
     */
    public static function ticksDeNota(array $nota): int
    {
        $base = self::DURACIONES[$nota['dur'] ?? 'q'] ?? self::TPQ;
        $dots = max(0, min(2, (int) ($nota['dots'] ?? 0)));
        $t = $base;
        if ($dots === 1) {
            $t = $base * 1.5;
        }
        if ($dots === 2) {
            $t = $base * 1.75;
        }
        if (! empty($nota['tuplet']) && is_array($nota['tuplet'])) {
            $num = (int) ($nota['tuplet']['num'] ?? 0);
            $den = (int) ($nota['tuplet']['den'] ?? 0);
            if ($num > 0 && $den > 0) {
                $t = ($t * $den) / $num;
            }
        }

        return (int) round($t);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function silenciosPara(int $ticks): array
    {
        $escala = [
            ['w', 0, self::TPQ * 4],
            ['h', 1, self::TPQ * 3],
            ['h', 0, self::TPQ * 2],
            ['q', 1, 72],
            ['q', 0, self::TPQ],
            ['8', 1, 36],
            ['8', 0, 24],
            ['16', 0, 12],
            ['32', 0, 6],
        ];
        $out = [];
        $resto = max(0, $ticks);
        $guard = 0;
        while ($resto > 0 && $guard < 64) {
            $guard++;
            $paso = null;
            foreach ($escala as $e) {
                if ($e[2] <= $resto) {
                    $paso = $e;
                    break;
                }
            }
            if ($paso === null) {
                break;
            }
            $out[] = self::nota(['dur' => $paso[0], 'dots' => $paso[1], 'rest' => true]);
            $resto -= $paso[2];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return array<string, mixed>
     */
    public static function nota(array $attrs = []): array
    {
        return [
            'id' => (string) ($attrs['id'] ?? 'n'.substr(md5(uniqid('', true)), 0, 10)),
            'dur' => $attrs['dur'] ?? 'q',
            'dots' => (int) ($attrs['dots'] ?? 0),
            'rest' => (bool) ($attrs['rest'] ?? false),
            'stroke' => $attrs['stroke'] ?? 'nota',
            'dyn' => $attrs['dyn'] ?? null,
            'tuplet' => $attrs['tuplet'] ?? null,
        ];
    }

    /**
     * Normaliza cualquier entrada al modelo v4. Devuelve null si no hay nada usable.
     *
     * @return array<string, mixed>|null
     */
    public static function normalizar(mixed $raw): ?array
    {
        if (! is_array($raw) || empty($raw['sections']) || ! is_array($raw['sections'])) {
            return null;
        }

        $num = max(1, min(12, (int) ($raw['timeSignature']['num'] ?? 4)));
        $den = (int) ($raw['timeSignature']['den'] ?? 4);
        $den = in_array($den, [2, 4, 8, 16], true) ? $den : 4;
        $ts = ['num' => $num, 'den' => $den];
        $capacidad = self::ticksDeCompas($ts);

        $instrumentos = [];
        foreach (is_array($raw['instruments'] ?? null) ? $raw['instruments'] : [] as $cfg) {
            $id = is_array($cfg) ? (string) ($cfg['id'] ?? '') : (string) $cfg;
            if (! array_key_exists($id, self::INSTRUMENTOS) || isset($instrumentos[$id])) {
                continue;
            }
            $instrumentos[$id] = [
                'id' => $id,
                'volume' => round(min(1, max(0, (float) (is_array($cfg) ? ($cfg['volume'] ?? 0.9) : 0.9))), 2),
                'mute' => (bool) (is_array($cfg) ? ($cfg['mute'] ?? false) : false),
                'solo' => (bool) (is_array($cfg) ? ($cfg['solo'] ?? false) : false),
                'visible' => is_array($cfg) ? ($cfg['visible'] ?? true) !== false : true,
            ];
        }
        if ($instrumentos === []) {
            foreach (self::INSTRUMENTOS_DEFAULT as $id) {
                $instrumentos[$id] = ['id' => $id, 'volume' => 0.9, 'mute' => false, 'solo' => false, 'visible' => true];
            }
        }
        $ids = array_keys($instrumentos);

        $sections = [];
        foreach (array_slice($raw['sections'], 0, 24) as $si => $sec) {
            if (! is_array($sec)) {
                continue;
            }
            $measuresRaw = is_array($sec['measures'] ?? null) ? array_slice($sec['measures'], 0, 64) : [];
            $measures = [];
            foreach ($measuresRaw as $mi => $m) {
                if (! is_array($m)) {
                    continue;
                }
                $voces = [];
                foreach ($ids as $id) {
                    $voz = is_array($m['voces'][$id] ?? null) ? $m['voces'][$id] : [];
                    $voces[$id] = self::normalizarVoz($voz, $capacidad, $id);
                }
                $ending = isset($m['ending']) && in_array((int) $m['ending'], [1, 2, 3], true) ? (int) $m['ending'] : null;
                $texto = trim((string) ($m['texto'] ?? ''));
                $measures[] = [
                    'id' => (string) ($m['id'] ?? 'm'.$si.'_'.$mi),
                    'repeatBegin' => ! empty($m['repeatBegin']),
                    'repeatEnd' => ! empty($m['repeatEnd']),
                    'ending' => $ending,
                    'texto' => $texto !== '' ? mb_substr($texto, 0, 60) : null,
                    'voces' => $voces,
                ];
            }
            if ($measures === []) {
                $measures[] = self::compasVacio($ids, $ts);
            }
            $nombre = trim((string) ($sec['name'] ?? ''));
            $sections[] = [
                'id' => (string) ($sec['id'] ?? 's'.$si),
                'name' => $nombre !== '' ? mb_substr($nombre, 0, 40) : 'Parte '.($si + 1),
                'repeatX' => max(1, min(16, (int) ($sec['repeatX'] ?? 1))),
                'measures' => $measures,
            ];
        }

        if ($sections === []) {
            return null;
        }

        $out = [
            'version' => self::VERSION,
            'title' => mb_substr(trim((string) ($raw['title'] ?? 'Toque')), 0, 120) ?: 'Toque',
            'autor' => mb_substr(trim((string) ($raw['autor'] ?? '')), 0, 120),
            'tempo' => max(30, min(260, (int) ($raw['tempo'] ?? 100))),
            'timeSignature' => $ts,
            'instruments' => array_values($instrumentos),
            'sections' => $sections,
            'updated_at' => now()->toIso8601String(),
        ];

        $fuente = self::normalizarFuente($raw['fuente'] ?? null);
        if ($fuente !== null) {
            $out['fuente'] = $fuente;
        }

        return $out;
    }

    /**
     * Sello de origen: de qué archivo del cuadernillo v4 salió la partitura y con qué hash.
     * Lo usa el seeder para saber si lo guardado en la base quedó viejo.
     *
     * @return array{origen: string, hash: string}|null
     */
    public static function normalizarFuente(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $origen = mb_substr(trim((string) ($raw['origen'] ?? '')), 0, 80);
        $hash = preg_replace('/[^a-f0-9]/i', '', (string) ($raw['hash'] ?? ''));
        $hash = mb_substr((string) $hash, 0, 40);
        if ($origen === '' || $hash === '') {
            return null;
        }

        return ['origen' => $origen, 'hash' => $hash];
    }

    /** Hash del contenido de un JSON de partitura (fuente de verdad en el repo). */
    public static function hashDeFuente(string $contenido): string
    {
        return substr(sha1($contenido), 0, 16);
    }

    /**
     * @param  array<int, mixed>  $voz
     * @return array<int, array<string, mixed>>
     */
    private static function normalizarVoz(array $voz, int $capacidad, string $instId): array
    {
        $out = [];
        $acum = 0;
        foreach (array_slice($voz, 0, 256) as $n) {
            if (! is_array($n)) {
                continue;
            }
            $dur = array_key_exists((string) ($n['dur'] ?? ''), self::DURACIONES) ? (string) $n['dur'] : 'q';
            $rest = ! empty($n['rest']);
            $stroke = in_array((string) ($n['stroke'] ?? ''), self::GOLPES, true)
                ? (string) $n['stroke']
                : (self::GOLPE_DEFAULT[$instId] ?? 'nota');
            $tuplet = null;
            if (is_array($n['tuplet'] ?? null)) {
                $tnum = (int) ($n['tuplet']['num'] ?? 0);
                $tden = (int) ($n['tuplet']['den'] ?? 0);
                if ($tnum > 1 && $tden > 0) {
                    $tuplet = [
                        'id' => (string) ($n['tuplet']['id'] ?? 't'.substr(md5(uniqid('', true)), 0, 8)),
                        'num' => min(12, $tnum),
                        'den' => min(12, $tden),
                    ];
                }
            }
            $nota = self::nota([
                'id' => $n['id'] ?? null,
                'dur' => $dur,
                'dots' => max(0, min(2, (int) ($n['dots'] ?? 0))),
                'rest' => $rest,
                'stroke' => $rest ? 'nota' : $stroke,
                'dyn' => in_array((string) ($n['dyn'] ?? ''), self::DINAMICAS, true) ? (string) $n['dyn'] : null,
                'tuplet' => $tuplet,
            ]);
            $t = self::ticksDeNota($nota);
            if ($acum + $t > $capacidad) {
                continue;
            }
            $out[] = $nota;
            $acum += $t;
        }
        if ($acum < $capacidad) {
            $out = array_merge($out, self::silenciosPara($capacidad - $acum));
        }

        return $out;
    }

    /**
     * ¿La partitura tiene al menos un golpe (no solo silencios)?
     *
     * @param  array<string, mixed>|null  $score
     */
    public static function tieneGolpes(?array $score): bool
    {
        foreach ($score['sections'] ?? [] as $sec) {
            foreach ($sec['measures'] ?? [] as $m) {
                foreach ($m['voces'] ?? [] as $voz) {
                    foreach ($voz as $n) {
                        if (empty($n['rest'])) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $score
     * @return array{partes:int, compases:int, golpes:int, instrumentos:int}
     */
    public static function resumen(?array $score): array
    {
        $compases = 0;
        $golpes = 0;
        foreach ($score['sections'] ?? [] as $sec) {
            $compases += count($sec['measures'] ?? []);
            foreach ($sec['measures'] ?? [] as $m) {
                foreach ($m['voces'] ?? [] as $voz) {
                    foreach ($voz as $n) {
                        if (empty($n['rest'])) {
                            $golpes++;
                        }
                    }
                }
            }
        }

        return [
            'partes' => count($score['sections'] ?? []),
            'compases' => $compases,
            'golpes' => $golpes,
            'instrumentos' => count($score['instruments'] ?? []),
        ];
    }
}
