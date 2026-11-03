<?php
    return <<<HTML
    <!-- MODAL PREVISUALIZACIÓN DE DISEÑO / FOTO DE BOLSA CON ROTACIÓN -->
    <div id="modal_design_preview" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.8);backdrop-filter:blur(3px);z-index:99999;align-items:center;justify-content:center;padding:16px;box-sizing:border-box" onclick="if(event.target===this)closeDesignModal()">
        <div style="background:#FFFFFF;border-radius:10px;max-width:960px;width:96%;max-height:94vh;display:flex;flex-direction:column;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);overflow:hidden;border:1px solid #CBD5E1">
            <div style="padding:10px 18px;background:#0F172A;color:#FFFFFF;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div style="font-weight:bold;font-size:15px;display:flex;align-items:center;gap:8px">
                    <i class="fa fa-camera" style="color:#38BDF8"></i>
                    <span id="modal_design_title">Foto de Diseño de la Bolsa</span>
                </div>
                <div style="display:flex;align-items:center;gap:6px">
                    <button type="button" onclick="rotateDesignModal(-90)" style="background:#1E293B;color:#FFFFFF;border:1px solid #475569;border-radius:4px;padding:5px 10px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Girar 90° a la izquierda (tecla L)">
                        <i class="fa fa-undo"></i> -90°
                    </button>
                    <button type="button" onclick="rotateDesignModal(90)" style="background:#1E293B;color:#FFFFFF;border:1px solid #475569;border-radius:4px;padding:5px 10px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Girar 90° a la derecha (tecla R)">
                        <i class="fa fa-redo"></i> +90°
                    </button>
                    <button type="button" onclick="resetRotateDesignModal()" style="background:#1E293B;color:#94A3B8;border:1px solid #475569;border-radius:4px;padding:5px 8px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Restablecer rotación (tecla 0)">
                        <i class="fa fa-sync-alt"></i> <span id="modal_design_rot_indicator" style="color:#38BDF8;font-weight:bold">0°</span>
                    </button>
                    <button type="button" onclick="closeDesignModal()" style="background:none;border:none;color:#94A3B8;font-size:26px;cursor:pointer;line-height:1;margin-left:6px" title="Cerrar">&times;</button>
                </div>
            </div>
            <div style="padding:16px;overflow:hidden;text-align:center;background:#0F172A;flex:1;display:flex;align-items:center;justify-content:center;min-height:300px;position:relative">
                <img id="modal_design_img" src="" alt="Diseño de bolsa" style="max-width:100%;max-height:65vh;object-fit:contain;border-radius:6px;box-shadow:0 4px 14px rgba(0,0,0,0.3);background:#FFFFFF;transition:transform 0.22s ease-in-out">
            </div>
            <div style="padding:10px 18px;background:#FFFFFF;border-top:1px solid #E2E8F0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div>
                    <div id="modal_design_subtitle" style="font-size:13px;font-weight:600;color:#1E293B"></div>
                    <div id="modal_design_meta" style="font-size:11.5px;color:#64748B"></div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <button type="button" onclick="rotateDesignModal(-90)" class="btngrey" style="padding:5px 10px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Girar 90° a la izquierda">
                        <i class="fa fa-undo"></i> Girar Izq.
                    </button>
                    <button type="button" onclick="rotateDesignModal(90)" class="btngrey" style="padding:5px 10px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:4px" title="Girar 90° a la derecha">
                        <i class="fa fa-redo"></i> Girar Der.
                    </button>
                    <a id="modal_design_link" href="#" target="_blank" class="btn" style="background:#0284C7;color:#FFFFFF;padding:6px 14px;border-radius:4px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                        <i class="fa fa-external-link-alt"></i> Ver original
                    </a>
                    <button type="button" class="btngrey" style="padding:6px 14px;font-size:12px;cursor:pointer" onclick="closeDesignModal()">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <script>
    var currentDesignRotation = 0;
    function applyDesignRotation() {
        var img = document.getElementById('modal_design_img');
        var badge = document.getElementById('modal_design_rot_indicator');
        if (!img) return;
        var norm = ((currentDesignRotation % 360) + 360) % 360;
        img.style.transform = 'rotate(' + currentDesignRotation + 'deg)';
        if (badge) badge.textContent = norm + '°';
        var isSideways = (norm === 90 || norm === 270);
        if (isSideways) {
            img.style.maxHeight = '72vw';
            img.style.maxWidth = '55vh';
        } else {
            img.style.maxHeight = '65vh';
            img.style.maxWidth = '100%';
        }
    }
    function rotateDesignModal(deg) {
        currentDesignRotation += deg;
        applyDesignRotation();
    }
    function resetRotateDesignModal() {
        currentDesignRotation = 0;
        applyDesignRotation();
    }
    function openDesignModal(imgUrl, otNumber, custName, designName, medInfo) {
        var modal = document.getElementById('modal_design_preview');
        if (!modal) return;
        currentDesignRotation = 0;
        applyDesignRotation();
        document.getElementById('modal_design_img').src = imgUrl;
        document.getElementById('modal_design_title').textContent = 'Diseño de Bolsa · OT #' + otNumber;
        document.getElementById('modal_design_subtitle').textContent = custName ? ('Cliente: ' + custName) : '';
        var meta = '';
        if (designName) meta += 'Diseño: ' + designName;
        if (medInfo) meta += (meta ? ' · ' : '') + 'Medidas: ' + medInfo;
        document.getElementById('modal_design_meta').textContent = meta;
        document.getElementById('modal_design_link').href = imgUrl;
        modal.style.display = 'flex';
    }
    function closeDesignModal() {
        var modal = document.getElementById('modal_design_preview');
        if (modal) {
            modal.style.display = 'none';
            document.getElementById('modal_design_img').src = '';
            currentDesignRotation = 0;
        }
    }
    document.addEventListener('keydown', function(e) {
        var modal = document.getElementById('modal_design_preview');
        if (!modal || modal.style.display !== 'flex') return;
        if (e.key === 'Escape') {
            closeDesignModal();
        } else if (e.key === 'r' || e.key === 'R') {
            rotateDesignModal(90);
        } else if (e.key === 'l' || e.key === 'L') {
            rotateDesignModal(-90);
        } else if (e.key === '0') {
            resetRotateDesignModal();
        }
    });
    </script>
HTML;

