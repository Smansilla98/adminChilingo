@extends('layouts.app')

@section('title', 'Cargar facturación mensual')
@section('page-title', 'Nueva facturación mensual')

@section('content')
<x-ito.shell-page title="Nueva facturación mensual" subtitle="Cuántos alumnos y cuánto se facturó en el mes." :plain="true">
    <form action="{{ route('facturacion-mensual.store') }}" method="POST">
        @csrf
        @include('facturacion-mensual._form')
        <x-ito.form-actions :cancel="route('facturacion-mensual.index')" submit="Guardar facturación" />
    </form>
</x-ito.shell-page>
@endsection
