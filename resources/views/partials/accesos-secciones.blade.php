{{-- Cuadrícula de accesos del inicio: una tarjeta por sección, con todos los accesos a los que tiene permiso el usuario. --}}
@php($secciones = \App\Support\Navegacion::secciones(auth()->user(), request()))
@if($secciones !== [])
<section class="hub-section hub-accesos" aria-labelledby="hub-accesos-titulo">
    <h2 id="hub-accesos-titulo" class="hub-section-title"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Accesos por sección</h2>
    <div class="accesos-grid">
        @foreach($secciones as $seccion)
            <article class="acceso-card">
                <h3 class="acceso-card-titulo">{{ $seccion['label'] }}</h3>
                <ul class="acceso-card-lista">
                    @foreach($seccion['links'] as $link)
                        <li>
                            <a href="{{ $link['href'] }}" class="acceso-card-link{{ $link['active'] ? ' is-active' : '' }}" @if($link['active']) aria-current="page" @endif>
                                <i class="bi {{ $link['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $link['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </article>
        @endforeach
    </div>
</section>
@endif
