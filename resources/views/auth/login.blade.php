<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - La Chilinga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}">
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <div class="auth-head">
                <div class="auth-brand">LC</div>
                <div>
                    <div class="auth-title">Iniciar sesión</div>
                    <div class="auth-sub">Sistema de Gestión Administrativa</div>
                </div>
            </div>
            <div class="auth-body">
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="username" class="form-label">{{ !empty($loginUsesEmail) ? 'Correo' : 'Usuario' }}</label>
                        <input type="{{ !empty($loginUsesEmail) ? 'email' : 'text' }}" class="form-control @error('username') is-invalid @enderror"
                               id="username" name="username" value="{{ old('username') }}" required autofocus
                               placeholder="{{ !empty($loginUsesEmail) ? 'ej: admin@ejemplo.com' : '' }}"
                               autocomplete="{{ !empty($loginUsesEmail) ? 'email' : 'username' }}">
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
            <div class="auth-footer text-center">
                ¿No tenés cuenta? <a href="{{ route('register') }}">Registrarse</a>
                <div class="mt-2 small text-muted">
                    ¿Pagaste la cuota? <a href="{{ route('comprobante-cuota-public.create') }}">Cargar comprobante (sin cuenta)</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

