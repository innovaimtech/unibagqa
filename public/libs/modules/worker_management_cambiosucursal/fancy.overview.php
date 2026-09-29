<?php
$_sesmodulename  = "mgmt_balance";

//----------------------------------------------------------------------------------
$xarr                   = explode("_", $_REQUEST["xidx"]);
$cust_id                = (int)$xarr[0];
$inst_id                = (int)$xarr[1];
$inst_contr_pautaid     = (int)$xarr[2];
$inst_hour_id           = (int)$xarr[3];
$isspecial              = (int)$xarr[4];
$puestoidx              = (int)$xarr[5];
$month                  = (int)$_SESSION[$_sesmodulename]["sql_month1"];
$year                   = (int)$_SESSION[$_sesmodulename]["sql_year1"];
$assignrelevoid         = (int)$_REQUEST["assignrelevoid"];
$querystr               = "xidx={$_REQUEST["xidx"]}&mid={$_REQUEST["mid"]}&wid={$_REQUEST["wid"]}&wtype={$_REQUEST["wtype"]}";
$querystr              .= "&multimode={$_REQUEST["multimode"]}&multistart={$_REQUEST["multistart"]}&multiend={$_REQUEST["multiend"]}";
$querystr              .= "&custid={$_REQUEST["custid"]}";

//----------------------------------------------------------------------------------
$wtype = str_replace("type", "", $_REQUEST["wtype"]);
$wdata = getWorkerData($CON, $_REQUEST["wid"], $wtype);
?>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="125">
   <col width="40%">
   <col width="125">
   <col width="40%">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos del Trabajador</td>
</tr>
<tr>
   <td class="content_rowl" style="border-top:2px solid #CCCCCC">RUT</td>
   <td class="content_row" style="border-top:2px solid #CCCCCC"><?=$wdata["wrk_rut"]?>&nbsp;</td>
   <td class="content_rowl" style="border-top:2px solid #CCCCCC">Mes/Año</td>
   <td class="content_row" style="border-top:2px solid #CCCCCC">
      <?=sprintf("%02s", $_SESSION[$_sesmodulename]["sql_month1"])?> /
      <?=$_SESSION[$_sesmodulename]["sql_year1"]?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombres</td>
   <td class="content_row"><?=$wdata["wrk_firstname"]?>&nbsp;</td>
   <td class="content_rowl">Apellidos</td>
   <td class="content_row"><?=$wdata["wrk_lastname"]?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if($_REQUEST["exec"] == "edit")
{
   $_FROMFANCY = true;
   require_once("edit.php");
}
else
{  ?>
   <div style="text-align:left;padding-left:5px">
   <table border="0" cellpadding="0" cellspacing="0" width="99%">
   <tr>
      <td>
         <select class="text" style="width:300px;" name="sql_xmode"
         onchange="gotoModifyView(this.value, '<?=$querystr?>')">
            <option value="0"> Vista: Asignación mensual</option>
            <option value="1"> Vista: Incidencias</option>
            <option value="2" selected> Vista: Cambio Turno</option>
         </select>
      </td>
      <td align="right">
         <?php
         printButton("Agregar cambio de turno", "postnav_save", "iframe.fancy.php?{$querystr}&module={$_REQUEST["module"]}&exec=edit&id=", "", "plus", 150);
         ?>
      </td>
   </tr>
   </table>
   </div>
   <br>
   <?php
   //----------------------------------------------------------------------------------
   if($_REQUEST["exec"] == "del" && (int)$_REQUEST["id"])
   {
      $currtme = time();

      $sql = " update turnos_config_incidencias
               set
               cfi_status = 0,
               cfi_crtusr = {$_SESSION["user_id"]},
               cfi_crtdat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $savemsg = getSaveMessage($res);
   }
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.id, t1.cfi_startdate, t1.cfi_enddate, t3.inc_name, t3.inc_color, t1.cfi_status
            from turnos_config_incidencias t1
            INNER JOIN incidencias t3  ON t1.cfi_inc_id = t3.id
            where
            t1.cfi_status     > 0 and
            t3.inc_type       = 1 and
            t1.cfi_workerid   = {$_REQUEST["wid"]} and
            t1.cfi_workertype = '{$_REQUEST["wtype"]}'
            order by t1.cfi_startdate desc";
   $data = $CON->select($sql);
   ?>
   <?=Nifty_printH("box1", "99%")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="100">
      <col>
      <col width="100">
      <col>
      <col width="1">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_subheader">RUT</td>
      <td class="content_tbl_subheader">Nombre Trabajador</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader" align="center">Incidencia</td>
      <td class="content_tbl_subheader" align="center">Estado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($data) && $data != false; $x++)
   {
      $statimg = "";
      switch((int)$data[$x]["cfi_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "green_active.gif"; break;
      }

      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$wdata["wrk_rut"]?>&nbsp;</td>
         <td class="content_row"><?=$wdata["wrk_lastname"]?>, <?=$wdata["wrk_firstname"]?></td>
         <td class="content_row"><?=date("d.m.Y", $data[$x]["cfi_startdate"])?></td>
         <td class="content_row" align="center" style="background-color:<?=$data[$x]["inc_color"]?>"><?=$data[$x]["inc_name"]?>&nbsp;</td>
         <td class="content_row" align="center">
            <img class="select" src="./images/content/<?=$statimg?>">
         </td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "iframe.fancy.php?{$querystr}&module={$_REQUEST["module"]}&exec=edit&id={$data[$x]["id"]}", "", "pencil");
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" align="center" colspan="6">
            <br>
            <b class="msg_save_err">No hay datos disponibles.</b>
            <br><br>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}