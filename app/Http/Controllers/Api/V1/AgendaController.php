<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\EventoResource;
use App\Models\Bloque;
use App\Models\Evento;
use App\Models\Show;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Eventos y calendario unificado (clases, eventos, shows) filtrado por el alcance de la persona.
 */
class AgendaController extends Controller
{
    public function eventos(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Evento::class);
        $query = Evento::query()->with(['sede:id,nombre', 'bloque:id,nombre'])
            ->where('fecha', '>=', $request->date('desde')?->toDateString() ?? now()->toDateString())
            ->orderBy('fecha')->orderBy('hora_inicio');
        $request->user()->acceso()->alcance('eventos.view')->aplicarEventos($query);

        return EventoResource::collection($query->limit(100)->get());
    }

    /**
     * GET /calendario?desde=YYYY-MM-DD&hasta=YYYY-MM-DD[&sede_id=&bloque_id=]
     * Máximo 62 días por consulta.
     */
    public function calendario(Request $request): JsonResponse
    {
        $acceso = $request->user()->acceso();
        abort_unless($acceso->puede('calendario.view'), 403);
        $data = $request->validate([
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'sede_id' => 'nullable|integer',
            'bloque_id' => 'nullable|integer',
        ]);
        $desde = CarbonImmutable::parse($data['desde'] ?? now()->startOfWeek());
        $hasta = CarbonImmutable::parse($data['hasta'] ?? $desde->addDays(13));
        if ($desde->diffInDays($hasta) > 62) {
            $hasta = $desde->addDays(62);
        }
        $alcance = $acceso->alcance('calendario.view');
        $items = [];

        // Clases: horarios semanales de los bloques del alcance.
        $bloques = Bloque::query()->where('activo', true)->with(['horarios', 'sede:id,nombre'])->whereHas('horarios');
        $alcance->aplicarBloques($bloques);
        if (! empty($data['sede_id'])) {
            $bloques->where('sede_id', $data['sede_id']);
        }
        if (! empty($data['bloque_id'])) {
            $bloques->whereKey($data['bloque_id']);
        }
        $bloques = $bloques->get();
        foreach (CarbonPeriod::create($desde, $hasta) as $dia) {
            foreach ($bloques as $b) {
                foreach ($b->horarios as $h) {
                    if ((int) $h->dia_semana !== (int) $dia->dayOfWeekIso) {
                        continue;
                    }
                    $items[] = [
                        'tipo' => 'clase',
                        'id' => 'clase-'.$b->id.'-'.$dia->format('Ymd'),
                        'titulo' => $b->nombre,
                        'fecha' => $dia->toDateString(),
                        'inicio' => substr((string) $h->hora_inicio, 0, 5),
                        'fin' => substr((string) $h->hora_fin, 0, 5),
                        'sede' => $b->sede?->nombre,
                        'bloque_id' => $b->id,
                    ];
                }
            }
        }

        $eventos = Evento::query()->with(['sede:id,nombre', 'bloque:id,nombre'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()]);
        $alcance->aplicarEventos($eventos);
        if (! empty($data['sede_id'])) {
            $eventos->where(fn ($q) => $q->where('sede_id', $data['sede_id'])->orWhereNull('sede_id'));
        }
        foreach ($eventos->get() as $e) {
            $items[] = [
                'tipo' => in_array($e->tipo_evento, ['show', 'show_beneficio'], true) ? 'show' : ($e->tipo_evento ?: 'evento'),
                'id' => 'evento-'.$e->id,
                'titulo' => $e->titulo,
                'fecha' => $e->fecha?->toDateString(),
                'inicio' => $e->hora_inicio?->format('H:i'),
                'fin' => $e->hora_fin?->format('H:i'),
                'sede' => $e->sede?->nombre,
                'bloque_id' => $e->bloque_id,
            ];
        }

        foreach (Show::query()->with('bloques:id')->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])->get() as $s) {
            $convocado = $s->convocatoria_abierta || $alcance->esGlobal()
                || $s->bloques->contains(fn ($b) => $alcance->incluyeBloque((int) $b->id));
            if (! $convocado) {
                continue;
            }
            $items[] = [
                'tipo' => $s->convocatoria_abierta ? 'convocatoria' : 'show',
                'id' => 'show-'.$s->id,
                'titulo' => $s->titulo,
                'fecha' => $s->fecha?->toDateString(),
                'inicio' => $s->hora_inicio?->format('H:i'),
                'fin' => $s->hora_fin?->format('H:i'),
                'sede' => $s->lugar,
                'bloque_id' => null,
            ];
        }

        usort($items, fn ($a, $b) => [$a['fecha'], $a['inicio'] ?? '99'] <=> [$b['fecha'], $b['inicio'] ?? '99']);

        return response()->json(['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'items' => $items]);
    }
}
