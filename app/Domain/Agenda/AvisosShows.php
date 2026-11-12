<?php

namespace App\Domain\Agenda;

use App\Domain\Notificaciones\ConfigAvisos;
use App\Domain\Notificaciones\NotificacionService;
use App\Models\Alumno;
use App\Models\Show;
use App\Models\ShowConfirmacion;
use Illuminate\Support\Facades\DB;

/**
 * Avisos de shows por el sistema de avisos existente (idempotentes por clave):
 * convocatoria (al sumar un bloque a un show) y recordatorio unos días antes.
 */
class AvisosShows
{
    public function __construct(
        private readonly NotificacionService $notificaciones,
        private readonly ConvocatoriaShow $convocatoria,
    ) {}

    /** Shows próximos con bloques convocados hace poco: aviso a cada alumno convocado. */
    public function convocados(int $diasAtras = 7): int
    {
        $config = ConfigAvisos::de('show_convocatoria');
        if (! $config['activo'] || $config['canales'] === []) {
            return 0;
        }
        $showIds = DB::table('show_bloque')->where('created_at', '>=', now()->subDays($diasAtras))->distinct()->pluck('show_id');
        $shows = Show::query()->whereIn('id', $showIds->all() ?: [0])->where('fecha', '>=', now()->toDateString())->get();

        $enviados = 0;
        foreach ($shows as $show) {
            $texto = ConfigAvisos::texto('show_convocatoria', self::variables($show));
            foreach ($this->convocatoria->convocados($show) as $alumno) {
                $enviados += $this->avisar($show, $alumno, 'show_convocatoria', $texto, $config['canales']);
            }
        }

        return $enviados;
    }

    /** Unos días antes: a los convocados que no dijeron «no voy». */
    public function recordatorios(): int
    {
        $config = ConfigAvisos::de('show_recordatorio');
        if (! $config['activo'] || $config['canales'] === []) {
            return 0;
        }
        $shows = Show::query()->whereBetween('fecha', [now()->toDateString(), now()->addDays((int) $config['dias'])->toDateString()])->get();

        $enviados = 0;
        foreach ($shows as $show) {
            $noVan = ShowConfirmacion::query()->where('show_id', $show->id)->where('respuesta', 'no_voy')->pluck('alumno_id')->all();
            $texto = ConfigAvisos::texto('show_recordatorio', self::variables($show));
            foreach ($this->convocatoria->convocados($show) as $alumno) {
                if (! in_array($alumno->id, $noVan, false)) {
                    $enviados += $this->avisar($show, $alumno, 'show_recordatorio', $texto, $config['canales']);
                }
            }
        }

        return $enviados;
    }

    /**
     * @param  array{titulo: string, mensaje: string}  $texto
     * @param  list<string>  $canales
     */
    private function avisar(Show $show, Alumno $alumno, string $tipo, array $texto, array $canales): int
    {
        $user = ConvocatoriaShow::cuentaDe($alumno);
        if (! $user) {
            return 0;
        }
        $r = $this->notificaciones->enviar($user, "{$tipo}:{$show->id}:{$alumno->id}", [
            'tipo' => $tipo,
            ...$texto,
            'enlace' => "app://mis-shows/{$show->id}",
            'datos' => ['show_id' => $show->id],
        ], $canales);

        return (int) in_array('enviado', $r, true);
    }

    /** @return array<string, string> */
    public static function variables(Show $show): array
    {
        $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

        return [
            'evento' => (string) $show->titulo,
            'fecha' => $dias[$show->fecha->dayOfWeek].' '.$show->fecha->format('d/m'),
            'hora' => $show->hora_inicio ? $show->hora_inicio->format('H:i') : '',
            'lugar' => (string) $show->lugar,
            'descripcion' => trim((string) $show->descripcion),
            'dias' => (string) max(0, (int) now()->startOfDay()->diffInDays($show->fecha, false)),
        ];
    }
}
