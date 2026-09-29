<script language="JavaScript">
function setMainMargen()
{
   var mvalue  = document.getElementById('item_price_perc').value;
   var xmvalue = mvalue.replace('.','');
   xmvalue = xmvalue.replace(',','.');
   xmvalue = parseFloat(xmvalue);

   if(isNaN(xmvalue))
   {
      xmvalue = 0;
      mvalue  = 0;
   }
   $(".mval").each(function ()
   {
      var itemid  = $(this).attr('id');
      var idarr   = itemid.split('_');
      var ididx   = idarr[1] + '_' + idarr[2] + '_' + idarr[3];

      var baseprice  = parseFloat(document.getElementById('itembaseprice_' +ididx).value);
      var costprice  = parseFloat(document.getElementById('itemcostprice_' +ididx).value);
      var newprice   = document.getElementById('itemnewprice_' +ididx);
      var itemact    = document.getElementById('item_act_' +ididx).checked;
      if(itemact)
      {
         newprice.value = Math.round(baseprice + (baseprice / 100 * xmvalue));
      }
   });
}
</script>
<?=Nifty_printH("box1", "650")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col>
   <col width="200">
   <col width="200">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Cambio de precios</td>
</tr>
<tr>
   <td class="content_rowl">Nuevo Margen</td>
   <td class="content_row">
      <input type="text" class="text" id="item_price_perc" style="width:100px;text-align:center"
      value=""> %
   </td>
   <td class="content_row">
      <?php
      printButton("Aplicar a artículos marcados", "postnav", "javascript: deactivateFormChange()", "setMainMargen()", "document", 200);
      ?>
   </td>
   <td class="content_row">
      <?php
      printButton("Guardar cambios", "postnav_save", "javascript: deactivateFormChange()", "document.xform_savechg.submit()", "document", 200);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<form action="index.php" method="post" name="xform_savechg">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="savechanges" value="1">
<?=Nifty_printH("box1", "95%")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="25">
   <col width="75">
   <col>
   <col width="60">
   <col>
   <col>
   <col>
   <col>
   <col>
   <col width="80">
   <col width="80">
</colgroup>
<tr>
   <td class="content_tbl_subheader content_row_os">
      <input type="checkbox" onclick="$('.chkme').attr('checked',this.checked)">
   </td>
   <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
   <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
   <td class="content_tbl_subheader content_row_os">Unidad</td>
   <td class="content_tbl_subheader content_row_os">Codigo/Prov.</td>
   <td class="content_tbl_subheader content_row_os" align="left"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
   <td class="content_tbl_subheader content_row_os" align="right">Precio<br>compra</td>
   <td class="content_tbl_subheader content_row_os" align="center">Margen<br>actual</td>
   <td class="content_tbl_subheader content_row_os" align="center">Margen<br>familia</td>
   <td class="content_tbl_subheader content_row_os" align="right">Precio actual</td>
   <td class="content_tbl_subheader content_row_os" align="right">Precio nuevo</td>
</tr>
<?php

//----------------------------------------------------------------------------------
for($x = 0; $x < count($items) && $items != false; $x++)
{
   if($items[$x]["item_type"] == "item_typeI")
      $items[$x]["item_type"] = "item";
   else
      $items[$x]["item_type"] = "itemlist";

   $unitdesc   = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
   $taxes      = getSupplierTaxes($CON, $items[$x]["supplierid"]);
   if($taxes)
   {
      //$items[$x]["item_costprice_netto"]

      $buyval     = getSupplierFinalCostNetto($CON, 0, $items[$x]["id"], $items[$x]["item_type"]);
      $selval     = getFinalSellNetto($CON, $items[$x]["id"], $items[$x]["item_type"]);

      $itemshopprice = $items[$x]["itemshop_sellprice_netto"];
      $items[$x]["itemshop_sellprice_netto"] = $selval;
      $items[$x]["item_costprice_netto"]     = $buyval;

      $item_price = $items[$x]["itemshop_sellprice_netto"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]     = $items[$x]["item_number_prod"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]           = $items[$x]["item_title"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]         = $items[$x]["supp_company"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]             = $unitdesc;

      $idx = $items[$x]["item_type"]."_".$items[$x]["id"]."_".$items[$x]["shop_id"];

      $marg_act = round(($item_price - $items[$x]["item_costprice_netto"]) / $items[$x]["item_costprice_netto"] * 100,2);
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os">
            <input type="checkbox" class="chkme" name="item_act_<?=$idx?>" id="item_act_<?=$idx?>" value="1">
         </td>
         <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
         <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
         <td class="content_row_os"><?=$unitdesc?></td>
         <td class="content_row_os"><?=$items[$x]["item_code"]?>&nbsp;</td>
         <td class="content_row_os" align="left"><?=$items[$x]["supp_company"]?></td>
         <td class="content_row_os" align="right"><?=printPrice($items[$x]["item_costprice_netto"],4)?></td>
         <td class="content_row_os" align="center"><?=printPrice($marg_act,2)?></td>
         <td class="content_row_os" align="center"><?=printPrice($items[$x]["cat_calc_price_perc"])?></td>
         <td class="content_row_os" align="right">
            <input type="hidden" class="mval" name="itembaseprice_<?=$idx?>" id="itembaseprice_<?=$idx?>"
            value="<?=(float)$items[$x]["item_costprice_netto"]?>">
            <input type="hidden" class="mval" name="itemcostprice_<?=$idx?>" id="itemcostprice_<?=$idx?>"
            value="<?=(float)$item_price?>">
            <input type="hidden" class="mval" name="itemshopprice_<?=$idx?>" id="itemshopprice_<?=$idx?>"
            value="<?=(float)$itemshopprice?>">
            <?=printPrice($item_price,4)?>
         </td>
         <td class="content_row_os" align="right">
            <input type="text" class="text" name="itemnewprice_<?=$idx?>" id="itemnewprice_<?=$idx?>"
            style="width:70px;text-align:right"
            value="<?=printPrice($item_price,4)?>">
         </td>
      </tr>
      <?php
   }
}

if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="11" align="center">
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
</form>
<br>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createItemBuyPrice($CON, $repsql);

if($_REQUEST["printxls"])
  $xlsfile = xls_createItemBuyPrice($CON, $repsql);

if($pdffile != "")
{
   $doctitle = "Lista-precios-compra.pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<?php
if($xlsfile != "")
{
   $doctitle = "Lista-precios-compra.xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
$_SESSION["JSEXEC"] .= ";$('#obitpanel').html('');";
?>