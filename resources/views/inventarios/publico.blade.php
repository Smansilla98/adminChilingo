@extends('layouts.publico')

@section('title', 'Tambor '.$ficha['codigo'])
@section('publico-brand', 'Inventario')

@section('content')
<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Instrumento de la escuela</p>
        <h1 class="font-monospace">{{ $ficha['codigo'] }}</h1>
        <p class="biblio-lead">{{ $ficha['nombre'] }} · {{ $ficha['tipo'] }}. Esta ficha no muestra a quién se lo prestó ni datos personales.</p>
    </div>
</div>
<dl class="hub-meta mb-4">
    <div>
        <dt>Sede</dt>
        <dd>{{ $ficha['sede'] ?: '—' }}</dd>
    </div>
    <div>
        <dt>Estado</dt>
        <dd>{{ $ficha['estado'] }}</dd>
    </div>
    @if($ficha['marca'])
    <div>
        <dt>Marca</dt>
        <dd>{{ $ficha['marca'] }}</dd>
    </div>
    @endif
    @if($ficha['medida'])
    <div>
        <dt>Medida</dt>
        <dd>{{ $ficha['medida'] }}</dd>
    </div>
    @endif
</dl>
<p class="small text-muted mb-0">Si encontraste este tambor, avisá en la sede. No hace falta cuenta para ver esta etiqueta.</p>
@endsection
