<?php

namespace App\Http\Controllers;

use App\Models\ProgramaRitmo;
use App\Support\PartituraAudio;
use App\Support\VideosMultipista;
use App\Support\VideoStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/** Videos multipista del toque: entrega del archivo (web). */
class PartituraVideoController extends Controller
{
    public function ver(Request $request, ProgramaRitmo $programaRitmo, string $archivo): Response
    {
        $this->abortSiNoPublico($programaRitmo);
        $video = collect(VideosMultipista::normalizar($programaRitmo->mediosNormalizados()['videos_multipista'] ?? []))
            ->first(fn (array $v) => basename($v['archivo']) === $archivo);
        abort_unless($video !== null, 404, 'No está ese video.');

        return VideoStream::responder($request, PartituraAudio::disco(), $video['archivo'], $archivo);
    }

    private function abortSiNoPublico(ProgramaRitmo $programaRitmo): void
    {
        if (Schema::hasColumn('programa_ritmos', 'publicado')
            && ! $programaRitmo->publicado
            && ! auth()->user()?->isAdmin()) {
            abort(404);
        }
    }
}
