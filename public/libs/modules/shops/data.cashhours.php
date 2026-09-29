<?php
//----------------------------------------------------------------------------------
// Author:        Alexander Appelt
// Copyright:     2018 by Alexander Appelt. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["saveexec"] == "save")
{
   $currtme = time();
   for($x = 1; $x <= 7; $x++)
   {
      $shop_inithour = (int)$_REQUEST["shop_inithour_{$x}"];
      $shop_initmin  = (int)$_REQUEST["shop_initmin_{$x}"];
      $shop_endhour  = (int)$_REQUEST["shop_endhour_{$x}"];
      $shop_endmin   = (int)$_REQUEST["shop_endmin_{$x}"];
      $shop_prdhour  = (int)$_REQUEST["shop_prdhour_{$x}"];
      $shop_prdmin   = (int)$_REQUEST["shop_prdmin_{$x}"];

      $sql = " update company_shops
               set
               shop_inithour_{$x}   = {$shop_inithour},
               shop_initmin_{$x}    = {$shop_initmin},
               shop_endhour_{$x}    = {$shop_endhour},
               shop_endmin_{$x}     = {$shop_endmin},
               shop_prdhour_{$x}    = {$shop_prdhour},
               shop_prdmin_{$x}     = {$shop_prdmin}
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
   $savemsg = getSaveMessage(true);
}

$sql = " select *
         from company_shops
         where
         id = {$_REQUEST["id"]}";
$shop = $CON->select($sql);
$shop = $shop[0];
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="idx_cond" class="fokusfirst">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="saveexec" value="save">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<?=Nifty_printH("box1", "980")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header content_row_os" align="left">Tipo</td>
   <td class="content_tbl_header content_row_os" align="center">Lunes</td>
   <td class="content_tbl_header content_row_os" align="center">Martes</td>
   <td class="content_tbl_header content_row_os" align="center">Miercoles</td>
   <td class="content_tbl_header content_row_os" align="center">Jueves</td>
   <td class="content_tbl_header content_row_os" align="center">Viernes</td>
   <td class="content_tbl_header content_row_os" align="center">Sabado</td>
   <td class="content_tbl_header content_row_os" align="center">Domingo</td>
</tr>
<tr>
   <td class="content_rowl content_row_os" align="left">Inicio</td>
   <?php
   for($x = 1; $x <= 7; $x++)
   {  ?>
      <td class="content_row_os" align="center">
         <input type="text" class="text" name="shop_inithour_<?=$x?>" style="text-align:center;width:42%"
         value="<?=$shop["shop_inithour_{$x}"]?>" placeholder="Hora"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         :
         <input type="text" class="text" name="shop_initmin_<?=$x?>" style="text-align:center;width:42%"
         value="<?=$shop["shop_initmin_{$x}"]?>" placeholder="Min."
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <?php
   }
   ?>
</tr>
<tr>
   <td class="content_rowl content_row_os" align="left">Termino</td>
   <?php
   for($x = 1; $x <= 7; $x++)
   {  ?>
      <td class="content_row_os" align="center">
         <input type="text" class="text" name="shop_endhour_<?=$x?>" style="text-align:center;width:42%"
         value="<?=$shop["shop_endhour_{$x}"]?>" placeholder="Hora"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         :
         <input type="text" class="text" name="shop_endmin_<?=$x?>" style="text-align:center;width:42%"
         value="<?=$shop["shop_endmin_{$x}"]?>" placeholder="Min."
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_cond)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>