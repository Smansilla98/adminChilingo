@extends('layouts.app')

@section('title', 'Avisos y cobro')
@section('page-title', 'Avisos y cobro')

@section('content')
<x-ito.shell-page title="Avisos y cobro" subtitle="Qué avisos automáticos reciben los alumnos, por dónde y con qué texto, y los datos para que paguen." :plain="true">
    <form method="POST" action="{{ route('configuracion.avisos.update') }}" style="max-width: 900px">
        @csrf @method('PUT')

        <x-ito.form-section title="Datos para pagar" icon="bi-bank" help="Se muestran en Mi cuenta (web y app), con botón para copiar el alias. Lo que dejes vacío toma el valor de las variables COBRO_* del servidor.">
            <div class="row g-3">
                @foreach(['alias' => 'Alias', 'cbu' => 'CBU / CVU', 'titular' => 'Titular', 'cuit' => 'CUIT', 'banco' => 'Banco o billetera'] as $campo => $etiqueta)
                    <div class="col-md-{{ $campo === 'titular' ? 6 : ($campo === 'cbu' ? 6 : 4) }}">
                        <label class="form-label" for="cobro_{{ $campo }}">{{ $etiqueta }}</label>
                        <input class="form-control @error('cobro.'.$campo) is-invalid @enderror" id="cobro_{{ $campo }}" name="cobro[{{ $campo }}]"
                               value="{{ old('cobro.'.$campo, $cobro[$campo] ?? '') }}" placeholder="{{ $cobroEfectivo[$campo] ?? '' }}">
                        @error('cobro.'.$campo)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
            <p class="small text-muted mt-2 mb-0">
                Mercado Pago: {{ $mercadoPago ? 'configurado, los alumnos pueden pagar desde la app.' : 'sin configurar (variables MERCADOPAGO_* del servidor).' }}
            </p>
        </x-ito.form-section>

        <x-ito.form-section title="Avisos automáticos" icon="bi-bell" help="Cada aviso se manda una sola vez por persona (por cuota, clase o evento). Debajo de cada mensaje están las variables que podés usar.">
            @if($push['habilitado'])
                <div class="alert alert-light border small mb-3">
                    <i class="bi bi-phone me-1" aria-hidden="true"></i>
                    Las notificaciones en el celular están activas: hoy las reciben <strong>{{ $push['alumnos_con_app'] }}</strong> {{ $push['alumnos_con_app'] === 1 ? 'alumno' : 'alumnos' }} (los que instalaron la app y aceptaron notificaciones).
                    <br>Tu celular: {!! $push['mio'] ? '<strong>registrado</strong> — «Mandarme una prueba» te llega al teléfono.' : '<strong>todavía no registrado</strong> — entrá a la app con tu usuario y, en Avisos, tocá «Activar notificaciones».' !!}
                </div>
            @else
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-phone me-1" aria-hidden="true"></i>
                    Las notificaciones en el celular están apagadas en el servidor (<code>EXPO_PUSH_ENABLED=false</code>). Los avisos con ese canal llegan solo por los demás.
                </div>
            @endif
            @foreach($avisos as $tipo => $a)
                <fieldset class="border rounded p-3 mb-3">
                    <legend class="float-none w-auto px-2 fs-6 mb-0">{{ $a['nombre'] }} <span class="text-muted small">· {{ $a['para'] }}</span></legend>
                    <p class="small text-muted mb-2">{{ $a['descripcion'] }}</p>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="avisos[{{ $tipo }}][activo]" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="act_{{ $tipo }}" name="avisos[{{ $tipo }}][activo]" value="1" @checked(old("avisos.$tipo.activo", $a['activo']))>
                        <label class="form-check-label" for="act_{{ $tipo }}">Activo</label>
                    </div>
                    <div class="d-flex flex-wrap gap-3 mb-2" role="group" aria-label="Canales de {{ $a['nombre'] }}">
                        <input type="hidden" name="avisos[{{ $tipo }}][canales][]" value="">
                        @foreach($canales as $canal => $etiqueta)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="can_{{ $tipo }}_{{ $canal }}" name="avisos[{{ $tipo }}][canales][]" value="{{ $canal }}" @checked(in_array($canal, old("avisos.$tipo.canales", $a['canales']), true))>
                                <label class="form-check-label" for="can_{{ $tipo }}_{{ $canal }}">{{ $etiqueta }}</label>
                            </div>
                        @endforeach
                    </div>
                    @php($plazo = collect(\App\Domain\Notificaciones\ConfigAvisos::PLAZOS)->keys()->first(fn ($campo) => isset($a[$campo])))
                    <div class="row g-2">
                        <div class="{{ $plazo ? 'col-md-9' : 'col-12' }}">
                            <label class="form-label small" for="tit_{{ $tipo }}">Título</label>
                            <input class="form-control" id="tit_{{ $tipo }}" name="avisos[{{ $tipo }}][titulo]" value="{{ old("avisos.$tipo.titulo", $a['titulo']) }}" maxlength="120">
                        </div>
                        @if($plazo)
                            @php([$min, $max] = \App\Domain\Notificaciones\ConfigAvisos::PLAZOS[$plazo])
                            <div class="col-md-3">
                                <label class="form-label small" for="{{ $plazo }}_{{ $tipo }}">{{ $a['plazo_etiqueta'] ?? ($tipo === 'cuota_vencida' ? 'Revisar vencidas de los últimos (días)' : 'Días antes') }}</label>
                                <input type="number" class="form-control" id="{{ $plazo }}_{{ $tipo }}" name="avisos[{{ $tipo }}][{{ $plazo }}]" min="{{ $min }}" max="{{ $max }}" value="{{ old("avisos.$tipo.$plazo", $a[$plazo]) }}">
                            </div>
                        @endif
                        <div class="col-12">
                            <label class="form-label small" for="msg_{{ $tipo }}">Mensaje</label>
                            <textarea class="form-control" id="msg_{{ $tipo }}" name="avisos[{{ $tipo }}][mensaje]" rows="2" maxlength="600">{{ old("avisos.$tipo.mensaje", $a['mensaje']) }}</textarea>
                            @if(! empty($a['variables']))<div class="form-text">Variables: {{ implode(' ', $a['variables']) }}</div>@endif
                        </div>
                    </div>
                    <button type="submit" form="probar_{{ $tipo }}" class="btn btn-link btn-sm px-0 mt-1"><i class="bi bi-send" aria-hidden="true"></i> Mandarme una prueba</button>
                </fieldset>
            @endforeach
            <p class="small text-muted mb-0">WhatsApp y mail usan los datos de contacto del alumno y la configuración del servidor (Twilio y mail); si no están, ese canal se omite.</p>
        </x-ito.form-section>

        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary"><i class="bi bi-check2" aria-hidden="true"></i> Guardar</button>
            <button type="submit" form="enviarAhora" class="btn btn-outline-secondary"><i class="bi bi-lightning" aria-hidden="true"></i> Enviar ahora los avisos de cuotas nuevas</button>
        </div>
    </form>

    <form id="enviarAhora" method="POST" action="{{ route('configuracion.avisos.enviar') }}" class="d-none">@csrf</form>
    @foreach(array_keys($avisos) as $tipo)
        <form id="probar_{{ $tipo }}" method="POST" action="{{ route('configuracion.avisos.probar') }}" class="d-none">@csrf<input type="hidden" name="tipo" value="{{ $tipo }}"></form>
    @endforeach
</x-ito.shell-page>
@endsection
