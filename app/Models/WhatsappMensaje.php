<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMensaje extends Model
{
    public const TIPO_CUOTA = 'cuota';

    public const TIPO_EVENTO = 'evento';

    public const TIPO_RESUMEN_ADMIN = 'resumen_admin';

    public const TIPO_TEST = 'test';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNDELIVERED = 'undelivered';

    /**
     * Progresión Twilio WhatsApp. Un callback fuera de orden no puede bajar de rango.
     * failed/undelivered son terminales (rango 100).
     *
     * @var array<string, int>
     */
    public const STATUS_RANK = [
        self::STATUS_QUEUED => 10,
        'accepted' => 10,
        self::STATUS_SENDING => 20,
        self::STATUS_SENT => 30,
        self::STATUS_DELIVERED => 40,
        self::STATUS_READ => 50,
        self::STATUS_UNDELIVERED => 100,
        self::STATUS_FAILED => 100,
    ];

    protected $table = 'whatsapp_mensajes';

    protected $fillable = [
        'alumno_id',
        'user_id',
        'cuota_id',
        'telefono',
        'tipo',
        'twilio_sid',
        'status',
        'error_code',
        'error_message',
        'accepted_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    public static function normalizeStatus(?string $raw): ?string
    {
        $status = strtolower(trim((string) $raw));
        if ($status === 'accepted') {
            return self::STATUS_QUEUED;
        }
        if (! array_key_exists($status, self::STATUS_RANK)) {
            return null;
        }

        return $status;
    }

    public function isDeliveredToRecipient(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_READ], true);
    }

    public function etiquetaEstado(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Enviado',
            self::STATUS_DELIVERED => 'Entregado',
            self::STATUS_READ => 'Leído',
            self::STATUS_FAILED => 'Fallido',
            self::STATUS_UNDELIVERED => 'No entregado',
            default => 'Pendiente de confirmación',
        };
    }

    public function isFailureStatus(?string $status = null): bool
    {
        return in_array($status ?? $this->status, [self::STATUS_FAILED, self::STATUS_UNDELIVERED], true);
    }

    /**
     * Aplica un status de Twilio sin retroceder ni duplicar filas.
     * delivered/read no vuelven a sent; failed/undelivered son terminales.
     *
     * @return bool true si el registro se actualizó
     */
    public function applyTwilioCallback(string $rawStatus, ?string $errorCode = null, ?string $errorMessage = null): bool
    {
        $status = self::normalizeStatus($rawStatus);
        if ($status === null) {
            return false;
        }

        $incoming = self::STATUS_RANK[$status];
        $current = self::STATUS_RANK[$this->status] ?? 0;
        $incomingFailure = $this->isFailureStatus($status);
        $currentFailure = $this->isFailureStatus();
        $currentDelivered = in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_READ], true);

        if ($currentFailure && ! $incomingFailure) {
            return false;
        }
        if ($incomingFailure && $currentDelivered) {
            return false;
        }
        if (! $incomingFailure && ! $currentFailure && $incoming < $current) {
            return false;
        }

        if ($incoming === $current) {
            if ($this->status !== $status) {
                $this->status = $status;
            }
            $this->fillError($errorCode, $errorMessage);
            if (! $this->isDirty()) {
                return false;
            }
            $this->save();

            return true;
        }

        $this->status = $status;
        $now = now();
        if (in_array($status, [self::STATUS_SENT, self::STATUS_DELIVERED, self::STATUS_READ], true) && ! $this->sent_at) {
            $this->sent_at = $now;
        }
        if (in_array($status, [self::STATUS_DELIVERED, self::STATUS_READ], true) && ! $this->delivered_at) {
            $this->delivered_at = $now;
        }
        if ($status === self::STATUS_READ && ! $this->read_at) {
            $this->read_at = $now;
        }
        if ($incomingFailure) {
            $this->failed_at = $now;
        }
        $this->fillError($errorCode, $errorMessage);
        $this->save();

        return true;
    }

    private function fillError(?string $errorCode, ?string $errorMessage): void
    {
        if ($errorCode !== null && $errorCode !== '') {
            $this->error_code = substr($errorCode, 0, 32);
        }
        if ($errorMessage !== null && $errorMessage !== '') {
            $this->error_message = $errorMessage;
        }
    }
}
