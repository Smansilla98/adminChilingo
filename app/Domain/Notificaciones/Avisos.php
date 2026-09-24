<?php

namespace App\Domain\Notificaciones;

use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Evento;
use App\Models\Pago;
use App\Models\PagoDetalle;
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
        $alumno = $detalle->alumno;
        $user = $alumno ? $this->cuentaDe($alumno) : null;
        if (! $user) {
            return;
        }
        $this->notificaciones->enviar($user, "pago:{$detalle->pago_id}:alumno:{$alumno->id}:cuota:{$detalle->cuota_id}", [
            'tipo' => 'pago_registrado',
            'titulo' => 'Pago registrado',
            'mensaje' => 'Registramos tu pago de '.($detalle->cuota?->nombre ?? 'la cuota').' por $'.number_format((float) $detalle->monto, 0, ',', '.').'.',
            'enlace' => 'app://cuotas',
        ]);
    }

    /**
     * Cuotas vencidas sin pagar (alumnos con cuenta). Devuelve cuántos avisos nuevos salieron.
     */
    public function cuotasVencidas(int $diasAtras = 30): int
    {
        $enviados = 0;
        $cuotas = Cuota::query()->where('activo', true)->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [now()->subDays($diasAtras)->toDateString(), now()->subDay()->toDateString()])
            ->get();
        foreach ($cuotas as $cuota) {
            $pagaron = PagoDetalle::query()->where('cuota_id', $cuota->id)->pluck('alumno_id')->all();
            $alumnos = Alumno::query()->where('activo', true)->whereNotIn('id', $pagaron ?: [0])
                ->where(fn ($q) => $q->whereNotNull('user_id')->orWhereHas('persona.user'))
                ->with(['bloques:id,sede_id', 'bloque:id,sede_id'])->get();
            foreach ($alumnos as $alumno) {
                if (! $cuota->aplicaAAlumno($alumno) || ! ($user = $this->cuentaDe($alumno))) {
                    continue;
                }
                $r = $this->notificaciones->enviar($user, "cuota_vencida:{$alumno->id}:{$cuota->id}", [
                    'tipo' => 'cuota_vencida',
                    'titulo' => 'Cuota vencida',
                    'mensaje' => "La cuota {$cuota->nombre} venció el {$cuota->fecha_vencimiento->format('d/m')}. Si ya pagaste, cargá el comprobante.",
                    'enlace' => 'app://cuotas',
                ]);
                $enviados += (int) (($r['interna'] ?? null) === 'enviado');
            }
        }

        return $enviados;
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
