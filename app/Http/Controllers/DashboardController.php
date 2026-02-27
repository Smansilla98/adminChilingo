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

        // Dashboard Admin
        $totalAlumnos = Alumno::where('activo', true)->count();
        $totalProfesores = Profesor::where('activo', true)->count();
        $totalBloques = Bloque::where('activo', true)->count();
        $proximosEventos = Evento::proximos()->limit(5)->get();

        // Alumnos por sede
        $alumnosPorSede = Sede::withCount(['alumnos' => function($query) {
            $query->where('activo', true);
        }])->get();

        // Alumnos por año
        $alumnosPorAño = Bloque::select('año', DB::raw('count(*) as total'))
            ->whereHas('alumnos', function($query) {
                $query->where('activo', true);
            })
            ->where('activo', true)
            ->groupBy('año')
            ->orderBy('año')
            ->get();

        // % alumnos con tambor propio vs sede
        $totalConTamborPropio = Alumno::where('activo', true)
            ->where('tipo_tambor', 'Propio')
            ->count();
        $totalConTamborSede = Alumno::where('activo', true)
            ->where('tipo_tambor', 'Sede')
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
    }

    private function dashboardProfesor()
    {
        $user = auth()->user();
        
        // Obtener profesor asociado al usuario (asumiendo que hay relación)
        // Por ahora, obtener bloques del profesor
        $profesor = $user->profesor;
        
        if (!$profesor) {
            return view('dashboard.profesor', [
                'bloques' => collect(),
                'proximosEventos' => collect(),
            ]);
        }

        $bloques = $profesor->bloquesActivos()->with('sede', 'alumnos')->get();
        $proximosEventos = Evento::where('profesor_id', $profesor->id)
            ->proximos()
            ->limit(5)
            ->get();

        return view('dashboard.profesor', compact('bloques', 'proximosEventos'));
    }
}
