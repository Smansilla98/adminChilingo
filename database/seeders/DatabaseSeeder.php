<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles: dirección, coordinador_sede, coordinador_area, profesor, alumno
        Role::firstOrCreate(['name' => 'admin']);           // dirección
        Role::firstOrCreate(['name' => 'direccion']);
        Role::firstOrCreate(['name' => 'coordinador_sede']);
        Role::firstOrCreate(['name' => 'coordinador_area']);
        Role::firstOrCreate(['name' => 'profesor']);
        Role::firstOrCreate(['name' => 'alumno']);

        // Ejecutar seeders
        $this->call([
            SedesSeeder::class,
            UsersSeeder::class,
            ProgramaRitmosSeeder::class,
        ]);
    }
}
