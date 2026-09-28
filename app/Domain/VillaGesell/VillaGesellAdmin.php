<?php

namespace App\Domain\VillaGesell;

use App\Domain\Personas\PersonaService;
use App\Models\Alumno;
use App\Models\Bloque;
use App\Models\Profesor;
use App\Models\Sede;
use App\Models\VillaGesellConfig;
use App\Models\VillaGesellDia;
use App\Models\VillaGesellGasto;
use App\Models\VillaGesellInscripto;
use App\Models\VillaGesellInsumo;
use App\Models\VillaGesellTocada;
use App\Services\VillaGesellGiraService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Administración de la gira a Villa Gesell (web y API): datos de la gira, inscriptos
 * con plaza o lista de espera, calendario de tocadas, gastos, insumos y altas rápidas.
 * Los cálculos (plan, plazas) están en VillaGesellGiraService.
 */
class VillaGesellAdmin
{
    public function __construct(private VillaGesellGiraService $gira, private PersonaService $personas) {}

    /** @return array<string, mixed> */
    public function reglasConfig(): array
    {
        return [
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'cupo_maximo' => ['required', 'integer', 'min:1', 'max:500'],
            'aporte_esperado' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarConfig(array $datos): VillaGesellConfig
    {
        $plazas = $this->gira->plazasOcupadas();
        $maxPlaza = $plazas === [] ? 0 : max($plazas);
        if ((int) $datos['cupo_maximo'] < $maxPlaza) {
            throw ValidationException::withMessages(['cupo_maximo' => "Hay una plaza ocupada n.º {$maxPlaza}. Bajá esa plaza antes de reducir el cupo."]);
        }
        $config = $this->gira->config();
        $config->fill($datos)->save();
        $this->gira->asegurarDias();

        return $config;
    }

    /** @return array<string, mixed> */
    public function reglasInscripcion(?int $exceptoId = null): array
    {
        $config = $this->gira->config();

        return [
            'alumno_id' => ['required', 'exists:alumnos,id', Rule::unique('villa_gesell_inscriptos', 'alumno_id')->ignore($exceptoId)],
            'estado_pago' => ['required', Rule::in(array_keys(VillaGesellInscripto::ESTADOS_PAGO))],
            'monto_esperado' => ['required', 'numeric', 'min:0'],
            'monto_pagado' => ['required', 'numeric', 'min:0'],
            'plaza' => ['nullable', 'integer', 'min:1', 'max:'.$config->cupo_maximo],
            'lista_espera' => ['sometimes', 'boolean'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
            'talle_remera' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::TALLES))],
            'tambor_principal' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_secundario' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_terciario' => ['nullable', Rule::in(VillaGesellInscripto::TAMBORES)],
            'tambor_principal_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'tambor_secundario_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'tambor_terciario_origen' => ['nullable', Rule::in(array_keys(VillaGesellInscripto::ORIGENES_TAMBOR))],
            'notas' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * Crea o actualiza una inscripción. En lista de espera no ocupa plaza; con
     * `calcularAporte` el monto esperado sale de los días elegidos.
     *
     * @param  array<string, mixed>  $datos  validados con reglasInscripcion()
     */
    public function guardarInscripcion(?VillaGesellInscripto $inscripto, array $datos, bool $listaEspera, bool $calcularAporte): VillaGesellInscripto
    {
        $datos['lista_espera'] = $listaEspera;
        $datos['plaza'] = isset($datos['plaza']) && $datos['plaza'] !== null ? (int) $datos['plaza'] : null;
        if ($calcularAporte) {
            $tmp = new VillaGesellInscripto(['fecha_desde' => $datos['fecha_desde'] ?? null, 'fecha_hasta' => $datos['fecha_hasta'] ?? null]);
            $datos['monto_esperado'] = $tmp->aporteSegunDias($this->gira->config()->valorPorDia());
        }
        $this->gira->validarPlaza($listaEspera ? null : $datos['plaza'], $inscripto?->id);
        if ($listaEspera) {
            $datos['plaza'] = null;
        }
        if ($inscripto) {
            $inscripto->update($datos);

            return $inscripto;
        }

        return VillaGesellInscripto::query()->create($datos);
    }

    /** Valores sugeridos para una inscripción nueva (plaza libre y aporte por días). */
    public function nuevaInscripcion(): VillaGesellInscripto
    {
        $config = $this->gira->config();
        $i = new VillaGesellInscripto([
            'estado_pago' => 'pendiente',
            'fecha_desde' => $config->fecha_inicio,
            'fecha_hasta' => $config->fecha_fin,
            'plaza' => $this->gira->plazaDisponible(),
        ]);
        $i->monto_esperado = $i->aporteSegunDias($config->valorPorDia());

        return $i;
    }

    /** @return array<string, mixed> */
    public function reglasTocada(): array
    {
        return [
            'orden' => ['nullable', 'integer', 'min:1', 'max:99'],
            'hora' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'que' => ['required', 'string', 'max:160'],
            'donde' => ['nullable', 'string', 'max:160'],
            'notas' => ['nullable', 'string', 'max:400'],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function agregarTocada(VillaGesellDia $dia, array $datos): VillaGesellTocada
    {
        $datos['dia_id'] = $dia->id;
        $datos['orden'] = ! empty($datos['orden']) ? (int) $datos['orden'] : ((int) $dia->tocadas()->max('orden') + 1);

        return VillaGesellTocada::query()->create($datos);
    }

    /** Fechas "por definir" para completar después. */
    public function generarTocadas(VillaGesellDia $dia, int $cantidad): int
    {
        $orden = (int) $dia->tocadas()->max('orden') + 1;
        for ($i = 0; $i < $cantidad; $i++) {
            VillaGesellTocada::query()->create(['dia_id' => $dia->id, 'orden' => $orden + $i, 'hora' => null, 'que' => 'Por definir', 'donde' => null]);
        }

        return $cantidad;
    }

    /** @return array<string, mixed> */
    public function reglasInsumo(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:160'],
            'categoria' => ['required', Rule::in(array_keys(VillaGesellInsumo::CATEGORIAS))],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'unidad' => ['nullable', 'string', 'max:20'],
            'costo_unitario' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public function reglasGasto(): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(VillaGesellGasto::TIPOS))],
            'concepto' => ['required', 'string', 'max:160'],
            'monto' => ['required', 'numeric', 'min:0'],
            'modo' => ['required', Rule::in(array_keys(VillaGesellGasto::MODOS))],
            'fecha' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Los gastos diarios siempre se multiplican por la cantidad de días.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function normalizarGasto(array $datos): array
    {
        if ($datos['tipo'] === 'diario') {
            $datos['modo'] = 'por_dia';
        }

        return $datos;
    }

    /** @return array<string, mixed> */
    public function reglasAlumnoRapido(): array
    {
        return [
            'nombre_apellido' => ['required', 'string', 'max:255'],
            'dni' => ['nullable', 'string', 'max:20', 'unique:alumnos,dni'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'sede_id' => ['nullable', 'exists:sedes,id'],
            'bloque_id' => ['nullable', 'exists:bloques,id'],
            'profesor_id' => ['nullable', 'exists:profesores,id'],
            'instrumento_principal' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * Alta rápida al padrón para inscribir a la gira a alguien que no estaba cargado.
     *
     * @param  array<string, mixed>  $datos
     * @return array{alumno: Alumno, bloque: ?Bloque, profesor: ?string}
     */
    public function altaRapidaAlumno(array $datos): array
    {
        if ($existente = $this->personas->alumnoPorDni($datos['dni'] ?? null)) {
            throw ValidationException::withMessages(['dni' => "Ese DNI ya está cargado en el padrón ({$existente->nombre_apellido})."]);
        }
        $bloque = ! empty($datos['bloque_id']) ? Bloque::query()->with('profesor')->find((int) $datos['bloque_id']) : null;
        if ($bloque && empty($datos['sede_id']) && $bloque->sede_id) {
            $datos['sede_id'] = $bloque->sede_id;
        }
        // Si eligió profesor pero no bloque, se toma un bloque activo de ese profe.
        if (! $bloque && ! empty($datos['profesor_id'])) {
            $bloque = Bloque::query()->where('activo', true)->where('profesor_id', (int) $datos['profesor_id'])->orderBy('nombre')->first();
            if ($bloque && empty($datos['sede_id']) && $bloque->sede_id) {
                $datos['sede_id'] = $bloque->sede_id;
            }
        }

        $alumno = Alumno::query()->create([
            'nombre_apellido' => trim($datos['nombre_apellido']),
            'dni' => $datos['dni'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'fecha_nacimiento' => $datos['fecha_nacimiento'] ?? null,
            'sede_id' => $datos['sede_id'] ?? null,
            'bloque_id' => $bloque?->id,
            'instrumento_principal' => ($datos['instrumento_principal'] ?? null) ?: 'Otro',
            'activo' => true,
        ]);
        if ($bloque && Schema::hasTable('alumno_bloque')) {
            $alumno->bloques()->sync([$bloque->id => ['es_principal' => true]]);
        }
        $profesor = $bloque?->profesor?->nombre ?? (! empty($datos['profesor_id']) ? Profesor::query()->find((int) $datos['profesor_id'])?->nombre : null);

        return ['alumno' => $alumno, 'bloque' => $bloque, 'profesor' => $profesor];
    }

    public function altaRapidaProfesor(string $nombre, ?string $telefono): Profesor
    {
        return Profesor::query()->create(['nombre' => trim($nombre), 'telefono' => filled($telefono) ? trim((string) $telefono) : null, 'activo' => true]);
    }

    public function altaRapidaBloque(string $nombre, ?int $sedeId, ?int $profesorId): Bloque
    {
        $sedeId ??= Sede::query()->where('activo', true)->orderBy('nombre')->value('id');
        if (! $sedeId) {
            throw ValidationException::withMessages(['sede_id' => 'Necesitás al menos una sede activa para crear un bloque. Cargala en Sedes y volvé.']);
        }
        $bloque = Bloque::query()->create(['nombre' => trim($nombre), 'año' => 1, 'sede_id' => $sedeId, 'profesor_id' => $profesorId, 'cantidad_max_alumnos' => 40, 'activo' => true]);
        $bloque->syncProfesorTitularEnPivot();

        return $bloque->load(['sede', 'profesor']);
    }
}
