<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RecordatorioChatbotService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppResumenAdminCommand extends Command
{
    protected $signature = 'whatsapp:resumen-admin
                            {--dry-run : Mostrar mensaje y destinatarios sin enviar}';

    protected $description = 'Envía por WhatsApp el resumen semanal de pendientes (asistencias y cuotas) a administradores';

    public function handle(
        WhatsAppService $whatsapp,
        RecordatorioChatbotService $recordatorios
    ): int {
        if (! $whatsapp->isConfigured()) {
            $this->error('WhatsApp no configurado. Definí TWILIO_* en .env (ver WHATSAPP.md).');

            return self::FAILURE;
        }

        $destinatarios = $this->destinatarios();
        if ($destinatarios === []) {
            $this->error('No hay destinatarios. Cargá teléfono en un usuario admin o definí WHATSAPP_ADMIN_NUMBERS en .env.');

            return self::FAILURE;
        }

        $fallbackAdmin = $this->primerAdmin();
        if (! $fallbackAdmin) {
            $this->error('No hay ningún usuario administrador en el sistema.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $enviados = 0;
        $errores = 0;

        foreach ($destinatarios as $telefono => $usuario) {
            $admin = $usuario ?? $fallbackAdmin;
            $data = $recordatorios->build($admin);
            $mensaje = $recordatorios->formatWhatsAppResumenSemanal($data);
            $etiqueta = $usuario
                ? ($usuario->name ?: $usuario->username ?: 'Admin #'.$usuario->id)
                : $telefono;

            if ($dryRun) {
                $this->line("--- {$etiqueta} ({$telefono}) ---");
                $this->line($mensaje);
                $this->newLine();
                $enviados++;

                continue;
            }

            $result = $whatsapp->send($mensaje, $telefono);
            if ($result['success']) {
                $enviados++;
                $this->info("Enviado a {$etiqueta}");
            } else {
                $errores++;
                $this->warn("{$etiqueta}: ".($result['error'] ?? 'Error desconocido'));
            }
        }

        $this->info("Resumen: {$enviados} enviado(s), {$errores} error(es).");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, User|null> teléfono normalizado => usuario (null si viene solo del .env)
     */
    private function destinatarios(): array
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
