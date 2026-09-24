<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SedeResource;
use App\Models\Sede;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SedeController extends Controller
{
    /** Sedes donde la persona tiene alguna función (o todas, con alcance global). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $acceso = $request->user()->acceso();
        $query = Sede::query()->where('activo', true)->orderBy('nombre');
        if (! $acceso->puedeGlobal('sedes.view')) {
            $ids = [];
            foreach ($acceso->roles() as $r) {
                if ($r->sedeId) {
                    $ids[] = $r->sedeId;
                }
            }
            $query->whereIn('id', array_values(array_unique($ids)) ?: [0]);
        }

        return SedeResource::collection($query->get());
    }
}
