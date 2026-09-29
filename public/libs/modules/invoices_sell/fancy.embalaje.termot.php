<?php
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
     

      $sql = " insert into prod_worker_embalajes
               (evt_prod_worker_otid, evt_reqid, evt_crtdat, req_embalaje_medidas_caja, req_embalaje_bolsas_por_caja_amt,
                req_embalaje_cajas_por_pallet_amt, req_embalaje_pallets_completos_amt, req_embalaje_palletcajas_incompletos_amt,
                req_embalaje_cajas_completas_amt, req_embalaje_caja_final, req_embalaje_bolsas_sobrantes_amt, req_id_bodega,req_embalaje_showroom,
                req_codigo_cierre)
               VALUES
               ({$hasopenot["id"]}, {$hasopenot["prd_reqid"]}, {$currtme}, '{$_REQUEST["req_embalaje_medidas_caja"]}',
                {$_REQUEST["req_embalaje_bolsas_por_caja_amt"]}, {$_REQUEST["req_embalaje_cajas_por_pallet_amt"]},
                {$_REQUEST["req_embalaje_pallets_completos_amt"]}, {$_REQUEST["req_embalaje_palletcajas_incompletos_amt"]},
                {$_REQUEST["req_embalaje_cajas_completas_amt"]}, {$_REQUEST["req_embalaje_caja_final"]},
                {$_REQUEST["req_embalaje_bolsas_sobrantes_amt"]}, {$_REQUEST["req_id_bodega"]},
                {$_REQUEST["req_embalaje_showroom"]},{$_REQUEST["req_codigo_cierre"]})";
      $CON->no_result($sql);

      $_REQUEST["prodeventid"]  = (int)$_REQUEST["prodeventid"];
      $_REQUEST["prodeventamt"] = (int)$_REQUEST["prodeventamt"];

      if((int)$_REQUEST["prodeventid"])
      {
         $sql = " update prod_worker_ot_events
                  set
                  evt_amount = {$_REQUEST["prodeventamt"]}
                  where
                  id = {$_REQUEST["prodeventid"]}";
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
      $_REQUEST["total_pred"] = printPrice($event["evt_amount"]);
      $_REQUEST["total_ing"]  = printPrice($event["evt_amount"]);
   }
}

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
onsubmit="return checkform(new Array(this.req_embalaje_medidas_caja, this.req_embalaje_bolsas_por_caja_amt,
this.req_embalaje_cajas_por_pallet_amt, this.req_embalaje_palletcajas_incompletos_amt, this.req_embalaje_cajas_completas_amt,
this.req_embalaje_caja_final, this.req_embalaje_pallets_completos_amt, this.req_embalaje_bolsas_sobrantes_amt, this.axx_username, this.axx_pass))">
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
      value="<?if((int)$embalajesaved["req_embalaje_cajas_por_pallet_amt"]) echo $embalajesaved["req_embalaje_cajas_por_pallet_amt"] ?>" autocomplete="off">
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
   </td>
</tr>
<tr>
   <td class="tdleft">Totales</td>
   <td class="tdnrm">
      <input type="text" class="inptxt" name="total_pred" id="total_pred" style="width:200px;background-color:#EEEEEE" readonly tabindex="-1"
        value="<?php echo $headdata["req_embalaje_bolsas_por_caja_amt"] * $headdata["req_embalaje_cajas_completas_amt"] ?>"
        autocomplete="off">
   </td>
   <td class="tdnrm">
       <input type="text" class="inptxt" name="total_ing" id="total_ing" style="width:200px;background-color:#EEEEEE" readonly tabindex="-1"
            value="<?php echo $_REQUEST["total_ing"]?>"
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
</tr>
<tr>
   <td class="tdleft">Clasificación de Cierre</td>
   <td class="tdnrm" colspan="2">
        <select class="inptxt" style="width:360px" name="req_codigo_cierre" id="req_codigo_cierre"
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
<table border="1" width="99%" cellpadding="0" cellspacing="0">
   <colgroup>
      <col>
      <col width="100" >
      <col>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="tdheader" align="center" colspan="2">Detalle</td>
      <td class="tdheader" align="center" colspan="3">Cantidad de Bolsa</td>
   </tr>
   <tr>
      <td class="tdheader">C.C</td>
      <td class="tdheader" align="center">Medida Caja</td>
      <td class="tdheader" align="center">Por Caja</td>
      <td class="tdheader" align="center">Caja Final</td>
      <td class="tdheader" align="center">Sobrante</td>
   </tr>
   <tr>
      <?php
         for($x = 0; $x < count($detalle) && $detalle != false; $x++)
               {
                  ?>
                  <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_os"><?=$detalle[$x]["req_number"]?></td>
                     <td class="content_row_os"><?=$detalle[$x]["req_embalaje_medidas_caja"]?></td>
                     <td class="content_row_os"><?=$detalle[$x]["req_embalaje_bolsas_por_caja_amt"]?></td>
                     <td class="content_row_os"><?=$detalle[$x]["req_embalaje_caja_final"]?></td>
                     <td class="content_row_os"><?=$detalle[$x]["req_embalaje_mermaperc"]?></td>
                  </tr>
                  <?php
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

<div style="height:10px"></div>

   <table border="0" width="100%" cellpadding="0" cellspacing="0">
      <colgroup>
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="tdleft">Cantidad de Caja</td>
         <td class="tdnrm">
            <input type="text" class="inptxt" name="req_embalaje_showroom" style="width:200px" onkeyup="calcEmbalajeProdAmt()"
            value="<?if((int)$embalajesaved["req_embalaje_showroom"]) echo $embalajesaved["req_embalaje_showroom"]?>" autocomplete="off">
         </td>

      </tr>
      <tr>
         <td class="tdleft">Total de Bolsa</td>
         <td class="tdnrm">
            <input type="text" class="inptxt" style="width:200px;background-color:#EEEEEE" readonly value="" tabindex="-1">
         </td>
      </tr>
   </table>

<div style="height:10px"></div>

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

<table border="0" width="99%" cellpadding="0" cellspacing="0">
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

<script language="Javascript">
   function calcEmbalajeProdAmt()
   {
      var bolsas_por_caja_amt    = getIntegerFromValue(document.xform_inp.req_embalaje_bolsas_por_caja_amt.value);
      var cajas_completas_amt    = getIntegerFromValue(document.xform_inp.req_embalaje_cajas_completas_amt.value);
      var bolsas_sobrantes_amt   = getIntegerFromValue(document.xform_inp.req_embalaje_bolsas_sobrantes_amt.value);
      var bolsas_caja_final_amt  = getIntegerFromValue(document.xform_inp.req_embalaje_caja_final.value);
      var showroom               = getIntegerFromValue(document.xform_inp.req_embalaje_showroom.value);
      var totalprod              = parseInt((bolsas_por_caja_amt * cajas_completas_amt) +bolsas_sobrantes_amt +bolsas_caja_final_amt+showroom);
      $('#prodeventamt').val(totalprod);
      $('#total_ing').val(totalprod);
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