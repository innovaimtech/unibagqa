<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');

$pdo = Db::erpPdo();
$agId = 487;

$times = [];
function markTime($label, &$times, &$last) {
    $now = microtime(true);
    $times[$label] = round(($now - $last) * 1000, 2);
    $last = $now;
}

$last = microtime(true);

// 1. Agenda query
$sqlAgenda = "SELECT DISTINCT t0.*,
               t1.req_number, t1.req_production_initdate, t1.req_status, t2.company_short,
               t3.shop_name, t4.cust_name, t1.req_hash, t2x.item_number_prod, t2x.item_title,
               t1x.item_amount, t3x.prd_number, t1x.fab_printtype, t1x.fab_type, t3x.id AS prdid,
               t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle, t1x.fab_print_width,
               t1x.fab_print_height, v1.add_name AS fabric_color, v2.add_name AS manilla_color,
               fab_print_colors_front_1, fab_print_colors_front_2, fab_print_colors_front_3, fab_print_colors_front_4, fab_print_colors_front_5,
               fab_print_colors_front_6, fab_print_colors_front_7, fab_print_colors_front_8, fab_print_colors_front_9, fab_print_colors_front_10,
               fab_print_colors_back_1, fab_print_colors_back_2, fab_print_colors_back_3, fab_print_colors_back_4, fab_print_colors_back_5,
               fab_print_colors_back_6, fab_print_colors_back_7, fab_print_colors_back_8, fab_print_colors_back_9, fab_print_colors_back_10,
               fab_print_colordesc_1, fab_print_colordesc_2, fab_print_colordesc_3, fab_print_colordesc_4, fab_print_colordesc_5,
               fab_print_colordesc_6, fab_print_colordesc_7, fab_print_colordesc_8, fab_print_colordesc_9, fab_print_colordesc_10,
               t0.ag_amount, t1.req_cliche_peli_solic_dat, t1.req_cliche_peli_recep_dat,
               t1.req_prod_adjfile_0, t1.req_prod_adjfile_1, t1.req_prod_adjfile_2, t1.req_prod_adjfile_3, t1.req_prod_adjfile_4,
               t1.req_prod_adjcomments_0, t1.req_prod_adjcomments_1, t1.req_prod_adjcomments_2, t1.req_prod_adjcomments_3, t1.req_prod_adjcomments_4,
               t1x.fab_design_imagehash, t1.req_company_id, t1.req_shop_id, t1x.fab_mat_fabric_color, t1x.fab_mat_manilla_color,
               t1.req_solic_devprints_cc, t1x.fab_design_name, t1x.fab_mat_gramms,
               t1.req_operador_bastidoramt, t1.req_operador_mermaperc, t1.req_operador_tela_width,
               t1.req_solic_devprints_poltype, t1x.fab_manilla_length, t1.req_rebo_type,
               t1.req_rebo_state, t1.req_rebo_rolloscc, t2x.item_prodcalc_fuelle_act, t1.req_rebo_cortescc,
               t1x.item_sellprice_barcodenumber,
               t1.req_infoaddprd_dado_manillas,
               t1.req_infoaddprd_cabezal_act,
               t1.req_infoaddprd_procedencia,
               t1.req_infoaddprd_reversa_act,
               t1.req_infoaddprd_cliche_ubicacion,
               t1.req_infoaddprd_cliche_codigo,
               t1.req_solic_supp_file_0, t1.req_solic_supp_file_1, t1.req_solic_supp_file_2,
               t1.req_solic_supp_file_3, t1.req_solic_supp_file_4, t1.req_solic_supp_name_0,
               t1.req_solic_supp_name_1, t1.req_solic_supp_name_2, t1.req_solic_supp_name_3,
               t1.req_solic_supp_name_4, t1x.fab_mat_dispositivo, tz.descripcion AS dispositivo,
               t1x.item_id, tz2.cat_id,
               t1.req_embalaje_medidas_caja, t1.req_embalaje_cajas_por_pallet_amt,
               t1.req_embalaje_bolsas_por_caja_amt,
               t1.req_caja_impresa, t1.req_embalaje_cajas_completas_amt, t1.req_embalaje_caja_final,
               t1.req_pie_imprenta, t1.req_despacho_desc,
               to1.req_number AS reqnum_mezcla_1, to2.req_number AS reqnum_mezcla_2,
               to3.req_number AS reqnum_mezcla_3, t1.req_rebo_cc_genrefid,
                teq.equipo_name, teq.equipo_prod_isprinter_flexo, teq.equipo_prod_isprinter_seri,
                teq.equipo_type_id, ttype.type_ant_title, ttype.type_createstock_act
        FROM prod_agenda t0
        INNER JOIN prod_header t3x ON t0.ag_prdid = t3x.id AND t3x.prd_status >= 2
        INNER JOIN orders t1 ON t0.ag_reqid = t1.id
        LEFT OUTER JOIN company_data t2 ON t1.req_company_id = t2.id
        LEFT OUTER JOIN company_shops t3 ON t1.req_shop_id = t3.id
        LEFT OUTER JOIN customer t4 ON t1.req_cust_id = t4.id
        INNER JOIN orders_items t1x ON t1.id = t1x.req_id
        INNER JOIN item t2x ON t1x.item_id = t2x.id
        LEFT OUTER JOIN tran_comments_vals v1 ON t1x.fab_mat_fabric_color = v1.id
        LEFT OUTER JOIN tran_comments_vals v2 ON t1x.fab_mat_manilla_color = v2.id
        LEFT OUTER JOIN parametros tz ON t1x.fab_mat_dispositivo = tz.codigo AND tz.tabla = 'DISPOSITIVO'
        LEFT OUTER JOIN item_productcats tz2 ON t1x.item_id = tz2.item_id
        LEFT OUTER JOIN productcats tz3 ON tz2.cat_id = tz3.id
        LEFT OUTER JOIN orders to1 ON t1.req_id_cc_m1 = to1.id
        LEFT OUTER JOIN orders to2 ON t1.req_id_cc_m2 = to2.id
        LEFT OUTER JOIN orders to3 ON t1.req_id_cc_m3 = to3.id
         LEFT OUTER JOIN equipo teq ON t0.ag_equipo_id = teq.id
         LEFT OUTER JOIN equipo_type ttype ON teq.equipo_type_id = ttype.id
        WHERE t0.id = :ag_id
        LIMIT 1";
$stmt = $pdo->prepare($sqlAgenda);
$stmt->execute([':ag_id' => $agId]);
$agendaData = $stmt->fetch();
markTime("Agenda SQL", $times, $last);

// 2. tran_comments
$stmtCom = $pdo->prepare(
    "SELECT t1.com_id, t2.add_name
     FROM tran_comments_item_vals t1
     INNER JOIN tran_comments_vals t2 ON t1.val_id = t2.id
     WHERE t1.item_id = :item_id"
);
$stmtCom->execute([':item_id' => (int)($agendaData['item_id'] ?? 0)]);
$comvals = $stmtCom->fetchAll(PDO::FETCH_KEY_PAIR);
markTime("tran_comments", $times, $last);

// 3. equipo_params
$stmtEq = $pdo->prepare(
    "SELECT * FROM equipo_params
     WHERE param_equipo_id = 33
       AND param_medida >= 40
     ORDER BY param_medida ASC
     LIMIT 1"
);
$stmtEq->execute();
$eq = $stmtEq->fetch();
markTime("equipo_params", $times, $last);

// 4. prod_amtplan
$stmtEnt = $pdo->prepare(
    "SELECT * FROM prod_amtplan
     WHERE prodplan_prdid = :prdid
     ORDER BY prodplan_date ASC"
);
$stmtEnt->execute([':prdid' => (int)($agendaData['prdid'] ?? 0)]);
$entregas = $stmtEnt->fetchAll();
markTime("prod_amtplan", $times, $last);

// 5. prod_worker_ot_events totals
$stmtTot = $pdo->prepare(
    "SELECT SUM(evt_amount) AS tot_amt
     FROM prod_worker_ot_events
     WHERE evt_prod_worker_otid = 1000191 AND evt_type = 'prod' AND evt_status > 0"
);
$stmtTot->execute();
$tot = $stmtTot->fetch();
markTime("ot_events tot", $times, $last);

// 6. Agenda totals
$stmtAgTot = $pdo->prepare(
    "SELECT SUM(t3.evt_amount) AS tot_all
     FROM prod_worker_ot t2
     INNER JOIN prod_worker_ot_events t3 ON t3.evt_prod_worker_otid = t2.id
     WHERE t2.wok_ag_id = :ag_id AND t3.evt_type = 'prod' AND t3.evt_status > 0"
);
$stmtAgTot->execute([':ag_id' => $agId]);
$totAg = $stmtAgTot->fetch();
markTime("ag_events tot", $times, $last);

// 7. Anilox context
require_once __DIR__ . '/src/ProductionService.php';
$prod = new ProductionService($pdo, Db::trzPdo());
$an = $prod->getAniloxContext($agId, 4622, 33, 4075);
markTime("getAniloxContext", $times, $last);

// 8. Rebo order queries
$stmtRO = $pdo->prepare("SELECT * FROM orders WHERE id = 4622 LIMIT 1");
$stmtRO->execute();
$ro = $stmtRO->fetch();
markTime("orders rebo", $times, $last);

$stmtRV = $pdo->prepare("SELECT * FROM orders_classify_rebo_values WHERE req_id = 4622 ORDER BY id ASC");
$stmtRV->execute();
$rv = $stmtRV->fetchAll();
markTime("rebo_values", $times, $last);

$stmtSP = $pdo->prepare("SELECT * FROM orders_classify_subproducts WHERE req_id = 4622 ORDER BY sub_pos ASC");
$stmtSP->execute();
$sp = $stmtSP->fetchAll();
markTime("rebo_subproducts", $times, $last);

print_r($times);
