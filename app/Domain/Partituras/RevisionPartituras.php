<?php

namespace App\Domain\Partituras;

use App\Models\PartituraRevision;
use App\Models\ProgramaRitmo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pasajes dudosos de las partituras: compases cuyo texto empieza con «Revisar:».
 *
 * Una marca se identifica por toque + sección + número de compás + nota (los ids de
 * compás cambian cada vez que se regeneran los JSON). Al validarla se quita la marca
 * de la partitura y se guarda en partitura_revisiones, que la recarga de cada deploy
 * vuelve a aplicar.
 */
class RevisionPartituras
{
    private const PREFIJO = '/^\s*revisar\s*:\s*/iu';

    /**
     * @return Collection<int, array{ritmo: ProgramaRitmo, marcas: list<array{seccion: string, compas: int, nota: string}>}>
     */
    public function pendientes(): Collection
    {
        $pendientes = collect();
        foreach (ProgramaRitmo::query()->orderBy('año')->orderBy('orden')->get() as $ritmo) {
            $marcas = self::marcas($ritmo->mediosNormalizados()['partitura_score'] ?? null);
            if ($marcas !== []) {
                $pendientes->push(['ritmo' => $ritmo, 'marcas' => $marcas]);
            }
        }

        return $pendientes;
    }

    public function totalPendientes(): int
    {
        return $this->pendientes()->sum(fn ($t) => count($t['marcas']));
    }

    /** @return Collection<int, PartituraRevision> últimas validaciones */
    public function validadas(int $limite = 20): Collection
    {
        if (! Schema::hasTable('partitura_revisiones')) {
            return collect();
        }

        return PartituraRevision::query()->with(['programaRitmo:id,nombre,slug', 'validador:id,name'])
            ->latest('validado_at')->limit($limite)->get();
    }

    /** Marca el pasaje como validado y le quita el «Revisar:» a la partitura. */
    public function validar(ProgramaRitmo $ritmo, string $seccion, int $compas, string $nota, ?User $user): bool
    {
        return DB::transaction(function () use ($ritmo, $seccion, $compas, $nota, $user) {
            $ritmo->refresh();
            $medios = $ritmo->mediosNormalizados();
            $score = $medios['partitura_score'] ?? null;
            $existe = collect(self::marcas($score))->contains(fn ($m) => $m['seccion'] === $seccion && $m['compas'] === $compas && $m['nota'] === $nota);
            if (! $existe) {
                return false;
            }

            PartituraRevision::query()->updateOrCreate(
                ['programa_ritmo_id' => $ritmo->id, 'seccion' => $seccion, 'compas' => $compas, 'nota_hash' => md5($nota)],
                ['nota' => mb_substr($nota, 0, 500), 'validado_por' => $user?->id, 'validado_at' => now()],
            );

            $medios['partitura_score'] = self::quitarMarcas($score, [[$seccion, $compas, $nota]]);
            $ritmo->update(['medios' => $medios]);

            return true;
        });
    }

    /**
     * Saca de una partitura las marcas ya validadas (se usa al recargar desde los JSON).
     *
     * @param  array<string, mixed>|null  $score
     * @return array<string, mixed>|null
     */
    public static function aplicarValidadas(?array $score, int $ritmoId): ?array
    {
        if ($score === null || ! Schema::hasTable('partitura_revisiones')) {
            return $score;
        }
        $validadas = PartituraRevision::query()->where('programa_ritmo_id', $ritmoId)->get()
            ->map(fn (PartituraRevision $v) => [$v->seccion, $v->compas, $v->nota])->all();

        return $validadas === [] ? $score : self::quitarMarcas($score, $validadas);
    }

    /**
     * @param  array<string, mixed>|null  $score
     * @return list<array{seccion: string, compas: int, nota: string}>
     */
    public static function marcas(?array $score): array
    {
        $marcas = [];
        foreach (($score['sections'] ?? []) as $s) {
            foreach (array_values($s['measures'] ?? []) as $i => $m) {
                $texto = (string) ($m['texto'] ?? '');
                if (preg_match(self::PREFIJO, $texto)) {
                    $marcas[] = ['seccion' => (string) ($s['name'] ?? ''), 'compas' => $i + 1, 'nota' => trim((string) preg_replace(self::PREFIJO, '', $texto))];
                }
            }
        }

        return $marcas;
    }

    /**
     * @param  array<string, mixed>  $score
     * @param  list<array{0: string, 1: int, 2: string}>  $quitar
     * @return array<string, mixed>
     */
    private static function quitarMarcas(array $score, array $quitar): array
    {
        foreach (($score['sections'] ?? []) as $si => $s) {
            foreach (array_values($s['measures'] ?? []) as $i => $m) {
                $texto = (string) ($m['texto'] ?? '');
                if (! preg_match(self::PREFIJO, $texto)) {
                    continue;
                }
                $nota = trim((string) preg_replace(self::PREFIJO, '', $texto));
                foreach ($quitar as [$seccion, $compas, $notaValidada]) {
                    if ($seccion === (string) ($s['name'] ?? '') && $compas === $i + 1 && $notaValidada === $nota) {
                        $score['sections'][$si]['measures'][$i]['texto'] = null;
                    }
                }
            }
        }

        return $score;
    }
}
