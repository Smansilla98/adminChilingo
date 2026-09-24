<?php

namespace App\Domain\Acceso;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Lee config/permisos.php y lo vuelca a las tablas de Spatie.
 */
final class CatalogoPermisos
{
    /** @var array<string, string>|null */
    private static ?array $etiquetas = null;

    /** @var array<string, list<string>> */
    private static array $expandidos = [];

    /** @return array<string, string> permiso => etiqueta */
    public static function etiquetas(): array
    {
        if (self::$etiquetas === null) {
            self::$etiquetas = [];
            foreach (config('permisos.grupos', []) as $permisos) {
                self::$etiquetas += $permisos;
            }
        }

        return self::$etiquetas;
    }

    /** @return list<string> */
    public static function todos(): array
    {
        return array_keys(self::etiquetas());
    }

    public static function existe(string $permiso): bool
    {
        return array_key_exists($permiso, self::etiquetas());
    }

    public static function etiqueta(string $permiso): string
    {
        return self::etiquetas()[$permiso] ?? $permiso;
    }

    /** @return array<string, array<string, string>> */
    public static function grupos(): array
    {
        return config('permisos.grupos', []);
    }

    public static function grupoDe(string $permiso): string
    {
        foreach (self::grupos() as $grupo => $permisos) {
            if (array_key_exists($permiso, $permisos)) {
                return $grupo;
            }
        }

        return 'Otros';
    }

    /** @return array<string, array<string, mixed>> */
    public static function roles(): array
    {
        return config('permisos.roles', []);
    }

    public static function existeRol(string $rol): bool
    {
        return array_key_exists($rol, self::roles());
    }

    public static function nombreRol(string $rol): string
    {
        return self::roles()[$rol]['nombre'] ?? ucfirst(str_replace('_', ' ', $rol));
    }

    public static function esDerivado(string $rol): bool
    {
        return (bool) (self::roles()[$rol]['derivado'] ?? false);
    }

    /** @return list<string> */
    public static function ambitosDeRol(string $rol): array
    {
        return self::roles()[$rol]['ambitos'] ?? ['global'];
    }

    /** Roles que solo puede asignar quien tiene usuarios.assign_admin. */
    public static function esRolDeAdministracion(string $rol): bool
    {
        return in_array($rol, ['administrador', 'superadministrador'], true);
    }

    /**
     * Permisos de un rol, expandiendo '*' y exclusiones '!permiso'.
     *
     * @return list<string>
     */
    public static function permisosDeRol(string $rol): array
    {
        if (isset(self::$expandidos[$rol])) {
            return self::$expandidos[$rol];
        }

        $def = self::roles()[$rol]['permisos'] ?? [];
        $out = [];
        $excluir = [];
        foreach ($def as $p) {
            if ($p === '*') {
                $out = array_merge($out, self::todos());
            } elseif (str_starts_with($p, '!')) {
                $excluir[] = substr($p, 1);
            } else {
                $out[] = $p;
            }
        }

        return self::$expandidos[$rol] = array_values(array_diff(array_unique($out), $excluir));
    }

    /** Rol del catálogo equivalente a un rol Spatie heredado. */
    public static function rolLegacy(string $nombre): ?string
    {
        return config('permisos.legacy', [])[$nombre] ?? null;
    }

    public static function limpiarCache(): void
    {
        self::$etiquetas = null;
        self::$expandidos = [];
    }

    /**
     * Crea/actualiza permisos y roles en las tablas de Spatie. Idempotente.
     *
     * @return array{permisos: int, roles: int}
     */
    public static function sincronizar(): array
    {
        self::limpiarCache();
        $guard = 'web';

        DB::transaction(function () use ($guard) {
            foreach (self::todos() as $nombre) {
                Permission::findOrCreate($nombre, $guard);
            }

            foreach (array_keys(self::roles()) as $rol) {
                Role::findOrCreate($rol, $guard)->syncPermissions(self::permisosDeRol($rol));
            }

            // Roles heredados: se conservan con los permisos de su equivalente.
            foreach (config('permisos.legacy', []) as $viejo => $nuevo) {
                Role::findOrCreate($viejo, $guard)->syncPermissions(self::permisosDeRol($nuevo));
            }
            Role::findOrCreate('coordinador_sede', $guard)->syncPermissions(self::permisosDeRol('coordinador'));
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['permisos' => count(self::todos()), 'roles' => count(self::roles())];
    }
}
