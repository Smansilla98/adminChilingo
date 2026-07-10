<?php

namespace App\Http\Controllers;

use App\Models\Diseno;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DisenoController extends Controller
{
    public function index()
    {
        $disenos = Diseno::query()->latest()->paginate(12);

        return view('disenos.index', compact('disenos'));
    }

    public function create()
    {
        return view('disenos.form', ['diseno' => new Diseno([
            'formato' => 'flyer_feed',
            'ancho' => 1080,
            'alto' => 1350,
        ])]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateDiseno($request);
        $diseno = Diseno::create([
            ...$validated,
            'user_id' => auth()->id(),
        ]);
        $this->guardarPreview($diseno, $request->input('preview_base64'));

        return redirect()->route('disenos.index')->with('success', 'Diseño guardado.');
    }

    public function edit(Diseno $diseno)
    {
        return view('disenos.form', compact('diseno'));
    }

    public function update(Request $request, Diseno $diseno)
    {
        $validated = $this->validateDiseno($request);
        $diseno->update($validated);
        $this->guardarPreview($diseno, $request->input('preview_base64'));

        return redirect()->route('disenos.index')->with('success', 'Diseño actualizado.');
    }

    public function destroy(Diseno $diseno)
    {
        if ($diseno->preview_path) {
            Storage::disk('public')->delete($diseno->preview_path);
        }
        $diseno->delete();

        return redirect()->route('disenos.index')->with('success', 'Diseño eliminado.');
    }

    private function validateDiseno(Request $request): array
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'formato' => 'required|string|max:40',
            'ancho' => 'required|integer|min:200|max:4000',
            'alto' => 'required|integer|min:200|max:6000',
            'canvas_json' => 'required|string',
            'preview_base64' => 'nullable|string',
        ]);

        json_decode($data['canvas_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            abort(422, 'JSON del canvas inválido.');
        }

        return [
            'titulo' => $data['titulo'],
            'formato' => $data['formato'],
            'ancho' => (int) $data['ancho'],
            'alto' => (int) $data['alto'],
            'canvas_json' => json_decode($data['canvas_json'], true),
        ];
    }

    private function guardarPreview(Diseno $diseno, ?string $base64): void
    {
        if (! $base64 || ! str_starts_with($base64, 'data:image')) {
            return;
        }

        $parts = explode(',', $base64, 2);
        if (count($parts) !== 2) {
            return;
        }

        $binary = base64_decode($parts[1], true);
        if ($binary === false) {
            return;
        }

        if ($diseno->preview_path) {
            Storage::disk('public')->delete($diseno->preview_path);
        }

        $path = 'disenos/previews/'.Str::uuid().'.png';
        Storage::disk('public')->put($path, $binary);
        $diseno->update(['preview_path' => $path]);
    }
}
