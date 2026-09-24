@extends('layouts.app')

@section('title', 'Nuevo ítem')
@section('page-title', 'Inventario — Nuevo ítem')

@section('content')
<x-ito.shell-page title="Nuevo ítem" subtitle="Tambor, parche, accesorio o insumo." :plain="true">
    <form action="{{ route('inventarios.store') }}" method="POST">
        @csrf
        @include('inventarios._form', ['item' => null, 'values' => $defaults])
        <x-ito.form-actions :cancel="route('inventarios.index')" submit="Guardar ítem" />
    </form>
</x-ito.shell-page>
@endsection
