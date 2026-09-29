<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$sql = " select *
         from invoices_buy
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
   
$invcparts  = getInvoiceBuyParts($CON, $_REQUEST["id"]);
$poscounter = 0;
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header" colspan="6">Cambio de moneda</td>
</tr>
<tr>
   <td class="content_tbl_subheader" valign="top">Artículo</td>
   <td class="content_tbl_subheader" valign="top" align="right">Cantidad</td>
   <td class="content_tbl_subheader" valign="top" align="right">USD Precio</td>
   <td class="content_tbl_subheader" valign="top" align="right">USD Total</td>
   <td class="content_tbl_subheader" valign="top" align="right">CLP Precio</td>
   <td class="content_tbl_subheader" valign="top" align="right">CLP Total</td>
</tr>
<?php
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $posdata = getInvoiceBuyPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"]);
   for($y = 0; $y < count($posdata) && $posdata != false; $y++)
   {
      $desc          = trim(addslashes($posdata[$y]["item_title"]));
      $unitdesc      = getItemUnitDesc($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
      $itemprc_usd   = round($posdata[$y]["item_costprice_netto_dsc2"] / $posdata[$y]["item_amount"],4);

      $item_title    = "{$posdata[$y]["item_number_prod"]} - {$desc} ({$unitdesc})";
      if($posdata[$y]["item_type"] == "manual")
         $item_title = $posdata[$y]["item_title"];
      ?>
      <tr bgcolor="<?=getRowColor($poscounter)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$item_title?></td>
         <td class="content_row" align="right"><?=printPrice($posdata[$y]["item_amount"],2)?></td>
         <td class="content_row" align="right"><?=printPrice($itemprc_usd, 4)?></td>
         <td class="content_row" align="right"><?=printPrice($posdata[$y]["item_costprice_netto_dsc2"],2)?></td>
         <td class="content_row" align="right"><?=printPrice($posdata[$y]["item_costprice_import_item"])?></td>
         <td class="content_row" align="right"><?=printPrice($posdata[$y]["item_costprice_import_total"])?></td>
      </tr>
      <?php
      $poscounter++;
   }
}
if(!$y)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="6" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>