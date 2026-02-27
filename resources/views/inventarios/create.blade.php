@extends('layouts.app')

@section('title', 'Nuevo ítem')
@section('page-title', 'Inventario — Nuevo ítem')

@section('content')
<div class="card">
    <div class="card-header py-3">Nuevo ítem de inventario</div>
    <div class="card-body">
        <form action="{{ route('inventarios.store') }}" method="POST">
            @csrf
            @include('inventarios._form', ['item' => null, 'values' => $defaults])

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ route('inventarios.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

