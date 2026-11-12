@extends('layouts.app')

@section('title', 'Progreso por toque')
@section('page-title', 'Progreso por toque')

@section('content')
<x-ito.shell-page title="Progreso por toque" eyebrow="Pedagogía" subtitle="Elegí un bloque para ver, toque por toque, en qué anda cada alumno con los toques de su año.">
    <x-slot:actions>
        <a href="{{ route('progreso.reporte') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-data"></i> Reporte por sede</a>
    </x-slot:actions>

    <p class="text-muted small">No es una nota: sirve para acompañar. Cada toque está en «Todavía no», «Lo está trabajando» o «Lo toca con el bloque».</p>

    @forelse($bloques as $sede => $lista)
        <section class="ito-card p-3 mb-3" aria-label="{{ $sede }}">
            <h2 class="h6 mb-2">{{ $sede }}</h2>
            <div class="list-group list-group-flush">
                @foreach($lista as $b)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" href="{{ route('progreso.bloque', $b) }}">
                        <span>{{ $b->nombre }}</span>
                        <span class="badge bg-secondary">{{ $b->año ? $b->año.'° año' : 'Sin año' }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @empty
        <x-ito.empty title="No tenés bloques para mirar" description="Cuando des clase en un bloque o coordines una sede, aparecen acá." />
    @endforelse
</x-ito.shell-page>
@endsection
