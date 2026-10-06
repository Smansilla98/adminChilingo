<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramaRitmo extends Model
{
    protected $table = 'programa_ritmos';

    protected $fillable = [
        'slug',
        'año',
        'orden',
        'nombre',
        'autor',
        'opcional',
        'notas',
        'resumen',
        'contenido',
        'secciones',
        'enlaces',
        'medios',
        'publicado',
        'vigente',
        'en_programa',
        'nombres_anteriores',
        'estado_nota',
    ];

    protected $casts = [
        'año' => 'integer',
        'orden' => 'integer',
        'opcional' => 'boolean',
        'secciones' => 'array',
        'enlaces' => 'array',
        'medios' => 'array',
        'publicado' => 'boolean',
        'vigente' => 'boolean',
        'en_programa' => 'boolean',
        'nombres_anteriores' => 'array',
    ];

    /**
     * @return array<string, mixed>
     */
    public function mediosNormalizados(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn($this->getTable(), 'medios')) {
            return \App\Support\ProgramaRitmoMedios::estructuraVacia();
        }

        return \App\Support\ProgramaRitmoMedios::normalizar($this->medios);
    }

    /**
     * Toques que forman parte del programa oficial (los retirados quedan fuera).
     * Tolera bases sin migrar.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $consulta
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public static function soloEnPrograma($consulta)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('programa_ritmos', 'en_programa')) {
            $consulta->where('en_programa', true);
        }

        return $consulta;
    }

    public function estaEnPrograma(): bool
    {
        return $this->en_programa ?? true;
    }

    public function sigueVigente(): bool
    {
        return $this->vigente ?? true;
    }

    /**
     * @return list<array{nombre: string, hasta: string|null}>
     */
    public function historialNombres(): array
    {
        return array_values(array_filter(
            is_array($this->nombres_anteriores) ? $this->nombres_anteriores : [],
            fn ($n) => is_array($n) && filled($n['nombre'] ?? null)
        ));
    }

    /**
     * Toque por nombre actual o anterior (para no duplicarlo al reimportar
     * después de un renombre o de un cambio de año).
     */
    public static function porNombre(string $nombre, ?int $año = null): ?self
    {
        $nombre = trim($nombre);
        if ($nombre === '') {
            return null;
        }
        $actual = static::query()->where('nombre', $nombre)
            ->when($año !== null, fn ($q) => $q->orderByRaw('CASE WHEN año = ? THEN 0 ELSE 1 END', [$año]))
            ->first();
        if ($actual || ! \Illuminate\Support\Facades\Schema::hasColumn('programa_ritmos', 'nombres_anteriores')) {
            return $actual;
        }

        $buscado = mb_strtolower($nombre);

        // Pocas decenas de toques: se filtra en PHP (el JSON guarda los acentos escapados).
        return static::query()->whereNotNull('nombres_anteriores')
            ->get(['id', 'nombre', 'nombres_anteriores'])
            ->first(fn (self $r) => collect($r->historialNombres())->contains(fn ($n) => mb_strtolower($n['nombre']) === $buscado))
            ?->fresh();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Toques ordenados por año y orden, sin pedirle a MySQL que ordene `medios`.
     * Ese JSON es grande: el filesort agota el sort buffer (error 1038) cuando
     * la consulta no puede usar el índice (publicado, año, orden).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $consulta
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function traerOrdenados($consulta)
    {
        $ids = (clone $consulta)->reorder()->orderBy('año')->orderBy('orden')->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $porId = static::query()->whereIn('id', $ids->all())->get()->keyBy('id');

        return $ids->map(fn ($id) => $porId->get($id))->filter()->values();
    }

    public function bibliotecaItems()
    {
        return $this->hasMany(BibliotecaItem::class, 'programa_ritmo_id');
    }

    public static function años(): array
    {
        return [
            1 => '1° Año',
            2 => '2° Año',
            3 => '3° Año',
            4 => '4° Año',
            5 => '5° Año',
            6 => '6° Año',
            7 => '7° Año',
        ];
    }

    /**
     * @return array<int, array{titulo: string, contenido: string}>
     */
    public function seccionesProfundizacion(): array
    {
        $secciones = $this->secciones;
        if (is_array($secciones) && $secciones !== []) {
            return array_values(array_filter($secciones, fn ($s) => ! empty($s['titulo'] ?? $s['contenido'] ?? null)));
        }

        return [
            ['titulo' => 'Contexto del toque', 'contenido' => ''],
            ['titulo' => 'Desarrollo en clase', 'contenido' => ''],
            ['titulo' => 'Referencias y escucha', 'contenido' => ''],
        ];
    }

    public function tieneProfundizacion(): bool
    {
        if (filled($this->resumen) || filled($this->contenido)) {
            return true;
        }
        foreach ($this->seccionesProfundizacion() as $s) {
            if (filled($s['contenido'] ?? null)) {
                return true;
            }
        }
        if (is_array($this->enlaces) && count($this->enlaces) > 0) {
            return true;
        }

        if (\App\Support\ProgramaRitmoMedios::tieneContenidoMultimedia($this->mediosNormalizados())) {
            return true;
        }

        return false;
    }
}
