@extends('layouts.guest')

@section('title', 'Preinscripción')
@section('guest-title', 'Preinscripción recibida')
@section('guest-subtitle', 'Gracias, '.$pre->nombre.'.')

@section('content')
@if($pre->estado === \App\Models\Preinscripcion::PENDIENTE)
    <div class="alert alert-success" role="status">{{ \App\Http\Controllers\PreinscripcionPublicController::EXITO }}</div>
@else
    <div class="alert alert-info" role="status">Tu preinscripción ya fue procesada. Si tenés dudas, escribí a la sede.</div>
@endif
<a href="{{ route('inscripcion.create') }}">Hacer otra preinscripción</a>
@endsection
