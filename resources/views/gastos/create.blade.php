@extends('layouts.app')

@section('title', 'Nuevo gasto')
@section('page-title', 'Nuevo gasto')

@section('content')
<x-ito.shell-page title="Registrar gasto" subtitle="Egresos de la escuela: alquiler, servicios, compras." :plain="true">
    <form action="{{ route('gastos.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        @include('gastos._form')
        <x-ito.form-actions :cancel="route('gastos.index')" submit="Registrar gasto" />
    </form>
</x-ito.shell-page>
@endsection
