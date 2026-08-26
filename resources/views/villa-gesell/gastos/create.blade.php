@extends('layouts.app')

@section('title', 'Nuevo gasto de gira')
@section('page-title', 'Nuevo gasto')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    title="Gasto de la gira"
    eyebrow="Villa Gesell"
    subtitle="Egreso de la campaña."
>

        <form action="{{ route('villa-gesell.gastos.store') }}" method="POST">
            @csrf
            @include('villa-gesell.gastos._form', ['diasGira' => app(\App\Services\VillaGesellGiraService::class)->config()->cantidadDias()])
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Guardar</button>
                <a class="btn btn-link" href="{{ route('villa-gesell.gastos.index') }}">Cancelar</a>
            </div>
        </form>
</x-ito.shell-page>

@endsection
