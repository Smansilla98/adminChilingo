{{-- Las vistas públicas usan layouts.publico. Este archivo queda por compatibilidad. --}}
@extends('layouts.publico')

@section('publico-brand', 'Biblioteca')

@section('content')
    @yield('publico-content')
@endsection
