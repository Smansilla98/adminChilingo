<?php

namespace App\Domain\Notificaciones;

use App\Domain\Asistencias\AusenciasSeguidas;
use App\Domain\Finanzas\CobroCuotasService;
use App\Domain\Finanzas\EstadoCuentaService;
use App\Models\Alumno;
use App\Models\Asistencia;
use App\Models\Bloque;
use App\Models\BloqueHorario;
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

    /**
     * Recordatorio de evento: los eventos y convocatorias de los próximos días configurados,
     * a quienes participan. Un aviso por evento y persona.
     */
    public function eventosProximos(): int
    {
        $config = ConfigAvisos::de('evento_proximo');
        if (! $config['activo'] || $config['canales'] === []) {
            return 0;
        }
        $eventos = Evento::query()->with(['sede', 'bloque.sede'])
            ->whereBetween('fecha', [now()->addDay()->toDateString(), now()->addDays((int) $config['dias'])->toDateString()])->get();

        return $this->avisarEventos($eventos, 'evento_proximo', 'evento', $config['canales']);
    }

    /**
     * Eventos y convocatorias recién cargados (corre seguido desde el scheduler): los eventos de
     * un bloque o sede avisan a su gente; las convocatorias, a todo su ámbito (toda la escuela si no tienen).
     */
    public function eventosNuevos(int $diasAtras = 2): int
    {
        $recientes = Evento::query()->with(['sede', 'bloque.sede'])->where('created_at', '>=', now()->subDays($diasAtras))
            ->whereDate('fecha', '>=', now()->toDateString())->get();
        [$convocatorias, $eventos] = $recientes->partition(fn (Evento $e) => $e->tipo_evento === 'convocatoria');

        $enviados = 0;
        foreach (['convocatoria' => $convocatorias, 'evento_nuevo' => $eventos] as $tipo => $lista) {
            $config = ConfigAvisos::de($tipo);
            if ($config['activo'] && $config['canales'] !== []) {
                $enviados += $this->avisarEventos($lista, $tipo, $tipo, $config['canales']);
            }
        }

        return $enviados;
    }

    /**
     * @param  Collection<int, Evento>  $eventos
     * @param  list<string>  $canales
     */
    private function avisarEventos(Collection $eventos, string $tipo, string $prefijoClave, array $canales): int
    {
        $enviados = 0;
        foreach ($eventos as $evento) {
            $dias = max(0, (int) now()->startOfDay()->diffInDays($evento->fecha, false));
            $texto = ConfigAvisos::texto($tipo, self::variablesEvento($evento) + ['dias' => (string) $dias]);
            foreach ($this->destinatariosDe($evento) as $user) {
                $r = $this->notificaciones->enviar($user, "{$prefijoClave}:{$evento->id}:{$user->id}", [
                    'tipo' => $tipo,
                    ...$texto,
                    'enlace' => 'app://calendario',
                    'datos' => ['evento_id' => $evento->id],
                ], $canales);
                $enviados += (int) in_array('enviado', $r, true);
            }
        }

        return $enviados;
    }

    /** @return array<string, string> */
    public static function variablesEvento(Evento $evento): array
    {
        $dias = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

        return [
            'evento' => (string) $evento->titulo,
            'fecha' => $dias[$evento->fecha->dayOfWeek].' '.$evento->fecha->format('d/m'),
            'hora' => $evento->hora_inicio ? $evento->hora_inicio->format('H:i') : '',
            'lugar' => $evento->sede->nombre ?? $evento->bloque->sede->nombre ?? '',
            'descripcion' => trim((string) $evento->descripcion),
        ];
    }

    /**
     * Clases que empezaron hace los minutos configurados y todavía no tienen asistencia:
     * recordatorio a sus docentes, una vez por clase. No avisa de clases que terminaron hace horas.
     */
    public function pasarLista(): int
    {
        $config = ConfigAvisos::de('pasar_lista');
        if (! $config['activo'] || $config['canales'] === []) {
            return 0;
        }
        $ahora = now();
        $hoy = $ahora->copy()->startOfDay();
        $horarios = BloqueHorario::query()->where('dia_semana', $ahora->dayOfWeekIso)
            ->whereHas('bloque', fn ($q) => $q->where('activo', true))
            ->with(['bloque.profesor.user', 'bloque.profesores.user', 'bloque.sede'])->get();

        $enviados = 0;
        foreach ($horarios as $h) {
            $bloque = $h->bloque;
            $inicio = $hoy->copy()->setTimeFrom($h->hora_inicio);
            $fin = $hoy->copy()->setTimeFrom($h->hora_fin);
            if ($ahora->lt($inicio->copy()->addMinutes((int) $config['minutos'])) || $ahora->gt($fin->copy()->addHours(3))) {
                continue;
            }
            if (Asistencia::query()->where('bloque_id', $bloque->id)->whereDate('fecha', $hoy->toDateString())->exists()) {
                continue;
            }
            $texto = ConfigAvisos::texto('pasar_lista', [
                'bloque' => $bloque->nombre, 'hora' => $inicio->format('H:i'), 'lugar' => $bloque->sede?->nombre,
            ]);
            foreach ($this->docentesDe($bloque) as $user) {
                $r = $this->notificaciones->enviar($user, "pasar_lista:{$h->id}:{$hoy->toDateString()}:{$user->id}", [
                    'tipo' => 'pasar_lista',
                    ...$texto,
                    'enlace' => "app://asistencia/{$bloque->id}",
                    'datos' => ['bloque_id' => $bloque->id],
                ], $config['canales']);
                $enviados += (int) in_array('enviado', $r, true);
            }
        }

        return $enviados;
    }

    /** @return array<int, User> docentes con cuenta (titular y los del equipo), sin repetir */
    private function docentesDe(Bloque $bloque): array
    {
        $docentes = [];
        foreach ([$bloque->profesor, ...$bloque->profesores] as $profesor) {
            if ($profesor instanceof Profesor && $profesor->user instanceof User && $profesor->user->activo) {
                $docentes[$profesor->user->id] = $profesor->user;
            }
        }

        return $docentes;
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
            $docentes = $bloque instanceof Bloque ? $this->docentesDe($bloque) : [];
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

    /**
     * Quienes participan de un evento: alumnos y docentes de su bloque o de su sede. Un evento de
     * toda la escuela no avisa a nadie (se ve en la agenda), salvo que sea una convocatoria: esa va a todos.
     *
     * @return Collection<int, User>
     */
    private function destinatariosDe(Evento $evento): Collection
    {
        if (! $evento->bloque_id && ! $evento->sede_id) {
            return $evento->tipo_evento === 'convocatoria'
                ? User::query()->where('activo', true)->get()
                : collect();
        }
        $alumnos = Alumno::query()->where('activo', true);
        $bloques = Bloque::query()->where('activo', true)->with(['profesor.user', 'profesores.user']);
        if ($evento->bloque_id) {
            $alumnos->where(fn ($q) => $q->where('bloque_id', $evento->bloque_id)->orWhereHas('bloques', fn ($b) => $b->where('bloques.id', $evento->bloque_id)));
            $bloques->whereKey($evento->bloque_id);
        } else {
            $alumnos->where(fn ($q) => $q->where('sede_id', $evento->sede_id)->orWhereHas('bloques', fn ($b) => $b->where('bloques.sede_id', $evento->sede_id)));
            $bloques->where('sede_id', $evento->sede_id);
        }
        $personaIds = $alumnos->pluck('persona_id')->filter()->unique()->all();
        $userIds = $alumnos->clone()->whereNotNull('user_id')->pluck('user_id')->all();
        $usuarios = User::query()->where('activo', true)
            ->where(fn ($q) => $q->whereIn('persona_id', $personaIds ?: [0])->orWhereIn('id', $userIds ?: [0]))->get()->keyBy('id');

        foreach ($bloques->get() as $bloque) {
            $usuarios = $usuarios->union($this->docentesDe($bloque));
        }
        if ($evento->profesor_id && ($profe = Profesor::query()->with('user')->find($evento->profesor_id)) && $profe->user?->activo) {
            $usuarios->put($profe->user->id, $profe->user);
        }

        return $usuarios->values();
    }

    private function cuentaDe(Alumno $alumno): ?User
    {
        return User::query()->where(fn ($q) => $q->where('persona_id', $alumno->persona_id ?: 0)->orWhere('id', $alumno->user_id ?: 0))
            ->where('activo', true)->first();
    }
}
