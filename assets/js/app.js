/* =====================================================================
   SIGExpediente - Comportamentos de interface
   ===================================================================== */
(function () {
    'use strict';

    var body = document.body;

    /* ---- Barra lateral (mobile) ---------------------------------- */
    var menuToggle = document.getElementById('menuToggle');
    var sidebarClose = document.getElementById('sidebarClose');
    var overlay = document.getElementById('sidebarOverlay');

    function closeSidebar() { body.classList.remove('sidebar-open'); }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            body.classList.toggle('sidebar-open');
        });
    }
    if (sidebarClose) { sidebarClose.addEventListener('click', closeSidebar); }
    if (overlay) { overlay.addEventListener('click', closeSidebar); }
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { closeSidebar(); }
    });

    /* ---- Mensagens flash ------------------------------------------ */
    document.querySelectorAll('.flash').forEach(function (flash) {
        var close = flash.querySelector('.flash-close');
        if (close) {
            close.addEventListener('click', function () { flash.remove(); });
        }
        var type = Array.prototype.find.call(flash.classList, function (c) { return c.indexOf('flash-') === 0; });
        if (type !== 'flash-danger' && type !== 'flash-warning') {
            setTimeout(function () {
                flash.style.transition = 'opacity .3s, transform .3s';
                flash.style.opacity = '0';
                flash.style.transform = 'translateY(-4px)';
                setTimeout(function () { flash.remove(); }, 320);
            }, 6500);
        }
    });

    /* ---- Confirmação de acções destrutivas ----------------------- */
    document.querySelectorAll('[data-confirm]:not(form)').forEach(function (el) {
        el.addEventListener('click', function (ev) {
            var msg = el.getAttribute('data-confirm') || 'Confirma esta operação?';
            if (!window.confirm(msg)) {
                ev.preventDefault();
                ev.stopPropagation();
            }
        });
    });
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            var msg = form.getAttribute('data-confirm') || 'Confirma esta operação?';
            if (!window.confirm(msg)) { ev.preventDefault(); }
        });
    });

    /* ---- Campos de nome: apenas letras --------------------------- */
    var regexNome = /[^\p{L}\s'’\-.]/gu;
    document.querySelectorAll('[data-letras]').forEach(function (input) {
        input.addEventListener('input', function () {
            var cursor = input.selectionStart;
            var limpo = input.value.replace(regexNome, '');
            if (limpo !== input.value) {
                input.value = limpo;
                try { input.setSelectionRange(cursor - 1, cursor - 1); } catch (e) {}
            }
        });
    });

    /* ---- Campos apenas numéricos --------------------------------- */
    document.querySelectorAll('[data-digitos]').forEach(function (input) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D+/g, '');
        });
    });

    /* ---- Mostrar/ocultar palavra-passe --------------------------- */
    document.querySelectorAll('.toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            if (!input) { return; }
            var visivel = input.type === 'text';
            input.type = visivel ? 'password' : 'text';
            btn.setAttribute('aria-label', visivel ? 'Mostrar palavra-passe' : 'Ocultar palavra-passe');
            btn.classList.toggle('is-visible', !visivel);
        });
    });

    /* ---- Selects de filtro: submeter ao mudar -------------------- */
    document.querySelectorAll('select[data-auto-submit]').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var form = sel.closest('form');
            if (form) { form.submit(); }
        });
    });

    /* ---- Limitar datas anteriores a hoje ------------------------- */
    var hoje = new Date();
    var iso = hoje.getFullYear() + '-' + String(hoje.getMonth() + 1).padStart(2, '0') + '-' + String(hoje.getDate()).padStart(2, '0');
    document.querySelectorAll('input[data-min-hoje]').forEach(function (input) {
        if (!input.min) {
            input.min = input.type === 'datetime-local' ? iso + 'T00:00' : iso;
        }
    });

    /* ---- Linhas clicáveis ---------------------------------------- */
    document.querySelectorAll('tr[data-href]').forEach(function (tr) {
        tr.addEventListener('click', function (ev) {
            if (ev.target.closest('a, button, input, select, label')) { return; }
            window.location.href = tr.getAttribute('data-href');
        });
    });

    /* ---- Expediente interno: número de processo opcional ---------- */
    document.querySelectorAll('[data-interno-toggle]').forEach(function (chk) {
        var target = document.getElementById('nrm_processo');
        var req = document.getElementById('nrm_processo_req');
        var sync = function () {
            if (!target) { return; }
            target.disabled = chk.checked;
            target.required = !chk.checked;
            if (chk.checked) { target.value = ''; }
            if (req) { req.style.display = chk.checked ? 'none' : ''; }
        };
        chk.addEventListener('change', sync);
        sync();
    });

    /* ---- Requerido civil: patente opcional ------------------------ */
    document.querySelectorAll('[data-civil-toggle]').forEach(function (chk) {
        var target = document.getElementById('patente');
        var req = document.getElementById('patente_req');
        var sync = function () {
            if (!target) { return; }
            target.disabled = chk.checked;
            target.required = !chk.checked;
            if (chk.checked) { target.value = ''; }
            if (req) { req.style.display = chk.checked ? 'none' : ''; }
        };
        chk.addEventListener('change', sync);
        sync();
    });

    /* ---- Contador de caracteres ---------------------------------- */
    document.querySelectorAll('[data-counter]').forEach(function (input) {
        var target = document.querySelector(input.getAttribute('data-counter'));
        if (!target) { return; }
        var max = parseInt(input.getAttribute('maxlength') || '0', 10);
        var update = function () {
            target.textContent = (max ? input.value.length + '/' + max : input.value.length + '');
        };
        input.addEventListener('input', update);
        update();
    });
})();
