@extends('errors.layout')

@section('code', '429')
@section('title', 'Demasiados intentos')
@section('message', __('Hiciste muchas solicitudes seguidas. Esperá un minuto y volvé a intentar.'))

