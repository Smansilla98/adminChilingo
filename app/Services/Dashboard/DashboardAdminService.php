<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Services\AmbitoSedeService;
use Carbon\Carbon;

/**
 * Panel de administración: arma los bloques de datos (KPIs, cuotas, profesores, semana, cobros,
 * recaudación y actividad) y la lista de «atención hoy». Lo llama DashboardController; la vista
 * recibe exactamente las mismas variables que antes.
 */
class DashboardAdminService
{
    public function __construct(
        private readonly CobrosPendientesService $cobros,
        private readonly DashboardKpiService $kpis,
        private readonly CuotasMesService $cuotas,
        private readonly ProfesoresTopService $profesores,
        private readonly AsistenciaSemanaService $semana,
        private readonly RecaudacionService $recaudacion,
        private readonly ActividadPanelService $actividad,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(User $user, AmbitoSedeService $ambito): array
    {
        $filtroSedes = $ambito->idsPara($user);
        $dashboardAmbito = $ambito->etiqueta($user);

        $mesActual = (int) now()->month;
        $anioActual = (int) now()->year;
        $hoy = Carbon::today();

        $k = $this->kpis->armar($user, $filtroSedes, $ambito, $anioActual, $mesActual);
        $alumnosActivos = $k['alumnosActivos'];
        $bloquesActivos = $k['bloquesActivos'];
        $alumnosNuevosMes = $k['alumnosNuevosMes'];
        $sedesActivasEnBloques = $k['sedesActivasEnBloques'];
        $asistenciasMes = $k['asistenciasMes'];

        $c = $this->cuotas->armar($filtroSedes, $ambito, $anioActual, $mesActual);
        $cuotasMes = $c['cuotasMes'];
        $cuotasTotal = $c['cuotasTotal'];
        $cuotasAbonadas = $c['cuotasAbonadas'];
        $cuotasPendientes = $c['cuotasPendientes'];
        $pctAbonadas = $c['pctAbonadas'];
        $pctPendientes = $c['pctPendientes'];

        $p = $this->profesores->armar($filtroSedes, $ambito);
        $profBase = $p['profBase'];
        $maxAlumnosProfesor = $p['maxAlumnosProfesor'];

        $bloquesSemanales = $this->semana->armar($filtroSedes, $ambito, $hoy)['bloquesSemanales'];

        // Cobros pendientes del mes (misma lógica de alcance / cuota_alumno que el contador superior)
        $cuotasPendientesList = $this->cobros->armarListadoCobrosPendientes($cuotasMes, $hoy, 8, $filtroSedes, $ambito);

        $r = $this->recaudacion->armar($filtroSedes, $ambito, $anioActual, $mesActual);
        $recaudacion = $r['recaudacion'];
        $cobradoMes = $r['cobradoMes'];
        $chartLabels = $r['chartLabels'];
        $chartIngresos = $r['chartIngresos'];
        $chartGastos = $r['chartGastos'];

        $a = $this->actividad->armar($filtroSedes, $ambito);
        $proximosEventos = $a['proximosEventos'];
        $proximosEventosCount = $a['proximosEventosCount'];
        $comprobantesPendientesCount = $a['comprobantesPendientesCount'];
        $comprobantesPendientesList = $a['comprobantesPendientesList'];
        $bloquesCupo = $a['bloquesCupo'];
        $alumnosPorSedeChart = $a['alumnosPorSedeChart'];

        $adminNombre = trim(auth()->user()->name ?: auth()->user()->username ?: '');

        $atencionHoy = collect();
        if ($comprobantesPendientesCount > 0) {
            $atencionHoy->push([
                'href' => route('comprobantes-cuota-alumnos.index', ['estado' => 'pendiente']),
                'title' => $comprobantesPendientesCount.' comprobantes sin revisar',
                'hint' => 'Cobranza de hoy',
            ]);
        }
        if ($cuotasPendientes > 0) {
            $atencionHoy->push([
                'href' => route('cuotas.index'),
                'title' => $cuotasPendientes.' cuotas del mes aún abiertas',
                'hint' => 'Finanzas',
            ]);
        }
        $evHoy = $proximosEventos->first();
        if ($evHoy && $evHoy->fecha->isToday()) {
            $atencionHoy->push([
                'href' => route('eventos.index'),
                'title' => 'Hoy: '.$evHoy->titulo,
                'hint' => 'Agenda',
            ]);
        } elseif ($evHoy !== null && $proximosEventosCount > 0) {
            $atencionHoy->push([
                'href' => route('eventos.index'),
                'title' => 'Próximo: '.$evHoy->titulo,
                'hint' => $evHoy->fecha->locale('es')->translatedFormat('d M'),
            ]);
        }

        return compact(
            'alumnosActivos',
            'bloquesActivos',
            'alumnosNuevosMes',
            'sedesActivasEnBloques',
            'cuotasTotal',
            'cuotasAbonadas',
            'cuotasPendientes',
            'pctAbonadas',
            'pctPendientes',
            'profBase',
            'maxAlumnosProfesor',
            'bloquesSemanales',
            'cuotasPendientesList',
            'recaudacion',
            'cobradoMes',
            'proximosEventos',
            'proximosEventosCount',
            'comprobantesPendientesCount',
            'comprobantesPendientesList',
            'bloquesCupo',
            'chartLabels',
            'chartIngresos',
            'chartGastos',
            'alumnosPorSedeChart',
            'asistenciasMes',
            'adminNombre',
            'dashboardAmbito',
            'atencionHoy'
        );
    }

    /**
     * Panel sin datos: se usa si faltan tablas por migraciones pendientes.
     *
     * @return array<string, mixed>
     */
    public static function vacio(): array
    {
        return [
            'alumnosActivos' => 0,
            'bloquesActivos' => 0,
            'alumnosNuevosMes' => 0,
            'sedesActivasEnBloques' => 0,
            'cuotasTotal' => 0,
            'cuotasAbonadas' => 0,
            'cuotasPendientes' => 0,
            'pctAbonadas' => 0,
            'pctPendientes' => 0,
            'profBase' => collect(),
            'maxAlumnosProfesor' => 1,
            'bloquesSemanales' => collect(),
            'cuotasPendientesList' => collect(),
            'recaudacion' => collect([0, 0, 0, 0, 0, 0]),
            'cobradoMes' => 0,
            'proximosEventos' => collect(),
            'proximosEventosCount' => 0,
            'comprobantesPendientesCount' => 0,
            'comprobantesPendientesList' => collect(),
            'bloquesCupo' => collect(),
            'chartLabels' => [],
            'chartIngresos' => [],
            'chartGastos' => [],
            'alumnosPorSedeChart' => collect(),
            'asistenciasMes' => 0,
            'adminNombre' => '',
            'dashboardAmbito' => 'Vista general de la escuela',
            'atencionHoy' => collect(),
        ];
    }
}
