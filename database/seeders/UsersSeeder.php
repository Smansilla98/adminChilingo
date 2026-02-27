<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

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

        if (!$admin->hasRole('admin')) {
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

        if (!$profesor->hasRole('profesor')) {
            $profesor->assignRole('profesor');
        }
    }
}
