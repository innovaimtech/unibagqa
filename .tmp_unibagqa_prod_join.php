<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=unibagqa;charset=utf8mb4', 'root', 'AY2309', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sql = "SELECT pwo.id AS worker_ot_id, pwo.wok_status, pwo.wok_crtdat, pwo.wok_enddat,
               pwi.id AS init_id, pwi.win_wrkid, pwi.win_equipoid, pwi.win_status, pwi.win_crtdat, pwi.win_enddat,
               w.wrk_firstname, w.wrk_lastname, w.wrk_uid,
               u.id AS user_id, u.user_login, u.user_firstname, u.user_lastname,
               pa.id AS agenda_id, pa.ag_date, pa.ag_equipo_id, pa.ag_equipotype_id, pa.ag_amount, pa.ag_prdid, pa.ag_reqid, pa.ag_status, pa.ag_active,
               ph.id AS header_id, ph.prd_reqid, ph.prd_status, ph.prd_number, ph.prd_plantaid
        FROM prod_worker_ot pwo
        LEFT JOIN prod_worker_init pwi ON pwi.id = pwo.wok_init_id
        LEFT JOIN workers w ON w.id = pwi.win_wrkid
        LEFT JOIN user u ON u.id = w.wrk_uid
        LEFT JOIN prod_agenda pa ON pa.id = pwo.wok_ag_id
        LEFT JOIN prod_header ph ON ph.id = pa.ag_prdid
        ORDER BY pwo.id DESC
        LIMIT 12";
foreach ($pdo->query($sql) as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
}

echo "===== EVENT TYPES =====\n";
foreach ($pdo->query("SELECT evt_type, COUNT(*) qty FROM prod_worker_ot_events GROUP BY evt_type ORDER BY qty DESC LIMIT 20") as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), PHP_EOL;
}
?>
