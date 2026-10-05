@extends('layouts.archivo')

@section('body-class', 'archivo--buscar')

@section('content')
<div class="ar-busqueda">
    <form class="ar-busqueda__form" method="GET" action="{{ route('archivo.buscar') }}" role="search" data-busqueda>
        <label class="ar-sr" for="q">Buscar en el archivo</label>
        <input class="ar-busqueda__campo" id="q" type="search" name="q" value="{{ $f['q'] }}" placeholder="Buscar en el archivo…" autocomplete="off" data-atajo-busqueda>
        <button type="submit" class="ar-boton">Buscar</button>
        <button type="button" class="ar-boton ar-boton--linea ar-solo-movil" data-abrir-filtros aria-controls="filtros" aria-expanded="false">Filtros</button>
        <p class="ar-busqueda__ejemplos ar-tenue">Probá con un nombre, un lugar, una sede, un año o una etiqueta: “Banfield”, “1998”, “gira”.</p>

        <div class="ar-filtros" id="filtros" data-filtros>
            <div class="ar-filtros__cabecera ar-solo-movil">
                <strong>Filtros</strong>
                <button type="button" class="ar-visor__btn" data-cerrar-filtros aria-label="Cerrar filtros">✕</button>
            </div>
            <fieldset>
                <legend>Década</legend>
                <div class="ar-chips">
                    <label class="ar-chip"><input type="radio" name="decada" value="" @checked(empty($f['decada']))><span>Todas</span></label>
                    @foreach($linea['decadas'] as $d)
                        <label class="ar-chip"><input type="radio" name="decada" value="{{ $d['decada'] }}" @checked((string) $f['decada'] === (string) $d['decada'])><span>{{ $d['decada'] }}s</span></label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset class="ar-filtros__rango">
                <legend>Años</legend>
                <label>Desde <input type="number" name="desde" min="1900" max="{{ now()->year }}" value="{{ $f['desde'] }}" inputmode="numeric"></label>
                <label>Hasta <input type="number" name="hasta" min="1900" max="{{ now()->year }}" value="{{ $f['hasta'] }}" inputmode="numeric"></label>
            </fieldset>
            <fieldset>
                <legend>Tipo</legend>
                <div class="ar-chips">
                    <label class="ar-chip"><input type="radio" name="tipo" value="" @checked(empty($f['tipo']))><span>Todos</span></label>
                    @foreach(\App\Models\ArchivoFoto::TIPOS as $k => $v)
                        <label class="ar-chip"><input type="radio" name="tipo" value="{{ $k }}" @checked($f['tipo'] === $k)><span>{{ \Illuminate\Support\Str::before($v, ' (') }}</span></label>
                    @endforeach
                </div>
            </fieldset>
            @if($sedes->isNotEmpty())
                <label class="ar-filtros__select">Sede
                    <select name="sede">
                        <option value="">Todas</option>
                        @foreach($sedes as $s)<option value="{{ $s->id }}" @selected((string) $f['sede'] === (string) $s->id)>{{ $s->nombre }}</option>@endforeach
                    </select>
                </label>
            @endif
            @if($personas->isNotEmpty())
                <label class="ar-filtros__select">Persona
                    <select name="persona">
                        <option value="">Cualquiera</option>
                        @foreach($personas as $p)<option value="{{ $p['clave'] }}" @selected((string) $f['persona'] === (string) $p['clave'])>{{ $p['nombre'] }} ({{ $p['fotos'] }})</option>@endforeach
                    </select>
                </label>
            @endif
            @if($tags->isNotEmpty())
                <fieldset>
                    <legend>Etiquetas <span class="ar-tenue">(se combinan)</span></legend>
                    <div class="ar-chips">
                        @foreach($tags as $t)
                            <label class="ar-chip"><input type="checkbox" name="tags[]" value="{{ $t->slug }}" @checked(in_array($t->slug, $f['tags'], true))><span>#{{ $t->nombre }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
            @endif
            <div class="ar-filtros__acciones">
                <button type="submit" class="ar-boton">Aplicar</button>
                @if($hayFiltros)<a class="ar-enlace" href="{{ route('archivo.buscar') }}">Limpiar todo</a>@endif
            </div>
        </div>
    </form>

    <div class="ar-busqueda__resultados" aria-live="polite">
        @if($historias->isNotEmpty())
            <section aria-labelledby="res-historias" class="ar-bloque">
                <h2 class="ar-subtitulo" id="res-historias">Historias</h2>
                <div class="ar-historias">
                    @foreach($historias as $a)
                        @include('archivo.partials.tarjeta-historia', ['a' => $a])
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-labelledby="res-fotos" class="ar-bloque">
            <h2 class="ar-subtitulo" id="res-fotos">
                {{ $hayFiltros ? 'Fotografías encontradas' : 'Todo el archivo' }}
                <span class="ar-tenue">· {{ number_format($fotos->total(), 0, ',', '.') }}</span>
            </h2>
            @if($fotos->isEmpty())
                <div class="ar-vacio ar-vacio--chico">
                    <p>No encontramos fotos con esa búsqueda.</p>
                    <p class="ar-tenue">¿Tenés alguna? <a class="ar-enlace" href="{{ route('archivo.aportar') }}">Sumala al archivo</a></p>
                </div>
            @else
                <div class="ar-resultados">
                    @foreach($fotos as $foto)
                        <article class="ar-resultado">
                            @include('archivo.partials.figura', ['foto' => $foto, 'sizes' => '(min-width: 1100px) 22vw, (min-width: 700px) 33vw, 50vw'])
                            <div class="ar-resultado__texto">
                                @if($foto->anio)<span class="ar-mono ar-acento">{{ $foto->anio }}</span>@endif
                                <h3><a href="{{ $foto->url() }}">{{ $foto->tituloVisible() }}</a></h3>
                                @if($foto->acontecimiento)<span class="ar-tenue">{{ $foto->acontecimiento->titulo }}</span>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
                @if($fotos->hasPages())
                    <nav class="ar-paso" aria-label="Páginas de resultados">
                        @if($fotos->previousPageUrl())<a href="{{ $fotos->previousPageUrl() }}" rel="prev">← Anteriores</a>@else<span></span>@endif
                        <span class="ar-mono ar-tenue">{{ $fotos->currentPage() }} / {{ $fotos->lastPage() }}</span>
                        @if($fotos->nextPageUrl())<a href="{{ $fotos->nextPageUrl() }}" rel="next">Siguientes →</a>@endif
                    </nav>
                @endif
            @endif
        </section>
    </div>
</div>
@endsection
