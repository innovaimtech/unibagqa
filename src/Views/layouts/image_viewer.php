<?php
    $safeHash = htmlspecialchars($hash, ENT_QUOTES, 'UTF-8');
    $rawUrl = '/production/design-image?folder=' . rawurlencode($folder) . '&hash=' . rawurlencode($hash) . '&raw=1';
    $meta = unibagLookupDesignMetadata($hash);

    $otNum = !empty($meta['prd_number']) ? htmlspecialchars((string)$meta['prd_number']) : '';
    $ccNum = !empty($meta['req_number']) ? htmlspecialchars((string)$meta['req_number']) : '';
    $cust = !empty($meta['cust_name']) ? htmlspecialchars((string)$meta['cust_name']) : '';
    $prod = !empty($meta['item_title']) ? htmlspecialchars((string)$meta['item_title']) : '';

    ob_start();
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor de Diseño &bull; <?=$otNum !== '' ? ('OT #' . $otNum . ' &bull; ') : ''?><?=$safeHash?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; user-select: none; }
        html, body {
            width: 100%; height: 100%;
            overflow: hidden;
            background-color: #0b0f19;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #f1f5f9;
        }

        /* Top Bar */
        .top-bar {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 52px;
            background: rgba(15, 23, 42, 0.88);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 18px;
            z-index: 100;
        }
        .top-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 65vw;
        }
        .badge-brand {
            background: linear-gradient(135deg, #00A9A6, #0284C7);
            color: #ffffff;
            font-weight: 800;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        .meta-tag {
            background: rgba(2, 132, 199, 0.2);
            border: 1px solid rgba(56, 189, 248, 0.35);
            color: #7dd3fc;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 11.5px;
            flex-shrink: 0;
        }
        .meta-client {
            font-weight: 700;
            color: #f8fafc;
            flex-shrink: 0;
        }
        .meta-item {
            color: #94a3b8;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .filename {
            font-weight: 600;
            color: #cbd5e1;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .status-indicators {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .status-badge {
            font-size: 11.5px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #94a3b8;
            padding: 3px 9px;
            border-radius: 20px;
            font-variant-numeric: tabular-nums;
        }
        .status-badge b {
            color: #38bdf8;
        }
        .top-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .btn-top {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: #f1f5f9;
            font-size: 12px;
            font-weight: 500;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-top:hover {
            background: rgba(255, 255, 255, 0.18);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }

        /* Viewport Canvas */
        .viewport {
            position: absolute;
            top: 52px; bottom: 0; left: 0; right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: grab;
            overflow: hidden;
            background-color: #0b0f19;
            background-image: 
                radial-gradient(circle at center, rgba(30, 41, 59, 0.35) 0%, transparent 70%),
                linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
            background-size: 100% 100%, 32px 32px, 32px 32px;
        }
        .viewport.grabbing {
            cursor: grabbing;
        }
        .image-wrapper {
            position: relative;
            transform-origin: center center;
            will-change: transform;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .image-wrapper.no-transition {
            transition: none;
        }
        .main-image {
            max-width: 82vw;
            max-height: 78vh;
            display: block;
            object-fit: contain;
            border-radius: 4px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(255, 255, 255, 0.1);
            background: #ffffff;
            pointer-events: none;
        }

        /* Bottom Floating Dock */
        .bottom-dock {
            position: absolute;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 40px;
            padding: 6px 14px;
            display: flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 14px 40px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(0, 0, 0, 0.2);
            z-index: 100;
        }
        .dock-btn {
            background: transparent;
            border: none;
            color: #cbd5e1;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            position: relative;
        }
        .dock-btn:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
            transform: translateY(-1px);
        }
        .dock-btn:active {
            transform: translateY(1px);
            background: rgba(255, 255, 255, 0.2);
        }
        .dock-btn svg {
            width: 19px;
            height: 19px;
            fill: currentColor;
        }
        .dock-btn.highlight {
            color: #38bdf8;
        }
        .dock-btn.highlight:hover {
            background: rgba(56, 189, 248, 0.18);
            color: #7dd3fc;
        }
        .dock-sep {
            width: 1px;
            height: 20px;
            background: rgba(255, 255, 255, 0.15);
            margin: 0 4px;
        }
        .zoom-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #e2e8f0;
            padding: 0 8px;
            min-width: 48px;
            text-align: center;
            cursor: pointer;
            border-radius: 12px;
            line-height: 28px;
            font-variant-numeric: tabular-nums;
        }
        .zoom-label:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #38bdf8;
        }

        /* Keyboard Hints */
        .key-hints {
            position: absolute;
            bottom: 8px;
            right: 16px;
            font-size: 10.5px;
            color: rgba(255, 255, 255, 0.4);
            z-index: 90;
            pointer-events: none;
        }
        kbd {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 3px;
            padding: 1px 4px;
            font-size: 9.5px;
            color: #94a3b8;
        }

        /* Tooltip */
        [data-tooltip] {
            position: relative;
        }
        [data-tooltip]::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            padding: 5px 9px;
            border-radius: 6px;
            white-space: nowrap;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s ease, transform 0.15s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 200;
        }
        [data-tooltip]:hover::after {
            opacity: 1;
            transform: translateX(-50%) translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-title">
            <span class="badge-brand">UNIBAG</span>
            <?php if ($otNum !== ''): ?>
                <span class="meta-tag">OT #<b><?=$otNum?></b></span>
            <?php endif; ?>
            <?php if ($ccNum !== ''): ?>
                <span class="meta-tag">CC #<b><?=$ccNum?></b></span>
            <?php endif; ?>
            <?php if ($cust !== ''): ?>
                <span class="meta-client"><?=$cust?></span>
            <?php endif; ?>
            <?php if ($prod !== ''): ?>
                <span class="meta-item">&bull; <?=$prod?></span>
            <?php else: ?>
                <span class="filename" title="<?=$safeHash?>"><?=$safeHash?></span>
            <?php endif; ?>
        </div>
        <div class="status-indicators">
            <span class="status-badge">Rotación: <b id="lbl_rotation">0°</b></span>
            <span class="status-badge">Zoom: <b id="lbl_zoom">100%</b></span>
        </div>
        <div class="top-actions">
            <a href="<?=$rawUrl?>" target="_blank" download="<?=$safeHash?>" class="btn-top" title="Descargar imagen en tamaño original">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Descargar original
            </a>
            <button type="button" class="btn-top" onclick="window.close()" title="Cerrar esta pestaña">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Cerrar
            </button>
        </div>
    </div>

    <!-- Viewport Canvas -->
    <div class="viewport" id="viewport">
        <div class="image-wrapper" id="imgWrapper">
            <img src="<?=$rawUrl?>" alt="<?=$safeHash?>" class="main-image" id="mainImg">
        </div>
    </div>

    <!-- Bottom Floating Dock -->
    <div class="bottom-dock">
        <!-- Rotar Antihorario -90° -->
        <button class="dock-btn highlight" id="btn_rot_ccw" data-tooltip="Rotar -90° (Q)" title="Rotar 90° a la izquierda">
            <svg viewBox="0 0 24 24"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>
        </button>
        <!-- Rotar Horario +90° -->
        <button class="dock-btn highlight" id="btn_rot_cw" data-tooltip="Rotar +90° (R / Espacio)" title="Rotar 90° a la derecha">
            <svg viewBox="0 0 24 24"><path d="M11.5 8c2.65 0 5.05.99 6.9 2.6L22 7v9h-9l3.62-3.62c-1.39-1.16-3.16-1.88-5.12-1.88-3.54 0-6.55 2.31-7.6 5.5l-2.37-.78C2.92 11.03 6.85 8 11.5 8z"/></svg>
        </button>
        <!-- Rotar 180° -->
        <button class="dock-btn" id="btn_rot_180" data-tooltip="Rotar 180°" title="Rotar 180 grados">
            <svg viewBox="0 0 24 24"><path d="M7 7h10v2l3-3-3-3v2H5v6h2V7zm10 10H7v-2l-3 3 3 3v-2h12v-6h-2v4z"/></svg>
        </button>

        <div class="dock-sep"></div>

        <!-- Zoom Menos -->
        <button class="dock-btn" id="btn_zoom_out" data-tooltip="Alejar (-)" title="Disminuir zoom">
            <svg viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
        </button>
        <!-- Zoom Label / Reset to 100% -->
        <div class="zoom-label" id="btn_zoom_100" data-tooltip="Click para 100%" title="Restablecer a escala 100%">100%</div>
        <!-- Zoom Mas -->
        <button class="dock-btn" id="btn_zoom_in" data-tooltip="Acercar (+)" title="Aumentar zoom">
            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        </button>
        <!-- Fit to Screen -->
        <button class="dock-btn" id="btn_fit" data-tooltip="Ajustar a pantalla" title="Ajustar imagen completa a la ventana">
            <svg viewBox="0 0 24 24"><path d="M4 4h6v2H6v4H4V4zm16 0h-6v2h4v4h2V4zM4 20h6v-2H6v-4H4v6zm16 0h-6v-2h4v-4h2v6z"/></svg>
        </button>

        <div class="dock-sep"></div>

        <!-- Flip Horizontal (Espejo) -->
        <button class="dock-btn" id="btn_flip_h" data-tooltip="Espejo horizontal (H)" title="Voltear horizontalmente">
            <svg viewBox="0 0 24 24"><path d="M15 21h2v-2h-2v2zm4-12h2V7h-2v2zM3 5v14c0 1.1.9 2 2 2h4v-2H5V5h4V3H5c-1.1 0-2 .9-2 2zm16-2v2h2c0-1.1-.9-2-2-2zm-8 20h2V1h-2v22zm8-6h2v-2h-2v2zM15 5h2V3h-2v2zm4 8h2v-2h-2v2zm0 8c1.1 0 2-.9 2-2h-2v2z"/></svg>
        </button>
        <!-- Flip Vertical -->
        <button class="dock-btn" id="btn_flip_v" data-tooltip="Voltear vertical (V)" title="Voltear verticalmente">
            <svg viewBox="0 0 24 24" style="transform: rotate(90deg);"><path d="M15 21h2v-2h-2v2zm4-12h2V7h-2v2zM3 5v14c0 1.1.9 2 2 2h4v-2H5V5h4V3H5c-1.1 0-2 .9-2 2zm16-2v2h2c0-1.1-.9-2-2-2zm-8 20h2V1h-2v22zm8-6h2v-2h-2v2zM15 5h2V3h-2v2zm4 8h2v-2h-2v2zm0 8c1.1 0 2-.9 2-2h-2v2z"/></svg>
        </button>

        <div class="dock-sep"></div>

        <!-- Reset All -->
        <button class="dock-btn" id="btn_reset" data-tooltip="Restablecer todo (0 / Esc)" title="Restablecer rotación, zoom y posición">
            <svg viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
        </button>
        <!-- Fullscreen -->
        <button class="dock-btn" id="btn_fullscreen" data-tooltip="Pantalla completa (F)" title="Ver en pantalla completa">
            <svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
        </button>
    </div>

    <!-- Keyboard Hints -->
    <div class="key-hints">
        Atajos: <kbd>R</kbd> Rotar +90° &bull; <kbd>Q</kbd> Rotar -90° &bull; <kbd>+</kbd><kbd>-</kbd> Zoom &bull; <kbd>0</kbd> Reset &bull; <kbd>F</kbd> Pantalla completa &bull; Arrastre para mover
    </div>

    <script>
    (function() {
        var rotation = 0;
        var scale = 1.0;
        var panX = 0;
        var panY = 0;
        var flipH = false;
        var flipV = false;

        var isDragging = false;
        var startMouseX = 0;
        var startMouseY = 0;
        var startPanX = 0;
        var startPanY = 0;

        var viewport = document.getElementById('viewport');
        var wrapper = document.getElementById('imgWrapper');
        var img = document.getElementById('mainImg');

        var lblRotation = document.getElementById('lbl_rotation');
        var lblZoom = document.getElementById('lbl_zoom');
        var btnZoom100 = document.getElementById('btn_zoom_100');

        function updateTransform(animate) {
            if (animate === false) {
                wrapper.classList.add('no-transition');
            } else {
                wrapper.classList.remove('no-transition');
            }

            var sx = scale * (flipH ? -1 : 1);
            var sy = scale * (flipV ? -1 : 1);

            wrapper.style.transform = 'translate(' + Math.round(panX) + 'px, ' + Math.round(panY) + 'px) rotate(' + rotation + 'deg) scale(' + sx + ', ' + sy + ')';

            var normAngle = ((rotation % 360) + 360) % 360;
            lblRotation.textContent = normAngle + '°';

            var pct = Math.round(scale * 100);
            lblZoom.textContent = pct + '%';
            btnZoom100.textContent = pct + '%';
        }

        function rotateBy(deltaDeg) {
            rotation += deltaDeg;
            updateTransform(true);
        }

        function zoomBy(factor) {
            var newScale = Math.min(6.0, Math.max(0.15, scale * factor));
            scale = Math.round(newScale * 100) / 100;
            updateTransform(true);
        }

        function setZoom(val) {
            scale = Math.min(6.0, Math.max(0.15, val));
            panX = 0;
            panY = 0;
            updateTransform(true);
        }

        function resetAll() {
            rotation = 0;
            scale = 1.0;
            panX = 0;
            panY = 0;
            flipH = false;
            flipV = false;
            updateTransform(true);
        }

        // Pan / Dragging
        viewport.addEventListener('mousedown', function(e) {
            if (e.button !== 0) return;
            isDragging = true;
            viewport.classList.add('grabbing');
            startMouseX = e.clientX;
            startMouseY = e.clientY;
            startPanX = panX;
            startPanY = panY;
            wrapper.classList.add('no-transition');
            e.preventDefault();
        });

        window.addEventListener('mousemove', function(e) {
            if (!isDragging) return;
            var dx = e.clientX - startMouseX;
            var dy = e.clientY - startMouseY;
            panX = startPanX + dx;
            panY = startPanY + dy;
            updateTransform(false);
        });

        window.addEventListener('mouseup', function() {
            if (isDragging) {
                isDragging = false;
                viewport.classList.remove('grabbing');
                wrapper.classList.remove('no-transition');
            }
        });

        // Mouse Wheel Zoom
        viewport.addEventListener('wheel', function(e) {
            e.preventDefault();
            var factor = e.deltaY < 0 ? 1.15 : 0.87;
            zoomBy(factor);
        }, { passive: false });

        // Double Click to toggle 100% / 180%
        viewport.addEventListener('dblclick', function(e) {
            if (scale > 1.2) {
                scale = 1.0;
                panX = 0;
                panY = 0;
            } else {
                scale = 1.8;
            }
            updateTransform(true);
        });

        // Button Click Listeners
        document.getElementById('btn_rot_cw').onclick = function() { rotateBy(90); };
        document.getElementById('btn_rot_ccw').onclick = function() { rotateBy(-90); };
        document.getElementById('btn_rot_180').onclick = function() { rotateBy(180); };

        document.getElementById('btn_zoom_in').onclick = function() { zoomBy(1.25); };
        document.getElementById('btn_zoom_out').onclick = function() { zoomBy(0.8); };
        btnZoom100.onclick = function() { setZoom(1.0); };

        document.getElementById('btn_fit').onclick = function() {
            panX = 0;
            panY = 0;
            scale = 1.0;
            updateTransform(true);
        };

        document.getElementById('btn_flip_h').onclick = function() {
            flipH = !flipH;
            updateTransform(true);
        };

        document.getElementById('btn_flip_v').onclick = function() {
            flipV = !flipV;
            updateTransform(true);
        };

        document.getElementById('btn_reset').onclick = resetAll;

        // Fullscreen
        document.getElementById('btn_fullscreen').onclick = function() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function(){});
            } else {
                if (document.exitFullscreen) document.exitFullscreen();
            }
        };

        // Keyboard Shortcuts
        window.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

            var key = e.key.toLowerCase();
            if (key === 'r' || key === 'e' || e.key === 'ArrowRight') {
                rotateBy(90);
            } else if (key === 'q' || e.key === 'ArrowLeft') {
                rotateBy(-90);
            } else if (key === '+' || key === '=') {
                zoomBy(1.2);
            } else if (key === '-' || key === '_') {
                zoomBy(0.8);
            } else if (key === '0' || key === 'escape') {
                resetAll();
            } else if (key === 'h') {
                flipH = !flipH;
                updateTransform(true);
            } else if (key === 'v') {
                flipV = !flipV;
                updateTransform(true);
            } else if (key === 'f') {
                document.getElementById('btn_fullscreen').click();
            }
        });

        updateTransform(false);
    })();
    </script>
</body>
</html>
    <?php
    return ob_get_clean();

