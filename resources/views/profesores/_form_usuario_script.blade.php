<script>
(function () {
    const modo = document.getElementById('cuenta_modo');
    const boxNueva = document.getElementById('box-cuenta-nueva');
    const boxExistente = document.getElementById('box-cuenta-existente');
    if (!modo) return;
    function sync() {
        const v = modo.value;
        if (boxNueva) boxNueva.hidden = v !== 'nueva';
        if (boxExistente) boxExistente.hidden = v !== 'existente';
        [boxNueva, boxExistente].forEach(function (box) {
            if (!box) return;
            box.querySelectorAll('input, select, textarea').forEach(function (el) {
                el.disabled = box.hidden;
            });
        });
        const user = document.getElementById('login_username');
        const pass = document.getElementById('login_password');
        const conf = document.getElementById('login_password_confirmation');
        if (user) user.required = v === 'nueva';
        if (pass) pass.required = v === 'nueva';
        if (conf) conf.required = v === 'nueva';
    }
    modo.addEventListener('change', sync);
    sync();
})();
</script>
