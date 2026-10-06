{{-- Discos en la sección del programa: tapas que llevan a cada ficha. --}}
@php $discos = \Illuminate\Support\Facades\Schema::hasTable('discos') ? \App\Models\Disco::query()->publicados()->get() : collect(); @endphp
@if($discos->isNotEmpty())
<div class="disco-mini-fila">
    @foreach($discos as $disco)
        <a href="{{ route('programa.discos.show', $disco) }}" aria-label="{{ $disco->etiqueta() }}">
            @include('programa.discografia.partials.tapa', ['disco' => $disco, 'mini' => true])
            <span><strong>{{ $disco->titulo }}</strong> <span class="text-muted">{{ $disco->anio }}</span></span>
        </a>
    @endforeach
</div>
<p><a href="{{ route('programa.discos.index') }}" class="btn btn-sm btn-outline-primary">Ver la discografía completa <i class="bi bi-arrow-right" aria-hidden="true"></i></a></p>
@endif
