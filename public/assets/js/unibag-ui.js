/* ==========================================================================
   UNIBAG ERP & TRAZABILIDAD · COMPORTAMIENTO UI COMPARTIDO
   Archivo: public/assets/js/unibag-ui.js
   Objetivo:
   - Sincronización universal de filtros Período (26 al 25) vs. Rango de fechas.
   - Normalización accesible de controles dinámicos y datepickers.
   ========================================================================== */
(function () {
    'use strict';

    function syncPeriodRangeForm(selectEl) {
        if (!selectEl || !selectEl.form) return;
        var form = selectEl.form;
        var isRange = selectEl.value === 'range';

        var periodGroups = form.querySelectorAll('[data-filter-group="period"], .bonus-filter-period-box');
        var rangeGroups = form.querySelectorAll('[data-filter-group="range"], .bonus-filter-range-box');

        if (periodGroups.length === 0 && rangeGroups.length === 0) {
            return;
        }

        periodGroups.forEach(function (group) {
            group.classList.toggle('is-hidden', isRange);
            group.style.display = isRange ? 'none' : '';
            group.querySelectorAll('input, select').forEach(function (ctrl) {
                if (ctrl === selectEl) return;
                if (ctrl.dataset && ctrl.dataset.unibagDatepickerVisible === '1') {
                    ctrl.disabled = isRange;
                } else if (group.hasAttribute('data-filter-group')) {
                    ctrl.disabled = isRange;
                }
            });
        });

        rangeGroups.forEach(function (group) {
            group.classList.toggle('is-hidden', !isRange);
            group.style.display = !isRange ? 'none' : '';
            group.querySelectorAll('input, select').forEach(function (ctrl) {
                if (ctrl === selectEl) return;
                if (ctrl.dataset && ctrl.dataset.unibagDatepickerVisible === '1') {
                    ctrl.disabled = !isRange;
                } else if (group.hasAttribute('data-filter-group')) {
                    ctrl.disabled = !isRange;
                }
            });
        });
    }

    function initUnifiedFilters(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var selects = scope.querySelectorAll('select[name="filter_type"]:not([data-ub-filter-bound])');
        selects.forEach(function (sel) {
            sel.dataset.ubFilterBound = '1';
            sel.addEventListener('change', function () {
                syncPeriodRangeForm(sel);
            });
            syncPeriodRangeForm(sel);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initUnifiedFilters(document);
        });
    } else {
        initUnifiedFilters(document);
    }

    window.UnibagUI = {
        initUnifiedFilters: initUnifiedFilters,
        syncPeriodRangeForm: syncPeriodRangeForm
    };
})();

// Barra de carga: al hacer clic en un enlace o enviar un formulario se ve de inmediato que la página está cargando.
(function () {
  var bar = null, timer = null;
  function hide() {
    if (timer) { clearTimeout(timer); timer = null; }
    if (bar) { bar.classList.remove('on'); }
  }
  function show() {
    if (!bar) {
      bar = document.createElement('div');
      bar.id = 'nav-progress';
      document.body.appendChild(bar);
    }
    hide();
    void bar.offsetWidth;
    bar.classList.add('on');
    timer = setTimeout(hide, 10000); // Descargas y otros casos en que la página no cambia.
  }
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest ? e.target.closest('a[href]') : null;
    if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
    var href = a.getAttribute('href') || '';
    if (href === '' || href.charAt(0) === '#' || /^(javascript|mailto|tel):/i.test(href)) return;
    if (/[?&]download=|export|\.(xlsx?|pdf|docx?|pptx?|csv|zip)(\?|$)/i.test(href)) return;
    if (a.origin !== location.origin) return;
    show();
  });
  document.addEventListener('submit', function (e) {
    if (e.defaultPrevented || (e.target.target && e.target.target !== '_self')) return;
    show();
  });
  window.addEventListener('pageshow', hide);
})();
