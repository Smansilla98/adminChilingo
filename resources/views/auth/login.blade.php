<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito', ['headTitle' => 'Iniciar sesión · La Chilinga'])
</head>
<body class="auth-page-ito">
<a class="ito-skip" href="#contenido-principal">Ir al formulario</a>
@php
    $loginEmail = ! empty($loginUsesEmail);
    $registroAbierto = filter_var(env('ALLOW_PUBLIC_REGISTER', false), FILTER_VALIDATE_BOOLEAN);
@endphp
<div class="auth-split">
    @include('auth.partials.aside')

    <main class="auth-main" id="contenido-principal" tabindex="-1">
        <div class="auth-card auth-card--ito">
            <div class="auth-mobile-brand">
                <img src="{{ asset('images/brand/logo.png') }}" alt="" width="36" height="36">
                La Chilinga
            </div>
            <div class="auth-card-ito__head">
                <h1 class="auth-card-ito__title">Iniciar sesión</h1>
                <p class="auth-card-ito__sub">Entrá con tu {{ $loginEmail ? 'correo' : 'usuario' }} y contraseña.</p>
            </div>
            <div class="auth-card-ito__body">
                @if(session('success'))
                    <div class="auth-alerts"><div class="alert alert-success" role="status">{{ session('success') }}</div></div>
                @endif
                @if(session('error'))
                    <div class="auth-alerts"><div class="alert alert-warning" role="alert">{{ session('error') }}</div></div>
                @endif
                @if($errors->any() && ! $errors->has('username') && ! $errors->has('password'))
                    <div class="auth-alerts">
                        <div class="alert alert-danger" role="alert">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label class="auth-field-label" for="username">{{ $loginEmail ? 'Correo electrónico' : 'Usuario' }}</label>
                        <div class="auth-infield @error('username') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi {{ $loginEmail ? 'bi-envelope' : 'bi-person' }}"></i></span>
                            <input type="{{ $loginEmail ? 'email' : 'text' }}"
                                   class="auth-infield__input"
                                   id="username" name="username" value="{{ old('username') }}" required autofocus
                                   autocomplete="{{ $loginEmail ? 'email' : 'username' }}"
                                   autocapitalize="none" spellcheck="false"
                                   @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                        </div>
                        @error('username')
                            <p class="auth-field-error" id="username-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label" for="password">Contraseña</label>
                        <div class="auth-infield @error('password') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input type="password" class="auth-infield__input" id="password" name="password" required
                                   autocomplete="current-password"
                                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                            <button type="button" class="auth-infield__toggle" id="togglePassword" aria-label="Mostrar contraseña" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password')
                            <p class="auth-field-error" id="password-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="auth-check">
                        <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                        <span>Mantener la sesión iniciada</span>
                    </label>

                    <button type="submit" class="auth-btn-submit">
                        Iniciar sesión
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
            <div class="auth-card-ito__foot">
                @if($registroAbierto)
                    <div class="mb-1">
                        <span class="auth-foot-muted">¿No tenés cuenta?</span>
                        <a href="{{ route('register') }}">Registrate</a>
                    </div>
                @endif
                <div>
                    <span class="auth-foot-muted">¿Pagaste la cuota?</span>
                    <a href="{{ route('comprobante-cuota-public.create') }}">Cargá el comprobante sin cuenta</a>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
(function () {
    var btn = document.getElementById('togglePassword');
    var input = document.getElementById('password');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
        btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        btn.querySelector('i').className = 'bi ' + (visible ? 'bi-eye' : 'bi-eye-slash');
    });
})();
</script>
</body>
</html>
