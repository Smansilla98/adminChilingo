<?php

namespace App\Models;

use App\Models\Alumno;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Profesor extends Model
{
    public const ROLES_BLOQUE = ['titular', 'ayudante', 'suplente', 'coordinador_clase'];

    /** Roles del profesor en una sede (puede tener varios por sede). */
    public const ROLES_SEDE = [
        'profesor' => 'Profesor',
        'encargado' => 'Encargado',
        'coordinador' => 'Coordinador de sede',
    ];

    /** Tabla real: 'profesores'. Laravel infiere 'profesors' por defecto. */
    protected $table = 'profesores';

    public function getTable(): string
    {
        return 'profesores';
    }

    protected $fillable = [
        'user_id',
        'nombre',
        'telefono',
        'email',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Usuario asociado (un profesor puede ser también alumno en otro bloque)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Bloques en los que participa, con rol en el pivot (titular, ayudante, etc.).
     */
    public function bloques(): BelongsToMany
    {
        return $this->belongsToMany(Bloque::class, 'bloque_profesor')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * Roles en sedes (profesor, encargado, coordinador).
     */
    public function sedesConRol(): BelongsToMany
    {
        return $this->belongsToMany(Sede::class, 'profesor_sede')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * IDs de bloques donde tiene acceso (titular en columna o cualquier rol en pivot).
     *
     * @return Collection<int, int>
     */
    public function bloqueIdsDondeParticipa(): Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('bloque_profesor')) {
            return Bloque::query()->where('profesor_id', $this->id)->pluck('id');
        }

        $desdePivot = $this->bloques()->pluck('bloques.id');
        $desdeColumna = Bloque::query()->where('profesor_id', $this->id)->pluck('id');

        return $desdePivot->merge($desdeColumna)->unique()->values();
    }

    /**
     * @param  array<int, array{bloque_id: int, rol: string}>  $filas
     */
    public function sincronizarAsignacionesBloques(array $filas): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('bloque_profesor')) {
            return;
        }

        DB::transaction(function () use ($filas) {
            $wasTitularBloqueIds = Bloque::query()->where('profesor_id', $this->id)->pluck('id')->all();

            $this->bloques()->detach();

            $normalize = [];
            foreach ($filas as $row) {
                $bid = isset($row['bloque_id']) ? (int) $row['bloque_id'] : 0;
                if ($bid <= 0) {
                    continue;
                }
                $rol = $row['rol'] ?? 'ayudante';
                if (! in_array($rol, self::ROLES_BLOQUE, true)) {
                    $rol = 'ayudante';
                }
                $normalize[$bid] = ['rol' => $rol];
            }

            foreach ($normalize as $bloqueId => $pivot) {
                $this->bloques()->attach($bloqueId, $pivot);
            }

            foreach ($normalize as $bloqueId => $pivot) {
                if ($pivot['rol'] !== 'titular') {
                    continue;
                }
                DB::table('bloque_profesor')
                    ->where('bloque_id', $bloqueId)
                    ->where('rol', 'titular')
                    ->where('profesor_id', '!=', $this->id)
                    ->delete();
                Bloque::query()->whereKey($bloqueId)->update(['profesor_id' => $this->id]);
            }

            $nuevosTitulares = collect($normalize)->filter(fn ($p) => $p['rol'] === 'titular')->keys()->all();
            foreach ($wasTitularBloqueIds as $bid) {
                if (in_array((int) $bid, array_map('intval', $nuevosTitulares), true)) {
                    continue;
                }
                $tieneTitularPivot = DB::table('bloque_profesor')
                    ->where('bloque_id', $bid)
                    ->where('profesor_id', $this->id)
                    ->where('rol', 'titular')
                    ->exists();
                if ($tieneTitularPivot) {
                    continue;
                }
                $b = Bloque::query()->find($bid);
                if ($b && (int) $b->profesor_id === (int) $this->id) {
                    $b->forceFill(['profesor_id' => null])->saveQuietly();
                }
            }
        });
    }

    /**
     * Sede de la que es coordinador (si aplica)
     */
    public function sedeCoordinada(): HasOne
    {
        return $this->hasOne(Sede::class, 'coordinador_id');
    }

    /**
     * Áreas que coordina: género, costa, tambores (coordinador de área)
     */
    public function coordinadorAreas(): HasMany
    {
        return $this->hasMany(CoordinadorArea::class);
    }

    /**
     * Relación con eventos
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * Obtener bloques activos
     */
    public function bloquesActivos(): BelongsToMany
    {
        return $this->bloques()->where('bloques.activo', true);
    }

    /**
     * @param  array<int, array{sede_id: int, rol: string}>  $filas
     */
    public function sincronizarRolesSede(array $filas): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('profesor_sede')) {
            return;
        }

        DB::transaction(function () use ($filas) {
            $this->sedesConRol()->detach();
            $coordinadorSedeIds = [];

            foreach ($filas as $row) {
                $sid = (int) ($row['sede_id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $rol = (string) ($row['rol'] ?? 'profesor');
                if (! array_key_exists($rol, self::ROLES_SEDE)) {
                    $rol = 'profesor';
                }
                $this->sedesConRol()->attach($sid, ['rol' => $rol]);
                if ($rol === 'coordinador') {
                    $coordinadorSedeIds[$sid] = true;
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('sedes', 'coordinador_id')) {
                Sede::query()->where('coordinador_id', $this->id)->update(['coordinador_id' => null]);
                foreach (array_keys($coordinadorSedeIds) as $sedeId) {
                    Sede::query()->whereKey($sedeId)->update(['coordinador_id' => $this->id]);
                }
            }
        });
    }

    /**
     * Roles Spatie sugeridos según perfiles (alumno + profesor + coordinación).
     */
    public function sincronizarRolesUsuario(): void
    {
        $user = $this->user;
        if (! $user) {
            return;
        }

        $roles = ['profesor'];
        if ($this->alumnoPerfil()) {
            $roles[] = 'alumno';
        }
        if ($this->sedeCoordinada()->exists() || $this->sedesConRol()->wherePivot('rol', 'coordinador')->exists()) {
            $roles[] = 'coordinador_sede';
        }
        if ($this->coordinadorAreas()->exists()) {
            $roles[] = 'coordinador_area';
        }

        foreach (array_unique($roles) as $rol) {
            if (! $user->hasRole($rol)) {
                $user->assignRole($rol);
            }
        }
        if ($user->role !== 'admin') {
            $user->update(['role' => 'profesor']);
        }
    }

    public function alumnoPerfil(): ?Alumno
    {
        if ($this->user_id) {
            return Alumno::query()->where('user_id', $this->user_id)->first();
        }

        return null;
    }
}
