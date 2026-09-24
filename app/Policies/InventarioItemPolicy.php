<?php

namespace App\Policies;

use App\Models\InventarioItem;
use App\Models\User;

class InventarioItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->acceso()->puede('inventario.view');
    }

    public function view(User $user, InventarioItem $item): bool
    {
        return $user->acceso()->puedeEnSede('inventario.view', (int) $item->sede_id);
    }

    public function create(User $user): bool
    {
        return $user->acceso()->puede('inventario.create');
    }

    public function update(User $user, InventarioItem $item): bool
    {
        return $user->acceso()->puedeEnSede('inventario.update', (int) $item->sede_id);
    }

    public function delete(User $user, InventarioItem $item): bool
    {
        return $user->acceso()->puedeEnSede('inventario.delete', (int) $item->sede_id);
    }
}
