<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["cfi_startdate"]    = trim(addslashes($_REQUEST["cfi_startdate"]));
   $_REQUEST["cfi_desc"]         = trim(addslashes($_REQUEST["cfi_desc"]));
   $_REQUEST["cfi_workerid"]     = (int)$_REQUEST["cfi_workerid"];
   $_REQUEST["cfi_inc_id"]       = (int)$_REQUEST["cfi_inc_id"];
   $_REQUEST["sql_instid_old"]   = (int)$_REQUEST["sql_instid_old"];
   $_REQUEST["cfi_wrk_opt"]      = (int)$_REQUEST["cfi_wrk_opt"];
   $_REQUEST["sql_lastturnoid"]  = (int)$_REQUEST["sql_lastturnoid"];

   $_REQUEST["cfi_startdate"]    = explode(".", trim($_REQUEST["cfi_startdate"]));
   $_REQUEST["cfi_startdate"]    = (int)mktime(0, 0, 0, $_REQUEST["cfi_startdate"][1], $_REQUEST["cfi_startdate"][0], $_REQUEST["cfi_startdate"][2]);
   $_REQUEST["cfi_enddate"]      = explode(".", trim($_REQUEST["cfi_enddate"]));
   $_REQUEST["cfi_enddate"]      = (int)mktime(23, 59, 59, $_REQUEST["cfi_enddate"][1], $_REQUEST["cfi_enddate"][0], $_REQUEST["cfi_enddate"][2]);
   $_REQUEST["cfi_lastequipoid"] = (int)$_REQUEST["sql_new_equipo"];
   
   //----------------------------------------------------------------------------------
   $puestorelevoarr              = explode("#", $_REQUEST["sql_puestorelevo_new"]);
   $cfi_relevotran_id            = (int)$puestorelevoarr[0];
   $cfi_relevotran_type          = $puestorelevoarr[1];
   $cfi_puestofijo_balid         = (int)$_REQUEST["sql_puestofijo_new"];

   if($_REQUEST["cfi_wrk_opt"] != 2)
   {
      $cfi_relevotran_id   = 0;
      $cfi_relevotran_type = "";
   }
   if($_REQUEST["cfi_wrk_opt"] != 1)
      $cfi_puestofijo_balid = 0;

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into turnos_config_incidencias
               (cfi_startdate, cfi_enddate, cfi_crtusr, cfi_crtdat, cfi_desc, cfi_workerid, cfi_inc_id,
                cfi_old_instid, cfi_wrk_opt, cfi_relevotran_id, cfi_relevotran_type, cfi_puestofijo_balid,
                cfi_workertype, cfi_lastturnoid, cfi_lastequipoid)
               VALUES
               ({$_REQUEST["cfi_startdate"]}, {$_REQUEST["cfi_enddate"]}, {$_SESSION["user_id"]}, {$currtme},
                '{$_REQUEST["cfi_desc"]}', {$_REQUEST["cfi_workerid"]}, {$_REQUEST["cfi_inc_id"]},
                {$_REQUEST["sql_instid_old"]}, {$_REQUEST["cfi_wrk_opt"]}, {$cfi_relevotran_id},
                '{$cfi_relevotran_type}', {$cfi_puestofijo_balid}, 'worker', {$_REQUEST["sql_new_turno"]},
                {$_REQUEST["sql_new_equipo"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from turnos_config_incidencias
                  where
                  cfi_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];

         $_REQUEST["id"] = $thisid;
      }
   }
   else
   {
      $sql = " update turnos_config_incidencias
               set
               cfi_startdate        = {$_REQUEST["cfi_startdate"]},
               cfi_enddate          = {$_REQUEST["cfi_enddate"]},
               cfi_desc             = '{$_REQUEST["cfi_desc"]}',
               cfi_workerid         = {$_REQUEST["cfi_workerid"]},
               cfi_workertype       = 'worker',
               cfi_inc_id           = {$_REQUEST["cfi_inc_id"]},
               cfi_old_instid       = {$_REQUEST["sql_instid_old"]},
               cfi_wrk_opt          = {$_REQUEST["cfi_wrk_opt"]},
               cfi_relevotran_id    = {$cfi_relevotran_id},
               cfi_relevotran_type  = '{$cfi_relevotran_type}',
               cfi_puestofijo_balid = {$cfi_puestofijo_balid},
               cfi_lastturnoid      = {$_REQUEST["sql_new_turno"]},
               cfi_lastequipoid     = {$_REQUEST["sql_new_equipo"]},
               cfi_updusr           = {$_SESSION["user_id"]},
               cfi_upddat           = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      if((int)$_REQUEST["finalize"])
      {
         $sql = " update turnos_config_incidencias
                  set
                  cfi_status = 2
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);

         $sql = " select *
                  from turnos_config_assign
                  where
                  assign_worker_id     = {$_REQUEST["cfi_workerid"]} and
                  assign_stamp         between {$_REQUEST["cfi_startdate"]} and {$_REQUEST["cfi_enddate"]} and
                  assign_special_slot  = 0 and
                  assign_planta_id     = {$_SESSION["mgmt_balance"]["sql_planta_id"]}";
         $assigns = $CON->select($sql);

         foreach($assigns AS $assign)
         {
            $sql = " delete from turnos_config_assign
                     where
                     id = {$assign["id"]}";
            $CON->no_result($sql);
         }

         $sql = " select *
                  from turnos_config
                  where
                  cfg_turno_id   = {$_REQUEST["sql_new_turno"]} and
                  cfg_planta_id  = {$_SESSION["mgmt_balance"]["sql_planta_id"]} and
                  cfg_datestamp   between {$_REQUEST["cfi_startdate"]} and {$_REQUEST["cfi_enddate"]}";
         $turnos_configs = $CON->select($sql);
         foreach($turnos_configs AS $turnos_config)
         {
            $dayarr = explode(".", $turnos_config["cfg_datestr"]);
            $dayarr[0] = (int)$dayarr[0];
            $dayarr[1] = (int)$dayarr[1];
            
            $sql = " insert into turnos_config_assign
                     (assign_day, assign_month, assign_year, assign_stamp, assign_turno_type_id,
                      assign_worker_id, assign_worker_type, assign_special_slot, assign_turno_id,
                      assign_planta_id, assign_equipoaid)
                     VALUES
                     ({$dayarr[0]}, {$dayarr[1]}, {$dayarr[2]}, {$turnos_config["cfg_datestamp"]}, {$turnos_config["cfg_turno_type_id"]},
                      {$_REQUEST["cfi_workerid"]}, 'worker', 0, {$turnos_config["cfg_turno_id"]}, '{$_SESSION["mgmt_balance"]["sql_planta_id"]}',
                      {$_REQUEST["sql_new_equipo"]})";
            $CON->no_result($sql);
         }
      }
   }

   $savemsg = getSaveMessage($res);
}

if((int)$_REQUEST["revert"])
{
}
      
if($_REQUEST["id"] == "")
{
   $title = "Agregar cambio de turno";
}
else
{
   $title = "Cambiar cambio de turno";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname',
            t3x.inc_name
            from turnos_config_incidencias t1
            LEFT OUTER JOIN user t2          ON t1.cfi_updusr = t2.id
            LEFT OUTER JOIN user t3          ON t1.cfi_crtusr = t3.id
            LEFT OUTER JOIN incidencias t3x  ON t1.cfi_inc_id = t3x.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $incdata = $CON->select($sql);
   $incdata = $incdata[0];
}

if((int)$incdata["cfi_status"] <= 2)
{
   $incidencias = getIncidencias($CON, "cambio");

   if($_REQUEST["cfi_startdate_dummy"] == "")
   {
      $_REQUEST["cfi_startdate"] = date("d.m.Y", $incdata["cfi_startdate"]);

      if(!(int)$incdata["cfi_startdate"])
      {
         $_REQUEST["cfi_startdate"] = date("d").".".sprintf("%02s", $_SESSION["mgmt_balance"]["sql_month1"]).".".$_SESSION["mgmt_balance"]["sql_year1"];
      }
   }

   $xdate   = explode(".", $_REQUEST["cfi_startdate"]);
   $xdate   = mktime(15, 0, 0, $xdate[1], $xdate[0], $xdate[2]);
   $xmonth  = (int)date("m", $xdate);
   $xyear   = (int)date("Y", $xdate);
            

   $sql = " select distinct t1.*
            FROM workers t1
            WHERE
            t1.wrk_status        > 0 and
            t1.wrk_turno_state   = 1 and
            t1.id = {$_REQUEST["wid"]} 
            order by t1.wrk_lastname, t1.wrk_firstname";
   $workers = $CON->select($sql);
   $_CURRENT_TURNOID = $workers[0]["trn_turnid"];
}

$datecls = "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency";

$_DIVWIDTH = 822;
$_FORMURL  = "index.php";
if($_FROMFANCY)
{
   $_DIVWIDTH = "99%";
   $_FORMURL  = "/iframe.fancy.php";
}
?>
<script language="Javascript">
function setWorkerCurrentInst(xval)
{
   var xarr = xval.split("#");
   document.getElementById('sql_instid_old').value = xarr[1];
   document.getElementById('sql_instid_new').selectedIndex = 0;
}
</script>
<div id="idx_filteroutput"></div>
<style type="text/css"><!-- @import url(../libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="../libs/jscripts/datepicker/datepicker.js"></script>
<?php
if(!$_FROMFANCY)
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header"><?=$title?></b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?php
}
?>
<script language="JavaScript">
function checkCambioForm(xobj)
{
   var precheck = checkform(new Array(xobj.cfi_desc, xobj.cfi_workerid, xobj.cfi_inc_id, xobj.cfi_startdate, xobj.cfi_enddate, xobj.sql_new_turno, xobj.sql_new_equipo));
   if(!precheck)
      return false;
   return true;
}
</script>
<form action="<?=$_FORMURL?>" method="post" name="idx_giro" class="fokusfirst"
onsubmit="<?php if((int)$incdata["cfi_status"] == 2) echo "return false"; else { ?> return checkCambioForm(this) <?php } ?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="finalize" value="">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="custid" value="<?=$_REQUEST["custid"]?>">
<input type="hidden" name="cfi_startdate_dummy" value="">
<?php
if($_FROMFANCY)
{  ?>
   <input type="hidden" name="xidx" value="<?=$_REQUEST["xidx"]?>">
   <input type="hidden" name="wid" value="<?=$_REQUEST["wid"]?>">
   <input type="hidden" name="wtype" value="<?=$_REQUEST["wtype"]?>">
   <input type="hidden" name="module" value="<?=$_REQUEST["module"]?>">
   <input type="hidden" name="multimode" value="<?=$_REQUEST["multimode"]?>">
   <input type="hidden" name="multistart" value="<?=$_REQUEST["multistart"]?>">
   <input type="hidden" name="multiend" value="<?=$_REQUEST["multiend"]?>">
   <?php
   if($_REQUEST["id"] == "")
      $_SESSION["JSEXEC"] .= ";setWorkerCurrentInst(document.idx_giro.cfi_workerid.value);";
}
?>
<?=Nifty_printH("box1", $_DIVWIDTH)?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="160">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos del Cambio</td>
</tr>
<?php
if((int)$incdata["cfi_status"] == 2)
{  ?>
   <tr>
      <td class="content_rowl" height="28">Trabajador</td>
      <td class="content_row">
         <?=$wdata["wrk_lastname"]?>, <?=$wdata["wrk_firstname"]?>, RUT <?=$wdata["wrk_rut"]?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl" height="28">Incidencia</td>
      <td class="content_row"><?=$incdata["inc_name"]?></td>
   </tr>
   <?php
   $rdlo       = "readonly";
   $datecls    = "";
   ?>
   <tr>
      <td class="content_rowl">Fecha Inicio *</td>
      <td class="content_row">
         <?php
         if($_REQUEST["subexec"] == "reload")
         {
            $xdate = explode(".", $_REQUEST["cfi_startdate_dummy"]);
            $incdata["cfi_startdate"] = mktime(15, 0, 0, $xdate[1], $xdate[0], $xdate[2]);
         }
         ?>
         <input name="cfi_startdate" id="cfi_startdate" type="text" style="width:85px" readonly
         onchange="document.idx_giro.subexec.value='reload';document.idx_giro.cfi_startdate_dummy.value=this.value;document.idx_giro.submit();"
         class="text <?=$datecls?> range-low-<?=date("Y-m-d", $sql_datefrom)?>" <?=$rdlo?>
         value="<?if((int)$incdata["cfi_startdate"]) echo date("d.m.Y", $incdata["cfi_startdate"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         &nbsp;&nbsp; (corresponde al dia de inicio en el nuevo turno)
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Fecha Termino *</td>
      <td class="content_row">
         <input name="cfi_enddate" id="cfi_enddate" type="text" style="width:85px" readonly
         class="text <?=$datecls?>" <?=$rdlo?>
         value="<?if((int)$incdata["cfi_enddate"]) echo date("d.m.Y", $incdata["cfi_enddate"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         &nbsp;&nbsp; (corresponde al ultimo dia en el nuevo turno)
      </td>
   </tr>
   <?php
}
else
{  ?>
   <tr>
      <td class="content_rowl">Fecha Inicio *</td>
      <td class="content_row">
         <?php
         if($_REQUEST["subexec"] == "reload")
         {
            $xdate = explode(".", $_REQUEST["cfi_startdate_dummy"]);
            $incdata["cfi_startdate"] = mktime(15, 0, 0, $xdate[1], $xdate[0], $xdate[2]);
         }
         ?>
         <input name="cfi_startdate" id="cfi_startdate" type="text" style="width:85px" readonly
         onchange="document.idx_giro.subexec.value='reload';document.idx_giro.cfi_startdate_dummy.value=this.value;document.idx_giro.submit();"
         class="text <?=$datecls?> range-low-<?=date("Y-m-d", $sql_datefrom)?>" <?=$rdlo?>
         value="<?if((int)$incdata["cfi_startdate"]) echo date("d.m.Y", $incdata["cfi_startdate"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         &nbsp;&nbsp; (corresponde al dia de inicio en el nuevo turno)
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Fecha Termino</td>
      <td class="content_row">
         <input name="cfi_enddate" id="cfi_enddate" type="text" style="width:85px" readonly
         class="text <?=$datecls?>" <?=$rdlo?>
         value="<?if((int)$incdata["cfi_enddate"]) echo date("d.m.Y", $incdata["cfi_enddate"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Trabajador *</td>
      <td class="content_row">
         <select class="text" style="width:100%" name="cfi_workerid"
         onchange="setWorkerCurrentInst(this.value)">
            <?php
            if(!$_FROMFANCY)
            {  ?>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
            }
            foreach($workers AS $worker)
            {  ?>
               <option value="<?=$worker["id"]?>#<?=$worker["wrk_turno_installid"]?>" <?php if($worker["id"] == $incdata["cfi_workerid"]) echo "selected"?>>
                  <?=$worker["wrk_lastname"]?>, <?=$worker["wrk_firstname"]?>, RUT <?=$worker["wrk_rut"]?>
               </option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Tipo *</td>
      <td class="content_row">
         <select class="text" style="width:100%" name="cfi_inc_id">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($incidencias AS $incidencia)
            {  ?>
               <option value="<?=$incidencia["id"]?>" <?php if($incidencia["id"] == $incdata["cfi_inc_id"]) echo "selected"?>><?=$incidencia["inc_name"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <?php
}

//----------------------------------------------------------------------------------
$month = (int)$_SESSION["mgmt_balance"]["sql_month1"];
$year  = (int)$_SESSION["mgmt_balance"]["sql_year1"];

//----------------------------------------------------------------------------------
$date_init  = mktime(0, 0, 0, $month, 1, $year);
$date_end   = mktime(23, 59, 59, $month, 15, $year);
$datedays   = date('t', $date_end);
$date_end   = mktime(23, 59, 59, $month, $datedays, $year);


//----------------------------------------------------------------------------------
$sql = " select distinct t3.jorn_name, t2.turn_name, t2.id
         from turnos_config t1
         INNER JOIN turnos t2          ON t1.cfg_turno_id = t2.id
         INNER JOIN turnos_jornadas t3 ON t2.turn_jornada_id = t3.id
         where
         t1.cfg_datestamp     between {$date_init} and {$date_end} and
         t1.cfg_planta_id     = {$_SESSION["mgmt_balance"]["sql_planta_id"]} and
         t2.turn_status       > 0 and
         t3.jorn_status       > 0
         order by 1,2";
$turnos = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.equipo_name
         from equipo t1
         where
         t1.equipo_status > 0 and
         t1.equipo_planta_id = {$_SESSION["mgmt_balance"]["sql_planta_id"]}
         order by 2";
$equipos = $CON->select($sql);
?>
<tr>
   <td class="content_rowl">Cambiar turno a *</td>
   <td class="content_row">
      <input type="hidden" name="sql_lastturnoid" value="<?=$_CURRENT_TURNOID?>"> 
      <select class="text" style="width:100%" name="sql_new_turno" id="sql_new_turno"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($turnos AS $turno)
         {  ?>
            <option value="<?=$turno["id"]?>" <?if($turno["id"] == $incdata["cfi_lastturnoid"]) echo "selected"?>><?=$turno["jorn_name"]?> - <?=$turno["turn_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Cambiar máquina a *</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="sql_new_equipo" id="sql_new_equipo"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($equipos AS $equipo)
         {  ?>
            <option value="<?=$equipo["id"]?>" <?if($equipo["id"] == $incdata["cfi_lastequipoid"]) echo "selected"?>><?=$equipo["equipo_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<?php
//----------------------------------------------------------------------------------
if(!(int)$incdata["cfi_startdate"])
   $incdata["cfi_startdate"] = time();
   
$start_month = date("m", $incdata["cfi_startdate"]);
$start_year  = date("Y", $incdata["cfi_startdate"]);

//----------------------------------------------------------------------------------
$currtme = time();
$pfijos = Array();

//----------------------------------------------------------------------------------
$sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$datedays      = date('t', $sql_dateto);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month1"], $datedays, $_SESSION[$_sesmodulename]["sql_year1"]);

//----------------------------------------------------------------------------------
$incdata["cfi_wrk_opt"] = 4;
?>
<script language="JavaScript">
function execFilters()
{
   var custid = $('#sql_filtercust').val();
   var instid = $('#sql_filterinst').val();
   
   var dataString = "currtme=<?=$currtme?>&start_month=<?=$start_month?>&start_year=<?=$start_year?>&custid=" +custid +"&instid=" +instid;
   $.ajax({
      type:       "POST",
      cache:      false,
      url:        "/libs/modules/worker_management_cambiosucursal/jq.filters.php",
      data:       dataString,
      dataType:   "html",
      success: function(res)
      {
         $('#idx_filteroutput').html(res);
      }
   });
}
</script>
<input type="hidden" name="cfi_wrk_opt" value="4">
<tr class="sqlnewoptcls" id="idx_puestofijo_opt" style="<?php if((int)$incdata["cfi_wrk_opt"] != 1) echo "display:none"?>">
   <td class="content_rowl">Nuevo Puesto fijo *</td>
   <td class="content_row">
      <nobr>
      <select class="text" style="width:<?if($_REQUEST["id"] == "") echo "305px"; else echo "100%"?>" name="sql_puestofijo_new" id="sql_puestofijo_new"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         for($x = 0; $x < count($pfijos); $x++)
         {  ?>
            <option value="<?=$pfijos[$x]["id"]?>" <?php if($incdata["cfi_puestofijo_balid"] == $pfijos[$x]["id"]) echo "selected"?>>
               <?=sprintf("%02s", $pfijos[$x]["bal_month"])?>-<?=$pfijos[$x]["bal_year"]?>:
               <?=$pfijos[$x]["cust_name"]?> - P_<?=$pfijos[$x]["data_puesto_index"]?>
            </option>
            <?php
         }
         ?>
      </select>
      <?php
      if($_REQUEST["id"] == "")
      {  ?>
         &nbsp;Filtrar Cliente:
         <select class="text" style="width:140px" name="sql_filtercust" id="sql_filtercust"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="$('#sql_filterinst').val('');execFilters()">
            <option value="">Seleccione</option>
            <?php
            foreach(array_keys($_CLIENTES) AS $filterclienteid)
            {  ?>
               <option value="<?=$filterclienteid?>"><?=$_CLIENTES[$filterclienteid]?></option>
               <?php
            }
            ?>
         </select>
         &nbsp;Filtrar Instalación:
         <select class="text" style="width:140px" name="sql_filterinst" id="sql_filterinst"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="execFilters()">
            <option value="">Seleccione</option>
         </select>
         <?php
      }
      ?>
      </nobr>
   </td>
</tr>
<tr class="sqlnewoptcls" id="idx_puestorelevo_opt" style="<?php if((int)$incdata["cfi_wrk_opt"] != 2) echo "display:none"?>">
   <td class="content_rowl">Nuevo Puesto Relevo *</td>
   <td class="content_row">
      <select class="text" style="width:100%" name="sql_puestorelevo_new" id="sql_puestorelevo_new"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         for($x = 0; $x < count($relevodispos) && $relevodispos != false; $x++)
         {
            $dsptype = "RELEVO PUESTO FIJO";
            if($relevodispos[$x]["type"] == "relevoplan")
            {
               $dsptype = sprintf("%02s", $relevodispos[$x]["plan_month"])."-".$relevodispos[$x]["plan_year"]." - LIBRE PARCIAL";
            }
            ?>
            <option value="<?=$relevodispos[$x]["id"]?>#<?=$relevodispos[$x]["type"]?>"
            <?php if($incdata["cfi_relevotran_id"] == $relevodispos[$x]["id"] && $incdata["cfi_relevotran_type"] == $relevodispos[$x]["type"]) echo "selected"?>>
               <?=$dsptype?>: 
               <?=$relevodispos[$x]["inst_names"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción/<br>Motivo *</td>
   <td class="content_row">
      <textarea class="text" style="width:100%;height:72px" name="cfi_desc" <?=$rdlo?>><?=stripslashes($incdata["cfi_desc"])?></textarea>
   </td>
</tr>
<?php
if($incdata["cfi_crtusr"] != "")
{  ?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
	   <td class="content_row"><?php if($incdata["cfi_crtusr"] != "") echo "{$incdata["crt_firstname"]} {$incdata["crt_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
	   <td class="content_row"><?php if($incdata["cfi_crtusr"] != "") echo displayDate($incdata["cfi_crtdat"])?>&nbsp;</td>
	</tr>
	<?php
}
if($incdata["cfi_updusr"] != "")
{	?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
	   <td class="content_row"><?php if($incdata["cfi_updusr"] != "") echo "{$incdata["upd_firstname"]} {$incdata["upd_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
	   <td class="content_row"><?php if($incdata["cfi_updusr"] != "") echo displayDate($incdata["cfi_upddat"])?>&nbsp;</td>
	</tr>
	<?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", $_DIVWIDTH)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130" style="padding-right:5px">
         <?php
         if($_FROMFANCY)
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "iframe.fancy.php?{$querystr}&module={$_REQUEST["module"]}", "", "arrow-180");
         else
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
      if((int)$incdata["cfi_status"] == 1)
      {  ?>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            if($_FROMFANCY)
               printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('iframe.fancy.php?{$querystr}&module={$_REQUEST["module"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
            else
               printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   else
   {
      if($_FROMFANCY)
      {  ?>
         <td align="left" width="130" style="padding-right:5px">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "iframe.fancy.php?{$querystr}&module={$_REQUEST["module"]}", "", "arrow-180");
            ?>
         </td>
         <?php
      }
      ?>
      <td>&nbsp;</td>
      <?php
   }

   if((int)$incdata["cfi_status"] != 2)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.idx_giro)", "disk-black");
         ?>
      </td>
      <?php
   }
   if((int)$incdata["cfi_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-left:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.idx_giro.finalize.value='1';submitForm(document.idx_giro) }", "tick-circle-frame");
         ?>
      </td>
      <?php
   }

   /*
   if((int)$incdata["cfi_status"] == 2 && (int)$incdata["cfi_wrk_opt"] != 1 && (int)$incdata["cfi_wrk_opt"] != 2)
   {  ?>
      <td align="right" width="130" style="padding-left:5px">
         <?php
         if($_FROMFANCY)
            printButton("Restablecer", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){ location.href='iframe.fancy.php?{$querystr}&module={$_REQUEST["module"]}&exec=edit&id={$_REQUEST["id"]}&revert=1';}", "arrow-circle-045-left");
         else
            printButton("Restablecer", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){ location.href='index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&revert=1';}", "arrow-circle-045-left");
         ?>
      </td>
      <?php
   }
   */
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_giro');" ?>
<?php
if($jsmsg)
   $_SESSION["JSEXEC"] .= "alert('Los datos del trabajador fueron eliminados del proximo mes.');";