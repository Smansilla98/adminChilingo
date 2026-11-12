<?php

namespace App\Domain\Personas;

use App\Models\Alumno;
use App\Models\Auditoria;
use App\Models\Bloque;
use App\Models\Persona;
use App\Models\Preinscripcion;
use App\Models\Sede;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Preinscripción pública: un interesado deja sus datos y queda en lista de espera. La administración
 * la aprueba (crea la Persona y el Alumno inscripto en el bloque, si tiene cupo) o la rechaza. En
 * los dos casos se avisa al interesado por correo o WhatsApp.
 */
class PreinscripcionService
{
    /** @param  array<string, mixed>  $datos */
    public function crear(array $datos): Preinscripcion
    {
        $sede = Sede::query()->where('activo', true)->findOrFail($datos['sede_id']);
        $bloqueId = $datos['bloque_id'] ?? null;
        if ($bloqueId !== null) {
            $bloque = Bloque::query()->where('activo', true)->where('sede_id', $sede->id)->find($bloqueId);
            if (! $bloque) {
                throw ValidationException::withMessages(['bloque_id' => 'Ese bloque no es de la sede elegida.']);
            }
        }

        $pre = Preinscripcion::query()->create(array_merge($datos, [
            'sede_id' => $sede->id,
            'bloque_id' => $bloqueId,
            'estado' => Preinscripcion::PENDIENTE,
        ]));
        Auditoria::registrar('preinscripcion.crear', $pre, null, ['sede_id' => $sede->id]);

        return $pre;
    }

    /**
     * @return Alumno el alumno inscripto
     */
    public function aprobar(Preinscripcion $pre, User $por, ?int $bloqueId = null): Alumno
    {
        if ($pre->estado !== Preinscripcion::PENDIENTE) {
            throw ValidationException::withMessages(['estado' => 'Esta preinscripción ya fue resuelta.']);
        }
        $bloqueId ??= $pre->bloque_id;
        $bloque = $bloqueId ? Bloque::query()->where('sede_id', $pre->sede_id)->where('activo', true)->find($bloqueId) : null;
        if ($bloqueId && ! $bloque) {
            throw ValidationException::withMessages(['bloque_id' => 'El bloque no es de la sede o no está activo.']);
        }
        if ($bloque && ! $bloque->tieneCuposDisponibles()) {
            throw ValidationException::withMessages(['bloque_id' => 'El bloque no tiene cupo. Dejala en lista de espera o elegí otro bloque.']);
        }

        $alumno = DB::transaction(function () use ($pre, $por, $bloque) {
            $dni = $pre->dni ? RegistroAlumnoService::soloNumeros($pre->dni) : null;
            $persona = ($dni ? Persona::query()->where('dni', $dni)->first() : null)
                ?? Persona::query()->create([
                    'nombre' => $pre->nombre,
                    'apellido' => $pre->apellido,
                    'dni' => $dni,
                    'fecha_nacimiento' => $pre->fecha_nacimiento->toDateString(),
                    'telefono' => $pre->telefono,
                    'email' => $pre->email,
                    'estado' => 'activo',
                ]);

            $alumno = Alumno::query()->create([
                'persona_id' => $persona->id,
                'nombre_apellido' => trim($pre->nombre.' '.$pre->apellido),
                'dni' => $dni,
                'fecha_nacimiento' => $pre->fecha_nacimiento->toDateString(),
                'telefono' => $pre->telefono,
                'sede_id' => $pre->sede_id,
                'bloque_id' => $bloque?->id,
                'activo' => true,
            ]);
            if ($bloque) {
                $alumno->bloques()->syncWithoutDetaching([$bloque->id => ['es_principal' => true]]);
            }

            $pre->forceFill([
                'estado' => Preinscripcion::APROBADA,
                'alumno_id' => $alumno->id,
                'resuelto_por' => $por->id,
                'resuelto_at' => now(),
            ])->save();
            Auditoria::registrar('preinscripcion.aprobar', $pre, null, ['alumno_id' => $alumno->id]);

            return $alumno;
        });

        $this->avisar($pre, 'Hola '.$pre->nombre.' ¡Tu inscripción en La Chilinga fue aprobada! Te esperamos. Si tenés dudas, escribí a la sede.');

        return $alumno;
    }

    public function rechazar(Preinscripcion $pre, User $por, string $motivo): void
    {
        if ($pre->estado !== Preinscripcion::PENDIENTE) {
            throw ValidationException::withMessages(['estado' => 'Esta preinscripción ya fue resuelta.']);
        }
        $pre->forceFill([
            'estado' => Preinscripcion::RECHAZADA,
            'motivo_rechazo' => mb_substr($motivo, 0, 255),
            'resuelto_por' => $por->id,
            'resuelto_at' => now(),
        ])->save();
        Auditoria::registrar('preinscripcion.rechazar', $pre, null, ['motivo' => $motivo]);

        $this->avisar($pre, 'Hola '.$pre->nombre.'. Por ahora no pudimos confirmar tu inscripción en La Chilinga'.($motivo !== '' ? ': '.$motivo : '.').' Si querés, escribinos y lo charlamos.');
    }

    /** Correo si la persona dejó uno; si no, WhatsApp (si Twilio está configurado). */
    public function avisar(Preinscripcion $pre, string $texto): ?string
    {
        if ($pre->email) {
            Mail::raw($texto, fn ($m) => $m->to($pre->email)->subject('La Chilinga: tu preinscripción'));

            return 'email';
        }
        $whatsapp = app(WhatsAppService::class);
        if ($whatsapp->isConfigured() && $pre->telefonoContacto()) {
            $whatsapp->send($texto, (string) $pre->telefonoContacto(), ['tipo' => 'preinscripcion']);

            return 'whatsapp';
        }

        return null;
    }
}
