<?php

namespace App\Domain\Inventario;

use App\Domain\Agenda\ConvocatoriaShow;
use App\Domain\Notificaciones\ConfigAvisos;
use App\Domain\Notificaciones\NotificacionService;
use App\Models\InventarioPrestamo;
use App\Models\User;

/**
 * Aviso de préstamo vencido (una vez por préstamo y persona): a quien tiene el instrumento y a la
 * administración del inventario (quienes pueden editarlo en toda la escuela).
 */
class AvisosPrestamos
{
    public function __construct(private readonly NotificacionService $notificaciones) {}

    public function vencidos(): int
    {
        $config = ConfigAvisos::de('prestamo_vencido');
        if (! $config['activo'] || $config['canales'] === []) {
            return 0;
        }
        $admin = User::query()->where('activo', true)->get()->filter(fn (User $u) => $u->acceso()->puedeGlobal('inventario.update'));

        $enviados = 0;
        InventarioPrestamo::query()->vencidos()->with(['item', 'alumno', 'persona'])->get()->each(function (InventarioPrestamo $p) use ($config, $admin, &$enviados) {
            $texto = ConfigAvisos::texto('prestamo_vencido', [
                'alumno' => $p->responsable(),
                'instrumento' => (string) $p->item?->nombre,
                'vencimiento' => $p->devolucion_prevista->format('d/m'),
            ]);
            $destinatarios = $admin->all();
            $alumno = $p->alumno;
            if ($alumno && ($cuenta = ConvocatoriaShow::cuentaDe($alumno))) {
                $destinatarios[] = $cuenta;
            }
            foreach (collect($destinatarios)->unique('id') as $user) {
                $r = $this->notificaciones->enviar($user, "prestamo_vencido:{$p->id}:{$user->id}", [
                    'tipo' => 'prestamo_vencido',
                    ...$texto,
                    'enlace' => 'app://mis-prestamos',
                    'datos' => ['prestamo_id' => $p->id],
                ], $config['canales']);
                $enviados += (int) in_array('enviado', $r, true);
            }
        });

        return $enviados;
    }
}
