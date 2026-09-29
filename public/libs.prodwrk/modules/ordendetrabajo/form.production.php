<?php
$_REQUEST["agid"] = (int)$_REQUEST["agid"];
$_REQUEST["overrideembalaje"] = (int)$_REQUEST["overrideembalaje"];

if((int)$_REQUEST["resetv2"] && (int)$_REQUEST["refid"])
{
   $sql = " update prod_worker_ot_events
            set
            evt_enddat = 0,
            evt_status = 1
            where
            id = {$_REQUEST["refid"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["overrideembalaje"])
{
   $hasopeninit["type_ant_title"] = "EMBALAJE";
   $hasopeninit["equipo_name"] = "EMBALAJE";
   $hasopeninit["equipo_type_id"] = 15;
   $hasopeninit["win_equipoid"] = 25;
   $hasopeninit["type_createstock_act"] = 1;
}

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "production" && $_REQUEST["overrideembalaje"] && (int)$_REQUEST["overrideembalajesaveend"] && (int)$_REQUEST["refid"])
{
   // echo "<pre>";
   // print_r($_REQUEST);
   // exit;

   $_REQUEST["axx_username"]  = trim(addslashes($_REQUEST["axx_username"]));
   $_REQUEST["axx_pass"]      = md5(trim($_REQUEST["axx_pass"]));


   $_REQUEST["prod_amount"]         = (int)trim($_REQUEST["prod_amount"]);

   $sql = " select t1.*
            from user t1
            where
            t1.user_login  = '{$_REQUEST["axx_username"]}' and
            t1.user_pass   = '{$_REQUEST["axx_pass"]}' and
            t1.user_status = 1 and
            (
               t1.user_type = 1 or
               (
                  select count(*) 'cc'
                  from user_group t2
                  where
                  t2.user_id  = t1.id and
                  t2.group_id IN ({$_CONFIG["_PROD_SUPERVISOR_ROLEID"]})
               ) > 0
            ) ";
   $checkuser = $CON->select($sql);
   $checkuser = $checkuser[0];

   if((int)$checkuser["id"])
   {
      $sql = " select *
               from prod_worker_ot_events
               where
               id = {$_REQUEST["refid"]}";
      $refevent = $CON->select($sql);
      $refevent = $refevent[0];

      $currtme = time();

      $sql_day    = (int)date("d");
      $sql_month  = (int)date("m");
      $sql_year   = (int)date("Y");

      $sql = " insert into prod_worker_init
               (win_crtdat, win_wrkid, win_status, win_plantaid, win_equipoid,
                win_ass_id, win_day, win_month, win_year, win_enddat)
               VALUES
               ({$currtme}, {$_SESSION["wrk_id"]}, 2, {$_SESSION["user_planta_id"]}, {$hasopeninit["win_equipoid"]},
                0, {$sql_day}, {$sql_month}, {$sql_year}, {$currtme})";
      $res = $CON->no_result($sql);
      if($res)
      {
         $prod_worker_init_id = mysql_insert_id();

         $ag_date = date("d.m.Y");
         $ag_date_stamp = time();
         $sql = " insert into prod_agenda
                  (ag_date, ag_date_stamp, ag_equipo_id, ag_equipotype_id, ag_amount, ag_prdid, ag_reqid,
                   ag_plantaid, ag_crtdat, ag_crtusr, ag_status, ag_active)
                  VALUES
                  ('{$ag_date}', {$ag_date_stamp}, {$hasopeninit["win_equipoid"]}, {$hasopeninit["equipo_type_id"]},
                   {$_REQUEST["prod_amount"]}, {$agenda["ag_prdid"]}, {$agenda["ag_reqid"]}, {$agenda["ag_plantaid"]},
                   {$currtme}, 0, 1, 0)";
         $res = $CON->no_result($sql);
         if($res)
         {
            $prod_agenda_id = mysql_insert_id();

            $sql = " insert into prod_worker_ot
                     (wok_ag_id, wok_init_id, wok_crtdat, wok_enddat, wok_status)
                     VALUES
                     ({$prod_agenda_id}, {$prod_worker_init_id}, {$refevent["evt_crtdat"]}, {$currtme}, 2)";
            $res = $CON->no_result($sql);

            if($res)
            {
               $prod_worker_ot_id = mysql_insert_id();

               $sql = " update prod_worker_ot_events
                        set
                        overrideembalaje_selladora_evtrefid = {$hasopenot["id"]},
                        evt_prod_worker_otid = {$prod_worker_ot_id},
                        evt_enddat           = {$currtme},
                        evt_status           = 2,
                        evt_amount           = {$_REQUEST["prod_amount"]}
                        where
                        id = {$_REQUEST["refid"]}";
               $CON->no_result($sql);

               $sql = " delete from prod_worker_embalajes
                        where
                        evt_prod_worker_otid = {$hasopenot["id"]} and
                        evt_reqid            = {$hasopenot["prd_reqid"]}";
               $CON->no_result($sql);



               //----------------------------------------------------------------------------------
               // COPY FROM fancy.embalaje.termot.php
               //----------------------------------------------------------------------------------
               $sql = " select *
                        from orders
                        where
                        id = {$hasopenot["prd_reqid"]}";
               $headdata = $CON->select($sql);
               $headdata = $headdata[0];

                /* Clasificacion de cierres */

                $sw_cierre = 1;

                $sql = " select * from orders where id = {$hasopenot["prd_reqid"]} ";
                $ord = $CON->select($sql);
                $ord = $ord[0];



                if( (int)$ord["req_id_cc_m1"] || (int)$ord["req_id_cc_m2"] || (int)$ord["req_id_cc_m3"] )
                {
                  $sql = " select * from orders where id = {$hasopenot["prd_reqid"]}
                              union
                           select * from orders where id = ifnull({$ord["req_id_cc_m1"]},0)
                              union
                           select * from orders where id = ifnull({$ord["req_id_cc_m2"]},0)
                              union
                           select * from orders where id = ifnull({$ord["req_id_cc_m3"]},0) ";
                  $detalle = $CON->select($sql);
                  $sw_cierre = 2;

                }

                $sql = "select st.id
                             , st.st_name
                             , i1.item_number_prod
                             , ifnull(iss.iss_inventory,0) as stock
                             , i1.id as id_item
                             , o.id as id_orders
                           from orders o
                              inner join orders_items oi on oi.req_id = o.id
                              inner join item i1 on i1.id = oi.item_id
                              inner join customer c on c.id = o.req_cust_id
                              inner join bodegacanal bc on c.cust_canal = bc.bodegacanal_codigo_canal
                              inner join company_shops_storehouses st on st.id = bc.bodegacanal_id_bodega
                              LEFT OUTER JOIN item_shops_storehouses iss on iss.item_id = i1.id and iss.iss_order_id = o.id and iss.st_id = st.id
                        where o.id in({$ord["req_id_cc_m1"]},{$ord["req_id_cc_m2"]},{$ord["req_id_cc_m3"]}) ";
               $t_bodegas = $CON->select($sql);


               $currtme = time();
               $_REQUEST["req_embalaje_medidas_caja"]                = trim(addslashes($_REQUEST["req_embalaje_medidas_caja"]));
               $_REQUEST["req_embalaje_bolsas_por_caja_amt"]         = (int)trim($_REQUEST["req_embalaje_bolsas_por_caja_amt"]);
               $_REQUEST["req_embalaje_cajas_por_pallet_amt"]        = (int)trim($_REQUEST["req_embalaje_cajas_por_pallet_amt"]);
               $_REQUEST["req_embalaje_pallets_completos_amt"]       = (int)trim($_REQUEST["req_embalaje_pallets_completos_amt"]);
               $_REQUEST["req_embalaje_palletcajas_incompletos_amt"] = (int)trim($_REQUEST["req_embalaje_palletcajas_incompletos_amt"]);
               $_REQUEST["req_embalaje_cajas_completas_amt"]         = (int)trim($_REQUEST["req_embalaje_cajas_completas_amt"]);
               $_REQUEST["req_embalaje_caja_final"]                  = (int)trim($_REQUEST["req_embalaje_caja_final"]);
               $_REQUEST["req_embalaje_bolsas_sobrantes_amt"]        = (int)trim($_REQUEST["req_embalaje_bolsas_sobrantes_amt"]);
               $_REQUEST["req_id_bodega"]                            = (int)trim($_REQUEST["req_id_bodega"]);
               $_REQUEST["req_embalaje_showroom"]                    = (int)trim($_REQUEST["req_embalaje_showroom"]);
               $_REQUEST["req_codigo_cierre"]                        = trim(addslashes($_REQUEST["req_codigo_cierre"]));
               $_REQUEST["req_observacion"]                          = trim(addslashes($_REQUEST["req_observacion"]));

               $_REQUEST["prodeventid"]  = (int)$_REQUEST["prodeventid"];
               $_REQUEST["prodeventamt"] = (int)$_REQUEST["prodeventamt"];


               $sql = " insert into prod_worker_embalajes
                        (evt_prod_worker_otid, evt_reqid, evt_crtdat, req_embalaje_medidas_caja, req_embalaje_bolsas_por_caja_amt,
                         req_embalaje_cajas_por_pallet_amt, req_embalaje_pallets_completos_amt, req_embalaje_palletcajas_incompletos_amt,
                         req_embalaje_cajas_completas_amt, req_embalaje_caja_final, req_embalaje_bolsas_sobrantes_amt, req_id_bodega,req_embalaje_showroom,
                         req_codigo_cierre, req_id_supervisor,req_observacion)
                        VALUES
                        ({$prod_worker_ot_id}, {$hasopenot["prd_reqid"]}, {$currtme}, '{$_REQUEST["req_embalaje_medidas_caja"]}',
                         {$_REQUEST["req_embalaje_bolsas_por_caja_amt"]}, {$_REQUEST["req_embalaje_cajas_por_pallet_amt"]},
                         {$_REQUEST["req_embalaje_pallets_completos_amt"]}, {$_REQUEST["req_embalaje_palletcajas_incompletos_amt"]},
                         {$_REQUEST["req_embalaje_cajas_completas_amt"]}, {$_REQUEST["req_embalaje_caja_final"]},
                         {$_REQUEST["req_embalaje_bolsas_sobrantes_amt"]}, {$_REQUEST["req_id_bodega"]},
                         {$_REQUEST["req_embalaje_showroom"]},{$_REQUEST["req_codigo_cierre"]},{$checkuser["id"]},'{$_REQUEST["req_observacion"]}')";
               $resp = $CON->no_result($sql);
               if($resp)
                  $sql_idembalaje = mysql_insert_id();

               if( $sw_cierre == 2 )
               {
                  $sql = " insert into embalaje_mixto(id_embalaje, cantidad_cajas, total_bolsa) value({$sql_idembalaje},0,0) ";
                  $reso = $CON->no_result($sql);
                  if($reso)
                     $sql_id = mysql_insert_id();

                  $sw_cabecera = 0;
                  $poscounter  = 0;

                  $_REQUEST["req_embalaje_medidas_caja"]                = trim(addslashes($_REQUEST["req_embalaje_medidas_caja"]));
                  $_REQUEST["req_embalaje_bolsas_por_caja_amt"]         = (int)trim($_REQUEST["req_embalaje_bolsas_por_caja_amt"]);
                  $_REQUEST["req_embalaje_cajas_por_pallet_amt"]        = (int)trim($_REQUEST["req_embalaje_cajas_por_pallet_amt"]);
                  $_REQUEST["req_embalaje_pallets_completos_amt"]       = (int)trim($_REQUEST["req_embalaje_pallets_completos_amt"]);
                  $_REQUEST["req_embalaje_palletcajas_incompletos_amt"] = (int)trim($_REQUEST["req_embalaje_palletcajas_incompletos_amt"]);
                  $_REQUEST["req_embalaje_cajas_completas_amt"]         = (int)trim($_REQUEST["req_embalaje_cajas_completas_amt"]);
                  $_REQUEST["req_embalaje_caja_final"]                  = (int)trim($_REQUEST["req_embalaje_caja_final"]);
                  $_REQUEST["req_embalaje_bolsas_sobrantes_amt"]        = (int)trim($_REQUEST["req_embalaje_bolsas_sobrantes_amt"]);
                  $_REQUEST["req_id_bodega"]                            = (int)trim($_REQUEST["req_id_bodega"]);
                  $_REQUEST["req_embalaje_showroom"]                    = (int)trim($_REQUEST["req_embalaje_showroom"]);
                  $_REQUEST["req_codigo_cierre"]                        = trim(addslashes($_REQUEST["req_codigo_cierre"]));
                  $_REQUEST["req_observacion"]                          = trim(addslashes($_REQUEST["req_observacion"]));

                  $totalxCaja  = 0;
                  $totalxCaja  = ($_REQUEST["req_embalaje_cajas_completas_amt"] * $_REQUEST["req_embalaje_bolsas_por_caja_amt"]) + $_REQUEST["req_embalaje_caja_final"] + $_REQUEST["req_embalaje_bolsas_sobrantes_amt"];

                  $sql = " insert into detalle_mixto(id_mixto,
                                                         bolsas_por_caja,
                                                         bolsas_caja_final,
                                                         bolsas_sobrante,
                                                         req_number,
                                                         id_item,
                                                         total_cajas,
                                                         total_bolsas)
                                             value({$sql_id},
                                                   {$_REQUEST["req_embalaje_bolsas_por_caja_amt"]},
                                                   {$_REQUEST["req_embalaje_caja_final"]},
                                                   {$_REQUEST["req_embalaje_bolsas_sobrantes_amt"]},
                                                   '{$headdata["req_number"]}',
                                                   {$t_bodegas[0]["id_item"]},
                                                   {$_REQUEST["req_embalaje_cajas_completas_amt"]},
                                                   {$totalxCaja}
                                                   ) ";
                  $CON->no_result($sql);

                  for($x = 0; $x < count($detalle) && $detalle != false; $x++)
                  {
                     if($detalle[$x]["id"] != $hasopenot["prd_reqid"])
                     {
                        $_REQUEST["total_cajas_".$x]       = (int)getPrice($_REQUEST["total_cajas_".$x]);
                        $_REQUEST["bolsas_por_caja_".$x]   = (int)getPrice($_REQUEST["bolsas_por_caja_".$x]);
                        $_REQUEST["bolsas_caja_final_".$x] = (int)getPrice($_REQUEST["bolsas_caja_final_".$x]);
                        $_REQUEST["bolsas_sobrante_".$x]   = (int)getPrice($_REQUEST["bolsas_sobrante_".$x]);
                        $detalle[$x]["req_number"]         = trim(addslashes($detalle[$x]["req_number"]));
                        $totalxCaja  = (int)(($_REQUEST["total_cajas_".$x] * $_REQUEST["bolsas_por_caja_".$x] ) + $_REQUEST["bolsas_caja_final_".$x] + $_REQUEST["bolsas_sobrante_".$x]);

                        $sql = " insert into detalle_mixto(id_mixto,
                                                         bolsas_por_caja,
                                                         bolsas_caja_final,
                                                         bolsas_sobrante,
                                                         req_number,
                                                         id_item,
                                                         total_cajas,
                                                         total_bolsas)
                                             value({$sql_id},
                                                   {$_REQUEST["bolsas_por_caja_".$x]},
                                                   {$_REQUEST["bolsas_caja_final_".$x]},
                                                   {$_REQUEST["bolsas_sobrante_".$x]},
                                                   '{$detalle[$x]["req_number"]}',
                                                   {$t_bodegas[0]["id_item"]},
                                                   {$_REQUEST["total_cajas_".$x]},
                                                   {$totalxCaja}
                                                   ) ";
                        $CON->no_result($sql);
                        if($sw_cabecera == 0)
                        {
                           $strcnumber = createTransactionNumber($CON, 20010, "storehouse");
                           $currtme    = time();
                           $sql = " insert into storehousechanges
                                    (strc_company_id
                                    ,strc_shop_id
                                    ,strc_company_dest_id
                                    ,strc_shop_dest_id
                                    ,strc_date
                                    ,strc_number
                                    ,strc_crtusr
                                    ,strc_crtdat
                                    ,strc_order_num
                                    ,strc_order_id
                                    ,strc_order_num_dest
                                    ,strc_order_id_dest
                                    ,strc_status)
                                    VALUES
                                    (20010
                                    ,30010
                                    ,20010
                                    ,30010
                                    ,{$currtme}
                                    ,'{$strcnumber}'
                                    ,{$_SESSION["user_id"]}
                                    ,{$currtme}
                                    ,'{$detalle[$x]["req_number"]}'
                                    ,{$detalle[$x]["id"]}
                                    ,'{$detalle[$x]["req_number"]}'
                                    ,{$detalle[$x]["id"]}
                                    ,2)";
                           $res = $CON->no_result($sql);
                           if($res)
                           {
                              $sql = " select MAX(id) 'id'
                                    from storehousechanges
                                    where
                                    strc_crtusr = {$_SESSION["user_id"]} ";
                              $thisid = $CON->select($sql);
                              $id_bodega = (int)$thisid[0]["id"];
                           }
                           $sw_cabecera = 1;
                        }

                        $itemid        = $t_bodegas[0]["id_item"];
                        $itemtype      = 'item';

                        $sql = " insert into storehousechanges_items
                              (strc_id, item_id, item_pos, item_type, item_amount, item_st_id, item_st_dest_id, item_charges_act)
                              VALUES
                              ({$id_bodega}, {$itemid}, {$poscounter}, '{$itemtype}', {$_REQUEST['total_bolsas_'.$x]},
                              {$_REQUEST['req_id_bodega_'.$x]} , {$_REQUEST['req_id_bodega']}, 0) ";

                        $res = $CON->no_result($sql);
                        if($res)
                        {
                           /* ajuste de bodegas */
                           $sql = "select count(*) as contador from item_shops_storehouses where item_id  = {$itemid} and
                                       shop_id      = 30010 and
                                       st_id        = {$_REQUEST["req_id_bodega"]} and
                                       iss_order_id = {$detalle[$x]["id"]} ";
                           $existe_prod_bodega = $CON->select($sql);
                           $existe_prod_bodega = $existe_prod_bodega[0];

                           if ($existe_prod_bodega["contador"] == 0)
                           {
                                 $sql = "insert into item_shops_storehouses(item_id
                                                                            ,shop_id
                                                                            ,st_id
                                                                            ,iss_order_amount
                                                                            ,iss_inventory_min
                                                                            ,iss_inventory
                                                                            ,iss_inventory_reserved
                                                                            ,iss_order_id)
                                         values({$itemid}
                                               ,30010
                                               ,{$_REQUEST["req_id_bodega"]}
                                               ,0
                                               ,0
                                               ,0
                                               ,0
                                               ,{$detalle[$x]["id"]}) ";
                             $CON->no_result($sql);
                           }

                           $sql = " update item_shops_storehouses
                                    set
                                      iss_inventory = iss_inventory - {$_REQUEST["total_bolsas_".$x]}
                                    where
                                       item_id  = {$itemid} and
                                       shop_id  = 30010 and
                                       st_id    = {$_REQUEST["req_id_bodega_".$x]} and
                                       iss_order_id = {$detalle[$x]["id"]} ";
                           $CON->no_result($sql);

                           $sql = " delete item_shops_storehouses
                                       where item_id  = {$itemid} and
                                             shop_id  = 30010 and
                                             st_id    = {$_REQUEST["req_id_bodega_".$x]} and
                                             iss_order_id = {$detalle[$x]["id"]} and
                                             iss_inventory = 0";
                           $borrar_bodega =  $CON->no_result($sql);

                           $sql = " update item_shops_storehouses
                                    set
                                      iss_inventory = iss_inventory + {$_REQUEST["total_bolsas_".$x]}
                                    where
                                       item_id  = {$itemid} and
                                       shop_id  = 30010 and
                                       st_id    = {$_REQUEST["req_id_bodega"]} and
                                       iss_order_id = {$detalle[$x]["id"]} ";

                           $CON->no_result($sql);
                           /* fin ajuste de bodega */
                        }
                        $poscounter++;
                     }

                  }
               }
            }
         }
      }

      if((int)$prod_worker_ot_id)
      {
         require_once("./libs/functions.pdf.php");
         EnvioCorreo( $CON , $_REQUEST["req_codigo_cierre"], $prod_worker_ot_id);
         createEmbalajeProdStock($CON, $prod_worker_ot_id);
      }
   }
   ?>
   <script language="Javascript">
      location.href = '/prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';
   </script>
   <?php
   exit;
}


// echo "<pre>";
// print_r($agenda);
// print_r($hasopeninit);
//----------------------------------------------------------------------------------
if((int)$_REQUEST["refid"])
{
   $sql = " select *
            from prod_worker_ot_events
            where
            id = {$_REQUEST["refid"]}";
   $refevent = $CON->select($sql);
   $refevent = $refevent[0];
}

//----------------------------------------------------------------------------------
$_FORMMODE        = "productionsave";
$_SERICOLORS      = Array();
$_SERICOLORS_ACT  = false;

// echo "<pre>";
// print_r($agenda);
if($agenda["fab_printtype"] == "SERI" && (int)$hasopeninit["equipo_prod_serimulticolors_act"])
{
   for($x = 1; $x <= 5; $x++)
   {
      if($agenda["fab_print_colordesc_{$x}"] != "")
         $_SERICOLORS[$agenda["fab_print_colordesc_{$x}"]] = 1;
   }
   if(count($_SERICOLORS) > 1)
   {
      $_SERICOLORS_ACT  = true;
      $_FORMMODE        = "productionmulticolorsave";
   }
}

//----------------------------------------------------------------------------------
$_ISSELLADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "SELLADORA") !== false)
   $_ISSELLADORA = true;

$_ISREBOBINADORA = false;
if(strpos(strtoupper($hasopeninit["type_ant_title"]), "REBOBIN") !== false)
   $_ISREBOBINADORA = true;

//----------------------------------------------------------------------------------
$_ISSERIPULPO = false;
if((int)$hasopeninit["equipo_prod_isprinter_seri"] && strpos(strtoupper($hasopeninit["equipo_name"]), "PULPO") !== false)
   $_ISSERIPULPO = true;

//----------------------------------------------------------------------------------
$_EMBALAJE_PROD_CALCINP = false;
if((int)$hasopeninit["type_createstock_act"] && (int)$_REQUEST["refid"])
   $_EMBALAJE_PROD_CALCINP = true;

/* datos para ingresar consumos */


$sql = " select equipo_type_id from equipo where id = {$hasopeninit["win_equipoid"]} ";
$equipo = $CON->select($sql);

$sql = "select i.* , 0 as salida, 0 as entrada from parametros p
   inner join equipo e on e.id = {$hasopeninit["win_equipoid"]} and p.valor1 in(0,e.equipo_type_id)
   inner join item i on p.codigo = i.item_number_prod
where tabla = 'INSUMOS' ";
$materiales = $CON->select($sql);

if(in_array($equipo[0]["equipo_type_id"], [7, 11]))
{
   $sql = " select fab_print_colordesc_1
		            ,fab_print_colordesc_2
		            ,fab_print_colordesc_3
		            ,fab_print_colordesc_4
		            ,fab_print_colordesc_5
		            ,fab_print_colordesc_6
		            ,fab_print_colordesc_7
		            ,fab_print_colordesc_8
		            ,fab_print_colordesc_9
		            ,fab_print_colordesc_10
                  ,0 as entrada_1
                  ,0 as entrada_2
                  ,0 as entrada_3
                  ,0 as entrada_4
                  ,0 as entrada_5
                  ,0 as entrada_6
                  ,0 as entrada_7
                  ,0 as entrada_8
                  ,0 as entrada_9
                  ,0 as entrada_10
                  ,0 as salida_1
                  ,0 as salida_2
                  ,0 as salida_3
                  ,0 as salida_4
                  ,0 as salida_5
                  ,0 as salida_6
                  ,0 as salida_7
                  ,0 as salida_8
                  ,0 as salida_9
                  ,0 as salida_10
            from orders_items 
               where req_id = {$hasopenot["prd_reqid"]} ";
   $colores = $CON->select($sql);
   $colores = $colores[0];
}     

//DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY
// $hasopeninit["equipo_prod_printer_metrotype"] = "";
//DEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLYDEV ONLY
?>
<script language="Javascript">
function prdinpcheck(xform)
{
   /*
   if(xform.sql_metrotype.value == '')
      return checkform(new Array(this.prod_amount<?if($_SERICOLORS_ACT) echo ", this.prod_seri_color";?> <?php if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false) echo ", this.prod_bobina_kg" ?>))
   else if(xform.sql_metrotype.value == 'metros_maquina')
      return checkform(new Array(this.evt_amount_metros_maquina<?php if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false) echo ", this.prod_bobina_kg" ?>));
   else if(xform.sql_metrotype.value == 'metros_lineales')
      return checkform(new Array(this.evt_amount_metros_lineales<?php if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false) echo ", this.prod_bobina_kg" ?>));
   */
   return true;
}
</script>
<form action="prodwrk.php" method="post" name="xform_inp" id="xform_inp"
onsubmit="return prdinpcheck(this)">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="<?=$_FORMMODE?>">
<input type="hidden" name="refid" value="<?=$_REQUEST["refid"]?>">
<input type="hidden" name="submode" value="">
<input type="hidden" name="overrideembalaje" value="<?=$_REQUEST["overrideembalaje"]?>">
<input type="hidden" name="overrideembalajesaveend" value="0">
<input type="hidden" name="reloadact" value="">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<input type="hidden" name="req_codigo_cierre" value="<?$_REQUEST["req_codigo_cierre"]?>">
<input type="hidden" name="idconsumo" value="">
<?php
$visible = "";
if(!(int)$_REQUEST["refid"] || $_ISSERIPULPO || (int)$_REQUEST["overrideembalaje"])
  $visible = "display: none";
?>

<table border="0" width="100%" cellpadding="0" cellspacing="0" style="<?echo $visible?>">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
         </colgroup>
         <tr>
            <td colspan="6" class="tdheader" 
            style="color: white;background-color: #7d9894;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Cargar Insumos</td>
         </tr>
         <tr>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Codigo</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Detalle</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Entrada</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Salida</td>
            <td class="tdheader" style="color: white;background-color: #2dc3b7;border-left:1px solid #DDDDDD;border-top:1px solid #DDDDDD">Consumo</td>
         </tr>
         <?php
         $x = 0;
         foreach($materiales AS $material)
         {  ?>
            <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$material["item_number_prod"]?></td>
               <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$material["item_title"]?></td>
               <td class="tdnrm">
                    <input type="text" class="inptxt" id="entrada_<?=$x?>" name="entrada_<?=$x?>" style="width:100px;text-align:center"
                       value="<?=printPrice($material["entrada"],2)?>">
                    <select class="inptxt" style="" name="unidadd_e_<?=$x?>" id="unidadd_e_<?=$x?>" disabled>
                                 <option value="1">Kilos</option>
                                 <option value="2">Litros</option>
                     </select>
               </td>
               <td class="tdnrm">
                    <input type="text" class="inptxt" id ="salida_<?=$x?>" name="salida_<?=$x?>" style="width:100px;text-align:center"
                           value="<?=printPrice($material["salida"],2)?>">
                    <select class="inptxt" style="" name="unidad_s_<?=$x?>" id="unidad_s_<?=$x?>" disabled> 
                                 <option value="1">Kilos</option>
                                 <option value="2">Litros</option>
                     </select>
               </td>
               <td class="tdnrm">
                  <?php
                        $sql = "select i.id
                                      ,sum(ci.consumo) as consumo
                              from consumos c
                                 inner join consumos_item ci on c.id = ci.consumo_id
                                 inner join item i on i.id = ci.item_id
                                 inner join equipo e on e.id = {$hasopeninit["win_equipoid"]} and maquina_id = e.equipo_type_id
                                 where order_id = {$hasopenot["prd_reqid"]} and ag_id = {$_REQUEST["agid"]}
                                    and i.id = {$material["id"]}
                                    group by i.id" ;
                        $consumo = $CON->select($sql);
                        $consumo = $consumo[0];
                  ?>
                  <input type="text" class="inptxt" name="consumo_x" id="consumo_x"  
                  style="width:100px;background-color:#EEEEEE" value="<?=printPrice($consumo["consumo"],2)?>" readonly>
               </td>
            </tr>
            <?php
            $x++;
         }
         for ($x = 1; $x <= 10; $x++)
         { 
            if($colores["fab_print_colordesc_".$x]!="")
            {
               ?>
               
               <tr onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD">Color # <?=$x?></td>
                  <td class="tdnrm" style="border-left:1px solid #DDDDDD"><?=$colores["fab_print_colordesc_".$x]?></td>
                  <td class="tdnrm">
                     <input type="text" class="inptxt" id="entrada_color<?=$x?>" name="entrada_color<?=$x?>" style="width:100px;text-align:center"
                            value="<?=printPrice($colores["entrada".$x],2)?>"> 
                     <select class="inptxt" style="" name="unidadd_e_<?=$x?>" id="unidadd_e_<?=$x?>" disabled>
                                    <option value="1">Kilos</option>
                                    <option value="2">Litros</option>
                        </select>
                  </td>
                  <td class="tdnrm">
                     <input type="text" class="inptxt" id ="salida_color<?=$x?>" name="salida_color<?=$x?>" style="width:100px;text-align:center" 
                         value="<?=printPrice($colores["salida".$x],2)?>"> 
                     <select class="inptxt" style="" name="unidad_s_<?=$x?>" id="unidad_s_<?=$x?>" disabled>
                                    <option value="1">Kilos</option>
                                    <option value="2">Litros</option>
                        </select>
                  </td>
                  <td class="tdnrm">
                     <?php
                        $sql = " select order_id
                                      ,sum(ci.consumo) as consumo
                                     from consumos c
                                  inner join consumos_color ci on c.id = ci.consumo_id and ci.numero = {$x}
                                  where order_id = {$hasopenot["prd_reqid"]}
                                     group by c.order_id " ;
                        $consumo = $CON->select($sql);
                        $consumo = $consumo[0];
                     ?>
                     <input type="text" class="inptxt" name="consumo_x" id="consumo_x"  
                     style="width:100px;background-color:#EEEEEE" value="<?=printPrice($consumo["consumo"],2)?>" readonly>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
      </table>
   </td>
</tr>
</table>   

<div style="clear:both;height:20px"></div>

<table border="0" width="100%" cellpadding="0" cellspacing="0">
<tr>
   <td style="background-color:#FFFFFF;border:1px solid #EEEEEE;border-radius:5px;padding:20px">
      <table border="0" width="100%" cellpadding="6" cellspacing="0" style="table-layout:fixed">
      <colgroup>
         <col width="150">
         <col>
         <?php
         if((int)$_REQUEST["refid"] && !(int)$refevent["evt_enddat"])
         {  ?>
            <col width="160">
            <?php
         }
         ?>
      </colgroup>
      <tr>
         <td class="tdleft">Tipo</td>
         <td class="tdnrm" colspan="2"><?=$hasopeninit["type_ant_title"]?></td>
         <?php
         ?>
      </tr>
      <tr>
         <td class="tdleft">Máquina</td>
         <td class="tdnrm" colspan="2"><?=$hasopeninit["equipo_name"]?></td>
      </tr>
      <?php
      if((int)$hasopeninit["equipo_prod_isprinter_flexo"])
      {  ?>
         <tr style="display:none">
            <td class="tdleft">Producción</td>
            <td class="tdnrm" colspan="2">
               <input type="text" class="inptxt" name="prod_amount" id="prod_amount"
               value="0">
            </td>
         </tr>
         <?php
         if($hasopeninit["equipo_prod_printer_metrotype"] == "mtrs/máquina")
         {  ?>
            <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
               <td class="tdleft">Producción</td>
               <td class="tdnrm" colspan="2">
                  <input type="hidden" name="sql_metrotype" value="metros_maquina">
                  <input type="text" class="inptxt" name="evt_amount_metros_maquina" id="evt_amount_metros_maquina"
                  value="<?php
                     if(!(int)$refevent["id"])
                        echo "0";
                     elseif($refevent["evt_amount_metros_maquina"] > 0.00)
                        echo printPrice($refevent["evt_amount_metros_maquina"]);
                     ?>">
                  mtrs/máquina
               </td>
            </tr>
            <?php
         }
         else
         {  ?>
            <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
               <td class="tdleft">Producción</td>
               <td class="tdnrm" colspan="2">
                  <input type="hidden" name="sql_metrotype" value="metros_lineales">
                  <input type="text" class="inptxt" name="evt_amount_metros_lineales" id="evt_amount_metros_lineales"
                  value="<?php
                     if(!(int)$refevent["id"])
                        echo "0";
                     elseif($refevent["evt_amount_metros_lineales"] > 0.00)
                        echo printPrice($refevent["evt_amount_metros_lineales"]);
                     ?>">
                  mtrs/lineales
               </td>
            </tr>
            <?php

         }
      }
      else
      {
         if((int)$hasopeninit["equipo_prod_isprinter_seri"])
         {
            if($_ISSERIPULPO)
            {
               $xamt_colors_frente   = 0;
               $xamt_colors_dorso    = 0;
               for($xx = 1; $xx <= 10; $xx++)
               {
                  if((int)$agenda["fab_print_colors_front_{$xx}"])
                     $xamt_colors_frente++;
                  if((int)$agenda["fab_print_colors_back_{$xx}"])
                     $xamt_colors_dorso++;
               }
               ?>
               <tr style="<?if(!(int)$refevent["id"] || !$xamt_colors_frente) echo "display:none"?>">
                  <td class="tdleft">Pasadas frente</td>
                  <td class="tdnrm" colspan="2">
                     <input type="text" id="idx_pulpo_seri_pasadas_inp_frente" name="idx_pulpo_seri_pasadas_inp_frente" class="inptxt" style="width:150px"
                     placeholder="Cantidad"
                     value="<?php
                        if($refevent["evt_amount_pulpo_frente"] > 0.00)
                           echo printPrice($refevent["evt_amount_pulpo_frente"]);
                        ?>">
                     <input type="hidden" id="idx_xamt_colors_frente" name="idx_xamt_colors_frente" value="<?=$xamt_colors_frente?>">
                  </td>
               </tr>
               <tr style="<?if(!(int)$refevent["id"] || !$xamt_colors_dorso) echo "display:none"?>">
                  <td class="tdleft">Pasadas dorso</td>
                  <td class="tdnrm" colspan="2">
                     <input type="text" id="idx_pulpo_seri_pasadas_inp_dorso" name="idx_pulpo_seri_pasadas_inp_dorso" class="inptxt" style="width:150px"
                     placeholder="Cantidad"
                     value="<?php
                        if($refevent["evt_amount_pulpo_dorso"] > 0.00)
                           echo printPrice($refevent["evt_amount_pulpo_dorso"]);
                        ?>">
                     <input type="hidden" id="idx_xamt_colors_dorso" name="idx_xamt_colors_dorso" value="<?=$xamt_colors_dorso?>">

                  </td>
               </tr>
               <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
                  <td class="tdleft">Producción</td>
                  <td class="tdnrm" colspan="2">
                     <input type="hidden" name="sql_metrotype" value="">
                     <input type="text" class="inptxt cls_prod_amount_pulpo" name="prod_amount" id="prod_amount"
                     style="background-color:#EEEEEE" readonly
                     value="<?php
                        if(!(int)$refevent["id"])
                           echo "0";
                        elseif($refevent["evt_amount"] > 0.00)
                           echo printPrice($refevent["evt_amount"]);
                        ?>">
                     unidades
                     &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                     <input type="text" id="idx_seri_pasadas_inp" class="inptxt" style="width:150px"
                     placeholder="Cantidad de pasadas">
                     <input type="button" class="btngreen" value="Calcular unidades" style="padding:5px;border:0px;font-size:12px;margin-left:5px"
                     onclick="calcUnitsFromPasadas()">
                  </td>
               </tr>
               <?php

            }
            else
            {  ?>
               <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
                  <td class="tdleft">Producción</td>
                  <td class="tdnrm" colspan="2">
                     <input type="hidden" name="sql_metrotype" value="">
                     <input type="text" class="inptxt cls_prod_amount_seri" name="prod_amount" id="prod_amount"
                     style="background-color:#EEEEEE" readonly
                     value="<?php
                        if(!(int)$refevent["id"])
                           echo "0";
                        elseif($refevent["evt_amount"] > 0.00)
                           echo printPrice($refevent["evt_amount"]);
                        ?>">
                     unidades
                     &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                     <input type="text" id="idx_seri_pasadas_inp" class="inptxt" style="width:150px"
                     placeholder="Cantidad de pasadas">
                     <input type="button" class="btngreen" value="Calcular unidades" style="padding:5px;border:0px;font-size:12px;margin-left:5px"
                     onclick="calcUnitsFromPasadas()">
                  </td>
               </tr>
               <?php
            }
         }
         else
         {  
            if($hasopeninit["equipo_type_id"]==8)
            {
               ?>
                  <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
                     <td class="tdleft">Contador Tablero Principal</td>
                     <td class="tdnrm">
                        <input type="hidden" name="sql_metrotype" value="">
                        <input type="text" class="inptxt cls_prod_amount_sella"  name="prod_amount" id="prod_amount"
                        style="<?if($_EMBALAJE_PROD_CALCINP) echo "background-color:#EEEEEE"?>"
                        <?if($_EMBALAJE_PROD_CALCINP) echo "readonly"?>
                        value="<?php
                           if(!(int)$refevent["id"])
                              echo "0";
                           elseif($refevent["evt_amount"] > 0.00)
                              echo printPrice($refevent["evt_amount"]);
                           ?>">
                           unidades
                     </td>
                  </tr>
                  <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
                     <td class="tdleft">Contador Módulo Recibidor</td>
                     <td class="tdnrm">
                        <input type="hidden" name="sql_metrotype" value="">
                        <input type="text" class="inptxt cls_prod_amount2_sella" name="prod_amount2" id="prod_amount2"
                        style="<?if($_EMBALAJE_PROD_CALCINP) echo "background-color:#EEEEEE"?>"
                        <?if($_EMBALAJE_PROD_CALCINP) echo "readonly"?>
                        value="<?php
                           if(!(int)$refevent["id"])
                              echo "0";
                           elseif($refevent["evt_amount2"] > 0.00)
                              echo printPrice($refevent["evt_amount2"]);
                           ?>">
                           unidades
                     </td>
                  </tr>
                  <?php
            }
            else
            {
               ?>
               <tr style="<?if(!(int)$refevent["id"]) echo "display:none"?>">
                  <td class="tdleft">Producción</td>
                  <td class="tdnrm" colspan="2">
                     <input type="hidden" name="sql_metrotype" value="">
                     <input type="text" class="inptxt" name="prod_amount" id="prod_amount"
                     style="<?if($_EMBALAJE_PROD_CALCINP) echo "background-color:#EEEEEE"?>"
                     <?if($_EMBALAJE_PROD_CALCINP) echo "readonly"?>
                     value="<?php
                        if(!(int)$refevent["id"])
                           echo "0";
                        elseif($refevent["evt_amount"] > 0.00)
                           echo printPrice($refevent["evt_amount"]);
                        ?>">
                        unidades
                  </td>
               </tr>
               <?php
            }
         }
      }
      if($_SERICOLORS_ACT)
      {  ?>
         <tr style="">
            <td class="tdleft">Color</td>
            <td class="tdnrm" colspan="2">
               <select class="inptxt" name="prod_seri_color" id="prod_seri_color" style="background-color:#FFFFFF;width:350px">
                  <option value="">Seleccione un color de impresión</option>
                  <?php
                  foreach(array_keys($_SERICOLORS) AS $_SERICOLORIDX)
                  {  ?>
                     <option value="<?=$_SERICOLORIDX?>" <?php if($refevent["prod_seri_color"] == $_SERICOLORIDX) echo "selected"?>>
                        <?=$_SERICOLORIDX?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </td>
         </tr>
         <?php
      }
      if(strpos(strtoupper($hasopeninit["type_ant_title"]), "IMPRESORA") !== false)
      {  ?>
         <tr style="display: none;">
            <td class="tdleft">Peso Bobina</td>
            <td class="tdnrm" colspan="2">
               <input type="text" class="inptxt" name="prod_bobina_kg" id="prod_bobina_kg"
               style="width:100px" value="<?if((int)$refevent["prod_bobina_kg"]) echo printPrice($refevent["prod_bobina_kg"],2)?>"> Kg
            </td>
         </tr>
         <?php
      }
      if((int)$_REQUEST["refid"])
      {
         if((int)$refevent["evt_item_id_sobrante"])
             $itemid["item_number_prod"] = (int)$refevent["evt_item_id_sobrante"];
         else
         {
            $sql              = "select si.item_id
                                    , i.item_number_prod
                                    , i.item_title
                                    , i.item_nameshop 
                                    , cat_id
                                    from stockchanges_items si
                                       inner join item i on i.id = si.item_id
                                       inner join stockchanges s on s.sth_fromprodotid = {$hasopenot["id"]} and si.stk_id = s.id and stk_status > 0
                                       inner join item_productcats ipc on ipc.item_id = i.id and cat_id = 6 
                                    ORDER BY si.item_id DESC LIMIT 1;";
            $itemid           = $CON->select($sql);
            $itemid           = $itemid[0];
            }
         ?>
         <?php
         $visible = "";
         if (strpos(strtoupper($hasopeninit["type_ant_title"]), "SELLADORA") !== false ||
             strpos(strtoupper($hasopeninit["type_ant_title"]), "EMBALAJE") !== false ||
            $_ISSERIPULPO)
         {
            $visible = 'style="display: none;"';
         }
         ?>
         <tr <?=$visible?>>
            <td class="tdleft">Peso Bobina Sobrante</td>
            <td class="tdnrm" >
               <input type="text" class="inptxt clsprodbobinakgsobrante" name="prod_bobina_kg" id="prod_bobina_kg"
               style="width:100px" value="<?if((int)$refevent["prod_bobina_kg"]) echo printPrice($refevent["prod_bobina_kg"],2)?>"> Kg 
               <input disabled type="text" class="inptxt" style="width:100px;background-color: lightgray;" value=<?=$itemid["item_number_prod"]?> >
               <input disabled type="text" class="inptxt" style="width:500px;background-color: lightgray;" value=<?=$itemid["item_title"]?>>
            </td>
         </tr>
         <?php
      }
      ?>
      <tr>
         <td class="tdleft" valign="top">Comentarios</td>
         <td class="tdnrm" colspan="2">
            <textarea class="inptxt" name="evt_comments" id="evt_comments"
            style="width:100%;height:60px"><?=stripslashes($refevent["evt_comments"])?></textarea>
         </td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      if((int)$_REQUEST["refid"])
      {
         $sql = " select t1.*
                  from prod_mermatypes t1
                  where
                  t1.merma_status > 0
                  order by t1.merma_title";
         $mermatypes = $CON->select($sql);
         foreach($mermatypes AS $mermatype)
         {
            $sql = " select *
                     from prod_worker_ot_defectunits
                     where
                     evt_refid = {$_REQUEST["refid"]} and
                     evt_type  = 'merma' and
                     evt_merma_typeid = {$mermatype["id"]}";
            $mermathisdata = $CON->select($sql);
            $mermathisdata = $mermathisdata[0];
            ?>
            <tr>
               <td class="tdleft">Merma</td>
               <td class="tdnrm" colspan="2">
                  <table border="0" width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                     <td class="tdnrm tdnoborder" width="300"><?=$mermatype["merma_title"]?></td>
                     <td class="tdnrm tdnoborder" width="220">
                        <?php
                        if($_ISSELLADORA || (int)$hasopeninit["equipo_prod_isprinter_flexo"] || (int)$hasopeninit["equipo_prod_isprinter_seri"])
                        {  ?>
                           <select class="inptxt" style="" name="unidaddemedida_<?=$mermatype["id"]?>" id="unidaddemedida_<?=$mermatype["id"]?>">
                                 <option value="1">Kilos</option>
                                 <option value="2">Unidad</option>
                           </select>
                           <!-- Kgs&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-->
                           <input type="hidden" name="mermaconvert" value="1">
                           <input type="text" class="inptxt clsinpmermas" name="merma_kgs_<?=$mermatype["id"]?>" id="merma_kgs_<?=$mermatype["id"]?>"
                           style="width:120px" autocomplete="off"
                           value="<?if((int)$mermathisdata["id"]) echo printPrice($mermathisdata["evt_kgstounits"],2)?>">

                           <input type="text" class="inptxt" name="merma_amount_<?=$mermatype["id"]?>" id="merma_amount_<?=$mermatype["id"]?>"
                           style="width:120px;background-color:#EEEEEE;display:none" autocomplete="off"
                           value="">
                           <?php
                        }
                        else
                        {  ?>
                           <select class="inptxt" style="" name="unidaddemedida_<?=$mermatype["id"]?>" id="unidaddemedida_<?=$mermatype["id"]?>">
                                 <option value="3">Cantidad</option>
                                 <option value="5">Kilos</option>
                           </select>
                           <!-- Cantidad -->
                           <input type="text" class="inptxt clsinpmermas" name="merma_amount_<?=$mermatype["id"]?>" id="merma_amount_<?=$mermatype["id"]?>"
                           style="width:120px" autocomplete="off"
                           value="<?if((int)$mermathisdata["id"]) echo printPrice($mermathisdata["evt_amount"])?>">
                           <?php
                        }
                        ?>
                     </td>
                     <td class="tdnrm tdnoborder">
                        Comentarios
                        <input class="inptxt" name="merma_comments_<?=$mermatype["id"]?>" id="merma_comments_<?=$mermatype["id"]?>"
                        style="width:calc(100% - 100px);" value="<?=stripslashes($mermathisdata["evt_comments"])?>">
                     </td>
                  </tr>
                  </table>
               </td>
            </tr>
            <?php
         }

         //----------------------------------------------------------------------------------
         if(!(int)$hasopeninit["equipo_prod_isprinter_flexo"] && !(int)$hasopeninit["equipo_prod_isprinter_seri"] && !$_ISREBOBINADORA)
         {
            $sql = " select t1.*
                     from prod_repairtypes t1
                     where
                     t1.repair_status > 0
                     order by t1.repair_title";
            $repairtypes = $CON->select($sql);
            foreach($repairtypes AS $repairtype)
            {
               $sql = " select *
                        from prod_worker_ot_defectunits
                        where
                        evt_refid = {$_REQUEST["refid"]} and
                        evt_type  = 'repair' and
                        evt_repair_typeid = {$repairtype["id"]}";
               $repthisdata = $CON->select($sql);
               $repthisdata = $repthisdata[0];
               ?>
               <tr>
                  <td class="tdleft">Reparación</td>
                  <td class="tdnrm" colspan="2">
                     <table border="0" width="100%" cellpadding="0" cellspacing="0">
                     <tr>
                        <td class="tdnrm tdnoborder" width="300"><?=$repairtype["repair_title"]?></td>
                        <td class="tdnrm tdnoborder" width="220">
                           <?php
                           if($_ISSELLADORA)
                           {  ?>
                              <!--Kgs&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-->
                              <select class="inptxt" style="" name="unidaddemedida_<?=$repairtype["id"]?>" id="unidaddemedida_<?=$repairtype["id"]?>">
                                 <option value="1">Kilos</option>
                                 <option value="2">Unidad</option>
                              </select>
                              <input type="hidden" name="repairconvert" value="1">
                              <input type="text" class="inptxt clsinpmermas" name="repair_kgs_<?=$repairtype["id"]?>" id="repair_kgs_<?=$repairtype["id"]?>"
                              style="width:120px" autocomplete="off"
                              value="<?if((int)$repthisdata["id"]) echo printPrice($repthisdata["evt_kgstounits"],2)?>">

                              <input type="text" class="inptxt" name="repair_amount_<?=$repairtype["id"]?>" id="repair_amount_<?=$repairtype["id"]?>"
                              style="width:120px;background-color:#EEEEEE;display:none" autocomplete="off"
                              value="">
                              <?php
                           }
                           else
                           {  ?>
                              <!-- Cantidad -->
                              <select class="inptxt" style="" name="unidaddemedida_<?=$repairtype["id"]?>" id="unidaddemedida_<?=$repairtype["id"]?>">
                                 <option value="3">Cantidad</option>
                                 <option value="5">Kilos</option>
                              </select>
                              <input type="text" class="inptxt clsinpmermas" name="repair_amount_<?=$repairtype["id"]?>" id="repair_amount_<?=$repairtype["id"]?>" style="width:120px" autocomplete="off"
                              value="<?if((int)$repthisdata["id"]) echo printPrice($repthisdata["evt_amount"])?>">
                              <?php
                           }
                           ?>
                        </td>
                        <td class="tdnrm tdnoborder">
                           Comentarios
                           <input class="inptxt" name="repair_comments_<?=$repairtype["id"]?>" id="repair_comments_<?=$repairtype["id"]?>"
                           style="width:calc(100% - 100px);" value="<?=stripslashes($repthisdata["evt_comments"])?>">
                        </td>
                     </tr>
                     </table>
                  </td>
               </tr>
               <?php
            }
         }
      }

      //----------------------------------------------------------------------------------
      /*
      $sql = " select t1.*, t2.merma_title 'causa'
               from prod_worker_ot_defectunits t1
               INNER JOIN prod_mermatypes t2 ON t1.evt_merma_typeid = t2.id
               where
               t1.evt_refid   = {$_REQUEST["refid"]} and
               t1.evt_type    = 'merma' and
               t1.evt_status  > 0
               UNION ALL
               select t1.*, t2.repair_title 'causa'
               from prod_worker_ot_defectunits t1
               INNER JOIN prod_repairtypes t2 ON t1.evt_repair_typeid = t2.id
               where
               t1.evt_refid   = {$_REQUEST["refid"]} and
               t1.evt_type    = 'repair' and
               t1.evt_status  > 0
               order by 1";
      $defectunits = $CON->select($sql);
      if(count($defectunits) && $defectunits != false)
      {  ?>
         <tr>
            <td class="tdleft" valign="top">Mermas/Reparación</td>
            <td class="tdnrm" colspan="2">
               <table border="0" width="100%" cellpadding="0" cellspacing="0">
               <tr>
                  <td><b>Tipo</b></td>
                  <td><b>Cantidad</b></td>
                  <td><b>Clasificación</b></td>
                  <td><b>Comentarios</b></td>
                  <td width="80"><b>Opción</b></td>
               </tr>
               <?php
               foreach($defectunits AS $defectunit)
               {
                  $xtype = "Merma";
                  $xlink = "fancy.mermas.php";
                  if($defectunit["evt_type"] == "repair")
                  {
                     $xtype   = "Reparación";
                     $xlink   = "fancy.repairs.php";
                  }
                  ?>
                  <tr>
                     <td class="tdnrmtop" style="padding:3px"><?=$xtype?></td>
                     <td class="tdnrmtop" style="padding:3px"><?=printPrice($defectunit["evt_amount"])?></td>
                     <td class="tdnrmtop" style="padding:3px"><?=$defectunit["causa"]?></td>
                     <td class="tdnrmtop" style="padding:3px"><?=$defectunit["evt_comments"]?>&nbsp;</td>
                     <td class="tdnrmtop" style="padding:3px">
                        <div class="btngrey" style="width:80px"
                        onclick="showColorbox('./libs.prodwrk/modules/ordendetrabajo/<?=$xlink?>?refid=<?=$_REQUEST["refid"]?>&evtid=<?=$defectunit["id"]?>', 'iframe', '500', '300', 'auto')">
                           <i class="fa fa-fw fa-edit" style="color:white;"></i> Editar
                        </div>
                     </td>
                  </tr>
                  <?php
               }
               ?>
               </table>
            </td>
         </tr>
         <?php
      }
      */
      ?>
      </table>
      <div style="clear:both;height:10px"></div>
      <?php
      if((int)$hasopeninit["type_createstock_act"] && (int)$_REQUEST["refid"])
      {
         $sql = " select *
                  from orders
                  where
                  id = {$agenda["ag_reqid"]}";
         $headdata = $CON->select($sql);
         $headdata = $headdata[0];

         $sql = " select *
                  from prod_worker_embalajes
                  where
                  evt_prod_worker_otid = {$hasopenot["id"]} and
                  evt_reqid            = {$hasopenot["prd_reqid"]} and
                  req_v2refid          = {$_REQUEST["refid"]}";
         $embalajesaved = $CON->select($sql);
         $embalajesaved = $embalajesaved[0];
         // print_r($embalajesaved);
         // echo $sql;
         ?>
         <input type="hidden" name="embalaje_save_params" value="1">
         <div style="clear:both;height:10px"></div>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="290">
            <col width="220">
            <col>
         </colgroup>
         <tr>
            <td class="tdheader">Medidas / Cantidades embalaje</td>
            <td class="tdheader">Predefinido</td>
            <td class="tdheader">Ingreso</td>
         </tr>
         <tr>
            <td class="tdleft">Medida de caja</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_medidas_caja" style="width:200px;background-color:#EEEEEE" readonly
               value="<?=$headdata["req_embalaje_medidas_caja"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_medidas_caja" style="width:200px;"
               value="<?if($embalajesaved["req_embalaje_medidas_caja"] != "") echo $embalajesaved["req_embalaje_medidas_caja"]; else echo $headdata["req_embalaje_medidas_caja"]?>">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad de bolsas por caja</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_bolsas_por_caja_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_bolsas_por_caja_amt"]) echo (int)$headdata["req_embalaje_bolsas_por_caja_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_bolsas_por_caja_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_bolsas_por_caja_amt"]) echo $embalajesaved["req_embalaje_bolsas_por_caja_amt"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad de cajas por pallets</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_cajas_por_pallet_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_cajas_por_pallet_amt"]) echo (int)$headdata["req_embalaje_cajas_por_pallet_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_cajas_por_pallet_amt" style="width:200px"
               value="<?if((int)$embalajesaved["req_embalaje_cajas_por_pallet_amt"]) echo $embalajesaved["req_embalaje_cajas_por_pallet_amt"]?>" autocomplete="off">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad de pallets completos</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_pallets_completos_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_pallets_completos_amt"]) echo (int)$headdata["req_embalaje_pallets_completos_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_pallets_completos_amt" style="width:200px"
               value="<?if((int)$embalajesaved["req_embalaje_pallets_completos_amt"]) echo $embalajesaved["req_embalaje_pallets_completos_amt"]?>" autocomplete="off">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Numero de cajas en pallet incompleto</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_palletcajas_incompletos_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_palletcajas_incompletos_amt"]) echo (int)$headdata["req_embalaje_palletcajas_incompletos_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_palletcajas_incompletos_amt" style="width:200px"
               value="<?if((int)$embalajesaved["req_embalaje_palletcajas_incompletos_amt"]) echo $embalajesaved["req_embalaje_palletcajas_incompletos_amt"]?>" autocomplete="off">
            </td>
         </tr>
         <tr>
            <td class="tdleft">Cantidad total de cajas completas</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_cajas_completas_amt" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_cajas_completas_amt"]) echo (int)$headdata["req_embalaje_cajas_completas_amt"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_cajas_completas_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_cajas_completas_amt"]) echo $embalajesaved["req_embalaje_cajas_completas_amt"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <tr>
            <td class="tdleft">Caja final (completa pedido)</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" id="id_req_embalaje_caja_final" style="width:200px;background-color:#EEEEEE" readonly
               value="<?if((int)$headdata["req_embalaje_caja_final"]) echo (int)$headdata["req_embalaje_caja_final"]?>" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_caja_final" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_caja_final"]) echo $embalajesaved["req_embalaje_caja_final"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <tr>
            <td class="tdleft">Bolsas sobrantes</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" style="width:200px;background-color:#EEEEEE" readonly value="" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_bolsas_sobrantes_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_bolsas_sobrantes_amt"]) echo $embalajesaved["req_embalaje_bolsas_sobrantes_amt"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <tr>
            <td class="tdleft">Bolsas a Showroom</td>
            <td class="tdnrm">
               <input type="text" class="inptxt" style="width:200px;background-color:#EEEEEE" readonly value="" tabindex="-1">
            </td>
            <td class="tdnrm">
               <input type="text" class="inptxt" name="req_embalaje_showroom" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
               value="<?if((int)$embalajesaved["req_embalaje_showroom"]) echo $embalajesaved["req_embalaje_showroom"]?>" autocomplete="off">
               (calcula unidades producidas)
            </td>
         </tr>
         <?php
         if((int)$_REQUEST["overrideembalaje"])
         {
            $sql = "select st.id
                         , st.st_name
                       from orders o
                           inner join customer c on c.id = o.req_cust_id
                           inner join bodegacanal bc on c.cust_canal = bc.bodegacanal_codigo_canal
                           inner join company_shops_storehouses st on st.id = bc.bodegacanal_id_bodega
                     where o.id = {$hasopenot["prd_reqid"]} ";
            $bodegas = $CON->select($sql);
            ?>
            <tr>
               <td class="tdleft">Bodega</td>
               <td class="tdnrm" colspan="2">
                    <select class="inptxt" style="width:360px" name="req_id_bodega" id="req_id_bodega"
                       onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                       <option value=" ">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                        <?php
                        foreach($bodegas as $bodega)
                        {  ?>
                           <option value="<?=$bodega["id"]?>"
                           <?php if($bodega["id"] == $embalajesaved["req_id_bodega"]) echo "selected"?>><?=$bodega["st_name"]?></option>
                           <?php
                        }
                       ?>
                  </select>
               </td>
            </tr>
            <tr>
               <td class="tdleft">Usuario supervisor</td>
               <td class="tdnrm">
                  <input type="text" class="inptxt" name="axx_username" id="axx_username" placeholder="Usuario Supervisor"
                  style="width:200px;" autocomplete="off">
               </td>
               <td class="tdnrm" colspan="2">
                  <input type="password" class="inptxt" name="axx_pass" placeholder="Contraseña Supervisor"
                  style="width:200px;" autocomplete="off">
               </td>
            </tr>
            <tr>
                <td class="tdleft" valign="top">Observación Supervisor</td>
                <td class="tdnrm" colspan="2">
                     <textarea class="inptxt" name="req_observacion" id="req_observacion"
                     style="width:100%;height:60px"><?=stripslashes($embalajesaved["req_observacion"])?></textarea>
                </td>
            </tr>
            <tr>
               <td class="tdleft">Clasificación de Cierre</td>
               <td class="tdnrm" colspan="2">
                    <select class="inptxt" style="width:360px" name="req_codigo_cierre" id="req_codigo_cierre" onchange="mostrarOcultarTabla()"
                       onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                       <option value=" ">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                        <?php
                        $sw_cierre = 1;

                        $sql = " select * from orders where id = {$hasopenot["prd_reqid"]} ";
                        $ord = $CON->select($sql);
                        $ord = $ord[0];

                        if((int)$ord["req_id_cc_m1"] || (int)$ord["req_id_cc_m2"] || (int)$ord["req_id_cc_m3"] )
                           $sw_cierre = 2;

                        $sql = " select * from parametros where tabla = 'CIERRES P' and (valor1 = 0 or valor1 = {$sw_cierre}) ";
                        $cierres = $CON->select($sql);
                        foreach($cierres as $cierre)
                        {  ?>
                           <option value="<?=$cierre["codigo"]?>"
                           <?php if($cierre["codigo"] == $embalajesaved["req_codigo_cierre"]) echo "selected"?>><?=$cierre["descripcion"]?> </option>
                           <?php
                        }
                       ?>
                  </select>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <div style="clear:both;height:20px"></div>
         <?php

         $sql = " select *
                  from orders
                  where
                  id = {$hasopenot["prd_reqid"]}";
         $headdata = $CON->select($sql);
         $headdata = $headdata[0];

          /* Clasificacion de cierres */

          $sw_cierre = 1;

          $sql = " select * from orders where id = {$hasopenot["prd_reqid"]} ";
          $ord = $CON->select($sql);
          $ord = $ord[0];



         if( (int)$ord["req_id_cc_m1"] || (int)$ord["req_id_cc_m2"] || (int)$ord["req_id_cc_m3"] )
         {
            $sql = " select t1.*, t1x.fab_design_imagehash from orders t1
                     INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
                     where
                     t1.id = {$hasopenot["prd_reqid"]}
                        union
                     select t1.*, t1x.fab_design_imagehash from orders t1
                     INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
                     where
                     t1.id = ifnull({$ord["req_id_cc_m1"]},0)
                        union
                     select t1.*, t1x.fab_design_imagehash from orders t1
                     INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
                     where
                     t1.id = ifnull({$ord["req_id_cc_m2"]},0)
                        union
                     select t1.*, t1x.fab_design_imagehash from orders t1
                     INNER JOIN orders_items t1x      ON t1.id = t1x.req_id
                     where
                     t1.id = ifnull({$ord["req_id_cc_m3"]},0) ";
            $detalle = $CON->select($sql);
            $sw_cierre = 2;
         }

          $sql = "select st.id
                       , st.st_name
                       , i1.item_number_prod
                       , ifnull(iss.iss_inventory,0) as stock
                       , i1.id as id_item
                       , o.id as id_orders
                     from orders o
                        inner join orders_items oi on oi.req_id = o.id
                        inner join item i1 on i1.id = oi.item_id
                        inner join customer c on c.id = o.req_cust_id
                        inner join bodegacanal bc on c.cust_canal = bc.bodegacanal_codigo_canal
                        inner join company_shops_storehouses st on st.id = bc.bodegacanal_id_bodega
                        LEFT OUTER JOIN item_shops_storehouses iss on iss.item_id = i1.id and iss.iss_order_id = o.id and iss.st_id = st.id
                  where o.id in({$ord["req_id_cc_m1"]},{$ord["req_id_cc_m2"]},{$ord["req_id_cc_m3"]}) ";
         $t_bodegas = $CON->select($sql);

         if($sw_cierre == 2)
         {  ?>
            <table border="1" id="table_mixto" name = "table_mixto" width="99%" cellpadding="0" cellspacing="0" style="display: none;">
               <colgroup>
                  <col width="100">
                  <col width="100">
                  <col>
                  <col>
                  <col>
                  <col>
                  <col width="100">
                  <col>
                  <col>
                  <col>
               </colgroup>
               <tr>
                  <td class="tdheader" align="center" colspan="2">Detalle</td>
                  <td class="tdheader" align="center" colspan="5">Cantidad de Bolsa</td>
                  <td class="tdheader" align="center" colspan="3">Movimiento de Bodega</td>
               </tr>
               <tr>
                  <td class="tdheader">C.C</td>
                  <td class="tdheader" align="center">Imagen C.C</td>
                  <td class="tdheader" align="center">Total de Cajas</td>
                  <td class="tdheader" align="center">Unidad Por Caja</td>
                  <td class="tdheader" align="center">Unidad Caja Final</td>
                  <td class="tdheader" align="center">Unidad de Sobrante</td>
                  <td class="tdheader" align="center">Total Unidades</td>
                  <td class="tdheader" align="center">Codigo</td>
                  <td class="tdheader" align="center">Desde Bodega</td>
                  <td class="tdheader" align="center">Total Traspaso</td>
               </tr>
               <tr>
                  <?php
                     for($x = 0; $x < count($detalle) && $detalle != false; $x++)
                     {
                        // echo "<pre>";
                        // print_r($detalle[$x]);
                        if($detalle[$x]["id"] != $hasopenot["prd_reqid"])
                        {
                           ?>
                           <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                              <td class="content_row_os"><nobr><?=$detalle[$x]["req_number"]?></nobr></td>
                              <td class="content_row_os">
                                 <?php
                                 if($detalle[$x]["fab_design_imagehash"] != "")
                                 {  ?>
                                    <img style="cursor:pointer;border:1px solid #CCCCCC" width="100%" title="<?=$detalle[$x]["req_embalaje_medidas_caja"]?>"
                                    onclick="
                                    $.colorbox({
                                             width:'90%',
                                             height:'90%',
                                             href:'/docs.order/<?=$detalle[$x]["fab_design_imagehash"]?>',
                                             closeButton:true,
                                             overlayClose:true,
                                             escKey:true,
                                             opacity: 0.5
                                          }
                                       );"
                                    src="/docs.order/<?=$detalle[$x]["fab_design_imagehash"]?>">
                                    <?php

                                 }
                                 else
                                    echo "- - -";
                                 ?>
                              </td>
                              <td class="content_row_os" align="center">
                                  <input type="text" class="text" style="width:45px;text-align:center;"
                                    name="total_cajas_<?=$x?>" id="total_cajas_<?=$x?>" onkeyup="calcEmbalajeMixto(<?=$x?>)" autocomplete="off"
                                    value="<?if($_SHOW_DEFVALUES_TABLE) { echo printPrice($detalle[$x]["req_embalaje_cajas_completas_amt"]); } ?>"
                                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                              </td>
                              <td class="content_row_os" align="center">
                                   <input type="text" class="text" style="width:45px;text-align:center;"
                                    name="bolsas_por_caja_<?=$x?>" id="bolsas_por_caja_<?=$x?>" onkeyup="calcEmbalajeMixto(<?=$x?>)" autocomplete="off"
                                    value="<?if($_SHOW_DEFVALUES_TABLE) { echo printPrice($detalle[$x]["req_embalaje_bolsas_por_caja_amt"]); } ?>"
                                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                              </td>
                              <td class="content_row_os" align="center">
                                    <input type="text" class="text" style="width:45px;text-align:center;"
                                     name="bolsas_caja_final_<?=$x?>" id="bolsas_caja_final_<?=$x?>" onkeyup="calcEmbalajeMixto(<?=$x?>)" autocomplete="off"
                                     value="<?if($_SHOW_DEFVALUES_TABLE) { echo printPrice($detalle[$x]["req_embalaje_caja_final"]); } ?>"
                                     onfocus="markfield(this,0)" onblur="markfield(this,1)">
                              </td>
                              <td class="content_row_os" align="center">
                                  <input type="text" class="text" style="width:45px;text-align:center;"
                                    name="bolsas_sobrante_<?=$x?>" id="bolsas_sobrante_<?=$x?>" onkeyup="calcEmbalajeMixto(<?=$x?>)" autocomplete="off"
                                    value="<?if($_SHOW_DEFVALUES_TABLE) { echo printPrice($detalle[$x]["req_operador_mermaperc"]); } ?>"
                                    onfocus="markfield(this,0)" onblur="markfield(this,1)"
                                    <?if($detalle[$x]["id"] == $hasopenot["prd_reqid"]) echo 'readonly'; ?> >
                              </td>
                              <td class="content_row_os" align="center"><nobr>
                                   <?php
                                      $detalle[$x]["req_embalaje_cajas_completas_amt"] = ($detalle[$x]["req_embalaje_bolsas_por_caja_amt"] *
                                                                                          $detalle[$x]["req_embalaje_cajas_completas_amt"] ) +
                                                                                          $detalle[$x]["req_operador_mermaperc"] +
                                                                                          $detalle[$x]["req_embalaje_caja_final"];
                                   ?>
                                   <input type="text" class="text" style="width:60px;text-align:center;"
                                   name="total_bolsas_<?=$x?>" id="total_bolsas_<?=$x?>" autocomplete="off"
                                   value="<?if($_SHOW_DEFVALUES_TABLE) { echo printPrice($detalle[$x]["req_embalaje_cajas_completas_amt"]); } ?>"
                                   onfocus="markfield(this,0)" onblur="markfield(this,1)"
                                   readonly>
                              </nobr></td>
                              <td class="content_row_os" align="center"><?=$t_bodegas[0]["item_number_prod"]?></td>
                              <td class="content_row_os" align="center">
                                 <select class="inptxt" style="width:360px" name="req_id_bodega_<?=$x?>" id="req_id_bodega_<?=$x?>"
                                    onmousedown="markfield(this,0)" onblur="markfield(this,1)" <?if($detalle[$x]["id"] == $hasopenot["prd_reqid"]) echo 'disabled'; ?> >
                                       <option value=" " >&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                                          <?php
                                          foreach($t_bodegas as $bodega)
                                          {
                                             if($bodega["id_orders"] == $detalle[$x]["id"])
                                             {
                                                ?>
                                                <option value="<?=$bodega["id"]?>" <?if($bodega["id"] == $_SHOW_DEFAULT_STHID) echo "selected"?>
                                                      ><?=$bodega["st_name"].' ('.printPrice($bodega["stock"]).')'?>
                                                </option>
                                                <?php
                                             }
                                          }
                                       ?>
                                 </select>
                              </td>
                              <td class="content_row_os" align="center">
                                 <input type="text" class="text" style="width:45px;text-align:center;" <?=$dscrdlo?>
                                    name="total_bolsas_<?=$x?>" id="total_bolsas_<?=$x?>" autocomplete="off"
                                    value="<?if($_SHOW_DEFVALUES_TABLE) { echo printPrice($detalle[$x]["total_bolsas"]); } ?>"
                                    onfocus="markfield(this,0)" onblur="markfield(this,1)">
                              </td>
                           </tr>
                        <?php
                        }
                     }
                     if(!$x)
                     {  ?>
                        <tr bgcolor="<?=getRowColor(0)?>">
                           <td class="content_row_os" colspan="10" align="center">
                              <br>
                                <b class="msg_save_err">No hay datos disponibles.</b>
                                 <br><br>
                           </td>
                        </tr>
                        <?php
                     }
                  ?>
               </tr>
            </table>
            <?php
         }
      }

      if((int)$_REQUEST["refid"])
      {  ?>
         <table border="0" width="100%" cellpadding="0" cellspacing="0">
         <tr>
            <td width="50%" style="padding-right:5px">
               <div class="btngrey" onclick="location.href = 'prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';"
               style="float:left;width:40%">
                  <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
               </div>
               <div class="btnred" onclick="if(askDel('')) { document.xform_inp.mode.value='eventdelete'; document.xform_inp.submit(); }"
               style="margin-left:5px;float:left;width:40%">
                  <i class="fa fa-fw fa-times-circle" style="color:white;"></i> Eliminar&nbsp;
               </div>
            </td>
            <td width="50%" style="padding-left:5px">
               <?php
               //----------------------------------------------------------------------------------
               $sql = " select t1.*, t2.item_amount
                        from stockchanges t1
                        INNER JOIN stockchanges_items t2    ON t1.id = t2.stk_id
                        INNER JOIN item t3                  ON t2.item_id = t3.id
                        LEFT OUTER JOIN item_productcats t4 ON t3.id = t4.item_id
                        where
                        t1.sth_fromprodotid  = {$hasopenot["id"]} and
                        t1.stk_status        = 2 and
                        t4.cat_id            = {$_CONFIG["TELA_CATID"]}";
               $bobinasuseds = $CON->select($sql);

               $bobinasused = 0;
               foreach($bobinasuseds AS $bobinasusedsrow)
               {
                  if((int)$bobinasusedsrow["stk_negative"])
                     $bobinasused += $bobinasusedsrow["item_amount"];
                  else
                     $bobinasused -= $bobinasusedsrow["item_amount"];
               }
               $bobinasused = printPrice($bobinasused,8);

               //----------------------------------------------------------------------------------
               $sql = " select t1.*, t2.item_amount
                        from stockchanges t1
                        INNER JOIN stockchanges_items t2    ON t1.id = t2.stk_id
                        INNER JOIN item t3                  ON t2.item_id = t3.id
                        LEFT OUTER JOIN item_productcats t4 ON t3.id = t4.item_id
                        where
                        t1.sth_fromprodotid  = {$hasopenot["id"]} and
                        t1.stk_status        = 2 and
                        t4.cat_id            = 27";
               $bobinasprinteds = $CON->select($sql);

               $bobinasprinted = 0;
               foreach($bobinasprinteds AS $bobinasprintedsrow)
               {
                  if(!(int)$bobinasprintedsrow["stk_negative"])
                     $bobinasprinted += $bobinasprintedsrow["item_amount"];
                  else
                     $bobinasprinted -= $bobinasprintedsrow["item_amount"];
               }
               $bobinasprinted = printPrice($bobinasprinted,8);

               if(!(int)$refevent["evt_enddat"])
               {
                  if((int)$_REQUEST["overrideembalaje"])
                  {  ?>
                     <script language="Javascript">
                        function overrideEmbalaje()
                        {
                           var xformthis = document.xform_inp;
                           var chk1 = checkform(new Array(
                                                xformthis.req_embalaje_medidas_caja, xformthis.req_embalaje_bolsas_por_caja_amt,
                                                xformthis.req_embalaje_cajas_por_pallet_amt, xformthis.req_embalaje_palletcajas_incompletos_amt,
                                                xformthis.req_embalaje_cajas_completas_amt,
                                                xformthis.req_embalaje_caja_final, xformthis.req_embalaje_pallets_completos_amt,
                                                xformthis.req_embalaje_bolsas_sobrantes_amt, xformthis.axx_username, xformthis.axx_pass));
                           if(chk1)
                           {
                              $('.cls_div_termwait_popup_btn').hide(0);
                              $('#idx_div_termwait_popup').show(0);
                           }
                           return chk1;
                        }
                     </script>
                     <div class="btnorange cls_div_termwait_popup_btn" onclick="if(overrideEmbalaje()) { document.xform_inp.mode.value='production'; document.xform_inp.overrideembalajesaveend.value='1';submitForm(document.xform_inp) }"
                     style="margin-left:5px;float:right;width:40%">
                        <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar producción&nbsp;
                     </div>
                     <div class="btngrey cls_div_termwait_popup_btn" onclick="submitForm(document.xform_inp)"
                     style="float:right;width:40%">
                        <i class="fa fa-fw fa-save" style="color:white;"></i> Actualizar&nbsp;
                     </div>
                     <?php
                  }
                  else
                  {
                     $pesoUnit = getCalculaPesoUnitario($CON, $hasopenot);
                     if((int)$hasopeninit["equipo_prod_isprinter_flexo"] ||
                        $_ISSELLADORA ||
                        ((int)$hasopeninit["equipo_prod_isprinter_seri"] && !$_ISSERIPULPO) ||
                        ((int)$hasopeninit["equipo_prod_isprinter_seri"] && $_ISSERIPULPO))
                     {
                        if($_ISSERIPULPO)
                        {  ?>
                           <div class="btnorange" onclick="if(checkPulpoAmts()) { showprdclose(); }"
                           style="margin-left:5px;float:right;width:40%">
                              <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar producción&nbsp;
                           </div>
                           <?php
                        }
                        else
                        {  ?>
                           <div class="btnorange" onclick="showprdclose()"
                           style="margin-left:5px;float:right;width:40%">
                              <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar producción&nbsp;
                           </div>
                           <?php
                        }
                        ?>
                        <script language="Javascript">
                           function showprdclose()
                           {
                              var pesounit = <?=(float)$pesoUnit?>;
                              var unit_prod = 0;
                              var divisor_perc = <?=(float)$hasopeninit["equipo_prod_divisor_perc"]?>;
                              var corte_m2 = <?=(float)$corte_m2?>;

                              var mermasamt = 0;
                              $('.clsinpmermas').each(function()
                              {
                                 var mamt = parseFloat($(this).val().replaceAll('.', '').replaceAll(',', '.'));
                                 if(isNaN(mamt))
                                    mamt = 0;

                                 var mobjnameidx = $(this).attr('name').split('_')[2];
                                 var unidaddemedida = $('#unidaddemedida_' +mobjnameidx).val();

                                 if(unidaddemedida == '2' || unidaddemedida == '5')
                                    mamt = mamt * pesounit;


                                 mermasamt += mamt;
                              });
                              $('#idx_askprdclosetbl_flexo_mermas').html(mermasamt);
                              $('#idx_askprdclosetbl_flexo_bobconsumed').html('<?=$bobinasused?>');
                              $('#idx_askprdclosetbl_flexo_bobprinted').html('<?=$bobinasprinted?>');

                              var xbobinakgsobrante = parseFloat($('.clsprodbobinakgsobrante').val().replaceAll('.', '').replaceAll(',', '.'));
                              if(isNaN(xbobinakgsobrante))
                                 xbobinakgsobrante = 0;
                              $('#idx_askprdclosetbl_flexo_bobsobrante').html(xbobinakgsobrante);

                              <?php
                              if($_ISSELLADORA)
                              {  ?>
                                 var pamt1 = parseFloat($('.cls_prod_amount_sella').val().replaceAll('.', '').replaceAll(',', '.'));
                                 var pamt2 = parseFloat($('.cls_prod_amount2_sella').val().replaceAll('.', '').replaceAll(',', '.'));
                                 if(isNaN(pamt1))
                                    pamt1 = 0;
                                 if(isNaN(pamt2))
                                    pamt2 = 0;

                                 $('#idx_askprdclosetbl_flexo_metros').html(pamt1);
                                 $('#idx_askprdclosetbl_flexo_metros2').html(pamt2);
                                 <?php
                              }
                              //SERI SIN PULPO)
                              elseif((int)$hasopeninit["equipo_prod_isprinter_seri"] && !$_ISSERIPULPO)
                              {  ?>
                                 if($('.cls_prod_amount_seri').val() == '')
                                    $('.cls_prod_amount_seri').val('0');
                                 var unit_prod = parseFloat($('.cls_prod_amount_seri').val().replaceAll('.', '').replaceAll(',', '.'));
                                 if(isNaN(unit_prod))
                                    unit_prod = 0;

                                 var xmetros = unit_prod * corte_m2;
                                 var xcontador = unit_prod / parseInt($('#idx_bastidor_amt').val());
                                 $('#idx_askprdclosetbl_flexo_metros').html(xcontador);
                                 $('#idx_askprdclosetbl_flexo_units').html(unit_prod);
                                 <?php
                              }
                              elseif((int)$hasopeninit["equipo_prod_isprinter_seri"] && $_ISSERIPULPO)
                              {
                                 ?>
                                 var xamt_frente  = parseFloat($('#idx_pulpo_seri_pasadas_inp_frente').val().replaceAll('.', '').replaceAll(',', '.'));
                                 var xamt_dorso   = parseFloat($('#idx_pulpo_seri_pasadas_inp_dorso ').val().replaceAll('.', '').replaceAll(',', '.'));

                                 if(isNaN(xamt_frente))
                                    xamt_frente = 0;
                                 if(isNaN(xamt_dorso))
                                    xamt_dorso = 0;

                                 var xamt_final = xamt_frente;
                                 if(xamt_dorso > xamt_final)
                                    xamt_final = xamt_dorso;
                                 $('#idx_askprdclosetbl_flexo_metros').html(xamt_final);


                                 var unit_prod = parseFloat($('.cls_prod_amount_pulpo').val().replaceAll('.', '').replaceAll(',', '.'));
                                 if(isNaN(unit_prod))
                                    unit_prod = 0;

                                 $('#idx_askprdclosetbl_flexo_units').html(unit_prod);
                                 <?php
                              }
                              //OTROS: FLEXO
                              else
                              {
                                 if($hasopeninit["equipo_prod_printer_metrotype"] == "mtrs lineales" || $hasopeninit["equipo_prod_printer_metrotype"] == "")
                                 {  ?>
                                    if($('#evt_amount_metros_lineales').val() == '')
                                       $('#evt_amount_metros_lineales').val('0');
                                    var unit_prod = parseFloat($('#evt_amount_metros_lineales').val().replaceAll('.', '').replaceAll(',', '.'));
                                    if(isNaN(unit_prod))
                                       unit_prod = 0;
                                    unit_prod = unit_prod * divisor_perc;
                                    unit_prod =  Math.floor(unit_prod / corte_m2);

                                    $('#idx_askprdclosetbl_flexo_metros').html($('#evt_amount_metros_lineales').val().replaceAll('.', ''));
                                    $('#idx_askprdclosetbl_flexo_units').html(unit_prod);

                                    <?php
                                 }
                                 elseif($hasopeninit["equipo_prod_printer_metrotype"] == "mtrs/máquina")
                                 {  ?>
                                    if($('#evt_amount_metros_maquina').val() == '')
                                       $('#evt_amount_metros_maquina').val('0');
                                    var unit_prod = parseFloat($('#evt_amount_metros_maquina').val().replaceAll('.', '').replaceAll(',', '.'));
                                    if(isNaN(unit_prod))
                                       unit_prod = 0;
                                    unit_prod = unit_prod * divisor_perc;
                                    unit_prod =  Math.floor(unit_prod / corte_m2);

                                    $('#idx_askprdclosetbl_flexo_metros').html($('#evt_amount_metros_maquina').val().replaceAll('.', ''));
                                    $('#idx_askprdclosetbl_flexo_units').html(unit_prod);
                                    <?php
                                 }
                              }
                              ?>

                              $('#idx_diag_askprdclose').fadeIn(300);
                           }
                        </script>
                        <div id="idx_diag_askprdclose" style="display:none;position:fixed;top:0px;left:0px;background-color:rgba(0,0,0,0.3);height:100%;width:100%">
                           <div style="overflow:hidden;box-shadow:0px 0px 12px 6px rgba(0,0,0,0.2);text-align:center;position:absolute;left: calc(50% - 300px);top: calc(50% - 200px);width:900px;height:400px;background-color:#FFFFFF">
                              <table border="0" cellpadding="0" cellspacing="0" width="100%">
                              <tr>
                                 <td class="tdleft" height="30" style="background-color:#165552;color:#FFFFFF" align="left">
                                    &nbsp;<i class="fa fa-fw fa-image"></i> Diseño
                                 </td>
                                 <td class="tdleft" style="background-color:#165552;color:#FFFFFF" align="left">
                                    &nbsp;<i class="fa fa-fw fa-info-circle"></i> Información
                                 </td>
                              </tr>
                              <tr>
                                 <td align="left" width="400"><img style="border:1px solid #CCCCCC" width="100%" src="/docs.order/<?=$agenda["fab_design_imagehash"]?>"></td>
                                 <td valign="top">
                                    <table border="0" cellpadding="6" cellspacing="0" width="100%">
                                    <colgroup>
                                       <col width="185">
                                       <col>
                                       <col width="30">
                                    </colgroup>
                                    <tr>
                                       <td class="tdleft" style="background-color:#EEEEEE;" width="185">Cliente</td>
                                       <td class="tdnrm" colspan="2"><?=$agenda["cust_name"]?></td>
                                    </tr>
                                    <?php
                                    if($_ISSELLADORA)
                                    {  ?>
                                       <tr>
                                          <td class="tdleft" style="background-color:#EEEEEE;">Contador princpial</td>
                                          <td class="tdnrm" id="idx_askprdclosetbl_flexo_metros"></td>
                                          <td class="tdnrm">Und</td>
                                       </tr>
                                       <tr>
                                          <td class="tdleft" style="background-color:#EEEEEE;">Contador recibidor</td>
                                          <td class="tdnrm" id="idx_askprdclosetbl_flexo_metros2"></td>
                                          <td class="tdnrm">Und</td>
                                       </tr>
                                       <tr>
                                          <td class="tdleft" style="background-color:#EEEEEE;">Total mermas reportadas</td>
                                          <td class="tdnrm" id="idx_askprdclosetbl_flexo_mermas"></td>
                                          <td class="tdnrm">Kg</td>
                                       </tr>
                                       <?php
                                    }
                                    else
                                    {
                                       if((int)$hasopeninit["equipo_prod_isprinter_seri"])
                                       {  ?>
                                          <tr>
                                             <td class="tdleft" style="background-color:#EEEEEE;">Contador impresora</td>
                                             <td class="tdnrm" id="idx_askprdclosetbl_flexo_metros" colspan="2"></td>
                                          </tr>
                                          <?php
                                       }
                                       else
                                       {  ?>
                                          <tr>
                                             <td class="tdleft" style="background-color:#EEEEEE;">Metros impresos</td>
                                             <td class="tdnrm" id="idx_askprdclosetbl_flexo_metros"></td>
                                             <td class="tdnrm">M</td>
                                          </tr>
                                          <?php
                                       }
                                       ?>
                                       <tr>
                                          <td class="tdleft" style="background-color:#EEEEEE;">Unidades impresas</td>
                                          <td class="tdnrm" id="idx_askprdclosetbl_flexo_units"></td>
                                          <td class="tdnrm">Und</td>
                                       </tr>
                                       <tr>
                                          <td class="tdleft" style="background-color:#EEEEEE;">Total mermas reportadas</td>
                                          <td class="tdnrm" id="idx_askprdclosetbl_flexo_mermas"></td>
                                          <td class="tdnrm">Kg</td>
                                       </tr>
                                       <?php
                                       if(!$_ISSERIPULPO)
                                       {  ?>
                                          <tr>
                                             <td class="tdleft" style="background-color:#EEEEEE;">Total bobinas consumidas</td>
                                             <td class="tdnrm" id="idx_askprdclosetbl_flexo_bobconsumed"></td>
                                             <td class="tdnrm">Und</td>
                                          </tr>
                                          <tr>
                                             <td class="tdleft" style="background-color:#EEEEEE;">Total bobinas impresas</td>
                                             <td class="tdnrm" id="idx_askprdclosetbl_flexo_bobprinted"></td>
                                             <td class="tdnrm">Und</td>
                                          </tr>
                                          <tr>
                                             <td class="tdleft" style="background-color:#EEEEEE;">Peso Bobina Sobrante</td>
                                             <td class="tdnrm" id="idx_askprdclosetbl_flexo_bobsobrante"></td>
                                             <td class="tdnrm">Kg</td>
                                          </tr>
                                          <?php
                                       }
                                    }
                                    ?>
                                    <tr>
                                       <td class="tdnrm">
                                          <input type="text" class="inptxt" name="askprdclose_axx_username" id="askprdclose_axx_username" placeholder="Usuario Supervisor"
                                          style="width:100%;" autocomplete="off">
                                       </td>
                                       <td class="tdnrm" colspan="2">
                                          <input type="password" class="inptxt" name="askprdclose_axx_pass" placeholder="Contraseña Supervisor"
                                          style="width:200px;" autocomplete="off">
                                       </td>
                                    </tr>
                                    </table>
                                    <input type="hidden" name="validateprdsave" id="validateprdsave" value="">

                                    <div style="height:15px"></div>
                                    <div class="btngrey" onclick="$('#idx_diag_askprdclose').fadeOut(300);"
                                    style="float:left;width:40%;margin-left:15px">
                                       <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
                                    </div>
                                    <div class="btngreen"
                                    onclick="if(checkform(new Array(document.xform_inp.askprdclose_axx_username, document.xform_inp.askprdclose_axx_pass))) { $('#validateprdsave').val('1');document.xform_inp.submode.value='end';submitForm(document.xform_inp); }"
                                    style="float:right;width:40%;margin-right:15px">
                                       <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
                                    </div>

                                 </td>
                              </tr>
                              </table>
                           </div>
                        </div>
                        <?php
                     }
                     else
                     {  ?>
                        <div class="btnorange" onclick="document.xform_inp.submode.value='end';submitForm(document.xform_inp)"
                        style="margin-left:5px;float:right;width:40%">
                           <i class="fa fa-fw fa-stop" style="color:white;"></i> Terminar producción&nbsp;
                        </div>
                        <?php
                     }

                     if($_ISSERIPULPO)
                     {  ?>
                        <div class="btngrey" onclick="$('#validateprdsave').val('0');if(checkPulpoAmts()) { submitForm(document.xform_inp); }"
                        style="float:right;width:40%">
                           <i class="fa fa-fw fa-save" style="color:white;"></i> Actualizar&nbsp;
                        </div>
                        <?php
                     }
                     else
                     {  ?>
                        <div class="btngrey" onclick="$('#validateprdsave').val('0');submitForm(document.xform_inp)"
                        style="float:right;width:40%">
                           <i class="fa fa-fw fa-save" style="color:white;"></i> Actualizar&nbsp;
                        </div>
                        <?php
                     }
                  }
               }
               else
               {  ?>
                  <div class="btnorange" onclick="if(confirm('Estas seguro?')) location.href = '/prodwrk.php?mid=<?=$_REQUEST["mid"]?>&agid=<?=$_REQUEST["agid"]?>&mode=production&refid=<?=$_REQUEST["refid"]?>&overrideembalaje=<?=$_REQUEST["overrideembalaje"]?>&resetv2=1';">
                     <i class="fa fa-fw fa-save" style="color:white;"></i> Restablecer&nbsp;
                  </div>
                  <!--
                  <div class="btngreen" onclick="$('#validateprdsave').val('0');submitForm(document.xform_inp)">
                     <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
                  </div>
                  -->
                  <?php
               }
               ?>
            </td>
         </tr>
         </table>
         <?php
      }
      else
      {  ?>
         <div class="btngreen" onclick="submitForm(document.xform_inp)">
            <i class="fa fa-fw fa-play" style="color:white;"></i> Iniciar producción&nbsp;
         </div>
         <div style="clear:both;height:10px"></div>
         <div class="btngrey" onclick="location.href = '/prodwrk.php?mid=2&agid=<?=$_REQUEST["agid"]?>';">
            <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
         </div>
         <?php
      }
      ?>
   </td>
</tr>
</table>
</form>
<div id="idx_div_termwait_popup" style="display:none;background-color:rgba(0,0,0,0.3);position:fixed;top:0px;left:0px;width:100%;height:100%">
   <div style="width: 340px; background-color:#FFFFFF;height: 90px;position: absolute;box-shadow:0px 0px 12px 8px rgba(0,0,0,0.1);
    left: 50%;top: 50%;transform: translate(-50%, -50%);padding: 30px;text-align:center">

      <div class="fa-4x"><i class="fas fa-spinner fa-spin"></i></div>
      <div style="height:10px"></div>
      POR FAVOR ESPERE...
   </div>
</div>
<script language="JavaScript">
   $(document).ready(function()
   {
      <?php
      if(!$_EMBALAJE_PROD_CALCINP)
      {  ?>
         $('#prod_amount').focus();
         <?php
      }
      ?>
   });

   function mostrarOcultarTabla()
   {
      var seleccion = document.getElementById("req_codigo_cierre").value;
      var tabla = document.getElementById("table_mixto");
      if (seleccion === "50")
      {
         tabla.style.display = "table";
      }
      else
      {
         tabla.style.display = "none";
      }
   }
   function calcEmbalajeMixto(puntero)
   {

      if (document.getElementById("bolsas_por_caja_0"))
      {
         xtotal_caja = getIntegerFromValue(document.xform_inp.total_cajas_0.value);
         xCaja       = getIntegerFromValue(document.xform_inp.bolsas_por_caja_0.value);
         xCajaFinal  = getIntegerFromValue(document.xform_inp.bolsas_caja_final_0.value);
         xSobrante   = getIntegerFromValue(document.xform_inp.bolsas_sobrante_0.value);
         totalxCaja  = parseInt((xtotal_caja * xCaja) + xCajaFinal + xSobrante);
         $('#total_bolsas_0').val(totalxCaja);
      }
      if (document.getElementById("bolsas_por_caja_1"))
      {
         xtotal_caja = getIntegerFromValue(document.xform_inp.total_cajas_1.value);
         xCaja       = getIntegerFromValue(document.xform_inp.bolsas_por_caja_1.value);
         xCajaFinal  = getIntegerFromValue(document.xform_inp.bolsas_caja_final_1.value);
         xSobrante   = getIntegerFromValue(document.xform_inp.bolsas_sobrante_1.value);
         totalxCaja  = parseInt((xtotal_caja * xCaja) + xCajaFinal + xSobrante);
         $('#total_bolsas_1').val(totalxCaja);
      }

      if (document.getElementById("bolsas_por_caja_2"))
      {
         xtotal_caja = getIntegerFromValue(document.xform_inp.total_cajas_2.value);
         xCaja       = getIntegerFromValue(document.xform_inp.bolsas_por_caja_2.value);
         xCajaFinal  = getIntegerFromValue(document.xform_inp.bolsas_caja_final_2.value);
         xSobrante   = getIntegerFromValue(document.xform_inp.bolsas_sobrante_2.value);
         totalxCaja  = parseInt((xtotal_caja * xCaja) + xCajaFinal + xSobrante);
         $('#total_bolsas_2').val(totalxCaja);
      }

      if (document.getElementById("bolsas_por_caja_3"))
      {
         xtotal_caja = getIntegerFromValue(document.xform_inp.total_cajas_3.value);
         xCaja       = getIntegerFromValue(document.xform_inp.bolsas_por_caja_3.value);
         xCajaFinal  = getIntegerFromValue(document.xform_inp.bolsas_caja_final_3.value);
         xSobrante   = getIntegerFromValue(document.xform_inp.bolsas_sobrante_3.value);
         totalxCaja  = parseInt((xtotal_caja * xCaja) + xCajaFinal + xSobrante);
         $('#total_bolsas_3').val(totalxCaja);
      }

   }
   function calcEmbalajeProdAmt()
   {
      var bolsas_por_caja_amt    = getIntegerFromValue(document.xform_inp.req_embalaje_bolsas_por_caja_amt.value);
      var cajas_completas_amt    = getIntegerFromValue(document.xform_inp.req_embalaje_cajas_completas_amt.value);
      var bolsas_sobrantes_amt   = getIntegerFromValue(document.xform_inp.req_embalaje_bolsas_sobrantes_amt.value);
      var bolsas_caja_final_amt  = getIntegerFromValue(document.xform_inp.req_embalaje_caja_final.value);
      var showroom               = getIntegerFromValue(document.xform_inp.req_embalaje_showroom.value);
      var totalprod              = parseInt((bolsas_por_caja_amt * cajas_completas_amt) +bolsas_sobrantes_amt +bolsas_caja_final_amt+showroom);

      $('#prod_amount').val(totalprod);
   }
   function calcUnitsFromPasadas()
   {
      var pasadas_inp      = getIntegerFromValue($('#idx_seri_pasadas_inp').val());
      var bastidor_inp     = getIntegerFromValue($('#idx_bastidor_amt').val());
      var totalpasprod     = parseInt((pasadas_inp * bastidor_inp));

      $('#prod_amount').val(totalpasprod);
   }
   function getIntegerFromValue(calval)
   {
      if(calval == '') calval = '0';
      while(calval.indexOf(".") > 0) calval = calval.replace(".", "");
      if(calval.indexOf("0") > 0) while(calval.substr(0,1) == '0') calval = calval.substr(1);
      calval = parseInt(calval);
      if(isNaN(calval)) calval = 0;
      return calval;
   }
   function checkPulpoAmts()
   {
      xret = false;

      var idx_pulpo_seri_pasadas_inp_frente  = getIntegerFromValue($('#idx_pulpo_seri_pasadas_inp_frente').val());
      var idx_pulpo_seri_pasadas_inp_dorso   = getIntegerFromValue($('#idx_pulpo_seri_pasadas_inp_dorso ').val());
      var idx_xamt_colors_frente             = getIntegerFromValue($('#idx_xamt_colors_frente').val());
      var idx_xamt_colors_dorso              = getIntegerFromValue($('#idx_xamt_colors_dorso').val());
      var prod_amount                        = getIntegerFromValue($('#prod_amount').val());

      var prodamt_lados = 0;
      if(idx_xamt_colors_frente == 0) idx_pulpo_seri_pasadas_inp_frente = 0;
      if(idx_xamt_colors_dorso == 0) idx_pulpo_seri_pasadas_inp_dorso = 0;


      if(prod_amount == 0 && (idx_pulpo_seri_pasadas_inp_frente > 0 || idx_pulpo_seri_pasadas_inp_dorso > 0))
      {
         alert('Cantidad de pasadas descuadrada.');
         xret = false;
      }
      else
      {
         if(prod_amount > 0)
         {
            if(idx_pulpo_seri_pasadas_inp_frente == prod_amount ||
               idx_pulpo_seri_pasadas_inp_dorso == prod_amount)
            {
               xret = true;
            }
            else
            {
               alert('Cantidad de pasadas descuadrada.');
               xret = false;
            }
         }
      }

      return xret;
   }
</script>
<?php