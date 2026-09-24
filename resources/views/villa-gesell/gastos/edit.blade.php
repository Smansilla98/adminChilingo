@extends('layouts.app')

@section('title', 'Editar gasto de gira')
@section('page-title', 'Editar gasto')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    :plain="true"
    title="Editar gasto"
    subtitle="Corregí el gasto de la gira."
>

        <form action="{{ route('villa-gesell.gastos.update', $gasto) }}" method="POST">
            @csrf
            @method('PUT')
            @include('villa-gesell.gastos._form', ['diasGira' => app(\App\Services\VillaGesellGiraService::class)->config()->cantidadDias()])
            <x-ito.form-actions :cancel="route('villa-gesell.gastos.index')" submit="Guardar" />
        </form>
</x-ito.shell-page>

@endsection
