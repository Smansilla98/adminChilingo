@extends('layouts.app')

@section('title', 'Editar ítem')
@section('page-title', 'Inventario — Editar ítem')

@section('content')
<div class="card">
    <div class="card-header py-3">Editar ítem de inventario</div>
    <div class="card-body">
        <form action="{{ route('inventarios.update', $inventario) }}" method="POST">
            @csrf
            @method('PUT')
            @include('inventarios._form', ['item' => $inventario])

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="{{ route('inventarios.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

