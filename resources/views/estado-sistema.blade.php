@extends('layouts.app')

@section('title', 'Estado del sistema')
@section('page-title', 'Estado del sistema')

@section('content')
@php
    $tonos = ['ok' => 'success', 'aviso' => 'warning', 'error' => 'danger'];
    $etiquetas = ['ok' => 'Bien', 'aviso' => 'Revisar', 'error' => 'Falla'];
    $iconos = ['ok' => 'bi-check-circle', 'aviso' => 'bi-exclamation-circle', 'error' => 'bi-x-octagon'];
    $errores = collect($chequeos)->where('estado', 'error')->count();
@endphp
<x-ito.shell-page title="Estado del sistema" subtitle="Configuraciones que no se ven desde las pantallas y que pueden hacer fallar mails, archivos o cobros." :plain="true">
    @if($errores > 0)
        <div class="alert alert-danger" role="alert"><strong>{{ $errores }} {{ $errores === 1 ? 'problema necesita' : 'problemas necesitan' }} arreglo.</strong> Se resuelven en las variables de Railway.</div>
    @endif
    <div class="ito-card p-0" style="max-width: 860px">
        @foreach($chequeos as $c)
            <section class="d-flex gap-3 p-3 {{ $loop->first ? '' : 'border-top' }}" aria-labelledby="chq-{{ $c['clave'] }}">
                <i class="bi {{ $iconos[$c['estado']] }} fs-4 text-{{ $tonos[$c['estado']] }}" aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h2 class="h6 mb-0" id="chq-{{ $c['clave'] }}">{{ $c['titulo'] }}</h2>
                        <x-ito.status :tone="$tonos[$c['estado']]" :label="$etiquetas[$c['estado']]" />
                    </div>
                    <p class="mb-0 mt-1">{{ $c['detalle'] }}</p>
                    @if($c['arreglo'])
                        <p class="mb-0 mt-1 small text-muted"><strong>Cómo arreglarlo:</strong> {{ $c['arreglo'] }}</p>
                    @endif
                    @if(! empty($c['descarga']))
                        <a href="{{ route('estado-sistema.respaldo') }}" class="btn btn-sm btn-outline-secondary mt-2"><i class="bi bi-download" aria-hidden="true"></i> Descargar el último backup</a>
                        <p class="mb-0 mt-1 small text-muted">Contiene todos los datos de la escuela: guardalo en un lugar seguro y no lo compartas.</p>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</x-ito.shell-page>
@endsection
