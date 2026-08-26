@extends('layouts.app')

@section('title', 'Editar insumo')
@section('page-title', 'Editar insumo')

@section('content')
@include('villa-gesell.partials.nav')
<x-ito.shell-page
    title="Editar insumo"
    eyebrow="Villa Gesell"
    subtitle="Corregí el insumo."
>

        <form action="{{ route('villa-gesell.insumos.update', $insumo) }}" method="POST">
            @csrf
            @method('PUT')
            @include('villa-gesell.insumos._form')
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Guardar</button>
                <a class="btn btn-link" href="{{ route('villa-gesell.insumos.index') }}">Volver</a>
            </div>
        </form>
</x-ito.shell-page>

@endsection
