@extends('layouts.app')

@section('title', 'Nueva orden de compra')
@section('page-title', 'Nueva orden de compra')

@section('content')
<div class="card">
    <div class="card-header py-3">Nueva orden de compra</div>
    <div class="card-body">
        <form action="{{ route('ordenes-compra.store') }}" method="POST">
            @csrf
            @include('ordenes-compra._form')

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Guardar orden</button>
                <a href="{{ route('ordenes-compra.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
@endsection

