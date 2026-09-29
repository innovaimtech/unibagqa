<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["clearData"] != "")
{
   $sql = " delete from dscbuy_supplier_productcats
            where
            dct_supp_id = {$_REQUEST["id"]} and
            dct_cat_id  = {$_REQUEST["clearData"]}";
   $CON->no_result($sql);

   $savemsg = getSaveMessage(true);
}

$sql = " select t2.*
         from dscbuy_supplier_productcats t1
         LEFT OUTER JOIN productcats t2 ON t1.dct_cat_id = t2.id
         where
         t1.dct_supp_id = {$_REQUEST["id"]}";
$suppproddiscs = $CON->select($sql);
//----------------------------------------------------------------------------------
?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="980">
<colgroup>
   <col>
   <col>
</colgroup>
<tr>
   <!--
   <td class="content_row_clear" width="25%" style="padding-right:5px">
      <?php
      printButton("Agregar condiciones por familia", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discountsedit&id={$_REQUEST["id"]}", "", "plus", 239);
      ?>
   </td>
   -->
   <td class="content_row_clear" width="25%" style="padding-right:5px">
      <?php
      printButton("Agregar condiciones por monto", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discountseditvalue&id={$_REQUEST["id"]}", "", "calculator", 239);
      ?>
   </td>
   <!--
   <td class="content_row_clear" width="25%" style="padding-right:5px">
      <?php
      printButton("Cambiar condiciones por forma de pago", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discountseditpayment&id={$_REQUEST["id"]}", "", "calculator", 260);
      ?>
   </td>
   -->
   <td class="content_row_clear" width="25%">&nbsp;</td>
</tr>
</table>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<br>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col width="85">
   <col width="85">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Resumen de condiciones por familia</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Familia</td>
   <td class="content_tbl_subheader" align="center" colspan="2"><?=$_LANG["MODULE"]["CUST"][40]?></td>
</tr>
<?php
$x = 0;
foreach($suppproddiscs AS $cat)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=sprintf("%03s", $cat["id"])?> <?=$cat["cat_title"]?>&nbsp;</td>
      <td class="content_row" align="center">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discountsedit&id={$_REQUEST["id"]}&catid={$cat["id"]}", "", "pencil");
         ?>
      </td>
      <td class="content_row" align="center">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=discounts&id={$_REQUEST["id"]}&clearData={$cat["id"]}')", "cross-circle-frame");
         ?>
      </td>
   </tr>
   <?php
   $x++;
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="4" align="center" valign="middle" height="30">
         <br>
         <b class="msg_save_err">No hay condiciones por familia</b>
         <br><br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?php