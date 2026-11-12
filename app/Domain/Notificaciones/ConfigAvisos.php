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

    /** Variables que se pueden usar en los textos (cada aviso dice cuáles le sirven). */
    public const VARIABLES = [
        '{alumno}' => 'Nombre del alumno',
        '{cuota}' => 'Nombre de la cuota',
        '{monto}' => 'Monto a pagar (con beca aplicada)',
        '{vencimiento}' => 'Fecha de vencimiento',
        '{dias}' => 'Días que faltan',
        '{alias}' => 'Alias para transferir',
        '{bloque}' => 'Nombre del bloque',
        '{hora}' => 'Hora de la clase o del evento',
        '{evento}' => 'Título del evento o la convocatoria',
        '{fecha}' => 'Fecha del evento (ej. sáb 24/03)',
        '{lugar}' => 'Sede o lugar',
        '{descripcion}' => 'Descripción del evento',
        '{instrumento}' => 'Instrumento prestado',
    ];

    private const VARS_CUOTA = ['{alumno}', '{cuota}', '{monto}', '{vencimiento}', '{dias}', '{alias}'];

    private const VARS_EVENTO = ['{evento}', '{fecha}', '{hora}', '{lugar}', '{descripcion}', '{dias}'];

    /** Plazos numéricos que puede tener un aviso: [mínimo, máximo]. */
    public const PLAZOS = ['dias' => [1, 60], 'minutos' => [5, 240]];

    /**
     * @return array<string, array{nombre: string, descripcion: string, para: string, activo: bool, canales: list<string>, titulo: string, mensaje: string, dias?: int}>
     */
    public static function defaults(): array
    {
        return [
            'cuota_nueva' => [
                'nombre' => 'Cuota nueva para pagar',
                'descripcion' => 'Cuando se carga una cuota que le corresponde al alumno (se revisa cada 15 minutos).',
                'variables' => self::VARS_CUOTA,
                'para' => 'Alumnos',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Nueva cuota: {cuota}',
                'mensaje' => 'Tenés para pagar {cuota} por {monto}{vencimiento}. Podés transferir al alias {alias} y cargar el comprobante desde Mi cuenta.',
            ],
            'cuota_por_vencer' => [
                'nombre' => 'Cuota por vencer',
                'descripcion' => 'Unos días antes del vencimiento, a quien todavía no la pagó.',
                'variables' => self::VARS_CUOTA,
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
                'variables' => self::VARS_CUOTA,
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
                'variables' => self::VARS_CUOTA,
                'para' => 'Alumnos',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Pago registrado',
                'mensaje' => 'Registramos tu pago de {cuota} por {monto}.',
            ],
            'pasar_lista' => [
                'nombre' => 'Recordatorio para pasar lista',
                'descripcion' => 'Si la clase empezó hace un rato y todavía no tiene asistencia cargada, se le recuerda a sus docentes (una vez por clase).',
                'variables' => ['{bloque}', '{hora}', '{lugar}'],
                'para' => 'Docentes',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Pasá lista: {bloque}',
                'mensaje' => 'La clase de las {hora} todavía no tiene la asistencia cargada. Tocá para tomarla.',
                'minutos' => 30,
                'plazo_etiqueta' => 'Minutos después de empezar la clase',
            ],
            'evento_nuevo' => [
                'nombre' => 'Evento nuevo',
                'descripcion' => 'Cuando se carga un evento de un bloque o de una sede: a sus alumnos y docentes (los eventos de toda la escuela solo se ven en la agenda).',
                'variables' => self::VARS_EVENTO,
                'para' => 'Alumnos y docentes',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Nuevo evento: {evento}',
                'mensaje' => '{fecha} {hora} · {lugar}. {descripcion}',
            ],
            'evento_proximo' => [
                'nombre' => 'Recordatorio de evento',
                'descripcion' => 'Unos días antes de cada evento y convocatoria, a quienes participan.',
                'variables' => self::VARS_EVENTO,
                'para' => 'Alumnos y docentes',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Se viene: {evento}',
                'mensaje' => '{fecha} {hora} · {lugar}. ¡Te esperamos!',
                'dias' => 1,
            ],
            'convocatoria' => [
                'nombre' => 'Convocatoria',
                'descripcion' => 'Al cargar un evento de tipo «Convocatoria» (una marcha, una movilización, una asamblea): a todo el ámbito elegido; si es de toda la escuela, a todas las personas con cuenta.',
                'variables' => self::VARS_EVENTO,
                'para' => 'Toda la escuela o el ámbito elegido',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Convocatoria: {evento}',
                'mensaje' => '{fecha} {hora} · {lugar}. {descripcion}',
            ],
            'show_convocatoria' => [
                'nombre' => 'Convocatoria a un show',
                'descripcion' => 'Cuando se convoca a un bloque a un show: a cada alumno, para que confirme si va (se revisa cada 5 minutos).',
                'variables' => self::VARS_EVENTO,
                'para' => 'Alumnos convocados',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Te convocaron: {evento}',
                'mensaje' => '{fecha} {hora} · {lugar}. Contanos si vas: tocá para confirmar.',
            ],
            'show_recordatorio' => [
                'nombre' => 'Recordatorio de show',
                'descripcion' => 'Unos días antes del show, a los convocados que no dijeron que no van (y a quienes todavía no respondieron, para que confirmen).',
                'variables' => self::VARS_EVENTO,
                'para' => 'Alumnos convocados',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Se viene el show: {evento}',
                'mensaje' => '{fecha} {hora} · {lugar}. Si todavía no confirmaste, avisanos si vas.',
                'dias' => 2,
            ],
            'prestamo_vencido' => [
                'nombre' => 'Préstamo de instrumento vencido',
                'descripcion' => 'Cuando pasó la fecha de devolución de un instrumento prestado: a quien lo tiene y a quien lo entregó (una vez por préstamo).',
                'variables' => ['{alumno}', '{instrumento}', '{vencimiento}'],
                'para' => 'Alumnos y administración',
                'activo' => true,
                'canales' => ['interna', 'push'],
                'titulo' => 'Devolución pendiente: {instrumento}',
                'mensaje' => 'El préstamo de {instrumento} a {alumno} venció el {vencimiento}. Coordiná la devolución con la escuela.',
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
            $out[$tipo] = array_merge($def, array_intersect_key($g, array_intersect_key($def, array_flip(['activo', 'canales', 'titulo', 'mensaje', 'dias', 'minutos']))));
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
        // Una variable vacía desaparece sin dejar espacios dobles, un «·» colgado ni un espacio antes del punto.
        $render = fn (string $t) => trim((string) preg_replace(
            ['/\{[a-z_]+\}/', '/ {2,}/u', '/\s*·\s*(?=[.,]|$)/u', '/^\s*·\s*/u', '/\s+([.,])/u', '/^[\s.,]+/u'],
            ['', ' ', '', '', '$1', ''],
            strtr($t, $reemplazos),
        ));

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
            ];
            foreach (self::PLAZOS as $campo => [$min, $max]) {
                if (isset($def[$campo])) {
                    $limpio[$tipo][$campo] = max($min, min($max, (int) ($d[$campo] ?? $a[$campo])));
                }
            }
        }
        Ajuste::guardar(self::CLAVE, $limpio, $por);
    }
}
