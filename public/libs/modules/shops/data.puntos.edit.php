<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "save")
{
   $currtme = time();

   $_REQUEST["puntos_name"]     = trim(addslashes($_REQUEST["puntos_name"]));
   $_REQUEST["puntos_conexion"] = (int)$_REQUEST["puntos_conexion"];
   $_REQUEST["puntos_ip"]       = trim(addslashes($_REQUEST["puntos_ip"]));

   //----------------------------------------------------------------------------------
   if($_REQUEST["puntosid"] == "")
   {
      $sql = " insert into company_shops_puntos
               (puntos_name, puntos_conexion, puntos_ip, puntos_shop_id, puntos_crtusr, puntos_crtdat)
               VALUES
               ('{$_REQUEST["puntos_name"]}', {$_REQUEST["puntos_conexion"]}, '{$_REQUEST["puntos_ip"]}', {$_REQUEST["id"]},
                 {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'maxid'
                  from company_shops_puntos
                  where
                  puntos_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["maxid"];
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update company_shops_puntos
               set
               puntos_name     = '{$_REQUEST["puntos_name"]}',
               puntos_conexion =  {$_REQUEST["puntos_conexion"]},
               puntos_ip       = '{$_REQUEST["puntos_ip"]}',
               puntos_updusr   =  {$_SESSION["user_id"]},
               puntos_upddat   =  {$currtme}
               where
               id = {$_REQUEST["puntosid"]}";
      $res = $CON->no_result($sql); 
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["puntosid"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from company_shops_puntos t1
            LEFT OUTER JOIN user t2 ON t1.puntos_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.puntos_crtusr = t3.id
            where
            t1.id = {$_REQUEST["puntosid"]}";
   $puntos = $CON->select($sql);
   $puntos = $puntos[0]; 
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_puntos"
 onsubmit="return checkform(new Array(this.puntos_name))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="puntosid" value="<?=$_REQUEST["puntosid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subsubexec" value="save">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de puntos de ventas</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="puntos_name" type="text" class="text" style="width:510px" value="<?=$puntos["puntos_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">IP *</td>
   <td class="content_row">
      <input name="puntos_ip" type="text" class="text" style="width:510px" value="<?=$puntos["puntos_ip"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Conexion *</td>
   <td class="content_row">
      <input type="radio" name="puntos_conexion" value="0" <?if(!(int)$puntos["puntos_conexion"]) echo "checked"?>>&nbsp; USB
      <input type="radio" name="puntos_conexion" value="1" <?if((int)$puntos["puntos_conexion"] == 1) echo "checked"?>>&nbsp; COM1
      <input type="radio" name="puntos_conexion" value="2" <?if((int)$puntos["puntos_conexion"] == 2) echo "checked"?>>&nbsp; LPT1
   </td> 
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$puntos["crt_firstname"]?> <?=$puntos["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($puntos["puntos_crtdat"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$puntos["upd_firstname"]?> <?=$puntos["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($puntos["puntos_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["stid"] != "")
   {  ?>
      <td width="130" align="right" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&stid={$_REQUEST["stid"]}&subexec=del')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_puntos)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_puntos');" ?>
