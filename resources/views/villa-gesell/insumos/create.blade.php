@extends('layouts.app')

@section('title', 'Nuevo insumo')
@section('page-title', 'Nuevo insumo')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    :plain="true"
    title="Nuevo insumo"
    subtitle="Material de la gira."
>

        <form action="{{ route('villa-gesell.insumos.store') }}" method="POST">
            @csrf
            @include('villa-gesell.insumos._form')
            <x-ito.form-actions :cancel="route('villa-gesell.insumos.index')" submit="Guardar" />
        </form>
</x-ito.shell-page>

@endsection
