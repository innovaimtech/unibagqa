<?php
    return <<<'HTML'
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style id="unibag-date-picker-style">
.flatpickr-calendar {
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
    border: 1px solid #CBD5E1;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.16);
    border-radius: 12px;
}
.flatpickr-months .flatpickr-month,
.flatpickr-current-month .flatpickr-monthDropdown-months,
.flatpickr-weekdays,
.flatpickr-weekday,
.flatpickr-day {
    text-transform: none;
}
.flatpickr-current-month .flatpickr-monthDropdown-months,
.flatpickr-current-month .flatpickr-monthDropdown-months .flatpickr-monthDropdown-month {
    background-color: #FFFFFF !important;
    color: #0F172A !important;
    font-weight: 700;
}
.flatpickr-day.selected,
.flatpickr-day.startRange,
.flatpickr-day.endRange,
.flatpickr-day.selected:hover,
.flatpickr-day.selected:focus {
    background: #00A9A6 !important;
    border-color: #00A9A6 !important;
    color: #FFFFFF !important;
}
.flatpickr-day.today {
    border-color: #00A9A6;
}
.flatpickr-input,
input[data-unibag-datepicker-visible="1"] {
    letter-spacing: 0.2px;
    background-color: #FFFFFF !important;
    color: #0F172A !important;
    -webkit-text-fill-color: #0F172A !important;
    border: 1px solid #CBD5E1;
    border-radius: 9px;
    height: 40px;
    padding: 0 12px;
    font-size: 13px;
    font-weight: 600;
    box-sizing: border-box;
}
input[data-unibag-datepicker-visible="1"]::placeholder {
    color: #64748B !important;
    -webkit-text-fill-color: #64748B !important;
    opacity: 1 !important;
}
input[data-unibag-datepicker-visible="1"]:focus {
    border-color: #00A9A6 !important;
    box-shadow: 0 0 0 3px rgba(0, 169, 166, 0.18) !important;
    outline: none !important;
}
input[data-unibag-datepicker-visible="1"]:disabled {
    background-color: #F1F5F9 !important;
    color: #475569 !important;
    -webkit-text-fill-color: #475569 !important;
    opacity: 1 !important;
}
</style>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script id="unibag-date-picker-script">
(function () {
    function copySafeInlineStyles(sourceInput, altInput) {
        if (!sourceInput || !altInput || !sourceInput.style) return;
        var props = ['width', 'minWidth', 'maxWidth', 'height', 'flex'];
        props.forEach(function (prop) {
            if (sourceInput.style[prop]) {
                altInput.style[prop] = sourceInput.style[prop];
            }
        });
    }

    function bindDateInputs(root) {
        if (!window.flatpickr) {
            return;
        }
        var scope = root && root.querySelectorAll ? root : document;
        var inputs = scope.querySelectorAll('input[type="date"]:not([data-unibag-datepicker-bound])');
        inputs.forEach(function (input) {
            var originalInlineStyle = input.getAttribute('style') || '';
            input.setAttribute('lang', 'es');
            input.dataset.unibagDatepickerBound = '1';

            var picker = flatpickr(input, {
                locale: (window.flatpickr && window.flatpickr.l10ns && window.flatpickr.l10ns.es) ? window.flatpickr.l10ns.es : 'es',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: true,
                disableMobile: true,
                monthSelectorType: 'static',
                onReady: function (_selectedDates, _dateStr, instance) {
                    if (!instance.altInput) return;
                    instance.altInput.setAttribute('placeholder', 'dd/mm/aaaa');
                    instance.altInput.setAttribute('autocomplete', 'off');
                    instance.altInput.dataset.unibagDatepickerVisible = '1';
                    instance.altInput.classList.add('ub-datepicker-input');
                    instance.altInput.lang = 'es';
                    if (originalInlineStyle.indexOf('width') !== -1) {
                        copySafeInlineStyles(input, instance.altInput);
                    }
                    if (input.disabled) instance.altInput.disabled = true;
                    if (input.readOnly) instance.altInput.readOnly = true;
                },
                onChange: function () {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                },
                onValueUpdate: function () {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });

            if (picker && picker.altInput && input.className) {
                picker.altInput.className += ' ' + input.className;
            }
        });
    }

    function initDateInputs() {
        if (!window.flatpickr) {
            window.setTimeout(initDateInputs, 120);
            return;
        }
        bindDateInputs(document);
        if (!window.__unibagDatePickerObserver) {
            window.__unibagDatePickerObserver = new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (!node || node.nodeType !== 1) return;
                        if (node.matches && node.matches('input[type="date"]')) {
                            bindDateInputs(node.parentNode || document);
                        } else if (node.querySelectorAll) {
                            bindDateInputs(node);
                        }
                    });
                });
            });
            window.__unibagDatePickerObserver.observe(document.documentElement || document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDateInputs);
    } else {
        initDateInputs();
    }
})();
</script>
HTML;
