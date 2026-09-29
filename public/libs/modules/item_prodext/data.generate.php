<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$sql = " select *
         from prod_item_ext
         where
         id = {$_REQUEST["id"]}";
$xheaddata = $CON->select($sql);
$xheaddata = $xheaddata[0];

if((int)$_REQUEST["setprices"])
{
   $currtme = time();
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "itemchangeprice_") !== false && strpos($reqkey, "itemchangeprice_") == 0 && (int)$_REQUEST[$reqkey])
      {
         $idx        = substr($reqkey, strpos($reqkey, "_") +1);
         $idxarr     = explode("_", $idx);
         $newprice   = getPrice($_REQUEST["itemnewprice_{$idx}"]);
         $newmarge   = getPrice($_REQUEST["itemmargen_{$idx}"],2);
         $itemid     = $idxarr[0];
         $itemtype   = $idxarr[1];

         if($newprice > 0.00 && $itemid > 0)
         {
            $sql = " select item_sellprice_taxes_perc
                     from {$itemtype}
                     where
                     id = {$itemid}";
            $item_sellprice_taxes_perc = $CON->select($sql);
            $item_sellprice_taxes_perc = $item_sellprice_taxes_perc[0]["item_sellprice_taxes_perc"];
            
            $item_sellprice_taxes      = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $newprice / 100 * $item_sellprice_taxes_perc);
            $item_sellprice_brutto     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $newprice + $item_sellprice_taxes);

            $sql = " update {$itemtype}
                     set
                     item_sellprice_brutto      = {$item_sellprice_brutto},
                     item_sellprice_taxes       = {$item_sellprice_taxes},
                     item_sellprice_netto       = {$newprice},
                     item_updusr                = {$_SESSION["user_id"]},
                     item_upddat                = {$currtme}
                     where
                     id = {$itemid}";
            $res = $CON->no_result($sql);

            
            if($itemtype == "itemlist")
               updateItemlistStorePrices($CON, $itemid);
            else
               updateItemStorePrices($CON, $itemid);
               
            registerSellPriceHistory($CON, $itemid, $itemtype);

            $_PRICECHANGES[$itemtype][$itemid] = $newprice;
            $_PRICEMARGENS[$itemtype][$itemid] = $newmarge;
         }
      }

      if(strpos($reqkey, "itemcostprice_") !== false && strpos($reqkey, "itemcostprice_") == 0 && (float)$_REQUEST[$reqkey])
      {
         $idx        = substr($reqkey, strpos($reqkey, "_") +1);
         $idxarr     = explode("_", $idx);
         $itemid     = $idxarr[0];
         $itemtype   = $idxarr[1];

         $_ADJ[$itemid][$itemtype]["AMOUNT"] += (float)($_REQUEST["itemamt_base_{$idx}"]);
         $_ADJ[$itemid][$itemtype]["COST"]   = getPrice($_REQUEST["itemcostprice_{$idx}"],2);
      }
   }

}
unset($_SESSION[$_sesmodulename]["ADJDATA"]);
$_SESSION[$_sesmodulename]["ADJDATA"] = $_ADJ;
?>
<form action="index.php" method="post" name="form_shppos">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="calc">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col width="120">
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Ingresar número de factura relacionada</td>
</tr>
<tr>
   <td class="content_rowl">Número factura</td>
   <td class="content_row">
      <input type="text" style="width:100px" id="invc_docnumber" name="invc_docnumber"
      class="text" value="<?=$_REQUEST["invc_docnumber"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
   <td class="content_row">
      <?php
      printButton("Buscar factura", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.form_shppos);", "tick-circle-frame", 140);
      $_SESSION["JSEXEC"] .= ";document.getElementById('invc_docnumber').focus();";
      ?>
   </td>
   <td class="content_row">&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php
if($_REQUEST["invc_docnumber"] != "")
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t3.company_short, t4.shop_name
            from invoices_buy t1
            LEFT OUTER JOIN company_data t3  ON t1.invc_company_id   = t3.id
            LEFT OUTER JOIN company_shops t4 ON t1.invc_shop_id      = t4.id
            where
            t1.invc_company_id   = {$xheaddata["req_company_id"]} and
            t1.invc_shop_id      = {$xheaddata["req_shop_id"]} and
            t1.invc_supplier_id  = {$xheaddata["req_supplier_id"]} and
            t1.invc_docnumber    = '{$_REQUEST["invc_docnumber"]}' and
            t1.invc_status       > 1";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   if((int)$headdata["id"])
   {
      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
               from supplier t1
               LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
               where
               t1.id = {$headdata["invc_supplier_id"]}";
      $supplier = $CON->select($sql);
      $supplier = $supplier[0];

      //----------------------------------------------------------------------------------
      $posdata = Array();
      $invcparts = getInvoiceBuyParts($CON, $headdata["id"]);
      for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
      {
         $xposdata = getInvoiceBuyPartsItems($CON, $headdata["id"], $invcparts[$x]["id"]);
         for($y = 0; $y < count($xposdata) && $xposdata != false; $y++)
         {
            $posdata[] = $xposdata[$y];
         }
      }

      //----------------------------------------------------------------------------------
      $prodposdata  = getProdExtPos($CON, $_REQUEST["id"]);

      //----------------------------------------------------------------------------------
      ?>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="130">
         <col width="360">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Datos de factura</td>
      </tr>
      <tr>
         <td class="content_rowl">Número</td>
         <td class="content_row"><?=$headdata["invc_number"]?></td>
         <td class="content_rowl">Número factura</td>
         <td class="content_row"><?=$headdata["invc_docnumber"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?=$headdata["company_short"]?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?=$headdata["shop_name"]?></td>
      </tr>
      <tr>
         <td class="content_rowl">Fecha factura</td>
         <td class="content_row"><?=date('d.m.Y', $headdata["invc_date"])?></td>
         <td class="content_rowl">IVA</td>
         <td class="content_row">
            <?php
            if((int)$headdata["invc_taxes"])
               echo "<b class='msg_save_ok'>CON IVA</b>";
            else
               echo "<b class='msg_save_err'>SIN IVA</b>";
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?=$supplier["supp_company"]?></td>
         <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][13]?></td>
         <td class="content_row"><?=$supplier["supp_rut"]?>&nbsp;</td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
      <?php
      if(!(int)$_REQUEST["setprices"])
      {  ?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="980">
         <colgroup>
            <col width="70">
            <col width="160">
            <col>
         </colgroup>
         <tr>
            <td class="content_row_clear">
               <nobr>
               <input type="text" class="text" style="width:50px;text-align:center" id="main_margen"
               onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
               </nobr>
            </td>
            <td class="content_row_clear">
               <?php
               printButton("Aplicar % utilidad", "postnav_save", "javascript: deactivateFormChange()", "setMainMargen()", "tick-circle-frame");
               ?>
            </td>
            <td class="content_row_clear" align="right">
               <input type="checkbox" onclick="markAllInvcItems(this.checked)"> Marcar todos los artículos
            </td>
         </tr>
         </table>
         <br>
         <?php
      }
      if(!(int)$_REQUEST["setprices"])
      {  ?>
         <form action="index.php" method="post" name="xform_invcvalues">
         <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
         <input type="hidden" name="exec" value="edit">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
         <input type="hidden" name="invc_docnumber" value="<?=$_REQUEST["invc_docnumber"]?>">
         <input type="hidden" name="setprices" value="">
         <?php
      }
      ?>
      <script language="JavaScript">
         function setMainMargen()
         {
            var mvalue  = document.getElementById('main_margen').value;
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
               $(this).val(mvalue);
               var itemid  = $(this).attr('id');
               var idarr   = itemid.split('_');
               var ididx   = idarr[1] + '_' + idarr[2] + '_' + idarr[3];

               var costprice  = parseFloat(document.getElementById('itemcostprice_' +ididx).value);
               var newprice   = document.getElementById('itemnewprice_' +ididx);
               newprice.value = Math.round(costprice + costprice / 100 * xmvalue);
            });
         }
         function setMainCostDistrib()
         {
            var mdcost = 0.00;
            $(".dval").each(function ()
            {
               if($(this).attr('checked'))
                  mdcost += parseFloat($(this).val());
            });
            var newmdcost   = document.getElementById('main_cost_distrib');
            newmdcost.value = Math.round(mdcost);

            if(newmdcost.value > 0.00)
            {
               $(".ctval").each(function ()
               {
                  var itemid  = $(this).attr('id');
                  var idarr   = itemid.split('_');
                  var ididx   = idarr[2] + '_' + idarr[3] + '_' + idarr[4];
                  
                  var itemamt    = parseFloat($('#itemamt_orig_' +ididx).val());

                  var crval      = parseFloat($(this).val());
                  var ctperc     = crval / parseFloat($('#main_cost_total').val()) * 100;
                  var ctaddval   = Math.round(newmdcost.value / 100 * ctperc);

                  ctaddval       = Math.round(ctaddval / itemamt);

                  var newprice   = document.getElementById('itemcostprice_' +ididx);
                  newprice.value = Math.round(crval + ctaddval);
               });
            }
            else
            {
               $(".ctval").each(function ()
               {
                  var crval      = parseFloat($(this).val());
                  var itemid  = $(this).attr('id');
                  var idarr   = itemid.split('_');
                  var ididx   = idarr[2] + '_' + idarr[3] + '_' + idarr[4];
                  var newprice   = document.getElementById('itemcostprice_' +ididx);
                  newprice.value = Math.round(crval);
               });
            }
            setMainMargen();
         }
         function setItemMargen(ididx)
         {
            var mvalue  = document.getElementById('itemmargen_' + ididx).value;
            var xmvalue = mvalue.replace('.','');
            xmvalue = xmvalue.replace(',','.');
            xmvalue = parseFloat(xmvalue);
            if(isNaN(xmvalue))
            {
               xmvalue = 0;
               mvalue  = 0;
            }
            var costprice  = parseFloat(document.getElementById('itemcostprice_' +ididx).value);
            var newprice   = document.getElementById('itemnewprice_' +ididx);
            newprice.value = Math.round(costprice + costprice / 100 * xmvalue);
         }
         function markAllInvcItems(stat)
         {
            $(".mchk").each(function ()
            {
               $(this).attr('checked', stat);
            });
         }
      </script>
      <input type="hidden" id="main_cost_distrib" value="0">
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="30">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="12">Calculo de precios</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os" align="center">Pos</td>
         <td class="content_tbl_subheader content_row_os">Artículo</td>
         <td class="content_tbl_subheader content_row_os" align="right">Cantidad<br>Enviado</td>
         <td class="content_tbl_subheader content_row_os" align="right">Cantidad<br>Facturado</td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Costo/U<br>Mejora</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Costo/U<br>Base</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Costo/U<br>Total</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="center"><nobr>%<br>Utilidad</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Precio/V<br>Actual</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Precio/V<br>Nuevo</nobr></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      $poscounter = 1;
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         unset($prodpos);
         unset($costdata);
         unset($sellprice);
         unset($idx);

         //----------------------------------------------------------------------------------
         foreach($prodposdata AS $prodposrow)
         {
            if($posdata[$x]["item_id"] == $prodposrow["serv_id"] && $posdata[$x]["item_type"] == "item")
            {
               $prodpos = $prodposrow;
               $idx = "{$prodpos["item_id_dest"]}_{$prodpos["item_type_dest"]}_{$prodpos["item_pos"]}";
            }
         }

         //----------------------------------------------------------------------------------
         $sql = " select item_sellprice_calc
                  from {$posdata[$x]["item_type"]}
                  where
                  id = {$posdata[$x]["item_id"]}";
         $item_price_calced = $CON->select($sql);
         $item_price_calced = (int)$item_price_calced[0]["item_sellprice_calc"];

         if($posdata[$x]["item_type"] == "manual")
            $item_price_calced = 1;

         //----------------------------------------------------------------------------------
         if($posdata[$x]["item_type"] != "manual")
         {
            $costdata   = getSupplierItemCosts($CON, 0, $prodpos["item_id"], $prodpos["item_type"]);
            $sellprice  = getShopItemStorePrice($CON, $xheaddata["req_shop_id"], $prodpos["item_id_dest"], $prodpos["item_type"]);
         }
         $costprod   = round($posdata[$x]["item_costprice_netto_dsc2"] / $prodpos["item_amount"],0);
         $costtotal  = $costprod + $costdata["item_costprice_netto"];
         ?>
         <tr bgcolor="<?=getRowColor($poscounter +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" align="center"><?=$poscounter?></td>
            <td class="content_row_os">
               <b><?=$posdata[$x]["item_title"]?></b>
               <br>
               <?=$prodpos["item_titledest"]?>
            </td>
            <td class="content_row_os" align="right">
               <?php
               if((int)$prodpos["req_id"])
               {  ?>
                  <?=printPrice($prodpos["item_amount"],2)?>
                  <?php
               }
               else
                  echo "- - -";
               ?>
            </td>
            <td class="content_row_os" align="right">
               <?php
               if((int)$prodpos["req_id"])
               {  ?>
                  <input type="hidden" class="atval" id="itemamt_orig_<?=$idx?>" name="itemamt_orig_<?=$idx?>" value="<?=$posdata[$x]["item_amount"]?>">
                  <input type="hidden" class="atval" id="itemamt_base_<?=$idx?>" name="itemamt_base_<?=$idx?>" value="<?=$prodpos["item_amount"]?>">
                  <?php
               }
               ?>
               <?=printPrice($posdata[$x]["item_amount"],2)?>
            </td>
            <td class="content_row_os" align="right"><?=printPrice($costprod)?></td>
            <td class="content_row_os" align="right">
               <?php
               if((int)$prodpos["req_id"])
               {  ?>
                  <?=printPrice($costdata["item_costprice_netto"])?>
                  <?php
               }
               else
                  echo "- - -";
               ?>
            </td>
            <td class="content_row_os" align="right">
               <?php
               if((int)$prodpos["req_id"])
               {  ?>
                  <input type="text" class="text" style="width:80px;text-align:right"
                  name="itemcostprice_<?=$idx?>" id="itemcostprice_<?=$idx?>" value="<?=$costtotal?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  onchange="setItemMargen('<?=$idx?>')">
                  <input type="hidden" class="ctval" id="itemcostprice_orig_<?=$idx?>" value="<?=$costtotal?>">
                  <?php
                  $gescosttotal += $costtotal;
               }
               else
               {  ?>
                  <input type="checkbox" class="dval" id="item_distrib_cost_<?=$idx?>" value="<?=$costprod?>"
                  onchange="setMainCostDistrib()">
                  <font style="color:red">Distribuir costo</font>
                  <?php
               }
               ?>
            </td>
            <td class="content_row_os" align="center">
               <?php
               if((int)$prodpos["req_id"])
               {
                  if((int)$_REQUEST["setprices"])
                  {
                     if($_PRICEMARGENS[$prodpos["item_type_dest"]][$prodpos["item_id_dest"]] != "")
                        echo "<b class='msg_save_ok'>{$_PRICEMARGENS[$prodpos["item_type_dest"]][$prodpos["item_id_dest"]]}</b>";
                     else
                        echo "<b class='msg_save_err'>- - -</b>";
                  }
                  elseif(!$item_price_calced)
                  {  ?>
                     <input type="text" class="text mval" style="width:50px;text-align:center"
                     name="itemmargen_<?=$idx?>" id="itemmargen_<?=$idx?>"
                     onfocus="markfield(this,0)" onblur="markfield(this,1)"
                     onchange="setItemMargen('<?=$idx?>')">
                     <?php
                  }
                  else
                     echo "- - -";
               }
               else
                  echo "- - -";
               ?>
            </td>
            <td class="content_row_os" align="right">
               <?php
               if((int)$prodpos["req_id"])
               {
                  if((int)$_REQUEST["setprices"])
                  {
                     if($_PRICECHANGES[$prodpos["item_type_dest"]][$prodpos["item_id_dest"]] > 0.00)
                        echo "<b class='msg_save_ok'>CAMBIADO</b>";
                     else
                        echo "<b class='msg_save_err'>NO CAMBIADO</b>";
                  }
                  elseif(!$item_price_calced)
                  {  ?>
                     <?=printPrice($sellprice["itemshop_sellprice_netto"])?>
                     <?php
                  }
                  else
                     echo "- - -";
               }
               else
                  echo "- - -";
               ?>
            </td>
            <td class="content_row_os" align="right">
               <?php
               if((int)$prodpos["req_id"])
               {  ?>
                  <input type="checkbox" class="checkbox mchk" value="1"
                  name="itemchangeprice_<?=$idx?>" id="item_change_price_<?=$idx?>">

                  <input type="text" class="text" style="width:80px;text-align:right"
                  name="itemnewprice_<?=$idx?>" id="itemnewprice_<?=$idx?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">
                  <?php
               }
               else
                  echo "- - -";
               ?>
            </td>
         </tr>
         <?php
         $poscounter++;
      }
      ?>
      </table>
      <?=Nifty_printF(false)?>
      <input type="hidden" id="main_cost_total" value="<?=$gescosttotal?>">
      <br>
      <?php
      if(!(int)$_REQUEST["setprices"])
      {  ?>
         <table border="0" cellspacing="0" cellpadding="0" width="980">
         <tr>
            <td align="right">
               <?php
               printButton("Siguiente Paso", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.xform_invcvalues.setprices.value='1';submitForm(document.xform_invcvalues);}", "tick-circle-frame", 160);
               ?>
            </td>
         </tr>
         </table>
         </form>
         <?php
      }
      else
      {  ?>
         <table border="0" cellspacing="0" cellpadding="0" width="980">
         <tr>
            <td align="right" style="padding-right:5px">
               <?php
               printButton("Generar Ajuste de stock", "postnav_save", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/item_prodext/data.generate.adj.fancy.php?id={$_REQUEST["id"]}&sesmodulename={$_sesmodulename}', 'iframe', 750, 400, 'auto')", "tick-circle-frame", 160);
               ?>
            </td>
            <td align="right" width="160">
               <?php
               printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=generate&id={$_REQUEST["id"]}&invc_docnumber={$_REQUEST["invc_docnumber"]}", "", "arrow-180", 160);
               ?>
            </td>
         </tr>
         </table>
         </form>
         <?php
      }
   }
   else
      echo "<b class='msg_save_err'>Factura no econtrada.</b>";
}