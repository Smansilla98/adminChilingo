{{--
    Fotografía con derivados responsive y placeholder.
    $foto (ArchivoFoto) · $sizes · $clase · $eager · $epigrafe (bool) · $visor (bool, default true)
--}}
@php
    $abreVisor = $visor ?? true;
    $ratio = $foto->ancho && $foto->alto ? $foto->ancho.' / '.$foto->alto : '3 / 2';
    $estilo = '--ar-color:'.($foto->color ?: '#1d1b19').';--ar-ratio:'.$ratio.';'.($foto->placeholder ? "--ar-ph:url('".$foto->placeholder."');" : '');
@endphp
<figure @class(['ar-fig', $clase ?? '', 'ar-fig--vertical' => $foto->esVertical(), 'ar-fig--doc' => $foto->tipo === 'documento']) style="{{ $estilo }}" data-revelar>
    <{{ $abreVisor ? 'button type=button' : 'div' }} class="ar-fig__marco" @if($abreVisor) data-visor="{{ $foto->id }}" aria-label="Ampliar la foto: {{ $foto->tituloVisible() }}{{ $foto->anio ? ', '.$foto->anio : '' }}" @endif>
        <img class="ar-fig__img"
             src="{{ $foto->imagenUrl(800) }}"
             srcset="{{ $foto->srcset() }}"
             sizes="{{ $sizes ?? '(min-width: 1100px) 60vw, 100vw' }}"
             alt="{{ $foto->textoAlternativo() }}"
             @if($foto->ancho) width="{{ $foto->ancho }}" height="{{ $foto->alto }}" @endif
             loading="{{ ! empty($eager) ? 'eager' : 'lazy' }}"
             @if(! empty($eager)) fetchpriority="high" @endif
             decoding="async">
    </{{ $abreVisor ? 'button' : 'div' }}>
    @if(! empty($epigrafe))
        <figcaption class="ar-fig__epigrafe">
            @if($foto->anio)<span class="ar-mono">{{ $foto->anio }}</span>@endif
            <span>{{ $foto->tituloVisible() }}</span>
            @if($foto->fotografo)<span class="ar-tenue">Foto: {{ $foto->fotografo }}</span>@endif
        </figcaption>
    @endif
</figure>
