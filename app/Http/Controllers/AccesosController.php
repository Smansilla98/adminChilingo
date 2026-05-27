<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AccesosController extends Controller
{
    /**
     * Definición de módulos/submódulos para la matriz de accesos.
     * Clave => Etiqueta (grupo).
     *
     * Nota: esta matriz controla visibilidad + bloqueo por middleware "modulo".
     */
    private const MODULOS = [
        'programa' => ['Programa'],
        'calendario' => ['Calendario'],
        'ayuda' => ['Guía de uso'],
        'comprobantes' => ['Comprobantes'],

        // Profesor
        'profesor.mis_bloques' => ['Profesor', 'Mis bloques'],
        'profesor.asistencia' => ['Profesor', 'Asistencia'],
        'profesor.mis_alumnos' => ['Profesor', 'Mis alumnos'],
        'profesor.pagos_cuotas' => ['Profesor', 'Pagos de cuotas'],
        'profesor.mis_eventos' => ['Profesor', 'Mis eventos'],

        // Admin (módulos grandes)
        'admin.alumnos' => ['Administración', 'Alumnos'],
        'admin.importar' => ['Administración', 'Importar alumnos'],
        'admin.profesores' => ['Administración', 'Profesores'],
        'admin.bloques' => ['Administración', 'Bloques'],
        'admin.sedes' => ['Administración', 'Sedes'],
        'admin.cuotas' => ['Administración', 'Cuotas'],
        'admin.pagos' => ['Administración', 'Pagos'],
        'admin.eventos' => ['Administración', 'Eventos'],
        'admin.asistencias' => ['Administración', 'Asistencias'],
        'admin.reportes' => ['Administración', 'Reportes'],
        'admin.facturacion_mensual' => ['Administración', 'Facturación mensual'],
        'admin.inventarios' => ['Administración', 'Inventarios'],
        'admin.plan_compras' => ['Administración', 'Plan de compras'],
        'admin.ordenes_compra' => ['Administración', 'Órdenes de compra'],
        'admin.gastos' => ['Administración', 'Gastos'],
        'admin.shows' => ['Administración', 'Shows'],
    ];

    public function index(Request $request)
    {
        $users = User::query()->orderBy('name')->orderBy('username')->get();
        $userId = $request->integer('user_id') ?: ($users->first()?->id ?? null);
        $usuario = $userId ? $users->firstWhere('id', $userId) : null;

        $map = $usuario && is_array($usuario->modulos_access) ? $usuario->modulos_access : [];

        // Agrupar por "grupo" (primer elemento del array etiqueta)
        $agrupado = [];
        foreach (self::MODULOS as $clave => $labelParts) {
            $grupo = $labelParts[0] ?? 'General';
            $etiqueta = $labelParts[1] ?? ($labelParts[0] ?? $clave);
            $agrupado[$grupo][] = [
                'clave' => $clave,
                'etiqueta' => $etiqueta,
                'valor' => array_key_exists($clave, $map) ? (bool) $map[$clave] : true, // default permitido
            ];
        }
        ksort($agrupado);

        return view('accesos.index', compact('users', 'usuario', 'agrupado'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'access' => 'nullable|array',
        ]);

        $usuario = User::query()->findOrFail($request->integer('user_id'));

        $incoming = $request->input('access', []);
        if (! is_array($incoming)) {
            $incoming = [];
        }

        // Guardamos solo claves conocidas, para evitar basura.
        $out = [];
        foreach (array_keys(self::MODULOS) as $clave) {
            if (array_key_exists($clave, $incoming)) {
                $out[$clave] = (bool) $incoming[$clave];
            }
        }

        $usuario->forceFill(['modulos_access' => $out])->saveQuietly();

        return redirect()
            ->route('accesos.index', ['user_id' => $usuario->id])
            ->with('success', 'Accesos actualizados.');
    }
}

