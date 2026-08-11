<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear usuario admin (ingreso por usuario y contraseña)
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ]
        );

        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Crear usuario profesor de ejemplo
        $profesor = User::firstOrCreate(
            ['username' => 'profesor'],
            [
                'name' => 'Profesor Ejemplo',
                'password' => Hash::make('profesor123'),
                'role' => 'profesor',
            ]
        );

        if (! $profesor->hasRole('profesor')) {
            $profesor->assignRole('profesor');
        }

        // Dirección (mismo nivel que admin)
        $direccion = User::firstOrCreate(
            ['username' => 'direccion'],
            [
                'name' => 'Dirección',
                'email' => 'direccion@chilinga.local',
                'password' => Hash::make('direccion123'),
                'role' => 'direccion',
            ]
        );
        if (! $direccion->hasRole('direccion')) {
            $direccion->assignRole('direccion');
        }
        if (! $direccion->hasRole('admin')) {
            // isAdmin() ya contempla direccion; opcionalmente también Spatie admin
        }

        // Coordinador de sede (perfil profesor + rol)
        $coordSede = User::firstOrCreate(
            ['username' => 'coord.sede'],
            [
                'name' => 'Coordinador Sede',
                'email' => 'coordsede@chilinga.local',
                'password' => Hash::make('coord123'),
                'role' => 'profesor',
            ]
        );
        if (! $coordSede->hasRole('profesor')) {
            $coordSede->assignRole('profesor');
        }
        if (! $coordSede->hasRole('coordinador_sede')) {
            $coordSede->assignRole('coordinador_sede');
        }

        // Coordinador de área
        $coordArea = User::firstOrCreate(
            ['username' => 'coord.area'],
            [
                'name' => 'Coordinador Área',
                'email' => 'coordarea@chilinga.local',
                'password' => Hash::make('coord123'),
                'role' => 'profesor',
            ]
        );
        if (! $coordArea->hasRole('profesor')) {
            $coordArea->assignRole('profesor');
        }
        if (! $coordArea->hasRole('coordinador_area')) {
            $coordArea->assignRole('coordinador_area');
        }
    }
}
