<?php

namespace App\Domain\Personas;

use App\Models\Auditoria;
use App\Models\CodigoAlta;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Código de alta: 6 caracteres que la escuela (administración o el profe) le pasa a un
 * alumno para que cree su cuenta. Vence en 7 días, sirve una vez y generar uno nuevo
 * anula el anterior. Se guarda solo el hash: se muestra una única vez.
 */
class CodigoAltaService
{
    public const DIAS_VIGENCIA = 7;

    /** Sin 0/O, 1/I/L: se dictan por teléfono sin confusiones. */
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function puedeGenerar(User $por, Persona $persona): bool
    {
        if ($persona->user()->exists()) {
            return false;
        }
        // El profe ve a sus alumnos (AlumnoPolicy) aunque no tenga permiso sobre personas.
        $alumnos = $persona->alumnos()->where('activo', true)->get();

        return $alumnos->contains(fn ($a) => $por->can('view', $a))
            && $por->acceso()->puedeAlguno(['usuarios.create', 'alumnos.update', 'asistencias.create']);
    }

    /** @return array{codigo: string, vence: string} */
    public function generar(Persona $persona, User $por): array
    {
        if ($persona->user()->exists()) {
            throw ValidationException::withMessages(['persona' => 'Ya tiene cuenta: si no puede entrar, dale una contraseña provisoria.']);
        }
        if (! $this->puedeGenerar($por, $persona)) {
            throw new AuthorizationException('No podés generar un código de alta para esta persona.');
        }

        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
        }

        CodigoAlta::query()->where('persona_id', $persona->id)->whereNull('usado_at')->delete();
        $registro = CodigoAlta::query()->create([
            'persona_id' => $persona->id,
            'codigo_hash' => self::hash($codigo),
            'vence_at' => now()->addDays(self::DIAS_VIGENCIA),
            'creado_por' => $por->id,
        ]);
        Auditoria::registrar('acceso.codigo_alta', $persona, null, ['por' => $por->id]);

        return ['codigo' => $codigo, 'vence' => $registro->vence_at->toIso8601String()];
    }

    /** Código vigente de la persona que coincide con el escrito (o null). */
    public function vigente(Persona $persona, string $codigo): ?CodigoAlta
    {
        $codigo = self::normalizar($codigo);
        if (strlen($codigo) !== 6) {
            return null;
        }

        return CodigoAlta::query()
            ->where('persona_id', $persona->id)
            ->whereNull('usado_at')
            ->where('vence_at', '>', now())
            ->get()
            ->first(fn (CodigoAlta $c) => hash_equals($c->codigo_hash, self::hash($codigo)));
    }

    public function marcarUsado(CodigoAlta $codigo): void
    {
        $codigo->forceFill(['usado_at' => now()])->save();
    }

    public static function normalizar(string $codigo): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    }

    private static function hash(string $codigo): string
    {
        return hash_hmac('sha256', self::normalizar($codigo), (string) config('app.key'));
    }
}
