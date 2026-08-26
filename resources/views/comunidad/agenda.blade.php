@extends('layouts.publico')

@section('title', 'Agenda')
@section('publico-brand', 'La Chilinga')

@section('content')
<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Comunidad · sin cuenta</p>
        <h1>Agenda abierta</h1>
        <p class="biblio-lead">Fechas de la escuela: ensayos, muestras y shows. Acá no hay padrones ni datos de alumnos — solo lo que la comunidad puede saber.</p>
    </div>
</div>

<section class="mb-4" aria-labelledby="ag-ev">
    <h2 id="ag-ev" class="hub-section-title">Eventos</h2>
    @if($eventos->isEmpty())
        <p class="text-muted">Todavía no hay eventos publicados en la agenda. Cuando haya una fecha, aparece acá.</p>
    @else
        <ul class="ito-feed">
            @foreach($eventos as $ev)
            <li>
                <strong>{{ $ev->titulo }}</strong>
                · {{ $ev->fecha?->locale('es')->translatedFormat('d M Y') }}
                @if($ev->sede)<span class="text-muted"> · {{ $ev->sede->nombre }}</span>@endif
                @if($ev->tipo_evento)<span class="text-muted"> · {{ $ev->tipo_evento }}</span>@endif
            </li>
            @endforeach
        </ul>
    @endif
</section>

<section aria-labelledby="ag-sh">
    <h2 id="ag-sh" class="hub-section-title">Shows</h2>
    @if($shows->isEmpty())
        <p class="text-muted">No hay shows próximos cargados.</p>
    @else
        <ul class="ito-feed">
            @foreach($shows as $sh)
            <li>
                <strong>{{ $sh->titulo }}</strong>
                · {{ $sh->fecha?->locale('es')->translatedFormat('d M Y') }}
                @if($sh->lugar)<span class="text-muted"> · {{ $sh->lugar }}</span>@endif
                @if($sh->convocatoria_abierta) · convocatoria abierta @endif
            </li>
            @endforeach
        </ul>
    @endif
</section>
@endsection
