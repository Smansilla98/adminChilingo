{{--
    Mapa de sedes con su lista. $sedes: SedesPublicas::listar(). $alto: alto del mapa.
    Las sedes sin ubicación se listan igual, con "Cómo llegar" por dirección.
--}}
@php
    $alto = $alto ?? '480px';
    $conPunto = collect($sedes)->filter(fn ($s) => $s['lat'] !== null && $s['lng'] !== null);
    $esAdmin = auth()->user()?->isAdmin();
@endphp
@if(count($sedes))
<div class="sedes-layout">
    <ul class="sedes-lista" aria-label="Sedes">
        @foreach($sedes as $s)
            <li class="sede-item" data-sede-item>
                <h3>{{ $s['nombre'] }}</h3>
                @if($s['direccion'])<p>{{ $s['direccion'] }}</p>@endif
                <div class="sede-item__acciones">
                    @if($s['lat'] !== null)
                        <button type="button" class="btn btn-outline-secondary" data-sede-foco="{{ $s['id'] }}">
                            <i class="bi bi-geo-alt" aria-hidden="true"></i> Ver en el mapa
                        </button>
                    @endif
                    @if($s['como_llegar'])
                        <a class="btn btn-outline-secondary" href="{{ $s['como_llegar'] }}" target="_blank" rel="noopener">
                            <i class="bi bi-signpost-split" aria-hidden="true"></i> Cómo llegar
                        </a>
                    @endif
                </div>
                @if($s['lat'] === null)
                    <span class="sede-sin-punto">Todavía no está ubicada en el mapa.
                        @if($esAdmin)<a href="{{ route('sedes.edit', $s['id']) }}">Ubicarla</a>@endif
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
    @if($conPunto->isNotEmpty())
        <div class="sm-mapa" style="height: {{ $alto }}" data-mapa-sedes data-etiquetas="1"
             data-sedes='@json($sedes)' role="region" aria-label="Mapa de las sedes" tabindex="0"></div>
        @once
            @push('scripts')
                @vite(['resources/js/sedes-mapa.js'])
            @endpush
        @endonce
    @endif
</div>
@else
    <p class="text-muted mb-0">Todavía no hay sedes publicadas en el mapa.</p>
@endif
