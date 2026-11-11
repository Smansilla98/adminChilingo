<?php

namespace App\Domain\Finanzas;

use App\Domain\Notificaciones\NotificacionService;
use App\Models\Alumno;
use App\Models\User;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Morosidad: alumnos con cuotas vencidas y saldo, con la antigüedad de la deuda (la cuota
 * vencida más antigua) y recordatorios individuales o masivos.
 *
 * Los recordatorios pasan por NotificacionService: la clave es «morosidad:<alumno>:<AAAAMM>»,
 * así que a un mismo alumno no se le manda dos veces por el mismo canal en el mismo mes.
 */
class MorosidadService
{
    public const TRAMOS = [
        '0-30' => '0 a 30 días',
        '31-60' => '31 a 60 días',
        '61-90' => '61 a 90 días',
        '+90' => 'Más de 90 días',
    ];

    public function __construct(
        private EstadoCuentaService $estadoCuenta,
        private NotificacionService $notificaciones,
        private AmbitoSedeService $ambito,
    ) {}

    /**
     * Alumnos morosos del alcance de quien consulta.
     *
     * @param  array{sede_id?: int|null, bloque_id?: int|null, tramo?: string|null, alumno_ids?: list<int>|null}  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function deudores(array $filtros, ?User $por): Collection
    {
        $q = Alumno::query()->where('activo', true)->with(['sede:id,nombre', 'bloques:id,nombre,sede_id', 'bloque:id,nombre,sede_id'])->orderBy('nombre_apellido');
        $filtro = $this->ambito->idsPara($por, 'pagos.view');
        if ($filtro !== null) {
            $this->ambito->aplicarAlumnos($q, $filtro);
        }
        if (! empty($filtros['sede_id'])) {
            $sede = (int) $filtros['sede_id'];
            $q->where(fn ($w) => $w->where('sede_id', $sede)->orWhereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $sede)));
        }
        if (! empty($filtros['bloque_id'])) {
            $bloque = (int) $filtros['bloque_id'];
            $q->where(fn ($w) => $w->whereHas('bloques', fn ($b) => $b->where('bloques.id', $bloque))->orWhere('bloque_id', $bloque));
        }
        if (! empty($filtros['alumno_ids'])) {
            $q->whereIn('id', $filtros['alumno_ids']);
        }

        $anios = [now()->year - 1, now()->year];
        $hoy = Carbon::today();
        $out = collect();
        foreach ($q->get() as $alumno) {
            $vencidas = collect($anios)->flatMap(fn (int $a) => $this->estadoCuenta->paraAlumno($alumno, $a)['items'])
                ->filter(fn (array $i) => $i['saldo'] > 0 && $i['vencimiento'] !== null && Carbon::parse($i['vencimiento'])->lt($hoy));
            if ($vencidas->isEmpty()) {
                continue;
            }
            $masAntigua = $vencidas->min(fn (array $i) => $i['vencimiento']);
            $dias = (int) Carbon::parse($masAntigua)->diffInDays($hoy);
            $tramo = $this->tramo($dias);
            if (! empty($filtros['tramo']) && $filtros['tramo'] !== $tramo) {
                continue;
            }
            $out->push([
                'alumno_id' => (int) $alumno->id,
                'alumno' => (string) $alumno->nombre_apellido,
                'sede' => $alumno->sede?->nombre,
                'bloques' => $alumno->bloques->pluck('nombre')->merge($alumno->bloque ? [$alumno->bloque->nombre] : [])->unique()->values()->all(),
                'cuotas_vencidas' => $vencidas->count(),
                'saldo_vencido' => round((float) $vencidas->sum('saldo'), 2),
                'vencida_desde' => Carbon::parse($masAntigua)->toDateString(),
                'dias_mora' => $dias,
                'tramo' => $tramo,
                'tiene_cuenta' => $this->cuentaDe($alumno) !== null,
            ]);
        }

        return $out->sortByDesc('dias_mora')->values();
    }

    /**
     * Recordatorio a un alumno moroso. Si ya no debe nada, no envía.
     *
     * @param  list<string>  $canales
     * @return array{estado: string, canales: array<string, string>}
     */
    public function recordar(Alumno $alumno, ?User $por, array $canales = ['interna', 'push']): array
    {
        $fila = $this->deudores(['alumno_ids' => [(int) $alumno->id]], $por)->first();
        if ($fila === null) {
            return ['estado' => 'al_dia', 'canales' => []];
        }
        $cuenta = $this->cuentaDe($alumno);
        if ($cuenta === null) {
            return ['estado' => 'sin_cuenta', 'canales' => []];
        }

        $resultado = $this->notificaciones->enviar($cuenta, 'morosidad:'.$alumno->id.':'.now()->format('Ym'), [
            'tipo' => 'morosidad',
            'titulo' => 'Tenés cuotas vencidas',
            'mensaje' => sprintf(
                'Tenés $%s pendiente de cuotas vencidas (la más antigua venció hace %d días). Podés pagarla desde la app o consultar en la escuela.',
                number_format($fila['saldo_vencido'], 0, ',', '.'),
                $fila['dias_mora'],
            ),
            'enlace' => 'app://mi-cuenta',
            'datos' => ['alumno_id' => (int) $alumno->id],
        ], $canales);

        // enviado: salió por algún canal; duplicado: ya había salido este mes por todos los pedidos.
        $estado = match (true) {
            in_array('enviado', $resultado, true) => 'enviado',
            $resultado !== [] && count(array_filter($resultado, fn ($v) => $v === 'duplicado')) === count($resultado) => 'duplicado',
            default => 'sin_envio',
        };

        return ['estado' => $estado, 'canales' => $resultado];
    }

    /**
     * Recordatorio masivo a los morosos indicados (que estén en el alcance de quien envía).
     *
     * @param  list<int>  $alumnoIds
     * @param  list<string>  $canales
     * @return array{enviado: int, duplicado: int, sin_envio: int, al_dia: int, sin_cuenta: int}
     */
    public function recordarMasivo(array $alumnoIds, ?User $por, array $canales = ['interna', 'push']): array
    {
        $total = ['enviado' => 0, 'duplicado' => 0, 'sin_envio' => 0, 'al_dia' => 0, 'sin_cuenta' => 0];
        $alumnos = Alumno::query()->whereIn('id', array_map('intval', $alumnoIds))->get();
        foreach ($alumnos as $alumno) {
            $total[$this->recordar($alumno, $por, $canales)['estado']]++;
        }

        return $total;
    }

    public function tramo(int $dias): string
    {
        return match (true) {
            $dias <= 30 => '0-30',
            $dias <= 60 => '31-60',
            $dias <= 90 => '61-90',
            default => '+90',
        };
    }

    private function cuentaDe(Alumno $alumno): ?User
    {
        return User::query()->where(fn ($q) => $q->where('persona_id', (int) $alumno->getAttribute('persona_id') ?: 0)->orWhere('id', $alumno->user_id ?: 0))
            ->where('activo', true)->first();
    }
}
