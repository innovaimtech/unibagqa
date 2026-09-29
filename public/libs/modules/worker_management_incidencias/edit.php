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
   $_REQUEST["cfi_enddate"]      = trim(addslashes($_REQUEST["cfi_enddate"]));
   $_REQUEST["cfi_desc"]         = trim(addslashes($_REQUEST["cfi_desc"]));
   $_REQUEST["cfi_workerid"]     = (int)$_REQUEST["cfi_workerid"];
   $_REQUEST["cfi_inc_id"]       = (int)$_REQUEST["cfi_inc_id"];

   $_REQUEST["cfi_startdate"]    = explode(".", trim($_REQUEST["cfi_startdate"]));
   $_REQUEST["cfi_startdate"]    = (int)mktime(0, 0, 0, $_REQUEST["cfi_startdate"][1], $_REQUEST["cfi_startdate"][0], $_REQUEST["cfi_startdate"][2]);
   $_REQUEST["cfi_enddate"]      = explode(".", trim($_REQUEST["cfi_enddate"]));
   $_REQUEST["cfi_enddate"]      = (int)mktime(23, 59, 59, $_REQUEST["cfi_enddate"][1], $_REQUEST["cfi_enddate"][0], $_REQUEST["cfi_enddate"][2]);

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into turnos_config_incidencias
               (cfi_startdate, cfi_enddate, cfi_crtusr, cfi_crtdat, cfi_desc, cfi_workerid, cfi_inc_id, cfi_workertype)
               VALUES
               ({$_REQUEST["cfi_startdate"]}, {$_REQUEST["cfi_enddate"]}, {$_SESSION["user_id"]}, {$currtme},
                '{$_REQUEST["cfi_desc"]}', {$_REQUEST["cfi_workerid"]}, {$_REQUEST["cfi_inc_id"]}, '{$_REQUEST["wtype"]}')";
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
               cfi_startdate  = {$_REQUEST["cfi_startdate"]},
               cfi_enddate    = {$_REQUEST["cfi_enddate"]},
               cfi_desc       = '{$_REQUEST["cfi_desc"]}',
               cfi_workerid   = {$_REQUEST["cfi_workerid"]},
               cfi_workertype = '{$_REQUEST["wtype"]}',
               cfi_inc_id     = {$_REQUEST["cfi_inc_id"]},
               cfi_updusr     = {$_SESSION["user_id"]},
               cfi_upddat     = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

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
                  assign_worker_type   = '{$_REQUEST["wtype"]}' and
                  assign_stamp         between {$_REQUEST["cfi_startdate"]} and {$_REQUEST["cfi_enddate"]}";
         $assigns = $CON->select($sql);
         foreach($assigns AS $assign)
         {
            $sql = " update turnos_config_assign
                     set
                     incidencia_cfi_id = {$_REQUEST["id"]}
                     where
                     id = {$assign["id"]}";
            $CON->no_result($sql);

            //createSpecialSlotFromIncident($CON, $assign["id"], $_REQUEST["id"]);
         }
      }
   }

   $savemsg = getSaveMessage($res);
}

if((int)$_REQUEST["revert"])
{
   $currtme = time();
   $sql = " update turnos_config_incidencias
            set
            cfi_status     = 1,
            cfi_updusr     = {$_SESSION["user_id"]},
            cfi_upddat     = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $sql = " select t1.*
            from turnos_config_incidencias t1
            where
            t1.id = {$_REQUEST["id"]} ";
   $incdata = $CON->select($sql);
   $incdata = $incdata[0];

   $sql = " select *
            from turnos_config_assign
            where
            assign_worker_id     = {$incdata["cfi_workerid"]} and
            assign_worker_type   = '{$_REQUEST["wtype"]}' and
            assign_stamp         between {$incdata["cfi_startdate"]} and {$incdata["cfi_enddate"]} and
            incidencia_cfi_id    = {$_REQUEST["id"]}";
   $assigns = $CON->select($sql);
   foreach($assigns AS $assign)
   {
      $sql = " update turnos_config_assign
               set
               incidencia_cfi_id = 0
               where
               id = {$assign["id"]}";
      $CON->no_result($sql);
   }

   $sql = " delete from turnos_config_assign
            where
            assign_special_slot_fromcfi_id = {$_REQUEST["id"]} and
            assign_worker_id = 0";
   $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}
      
if($_REQUEST["id"] == "")
{
   $title = "Agregar Incidencia";
}
else
{
   $title = "Cambiar Incidencia";

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
   $incidencias = getIncidencias($CON, "inc");

   if($_REQUEST["wtype"] == "supervisor")
   {
      $sql = " select t1.id, t1.user_firstname 'wrk_firstname', t1.user_lastname 'wrk_lastname', 
               t1.user_telephone 'wrk_telefono1', t1.user_cellphone 'wrk_telefono2',
               t1.user_mail 'wrk_email', t1.user_rut 'wrk_rut', t1.user_pic 'wrk_foto',
               t1.user_titulo 'wrk_titulo', t2.type_name, 'supervisor' AS 'type'
               from user t1
               LEFT OUTER JOIN workers_types t2 ON t1.user_cargoid = t2.id
               where
               t1.id = {$_REQUEST["wid"]}";
      $workers = $CON->select($sql);
   }
   else
   {
      $sql = " select t1.id, t1.wrk_firstname, t1.wrk_lastname, t1.wrk_rut, t1.wrk_folio
               FROM workers t1
               WHERE
               t1.wrk_status        > 0 and
               t1.wrk_turno_state   = 1 and
               t1.id = {$_REQUEST["wid"]}
               order by t1.wrk_lastname, t1.wrk_firstname";
      $workers = $CON->select($sql);
   }
}
$datecls = "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency";

$_DIVWIDTH = 822;
$_FORMURL  = "index.php";
if($_FROMFANCY)
{
   $_DIVWIDTH = "99%";
   $_FORMURL  = "/iframe.fancy.php";
}

//----------------------------------------------------------------------------------
$sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
$datedays      = date('t', $sql_dateto);
$sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month1"], $datedays, $_SESSION[$_sesmodulename]["sql_year1"]);
?>
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
<form action="<?=$_FORMURL?>" method="post" name="idx_giro" class="fokusfirst"
onsubmit="<?php if((int)$incdata["cfi_status"] == 2) echo "return false"; else { ?> return checkform(new Array(this.cfi_workerid, this.cfi_inc_id, this.cfi_startdate, this.cfi_enddate)) <?php } ?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="finalize" value="">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="custid" value="<?=$_REQUEST["custid"]?>">
<?php
if($_FROMFANCY)
{  ?>
   <input type="hidden" name="startdate" value="<?=$_REQUEST["startdate"]?>">
   <input type="hidden" name="wid" value="<?=$_REQUEST["wid"]?>">
   <input type="hidden" name="wtype" value="<?=$_REQUEST["wtype"]?>">
   <input type="hidden" name="module" value="<?=$_REQUEST["module"]?>">
   <input type="hidden" name="enddate" value="<?=$_REQUEST["enddate"]?>">
   <input type="hidden" name="plantaid" value="<?=$_REQUEST["plantaid"]?>">
   <?php
}
?>
<?=Nifty_printH("box1", $_DIVWIDTH)?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de Incidencia</td>
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
}
else
{  ?>
   <tr>
      <td class="content_rowl">Trabajador *</td>
      <td class="content_row">
         <select class="text" style="width:100%" name="cfi_workerid">
            <?php
            if(!$_FROMFANCY)
            {  ?>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
            }
            foreach($workers AS $worker)
            {  ?>
               <option value="<?=$worker["id"]?>" <?php if($worker["id"] == $incdata["cfi_workerid"]) echo "selected"?>>
                  <?=$worker["wrk_lastname"]?>, <?=$worker["wrk_firstname"]?>, RUT <?=$worker["wrk_rut"]?>
               </option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Incidencia *</td>
      <td class="content_row">
         <select class="text" style="width:100%" name="cfi_inc_id">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($incidencias AS $incidencia)
            {
               if($incidencia["id"] != 8 && $incidencia["id"] != 17)
               {  ?>
                  <option value="<?=$incidencia["id"]?>" <?php if($incidencia["id"] == $incdata["cfi_inc_id"]) echo "selected"?>><?=$incidencia["inc_name"]?></option>
                  <?php
               }
            }
            ?>
         </select>
      </td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_rowl">Fecha Inicio</td>
   <td class="content_row">
      <input name="cfi_startdate" id="cfi_startdate" type="text" style="width:85px" readonly
      class="text <?=$datecls?> range-low-<?=date("Y-m-d", $sql_datefrom)?>" <?=$rdlo?>
      value="<?if((int)$incdata["cfi_startdate"]) echo date("d.m.Y", $incdata["cfi_startdate"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
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
   <td class="content_rowl" valign="top">Descripción</td>
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
   if((int)$incdata["cfi_status"] == 2)
   {  ?>
      <td align="right" width="130" style="padding-left:5px">
         <?php
         $btnicon  = "arrow-circle-045-left";
         $btntitle = "Restablecer";
         $urladd   = "";

         if($_FROMFANCY)
            printButton($btntitle, "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){ location.href='iframe.fancy.php?{$querystr}&module={$_REQUEST["module"]}&exec=edit&id={$_REQUEST["id"]}&revert=1{$urladd}';}", $btnicon);
         else
            printButton($btntitle, "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){ location.href='index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&revert=1{$urladd}';}", $btnicon);
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_giro');" ?>