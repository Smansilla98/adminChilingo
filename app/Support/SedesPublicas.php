<?php

namespace App\Support;

use App\Models\Sede;
use Illuminate\Support\Facades\Schema;

/**
 * Sedes que se muestran al público (mapa del programa): solo nombre, dirección y
 * ubicación. Nunca datos internos de la sede (alquiler, liquidaciones, coordinador).
 */
class SedesPublicas
{
    /**
     * @return list<array{id: int, nombre: string, direccion: string|null, lat: float|null, lng: float|null, como_llegar: string|null}>
     */
    public static function listar(): array
    {
        if (! Schema::hasTable('sedes') || ! Schema::hasColumn('sedes', 'en_mapa')) {
            return [];
        }

        return Sede::query()
            ->where('activo', true)
            ->where('en_mapa', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'direccion', 'latitud', 'longitud'])
            ->map(fn (Sede $s) => [
                'id' => (int) $s->id,
                'nombre' => (string) $s->nombre,
                'direccion' => $s->direccion,
                'lat' => $s->latitud !== null ? (float) $s->latitud : null,
                'lng' => $s->longitud !== null ? (float) $s->longitud : null,
                'como_llegar' => $s->urlComoLlegar(),
            ])
            ->values()
            ->all();
    }
}
