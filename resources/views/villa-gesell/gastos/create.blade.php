@extends('layouts.app')

@section('title', 'Nuevo gasto de gira')
@section('page-title', 'Nuevo gasto')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    :plain="true"
    title="Gasto de la gira"
    subtitle="Egreso de la campaña."
>

        <form action="{{ route('villa-gesell.gastos.store') }}" method="POST">
            @csrf
            @include('villa-gesell.gastos._form', ['diasGira' => app(\App\Services\VillaGesellGiraService::class)->config()->cantidadDias()])
            <x-ito.form-actions :cancel="route('villa-gesell.gastos.index')" submit="Guardar" />
        </form>
</x-ito.shell-page>

@endsection
