@extends('layouts.app')

@section('title', 'Nuevo show')
@section('page-title', 'Nuevo show')

@section('content')
<x-ito.shell-page title="Nuevo show" subtitle="Presentación: fecha, lugar y bloques que participan." :plain="true">
    <form action="{{ route('shows.store') }}" method="POST">
        @csrf
        @include('shows._form')
        <x-ito.form-actions :cancel="route('shows.index')" submit="Crear show" />
    </form>
</x-ito.shell-page>
@endsection
