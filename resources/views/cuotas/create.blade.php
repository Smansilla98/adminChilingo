@extends('layouts.app')

@section('title', 'Nueva cuota')
@section('page-title', 'Nueva cuota')

@section('content')
<x-ito.shell-page title="Nueva cuota" subtitle="Monto del período y para quién aplica." :plain="true">
    <form action="{{ route('cuotas.store') }}" method="POST" id="formCuota">
        @csrf
        @include('cuotas._form')
        <x-ito.form-actions :cancel="route('cuotas.index')" submit="Crear cuota" />
    </form>
</x-ito.shell-page>
@endsection
