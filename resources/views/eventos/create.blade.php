@extends('layouts.app')

@section('title', 'Nuevo evento')
@section('page-title', 'Nuevo evento')

@section('content')
<x-ito.shell-page title="Nuevo evento" subtitle="Clase especial, ensayo, muestra o reunión." :plain="true">
    <form action="{{ route('eventos.store') }}" method="POST">
        @csrf
        @include('eventos._form')
        <x-ito.form-actions :cancel="route('eventos.index')" submit="Crear evento" />
    </form>
</x-ito.shell-page>
@endsection
