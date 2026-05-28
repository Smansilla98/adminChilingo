<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - ITO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ asset('css/chilinga-admin.css') }}">
</head>
<body class="auth-page-ito">
    <div class="auth-shell">
        <div class="auth-card auth-card--ito">
            <div class="auth-card-ito__logo-ring" aria-hidden="true">
                <img src="{{ asset('images/brand/logo.png') }}" alt="ITO">
            </div>
            <div class="auth-card-ito__head">
                <h1 class="auth-card-ito__title">Registrarse</h1>
                <p class="auth-card-ito__sub">Completá tus datos para pedir acceso</p>
            </div>
            <div class="auth-card-ito__body">
                @if($errors->any())
                    <div class="auth-alerts">
                        <div class="alert alert-danger mb-0 py-2 px-3">
                            <ul class="mb-0 ps-3 small">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="auth-infield @error('name') is-invalid @enderror">
                        <span class="auth-infield__icon"><i class="bi bi-person-fill"></i></span>
                        <input type="text" class="auth-infield__input" id="name" name="name" value="{{ old('name') }}" required autofocus
                               placeholder="Nombre completo" autocomplete="name">
                    </div>
                    @error('name')
                        <div class="auth-field-error">{{ $message }}</div>
                    @enderror

                    @if(!empty($hasUsernameColumn))
                    <div class="auth-infield @error('username') is-invalid @enderror">
                        <span class="auth-infield__icon"><i class="bi bi-person-badge"></i></span>
                        <input type="text" class="auth-infield__input" id="username" name="username" value="{{ old('username') }}" required
                               placeholder="Usuario" autocomplete="username">
                    </div>
                    @error('username')
                        <div class="auth-field-error">{{ $message }}</div>
                    @enderror
                    @endif

                    <div class="auth-infield @error('email') is-invalid @enderror">
                        <span class="auth-infield__icon"><i class="bi bi-envelope-fill"></i></span>
                        <input type="email" class="auth-infield__input" id="email" name="email" value="{{ old('email') }}" required
                               placeholder="Correo electrónico" autocomplete="email">
                    </div>
                    @error('email')
                        <div class="auth-field-error">{{ $message }}</div>
                    @enderror

                    <div class="auth-infield @error('password') is-invalid @enderror">
                        <span class="auth-infield__icon"><i class="bi bi-key-fill"></i></span>
                        <input type="password" class="auth-infield__input" id="password" name="password" required
                               placeholder="Contraseña" autocomplete="new-password">
                    </div>
                    @error('password')
                        <div class="auth-field-error">{{ $message }}</div>
                    @enderror

                    <div class="auth-infield">
                        <span class="auth-infield__icon"><i class="bi bi-shield-lock-fill"></i></span>
                        <input type="password" class="auth-infield__input" id="password_confirmation" name="password_confirmation" required
                               placeholder="Confirmar contraseña" autocomplete="new-password">
                    </div>

                    <button type="submit" class="auth-btn-submit mt-1">
                        <i class="bi bi-person-check me-1"></i> Crear cuenta
                    </button>
                </form>
            </div>
            <div class="auth-card-ito__foot">
                <span class="auth-foot-muted">¿Ya tenés cuenta?</span>
                <a href="{{ route('login') }}">Iniciar sesión</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
