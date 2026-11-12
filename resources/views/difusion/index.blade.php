@extends('layouts.app')

@section('title', 'Mensajes a grupos')
@section('page-title', 'Mensajes a grupos')

@section('content')
<x-ito.list-page title="Mensajes a grupos" subtitle="Avisos que mandás a mano: a toda la escuela, a una sede, a bloques o a los convocados de un show.">
    <x-slot:actions>
        <a href="{{ route('difusion.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Nuevo mensaje</a>
    </x-slot:actions>

    <table class="ito-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Mensaje</th>
                <th>Para</th>
                <th>Estado</th>
                <th class="text-end">Recibieron / leyeron</th>
            </tr>
        </thead>
        <tbody>
            @forelse($filas as $d)
                <tr>
                    <td class="ito-mono">{{ \Illuminate\Support\Carbon::parse($d['enviada_at'] ?? $d['programada_para'] ?? $d['creada_at'])->format('d/m/Y H:i') }}</td>
                    <td><a href="{{ route('difusion.show', $d['id']) }}" class="fw-semibold">{{ $d['titulo'] }}</a></td>
                    <td>{{ $d['destino'] }} <span class="text-muted small">· {{ $d['quienes'] }}</span></td>
                    <td><x-ito.status :tone="['enviada' => 'success', 'programada' => 'info', 'cancelada' => 'neutral'][$d['estado']] ?? 'warning'" :label="$d['estado_texto']" /></td>
                    <td class="text-end ito-mono">
                        @if($d['lecturas'])
                            {{ $d['destinatarios'] }} / {{ $d['lecturas']['leyeron'] }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="ito-empty">Todavía no mandaste mensajes. <a href="{{ route('difusion.create') }}">Escribir el primero</a></td></tr>
            @endforelse
        </tbody>
    </table>

    <x-slot:footer>
        <div class="ito-footer-meta">{{ $difusiones->total() }} mensajes</div>
        {{ $difusiones->withQueryString()->links('pagination::bootstrap-5') }}
    </x-slot:footer>
</x-ito.list-page>
@endsection
