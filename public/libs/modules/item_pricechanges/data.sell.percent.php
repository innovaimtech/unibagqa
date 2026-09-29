<?php
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_listasact"] == 1)
{
   $sql = " select distinct t1.*
            from price_lists t1
            INNER JOIN price_lists_shops t2 ON t1.id = t2.pl_id
            where
            t1.pl_status = 1 and
            t2.shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]}
            order by t1.pl_title";
   $pricelists = $CON->select($sql);
   for($x = 0; $x < count($pricelists) && $pricelists != false; $x++)
   {
      $sql = " select *
               from price_lists_items
               where
               pl_id = {$pricelists[$x]["id"]}";
      $plitems = $CON->select($sql);
      foreach($plitems AS $plitem)
         $pricelists[$x]["_ITEMS"][$plitem["item_id"]."_".$plitem["item_type"]] = $plitem;
   }
}
?>
<script language="JavaScript">
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
      var ididx   = idarr[1] + '_' + idarr[2] + '_' + idarr[3];

      var costprice  = parseFloat(document.getElementById('itemcostprice_' +ididx).value);
      var newprice   = document.getElementById('itemnewprice_' +ididx);
      var itemact    = document.getElementById('item_act_' +ididx).checked;
      if(itemact)
      {
         newprice.value = Math.round(costprice + (costprice / 100 * xmvalue));
         if(document.getElementById('sql_round').value == '0')
            newprice.value = round5(newprice.value);
      }
   });

   $(".xmval").each(function ()
   {
      var itemid  = $(this).attr('id');
      var idarr   = itemid.split('_');
      var ididx   = idarr[1] + '_' + idarr[2] + '_' + idarr[3];
      var plidx   = idarr[1] + '_' + idarr[2] + '_' + idarr[3] + '_' + idarr[4];

      var costprice1  = parseFloat(document.getElementById('plbruttoprice1_' +plidx).value);
//       var costprice2  = parseFloat(document.getElementById('plbruttoprice2_' +plidx).value);
      
      var newprice1   = document.getElementById('plitemnewprice1_' +plidx);
//       var newprice2   = document.getElementById('plitemnewprice2_' +plidx);

      var itemact    = document.getElementById('item_act_' +ididx).checked;
      if(itemact)
      {
         //newprice1.value = Math.round(costprice1 + (costprice1 / 100 * xmvalue));
         newprice1.value = myRound((costprice1 + (costprice1 / 100 * xmvalue)),2);
         newprice1.value = newprice1.value.replace('.',',');
//          newprice2.value = Math.round(costprice2 + (costprice2 / 100 * xmvalue));
         if(document.getElementById('sql_round').value == '0')
         {
//             newprice1.value = round5(newprice1.value);
//             newprice2.value = round5(newprice2.value);
         }
      }
   });
}
function round5(x)
{
   return (x % 5) >= 2.0 ? parseInt(x / 5) * 5 + 5 : parseInt(x / 5) * 5;
}
</script>
<?=Nifty_printH("box1", "980")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col width="170">
   <col width="100">
   <col width="60">
   <col>
   <col width="200">
   <col width="200">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="7">Cambio de precios</td>
</tr>
<tr>
   <td class="content_rowl">Porcentaje</td>
   <td class="content_row">
      <select class="text" style="width:170px" id="item_price_mode">
         <option value="0"> Aumentar Precios
         <option value="1"> Bajar Precios
      </select>
   </td>
   <td class="content_row">
      <nobr>
      <input type="text" class="text" id="item_price_perc" style="width:60px;text-align:center"
      value=""> %
      </nobr>
   </td>
   <td class="content_rowl">Redondeo</td>
   <td class="content_row">
      <nobr>
      <select class="text" id="sql_round" style="width:120px">
         <option value="0">Multiple de 5</option>
         <option value="1">Sin Redondeo</option>
      </select>
      </nobr>
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
</colgroup>
<tr>
   <td class="content_tbl_subheader content_row_os">
      <input type="checkbox" onclick="$('.chkme').attr('checked',this.checked)">
   </td>
   <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
   <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
   <td class="content_tbl_subheader content_row_os">Unidad</td>
   <td class="content_tbl_subheader content_row_os" align="right" style="border-left:3px double black">Precio/Venta/Bruto<br>Basico/Actual</td>
   <td class="content_tbl_subheader content_row_os" align="right">Precio/Venta/Bruto<br>Basico/Nuevo</td>
   <?php
   foreach($pricelists AS $pricelist)
   {  ?>
      <td class="content_tbl_subheader content_row_os" align="right"><?=$pricelist["pl_title"]?><br>Precio/Neto/Actual</td>
      <td class="content_tbl_subheader content_row_os" align="right"><?=$pricelist["pl_title"]?><br>Precio/Neto/Nuevo</td>
      <?php
   }
   ?>
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
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]     = $items[$x]["item_number_prod"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]           = $items[$x]["item_title"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]         = $items[$x]["supp_company"];
   $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]             = $unitdesc;

   $idx = $items[$x]["item_type"]."_".$items[$x]["id"]."_".$items[$x]["shop_id"];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os">
         <input type="checkbox" class="chkme" name="item_act_<?=$idx?>" id="item_act_<?=$idx?>" value="1">
      </td>
      <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
      <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
      <td class="content_row_os"><?=$unitdesc?></td>
      <td class="content_row_os" align="right" style="border-left:3px double black">
         <input type="hidden" class="mval" name="itemcostprice_<?=$idx?>" id="itemcostprice_<?=$idx?>"
         value="<?=(float)$items[$x]["itemshop_sellprice_brutto"]?>">
         <?=printPrice($items[$x]["itemshop_sellprice_brutto"])?>
      </td>
      <td class="content_row_os" align="right">
         <input type="text" class="text" name="itemnewprice_<?=$idx?>" id="itemnewprice_<?=$idx?>"
         style="width:70px;text-align:right"
         value="">
      </td>
      <?php
      foreach($pricelists AS $pricelist)
      {
         $plidx = $items[$x]["item_type"]."_".$items[$x]["id"]."_".$items[$x]["shop_id"]."_".$pricelist["id"];
         ?>
         <td class="content_row_os" align="right">
            <?=printPrice($pricelist["_ITEMS"][$items[$x]["id"]."_".$items[$x]["item_type"]]["item_sellprice_netto"],2)?>
         </td>
         <td class="content_row_os" align="right">
            <?php
            if((int)$pricelist["_ITEMS"][$items[$x]["id"]."_".$items[$x]["item_type"]]["pl_id"])
            {  ?>
               <input type="hidden" class="xmval" name="plbruttoprice1_<?=$plidx?>" id="plbruttoprice1_<?=$plidx?>"
               value="<?=(float)$pricelist["_ITEMS"][$items[$x]["id"]."_".$items[$x]["item_type"]]["item_sellprice_netto"]?>">
               <nobr>
               <input type="text" class="text" name="plitemnewprice1_<?=$plidx?>" id="plitemnewprice1_<?=$plidx?>"
               style="width:70px;text-align:right"
               value="">
               </nobr>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <?php
      }
      ?>
   </tr>
   <?php
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