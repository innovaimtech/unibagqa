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
