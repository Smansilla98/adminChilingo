<?php

namespace App\Support;

use App\Models\ProgramaRitmo;
use Illuminate\Support\Str;

class ProgramaRitmoSlug
{
    public static function generar(int $anio, string $nombre, ?int $excluirId = null): string
    {
        $base = 'a'.$anio.'-'.Str::slug($nombre);
        $slug = $base;
        $n = 2;
        while (self::existe($slug, $excluirId)) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    private static function existe(string $slug, ?int $excluirId): bool
    {
        $q = ProgramaRitmo::query()->where('slug', $slug);
        if ($excluirId !== null) {
            $q->where('id', '!=', $excluirId);
        }

        return $q->exists();
    }
}
