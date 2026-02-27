<?php

namespace Database\Seeders;

use App\Models\Sede;
use Illuminate\Database\Seeder;

class SedesSeeder extends Seeder
{
    /**
     * Sedes oficiales del programa La Chilinga (del texto del programa).
     */
    public function run(): void
    {
        $sedes = [
            ['nombre' => 'Palomar', 'direccion' => 'Ing. Marconi 181'],
            ['nombre' => 'Saavedra', 'direccion' => 'Ruiz Huidobro 4228'],
            ['nombre' => 'Varela', 'direccion' => 'Cerro Aconcagua 2153'],
            ['nombre' => 'Quilmes', 'direccion' => 'Humberto Primo 320'],
            ['nombre' => 'Banfield', 'direccion' => 'Av. Alsina 251'],
            ['nombre' => 'Tacheles', 'direccion' => 'Alsina 1475 (Congreso)'],
        ];

        foreach ($sedes as $s) {
            Sede::firstOrCreate(
                ['nombre' => $s['nombre']],
                ['direccion' => $s['direccion'], 'activo' => true]
            );
        }
    }
}
