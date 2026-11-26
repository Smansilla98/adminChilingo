<?php

declare(strict_types=1);

namespace App\Http\Controllers\Modulos;

use App\Http\Controllers\Controller;
use App\Support\ModulosInicio;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Página de un módulo del inicio: sus pantallas y su acción principal, sin paneles superpuestos. */
class ModuloController extends Controller
{
    public function show(Request $request, string $clave): View
    {
        $modulo = ModulosInicio::uno($request->user(), $request, $clave);
        abort_if($modulo === null, 404);

        return view('modulos.show', ['modulo' => $modulo]);
    }
}
