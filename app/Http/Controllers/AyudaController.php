<?php

namespace App\Http\Controllers;

use App\Domain\Acceso\CatalogoPermisos;

class AyudaController extends Controller
{
    public function index()
    {
        return view('ayuda.index');
    }

    public function roles()
    {
        return view('roles.index', ['guia' => CatalogoPermisos::guia()]);
    }
}
