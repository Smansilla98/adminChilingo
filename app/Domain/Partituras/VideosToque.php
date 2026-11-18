<?php

namespace App\Domain\Partituras;

use App\Models\ProgramaRitmo;
use App\Support\PartituraAudio;
use App\Support\ProgramaRitmoMedios;
use App\Support\VideosMultipista;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Alta, edición, orden y baja de los videos multipista de un toque. Cada cambio se guarda en
 * medios.videos_multipista; el archivo queda en la carpeta propia del toque.
 */
class VideosToque
{
    /** @return array<int, array<string, mixed>> */
    public function listar(ProgramaRitmo $toque): array
    {
        return VideosMultipista::normalizar($toque->mediosNormalizados()['videos_multipista'] ?? []);
    }

    /** @return array<string, mixed> el video agregado */
    public function agregar(ProgramaRitmo $toque, UploadedFile $archivo, string $instrumento, ?string $titulo, float $offset = 0.0): array
    {
        $actuales = $this->listar($toque);
        if (count($actuales) >= VideosMultipista::MAX) {
            throw new RuntimeException('Un toque admite hasta '.VideosMultipista::MAX.' videos.');
        }
        $ext = VideosMultipista::extension($archivo->getClientOriginalName()) ?? 'mp4';
        $id = 'v'.Str::lower(Str::random(8));
        $ruta = VideosMultipista::CARPETA.'/'.$toque->id.'-'.Str::lower(Str::random(16)).'.'.$ext;
        $archivo->storeAs(VideosMultipista::CARPETA, basename($ruta), PartituraAudio::disco());

        $actuales[] = [
            'id' => $id,
            'instrumento' => $instrumento,
            'titulo' => $titulo ?? '',
            'archivo' => $ruta,
            'offset_seg' => $offset,
            'orden' => count($actuales) + 1,
        ];
        $this->guardar($toque, $actuales);

        return collect($this->listar($toque))->firstWhere('id', $id);
    }

    /** @param array{titulo?: ?string, instrumento?: string, offset_seg?: float} $datos */
    public function actualizar(ProgramaRitmo $toque, string $id, array $datos): ?array
    {
        $lista = $this->listar($toque);
        foreach ($lista as $i => $v) {
            if ($v['id'] !== $id) {
                continue;
            }
            if (array_key_exists('titulo', $datos)) {
                $lista[$i]['titulo'] = (string) $datos['titulo'];
            }
            if (array_key_exists('instrumento', $datos)) {
                $lista[$i]['instrumento'] = (string) $datos['instrumento'];
            }
            if (array_key_exists('offset_seg', $datos)) {
                $lista[$i]['offset_seg'] = (float) $datos['offset_seg'];
            }
            $this->guardar($toque, $lista);

            return collect($this->listar($toque))->firstWhere('id', $id);
        }

        return null;
    }

    /** Sube (-1) o baja (+1) un video en el orden. */
    public function mover(ProgramaRitmo $toque, string $id, int $direccion): bool
    {
        $lista = $this->listar($toque);
        $i = collect($lista)->search(fn ($v) => $v['id'] === $id);
        if ($i === false) {
            return false;
        }
        $j = $i + ($direccion < 0 ? -1 : 1);
        if ($j < 0 || $j >= count($lista)) {
            return false;
        }
        [$lista[$i], $lista[$j]] = [$lista[$j], $lista[$i]];
        // El orden se guarda en el campo «orden»: se renumera tras el intercambio.
        foreach ($lista as $k => $v) {
            $lista[$k]['orden'] = $k + 1;
        }
        $this->guardar($toque, $lista);

        return true;
    }

    public function quitar(ProgramaRitmo $toque, string $id): bool
    {
        $lista = $this->listar($toque);
        $quitado = null;
        $resto = array_values(array_filter($lista, function ($v) use ($id, &$quitado) {
            if ($v['id'] === $id) {
                $quitado = $v;

                return false;
            }

            return true;
        }));
        if ($quitado === null) {
            return false;
        }
        $this->guardar($toque, $resto);
        Storage::disk(PartituraAudio::disco())->delete($quitado['archivo']);

        return true;
    }

    /** @param array<int, array<string, mixed>> $lista */
    private function guardar(ProgramaRitmo $toque, array $lista): void
    {
        $medios = ProgramaRitmoMedios::normalizar($toque->medios);
        $medios['videos_multipista'] = VideosMultipista::normalizar($lista);
        $toque->update(['medios' => $medios]);
    }
}
