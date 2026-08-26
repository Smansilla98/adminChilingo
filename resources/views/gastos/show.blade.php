@extends('layouts.app')

@section('title', 'Ver gasto')
@section('page-title', 'Ver gasto')

@section('content')
<x-ito.shell-page
    title="Gasto #{{ $gasto->id }}"
    subtitle="Detalle del egreso"
    eyebrow="Gastos"
>
    <x-slot:actions>
        <a href="{{ route('gastos.edit', $gasto) }}" class="btn btn-warning btn-sm"><i class="bi bi-pencil"></i> Editar</a>
        <a href="{{ route('gastos.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </x-slot:actions>

    <dl class="ito-dl">
        <div><dt>Fecha</dt><dd>{{ $gasto->fecha->format('d/m/Y') }}</dd></div>
        <div><dt>Tipo</dt><dd>{{ \App\Models\Gasto::TIPOS[$gasto->tipo] ?? $gasto->tipo }}</dd></div>
        @if($gasto->subtipo)
        <div><dt>Subtipo</dt><dd>{{ $gasto->subtipo }}</dd></div>
        @endif
        <div><dt>Monto</dt><dd><strong>$ {{ number_format($gasto->monto, 2, ',', '.') }}</strong></dd></div>
        <div><dt>Sede</dt><dd>{{ $gasto->sede?->nombre ?? '—' }}</dd></div>
        <div><dt>Bloque</dt><dd>{{ $gasto->bloque?->nombre ?? '—' }}</dd></div>
        @if($gasto->descripcion)
        <div><dt>Descripción</dt><dd>{{ $gasto->descripcion }}</dd></div>
        @endif
        @if($gasto->proveedor)
        <div><dt>Proveedor</dt><dd>{{ $gasto->proveedor }}</dd></div>
        @endif
        @if($gasto->notas)
        <div><dt>Notas</dt><dd>{{ $gasto->notas }}</dd></div>
        @endif
        @if($gasto->creador)
        <div><dt>Registrado por</dt><dd>{{ $gasto->creador->name ?? $gasto->creador->username }}</dd></div>
        @endif
    </dl>
</x-ito.shell-page>
@endsection
