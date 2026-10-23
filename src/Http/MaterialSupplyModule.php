<?php

declare(strict_types=1);

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
        || $path === '/stock/material-supply/resume' || $path === '/stock/material-supply/cancel';
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
                if ($path === '/stock/material-supply/resume') {
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
            redirectResponse($back . ($isWarehouse?'?':'&') . 'saved=1');
            return true;
        }
        if ($method !== 'GET') return false;
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
        if ($method === 'POST') redirectResponse($back . ($isWarehouse?'?':'&') . 'error=' . rawurlencode($e->getMessage()));
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
    <?php if ($warehouse): ?><a href="/stock/transfers" class="btnblue">Traspasos</a><?php endif; ?>
    <div style="overflow:auto;margin-top:12px">
    <table class="supply-table"><thead><tr><th>Solicitud / Maquina</th><th>Material</th><th>Origen → Destino</th><th>Cantidades</th><th>Solicitado por</th><th>Opciones</th></tr></thead>
    <tbody>
    <?php foreach ($requests as $i=>$r): $remaining=max(0,(float)$r['requested_qty']-(float)$r['allocated_qty']);$details='supply_details_'.$i;$ctx=json_decode($r['context'],true)?:[]; ?>
    <tr><td><b><?=supplyEscape(substr($r['request_key'],0,8))?></b><br><?=supplyEscape($ctx['machine_name']??('Maquina #'.($ctx['win_equipoid']??0)))?><?php if (!empty($ctx['planned_ot_number'])): ?><br>OT <?=supplyEscape($ctx['planned_ot_number'])?><?php elseif ((int)$r['worker_ot_id']>0): ?><br>OT asignacion #<?=(int)$r['worker_ot_id']?><?php endif; ?><br><?=supplyDate($r['created_at'])?></td>
        <td><b><?=supplyEscape($r['sku'])?></b><br><?=supplyEscape($r['description'])?><br><?=supplyEscape($r['notes'])?></td>
        <td><?=supplyWarehouseLabel((int)$r['source_storehouse_id'])?> → <?=(int)$r['source_storehouse_id']===16?'Maquina':'3000'?></td>
        <td>Solicitado: <?=supplyEscape($r['requested_qty'])?><br>Entregado: <?=supplyEscape($r['delivered_qty'])?><br>Recibido: <?=supplyEscape($r['received_qty'])?><br><?=supplyEscape($r['unit'])?></td>
        <td><?=supplyEscape(supplyActorLabel($r['requested_by']))?></td>
        <td><button type="button" class="btnblue" onclick="supplyOpen('<?=$details?>')">Detalle</button>
        <?php if ($warehouse && $remaining>0 && (int)$r['source_storehouse_id']!==16): ?><button type="button" class="btngreen" onclick="supplyOpen('supply_dispatch_<?=$i?>')">Entregar</button><?php endif; ?>
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
        <?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="supply-actions"><button type="button" class="btngrey" onclick="this.closest('dialog').close()">Volver</button></div>
    </dialog>
    <?php if ($warehouse && $remaining>0 && (int)$r['source_storehouse_id']!==16): ?>
    <dialog id="supply_dispatch_<?=$i?>" class="supply-dialog">
        <h2>Entregar Materiales</h2><b><?=supplyEscape($r['sku'])?></b>
        <p>La entrega mueve stock de <?=supplyWarehouseLabel((int)$r['source_storehouse_id'])?> a 3000 y genera el traspaso ERP.</p>
        <form method="post" action="/stock/material-supply/dispatch">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
            <input type="hidden" name="dispatch_key" value="<?=bin2hex(random_bytes(16))?>">
            <label>Cantidad a entregar (<?=supplyEscape($r['unit'])?>)</label>
            <input type="number" name="quantity" min="<?=str_starts_with(strtoupper($r['sku']),'TEL')?'1':'0.001'?>" max="<?=$remaining?>" step="<?=str_starts_with(strtoupper($r['sku']),'TEL')?'1':'0.001'?>" value="<?=$remaining?>" required>
            <?php if (!str_starts_with(strtoupper($r['sku']),'TIN')): ?><label>Codigos de trazabilidad</label>
            <textarea name="codes" rows="5" placeholder="Un codigo por linea" <?=str_starts_with(strtoupper($r['sku']),'TEL')?'required':''?>></textarea>
            <p>En bobinas, escanea cada codigo individual. Puedes hacer varias entregas parciales.</p>
            <?php endif; ?>
            <div class="supply-actions"><button type="button" class="btngrey" onclick="this.closest('dialog').close()">Volver</button><button class="btngreen">Entregar</button></div>
        </form>
    </dialog>
    <?php endif; endforeach;
}
