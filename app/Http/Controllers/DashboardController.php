<?php

namespace App\Http\Controllers;

use App\Services\AmbitoSedeService;
use App\Services\Dashboard\DashboardAdminService;
use App\Services\Dashboard\DashboardProfesorService;
use App\Services\EspacioAlumnoService;
use Illuminate\Database\QueryException;

/**
 * Elige qué panel mostrar según el rol y el contexto elegido. Los datos se arman en App\Services\Dashboard.
 */
class DashboardController extends Controller
{
    public function index(AmbitoSedeService $ambito)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Contexto elegido en "Estoy trabajando como…" (solo cambia la vista inicial).
        $rolContexto = explode(':', (string) session('contexto'))[0] ?: null;
        if ($rolContexto === 'alumno' && $user->isAlumno()) {
            return $this->dashboardAlumno();
        }
        if ($rolContexto === 'profesor' && $user->isProfesor()) {
            return $this->dashboardProfesor();
        }

        // Si es alumno (sin rol docente/gestión), espacio simple
        if ($user->isAlumno() && ! $user->isProfesor() && ! $user->isAdmin() && ! $user->puedeGestionarOperativo()) {
            return $this->dashboardAlumno();
        }

        // Si es profesor (sin panel de gestión), dashboard limitado
        if ($user->isProfesor() && ! $user->isAdmin() && ! $user->puedeGestionarOperativo()) {
            return $this->dashboardProfesor();
        }

        // Dashboard Admin (si faltan tablas por migraciones pendientes, se muestra el panel vacío)
        try {
            $datos = app(DashboardAdminService::class)->armar($user, $ambito);
        } catch (QueryException $e) {
            $datos = DashboardAdminService::vacio();
        }

        return view('dashboard.index', $datos);
    }

    private function dashboardProfesor()
    {
        return view('dashboard.profesor', app(DashboardProfesorService::class)->armar(auth()->user()));
    }

    private function dashboardAlumno()
    {
        $user = auth()->user();
        $alumno = $user->alumno;
        if ($alumno) {
            $alumno->loadMissing('sede');
        }

        $espacio = app(EspacioAlumnoService::class)->armar($alumno);

        return view('dashboard.alumno', array_merge(compact('alumno'), $espacio));
    }
}
