@extends('layouts.app')

@section('title', 'Mis liquidaciones')
@section('page-title', 'Mis liquidaciones')

@section('content')
<x-ito.shell-page title="Mis liquidaciones" eyebrow="Docente" subtitle="Lo que se te liquidó cada mes, con su recibo.">
    @forelse($liqs as $l)
        <div class="ito-card p-3 mb-2 d-flex flex-wrap gap-2 align-items-center">
            <span class="flex-grow-1"><strong>{{ $l->periodo() }}</strong> · {{ $l->etiquetaEstado() }} · $ {{ number_format($l->monto_total, 2, ',', '.') }}</span>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('liquidaciones.recibo', $l) }}" target="_blank" rel="noopener">Recibo</a>
        </div>
    @empty
        <x-ito.empty title="Todavía no tenés liquidaciones aprobadas" />
    @endforelse
</x-ito.shell-page>
@endsection
