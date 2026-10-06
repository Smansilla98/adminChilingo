@extends('layouts.publico')

@section('title', 'Sedes — Programa')
@section('publico-brand', 'Programa')

@section('content')
<nav aria-label="breadcrumb" class="mb-2">
    <ol class="breadcrumb mb-0 small">
        <li class="breadcrumb-item"><a href="{{ route('programa.index') }}">Programa</a></li>
        <li class="breadcrumb-item active" aria-current="page">Sedes</li>
    </ol>
</nav>

<div class="biblio-hero">
    <div>
        <p class="biblio-eyebrow">Dónde tocamos</p>
        <h1>Sedes de La Chilinga</h1>
        <p class="biblio-lead">Elegí la sede más cerca tuyo. Tocá una sede para verla en el mapa o abrí «Cómo llegar» para ir con tu app de mapas.</p>
    </div>
    <div class="prog-hero-actions">
        <a href="{{ route('programa.index') }}#contenido-programa" class="btn btn-outline-secondary">Volver al programa</a>
        @if(auth()->user()?->isAdmin())
            <a href="{{ route('sedes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-pencil" aria-hidden="true"></i> Administrar sedes</a>
        @endif
    </div>
</div>

@include('programa.partials.sedes-mapa', ['sedes' => $sedes, 'alto' => 'min(70vh, 560px)'])
@endsection
