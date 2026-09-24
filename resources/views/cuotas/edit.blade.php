@extends('layouts.app')

@section('title', 'Editar cuota')
@section('page-title', 'Editar cuota')

@section('content')
<x-ito.shell-page :title="'Editar '.$cuota->nombre" subtitle="Los pagos ya registrados no cambian." :plain="true">
    <form action="{{ route('cuotas.update', $cuota) }}" method="POST" id="formCuota">
        @csrf
        @method('PUT')
        @include('cuotas._form', ['cuota' => $cuota])
        <x-ito.form-actions :cancel="route('cuotas.show', $cuota)" submit="Guardar cambios" />
    </form>
</x-ito.shell-page>
@endsection
