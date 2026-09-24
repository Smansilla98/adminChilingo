<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito', ['headTitle' => 'Crear cuenta · La Chilinga'])
</head>
<body class="auth-page-ito">
<a class="ito-skip" href="#contenido-principal">Ir al formulario</a>
<div class="auth-split">
    @include('auth.partials.aside')

    <main class="auth-main" id="contenido-principal" tabindex="-1">
        <div class="auth-card auth-card--ito">
            <div class="auth-mobile-brand">
                <img src="{{ asset('images/brand/logo.png') }}" alt="" width="36" height="36">
                La Chilinga
            </div>
            <div class="auth-card-ito__head">
                <h1 class="auth-card-ito__title">Crear cuenta</h1>
                <p class="auth-card-ito__sub">Completá tus datos. Administración te asigna el acceso que corresponda.</p>
            </div>
            <div class="auth-card-ito__body">
                @if($errors->any() && ! $errors->hasAny(['name', 'username', 'email', 'password']))
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

                <form method="POST" action="{{ route('register') }}" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label class="auth-field-label" for="name">Nombre y apellido</label>
                        <div class="auth-infield @error('name') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-person"></i></span>
                            <input type="text" class="auth-infield__input" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                        </div>
                        @error('name')<p class="auth-field-error" id="name-error">{{ $message }}</p>@enderror
                    </div>

                    @if(!empty($hasUsernameColumn))
                        <div class="auth-field">
                            <label class="auth-field-label" for="username">Usuario</label>
                            <div class="auth-infield @error('username') is-invalid @enderror">
                                <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-at"></i></span>
                                <input type="text" class="auth-infield__input" id="username" name="username" value="{{ old('username') }}" required autocomplete="username" autocapitalize="none" spellcheck="false"
                                       @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                            </div>
                            @error('username')<p class="auth-field-error" id="username-error">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <div class="auth-field">
                        <label class="auth-field-label" for="email">Correo electrónico</label>
                        <div class="auth-infield @error('email') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="auth-infield__input" id="email" name="email" value="{{ old('email') }}" required autocomplete="email"
                                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                        </div>
                        @error('email')<p class="auth-field-error" id="email-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label" for="password">Contraseña</label>
                        <div class="auth-infield @error('password') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input type="password" class="auth-infield__input" id="password" name="password" required autocomplete="new-password"
                                   aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                            <button type="button" class="auth-infield__toggle" data-toggle-password="password" aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                        </div>
                        <p class="auth-field-help" id="password-help">Al menos 8 caracteres.</p>
                        @error('password')<p class="auth-field-error" id="password-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label" for="password_confirmation">Repetí la contraseña</label>
                        <div class="auth-infield">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input type="password" class="auth-infield__input" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="auth-btn-submit mt-2">
                        Crear cuenta
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
            <div class="auth-card-ito__foot">
                <span class="auth-foot-muted">¿Ya tenés cuenta?</span>
                <a href="{{ route('login') }}">Iniciá sesión</a>
            </div>
        </div>
    </main>
</div>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
    var input = document.getElementById(btn.getAttribute('data-toggle-password'));
    if (!input) return;
    btn.addEventListener('click', function () {
        var visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        btn.setAttribute('aria-pressed', visible ? 'false' : 'true');
        btn.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
        btn.querySelector('i').className = 'bi ' + (visible ? 'bi-eye' : 'bi-eye-slash');
    });
});
</script>
</body>
</html>
