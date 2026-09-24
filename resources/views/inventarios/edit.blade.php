@extends('layouts.app')

@section('title', 'Editar ítem')
@section('page-title', 'Inventario — Editar ítem')

@section('content')
<x-ito.shell-page :title="'Editar '.$inventario->nombre" :subtitle="$inventario->codigo ?: $inventario->sede?->nombre" :plain="true">
    <form action="{{ route('inventarios.update', $inventario) }}" method="POST">
        @csrf
        @method('PUT')
        @include('inventarios._form', ['item' => $inventario])
        <x-ito.form-actions :cancel="route('inventarios.show', $inventario)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
