<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - La Chilinga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/chilinga-v0.css') }}">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            max-width: 420px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }
        .register-header {
            background: rgba(255,255,255,.85);
            color: #0f172a;
            padding: 30px;
            border-radius: 16px 16px 0 0;
            text-align: center;
            border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="register-header">
                <h2><i class="bi bi-person-plus"></i> Registrarse</h2>
                <p class="mb-0 text-muted">Sistema de Gestión Administrativa</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre completo</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                               id="name" name="name" value="{{ old('name') }}" required autofocus
                               autocomplete="name">
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @if(!empty($hasUsernameColumn))
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario</label>
                        <input type="text" class="form-control @error('username') is-invalid @enderror"
                               id="username" name="username" value="{{ old('username') }}" required
                               autocomplete="username">
                        @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    @endif
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                               id="email" name="email" value="{{ old('email') }}" required
                               autocomplete="email">
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               id="password" name="password" required
                               autocomplete="new-password">
                        @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirmar contraseña</label>
                        <input type="password" class="form-control" id="password_confirmation"
                               name="password_confirmation" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-check"></i> Crear cuenta
                    </button>
                </form>
                <hr class="my-3">
                <p class="text-center text-muted mb-0 small">
                    ¿Ya tenés cuenta? <a href="{{ route('login') }}">Iniciar sesión</a>
                </p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
