@extends('layouts.app')

@section('title', 'Editar insumo')
@section('page-title', 'Editar insumo')

@section('content')
@include('villa-gesell.partials.nav')
<div class="card">
    <div class="card-header">Editar insumo</div>
    <div class="card-body">
        <form action="{{ route('villa-gesell.insumos.update', $insumo) }}" method="POST">
            @csrf
            @method('PUT')
            @include('villa-gesell.insumos._form')
            <div class="mt-3">
                <button class="btn btn-primary" type="submit">Guardar</button>
                <a class="btn btn-link" href="{{ route('villa-gesell.insumos.index') }}">Volver</a>
            </div>
        </form>
    </div>
</div>
@endsection
