@extends('layouts.app')

@section('title', 'Editar gasto de gira')
@section('page-title', 'Editar gasto')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    title="Editar gasto"
    eyebrow="Villa Gesell"
    subtitle="Corregí el gasto de la gira."
>

        <form action="{{ route('villa-gesell.gastos.update', $gasto) }}" method="POST">
            @csrf
            @method('PUT')
            @include('villa-gesell.gastos._form', ['diasGira' => app(\App\Services\VillaGesellGiraService::class)->config()->cantidadDias()])
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Guardar</button>
                <a class="btn btn-link" href="{{ route('villa-gesell.gastos.index') }}">Volver</a>
            </div>
        </form>
</x-ito.shell-page>

@endsection
