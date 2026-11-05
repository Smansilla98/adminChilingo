<?php

namespace App\Domain\Notificaciones;

use App\Models\Ajuste;

/**
 * Avisos automáticos configurables desde Configuración: si están activos, por qué canales salen,
 * el texto (con variables entre llaves) y, cuando aplica, cuántos días antes o después.
 */
class ConfigAvisos
{
    public const CLAVE = 'avisos';

    /** Variables que se pueden usar en los textos. */
    public const VARIABLES = [
        '{alumno}' => 'Nombre del alumno',
        '{cuota}' => 'Nombre de la cuota',
        '{monto}' => 'Monto a pagar (con beca aplicada)',
        '{vencimiento}' => 'Fecha de vencimiento',
        '{dias}' => 'Días que faltan para el vencimiento',
        '{alias}' => 'Alias para transferir',
    ];

    /**
     * @return array<string, array{nombre: string, descripcion: string, para: string, activo: bool, canales: list<string>, titulo: string, mensaje: string, dias?: int}>
     */
    public static function defaults(): array
    {
        return [
            'cuota_nueva' => [
                'nombre' => 'Cuota nueva para pagar',
                'descripcion' => 'Cuando se carga una cuota que le corresponde al alumno (se revisa cada 15 minutos).',
                'para' => 'Alumnos',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Nueva cuota: {cuota}',
                'mensaje' => 'Tenés para pagar {cuota} por {monto}{vencimiento}. Podés transferir al alias {alias} y cargar el comprobante desde Mi cuenta.',
            ],
            'cuota_por_vencer' => [
                'nombre' => 'Cuota por vencer',
                'descripcion' => 'Unos días antes del vencimiento, a quien todavía no la pagó.',
                'para' => 'Alumnos',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => '{cuota} vence en {dias} días',
                'mensaje' => 'Te quedan {dias} días para pagar {cuota} ({monto}). Alias: {alias}.',
                'dias' => 3,
            ],
            'cuota_vencida' => [
                'nombre' => 'Cuota vencida',
                'descripcion' => 'Al día siguiente del vencimiento, si sigue sin pagar (se revisan los vencimientos de los últimos días).',
                'para' => 'Alumnos',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Cuota vencida',
                'mensaje' => 'La cuota {cuota} venció el {vencimiento}. Si ya pagaste, cargá el comprobante.',
                'dias' => 30,
            ],
            'pago_registrado' => [
                'nombre' => 'Pago registrado',
                'descripcion' => 'Cuando administración registra un pago del alumno.',
                'para' => 'Alumnos',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Pago registrado',
                'mensaje' => 'Registramos tu pago de {cuota} por {monto}.',
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> configuración efectiva (defaults + lo guardado) */
    public static function todos(): array
    {
        $guardado = Ajuste::leer(self::CLAVE);
        $out = [];
        foreach (self::defaults() as $tipo => $def) {
            $g = (array) ($guardado[$tipo] ?? []);
            $out[$tipo] = array_merge($def, array_intersect_key($g, array_flip(['activo', 'canales', 'titulo', 'mensaje', 'dias'])));
            $out[$tipo]['activo'] = (bool) $out[$tipo]['activo'];
            $out[$tipo]['canales'] = array_values(array_intersect((array) $out[$tipo]['canales'], NotificacionService::CANALES));
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public static function de(string $tipo): array
    {
        return self::todos()[$tipo];
    }

    /**
     * Texto final: reemplaza las variables. Una variable vacía desaparece sin dejar «{…}».
     *
     * @param  array<string, string|null>  $vars  sin llaves: ['cuota' => 'Abril', …]
     * @return array{titulo: string, mensaje: string}
     */
    public static function texto(string $tipo, array $vars): array
    {
        $c = self::de($tipo);
        $reemplazos = [];
        foreach ($vars as $k => $v) {
            $reemplazos['{'.$k.'}'] = (string) ($v ?? '');
        }
        $render = fn (string $t) => trim(preg_replace('/\{[a-z_]+\}/', '', strtr($t, $reemplazos)) ?? $t);

        return ['titulo' => $render((string) $c['titulo']), 'mensaje' => $render((string) $c['mensaje'])];
    }

    /**
     * Guarda lo que llegó; lo que no vino (un aviso entero o uno de sus campos) conserva el valor actual.
     *
     * @param  array<string, array<string, mixed>>  $datos
     */
    public static function guardar(array $datos, ?int $por = null): void
    {
        $actual = self::todos();
        $limpio = [];
        foreach (self::defaults() as $tipo => $def) {
            $d = (array) ($datos[$tipo] ?? []);
            $a = $actual[$tipo];
            $limpio[$tipo] = [
                'activo' => array_key_exists('activo', $d) ? (bool) $d['activo'] : $a['activo'],
                'canales' => array_key_exists('canales', $d)
                    ? array_values(array_intersect((array) $d['canales'], NotificacionService::CANALES))
                    : $a['canales'],
                'titulo' => mb_substr(trim((string) ($d['titulo'] ?? $a['titulo'])), 0, 120) ?: $def['titulo'],
                'mensaje' => mb_substr(trim((string) ($d['mensaje'] ?? $a['mensaje'])), 0, 600) ?: $def['mensaje'],
            ] + (isset($def['dias']) ? ['dias' => max(1, min(60, (int) ($d['dias'] ?? $a['dias'])))] : []);
        }
        Ajuste::guardar(self::CLAVE, $limpio, $por);
    }
}
