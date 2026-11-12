<?php

namespace App\Domain\Agenda;

use App\Models\Auditoria;
use App\Models\InventarioItem;
use App\Models\ProgramaRitmo;
use App\Models\Show;
use App\Models\ShowEnsayo;
use App\Models\ShowInstrumento;
use App\Models\ShowTema;
use Illuminate\Support\Facades\DB;

/**
 * Preparación de un show: lista de temas, ensayos, logística del traslado e instrumentos.
 * Web y API usan las mismas reglas.
 */
class ShowPlanService
{
    public function __construct(private readonly ConvocatoriaShow $convocatoria) {}

    /** @return array<string, mixed> */
    public static function reglasLogistica(): array
    {
        return [
            'punto_encuentro' => 'nullable|string|max:255',
            'hora_encuentro' => 'nullable|date_format:H:i',
            'transporte' => 'nullable|string|max:255',
            'logistica_notas' => 'nullable|string|max:2000',
        ];
    }

    /** @return array<string, mixed> */
    public static function reglasTemas(): array
    {
        return [
            'temas' => 'present|array|max:60',
            'temas.*.programa_ritmo_id' => 'nullable|integer|exists:programa_ritmos,id',
            'temas.*.titulo' => 'nullable|string|max:255|required_without:temas.*.programa_ritmo_id',
            'temas.*.notas' => 'nullable|string|max:255',
        ];
    }

    /** @return array<string, mixed> */
    public static function reglasInstrumentos(): array
    {
        return [
            'instrumentos' => 'present|array|max:100',
            'instrumentos.*.inventario_item_id' => 'nullable|integer|exists:inventario_items,id',
            'instrumentos.*.descripcion' => 'nullable|string|max:255|required_without:instrumentos.*.inventario_item_id',
            'instrumentos.*.cantidad' => 'nullable|integer|min:1|max:999',
        ];
    }

    /** @return array<string, mixed> */
    public static function reglasEnsayo(): array
    {
        return [
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after_or_equal:hora_inicio',
            'lugar' => 'nullable|string|max:255',
            'notas' => 'nullable|string|max:255',
        ];
    }

    /** @param  array<string, mixed>  $datos */
    public function guardarLogistica(Show $show, array $datos): Show
    {
        $show->update([
            'punto_encuentro' => $datos['punto_encuentro'] ?? null,
            'hora_encuentro' => ! empty($datos['hora_encuentro']) ? $datos['hora_encuentro'].':00' : null,
            'transporte' => $datos['transporte'] ?? null,
            'logistica_notas' => $datos['logistica_notas'] ?? null,
        ]);
        Auditoria::registrar('show.logistica', $show);

        return $show;
    }

    /**
     * Reemplaza la lista de temas (en el orden recibido).
     *
     * @param  list<array<string, mixed>>  $temas
     */
    public function guardarTemas(Show $show, array $temas): void
    {
        DB::transaction(function () use ($show, $temas) {
            $show->temas()->delete();
            foreach ($temas as $i => $t) {
                ShowTema::query()->create([
                    'show_id' => $show->id,
                    'orden' => $i + 1,
                    'programa_ritmo_id' => $t['programa_ritmo_id'] ?? null,
                    'titulo' => filled($t['titulo'] ?? null) ? trim((string) $t['titulo']) : null,
                    'notas' => filled($t['notas'] ?? null) ? trim((string) $t['notas']) : null,
                ]);
            }
        });
        Auditoria::registrar('show.temas', $show, null, ['cantidad' => count($temas)]);
    }

    /**
     * Reemplaza la lista de instrumentos que van.
     *
     * @param  list<array<string, mixed>>  $instrumentos
     */
    public function guardarInstrumentos(Show $show, array $instrumentos): void
    {
        DB::transaction(function () use ($show, $instrumentos) {
            $show->instrumentos()->delete();
            foreach ($instrumentos as $i) {
                ShowInstrumento::query()->create([
                    'show_id' => $show->id,
                    'inventario_item_id' => $i['inventario_item_id'] ?? null,
                    'descripcion' => filled($i['descripcion'] ?? null) ? trim((string) $i['descripcion']) : null,
                    'cantidad' => max(1, (int) ($i['cantidad'] ?? 1)),
                ]);
            }
        });
        Auditoria::registrar('show.instrumentos', $show, null, ['cantidad' => count($instrumentos)]);
    }

    /** @param  array<string, mixed>  $datos */
    public function guardarEnsayo(Show $show, ?ShowEnsayo $ensayo, array $datos): ShowEnsayo
    {
        $campos = [
            'fecha' => $datos['fecha'],
            'hora_inicio' => ! empty($datos['hora_inicio']) ? $datos['hora_inicio'].':00' : null,
            'hora_fin' => ! empty($datos['hora_fin']) ? $datos['hora_fin'].':00' : null,
            'lugar' => $datos['lugar'] ?? null,
            'notas' => $datos['notas'] ?? null,
        ];
        if ($ensayo) {
            $ensayo->update($campos);
        } else {
            $ensayo = ShowEnsayo::query()->create($campos + ['show_id' => $show->id]);
        }
        Auditoria::registrar('show.ensayo', $show, null, ['ensayo_id' => $ensayo->id]);

        return $ensayo;
    }

    public function eliminarEnsayo(ShowEnsayo $ensayo): void
    {
        Auditoria::registrar('show.ensayo.eliminar', $ensayo->show, ['ensayo_id' => $ensayo->id, 'fecha' => $ensayo->fecha->toDateString()]);
        $ensayo->delete();
    }

    /**
     * Opciones para armar la lista de temas e instrumentos.
     *
     * @return array{toques: list<array{id: int, nombre: string, anio: ?int}>, instrumentos: list<array{id: int, nombre: string, codigo: ?string, sede: ?string}>}
     */
    public function catalogo(): array
    {
        $toques = ProgramaRitmo::query()->orderBy('año')->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'año']);
        $items = InventarioItem::query()->where('tipo', 'instrumento')->where('estado', '!=', 'baja')
            ->whereDoesntHave('prestamos', fn ($q) => $q->whereNull('devuelto_el'))
            ->with('sede:id,nombre')->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'sede_id']);

        return [
            'toques' => $toques->map(fn (ProgramaRitmo $t) => ['id' => (int) $t->id, 'nombre' => (string) $t->nombre, 'anio' => $t->año ? (int) $t->año : null])->values()->all(),
            'instrumentos' => $items->map(fn (InventarioItem $i) => ['id' => (int) $i->id, 'nombre' => (string) $i->nombre, 'codigo' => $i->codigo, 'sede' => $i->sede?->nombre])->values()->all(),
        ];
    }

    /**
     * Todo lo del show para mostrar (organizador o alumno).
     *
     * @return array<string, mixed>
     */
    public function plan(Show $show): array
    {
        $show->loadMissing(['temas.toque:id,nombre,slug', 'ensayos.asistencias', 'instrumentos.item:id,nombre,codigo']);

        return [
            'logistica' => [
                'punto_encuentro' => $show->punto_encuentro,
                'hora_encuentro' => $show->hora_encuentro?->format('H:i'),
                'transporte' => $show->transporte,
                'notas' => $show->logistica_notas,
            ],
            'temas' => $show->temas->map(fn (ShowTema $t) => [
                'id' => $t->id,
                'orden' => $t->orden,
                'nombre' => $t->nombre(),
                'titulo' => $t->titulo,
                'programa_ritmo_id' => $t->programa_ritmo_id,
                'slug' => $t->toque?->slug,
                'notas' => $t->notas,
            ])->values()->all(),
            'ensayos' => $show->ensayos->map(fn (ShowEnsayo $e) => [
                'id' => $e->id,
                'fecha' => $e->fecha->toDateString(),
                'hora_inicio' => $e->hora_inicio?->format('H:i'),
                'hora_fin' => $e->hora_fin?->format('H:i'),
                'lugar' => $e->lugar,
                'notas' => $e->notas,
                'presentes' => $e->asistencias->where('presente', true)->count(),
            ])->values()->all(),
            'instrumentos' => $show->instrumentos->map(fn (ShowInstrumento $i) => [
                'id' => $i->id,
                'nombre' => $i->nombre(),
                'inventario_item_id' => $i->inventario_item_id,
                'descripcion' => $i->descripcion,
                'cantidad' => $i->cantidad,
            ])->values()->all(),
        ];
    }

    public function convocatoria(): ConvocatoriaShow
    {
        return $this->convocatoria;
    }
}
