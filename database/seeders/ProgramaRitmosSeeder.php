<?php

namespace Database\Seeders;

use App\Models\ProgramaRitmo;
use Illuminate\Database\Seeder;

class ProgramaRitmosSeeder extends Seeder
{
    /**
     * Programa oficial La Chilinga - Toques por año (del texto del programa).
     */
    public function run(): void
    {
        $ritmos = [
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

        foreach ($ritmos as $r) {
            ProgramaRitmo::firstOrCreate(
                ['año' => $r[0], 'orden' => $r[1], 'nombre' => $r[2]],
                ['autor' => $r[3], 'opcional' => $r[4], 'notas' => $r[5]]
            );
        }
    }
}
