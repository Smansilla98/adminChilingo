<?php

namespace App\Domain\Acceso;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dónde vale un permiso: en toda la escuela, en ciertas sedes o en ciertos bloques.
 * Un bloque recuerda su sede para poder resolver "¿este bloque está en mi sede?" sin consultas.
 */
final class Alcance
{
    private bool $global = false;

    /** @var array<int, true> */
    private array $sedes = [];

    /** @var array<int, int|null> bloque_id => sede_id */
    private array $bloques = [];

    public static function vacio(): self
    {
        return new self;
    }

    public static function total(): self
    {
        $a = new self;
        $a->global = true;

        return $a;
    }

    public function agregarGlobal(): self
    {
        $this->global = true;

        return $this;
    }

    public function agregarSede(int $sedeId): self
    {
        if ($sedeId > 0) {
            $this->sedes[$sedeId] = true;
        }

        return $this;
    }

    public function agregarBloque(int $bloqueId, ?int $sedeId): self
    {
        if ($bloqueId > 0) {
            $this->bloques[$bloqueId] = $sedeId ?: ($this->bloques[$bloqueId] ?? null);
        }

        return $this;
    }

    public function unir(self $otro): self
    {
        $this->global = $this->global || $otro->global;
        $this->sedes += $otro->sedes;
        foreach ($otro->bloques as $id => $sede) {
            $this->agregarBloque($id, $sede);
        }

        return $this;
    }

    public function esGlobal(): bool
    {
        return $this->global;
    }

    public function estaVacio(): bool
    {
        return ! $this->global && $this->sedes === [] && $this->bloques === [];
    }

    /** @return list<int> */
    public function sedeIds(): array
    {
        return array_keys($this->sedes);
    }

    /** @return list<int> */
    public function bloqueIds(): array
    {
        return array_keys($this->bloques);
    }

    /**
     * Sedes con alcance completo más las sedes de los bloques con alcance.
     *
     * @return list<int>
     */
    public function sedesTocadas(): array
    {
        $ids = $this->sedeIds();
        foreach ($this->bloques as $sede) {
            if ($sede) {
                $ids[] = (int) $sede;
            }
        }

        return array_values(array_unique($ids));
    }

    public function incluyeSede(?int $sedeId): bool
    {
        return $this->global || ($sedeId && isset($this->sedes[$sedeId]));
    }

    public function incluyeBloque(?int $bloqueId, ?int $sedeDelBloque = null): bool
    {
        if ($this->global) {
            return true;
        }
        if ($bloqueId && array_key_exists($bloqueId, $this->bloques)) {
            return true;
        }
        $sede = $sedeDelBloque ?: ($bloqueId ? ($this->bloques[$bloqueId] ?? null) : null);

        return $sede !== null && isset($this->sedes[$sede]);
    }

    /**
     * ¿Alcanza a algo de lo que pertenece el registro (sus sedes o bloques)?
     *
     * @param  list<int>  $sedeIds
     * @param  array<int, int|null>  $bloques  bloque_id => sede_id
     */
    public function incluyeAlguno(array $sedeIds, array $bloques): bool
    {
        if ($this->global) {
            return true;
        }
        foreach ($sedeIds as $s) {
            if ($s && isset($this->sedes[(int) $s])) {
                return true;
            }
        }
        foreach ($bloques as $b => $s) {
            if ($this->incluyeBloque((int) $b, $s ? (int) $s : null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filtra alumnos: por sede principal, por bloques (pivot o columna legacy).
     */
    public function aplicarAlumnos(Builder $query): Builder
    {
        if ($this->global) {
            return $query;
        }
        if ($this->estaVacio()) {
            return $query->whereRaw('1 = 0');
        }
        $sedes = $this->sedeIds() ?: [0];
        $bloques = $this->bloqueIds() ?: [0];
        $tabla = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($sedes, $bloques, $tabla) {
            $q->whereIn($tabla.'.sede_id', $sedes)
                ->orWhereIn($tabla.'.bloque_id', $bloques)
                ->orWhereHas('bloques', fn (Builder $b) => $b->whereIn('bloques.id', $bloques)->orWhereIn('bloques.sede_id', $sedes))
                ->orWhereHas('bloque', fn (Builder $b) => $b->whereIn('sede_id', $sedes));
        });
    }

    /**
     * Filtra una consulta de bloques.
     */
    public function aplicarBloques(Builder $query): Builder
    {
        if ($this->global) {
            return $query;
        }
        if ($this->estaVacio()) {
            return $query->whereRaw('1 = 0');
        }
        $tabla = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($tabla) {
            $q->whereIn($tabla.'.sede_id', $this->sedeIds() ?: [0])
                ->orWhereIn($tabla.'.id', $this->bloqueIds() ?: [0]);
        });
    }

    /**
     * Filtra por una columna sede_id. Con $incluirSedesDeBloques, un profesor de un bloque
     * ve lo de la sede de su bloque (eventos, inventario de la sede, etc.).
     */
    public function aplicarPorSede(Builder|BuilderContract $query, string $columna = 'sede_id', bool $incluirSedesDeBloques = false, bool $incluirSinSede = false): Builder|BuilderContract
    {
        if ($this->global) {
            return $query;
        }
        $ids = $incluirSedesDeBloques ? $this->sedesTocadas() : $this->sedeIds();
        if ($ids === [] && ! $incluirSinSede) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($columna, $ids, $incluirSinSede) {
            $q->whereIn($columna, $ids ?: [0]);
            if ($incluirSinSede) {
                $q->orWhereNull($columna);
            }
        });
    }

    /**
     * Filtra registros que tienen bloque_id (asistencias, cuotas por bloque, etc.).
     */
    public function aplicarPorBloque(Builder $query, string $columna = 'bloque_id', string $relacion = 'bloque'): Builder
    {
        if ($this->global) {
            return $query;
        }
        if ($this->estaVacio()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($columna, $relacion) {
            $q->whereIn($columna, $this->bloqueIds() ?: [0])
                ->orWhereHas($relacion, fn (Builder $b) => $b->whereIn('sede_id', $this->sedeIds() ?: [0]));
        });
    }

    /**
     * Eventos: de toda la escuela (sin sede ni bloque), de una sede o de un bloque.
     * Para lectura, dar clase en un bloque alcanza para ver lo de su sede y lo general.
     */
    public function aplicarEventos(Builder $query, bool $lectura = true): Builder
    {
        if ($this->global) {
            return $query;
        }
        if ($this->estaVacio()) {
            return $query->whereRaw('1 = 0');
        }
        $sedes = ($lectura ? $this->sedesTocadas() : $this->sedeIds()) ?: [0];
        $bloques = $this->bloqueIds() ?: [0];
        $tabla = $query->getModel()->getTable();

        return $query->where(function (Builder $q) use ($sedes, $bloques, $tabla, $lectura) {
            if ($lectura) {
                $q->where(fn (Builder $g) => $g->whereNull($tabla.'.sede_id')->whereNull($tabla.'.bloque_id'));
            }
            $q->orWhereIn($tabla.'.bloque_id', $bloques)
                ->orWhere(fn (Builder $s) => $s->whereNull($tabla.'.bloque_id')->whereIn($tabla.'.sede_id', $sedes))
                ->orWhereHas('bloque', fn (Builder $b) => $b->whereIn('sede_id', $this->sedeIds() ?: [0]));
        });
    }

    /**
     * @return array{global: bool, sedes: list<int>, bloques: list<int>}
     */
    public function toArray(): array
    {
        return [
            'global' => $this->global,
            'sedes' => $this->sedeIds(),
            'bloques' => $this->bloqueIds(),
        ];
    }
}
