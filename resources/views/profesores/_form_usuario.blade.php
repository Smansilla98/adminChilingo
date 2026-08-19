@php
    $usuarios = $usuarios ?? collect();
    $hasUsername = $hasUsername ?? true;
    $tieneCuenta = isset($profesor) && $profesor->user_id;
    $defaultModo = $tieneCuenta ? 'existente' : 'nueva';
    $modo = old('cuenta_modo', $defaultModo);
@endphp
<div class="border rounded p-3 mb-3" style="border-color: var(--border) !important;">
    <h2 class="h6 mb-2">Usuario y contraseña para entrar al sistema</h2>
    <p class="text-muted small mb-3">Si le creás una cuenta, va a poder iniciar sesión con ese usuario y esa clave.</p>

    <div class="mb-3">
        <label class="form-label" for="cuenta_modo">Qué hacer</label>
        <select name="cuenta_modo" id="cuenta_modo" class="form-select @error('cuenta_modo') is-invalid @enderror">
            <option value="ninguna" @selected($modo === 'ninguna')>Sin cuenta de ingreso</option>
            @if($tieneCuenta)
                <option value="existente" @selected($modo === 'existente')>Mantener o cambiar la cuenta actual</option>
            @else
                <option value="nueva" @selected($modo === 'nueva')>Crear usuario y contraseña ahora</option>
                <option value="existente" @selected($modo === 'existente')>Usar una cuenta que ya existe</option>
            @endif
        </select>
        @error('cuenta_modo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div id="box-cuenta-existente" class="mb-2" @if($modo !== 'existente') hidden @endif>
        @if($tieneCuenta && $profesor->user)
            <p class="mb-2">
                Cuenta actual:
                <strong>{{ $profesor->user->username ?: $profesor->user->email }}</strong>
                @if($profesor->user->email)
                    <span class="text-muted">({{ $profesor->user->email }})</span>
                @endif
            </p>
            <input type="hidden" name="user_id" value="{{ $profesor->user_id }}">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="login_password_existente">Nueva contraseña (opcional)</label>
                    <input type="password" name="login_password" id="login_password_existente" class="form-control @error('login_password') is-invalid @enderror" autocomplete="new-password">
                    <div class="form-text">Dejalo vacío para no cambiarla. Mínimo 8 caracteres si la cambiás.</div>
                    @error('login_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="login_password_confirmation_existente">Confirmar nueva contraseña</label>
                    <input type="password" name="login_password_confirmation" id="login_password_confirmation_existente" class="form-control" autocomplete="new-password">
                </div>
            </div>
        @else
            <label class="form-label" for="user_id">Cuenta existente</label>
            <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror">
                <option value="">Elegí una cuenta</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" @selected((string) old('user_id') === (string) $u->id)>
                        {{ $u->username ?: $u->name }} @if($u->email) — {{ $u->email }} @endif
                    </option>
                @endforeach
            </select>
            @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @endif
    </div>

    @unless($tieneCuenta)
    <div id="box-cuenta-nueva" class="row g-3" @if($modo !== 'nueva') hidden @endif>
        @if($hasUsername)
        <div class="col-md-6">
            <label class="form-label" for="login_username">Usuario de ingreso *</label>
            <input type="text" name="login_username" id="login_username" class="form-control @error('login_username') is-invalid @enderror" value="{{ old('login_username') }}" autocomplete="username">
            @error('login_username')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @endif
        <div class="col-md-6">
            <label class="form-label" for="login_password">Contraseña *</label>
            <input type="password" name="login_password" id="login_password" class="form-control @error('login_password') is-invalid @enderror" autocomplete="new-password">
            <div class="form-text">Mínimo 8 caracteres. El correo de arriba también queda en la cuenta.</div>
            @error('login_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="login_password_confirmation">Confirmar contraseña *</label>
            <input type="password" name="login_password_confirmation" id="login_password_confirmation" class="form-control" autocomplete="new-password">
        </div>
    </div>
    @endunless
</div>
