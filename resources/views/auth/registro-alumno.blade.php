<!DOCTYPE html>
<html lang="es">
<head>
    @include('layouts.partials.head-ito', ['headTitle' => 'Crear mi cuenta · La Chilinga'])
</head>
<body class="auth-page-ito">
<a class="ito-skip" href="#contenido-principal">Ir al formulario de registro</a>
@php
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
                <h1 class="auth-card-ito__title">Crear mi cuenta de alumno</h1>
                <p class="auth-card-ito__sub">Si ya estás anotado en La Chilinga, en un minuto tenés tu cuenta para ver tu material y mandar tus comprobantes.</p>
            </div>
            <div class="auth-card-ito__body">
                @if($errors->any())
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

                <form method="POST" action="{{ route('registro.alumno.store') }}" novalidate>
                    @csrf
                    <div class="auth-field">
                        <label class="auth-field-label" for="dni">Tu DNI</label>
                        <div class="auth-infield @error('dni') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-person-vcard"></i></span>
                            <input type="text" inputmode="numeric" class="auth-infield__input" id="dni" name="dni" value="{{ old('dni') }}"
                                   required autofocus autocomplete="off" placeholder="Solo números, sin puntos" aria-describedby="dni-ayuda">
                        </div>
                        <p class="auth-field-help" id="dni-ayuda">Va a ser tu usuario para entrar.</p>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label" for="fecha_nacimiento">Tu fecha de nacimiento</label>
                        <div class="auth-infield @error('fecha_nacimiento') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-calendar"></i></span>
                            <input type="date" class="auth-infield__input" id="fecha_nacimiento" name="fecha_nacimiento" value="{{ old('fecha_nacimiento') }}" required max="{{ now()->toDateString() }}" aria-describedby="nac-ayuda">
                        </div>
                        <p class="auth-field-help" id="nac-ayuda">La usamos para confirmar que sos vos. Tiene que ser la misma que figura en tu ficha.</p>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label" for="email">Tu correo <span class="auth-foot-muted">(opcional)</span></label>
                        <div class="auth-infield @error('email') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="auth-infield__input" id="email" name="email" value="{{ old('email') }}" autocomplete="email" autocapitalize="none" spellcheck="false">
                        </div>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label" for="password">Elegí una contraseña</label>
                        <div class="auth-infield @error('password') is-invalid @enderror">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input type="password" class="auth-infield__input" id="password" name="password" required minlength="6" autocomplete="new-password" aria-describedby="pass-ayuda">
                            <button type="button" class="auth-infield__toggle" id="togglePassword" aria-label="Mostrar contraseña" aria-pressed="false">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="auth-field-help" id="pass-ayuda">Al menos 6 letras o números. Anotala en un lugar seguro.</p>
                    </div>

                    <div class="auth-field">
                        <label class="auth-field-label" for="password_confirmation">Repetí la contraseña</label>
                        <div class="auth-infield">
                            <span class="auth-infield__icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
                            <input type="password" class="auth-infield__input" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="auth-btn-submit">
                        Crear mi cuenta
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
            <div class="auth-card-ito__foot">
                <div class="mb-1">
                    <span class="auth-foot-muted">¿Ya tenés cuenta?</span>
                    <a href="{{ route('login') }}">Entrá acá</a>
                </div>
                <div>
                    <span class="auth-foot-muted">¿Sos profe o trabajás en la escuela?</span>
                    <span>Tu cuenta te la da administración.</span>
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
