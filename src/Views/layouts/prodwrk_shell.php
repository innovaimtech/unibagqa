<?php
    ?>
<!DOCTYPE html>
<html lang="es" style="padding:0px;margin:0px;width:100%;height:100%">
<head>
    <meta charset="utf-8">
    <title><?=htmlspecialchars($title)?> - Producción Operador</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css"><link rel="stylesheet" href="/assets/css/unibag-ui.css?v=20261031"><link rel="stylesheet" href="/assets/css/unibag-responsive.css?v=20261103">
    <style>
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 13px;
            color: #333333;
            background-color: #EDF0F6;
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }
        * { box-sizing: border-box; }
        .tdheader {
            border-bottom: 1px solid #DDDDDD;
            color: #115452;
            font-weight: bold;
            font-size: 13px;
            padding: 7px 10px;
            background-color: #E8F0EF;
        }
        .tdleft {
            border-bottom: 1px solid #DDDDDD;
            font-weight: bold;
            background-color: #EEEEEE;
            padding: 6px 10px;
            font-size: 13px;
            color: #333333;
        }
        .tdnrm {
            font-size: 13px;
            border-bottom: 1px solid #DDDDDD;
            background-color: #FFFFFF;
            padding: 6px 10px;
            color: #333333;
        }
        .btnred {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #D64141;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btnred:hover { background-color: #b82e2e; }
        .btngreen {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #00A85A;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btngreen:hover { background-color: #008f4c; }
        .btnblue {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #3F4C9B;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btnblue:hover { background-color: #2f3a7a; }
        .btnorange {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #E1A500;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btnorange:hover { background-color: #c48f00; }
        .btngrey {
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            padding: 8px 14px;
            background-color: #888888;
            color: white !important;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            user-select: none;
            border: none;
            text-decoration: none;
        }
        .btngrey:hover { background-color: #6e6e6e; }
        .inptxt {
            padding: 6px 8px;
            font-size: 13px;
            font-family: Arial, sans-serif;
            border: 1px solid #CCCCCC;
            border-radius: 4px;
            color: #333333;
            background-color: #FFFFFF;
        }
        .msg_save_ok { color: #00A85A; font-weight: bold; }
        .msg_save_err { color: #D64141; font-weight: bold; }
        .tdmenu a { color: inherit; text-decoration: none; display: block; }
    </style>
</head>
<body style="background-color:#EDF0F6;padding:0px;margin:0px;width:100%;height:100%">

<!-- Topbar #008A87 / #00A9A4 -->
<table border="0" width="100%" cellpadding="0" cellspacing="0" height="60" style="table-layout:fixed">
<tr>
    <td width="280" style="background-color:#008A87" valign="middle" align="left" class="tdmenu">
        <div style="padding-left:20px;display:flex;align-items:center;gap:10px">
            <span style="font-weight:900;color:#FFFFFF;font-size:22px;letter-spacing:1px">UNIBAG</span>
            <span style="background:#0E7572;color:#FFF;font-size:11px;padding:2px 8px;border-radius:3px;font-weight:bold">PROD</span>
        </div>
    </td>
    <td style="background-color:#00A9A4" valign="middle" align="left">
        <table border="0" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="50">
                <i class="fa fa-fw fa-bars" style="font-size:22px;color:#FFFFFF;margin-left:15px;cursor:pointer"
                   onclick="var m=document.querySelectorAll('.tdmenu'); m.forEach(function(el){el.style.display=(el.style.display==='none'?'':'none');});" title="Alternar menú lateral"></i>
            </td>
            <td style="font-size:12px;color:white;line-height:1.4;padding:0 10px">
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <span><i class="fa fa-fw fa-user" style="color:#FFFFFF;"></i> USUARIO: <b><?=htmlspecialchars($operatorName !== '' ? $operatorName : 'OPERARIO')?></b></span>
                    <span style="opacity:0.65">|</span>
                    <span><i class="fa fa-fw fa-home" style="color:#FFFFFF;"></i> PLANTA: <?=htmlspecialchars($plantaName !== '' ? $plantaName : 'PLANTA 1')?></span>
                </div>
            </td>
            <td style="font-size:12px;color:white;padding-right:15px" align="right">
                <a href="/logout" style="background:#DC2626;color:#FFFFFF;border:1px solid #B91C1C;border-radius:4px;padding:6px 14px;text-decoration:none;font-size:12px;font-weight:bold;display:inline-flex;align-items:center;gap:6px;box-shadow:0 1px 3px rgba(0,0,0,0.2);cursor:pointer;transition:background 0.2s" onclick="return confirm('¿Desea cerrar sesión del sistema?');" title="Cerrar sesión / Salir">
                    <i class="fa fa-sign-out-alt"></i> <span>Cerrar Sesión</span>
                </a>
            </td>
        </tr>
        </table>
    </td>
</tr>
</table>

<!-- Body Table with Sidebar #0E7572 -->
<table border="0" width="100%" cellpadding="0" cellspacing="0" style="min-height: calc(100vh - 60px)">
<tr>
    <td width="280" style="background-color:#0E7572;vertical-align:top" class="tdmenu">
        <div style="width:100%;background-color:#116663;">
            <div style="padding:10px 15px;font-size:12px;color:#CCCCCC;font-weight:bold;text-transform:uppercase;letter-spacing:0.5px">Navegación</div>
        </div>
        <div style="clear:both;height:10px"></div>
        <div style="font-size:14px;padding-left:15px;color:white;font-weight:bold">
            <i class="fa fa-fw fa-laptop-code" style="font-size:16px;"></i> Asignación máquina
            <span style="float:right;padding-right:15px"><i class="fa fa-fw fa-chevron-down" style="font-size:12px"></i></span>
        </div>
        <div style="clear:both;height:8px"></div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 0 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 0 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/machines';">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:<?=$mid === 0 ? '#00A85A' : '#7FA8A5'?>"></i> Iniciar / Terminar turno
        </div>
        
        <div style="clear:both;height:12px"></div>
        <div style="font-size:14px;padding-left:15px;color:white;font-weight:bold">
            <i class="fa fa-fw fa-calendar-alt" style="font-size:16px;"></i> Ordenes de trabajo
            <span style="float:right;padding-right:15px"><i class="fa fa-fw fa-chevron-down" style="font-size:12px"></i></span>
        </div>
        <div style="clear:both;height:8px"></div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 1 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 1 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/work-orders/new';">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:<?=$mid === 1 ? '#00A85A' : '#7FA8A5'?>"></i> Iniciar nueva OT
        </div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 5 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 5 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/work-orders/active';">
            <i class="fa fa-fw fa-circle" style="font-size:12px;color:<?=$mid === 5 ? '#00A85A' : '#7FA8A5'?>"></i> En cursos
        </div>
        <div style="padding:10px 25px;background:<?=$mid === 3 ? '#115452' : 'transparent'?>">
            <a href="/production/work-orders/history" style="color:white;text-decoration:none">Histórico</a>
        </div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 7 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 7 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/materials/request';">
            <i class="fa fa-fw fa-dolly" style="font-size:12px;color:<?=$mid === 7 ? '#00A85A' : '#7FA8A5'?>"></i> Solicitar materiales
        </div>
        <div style="cursor:pointer;font-size:14px;padding-left:25px;color:<?=$mid === 8 ? '#FFFFFF' : '#B8D5D3'?>;background-color:<?=$mid === 8 ? '#115452' : 'transparent'?>;padding-top:10px;padding-bottom:10px"
             onclick="location.href='/production/materials/bodega3000';">
            <i class="fa fa-fw fa-warehouse" style="font-size:12px;color:<?=$mid === 8 ? '#00A85A' : '#7FA8A5'?>"></i> Bobinas Bodega 3000
        </div>
        
        

        <div style="clear:both;height:14px"></div>
        <div style="border-top:1px solid rgba(255,255,255,0.18);margin:10px 15px"></div>
        <div style="cursor:pointer;font-size:13px;padding-left:25px;color:#FECACA;padding-top:8px;padding-bottom:8px"
             onclick="if(confirm('¿Desea cerrar sesión del sistema?')) location.href='/logout';">
            <i class="fa fa-fw fa-sign-out-alt" style="font-size:13px;color:#F87171"></i> Cerrar sesión
        </div>
    </td>
    <td valign="top" style="padding:20px;background-color:#EDF0F6;vertical-align:top">
        <?php if (isset($_GET['lunch_saved'])): ?>
            <div style="background:#E0F2FE;border:1px solid #7DD3FC;border-radius:4px;padding:12px 15px;margin-bottom:15px;color:#0369A1">
                <i class="fa fa-coffee" style="font-size:16px"></i> <b>Colación registrada exitosamente.</b>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['standby_started'])): ?>
            <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:4px;padding:12px 15px;margin-bottom:15px;color:#92400E">
                <i class="fa fa-pause-circle" style="color:#D97706;font-size:16px"></i> <b>Evento Sin Producción iniciado.</b> La máquina figura en estado de espera informada.
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['standby_ended'])): ?>
            <div style="background:#D1FAE5;border:1px solid #6EE7B7;border-radius:4px;padding:12px 15px;margin-bottom:15px;color:#065F46">
                <i class="fa fa-play-circle" style="color:#059669;font-size:16px"></i> <b>Evento Sin Producción finalizado.</b> La máquina vuelve a estar disponible para operar.
            </div>
        <?php endif; ?>
        <?=$content?>
    </td>
</tr>
</table>

<script>
(function() {
    window.handleBastidorSubmit = function(e, form) {
        if (e) {
            if (typeof e.preventDefault === 'function') e.preventDefault();
            if (typeof e.stopPropagation === 'function') e.stopPropagation();
        }
        if (!form) return false;

        var select = form.querySelector('select[name="bastidoramt"]');
        var btn = form.querySelector('.js-bastidor-btn') || form.querySelector('button[type="submit"], input[type="submit"]');
        var feedback = form.querySelector('.js-bastidor-feedback');
        var val = select ? select.value : '';

        if (!val) {
            if (feedback) {
                feedback.style.display = 'inline-flex';
                feedback.style.color = '#DC2626';
                feedback.innerHTML = '<i class="fa fa-exclamation-triangle"></i> Seleccione un valor';
            }
            return false;
        }

        var originalHtml = btn ? (btn.innerHTML || btn.value) : '';
        if (btn) {
            btn.disabled = true;
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Guardando...';
            } else {
                btn.value = 'Guardando...';
            }
            btn.style.opacity = '0.7';
        }
        if (feedback) {
            feedback.style.display = 'none';
            feedback.innerHTML = '';
        }

        var agid = (form.querySelector('input[name="agid"]') || {}).value || '';
        var reqId = (form.querySelector('input[name="req_id"]') || {}).value || '';
        var url = '/production/work-orders/operate?mode=setspecparams&ajax=1&agid=' + encodeURIComponent(agid) + '&req_id=' + encodeURIComponent(reqId) + '&bastidoramt=' + encodeURIComponent(val);

        fetch(url, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(function(res) {
            return res.json();
        })
        .then(function(data) {
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '';
                if (btn.tagName === 'BUTTON') {
                    btn.innerHTML = '<i class="fa fa-check"></i> Guardado';
                } else {
                    btn.value = '✓ Guardado';
                }
                btn.style.backgroundColor = '#059669';
                setTimeout(function() {
                    if (btn.tagName === 'BUTTON') {
                        btn.innerHTML = originalHtml || '<i class="fa fa-save"></i> Guardar';
                    } else {
                        btn.value = originalHtml || 'Guardar';
                    }
                    btn.style.backgroundColor = '';
                }, 2500);
            }
            if (feedback) {
                feedback.style.display = 'inline-flex';
                feedback.style.color = '#166534';
                feedback.innerHTML = '<i class="fa fa-check-circle"></i> ' + (data.message || 'Guardado correctamente');
                setTimeout(function() {
                    feedback.style.display = 'none';
                }, 4000);
            }

            // Sincronizar todos los selects de bastidor en la pantalla
            document.querySelectorAll('.js-bastidor-select').forEach(function(s) {
                if (s) s.value = val;
            });

            // Si estamos en la pantalla de finalizar orden con multiplicador activo en el formulario, sincronizarlo y recalcular
            var formMultiplier = document.getElementById('idx_seri_bastidor_multiplier');
            if (formMultiplier) {
                formMultiplier.value = val;
                if (typeof calcSeriUnitsFromCounters === 'function') {
                    calcSeriUnitsFromCounters();
                }
            }
        })
        .catch(function(err) {
            if (btn) {
                btn.disabled = false;
                btn.style.opacity = '';
                if (btn.tagName === 'BUTTON') {
                    btn.innerHTML = originalHtml || '<i class="fa fa-save"></i> Guardar';
                } else {
                    btn.value = originalHtml || 'Guardar';
                }
            }
            if (feedback) {
                feedback.style.display = 'inline-flex';
                feedback.style.color = '#DC2626';
                feedback.innerHTML = '<i class="fa fa-exclamation-circle"></i> Error al guardar';
            }
        });

        return false;
    };

    // Protección global contra doble clic y envíos duplicados en formularios de producción
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;

        if (form.classList.contains('js-bastidor-form')) {
            return;
        }

        if (form.getAttribute('data-submitting') === 'true') {
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }

        form.setAttribute('data-submitting', 'true');

        var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        btns.forEach(function(btn) {
            btn.setAttribute('data-original-html', btn.innerHTML || btn.value);
            if (btn.tagName === 'BUTTON') {
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Procesando...';
            } else {
                btn.value = 'Procesando...';
            }
            btn.style.opacity = '0.65';
            btn.style.pointerEvents = 'none';
        });

        setTimeout(function() {
            form.removeAttribute('data-submitting');
            btns.forEach(function(btn) {
                var orig = btn.getAttribute('data-original-html');
                if (orig) {
                    if (btn.tagName === 'BUTTON') btn.innerHTML = orig;
                    else btn.value = orig;
                }
                btn.style.opacity = '';
                btn.style.pointerEvents = '';
            });
        }, 8000);
    }, true);
})();
</script>
</body>
</html>
<?php


