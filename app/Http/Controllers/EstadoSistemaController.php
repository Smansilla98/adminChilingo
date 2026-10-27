<?php

namespace App\Http\Controllers;

use App\Domain\Datos\Respaldo;
use App\Support\EstadoSistema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstadoSistemaController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->isAdmin(), 403);

        return view('estado-sistema', ['chequeos' => EstadoSistema::chequeos($request)]);
    }

    /** Descarga del backup más reciente, para guardar una copia fuera de Railway. */
    public function respaldo(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $archivo = Respaldo::archivos()[0] ?? null;
        abort_if($archivo === null, 404, 'Todavía no hay backups.');

        return Storage::disk('comprobantes')->download($archivo, basename($archivo));
    }
}
