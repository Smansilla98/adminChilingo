<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\CatalogoPermisos;
use App\Support\GuiaDireccion;

class AyudaController extends Controller
{
    public function index()
    {
        return view('ayuda.index');
    }

    /** Guía paso a paso para dirección y administración. */
    public function direccion()
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->puedeGestionarOperativo(), 403, 'Esta guía es para dirección y administración. La tuya está en Ayuda.');

        return view('ayuda.direccion', ['secciones' => GuiaDireccion::secciones()]);
    }

    public function roles()
    {
        return view('roles.index', ['guia' => CatalogoPermisos::guia()]);
    }
}
