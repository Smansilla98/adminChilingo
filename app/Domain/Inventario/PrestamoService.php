<?php

namespace App\Domain\Inventario;

use App\Models\Auditoria;
use App\Models\InventarioItem;
use App\Models\InventarioPrestamo;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Préstamo de instrumentos a alumnos o a personas. Mientras un préstamo está abierto, el
 * instrumento no figura disponible (no se puede prestar de nuevo ni sumar a un show).
 * Lo usan el panel web y la API.
 */
class PrestamoService
{
    /** @return array<string, mixed> */
    public static function reglas(): array
    {
        return [
            'alumno_id' => 'nullable|integer|exists:alumnos,id',
            'persona_id' => 'nullable|integer|exists:personas,id',
            'responsable_nombre' => 'nullable|string|max:255',
            'entregado_el' => 'required|date',
            'devolucion_prevista' => 'required|date|after_or_equal:entregado_el',
            'deposito' => 'nullable|numeric|min:0|max:9999999',
            'condicion_entrega' => 'required|in:'.implode(',', array_keys(InventarioItem::ESTADOS)),
            'notas' => 'nullable|string|max:400',
        ];
    }

    /** @return array<string, mixed> */
    public static function reglasDevolucion(): array
    {
        return [
            'devuelto_el' => 'required|date',
            'condicion_devolucion' => 'required|in:'.implode(',', array_keys(InventarioItem::ESTADOS)),
            'deposito_devuelto' => 'nullable|boolean',
            'notas' => 'nullable|string|max:400',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  validados con reglas()
     */
    public function prestar(InventarioItem $item, array $datos, User $por): InventarioPrestamo
    {
        if ($item->es_consumible || $item->estado === 'baja') {
            throw ValidationException::withMessages(['inventario' => 'Este ítem no se puede prestar (es consumible o está dado de baja).']);
        }
        if ($this->prestamoAbierto($item)) {
            throw ValidationException::withMessages(['inventario' => 'El instrumento ya está prestado. Devolvelo antes de prestarlo de nuevo.']);
        }
        if (empty($datos['alumno_id']) && empty($datos['persona_id']) && blank($datos['responsable_nombre'] ?? null)) {
            throw ValidationException::withMessages(['responsable_nombre' => 'Elegí a quién se presta (alumno, persona o un nombre).']);
        }

        return DB::transaction(function () use ($item, $datos, $por) {
            $prestamo = InventarioPrestamo::query()->create([
                'inventario_item_id' => $item->id,
                'alumno_id' => $datos['alumno_id'] ?? null,
                'persona_id' => $datos['persona_id'] ?? null,
                'responsable_nombre' => filled($datos['responsable_nombre'] ?? null) ? trim((string) $datos['responsable_nombre']) : null,
                'entregado_el' => $datos['entregado_el'],
                'devolucion_prevista' => $datos['devolucion_prevista'],
                'deposito' => $datos['deposito'] ?? null,
                'deposito_devuelto' => false,
                'condicion_entrega' => $datos['condicion_entrega'],
                'notas' => $datos['notas'] ?? null,
                'entregado_por' => $por->id,
            ]);
            app(InventarioService::class)->registrar($item, $por->id, 'prestamo', 'Préstamo a '.$prestamo->responsable().' hasta el '.$prestamo->devolucion_prevista->format('d/m/Y'));
            Auditoria::registrar('inventario.prestamo', $item, null, ['prestamo_id' => $prestamo->id]);

            return $prestamo;
        });
    }

    /**
     * @param  array<string, mixed>  $datos  validados con reglasDevolucion()
     */
    public function devolver(InventarioPrestamo $prestamo, array $datos, User $por): InventarioPrestamo
    {
        if ($prestamo->devuelto_el) {
            throw ValidationException::withMessages(['prestamo' => 'Ese préstamo ya fue devuelto.']);
        }

        return DB::transaction(function () use ($prestamo, $datos, $por) {
            $item = $prestamo->item;
            $prestamo->update([
                'devuelto_el' => $datos['devuelto_el'],
                'condicion_devolucion' => $datos['condicion_devolucion'],
                'deposito_devuelto' => (bool) ($datos['deposito_devuelto'] ?? false),
                'recibido_por' => $por->id,
                'notas' => filled($datos['notas'] ?? null) ? trim(($prestamo->notas ? $prestamo->notas."\n" : '').$datos['notas']) : $prestamo->notas,
            ]);
            // La condición con que vuelve queda como estado del ítem (si se rompió, en reparación).
            if ($item->estado !== $datos['condicion_devolucion']) {
                $item->update(['estado' => $datos['condicion_devolucion']]);
            }
            app(InventarioService::class)->registrar($item, $por->id, 'devolucion', 'Devuelto por '.$prestamo->responsable().' en condición «'.(InventarioItem::ESTADOS[$datos['condicion_devolucion']] ?? $datos['condicion_devolucion']).'»');
            Auditoria::registrar('inventario.devolucion', $item, null, ['prestamo_id' => $prestamo->id]);

            return $prestamo->fresh();
        });
    }

    public function prestamoAbierto(InventarioItem $item): ?InventarioPrestamo
    {
        return InventarioPrestamo::query()->where('inventario_item_id', $item->id)->whereNull('devuelto_el')->first();
    }

    /**
     * Préstamos por estado (prestado | vencido | devuelto | todos), acotados por sede del ítem.
     *
     * @return Builder<InventarioPrestamo>
     */
    public function consulta(?string $estado = null, ?int $sedeId = null): Builder
    {
        $query = InventarioPrestamo::query()->with(['item:id,nombre,codigo,sede_id', 'item.sede:id,nombre', 'alumno:id,nombre_apellido', 'persona:id,nombre,apellido'])
            ->orderByDesc('entregado_el')->orderByDesc('id');
        match ($estado) {
            'vencido' => $query->vencidos(),
            'prestado' => $query->activos()->whereDate('devolucion_prevista', '>=', now()->toDateString()),
            'devuelto' => $query->whereNotNull('devuelto_el'),
            default => null,
        };
        if ($sedeId) {
            $query->whereHas('item', fn ($q) => $q->where('sede_id', $sedeId));
        }

        return $query;
    }

    /** @return array<string, mixed> */
    public static function presentar(InventarioPrestamo $p): array
    {
        return [
            'id' => $p->id,
            'item' => ['id' => $p->inventario_item_id, 'nombre' => $p->item?->nombre, 'codigo' => $p->item?->codigo, 'sede' => $p->item?->sede?->nombre],
            'responsable' => $p->responsable(),
            'alumno_id' => $p->alumno_id,
            'persona_id' => $p->persona_id,
            'entregado_el' => $p->entregado_el->toDateString(),
            'devolucion_prevista' => $p->devolucion_prevista->toDateString(),
            'devuelto_el' => $p->devuelto_el?->toDateString(),
            'estado' => $p->estado(),
            'estado_nombre' => $p->etiquetaEstado(),
            'deposito' => $p->deposito !== null ? (float) $p->deposito : null,
            'deposito_devuelto' => (bool) $p->deposito_devuelto,
            'condicion_entrega' => $p->condicion_entrega,
            'condicion_devolucion' => $p->condicion_devolucion,
            'notas' => $p->notas,
        ];
    }
}
