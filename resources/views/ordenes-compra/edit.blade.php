@extends('layouts.app')

@section('title', 'Editar orden de compra')
@section('page-title', 'Editar orden de compra')

@section('content')
<x-ito.shell-page :title="'Editar orden #'.$orden->id" :subtitle="$orden->sede?->nombre" :plain="true">
    <form action="{{ route('ordenes-compra.update', $orden) }}" method="POST">
        @csrf
        @method('PUT')
        @include('ordenes-compra._form')
        <x-ito.form-actions :cancel="route('ordenes-compra.show', $orden)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
