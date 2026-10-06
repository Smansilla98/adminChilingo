{{-- Sedes en la sección del programa: mapa compacto y acceso al mapa completo. --}}
@php $sedes = \App\Support\SedesPublicas::listar(); @endphp
@if(count($sedes))
    <div class="mb-3">
        @include('programa.partials.sedes-mapa', ['sedes' => $sedes, 'alto' => '320px'])
    </div>
    <p><a href="{{ route('programa.sedes') }}" class="btn btn-sm btn-outline-primary">Ver el mapa completo <i class="bi bi-arrow-right" aria-hidden="true"></i></a></p>
@endif
