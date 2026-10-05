{{-- Resultado visual de un acontecimiento (búsqueda, relacionados). $a --}}
<a class="ar-historia-tarjeta" href="{{ $a->url() }}">
    @if($a->portada?->esPublica())
        <span class="ar-historia-tarjeta__foto" style="--ar-color: {{ $a->portada->color ?: '#1d1b19' }}">
            <img src="{{ $a->portada->imagenUrl(400) }}" srcset="{{ $a->portada->srcset() }}" sizes="(min-width: 900px) 280px, 40vw" alt="" loading="lazy" decoding="async">
        </span>
    @endif
    <span class="ar-historia-tarjeta__texto">
        @if($a->anio)<span class="ar-mono ar-acento">{{ $a->anio }}</span>@endif
        <strong>{{ $a->titulo }}</strong>
        @if($a->bajada || $a->descripcion)<span class="ar-tenue">{{ \Illuminate\Support\Str::limit($a->bajada ?: $a->descripcion, 140) }}</span>@endif
        <span class="ar-enlace">Ver historia <span aria-hidden="true">→</span></span>
    </span>
</a>
