<?php

namespace App\Http\Controllers;

use App\Domain\Reportes\ReportesService;
use App\Domain\Reportes\TendenciasService;
use Illuminate\Http\Request;

class ReportesController extends Controller
{
    public function index(Request $request)
    {
        $this->assertPuedeVerReportes();

        $mes = $request->filled('mes') ? (int) $request->mes : (int) now()->month;
        $año = $request->filled('año') ? (int) $request->año : (int) now()->year;
        $data = $this->compilarDatos($mes, $año);

        return view('reportes.index', $data);
    }

    public function exportExcel(Request $request)
    {
        $this->assertPuedeVerReportes();

        $mes = $request->filled('mes') ? (int) $request->mes : (int) now()->month;
        $año = $request->filled('año') ? (int) $request->año : (int) now()->year;
        $data = $this->compilarDatos($mes, $año);

        $payload = app(ReportesService::class)->payloadExcel($data, $mes, $año);

        $nombre = sprintf('reportes-%04d-%02d.xlsx', $año, $mes);

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\ReportesExport($payload), $nombre);
    }

    public function exportPdf(Request $request)
    {
        $this->assertPuedeVerReportes();

        $mes = $request->filled('mes') ? (int) $request->mes : (int) now()->month;
        $año = $request->filled('año') ? (int) $request->año : (int) now()->year;
        $data = $this->compilarDatos($mes, $año);

        return view('reportes.pdf', $data);
    }

    private function assertPuedeVerReportes(): void
    {
        if (! auth()->user()?->puedeVerReportes()) {
            abort(403, 'No tenés acceso a reportes.');
        }
    }

    private function compilarDatos(int $mes, int $año): array
    {
        return app(ReportesService::class)->compilar($mes, $año, auth()->user());
    }

    /** Tendencias (mes contra mes y contra el año pasado) y deuda por sede y bloque. */
    public function tendencias(Request $request, TendenciasService $tendencias)
    {
        $this->assertPuedeVerReportes();

        $mes = min(12, max(1, (int) $request->input('mes', now()->month)));
        $año = min((int) now()->year + 1, max(2015, (int) $request->input('año', now()->year)));
        $meses = in_array((int) $request->input('meses'), [6, 12, 24], true) ? (int) $request->input('meses') : 12;
        $sedes = app(ReportesService::class)->sedeScopeIds(auth()->user());

        return view('reportes.tendencias', [
            'mes' => $mes,
            'año' => $año,
            'meses' => $meses,
            'sedeScope' => $sedes,
            'serie' => $tendencias->serie($mes, $año, $meses, $sedes),
            'comparativo' => $tendencias->comparativo($mes, $año, $sedes),
            'morosidad' => $tendencias->morosidad($sedes),
        ]);
    }

    public function profesores()
    {
        $this->assertPuedeVerReportes();
        $alumnosPorProfesor = app(ReportesService::class)->alumnosPorProfesor(auth()->user());

        return view('reportes.profesores', compact('alumnosPorProfesor'));
    }
}
