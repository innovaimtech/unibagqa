<script language="JavaScript">
function setNewPrices(xmode)
{
   $(".mval").each(function ()
   {
      var itemid     = $(this).attr('id');
      var idarr      = itemid.split('_');
      var ididx      = idarr[1] + '_' + idarr[2] + '_' + idarr[3] + '_' + idarr[4];
      var itemact    = $('#item_act_' +ididx).attr('checked');
      if(itemact && xmode)
         $('#itemnewprice_' +ididx).val($('#itemhistprice_' +ididx).val());
      else if(!itemact && !xmode)
         $('#itemnewprice_' +ididx).val($('#itemnhistprice_' +ididx).val());
   });
}
</script>
<?=Nifty_printH("box1", "200")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header">Cambio de precios</td>
</tr>
<tr>
   <td class="content_row">
      <?php
      printButton("Restablecer precios en artículos marcados", "postnav_save", "javascript: deactivateFormChange()", "document.xform_savechg.submit()", "document", 200);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
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
   <td class="content_row_os content_tbl_subheader">
      <input type="checkbox" onclick="$('.chkme').attr('checked',this.checked);setNewPrices(this.checked);">
   </td>
   <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
   <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
   <td class="content_row_os content_tbl_subheader">Unidad</td>
   <td class="content_row_os content_tbl_subheader">Codigo/Prov.</td>
   <td class="content_row_os content_tbl_subheader" align="left"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
   <td class="content_row_os content_tbl_subheader" align="right">Precio/Bascio<br>Anterior</td>
   <td class="content_row_os content_tbl_subheader">Fecha</td>
   <td class="content_row_os content_tbl_subheader" align="right">Precio/Bascio<br>Actual</td>
   <td class="content_row_os content_tbl_subheader" align="right">Precio/Bascio<br>Nuevo</td>
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

   /*
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]     = $items[$x]["item_number_prod"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]           = $items[$x]["item_title"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]         = $items[$x]["supp_short"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]             = $unitdesc;
   */
   $sql = " select t1.prc_item_id, t1.prc_costprice_netto, t1.prc_crtdat
            from pricehist_buy t1
            where
            t1.prc_item_id    = {$items[$x]["id"]} and
            t1.prc_item_type  = '{$items[$x]["item_type"]}' and
            t1.prc_supplier_id = {$items[$x]["supplierid"]} 
            order by t1.prc_crtdat desc
            LIMIT 0,2";
   $buyhist = $CON->select($sql);
   $buyhist = $buyhist[1];

   $idx = $items[$x]["item_type"]."_".$items[$x]["id"]."_".$items[$x]["supplierid"]."_".$taxes;
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os">
         <?php
         if((int)$buyhist["prc_crtdat"])
         {  ?>
            <input type="checkbox" class="chkme" name="item_act_<?=$idx?>" id="item_act_<?=$idx?>" value="1"
            onclick="if(this.checked) $('#itemnewprice_<?=$idx?>').val('<?=printPrice($buyhist["prc_costprice_netto"],4)?>');
                     else  $('#itemnewprice_<?=$idx?>').val('<?=printPrice($item_price,4)?>');">
            <?php
         }
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
      <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
      <td class="content_row_os"><?=$unitdesc?></td>
      <td class="content_row_os"><?=$items[$x]["item_code"]?>&nbsp;</td>
      <td class="content_row_os" align="left"><?=$items[$x]["supp_short"]?></td>
      <td class="content_row_os" align="right">
         <?php
         if((int)$buyhist["prc_crtdat"])
            echo printPrice($buyhist["prc_costprice_netto"],4);
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row_os" align="right">
         <?php
         if((int)$buyhist["prc_crtdat"])
            echo date('d.m.Y', $buyhist["prc_crtdat"]);
         else
            echo "&nbsp;";
         ?>
      </td>
      <td class="content_row_os" align="right">
         <input type="hidden" class="mval" name="itemcostprice_<?=$idx?>" id="itemcostprice_<?=$idx?>"
         value="<?=(float)$item_price?>">
         <?=printPrice($item_price,4)?>
         <input type="hidden" name="itemhistprice_<?=$idx?>" id="itemhistprice_<?=$idx?>"
         value="<?=printPrice($buyhist["prc_costprice_netto"],4)?>">
         <input type="hidden" name="itemnhistprice_<?=$idx?>" id="itemnhistprice_<?=$idx?>"
         value="<?=printPrice($item_price,4)?>">
      </td>
      <td class="content_row_os" align="right">
         <input type="text" class="text" name="itemnewprice_<?=$idx?>" id="itemnewprice_<?=$idx?>"
         style="width:70px;text-align:right"
         value="<?=printPrice($item_price,4)?>">
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