<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'telefono',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'modulos_access' => 'array',
        'apariencia_json' => 'array',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // legacy (kept for compatibility)
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'modulos_access' => 'array',
            'apariencia_json' => 'array',
        ];
    }

    /**
     * Preferencia visual del usuario (tokens: accent, font_display, font_body).
     *
     * @return array{accent: string, font_display: string, font_body: string}
     */
    public function aparienciaTema(): array
    {
        return \App\Support\AparienciaTema::normalizar(
            is_array($this->apariencia_json) ? $this->apariencia_json : null
        );
    }

    /**
     * Admin o Dirección (mismo nivel de privilegios globales).
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin'
            || $this->role === 'direccion'
            || $this->hasRole('admin')
            || $this->hasRole('direccion');
    }

    /**
     * Verificar si el usuario es profesor (rol docente; no incluye solo-coordinador).
     */
    public function isProfesor(): bool
    {
        return $this->role === 'profesor' || $this->hasRole('profesor');
    }

    /**
     * Verificar si el usuario es alumno
     */
    public function isAlumno(): bool
    {
        return $this->hasRole('alumno');
    }

    /**
     * Dirección / admin (alias explícito).
     */
    public function isDireccion(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Verificar si es coordinador de sede
     */
    public function isCoordinadorSede(): bool
    {
        return $this->hasRole('coordinador_sede');
    }

    /**
     * Verificar si es coordinador de área
     */
    public function isCoordinadorArea(): bool
    {
        return $this->hasRole('coordinador_area');
    }

    /**
     * Puede ver el menú de gestión (no solo “Mi espacio”).
     */
    public function puedeGestionarOperativo(): bool
    {
        return $this->isAdmin() || $this->isCoordinadorSede() || $this->isCoordinadorArea();
    }

    /**
     * Puede ver reportes (global o de su sede).
     */
    public function puedeVerReportes(): bool
    {
        return $this->isAdmin() || $this->isCoordinadorSede();
    }

    /**
     * Etiqueta legible del rol principal.
     */
    public function etiquetaRol(): string
    {
        if ($this->role === 'direccion' || $this->hasRole('direccion')) {
            return 'Dirección';
        }
        if ($this->role === 'admin' || $this->hasRole('admin')) {
            return 'Administrador';
        }
        if ($this->isCoordinadorSede()) {
            return 'Coordinador de sede';
        }
        if ($this->isCoordinadorArea()) {
            return 'Coordinador de área';
        }
        if ($this->hasRole('profesor') || $this->role === 'profesor') {
            return 'Profesor';
        }
        if ($this->isAlumno()) {
            return 'Alumno';
        }

        return 'Usuario';
    }

    /**
     * IDs de sedes que coordina (columna sedes.coordinador_id o pivot profesor_sede).
     *
     * @return list<int>
     */
    public function sedeIdsCoordinadas(): array
    {
        $prof = $this->profesor;
        if (! $prof) {
            return [];
        }

        $ids = [];
        if (\Illuminate\Support\Facades\Schema::hasColumn('sedes', 'coordinador_id')) {
            $ids = array_merge(
                $ids,
                Sede::query()->where('coordinador_id', $prof->id)->pluck('id')->all()
            );
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('profesor_sede')) {
            $ids = array_merge(
                $ids,
                $prof->sedesConRol()->wherePivot('rol', 'coordinador')->pluck('sedes.id')->all()
            );
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Links del panel de gestión permitidos para coordinadores (admin ve todos).
     */
    public function puedeVerLinkGestion(string $clave): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $sede = [
            'admin.alumnos', 'admin.bloques', 'admin.sedes', 'admin.asistencias',
            'admin.eventos', 'admin.shows', 'comprobantes', 'admin.reportes',
            'programa', 'calendario', 'ayuda',
        ];
        $area = [
            'admin.alumnos', 'admin.asistencias', 'programa', 'calendario', 'ayuda',
        ];

        if ($this->isCoordinadorSede()) {
            return in_array($clave, $sede, true);
        }
        if ($this->isCoordinadorArea()) {
            return in_array($clave, $area, true);
        }

        return false;
    }

    /**
     * Perfil como profesor (un usuario puede ser profesor en unos bloques y alumno en otros)
     */
    public function profesor()
    {
        return $this->hasOne(Profesor::class);
    }

    /**
     * Perfil como alumno (el mismo usuario puede ser alumno en uno o más bloques)
     */
    public function alumno()
    {
        return $this->hasOne(Alumno::class);
    }

    /**
     * Relación con eventos creados
     */
    public function eventos()
    {
        return $this->hasMany(Evento::class, 'created_by');
    }

    /**
     * Control simple de accesos por módulo/submódulo.
     *
     * - Admin/dirección: siempre tiene acceso.
     * - Coordinadores: acceso a su set operativo (salvo bloqueo explícito en modulos_access).
     * - Otros: si la clave existe y es false → bloquea; si no existe → permite.
     */
    public function tieneAccesoModulo(string $clave): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $map = is_array($this->modulos_access) ? $this->modulos_access : [];

        if (array_key_exists($clave, $map) && ! (bool) $map[$clave]) {
            return false;
        }

        if ($this->isCoordinadorSede() || $this->isCoordinadorArea()) {
            return $this->puedeVerLinkGestion($clave);
        }

        if (! array_key_exists($clave, $map)) {
            return true;
        }

        return (bool) $map[$clave];
    }
}
