@extends('layouts.publico')

@section('title', $toque->nombre.' — Programa')
@section('publico-brand', 'Programa')

@section('content')
<div class="ito-card p-4 p-md-5 text-center mx-auto" style="max-width: 36rem">
    <p class="biblio-eyebrow mb-2">{{ (int) $toque->año }}° año</p>
    <h1 class="h3 mb-3">{{ $toque->nombre }}</h1>
    <p class="mb-2">Este toque se trabaja en <strong>{{ (int) $toque->año }}° año</strong>. Su material se abre cuando tu bloque llegue a ese año.</p>
    <p class="text-muted mb-4">Ahora podés ver todo el material hasta <strong>{{ $anioAlumno }}° año</strong>. Si cursás en otro bloque y no te aparece, avisale a tu profe.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="{{ route('programa.partituras.index') }}" class="btn btn-primary">Ver mi material</a>
        <a href="{{ route('programa.index') }}" class="btn btn-outline-secondary">Volver al programa</a>
    </div>
</div>
@endsection
