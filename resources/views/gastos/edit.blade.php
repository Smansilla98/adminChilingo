@extends('layouts.app')

@section('title', 'Editar gasto')
@section('page-title', 'Editar gasto')

@section('content')
<x-ito.shell-page :title="'Editar gasto #'.$gasto->id" :subtitle="$gasto->fecha?->format('d/m/Y')" :plain="true">
    <form action="{{ route('gastos.update', $gasto) }}" method="POST">
        @csrf
        @method('PUT')
        @include('gastos._form', ['gasto' => $gasto])
        <x-ito.form-actions :cancel="route('gastos.show', $gasto)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
