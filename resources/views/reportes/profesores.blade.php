@extends('layouts.app')

@section('title', 'Profesores')
@section('page-title', 'Profesores')

@section('content')
<div class="panel">
    <div class="panel-h">
        <div class="panel-h-title">Alumnos por profesor</div>
        <a class="panel-h-link" href="{{ route('reportes.index') }}">volver a reportes →</a>
    </div>
    <div class="panel-b">
        <div class="prof-list">
            @forelse($alumnosPorProfesor as $row)
                @php
                    $p = $row['profesor'];
                    $colors = ['av-orange', 'av-blue', 'av-green', 'av-purple', 'av-amber'];
                    $avatarClass = $colors[abs(crc32((string) $p->id)) % count($colors)];
                    $initials = collect(preg_split('/\s+/', trim($p->nombre ?? '')))
                        ->filter()
                        ->take(2)
                        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                        ->join('');
                @endphp
                <div class="prof-row" style="grid-template-columns: 44px 1fr auto auto;">
                    <div class="prof-avatar {{ $avatarClass }}">{{ $initials ?: 'P' }}</div>
                    <div>
                        <div class="prof-name">{{ $p->nombre }}</div>
                        <div class="prof-meta">{{ $row['sedes']->join(' · ') ?: '—' }}</div>
                    </div>
                    <div class="prof-kpi">
                        <div class="n">{{ $row['alumnos_count'] }}</div>
                        <div class="l">alumnos</div>
                    </div>
                    <div class="prof-kpi">
                        <div class="n">{{ $row['bloques_count'] }}</div>
                        <div class="l">bloques</div>
                    </div>
                </div>
            @empty
                <div class="muted">No hay profesores con alumnos asignados.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

