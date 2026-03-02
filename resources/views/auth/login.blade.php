<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - La Chilinga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #d32f2f 0%, #f57c00 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            max-width: 400px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .login-header {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="login-header">
                <h2><i class="bi bi-music-note-beamed"></i> La Chilinga</h2>
                <p class="mb-0">Sistema de Gestión Administrativa</p>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="username" class="form-label">{{ isset($loginUsesEmail) && $loginUsesEmail ? 'Correo' : 'Usuario' }}</label>
                        <input type="{{ isset($loginUsesEmail) && $loginUsesEmail ? 'email' : 'text' }}" class="form-control @error('username') is-invalid @enderror"
                               id="username" name="username" value="{{ old('username') }}" required autofocus
                               placeholder="{{ isset($loginUsesEmail) && $loginUsesEmail ? 'ej: admin@ejemplo.com' : '' }}"
                               autocomplete="{{ isset($loginUsesEmail) && $loginUsesEmail ? 'email' : 'username' }}">
                        @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" 
                               id="password" name="password" required>
                        @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Recordarme</label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

