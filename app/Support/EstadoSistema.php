<?php

namespace App\Support;

use App\Domain\Datos\Respaldo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Chequeos de configuración que no se ven desde el sistema y que ya causaron fallas:
 * links de mails a un dominio viejo, archivos que se borran en cada deploy, cobros
 * que no aparecen. Se muestran en Configuración → Estado del sistema.
 */
final class EstadoSistema
{
    public const ERROR = 'error';

    public const AVISO = 'aviso';

    public const OK = 'ok';

    /**
     * @return list<array{clave: string, titulo: string, estado: string, detalle: string, arreglo: string|null, descarga?: bool}>
     */
    public static function chequeos(?Request $request = null): array
    {
        $produccion = app()->environment('production');

        return [
            self::dominio($request, $produccion),
            self::almacenamiento($produccion),
            self::respaldos($produccion),
            self::mail(),
            self::cobroTransferencia(),
            self::mercadoPago(),
            self::whatsapp(),
        ];
    }

    /** Problemas graves: los que se avisan en el inicio de administración. */
    public static function errores(?Request $request = null): int
    {
        // El inicio lo consulta en cada visita: se recalcula cada 10 minutos.
        return Cache::remember('estado-sistema:errores:'.($request?->getHost() ?? '-'), 600, fn () => count(array_filter(
            self::chequeos($request),
            fn (array $c) => $c['estado'] === self::ERROR,
        )));
    }

    private static function dominio(?Request $request, bool $produccion): array
    {
        $configurado = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $actual = $request?->getHost();
        $coincide = $actual === null || $configurado === $actual;

        return [
            'clave' => 'dominio',
            'titulo' => 'Dirección del sistema (APP_URL)',
            'estado' => $coincide ? self::OK : ($produccion ? self::ERROR : self::AVISO),
            'detalle' => $coincide
                ? "Los links de mails y WhatsApp apuntan a {$configurado}."
                : "Los links de mails y WhatsApp apuntan a «{$configurado}», pero el sistema se está usando en «{$actual}». Los botones de los mails van a fallar.",
            'arreglo' => $coincide ? null : "En Railway, cambiá APP_URL a https://{$actual} y redesplegá.",
        ];
    }

    private static function almacenamiento(bool $produccion): array
    {
        $configurado = (bool) config('chilinga.almacenamiento_persistente');
        $escribe = false;
        try {
            $disco = Storage::disk('comprobantes');
            $prueba = '.estado-sistema-'.bin2hex(random_bytes(4));
            $escribe = $disco->put($prueba, 'ok') && $disco->delete($prueba);
        } catch (Throwable) {
            $escribe = false;
        }
        $estado = match (true) {
            ! $escribe => self::ERROR,
            ! $configurado && $produccion => self::ERROR,
            ! $configurado => self::AVISO,
            default => self::OK,
        };

        return [
            'clave' => 'almacenamiento',
            'titulo' => 'Archivos (comprobantes, diseños, fotos)',
            'estado' => $estado,
            'detalle' => match ($estado) {
                self::OK => 'Se guardan en un volumen persistente: no se pierden al redesplegar.',
                self::ERROR => $escribe
                    ? 'Se guardan dentro del contenedor: Railway los borra en cada deploy.'
                    : 'No se puede escribir en la carpeta de archivos.',
                default => 'Se guardan en storage/app/persistent (bien para desarrollo).',
            },
            'arreglo' => $estado === self::OK ? null : 'En Railway, agregá un volumen (por ejemplo en /data) y la variable PERSISTENT_STORAGE_PATH=/data/storage.',
        ];
    }

    private static function mail(): array
    {
        $mailer = (string) config('mail.default');
        $envia = ! in_array($mailer, ['log', 'array'], true);

        return [
            'clave' => 'mail',
            'titulo' => 'Envío de mails',
            'estado' => $envia ? self::OK : self::AVISO,
            'detalle' => $envia ? "Se envían con «{$mailer}»." : 'Los mails no salen: quedan solo en el registro del sistema.',
            'arreglo' => $envia ? null : 'Configurá MAIL_MAILER y sus credenciales (por ejemplo, SES o SMTP).',
        ];
    }

    private static function cobroTransferencia(): array
    {
        $datos = array_filter(config('chilinga.cobro', []), fn ($v) => filled($v));
        $ok = isset($datos['alias']) || isset($datos['cbu']);

        return [
            'clave' => 'transferencia',
            'titulo' => 'Pago de cuotas por transferencia',
            'estado' => $ok ? self::OK : self::AVISO,
            'detalle' => $ok
                ? 'Activo: el alumno ve '.collect(['alias' => 'alias '.($datos['alias'] ?? ''), 'cbu' => 'CBU', 'titular' => 'titular'])->only(array_keys($datos))->implode(', ').'.'
                : 'Apagado: en «Pagar cuotas» no aparece la opción de transferir.',
            'arreglo' => $ok ? null : 'Cargá COBRO_ALIAS (o COBRO_CBU) y COBRO_TITULAR. Ver docs/COBROS.md.',
        ];
    }

    private static function mercadoPago(): array
    {
        $token = filled(config('services.mercadopago.access_token'));
        $firma = filled(config('services.mercadopago.webhook_secret'));

        return [
            'clave' => 'mercadopago',
            'titulo' => 'Pago de cuotas con Mercado Pago',
            'estado' => ! $token ? self::AVISO : ($firma ? self::OK : self::AVISO),
            'detalle' => match (true) {
                ! $token => 'Apagado: falta el access token.',
                ! $firma => 'Activo, pero sin clave de webhook: los avisos no se verifican con firma (igual se confirman contra la API).',
                default => 'Activo, con avisos verificados. URL de aviso: '.route('webhooks.mercadopago').'.',
            },
            'arreglo' => match (true) {
                ! $token => 'Cargá MERCADOPAGO_ACCESS_TOKEN (de producción) y configurá el webhook. Ver docs/COBROS.md.',
                ! $firma => 'Copiá la clave secreta del webhook en MERCADOPAGO_WEBHOOK_SECRET.',
                default => null,
            },
        ];
    }

    private static function respaldos(bool $produccion): array
    {
        $ultimo = Respaldo::ultimo();
        $fecha = isset($ultimo['fecha']) ? Carbon::parse($ultimo['fecha']) : null;
        $horas = $fecha ? (int) $fecha->diffInHours(now()) : null;
        $ok = $ultimo !== null && $ultimo['ok'];
        $estado = match (true) {
            $ultimo === null => $produccion ? self::ERROR : self::AVISO,
            ! $ok => self::ERROR,
            $horas > 36 => self::ERROR,
            default => self::OK,
        };
        $copias = $ultimo !== null ? count(Respaldo::archivos()) : 0;

        return [
            'clave' => 'respaldos',
            'titulo' => 'Backups de la base de datos',
            'estado' => $estado,
            'detalle' => match (true) {
                $ultimo === null => 'Todavía no se hizo ningún backup. Se generan solos todos los días a las 3:30.',
                ! $ok => 'El último intento falló ('.$fecha?->diffForHumans().'): '.($ultimo['error'] ?? 'error desconocido').'.',
                $horas > 36 => 'El último backup es de '.$fecha->diffForHumans().': el automático dejó de correr.',
                default => 'Último backup '.$fecha->diffForHumans().' ('.number_format(($ultimo['bytes'] ?? 0) / 1024 / 1024, 1, ',', '.').' MB). Se guardan los últimos '.$copias.'.',
            },
            'arreglo' => match (true) {
                $estado === self::OK => null,
                $ultimo === null => 'Esperá al primer backup nocturno o corré «php artisan chilinga:respaldo». Necesita el volumen persistente.',
                default => 'Revisá que el volumen persistente tenga espacio y que el scheduler esté activo (SCHEDULER_ENABLED=1).',
            },
            'descarga' => $ok,
        ];
    }

    private static function whatsapp(): array
    {
        $ok = filled(config('services.twilio.account_sid')) && filled(config('services.twilio.auth_token')) && filled(config('services.twilio.whatsapp_from'));

        return [
            'clave' => 'whatsapp',
            'titulo' => 'Recordatorios por WhatsApp',
            'estado' => $ok ? self::OK : self::AVISO,
            'detalle' => $ok ? 'Activo.' : 'Apagado: faltan las credenciales de Twilio.',
            'arreglo' => $ok ? null : 'Ver docs/WHATSAPP.md.',
        ];
    }
}
