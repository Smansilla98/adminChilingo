<?php

namespace App\Domain\VillaGesell;

use App\Models\VillaGesellConfig;
use App\Models\VillaGesellGasto;
use App\Models\VillaGesellInscripto;
use App\Models\VillaGesellInsumo;
use App\Models\VillaGesellTocada;

/**
 * Forma en que la API de la gira devuelve cada entidad (claves y formatos del contrato JSON).
 */
class GiraPresentador
{
    /** @return array<string, mixed> */
    public function inscripto(VillaGesellInscripto $inscripto): array
    {
        $inscripto->loadMissing('alumno.sede');

        return [
            'id' => $inscripto->id,
            'alumno' => $inscripto->alumno ? ['id' => $inscripto->alumno->id, 'nombre' => $inscripto->alumno->nombre_apellido, 'sede' => $inscripto->alumno->sede?->nombre, 'persona_id' => $inscripto->alumno->persona_id] : null,
            'estado_pago' => $inscripto->estado_pago,
            'estado_pago_nombre' => $inscripto->etiquetaPago(),
            'monto_esperado' => (float) $inscripto->monto_esperado,
            'monto_pagado' => (float) $inscripto->monto_pagado,
            'saldo' => round($inscripto->saldo(), 2),
            'plaza' => $inscripto->plaza,
            'lista_espera' => (bool) $inscripto->lista_espera,
            'fecha_desde' => $inscripto->fecha_desde?->toDateString(),
            'fecha_hasta' => $inscripto->fecha_hasta?->toDateString(),
            'dias' => $inscripto->diasUtilizados(),
            'talle_remera' => $inscripto->talle_remera,
            'tambor_principal' => $inscripto->tambor_principal,
            'tambor_secundario' => $inscripto->tambor_secundario,
            'tambor_terciario' => $inscripto->tambor_terciario,
            'tambor_principal_origen' => $inscripto->tambor_principal_origen,
            'tambor_secundario_origen' => $inscripto->tambor_secundario_origen,
            'tambor_terciario_origen' => $inscripto->tambor_terciario_origen,
            'notas' => $inscripto->notas,
        ];
    }

    /** @return array<string, mixed> */
    public function config(VillaGesellConfig $c): array
    {
        return [
            'fecha_inicio' => $c->fecha_inicio?->toDateString(),
            'fecha_fin' => $c->fecha_fin?->toDateString(),
            'cupo_maximo' => (int) $c->cupo_maximo,
            'aporte_esperado' => (float) $c->aporte_esperado,
            'notas' => $c->notas,
            'dias' => $c->cantidadDias(),
            'valor_por_dia' => $c->valorPorDia(),
        ];
    }

    /** @return array<string, mixed> */
    public function tocada(VillaGesellTocada $t): array
    {
        return ['id' => $t->id, 'dia_id' => $t->dia_id, 'orden' => $t->orden, 'hora' => $t->hora ? substr((string) $t->hora, 0, 5) : null, 'que' => $t->que, 'donde' => $t->donde, 'notas' => $t->notas];
    }

    /** @return array<string, mixed> */
    public function gasto(VillaGesellGasto $g, int $dias): array
    {
        return [
            'id' => $g->id, 'tipo' => $g->tipo, 'tipo_nombre' => VillaGesellGasto::TIPOS[$g->tipo] ?? $g->tipo, 'concepto' => $g->concepto,
            'monto' => (float) $g->monto, 'modo' => $g->modo, 'fecha' => $g->fecha?->toDateString(), 'notas' => $g->notas,
            'proyectado' => round($g->proyectado($dias), 2),
        ];
    }

    /** @return array<string, mixed> */
    public function insumo(VillaGesellInsumo $i): array
    {
        return [
            'id' => $i->id, 'nombre' => $i->nombre, 'categoria' => $i->categoria, 'categoria_nombre' => VillaGesellInsumo::CATEGORIAS[$i->categoria] ?? $i->categoria,
            'cantidad' => (float) $i->cantidad, 'unidad' => $i->unidad, 'costo_unitario' => (float) $i->costo_unitario, 'costo_total' => round($i->costoTotal(), 2), 'notas' => $i->notas,
        ];
    }
}
