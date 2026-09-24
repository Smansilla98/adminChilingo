@extends('layouts.app')

@section('title', 'Editar facturación')
@section('page-title', 'Editar facturación')

@section('content')
<x-ito.shell-page :title="'Facturación de '.$facturacionMensual->nombre_mes.' '.$facturacionMensual->año" :subtitle="$facturacionMensual->sede?->nombre ?? 'Toda la escuela'" :plain="true">
    <form action="{{ route('facturacion-mensual.update', $facturacionMensual) }}" method="POST">
        @csrf
        @method('PUT')
        @include('facturacion-mensual._form', ['facturacion' => $facturacionMensual])
        <x-ito.form-actions :cancel="route('facturacion-mensual.index')" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
