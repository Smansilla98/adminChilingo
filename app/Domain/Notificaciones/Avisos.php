<?php

namespace App\Domain\Notificaciones;

use App\Domain\Asistencias\AusenciasSeguidas;
use App\Domain\Finanzas\CobroCuotasService;
use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Cuota;
use App\Models\Evento;
use App\Models\Pago;
use App\Models\PagoDetalle;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Disparadores de negocio → avisos. Cada método es idempotente gracias a la clave.
 */
class Avisos
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    /** Al registrar un pago: aviso a la persona alumna (si tiene cuenta). */
    public function pagoRegistrado(PagoDetalle $detalle): void
    {
        $config = ConfigAvisos::de('pago_registrado');
        $alumno = $detalle->alumno;
        $user = $alumno ? $this->cuentaDe($alumno) : null;
        if (! $user || ! $config['activo']) {
            return;
        }
        $this->notificaciones->enviar($user, "pago:{$detalle->pago_id}:alumno:{$alumno->id}:cuota:{$detalle->cuota_id}", [
            'tipo' => 'pago_registrado',
            ...ConfigAvisos::texto('pago_registrado', [
                'alumno' => $alumno->nombre_apellido, 'cuota' => $detalle->cuota?->nombre ?? 'la cuota', 'monto' => self::pesos((float) $detalle->monto),
            ]),
            'enlace' => 'app://mi-cuenta',
        ], $config['canales']);
    }

    /**
     * Cuotas nuevas: a cada alumno (con cuenta) que tenga saldo en una cuota cargada en los últimos
     * días. La clave evita repetir; corre seguido desde el scheduler.
     */
    public function cuotasNuevas(int $diasAtras = 7): int
    {
        $config = ConfigAvisos::de('cuota_nueva');
        if (! $config['activo']) {
            return 0;
        }
        $cuotas = Cuota::query()->where('activo', true)->where('created_at', '>=', now()->subDays($diasAtras))
            ->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>=', now()->toDateString()))
            ->get();

        return $this->avisarCuotas($cuotas, 'cuota_nueva', $config['canales']);
    }

    /** Cuotas que vencen dentro de los próximos días configurados, a quien todavía no pagó. */
    public function cuotasPorVencer(): int
    {
        $config = ConfigAvisos::de('cuota_por_vencer');
        if (! $config['activo']) {
            return 0;
        }
        $cuotas = Cuota::query()->where('activo', true)->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [now()->addDay()->toDateString(), now()->addDays((int) $config['dias'])->toDateString()])
            ->get();

        return $this->avisarCuotas($cuotas, 'cuota_por_vencer', $config['canales']);
    }

    /**
     * Cuotas vencidas sin pagar (alumnos con cuenta). Devuelve cuántos avisos nuevos salieron.
     */
    public function cuotasVencidas(?int $diasAtras = null): int
    {
        $config = ConfigAvisos::de('cuota_vencida');
        if (! $config['activo']) {
            return 0;
        }
        $diasAtras ??= (int) $config['dias'];
        $cuotas = Cuota::query()->where('activo', true)->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [now()->subDays($diasAtras)->toDateString(), now()->subDay()->toDateString()])
            ->get();

        return $this->avisarCuotas($cuotas, 'cuota_vencida', $config['canales']);
    }

    /**
     * A cada alumno activo con cuenta al que le corresponde la cuota y le queda saldo (con su
     * beca aplicada): un aviso por alumno y cuota.
     *
     * @param  \Illuminate\Support\Collection<int, Cuota>  $cuotas
     * @param  list<string>  $canales
     */
    private function avisarCuotas(Collection $cuotas, string $tipo, array $canales): int
    {
        if ($cuotas->isEmpty() || $canales === []) {
            return 0;
        }
        $estado = app(EstadoCuentaService::class);
        $alias = app(CobroCuotasService::class)->datosTransferencia()['alias'] ?? null;
        $alumnos = Alumno::query()->where('activo', true)
            ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereHas('persona.user'))
            ->with(['bloques:id,sede_id', 'bloque:id,sede_id'])->get();

        $enviados = 0;
        foreach ($alumnos as $alumno) {
            $aplicables = $cuotas->filter(fn (Cuota $c) => $c->aplicaAAlumno($alumno));
            if ($aplicables->isEmpty() || ! ($user = $this->cuentaDe($alumno))) {
                continue;
            }
            $cuentas = [];
            foreach ($aplicables as $cuota) {
                $anio = (int) $cuota->año;
                $cuentas[$anio] ??= collect($estado->paraAlumno($alumno, $anio)['items'])->keyBy('cuota_id');
                $item = $cuentas[$anio]->get($cuota->id);
                if (! $item || (float) $item['saldo'] <= 0) {
                    continue; // pagada o becada
                }
                $dias = $cuota->fecha_vencimiento ? max(0, (int) now()->startOfDay()->diffInDays($cuota->fecha_vencimiento, false)) : null;
                $r = $this->notificaciones->enviar($user, "{$tipo}:{$alumno->id}:{$cuota->id}", [
                    'tipo' => $tipo,
                    ...ConfigAvisos::texto($tipo, [
                        'alumno' => $alumno->nombre_apellido,
                        'cuota' => $cuota->nombre,
                        'monto' => self::pesos((float) $item['saldo']),
                        'vencimiento' => $tipo === 'cuota_nueva'
                            ? ($cuota->fecha_vencimiento ? ', vence el '.$cuota->fecha_vencimiento->format('d/m') : '')
                            : $cuota->fecha_vencimiento?->format('d/m'),
                        'dias' => $dias !== null ? (string) $dias : '',
                        'alias' => $alias ?? 'el de la escuela',
                    ]),
                    'enlace' => 'app://mi-cuenta',
                    'datos' => ['cuota_id' => $cuota->id, 'alumno_id' => $alumno->id],
                ], $canales);
                $enviados += (int) in_array('enviado', $r, true);
            }
        }

        return $enviados;
    }

    private static function pesos(float $monto): string
    {
        return '$'.number_format($monto, 0, ',', '.');
    }

    /** Eventos de mañana: aviso a quienes participan (alumnos y docentes del alcance del evento). */
    public function eventosProximos(): int
    {
        $enviados = 0;
        foreach (Evento::query()->whereDate('fecha', now()->addDay()->toDateString())->get() as $evento) {
            foreach ($this->destinatariosDe($evento) as $user) {
                $r = $this->notificaciones->enviar($user, "evento:{$evento->id}:{$user->id}", [
                    'tipo' => 'evento_proximo',
                    'titulo' => 'Mañana: '.$evento->titulo,
                    'mensaje' => trim(($evento->hora_inicio ? $evento->hora_inicio->format('H:i').' · ' : '').($evento->sede?->nombre ?? 'La Chilinga')),
                    'enlace' => 'app://calendario',
                ]);
                $enviados += (int) (($r['interna'] ?? null) === 'enviado');
            }
        }

        return $enviados;
    }

    /**
     * Alumnos con varias faltas seguidas: aviso a los docentes del bloque para que los contacten.
     * La clave incluye el inicio de la racha: si vuelve y después deja otra vez, se avisa de nuevo.
     */
    public function ausenciasSeguidas(): int
    {
        $enviados = 0;
        $bloques = Bloque::query()->where('activo', true)->with(['profesores.user', 'profesor.user'])->get();
        $rachas = app(AusenciasSeguidas::class)->enBloques($bloques->pluck('id'));

        foreach ($rachas as $r) {
            $bloque = $bloques->firstWhere('id', $r['bloque']->id);
            $docentes = [];
            if ($bloque instanceof Bloque) {
                foreach ([$bloque->profesor, ...$bloque->profesores] as $profesor) {
                    if ($profesor instanceof Profesor && $profesor->user instanceof User) {
                        $docentes[$profesor->user->id] = $profesor->user;
                    }
                }
            }
            $nombre = $r['alumno']->nombre_apellido ?? 'Un alumno';
            foreach ($docentes as $user) {
                $res = $this->notificaciones->enviar($user, "ausencias:{$r['alumno']->id}:{$r['bloque']->id}:{$r['desde']->toDateString()}:{$user->id}", [
                    'tipo' => 'ausencias_seguidas',
                    'titulo' => "{$nombre} faltó {$r['faltas']} clases seguidas",
                    'mensaje' => "En {$r['bloque']->nombre}, desde el {$r['desde']->format('d/m')}. Un mensaje a tiempo ayuda a que no deje.",
                    'enlace' => route('profesor.alumnos.show', $r['alumno']),
                ]);
                $enviados += (int) (($res['interna'] ?? null) === 'enviado');
            }
        }

        return $enviados;
    }

    /** @return Collection<int, User> */
    private function destinatariosDe(Evento $evento): Collection
    {
        $alumnos = Alumno::query()->where('activo', true);
        if ($evento->bloque_id) {
            $alumnos->where(fn ($q) => $q->where('bloque_id', $evento->bloque_id)->orWhereHas('bloques', fn ($b) => $b->where('bloques.id', $evento->bloque_id)));
        } elseif ($evento->sede_id) {
            $alumnos->where(fn ($q) => $q->where('sede_id', $evento->sede_id)->orWhereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $evento->sede_id)));
        } else {
            return collect(); // eventos generales: se ven en el calendario, sin aviso masivo
        }
        $personaIds = $alumnos->pluck('persona_id')->filter()->unique()->all();

        return User::query()->whereIn('persona_id', $personaIds ?: [0])->where('activo', true)->get();
    }

    private function cuentaDe(Alumno $alumno): ?User
    {
        return User::query()->where(fn ($q) => $q->where('persona_id', $alumno->persona_id ?: 0)->orWhere('id', $alumno->user_id ?: 0))
            ->where('activo', true)->first();
    }
}
