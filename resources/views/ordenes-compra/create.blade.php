@extends('layouts.app')

@section('title', 'Nueva orden de compra')
@section('page-title', 'Nueva orden de compra')

@section('content')
<x-ito.shell-page
    title="Nueva orden de compra"
    eyebrow="Compras"
    subtitle="Sede, motivo e ítems a comprar."
>

        @include('partials.form-ayuda-intro', ['text' => 'Pedido de compra: sede, motivo y lista de cosas a comprar (podés sumar varias filas).'])
        <form action="{{ route('ordenes-compra.store') }}" method="POST">
            @csrf
            @include('ordenes-compra._form')

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Guardar orden</button>
                <a href="{{ route('ordenes-compra.index') }}" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
</x-ito.shell-page>

@endsection

