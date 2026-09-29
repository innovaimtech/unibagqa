<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   
   $_REQUEST["country_name"]     = trim(addslashes($_REQUEST["country_name"]));
   $_REQUEST["country_status"]   = (int)$_REQUEST["country_status"];
   $_REQUEST["taxes_active"]     = (int)$_REQUEST["taxes_active"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into country
               (country_name, country_status, taxes_active, country_money_type, country_crtusr, country_crtdat)
               VALUES
               ('{$_REQUEST["country_name"]}', {$_REQUEST["country_status"]}, {$_REQUEST["taxes_active"]},
                '{$_REQUEST["money_type"]}', {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);
   }
   else
   {
      $sql = " update country
               set
               country_name         = '{$_REQUEST["country_name"]}',
               country_status       = {$_REQUEST["country_status"]},
               taxes_active         = {$_REQUEST["taxes_active"]},
               country_money_type   = '{$_REQUEST["money_type"]}',
               country_updusr       = {$_SESSION["user_id"]},
               country_upddat       = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $title = "Cambiar pais";

   $sql = " select t1.*,
                   t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
                   t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from country t1
            LEFT OUTER JOIN user t2 ON t1.country_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.country_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}
else
{
   $title = "Agregar pais";
}

$moneytypes = getMoneyTypes();

?>
<form action="index.php" method="post" class="fokusfirst" name="xform_country"
 onsubmit="return checkform(new Array(this.country_name))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col width="">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos del país</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="country_name" type="text" class="text" style="width:330px" value="<?=$data["country_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <input type="radio" name="country_status" value="1"
      <?php if((int)$data["country_status"] == 1 || $_REQUEST["id"] == "") echo "checked" ?>>Activado
      <input type="radio" name="country_status" value="0"
      <?php if((int)$data["country_status"] == 0 && $_REQUEST["id"] != "") echo "checked" ?>>Desactivado
   </td>
</tr>
<tr>
   <td class="content_rowl">IVA</td>
   <td class="content_row">
      <input type="radio" name="taxes_active" value="1"
      <?php if((int)$data["taxes_active"] == 1 || $_REQUEST["id"] == "") echo "checked" ?>>Con IVA
      <input type="radio" name="taxes_active" value="0"
      <?php if((int)$data["taxes_active"] == 0 && $_REQUEST["id"] != "") echo "checked" ?>>Sin IVA
   </td>
</tr>
<tr>
   <td class="content_rowl">Moneda</td>
   <td class="content_row">
      <select class="text" style="width:100px;" name="money_type" id="money_type"
      onblur="markfield(this,1)" onmousedown="markfield(this,0)">
         <?php
         foreach($moneytypes AS $moneytype)
         {  ?>
            <option value="<?=$moneytype?>" <?php if($data["country_money_type"] == $moneytype) echo "selected"?>><?=$moneytype?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$data["crt_firstname"]?> <?=$data["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($data["country_crtdat"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$data["upd_firstname"]?> <?=$data["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($data["country_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
      if($_REQUEST["id"] != 81)
      {  ?>
         <td width="130" align="right" style="padding-right:5px">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=delete&id={$_REQUEST["id"]}')", "cross-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_country)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_country');" ?>