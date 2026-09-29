<?php
$_SHOW_DEFVALUES_TOP   = true;  //SHOW DEFAULT VALUES IN TOP SECTION
$_SHOW_DEFVALUES_TABLE = false;  //SHOW DEFAULT VALUES IN TABLE (MIXED)
$_SHOW_DEFAULT_STHID   = 8;      //SHOW DEFAULT STOREHOUSE 500

$_REQUEST["agid"] = (int)$_REQUEST["agid"];


/*?><script> alert("fancy.embalaje <?php echo $_REQUEST["agid"]; ?>");</script><?php */

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
unset($_SESSION["_CONF"]);


$sql = " select *
         from config_system";
$conf = $CON->select($sql);
$conf = $conf[0];
foreach(array_keys($conf) AS $ckey)
   $_SESSION["_CONF"][$ckey] = trim($conf[$ckey]);

require_once("../../../libs/lang/es.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

//----------------------------------------------------------------------------------
$hasopenot   = getProdOpenWorkerOTId($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"],$_REQUEST["agid"]);
$hasopeninit = getProdOpenWorkerInit($CON, $_SESSION["wrk_id"], $_SESSION["user_planta_id"]);

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

//----------------------------------------------------------------------------------
if($_REQUEST["mode"] == "save")
{
   $_REQUEST["axx_username"]  = trim(addslashes($_REQUEST["axx_username"]));
   $_REQUEST["axx_pass"]      = md5(trim($_REQUEST["axx_pass"]));

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
      $sql = " delete from prod_worker_embalajes
               where
               evt_prod_worker_otid = {$hasopenot["id"]} and
               evt_reqid            = {$hasopenot["prd_reqid"]}";
      $CON->no_result($sql);

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
               ({$hasopenot["id"]}, {$hasopenot["prd_reqid"]}, {$currtme}, '{$_REQUEST["req_embalaje_medidas_caja"]}',
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

      if((int)$_REQUEST["prodeventid"])
      {

         $sql = " update prod_worker_ot_events
                  set
                  evt_amount = {$_REQUEST["prodeventamt"]}
                  where
                  id = {$_REQUEST["prodeventid"]} ";
         $CON->no_result($sql);

      }
     
      ?>
      <script language="Javascript">
         parent.location.href='/prodwrk.php?mid=2&mode=terminarot&agid=<?=$_REQUEST["agid"]?>&req_codigo_cierre=<?=$_REQUEST["req_codigo_cierre"]?>';
      </script>
      <?php
      exit;
   }
   else
   {  ?>
      <script language="Javascript">
         alert("Usuario no valido para aprobaciones");
      </script>
      <?php
   }
}

$sql = " select *
         from prod_worker_embalajes
         where
         evt_prod_worker_otid = {$hasopenot["id"]} and
         evt_reqid            = {$hasopenot["prd_reqid"]}";
$embalajesaved = $CON->select($sql);
$embalajesaved = $embalajesaved[0];

//----------------------------------------------------------------------------------
$_REQUEST["total_ing"] = 0; 
$sql = " select *
         from prod_worker_ot_events
         where
         evt_prod_worker_otid = {$hasopenot["id"]} and
         evt_status           > 0
         order by evt_crtdat desc";
$events = $CON->select($sql);
foreach($events AS $event)
{
   if($event["evt_type"] == "prod")
   {
      $_PROD_EVENT = $event;
      $_REQUEST["total_pred"]    = printPrice($event["evt_amount"]);

   }
}

$sql = " select (req_embalaje_bolsas_por_caja_amt * req_embalaje_cajas_completas_amt)
                + req_embalaje_bolsas_sobrantes_amt + req_embalaje_caja_final + req_embalaje_showroom as 'TotalIngresadas' 
         from prod_worker_embalajes where evt_prod_worker_otid = {$hasopenot["id"]}  ";
$total_ingresada = $CON->select($sql);
$_REQUEST["prodeventamt"] =  $total_ingresada[0]["TotalIngresadas"];

function getProdOpenWorkerOTId($CON, $wrkid, $plantaid, $idagid)
{
   $hasopeninit = getProdOpenWorkerInit($CON, $wrkid, $plantaid);
   if((int)$hasopeninit["id"])
   {
      $sql = " select t1.*, t4.prd_number, t4.prd_reqid
                 from prod_worker_ot t1
                 INNER JOIN prod_worker_init t2   ON t1.wok_init_id = t2.id
                 INNER JOIN prod_agenda t3        ON t1.wok_ag_id = t3.id
                 INNER JOIN prod_header t4        ON t3.ag_prdid = t4.id
                 where
                 t1.wok_init_id = {$hasopeninit["id"]} and
                 t1.wok_ag_id   = {$idagid} and
                 t1.wok_status  = 1";
      $hasopenot = $CON->select($sql);
      $hasopenot = $hasopenot[0];
  
      return $hasopenot;
   }
   return false;
}

/* Cargar bodegas */
/*
$sql = "select st.id , st.st_name  
         from orders o
            inner join orders_items oi on o.id = oi.req_id
            inner join customer c on c.id = o.req_cust_id
            inner join item i on i.id = oi.item_id
            inner join company_shops_storehouses st on st.st_canal = c.cust_canal and st_status > 0
         where o.id = {$hasopenot["prd_reqid"]} ";
*/

$sql = "select st.id 
             , st.st_name
           from orders o 
               inner join customer c on c.id = o.req_cust_id 
               inner join bodegacanal bc on c.cust_canal = bc.bodegacanal_codigo_canal
               inner join company_shops_storehouses st on st.id = bc.bodegacanal_id_bodega
         where o.id = {$hasopenot["prd_reqid"]} ";
$bodegas = $CON->select($sql);

 /* fin carga de bodegas */

 $sql = " select * from parametros where tabla = 'CIERRES P' and (valor1 = 0 or valor1 = {$sw_cierre}) ";
 $cierres = $CON->select($sql);

 ?>
<html style="padding:0px;margin:0px;width:100%;height:100%">
<head>
   <title>Producción - Operador</title>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <link href="/css/fontawesome/css/all.min.css" rel="stylesheet" type="text/css">
   <link href="/css/style.css?ruid=<?=md5(microtime())?>" rel="stylesheet" type="text/css">
   <script language="Javascript"><?php require_once("../../../libs/jscripts/sourcen.php") ?></script>
   <link rel="stylesheet" href="/libs/jscripts/colorbox/colorbox.css">
   <script src="/libs/jscripts/colorbox/jquery.colorbox-min.js"></script>
</head>
<body style="background-color:#FFFFFF;padding:0px;margin:0px;width:100%;height:100%">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<input type="hidden" name="jschk_obitpanel" id="jschk_obitpanel" value="<?=(int)$_SESSION["jschk_obitpanel"]?>">
<input type="hidden" name="jschk_currenturl" id="jschk_currenturl" value="<?=$_SERVER["REQUEST_URI"]?>">
<form action="fancy.embalaje.termot.php" method="post" name="xform_inp" id="xform_inp"
onsubmit="var chk1 = checkform(new Array(this.req_embalaje_medidas_caja, this.req_embalaje_bolsas_por_caja_amt,
this.req_embalaje_cajas_por_pallet_amt, this.req_embalaje_palletcajas_incompletos_amt, this.req_embalaje_cajas_completas_amt,
this.req_embalaje_caja_final, this.req_embalaje_pallets_completos_amt, this.req_embalaje_bolsas_sobrantes_amt,
this.axx_username, this.axx_pass));if(chk1) { $('.cls_div_termwait_popup_btn').hide(0); $('#idx_div_termwait_popup').show(0);} return chk1;">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="mode" value="save">
<input type="hidden" name="prodeventid" value="<?=$_PROD_EVENT["id"]?>">
<input type="hidden" name="agid" value="<?=$_REQUEST["agid"]?>">
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="300">
   <col>
   <col width="220">
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
      value="<?if($_SHOW_DEFVALUES_TOP) { if($embalajesaved["req_embalaje_medidas_caja"] != "") echo $embalajesaved["req_embalaje_medidas_caja"]; else echo $headdata["req_embalaje_medidas_caja"]; }?>">
   </td>
</tr>
<tr>
   <td class="tdleft">Cantidad de bolsas por caja</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" id="id_req_embalaje_bolsas_por_caja_amt" style="width:200px;background-color:#EEEEEE" readonly
      value="<?if((int)$headdata["req_embalaje_bolsas_por_caja_amt"]) echo (int)$headdata["req_embalaje_bolsas_por_caja_amt"]?>" tabindex="-1">
   </td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="req_embalaje_bolsas_por_caja_amt" id="req_embalaje_bolsas_por_caja_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_bolsas_por_caja_amt"]) echo $embalajesaved["req_embalaje_bolsas_por_caja_amt"]; } ?>" autocomplete="off">
   </td>
</tr>
<tr>
   <td class="tdleft">Cantidad de cajas por pallets</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" id="id_req_embalaje_cajas_por_pallet_amt" style="width:200px;background-color:#EEEEEE" readonly
      value="<?if((int)$headdata["req_embalaje_cajas_por_pallet_amt"]) echo (int)$headdata["req_embalaje_cajas_por_pallet_amt"]?>" tabindex="-1">
   </td>
   <td class="tdnrm">
      <?if(!(int)$embalajesaved["req_embalaje_cajas_por_pallet_amt"]) $embalajesaved["req_embalaje_cajas_por_pallet_amt"]="0" ?>
      <input type="text" class="inptxt" name="req_embalaje_cajas_por_pallet_amt" style="width:200px"
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_cajas_por_pallet_amt"]) echo $embalajesaved["req_embalaje_cajas_por_pallet_amt"]; } ?>" autocomplete="off">
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
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_pallets_completos_amt"]) echo $embalajesaved["req_embalaje_pallets_completos_amt"]; } ?>" autocomplete="off">
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
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_palletcajas_incompletos_amt"]) echo $embalajesaved["req_embalaje_palletcajas_incompletos_amt"]; } ?>" autocomplete="off">
   </td>
</tr>
<tr>
   <td class="tdleft">Cantidad total de cajas completas</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" id="id_req_embalaje_cajas_completas_amt" style="width:200px;background-color:#EEEEEE" readonly
      value="<?if((int)$headdata["req_embalaje_cajas_completas_amt"]) echo (int)$headdata["req_embalaje_cajas_completas_amt"]?>" tabindex="-1">
   </td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="req_embalaje_cajas_completas_amt" id="req_embalaje_cajas_completas_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_cajas_completas_amt"]) echo $embalajesaved["req_embalaje_cajas_completas_amt"]; } ?>" autocomplete="off">
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
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_caja_final"]) echo $embalajesaved["req_embalaje_caja_final"]; } ?>" autocomplete="off">
   </td>
</tr>
<tr>
   <td class="tdleft">Bolsas sobrantes</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" style="width:200px;background-color:#EEEEEE" readonly value="" tabindex="-1">
   </td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="req_embalaje_bolsas_sobrantes_amt" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_bolsas_sobrantes_amt"]) echo $embalajesaved["req_embalaje_bolsas_sobrantes_amt"]; } ?>" autocomplete="off">
   </td>
</tr>
<tr>
   <td class="tdleft">Bolsas a Showroom</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" style="width:200px;background-color:#EEEEEE" readonly value="" tabindex="-1">
   </td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="req_embalaje_showroom" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
      value="<?if($_SHOW_DEFVALUES_TOP) { if((int)$embalajesaved["req_embalaje_showroom"]) echo $embalajesaved["req_embalaje_showroom"]; } ?>" autocomplete="off">
   </td>
</tr>
<tr>
   <td class="tdleft">Totales</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="total_pred" id="total_pred" style="width:200px;background-color:#EEEEEE" readonly tabindex="-1"
        value="<?echo $_REQUEST["total_pred"] ?>"
        autocomplete="off">
   </td>
   <td class="tdnrm">
       <input type="text" class="inptxt" name="prodeventamt" id="prodeventamt" style="width:200px;background-color:#EEEEEE" readonly tabindex="-1"
            value="<?if($_SHOW_DEFVALUES_TOP) { echo $_REQUEST["prodeventamt"]; } ?>"
            autocomplete="off">
   </td>
</tr>
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
    <td class="tdleft">Observación Supervisor</td>
    <td class="tdnrm" colspan="2">
         <textarea class="inptxt" name="req_observacion" id="req_observacion"
         style="width:100%;height:60px"><?=stripslashes($refevent["req_observacion"])?></textarea>
    </td>
</tr>
<tr>
   <td class="tdleft">Clasificación de Cierre</td>
   <td class="tdnrm" colspan="2">
        <select class="inptxt" style="width:360px" name="req_codigo_cierre" id="req_codigo_cierre" onchange="mostrarOcultarTabla()"
           onmousedown="markfield(this,0)" onblur="markfield(this,1)">
           <option value=" ">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
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
</table>
<div style="height:10px"></div>
<?php
if($sw_cierre == 2)
{
?>
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
?>
<div style="height:10px"></div>
<?php
}
?>

<?php
if(!(int)$_PROD_EVENT["id"])
{  ?>
   <table border="0" width="99%" cellpadding="3" cellspacing="0">
   <tr>
      <td style="padding-right:5px;background-color:red;color:white" align="center">
         <b>Alerta:</b> No se detectó un registro de producción ingresado.<br>
         Las cantidades de cajas y bolsas no se van a convertir en unidades producidas.
      </td>
   </tr>
   </table>
   <div style="height:10px"></div>
   <?php
}
?>

<table border="0" width="99%" cellpadding="0" cellspacing="0" class="cls_div_termwait_popup_btn">
<tr>
   <td width="120" style="padding-right:5px">
      <div class="btngrey" onclick="parent.$.colorbox.close();"
      style="float:left;width:120px">
         <i class="fa fa-fw fa-chevron-left" style="color:white;"></i> Volver&nbsp;
      </div>
   </td>
   <td></td>
   <td width="120" style="padding-right:5px">
      <div class="btngreen" onclick="submitForm(document.xform_inp)">
         <i class="fa fa-fw fa-save" style="color:white;"></i> Guardar&nbsp;
      </div>
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

<script language="Javascript">

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
      $('#prodeventamt').val(totalprod);
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
</script>

</body>
</html>
<?php