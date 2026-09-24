<?php

namespace App\Console\Commands;

use App\Domain\Acceso\CatalogoPermisos;
use App\Domain\Personas\PersonaService;
use App\Models\Asignacion;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class SuperadminCommand extends Command
{
    protected $signature = 'chilinga:superadmin {usuario : username o email} {--force : No pedir confirmación}';

    protected $description = 'Da el rol superadministrador (global) a una cuenta existente. Para la puesta en marcha o recuperar el acceso.';

    public function handle(PersonaService $personas): int
    {
        $valor = (string) $this->argument('usuario');
        $user = User::query()->where('username', $valor)->orWhere('email', $valor)->first();
        if (! $user) {
            $this->error('No existe esa cuenta.');

            return self::FAILURE;
        }
        if (! $this->option('force') && ! $this->confirm("¿Dar acceso total a {$user->username}?")) {
            return self::FAILURE;
        }

        CatalogoPermisos::sincronizar();
        $persona = $user->persona ?? $personas->asegurarParaUsuario($user);
        Asignacion::query()->firstOrCreate([
            'persona_id' => $persona->id,
            'role_id' => Role::findByName('superadministrador', 'web')->id,
            'ambito_tipo' => 'global',
        ], ['activo' => true, 'notas' => 'Asignado por consola.']);

        $this->info("{$user->username} es superadministrador.");

        return self::SUCCESS;
    }
}
