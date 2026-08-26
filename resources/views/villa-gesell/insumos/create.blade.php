@extends('layouts.app')

@section('title', 'Nuevo insumo')
@section('page-title', 'Nuevo insumo')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    title="Nuevo insumo"
    eyebrow="Villa Gesell"
    subtitle="Material de la gira."
>

        <form action="{{ route('villa-gesell.insumos.store') }}" method="POST">
            @csrf
            @include('villa-gesell.insumos._form')
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Guardar</button>
                <a class="btn btn-link" href="{{ route('villa-gesell.insumos.index') }}">Cancelar</a>
            </div>
        </form>
</x-ito.shell-page>

@endsection
