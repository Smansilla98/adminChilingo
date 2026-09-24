@extends('layouts.app')

@section('title', 'Editar insumo')
@section('page-title', 'Editar insumo')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    :plain="true"
    title="Editar insumo"
    subtitle="Corregí el insumo."
>

        <form action="{{ route('villa-gesell.insumos.update', $insumo) }}" method="POST">
            @csrf
            @method('PUT')
            @include('villa-gesell.insumos._form')
            <x-ito.form-actions :cancel="route('villa-gesell.insumos.index')" submit="Guardar" />
        </form>
</x-ito.shell-page>

@endsection
