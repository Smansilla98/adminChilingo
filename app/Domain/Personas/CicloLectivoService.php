<?php

namespace App\Domain\Personas;

use App\Domain\Notificaciones\NotificacionService;
use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\CicloLectivo;
use App\Models\Reinscripcion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ciclo lectivo y reinscripción anual. Abrir un ciclo crea una reinscripción pendiente por cada
 * alumno activo y le avisa. Quien confirma queda reinscripto; a quien no confirma la administración
 * le puede dar de baja (el alumno pasa a inactivo: no se borran cuotas, pagos ni asistencias).
 */
class CicloLectivoService
{
    /** @param  array{anio: int, nombre?: string|null, inicio: string, fin: string}  $datos */
    public function abrir(array $datos, User $por): CicloLectivo
    {
        if ($datos['fin'] < $datos['inicio']) {
            throw ValidationException::withMessages(['fin' => 'La fecha de fin tiene que ser posterior al inicio.']);
        }

        return DB::transaction(function () use ($datos, $por) {
            $ciclo = CicloLectivo::query()->create([
                'anio' => $datos['anio'],
                'nombre' => $datos['nombre'] ?: 'Ciclo '.$datos['anio'],
                'inicio' => $datos['inicio'],
                'fin' => $datos['fin'],
                'abierto_por' => $por->id,
            ]);

            Alumno::query()->where('activo', true)->orderBy('id')->chunkById(200, function ($alumnos) use ($ciclo) {
                foreach ($alumnos as $alumno) {
                    Reinscripcion::query()->create([
                        'ciclo_lectivo_id' => $ciclo->id,
                        'alumno_id' => $alumno->id,
                        'estado' => Reinscripcion::PENDIENTE,
                    ]);
                }
            });

            Auditoria::registrar('ciclo.abrir', $ciclo, null, ['anio' => $ciclo->anio, 'por' => $por->id]);

            return $ciclo;
        });
    }

    /** Aviso a cada alumno con cuenta. Se llama después de abrir (fuera de la transacción). */
    public function avisar(CicloLectivo $ciclo): int
    {
        $enviados = 0;
        Reinscripcion::query()->where('ciclo_lectivo_id', $ciclo->id)->with('alumno.user')->chunkById(200, function ($lote) use ($ciclo, &$enviados) {
            foreach ($lote as $r) {
                $user = $r->alumno?->user;
                if (! $user) {
                    continue;
                }
                app(NotificacionService::class)->enviar($user, 'reinscripcion:'.$ciclo->anio.':'.$r->alumno_id, [
                    'tipo' => 'aviso',
                    'titulo' => 'Reinscripción '.$ciclo->anio,
                    'mensaje' => 'Ya está abierta la reinscripción a '.$ciclo->nombre.'. Confirmala desde la app o desde Mi espacio.',
                    'enlace' => '/mi-reinscripcion',
                ], ['interna', 'push']);
                $enviados++;
            }
        });

        return $enviados;
    }

    /** El alumno confirma su reinscripción al ciclo vigente (puede tener varios alumnos con la misma cuenta). */
    public function confirmar(User $user): int
    {
        $ciclo = CicloLectivo::vigente();
        if (! $ciclo) {
            throw ValidationException::withMessages(['ciclo' => 'Todavía no hay un ciclo lectivo abierto para reinscribirse.']);
        }

        $alumnosIds = Alumno::query()->where('user_id', $user->id)->where('activo', true)->pluck('id');
        if ($alumnosIds->isEmpty()) {
            throw ValidationException::withMessages(['ciclo' => 'No tenés un alumno activo para reinscribir.']);
        }

        return Reinscripcion::query()
            ->where('ciclo_lectivo_id', $ciclo->id)
            ->whereIn('alumno_id', $alumnosIds)
            ->where('estado', Reinscripcion::PENDIENTE)
            ->update(['estado' => Reinscripcion::CONFIRMADA, 'confirmada_at' => now(), 'updated_at' => now()]);
    }

    /** Dar de baja a quien no se reinscribió: el alumno queda inactivo. No borra cuotas, pagos ni asistencias. */
    public function darDeBaja(Reinscripcion $r, User $por): void
    {
        if ($r->estado !== Reinscripcion::PENDIENTE) {
            throw ValidationException::withMessages(['estado' => 'Solo se da de baja a quien no se reinscribió.']);
        }

        DB::transaction(function () use ($r, $por) {
            $alumno = Alumno::query()->findOrFail($r->alumno_id);
            $alumno->forceFill(['activo' => false])->save();
            $r->forceFill(['estado' => Reinscripcion::BAJA, 'resuelta_por' => $por->id, 'resuelta_at' => now()])->save();
            Auditoria::registrar('reinscripcion.baja', $alumno, ['activo' => true], ['activo' => false, 'ciclo' => $r->ciclo_lectivo_id]);
        });
    }

    /** @return array{total: int, confirmadas: int, pendientes: int, bajas: int} */
    public function resumen(CicloLectivo $ciclo): array
    {
        $porEstado = Reinscripcion::query()->where('ciclo_lectivo_id', $ciclo->id)
            ->selectRaw('estado, count(*) as n')->groupBy('estado')->pluck('n', 'estado');

        return [
            'total' => (int) $porEstado->sum(),
            'confirmadas' => (int) ($porEstado[Reinscripcion::CONFIRMADA] ?? 0),
            'pendientes' => (int) ($porEstado[Reinscripcion::PENDIENTE] ?? 0),
            'bajas' => (int) ($porEstado[Reinscripcion::BAJA] ?? 0),
        ];
    }
}
