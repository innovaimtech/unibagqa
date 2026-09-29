<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["sql_datefrom"] == "")
   $_REQUEST["sql_datefrom"] = '01.01.'.(date('Y')-5);
if($_REQUEST["sql_dateto"] == "")
   $_REQUEST["sql_dateto"] = date('d.m.Y');

if($_REQUEST["rep_type"] == "")
   $_REQUEST["rep_type"] = "selling";

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from {$_REQUEST["itemtype"]}_suppliers t0
         INNER JOIN supplier t1 ON t0.supplier_id = t1.id
         where
         t0.item_id     =  {$_REQUEST["id"]} and
         t1.supp_status = 1
         order by t1.supp_company";
$suppliers  = $CON->select($sql);

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_REQUEST["sql_company"])
         array_push($selshops, $shop);
}
  
//----------------------------------------------------------------------------------
$_sesmodulename = "price_history_prodbuy";
unset($_SESSION["STATS"][$_sesmodulename]);
$_SESSION[$_sesmodulename]["sql_item_id"]    = $_REQUEST["id"];
$_SESSION[$_sesmodulename]["sql_item_type"]  = "item";
$_SESSION[$_sesmodulename]["sql_supplier"]   = $_REQUEST["sql_supplier"];
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="itemtype" value="<?=$_REQUEST["itemtype"]?>">
<input type="hidden" name="printpdf" value="0">
<input type="hidden" name="printxls" value="0">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="100">
   <col>
   <col width="100">
   <col width="400">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
</tr>
<tr>
   <td class="content_rowl">Periodo</td>
   <td class="content_row">
      <input type="text" style="width:70px" id="sql_datefrom" name="sql_datefrom"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_datefrom"]?>">
      &nbsp;-&nbsp;
      <input type="text" style="width:70px" id="sql_dateto" name="sql_dateto"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$_REQUEST["sql_dateto"]?>">
   </td>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <input type="radio" name="rep_type" value="selling"
      onclick="document.getElementById('idx_sql_buying').style.display='none';
               document.getElementById('idx_sql_selling').style.display='';"
      <?php if($_REQUEST["rep_type"] == "selling") echo "checked"?>> Precios de venta
      <input type="radio" name="rep_type" value="buying"
      onclick="document.getElementById('idx_sql_buying').style.display='';
               document.getElementById('idx_sql_selling').style.display='none';"
      <?php if($_REQUEST["rep_type"] == "buying") echo "checked"?>> Precios de compra
   </td>
</tr>
<tr id="idx_sql_selling" <?php if($_REQUEST["rep_type"] == "buying") echo "style='display:none'"?>>
   <td class="content_rowl">Empresa</td>
   <td class="content_row">
      <select class="text" name="sql_company" style="width:300px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setCompanyShop(this.value)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($companies AS $company)
         {  ?>
            <option value="<?=$company["id"]?>"
            <?php if($company["id"] == $_REQUEST["sql_company"]) echo "selected"?>><?=$company["company_short"]?></option><?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row">
      <select class="text" name="sql_shop" style="width:300px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setCompanyShopStorehouse(this.value)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($selshops AS $selshop)
         {  ?>
            <option value="<?=$selshop["id"]?>"
            <?php if($selshop["id"] == $_REQUEST["sql_shop"]) echo "selected"?>><?=$selshop["shop_name"]?>
            </option><?php
         }
         ?>
      </select>
   </td>
</tr>
<tr id="idx_sql_buying" <?php if($_REQUEST["rep_type"] == "selling") echo "style='display:none'"?>>
   <td class="content_rowl">Proveedor</td>
   <td class="content_row">
      <select class="text" name="sql_supplier" style="width:355px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($suppliers AS $supplier)
         {  ?>
            <option value="<?=$supplier["id"]?>"
            <?php if($supplier["id"] == $_REQUEST["sql_supplier"]) echo "selected"?>>
               <?=$supplier["supp_company"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">Descuento fin.</td>
   <td class="content_row">
      <input type="radio" value="0" name="sql_financedsc" <?php if((int)$_REQUEST["sql_financedsc"] == 0) echo "checked"?>> Aplicar
      <input type="radio" value="1" name="sql_financedsc" <?php if((int)$_REQUEST["sql_financedsc"] == 1) echo "checked"?>> No Aplicar
   </td>
</tr>
<tr>
   <td class="content_row" align="right" colspan="4">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <colgroup>
         <col width="135">
         <col width="130">
         <col>
      </colgroup>
      <tr>
         <td align="left">
            <?php
            printButton("Generar PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
            $_SESSION["_SUBMITBTN"] = 1;
            ?>
         </td>
         <td align="left">
            <?php
            printButton("Generar XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
            $_SESSION["_SUBMITBTN"] = 1;
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
if($_REQUEST["rep_type"] == "selling")
{
   $sql = " select t1.*, t2.shop_name, t3.company_name, t4.user_lastname
            from pricehist_sell t1
            LEFT OUTER JOIN company_shops t2 ON t1.prc_shop_id = t2.id
            LEFT OUTER JOIN company_data  t3 ON t2.shop_company_id = t3.id
            LEFT OUTER JOIN user          t4 ON t1.prc_crtusr = t4.id
            where
            t1.prc_item_id    = {$_REQUEST["id"]} and
            t1.prc_item_type  = '{$_REQUEST["itemtype"]}' ";

   //----------------------------------------------------------------------------------
   $_REQUEST["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_REQUEST["sql_shop"]      = (int)$_REQUEST["sql_shop"];

   if($_REQUEST["sql_company"])
      $sql .= " and t2.shop_company_id = {$_REQUEST["sql_company"]} ";
   if($_REQUEST["sql_shop"])
      $sql .= " and t1.prc_shop_id = {$_REQUEST["sql_shop"]} ";
      
   if($_REQUEST["sql_datefrom"] != "" || $_REQUEST["sql_dateto"] != "")
   {

      $sqldate_from = getDateFromString($_REQUEST["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_REQUEST["sql_dateto"], false);

      $sql .= " and t1.prc_crtdat between {$sqldate_from} and {$sqldate_to} ";
   }
   //----------------------------------------------------------------------------------
   $sql .= " order by t1.prc_crtdat desc";
   $sellhist = $CON->select($sql);

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["printpdf"])
   {
      $cols    = Array ("company_name"             => Array("name" => "Empresa", "format" => "text"),
                        "shop_name"                => Array("name" => "Sucursal", "format" => "text"),
                        "prc_crtdat"               => Array("name" => "Fecha", "format" => "datetime"),
                        "user_lastname"            => Array("name" => "Usuario", "format" => "text"),
                        "prc_sellprice_netto"      => Array("name" => "Precio (neto)", "format" => "price"),
                        "prc_sellprice_taxes_perc" => Array("name" => "IVA (%)", "format" => "perc"),
                        "prc_sellprice_taxes"      => Array("name" => "IVA ($)", "format" => "price"),
                        "prc_sellprice_brutto"     => Array("name" => "Precio (bruto)", "format" => "price"));
                        
      $pdffile = createOverviewDoc($CON, $_REQUEST["itemtype"], "Historia: Precios de venta" , "landscape", $cols, $sql);
   }
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col width="130">
      <col width="100">
      <col width="90">
      <col width="90">
      <col width="90">
      <col width="90">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="8">Historia: Precios de venta</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Empresa</td>
      <td class="content_tbl_subheader">Sucursal</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Usuario</td>
      <td class="content_tbl_subheader" align="right">Precio (neto)</td>
      <td class="content_tbl_subheader" align="right">IVA (%)</td>
      <td class="content_tbl_subheader" align="right">IVA ($)</td>
      <td class="content_tbl_subheader" align="right">Precio (bruto)</td>
   </tr>
   <?php
   for($x = 0; $x < count($sellhist) && $sellhist != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$sellhist[$x]["company_name"]?></td>
         <td class="content_row"><?=$sellhist[$x]["shop_name"]?></td>
         <td class="content_row"><?=displayDate($sellhist[$x]["prc_crtdat"])?></td>
         <td class="content_row"><?=$sellhist[$x]["user_lastname"]?></td>
         <td class="content_row" align="right"><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($sellhist[$x]["prc_sellprice_netto"])?></td>
         <td class="content_row" align="right"><?=printPrice($sellhist[$x]["prc_sellprice_taxes_perc"],2)?></td>
         <td class="content_row" align="right"><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($sellhist[$x]["prc_sellprice_taxes"])?></td>
         <td class="content_row" align="right"><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($sellhist[$x]["prc_sellprice_brutto"])?></td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="8" align="center" valign="middle" height="30">
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
if($_REQUEST["rep_type"] == "buying")
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.supp_company, t3.taxes_active, t4.user_lastname
            from pricehist_buy t1
            LEFT OUTER JOIN supplier      t2 ON t1.prc_supplier_id = t2.id
            LEFT OUTER JOIN country       t3 ON t2.supp_countryid = t3.id
            LEFT OUTER JOIN user          t4 ON t1.prc_crtusr = t4.id
            where
            t1.prc_item_id    = {$_REQUEST["id"]} and
            t1.prc_item_type  = '{$_REQUEST["itemtype"]}' ";
            
   //----------------------------------------------------------------------------------
   $_REQUEST["sql_supplier"] = (int)$_REQUEST["sql_supplier"];

   if($_REQUEST["sql_supplier"])
      $sql .= " and t1.prc_supplier_id = {$_REQUEST["sql_supplier"]} ";
      
   if($_REQUEST["sql_datefrom"] != "" || $_REQUEST["sql_dateto"] != "")
   {

      $sqldate_from = getDateFromString($_REQUEST["sql_datefrom"]);
      $sqldate_to   = getDateFromString($_REQUEST["sql_dateto"], false);

      $sql .= " and t1.prc_crtdat between {$sqldate_from} and {$sqldate_to} ";
   }
   $sql .= " order by t1.prc_crtdat desc";
   $buyhist = $CON->select($sql);
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="100">
      <col>
      <col width="90">
      <col width="90">
      <col width="90">
      <col width="90">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7">Historia: Precios de compra</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Proveedor</td>
      <td class="content_tbl_subheader">Fecha</td>
      <td class="content_tbl_subheader">Usuario</td>
      <td class="content_tbl_subheader" align="right">Precio/Basico</td>
      <td class="content_tbl_subheader" align="right">Desc./$</td>
      <td class="content_tbl_subheader" align="right">Desc./%</td>
      <td class="content_tbl_subheader" align="right">Precio/Final</td>
   </tr>
   <?php
   for($x = 0; $x < count($buyhist) && $buyhist != false; $x++)
   {
      $buyval = getSupplierFinalCostNetto($CON, $buyhist[$x]["prc_supplier_id"], $_REQUEST["id"], "item", $buyhist[$x]["prc_costprice_netto"], (int)$_REQUEST["sql_financedsc"]);
      $diffp  = round(($buyhist[$x]["prc_costprice_netto"] - $buyval) / $buyhist[$x]["prc_costprice_netto"] * 100,0);
      $diffa  = round($buyhist[$x]["prc_costprice_netto"] - $buyval,0);
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$buyhist[$x]["supp_company"]?></td>
         <td class="content_row"><?=displayDate($buyhist[$x]["prc_crtdat"])?></td>
         <td class="content_row"><?=$buyhist[$x]["user_lastname"]?></td>
         <td class="content_row" align="right"><nobr><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($buyhist[$x]["prc_costprice_netto"],4)?></nobr></td>
         <td class="content_row" align="right">$ <?=printPrice($diffa,2)?></td>
         <td class="content_row" align="right"><?=printPrice($diffp,2)?></td>
         <td class="content_row" align="right"><nobr><?=$_SESSION["_CONF"]["conf_currency"]?> <?=printPrice($buyval,4)?></nobr></td>
      </tr>
      <?php
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]         = $buyhist[$x]["supp_company"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_crtdat"]           = displayDate($buyhist[$x]["prc_crtdat"]);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_lastname"]        = $buyhist[$x]["user_lastname"];
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["prc_costprice_netto"]  = printPrice($buyhist[$x]["prc_costprice_netto"],4);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["diffa"]                = printPrice($diffa,2);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["diffp"]                = printPrice($diffp,2);
      $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["buyval"]               = printPrice($buyval,4);
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
   <?=Nifty_printF(false)?>
   <?php
   if($_REQUEST["printpdf"])
      $pdffile = doc_createItemPriceHistProd($CON);
   if($_REQUEST["printxls"])
      $xlsfile = xls_createItemPriceHistProd($CON);
   if($pdffile != "")
   {
      $doctitle = "Historia-precios-compra-".time().".pdf";
      $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
      ?>
      <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
      <?php
      $pdffile = "";
   }
   if($xlsfile != "")
   {
      $doctitle = "Historia-precios-compra-".time().".xls";
      $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
      ?>
      <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
      <?php
      $xlsfile = "";
   }
}

if($pdffile != "")
{
   if($_REQUEST["rep_type"] == "selling")
      $doctitle = "Precios_venta_{$_REQUEST["sql_datefrom"]}-{$_REQUEST["sql_dateto"]}.pdf";
   else
   {
      $doctitle = "Precios_compra_{$_REQUEST["sql_datefrom"]}-{$_REQUEST["sql_dateto"]}.pdf";
   }
      
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}