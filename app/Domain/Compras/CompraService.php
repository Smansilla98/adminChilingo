<?php

namespace App\Domain\Compras;

use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Órdenes de compra (web y API). Se operan dentro de la sede con `compras.create`;
 * pasar una orden a aprobada o recibida es una decisión de gasto y requiere
 * `compras.approve` en esa sede.
 */
class CompraService
{
    /** @return array<string, mixed> */
    public function reglas(): array
    {
        return [
            'sede_id' => 'required|exists:sedes,id',
            'motivo' => 'required|in:'.implode(',', array_keys(OrdenCompra::MOTIVOS)),
            'estado' => 'required|in:'.implode(',', array_keys(OrdenCompra::ESTADOS)),
            'fecha_objetivo' => 'nullable|date',
            'justificacion' => 'nullable|string',
        ];
    }

    /** Ítems como lista de objetos (API). La web los arma desde columnas del formulario. */
    public function reglasItems(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.descripcion' => 'required|string|max:255',
            'items.*.tipo' => 'nullable|string|max:60',
            'items.*.familia' => 'nullable|string|max:60',
            'items.*.marca' => 'nullable|string|max:120',
            'items.*.modelo' => 'nullable|string|max:120',
            'items.*.medida' => 'nullable|string|max:60',
            'items.*.cantidad' => 'nullable|numeric|min:0.01',
            'items.*.unidad' => 'nullable|string|max:20',
            'items.*.precio_estimado' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos  validados con reglas()
     * @param  list<array<string, mixed>>  $items
     */
    public function guardar(?OrdenCompra $orden, array $datos, array $items, User $por): OrdenCompra
    {
        if ($orden) {
            $this->asegurarAlcance($por, 'compras.create', (int) $orden->sede_id);
        }
        $this->asegurarAlcance($por, 'compras.create', (int) $datos['sede_id']);
        $this->asegurarAprobacion($por, $orden, (string) $datos['estado'], (int) $datos['sede_id']);
        $items = $this->normalizarItems($items);

        return DB::transaction(function () use ($orden, $datos, $items, $por) {
            if ($orden) {
                $orden->update($datos);
                $orden->items()->delete();
            } else {
                $orden = OrdenCompra::create($datos + ['created_by' => $por->id]);
            }
            $total = 0;
            foreach ($items as $data) {
                $item = new OrdenCompraItem($data);
                $item->orden_compra_id = $orden->id;
                $item->subtotal_estimado = $item->cantidad * ($item->precio_estimado ?? 0);
                $item->save();
                $total += $item->subtotal_estimado;
            }
            $orden->update(['total_estimado' => $total]);

            return $orden;
        });
    }

    /** Cambiar solo el estado (enviar, aprobar, recibir, cancelar). */
    public function cambiarEstado(OrdenCompra $orden, string $estado, User $por): OrdenCompra
    {
        if (! array_key_exists($estado, OrdenCompra::ESTADOS)) {
            throw ValidationException::withMessages(['estado' => 'Estado inválido.']);
        }
        $this->asegurarAlcance($por, 'compras.create', (int) $orden->sede_id);
        $this->asegurarAprobacion($por, $orden, $estado, (int) $orden->sede_id);
        $orden->update(['estado' => $estado]);

        return $orden;
    }

    public function eliminar(OrdenCompra $orden, User $por): void
    {
        $this->asegurarAlcance($por, 'compras.create', (int) $orden->sede_id);
        DB::transaction(function () use ($orden) {
            $orden->items()->delete();
            $orden->delete();
        });
    }

    public function puede(User $user, string $permiso, int $sedeId): bool
    {
        return $user->acceso()->puedeEnSede($permiso, $sedeId);
    }

    public function asegurarAlcance(User $user, string $permiso, int $sedeId): void
    {
        if (! $this->puede($user, $permiso, $sedeId)) {
            abort(403, 'No podés operar compras de esa sede.');
        }
    }

    private function asegurarAprobacion(User $user, ?OrdenCompra $orden, string $nuevo, int $sedeId): void
    {
        $cambia = $orden === null || $orden->estado !== $nuevo;
        if ($cambia && in_array($nuevo, ['aprobada', 'recibida'], true) && ! $user->acceso()->puedeEnSede('compras.approve', $sedeId)) {
            abort(403, 'Aprobar una orden de compra requiere permiso de aprobación.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizarItems(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            $desc = trim((string) ($i['descripcion'] ?? ''));
            if ($desc === '') {
                continue;
            }
            $cantidad = isset($i['cantidad']) && (float) $i['cantidad'] > 0 ? (float) $i['cantidad'] : 1;
            $precio = isset($i['precio_estimado']) && $i['precio_estimado'] !== '' && $i['precio_estimado'] !== null ? (float) $i['precio_estimado'] : null;
            $out[] = [
                'tipo' => $i['tipo'] ?? null,
                'familia' => $i['familia'] ?? null,
                'descripcion' => $desc,
                'marca' => $i['marca'] ?? null,
                'modelo' => $i['modelo'] ?? null,
                'medida' => $i['medida'] ?? null,
                'cantidad' => $cantidad,
                'unidad' => ($i['unidad'] ?? null) ?: 'u',
                'precio_estimado' => $precio,
            ];
        }
        if ($out === []) {
            throw ValidationException::withMessages(['items' => 'Agregá al menos un ítem a la orden de compra.']);
        }

        return $out;
    }
}
