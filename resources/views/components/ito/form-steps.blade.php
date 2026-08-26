@props([
    'steps' => [],
    'id' => 'itoFormSteps',
    'submitLabel' => 'Guardar',
])
{{--
  Wizard de un solo <form>.
  Uso: envolver paneles con data-ito-step="0|1|…" dentro del slot.
  $steps = ['Datos', 'Instrumentos', 'Clases']
--}}
@php
    $stepLabels = array_values($steps);
    $count = max(count($stepLabels), 1);
@endphp
<div
    {{ $attributes->merge(['class' => 'ito-form-steps']) }}
    id="{{ $id }}"
    data-ito-form-steps
    data-ito-steps-count="{{ $count }}"
>
    <ol class="ito-steps-nav" aria-label="Pasos del formulario">
        @foreach($stepLabels as $i => $label)
            <li>
                <button
                    type="button"
                    class="ito-step-tab {{ $i === 0 ? 'is-active' : '' }}"
                    data-ito-step-goto="{{ $i }}"
                    aria-current="{{ $i === 0 ? 'step' : 'false' }}"
                >
                    <span class="ito-step-num">{{ $i + 1 }}</span>
                    <span class="ito-step-label">{{ $label }}</span>
                </button>
            </li>
        @endforeach
    </ol>

    <div class="ito-steps-body">
        {{ $slot }}
    </div>

    <div class="ito-steps-actions" data-ito-steps-actions>
        <button type="button" class="btn btn-outline-secondary" data-ito-step-prev hidden>
            Anterior
        </button>
        <div class="ito-steps-actions-end">
            @isset($cancel)
                {{ $cancel }}
            @endisset
            <button type="button" class="btn btn-primary" data-ito-step-next>
                Siguiente
            </button>
            <button type="submit" class="btn btn-primary" data-ito-step-submit hidden>
                {{ $submitLabel }}
            </button>
        </div>
    </div>
</div>
