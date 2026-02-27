@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-people"></i> Alumnos Activos</h5>
                <h2>{{ $totalAlumnos }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-person-badge"></i> Profesores</h5>
                <h2>{{ $totalProfesores }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-collection"></i> Bloques Activos</h5>
                <h2>{{ $totalBloques }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-calendar-event"></i> Próximos Eventos</h5>
                <h2>{{ $proximosEventos->count() }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Alumnos por Sede</div>
            <div class="card-body">
                <canvas id="alumnosPorSedeChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Alumnos por Año</div>
            <div class="card-body">
                <canvas id="alumnosPorAñoChart"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Distribución de Tambores</div>
            <div class="card-body">
                <canvas id="tamboresChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Próximos Eventos</div>
            <div class="card-body">
                @if($proximosEventos->count() > 0)
                <ul class="list-group">
                    @foreach($proximosEventos as $evento)
                    <li class="list-group-item">
                        <strong>{{ $evento->titulo }}</strong><br>
                        <small>{{ $evento->fecha->format('d/m/Y') }} 
                        @if($evento->hora_inicio)
                        - {{ $evento->hora_inicio->format('H:i') }}
                        @endif
                        </small>
                        @if($evento->sede)
                        <br><span class="badge bg-secondary">{{ $evento->sede->nombre }}</span>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted">No hay eventos próximos</p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Alumnos por Sede
    const sedeCtx = document.getElementById('alumnosPorSedeChart').getContext('2d');
    new Chart(sedeCtx, {
        type: 'bar',
        data: {
            labels: {!! json_encode($alumnosPorSede->pluck('nombre')) !!},
            datasets: [{
                label: 'Alumnos',
                data: {!! json_encode($alumnosPorSede->pluck('alumnos_count')) !!},
                backgroundColor: '#d32f2f'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Alumnos por Año
    const añoCtx = document.getElementById('alumnosPorAñoChart').getContext('2d');
    new Chart(añoCtx, {
        type: 'line',
        data: {
            labels: {!! json_encode($alumnosPorAño->pluck('año')->map(fn($a) => $a . '° Año')) !!},
            datasets: [{
                label: 'Alumnos',
                data: {!! json_encode($alumnosPorAño->pluck('total')) !!},
                borderColor: '#f57c00',
                backgroundColor: 'rgba(245, 124, 0, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // Distribución de Tambores
    const tamboresCtx = document.getElementById('tamboresChart').getContext('2d');
    new Chart(tamboresCtx, {
        type: 'doughnut',
        data: {
            labels: ['Tambor Propio', 'Tambor Sede'],
            datasets: [{
                data: [{{ $totalConTamborPropio }}, {{ $totalConTamborSede }}],
                backgroundColor: ['#4caf50', '#ff9800']
            }]
        },
        options: {
            responsive: true
        }
    });
</script>
@endpush
@endsection

