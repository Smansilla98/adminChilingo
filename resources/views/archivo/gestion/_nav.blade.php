{{-- Navegación interna del backoffice del archivo. --}}
@php
    $pendientesNav = \App\Models\ArchivoFoto::query()->where('estado', 'pendiente')->count();
    $u = auth()->user();
    $links = [
        ['archivo.gestion.tablero', 'Tablero', 'bi-speedometer2', 'archivo.gestion.tablero'],
        ['archivo.gestion.fotos', 'Fotos', 'bi-images', 'archivo.gestion.fotos*'],
        $u->can('create', \App\Models\ArchivoFoto::class) ? ['archivo.gestion.subir', 'Subir', 'bi-cloud-arrow-up', 'archivo.gestion.subir'] : null,
        ['archivo.gestion.moderacion', 'Moderación', 'bi-shield-check', 'archivo.gestion.moderacion'],
        ['archivo.gestion.capitulos.index', 'Capítulos', 'bi-book', 'archivo.gestion.capitulos.*'],
        ['archivo.gestion.eventos.index', 'Acontecimientos', 'bi-calendar2-event', 'archivo.gestion.eventos.*'],
        ['archivo.gestion.etiquetas.index', 'Etiquetas', 'bi-hash', 'archivo.gestion.etiquetas.*'],
    ];
@endphp
<nav class="agx-nav" aria-label="Archivo histórico">
    @foreach(array_filter($links) as [$ruta, $label, $icono, $patron])
        <a href="{{ route($ruta) }}" @class(['agx-nav__link', 'is-activo' => request()->routeIs($patron)]) @if(request()->routeIs($patron)) aria-current="page" @endif>
            <i class="bi {{ $icono }}" aria-hidden="true"></i> {{ $label }}
            @if($ruta === 'archivo.gestion.moderacion' && $pendientesNav > 0)<span class="badge rounded-pill text-bg-warning">{{ $pendientesNav }}</span>@endif
        </a>
    @endforeach
    <a href="{{ route('archivo.index') }}" class="agx-nav__link agx-nav__link--fin" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> Ver archivo público</a>
</nav>
