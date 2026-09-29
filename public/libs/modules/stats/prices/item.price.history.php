<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "price_history";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "desc";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

if($_REQUEST["sql_type"] != "")
   $_SESSION[$_sesmodulename]["sql_type"] = $_REQUEST["sql_type"];
if($_SESSION[$_sesmodulename]["sql_type"] == "")
   $_SESSION[$_sesmodulename]["sql_type"] = "selling";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_type"]      = trim($_REQUEST["sql_type"]);
   $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_dateto"]    = trim($_REQUEST["sql_dateto"]);
   $_SESSION[$_sesmodulename]["sql_datefrom"]  = trim($_REQUEST["sql_datefrom"]);
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);

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
if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
{
   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN company_shops t2 ON t1.prc_shop_id     = t2.id
               LEFT OUTER JOIN company_data  t3 ON t2.shop_company_id = t3.id
               LEFT OUTER JOIN user          t4 ON t1.prc_crtusr      = t4.id ";

   $cntsql = " select count(t1.prc_item_id) 'cc'
               from pricehist_sell t1
               {$joisql}
               where
               1 = 1";

   $datsql = " select t1.*, t2.shop_name, t3.company_short, t4.user_lastname, t1.prc_item_id, t1.prc_item_type
               from pricehist_sell t1
               {$joisql}
               where
               1 = 1 ";
}
else
{
   //----------------------------------------------------------------------------------
   $seasql = "";
   $joisql = " LEFT OUTER JOIN supplier      t2 ON t1.prc_supplier_id = t2.id
               LEFT OUTER JOIN country       t3 ON t2.supp_countryid  = t3.id
               LEFT OUTER JOIN user          t4 ON t1.prc_crtusr      = t4.id ";

   $cntsql = " select count(t1.prc_item_id) 'cc'
               from pricehist_buy t1
               {$joisql}
               where
               1 = 1";

   $datsql = " select t1.*, t2.supp_company, t3.taxes_active, t4.user_lastname, t1.prc_item_id, t1.prc_item_type
               from pricehist_buy t1
               {$joisql}
               where
               1 = 1 ";

   $_SESSION[$_sesmodulename]["sql_company"]   = 0;
   $_SESSION[$_sesmodulename]["sql_shop"]      = 0;
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t2.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.prc_shop_id     = {$_SESSION[$_sesmodulename]["sql_shop"]} ";

$seasql .= " and t1.prc_item_id     = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
$seasql .= " and t1.prc_item_type   = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
{
   if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
      $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
   if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
      $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

   $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
   $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);

   $seasql .= " and t1.prc_crtdat between {$sqldate_from} and {$sqldate_to} ";
}

//----------------------------------------------------------------------------------
$cntsql   .= $seasql;
$datsql   .= $seasql;
$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$repsql  = $datsql;

//----------------------------------------------------------------------------------
$datas = $CON->select($datsql);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_REQUEST["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
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
   <td height="30"><b class="content_header">Historia de precios</b></td>
   <td align="right" class="content_row_clear"><?php printOverviewResults($itemcount) ?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst" onsubmit="return checkform(new Array(this.item_id))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="printpdf" value="0">
<input type="hidden" name="printxls" value="0">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col>
   <col width="100">
   <col width="385">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
</tr>
<tr>
   <td class="content_rowl">
      Articulo
      <img src="./images/menu/icons/magnifier-zoom.png">
   </td>
   <td class="content_row" colspan="3">
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
            <select class="text" style="width:760px" name="item_id"
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
</tr>
<tr>
   <td class="content_rowl">Periodo</td>
   <td class="content_row"><?printOverviewPeriodSelect($_sesmodulename)?>
   </td>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <?php
      if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
      {  ?>
         <input type="radio" name="sql_type" value="selling" checked> Precios de venta
         <?php
      }
      else
      {  ?>
         <input type="radio" name="sql_type" value="buying" checked> Precios de compra
         <?php
      }
      ?>
   </td>
</tr>
<tr id="idx_sql_selling" <?php if($_SESSION[$_sesmodulename]["sql_type"] == "buying") echo "style='display:none'"?>>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
</tr>
<tr>
   <td class="content_row" align="right" colspan="4">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <colgroup>
         <col width="135">
         <col>
         <col width="135">
         <col width="135">
      </colgroup>
      <tr>
         <td align="left">
            <?php
            if($_SESSION[$_sesmodulename]["sql_item_id"])
            {
               printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
               $_SESSION["_SUBMITBTN"] = 1;
            }
            ?>
         </td>
         <td align="left">
            <?php
            if($_SESSION[$_sesmodulename]["sql_item_id"])
            {
               printButton("Imprimir", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
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
<?php
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <?php
   printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
   ?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col width="130">
      <col width="90">
      <col width="130">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7">Historia: Precios de venta</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Usuario</td>
      <td class="content_tbl_subheader" align="right"><nobr>Precio/Basico/Neto</nobr></td>
      <td class="content_tbl_subheader" align="right">IVA (%)</td>
      <td class="content_tbl_subheader" align="right"><nobr>Precio/Basico/Bruto</nobr></td>
   </tr>
   <?php
   for($x = 0; $x < count($datas) && $datas != false && $_SESSION[$_sesmodulename]["sql_item_id"]; $x++)
   {
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=displayDate($datas[$x]["prc_crtdat"])?></td>
         <td class="content_row"><?=$datas[$x]["user_lastname"]?></td>
         <td class="content_row" align="right"><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($datas[$x]["prc_sellprice_netto"])?></td>
         <td class="content_row" align="right"><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($datas[$x]["prc_sellprice_taxes"])?></td>
         <td class="content_row" align="right"><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($datas[$x]["prc_sellprice_brutto"])?></td>
      </tr>
      <?php
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_crtdat"]           = displayDate($datas[$x]["prc_crtdat"]);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_lastname"]        = $datas[$x]["user_lastname"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_sellprice_netto"]  = printPrice($datas[$x]["prc_sellprice_netto"]);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_sellprice_taxes"]  = printPrice($datas[$x]["prc_sellprice_taxes"]);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_sellprice_brutto"] = printPrice($datas[$x]["prc_sellprice_brutto"]);
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="7" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay datos disponibles.</b>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?php
   printPageCounts($_SESSION[$_sesmodulename]["rows_per_page"], $itemcount, $_SESSION[$_sesmodulename]["page"]);
   ?>
   <?=Nifty_printF(false)?>
   <?php
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_type"] == "buying")
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col width="130">
      <col width="90">
      <col width="130">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="6">Historia: Precios de compra</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Proveedor</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Usuario</td>
      <td class="content_tbl_subheader" align="right"><nobr>Precio/Basico/Neto</nobr></td>
      <td class="content_tbl_subheader" align="right">IVA (%)</td>
      <td class="content_tbl_subheader" align="right"><nobr>Precio/Basico/Bruto</nobr></td>
   </tr>
   <?php
   for($x = 0; $x < count($datas) && $datas != false && $_SESSION[$_sesmodulename]["sql_item_id"]; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$datas[$x]["supp_company"]?></td>
         <td class="content_row"><?=displayDate($datas[$x]["prc_crtdat"])?></td>
         <td class="content_row"><?=$datas[$x]["user_lastname"]?></td>
         <td class="content_row" align="right"><nobr><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($datas[$x]["prc_costprice_netto"])?></nobr></td>
         <td class="content_row" align="right"><nobr><?=printPrice($datas[$x]["prc_costprice_taxes_perc"],2)?></nobr></td>
         <td class="content_row" align="right"><nobr><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($datas[$x]["prc_costprice_brutto"])?></nobr></td>
      </tr>
      <?php
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]             = $datas[$x]["supp_company"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_crtdat"]               = displayDate($datas[$x]["prc_crtdat"]);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_lastname"]            = $datas[$x]["user_lastname"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_costprice_netto"]      = printPrice($datas[$x]["prc_costprice_netto"]);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_costprice_taxes_perc"] = printPrice($datas[$x]["prc_costprice_taxes_perc"],2);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_costprice_brutto"]     = printPrice($datas[$x]["prc_costprice_brutto"]);
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="6" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay datos disponibles.</b>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}

//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createItemPriceHistory($CON, $repsql);

//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createItemPriceHistory($CON, $repsql);

if($pdffile != "")
{
   if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
      $doctitle = "Historia-precios-venta.pdf";
   else
      $doctitle = "Historia-precios-compra.pdf";

   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}

if($xlsfile != "")
{
   if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
      $doctitle = "Historia-precios-venta.xls";
   else
      $doctitle = "Historia-precios-compra.xls";

   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>