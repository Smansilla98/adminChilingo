@php
    $vgLinks = [
        ['match' => 'villa-gesell.index', 'route' => 'villa-gesell.index', 'label' => 'Resumen'],
        ['match' => 'villa-gesell.inscriptos.*', 'route' => 'villa-gesell.inscriptos.index', 'label' => 'Inscriptos'],
        ['match' => 'villa-gesell.calendario', 'route' => 'villa-gesell.calendario', 'label' => 'Calendario'],
        ['match' => 'villa-gesell.insumos.*', 'route' => 'villa-gesell.insumos.index', 'label' => 'Insumos'],
        ['match' => 'villa-gesell.gastos.*', 'route' => 'villa-gesell.gastos.index', 'label' => 'Gastos'],
        ['match' => 'villa-gesell.plan', 'route' => 'villa-gesell.plan', 'label' => 'Plan de gastos'],
    ];
@endphp
<nav class="ito-subnav mb-3" aria-label="Villa Gesell">
    <ul class="nav nav-pills flex-wrap gap-1">
        @foreach($vgLinks as $link)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs($link['match']) ? 'active' : '' }}" href="{{ route($link['route']) }}">{{ $link['label'] }}</a>
            </li>
        @endforeach
    </ul>
</nav>
