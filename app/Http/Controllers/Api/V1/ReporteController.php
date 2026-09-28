<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reportes\ReportesService;
use App\Exports\ReportesExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reportes de gestión. Los cálculos son los del panel web (ReportesService); la app
 * los muestra, descarga el Excel que genera el servidor y arma el PDF a partir del
 * mismo HTML imprimible que usa la web.
 */
class ReporteController extends Controller
{
    public function index(Request $request, ReportesService $reportes): JsonResponse
    {
        [$mes, $anio] = $this->periodo($request);
        $d = $reportes->compilar($mes, $anio, $request->user());
        $porBloque = $d['ingresosPorBloque'];

        return response()->json([
            'mes' => $mes,
            'anio' => $anio,
            'anios_disponibles' => collect($d['añosDisponibles'])->map(fn ($a) => (int) $a)->values(),
            'alcance' => $d['sedeScope'] === null ? 'global' : 'sedes',
            'global' => [
                'ingresos' => round((float) $d['ingresosTotales'], 2),
                'gastos' => round((float) $d['gastosTotales'], 2),
                'resultado' => round((float) $d['resultadoGlobal'], 2),
            ],
            'financiero_sede' => collect($d['resumenFinanciero'])->map(fn ($r) => [
                'sede' => ['id' => $r['sede']->id, 'nombre' => $r['sede']->nombre],
                'ingresos' => round((float) $r['ingresos'], 2),
                'gastos' => round((float) $r['total_gastos'], 2),
                'gastos_detalle' => collect($r['gastos_detalle'])->map(fn ($v) => round((float) $v, 2)),
                'resultado' => round((float) $r['resultado'], 2),
            ])->values(),
            'ingresos_profesor' => collect($d['ingresosPorProfesor'])->map(fn ($r) => [
                'profesor' => ['id' => $r['profesor']->id, 'nombre' => $r['profesor']->nombre],
                'alumnos' => $r['alumnos_count'],
                'emitido' => round((float) $r['emitido'], 2),
                'cobrado' => round((float) $r['cobrado'], 2),
                'porcentaje_cobrado' => $r['porcentaje_cobrado'],
            ])->values(),
            'actividad_profesor' => collect($d['actividadPorProfesor'])->map(fn ($r) => [
                'profesor' => ['id' => $r['profesor_id'] ?? null, 'nombre' => $r['profesor_nombre']],
                'clases_dictadas' => $r['clases_dictadas'],
                'promedio_presentes' => $r['alumnos_promedio_presentes'] !== null ? round((float) $r['alumnos_promedio_presentes'], 1) : null,
                'ultimo_bloque' => $r['ultimo_bloque']?->nombre,
                'ultima_fecha' => $r['ultima_fecha'],
            ])->values(),
            'alumnos_bloque' => collect($d['alumnosPorBloque'])->map(fn ($r) => [
                'bloque' => ['id' => $r['bloque']->id, 'nombre' => $r['bloque']->nombre],
                'sede' => $r['sede']?->nombre,
                'profesor' => $r['profesor']?->nombre,
                'alumnos' => $r['alumnos_count'],
                'ingresos' => round((float) ($porBloque[$r['bloque']->id] ?? 0), 2),
            ])->values(),
        ]);
    }

    /** Excel generado por el servidor (mismo archivo que el panel web). */
    public function excel(Request $request, ReportesService $reportes): BinaryFileResponse
    {
        [$mes, $anio] = $this->periodo($request);
        $datos = $reportes->compilar($mes, $anio, $request->user());

        return Excel::download(new ReportesExport($reportes->payloadExcel($datos, $mes, $anio)), sprintf('reportes-%04d-%02d.xlsx', $anio, $mes));
    }

    /** HTML imprimible del período (la app lo convierte en PDF en el teléfono). */
    public function imprimible(Request $request, ReportesService $reportes): JsonResponse
    {
        [$mes, $anio] = $this->periodo($request);
        $datos = $reportes->compilar($mes, $anio, $request->user());

        return response()->json([
            'nombre' => sprintf('reportes-%04d-%02d.pdf', $anio, $mes),
            'html' => view('reportes.pdf', $datos)->render(),
        ]);
    }

    public function profesores(Request $request, ReportesService $reportes): JsonResponse
    {
        return response()->json(['data' => $reportes->alumnosPorProfesor($request->user())->map(fn ($r) => [
            'profesor' => ['id' => $r['profesor']->id, 'nombre' => $r['profesor']->nombre],
            'sedes' => $r['sedes']->values(),
            'bloques' => $r['bloques_count'],
            'alumnos' => $r['alumnos_count'],
        ])->values()]);
    }

    /** @return array{0: int, 1: int} */
    private function periodo(Request $request): array
    {
        $data = $request->validate(['mes' => 'nullable|integer|min:1|max:12', 'anio' => 'nullable|integer|min:2000|max:2100']);

        return [(int) ($data['mes'] ?? now()->month), (int) ($data['anio'] ?? now()->year)];
    }
}
