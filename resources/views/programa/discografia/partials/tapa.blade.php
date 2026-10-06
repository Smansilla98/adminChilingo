{{-- Tapa del disco: la portada subida o una generada con su color. --}}
@php $mini = $mini ?? false; @endphp
<div class="disco-tapa {{ $mini ? 'disco-tapa--mini' : '' }}" style="--disco: {{ $disco->color }}">
    @if($disco->portada_path)
        <img src="{{ route('programa.discos.portada', $disco) }}" alt="Tapa de {{ $disco->titulo }}" loading="lazy" decoding="async">
    @else
        <span class="disco-tapa__anio" aria-hidden="true">{{ $disco->anio }}</span>
        <span class="disco-tapa__texto" aria-hidden="true">
            <span class="disco-tapa__marca">La Chilinga</span>
            <span class="disco-tapa__titulo">{{ $disco->titulo }}</span>
        </span>
    @endif
</div>
