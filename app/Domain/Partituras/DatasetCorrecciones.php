<?php

namespace App\Domain\Partituras;

use App\Models\PartituraTranscripcion;
use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;

/**
 * Dataset de correcciones humanas: por cada transcripción terminada, lo que detectó el servicio
 * frente a lo que la escuela publicó al final. Sirve para medir el borrador y para entrenar el
 * clasificador propio (transcriptor/entrenamiento). Una línea JSON por toque corregido.
 */
class DatasetCorrecciones
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function registros(): iterable
    {
        $transcripciones = PartituraTranscripcion::query()
            ->where('estado', PartituraTranscripcion::LISTO)
            ->whereNotNull('borrador')
            ->with('programaRitmo')
            ->orderBy('id')
            ->cursor();

        foreach ($transcripciones as $t) {
            $toque = $t->programaRitmo;
            if (! $toque instanceof ProgramaRitmo) {
                continue;
            }
            $publicada = PartituraScore::normalizar($toque->mediosNormalizados()['partitura_score'] ?? null);
            if ($publicada === null) {
                continue;
            }
            $borrador = $t->borrador ?? [];
            $golpesBorrador = self::golpesDe($borrador);
            $golpesPublicados = self::golpesDe($publicada);
            if ($golpesBorrador === $golpesPublicados) {
                continue; // la escuela publicó tal cual: no aporta correcciones
            }

            yield [
                'transcripcion_id' => $t->id,
                'toque' => $toque->slug,
                'audio' => $t->audio_path,
                'tempo_detectado' => $t->resultado['tempo'] ?? null,
                'golpes_detectados' => $t->resultado['golpes'] ?? [],
                'golpes_borrador' => $golpesBorrador,
                'golpes_publicados' => $golpesPublicados,
                'coinciden' => count(array_intersect_key(self::claves($golpesBorrador), self::claves($golpesPublicados))),
            ];
        }
    }

    /**
     * Golpes de la partitura en orden, con compás (ordinal sin repeticiones) y tick dentro del compás.
     *
     * @param  array<string, mixed>  $score
     * @return list<array{c: int, inst: string, t: int, golpe: string}>
     */
    public static function golpesDe(array $score): array
    {
        $out = [];
        $compas = 0;
        foreach ($score['sections'] ?? [] as $sec) {
            foreach ($sec['measures'] ?? [] as $m) {
                $compas++;
                foreach ($m['voces'] ?? [] as $inst => $voz) {
                    $t = 0;
                    foreach ($voz as $n) {
                        if (empty($n['rest'])) {
                            $out[] = ['c' => $compas, 'inst' => (string) $inst, 't' => $t, 'golpe' => (string) ($n['stroke'] ?? 'nota')];
                        }
                        $t += PartituraScore::ticksDeNota($n);
                    }
                }
            }
        }
        usort($out, fn ($a, $b) => [$a['c'], $a['t'], $a['inst']] <=> [$b['c'], $b['t'], $b['inst']]);

        return $out;
    }

    /** @param list<array{c: int, inst: string, t: int, golpe: string}> $golpes */
    private static function claves(array $golpes): array
    {
        $claves = [];
        foreach ($golpes as $g) {
            $claves[$g['c'].'|'.$g['inst'].'|'.$g['t'].'|'.$g['golpe']] = true;
        }

        return $claves;
    }
}
