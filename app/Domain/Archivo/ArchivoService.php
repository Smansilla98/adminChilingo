<?php

namespace App\Domain\Archivo;

use App\Domain\Notificaciones\NotificacionService;
use App\Models\ArchivoAcontecimiento;
use App\Models\ArchivoCapitulo;
use App\Models\ArchivoFoto;
use App\Models\ArchivoRevision;
use App\Models\BibliotecaTag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fotos del archivo histórico: carga, metadatos, edición en lote, orden y el flujo
 * de moderación de aportes. Lo usan la web (equipo y aportantes) y la API.
 */
class ArchivoService
{
    /** Campos que puede completar cualquier aportante. */
    private const CAMPOS_APORTE = [
        'titulo', 'descripcion', 'contexto', 'notas_aportante', 'tipo', 'fecha', 'anio', 'mes', 'precision',
        'lugar', 'ciudad', 'pais', 'fotografo', 'fuente', 'fuente_detalle', 'mostrar_aportante',
        'sede_id', 'acontecimiento_id',
    ];

    /** Campos que solo edita el equipo del archivo. */
    private const CAMPOS_EQUIPO = [
        'alt_text', 'credito', 'licencia', 'capitulo_id', 'destacada', 'latitud', 'longitud',
    ];

    public function __construct(
        private readonly ImagenesArchivo $imagenes,
        private readonly NotificacionService $notificaciones,
    ) {}

    /** @return array<string, mixed> */
    public function reglasArchivo(): array
    {
        return [
            'archivo' => 'required|file|max:'.ImagenesArchivo::MAX_KB.'|extensions:'.ImagenesArchivo::EXTENSIONES.'|mimes:jpg,jpeg,png,webp',
        ];
    }

    /** @return array<string, string> */
    public function mensajes(): array
    {
        return [
            'archivo.max' => 'La imagen no puede superar 40 MB.',
            'archivo.extensions' => 'Formatos aceptados: JPG, PNG o WebP. Si es una foto HEIC del iPhone, compartila como JPG.',
            'archivo.mimes' => 'Formatos aceptados: JPG, PNG o WebP.',
            'archivo.uploaded' => 'No se pudo subir la imagen (suele ser demasiado grande o se cortó la conexión).',
            'anio.between' => 'El año tiene que estar entre 1900 y el año actual.',
        ];
    }

    /**
     * Reglas de metadatos. Con `$parcial` los campos no enviados no se tocan.
     *
     * @return array<string, mixed>
     */
    public function reglasMetadatos(bool $equipo, bool $parcial = true): array
    {
        $anioMax = (int) now()->year;
        $reglas = [
            'titulo' => 'nullable|string|max:180',
            'descripcion' => 'nullable|string|max:3000',
            'contexto' => 'nullable|string|max:8000',
            'notas_aportante' => 'nullable|string|max:4000',
            'tipo' => ['nullable', Rule::in(array_keys(ArchivoFoto::TIPOS))],
            'fecha' => 'nullable|date|before_or_equal:today',
            'anio' => "nullable|integer|between:1900,{$anioMax}",
            'mes' => 'nullable|integer|between:1,12',
            'precision' => ['nullable', Rule::in(array_keys(ArchivoFoto::PRECISIONES))],
            'lugar' => 'nullable|string|max:180',
            'ciudad' => 'nullable|string|max:120',
            'pais' => 'nullable|string|max:80',
            'fotografo' => 'nullable|string|max:150',
            'fuente' => ['nullable', Rule::in(array_keys(ArchivoFoto::FUENTES))],
            'fuente_detalle' => 'nullable|string|max:255',
            'mostrar_aportante' => 'nullable|boolean',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'acontecimiento_id' => 'nullable|integer|exists:archivo_acontecimientos,id',
            'tags' => 'nullable',
            'tags.*' => 'string|max:40',
            'personas' => 'nullable|array|max:60',
            'personas.*.persona_id' => 'nullable|integer|exists:personas,id',
            'personas.*.nombre' => 'nullable|string|max:150',
            'personas.*.detalle' => 'nullable|string|max:150',
        ];
        if ($equipo) {
            $reglas += [
                'alt_text' => 'nullable|string|max:300',
                'credito' => 'nullable|string|max:200',
                'licencia' => 'nullable|string|max:120',
                'capitulo_id' => 'nullable|integer|exists:archivo_capitulos,id',
                'destacada' => 'nullable|boolean',
                'latitud' => 'nullable|numeric|between:-90,90',
                'longitud' => 'nullable|numeric|between:-180,180',
            ];
        } else {
            // Un aportante solo vincula personas que ya aparecen en el archivo público
            // o nombres libres: no puede recorrer el padrón de personas.
            $reglas['personas.*.persona_id'] = ['nullable', 'integer', Rule::exists('archivo_foto_persona', 'persona_id')];
        }

        return $reglas;
    }

    /** @return Collection<int, ArchivoFoto> */
    public function duplicados(string $hash, ?int $excepto = null): Collection
    {
        return ArchivoFoto::query()
            ->where('hash', $hash)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))
            ->limit(5)
            ->get();
    }

    /**
     * Crea la foto a partir de la imagen subida. Queda en borrador: el equipo la
     * publica y el aportante la envía a revisión.
     *
     * @param  array<string, mixed>  $datos  validados con reglasMetadatos()
     */
    public function subir(UploadedFile $archivo, array $datos, User $user, bool $equipo): ArchivoFoto
    {
        $tecnico = $this->imagenes->guardarOriginal($archivo);

        try {
            $foto = DB::transaction(function () use ($tecnico, $datos, $user, $equipo) {
                $foto = new ArchivoFoto($tecnico + [
                    'slug' => 'tmp-'.Str::uuid(),
                    'estado' => 'borrador',
                    'aportada_por' => $user->id,
                    'mostrar_aportante' => false,
                    'precision' => 'anio',
                ]);
                if (empty($datos['titulo'])) {
                    $datos['titulo'] = $this->tituloDesdeNombre($tecnico['nombre_original']);
                }
                $this->asignar($foto, $datos, $equipo);
                $this->completarFechaDesdeExif($foto);
                $foto->orden = (int) ArchivoFoto::query()->max('orden') + 1;
                $foto->save();
                $foto->slug = $this->slugUnico(ArchivoFoto::class, $foto->titulo ?: 'foto', $foto->id);
                $foto->saveQuietly();
                $this->sincronizarRelaciones($foto, $datos);
                $this->registrar($foto, $user, 'creada', null, 'borrador');

                return $foto;
            });
        } catch (\Throwable $e) {
            Storage::disk(ImagenesArchivo::DISCO)->delete($tecnico['path']);
            throw $e;
        }

        return $this->imagenes->generarDerivados($foto)->fresh(['tags', 'personas.persona']);
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(ArchivoFoto $foto, array $datos, User $user, bool $equipo): ArchivoFoto
    {
        return DB::transaction(function () use ($foto, $datos, $equipo) {
            $this->asignar($foto, $datos, $equipo);
            if ($foto->isDirty('titulo') && $foto->titulo) {
                $foto->slug = $this->slugUnico(ArchivoFoto::class, $foto->titulo, $foto->id);
            }
            $foto->save();
            $this->sincronizarRelaciones($foto, $datos);

            return $foto->fresh(['tags', 'personas.persona', 'acontecimiento', 'sede', 'capitulo']);
        });
    }

    /**
     * Aplica los mismos datos a varias fotos. Las etiquetas y personas se suman a las
     * que ya tienen (salvo `reemplazar_tags`).
     *
     * @param  Collection<int, ArchivoFoto>  $fotos
     * @param  array<string, mixed>  $cambios
     */
    public function aplicarEnLote(Collection $fotos, array $cambios, User $user, bool $equipo): int
    {
        $soloPresentes = array_filter($cambios, fn ($v, $k) => ! in_array($k, ['tags', 'personas', 'reemplazar_tags'], true) && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH);

        DB::transaction(function () use ($fotos, $soloPresentes, $cambios, $equipo) {
            foreach ($fotos as $foto) {
                $this->asignar($foto, $soloPresentes, $equipo);
                $foto->save();
                if (! empty($cambios['tags'])) {
                    $nuevos = collect($this->resolverTags($cambios['tags']))->pluck('id');
                    ! empty($cambios['reemplazar_tags'])
                        ? $foto->tags()->sync($nuevos)
                        : $foto->tags()->syncWithoutDetaching($nuevos);
                }
                if (! empty($cambios['personas'])) {
                    $this->agregarPersonas($foto, $cambios['personas']);
                }
            }
        });

        return $fotos->count();
    }

    /**
     * Acción sobre una selección: aplicar datos, publicar, ocultar o eliminar. Cada foto
     * se autoriza por separado; las que quedan fuera del alcance se cuentan como omitidas.
     *
     * @param  Collection<int, ArchivoFoto>  $fotos
     * @param  array<string, mixed>  $datos  metadatos validados (para `aplicar`) + `publicar`
     * @return array{hechas: int, omitidas: int}
     */
    public function accionEnLote(Collection $fotos, string $accion, array $datos, User $user): array
    {
        $permiso = match ($accion) {
            'aplicar' => 'editarComoEquipo',
            'publicar', 'ocultar' => 'publish',
            'eliminar' => 'delete',
        };
        $permitidas = $fotos->filter(fn ($f) => $user->can($permiso, $f))->values();
        $hechas = 0;

        switch ($accion) {
            case 'aplicar':
                $cambios = collect($datos)->except(['ids', 'accion', 'publicar', 'volver'])->all();
                $hechas = $this->aplicarEnLote($permitidas, $cambios, $user, true);
                if (! empty($datos['publicar'])) {
                    foreach ($permitidas as $f) {
                        $f->refresh();
                        if ($user->can('publish', $f) && in_array($f->estado, ['borrador', 'oculta'], true)) {
                            $this->publicar($f, $user);
                        }
                    }
                }
                break;
            case 'publicar':
            case 'ocultar':
                foreach ($permitidas as $f) {
                    if ($accion === 'publicar' && in_array($f->estado, ['borrador', 'oculta'], true)) {
                        $this->publicar($f, $user);
                        $hechas++;
                    } elseif ($accion === 'ocultar' && $f->estado === 'publicada') {
                        $this->ocultar($f, $user);
                        $hechas++;
                    }
                }
                break;
            case 'eliminar':
                foreach ($permitidas as $f) {
                    $this->eliminar($f);
                    $hechas++;
                }
                break;
        }

        return ['hechas' => $hechas, 'omitidas' => $fotos->count() - $permitidas->count()];
    }

    public function reemplazarImagen(ArchivoFoto $foto, UploadedFile $archivo, User $user): ArchivoFoto
    {
        $anterior = $foto->path;
        $tecnico = $this->imagenes->guardarOriginal($archivo);
        $this->imagenes->borrarDerivados($foto);
        $foto->forceFill($tecnico + ['derivados' => null, 'placeholder' => null, 'color' => null])->save();
        if ($anterior && $anterior !== $foto->path) {
            Storage::disk(ImagenesArchivo::DISCO)->delete($anterior);
        }
        $this->registrar($foto, $user, 'reemplazada', $foto->estado, $foto->estado);

        return $this->imagenes->generarDerivados($foto);
    }

    public function eliminar(ArchivoFoto $foto): void
    {
        DB::transaction(function () use ($foto) {
            ArchivoCapitulo::query()->where('portada_foto_id', $foto->id)->update(['portada_foto_id' => null]);
            ArchivoAcontecimiento::query()->where('portada_foto_id', $foto->id)->update(['portada_foto_id' => null]);
            $foto->delete();
        });
        $this->imagenes->eliminar($foto);
    }

    /**
     * Guarda el orden recibido (ids en el orden deseado). Solo toca las fotos listadas.
     *
     * @param  list<int>  $ids
     */
    public function ordenar(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $i => $id) {
                ArchivoFoto::query()->whereKey($id)->update(['orden' => $i + 1]);
            }
        });
    }

    // ── Moderación ───────────────────────────────────────────────────────────

    public function enviar(ArchivoFoto $foto, User $user): ArchivoFoto
    {
        if (! in_array($foto->estado, ['borrador', 'cambios'], true)) {
            throw ValidationException::withMessages(['estado' => 'Este aporte ya fue enviado.']);
        }
        if (! $foto->anio && ! $foto->fecha) {
            throw ValidationException::withMessages(['anio' => 'Contanos al menos el año aproximado antes de enviarla.']);
        }

        $foto = $this->transicion($foto, 'pendiente', 'enviada', $user, null, ['enviada_at' => now()]);
        $this->avisar($foto, 'recibido', 'Recibimos tu aporte', 'Gracias por sumar "'.$foto->tituloVisible().'" al archivo. Lo vamos a revisar pronto.');

        return $foto;
    }

    public function aprobar(ArchivoFoto $foto, User $moderador, ?string $notas = null): ArchivoFoto
    {
        $this->exigirEstado($foto, ['pendiente', 'cambios', 'rechazada', 'borrador']);
        $foto = $this->transicion($foto, 'publicada', 'aprobada', $moderador, $notas, [
            'revisada_por' => $moderador->id, 'revisada_at' => now(), 'notas_revision' => $notas,
            'motivo_rechazo' => null, 'publicada_at' => $foto->publicada_at ?? now(),
        ]);
        $this->avisar($foto, 'aprobado', 'Tu foto ya es parte del archivo', '"'.$foto->tituloVisible().'" fue aprobada y está publicada en el archivo histórico.');

        return $foto;
    }

    public function rechazar(ArchivoFoto $foto, User $moderador, string $motivo): ArchivoFoto
    {
        $this->exigirEstado($foto, ['pendiente', 'cambios', 'borrador']);
        $foto = $this->transicion($foto, 'rechazada', 'rechazada', $moderador, $motivo, [
            'revisada_por' => $moderador->id, 'revisada_at' => now(), 'motivo_rechazo' => $motivo,
        ]);
        $this->avisar($foto, 'rechazado', 'Revisamos tu aporte', '"'.$foto->tituloVisible().'" no se va a sumar al archivo. Motivo: '.$motivo);

        return $foto;
    }

    public function pedirCambios(ArchivoFoto $foto, User $moderador, string $notas): ArchivoFoto
    {
        $this->exigirEstado($foto, ['pendiente', 'borrador']);
        $foto = $this->transicion($foto, 'cambios', 'cambios', $moderador, $notas, [
            'revisada_por' => $moderador->id, 'revisada_at' => now(), 'notas_revision' => $notas,
        ]);
        $this->avisar($foto, 'cambios', 'Necesitamos más datos de tu foto', $notas);

        return $foto;
    }

    public function publicar(ArchivoFoto $foto, User $user): ArchivoFoto
    {
        $this->exigirEstado($foto, ['borrador', 'oculta']);

        return $this->transicion($foto, 'publicada', 'publicada', $user, null, ['publicada_at' => $foto->publicada_at ?? now()]);
    }

    public function ocultar(ArchivoFoto $foto, User $user): ArchivoFoto
    {
        $this->exigirEstado($foto, ['publicada']);

        return $this->transicion($foto, 'oculta', 'ocultada', $user);
    }

    /** Permiso de ArchivoFotoPolicy que exige cada acción de estado. */
    public static function permisoDeAccion(string $accion): string
    {
        return in_array($accion, ['publicar', 'ocultar'], true) ? 'publish' : 'moderate';
    }

    /** Aplica una acción de estado (la autorización la hace quien llama). */
    public function cambiarEstado(ArchivoFoto $foto, string $accion, ?string $notas, User $user): ArchivoFoto
    {
        return match ($accion) {
            'aprobar' => $this->aprobar($foto, $user, $notas),
            'rechazar' => $this->rechazar($foto, $user, (string) $notas),
            'cambios' => $this->pedirCambios($foto, $user, (string) $notas),
            'publicar' => $this->publicar($foto, $user),
            'ocultar' => $this->ocultar($foto, $user),
        };
    }

    // ── Etiquetas y personas ─────────────────────────────────────────────────

    /**
     * @param  list<string>|string  $entrada
     * @return list<BibliotecaTag>
     */
    public function resolverTags(array|string $entrada): array
    {
        return BibliotecaTag::syncFromInput(is_string($entrada) ? $entrada : array_values(array_filter($entrada, 'is_string')));
    }

    /** @param  list<array{persona_id?: int|null, nombre?: string|null, detalle?: string|null}>  $personas */
    public function agregarPersonas(ArchivoFoto $foto, array $personas): void
    {
        $orden = (int) $foto->personas()->max('orden');
        foreach ($this->normalizarPersonas($personas) as $p) {
            $existe = $foto->personas()
                ->when($p['persona_id'], fn ($q) => $q->where('persona_id', $p['persona_id']), fn ($q) => $q->whereNull('persona_id')->where('nombre', $p['nombre']))
                ->exists();
            if (! $existe) {
                $foto->personas()->create($p + ['orden' => ++$orden]);
            }
        }
    }

    // ── Internos ─────────────────────────────────────────────────────────────

    /** @param  array<string, mixed>  $datos */
    private function asignar(ArchivoFoto $foto, array $datos, bool $equipo): void
    {
        $campos = $equipo ? array_merge(self::CAMPOS_APORTE, self::CAMPOS_EQUIPO) : self::CAMPOS_APORTE;
        foreach ($campos as $campo) {
            if (! array_key_exists($campo, $datos)) {
                continue;
            }
            $valor = $datos[$campo];
            $foto->{$campo} = is_string($valor) ? (trim($valor) === '' ? null : trim($valor)) : $valor;
        }
        foreach (['mostrar_aportante', 'destacada'] as $bool) {
            if ($foto->{$bool} === null) {
                $foto->{$bool} = false;
            }
        }
        $this->normalizarFecha($foto);
        // Al elegir un acontecimiento, la foto hereda su capítulo y sede si no tiene.
        if ($foto->isDirty('acontecimiento_id') && $foto->acontecimiento_id) {
            $a = ArchivoAcontecimiento::query()->find($foto->acontecimiento_id);
            $foto->capitulo_id ??= $a?->capitulo_id;
            $foto->sede_id ??= $a?->sede_id;
            $foto->anio ??= $a?->anio;
        }
    }

    private function normalizarFecha(ArchivoFoto $foto): void
    {
        if ($foto->fecha && ($foto->isDirty('fecha') || ! $foto->anio)) {
            $foto->anio = $foto->fecha->year;
            $foto->mes = $foto->fecha->month;
            if (! $foto->isDirty('precision') || ! $foto->precision || $foto->precision === 'anio') {
                $foto->precision = 'dia';
            }
        }
        $foto->precision ??= 'anio';
    }

    private function completarFechaDesdeExif(ArchivoFoto $foto): void
    {
        if ($foto->anio || $foto->fecha) {
            return;
        }
        $original = $foto->exif['DateTimeOriginal'] ?? null;
        if (is_string($original) && preg_match('/^(\d{4}):(\d{2}):(\d{2})/', $original, $m) && (int) $m[1] >= 1900 && (int) $m[1] <= now()->year) {
            // Solo una pista: la cámara pudo tener mal la hora. Se marca aproximada.
            $foto->anio = (int) $m[1];
            $foto->mes = (int) $m[2] ?: null;
            $foto->precision = 'aprox';
        }
    }

    /** @param  array<string, mixed>  $datos */
    private function sincronizarRelaciones(ArchivoFoto $foto, array $datos): void
    {
        if (array_key_exists('tags', $datos)) {
            $foto->tags()->sync(collect($this->resolverTags($datos['tags'] ?? []))->pluck('id'));
        }
        if (array_key_exists('personas', $datos)) {
            $foto->personas()->delete();
            foreach ($this->normalizarPersonas($datos['personas'] ?? []) as $i => $p) {
                $foto->personas()->create($p + ['orden' => $i + 1]);
            }
        }
    }

    /**
     * @return list<array{persona_id: int|null, nombre: string|null, detalle: string|null}>
     */
    private function normalizarPersonas(mixed $personas): array
    {
        $salida = [];
        $vistos = [];
        foreach ((array) $personas as $p) {
            if (! is_array($p)) {
                continue;
            }
            $id = isset($p['persona_id']) && $p['persona_id'] !== '' ? (int) $p['persona_id'] : null;
            $nombre = trim((string) ($p['nombre'] ?? ''));
            if (! $id && $nombre === '') {
                continue;
            }
            $clave = $id ? 'p'.$id : 'n'.mb_strtolower($nombre);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $salida[] = [
                'persona_id' => $id,
                'nombre' => $id ? null : mb_substr($nombre, 0, 150),
                'detalle' => ($d = trim((string) ($p['detalle'] ?? ''))) !== '' ? mb_substr($d, 0, 150) : null,
            ];
        }

        return $salida;
    }

    /** @param  array<string, mixed>  $extra */
    private function transicion(ArchivoFoto $foto, string $nuevo, string $accion, User $user, ?string $notas = null, array $extra = []): ArchivoFoto
    {
        return DB::transaction(function () use ($foto, $nuevo, $accion, $user, $notas, $extra) {
            $foto = ArchivoFoto::query()->lockForUpdate()->findOrFail($foto->id);
            $anterior = $foto->estado;
            $foto->forceFill(['estado' => $nuevo] + $extra)->save();
            $this->registrar($foto, $user, $accion, $anterior, $nuevo, $notas);

            return $foto;
        });
    }

    /** @param  list<string>  $permitidos */
    private function exigirEstado(ArchivoFoto $foto, array $permitidos): void
    {
        if (! in_array($foto->estado, $permitidos, true)) {
            throw ValidationException::withMessages(['estado' => 'La foto está "'.$foto->etiquetaEstado().'": esa acción no aplica.']);
        }
    }

    private function registrar(ArchivoFoto $foto, User $user, string $accion, ?string $anterior, ?string $nuevo, ?string $notas = null): ArchivoRevision
    {
        return ArchivoRevision::query()->create([
            'archivo_foto_id' => $foto->id,
            'user_id' => $user->id,
            'accion' => $accion,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'notas' => $notas,
        ]);
    }

    /** Aviso al aportante (interno + push) con el mismo servicio que el resto del sistema. */
    private function avisar(ArchivoFoto $foto, string $evento, string $titulo, string $mensaje): void
    {
        $destino = $foto->aportante;
        if (! $destino) {
            return;
        }
        $revision = $foto->revisiones()->value('id');
        rescue(fn () => $this->notificaciones->enviar($destino, "archivo:{$foto->id}:{$evento}:{$revision}", [
            'tipo' => 'archivo_'.$evento,
            'titulo' => $titulo,
            'mensaje' => Str::limit($mensaje, 400),
            'enlace' => route('archivo.aportes.show', $foto),
        ]), report: true);
    }

    private function tituloDesdeNombre(?string $nombre): ?string
    {
        $base = pathinfo((string) $nombre, PATHINFO_FILENAME);
        $base = trim(preg_replace('/[_\-]+/', ' ', $base) ?? '');
        // Nombres de cámara (IMG_1234, DSC0001, foto001) no son un título.
        if ($base === '' || preg_match('/^(img|dsc|dscn|pxl|photo|foto|image|imagen|wa)?\s*\d[\d\s]*$/i', $base)) {
            return null;
        }

        return Str::limit(Str::ucfirst($base), 180, '');
    }

    /** @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo */
    public function slugUnico(string $modelo, string $texto, ?int $id = null): string
    {
        $base = Str::limit(Str::slug($texto) ?: 'item', 150, '');
        $slug = $base;
        $n = 1;
        while ($modelo::query()->where('slug', $slug)->when($id, fn ($q) => $q->whereKeyNot($id))->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
