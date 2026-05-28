<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ITO</title>
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
                <h1 class="auth-card-ito__title">Iniciar sesión</h1>
                <p class="auth-card-ito__sub">Entrá con tu usuario y contraseña</p>
            </div>
            <div class="auth-card-ito__body">
                @if(session('success'))
                    <div class="auth-alerts"><div class="alert alert-success mb-0 py-2">{{ session('success') }}</div></div>
                @endif
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
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    @php $loginEmail = !empty($loginUsesEmail); @endphp
                    <div class="auth-infield @error('username') is-invalid @enderror">
                        <span class="auth-infield__icon"><i class="bi {{ $loginEmail ? 'bi-envelope' : 'bi-person-fill' }}"></i></span>
                        <input type="{{ $loginEmail ? 'email' : 'text' }}"
                               class="auth-infield__input"
                               id="username" name="username" value="{{ old('username') }}" required autofocus
                               placeholder="{{ $loginEmail ? 'Correo electrónico' : 'Usuario' }}"
                               autocomplete="{{ $loginEmail ? 'email' : 'username' }}">
                    </div>
                    @error('username')
                        <div class="auth-field-error">{{ $message }}</div>
                    @enderror

                    <div class="auth-infield @error('password') is-invalid @enderror">
                        <span class="auth-infield__icon"><i class="bi bi-key-fill"></i></span>
                        <input type="password" class="auth-infield__input" id="password" name="password" required
                               placeholder="Contraseña" autocomplete="current-password">
                    </div>
                    @error('password')
                        <div class="auth-field-error">{{ $message }}</div>
                    @enderror

                    <label class="auth-check">
                        <input type="checkbox" id="remember" name="remember" value="1">
                        <span>Recordarme</span>
                    </label>

                    <button type="submit" class="auth-btn-submit">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
                    </button>
                </form>
            </div>
            <div class="auth-card-ito__foot">
                <span class="auth-foot-muted">¿No tenés cuenta?</span>
                <a href="{{ route('register') }}">Registrarse</a>
                <div class="mt-2">
                    <span class="auth-foot-muted">¿Pagaste la cuota?</span>
                    <a href="{{ route('comprobante-cuota-public.create') }}">Cargar comprobante (sin cuenta)</a>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
