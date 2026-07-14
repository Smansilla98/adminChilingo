@php
    use App\Support\ProgramaRitmoMedios;
    $m = $medios ?? ProgramaRitmoMedios::estructuraVacia();
    $tieneMedios = ProgramaRitmoMedios::tieneContenidoMultimedia($m);
@endphp

@if($tieneMedios)
<section class="programa-medios mb-3">
    @if(!empty($m['partitura']['path']))
    @php
        $partituraUrl = route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'partitura']);
        $partituraInlineUrl = route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'partitura', 'inline' => 1]);
        $partituraNombre = $m['partitura']['nombre'] ?? '';
        $partituraEsImagen = (bool) preg_match('/\.(jpe?g|png|webp)$/i', $partituraNombre);
        $partituraEsPdf = (bool) preg_match('/\.pdf$/i', $partituraNombre);
    @endphp
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h3 class="h6 mb-0"><i class="bi bi-music-note-beamed"></i> Partitura</h3>
            <a href="{{ $partituraUrl }}" class="btn btn-outline-warning btn-sm" target="_blank" rel="noopener">
                <i class="bi bi-{{ $partituraEsPdf ? 'file-pdf' : 'download' }}"></i>
                {{ $partituraEsPdf ? 'Abrir PDF' : 'Descargar' }}
            </a>
        </div>
        <div class="card-body">
            @include('programa.partials.partitura-lectura', ['collapseId' => 'partituraLecturaToque', 'expanded' => true])

            @if($partituraEsImagen)
            <div class="programa-partitura-preview mt-3">
                <img
                    src="{{ $partituraInlineUrl }}"
                    alt="Partitura: {{ $programaRitmo->nombre }}"
                    class="img-fluid rounded border programa-partitura-preview-img"
                    loading="lazy"
                >
            </div>
            @elseif($partituraEsPdf)
            <div class="programa-partitura-preview mt-3">
                <iframe
                    src="{{ $partituraInlineUrl }}#view=FitH"
                    title="Partitura PDF — {{ $programaRitmo->nombre }}"
                    class="programa-partitura-pdf-frame"
                ></iframe>
                <p class="form-text mb-0 mt-2">Si no se ve el PDF, usá el botón «Abrir PDF» de arriba.</p>
            </div>
            @else
            <p class="text-muted small mb-0">Descargá el archivo para verlo en tu dispositivo.</p>
            @endif
        </div>
    </div>
    @endif

    @include('programa.partials.partitura-vexflow-show', ['medios' => $m, 'programaRitmo' => $programaRitmo])

    @php
        $basesConUrl = collect($m['videos_base'] ?? [])->filter(fn ($v) => !empty($v['url']));
        $grupoConUrl = collect($m['videos_grupo'] ?? [])->filter(fn ($v) => !empty($v['url']));
    @endphp

    @if($basesConUrl->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><h3 class="h6 mb-0">Bases por tambor</h3></div>
        <div class="card-body">
            <div class="row g-4">
                @foreach(ProgramaRitmoMedios::VIDEOS_BASE as $key => $label)
                    @php $url = $m['videos_base'][$key]['url'] ?? null; @endphp
                    @if($url)
                    <div class="col-md-6 col-lg-4">
                        <h4 class="h6 small text-muted mb-2">{{ $label }}</h4>
                        @include('programa.partials.video-embed', ['url' => $url])
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if($grupoConUrl->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><h3 class="h6 mb-0">Ensamble y llamadas</h3></div>
        <div class="card-body">
            <div class="row g-4">
                @foreach(ProgramaRitmoMedios::VIDEOS_GRUPO as $key => $label)
                    @php $url = $m['videos_grupo'][$key]['url'] ?? null; @endphp
                    @if($url)
                    <div class="col-md-6">
                        <h4 class="h6 small text-muted mb-2">{{ $label }}</h4>
                        @include('programa.partials.video-embed', ['url' => $url])
                    </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
    @endif

    @if(count($m['cortes'] ?? []) > 0)
    <div class="card mb-3">
        <div class="card-header"><h3 class="h6 mb-0">Cortes</h3></div>
        <div class="card-body d-grid gap-4">
            @foreach($m['cortes'] as $i => $corte)
            <article>
                @if(!empty($corte['titulo']))
                <h4 class="h6 mb-2">{{ $corte['titulo'] }}</h4>
                @endif
                @if(!empty($corte['url']))
                    @include('programa.partials.video-embed', ['url' => $corte['url']])
                @elseif(!empty($corte['path']))
                    <a href="{{ route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'corte', 'i' => $i]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                        <i class="bi bi-download"></i> {{ $corte['nombre'] ?? 'Descargar archivo' }}
                    </a>
                @endif
            </article>
            @endforeach
        </div>
    </div>
    @endif

    @if(count($m['recursos'] ?? []) > 0)
    <div class="card mb-3">
        <div class="card-header"><h3 class="h6 mb-0">Material de apoyo</h3></div>
        <div class="card-body d-grid gap-4">
            @foreach($m['recursos'] as $i => $rec)
            <article class="programa-recurso">
                @if(!empty($rec['titulo']))
                <h4 class="h6 mb-2">{{ $rec['titulo'] }}</h4>
                @endif
                @switch($rec['tipo'] ?? 'enlace')
                    @case('texto')
                        @if(!empty($rec['contenido']))
                        <div class="programa-contenido">{!! nl2br(e($rec['contenido'])) !!}</div>
                        @endif
                        @break
                    @case('imagen')
                        @if(!empty($rec['url']))
                        <img src="{{ $rec['url'] }}" alt="{{ $rec['titulo'] ?? 'Imagen' }}" class="img-fluid rounded programa-recurso-img" loading="lazy">
                        @elseif(!empty($rec['path']))
                        <img src="{{ route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'recurso', 'i' => $i, 'inline' => 1]) }}" alt="{{ $rec['titulo'] ?? '' }}" class="img-fluid rounded programa-recurso-img" loading="lazy">
                        @endif
                        @break
                    @case('video')
                        @if(!empty($rec['url']))
                            @include('programa.partials.video-embed', ['url' => $rec['url']])
                        @endif
                        @break
                    @case('pdf')
                        @if(!empty($rec['path']))
                        <a href="{{ route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'recurso', 'i' => $i]) }}" class="btn btn-sm btn-outline-warning" target="_blank" rel="noopener">
                            <i class="bi bi-file-pdf"></i> {{ $rec['nombre'] ?? 'Ver PDF' }}
                        </a>
                        @elseif(!empty($rec['url']))
                        <a href="{{ $rec['url'] }}" class="btn btn-sm btn-outline-warning" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Abrir documento</a>
                        @endif
                        @break
                    @default
                        @if(!empty($rec['url']))
                        <a href="{{ $rec['url'] }}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i> {{ $rec['titulo'] ?: $rec['url'] }}</a>
                        @elseif(!empty($rec['path']))
                        <a href="{{ route('programa.toque.archivo', [$programaRitmo, 'tipo' => 'recurso', 'i' => $i]) }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                            <i class="bi bi-download"></i> {{ $rec['nombre'] ?? 'Descargar' }}
                        </a>
                        @endif
                @endswitch
            </article>
            @endforeach
        </div>
    </div>
    @endif
</section>
@endif
