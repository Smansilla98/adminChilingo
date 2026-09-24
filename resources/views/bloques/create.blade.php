@extends('layouts.app')

@section('title', 'Nuevo bloque')
@section('page-title', 'Nuevo bloque')

@section('content')
<x-ito.shell-page title="Nuevo bloque" subtitle="Nombre, sede, docente e instrumentos. Los horarios se cargan después, al editarlo." :plain="true">
    <form action="{{ route('bloques.store') }}" method="POST">
        @csrf
        @include('bloques._form')
        <x-ito.form-actions :cancel="route('bloques.index')" submit="Crear bloque" />
    </form>
</x-ito.shell-page>
@endsection
