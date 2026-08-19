@extends('layouts.app')

@section('title', 'Editar gasto de gira')
@section('page-title', 'Editar gasto')

@section('content')
@include('villa-gesell.partials.nav')
<div class="card">
    <div class="card-header">Editar gasto</div>
    <div class="card-body">
        <form action="{{ route('villa-gesell.gastos.update', $gasto) }}" method="POST">
            @csrf
            @method('PUT')
            @include('villa-gesell.gastos._form', ['diasGira' => app(\App\Services\VillaGesellGiraService::class)->config()->cantidadDias()])
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Guardar</button>
                <a class="btn btn-link" href="{{ route('villa-gesell.gastos.index') }}">Volver</a>
            </div>
        </form>
    </div>
</div>
@endsection
