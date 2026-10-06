<?php

namespace App\Services;

use App\Models\PartituraVersion;
use App\Models\ProgramaRitmo;
use App\Support\PartituraScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de la partitura de un toque: versiones publicadas (restaurables) y un
 * borrador autoguardado. Publicar sigue escribiendo `medios.partitura_score`.
 */
class PartituraHistorialService
{
    public function disponible(): bool
    {
        return Schema::hasTable('partitura_versiones');
    }

    /**
     * Registra una versión publicada. No duplica si la música es la misma que la última.
     *
     * @param  array<string, mixed>  $score  ya normalizado
     */
    public function registrarVersion(ProgramaRitmo $toque, array $score, ?string $autor, ?string $nota = null): ?PartituraVersion
    {
        if (! $this->disponible()) {
            return null;
        }

        return DB::transaction(function () use ($toque, $score, $autor, $nota) {
            $ultima = PartituraVersion::query()->where('programa_ritmo_id', $toque->id)->lockForUpdate()->orderByDesc('numero')->first();
            if ($ultima && $this->huella($ultima->score) === $this->huella($score)) {
                return $ultima;
            }

            return PartituraVersion::query()->create([
                'programa_ritmo_id' => $toque->id,
                'numero' => ($ultima?->numero ?? 0) + 1,
                'score' => $score,
                'autor' => $autor ? mb_substr($autor, 0, 80) : null,
                'nota' => $nota ? mb_substr(trim($nota), 0, 200) : null,
            ]);
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function listar(ProgramaRitmo $toque, int $limite = 50): Collection
    {
        if (! $this->disponible()) {
            return collect();
        }

        return PartituraVersion::query()->where('programa_ritmo_id', $toque->id)
            ->orderByDesc('numero')->limit($limite)->get()
            ->map(fn (PartituraVersion $v) => [
                'numero' => $v->numero,
                'autor' => $v->autor,
                'nota' => $v->nota,
                'fecha' => $v->created_at?->toIso8601String(),
                'resumen' => PartituraScore::resumen($v->score),
            ]);
    }

    public function version(ProgramaRitmo $toque, int $numero): ?PartituraVersion
    {
        if (! $this->disponible()) {
            return null;
        }

        return PartituraVersion::query()->where('programa_ritmo_id', $toque->id)->where('numero', $numero)->first();
    }

    /** @param  array<string, mixed>  $score  ya normalizado */
    public function guardarBorrador(ProgramaRitmo $toque, array $score, ?string $autor): void
    {
        if (! Schema::hasColumn('programa_ritmos', 'partitura_borrador')) {
            return;
        }
        $toque->forceFill([
            'partitura_borrador' => json_encode($score, JSON_UNESCAPED_UNICODE),
            'partitura_borrador_at' => now(),
            'partitura_borrador_autor' => $autor ? mb_substr($autor, 0, 80) : null,
        ])->saveQuietly();
    }

    public function descartarBorrador(ProgramaRitmo $toque): void
    {
        if (! Schema::hasColumn('programa_ritmos', 'partitura_borrador')) {
            return;
        }
        $toque->forceFill(['partitura_borrador' => null, 'partitura_borrador_at' => null, 'partitura_borrador_autor' => null])->saveQuietly();
    }

    /**
     * Borrador pendiente (más nuevo que lo publicado), o null.
     *
     * @return array{score: array<string, mixed>, at: ?string, autor: ?string}|null
     */
    public function borradorPendiente(ProgramaRitmo $toque): ?array
    {
        if (! Schema::hasColumn('programa_ritmos', 'partitura_borrador') || ! $toque->partitura_borrador) {
            return null;
        }
        $score = json_decode((string) $toque->partitura_borrador, true);
        if (! is_array($score)) {
            return null;
        }
        $publicada = $toque->mediosNormalizados()['partitura_score'] ?? null;
        if (is_array($publicada) && $this->huella($publicada) === $this->huella($score)) {
            return null;
        }

        return [
            'score' => $score,
            'at' => $toque->partitura_borrador_at ? \Illuminate\Support\Carbon::parse($toque->partitura_borrador_at)->toIso8601String() : null,
            'autor' => $toque->partitura_borrador_autor,
        ];
    }

    /** Huella musical: ignora marcas de tiempo e ids. */
    private function huella(array $score): string
    {
        unset($score['updated_at']);
        $json = json_encode($score);

        return sha1((string) preg_replace('/"id":"[^"]*",?/', '', (string) $json));
    }
}
