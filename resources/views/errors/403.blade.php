@extends('errors.layout')

@section('code', '403')
@section('title', 'No tenés acceso a esta sección')
@section('message', (isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.') ? $exception->getMessage() : __('Tu usuario no tiene permiso para ver esta página. Si creés que es un error, pedile a administración que revise tus permisos.'))

