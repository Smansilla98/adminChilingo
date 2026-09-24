<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventoResource;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\ComprobanteCuotaAlumno;
use App\Models\Evento;
use App\Models\InventarioItem;
use App\Models\Pago;
use App\Services\AmbitoSedeService;
use App\Services\EspacioAlumnoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Home de la app: solo lo que la persona puede usar, en el orden de su contexto.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request, EspacioAlumnoService $espacio, AmbitoSedeService $ambito): JsonResponse
    {
        $user = $request->user();
        $acceso = $user->acceso();
        $rolContexto = explode(':', (string) $request->header('X-Contexto', ''))[0] ?: null;
        $tarjetas = [];

        // Alumno: mi bloque, próxima clase y cuota del mes.
        if ($acceso->tieneRol('alumno')) {
            $alumnos = Alumno::query()->with(['bloques:id,nombre,sede_id', 'sede:id,nombre'])
                ->where(fn ($q) => $q->where('persona_id', $user->persona_id ?: 0)->orWhere('user_id', $user->id))
                ->where('activo', true)->get();
            foreach ($alumnos as $alumno) {
                $datos = $espacio->armar($alumno);
                $tarjetas[] = ['tipo' => 'mi_espacio', 'rol' => 'alumno', 'datos' => [
                    'alumno_id' => $alumno->id,
                    'sede' => $alumno->sede?->nombre,
                    'bloques' => $alumno->bloques->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre])->values(),
                    'proxima_clase' => $datos['proximaClase'] ?? null,
                    'cuota' => $datos['estadoCuota'] ?? null,
                ]];
            }
        }

        // Docente / coordinación: clases de hoy para tomar asistencia.
        if ($acceso->puede('asistencias.create')) {
            $hoy = now();
            $q = Bloque::query()->where('activo', true)->with(['sede:id,nombre', 'horarios'])
                ->whereHas('horarios', fn ($h) => $h->where('dia_semana', $hoy->dayOfWeekIso));
            $acceso->alcance('asistencias.create')->aplicarBloques($q);
            $bloques = $q->orderBy('nombre')->limit(30)->get();
            $tomadas = Asistencia::query()->whereIn('bloque_id', $bloques->pluck('id')->all() ?: [0])
                ->whereDate('fecha', $hoy->toDateString())->distinct()->pluck('bloque_id')->all();
            $tarjetas[] = ['tipo' => 'clases_hoy', 'rol' => 'profesor', 'datos' => $bloques->map(function ($b) use ($hoy, $tomadas) {
                $h = $b->horarios->firstWhere('dia_semana', $hoy->dayOfWeekIso);

                return [
                    'bloque_id' => $b->id,
                    'nombre' => $b->nombre,
                    'sede' => $b->sede?->nombre,
                    'inicio' => $h ? substr((string) $h->hora_inicio, 0, 5) : null,
                    'asistencia_tomada' => in_array($b->id, $tomadas, true),
                ];
            })->sortBy('inicio')->values()];
        }

        // Finanzas: cobrado del mes y comprobantes por revisar.
        if ($acceso->puede('pagos.view') && ($acceso->puedeGlobal('pagos.view') || $acceso->alcance('pagos.view')->sedeIds() !== [])) {
            $pagos = Pago::query()->vigentes()->whereYear('fecha_pago', now()->year)->whereMonth('fecha_pago', now()->month);
            $filtro = $ambito->idsPara($user, 'pagos.view');
            if ($filtro !== null) {
                $ambito->aplicarPagos($pagos, $filtro);
            }
            $comprobantes = ComprobanteCuotaAlumno::query()->where('estado', 'pendiente');
            $alcanceComp = $acceso->alcance('comprobantes.view');
            if (! $alcanceComp->esGlobal()) {
                $comprobantes->whereIn('sede_id', $alcanceComp->sedeIds() ?: [0]);
            }
            $tarjetas[] = ['tipo' => 'finanzas', 'rol' => 'contador', 'datos' => [
                'cobrado_mes' => round((float) $pagos->sum('monto_total'), 2),
                'pagos_mes' => $pagos->count(),
                'comprobantes_pendientes' => $acceso->puede('comprobantes.view') ? $comprobantes->count() : null,
            ]];
        }

        // Inventario.
        if ($acceso->puede('inventario.view')) {
            $inv = InventarioItem::query();
            $acceso->alcance('inventario.view')->aplicarPorSede($inv);
            $tarjetas[] = ['tipo' => 'inventario', 'rol' => 'encargado', 'datos' => [
                'items' => (clone $inv)->count(),
                'en_reparacion' => (clone $inv)->where('estado', 'reparacion')->count(),
                'puede_cargar' => $acceso->puede('inventario.create'),
            ]];
        }

        // Próximos eventos (todos los que pueden verlos).
        if ($acceso->puede('eventos.view')) {
            $ev = Evento::query()->with(['sede:id,nombre', 'bloque:id,nombre'])->where('fecha', '>=', now()->toDateString())
                ->orderBy('fecha')->orderBy('hora_inicio')->limit(5);
            $acceso->alcance('eventos.view')->aplicarEventos($ev);
            $tarjetas[] = ['tipo' => 'eventos', 'rol' => null, 'datos' => EventoResource::collection($ev->get())->toArray($request)];
        }

        // El contexto elegido va primero; el resto conserva su orden.
        if ($rolContexto) {
            usort($tarjetas, fn ($a, $b) => (int) ($b['rol'] === $rolContexto) <=> (int) ($a['rol'] === $rolContexto));
        }

        return response()->json([
            'saludo' => 'Hola, '.($user->persona?->nombre ?? $user->name),
            'avisos_no_leidos' => $user->unreadNotifications()->count(),
            'modulos' => $acceso->modulos(),
            'tarjetas' => $tarjetas,
        ]);
    }
}
