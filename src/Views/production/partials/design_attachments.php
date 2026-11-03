<?php
    $imgHash = trim((string)($agenda['fab_design_imagehash'] ?? $agenda['item_img_hash'] ?? ''));
    $hasImg = ($imgHash !== '' && $imgHash !== 'dummy');
    $imgUrl = $hasImg ? ('/production/design-image?folder=docs.order&hash=' . rawurlencode($imgHash)) : '';
    $prdNumber = (string)($agenda['prd_number'] ?? '');
    $custName = (string)($agenda['cust_name'] ?? '');
    $designTitle = $fullDesign !== '' ? $fullDesign : trim((string)($agenda['fab_design_name'] ?? $agenda['item_title'] ?? ''));
    if ($medidasStr === '') {
        $w = (int)($agenda['fab_med_width'] ?? 0);
        $h = (int)($agenda['fab_med_height'] ?? 0);
        $f = (int)($agenda['fab_med_fuelle'] ?? 0);
        $medidasStr = $w > 0 ? ("{$w} x {$h}" . ($f > 0 ? " + {$f}" : "") . " cm") : '-';
    }

    $lc = $labelColspan > 1 ? ' colspan="' . $labelColspan . '"' : '';
    $vc = $valueColspan > 1 ? ' colspan="' . $valueColspan . '"' : '';

    $html = '<tr>';
    $html .= '<td class="tdleft"' . $lc . ' valign="middle" style="font-weight:bold">' . htmlspecialchars($contextLabel) . '</td>';
    $html .= '<td class="tdnrm"' . $vc . ' valign="middle">';
    if ($hasImg) {
        $html .= '<div style="display:inline-flex;align-items:center;gap:12px;flex-wrap:wrap">';
        $html .= '<a href="' . htmlspecialchars($imgUrl) . '" target="_blank" onclick="openDesignModal(\'' . htmlspecialchars($imgUrl) . '\', \'' . htmlspecialchars($prdNumber) . '\', \'' . htmlspecialchars(addslashes($custName)) . '\', \'' . htmlspecialchars(addslashes($designTitle)) . '\', \'' . htmlspecialchars($medidasStr) . '\'); return false;" title="Clic para ampliar foto en ventana flotante (con rotación)">';
        $html .= '<img src="' . htmlspecialchars($imgUrl) . '" alt="Foto Diseño" style="max-height:75px;max-width:120px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.12);cursor:pointer">';
        $html .= '</a>';
        $html .= '<a href="' . htmlspecialchars($imgUrl) . '" target="_blank" onclick="openDesignModal(\'' . htmlspecialchars($imgUrl) . '\', \'' . htmlspecialchars($prdNumber) . '\', \'' . htmlspecialchars(addslashes($custName)) . '\', \'' . htmlspecialchars(addslashes($designTitle)) . '\', \'' . htmlspecialchars($medidasStr) . '\'); return false;" class="btngrey" style="padding:5px 12px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">';
        $html .= '<i class="fa fa-fw fa-image" style="color:white;"></i> Ver Foto Completa';
        $html .= '</a>';
        $html .= '</div>';
    } else {
        $html .= '<span style="color:#94A3B8;font-style:italic">Sin imagen de diseño cargada</span>';
    }
    $html .= '</td></tr>';

    for ($x = 0; $x < 5; $x++) {
        if (!empty($agenda["req_prod_adjfile_{$x}"])) {
            $adjFile = (string)$agenda["req_prod_adjfile_{$x}"];
            $adjIsImage = (bool)preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $adjFile);
            $adjUrl = '/docs/prod/' . rawurlencode($adjFile);
            $adjComments = (string)($agenda["req_prod_adjcomments_{$x}"] ?? '');

            $html .= '<tr>';
            $html .= '<td class="tdleft"' . $lc . ' valign="middle">Adjunto #' . ($x + 1) . '</td>';
            $html .= '<td class="tdnrm"' . $vc . ' valign="middle">';
            $html .= '<div style="display:inline-flex;align-items:center;gap:10px;flex-wrap:wrap">';
            if ($adjIsImage) {
                $html .= '<a href="' . htmlspecialchars($adjUrl) . '" target="_blank" onclick="openDesignModal(\'' . htmlspecialchars($adjUrl) . '\', \'' . htmlspecialchars($prdNumber) . '\', \'' . htmlspecialchars(addslashes($custName)) . '\', \'Adjunto #' . ($x + 1) . '\', \'' . htmlspecialchars($medidasStr) . '\'); return false;" title="Clic para ampliar adjunto">';
                $html .= '<img src="' . htmlspecialchars($adjUrl) . '" alt="Adjunto #' . ($x + 1) . '" style="max-height:55px;max-width:85px;object-fit:contain;border:1px solid #CBD5E1;border-radius:4px;background:#fff;vertical-align:middle;box-shadow:0 1px 3px rgba(0,0,0,0.1);cursor:pointer">';
                $html .= '</a>';
                $html .= '<a href="' . htmlspecialchars($adjUrl) . '" target="_blank" onclick="openDesignModal(\'' . htmlspecialchars($adjUrl) . '\', \'' . htmlspecialchars($prdNumber) . '\', \'' . htmlspecialchars(addslashes($custName)) . '\', \'Adjunto #' . ($x + 1) . '\', \'' . htmlspecialchars($medidasStr) . '\'); return false;" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">';
                $html .= '<i class="fa fa-fw fa-image" style="color:white;"></i> Mostrar Imagen';
                $html .= '</a>';
            } else {
                $html .= '<a href="' . htmlspecialchars($adjUrl) . '" target="_blank" class="btngrey" style="padding:4px 10px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer">';
                $html .= '<i class="fa fa-fw fa-file" style="color:white;"></i> Descargar Documento';
                $html .= '</a>';
            }
            if ($adjComments !== '') {
                $html .= '<span style="color:#64748B;font-size:11.5px">' . htmlspecialchars($adjComments) . '</span>';
            }
            $html .= '</div>';
            $html .= '</td></tr>';
        }
    }

    return $html;

