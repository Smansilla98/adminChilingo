@extends('layouts.app')

@section('title', 'Gasto #'.$gasto->id)
@section('page-title', 'Gasto #'.$gasto->id)

@section('content')
@php
    $tipo = \App\Models\Gasto::TIPOS[$gasto->tipo] ?? $gasto->tipo;
    $subtipo = $gasto->subtipo ? ((\App\Models\Gasto::SUBTIPOS[$gasto->tipo] ?? [])[$gasto->subtipo] ?? $gasto->subtipo) : null;
@endphp
<x-ito.shell-page :title="$gasto->descripcion ?: $tipo" :subtitle="'Gasto #'.$gasto->id.' · '.$gasto->fecha->format('d/m/Y')" :plain="true">
    <x-slot:actions>
        <a href="{{ route('gastos.index') }}" class="btn btn-outline-secondary">Volver</a>
        @can('update', $gasto)
            <a href="{{ route('gastos.edit', $gasto) }}" class="btn btn-primary"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
        @endcan
    </x-slot:actions>

    <x-ito.facts>
        <x-ito.fact label="Monto">$ {{ number_format($gasto->monto, 2, ',', '.') }}</x-ito.fact>
        <x-ito.fact label="Tipo" :value="$tipo.($subtipo ? ' · '.$subtipo : '')" />
        <x-ito.fact label="Sede" :value="$gasto->sede?->nombre" />
        <x-ito.fact label="Bloque" :value="$gasto->bloque?->nombre" />
        <x-ito.fact label="Proveedor" :value="$gasto->proveedor" />
    </x-ito.facts>

    @if($gasto->notas || $gasto->creador)
        <x-ito.detail-section title="Notas" icon="bi-journal-text">
            <p class="mb-0" style="white-space: pre-line">{{ $gasto->notas ?: 'Sin notas.' }}</p>
            @if($gasto->creador)
                <p class="small text-muted mt-3 mb-0">Registrado por {{ $gasto->creador->name ?? $gasto->creador->username }}{{ $gasto->created_at ? ' el '.$gasto->created_at->format('d/m/Y') : '' }}.</p>
            @endif
        </x-ito.detail-section>
    @endif
</x-ito.shell-page>
@endsection
