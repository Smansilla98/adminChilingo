<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alumno;
use App\Models\Profesor;
use App\Models\Bloque;
use App\Models\Sede;
use App\Models\Evento;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Si es profesor, mostrar dashboard limitado
        if ($user->isProfesor() && !$user->isAdmin()) {
            return $this->dashboardProfesor();
        }

        // Dashboard Admin (protegido si faltan tablas por migraciones pendientes)
        try {
            $totalAlumnos = Alumno::where('activo', true)->count();
            $totalProfesores = Profesor::where('activo', true)->count();
            $totalBloques = Bloque::where('activo', true)->count();
            $proximosEventos = Evento::proximos()->limit(5)->get();

            $alumnosPorSede = Sede::withCount(['alumnos' => function ($query) {
                $query->where('activo', true);
            }])->get();

            $alumnosPorAño = Bloque::select('año', DB::raw('count(*) as total'))
                ->whereHas('alumnos', function ($query) {
                    $query->where('activo', true);
                })
                ->where('activo', true)
                ->groupBy('año')
                ->orderBy('año')
                ->get();

            $totalConTamborPropio = Alumno::where('activo', true)
                ->where('tambor_procedencia', 'Propio')
                ->count();
            $totalConTamborSede = Alumno::where('activo', true)
                ->where('tambor_procedencia', 'Sede')
                ->count();
            $totalTambores = $totalAlumnos > 0 ? $totalAlumnos : 1;
            $porcentajePropio = ($totalConTamborPropio / $totalTambores) * 100;
            $porcentajeSede = ($totalConTamborSede / $totalTambores) * 100;

            return view('dashboard.index', compact(
                'totalAlumnos',
                'totalProfesores',
                'totalBloques',
                'proximosEventos',
                'alumnosPorSede',
                'alumnosPorAño',
                'porcentajePropio',
                'porcentajeSede',
                'totalConTamborPropio',
                'totalConTamborSede'
            ));
        } catch (\Illuminate\Database\QueryException $e) {
            return view('dashboard.index', [
                'totalAlumnos' => 0,
                'totalProfesores' => 0,
                'totalBloques' => 0,
                'proximosEventos' => collect(),
                'alumnosPorSede' => collect(),
                'alumnosPorAño' => collect(),
                'porcentajePropio' => 0,
                'porcentajeSede' => 0,
                'totalConTamborPropio' => 0,
                'totalConTamborSede' => 0,
            ]);
        }
    }

    private function dashboardProfesor()
    {
        $user = auth()->user();
        $bloques = collect();
        $proximosEventos = collect();

        try {
            $profesor = $user->profesor;

            if ($profesor) {
                $bloques = $profesor->bloquesActivos()->with('sede', 'alumnos')->get();
                $proximosEventos = Evento::where('profesor_id', $profesor->id)
                    ->proximos()
                    ->limit(5)
                    ->get();
            }
        } catch (\Illuminate\Database\QueryException $e) {
            // Tabla profesors/profesores u otras no existen (migraciones pendientes)
        } catch (\Throwable $e) {
            // Cualquier otro fallo: mostrar dashboard vacío
        }

        return view('dashboard.profesor', compact('bloques', 'proximosEventos'));
    }
}
