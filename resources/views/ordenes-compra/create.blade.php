@extends('layouts.app')

@section('title', 'Nueva orden de compra')
@section('page-title', 'Nueva orden de compra')

@section('content')
<x-ito.shell-page title="Nueva orden de compra" subtitle="Sede, motivo y lista de cosas a comprar." :plain="true">
    <form action="{{ route('ordenes-compra.store') }}" method="POST">
        @csrf
        @include('ordenes-compra._form')
        <x-ito.form-actions :cancel="route('ordenes-compra.index')" submit="Crear orden" />
    </form>
</x-ito.shell-page>
@endsection
