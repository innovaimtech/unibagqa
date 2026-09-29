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

      $sql = " insert into prod_worker_embalajes
               (evt_prod_worker_otid, evt_reqid, evt_crtdat, req_embalaje_medidas_caja, req_embalaje_bolsas_por_caja_amt,
                req_embalaje_cajas_por_pallet_amt, req_embalaje_pallets_completos_amt, req_embalaje_palletcajas_incompletos_amt,
                req_embalaje_cajas_completas_amt, req_embalaje_caja_final, req_embalaje_bolsas_sobrantes_amt)
               VALUES
               ({$hasopenot["id"]}, {$hasopenot["prd_reqid"]}, {$currtme}, '{$_REQUEST["req_embalaje_medidas_caja"]}',
                {$_REQUEST["req_embalaje_bolsas_por_caja_amt"]}, {$_REQUEST["req_embalaje_cajas_por_pallet_amt"]},
                {$_REQUEST["req_embalaje_pallets_completos_amt"]}, {$_REQUEST["req_embalaje_palletcajas_incompletos_amt"]},
                {$_REQUEST["req_embalaje_cajas_completas_amt"]}, {$_REQUEST["req_embalaje_caja_final"]},
                {$_REQUEST["req_embalaje_bolsas_sobrantes_amt"]})";
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
         parent.location.href='/prodwrk.php?mid=2&mode=terminarot&agid=<?=$_REQUEST["agid"]?>';
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
      $_PROD_EVENT = $event;
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
<input type="hidden" name="prodeventamt" id="prodeventamt" value="<?=(int)$_PROD_EVENT["evt_amount"]?>">
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
   <td class="tdleft">Usuario supervisor</td>
   <td class="tdnrm" colspan="2">
      <input type="text" class="inptxt" name="axx_username" id="axx_username" placeholder="Usuario Supervisor"
      style="width:200px;" autocomplete="off">
   </td>
</tr>
<tr>
   <td class="tdleft">Contraseña supervisor</td>
   <td class="tdnrm" colspan="2">
      <input type="password" class="inptxt" name="axx_pass" placeholder="Contraseña Supervisor"
      style="width:200px;" autocomplete="off">
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
      var totalprod              = parseInt((bolsas_por_caja_amt * cajas_completas_amt) +bolsas_sobrantes_amt +bolsas_caja_final_amt);

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