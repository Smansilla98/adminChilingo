<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ControlaToquesPrograma;
use App\Models\ProgramaRitmo;
use App\Models\ProgramaSeccion;
use App\Services\ProgramaRitmoMediosService;
use App\Support\ArchivoSeguro;
use App\Support\ProgramaRitmoMedios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Edición de toques y secciones del programa (página de edición con permiso).
 */
class ProgramaEdicionController extends Controller
{
    use ControlaToquesPrograma;

    public function editToque(ProgramaRitmo $programaRitmo)
    {
        $this->abortSiToqueNoPublico($programaRitmo);

        $medios = $programaRitmo->mediosNormalizados();

        return view('programa.toque-edit', [
            'programaRitmo' => $programaRitmo,
            'medios' => $medios,
            'videosBase' => ProgramaRitmoMedios::VIDEOS_BASE,
            'videosGrupo' => ProgramaRitmoMedios::VIDEOS_GRUPO,
            'tiposRecurso' => ProgramaRitmoMedios::TIPOS_RECURSO,
            'ultimaEdicion' => ProgramaRitmoMedios::ultimaEdicion($medios, 'pagina_ediciones'),
            'nombreSugerido' => auth()->user()?->name ?: auth()->user()?->username,
            'esAdmin' => (bool) auth()->user()?->isAdmin(),
        ]);
    }

    public function updateToque(Request $request, ProgramaRitmo $programaRitmo)
    {
        if (! \App\Domain\Acceso\EdicionPrograma::puedeEditar(auth()->user())) {
            abort(403, 'La edición del programa requiere iniciar sesión con permiso.');
        }
        $this->abortSiToqueNoPublico($programaRitmo);

        $esAdmin = (bool) auth()->user()?->isAdmin();

        $validated = $request->validate([
            'editor_nombre' => 'required|string|min:2|max:80',
            'nombre' => ($esAdmin ? 'required' : 'nullable').'|string|max:255',
            'autor' => 'nullable|string|max:500',
            'notas' => 'nullable|string|max:500',
            'resumen' => 'nullable|string|max:2000',
            'contenido' => 'nullable|string|max:50000',
            'opcional' => 'nullable|boolean',
            'publicado' => 'nullable|boolean',
            'secciones' => 'nullable|array',
            'secciones.*.titulo' => 'nullable|string|max:255',
            'secciones.*.contenido' => 'nullable|string|max:20000',
            'enlaces' => 'nullable|array',
            'enlaces.*.etiqueta' => 'nullable|string|max:120',
            'enlaces.*.url' => 'nullable|string|max:500',
            'quitar_partitura' => 'nullable|boolean',
            'partitura_archivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:20480',
            'videos_base' => 'nullable|array',
            'videos_base.*.url' => 'nullable|string|max:500',
            'videos_grupo' => 'nullable|array',
            'videos_grupo.*.url' => 'nullable|string|max:500',
            'cortes' => 'nullable|array',
            'cortes.*.titulo' => 'nullable|string|max:255',
            'cortes.*.url' => 'nullable|string|max:500',
            'cortes.*.archivo' => ['nullable', 'file', 'max:102400', 'extensions:pdf,jpg,jpeg,png,webp,gif,mp4,webm,mov', ArchivoSeguro::regla()],
            'recursos' => 'nullable|array',
            'recursos.*.tipo' => 'nullable|string|max:32',
            'recursos.*.titulo' => 'nullable|string|max:255',
            'recursos.*.url' => 'nullable|string|max:500',
            'recursos.*.contenido' => 'nullable|string|max:20000',
            'recursos.*.archivo' => ['nullable', 'file', 'max:102400', 'extensions:jpg,jpeg,png,webp,gif,mp4,webm,mov,pdf', ArchivoSeguro::regla()],
        ], [
            'editor_nombre.required' => 'Indicá tu nombre para dejar registro de la edición.',
            'cortes.*.archivo.extensions' => 'El corte tiene que ser imagen, video o PDF.',
            'recursos.*.archivo.extensions' => 'El archivo tiene que ser imagen, video o PDF.',
        ]);

        $secciones = collect($validated['secciones'] ?? [])
            ->map(fn ($s) => [
                'titulo' => trim((string) ($s['titulo'] ?? '')),
                'contenido' => trim((string) ($s['contenido'] ?? '')),
            ])
            ->filter(fn ($s) => $s['titulo'] !== '' || $s['contenido'] !== '')
            ->values()
            ->all();

        $enlaces = collect($validated['enlaces'] ?? [])
            ->map(fn ($e) => [
                'etiqueta' => trim((string) ($e['etiqueta'] ?? '')),
                'url' => trim((string) ($e['url'] ?? '')),
            ])
            ->filter(fn ($e) => $e['url'] !== '')
            ->values()
            ->all();

        $update = [
            'autor' => $validated['autor'] ?? null,
            'notas' => $validated['notas'] ?? null,
            'resumen' => $validated['resumen'] ?? null,
            'contenido' => $validated['contenido'] ?? null,
            'secciones' => $secciones !== [] ? $secciones : null,
            'enlaces' => $enlaces !== [] ? $enlaces : null,
        ];

        if ($esAdmin) {
            $update['nombre'] = $validated['nombre'] ?? $programaRitmo->nombre;
            $update['opcional'] = $request->boolean('opcional');
            $update['publicado'] = $request->boolean('publicado');
        }

        if (Schema::hasColumn('programa_ritmos', 'medios')) {
            $medios = app(ProgramaRitmoMediosService::class)->procesarDesdeRequest($request, $programaRitmo);
            $update['medios'] = ProgramaRitmoMedios::registrarEdicion(
                $medios,
                (string) $validated['editor_nombre'],
                $request->ip(),
                'pagina_ediciones'
            );
        }

        $programaRitmo->update($update);

        return redirect()
            ->route('programa.toque.show', $programaRitmo)
            ->with('success', 'Gracias, '.$validated['editor_nombre'].'. El toque quedó actualizado.');
    }

    public function editSeccion(ProgramaSeccion $programaSeccion)
    {
        $this->authorizeAdmin();

        return view('programa.seccion-edit', [
            'seccion' => $programaSeccion,
            'categorias' => ProgramaSeccion::categorias(),
        ]);
    }

    public function updateSeccion(Request $request, ProgramaSeccion $programaSeccion)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'subtitulo' => 'nullable|string|max:500',
            'cuerpo' => 'nullable|string|max:100000',
            'activo' => 'nullable|boolean',
        ]);

        $programaSeccion->update([
            'titulo' => $validated['titulo'],
            'subtitulo' => $validated['subtitulo'] ?? null,
            'cuerpo' => $validated['cuerpo'] ?? null,
            'activo' => $request->boolean('activo'),
        ]);

        return redirect()
            ->route('programa.index', ['seccion' => $programaSeccion->slug])
            ->with('success', 'Sección del programa actualizada.');
    }
}
