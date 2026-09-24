@extends('layouts.app')

@section('title', 'Nueva sede')
@section('page-title', 'Nueva sede')

@section('content')
<x-ito.shell-page title="Nueva sede" subtitle="Datos de la sede y cómo se reparte la cuota." :plain="true">
    <form action="{{ route('sedes.store') }}" method="POST">
        @csrf
        @include('sedes._form')
        <x-ito.form-actions :cancel="route('sedes.index')" submit="Crear sede" />
    </form>
</x-ito.shell-page>
@endsection
