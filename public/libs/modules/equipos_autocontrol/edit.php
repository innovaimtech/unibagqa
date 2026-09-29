<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["ac_name"]    = trim(addslashes($_REQUEST["ac_name"]));
   $_REQUEST["ac_desc"]    = trim(addslashes($_REQUEST["ac_desc"]));
   $_REQUEST["ac_equipotype_id"]    = (int)$_REQUEST["ac_equipotype_id"];
   $_REQUEST["ac_order"] = (int)$_REQUEST["ac_order"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into equipo_puntos_autocontrol
               (ac_name, ac_equipotype_id, ac_crtusr, ac_crtdat, ac_desc, ac_order)
               VALUES
               ('{$_REQUEST["ac_name"]}', {$_REQUEST["ac_equipotype_id"]}, {$_SESSION["user_id"]},
                {$currtme}, '{$_REQUEST["ac_desc"]}', {$_REQUEST["ac_order"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from equipo_puntos_autocontrol
                  where
                  ac_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];

         $_REQUEST["id"] = $thisid;
      }
   }
   else
   {
      $sql = " update equipo_puntos_autocontrol
               set
               ac_name   = '{$_REQUEST["ac_name"]}',
               ac_desc   = '{$_REQUEST["ac_desc"]}',
               ac_equipotype_id    = {$_REQUEST["ac_equipotype_id"]},
               ac_order  = {$_REQUEST["ac_order"]},
               ac_updusr = {$_SESSION["user_id"]},
               ac_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar punto";
}
else
{
   $title = "Cambiar punto";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from equipo_puntos_autocontrol t1
            LEFT OUTER JOIN user t2 ON t1.ac_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.ac_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $punto = $CON->select($sql);
}

$puntos = getTiposEquipo($CON);
?>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="idx_giro" class="fokusfirst" 
onsubmit="return checkform(new Array(this.ac_name, this.ac_equipotype_id))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box1", "650")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de máquina</td>
</tr>
<tr>
   <td class="content_rowl">Tipo máquina *</td>
   <td class="content_row">
      <select class="text" name="ac_equipotype_id" id="ac_equipotype_id" style="width:100%"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach ($puntos as $tipoAntena)
         {  ?>
            <option value="<?=$tipoAntena["id"]?>" <?php if($tipoAntena["id"] == $punto[0]["ac_equipotype_id"]) echo "selected" ?>>
               <?=$tipoAntena["type_ant_title"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="ac_name" style="width:510px" value="<?=$punto[0]["ac_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Orden</td>
   <td class="content_row">
      <input type="text" class="text" name="ac_order" style="width:30px;text-align:center"
      value="<?=$punto[0]["ac_order"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="ac_desc" class="text" style="width:510px;height:130px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$punto[0]["ac_desc"]?></textarea>
   </td>
</tr>
<?php
if($punto[0]["ac_crtusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
      <td class="content_row"><?php if($punto[0]["ac_crtusr"] != "") echo "{$punto[0]["crt_firstname"]} {$punto[0]["crt_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
      <td class="content_row"><?php if($punto[0]["ac_crtusr"] != "") echo displayDate($punto[0]["ac_crtdat"])?>&nbsp;</td>
   </tr>
   <?php
}
if($punto[0]["ac_updusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
      <td class="content_row"><?php if($punto[0]["ac_updusr"] != "") echo "{$punto[0]["upd_firstname"]} {$punto[0]["upd_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
      <td class="content_row"><?php if($punto[0]["ac_updusr"] != "") echo displayDate($punto[0]["ac_upddat"])?>&nbsp;</td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
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