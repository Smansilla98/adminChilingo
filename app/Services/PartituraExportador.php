<?php

namespace App\Services;

use App\Support\PartituraScore;

/**
 * Exporta la partitura (modelo v5) a MIDI y MusicXML desde el servidor.
 *
 * Equivalente a resources/js/partitura/exporters.js, para clientes que no tienen el
 * editor web (la app la baja y la comparte). Mismo mapeo de golpes a notas General MIDI,
 * canal 10 (percusión), misma resolución (TPQ = 48).
 */
class PartituraExportador
{
    /** Nota GM base por instrumento (instruments.js → INSTRUMENTOS[].midi). */
    public const MIDI_BASE = [
        'todos' => 38, 'surdo_grave' => 87, 'surdo_agudo' => 43, 'surdo_medio' => 41,
        'redoblante' => 38, 'repique' => 40, 'timbal' => 66, 'agogo' => 67, 'palmas' => 39,
    ];

    /** Nota GM por (instrumento, golpe). Espejo de MIDI_POR_GOLPE en instruments.js. */
    public const MIDI_POR_GOLPE = [
        'timbal' => ['abierto' => 66, 'slap' => 65, 'tapado' => 64, 'palma' => 39, 'dedo' => 62, 'presionado' => 64, 'acentuado' => 66, 'nota' => 66],
        'redoblante' => ['nota' => 38, 'acentuado' => 40, 'chapa' => 37, 'tapado' => 37, 'flam' => 38, 'agudo' => 43],
        'repique' => ['nota' => 40, 'acentuado' => 40, 'chapa' => 37, 'agudo' => 43, 'flam' => 40],
        'surdo_grave' => ['nota' => 87, 'acentuado' => 87, 'chapa' => 37, 'tapado' => 86, 'flam' => 87],
        'surdo_medio' => ['nota' => 41, 'acentuado' => 41, 'chapa' => 37, 'tapado' => 86, 'flam' => 41],
        'surdo_agudo' => ['nota' => 43, 'acentuado' => 43, 'chapa' => 37, 'tapado' => 86, 'flam' => 43],
        'agogo' => ['nota' => 67, 'acentuado' => 67, 'tapado' => 68, 'chapa' => 68, 'flam' => 67],
        'palmas' => ['nota' => 39, 'acentuado' => 39, 'flam' => 39],
    ];

    /** Ganancia por golpe (GOLPES[].gain en instruments.js). */
    private const GANANCIA = [
        'nota' => 1, 'acentuado' => 1.2, 'chapa' => 0.85, 'tapado' => 0.6, 'presionado' => 0.55, 'abierto' => 1.1,
        'slap' => 1.15, 'palma' => 0.9, 'dedo' => 0.5, 'agudo' => 1, 'flam' => 1, 'fantasma' => 0.35,
    ];

    private const DINAMICA = ['pp' => 0.35, 'p' => 0.55, 'mp' => 0.72, 'mf' => 0.9, 'f' => 1.1, 'ff' => 1.3];

    private const TIPO_XML = ['w' => 'whole', 'h' => 'half', 'q' => 'quarter', '8' => 'eighth', '16' => '16th', '32' => '32nd'];

    private const CABEZA_XML = ['chapa' => 'x', 'dedo' => 'x', 'slap' => 'normal', 'palma' => 'diamond', 'agudo' => 'triangle'];

    public const FORMATOS = ['midi', 'musicxml'];

    public static function midiDeGolpe(string $inst, string $golpe): int
    {
        if (isset(self::MIDI_POR_GOLPE[$inst][$golpe])) {
            return self::MIDI_POR_GOLPE[$inst][$golpe];
        }
        $base = self::MIDI_BASE[$inst] ?? 38;
        if (in_array($golpe, ['chapa', 'slap', 'agudo'], true)) {
            return min(81, $base + 1);
        }

        return $base;
    }

    /** @param array<string, mixed> $nota */
    public static function velocidad(array $nota): float
    {
        if (! empty($nota['vel'])) {
            return min(1.4, max(0.05, ((int) $nota['vel']) / 100));
        }

        return (self::DINAMICA[$nota['dyn'] ?? ''] ?? 1) * (self::GANANCIA[$nota['stroke'] ?? 'nota'] ?? 1);
    }

    /**
     * Orden de reproducción (barras de repetición y repeatX), como expandirTimeline().
     *
     * @param  array<string, mixed>  $score
     * @return list<array{0: int, 1: int}> pares [sección, compás]
     */
    public static function timeline(array $score): array
    {
        $out = [];
        foreach ($score['sections'] ?? [] as $si => $sec) {
            $pasada = [];
            $inicio = 0;
            foreach (array_values($sec['measures'] ?? []) as $mi => $m) {
                if (! empty($m['repeatBegin'])) {
                    $inicio = $mi;
                }
                $pasada[] = [(int) $si, $mi];
                if (! empty($m['repeatEnd'])) {
                    for ($k = $inicio; $k <= $mi; $k++) {
                        $pasada[] = [(int) $si, $k];
                    }
                    $inicio = $mi + 1;
                }
            }
            for ($r = 0; $r < max(1, (int) ($sec['repeatX'] ?? 1)); $r++) {
                array_push($out, ...$pasada);
            }
        }

        return $out;
    }

    /**
     * Archivo MIDI estándar (formato 0, una pista, canal 10).
     *
     * @param  array<string, mixed>  $score  ya normalizado
     */
    public function midi(array $score): string
    {
        $ts = $score['timeSignature'] ?? ['num' => 4, 'den' => 4];
        $capacidad = PartituraScore::ticksDeCompas($ts);
        $reales = array_values(array_filter(array_map(fn ($i) => (string) $i['id'], $score['instruments'] ?? []), fn ($id) => $id !== 'todos'));
        if ($reales === []) {
            $reales = PartituraScore::INSTRUMENTOS_DEFAULT;
        }
        $eventos = [];
        $cursor = 0;
        foreach (self::timeline($score) as [$si, $mi]) {
            $m = $score['sections'][$si]['measures'][$mi] ?? null;
            if (! is_array($m)) {
                continue;
            }
            foreach ($score['instruments'] ?? [] as $cfg) {
                if (! empty($cfg['mute'])) {
                    continue;
                }
                $id = (string) $cfg['id'];
                $destinos = $id === 'todos' ? $reales : [$id];
                $local = 0;
                foreach ($m['voces'][$id] ?? [] as $n) {
                    if (empty($n['rest'])) {
                        $vel = (int) max(8, min(127, round(96 * self::velocidad($n) * (float) ($cfg['volume'] ?? 1))));
                        foreach ($destinos as $dest) {
                            $nota = self::midiDeGolpe($dest, (string) ($n['stroke'] ?? 'nota'));
                            $eventos[] = [$cursor + $local, 1, $nota, $vel];
                            $eventos[] = [$cursor + $local + 6, 0, $nota, 0];
                        }
                    }
                    $local += PartituraScore::ticksDeNota($n);
                }
            }
            $cursor += $capacidad;
        }
        // Mismo instante: primero los note-off.
        usort($eventos, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        $pista = '';
        $usPorNegra = (int) round(60000000 / max(1, (int) ($score['tempo'] ?? 100)));
        $pista .= self::varLen(0)."\xFF\x51\x03".substr(pack('N', $usPorNegra), 1);
        $pista .= self::varLen(0)."\xFF\x58\x04".chr((int) $ts['num']).chr((int) log((int) $ts['den'], 2)).chr(24).chr(8);
        $titulo = mb_strcut((string) ($score['title'] ?? 'Toque'), 0, 60, 'UTF-8');
        $pista .= self::varLen(0)."\xFF\x03".self::varLen(strlen($titulo)).$titulo;
        $ultimo = 0;
        foreach ($eventos as [$t, $on, $nota, $vel]) {
            $pista .= self::varLen(max(0, $t - $ultimo)).chr($on ? 0x99 : 0x89).chr($nota & 0x7F).chr($vel & 0x7F);
            $ultimo = $t;
        }
        $pista .= self::varLen(0)."\xFF\x2F\x00";

        return 'MThd'.pack('N', 6).pack('n', 0).pack('n', 1).pack('n', PartituraScore::TPQ)
            .'MTrk'.pack('N', strlen($pista)).$pista;
    }

    /**
     * MusicXML 4.0 partwise: una parte por instrumento, percusión sin altura.
     *
     * @param  array<string, mixed>  $score  ya normalizado
     */
    public function musicXml(array $score): string
    {
        $ts = $score['timeSignature'] ?? ['num' => 4, 'den' => 4];
        $capacidad = PartituraScore::ticksDeCompas($ts);
        $tempo = (int) ($score['tempo'] ?? 100);
        $x = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $x .= '<!DOCTYPE score-partwise PUBLIC "-//Recordare//DTD MusicXML 4.0 Partwise//EN" "http://www.musicxml.org/dtds/partwise.dtd">'."\n";
        $x .= '<score-partwise version="4.0">'."\n";
        $x .= '  <work><work-title>'.self::esc((string) ($score['title'] ?? 'Toque')).'</work-title></work>'."\n";
        $x .= '  <identification><creator type="arranger">'.self::esc((string) (($score['autor'] ?? '') ?: 'La Chilinga')).'</creator>';
        $x .= '<encoding><software>Chilinga Partituras</software></encoding></identification>'."\n";
        $x .= "  <part-list>\n";
        $partes = [];
        foreach (array_values($score['instruments'] ?? []) as $i => $cfg) {
            $id = (string) $cfg['id'];
            $pid = 'P'.($i + 1);
            $partes[] = [$pid, $id];
            $x .= '    <score-part id="'.$pid.'"><part-name>'.self::esc(PartituraScore::INSTRUMENTOS[$id] ?? $id).'</part-name>';
            $x .= '<score-instrument id="'.$pid.'-I1"><instrument-name>'.self::esc(PartituraScore::INSTRUMENTOS[$id] ?? $id).'</instrument-name></score-instrument>';
            $x .= '<midi-instrument id="'.$pid.'-I1"><midi-channel>10</midi-channel><midi-unpitched>'.(self::midiDeGolpe($id, 'nota') + 1).'</midi-unpitched></midi-instrument>';
            $x .= "</score-part>\n";
        }
        $x .= "  </part-list>\n";

        $secciones = array_values($score['sections'] ?? []);
        foreach ($partes as [$pid, $id]) {
            $x .= '  <part id="'.$pid.'">'."\n";
            $nro = 0;
            foreach ($secciones as $si => $sec) {
                $compases = array_values($sec['measures'] ?? []);
                $repeatX = max(1, (int) ($sec['repeatX'] ?? 1));
                foreach ($compases as $mi => $m) {
                    $nro++;
                    $x .= '    <measure number="'.$nro.'">'."\n";
                    if ($nro === 1) {
                        $x .= '      <attributes><divisions>'.PartituraScore::TPQ.'</divisions>';
                        $x .= '<time><beats>'.(int) $ts['num'].'</beats><beat-type>'.(int) $ts['den'].'</beat-type></time>';
                        $x .= '<clef><sign>percussion</sign><line>2</line></clef><staff-details><staff-lines>1</staff-lines></staff-details></attributes>'."\n";
                        $x .= '      <direction placement="above"><direction-type><metronome><beat-unit>quarter</beat-unit><per-minute>'.$tempo.'</per-minute></metronome></direction-type><sound tempo="'.$tempo.'"/></direction>'."\n";
                    }
                    if ($mi === 0) {
                        $x .= '      <direction placement="above"><direction-type><rehearsal>'.self::esc((string) ($sec['name'] ?? '')).'</rehearsal></direction-type></direction>'."\n";
                        if ($repeatX > 1) {
                            $x .= '      <direction placement="above"><direction-type><words>×'.$repeatX.'</words></direction-type></direction>'."\n";
                        }
                    }
                    if (! empty($m['texto'])) {
                        $x .= '      <direction placement="above"><direction-type><words>'.self::esc((string) $m['texto']).'</words></direction-type></direction>'."\n";
                    }
                    if (! empty($m['repeatBegin']) || ($repeatX > 1 && $mi === 0)) {
                        $x .= '      <barline location="left"><bar-style>heavy-light</bar-style><repeat direction="forward"/></barline>'."\n";
                    }
                    $notas = $m['voces'][$id] ?? [];
                    if ($notas === []) {
                        $x .= '      <note><rest measure="yes"/><duration>'.$capacidad.'</duration><voice>1</voice></note>'."\n";
                    }
                    foreach ($notas as $n) {
                        $x .= $this->notaXml($n, $pid);
                    }
                    $ultimo = $mi === count($compases) - 1;
                    if (! empty($m['repeatEnd']) || ($repeatX > 1 && $ultimo)) {
                        $veces = $repeatX > 1 ? ' times="'.$repeatX.'"' : '';
                        $x .= '      <barline location="right"><bar-style>light-heavy</bar-style><repeat direction="backward"'.$veces.'/></barline>'."\n";
                    } elseif ($ultimo && $si === count($secciones) - 1) {
                        $x .= '      <barline location="right"><bar-style>light-heavy</bar-style></barline>'."\n";
                    }
                    $x .= "    </measure>\n";
                }
            }
            $x .= "  </part>\n";
        }
        $x .= "</score-partwise>\n";

        return $x;
    }

    /** @param array<string, mixed> $n */
    private function notaXml(array $n, string $pid): string
    {
        $s = '      <note>';
        $golpe = (string) ($n['stroke'] ?? 'nota');
        if (! empty($n['rest'])) {
            $s .= '<rest/>';
        } else {
            $s .= '<unpitched><display-step>C</display-step><display-octave>5</display-octave></unpitched>';
        }
        $s .= '<duration>'.PartituraScore::ticksDeNota($n).'</duration>';
        if (empty($n['rest'])) {
            $s .= '<instrument id="'.$pid.'-I1"/>';
        }
        $s .= '<voice>1</voice><type>'.(self::TIPO_XML[$n['dur'] ?? 'q'] ?? 'quarter').'</type>';
        $s .= str_repeat('<dot/>', max(0, min(2, (int) ($n['dots'] ?? 0))));
        if (is_array($n['tuplet'] ?? null)) {
            $s .= '<time-modification><actual-notes>'.(int) $n['tuplet']['num'].'</actual-notes><normal-notes>'.(int) $n['tuplet']['den'].'</normal-notes></time-modification>';
        }
        if (empty($n['rest'])) {
            $s .= '<stem>up</stem>';
            $s .= '<notehead'.($golpe === 'fantasma' ? ' parentheses="yes"' : '').($golpe === 'slap' ? ' filled="no"' : '').'>'.(self::CABEZA_XML[$golpe] ?? 'normal').'</notehead>';
            $art = [];
            if ($golpe === 'acentuado') {
                $art[] = '<accent/>';
            }
            if (in_array($golpe, ['tapado', 'presionado'], true)) {
                $art[] = '<tenuto/>';
            }
            $dig = $n['digitacion'] ?? null;
            $tech = in_array($dig, ['D', 'I'], true) ? '<technical><fingering>'.$dig.'</fingering></technical>' : '';
            if ($art !== [] || $tech !== '') {
                $s .= '<notations>'.($art !== [] ? '<articulations>'.implode('', $art).'</articulations>' : '').$tech.'</notations>';
            }
        }

        return $s."</note>\n";
    }

    /** Nombre de archivo seguro para la descarga. */
    public static function nombreArchivo(string $titulo, string $formato): string
    {
        $base = \Illuminate\Support\Str::slug($titulo) ?: 'toque';

        return $base.($formato === 'midi' ? '.mid' : '.musicxml');
    }

    private static function varLen(int $n): string
    {
        $bytes = [$n & 0x7F];
        $n >>= 7;
        while ($n > 0) {
            array_unshift($bytes, ($n & 0x7F) | 0x80);
            $n >>= 7;
        }

        return implode('', array_map('chr', $bytes));
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
