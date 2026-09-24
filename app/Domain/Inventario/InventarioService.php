<?php

namespace App\Domain\Inventario;

use App\Models\InventarioItem;
use App\Models\InventarioMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alta y movimientos de inventario. Compartido por el panel web y la API.
 */
class InventarioService
{
    /** Reglas de validación de un ítem (web y API). */
    public static function reglas(?int $id = null): array
    {
        return [
            'sede_id' => 'required|exists:sedes,id',
            'tipo' => 'required|string|max:30', // se admite texto libre: hay ítems históricos con tipos fuera del catálogo
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:40|unique:inventario_items,codigo'.($id ? ','.$id : ''),
            'es_consumible' => 'boolean',
            'cantidad' => 'required|numeric|min:0',
            'unidad' => 'nullable|string|max:20',
            'propietario_tipo' => 'required|in:escuela,alumno',
            'alumno_id' => 'nullable|exists:alumnos,id',
            'marca' => 'nullable|string|max:255',
            'modelo' => 'nullable|string|max:255',
            'linea' => 'nullable|string|max:255',
            'material' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'medida' => 'nullable|string|max:255',
            'diametro_pulgadas' => 'nullable|numeric|min:0|max:99.99',
            'torres' => 'nullable|integer|min:0|max:999',
            'anio_fabricacion' => 'nullable|integer|min:1900|max:2100',
            'origen_adquisicion' => 'nullable|in:comprado,donado,prestado,otro',
            'fecha_adquisicion' => 'nullable|date',
            'precio' => 'nullable|numeric|min:0',
            'estado' => 'required|in:'.implode(',', array_keys(InventarioItem::ESTADOS)),
            'reparado_en' => 'nullable|date',
            'detalle_reparacion' => 'nullable|string',
            'utilitario' => 'boolean',
            'notas' => 'nullable|string',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public static function normalizar(array $datos): array
    {
        $datos['es_consumible'] = (bool) ($datos['es_consumible'] ?? false);
        $datos['utilitario'] = (bool) ($datos['utilitario'] ?? false);
        if (($datos['propietario_tipo'] ?? 'escuela') !== 'alumno') {
            $datos['alumno_id'] = null;
        }
        $codigo = trim((string) ($datos['codigo'] ?? ''));
        $datos['codigo'] = $codigo === '' ? null : $codigo;
        if (! $datos['es_consumible']) {
            $datos['cantidad'] = 1;
            $datos['unidad'] = ($datos['unidad'] ?? null) ?: 'u';
        }

        return $datos;
    }

    /**
     * @param  array<string, mixed>  $datos  ya validados
     */
    public function crear(array $datos, ?int $userId): InventarioItem
    {
        return DB::transaction(function () use ($datos, $userId) {
            $item = InventarioItem::query()->create(self::normalizar($datos));
            $item->asegurarCodigo();
            $this->registrar($item, $userId, 'ingreso', 'Alta en inventario');

            return $item;
        });
    }

    /**
     * Movimiento: puede cambiar la sede y/o el estado del ítem y siempre deja registro.
     */
    public function registrarMovimiento(InventarioItem $item, ?int $userId, string $tipo, ?string $nota, ?int $sedeId, ?string $estado): InventarioItem
    {
        return DB::transaction(function () use ($item, $userId, $tipo, $nota, $sedeId, $estado) {
            if ($sedeId && $sedeId !== (int) $item->sede_id) {
                $item->update(['sede_id' => $sedeId]);
            }
            if ($estado && $estado !== $item->estado) {
                $item->update(['estado' => $estado]);
            }
            $this->registrar($item, $userId, $tipo, $nota, $sedeId ?: (int) $item->sede_id);

            return $item->fresh();
        });
    }

    public function registrar(InventarioItem $item, ?int $userId, string $tipo, ?string $nota = null, ?int $sedeId = null): void
    {
        if (! Schema::hasTable('inventario_movimientos')) {
            return;
        }
        InventarioMovimiento::query()->create([
            'inventario_item_id' => $item->id,
            'user_id' => $userId,
            'sede_id' => $sedeId ?? $item->sede_id,
            'tipo' => array_key_exists($tipo, InventarioMovimiento::TIPOS) ? $tipo : 'asignado',
            'nota' => $nota,
        ]);
    }
}
