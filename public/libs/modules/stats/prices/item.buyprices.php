<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "price_buying";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Proveedor" => "8", "Precio" => "6,7", "Codigo/Prov" => "11");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_financedsc"] = (int)$_REQUEST["sql_financedsc"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$suppliers  = getSuppliers($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $first = false;
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"] && !$first)
      {
         $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
         $first = true;
      }
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

//----------------------------------------------------------------------------------
$joisql = " INNER JOIN item_shops      t2 ON t1.id = t2.item_id
            INNER JOIN company_shops   t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
            INNER JOIN company_data    t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
            INNER JOIN item_suppliers  t5 ON ( t1.id = t5.item_id and ";

if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql .= " t5.item_supp_act = 1 ) ";

$joisql .= " INNER JOIN supplier t6 ON ( t5.supplier_id = t6.id ) ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
      $joisql .= " INNER JOIN item_productcats t7 ON t1.id = t7.item_id ";

//----------------------------------------------------------------------------------
$joisql2 = " INNER JOIN itemlist_shops       t2 ON t1.id = t2.item_id
             INNER JOIN company_shops        t3 ON ( t2.shop_id = t3.id and t3.shop_status = 1 )
             INNER JOIN company_data         t4 ON ( t3.shop_company_id = t4.id and t4.company_status = 1 )
             INNER JOIN itemlist_suppliers   t5 ON ( t1.id = t5.item_id and ";

if($_SESSION[$_sesmodulename]["sql_supplier"])
   $joisql2 .= " t5.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ) ";
else
   $joisql2 .= " t5.item_supp_act = 1 ) ";

$joisql2 .= " INNER JOIN supplier t6 ON ( t5.supplier_id = t6.id ) ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
      $joisql2 .= " INNER JOIN item_productcats_itemlist t7 ON t1.id = t7.item_id ";

//----------------------------------------------------------------------------------
$cntsql = " select count(distinct t1.id) 'cc'
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";

$datsql = " select t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name,
                   t5.item_costprice_netto, t5.item_costprice_usd, t6.supp_company, t6.id 'supplierid',
                   'item_type' 'I', t5.item_code, t6.supp_short
            from item t1
            {$joisql}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
     
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
{
   $datsql .= " and 1 = 2 ";
   $cntsql .= " and 1 = 2 ";
}
elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
{
   $datsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   $cntsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t3.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t2.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t7.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
      
//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;

//----------------------------------------------------------------------------------
$cntsql .= "UNION ALL
            select count(distinct t1.id) 'cc'
            from itemlist t1
            {$joisql2}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
$cntsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= "UNION ALL
            select t1.item_number_prod, t1.item_title, t1.id, t4.company_short, t3.shop_name,
                   t5.item_costprice_netto, t5.item_costprice_usd, t6.supp_company, t6.id 'supplierid',
                   'item_type' 'L', t5.item_code, t6.supp_short
            from itemlist t1
            {$joisql2}
            where
            t1.item_status    = 1 and
            t1.item_released  = 1 ";
            
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
{
   $datsql .= " and 1 = 2 ";
   $cntsql .= " and 1 = 2 ";
}
elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
{
   $datsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   $cntsql .= " and t1.id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
}

$datsql .= $seasql;


$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"] + (int)$itemcount[1]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$repsql  = $datsql;

//----------------------------------------------------------------------------------
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
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);
?>
<script language="JavaScript">
function detectEvent (event, mode)
{
   var xurl = './libs/modules/items/searchitem.fancy.php?mode=' +mode;
   var keyCode = ('which' in event) ? event.which : event.keyCode;
   if(keyCode == 112)
      showFancybox(xurl, 'iframe', 1000, 450, 'auto');
}
</script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Lista de precios de compra</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Articulo</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="105">
               <col>
            </colgroup>
            <tr>
               <td>
                  <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)" name="xf_search"
                  onblur="markfield(this,1);if(this.value != '') document.all.idxifrsrc.src='./libs/modules/stats/searchitem.php?search=' +this.value"
                  onkeyup="detectEvent(event, 'stats')">
               </td>
               <td>
                  <select class="text" style="width:270px" name="item_id"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
                     {
                        if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
                        {
                           $sql = " select *
                                    from item
                                    where
                                    id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
                           $item = $CON->select($sql);
                           $item = $item[0];
                        }
                        else
                        {
                           $sql = " select *
                                    from itemlist
                                    where
                                    id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
                           $item = $CON->select($sql);
                           $item = $item[0];
                        }

                        $desc       = trim(addslashes($item["item_title"]));
                        $unitdesc   = getItemUnitDesc($CON, $_SESSION[$_sesmodulename]["sql_item_id"], $_SESSION[$_sesmodulename]["sql_item_type"]);
                        ?>
                        <option value="<?=$_SESSION[$_sesmodulename]["sql_item_id"]?>#<?=$_SESSION[$_sesmodulename]["sql_item_type"]?>">
                           <?=$item["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)
                        </option>
                        <?php
                     }
                     ?>
                  </select>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Familia</td>
         <td class="content_row">
            <select class="text" name="sql_pcat" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($pcats AS $pcat)
               {  ?>
                  <option value="<?=$pcat["id"]?>"
                  <?php if($pcat["id"] == $_SESSION[$_sesmodulename]["sql_pcat"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Descuento fin.</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 0) echo "checked"?>> Aplicar
            <input type="radio" value="1" name="sql_financedsc" <?php if((int)$_SESSION[$_sesmodulename]["sql_financedsc"] == 1) echo "checked"?>> No Aplicar
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width='132'>            
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
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
      </form>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_row_os content_tbl_subheader"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_row_os content_tbl_subheader">Unidad</td>
         <td class="content_row_os content_tbl_subheader" align="left"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_row_os content_tbl_subheader" align="right">Precio<br>basico</td>
         <td class="content_row_os content_tbl_subheader" align="right">Desc<br>%</td>
         <td class="content_row_os content_tbl_subheader" align="right">Precio<br>Final</td>
         <td class="content_row_os content_tbl_subheader" align="right">Precio Ult.<br>Compra</td>
         <td class="content_row_os content_tbl_subheader" align="right">Fecha Ult.<br>Compra</td>
         <td class="content_row_os content_tbl_subheader" align="right">Factura<br>Compra</td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($items[$x]["item_type"] == "item_typeI")
            $items[$x]["item_type"] = "item";
         else
            $items[$x]["item_type"] = "itemlist";
            
         $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         $taxes         = getSupplierTaxes($CON, $items[$x]["supplierid"]);
         $buyval        = getSupplierFinalCostNetto($CON, $items[$x]["supplierid"], $items[$x]["id"], $items[$x]["item_type"], 0.00, $_SESSION[$_sesmodulename]["sql_financedsc"]);
         $lastbuyprice  = getSupplierItemLastBuyPrice($CON, $items[$x]["supplierid"], $items[$x]["id"], $items[$x]["item_type"]);
         $buytstamp     = (int)$lastbuyprice["invc_date"];
         $sinvc         = $lastbuyprice["invc_docnumber"];
         $sinvcid       = $lastbuyprice["id"];
         $buycostprice  = round($lastbuyprice["item_costprice_netto_dsc2"] / $lastbuyprice["item_amount"],2);
         $descperc      = round(($items[$x]["item_costprice_netto"] - $buyval) / $items[$x]["item_costprice_netto"] * 100,2);
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_code"]?>&nbsp;</td>
            <td class="content_row_os"><?=$unitdesc?></td>
            <td class="content_row_os" align="left"><?=$items[$x]["supp_short"]?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["item_costprice_netto"],4)?></td>
            <td class="content_row_os" align="right"><?=printPrice($descperc,2)?></td>
            <td class="content_row_os" align="right"><?=printPrice($buyval,4)?></td>
            <td class="content_row_os" align="right"><?=printPrice($buycostprice,2)?></td>
            <td class="content_row_os">
               <?php
               if($buytstamp)
                  echo date('d.m.Y', $buytstamp);
               else
                  echo "&nbsp;";
               ?>
            </td>
            <td class="content_row_os" align="right">
               <a href="javascript: deactivateFormChange()" class="link" onclick="document.all.idxifrsrc.src='index.php?mid=723&exec=edit&subexec=edit&id=<?=$sinvcid?>&printpdf=1'"><?=$sinvc?></a>
               <?php
               if((int)$sinvcid)
               {  ?>
                  <input class="button" type="button" value="&gt;&gt;" style="width:25px;"
                  onclick="location.href='index.php?mid=723&exec=edit&subexec=edit&id=<?=$sinvcid?>'">
                  <?php
               }
               ?>
            </td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]     = $items[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]           = $items[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]            = $items[$x]["item_code"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]         = $items[$x]["supp_short"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]             = $unitdesc;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["sinvc"]                = $sinvc;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_costprice_netto"] = printPrice($items[$x]["item_costprice_netto"],4);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["descperc"]             = printPrice($descperc,2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["buyval"]               = printPrice($buyval,4);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["buycostprice"]         = printPrice($buycostprice,2);
         if($buytstamp)
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["buytstamp"] = date('d.m.Y', $buytstamp);
         else
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["buytstamp"] = " ";
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="10" align="center">
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
      <br>
   </td>
</tr>
</table>
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
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>