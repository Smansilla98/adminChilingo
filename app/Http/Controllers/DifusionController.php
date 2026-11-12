<?php

namespace App\Http\Controllers;

use App\Domain\Notificaciones\DifusionService;
use App\Models\Difusion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mensajes a grupos desde el panel web (permiso difusion.send). Misma lógica que la API.
 */
class DifusionController extends Controller
{
    public function index(Request $request, DifusionService $servicio)
    {
        $query = Difusion::query()->with('autor:id,name')->orderByDesc('id');
        if (! $request->user()->acceso()->puedeGlobal('difusion.send')) {
            $query->where('user_id', $request->user()->id);
        }
        $difusiones = $query->paginate(20);
        $filas = collect($difusiones->items())->map(fn (Difusion $d) => $servicio->presentar($d));

        return view('difusion.index', compact('difusiones', 'filas'));
    }

    public function create(Request $request, DifusionService $servicio)
    {
        return view('difusion.create', ['catalogo' => $servicio->catalogo($request->user())]);
    }

    public function vistaPrevia(Request $request, DifusionService $servicio): JsonResponse
    {
        $reglas = DifusionService::reglas();
        unset($reglas['titulo'], $reglas['mensaje'], $reglas['programada_para']);
        $datos = $request->validate($reglas);
        $filtros = DifusionService::filtros($datos);
        $servicio->autorizar($request->user(), $filtros);

        return response()->json($servicio->vistaPrevia($filtros, (array) $datos['canales']));
    }

    public function store(Request $request, DifusionService $servicio)
    {
        $datos = $request->validate(DifusionService::reglas());
        $datos['alumnos'] = $request->boolean('alumnos');
        $datos['docentes'] = $request->boolean('docentes');
        $datos['solo_con_deuda'] = $request->boolean('solo_con_deuda');
        $datos['confirmar_whatsapp'] = $request->boolean('confirmar_whatsapp');
        $difusion = $servicio->crear($datos, $request->user());

        return redirect()->route('difusion.show', $difusion)->with('success', $difusion->estado === 'enviada'
            ? "Mensaje enviado a {$difusion->destinatarios} personas."
            : 'Mensaje programado para el '.$difusion->programada_para?->format('d/m H:i').'.');
    }

    public function show(Request $request, Difusion $difusion, DifusionService $servicio)
    {
        abort_unless($servicio->puedeVer($request->user(), $difusion), 403);

        return view('difusion.show', ['d' => $servicio->presentar($difusion, true), 'difusion' => $difusion]);
    }

    public function cancelar(Request $request, Difusion $difusion, DifusionService $servicio)
    {
        abort_unless($servicio->puedeVer($request->user(), $difusion), 403);
        $servicio->cancelar($difusion);

        return redirect()->route('difusion.show', $difusion)->with('success', 'Mensaje cancelado: no se va a enviar.');
    }
}
