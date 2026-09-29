<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $discountval = getPrice($_REQUEST["item_discount"]);
   ?>
   <script language="JavaScript">
      location.href='index.php?mid=755&invcidOrg=<?=$_REQUEST["id"]?>&exec=edit&&subexec=createFromNoteDiscount&setstatus=1,2';
   </script>
   <?php
   exit;
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from invoices_sell t1
         where
         t1.id = {$_REQUEST["id"]} ";
$invoice = $CON->select($sql);
$invoice = $invoice[0];

//----------------------------------------------------------------------------------
$posdata    = Array();
$invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"]);
   for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
      $posdata[] = $partposdata[$y];
}

//----------------------------------------------------------------------------------
$_RES = getCustomerNoteDiscounts($CON, $invoice["invc_cust_id"]);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" name="xform_itemprices" id="xform_itemprices">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="">
<input type="hidden" name="subcatexec" value="notediscount">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="25">
   <col>
   <col width="90">
   <col width="80">
   <col width="80">
   <col width="100">
   <col width="90">
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="8">Condiciones de venta</td>
</tr>
<tr>
   <td class="content_tbl_subheader content_row_os" align="center">Pos</td>
   <td class="content_tbl_subheader content_row_os">Artículo</td>
   <td class="content_tbl_subheader content_row_os">Unidad</td>
   <td class="content_tbl_subheader content_row_os" align="right">Cantidad</td>
   <td class="content_tbl_subheader content_row_os" align="right"><nobr>Subtotal</nobr></td>
   <td class="content_tbl_subheader content_row_os" align="right">Descuento/Nota</td>
   <td class="content_tbl_subheader content_row_os" align="right"><nobr>Precio Total</nobr></td>
   <td class="content_tbl_subheader content_row_os" align="right"><nobr>Diferencia</nobr></td>
</tr>
<?php
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{
   $unitdesc   = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
   $dsc        = $_RES[$posdata[$x]["cat_id"]];
   $new_netto  = round($posdata[$x]["item_sellprice_netto_dsc"] - ($posdata[$x]["item_sellprice_netto_dsc"] / 100 * $dsc),0);
   $new_diff   = $posdata[$x]["item_sellprice_netto_dsc"] - $new_netto;
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row_os" align="center"><?=($x +1)?></td>
      <td class="content_row_os"><?=$posdata[$x]["item_title"]?></td>
      <td class="content_row_os"><?=$unitdesc?></td>
      <td class="content_row_os" align="right"><?=printPrice($posdata[$x]["item_amount"],2)?></td>
      <td class="content_row_os" align="right"><nobr><?=printPrice($posdata[$x]["item_sellprice_netto_dsc"])?></nobr></td>
      <td class="content_row_os" align="right"><?=printPrice($dsc,2)?> %</td>
      <td class="content_row_os" align="right"><nobr><?=printPrice($new_netto)?></nobr></td>
      <td class="content_row_os" align="right"><nobr><?=printPrice($new_diff)?></nobr></td>
   </tr>
   <?php
   $ges_amt += $posdata[$x]["item_amount"];
   $ges_net += $posdata[$x]["item_sellprice_netto_dsc"];
   $ges_new += $new_netto;
   $ges_dif += $new_diff;
}

//----------------------------------------------------------------------------------
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="8" align="center" valign="middle" height="30">
         <b class="msg_save_err">No hay datos disponibles.</b>
      </td>
   </tr>
   <?php
}
else
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_totals" colspan="3">TOTAL</td>
      <td class="content_row_totals" align="right"><?=printPrice($ges_amt,2)?></td>
      <td class="content_row_totals" align="right"><?=printPrice($ges_net)?></td>
      <td class="content_row_totals">&nbsp;</td>
      <td class="content_row_totals" align="right"><?=printPrice($ges_new)?></td>
      <td class="content_row_totals" align="right"><?php if($ges_dif > 0.00) echo printPrice($ges_dif);?></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <?php
   if($invoice["invc_status"] > 1)
   {  ?>
      <td align="right" width="130">
         <?php
         printButton("Generar Nota de Credito", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.xform_itemprices.subexec.value='save';submitForm(document.xform_itemprices);}", "tick-circle-frame", 200);
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?=Nifty_printF(false)?>