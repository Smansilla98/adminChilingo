<?php

namespace App\Domain\Acceso;

use App\Models\Alumno;
use App\Models\Bloque;

/**
 * Resultado de resolver qué puede hacer una persona y dónde.
 * Se calcula una vez por request (ver User::acceso()).
 */
final class PermisosEfectivos
{
    /** @var array<int, int|null> cache bloque_id => sede_id */
    private array $sedeDeBloque = [];

    /**
     * @param  list<RolContextual>  $roles
     * @param  array<string, Alcance>  $permisos
     * @param  array<string, list<string>>  $origenes  permiso => claves de roles/asignaciones que lo otorgan
     */
    public function __construct(
        public readonly ?int $personaId,
        private readonly array $roles,
        private readonly array $permisos,
        private readonly array $origenes,
        private readonly bool $superadmin,
    ) {}

    public static function ninguno(): self
    {
        return new self(null, [], [], [], false);
    }

    public function esSuperadmin(): bool
    {
        return $this->superadmin;
    }

    /** Administrador (o superadmin) con alcance global. */
    public function esAdministrador(): bool
    {
        return $this->superadmin || $this->tieneRol('administrador', 'global');
    }

    public function puede(string $permiso): bool
    {
        if ($this->superadmin) {
            return true;
        }

        return isset($this->permisos[$permiso]) && ! $this->permisos[$permiso]->estaVacio();
    }

    /** @param  list<string>  $permisos */
    public function puedeAlguno(array $permisos): bool
    {
        foreach ($permisos as $p) {
            if ($this->puede($p)) {
                return true;
            }
        }

        return false;
    }

    public function alcance(string $permiso): Alcance
    {
        if ($this->superadmin) {
            return Alcance::total();
        }

        return clone ($this->permisos[$permiso] ?? Alcance::vacio());
    }

    public function puedeGlobal(string $permiso): bool
    {
        return $this->alcance($permiso)->esGlobal();
    }

    public function puedeEnSede(string $permiso, ?int $sedeId): bool
    {
        return $this->alcance($permiso)->incluyeSede($sedeId);
    }

    public function puedeEnBloque(string $permiso, ?int $bloqueId, ?int $sedeId = null): bool
    {
        $alcance = $this->alcance($permiso);
        if ($alcance->esGlobal()) {
            return true;
        }
        if (! $bloqueId) {
            return false;
        }
        $sedeId ??= $this->sedeDelBloque($bloqueId);

        return $alcance->incluyeBloque($bloqueId, $sedeId);
    }

    /**
     * ¿El permiso alcanza a este alumno? (por su sede principal o cualquiera de sus bloques)
     */
    public function puedeSobreAlumno(string $permiso, Alumno $alumno): bool
    {
        $alcance = $this->alcance($permiso);
        if ($alcance->esGlobal()) {
            return true;
        }
        if ($alcance->estaVacio()) {
            return false;
        }
        $alumno->loadMissing(['bloques:id,sede_id', 'bloque:id,sede_id']);
        $bloques = [];
        foreach ($alumno->bloques as $b) {
            $bloques[(int) $b->id] = $b->sede_id ? (int) $b->sede_id : null;
        }
        if ($alumno->bloque) {
            $bloques[(int) $alumno->bloque->id] = $alumno->bloque->sede_id ? (int) $alumno->bloque->sede_id : null;
        }

        return $alcance->incluyeAlguno(array_filter([(int) $alumno->sede_id]), $bloques);
    }

    public function tieneRol(string $rol, ?string $ambito = null): bool
    {
        foreach ($this->roles as $r) {
            if ($r->rol === $rol && ($ambito === null || $r->ambito === $ambito)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $roles */
    public function tieneAlgunRol(array $roles): bool
    {
        foreach ($roles as $rol) {
            if ($this->tieneRol($rol)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<RolContextual> */
    public function roles(): array
    {
        return $this->roles;
    }

    /** @return list<string> */
    public function permisos(): array
    {
        if ($this->superadmin) {
            return CatalogoPermisos::todos();
        }

        return array_values(array_filter(
            array_keys($this->permisos),
            fn (string $p) => ! $this->permisos[$p]->estaVacio()
        ));
    }

    /** @return list<string> */
    public function origenesDe(string $permiso): array
    {
        return $this->origenes[$permiso] ?? [];
    }

    /**
     * Módulos de navegación visibles (config permisos.modulos).
     *
     * @return list<string>
     */
    public function modulos(): array
    {
        $out = [];
        foreach (config('permisos.modulos', []) as $clave => $def) {
            $requeridos = $def['permisos'] ?? [];
            if ($requeridos === []) {
                $out[] = $clave;

                continue;
            }
            foreach ($requeridos as $req) {
                $ok = str_starts_with($req, '@') ? $this->tieneRol(substr($req, 1)) : $this->puede($req);
                if ($ok) {
                    $out[] = $clave;
                    break;
                }
            }
        }

        return $out;
    }

    private function sedeDelBloque(int $bloqueId): ?int
    {
        if (! array_key_exists($bloqueId, $this->sedeDeBloque)) {
            $sede = Bloque::query()->whereKey($bloqueId)->value('sede_id');
            $this->sedeDeBloque[$bloqueId] = $sede ? (int) $sede : null;
        }

        return $this->sedeDeBloque[$bloqueId];
    }

    /**
     * @return array{permisos: array<string, array{global: bool, sedes: list<int>, bloques: list<int>}>, superadmin: bool}
     */
    public function toArray(): array
    {
        $permisos = [];
        foreach ($this->permisos() as $p) {
            $permisos[$p] = $this->alcance($p)->toArray();
        }

        return ['permisos' => $permisos, 'superadmin' => $this->superadmin];
    }
}
