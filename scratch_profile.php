<?php
require_once __DIR__ . '/src/Env.php';
require_once __DIR__ . '/src/Db.php';
Env::load(__DIR__ . '/.env');

$pdo = Db::erpPdo();

// Test the queries inside listActiveOrdersInCourse
$t0 = microtime(true);
$sql = "SELECT t1.id AS worker_ot_id, t1.wok_crtdat, t1.wok_status,
               t2.win_equipoid, t7.equipo_name, t8.type_ant_title, t7.equipo_type_id,
               t9.wrk_firstname, t9.wrk_lastname,
               t0.id AS ag_id, t0.ag_amount,
               t3x.prd_number,
               t5.id AS req_id, t5.req_number, t4.cust_name, t2x.item_title, t2x.item_number_prod,
               t5.req_operador_tela_width, t5.req_rebo_type, t5.req_rebo_cc_genrefid,
               t1x.fab_type, t1x.fab_med_width, t1x.fab_med_height, t1x.fab_med_fuelle,
               t1x.fab_printtype, t1x.fab_design_imagehash, t1x.fab_design_name, t1x.item_img_hash
        FROM prod_worker_ot t1
        INNER JOIN prod_worker_init t2 ON t1.wok_init_id = t2.id
        LEFT OUTER JOIN equipo t7 ON t2.win_equipoid = t7.id
        LEFT OUTER JOIN equipo_type t8 ON t7.equipo_type_id = t8.id
        LEFT OUTER JOIN workers t9 ON t2.win_wrkid = t9.id
        INNER JOIN prod_agenda t0 ON t1.wok_ag_id = t0.id
        INNER JOIN prod_header t3x ON t0.ag_prdid = t3x.id
        INNER JOIN orders t5 ON t0.ag_reqid = t5.id
        LEFT OUTER JOIN customer t4 ON t5.req_cust_id = t4.id
        LEFT OUTER JOIN orders_items t1x ON t5.id = t1x.req_id
        LEFT OUTER JOIN item t2x ON t1x.item_id = t2x.id
        WHERE t1.wok_status = 1
        ORDER BY t1.wok_crtdat DESC";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();
$t1 = microtime(true);
echo "Main query in listActiveOrders: " . round(($t1 - $t0) * 1000, 2) . " ms (rows: " . count($rows) . ")\n";

// Now test getWorkOrderOperatingContext queries
$t0 = microtime(true);
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
        WHERE t0.id = 487
        LIMIT 1";
$stmt2 = $pdo->query($sqlAgenda);
$row2 = $stmt2->fetch();
$t1 = microtime(true);
echo "Agenda query in getWorkOrderOperatingContext: " . round(($t1 - $t0) * 1000, 2) . " ms\n";
