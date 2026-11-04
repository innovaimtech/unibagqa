<?php

declare(strict_types=1);

/**
 * Cabecera común de todas las pantallas de configuración de bonos:
 * título, estado de edición y pestañas para cambiar de bono (sin recargar a mano un selector).
 * Solo presentación; los formularios de cada bono no cambian.
 */
function bonusConfigHeader(string $activeCode, bool $canEdit, ?string $month = null): string
{
    $tabs = [
        'bonoflexo' => ['🎨', 'Flexo'],
        'bonoseri' => ['🖼️', 'Serigrafía'],
        'bonocys' => ['✂️', 'Corte y Sellado'],
        'bonopulp' => ['📦', 'Pulp'],
        'bonoayudante' => ['🤝', 'Ayudante'],
        'bonomantencion' => ['🛠️', 'Mantención'],
    ];
    $monthQuery = ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1 && $activeCode !== 'bonoflexo') ? '&month=' . rawurlencode($month) : '';
    $html = '<style>
        main:has(.bch){max-width:none!important;width:100%;box-sizing:border-box}
        .bch{background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 4px 16px rgba(15,23,42,.04);margin-bottom:16px;overflow:hidden}
        .bch-top{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding:18px 22px 14px}
        .bch-top h2{margin:0;font-size:21px;font-weight:900;color:#0f172a;letter-spacing:-.01em}
        .bch-top p{margin:4px 0 0;color:#64748b;font-size:13px}
        .bch-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:12px;font-weight:800;background:#dcfce7;color:#166534}
        .bch-pill.off{background:#f1f5f9;color:#64748b}
        .bch-tabs{display:flex;gap:6px;padding:0 16px;border-top:1px solid #e2e8f0;background:#f8fafc;overflow-x:auto;scrollbar-width:thin}
        .bch-tab{display:inline-flex;align-items:center;gap:7px;padding:13px 16px;font-size:14px;font-weight:800;color:#64748b;text-decoration:none;border-bottom:3px solid transparent;white-space:nowrap}
        .bch-tab:hover{color:#0f172a;background:#f1f5f9}
        .bch-tab.active{color:#0f766e;border-bottom-color:#00A9A6;background:#fff}
        @media(max-width:640px){.bch-top{padding:14px 16px 12px}.bch-tab{padding:12px 12px;font-size:13px}}
    </style>';
    $html .= '<div class="bch"><div class="bch-top"><div><h2>Configuración de bonificaciones</h2></div>'
        . '<span class="bch-pill' . ($canEdit ? '' : ' off') . '">' . ($canEdit ? '✔ Edición habilitada' : '🔒 Solo lectura') . '</span></div>'
        . '<nav class="bch-tabs" aria-label="Bonos">';
    foreach ($tabs as $code => [$icon, $label]) {
        $html .= '<a class="bch-tab' . ($code === $activeCode ? ' active' : '') . '"' . ($code === $activeCode ? ' aria-current="page"' : '')
            . ' href="/bonificaciones?view=configuracion&amp;bonus=' . h($code) . $monthQuery . '"><span aria-hidden="true">' . $icon . '</span>' . h($label) . '</a>';
    }
    return $html . '</nav></div>';
}
