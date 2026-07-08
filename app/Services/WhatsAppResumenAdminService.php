<?php

namespace App\Services;

use App\Models\User;

class WhatsAppResumenAdminService
{
    public function __construct(
        private WhatsAppService $whatsapp,
        private RecordatorioChatbotService $recordatorios,
    ) {}

    public function isDisponible(): bool
    {
        return $this->whatsapp->isConfigured() && $this->destinatarios() !== [];
    }

    /**
     * @return array{ok: bool, enviados: int, errores: int, mensaje: string, detalles: array<int, array<string, mixed>>}
     */
    public function enviar(bool $dryRun = false): array
    {
        if (! $this->whatsapp->isConfigured()) {
            return [
                'ok' => false,
                'enviados' => 0,
                'errores' => 0,
                'mensaje' => 'WhatsApp no configurado. Revisá TWILIO_* en .env.',
                'detalles' => [],
            ];
        }

        $destinatarios = $this->destinatarios();
        if ($destinatarios === []) {
            return [
                'ok' => false,
                'enviados' => 0,
                'errores' => 0,
                'mensaje' => 'No hay destinatarios. Cargá un teléfono en Accesos o WHATSAPP_ADMIN_NUMBERS en .env.',
                'detalles' => [],
            ];
        }

        $fallbackAdmin = $this->primerAdmin();
        if (! $fallbackAdmin) {
            return [
                'ok' => false,
                'enviados' => 0,
                'errores' => 0,
                'mensaje' => 'No hay ningún usuario administrador.',
                'detalles' => [],
            ];
        }

        $enviados = 0;
        $errores = 0;
        $detalles = [];

        foreach ($destinatarios as $telefono => $usuario) {
            $admin = $usuario ?? $fallbackAdmin;
            $data = $this->recordatorios->build($admin);
            $mensaje = $this->recordatorios->formatWhatsAppResumenSemanal($data);
            $etiqueta = $usuario
                ? ($usuario->name ?: $usuario->username ?: 'Admin #'.$usuario->id)
                : $telefono;

            if ($dryRun) {
                $enviados++;
                $detalles[] = [
                    'telefono' => $telefono,
                    'etiqueta' => $etiqueta,
                    'success' => true,
                    'preview' => $mensaje,
                ];

                continue;
            }

            $result = $this->whatsapp->send($mensaje, $telefono);
            if ($result['success']) {
                $enviados++;
                $detalles[] = [
                    'telefono' => $telefono,
                    'etiqueta' => $etiqueta,
                    'success' => true,
                ];
            } else {
                $errores++;
                $detalles[] = [
                    'telefono' => $telefono,
                    'etiqueta' => $etiqueta,
                    'success' => false,
                    'error' => $result['error'] ?? 'Error desconocido',
                ];
            }
        }

        $ok = $errores === 0 && $enviados > 0;
        $mensajeResumen = $dryRun
            ? "Vista previa para {$enviados} destinatario(s)."
            : ($ok
                ? "Enviado a {$enviados} destinatario(s)."
                : ($enviados > 0
                    ? "Enviado a {$enviados}, con {$errores} error(es)."
                    : 'No se pudo enviar a ningún destinatario.'));

        return [
            'ok' => $ok,
            'enviados' => $enviados,
            'errores' => $errores,
            'mensaje' => $mensajeResumen,
            'detalles' => $detalles,
        ];
    }

    /**
     * @return array<string, User|null>
     */
    public function destinatarios(): array
    {
        $out = [];

        User::query()
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->get()
            ->filter(fn (User $u) => $u->isAdmin())
            ->each(function (User $u) use (&$out) {
                $phone = WhatsAppService::normalizePhone($u->telefono);
                if ($phone !== '') {
                    $out[$phone] = $u;
                }
            });

        foreach (config('services.twilio.admin_whatsapp_numbers', []) as $num) {
            $phone = WhatsAppService::normalizePhone((string) $num);
            if ($phone !== '' && ! array_key_exists($phone, $out)) {
                $out[$phone] = null;
            }
        }

        return $out;
    }

    private function primerAdmin(): ?User
    {
        return User::query()
            ->get()
            ->first(fn (User $u) => $u->isAdmin());
    }
}
