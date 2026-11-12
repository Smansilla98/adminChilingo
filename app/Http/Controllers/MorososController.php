<?php

namespace App\Http\Controllers;

use App\Domain\Finanzas\MorosidadService;
use App\Domain\Notificaciones\NotificacionService;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Sede;
use App\Services\AmbitoSedeService;
use Illuminate\Http\Request;

/** Morosidad (web): lista con antigüedad de la deuda y recordatorios individuales o masivos. */
class MorososController extends Controller
{
    public function index(Request $request, MorosidadService $morosidad, AmbitoSedeService $ambito)
    {
        $filtros = $request->validate([
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'bloque_id' => 'nullable|integer|exists:bloques,id',
            'tramo' => 'nullable|in:'.implode(',', array_keys(MorosidadService::TRAMOS)),
        ]);
        $deudores = $morosidad->deudores($filtros, $request->user());

        $filtro = $ambito->idsPara($request->user(), 'pagos.view');
        $sedes = Sede::query()->orderBy('nombre');
        $bloques = Bloque::query()->where('activo', true)->with('sede')->orderBy('nombre');
        if ($filtro !== null) {
            $ambito->aplicarSedesCatalogo($sedes, $filtro);
            $ambito->aplicarBloques($bloques, $filtro);
        }

        return view('morosos.index', [
            'deudores' => $deudores,
            'total' => round((float) $deudores->sum('saldo_vencido'), 2),
            'sedes' => $sedes->get(),
            'bloques' => $bloques->get(),
            'tramos' => MorosidadService::TRAMOS,
            'filtros' => $filtros,
        ]);
    }

    public function recordar(Request $request, Alumno $alumno, MorosidadService $morosidad)
    {
        $canales = $this->canales($request);
        $r = $morosidad->recordar($alumno, $request->user(), $canales);

        return back()->with('success', match ($r['estado']) {
            'enviado' => 'Recordatorio enviado a '.$alumno->nombre_apellido.'.',
            'duplicado' => 'Ya se le envió un recordatorio este mes por esos canales: no se repite.',
            'al_dia' => $alumno->nombre_apellido.' ya no tiene cuotas vencidas.',
            'sin_cuenta' => $alumno->nombre_apellido.' no tiene cuenta para recibir avisos.',
            default => 'No se pudo enviar por ningún canal disponible.',
        });
    }

    public function recordarMasivo(Request $request, MorosidadService $morosidad)
    {
        $datos = $request->validate(['alumno_ids' => 'required|array|min:1', 'alumno_ids.*' => 'integer|exists:alumnos,id']);
        $r = $morosidad->recordarMasivo($datos['alumno_ids'], $request->user(), $this->canales($request));

        return back()->with('success', "Recordatorios: {$r['enviado']} enviados, {$r['duplicado']} ya enviados este mes, {$r['sin_envio']} sin canal disponible, {$r['sin_cuenta']} sin cuenta, {$r['al_dia']} al día.");
    }

    /** @return list<string> */
    private function canales(Request $request): array
    {
        $canales = $request->validate(['canales' => 'nullable|array', 'canales.*' => 'in:'.implode(',', NotificacionService::CANALES)])['canales'] ?? [];

        return $canales !== [] ? array_values(array_unique($canales)) : ['interna', 'push'];
    }
}
