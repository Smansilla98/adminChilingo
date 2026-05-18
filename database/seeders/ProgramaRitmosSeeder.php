<?php

namespace Database\Seeders;

use App\Models\ProgramaRitmo;
use App\Support\ProgramaRitmoSlug;
use Illuminate\Database\Seeder;

class ProgramaRitmosSeeder extends Seeder
{
    /**
     * Inserta ritmos solo si la tabla está vacía (migraciones / deploy).
     */
    public static function poblarSiVacio(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('programa_ritmos')) {
            return 0;
        }
        if (ProgramaRitmo::query()->exists()) {
            return (int) ProgramaRitmo::query()->count();
        }

        return self::poblar();
    }

    /**
     * Programa oficial La Chilinga - Toques por año (del texto del programa).
     */
    public function run(): void
    {
        self::poblar();
    }

    /**
     * @return int Cantidad de filas procesadas
     */
    public static function poblar(): int
    {
        $ritmos = self::datos();

        foreach ($ritmos as $r) {
            $existente = ProgramaRitmo::query()
                ->where('año', $r[0])
                ->where('orden', $r[1])
                ->where('nombre', $r[2])
                ->first();

            $attrs = [
                'autor' => $r[3],
                'opcional' => $r[4],
                'notas' => $r[5],
                'publicado' => true,
            ];

            if ($existente) {
                if (! $existente->slug) {
                    $attrs['slug'] = ProgramaRitmoSlug::generar($r[0], $r[2], $existente->id);
                }
                $existente->update($attrs);
            } else {
                ProgramaRitmo::create(array_merge([
                    'año' => $r[0],
                    'orden' => $r[1],
                    'nombre' => $r[2],
                    'slug' => ProgramaRitmoSlug::generar($r[0], $r[2]),
                ], $attrs));
            }
        }

        self::asegurarSlugs();

        return count($ritmos);
    }

    public static function asegurarSlugs(): void
    {
        ProgramaRitmo::query()->whereNull('slug')->orWhere('slug', '')->each(function (ProgramaRitmo $ritmo) {
            $ritmo->update([
                'slug' => ProgramaRitmoSlug::generar((int) $ritmo->año, $ritmo->nombre, $ritmo->id),
            ]);
        });
    }

    /**
     * @return array<int, array{0: int, 1: int, 2: string, 3: string, 4: bool, 5: ?string}>
     */
    public static function datos(): array
    {
        return [
            // 1er Año
            [1, 1, 'Ritmo Chilinga', 'D. Buira', false, null],
            [1, 2, 'Ochosi', 'Ritmo Popular | Adaptación: D. Buira', false, null],
            [1, 3, 'Marcha Camión', 'Ritmo Popular Uruguay | Adaptación: D. Buira', false, null],
            [1, 4, 'Ochosi en Murga', 'D. Buira', false, null],
            [1, 5, 'Dance', 'D. Buira', false, null],
            [1, 6, 'Samba Reggae I y II', 'Ritmo Popular Brasil | Adaptación: D. Buira', false, null],
            [1, 7, 'Toque de Marcha', 'D. Buira', false, null],
            [1, 8, 'Rap - Murga', 'D. Buira', false, null],
            [1, 9, 'Ixesa I', 'Ritmo Popular Brasil | Adaptación: D. Buira', true, 'Opcional 1er o 2do año'],
            // 2do Año
            [2, 1, 'Ixesa I', 'Ritmo Popular Brasil | Adaptación: D. Buira', true, 'Opcional 1er o 2do año'],
            [2, 2, 'Candombe Argentino', 'Ritmo Argentino | Adaptación: Egle Martin, D. Buira', false, null],
            [2, 3, 'Toque de Comparsa', 'Ritmo del litoral | Adaptación: D. Buira', false, null],
            [2, 4, 'Sacateca', 'D. Buira', false, null],
            [2, 5, 'Chiruda', 'D. Buira', false, null],
            [2, 6, 'Ritmo de Chacarera', 'Santiago del Estero | Adaptación: D. Buira', false, null],
            [2, 7, 'Ritmo de Rumba', 'Ritmo Popular Cuba | Adaptación: D. Buira', false, null],
            // 3er Año
            [3, 1, 'Buscando a Coco', 'D. Buira', false, null],
            [3, 2, 'Solo de timbales I', 'D. Buira', false, null],
            [3, 3, 'Malamakua I', 'D. Buira', false, null],
            [3, 4, 'Solo de redoblantes (Chiruda)', 'D. Buira', false, null],
            [3, 5, 'Afrotango', 'M. Pacios', true, 'Opcional 3er o 4to año'],
            [3, 6, 'Mongokuta I', 'D. Buira', false, null],
            [3, 7, 'Chiruda Blues', 'D. Buira', false, null],
            [3, 8, 'Arabe', 'Turca Zahra', false, null],
            [3, 9, 'Batería', 'T. Barbeira', false, null],
            // 4to Año
            [4, 1, 'Iyesa II', 'Ritmo Popular Cuba | Adaptación: D. Buira', false, null],
            [4, 2, 'La Meta', 'D. Buira', false, null],
            [4, 3, 'Solo de Repiques (La Meta)', 'D. Buira', false, null],
            [4, 4, 'Afrotango', 'M. Pacios', true, 'Opcional 3er o 4to año'],
            [4, 5, 'Muñequitos I', 'D. Buira', false, null],
            [4, 6, 'Oxosi II', 'D. Buira', false, null],
            [4, 7, 'Kukomalo', 'D. Buira', true, 'Opcional 4to o 5to año'],
            [4, 8, 'Samborombón', 'D. Buira', false, null],
            [4, 9, 'Ritmo de Makuta (Cuba)', 'Adaptación D. Buira', false, null],
            // 5to Año
            [5, 1, 'Kukomalo', 'D. Buira', true, 'Opcional 4to o 5to año'],
            [5, 2, 'Chilinga II', 'D. Buira', true, 'Opcional 5to o 6to año'],
            [5, 3, 'Solo de Timbales II', 'D. Buira', false, null],
            [5, 4, 'Juancito', 'D. Buira', false, null],
            [5, 5, 'Malamakua II', 'D. Buira', false, null],
            [5, 6, 'Solos de tambor individual', 'D. Buira', false, null],
            [5, 7, 'Toque en 7 - Timbales', 'D. Buira', false, null],
            // 6to Año
            [6, 1, 'Chilinga II', 'D. Buira', true, 'Opcional 5to o 6to año'],
            [6, 2, 'Santito', 'D. Buira', false, null],
            [6, 3, 'Ritmo sobre Paradiddle', 'Adaptación: D. Buira', false, null],
            [6, 4, 'Ayinde', 'D. Buira', false, null],
            [6, 5, 'Sacateca II', 'D. Buira', false, null],
            [6, 6, 'Solo de Timbales II', 'D. Buira', false, null],
            [6, 7, 'Muñequitos II y III', 'D. Buira', false, null],
        ];
    }
}
