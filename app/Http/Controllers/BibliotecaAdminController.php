<?php

namespace App\Http\Controllers;

use App\Models\BibliotecaItem;
use App\Models\BibliotecaTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class BibliotecaAdminController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        if (! Schema::hasTable('biblioteca_items')) {
            return view('biblioteca.admin-index', [
                'items' => collect(),
                'sinTabla' => true,
                'q' => '',
                'estado' => '',
            ]);
        }

        $q = trim((string) $request->query('q', ''));
        $estado = trim((string) $request->query('estado', ''));

        $query = BibliotecaItem::query()->with(['tags', 'toque'])->latest();
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', '%'.$q.'%')
                    ->orWhere('autor_nombre', 'like', '%'.$q.'%')
                    ->orWhereHas('toque', fn ($t) => $t->where('nombre', 'like', '%'.$q.'%'));
            });
        }
        if (in_array($estado, ['publicado', 'oculto'], true)) {
            $query->where('estado', $estado);
        }

        $items = $query->paginate(30)->withQueryString();

        return view('biblioteca.admin-index', compact('items', 'q', 'estado') + ['sinTabla' => false]);
    }

    public function toggle(BibliotecaItem $bibliotecaItem)
    {
        $this->authorizeAdmin();
        $bibliotecaItem->estado = $bibliotecaItem->estado === 'publicado' ? 'oculto' : 'publicado';
        $bibliotecaItem->save();

        return back()->with('success', 'Estado actualizado: '.$bibliotecaItem->estado);
    }

    public function destroy(BibliotecaItem $bibliotecaItem)
    {
        $this->authorizeAdmin();

        $tagIds = $bibliotecaItem->tags()->pluck('biblioteca_tags.id');
        if ($bibliotecaItem->path && Storage::disk('comprobantes')->exists($bibliotecaItem->path)) {
            Storage::disk('comprobantes')->delete($bibliotecaItem->path);
        }
        $bibliotecaItem->tags()->detach();
        $bibliotecaItem->delete();

        if (Schema::hasTable('biblioteca_tags') && $tagIds->isNotEmpty()) {
            BibliotecaTag::query()->whereIn('id', $tagIds)->each(function (BibliotecaTag $tag) {
                $tag->usos = max(0, (int) $tag->items()->count());
                $tag->save();
            });
        }

        return back()->with('success', 'Material eliminado.');
    }

    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }
    }
}
