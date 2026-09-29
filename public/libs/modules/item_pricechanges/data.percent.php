<script language="JavaScript">
function round5(x)
{
   return (x % 5) >= 2.0 ? parseInt(x / 5) * 5 + 5 : parseInt(x / 5) * 5;
}
function setMainMargen()
{
   var mvalue  = document.getElementById('item_price_perc').value;
   var mmode   = document.getElementById('item_price_mode').value;
   var xmvalue = mvalue.replace('.','');
   xmvalue = xmvalue.replace(',','.');
   xmvalue = parseFloat(xmvalue);

   if(isNaN(xmvalue))
   {
      xmvalue = 0;
      mvalue  = 0;
   }

   if(mmode == '1')
      xmvalue = xmvalue * -1;
   
   $(".mval").each(function ()
   {
      var itemid  = $(this).attr('id');
      var idarr   = itemid.split('_');
      var ididx   = idarr[1] + '_' + idarr[2] + '_' + idarr[3] + '_' + idarr[4];

      var costprice  = parseFloat(document.getElementById('itemcostprice_' +ididx).value);
      var newprice   = document.getElementById('itemnewprice_' +ididx);
      var itemact    = document.getElementById('item_act_' +ididx).checked;
      if(itemact)
      {
         if(idarr[4] == '1')
         {
            newprice.value = Math.round(costprice + (costprice / 100 * xmvalue));
         }
         else
         {
            newprice.value = Math.round((costprice + (costprice / 100 * xmvalue)) *100)/100;
            newprice.value = newprice.value.replace('.',',');
         }
         if(document.getElementById('sql_applytosell').checked)
         {
            var soldprice  = parseFloat(document.getElementById('sellval_old_' +ididx).value);
            var snewprice   = document.getElementById('sellval_new_' +ididx);
            snewprice.value = Math.round(soldprice + (soldprice / 100 * xmvalue));
            if(document.getElementById('sql_round').value == '0')
               snewprice.value = round5(snewprice.value);
         }
      }
   });
}
</script>
<div style="position:fixed;top:30px;left:1000px">
<?=Nifty_printH("box1", "250")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header">Cambio de precios</td>
</tr>
<tr>
   <td class="content_row">
      <nobr>
      <select class="text" style="width:130px" id="item_price_mode">
         <option value="0"> Aumentar Precios</option>
         <option value="1"> Bajar Precios</option>
      </select>
      &nbsp;
      <input type="text" class="text" id="item_price_perc" style="width:60px;text-align:center" value=""> %
      </nobr>
   </td>
</tr>
<tr>
   <td class="content_row">
      <input type="checkbox" value="1" name="sql_applytosell" id="sql_applytosell"
      onclick="$('#tr_sql_applytosell').hide(); if(this.checked) $('#tr_sql_applytosell').show();"> Modificar a precios de venta
   </td>
</tr>
<tr id="tr_sql_applytosell" style="display:none">
   <td class="content_row">
      <nobr>
      Redondeo $:
      <select class="text" id="sql_round" style="width:120px">
         <option value="0">Multiple de 5</option>
         <option value="1">Sin Redondeo</option>
      </select>
      </nobr>
   </td>
</tr>
<tr>
   <td class="content_row">
      <?php
      printButton("Aplicar a artículos marcados", "postnav", "javascript: deactivateFormChange()", "setMainMargen()", "document", 200);
      ?>
   </td>
</tr>
<tr>
   <td class="content_row">
      <?php
      printButton("Guardar cambios", "postnav_save", "javascript: deactivateFormChange()", "document.xform_savechg.submit()", "document", 200);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
</div>

<form action="index.php" method="post" name="xform_savechg">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="savechanges" value="1">
<?=Nifty_printH("box1", "980")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="25">
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_subheader">
      <input type="checkbox" onclick="$('.chkme').attr('checked',this.checked)">
   </td>
   <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
   <td class="content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
   <td class="content_tbl_subheader">Unidad</td>
   <td class="content_tbl_subheader">Codigo/Prov.</td>
   <td class="content_tbl_subheader" align="left"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
   <td class="content_tbl_subheader" align="right" style="border-left:3px double black">Precio/Compra<br>Basico/Actual</td>
   <td class="content_tbl_subheader" align="right">Precio/Compra<br>Basico/Nuevo</td>
   <td class="content_tbl_subheader" align="right" style="border-left:3px double black">Precio/Venta<br>Basico/Actual</td>
   <td class="content_tbl_subheader" align="right">Precio/Venta<br>Basico/Nuevo</td>
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

   $item_costprice_usd     = $items[$x]["item_costprice_usd"];
   $item_costprice_netto   = $items[$x]["item_costprice_netto"];

   if($taxes)
      $item_price = $item_costprice_netto;
   else
      $item_price = $item_costprice_usd;

   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]     = $items[$x]["item_number_prod"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]           = $items[$x]["item_title"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]         = $items[$x]["supp_short"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]             = $unitdesc;

   $idx = $items[$x]["item_type"]."_".$items[$x]["id"]."_".$items[$x]["supplierid"]."_".$taxes;
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row">
         <input type="checkbox" class="chkme" name="item_act_<?=$idx?>" id="item_act_<?=$idx?>" value="1">
      </td>
      <td class="content_row"><?=$items[$x]["item_number_prod"]?></td>
      <td class="content_row"><?=$items[$x]["item_title"]?></td>
      <td class="content_row"><?=$unitdesc?></td>
      <td class="content_row"><?=$items[$x]["item_code"]?>&nbsp;</td>
      <td class="content_row" align="left"><?=$items[$x]["supp_short"]?></td>
      <td class="content_row" align="right" style="border-left:3px double black">
         <input type="hidden" class="mval" name="itemcostprice_<?=$idx?>" id="itemcostprice_<?=$idx?>"
         value="<?=(float)$item_price?>">
         <?=printPrice($item_price,4)?>
      </td>
      <td class="content_row" align="right">
         <input type="text" class="text" name="itemnewprice_<?=$idx?>" id="itemnewprice_<?=$idx?>"
         style="width:70px;text-align:right"
         value="<?=printPrice($item_price,4)?>">
      </td>
      <td class="content_row" align="right" style="border-left:3px double black">
         <?=printPrice($items[$x]["item_sellprice_netto"],2)?>
      </td>
      <td class="content_row" align="right">
         <input type="hidden" class="sval" name="sellval_old_<?=$idx?>" id="sellval_old_<?=$idx?>"
         value="<?=(float)$items[$x]["item_sellprice_netto"]?>">
         <input type="text" class="text" name="sellval_new_<?=$idx?>" id="sellval_new_<?=$idx?>"
         style="width:70px;text-align:right"
         value="">
      </td>
   </tr>
   <?php
}

if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="7" align="center">
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
?>