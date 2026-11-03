<?php

declare(strict_types=1);

/**
 * =============================================================================
 * Módulo HTTP · Rutas de Suministro y Despacho de Materiales
 * (src/Http/MaterialSupplyModule.php)
 * =============================================================================
 *
 * Responsabilidad:
 * Gestiona los controladores y vistas HTTP del ciclo de abastecimiento:
 * 1. Rutas de Operador (/production/materials/*):
 *    - /production/materials/request: Formulario para solicitar bobinas para una máquina/OT.
 *    - /production/materials/request/assign: Asignación de OT agendada a la solicitud.
 *    - /production/materials/receive: Confirmación de recepción física en la máquina.
 * 2. Rutas de Bodega Central (/stock/material-supply/*):
 *    - /stock/material-supply: Bandeja de entrada de solicitudes pendientes en bodega.
 *    - /stock/material-supply/dispatch: Registro del despacho físico de bobinas con códigos RB-*.
 *    - /stock/material-supply/resume: Reanudación de despachos en borrador.
 *    - /stock/material-supply/cancel: Cancelación justificada de un despacho pendiente.
 *    - /stock/material-supply/accept: El encargado ACEPTA la solicitud (pasa a EN PREPARACIÓN).
 *    - /stock/material-supply/reject: RECHAZA la solicitud (motivo obligatorio).
 *    - /stock/material-supply/scan: Registra el escaneo de una bobina (una por vez, con validaciones).
 *    - /stock/material-supply/scan-void: Anula un escaneo aún no despachado (motivo obligatorio).
 *    - /stock/material-supply/cancel-request: Cancela la solicitud antes de despachar (motivo obligatorio).
 *    - /stock/material-supply/receive: El encargado confirma la recepción en Bodega 3000.
 *    - /stock/material-supply/reports: Reportes de traspasos entre bodegas.
 */

require_once __DIR__ . '/../MaterialSupplyService.php';
require_once __DIR__ . '/../MaterialSupplyRecommendations.php';

function supplySessionActor(string $name): array
{
    return ['user_id'=>(int)($_SESSION['user_id']??$_SESSION['auth_user_id']??0),
        'worker_id'=>(int)($_SESSION['wrk_id']??0),'plant_id'=>(int)($_SESSION['user_planta_id']??0),
        'name'=>$name];
}

function supplyPostedQuantity(): float
{
    $value=str_replace(',','.',trim((string)($_POST['quantity']??'')));
    if (!is_numeric($value)) throw new RuntimeException('Indica una cantidad numerica valida.');
    return MaterialSupplyService::quantity((float)$value);
}

function handleMaterialSupplyRoutes(string $path, string $method, ProductionService $production, string $name): bool
{
    $isOperator = $path === '/production/materials/request' || $path === '/production/materials/request/assign'
        || $path === '/production/materials/receive';
    $isWarehouse = $path === '/stock/material-supply' || $path === '/stock/material-supply/dispatch'
        || $path === '/stock/material-supply/resume' || $path === '/stock/material-supply/cancel'
        || in_array($path, ['/stock/material-supply/accept','/stock/material-supply/reject','/stock/material-supply/scan',
            '/stock/material-supply/scan-void','/stock/material-supply/cancel-request','/stock/material-supply/receive',
            '/stock/material-supply/reports'], true);
    if (!$isOperator && !$isWarehouse) return false;
    $actor = supplySessionActor($name);
    $service = new MaterialSupplyService(Db::erpPdo(),Db::trzPdo());
    $agId = max(0,(int)($_POST['agid'] ?? $_GET['agid'] ?? 0));
    $kind = (($_POST['material_kind'] ?? $_GET['material_kind'] ?? '') === 'inks') ? 'inks' : 'rolls';
    $back = $isWarehouse ? '/stock/material-supply' : '/production/materials/request';
    if (!$isWarehouse) $back .= '?agid='.$agId.'&material_kind='.$kind;
    try {
        if ($isWarehouse && !userCanAccessArea('ERP',sessionAreaPermissions())
            && !userCanAccessArea('RECEPTION',sessionAreaPermissions())) {
            throw new RuntimeException('La entrega requiere permisos de bodega.');
        }
        if ($method === 'POST') {
            requireCsrf();
            if ($isOperator && isErpProductionReadOnlyMode($path)) throw new RuntimeException('Esta vista de produccion es de solo lectura.');
            if ($isWarehouse) {
                $requestKey = (string)($_POST['request_key']??'');
                if ($path === '/stock/material-supply/accept') {
                    $service->accept($requestKey,$actor);
                } elseif ($path === '/stock/material-supply/reject') {
                    $service->reject($requestKey,(string)($_POST['reason']??''),$actor);
                } elseif ($path === '/stock/material-supply/scan') {
                    $service->scan($requestKey,(string)($_POST['code']??''),$actor);
                } elseif ($path === '/stock/material-supply/scan-void') {
                    $service->voidScan((string)($_POST['scan_key']??''),(string)($_POST['reason']??''),$actor);
                } elseif ($path === '/stock/material-supply/cancel-request') {
                    $service->cancelRequest($requestKey,(string)($_POST['reason']??''),$actor);
                } elseif ($path === '/stock/material-supply/receive') {
                    if (empty($_POST['receipt_confirmed'])) throw new RuntimeException('Confirma la recepción de la cantidad y los códigos.');
                    $service->receiveByWarehouse((string)($_POST['dispatch_key']??''),$actor);
                } elseif ($path === '/stock/material-supply/resume') {
                    $service->resume((string)($_POST['dispatch_key']??''),$actor);
                } elseif ($path === '/stock/material-supply/cancel') {
                    $service->cancelPending((string)($_POST['dispatch_key']??''),(string)($_POST['reason']??''),$actor);
                } else {
                    $service->dispatch((string)($_POST['dispatch_key']??''),(string)($_POST['request_key']??''),
                        supplyPostedQuantity(),(string)($_POST['codes']??''),$actor);
                }
            } else {
                if ($path === '/production/materials/receive') {
                    if (empty($_POST['receipt_confirmed'])) throw new RuntimeException('Confirma la cantidad y codigos recibidos.');
                    $service->receive((string)($_POST['dispatch_key']??''),0,$actor);
                } else {
                    if ((string)($_POST['action_mode']??'') !== 'central_supply') {
                        throw new RuntimeException('Usa Solicitar para pedir bobinas o tintas desde esta pantalla.');
                    }
                    $service->request((string)($_POST['request_key']??''),0,$actor,
                        (int)($_POST['item_id']??0),(int)($_POST['source_storehouse_id']??0),
                        supplyPostedQuantity(),(string)($_POST['notes']??''),$agId);
                }
            }
            $reopen = '';
            if ($isWarehouse && in_array($path, ['/stock/material-supply/accept','/stock/material-supply/scan','/stock/material-supply/scan-void'], true)
                && preg_match('/^[a-f0-9]{32}$/D', (string)($_POST['request_key']??'')) === 1) {
                $reopen = '&open=' . $_POST['request_key'];
            }
            redirectResponse($back . ($isWarehouse?'?':'&') . 'saved=1' . $reopen);
            return true;
        }
        if ($method !== 'GET') return false;
        if ($path === '/stock/material-supply/reports') {
            ob_start();
            renderSupplyReports($service,(string)($_GET['from']??date('Y-m-d',strtotime('-30 days'))),(string)($_GET['to']??date('Y-m-d')));
            $body = ob_get_clean();
            render('Reportes de Traspasos',$body);
            return true;
        }
        if ($isWarehouse) {
            ob_start();
            renderSupplyRequestHistory($service,$service->requests(),0,true);
            $body = ob_get_clean();
            render('Solicitudes de Materiales',$body);
        } else {
            $context = $service->machineContext($actor['worker_id']);
            $orders = $production->listScheduledOrdersForMachine((int)$context['win_equipoid'],0,date('Y-m-d'));
            $context = $service->planningContext($context,$agId);
            renderSupplyOperatorScreen($service,$context,$name,$orders,$kind);
        }
    } catch (Throwable $e) {
        if ($method === 'POST') {
            $reopenOnError = '';
            if ($isWarehouse && in_array($path, ['/stock/material-supply/scan','/stock/material-supply/scan-void'], true)
                && preg_match('/^[a-f0-9]{32}$/D', (string)($_POST['request_key']??'')) === 1) {
                $reopenOnError = '&open=' . $_POST['request_key'];
            }
            redirectResponse($back . ($isWarehouse?'?':'&') . 'error=' . rawurlencode($e->getMessage()) . $reopenOnError);
        }
        $body='<div style="padding:20px;color:#991b1b">' . htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8') . '</div>';
        if ($isWarehouse) render('Solicitudes de Materiales',$body);
        else renderProdwrkShell('Solicitar Materiales',$body,7,$name,(string)($_SESSION['planta_name']??'PLANTA'));
    }
    return true;
}

function supplyEscape($value): string
{
    return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
}

function supplyActorLabel(?string $json): string
{
    $actor = json_decode($json??'{}',true) ?: [];
    return (string)($actor['name']??'Pendiente') . (isset($actor['user_id'])?' (usuario '.$actor['user_id'].')':'');
}

function supplyDate($time): string
{
    return $time ? date('d/m/Y H:i',(int)$time) : '-';
}

function supplyAssets(): void
{
    ?>
    <style>
    .supply-panel{background:#fff;border:1px solid #cbd5e1;border-radius:8px;padding:16px;margin-bottom:14px}
    .supply-table{width:100%;border-collapse:collapse;font-size:13px}
    .supply-table th,.supply-table td{padding:9px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
    .supply-table th{background:#115452;color:#fff}
    .supply-dialog{width:min(950px,94vw);max-height:90vh;border:1px solid #cbd5e1;border-radius:10px;padding:24px;box-sizing:border-box}
    .supply-dialog::backdrop{background:rgba(15,23,42,.55)}
    .supply-dialog label{display:block;margin:12px 0 6px;font-weight:700}
    .supply-dialog input:not([type="checkbox"]),.supply-dialog textarea,.supply-dialog select{box-sizing:border-box;width:100%;padding:10px;border:1px solid #cbd5e1}
    .supply-table [hidden]{display:none!important}
    .supply-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
    </style>
    <script>
    let supplyCompatibleOnly=false;
    function supplyOpen(id){document.getElementById(id).showModal();}
    function supplyFilter(value,id){document.querySelectorAll('#'+id+' tbody tr').forEach(function(row){
        row.hidden=row.innerText.toLowerCase().indexOf(value.toLowerCase())===-1
            || (id==='supply_catalog_rolls' && supplyCompatibleOnly && row.dataset.compatible==='0');
    });}
    function supplyShowRolls(compatibleOnly){supplyCompatibleOnly=compatibleOnly;
        const input=document.getElementById('supply_roll_search');
        supplyFilter(input?input.value:'','supply_catalog_rolls');}
    function supplyTab(kind){document.getElementById('supply_rolls').hidden=kind!=='rolls';
        document.getElementById('supply_inks').hidden=kind!=='inks';
        document.querySelectorAll('.supply-request-kind').forEach(function(input){input.value=kind;});
        const url=new URL(location.href);url.searchParams.set('material_kind',kind);history.replaceState(null,'',url);}
    </script>
    <?php
    if (!empty($_GET['error'])) echo '<div class="supply-panel" style="color:#991b1b">' . supplyEscape($_GET['error']) . '</div>';
    if (!empty($_GET['saved'])) echo '<div class="supply-panel" style="color:#166534">Operacion registrada.</div>';
}

function renderSupplyOperatorScreen(MaterialSupplyService $service, array $context, string $name, array $orders = [], string $kind = 'rolls'): void
{
    $agId = (int)$context['agenda_id'];
    $agenda = $context['planning_details'] ?? null;
    $catalog = $service->catalog((int)$context['req_shop_id']);
    $rolls = [];
    $inks = [];
    foreach ($catalog as $item) {
        if (str_starts_with(strtoupper($item['sku']),'TIN')) {
            $item['recommended'] = MaterialSupplyRecommendations::inkMatches($item,$agenda);
            $inks[] = $item;
        } else {
            $rolls[] = MaterialSupplyRecommendations::roll($item,$agenda);
        }
    }
    usort($rolls,static fn($a,$b)=>($b['match_score']<=>$a['match_score'])
        ?: ((float)$a['specs']['width_cm']<=>(float)$b['specs']['width_cm']) ?: strcmp($a['sku'],$b['sku']));
    usort($inks,static fn($a,$b)=>($b['recommended']<=>$a['recommended']) ?: strcmp($a['sku'],$b['sku']));
    $compatible = array_values(array_filter($rolls,static fn($item)=>$item['recommended']));
    $compatibleOnly = $agenda !== null && count($compatible) > 0;
    $catalog = array_merge($rolls,$inks);
    ob_start();
    supplyAssets();
    ?>
    <h2>Solicitar Materiales</h2>
    <div class="supply-panel">
        <b>Maquina: <?=supplyEscape($context['machine_name'])?></b>
        <a class="btnblue" href="/production/machines">Maquinas</a>
        <div style="margin-top:12px">
        <label for="supply_ot"><b>OT planificada para hoy (<?=date('d/m/Y')?>)</b></label>
        <select id="supply_ot" class="inptxt" onchange="location.href='/production/materials/request?agid='+this.value+'&material_kind='+new URL(location.href).searchParams.get('material_kind')">
            <option value="0">Sin OT / Solicitud para la maquina</option>
            <?php $seen=[]; foreach ($orders as $order): $id=(int)$order['ag_id'];if(isset($seen[$id]))continue;$seen[$id]=true; ?>
            <option value="<?=$id?>" <?=$id===$agId?'selected':''?>>OT <?=supplyEscape($order['prd_number']??$id)?> - <?=supplyEscape($order['cust_name']??'')?> - <?=supplyEscape($order['item_title']??'')?></option>
            <?php endforeach; ?>
            <?php if ($agId>0 && !isset($seen[$agId])): ?><option selected value="<?=$agId?>">OT <?=supplyEscape($context['planned_ot_number']??$agId)?></option><?php endif; ?>
        </select>
        </div>
        <?php if ($agenda): $requirements=MaterialSupplyRecommendations::requirements($agenda); ?>
        <p><b>Material:</b> <?=supplyEscape($requirements['material']?:'Sin dato')?> |
            <b>Ancho de tela:</b> <?=$requirements['width_cm']>0?supplyEscape($requirements['width_cm']).' cm':'Sin dato'?> |
            <b>Color:</b> <?=supplyEscape($requirements['color']?:'Sin dato')?> |
            <b>Gramaje:</b> <?=$requirements['grammage']>0?supplyEscape($requirements['grammage']).' g/m2':'Sin dato'?></p>
        <div style="padding:12px;border:2px solid <?=$compatible?'#16a34a':'#d97706'?>;background:<?=$compatible?'#f0fdf4':'#fffbeb'?>">
        <b>Bobinas que sirven para esta OT: <?=count($compatible)?> tipos disponibles</b>
        <?php if ($compatible): ?>
            <p style="font-size:20px;font-weight:bold;margin:8px 0"><?=supplyEscape(implode(', ',array_column($compatible,'sku')))?></p>
            <div>Coinciden material, color y gramaje y tienen ancho suficiente. Se muestran primero las compatibles.</div>
        <?php else: ?>
            <p>No hay bobinas con stock disponible en 100 o 200 que coincidan con los cuatro datos de esta OT.
            Las alternativas indican que dato debes revisar; puedes elegirlas manualmente.</p>
        <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="supply-panel"><button type="button" class="btnblue" onclick="supplyTab('rolls')">Bobinas</button>
        <button type="button" class="btnblue" onclick="supplyTab('inks')">Tintas</button></div>
    <?php foreach (['rolls'=>'Bobinas e Insumos','inks'=>'Solicitud de Tintas'] as $section=>$title): ?>
    <div id="supply_<?=$section?>" <?=$kind!==$section?'hidden':''?>>
    <div class="supply-panel">
        <h3><?=$section==='rolls' && $agenda?'Bobinas para esta OT':$title?></h3>
        <b><?=$section==='inks'?'Bodegas 900 / 910 / 920 → Bodega 3000':'Bodegas 100 / 200 → Bodega 3000'?></b>
        <?php if ($section==='inks'): ?><p>Solicita la cantidad de tinta necesaria. Bodega registra el traspaso a 3000; recibir no descuenta consumo.</p>
        <?php else: ?><p>La solicitud no mueve stock. Bodega registra el despacho y el operador confirma la recepcion.</p><?php endif; ?>
        <?php if ($section==='rolls' && $agenda): ?><div class="supply-actions" style="justify-content:flex-start;margin:10px 0">
            <button type="button" class="btngreen" onclick="supplyShowRolls(true)">Compatibles (<?=count($compatible)?>)</button>
            <button type="button" class="btnblue" onclick="supplyShowRolls(false)">Todas / Elegir otra</button>
        </div><?php endif; ?>
        <input id="supply_<?=$section==='rolls'?'roll':'ink'?>_search" class="inptxt" placeholder="Buscar codigo o descripcion" oninput="supplyFilter(this.value,'supply_catalog_<?=$section?>')">
        <div style="overflow:auto;max-height:48vh;margin-top:12px">
        <table class="supply-table" id="supply_catalog_<?=$section?>">
            <thead><tr><th>Codigo</th><th>Descripcion / Datos</th><th>Bodega</th><th>Disponible</th><th>Recomendacion</th><th>Opciones</th></tr></thead>
            <tbody>
            <?php $count=0; foreach ($catalog as $i=>$item): $isInk=str_starts_with(strtoupper($item['sku']),'TIN');if($isInk!==($section==='inks'))continue;$count++;$dialog='supply_request_'.$i; ?>
                <tr data-compatible="<?=$item['recommended']?'1':'0'?>" <?=$section==='rolls'&&$compatibleOnly&&!$item['recommended']?'hidden':''?>><td><b style="font-size:18px"><?=supplyEscape($item['sku'])?></b></td><td><?=supplyEscape($item['description'])?></td>
                <td><?=supplyWarehouseLabel((int)$item['source_storehouse_id'])?></td>
                <td><?=number_format((float)$item['available_qty'],3,',','.')?> <?=supplyEscape($item['unit'])?></td>
                <td><?php if ($item['recommended']): ?><b style="color:#166534"><?=$isInk?'Color de la OT':'Recomendada'?></b><?php elseif ($agenda && !$isInk): ?>Revisar compatibilidad<?php else: ?>-<?php endif; ?>
                    <?php if (!$isInk): $specs=$item['specs']; ?><br><?=supplyEscape($specs['material']?:'Sin material')?> |
                    <?=$specs['width_cm']>0?supplyEscape($specs['width_cm']).' cm':'Sin ancho'?> |
                    <?=supplyEscape($specs['color']?:'Sin color')?> |
                    <?=$specs['grammage']>0?supplyEscape($specs['grammage']).' g/m2':'Sin gramaje'?>
                    <?php if ($agenda): ?><br><?php $failed=array_keys(array_filter($item['checks'],static fn($match)=>!$match)); ?><?=supplyEscape($failed?'Revisar: '.implode(', ',$failed):'Coinciden los cuatro criterios')?><?php endif; endif; ?>
                </td>
                <td><button type="button" class="btnblue" onclick="supplyOpen('<?=$dialog?>')">Solicitar</button></td></tr>
            <?php endforeach; ?>
            <?php if (!$count): ?><tr><td colspan="6">No hay materiales con stock disponible en esta seccion.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
    </div>
    <?php endforeach; ?>
    <script>supplyCompatibleOnly=<?=$compatibleOnly?'true':'false'?>;</script>
    <?php foreach ($catalog as $i=>$item): ?>
    <dialog id="supply_request_<?=$i?>" class="supply-dialog">
        <h2>Solicitar Materiales</h2><b><?=supplyEscape($item['sku'])?></b><p><?=supplyEscape($item['description'])?></p>
        <form method="post" action="/production/materials/request/assign">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="action_mode" value="central_supply">
            <input type="hidden" name="agid" value="<?=$agId?>">
            <input type="hidden" class="supply-request-kind" name="material_kind" value="<?=$kind?>">
            <input type="hidden" name="request_key" value="<?=bin2hex(random_bytes(16))?>">
            <input type="hidden" name="item_id" value="<?=(int)$item['item_id']?>">
            <input type="hidden" name="source_storehouse_id" value="<?=(int)$item['source_storehouse_id']?>">
            <label>Cantidad (<?=supplyEscape($item['unit'])?>)</label>
            <input type="number" name="quantity" step="<?=str_starts_with(strtoupper($item['sku']),'TEL')?'1':'0.001'?>" min="<?=str_starts_with(strtoupper($item['sku']),'TEL')?'1':'0.001'?>" max="<?=(float)$item['available_qty']?>" required>
            <label>Observaciones</label><textarea name="notes" rows="3"></textarea>
            <div class="supply-actions"><button type="button" class="btngrey" onclick="this.closest('dialog').close()">Volver</button><button class="btnblue">Solicitar</button></div>
        </form>
    </dialog>
    <?php endforeach; ?>
    <?php renderSupplyRequestHistory($service,$service->requests(null,(int)$context['win_equipoid']),$agId,false); ?>
    <?php
    renderProdwrkShell('Solicitar Materiales',ob_get_clean(),7,$name,(string)($_SESSION['planta_name']??'PLANTA'));
}

function supplyWarehouseLabel(int $id): string
{
    return [5=>'100',11=>'200',7=>'900',24=>'910',28=>'920',16=>'3000'][$id] ?? (string)$id;
}

function renderSupplyRequestHistory(MaterialSupplyService $service, array $requests, int $agId, bool $warehouse): void
{
    if ($warehouse) supplyAssets();
    ?>
    <div class="supply-panel">
    <h3><?=$warehouse?'Solicitudes de Materiales':'Solicitudes de la Maquina'?></h3>
    <?php if ($warehouse): ?><a href="/stock/transfers" class="btnblue">Traspasos</a> <a href="/stock/material-supply/reports" class="btnblue">Reportes</a><?php endif; ?>
    <div style="overflow:auto;margin-top:12px">
    <table class="supply-table"><thead><tr><th>Solicitud / Maquina</th><th>Material</th><th>Origen → Destino</th><th>Cantidades</th><th>Solicitado por</th><th>Opciones</th></tr></thead>
    <tbody>
    <?php foreach ($requests as $i=>$r): $remaining=max(0,(float)$r['requested_qty']-(float)$r['allocated_qty']);$details='supply_details_'.$i;$ctx=json_decode($r['context'],true)?:[]; ?>
    <tr><td><b><?=supplyEscape(substr($r['request_key'],0,8))?></b><br><?=supplyEscape($ctx['machine_name']??('Maquina #'.($ctx['win_equipoid']??0)))?><?php if (!empty($ctx['planned_ot_number'])): ?><br>OT <?=supplyEscape($ctx['planned_ot_number'])?><?php elseif ((int)$r['worker_ot_id']>0): ?><br>OT asignacion #<?=(int)$r['worker_ot_id']?><?php endif; ?><br><?=supplyDate($r['created_at'])?><br><?=supplyStatusBadge($r)?></td>
        <td><b><?=supplyEscape($r['sku'])?></b><br><?=supplyEscape($r['description'])?><br><?=supplyEscape($r['notes'])?></td>
        <td><?=supplyWarehouseLabel((int)$r['source_storehouse_id'])?> → <?=(int)$r['source_storehouse_id']===16?'Maquina':'3000'?></td>
        <td>Solicitado: <?=supplyEscape($r['requested_qty'])?><br>Entregado: <?=supplyEscape($r['delivered_qty'])?><br>Recibido: <?=supplyEscape($r['received_qty'])?><br><?=supplyEscape($r['unit'])?></td>
        <td><?=supplyEscape(supplyActorLabel($r['requested_by']))?></td>
        <td><button type="button" class="btnblue" onclick="supplyOpen('<?=$details?>')">Detalle</button>
        <?php if ($warehouse && (int)$r['source_storehouse_id']!==16): ?>
            <?php if ($r['status']==='PENDING'): ?>
            <form method="post" action="/stock/material-supply/accept" style="display:inline">
                <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
                <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
                <button class="btngreen">Aceptar</button>
            </form>
            <button type="button" class="btngrey" style="color:#991b1b" onclick="supplyOpen('supply_reject_<?=$i?>')">Rechazar</button>
            <?php elseif ($r['status']==='ACCEPTED' && $remaining>0): ?>
            <button type="button" class="btngreen" onclick="supplyOpen('supply_dispatch_<?=$i?>')">Preparar y despachar</button>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ((int)$r['source_storehouse_id']===16 && $remaining>0): ?><p>Registro anterior: crea una solicitud desde 900, 910 o 920.</p><?php endif; ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (!$requests): ?><tr><td colspan="6">No hay solicitudes registradas.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <?php foreach ($requests as $i=>$r): $remaining=max(0,(float)$r['requested_qty']-(float)$r['allocated_qty']); ?>
    <dialog id="supply_details_<?=$i?>" class="supply-dialog">
        <h2>Detalle de Solicitud</h2>
        <b><?=supplyEscape($r['sku'])?> · <?=supplyEscape($r['request_key'])?></b>
        <p>Solicitado por <?=supplyEscape(supplyActorLabel($r['requested_by']))?> · <?=supplyDate($r['created_at'])?></p>
        <?php $ctx=json_decode($r['context'],true)?:[]; ?><p>Maquina: <?=supplyEscape($ctx['machine_name']??('#'.($ctx['win_equipoid']??0)))?></p>
        <?php if (!empty($ctx['planned_ot_number'])): ?><p>OT: <?=supplyEscape($ctx['planned_ot_number'])?></p><?php endif; ?>
        <div style="overflow:auto"><table class="supply-table"><thead><tr><th>Entrega / Traspaso ERP</th><th>Cantidad / Codigos</th><th>Entregado por</th><th>Recibido por</th><th>Estado / Opciones</th></tr></thead><tbody>
        <?php foreach ($service->deliveries($r['request_key']) as $d): $payload=json_decode($d['payload'],true)?:[]; ?>
        <tr><td><?=supplyEscape(substr($d['dispatch_key'],0,8))?><br><?=supplyEscape($d['erp_transfer_id']??((int)$r['source_storehouse_id']===16?'Entrega interna':'-'))?></td>
        <td><?=supplyEscape($d['quantity'])?> <?=supplyEscape($r['unit'])?><br><?=supplyEscape(implode(', ',array_column($payload['rolls']??[],'code')))?></td>
        <td><?=supplyEscape(supplyActorLabel($d['delivered_by']))?><br><?=supplyDate($d['delivered_at'])?></td>
        <td><?=supplyEscape(supplyActorLabel($d['received_by']))?><br><?=supplyDate($d['received_at'])?></td>
        <td><?=supplyEscape(['PENDING'=>'Pendiente de resolver','DISPATCHED'=>'Por recibir','RECEIVED'=>'Recibido','CANCELLED'=>'Cancelado'][$d['state']]??$d['state'])?>
        <?php if ($d['error_message']): ?><p style="color:#991b1b"><?=supplyEscape($d['error_message'])?></p><?php endif; ?>
        <?php if (($warehouse && $d['state']==='PENDING') || (!$warehouse && $d['state']==='DISPATCHED')): ?>
        <form method="post" action="<?=$warehouse?'/stock/material-supply/resume':'/production/materials/receive'?>">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="dispatch_key" value="<?=supplyEscape($d['dispatch_key'])?>">
            <input type="hidden" name="agid" value="<?=$agId?>">
            <?php if (!$warehouse): ?><label><input type="checkbox" name="receipt_confirmed" value="1" required> Confirmo que recibi esta cantidad y los codigos indicados.</label><?php endif; ?>
            <button class="btngreen"><?=$warehouse?'Reintentar':'Recibir'?></button>
        </form>
        <?php if ($warehouse): ?>
        <form method="post" action="/stock/material-supply/cancel">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="dispatch_key" value="<?=supplyEscape($d['dispatch_key'])?>">
            <label>Motivo de cancelacion</label><input name="reason" required>
            <button class="btngrey">Cancelar</button>
            <p>Solo si todavia no existe movimiento ERP.</p>
        </form>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($warehouse && $d['state']==='DISPATCHED'): ?>
        <form method="post" action="/stock/material-supply/receive">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="dispatch_key" value="<?=supplyEscape($d['dispatch_key'])?>">
            <label><input type="checkbox" name="receipt_confirmed" value="1" required> Confirmo la recepción en Bodega 3000 de esta cantidad y estos códigos.</label>
            <button class="btngreen">Confirmar recepción</button>
        </form>
        <?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="supply-actions"><button type="button" class="btngrey" onclick="this.closest('dialog').close()">Volver</button></div>
    </dialog>
    <?php if ($warehouse && (int)$r['source_storehouse_id']!==16 && $r['status']==='PENDING'): ?>
    <dialog id="supply_reject_<?=$i?>" class="supply-dialog">
        <h2>Rechazar solicitud</h2>
        <b><?=supplyEscape($r['sku'])?> · <?=supplyEscape(substr($r['request_key'],0,8))?></b>
        <form method="post" action="/stock/material-supply/reject">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
            <label>Motivo del rechazo (obligatorio)</label>
            <input name="reason" required maxlength="250" placeholder="Ej.: sin stock físico en la bodega">
            <div class="supply-actions"><button type="button" class="btngrey" onclick="this.closest('dialog').close()">Volver</button><button class="btngrey" style="color:#991b1b;font-weight:700">Rechazar solicitud</button></div>
        </form>
    </dialog>
    <?php endif; ?>
    <?php if ($warehouse && (int)$r['source_storehouse_id']!==16 && $r['status']==='ACCEPTED' && $remaining>0):
        $isRoll=str_starts_with(strtoupper($r['sku']),'TEL'); $reqQty=(float)$r['requested_qty']; $scans=$isRoll?$service->scans($r['request_key']):[]; ?>
    <dialog id="supply_dispatch_<?=$i?>" class="supply-dialog">
        <h2>Preparar y despachar</h2>
        <b><?=supplyEscape($r['sku'])?></b> <?=supplyEscape($r['description'])?>
        <p>Se solicitaron <b><?=supplyEscape($r['requested_qty'])?> <?=supplyEscape($r['unit'])?></b> desde la Bodega <?=supplyWarehouseLabel((int)$r['source_storehouse_id'])?>. El despacho debe ser completo. Al despachar se descuenta el stock de origen, se carga en la Bodega 3000 y se genera el traspaso ERP.</p>
        <?php if ($isRoll): ?>
        <form method="post" action="/stock/material-supply/scan" autocomplete="off">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
            <label>Escanea el código de trazabilidad de la bobina (<?=count($scans)?> de <?=(int)$reqQty?>)</label>
            <input name="code" required maxlength="60" autofocus placeholder="Escanea aquí; el lector envía Enter">
            <div class="supply-actions"><button class="btnblue">Registrar escaneo</button></div>
        </form>
        <?php if ($scans): ?>
        <div style="overflow:auto"><table class="supply-table"><thead><tr><th>Código</th><th>Escaneado por</th><th>Fecha</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($scans as $s): ?>
        <tr><td><b><?=supplyEscape($s['code'])?></b></td><td><?=supplyEscape(supplyActorLabel($s['scanned_by']))?></td><td><?=supplyDate($s['scanned_at'])?></td>
            <td><?=supplyEscape(['SCANNED'=>'Escaneada','DISPATCHED'=>'Despachada','RECEIVED'=>'Recibida'][$s['state']]??$s['state'])?></td>
            <td><?php if ($s['state']==='SCANNED'): ?>
            <form method="post" action="/stock/material-supply/scan-void" style="display:flex;gap:6px">
                <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
                <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
                <input type="hidden" name="scan_key" value="<?=supplyEscape($s['scan_key'])?>">
                <input name="reason" required maxlength="200" placeholder="Motivo de anulación">
                <button class="btngrey">Anular</button>
            </form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <?php endif; ?>
        <?php else: ?><p>Este material se entrega por cantidad: no requiere escanear códigos.</p><?php endif; ?>
        <form method="post" action="/stock/material-supply/dispatch">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
            <input type="hidden" name="dispatch_key" value="<?=bin2hex(random_bytes(16))?>">
            <input type="hidden" name="quantity" value="<?=supplyEscape($r['requested_qty'])?>">
            <div class="supply-actions"><button class="btngreen" <?=($isRoll && count($scans)!==(int)$reqQty)?'disabled title="Faltan bobinas por escanear"':''?>>Despachar a Bodega 3000</button></div>
        </form>
        <form method="post" action="/stock/material-supply/cancel-request" style="margin-top:14px;border-top:1px solid #e2e8f0;padding-top:10px">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
            <label>Cancelar esta solicitud (motivo obligatorio)</label>
            <input name="reason" required maxlength="250">
            <div class="supply-actions"><button type="button" class="btngrey" onclick="this.closest('dialog').close()">Volver</button><button class="btngrey">Cancelar solicitud</button></div>
        </form>
    </dialog>
    <?php endif;
    endforeach;
    if ($warehouse && !empty($_GET['open'])) {
        foreach ($requests as $i=>$r) {
            if ($r['request_key']===(string)$_GET['open']) {
                echo '<script>document.addEventListener("DOMContentLoaded",function(){var d=document.getElementById("supply_dispatch_'.(int)$i.'");'
                    .'if(d&&d.showModal){d.showModal();var f=d.querySelector("input[name=code]");if(f)f.focus();}});</script>';
            }
        }
    }
}

/** Etiqueta de estado de la solicitud (con motivo de rechazo o cancelación si corresponde). */
function supplyStatusBadge(array $r): string
{
    $status = (string)($r['status'] ?? 'PENDING');
    $labels = ['PENDING'=>['Pendiente','#92400e','#fef3c7'],'ACCEPTED'=>['En preparación','#1e40af','#dbeafe'],
        'REJECTED'=>['Rechazada','#991b1b','#fee2e2'],'CANCELLED'=>['Cancelada','#475569','#e2e8f0'],
        'DISPATCHED'=>['Despachada','#0f766e','#ccfbf1'],'RECEIVED'=>['Recibida','#166534','#dcfce7']];
    [$text,$color,$bg] = $labels[$status] ?? [$status,'#334155','#f1f5f9'];
    $html = '<span style="display:inline-block;margin-top:4px;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:700;color:'.$color.';background:'.$bg.'">'.supplyEscape($text).'</span>';
    if ($status === 'REJECTED' && !empty($r['reject_reason'])) $html .= '<br><small>Motivo: '.supplyEscape($r['reject_reason']).'</small>';
    if ($status === 'CANCELLED' && !empty($r['cancel_reason'])) $html .= '<br><small>Motivo: '.supplyEscape($r['cancel_reason']).'</small>';
    return $html;
}

/** Pantalla de reportes de traspasos: respuesta por bodega, movimientos y diferencias. */
function renderSupplyReports(MaterialSupplyService $service, string $from, string $to): void
{
    supplyAssets();
    $fromTs = strtotime($from . ' 00:00:00') ?: strtotime('-30 days');
    $toTs = strtotime($to . ' 23:59:59') ?: time();
    $rep = $service->report($fromTs, $toTs);
    ?>
    <div class="supply-panel">
    <h3>Reportes de Traspasos de Bodega</h3>
    <a href="/stock/material-supply" class="btnblue">Volver a solicitudes</a>
    <form method="get" style="display:flex;gap:10px;align-items:end;margin:12px 0;flex-wrap:wrap">
        <div><label>Desde</label><input type="date" name="from" value="<?=supplyEscape(date('Y-m-d',$fromTs))?>"></div>
        <div><label>Hasta</label><input type="date" name="to" value="<?=supplyEscape(date('Y-m-d',$toTs))?>"></div>
        <button class="btnblue">🔍 Aplicar Filtros</button>
    </form>
    <h4>Solicitudes y tiempo de respuesta por bodega de origen</h4>
    <div style="overflow:auto"><table class="supply-table"><thead><tr><th>Bodega</th><th>Solicitudes</th><th>Pendientes</th><th>Rechazadas</th><th>Canceladas</th><th>Tiempo medio de respuesta</th></tr></thead><tbody>
    <?php foreach ($rep['response'] as $row): $sec=$row['avg_response_seconds']; ?>
    <tr><td><?=supplyWarehouseLabel((int)$row['source_storehouse_id'])?></td><td><?=(int)$row['total']?></td><td><?=(int)$row['pending']?></td><td><?=(int)$row['rejected']?></td><td><?=(int)$row['cancelled']?></td>
        <td><?=$sec===null?'-':supplyEscape(floor((float)$sec/3600).' h '.floor(fmod((float)$sec,3600)/60).' min')?></td></tr>
    <?php endforeach; if (!$rep['response']): ?><tr><td colspan="6">Sin solicitudes en el periodo.</td></tr><?php endif; ?>
    </tbody></table></div>
    <h4 style="margin-top:20px">Movimientos por producto y bodega</h4>
    <div style="overflow:auto"><table class="supply-table"><thead><tr><th>Producto</th><th>Bodega origen</th><th>Despachos</th><th>Cantidad</th></tr></thead><tbody>
    <?php foreach ($rep['movements'] as $row): ?>
    <tr><td><b><?=supplyEscape($row['sku'])?></b><br><?=supplyEscape($row['description'])?></td><td><?=supplyWarehouseLabel((int)$row['source_storehouse_id'])?> → 3000</td><td><?=(int)$row['dispatches']?></td><td><?=supplyEscape($row['quantity'])?> <?=supplyEscape($row['unit'])?></td></tr>
    <?php endforeach; if (!$rep['movements']): ?><tr><td colspan="4">Sin movimientos en el periodo.</td></tr><?php endif; ?>
    </tbody></table></div>
    <h4 style="margin-top:20px">Diferencias: solicitado, despachado y recibido</h4>
    <div style="overflow:auto"><table class="supply-table"><thead><tr><th>Solicitud</th><th>Producto</th><th>Bodega</th><th>Estado</th><th>Solicitado</th><th>Despachado</th><th>Recibido</th><th>Diferencia</th></tr></thead><tbody>
    <?php foreach ($rep['differences'] as $row): $diff=(float)$row['dispatched_qty']-(float)$row['received_qty']; $short=(float)$row['requested_qty']-(float)$row['dispatched_qty']; ?>
    <tr><td><?=supplyEscape(substr($row['request_key'],0,8))?></td><td><?=supplyEscape($row['sku'])?></td><td><?=supplyWarehouseLabel((int)$row['source_storehouse_id'])?></td>
        <td><?=supplyEscape(['ACCEPTED'=>'En preparación','DISPATCHED'=>'Despachada','RECEIVED'=>'Recibida'][$row['status']]??$row['status'])?></td>
        <td><?=supplyEscape($row['requested_qty'])?></td><td><?=supplyEscape($row['dispatched_qty'])?></td><td><?=supplyEscape($row['received_qty'])?></td>
        <td style="<?=(abs($diff)>0.000001||abs($short)>0.000001)?'color:#991b1b;font-weight:700':''?>"><?=abs($diff)>0.000001?'Sin recibir: '.supplyEscape($diff):(abs($short)>0.000001?'Falta despachar: '.supplyEscape($short):'OK')?></td></tr>
    <?php endforeach; if (!$rep['differences']): ?><tr><td colspan="8">Sin solicitudes en el periodo.</td></tr><?php endif; ?>
    </tbody></table></div>
    </div>
    <?php
}

