<script>
(function () {
    const valorDia = {{ (float) $config->valorPorDia() }};
    const desde = document.getElementById('vg_fecha_desde');
    const hasta = document.getElementById('vg_fecha_hasta');
    const monto = document.getElementById('vg_monto_esperado');
    const auto = document.getElementById('calcular_aporte');
    const hint = document.getElementById('vg_aporte_hint');
    function diasEntre(a, b) {
        if (!a || !b) return 0;
        const d1 = new Date(a + 'T00:00:00');
        const d2 = new Date(b + 'T00:00:00');
        if (d2 < d1) return 0;
        return Math.round((d2 - d1) / 86400000) + 1;
    }
    function sync() {
        if (!desde || !hasta || !monto || !auto || !auto.checked) return;
        const n = diasEntre(desde.value, hasta.value);
        const total = valorDia * n;
        monto.value = total.toFixed(2);
        if (hint) hint.textContent = '$' + valorDia.toLocaleString('es-AR') + ' / día × ' + n + ' días = $' + total.toLocaleString('es-AR');
    }
    [desde, hasta, auto].forEach(function (el) {
        if (el) el.addEventListener('change', sync);
    });
    sync();
})();
</script>
