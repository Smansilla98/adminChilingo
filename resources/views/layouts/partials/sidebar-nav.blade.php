@php
    $nav = \App\Support\Navegacion::para(auth()->user(), request());
@endphp

@foreach($nav['top'] as $link)
    <a class="side-link side-link--top {{ $link['active'] ? 'active' : '' }}"
       href="{{ $link['href'] }}"
       title="{{ $link['label'] }}"
       @if($link['active']) aria-current="page" @endif>
        <i class="bi {{ $link['icon'] }}" aria-hidden="true"></i>
        <span class="side-link-text">{{ $link['label'] }}</span>
    </a>
@endforeach

@foreach($nav['grupos'] as $grupo)
    <div class="nav-group {{ $grupo['abierto'] ? 'open is-current' : '' }}" data-nav-group="{{ $grupo['clave'] }}">
        <button type="button" class="nav-group-btn" aria-expanded="{{ $grupo['abierto'] ? 'true' : 'false' }}" aria-controls="nav-grupo-{{ $grupo['clave'] }}">
            <span class="nav-group-label">{{ $grupo['label'] }}</span>
            <svg class="nav-group-chev" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.5"/></svg>
        </button>
        <div class="nav-group-links" id="nav-grupo-{{ $grupo['clave'] }}">
            @foreach($grupo['links'] as $link)
                <a class="side-link side-link--nested {{ $link['active'] ? 'active' : '' }}"
                   href="{{ $link['href'] }}"
                   title="{{ $link['label'] }}"
                   @if($link['active']) aria-current="page" @endif>
                    <i class="bi {{ $link['icon'] }}" aria-hidden="true"></i>
                    <span class="side-link-text">{{ $link['label'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endforeach
