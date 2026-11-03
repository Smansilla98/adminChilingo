<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bloque extends Model
{
    use Auditable;

    /** Tambores disponibles (igual que en Excel: Repique, Medio, Redoblante, Fondo Agudo, Fondo Grave, Timbal) */
    public const TAMBORES_DISPONIBLES = [
        'Repique',
        'Medio',
        'Redoblante',
        'Fondo Agudo',
        'Fondo Grave',
        'Timbal',
    ];

    protected $fillable = [
        'nombre',
        'año',
        'funciona_desde',
        'profesor_id',
        'corresponde_a',
        'sede_id',
        'cantidad_max_alumnos',
        'tambores',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'año' => 'integer',
        'funciona_desde' => 'integer',
        'cantidad_max_alumnos' => 'integer',
        'tambores' => 'array',
    ];

    /** Años que lleva funcionando (null si no se cargó desde cuándo). */
    public function aniosFuncionando(): ?int
    {
        return $this->funciona_desde ? max(0, (int) now()->year - $this->funciona_desde) : null;
    }

    /**
     * Año del programa que corresponde por antigüedad: el primer año de vida es 1°,
     * y desde el sexto en adelante queda en 6° (el programa no sigue más allá).
     */
    public function anioSugeridoPorAntiguedad(): ?int
    {
        $anios = $this->aniosFuncionando();

        return $anios === null ? null : min(6, $anios + 1);
    }

    /** Texto corto para listados: «desde 2009 · 17 años». */
    public function antiguedadTexto(): ?string
    {
        $anios = $this->aniosFuncionando();
        if ($anios === null) {
            return null;
        }

        return 'desde '.$this->funciona_desde.' · '.match (true) {
            $anios === 0 => 'primer año',
            $anios === 1 => '1 año',
            default => $anios.' años',
        };
    }

    /**
     * Relación con profesor titular (columna legada; debe coincidir con pivot rol titular).
     */
    public function profesor(): BelongsTo
    {
        return $this->belongsTo(Profesor::class);
    }

    /**
     * Profesores asignados al bloque (titular, ayudante, suplente, etc.).
     */
    public function profesores(): BelongsToMany
    {
        return $this->belongsToMany(Profesor::class, 'bloque_profesor')
            ->withPivot('rol')
            ->withTimestamps();
    }

    /**
     * Sincroniza la fila pivot titular con bloques.profesor_id (tras crear/editar bloque).
     */
    public function syncProfesorTitularEnPivot(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('bloque_profesor')) {
            return;
        }

        \Illuminate\Support\Facades\DB::table('bloque_profesor')
            ->where('bloque_id', $this->id)
            ->where('rol', 'titular')
            ->delete();

        if ($this->profesor_id) {
            $existe = \Illuminate\Support\Facades\DB::table('bloque_profesor')
                ->where('bloque_id', $this->id)
                ->where('profesor_id', $this->profesor_id)
                ->first();

            if ($existe) {
                \Illuminate\Support\Facades\DB::table('bloque_profesor')
                    ->where('id', $existe->id)
                    ->update(['rol' => 'titular', 'updated_at' => now()]);
            } else {
                $this->profesores()->attach($this->profesor_id, ['rol' => 'titular']);
            }
        }
    }

    /**
     * Relación con sede
     */
    /** Nombre del docente titular (pivot bloque_profesor; si no, la columna legada). */
    public function nombreTitular(): ?string
    {
        $legado = $this->profesor_id ? $this->profesor : null;
        if ($legado instanceof Profesor) {
            return $legado->nombre;
        }
        $titular = $this->profesores->firstWhere('pivot.rol', 'titular') ?? $this->profesores->first();

        return $titular instanceof Profesor ? $titular->nombre : null;
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class);
    }

    /**
     * Alumnos inscritos en este bloque (un alumno puede estar en varios bloques)
     */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_bloque')
            ->withPivot('es_principal')
            ->withTimestamps();
    }

    /**
     * Horarios semanales del bloque (día + hora)
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(BloqueHorario::class, 'bloque_id');
    }

    /**
     * Shows en los que participa este bloque
     */
    public function shows(): BelongsToMany
    {
        return $this->belongsToMany(Show::class, 'show_bloque')->withTimestamps();
    }

    /**
     * Relación con eventos
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    /**
     * Relación con asistencias
     */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /**
     * Obtener cantidad de alumnos inscritos (activos) en este bloque
     */
    public function getCantidadAlumnosAttribute(): int
    {
        return $this->alumnos()->where('alumnos.activo', true)->count();
    }

    /**
     * Verificar si tiene cupos disponibles
     */
    public function tieneCuposDisponibles(): bool
    {
        return $this->cantidad_alumnos < $this->cantidad_max_alumnos;
    }
}
