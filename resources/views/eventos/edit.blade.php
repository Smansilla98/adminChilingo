@extends('layouts.app')

@section('title', 'Editar evento')
@section('page-title', 'Editar evento')

@section('content')
<x-ito.shell-page :title="'Editar '.$evento->titulo" :subtitle="$evento->fecha?->locale('es')->translatedFormat('l j \d\e F Y')" :plain="true">
    <form action="{{ route('eventos.update', $evento) }}" method="POST">
        @csrf
        @method('PUT')
        @include('eventos._form', ['evento' => $evento])
        <x-ito.form-actions :cancel="route('eventos.show', $evento)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
