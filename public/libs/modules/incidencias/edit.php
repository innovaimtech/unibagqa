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
   $_REQUEST["inc_name"]        = trim(addslashes($_REQUEST["inc_name"]));
   $_REQUEST["inc_name_short"]  = trim(addslashes($_REQUEST["inc_name_short"]));
   $_REQUEST["inc_color"]       = trim(addslashes($_REQUEST["inc_color"]));
   $_REQUEST["inc_color_dom"]   = trim(addslashes($_REQUEST["inc_color_dom"]));
   $_REQUEST["inc_createslot_act"]        = (int)$_REQUEST["inc_createslot_act"];
   $_REQUEST["inc_discountbono_act"]      = (int)$_REQUEST["inc_discountbono_act"];
   $_REQUEST["inc_discountworkday_act"]   = (int)$_REQUEST["inc_discountworkday_act"];
   $_REQUEST["inc_type"]                  = (int)$_REQUEST["inc_type"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into incidencias
               (inc_name, inc_crtusr, inc_crtdat, inc_name_short, inc_color, inc_createslot_act,
                inc_color_dom, inc_discountbono_act, inc_discountworkday_act, inc_type)
               VALUES
               ('{$_REQUEST["inc_name"]}', {$_SESSION["user_id"]}, {$currtme},
                '{$_REQUEST["inc_name_short"]}', '{$_REQUEST["inc_color"]}',
                 {$_REQUEST["inc_createslot_act"]}, '{$_REQUEST["inc_color_dom"]}',
                 {$_REQUEST["inc_discountbono_act"]}, {$_REQUEST["inc_discountworkday_act"]},
                 {$_REQUEST["inc_type"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from incidencias
                  where
                  inc_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
      }
   }
   else
   {
      $sql = " update incidencias
               set
               inc_name         = '{$_REQUEST["inc_name"]}',
               inc_name_short   = '{$_REQUEST["inc_name_short"]}',
               inc_color        = '{$_REQUEST["inc_color"]}',
               inc_color_dom    = '{$_REQUEST["inc_color_dom"]}',
               inc_createslot_act      = {$_REQUEST["inc_createslot_act"]},
               inc_discountbono_act    = {$_REQUEST["inc_discountbono_act"]},
               inc_discountworkday_act = {$_REQUEST["inc_discountworkday_act"]},
               inc_updusr = {$_SESSION["user_id"]},
               inc_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

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
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from incidencias t1
            LEFT OUTER JOIN user t2 ON t1.inc_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.inc_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $business = $CON->select($sql);
}
?>
<script type="text/javascript" src="./libs/jscripts/jscolor/jscolor.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="idx_giro" class="fokusfirst" 
onsubmit="return checkform(new Array(this.inc_name, this.inc_name_short))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box1", "822")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="150">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de Incidencia</td>
</tr>
<tr>
   <td class="content_rowl">Nombre corto *</td>
   <td class="content_row">
      <input type="text" class="text" name="inc_name_short" style="width:50px" value="<?=$business[0]["inc_name_short"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="inc_name" style="width:100%" value="<?=$business[0]["inc_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Color</td>
   <td class="content_row">
      <input type="text" class="text color {hash:true, pickerMode:'HVS'}"
      name="inc_color" style="width:80px" value="<?=$business[0]["inc_color"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Color Domingo</td>
   <td class="content_row">
      <input type="text" class="text color {hash:true, pickerMode:'HVS'}"
      name="inc_color_dom" style="width:80px" value="<?=$business[0]["inc_color_dom"]?>">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Descuenta Bono</td>
   <td class="content_row">
      <input type="checkbox" name="inc_discountbono_act" value="1"
      <?if((int)$business[0]["inc_discountbono_act"]) echo "checked"?>> Activado
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Descuenta Día trabajado</td>
   <td class="content_row">
      <input type="checkbox" name="inc_discountworkday_act" value="1"
      <?if((int)$business[0]["inc_discountworkday_act"]) echo "checked"?>> Activado
   </td>
</tr>
<input type="hidden" name="inc_type" value="0">
<input type="hidden" name="inc_createslot_act" value="0">
<!--
<tr>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <input type="radio" name="inc_type" value="0" <?if((int)$business[0]["inc_type"] == 0) echo "checked"?>> Incidencia
      <?php
      if($_REQUEST["id"] != 8 && $_REQUEST["id"] != 17)
      {  ?>
         <input type="radio" name="inc_type" value="1" <?if((int)$business[0]["inc_type"] == 1) echo "checked"?>> Cambio Turno
         <input type="radio" name="inc_type" value="2" <?if((int)$business[0]["inc_type"] == 2) echo "checked"?>> Termino Contrato
         <?php
      }
      ?>
   </td>
</tr>
-->
<?php
if($business[0]["inc_crtusr"] != "")
{  ?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
	   <td class="content_row"><?php if($business[0]["inc_crtusr"] != "") echo "{$business[0]["crt_firstname"]} {$business[0]["crt_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
	   <td class="content_row"><?php if($business[0]["inc_crtusr"] != "") echo displayDate($business[0]["inc_crtdat"])?>&nbsp;</td>
	</tr>
	<?php
}
if($business[0]["inc_updusr"] != "")
{	?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
	   <td class="content_row"><?php if($business[0]["inc_updusr"] != "") echo "{$business[0]["upd_firstname"]} {$business[0]["upd_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
	   <td class="content_row"><?php if($business[0]["inc_updusr"] != "") echo displayDate($business[0]["inc_upddat"])?>&nbsp;</td>
	</tr>
	<?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "822")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if($_REQUEST["id"] != 8 && $_REQUEST["id"] != 17 && $_REQUEST["id"] != 13)
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_giro)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_giro');" ?>