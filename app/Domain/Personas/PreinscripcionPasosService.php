<?php

namespace App\Domain\Personas;

use App\Domain\Notificaciones\NotificacionService;
use App\Models\Auditoria;
use App\Models\Bloque;
use App\Models\Preinscripcion;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Preinscripción por pasos, como el formulario de Patuka pero en cuatro pasos:
 *  1. sede, bloque y nombre
 *  2. datos personales (DNI, fecha de nacimiento)
 *  3. contacto (teléfono, correo y, si es menor, el tutor)
 *  4. confirmación: queda en lista de espera
 *
 * Cada paso completado avisa al equipo (bandeja interna y correo a quien tenga permiso de alumnos).
 * La clave de cada aviso incluye el paso, así que un mismo paso nunca avisa dos veces (lo deduplica
 * NotificacionService).
 */
class PreinscripcionPasosService
{
    /** Título y tipo de aviso por paso. */
    private const TITULOS = [
        1 => 'Preinscripción en curso',
        2 => 'Preinscripción: datos personales',
        3 => 'Preinscripción: contacto',
        4 => 'Preinscripción completa',
    ];

    /** @param  array{sede_id: int, bloque_id?: int|null, nombre: string, apellido: string}  $datos */
    public function iniciar(array $datos): Preinscripcion
    {
        $sede = Sede::query()->where('activo', true)->findOrFail($datos['sede_id']);
        $bloqueId = $datos['bloque_id'] ?? null;
        if ($bloqueId !== null && ! Bloque::query()->where('activo', true)->where('sede_id', $sede->id)->whereKey($bloqueId)->exists()) {
            throw ValidationException::withMessages(['bloque_id' => 'Ese bloque no es de la sede elegida.']);
        }

        $pre = Preinscripcion::query()->create([
            'token' => Str::random(48),
            'paso' => 1,
            'nombre' => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'sede_id' => $sede->id,
            'bloque_id' => $bloqueId,
            'estado' => Preinscripcion::EN_CURSO,
        ]);
        Auditoria::registrar('preinscripcion.paso', $pre, null, ['paso' => 1]);
        $this->notificarPaso($pre, 1);

        return $pre;
    }

    /**
     * Guarda el paso indicado. Tiene que ser el siguiente al último completado: no se puede saltar.
     *
     * @param  array<string, mixed>  $datos  ya validados para ese paso
     */
    public function completarPaso(Preinscripcion $pre, int $paso, array $datos): Preinscripcion
    {
        if ($pre->estado !== Preinscripcion::EN_CURSO || $paso !== $pre->paso + 1) {
            throw ValidationException::withMessages(['paso' => 'Ese paso ya está completo o todavía no corresponde.']);
        }

        $pre->fill($datos);
        $pre->paso = $paso;
        if ($paso === Preinscripcion::PASOS) {
            $pre->estado = Preinscripcion::PENDIENTE;
        }
        $pre->save();
        Auditoria::registrar('preinscripcion.paso', $pre, null, ['paso' => $paso]);
        $this->notificarPaso($pre, $paso);

        return $pre;
    }

    /**
     * Avisa al equipo lo que la persona lleva cargado. Devuelve cuántos destinatarios recibieron el aviso.
     */
    public function notificarPaso(Preinscripcion $pre, int $paso): int
    {
        $contenido = [
            'tipo' => 'aviso',
            'titulo' => self::TITULOS[$paso].': '.$pre->nombre.' '.$pre->apellido,
            'mensaje' => implode("\n", $this->lineas($pre)),
            'enlace' => '/preinscripciones/'.$pre->id,
        ];

        $enviados = 0;
        foreach ($this->equipo() as $user) {
            $resultado = app(NotificacionService::class)->enviar(
                $user,
                'preinscripcion:'.$pre->id.':paso:'.$paso,
                $contenido,
                ['interna', 'mail'],
            );
            if (($resultado['interna'] ?? null) === 'enviado') {
                $enviados++;
            }
        }

        return $enviados;
    }

    /** Lo que va cargado hasta ahora, en líneas (sin datos vacíos). @return list<string> */
    public function lineas(Preinscripcion $pre): array
    {
        $lineas = [
            'Sede: '.($pre->sede->nombre ?? '—'),
            'Bloque: '.($pre->bloque->nombre ?? 'Sin preferencia'),
            'Nombre: '.$pre->nombre.' '.$pre->apellido,
        ];
        if ($pre->paso >= 2 || $pre->dni || $pre->fecha_nacimiento) {
            $lineas[] = 'DNI: '.($pre->dni ?: 'no informado');
            $lineas[] = 'Nacimiento: '.($pre->fecha_nacimiento?->format('d/m/Y') ?? 'no informado').($pre->esMenor() ? ' (menor de 18)' : '');
        }
        if ($pre->telefono || $pre->email) {
            $lineas[] = 'Teléfono: '.($pre->telefono ?: 'no informado');
            $lineas[] = 'Correo: '.($pre->email ?: 'no informado');
        }
        if ($pre->tutor_nombre) {
            $lineas[] = 'Tutor: '.$pre->tutor_nombre.' ('.$pre->tutor_vinculo.') · '.$pre->tutor_telefono.($pre->tutor_dni ? ' · DNI '.$pre->tutor_dni : '');
        }
        if ($pre->notas) {
            $lineas[] = 'Comentario: '.$pre->notas;
        }
        if ($pre->estado === Preinscripcion::PENDIENTE) {
            $lineas[] = 'Queda en lista de espera: revisala en Preinscripciones.';
        }

        return $lineas;
    }

    /** Quienes reciben los avisos: cuentas activas con permiso para inscribir alumnos. @return list<User> */
    private function equipo(): array
    {
        return User::query()->where('activo', true)->get()
            ->filter(fn (User $u) => $u->acceso()->puede('alumnos.create'))
            ->values()->all();
    }
}
