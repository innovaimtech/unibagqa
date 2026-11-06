/* Bodega · Bonificación: totales en pantalla y guardado automático. El pago definitivo lo calcula el servidor. */
(function () {
    var search = document.getElementById('wbn-operator-search');
    if (search) {
        var plain = function (text) {
            return text.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
        };
        search.addEventListener('input', function () {
            var term = plain(search.value);
            document.querySelectorAll('.wbn-operator-list label').forEach(function (label) {
                label.hidden = plain(label.dataset.search).indexOf(term) === -1;
            });
        });
        search.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') { event.preventDefault(); }
        });
    }

    var money = function (n) { return '$ ' + Math.round(n).toLocaleString('es-CL'); };
    var number = function (value) {
        var parsed = Number(String(value).replace(',', '.'));
        return isFinite(parsed) ? parsed : 0;
    };

    var amounts = document.getElementById('wbn-amounts');
    if (amounts) {
        amounts.addEventListener('input', function (event) {
            var row = event.target.closest('[data-amount-row]');
            if (!row) { return; }
            var sum = 0;
            row.querySelectorAll('[data-amount]').forEach(function (input) { sum += number(input.value); });
            row.querySelector('[data-amount-total]').textContent = money(sum);
        });
    }

    var form = document.getElementById('wbn-eval');
    if (!form) { return; }
    var states = new Map();
    var state = function (op) {
        if (!states.has(op)) { states.set(op, { dirty: false, busy: false, failed: false, timer: null, waiters: [] }); }
        return states.get(op);
    };
    var status = function (op, name, text) {
        var el = op.querySelector('[data-status]');
        el.dataset.state = name;
        el.textContent = text;
    };

    function recalc(op) {
        var subtotal = 0;
        op.querySelectorAll('[data-kpi]').forEach(function (row) {
            var paid = Math.round(Number(row.dataset.amount) * number(row.querySelector('[data-pct]').value) / 100);
            row.querySelector('[data-paid]').textContent = money(paid);
            subtotal += paid;
        });
        op.dataset.subtotal = subtotal;
        op.querySelectorAll('[data-note]').forEach(function (input) {
            input.parentElement.querySelector('[data-note-pct]').textContent = Math.floor(number(input.value)) * 10 + '%';
        });
        op.querySelector('[data-total]').textContent = money(subtotal + number(op.querySelector('[data-additional]').value));
    }

    function totals() {
        var kpis = 0, additional = 0;
        form.querySelectorAll('[data-op]').forEach(function (op) {
            if (op.dataset.subtotal === undefined) { recalc(op); }
            kpis += Number(op.dataset.subtotal) || 0;
            additional += number(op.querySelector('[data-additional]').value);
        });
        document.getElementById('wbn-sum-kpis').textContent = money(kpis);
        document.getElementById('wbn-sum-additional').textContent = money(additional);
        document.getElementById('wbn-sum-total').textContent = money(kpis + additional);
    }

    function pendingCount() {
        var pending = 0;
        form.querySelectorAll('[data-status]').forEach(function (el) {
            if (el.dataset.state === 'none') { pending++; }
        });
        document.getElementById('wbn-pending').textContent = pending;
    }

    async function saveOp(op) {
        var s = state(op);
        clearTimeout(s.timer);
        s.timer = null;
        if (s.busy || !s.dirty) { return; }
        var invalid = op.querySelector('input:invalid');
        if (invalid) {
            s.failed = true;
            s.dirty = false;
            status(op, 'error', 'Revisa los valores marcados');
            s.waiters.splice(0).forEach(function (resolve) { resolve(); });
            return;
        }
        s.dirty = false;
        s.busy = true;
        var body = new URLSearchParams({ _csrf: form.elements.namedItem('_csrf').value, month: form.dataset.month, ajax: '1' });
        op.querySelectorAll('input').forEach(function (input) { body.append(input.name, input.value); });
        status(op, 'pending', 'Guardando...');
        try {
            var response = await fetch(form.action, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true });
            var result = await response.json();
            if (!response.ok || !result.ok) { throw new Error(result.error || 'No se pudo guardar'); }
            s.failed = false;
            if (!s.dirty) {
                status(op, 'saved', 'Guardado');
                var name = op.querySelector('input[type=hidden]').value;
                if (result.totals && result.totals[name] !== undefined) {
                    op.querySelector('[data-total]').textContent = money(result.totals[name]);
                }
            }
        } catch (error) {
            s.failed = true;
            status(op, 'error', 'No guardado: ' + error.message);
        } finally {
            s.busy = false;
            pendingCount();
            if (s.dirty) { saveOp(op); } else { s.waiters.splice(0).forEach(function (resolve) { resolve(); }); }
        }
    }

    function queue(op, delay) {
        var s = state(op);
        s.dirty = true;
        clearTimeout(s.timer);
        status(op, 'pending', 'Pendiente de guardar');
        s.timer = setTimeout(function () { saveOp(op); }, delay);
    }

    form.addEventListener('input', function (event) {
        var op = event.target.closest('[data-op]');
        if (!op) { return; }
        recalc(op);
        totals();
        queue(op, 700);
    });
    form.addEventListener('change', function (event) {
        var op = event.target.closest('[data-op]');
        if (op && state(op).dirty) { saveOp(op); }
    });

    var exportLink = document.getElementById('wbn-export');
    if (exportLink) {
        exportLink.addEventListener('click', async function (event) {
            var waiting = [];
            states.forEach(function (s, op) {
                if (s.dirty || s.busy) {
                    waiting.push(new Promise(function (resolve) { s.waiters.push(resolve); saveOp(op); }));
                }
            });
            var anyFailed = function () {
                var failed = false;
                states.forEach(function (s) { failed = failed || s.failed; });
                return failed;
            };
            if (!waiting.length && !anyFailed()) { return; }
            event.preventDefault();
            var url = exportLink.href;
            await Promise.all(waiting);
            if (anyFailed()) {
                window.alert('Hay evaluaciones sin guardar. Revísalas antes de descargar el Excel.');
                return;
            }
            window.location.assign(url);
        });
    }
})();
