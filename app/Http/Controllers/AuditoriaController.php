<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $registros = Auditoria::query()
            ->with('user:id,name,username')
            ->when($request->filled('entidad'), fn ($q) => $q->where('entidad_tipo', $request->input('entidad')))
            ->when($request->filled('entidad_id'), fn ($q) => $q->where('entidad_id', $request->integer('entidad_id')))
            ->when($request->filled('accion'), fn ($q) => $q->where('accion', $request->input('accion')))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->integer('user_id')))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $entidades = Auditoria::query()->distinct()->orderBy('entidad_tipo')->pluck('entidad_tipo');

        return view('auditoria.index', compact('registros', 'entidades'));
    }
}
