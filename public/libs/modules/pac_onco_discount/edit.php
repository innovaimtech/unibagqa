<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["discount_perc"] = getPrice(trim($_REQUEST["discount_perc"]),2);

      $sql = " update pac_onco_discount
               set
               discount_perc = {$_REQUEST["discount_perc"]}";
      $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

$title = "Cambiar descuento";

$sql = " select *
         from pac_onco_discount";
$pac_onco_discount = $CON->select($sql);
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
<form action="index.php" method="post" name="idx_giro" class="fokusfirst" onsubmit="return checkform(new Array(this.discount_perc))">
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
   <td class="content_tbl_header" colspan="2">Datos</td>
</tr>
<tr>
   <td class="content_rowl">Descuento *</td>
   <td class="content_row">
      <input type="text" class="text" name="discount_perc" style="width:100px"
      value="<?=printPrice($pac_onco_discount[0]["discount_perc"],2)?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_giro)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>