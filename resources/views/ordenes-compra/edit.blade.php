@extends('layouts.app')

@section('title', 'Editar orden de compra')
@section('page-title', 'Editar orden de compra')

@section('content')
<div class="card">
    <div class="card-header py-3">Editar orden de compra #{{ $orden->id }}</div>
    <div class="card-body">
        @include('partials.form-ayuda-intro', ['text' => 'Corregí la orden y sus ítems.'])
        <form action="{{ route('ordenes-compra.update', $orden) }}" method="POST">
            @csrf
            @method('PUT')
            @include('ordenes-compra._form')

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a href="{{ route('ordenes-compra.show', $orden) }}" class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>
</div>
@endsection

