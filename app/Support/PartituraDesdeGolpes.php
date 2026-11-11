<?php

namespace App\Support;

/**
 * Golpes detectados en una grabación → partitura v5.
 *
 * Entrada (lo que devuelve el servicio transcriptor):
 *   { tempo, ts: {num, den}, beats: [seg...], downbeats: [seg...],
 *     golpes: [{ t, inst, golpe, vel, conf }] }
 *
 * Cuantiza por pulsos, no por reloj: la posición de cada golpe es la fracción entre
 * los dos pulsos vecinos, así la partitura no se desarma si el grupo acelera.
 * Cada pulso se escribe en semicorcheas o en tresillo (el que mejor explique sus golpes).
 * El compás arranca en el primer downbeat; los compases iguales seguidos se juntan
 * en una parte con repeatX. Los compases con golpes dudosos quedan marcados «Revisar:».
 */
final class PartituraDesdeGolpes
{
    public const MAX_COMPASES = 64;

    public const MAX_SECCIONES = 24;

    public const AUTOR = 'Transcripción automática';

    /** Un tresillo se elige solo si explica claramente mejor los golpes del pulso. */
    private const VENTAJA_TRESILLO = 0.6;

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>|null  $audioRef  grabación de origen (se le cargan los pulsos)
     * @return array{score: array<string, mixed>|null, dudosos: int, compases: int, truncado: bool, golpes: int}
     */
    public static function convertir(array $resultado, string $titulo, ?array $audioRef = null, float $confMinima = 0.5): array
    {
        $vacio = ['score' => null, 'dudosos' => 0, 'compases' => 0, 'truncado' => false, 'golpes' => 0];
        $num = max(1, min(12, (int) ($resultado['ts']['num'] ?? 4)));
        $den = (int) ($resultado['ts']['den'] ?? 4);
        $den = in_array($den, [2, 4, 8, 16], true) ? $den : 4;
        $ts = ['num' => $num, 'den' => $den];
        $porPulso = self::ticksDePulso($ts);
        $pulsosCompas = max(1, intdiv(PartituraScore::ticksDeCompas($ts), $porPulso));
        $tempo = max(30, min(260, (int) round((float) ($resultado['tempo'] ?? 100))));

        $golpes = [];
        foreach (is_array($resultado['golpes'] ?? null) ? $resultado['golpes'] : [] as $g) {
            if (! is_array($g) || ! is_numeric($g['t'] ?? null)) {
                continue;
            }
            $inst = (string) ($g['inst'] ?? '');
            if (! array_key_exists($inst, PartituraScore::INSTRUMENTOS) || $inst === 'todos') {
                continue;
            }
            $golpes[] = [
                't' => (float) $g['t'],
                'inst' => $inst,
                'golpe' => in_array($g['golpe'] ?? null, PartituraScore::GOLPES, true) ? (string) $g['golpe'] : (PartituraScore::GOLPE_DEFAULT[$inst] ?? 'nota'),
                'vel' => max(1, min(127, (int) ($g['vel'] ?? 96))),
                'conf' => max(0.0, min(1.0, (float) ($g['conf'] ?? 1))),
            ];
        }
        if ($golpes === []) {
            return $vacio;
        }
        usort($golpes, fn ($a, $b) => $a['t'] <=> $b['t']);

        $beats = self::pulsos($resultado, $tempo, $porPulso, $golpes);
        // Primer tiempo: el downbeat más temprano que tenga un pulso cerca.
        $b0 = 0;
        $downbeats = array_values(array_filter((array) ($resultado['downbeats'] ?? []), 'is_numeric'));
        if ($downbeats !== []) {
            sort($downbeats);
            $b0 = self::indiceMasCercano($beats, (float) $downbeats[0]);
        }
        $beats = array_slice($beats, $b0);
        $ultimo = end($golpes)['t'];
        while (count($beats) < 2 || end($beats) < $ultimo + 0.01) {
            $n = count($beats);
            $paso = $n >= 2 ? $beats[$n - 1] - $beats[$n - 2] : 60 / $tempo * ($porPulso / PartituraScore::TPQ);
            $beats[] = ($n ? $beats[$n - 1] : 0) + max(0.05, $paso);
        }

        // Golpes por pulso: [indice de pulso => list<[fraccion, golpe]>]
        $porBeat = [];
        $mitad = 0.5 * ($beats[1] - $beats[0]);
        foreach ($golpes as $g) {
            if ($g['t'] < $beats[0] - min(0.08, $mitad / 2)) {
                continue; // anacrusa o conteo antes del primer tiempo
            }
            $p = self::pulsoDe($beats, $g['t']);
            $i = (int) floor($p + 1e-9);
            $porBeat[$i][] = [$p - $i, $g];
        }

        // Cuantización: posición en ticks dentro del compás.
        $compases = []; // [m => [inst => [tick => golpe]]]
        $tresillos = []; // [m => [pulsoEnCompas => true]]
        $dudososPorCompas = [];
        $totalGolpes = 0;
        ksort($porBeat);
        foreach ($porBeat as $i => $lista) {
            $pasos = intdiv($porPulso, 12);
            $errRecto = 0.0;
            $errTres = 0.0;
            foreach ($lista as [$f]) {
                $errRecto += abs($f - round($f * $pasos) / $pasos);
                $errTres += abs($f - round($f * 3) / 3);
            }
            $tresillo = $porPulso === PartituraScore::TPQ && count($lista) >= 2 && $errTres < $errRecto * self::VENTAJA_TRESILLO;
            foreach ($lista as [$f, $g]) {
                $k = $tresillo ? (int) round($f * 3) : (int) round($f * $pasos);
                $div = $tresillo ? 3 : $pasos;
                $pulso = $i;
                if ($k >= $div) { // redondeó al pulso siguiente
                    $k = 0;
                    $pulso++;
                }
                $m = intdiv($pulso, $pulsosCompas);
                $pc = $pulso % $pulsosCompas;
                if ($k === 0) {
                    $tresilloAca = $tresillos[$m][$pc] ?? false;
                    $tick = $pc * $porPulso;
                } else {
                    $tresilloAca = $tresillo;
                    $tick = $pc * $porPulso + ($tresillo ? $k * 16 : $k * 12);
                }
                if ($tresilloAca && $k !== 0) {
                    $tresillos[$m][$pc] = true;
                }
                $prev = $compases[$m][$g['inst']][$tick] ?? null;
                if ($prev === null || $g['vel'] > $prev['vel']) {
                    $compases[$m][$g['inst']][$tick] = $g;
                }
                if ($g['conf'] < $confMinima) {
                    $dudososPorCompas[$m] = ($dudososPorCompas[$m] ?? 0) + 1;
                }
                $totalGolpes++;
            }
        }
        if ($compases === []) {
            return $vacio;
        }

        $instrumentos = [];
        foreach ($compases as $porInst) {
            foreach (array_keys($porInst) as $inst) {
                $instrumentos[$inst] = true;
            }
        }
        // Orden del cuadernillo.
        $ids = array_values(array_filter(array_keys(PartituraScore::INSTRUMENTOS), fn ($id) => isset($instrumentos[$id])));

        $nCompases = max(array_keys($compases)) + 1;
        $medidas = [];
        for ($m = 0; $m < $nCompases; $m++) {
            $voces = [];
            foreach ($ids as $inst) {
                $voces[$inst] = self::voz($compases[$m][$inst] ?? [], $tresillos[$m] ?? [], $pulsosCompas, $porPulso, $m.$inst);
            }
            $medidas[] = ['voces' => $voces, 'dudosos' => $dudososPorCompas[$m] ?? 0, 'firma' => self::firma($compases[$m] ?? [])];
        }

        [$secciones, $truncado] = self::agrupar($medidas);
        $dudosos = array_sum($dudososPorCompas);

        $raw = [
            'version' => PartituraScore::VERSION,
            'title' => $titulo,
            'autor' => self::AUTOR,
            'tempo' => $tempo,
            'timeSignature' => $ts,
            'instruments' => array_map(fn ($id) => ['id' => $id, 'volume' => 0.9], $ids),
            'sections' => $secciones,
        ];
        if ($audioRef !== null) {
            $raw['audio_ref'] = array_merge($audioRef, [
                'beats' => array_map(fn ($b) => round($b, 3), array_slice($beats, 0, PartituraScore::AUDIO_MAX_BEATS)),
                'offset_seg' => round($beats[0], 3),
                'tempo_origen' => $tempo,
            ]);
        }

        return [
            'score' => PartituraScore::normalizar($raw),
            'dudosos' => $dudosos,
            'compases' => $nCompases,
            'truncado' => $truncado,
            'golpes' => $totalGolpes,
        ];
    }

    /** @param array{num:int,den:int} $ts */
    public static function ticksDePulso(array $ts): int
    {
        if ($ts['den'] === 8 && $ts['num'] % 3 === 0 && $ts['num'] > 3) {
            return 72;
        }

        return (int) round(PartituraScore::TPQ * 4 / $ts['den']);
    }

    /**
     * Pulsos de la grabación: los del servicio, o una grilla al tempo desde el primer golpe.
     *
     * @param  array<string, mixed>  $resultado
     * @param  list<array<string, mixed>>  $golpes
     * @return list<float>
     */
    private static function pulsos(array $resultado, int $tempo, int $porPulso, array $golpes): array
    {
        $beats = [];
        foreach ((array) ($resultado['beats'] ?? []) as $b) {
            if (is_numeric($b) && (float) $b >= 0) {
                $beats[] = (float) $b;
            }
        }
        sort($beats);
        $limpios = [];
        foreach ($beats as $b) {
            if ($limpios === [] || $b - end($limpios) > 0.05) {
                $limpios[] = $b;
            }
        }
        if (count($limpios) >= 2) {
            return $limpios;
        }
        $paso = 60 / $tempo * ($porPulso / PartituraScore::TPQ);
        $inicio = $limpios[0] ?? $golpes[0]['t'];

        return [$inicio, $inicio + $paso];
    }

    /** @param list<float> $beats */
    private static function pulsoDe(array $beats, float $t): float
    {
        $n = count($beats);
        if ($t <= $beats[0]) {
            return ($t - $beats[0]) / ($beats[1] - $beats[0]);
        }
        $lo = 0;
        $hi = $n - 1;
        while ($hi - $lo > 1) {
            $mid = intdiv($lo + $hi, 2);
            if ($beats[$mid] <= $t) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        return $lo + ($t - $beats[$lo]) / ($beats[$hi] - $beats[$lo]);
    }

    /** @param list<float> $beats */
    private static function indiceMasCercano(array $beats, float $t): int
    {
        $mejor = 0;
        foreach ($beats as $i => $b) {
            if (abs($b - $t) < abs($beats[$mejor] - $t)) {
                $mejor = $i;
            }
        }

        return $mejor;
    }

    /**
     * Notas de una voz en un compás, pulso por pulso (más fácil de leer que figuras que cruzan el pulso).
     *
     * @param  array<int, array<string, mixed>>  $porTick
     * @param  array<int, bool>  $tresillos
     * @return list<array<string, mixed>>
     */
    private static function voz(array $porTick, array $tresillos, int $pulsosCompas, int $porPulso, string $semilla): array
    {
        $notas = [];
        for ($p = 0; $p < $pulsosCompas; $p++) {
            $desde = $p * $porPulso;
            $enPulso = array_filter($porTick, fn ($t) => $t >= $desde && $t < $desde + $porPulso, ARRAY_FILTER_USE_KEY);
            ksort($enPulso);
            if ($enPulso === []) {
                array_push($notas, ...PartituraScore::silenciosPara($porPulso));

                continue;
            }
            if (! empty($tresillos[$p])) {
                $tid = 't'.substr(md5($semilla.'-'.$p), 0, 8);
                for ($k = 0; $k < 3; $k++) {
                    $g = $enPulso[$desde + $k * 16] ?? null;
                    $notas[] = self::nota('8', $g, ['id' => $tid, 'num' => 3, 'den' => 2]);
                }

                continue;
            }
            $ticks = array_keys($enPulso);
            if ($ticks[0] > $desde) {
                array_push($notas, ...PartituraScore::silenciosPara($ticks[0] - $desde));
            }
            foreach ($ticks as $j => $t) {
                $fin = $ticks[$j + 1] ?? $desde + $porPulso;
                $largo = $fin - $t;
                [$dur, $dots, $ocupa] = self::figura($largo);
                $nota = self::nota($dur, $enPulso[$t]);
                $nota['dots'] = $dots;
                $notas[] = $nota;
                if ($largo > $ocupa) {
                    array_push($notas, ...PartituraScore::silenciosPara($largo - $ocupa));
                }
            }
        }

        return $notas;
    }

    /** @return array{0: string, 1: int, 2: int} figura más larga que entra en `$ticks` */
    private static function figura(int $ticks): array
    {
        foreach ([['q', 1, 72], ['q', 0, 48], ['8', 1, 36], ['8', 0, 24], ['16', 0, 12]] as $f) {
            if ($f[2] <= $ticks) {
                return $f;
            }
        }

        return ['16', 0, 12];
    }

    /**
     * @param  array<string, mixed>|null  $g
     * @param  array<string, mixed>|null  $tuplet
     * @return array<string, mixed>
     */
    private static function nota(string $dur, ?array $g, ?array $tuplet = null): array
    {
        if ($g === null) {
            return ['dur' => $dur, 'rest' => true, 'tuplet' => $tuplet];
        }

        return ['dur' => $dur, 'rest' => false, 'stroke' => $g['golpe'], 'vel' => $g['vel'], 'tuplet' => $tuplet];
    }

    /** @param array<string, array<int, array<string, mixed>>> $porInst */
    private static function firma(array $porInst): string
    {
        ksort($porInst);
        $partes = [];
        foreach ($porInst as $inst => $ticks) {
            ksort($ticks);
            foreach ($ticks as $t => $g) {
                // Velocidad agrupada de a 16: dos pasadas «iguales» difieren un poco en intensidad.
                $partes[] = $inst.'@'.$t.':'.$g['golpe'].':'.intdiv((int) $g['vel'], 16);
            }
        }

        return implode('|', $partes);
    }

    /**
     * Compases iguales seguidos → una parte con repeatX; el resto, partes de hasta 64 compases.
     *
     * @param  list<array{voces: array<string, mixed>, dudosos: int, firma: string}>  $medidas
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private static function agrupar(array $medidas): array
    {
        $secciones = [];
        $suelta = [];
        $cerrarSuelta = function (array $sueltos) use (&$secciones): void {
            foreach (array_chunk($sueltos, self::MAX_COMPASES) as $chunk) {
                $secciones[] = ['name' => 'Parte '.(count($secciones) + 1), 'repeatX' => 1, 'measures' => $chunk];
            }
        };
        $n = count($medidas);
        $i = 0;
        while ($i < $n) {
            $j = $i;
            while ($j + 1 < $n && $medidas[$j + 1]['firma'] === $medidas[$i]['firma']) {
                $j++;
            }
            $run = $j - $i + 1;
            $dudosos = array_sum(array_map(fn ($k) => $medidas[$k]['dudosos'], range($i, $j)));
            $compas = self::compas($medidas[$i]['voces'], $dudosos);
            if ($run >= 2 && $medidas[$i]['firma'] !== '') {
                $cerrarSuelta($suelta);
                $suelta = [];
                while ($run > 0) {
                    $veces = min(16, $run);
                    $secciones[] = ['name' => 'Parte '.(count($secciones) + 1), 'repeatX' => $veces, 'measures' => [$compas]];
                    $run -= $veces;
                }
            } else {
                for ($k = $i; $k <= $j; $k++) {
                    $suelta[] = self::compas($medidas[$k]['voces'], $medidas[$k]['dudosos']);
                }
            }
            $i = $j + 1;
        }
        $cerrarSuelta($suelta);

        $truncado = count($secciones) > self::MAX_SECCIONES;
        if ($truncado) {
            $secciones = array_slice($secciones, 0, self::MAX_SECCIONES);
            $ultima = &$secciones[self::MAX_SECCIONES - 1];
            $k = count($ultima['measures']) - 1;
            $ultima['measures'][$k]['texto'] = 'Revisar: la grabación sigue';
        }

        return [$secciones, $truncado];
    }

    /**
     * @param  array<string, mixed>  $voces
     * @return array<string, mixed>
     */
    private static function compas(array $voces, int $dudosos): array
    {
        return [
            'voces' => $voces,
            'texto' => $dudosos > 0 ? 'Revisar: '.$dudosos.($dudosos === 1 ? ' golpe dudoso' : ' golpes dudosos') : null,
        ];
    }
}
