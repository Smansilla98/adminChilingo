{{-- Compat: el editor usa layouts.publico para la misma piel que el programa. --}}
@extends('layouts.publico')
@section('publico-brand', 'Partituras')
@section('body-class', 'pt-shell')
@section('main-class', 'biblio-main--flush')
@section('content')
    @yield('content')
@endsection
