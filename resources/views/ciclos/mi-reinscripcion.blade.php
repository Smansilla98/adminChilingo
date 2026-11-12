@extends('layouts.app')

@section('title', 'Mi reinscripción')
@section('page-title', 'Mi reinscripción')

@section('content')
@if(session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@if(! $ciclo)
    <p class="text-muted">Todavía no hay un ciclo lectivo abierto para reinscribirse.</p>
@elseif($alumnos->isEmpty())
    <p class="text-muted">No tenés un alumno activo para reinscribir. Consultá con administración.</p>
@else
    <div class="ito-card p-4" style="max-width: 640px">
        <h2 class="h6">{{ $ciclo->nombre }}</h2>
        <ul class="list-unstyled mb-3">
            @foreach($alumnos as $a)
                <li class="mb-1">{{ $a->nombre_apellido }}:
                    <strong>{{ ($estados[$a->id] ?? null) === \App\Models\Reinscripcion::CONFIRMADA ? 'reinscripto' : 'pendiente' }}</strong>
                </li>
            @endforeach
        </ul>
        @if($alumnos->contains(fn ($a) => ($estados[$a->id] ?? null) === \App\Models\Reinscripcion::PENDIENTE))
            <form method="POST" action="{{ route('mi-reinscripcion.confirmar') }}">
                @csrf
                <button class="btn btn-primary">Confirmar mi reinscripción</button>
            </form>
        @endif
    </div>
@endif
@endsection
