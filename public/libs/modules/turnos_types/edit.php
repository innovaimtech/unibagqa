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
   $_REQUEST["type_name"]        = trim(addslashes($_REQUEST["type_name"]));
   $_REQUEST["type_name_short"]  = trim(addslashes($_REQUEST["type_name_short"]));
   $_REQUEST["type_color"]       = trim(addslashes($_REQUEST["type_color"]));
   $_REQUEST["type_color_dom"]   = trim(addslashes($_REQUEST["type_color_dom"]));
   $_REQUEST["type_libre_act"]   = (int)$_REQUEST["type_libre_act"];

   $_REQUEST["type_init_hour"]   = (int)$_REQUEST["type_init_hour"];
   $_REQUEST["type_init_min"]    = (int)$_REQUEST["type_init_min"];
   $_REQUEST["type_end_hour"]    = (int)$_REQUEST["type_end_hour"];
   $_REQUEST["type_end_min"]     = (int)$_REQUEST["type_end_min"];
   $_REQUEST["type_end_nextday"] = (int)$_REQUEST["type_end_nextday"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into turnos_types
               (type_name, type_crtusr, type_crtdat, type_name_short, type_color, type_libre_act, type_color_dom,
                type_init_hour, type_init_min, type_end_hour, type_end_min, type_end_nextday)
               VALUES
               ('{$_REQUEST["type_name"]}', {$_SESSION["user_id"]}, {$currtme},
                '{$_REQUEST["type_name_short"]}', '{$_REQUEST["type_color"]}',
                 {$_REQUEST["type_libre_act"]}, '{$_REQUEST["type_color_dom"]}',
                 {$_REQUEST["type_init_hour"]}, {$_REQUEST["type_init_min"]}, {$_REQUEST["type_end_hour"]},
                 {$_REQUEST["type_end_min"]}, {$_REQUEST["type_end_nextday"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from turnos_types
                  where
                  type_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
      }
   }
   else
   {
      $sql = " update turnos_types
               set
               type_name         = '{$_REQUEST["type_name"]}',
               type_name_short   = '{$_REQUEST["type_name_short"]}',
               type_color        = '{$_REQUEST["type_color"]}',
               type_color_dom    = '{$_REQUEST["type_color_dom"]}',
               type_libre_act    = {$_REQUEST["type_libre_act"]},
               type_init_hour    = {$_REQUEST["type_init_hour"]},
               type_init_min     = {$_REQUEST["type_init_min"]},
               type_end_hour     = {$_REQUEST["type_end_hour"]},
               type_end_min      = {$_REQUEST["type_end_min"]},
               type_end_nextday  = {$_REQUEST["type_end_nextday"]},
               type_updusr = {$_SESSION["user_id"]},
               type_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar Horario";
}
else
{
   $title = "Cambiar Horario";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from turnos_types t1
            LEFT OUTER JOIN user t2 ON t1.type_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.type_crtusr = t3.id
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
onsubmit="return checkform(new Array(this.type_name, this.type_name_short))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box1", "822")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos del Horario</td>
</tr>
<tr>
   <td class="content_rowl">Nombre corto *</td>
   <td class="content_row">
      <input type="text" class="text" name="type_name_short" style="width:50px" value="<?=$business[0]["type_name_short"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="type_name" style="width:100%" value="<?=$business[0]["type_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Color</td>
   <td class="content_row">
      <input type="text" class="text color {hash:true, pickerMode:'HVS'}"
      name="type_color" style="width:80px" value="<?=$business[0]["type_color"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Color Domingo</td>
   <td class="content_row">
      <input type="text" class="text color {hash:true, pickerMode:'HVS'}"
      name="type_color_dom" style="width:80px" value="<?=$business[0]["type_color_dom"]?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Es dia libre</td>
   <td class="content_row">
      <input type="checkbox" name="type_libre_act" value="1"
      <?if((int)$business[0]["type_libre_act"]) echo "checked"?>> Activado
   </td>
</tr>
<tr>
   <td class="content_rowl">Hora inicio *</td>
   <td class="content_row">
      <select class="text" name="type_init_hour" id="type_init_hour" style="width:50px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         for($x = 0; $x <= 23; $x++)
         {  ?>
            <option value="<?=$x?>" <?php if($x == $business[0]["type_init_hour"]) echo "selected" ?>>
               <?=sprintf("%02s", $x)?>
            </option>
            <?php
         }
         ?>
      </select>
      :
      <select class="text" name="type_init_min" id="type_init_min" style="width:50px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         for($x = 0; $x <= 59; $x++)
         {  ?>
            <option value="<?=$x?>" <?php if($x == $business[0]["type_init_min"]) echo "selected" ?>>
               <?=sprintf("%02s", $x)?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Hora termino *</td>
   <td class="content_row">
      <select class="text" name="type_end_hour" id="type_end_hour" style="width:50px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         for($x = 0; $x <= 23; $x++)
         {  ?>
            <option value="<?=$x?>" <?php if($x == $business[0]["type_end_hour"]) echo "selected" ?>>
               <?=sprintf("%02s", $x)?>
            </option>
            <?php
         }
         ?>
      </select>
      :
      <select class="text" name="type_end_min" id="type_end_min" style="width:50px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         for($x = 0; $x <= 59; $x++)
         {  ?>
            <option value="<?=$x?>" <?php if($x == $business[0]["type_end_min"]) echo "selected" ?>>
               <?=sprintf("%02s", $x)?>
            </option>
            <?php
         }
         ?>
      </select>
      <!--
      <input type="checkbox" name="type_end_nextday" value="1"
      <?if((int)$business[0]["type_end_nextday"]) echo "checked"?>>
      Hora termino en el siguiente dia
      -->
   </td>
</tr>
<?php
if($business[0]["type_crtusr"] != "")
{  ?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
	   <td class="content_row"><?php if($business[0]["type_crtusr"] != "") echo "{$business[0]["crt_firstname"]} {$business[0]["crt_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
	   <td class="content_row"><?php if($business[0]["type_crtusr"] != "") echo displayDate($business[0]["type_crtdat"])?>&nbsp;</td>
	</tr>
	<?php
}
if($business[0]["type_updusr"] != "")
{	?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
	   <td class="content_row"><?php if($business[0]["type_updusr"] != "") echo "{$business[0]["upd_firstname"]} {$business[0]["upd_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
	   <td class="content_row"><?php if($business[0]["type_updusr"] != "") echo displayDate($business[0]["type_upddat"])?>&nbsp;</td>
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