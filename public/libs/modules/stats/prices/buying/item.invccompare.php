<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_buying_products_invclist";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Numero" => "4", "Artículo" => "3", "Unidad" => "8", "Cod/Prov" => "14", "Proveedor" => "9", "Tipo" => "12", "DOCTO" => "11", "Fecha" => "7", "Cantidad" => "5",  "Compra<br>Unitario" => "6");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_financedsc"]    = (int)$_REQUEST["sql_financedsc"];
   $_SESSION[$_sesmodulename]["sql_prccomp"]       = (int)$_REQUEST["sql_prccomp"];
   $_SESSION[$_sesmodulename]["sql_invcnum"]       = trim(addslashes($_REQUEST["sql_invcnum"]));
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_chist"])
   $_SESSION[$_sesmodulename]["sql_chist"] = 1;

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $seasql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
}
if($_SESSION[$_sesmodulename]["sql_shop"])
{
   $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
}
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $seasql .= " and t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
}

//----------------------------------------------------------------------------------
$datsql = " select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_costprice_netto_dsc2,
                   t1.invc_date, t7.unit_name, t6.supp_short, t6.supp_rut, t1.invc_docnumber, 'note_type' 'invoice',
                   t3.item_costprice_taxes_perc, t9.item_code, t9.item_costprice_netto, t1.invc_supplier_id,
                   t10.pay_title, t1.id 'invc_id', t6.supp_dsc_finance_calc, t6.supp_dsc_finance
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t2          ON t1.id = t2.part_invc_id
            INNER JOIN invoices_buy_parts_items t3    ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
            INNER JOIN company_data t4                ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item t5                        ON ( t3.item_id = t5.id and t3.item_type = 'item' )
            LEFT OUTER JOIN supplier t6               ON ( t1.invc_supplier_id = t6.id )
            LEFT OUTER JOIN item_units t7             ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats t8       ON t5.id = t8.item_id
            LEFT OUTER JOIN item_suppliers t9         ON ( t3.item_id = t9.item_id and t9.supplier_id = t1.invc_supplier_id )
            LEFT OUTER JOIN payments t10              ON t1.invc_paymentid = t10.id
            where
            t1.invc_status       > 1 and
            t1.invc_docnumber    = '{$_SESSION[$_sesmodulename]["sql_invcnum"]}' ";
$datsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= " UNION ALL
            select t3.item_id, t3.item_type, t5.item_title, t5.item_number_prod, t3.item_amount, t3.item_costprice_netto_dsc2,
                   t1.invc_date, t7.unit_name, t6.supp_short, t6.supp_rut, t1.invc_docnumber, 'note_type' 'invoice',
                   t3.item_costprice_taxes_perc, t9.item_code, t9.item_costprice_netto, t1.invc_supplier_id,
                   t10.pay_title, t1.id 'invc_id', t6.supp_dsc_finance_calc, t6.supp_dsc_finance
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t2             ON t1.id = t2.part_invc_id
            INNER JOIN invoices_buy_parts_items t3       ON ( t1.id = t3.invc_id and t2.id = t3.part_id )
            INNER JOIN company_data t4                   ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN itemlist t5                       ON ( t3.item_id = t5.id and t3.item_type = 'itemlist' )
            LEFT OUTER JOIN supplier t6                  ON ( t1.invc_supplier_id = t6.id )
            LEFT OUTER JOIN item_units t7                ON t5.item_unit = t7.id
            LEFT OUTER JOIN item_productcats_itemlist t8 ON t5.id = t8.item_id
            LEFT OUTER JOIN itemlist_suppliers t9        ON ( t3.item_id = t9.item_id and t9.supplier_id = t1.invc_supplier_id )
            LEFT OUTER JOIN payments t10                 ON t1.invc_paymentid = t10.id
            where
            t1.invc_status       > 1 and
            t1.invc_docnumber    = '{$_SESSION[$_sesmodulename]["sql_invcnum"]}' ";
$datsql .= $seasql;

$datsql .= "order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $sql = " select supp_short
            from supplier
            where
            id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["supp_short"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$_SESSION["HEADER"][$_sesmodulename]["FROM"] = date('d.m.Y', $sql_datefrom);
$_SESSION["HEADER"][$_sesmodulename]["TO"]   = date('d.m.Y', $sql_dateto);

printJSsetCompanyShop($shops);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["savecomments"])
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "comments_") !== false && strpos($reqkey, "comments_") == 0)
      {
         $idx = substr($reqkey, strpos($reqkey, "_") +1);
         $idx = explode("_", $idx);
         $invcid = $idx[0];
         $itemid = $idx[1];
         $itemtp = $idx[2];

         $_REQUEST["comments_{$invcid}_{$itemid}_{$itemtp}"] = trim(addslashes($_REQUEST["comments_{$invcid}_{$itemid}_{$itemtp}"]));

         $sql = " delete from stat_invoice_buy_comments
                  where
                  invc_id   = {$invcid} and
                  item_id   = {$itemid} and
                  item_type = '{$itemtp}'";
         $CON->no_result($sql);

         if($_REQUEST["comments_{$invcid}_{$itemid}_{$itemtp}"] != "")
         {
            $sql = " insert into stat_invoice_buy_comments
                     (invc_id, item_id, item_type, comments)
                     VALUES
                     ({$invcid}, {$itemid}, '{$itemtp}', '{$_REQUEST["comments_{$invcid}_{$itemid}_{$itemtp}"]}')";
            $CON->no_result($sql);
         }
      }
   }
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Comparar Facturas con Lista de Precios</b></td>
   <td align="right" class="content_row_clear"></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_company, this.sql_invcnum, this.sql_supplier))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <input type="hidden" name="savecomments" value="0">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="110">
         <col>
         <col width="90">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
         <td class="content_rowl">Número Factura *</td>
         <td class="content_row">
            <input type="text" style="width:100px" id="sql_invcnum" name="sql_invcnum" class="text"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=$_SESSION[$_sesmodulename]["sql_invcnum"]?>">
         </td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor *</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Comparar Precio</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_prccomp" <?php if((int)$_SESSION[$_sesmodulename]["sql_prccomp"] == 0) echo "checked"?>> Con OC
            <input type="radio" value="1" name="sql_prccomp" <?php if((int)$_SESSION[$_sesmodulename]["sql_prccomp"] == 1) echo "checked"?>> Con Lista de Precios
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Descuento fin.</td>
         <td class="content_row" colspan="3">
            <input type="radio" value="0" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 0) echo "checked"?>> Aplicar
            <input type="radio" value="1" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 1) echo "checked"?>> No Aplicar
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
               </td>
               <td align="right">
                  <?php
                  printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      <br>
      <?=Nifty_printH("boxopt_b", "980")?>
      <table border="0" cellspacing="0" cellpadding="0" width="100%">
      <tr>
         <td>&nbsp;</td>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton("Guardar Comentarios", "postnav_save", "javascript: deactivateFormChange()", "document.xform_itemsearch.savecomments.value='1';submitForm(document.xform_itemsearch);", "tick-circle-frame", 200);
            ?>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col><col><col><col><col><col><col><col><col><col><col>
         <col width="150">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="13">
            <?=$_SESSION["STATS"][$_sesmodulename]["CUSTOMER"]?>,
            Factura: <?=$_SESSION[$_sesmodulename]["sql_invcnum"]?>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["HEAD"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"].", Factura: ".$_SESSION[$_sesmodulename]["sql_invcnum"];
            if((int)$items[0]["invc_date"])
            {  ?>,
               Fecha: <?=date('d.m.Y', $items[0]["invc_date"])?>,
               <?=$items[0]["pay_title"]?>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["HEAD"] .= ", Fecha: ".date('d.m.Y', $items[0]["invc_date"]).", ".$items[0]["pay_title"];
            }
            ?>
         </td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right" style="border-left:3px double black">Cantidad<br>Factura</td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right">Cantidad<br>OC</td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right">Cantidad<br>Guia</td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right" style="border-left:3px double black">Precio/U<br>Factura</td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right">
            <?php
            if((int)$_SESSION[$_sesmodulename]["sql_prccomp"])
               echo "Precio/U<br>Lista";
            else
               echo "Precio/U<br>OC";
            ?>
         </td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right" style="border-left:3px double black">Dif<br>$</td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right">Dif<br>%</td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right">Observaciones</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      $x = 0;
      foreach($items AS $row)
      {
         $prc_unit = round($row["item_costprice_netto_dsc2"] / $row["item_amount"],0);
         $prc_oc   = 0.00;
         $amt_oc   = 0.00;
         $amt_shp  = 0.00;

         $invcparts = getInvoiceBuyParts($CON, $row["invc_id"]);
         for($y = 0; $y < count($invcparts) && $invcparts != false; $y++)
         {
            $part = $invcparts[$y];

            if($part["part_shp_id"] > 0)
            {
               $sql = " select *
                        from shipment
                        where
                        id  =  {$part["part_shp_id"]}";
               $headdata = $CON->select($sql);
               $headdata = $headdata[0];

               $_SHPMOVES[$headdata["shp_supplier_docnum"]] = date('d.m.Y', $headdata["shp_delivery_date"]);

               $posdata = getShipmentPos($CON, $part["part_shp_id"]);
               foreach($posdata AS $posrow)
               {
                  if($posrow["item_id"] == $row["item_id"] && $posrow["item_type"] == $row["item_type"])
                  {
                     $amt_shp = $posrow["item_amount_shipped"];
                  }
               }
            }
            //----------------------------------------------------------------------------------
            if($part["part_sord_id"] > 0)
            {
               $sql = " select *
                        from supplier_order
                        where
                        id = {$part["part_sord_id"]}";
               $headdata = $CON->select($sql);
               $headdata = $headdata[0];

               $_OCMOVES[$headdata["sord_number"]] = date('d.m.Y', $headdata["sord_date"]);
               
               $posdata = getSupplierOrderPos($CON, $part["part_sord_id"]);
               foreach($posdata AS $posrow)
               {
                  if($posrow["item_id"] == $row["item_id"] && $posrow["item_type"] == $row["item_type"])
                  {
                     $prc_oc = (float)round($posrow["item_costprice_netto_dsc"] / $posrow["item_amount"],0);
                     $amt_oc = $posrow["item_amount"]; 
                  }
               }
            }
         }
         if((int)$_SESSION[$_sesmodulename]["sql_prccomp"])
         {
            $prc_oc = getSupplierFinalCostNetto($CON, $row["invc_supplier_id"], $row["item_id"], "item", 0.00, 1);
         }
         $diffunit = round($prc_unit - $prc_oc,0);
         $diffperc = round($diffunit / $prc_unit * 100,0);

         $comidx   = $row["invc_id"]."_".$row["item_id"]."_".$posrow["item_type"];

         $sql = " select comments
                  from stat_invoice_buy_comments
                  where
                  invc_id   = {$row["invc_id"]} and
                  item_id   = {$row["item_id"]} and
                  item_type = '{$posrow["item_type"]}'";
         $comment = $CON->select($sql);
         $comment = $comment[0]["comments"];
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$row["item_number_prod"]?></td>
            <td class="content_row_os"><?=$row["item_title"]?></td>
            <td class="content_row_os"><?=$row["unit_name"]?></td>
            <td class="content_row_os"><?=$row["item_code"]?>&nbsp;</td>
            <td class="content_row_os" align="right" style="border-left:3px double black"><?=printPrice($row["item_amount"], 2)?></td>
            <td class="content_row_os" align="right"><?=printPrice($amt_oc, 2)?></td>
            <td class="content_row_os" align="right"><?=printPrice($amt_shp, 2)?></td>
            <td class="content_row_os" align="right" style="border-left:3px double black"><?=printPrice($prc_unit, 0)?></td>
            <td class="content_row_os" align="right"><?=printPrice($prc_oc, 2)?></td>
            <td class="content_row_os" align="right" style="border-left:3px double black"><?=printPrice($diffunit, 0)?></td>
            <td class="content_row_os" align="right"><?=printPrice($diffperc, 0)?>%</td>
            <td class="content_row_os" align="right">
               <input type="text" name="comments_<?=$comidx?>" class="text" style="width:100%"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$comment?>">
            </td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]       = $row["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]             = $row["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_name"]              = $row["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]              = $row["item_code"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_amount"]            = printPrice($row["item_amount"], 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["amt_oc"]                 = printPrice($amt_oc, 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["amt_shp"]                = printPrice($amt_shp, 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_unit"]               = printPrice($prc_unit, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_oc"]                 = printPrice($prc_oc, 2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["diffunit"]               = printPrice($diffunit, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["diffperc"]               = printPrice($diffperc, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["comment"]                = $comment;
         $x++;
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="12" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      foreach(array_keys($_OCMOVES) AS $ocnum)
      {  ?>
         <tr>
            <td class="content_row_totals" colspan="13">Orden de compra: <?=$ocnum?>, Fecha: <?=$_OCMOVES[$ocnum]?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["FOOT"] = "Orden de compra: {$ocnum}, Fecha: {$_OCMOVES[$ocnum]}";
      }
      foreach(array_keys($_SHPMOVES) AS $shpnum)
      {  ?>
         <tr>
            <td class="content_row_totals" colspan="13">Guia de Despacho: <?=$shpnum?>, Fecha: <?=$_SHPMOVES[$shpnum]?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["FOOT"] = "Orden de compra: {$shpnum}, Fecha: {$_SHPMOVES[$shpnum]}";
      }
      
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
</form>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsItemProductsInvcComp($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemProductsInvcComp($CON);
  
if($pdffile != "")
{
   $doctitle = "Comparacion-Facturas-Lista-de-Precios-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Comparacion-Facturas-Lista-de-Precios-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>