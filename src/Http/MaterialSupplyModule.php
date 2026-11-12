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
 *    - (La recepción la confirma solo el operador en /production/materials/receive.)
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
            '/stock/material-supply/scan-void','/stock/material-supply/cancel-request',
            '/stock/material-supply/reports'], true);
    if (!$isOperator && !$isWarehouse) return false;
    $actor = supplySessionActor($name);
    $service = new MaterialSupplyService(Db::erpPdo(),Db::trzPdo());
    $agId = max(0,(int)($_POST['agid'] ?? $_GET['agid'] ?? 0));
    $kindRaw = (string)($_POST['material_kind'] ?? $_GET['material_kind'] ?? '');
    $kind = in_array($kindRaw, ['inks', 'products', 'boxes'], true) ? $kindRaw : 'rolls';
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
                    $rawCodes = $_POST['codes'] ?? ($_POST['code'] ?? '');
                    $service->scanBatch($requestKey, $rawCodes, $actor);
                } elseif ($path === '/stock/material-supply/scan-void') {
                    $service->voidScan((string)($_POST['scan_key']??''),(string)($_POST['reason']??''),$actor);
                } elseif ($path === '/stock/material-supply/cancel-request') {
                    $service->cancelRequest($requestKey,(string)($_POST['reason']??''),$actor);
                } elseif ($path === '/stock/material-supply/resume') {
                    $service->resume((string)($_POST['dispatch_key']??''),$actor);
                } elseif ($path === '/stock/material-supply/cancel') {
                    $service->cancelPending((string)($_POST['dispatch_key']??''),(string)($_POST['reason']??''),$actor);
                } else {
                    // Bobinas: la cantidad sale de las bobinas escaneadas. Otros materiales: cantidad indicada.
                    $rawDispatchCodes = $_POST['codes'] ?? '';
                    if (is_array($rawDispatchCodes)) {
                        $rawDispatchCodes = implode("\n", array_map('strval', $rawDispatchCodes));
                    }
                    $service->dispatch((string)($_POST['dispatch_key']??''),(string)($_POST['request_key']??''),
                        isset($_POST['quantity']) ? supplyPostedQuantity() : 0.0,(string)$rawDispatchCodes,$actor,
                        (string)($_POST['change_reason']??''),(string)($_POST['saldo_code']??''));
                }
            } else {
                if ($path === '/production/materials/receive') {
                    if (empty($_POST['receipt_confirmed'])) throw new RuntimeException('Confirma la cantidad y codigos recibidos.');
                    $service->receive((string)($_POST['dispatch_key']??''),0,$actor,(string)($_POST['receive_comment']??''));
                } else {
                    if ((string)($_POST['action_mode']??'') !== 'central_supply') {
                        throw new RuntimeException('Usa Solicitar para pedir bobinas o tintas desde esta pantalla.');
                    }
                    $service->request((string)($_POST['request_key']??''),0,$actor,
                        (int)($_POST['item_id']??0),(int)($_POST['source_storehouse_id']??0),
                        supplyPostedQuantity(),(string)($_POST['notes']??''),$agId,$kind);
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
            if ($isWarehouse && in_array($path, ['/stock/material-supply/scan','/stock/material-supply/scan-void','/stock/material-supply/dispatch'], true)
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

/** Texto legible de las diferencias de un despacho (SKU, UNITS, METERS). */
function supplyChangeLabel(?string $flags): string
{
    $names = ['SKU' => 'otro código de bobina', 'UNITS' => 'cantidad distinta', 'METERS' => 'metros distintos'];
    $out = [];
    foreach (array_filter(explode(',', (string)$flags)) as $f) $out[] = $names[$f] ?? $f;
    return implode(', ', $out);
}

/** Bobinas de un despacho, una por línea: código · SKU · metros · peso, marcando los reemplazos. */
function supplyRollLines(array $payload, string $requestedSku): string
{
    $html = '';
    foreach ($payload['rolls'] ?? [] as $roll) {
        $sku = (string)($roll['sku'] ?? $payload['sku'] ?? '');
        $meters = (float)($roll['meters'] ?? 0);
        $html .= '<div style="font-size:12px;margin-top:3px"><b>' . supplyEscape($roll['code'] ?? '') . '</b>'
            . ($sku !== '' ? ' · ' . supplyEscape($sku) : '')
            . ($meters > 0 ? ' · ' . supplyEscape(supplyNum($meters)) . ' m' : '')
            . ((float)($roll['weight_kg'] ?? 0) > 0 ? ' · ' . supplyEscape(supplyNum((float)$roll['weight_kg'])) . ' kg' : '')
            . ($sku !== '' && $sku !== $requestedSku ? ' <span style="background:#fef3c7;color:#92400e;border-radius:8px;padding:1px 7px;font-weight:700">Reemplazo</span>' : '')
            . '</div>';
    }
    return $html;
}

/** Cantidad legible: 120 / 35,5 / 1.250,25 (sin ceros sobrantes). */
function supplyNum(float $value): string
{
    $text = number_format($value, 3, ',', '.');
    return rtrim(rtrim($text, '0'), ',');
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
    /* ---- base (compatibilidad con diálogos y reportes existentes) ---- */
    .supply-panel{background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 1px 3px rgba(15,23,42,.05)}
    .supply-table{width:100%;border-collapse:collapse;font-size:13px}
    .supply-table th,.supply-table td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
    .supply-table th{background:#115452;color:#fff}
    .supply-dialog{width:min(950px,94vw);max-height:90vh;border:0;border-radius:16px;padding:24px;box-sizing:border-box;box-shadow:0 25px 60px rgba(15,23,42,.35)}
    .supply-dialog::backdrop{background:rgba(15,23,42,.55)}
    .supply-dialog label{display:block;margin:12px 0 6px;font-weight:700}
    .supply-dialog input:not([type="checkbox"]),.supply-dialog textarea,.supply-dialog select{box-sizing:border-box;width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px}
    .supply-table [hidden]{display:none!important}
    .supply-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px;flex-wrap:wrap}
    /* ---- nuevo diseño ---- */
    .sp{max-width:1280px;margin:0 auto;color:#0f172a}
    .sp *{box-sizing:border-box}
    .sp-hero{background:linear-gradient(135deg,#0f3f3d,#115452 55%,#0f766e);color:#fff;border-radius:18px;padding:20px 22px;margin-bottom:16px;display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap}
    .sp-hero h2{margin:0;font-size:24px;font-weight:800;letter-spacing:-.02em;color:#fff}
    .sp-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.16);border-radius:999px;padding:6px 12px;font-size:13px;font-weight:700;margin-top:8px}
    .sp-hero a.sp-link{color:#fff;background:rgba(255,255,255,.16);border-radius:10px;padding:10px 14px;font-weight:700;text-decoration:none;font-size:14px}
    .sp-card-box{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;margin-bottom:16px;box-shadow:0 1px 3px rgba(15,23,42,.05)}
    .sp-label{display:block;font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#475569;margin-bottom:6px}
    .sp-select{width:100%;padding:13px 12px;border:1px solid #cbd5e1;border-radius:12px;font-size:15px;background:#fff;color:#0f172a}
    .sp-req-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
    .sp-req-chips span{background:#f1f5f9;border-radius:10px;padding:7px 11px;font-size:13px;color:#334155}
    .sp-req-chips b{color:#0f172a}
    .sp-note{border-radius:12px;padding:12px 14px;margin-top:12px;font-size:14px;line-height:1.45}
    .sp-note.ok{background:#f0fdf4;border:1px solid #86efac;color:#14532d}
    .sp-note.warn{background:#fffbeb;border:1px solid #fcd34d;color:#78350f}
    .sp-note b.big{display:block;font-size:20px;margin:4px 0}
    .sp-tabs{display:flex;gap:8px;background:#e2e8f0;border-radius:16px;padding:6px;margin-bottom:16px;flex-wrap:wrap}
    .sp-tab{flex:1 1 160px;border:0;background:transparent;border-radius:12px;padding:13px 12px;font-size:15px;font-weight:800;color:#475569;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;min-height:48px}
    .sp-tab i{font-style:normal;background:#cbd5e1;color:#334155;border-radius:999px;padding:2px 9px;font-size:12px}
    .sp-tab.active{background:#fff;color:#115452;box-shadow:0 2px 8px rgba(15,23,42,.12)}
    .sp-tab.active i{background:#115452;color:#fff}
    .sp-tools{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
    .sp-search{flex:1 1 260px;padding:13px 14px;border:1px solid #cbd5e1;border-radius:12px;font-size:15px}
    .sp-seg{display:flex;gap:6px}
    .sp-seg button{border:1px solid #cbd5e1;background:#fff;border-radius:10px;padding:11px 14px;font-weight:700;font-size:14px;cursor:pointer;min-height:44px}
    .sp-seg button.active{background:#115452;color:#fff;border-color:#115452}
    .sp-route{font-size:13px;color:#475569;margin:0 0 12px}
    .sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:14px}
    .sp-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:16px;display:flex;flex-direction:column;gap:10px;box-shadow:0 1px 3px rgba(15,23,42,.05);transition:box-shadow .15s,transform .15s}
    .sp-card:hover{box-shadow:0 8px 24px rgba(15,23,42,.12);transform:translateY(-2px)}
    .sp-card[hidden]{display:none!important}
    .sp-card.rec{border-color:#22c55e;box-shadow:0 0 0 2px rgba(34,197,94,.18)}
    .sp-top{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}
    .sp-sku{font-size:22px;font-weight:900;letter-spacing:-.01em;line-height:1.1;word-break:break-word}
    .sp-wh{background:#e0f2fe;color:#075985;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:800;white-space:nowrap}
    .sp-desc{font-size:14px;color:#334155;line-height:1.35;flex:1}
    .sp-specs{display:flex;flex-wrap:wrap;gap:6px}
    .sp-specs span{background:#f1f5f9;border-radius:8px;padding:4px 8px;font-size:12px;color:#334155}
    .sp-badge{display:inline-block;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:800}
    .sp-badge.good{background:#dcfce7;color:#166534}
    .sp-badge.review{background:#fef3c7;color:#92400e}
    .sp-foot{display:flex;justify-content:space-between;align-items:center;gap:10px;border-top:1px solid #f1f5f9;padding-top:12px}
    .sp-avail{font-size:13px;color:#475569;line-height:1.2}
    .sp-avail b{display:block;font-size:20px;color:#0f172a}
    .sp-btn{border:0;background:#115452;color:#fff;border-radius:12px;padding:12px 18px;font-size:15px;font-weight:800;cursor:pointer;min-height:46px}
    .sp-btn:hover{background:#0f766e}
    .sp-btn.green{background:#16a34a}.sp-btn.green:hover{background:#15803d}
    .sp-btn.ghost{background:#f1f5f9;color:#334155}.sp-btn.ghost:hover{background:#e2e8f0}
    .sp-btn.red{background:#fee2e2;color:#991b1b}.sp-btn.red:hover{background:#fecaca}
    .sp-empty{text-align:center;color:#64748b;padding:32px 12px;background:#f8fafc;border:2px dashed #cbd5e1;border-radius:16px;font-size:15px}
    .sp-alert{border-radius:12px;padding:13px 16px;margin-bottom:14px;font-weight:700}
    .sp-alert.err{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
    .sp-alert.ok{background:#dcfce7;color:#166534;border:1px solid #86efac}
    .sp-qty{display:flex;gap:8px;align-items:stretch}
    .sp-qty button{width:56px;border:1px solid #cbd5e1;background:#f8fafc;border-radius:12px;font-size:26px;font-weight:800;cursor:pointer}
    .sp-qty input{text-align:center;font-size:24px!important;font-weight:800}
    .sp-dlg-head{border-bottom:1px solid #e2e8f0;margin-bottom:6px;padding-bottom:10px}
    .sp-dlg-head .sp-sku{font-size:26px}
    /* solicitudes */
    .sp-h3{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin:6px 0 12px}
    .sp-h3 h3{margin:0;font-size:20px;font-weight:800}
    .sp-req{background:#fff;border:1px solid #e2e8f0;border-left:6px solid #94a3b8;border-radius:16px;padding:16px;margin-bottom:12px;box-shadow:0 1px 3px rgba(15,23,42,.05)}
    .sp-req.st-PENDING{border-left-color:#f59e0b}.sp-req.st-ACCEPTED{border-left-color:#3b82f6}
    .sp-req.st-DISPATCHED{border-left-color:#0d9488}.sp-req.st-RECEIVED{border-left-color:#22c55e}
    .sp-req.st-REJECTED,.sp-req.st-CANCELLED{border-left-color:#ef4444}
    .sp-req-head{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:flex-start}
    .sp-req-sku{font-size:18px;font-weight:900}
    .sp-req-meta{font-size:13px;color:#64748b;margin-top:3px;line-height:1.4}
    .sp-steps{display:flex;align-items:center;margin:14px 0 6px}
    .sp-step{flex:1;text-align:center;font-size:11.5px;font-weight:700;color:#94a3b8;position:relative}
    .sp-step:before{content:"";display:block;width:18px;height:18px;border-radius:50%;background:#e2e8f0;margin:0 auto 4px;position:relative;z-index:1}
    .sp-step:after{content:"";position:absolute;top:8px;left:calc(-50% + 9px);width:calc(100% - 18px);height:3px;background:#e2e8f0}
    .sp-step:first-child:after{display:none}
    .sp-step.done{color:#115452}.sp-step.done:before{background:#16a34a}.sp-step.done:after{background:#16a34a}
    .sp-step.now:before{background:#fff;border:4px solid #3b82f6}
    .sp-req-nums{display:flex;gap:18px;flex-wrap:wrap;margin:10px 0;font-size:13px;color:#475569}
    .sp-req-nums b{display:block;font-size:20px;color:#0f172a}
    .sp-req-act{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
    .sp-req-act form{margin:0}
    .sp-reason{background:#fef2f2;color:#991b1b;border-radius:10px;padding:9px 12px;font-size:13px;margin-top:8px}
    .sp-banner{background:#ecfeff;border:1px solid #67e8f9;color:#155e75;border-radius:14px;padding:14px 16px;margin-bottom:16px;font-weight:700}
    @media(max-width:640px){.sp-hero h2{font-size:20px}.sp-grid{grid-template-columns:1fr}.sp-btn{width:100%}.sp-foot{flex-direction:column;align-items:stretch}.sp-tab{flex:1 1 100%}.sp-req-act .sp-btn,.sp-req-act form{width:100%}.sp-step{font-size:10px}}
    .sp-sm{width:auto;flex:0 1 190px;padding:12px 10px}
    .sp-pager{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:10px 0}
    .sp-pager-sum{font-size:14px;color:#475569;font-weight:700}
    .sp-pager-btns{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
    .sp-pager-btns button{border:1px solid #cbd5e1;background:#fff;border-radius:10px;min-width:44px;min-height:44px;padding:0 12px;font-size:14px;font-weight:800;cursor:pointer;color:#334155}
    .sp-pager-btns button.active{background:#115452;color:#fff;border-color:#115452}
    .sp-pager-btns button:disabled{opacity:.4;cursor:default}
    .sp-dots{color:#94a3b8;font-weight:800}
    /* casillas de carga múltiple */
    .sp-slot-box{background:#f8fafc;border:1px solid #cbd5e1;border-radius:14px;padding:14px;margin:10px 0 14px}
    .sp-slot-bar{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:10px}
    .sp-slot-bar label{margin:0;font-size:13px;font-weight:800;color:#334155}
    .sp-slot-bar input.sp-slot-count-inp{width:84px!important;padding:7px 10px!important;font-size:15px!important;font-weight:800;text-align:center}
    .sp-slot-summary{margin-left:auto;background:#e0f2fe;color:#0369a1;padding:5px 12px;border-radius:999px;font-size:12.5px;font-weight:800}
    .sp-slot-help{font-size:12.5px;color:#0f766e;background:#f0fdfa;border:1px solid #99f6e4;border-radius:10px;padding:8px 12px;margin-bottom:10px;line-height:1.4}
    .sp-slot-list{display:flex;flex-direction:column;gap:8px;max-height:320px;overflow-y:auto;padding-right:4px}
    .sp-slot-row{display:flex;align-items:center;gap:8px}
    .sp-slot-idx{min-width:36px;height:36px;border-radius:9px;background:#e2e8f0;color:#334155;font-weight:800;font-size:13px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
    .sp-slot-row.filled .sp-slot-idx{background:#16a34a;color:#fff}
    .sp-slot-row.dup .sp-slot-idx{background:#dc2626;color:#fff}
    .sp-slot-row input.sp-slot-input{flex:1;padding:9px 12px!important;font-size:15px!important;font-family:monospace;font-weight:700}
    .sp-slot-row.filled input.sp-slot-input{border-color:#22c55e;background:#f0fdf4}
    .sp-slot-row.dup input.sp-slot-input{border-color:#ef4444;background:#fef2f2}
    .sp-slot-tag{font-size:12px;font-weight:800;white-space:nowrap}
    .sp-slot-row.filled .sp-slot-tag{color:#15803d}
    .sp-slot-row.dup .sp-slot-tag{color:#b91c1c}
    .sp-slot-rm{border:1px solid #cbd5e1;background:#fff;color:#64748b;border-radius:8px;width:36px;height:36px;font-size:14px;font-weight:800;cursor:pointer;flex-shrink:0}
    .sp-slot-rm:hover{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
    @media(max-width:640px){.sp-sm{flex:1 1 100%}.sp-pager{justify-content:center}.sp-pager-sum{width:100%;text-align:center}.sp-slot-summary{margin-left:0;width:100%;text-align:center}}
    </style>
    <script>
    var supplyCompatibleOnly=false;
    var SP_KEYS={rolls:'roll',inks:'ink',products:'product'};
    var SP_STATE={rolls:{page:1},inks:{page:1},products:{page:1}};
    function spNorm(t){t=(t||'').toLowerCase();try{t=t.normalize('NFD').replace(/[̀-ͯ]/g,'');}catch(e){}return t;}
    function supplyOpen(id){
        var d=document.getElementById(id);
        if(d){
            d.showModal();
            var f=d.querySelector('.sp-slot-input')||d.querySelector('input[name=code]');
            if(f)setTimeout(function(){f.focus();},40);
        }
    }
    function supplyCtl(section,name){return document.getElementById('sp_'+name+'_'+section);}
    function supplyApply(section,keepPage){
        var wrap=document.getElementById('supply_catalog_'+section);if(!wrap)return;
        var st=SP_STATE[section];if(!keepPage)st.page=1;
        var input=document.getElementById('supply_'+SP_KEYS[section]+'_search');
        var tokens=spNorm(input?input.value:'').split(/\s+/).filter(Boolean);
        var famSel=supplyCtl(section,'fam'),fam=famSel?famSel.value:'';
        var sortSel=supplyCtl(section,'sort'),sort=sortSel?sortSel.value:'sku';
        var sizeSel=supplyCtl(section,'size'),size=parseInt(sizeSel?sizeSel.value:'24',10)||24;
        var cards=[].slice.call(wrap.querySelectorAll('.sp-card'));
        var match=cards.filter(function(c){
            if(section==='rolls'&&supplyCompatibleOnly&&c.dataset.compatible==='0')return false;
            if(fam&&c.dataset.fam!==fam)return false;
            var q=c.dataset.q||'';
            for(var i=0;i<tokens.length;i++){if(q.indexOf(tokens[i])<0)return false;}
            return true;
        });
        match.sort(function(a,b){
            if(sort==='stock')return (parseFloat(b.dataset.stock)||0)-(parseFloat(a.dataset.stock)||0);
            if(sort==='desc')return (a.dataset.desc||'').localeCompare(b.dataset.desc||'','es');
            return (a.dataset.sku||'').localeCompare(b.dataset.sku||'','es',{numeric:true});
        });
        var pages=Math.max(1,Math.ceil(match.length/size));
        if(st.page>pages)st.page=pages;
        var from=(st.page-1)*size,to=from+size;
        cards.forEach(function(c){c.hidden=true;});
        match.forEach(function(c,i){c.style.order=i;c.hidden=!(i>=from&&i<to);});
        var cnt=document.getElementById('supply_count_'+section);if(cnt)cnt.textContent=match.length;
        var none=document.getElementById('supply_none_'+section);if(none)none.hidden=match.length>0;
        var sum=match.length?('Mostrando '+(from+1)+'–'+Math.min(to,match.length)+' de '+match.length):'Sin resultados';
        wrap.querySelectorAll('.sp-pager').forEach(function(p){supplyPager(p,section,st.page,pages,sum);});
    }
    function supplyPager(el,section,page,pages,sum){
        var h='<span class="sp-pager-sum">'+sum+'</span><span class="sp-pager-btns">';
        function b(label,p,dis,act){return '<button type="button" class="'+(act?'active':'')+'" '+(dis?'disabled':'')+' onclick="supplyGo(\''+section+'\','+p+')">'+label+'</button>';}
        if(pages>1){
            h+=b('‹ Anterior',page-1,page<=1,false);
            var last=0;
            for(var p=1;p<=pages;p++){
                if(p===1||p===pages||Math.abs(p-page)<=2){
                    if(last&&p-last>1)h+='<span class="sp-dots">…</span>';
                    h+=b(p,p,false,p===page);last=p;
                }
            }
            h+=b('Siguiente ›',page+1,page>=pages,false);
        }
        el.innerHTML=h+'</span>';
    }
    function supplyGo(section,p){
        SP_STATE[section].page=Math.max(1,p);supplyApply(section,true);
        var w=document.getElementById('supply_'+section);if(w&&w.scrollIntoView)w.scrollIntoView({behavior:'smooth',block:'start'});
    }
    function supplyClear(section){
        var i=document.getElementById('supply_'+SP_KEYS[section]+'_search');if(i)i.value='';
        ['fam'].forEach(function(n){var c=supplyCtl(section,n);if(c)c.value='';});
        var s=supplyCtl(section,'sort');if(s)s.value='sku';
        supplyApply(section);
    }
    function supplyFilter(value,id){supplyApply(id.replace('supply_catalog_',''));}
    function supplyShowRolls(compatibleOnly){
        supplyCompatibleOnly=compatibleOnly;
        document.querySelectorAll('.sp-seg button').forEach(function(b){b.classList.toggle('active',(b.dataset.mode==='compat')===compatibleOnly);});
        supplyApply('rolls');
    }
    function supplyTab(kind){
        ['rolls','inks','products','boxes'].forEach(function(k){
            var s=document.getElementById('supply_'+k);if(s)s.hidden=kind!==k;
            var t=document.getElementById('sp_tab_'+k);if(t)t.classList.toggle('active',kind===k);
        });
        document.querySelectorAll('.supply-request-kind').forEach(function(input){input.value=kind;});
        try{var url=new URL(location.href);url.searchParams.set('material_kind',kind);history.replaceState(null,'',url);}catch(e){}
    }
    function supplyStep(btn,delta){
        var input=btn.parentNode.querySelector('input');if(!input)return;
        var step=parseFloat(input.dataset.btnstep||'1')||1,min=parseFloat(input.min)||0,max=parseFloat(input.max);
        var v=(parseFloat(input.value)||0)+delta*step;
        if(v<min)v=min;if(!isNaN(max)&&v>max)v=max;
        input.value=Math.round(v*1000)/1000;
    }
    function supplyKey(){var a=new Uint8Array(16);(window.crypto||window.msCrypto).getRandomValues(a);return [].map.call(a,function(x){return ('0'+x.toString(16)).slice(-2);}).join('');}
    function supplyAsk(btn){
        var d=document.getElementById('supply_request_dlg');if(!d)return;
        var c=btn.closest('.sp-card'),ds=c.dataset,f=d.querySelector('form');
        d.querySelector('.sp-sku').textContent=ds.skuraw;
        d.querySelector('.sp-dlg-desc').textContent=ds.descraw;
        d.querySelector('.sp-dlg-avail').textContent=ds.availtxt+' '+ds.unit+' · Bodega '+ds.whlabel;
        d.querySelector('.sp-dlg-unit').textContent=ds.unit;
        f.elements['item_id'].value=ds.item;
        f.elements['source_storehouse_id'].value=ds.source;
        f.elements['request_key'].value=supplyKey();
        var q=f.elements['quantity'];
        q.value='';q.max=ds.stock;q.min=ds.whole==='1'?'1':'0.001';q.step=ds.whole==='1'?'1':'0.001';
        f.elements['notes'].value='';
        d.showModal();
        setTimeout(function(){q.focus();},50);
    }
    /* ---- Cargador por casillas (sin pérdida en escaneo rápido) ---- */
    function spNormCode(v){
        return String(v||'').replace(/['`´’‘‛]/g,'-').trim();
    }
    function spSplitCodes(raw){
        var s=String(raw||'').replace(/['`´’‘‛]/g,'-');
        s=s.replace(/([A-Za-z0-9])((?:RB|CJ|BT|TRZ)-\d)/gi,'$1 $2');
        return s.split(/[\s,;]+/).map(function(p){return p.trim();}).filter(Boolean);
    }
    function spCreateSlotRow(idx,val){
        var row=document.createElement('div');
        row.className='sp-slot-row';
        row.innerHTML='<span class="sp-slot-idx">#'+idx+'</span>'
            +'<input type="text" name="codes[]" class="sp-slot-input" maxlength="240" autocomplete="off" placeholder="Escanea o escribe el código #'+idx+'">'
            +'<span class="sp-slot-tag"></span>'
            +'<button type="button" class="sp-slot-rm" onclick="spClearOrRemoveSlot(this)" title="Limpiar o quitar casilla">✕</button>';
        var inp=row.querySelector('.sp-slot-input');
        if(val)inp.value=val;
        return row;
    }
    function spRefreshSlots(form){
        if(!form)return;
        var list=form.querySelector('.sp-slot-list');if(!list)return;
        var rows=[].slice.call(list.querySelectorAll('.sp-slot-row'));
        var countInp=form.querySelector('.sp-slot-count-inp');
        if(countInp)countInp.value=rows.length;
        var existing=[];
        try{existing=JSON.parse(form.dataset.existing||'[]');}catch(e){}
        var seen={};
        existing.forEach(function(c){seen[String(c).toUpperCase()]=true;});
        var validCodes=[],dupCount=0;
        rows.forEach(function(row,i){
            var badge=row.querySelector('.sp-slot-idx');if(badge)badge.textContent='#'+(i+1);
            var inp=row.querySelector('.sp-slot-input');if(!inp)return;
            inp.placeholder='Escanea o escribe el código #'+(i+1);
            var tag=row.querySelector('.sp-slot-tag');
            var v=spNormCode(inp.value);
            if(inp.value!==v&&document.activeElement!==inp)inp.value=v;
            row.classList.remove('filled','dup');
            if(tag)tag.textContent='';
            if(v!==''){
                var up=v.toUpperCase();
                if(seen[up]){
                    row.classList.add('dup');
                    if(tag)tag.textContent='⚠️ Repetido';
                    dupCount++;
                }else{
                    seen[up]=true;
                    row.classList.add('filled');
                    if(tag)tag.textContent='✓ Listo';
                    validCodes.push(v);
                }
            }
        });
        var sum=form.querySelector('.sp-slot-summary');
        if(sum)sum.textContent=validCodes.length+' de '+rows.length+' casilla(s) con código'+(dupCount?' · '+dupCount+' repetido(s)':'');
        var scanBtn=form.querySelector('.sp-scan-submit-btn');
        if(scanBtn){
            scanBtn.disabled=(validCodes.length===0||dupCount>0);
            scanBtn.textContent=validCodes.length>0?('📥 Cargar todos los códigos ('+validCodes.length+')'):'📥 Cargar todos los códigos a la lista';
        }
        var dlg=form.closest('dialog');
        if(dlg){
            var dispForm=dlg.querySelector('.sp-dispatch-form');
            if(dispForm){
                var hiddenCodes=dispForm.querySelector('input[name="codes"]');
                if(hiddenCodes)hiddenCodes.value=validCodes.join('\n');
                var dispBtn=dispForm.querySelector('.sp-dispatch-submit-btn');
                if(dispBtn){
                    var isBox=form.dataset.isBox==='1';
                    var reqQty=parseFloat(form.dataset.reqQty||'0')||0;
                    var scanTotal=parseFloat(form.dataset.scanTotal||'0')||0;
                    var ready=isBox?((scanTotal>0||validCodes.length>0)&&dupCount===0):((scanTotal+validCodes.length+0.000001>=reqQty)&&dupCount===0);
                    dispBtn.disabled=!ready;
                    dispBtn.textContent=validCodes.length>0
                        ?('🚀 Cargar ('+validCodes.length+') y Traspasar a 3000 Insumos en Producción')
                        :'Traspasar a 3000 Insumos en Producción';
                }
            }
        }
    }
    function spDistributeInput(inp,advance){
        var form=inp.closest('.sp-batch-scan-form');if(!form)return;
        var list=form.querySelector('.sp-slot-list');if(!list)return;
        var parts=spSplitCodes(inp.value);
        var inputs=[].slice.call(list.querySelectorAll('.sp-slot-input'));
        var idx=inputs.indexOf(inp);
        if(parts.length===0){
            inp.value='';
            spRefreshSlots(form);
            return;
        }
        var existing={};
        try{(JSON.parse(form.dataset.existing||'[]')||[]).forEach(function(c){existing[String(c).toUpperCase()]=true;});}catch(e){}
        inputs.forEach(function(other,j){
            if(j!==idx){
                var ov=spNormCode(other.value).toUpperCase();
                if(ov)existing[ov]=true;
            }
        });
        var uniqueParts=[];
        parts.forEach(function(p){
            var up=p.toUpperCase();
            if(!existing[up]){
                existing[up]=true;
                uniqueParts.push(p);
            }
        });
        if(uniqueParts.length===0){
            inp.value='';
            spRefreshSlots(form);
            inp.focus();
            return;
        }
        inp.value=uniqueParts[0];
        var curIdx=idx;
        for(var k=1;k<uniqueParts.length;k++){
            curIdx++;
            inputs=[].slice.call(list.querySelectorAll('.sp-slot-input'));
            while(curIdx<inputs.length&&spNormCode(inputs[curIdx].value)!=='')curIdx++;
            if(curIdx>=inputs.length){
                var nr=spCreateSlotRow(inputs.length+1,uniqueParts[k]);
                list.appendChild(nr);
            }else{
                inputs[curIdx].value=uniqueParts[k];
            }
        }
        spRefreshSlots(form);
        if(advance||uniqueParts.length>1){
            inputs=[].slice.call(list.querySelectorAll('.sp-slot-input'));
            var nextEmpty=null;
            for(var m=curIdx+1;m<inputs.length;m++){
                if(spNormCode(inputs[m].value)===''){nextEmpty=inputs[m];break;}
            }
            if(!nextEmpty){
                for(var n=0;n<inputs.length;n++){
                    if(spNormCode(inputs[n].value)===''){nextEmpty=inputs[n];break;}
                }
            }
            if(!nextEmpty){
                var extraRow=spCreateSlotRow(inputs.length+1,'');
                list.appendChild(extraRow);
                spRefreshSlots(form);
                nextEmpty=extraRow.querySelector('.sp-slot-input');
            }
            if(nextEmpty)nextEmpty.focus();
        }
    }
    function spApplySlotCount(btn){
        var form=btn.closest('.sp-batch-scan-form');if(!form)return;
        var list=form.querySelector('.sp-slot-list');
        var countInp=form.querySelector('.sp-slot-count-inp');
        var target=Math.max(1,Math.min(100,parseInt(countInp?countInp.value:'1',10)||1));
        var rows=[].slice.call(list.querySelectorAll('.sp-slot-row'));
        if(rows.length<target){
            for(var i=rows.length;i<target;i++){
                list.appendChild(spCreateSlotRow(i+1,''));
            }
        }else if(rows.length>target){
            for(var j=rows.length-1;j>=0&&list.querySelectorAll('.sp-slot-row').length>target;j--){
                var inp=rows[j].querySelector('.sp-slot-input');
                if(!inp||spNormCode(inp.value)===''){
                    rows[j].remove();
                }
            }
        }
        spRefreshSlots(form);
        var firstEmpty=[].slice.call(list.querySelectorAll('.sp-slot-input')).find(function(x){return spNormCode(x.value)==='';});
        if(firstEmpty)firstEmpty.focus();
    }
    function spAddSlotRow(btn){
        var form=btn.closest('.sp-batch-scan-form');if(!form)return;
        var list=form.querySelector('.sp-slot-list');
        var n=list.querySelectorAll('.sp-slot-row').length+1;
        var row=spCreateSlotRow(n,'');
        list.appendChild(row);
        spRefreshSlots(form);
        var inp=row.querySelector('.sp-slot-input');if(inp)inp.focus();
    }
    function spCleanEmptySlots(btn){
        var form=btn.closest('.sp-batch-scan-form');if(!form)return;
        var list=form.querySelector('.sp-slot-list');
        var rows=[].slice.call(list.querySelectorAll('.sp-slot-row'));
        rows.forEach(function(r){
            var inp=r.querySelector('.sp-slot-input');
            if(list.querySelectorAll('.sp-slot-row').length>1&&(!inp||spNormCode(inp.value)==='')){
                r.remove();
            }
        });
        spRefreshSlots(form);
    }
    function spClearOrRemoveSlot(btn){
        var form=btn.closest('.sp-batch-scan-form');if(!form)return;
        var list=form.querySelector('.sp-slot-list');
        var row=btn.closest('.sp-slot-row');if(!row)return;
        var inp=row.querySelector('.sp-slot-input');
        if(inp&&spNormCode(inp.value)!==''){
            inp.value='';
            spRefreshSlots(form);
            inp.focus();
            return;
        }
        if(list.querySelectorAll('.sp-slot-row').length>1){
            row.remove();
            spRefreshSlots(form);
        }
    }
    document.addEventListener('keydown',function(e){
        var t=e.target;
        if(!t||!t.classList)return;
        if(t.classList.contains('sp-slot-input')&&(e.key==='Enter'||e.keyCode===13)){
            e.preventDefault();
            e.stopPropagation();
            spDistributeInput(t,true);
        }else if(t.classList.contains('sp-slot-count-inp')&&(e.key==='Enter'||e.keyCode===13)){
            e.preventDefault();
            e.stopPropagation();
            spApplySlotCount(t);
        }
    },true);
    document.addEventListener('input',function(e){
        var t=e.target;
        if(!t||!t.classList||!t.classList.contains('sp-slot-input'))return;
        var v=t.value.replace(/['`´’‘‛]/g,'-');
        if(v!==t.value)t.value=v;
        if(/[\s,;]/.test(v)||/([A-Za-z0-9])((?:RB|CJ|BT|TRZ)-\d)/i.test(v)){
            spDistributeInput(t,true);
        }else{
            spRefreshSlots(t.closest('.sp-batch-scan-form'));
        }
    });
    document.addEventListener('DOMContentLoaded',function(){
        ['rolls','inks','products','boxes'].forEach(function(s){supplyApply(s);});
        document.querySelectorAll('.sp-batch-scan-form').forEach(function(f){
            spRefreshSlots(f);
            f.addEventListener('submit',function(ev){
                var list=f.querySelectorAll('.sp-slot-input');
                var hasAny=false;
                for(var i=0;i<list.length;i++){
                    list[i].value=spNormCode(list[i].value);
                    if(list[i].value!=='')hasAny=true;
                }
                if(!hasAny){ev.preventDefault();return;}
                var btn=f.querySelector('.sp-scan-submit-btn');
                if(btn){btn.disabled=true;btn.textContent='Cargando códigos...';}
            });
        });
        document.querySelectorAll('.sp-dispatch-form').forEach(function(df){
            df.addEventListener('submit',function(){
                var dlg=df.closest('dialog');
                if(dlg){
                    var sf=dlg.querySelector('.sp-batch-scan-form');
                    if(sf)spRefreshSlots(sf);
                }
            });
        });
    });
    </script>
    <?php
    if (!empty($_GET['error'])) echo '<div class="sp-alert err">⚠️ ' . supplyEscape($_GET['error']) . '</div>';
    if (!empty($_GET['saved'])) echo '<div class="sp-alert ok">✅ Operación registrada.</div>';
}

function renderSupplyOperatorScreen(MaterialSupplyService $service, array $context, string $name, array $orders = [], string $kind = 'rolls'): void
{
    $agId = (int)$context['agenda_id'];
    $agenda = $context['planning_details'] ?? null;
    $catalog = $service->catalog((int)$context['req_shop_id']);
    $rolls = [];
    $inks = [];
    $products = [];
    $boxes = [];
    foreach ($catalog as $item) {
        if (($item['kind'] ?? '') === 'boxes') {
            $item['recommended'] = false;
            $boxes[] = $item;
        } elseif (($item['kind'] ?? '') === 'products') {
            $item['recommended'] = false;
            $products[] = $item;
        } elseif (str_starts_with(strtoupper($item['sku']),'TIN')) {
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
    $catalog = array_merge($rolls,$inks,$products,$boxes);
    ob_start();
    supplyAssets();
    $pendingReceipts = 0;
    foreach ($service->requests(null,(int)$context['win_equipoid']) as $mine) {
        if (($mine['status'] ?? '') === 'DISPATCHED') $pendingReceipts++;
    }
    $tabs = ['rolls'=>['📜','Bobinas',count($rolls)],'inks'=>['🎨','Tintas',count($inks)],'products'=>['📦','Productos Bodega 400',count($products)],
        'boxes'=>['🧺','Cajas de bolsas',count($boxes)]];
    $sections = ['rolls'=>'Bobinas e insumos','inks'=>'Tintas','products'=>'Productos de Bodega 400','boxes'=>'Bolsas en cajas con código'];
    $routes = ['rolls'=>'Bodegas 100 / 200 → 3000 Insumos en Producción','inks'=>'Bodegas 900 / 910 / 920 → 3000 Insumos en Producción','products'=>'Bodega 400 → 3000 Insumos en Producción',
        'boxes'=>'Bodega de la caja → 3000 Insumos en Producción'];
    $helps = ['rolls'=>'Pedir bobinas no mueve stock: Bodega prepara el despacho y tú confirmas la recepción.',
        'inks'=>'Indica la cantidad de tinta. Bodega registra el traspaso a 3000 Insumos en Producción; recibir no descuenta consumo.',
        'products'=>'Bolsas de algodón y otros productos de la bodega 400. Bodega registra el traspaso y tú confirmas la recepción.',
        'boxes'=>'Bolsas en blanco o de algodón que están en cajas con código (Pulpo Serigráfico). Pide las bolsas; bodega escanea las cajas y traspasa cada una completa, con su CC.'];
    ?>
    <div class="sp">
    <div class="sp-hero">
        <div>
            <h2>Solicitar materiales</h2>
            <span class="sp-chip">🏭 <?=supplyEscape($context['machine_name'])?></span>
        </div>
        <a class="sp-link" href="/production/machines">Cambiar máquina</a>
    </div>

    <?php if ($pendingReceipts > 0): ?>
    <div class="sp-banner">📬 Tienes <?=$pendingReceipts?> entrega<?=$pendingReceipts>1?'s':''?> por recibir. Búscalas abajo, en «Mis solicitudes».</div>
    <?php endif; ?>

    <div class="sp-card-box">
        <label class="sp-label" for="supply_ot">OT planificada para hoy (<?=date('d/m/Y')?>)</label>
        <select id="supply_ot" class="sp-select" onchange="location.href='/production/materials/request?agid='+this.value+'&material_kind='+(new URL(location.href).searchParams.get('material_kind')||'rolls')">
            <option value="0">Sin OT — solicitud para la máquina</option>
            <?php $seen=[]; foreach ($orders as $order): $id=(int)$order['ag_id'];if(isset($seen[$id]))continue;$seen[$id]=true; ?>
            <option value="<?=$id?>" <?=$id===$agId?'selected':''?>>OT <?=supplyEscape($order['prd_number']??$id)?> — <?=supplyEscape($order['cust_name']??'')?> — <?=supplyEscape($order['item_title']??'')?></option>
            <?php endforeach; ?>
            <?php if ($agId>0 && !isset($seen[$agId])): ?><option selected value="<?=$agId?>">OT <?=supplyEscape($context['planned_ot_number']??$agId)?></option><?php endif; ?>
        </select>
        <?php if ($agenda): $requirements=MaterialSupplyRecommendations::requirements($agenda); ?>
        <div class="sp-req-chips">
            <span>Material: <b><?=supplyEscape($requirements['material']?:'Sin dato')?></b></span>
            <span>Ancho: <b><?=$requirements['width_cm']>0?supplyEscape($requirements['width_cm']).' cm':'Sin dato'?></b></span>
            <span>Color: <b><?=supplyEscape($requirements['color']?:'Sin dato')?></b></span>
            <span>Gramaje: <b><?=$requirements['grammage']>0?supplyEscape($requirements['grammage']).' g/m²':'Sin dato'?></b></span>
        </div>
        <div class="sp-note <?=$compatible?'ok':'warn'?>">
            <?php if ($compatible): ?>
                ✅ <b><?=count($compatible)?> tipo<?=count($compatible)>1?'s':''?> de bobina sirven para esta OT</b>
                <b class="big"><?=supplyEscape(implode(', ',array_column($compatible,'sku')))?></b>
                Coinciden en material, color y ancho (igual o mayor) con la ficha de la OT, igual que en la pantalla de la OT. Se muestran primero.
            <?php else:
                $near=array_values(array_filter($rolls,static fn($r)=>count(array_filter($r['checks'],static fn($v)=>$v===false))<=1 && (int)$r['match_score']>0));
                $near=array_slice($near,0,5); ?>
                <?php if ($requirements['material']==='' && $requirements['width_cm']<=0 && $requirements['color']==='' && $requirements['grammage']<=0): ?>
                ⚠️ La ficha de esta OT no trae datos de la tela (material, ancho, color ni gramaje), por eso no se puede recomendar. Elige la bobina manualmente en «Todas» o completa la ficha de la OT.
                <?php else: ?>
                ⚠️ Ninguna bobina con stock en 100 o 200 coincide completamente con esta OT.
                <?php if ($near): ?>Las más cercanas:
                <b class="big"><?=supplyEscape(implode(', ',array_column($near,'sku')))?></b>
                Se muestran primero; revisa en cada tarjeta qué dato no coincide.
                <?php else: ?>No hay opciones cercanas; puedes elegir otra manualmente en «Todas».<?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="sp-tabs" role="tablist">
        <?php foreach ($tabs as $key=>[$icon,$label,$total]): ?>
        <button type="button" role="tab" id="sp_tab_<?=$key?>" class="sp-tab<?=$kind===$key?' active':''?>" onclick="supplyTab('<?=$key?>')"><?=$icon?> <?=$label?> <i><?=$total?></i></button>
        <?php endforeach; ?>
    </div>

    <?php foreach ($sections as $section=>$title): ?>
    <div id="supply_<?=$section?>" <?=$kind!==$section?'hidden':''?>>
        <p class="sp-route"><b><?=$routes[$section]?></b> · <?=$helps[$section]?></p>
        <?php
        $families=[];
        foreach ($catalog as $fi) {
            $fk=$fi['kind']??(str_starts_with(strtoupper($fi['sku']),'TIN')?'inks':'rolls');
            if ($fk!==$section) continue;
            $fam=preg_match('/^[A-Za-z]+/',trim((string)$fi['sku']),$fm)===1?strtoupper($fm[0]):'OTROS';
            $families[$fam]=($families[$fam]??0)+1;
        }
        ksort($families);
        ?>
        <div class="sp-tools">
            <input id="supply_<?=['rolls'=>'roll','inks'=>'ink','products'=>'product','boxes'=>'box'][$section]?>_search" class="sp-search" type="search" placeholder="🔎 Buscar por código o descripción (varias palabras)" oninput="supplyApply('<?=$section?>')">
            <?php if (count($families)>1): ?>
            <select id="sp_fam_<?=$section?>" class="sp-select sp-sm" onchange="supplyApply('<?=$section?>')" aria-label="Filtrar por familia de código">
                <option value="">Todas las familias (<?=count($families)?>)</option>
                <?php foreach ($families as $famName=>$famCount): ?><option value="<?=supplyEscape($famName)?>"><?=supplyEscape($famName)?> (<?=$famCount?>)</option><?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select id="sp_sort_<?=$section?>" class="sp-select sp-sm" onchange="supplyApply('<?=$section?>')" aria-label="Ordenar">
                <option value="sku">Orden: código</option>
                <option value="desc">Orden: descripción</option>
                <option value="stock">Orden: mayor stock</option>
            </select>
            <select id="sp_size_<?=$section?>" class="sp-select sp-sm" onchange="supplyApply('<?=$section?>')" aria-label="Productos por página">
                <option value="12">12 por página</option><option value="24" selected>24 por página</option><option value="48">48 por página</option><option value="96">96 por página</option>
            </select>
            <button type="button" class="sp-btn ghost" onclick="supplyClear('<?=$section?>')">Limpiar</button>
            <?php if ($section==='rolls' && $agenda): ?>
            <div class="sp-seg">
                <button type="button" data-mode="compat" class="<?=$compatibleOnly?'active':''?>" onclick="supplyShowRolls(true)">Compatibles (<?=count($compatible)?>)</button>
                <button type="button" data-mode="all" class="<?=$compatibleOnly?'':'active'?>" onclick="supplyShowRolls(false)">Todas</button>
            </div>
            <?php endif; ?>
        </div>
        <div id="supply_catalog_<?=$section?>">
            <div class="sp-pager"></div>
            <div class="sp-grid">
            <?php $count=0; foreach ($catalog as $i=>$item):
                $itemKind=$item['kind']??(str_starts_with(strtoupper($item['sku']),'TIN')?'inks':'rolls');
                if($itemKind!==$section)continue;$count++;
                $isRec=!empty($item['recommended']);
                $famItem=preg_match('/^[A-Za-z]+/',trim((string)$item['sku']),$fm2)===1?strtoupper($fm2[0]):'OTROS';
                $isWhole=str_starts_with(strtoupper($item['sku']),'TEL') || $itemKind==='boxes'; ?>
                <div class="sp-card<?=$isRec?' rec':''?>" hidden
                    data-compatible="<?=$isRec?'1':'0'?>" data-fam="<?=supplyEscape($famItem)?>"
                    data-sku="<?=supplyEscape(strtolower($item['sku']))?>" data-desc="<?=supplyEscape(mb_strtolower((string)$item['description'],'UTF-8'))?>"
                    data-q="<?=supplyEscape(mb_strtolower($item['sku'].' '.$item['description'],'UTF-8'))?>"
                    data-stock="<?=(float)$item['available_qty']?>" data-item="<?=(int)$item['item_id']?>" data-source="<?=(int)$item['source_storehouse_id']?>"
                    data-skuraw="<?=supplyEscape($item['sku'])?>" data-descraw="<?=supplyEscape($item['description'])?>" data-unit="<?=supplyEscape($item['unit'])?>"
                    data-availtxt="<?=supplyEscape(supplyNum((float)$item['available_qty']))?>" data-whlabel="<?=supplyEscape(supplyWarehouseLabel((int)$item['source_storehouse_id']))?>" data-whole="<?=$isWhole?'1':'0'?>">
                    <div class="sp-top"><span class="sp-sku"><?=supplyEscape($item['sku'])?></span><span class="sp-wh">Bod. <?=supplyWarehouseLabel((int)$item['source_storehouse_id'])?></span></div>
                    <div class="sp-desc"><?=supplyEscape($item['description'])?></div>
                    <?php if ($itemKind==='rolls'): $specs=$item['specs']; ?>
                    <div class="sp-specs">
                        <span><?=supplyEscape($specs['material']?:'Sin material')?></span>
                        <span><?=$specs['width_cm']>0?supplyEscape($specs['width_cm']).' cm':'Sin ancho'?></span>
                        <span><?=supplyEscape($specs['color']?:'Sin color')?></span>
                        <span><?=$specs['grammage']>0?supplyEscape($specs['grammage']).' g/m²':'Sin gramaje'?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($isRec): ?><div><span class="sp-badge good">✔ <?=$itemKind==='inks'?'Color de la OT':'Recomendada para esta OT'?></span></div>
                    <?php elseif ($agenda && $itemKind==='rolls'):
                        $failed=array_keys(array_filter($item['checks'],static fn($match)=>$match===false));
                        $unknown=array_keys(array_filter($item['checks'],static fn($match)=>$match===null)); ?>
                    <div><?php if ($failed): ?><span class="sp-badge review">No coincide: <?=supplyEscape(implode(', ',$failed))?></span><?php endif; ?>
                    <?php if ($unknown): ?><span class="sp-badge" style="background:#e2e8f0;color:#475569">Sin dato: <?=supplyEscape(implode(', ',$unknown))?></span><?php endif; ?></div>
                    <?php endif; ?>
                    <?php if ($itemKind==='boxes'): ?><div class="sp-specs"><span><?=(int)($item['box_count']??0)?> caja<?=(int)($item['box_count']??0)===1?'':'s'?> con código</span></div><?php endif; ?>
                    <div class="sp-foot">
                        <div class="sp-avail"><b><?=supplyNum((float)$item['available_qty'])?></b><?=supplyEscape($item['unit'])?> disponibles</div>
                        <button type="button" class="sp-btn" onclick="supplyAsk(this)">Solicitar</button>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <div id="supply_none_<?=$section?>" class="sp-empty" <?=$count?'hidden':''?>>No hay materiales que coincidan con los filtros<?=$count?'':' (esta sección no tiene stock disponible)'?>.</div>
            <div class="sp-pager"></div>
        </div>
    </div>
    <?php endforeach; ?>
    <script>supplyCompatibleOnly=<?=$compatibleOnly?'true':'false'?>;</script>

    <dialog id="supply_request_dlg" class="supply-dialog" style="width:min(560px,94vw)">
        <div class="sp-dlg-head"><span class="sp-sku"></span><div class="sp-desc sp-dlg-desc" style="margin-top:4px"></div>
        <div class="sp-avail sp-dlg-avail" style="margin-top:8px"></div></div>
        <form method="post" action="/production/materials/request/assign">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="action_mode" value="central_supply">
            <input type="hidden" name="agid" value="<?=$agId?>">
            <input type="hidden" class="supply-request-kind" name="material_kind" value="<?=$kind?>">
            <input type="hidden" name="request_key" value="">
            <input type="hidden" name="item_id" value="">
            <input type="hidden" name="source_storehouse_id" value="">
            <label>¿Cuántas <span class="sp-dlg-unit">unidades</span> necesitas?</label>
            <div class="sp-qty">
                <button type="button" onclick="supplyStep(this,-1)" aria-label="Menos">−</button>
                <input type="number" name="quantity" data-btnstep="1" step="0.001" min="0.001" placeholder="0" required inputmode="decimal">
                <button type="button" onclick="supplyStep(this,1)" aria-label="Más">+</button>
            </div>
            <label>Observaciones (opcional)</label><textarea name="notes" rows="3" placeholder="Ej.: urgente para el turno de la tarde"></textarea>
            <div class="supply-actions"><button type="button" class="sp-btn ghost" onclick="this.closest('dialog').close()">Volver</button><button class="sp-btn green">Enviar solicitud</button></div>
        </form>
    </dialog>
    <?php renderSupplyRequestHistory($service,$service->requests(null,(int)$context['win_equipoid']),$agId,false); ?>
    </div>
    <?php
    renderProdwrkShell('Solicitar Materiales',ob_get_clean(),7,$name,(string)($_SESSION['planta_name']??'PLANTA'));
}

function supplyWarehouseLabel(int $id): string
{
    static $names = null;
    $known = [5=>'100',11=>'200',7=>'900',24=>'910',28=>'920',16=>'3000'];
    if (isset($known[$id])) return $known[$id];
    if ($names === null) {
        $names = [];
        try {
            foreach (Db::erpPdo()->query('SELECT id, st_name FROM company_shops_storehouses')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (preg_match('/^\s*(\d{3,4})\b/', (string)$row['st_name'], $m) === 1) $names[(int)$row['id']] = $m[1];
            }
        } catch (Throwable $e) {
            error_log('supplyWarehouseLabel: ' . $e->getMessage());
        }
    }
    return $names[$id] ?? (string)$id;
}

function renderSupplyRequestHistory(MaterialSupplyService $service, array $requests, int $agId, bool $warehouse): void
{
    if ($warehouse) supplyAssets();
    ?>
    <div class="<?=$warehouse?'sp':''?>">
    <div class="sp-h3"><h3><?=$warehouse?'📋 Solicitudes de materiales':'📋 Mis solicitudes'?> <small style="color:#64748b;font-weight:600">(<?=count($requests)?>)</small></h3>
    <?php if ($warehouse): ?><div><a href="/stock/transfers" class="sp-btn ghost" style="text-decoration:none;display:inline-block">Traspasos</a> <a href="/stock/material-supply/reports" class="sp-btn ghost" style="text-decoration:none;display:inline-block">Reportes</a></div><?php endif; ?></div>
    <?php
    $stepFlow = ['PENDING'=>1,'ACCEPTED'=>2,'DISPATCHED'=>3,'RECEIVED'=>4];
    $stepNames = [1=>'Solicitada',2=>'En preparación',3=>'Despachada',4=>'Recibida'];
    foreach ($requests as $i=>$r):
        $remaining=max(0,(float)$r['requested_qty']-(float)$r['allocated_qty']);$details='supply_details_'.$i;
        $ctx=json_decode($r['context'],true)?:[];
        $st=(string)$r['status'];$cur=$stepFlow[$st]??0;
        $req=(float)$r['requested_qty'];$pct=$req>0?max(0,min(100,(int)round((float)$r['received_qty']/$req*100))):0; ?>
    <div class="sp-req st-<?=supplyEscape($st)?>">
        <div class="sp-req-head">
            <div>
                <div class="sp-req-sku"><?=supplyEscape($r['sku'])?> · <?=supplyEscape($r['description'])?></div>
                <div class="sp-req-meta">
                    🏭 <?=supplyEscape($ctx['machine_name']??('Máquina #'.($ctx['win_equipoid']??0)))?>
                    <?php if (!empty($ctx['planned_ot_number'])): ?> · OT <?=supplyEscape($ctx['planned_ot_number'])?><?php elseif ((int)$r['worker_ot_id']>0): ?> · OT #<?=(int)$r['worker_ot_id']?><?php endif; ?>
                    · <?=supplyDate($r['created_at'])?><br>
                    Solicita: <?=supplyEscape(supplyActorLabel($r['requested_by']))?> · Bodega <?=supplyWarehouseLabel((int)$r['source_storehouse_id'])?> → <?=(int)$r['source_storehouse_id']===16?'Máquina':'3000 Insumos en Producción'?> · N.º <?=supplyEscape(substr($r['request_key'],0,8))?>
                </div>
            </div>
            <div><?=supplyStatusBadge($r)?></div>
        </div>
        <?php if ($cur>0): ?>
        <div class="sp-steps"><?php foreach ($stepNames as $n=>$label): ?><div class="sp-step<?=$n<$cur||($cur===4&&$n===4)?' done':($n===$cur?' now':'')?>"><?=$label?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="sp-req-nums">
            <div>Solicitado<b><?=supplyEscape($r['requested_qty'])?></b><?=supplyEscape($r['unit'])?></div>
            <div>Entregado<b><?=supplyEscape($r['delivered_qty'])?></b><?=supplyEscape($r['unit'])?></div>
            <div>Recibido<b><?=supplyEscape($r['received_qty'])?></b><?=supplyEscape($r['unit'])?></div>
            <?php if (!empty($r['notes'])): ?><div style="flex:1 1 220px">Observaciones<b style="font-size:14px;font-weight:600"><?=supplyEscape($r['notes'])?></b></div><?php endif; ?>
        </div>
        <?php if ($st==='REJECTED' && !empty($r['reject_reason'])): ?><div class="sp-reason">Rechazada: <?=supplyEscape($r['reject_reason'])?></div><?php endif; ?>
        <?php if ($st==='CANCELLED' && !empty($r['cancel_reason'])): ?><div class="sp-reason">Cancelada: <?=supplyEscape($r['cancel_reason'])?></div><?php endif; ?>
        <div class="sp-req-act">
            <?php if (!$warehouse && $st==='DISPATCHED'): ?><button type="button" class="sp-btn green" onclick="supplyOpen('<?=$details?>')">📥 Recibir material</button><?php endif; ?>
            <button type="button" class="sp-btn ghost" onclick="supplyOpen('<?=$details?>')">Ver detalle</button>
            <?php if ($warehouse && (int)$r['source_storehouse_id']!==16): ?>
                <?php if ($st==='PENDING'): ?>
                <form method="post" action="/stock/material-supply/accept">
                    <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
                    <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
                    <button class="sp-btn green">✔ Aceptar</button>
                </form>
                <button type="button" class="sp-btn red" onclick="supplyOpen('supply_reject_<?=$i?>')">Rechazar</button>
                <?php elseif ($st==='ACCEPTED' && $remaining>0): ?>
                <button type="button" class="sp-btn green" onclick="supplyOpen('supply_dispatch_<?=$i?>')">📦 Preparar y despachar</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ((int)$r['source_storehouse_id']===16 && $remaining>0): ?><p class="sp-req-meta">Registro anterior: crea una solicitud desde 900, 910 o 920.</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php if (!$requests): ?><div class="sp-empty">Aún no hay solicitudes registradas.</div><?php endif; ?>
    </div>
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
        <td><?=supplyEscape($d['quantity'])?> <?=supplyEscape($r['unit'])?><?=supplyRollLines($payload,(string)$r['sku'])?>
            <?php if (!empty($d['change_flags'])): ?><div class="sp-reason" style="margin-top:6px">Cambio: <?=supplyEscape(supplyChangeLabel($d['change_flags']))?><br>Motivo de bodega: <?=supplyEscape($d['change_reason'])?></div><?php endif; ?>
            <?php if (!empty($d['receive_comment'])): ?><div style="margin-top:6px;font-size:12px"><b>Comentario del operador:</b> <?=supplyEscape($d['receive_comment'])?></div><?php endif; ?></td>
        <td><?=supplyEscape(supplyActorLabel($d['delivered_by']))?><br><?=supplyDate($d['delivered_at'])?></td>
        <td><?=supplyEscape(supplyActorLabel($d['received_by']))?><br><?=supplyDate($d['received_at'])?></td>
        <td><?=supplyEscape(['PENDING'=>'Pendiente de resolver','DISPATCHED'=>'Por recibir','RECEIVED'=>'Recibido','CANCELLED'=>'Cancelado'][$d['state']]??$d['state'])?>
        <?php if ($d['error_message']): ?><p style="color:#991b1b"><?=supplyEscape($d['error_message'])?></p><?php endif; ?>
        <?php if (($warehouse && $d['state']==='PENDING') || (!$warehouse && $d['state']==='DISPATCHED')): ?>
        <form method="post" action="<?=$warehouse?'/stock/material-supply/resume':'/production/materials/receive'?>">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="dispatch_key" value="<?=supplyEscape($d['dispatch_key'])?>">
            <input type="hidden" name="agid" value="<?=$agId?>">
            <?php if (!$warehouse): $hasChange = !empty($d['change_flags']); ?>
                <?php if ($hasChange): ?>
                <div class="sp-reason">Lo que llegó es distinto a lo que pediste (<?=supplyEscape(supplyChangeLabel($d['change_flags']))?>). Revisa las bobinas y deja tu comentario.</div>
                <label>Comentario obligatorio: acepto el cambio y está dentro de lo que necesito para producir</label>
                <textarea name="receive_comment" required minlength="10" maxlength="500" rows="3" style="width:100%" placeholder="Ej.: acepto las bobinas de reemplazo; el color y ancho sirven para esta OT"></textarea>
                <?php endif; ?>
                <label><input type="checkbox" name="receipt_confirmed" value="1" required> <?=$hasChange?'Acepto el cambio: lo recibido sirve para producir.':'Confirmo que recibí esta cantidad y los códigos indicados.'?></label>
            <?php endif; ?>
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
</td></tr>
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
        // Cajas y productos de la 400 con etiquetas de trazabilidad se traspasan escaneando sus etiquetas.
        $isBoxReq=$service->usesLabelScan($r);
        $isRoll=$isBoxReq || str_starts_with(strtoupper($r['sku']),'TEL'); $reqQty=(float)$r['requested_qty']; $scans=$isRoll?$service->scans($r['request_key']):[];
        $scanTotal=0.0; $hasSubstitute=false;
        foreach ($scans as $sc) { if ($sc['state']==='SCANNED') { $scanTotal+=(float)$sc['quantity']; if (!empty($sc['substitute'])) $hasSubstitute=true; } }
        $needReason=$hasSubstitute || (!$isBoxReq && $isRoll && $scanTotal>0 && abs($scanTotal-$reqQty)>0.000001); ?>
    <dialog id="supply_dispatch_<?=$i?>" class="supply-dialog">
        <h2>Preparar y traspasar</h2>
        <b><?=supplyEscape($r['sku'])?></b> <?=supplyEscape($r['description'])?>
        <?php if ($isBoxReq): ?>
        <p>Se solicitaron <b><?=supplyEscape(supplyNum($reqQty))?> <?=supplyEscape($r['unit'])?></b> desde la Bodega <?=supplyWarehouseLabel((int)$r['source_storehouse_id'])?>. Escanea la etiqueta de trazabilidad de cada caja que envías. Al traspasar se descuenta del origen, se carga en la 3000 Insumos en Producción y se genera el traspaso ERP.</p>
        <?php else: ?>
        <p>Se solicitaron <b><?=supplyEscape($r['requested_qty'])?> <?=supplyEscape($r['unit'])?></b> desde la Bodega <?=supplyWarehouseLabel((int)$r['source_storehouse_id'])?>. Puedes enviar otra bobina compatible (otro código o metros distintos) o más unidades; en ese caso debes escribir el motivo. Al despachar se descuenta el stock de origen, se carga en la bodega 3000 Insumos en Producción y se genera el traspaso ERP.</p>
        <?php endif; ?>
        <?php if ($isRoll):
            $existingCodes = [];
            foreach ($scans as $scRow) {
                if (($scRow['state'] ?? '') === 'SCANNED' && !empty($scRow['code'])) {
                    $existingCodes[] = strtoupper((string)$scRow['code']);
                }
            }
            $defaultSlots = $isBoxReq
                ? max(1, min(15, ($reqQty - $scanTotal) > 0.0005 ? 3 : 1))
                : max(1, min(30, (int)ceil(max(0.0, $reqQty - $scanTotal))));
        ?>
        <form method="post" action="/stock/material-supply/scan" autocomplete="off" class="sp-batch-scan-form"
            data-is-box="<?=$isBoxReq?'1':'0'?>" data-req-qty="<?=supplyEscape((string)$reqQty)?>"
            data-scan-total="<?=supplyEscape((string)$scanTotal)?>"
            data-existing="<?=supplyEscape(json_encode($existingCodes))?>">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
            <label><?php if ($isBoxReq): ?>Carga por casillas de etiquetas de cajas (<?=supplyEscape(supplyNum($scanTotal))?> ya cargadas · se pidieron <?=supplyEscape(supplyNum($reqQty))?>). Si se pasan de lo pedido, abajo eliges qué caja se queda con el saldo.<?php else: ?>Carga por casillas de códigos de trazabilidad de bobina (<?=supplyEscape(supplyNum($scanTotal))?> ya cargadas · se solicitaron <?=supplyEscape(supplyNum($reqQty))?>)<?php endif; ?></label>
            <div class="sp-slot-box">
                <div class="sp-slot-bar">
                    <label>Cantidad de casillas:</label>
                    <input type="number" class="sp-slot-count-inp" min="1" max="100" value="<?=$defaultSlots?>">
                    <button type="button" class="btngrey" onclick="spApplySlotCount(this)">Generar casillas</button>
                    <button type="button" class="btngrey" onclick="spAddSlotRow(this)">+ Agregar casilla</button>
                    <button type="button" class="btngrey" onclick="spCleanEmptySlots(this)">Limpiar vacías</button>
                    <span class="sp-slot-summary">0 de <?=$defaultSlots?> casilla(s) con código</span>
                </div>
                <div class="sp-slot-help">💡 Escanea rápido sin esperar: cada lectura llena su casilla y pasa automáticamente a la siguiente sin recargar la página. Al finalizar, presiona el botón para cargar todos los códigos de una sola vez.</div>
                <div class="sp-slot-list">
                    <?php for ($slotIdx = 1; $slotIdx <= $defaultSlots; $slotIdx++): ?>
                    <div class="sp-slot-row">
                        <span class="sp-slot-idx">#<?=$slotIdx?></span>
                        <input type="text" name="codes[]" class="sp-slot-input" maxlength="240" autocomplete="off" <?=$slotIdx===1?'autofocus':''?> placeholder="Escanea o escribe el código #<?=$slotIdx?>">
                        <span class="sp-slot-tag"></span>
                        <button type="button" class="sp-slot-rm" onclick="spClearOrRemoveSlot(this)" title="Limpiar o quitar casilla">✕</button>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="supply-actions"><button type="submit" class="btnblue sp-scan-submit-btn" disabled>📥 Cargar todos los códigos a la lista</button></div>
        </form>
        <?php if ($scans): ?>
        <div style="overflow:auto"><table class="supply-table"><thead><tr><th>Código</th><th>Producto</th><th><?=$isBoxReq?'Cantidad':'Metros'?></th><th>Escaneado por</th><th>Fecha</th><th>Estado</th><th></th></tr></thead><tbody>
        <?php foreach ($scans as $s): ?>
        <tr><td><b><?=supplyEscape($s['code'])?></b></td>
            <td><?=supplyEscape($s['sku']??'')?><?php if (!empty($s['substitute'])): ?> <span style="background:#fef3c7;color:#92400e;border-radius:8px;padding:1px 7px;font-weight:700;font-size:12px">Reemplazo</span><?php endif; ?></td>
            <td><?php if ($isBoxReq): ?><b><?=supplyEscape(supplyNum((float)$s['quantity']))?></b><?php else: ?><?=!empty($s['meters'])?supplyEscape(supplyNum((float)$s['meters'])).' m':'-'?><?php endif; ?></td>
            <td><?=supplyEscape(supplyActorLabel($s['scanned_by']))?></td><td><?=supplyDate($s['scanned_at'])?></td>
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
        <form method="post" action="/stock/material-supply/dispatch" class="sp-dispatch-form">
            <input type="hidden" name="_csrf" value="<?=supplyEscape(csrfToken())?>">
            <input type="hidden" name="request_key" value="<?=supplyEscape($r['request_key'])?>">
            <input type="hidden" name="dispatch_key" value="<?=bin2hex(random_bytes(16))?>">
            <input type="hidden" name="codes" value="">
            <?php if (!$isRoll): ?>
            <label>Cantidad a entregar (mínimo lo solicitado: <?=supplyEscape(supplyNum($reqQty))?>)</label>
            <input type="number" name="quantity" min="<?=supplyEscape($reqQty)?>" step="any" value="<?=supplyEscape($r['requested_qty'])?>" required oninput="var n=parseFloat(this.value),m=<?=json_encode($reqQty)?>,t=this.form.querySelector('textarea[name=change_reason]');if(t){t.required=(n!==m);}">
            <?php endif; ?>
            <?php if ($isBoxReq && $scanTotal > $reqQty + 0.000001):
                $excess = round($scanTotal - $reqQty, 3); ?>
            <label style="margin-top:8px">¿Qué caja se queda con el saldo? (sobran <?=supplyEscape(supplyNum($excess))?> de lo pedido)</label>
            <select name="saldo_code" style="width:100%">
                <option value="">Ninguna: se traspasan todas las cajas completas</option>
                <?php foreach ($scans as $sc): if ($sc['state']!=='SCANNED' || (float)$sc['quantity'] <= $excess + 0.0005) continue; ?>
                <option value="<?=supplyEscape($sc['code'])?>"><?=supplyEscape($sc['code'])?>: tiene <?=supplyEscape(supplyNum((float)$sc['quantity']))?>, salen <?=supplyEscape(supplyNum((float)$sc['quantity'] - $excess))?> y queda con <?=supplyEscape(supplyNum($excess))?> en la bodega</option>
                <?php endforeach; ?>
            </select>
            <div style="font-size:12px;color:#475569;margin-top:4px">Lo que sale de esa caja llega a la 3000 con un código temporal y queda cargado en el Pulpo (no se imprime ni se escanea).</div>
            <?php endif; ?>
            <label style="margin-top:8px">Motivo del cambio <?=$needReason?'(obligatorio: envías algo distinto a lo solicitado)':'(solo si envías otro código, otros metros o más unidades)'?></label>
            <textarea name="change_reason" rows="2" maxlength="500" style="width:100%" <?=$needReason?'required minlength="10"':''?> placeholder="Ej.: la bobina pedida no tiene stock físico; se envía una de otros metros"></textarea>
            <div class="supply-actions"><button class="btngreen sp-dispatch-submit-btn" <?=($isRoll && $scanTotal+0.000001<$reqQty)?'disabled title="'.($isBoxReq?'Faltan cajas por escanear':'Faltan bobinas por escanear').'"':''?>>Traspasar a 3000 Insumos en Producción</button></div>
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
                    .'if(d&&d.showModal){d.showModal();var f=d.querySelector(".sp-slot-input")||d.querySelector("input[name=code]");if(f)f.focus();}});</script>';
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
    <tr><td><b><?=supplyEscape($row['sku'])?></b><br><?=supplyEscape($row['description'])?></td><td><?=supplyWarehouseLabel((int)$row['source_storehouse_id'])?> → 3000 Insumos en Producción</td><td><?=(int)$row['dispatches']?></td><td><?=supplyEscape($row['quantity'])?> <?=supplyEscape($row['unit'])?></td></tr>
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
    <h4 style="margin-top:20px">Despachos con cambio (otro código, otra cantidad o metros distintos)</h4>
    <div style="overflow:auto"><table class="supply-table"><thead><tr><th>Solicitud</th><th>Pedido</th><th>Cantidad enviada</th><th>Cambio</th><th>Motivo de bodega</th><th>Comentario del operador</th></tr></thead><tbody>
    <?php foreach ($rep['changes'] as $row): ?>
    <tr><td><?=supplyEscape(substr($row['request_key'],0,8))?></td><td><?=supplyEscape($row['requested_sku'])?></td><td><?=supplyEscape($row['quantity'])?></td>
        <td><?=supplyEscape(supplyChangeLabel($row['change_flags']))?></td><td><?=supplyEscape($row['change_reason'])?></td>
        <td><?=$row['receive_comment']!==null&&$row['receive_comment']!==''?supplyEscape($row['receive_comment']):($row['state']==='RECEIVED'?'-':'Pendiente de recepción')?></td></tr>
    <?php endforeach; if (!$rep['changes']): ?><tr><td colspan="6">Sin cambios en el periodo.</td></tr><?php endif; ?>
    </tbody></table></div>
    </div>
    <?php
}

