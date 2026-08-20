<?php

namespace App\Services;

use App\Models\VillaGesellConfig;
use App\Models\VillaGesellDia;
use App\Models\VillaGesellGasto;
use App\Models\VillaGesellInscripto;
use App\Models\VillaGesellInsumo;
use App\Support\VillaGesellCalculo;

class VillaGesellGiraService
{
    public function config(): VillaGesellConfig
    {
        $row = VillaGesellConfig::query()->first();
        if ($row) {
            return $row;
        }

        return VillaGesellConfig::query()->create([
            'fecha_inicio' => '2027-01-16',
            'fecha_fin' => '2027-02-14',
            'cupo_maximo' => 40,
            'aporte_esperado' => 0,
        ]);
    }

    public function asegurarDias(): int
    {
        $config = $this->config();
        $creados = 0;
        foreach ($config->rangoFechas() as $fecha) {
            $dia = VillaGesellDia::query()->firstOrCreate(
                ['fecha' => $fecha->toDateString()],
                ['notas' => null]
            );
            if ($dia->wasRecentlyCreated) {
                $creados++;
            }
        }

        return $creados;
    }

    /**
     * @return array{
     *     dias:int,
     *     cupo:int,
     *     plazas_ocupadas:int,
     *     lista_espera:int,
     *     valor_por_dia:float,
     *     ingresos_pagados:float,
     *     ingresos_esperados:float,
     *     ingresos_si_cupo_lleno:float,
     *     gastos_por_tipo:array<string,float>,
     *     gastos_totales:float,
     *     insumos_totales:float,
     *     balance_pagado:float,
     *     balance_esperado:float,
     *     balance_cupo_lleno:float
     * }
     */
    public function plan(): array
    {
        $config = $this->config();
        $dias = $config->cantidadDias();
        $valorPorDia = $config->valorPorDia();
        $gastos = VillaGesellGasto::query()->get();
        $porTipo = [];
        foreach (array_keys(VillaGesellGasto::TIPOS) as $tipo) {
            $porTipo[$tipo] = 0.0;
        }
        $gastosTotales = 0.0;
        foreach ($gastos as $gasto) {
            $proy = $gasto->proyectado($dias);
            $porTipo[$gasto->tipo] = ($porTipo[$gasto->tipo] ?? 0) + $proy;
            $gastosTotales += $proy;
        }

        $insumosTotales = (float) VillaGesellInsumo::query()->get()->sum(fn (VillaGesellInsumo $i) => $i->costoTotal());

        $pagados = (float) VillaGesellInscripto::query()->sum('monto_pagado');
        $esperados = (float) VillaGesellInscripto::query()->get()->sum(
            fn (VillaGesellInscripto $i) => (float) $i->monto_esperado
        );
        $cupoLleno = VillaGesellCalculo::porDiaPorDias($valorPorDia, $dias) * (int) $config->cupo_maximo;
        $plazas = (int) VillaGesellInscripto::query()->whereNotNull('plaza')->where('lista_espera', false)->count();
        $espera = (int) VillaGesellInscripto::query()->where('lista_espera', true)->count();

        $egresos = $gastosTotales + $insumosTotales;

        return [
            'dias' => $dias,
            'cupo' => (int) $config->cupo_maximo,
            'plazas_ocupadas' => $plazas,
            'lista_espera' => $espera,
            'valor_por_dia' => $valorPorDia,
            'ingresos_pagados' => $pagados,
            'ingresos_esperados' => $esperados,
            'ingresos_si_cupo_lleno' => $cupoLleno,
            'gastos_por_tipo' => $porTipo,
            'gastos_totales' => $gastosTotales,
            'insumos_totales' => $insumosTotales,
            'balance_pagado' => $pagados - $egresos,
            'balance_esperado' => $esperados - $egresos,
            'balance_cupo_lleno' => $cupoLleno - $egresos,
        ];
    }

    public function plazasOcupadas(): array
    {
        return VillaGesellInscripto::query()
            ->whereNotNull('plaza')
            ->pluck('plaza')
            ->map(fn ($p) => (int) $p)
            ->all();
    }

    public function plazaDisponible(?int $exceptId = null): ?int
    {
        $config = $this->config();
        $ocupadas = VillaGesellInscripto::query()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereNotNull('plaza')
            ->pluck('plaza')
            ->map(fn ($p) => (int) $p)
            ->all();

        for ($i = 1; $i <= $config->cupo_maximo; $i++) {
            if (! in_array($i, $ocupadas, true)) {
                return $i;
            }
        }

        return null;
    }

    public function validarPlaza(?int $plaza, ?int $exceptId): void
    {
        if ($plaza === null) {
            return;
        }
        $max = $this->config()->cupo_maximo;
        if ($plaza < 1 || $plaza > $max) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'plaza' => "La plaza tiene que estar entre 1 y {$max} (cupo actual).",
            ]);
        }
        $taken = VillaGesellInscripto::query()
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->where('plaza', $plaza)
            ->exists();
        if ($taken) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'plaza' => "La plaza {$plaza} ya está ocupada.",
            ]);
        }
    }
}
