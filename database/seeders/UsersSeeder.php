<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cuentas de arranque: administración y perfiles de ejemplo para probar.
 *
 * Solo se crean si no existen (no pisa contraseñas cambiadas). Las contraseñas no son
 * fijas: salen de SEED_PASSWORD o, si no está, se generan al azar y se muestran una vez
 * en la consola. En producción solo se crea la cuenta de administración.
 */
class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $cuentas = [
            ['admin', 'Administrador', 'admin', ['admin']],
            ['direccion', 'Dirección', 'direccion', ['direccion']],
            ['profesor', 'Profesor Ejemplo', 'profesor', ['profesor']],
            ['coord.sede', 'Coordinador Sede', 'profesor', ['profesor', 'coordinador_sede']],
            ['coord.area', 'Coordinador Área', 'profesor', ['profesor', 'coordinador_area']],
        ];
        if (app()->environment('production')) {
            $cuentas = array_slice($cuentas, 0, 1);
        }

        foreach ($cuentas as [$username, $nombre, $rol, $roles]) {
            if (User::query()->where('username', $username)->exists()) {
                continue;
            }
            $password = (string) (env('SEED_PASSWORD') ?: Str::password(16, symbols: false));
            $user = User::query()->create([
                'username' => $username,
                'name' => $nombre,
                'email' => str_replace('.', '', $username).'@chilinga.local',
                'password' => Hash::make($password),
                'role' => $rol,
            ]);
            foreach ($roles as $r) {
                $user->assignRole($r);
            }
            $this->command?->warn("Usuario «{$username}» creado con la contraseña: {$password} (cambiala al ingresar)");
        }
    }
}
