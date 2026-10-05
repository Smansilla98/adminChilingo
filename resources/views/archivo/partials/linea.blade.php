{{--
    Línea de tiempo: riel lateral en escritorio y barra de décadas en el móvil.
    Marca el año visible mientras se lee (archivo.js). $linea = ArchivoConsultas::linea()
--}}
@php $decadas = collect($linea['decadas'] ?? []); @endphp
@if($decadas->isNotEmpty())
<nav class="ar-riel" aria-label="Línea de tiempo" data-riel>
    <p class="ar-riel__actual ar-mono" aria-live="polite"><span data-anio-actual>{{ $decadas->first()['anios'][0]['anio'] ?? '' }}</span></p>
    <ol class="ar-riel__decadas">
        @foreach($decadas as $d)
            <li>
                <a href="{{ route('archivo.anio', $d['anios'][0]['anio']) }}" class="ar-riel__decada" data-ir-decada="{{ $d['decada'] }}">{{ $d['decada'] }}s</a>
                <ol class="ar-riel__anios">
                    @foreach($d['anios'] as $x)
                        <li><a href="{{ route('archivo.anio', $x['anio']) }}" data-ir-anio="{{ $x['anio'] }}" title="{{ $x['fotos'] }} fotos">{{ $x['anio'] }}</a></li>
                    @endforeach
                </ol>
            </li>
        @endforeach
    </ol>
</nav>
<nav class="ar-decadas-movil" aria-label="Décadas" data-decadas-movil>
    <button type="button" class="ar-decadas-movil__flecha" data-decada-paso="-1" aria-label="Década anterior">‹</button>
    <div class="ar-decadas-movil__lista">
        @foreach($decadas as $d)
            <a href="{{ route('archivo.anio', $d['anios'][0]['anio']) }}" data-ir-decada="{{ $d['decada'] }}">{{ $d['decada'] }} — {{ $d['decada'] + 9 }}</a>
        @endforeach
    </div>
    <button type="button" class="ar-decadas-movil__flecha" data-decada-paso="1" aria-label="Década siguiente">›</button>
</nav>
@endif
