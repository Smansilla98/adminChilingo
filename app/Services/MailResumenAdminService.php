<?php

namespace App\Services;

use App\Mail\ResumenSemanalAdminMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class MailResumenAdminService
{
    public function __construct(
        private RecordatorioChatbotService $recordatorios,
    ) {}

    public function isDisponible(): bool
    {
        return $this->destinatarios() !== [];
    }

    /**
     * @return array{ok: bool, enviados: int, errores: int, mensaje: string, detalles: array<int, array<string, mixed>>}
     */
    public function enviar(?string $toOverride = null, bool $dryRun = false): array
    {
        $destinatarios = $this->destinatarios($toOverride);
        if ($destinatarios === []) {
            return [
                'ok' => false,
                'enviados' => 0,
                'errores' => 0,
                'mensaje' => 'No hay destinatarios. Definí ADMIN_RESUMEN_EMAIL o pasá --to=.',
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

        $payload = $this->recordatorios->buildMailPayload($fallbackAdmin);
        $subject = ($payload['app_name'] ?? 'ITO').' — Resumen diario · '.($payload['fecha_corta'] ?? now()->format('d/m/Y'));

        $enviados = 0;
        $errores = 0;
        $detalles = [];

        foreach ($destinatarios as $email) {
            $preview = $this->recordatorios->formatMailResumenTexto($payload);

            if ($dryRun) {
                $enviados++;
                $detalles[] = [
                    'email' => $email,
                    'success' => true,
                    'preview_subject' => $subject,
                    'preview' => $preview,
                ];

                continue;
            }

            try {
                Mail::to($email)->send(new ResumenSemanalAdminMail($subject, $payload));
                $enviados++;
                $detalles[] = ['email' => $email, 'success' => true];
            } catch (\Throwable $e) {
                $errores++;
                $detalles[] = ['email' => $email, 'success' => false, 'error' => $e->getMessage()];
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
     * @return list<string>
     */
    public function destinatarios(?string $toOverride = null): array
    {
        $out = [];

        $override = trim((string) $toOverride);
        if ($override !== '') {
            $out[] = $override;
        }

        $cfg = trim((string) config('mail.admin_resumen_email', ''));
        if ($cfg !== '' && ! in_array($cfg, $out, true)) {
            $out[] = $cfg;
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

