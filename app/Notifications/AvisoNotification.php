<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso interno (bandeja de la app y del panel). Se guarda en la tabla `notifications`.
 */
class AvisoNotification extends Notification
{
    /**
     * @param  array{tipo: string, titulo: string, mensaje: string, enlace?: ?string, datos?: array<string, mixed>}  $contenido
     */
    public function __construct(public array $contenido) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return $this->contenido;
    }
}
