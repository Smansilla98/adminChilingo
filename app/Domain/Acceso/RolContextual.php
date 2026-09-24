<?php

namespace App\Domain\Acceso;

/**
 * Un rol que una persona ejerce en un ámbito concreto, y de dónde surge.
 */
final class RolContextual
{
    public const ORIGEN_ASIGNACION = 'asignacion';

    public const ORIGEN_DOCENTE = 'docente';        // bloque_profesor / titular

    public const ORIGEN_SEDE = 'sede';              // profesor_sede / sedes.coordinador_id

    public const ORIGEN_AREA = 'area';              // coordinador_area

    public const ORIGEN_ALUMNO = 'alumno';          // inscripción del alumno

    public const ORIGEN_BECA = 'beca';

    public const ORIGEN_LEGACY = 'legacy';          // users.role / rol Spatie heredado

    public function __construct(
        public readonly string $rol,
        public readonly string $ambito,          // global | sede | bloque
        public readonly ?int $sedeId = null,
        public readonly ?int $bloqueId = null,
        public readonly string $origen = self::ORIGEN_ASIGNACION,
        public readonly ?int $asignacionId = null,
    ) {}

    public function clave(): string
    {
        return implode(':', [$this->rol, $this->ambito, $this->sedeId ?? 0, $this->bloqueId ?? 0]);
    }

    public function esEditable(): bool
    {
        return $this->origen === self::ORIGEN_ASIGNACION;
    }

    public static function etiquetaOrigen(string $origen): string
    {
        return match ($origen) {
            self::ORIGEN_ASIGNACION => 'Asignado',
            self::ORIGEN_DOCENTE => 'Ficha docente (bloque)',
            self::ORIGEN_SEDE => 'Ficha docente (sede)',
            self::ORIGEN_AREA => 'Coordinación de área',
            self::ORIGEN_ALUMNO => 'Inscripción',
            self::ORIGEN_BECA => 'Beca activa',
            self::ORIGEN_LEGACY => 'Rol heredado',
            default => $origen,
        };
    }
}
