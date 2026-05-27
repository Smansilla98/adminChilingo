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
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
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
        ];
    }

    /**
     * Verificar si el usuario es admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->hasRole('admin');
    }

    /**
     * Verificar si el usuario es profesor
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
     * Verificar si es dirección / admin
     */
    public function isDireccion(): bool
    {
        return $this->isAdmin() || $this->hasRole('direccion');
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
     * - Admin: siempre tiene acceso (para no bloquear administración).
     * - Otros usuarios: si la clave existe y es false → bloquea; si no existe → permite.
     */
    public function tieneAccesoModulo(string $clave): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $map = is_array($this->modulos_access) ? $this->modulos_access : [];

        // Si la clave no existe, por defecto permitimos (fallback seguro para despliegues graduales).
        if (! array_key_exists($clave, $map)) {
            return true;
        }

        return (bool) $map[$clave];
    }
}
