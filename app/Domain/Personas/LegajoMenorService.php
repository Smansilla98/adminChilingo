<?php

namespace App\Domain\Personas;

use App\Domain\Notificaciones\NotificacionService;
use App\Models\Auditoria;
use App\Models\LegajoMenor;
use App\Models\Persona;
use App\Models\User;
use App\Support\ArchivoSeguro;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Legajo de menores: tutor responsable, autorización de uso de imagen y apto médico.
 * El apto se guarda en el disco privado «comprobantes» (no es público) y se sirve con ArchivoSeguro.
 */
class LegajoMenorService
{
    /** Avisar cuando al apto le falten estos días o menos (y de nuevo cada vez que cambie la fecha). */
    public const DIAS_AVISO = 30;

    public function esMenor(Persona $persona): bool
    {
        return $persona->fecha_nacimiento !== null && $persona->fecha_nacimiento->diffInYears(now()) < 18;
    }

    /** @param  array<string, mixed>  $datos */
    public function guardar(Persona $persona, array $datos, ?UploadedFile $apto, User $por): LegajoMenor
    {
        $legajo = LegajoMenor::query()->firstOrNew(['persona_id' => $persona->id]);
        $legajo->fill([
            'tutor_nombre' => $datos['tutor_nombre'] ?? null,
            'tutor_dni' => filled($datos['tutor_dni'] ?? null) ? preg_replace('/\D+/', '', (string) $datos['tutor_dni']) : null,
            'tutor_telefono' => $datos['tutor_telefono'] ?? null,
            'tutor_vinculo' => $datos['tutor_vinculo'] ?? null,
            'uso_imagen' => $datos['uso_imagen'] ?? null,
            'uso_imagen_fecha' => $datos['uso_imagen_fecha'] ?? now()->toDateString(),
        ]);

        if ($apto) {
            $ruta = 'legajos/'.$persona->id.'/apto-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.'.strtolower($apto->getClientOriginalExtension());
            Storage::disk('comprobantes')->putFileAs(dirname($ruta), $apto, basename($ruta));
            if ($legajo->apto_medico_path) {
                Storage::disk('comprobantes')->delete($legajo->apto_medico_path);
            }
            $legajo->apto_medico_path = $ruta;
            $legajo->apto_medico_nombre = mb_substr($apto->getClientOriginalName(), 0, 255);
            $legajo->apto_aviso_enviado_el = null;
        }
        if (array_key_exists('apto_medico_vence', $datos)) {
            $legajo->apto_medico_vence = $datos['apto_medico_vence'] ?: null;
            $legajo->apto_aviso_enviado_el = null;
        }
        $legajo->save();

        Auditoria::registrar('legajo.menor.guardar', $persona, null, ['por' => $por->id, 'apto' => (bool) $apto]);

        return $legajo;
    }

    /**
     * Avisa a la administración de los aptos que vencen pronto o ya vencieron. Una vez por cada
     * fecha de vencimiento (se reinicia si se carga un apto nuevo).
     *
     * @return int cantidad de avisos
     */
    public function avisarVencimientos(): int
    {
        $admins = User::query()->where('activo', true)->get()->filter(fn (User $u) => $u->isAdmin());
        $avisos = 0;

        LegajoMenor::query()
            ->whereNotNull('apto_medico_vence')
            ->where('apto_medico_vence', '<=', now()->addDays(self::DIAS_AVISO)->toDateString())
            ->whereNull('apto_aviso_enviado_el')
            ->with('persona')
            ->each(function (LegajoMenor $legajo) use ($admins, &$avisos) {
                $dias = (int) $legajo->diasParaVencerApto();
                $cuando = $dias < 0 ? 'venció el '.$legajo->apto_medico_vence->format('d/m/Y') : 'vence en '.$dias.' días ('.$legajo->apto_medico_vence->format('d/m/Y').')';
                $contenido = [
                    'tipo' => 'aviso',
                    'titulo' => 'Apto médico: '.$legajo->persona?->nombre_completo,
                    'mensaje' => 'El apto médico de '.$legajo->persona?->nombre_completo.' '.$cuando.'. Pedile a la familia el nuevo certificado y cargalo en el legajo.',
                    'enlace' => '/personas/'.$legajo->persona_id.'/legajo',
                ];
                foreach ($admins as $admin) {
                    app(NotificacionService::class)->enviar($admin, 'apto-medico:'.$legajo->persona_id.':'.$legajo->apto_medico_vence->toDateString(), $contenido, ['interna']);
                }
                $legajo->forceFill(['apto_aviso_enviado_el' => now()->toDateString()])->save();
                $avisos++;
            });

        return $avisos;
    }

    /** Reglas del formulario web y de la API. Si es menor, el tutor es obligatorio. @return array<string, mixed> */
    public function reglas(Persona $persona): array
    {
        $tutor = $this->esMenor($persona) ? 'required' : 'nullable';

        return [
            'tutor_nombre' => [$tutor, 'string', 'max:120'],
            'tutor_dni' => ['nullable', 'string', 'max:20'],
            'tutor_telefono' => [$tutor, 'string', 'max:30'],
            'tutor_vinculo' => [$tutor, 'string', 'max:40'],
            'uso_imagen' => ['nullable', 'boolean'],
            'uso_imagen_fecha' => ['nullable', 'date'],
            'apto_medico_vence' => ['nullable', 'date'],
            'apto_medico' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240', ArchivoSeguro::regla()],
        ];
    }

    public static function respuestaApto(LegajoMenor $legajo)
    {
        return ArchivoSeguro::responder('comprobantes', (string) $legajo->apto_medico_path, (string) $legajo->apto_medico_nombre, false);
    }
}
