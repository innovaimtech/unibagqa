<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stats_buying_supplier";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Proveedor" => "1", "Nombre" => "2", "RUT" => "3", "Extento" => "4", "Neto" => "5", "IVA" => "6", "Total" => "7");
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
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
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
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = 1;
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y');
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$datsql = " select t1.invc_supplier_id, t6.supp_company, t6.supp_short, t6.supp_rut, SUM(t1.invc_total_netto) 'invc_total_netto',
                   SUM(t1.invc_total_taxes) 'invc_total_taxes', SUM(t1.invc_total_taxes_exclude) 'invc_total_taxes_exclude',
                   SUM(t1.invc_total_brutto) 'invc_total_brutto'
            from invoices_buy t1
            INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6   ON ( t1.invc_supplier_id = t6.id )
            where
            t1.invc_status       > 1 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
$datsql .= " group by 1,2,3";
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
$datsql = " select t1.note_supplier_id, t6.supp_company, t6.supp_short, t6.supp_rut, SUM(t1.note_total_netto) 'invc_total_netto',
                   SUM(t1.note_total_taxes) 'invc_total_taxes', SUM(t1.note_total_taxes_exclude) 'invc_total_taxes_exclude',
                   SUM(t1.note_total_brutto) 'invc_total_brutto', t1.note_type
            from invoices_notes_buy t1
            INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6   ON ( t1.note_supplier_id = t6.id )
            where
            t1.note_status       > 1 and
            t1.note_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.note_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
$datsql .= " group by 1,2,3,4,9";
$noteitems = $CON->select($datsql);

for($x = 0; $x < count($noteitems) && $noteitems != false; $x++)
{
   $row = $noteitems[$x];
   $hascustomer = false;
   for($y = 0; $y < count($items) && $items != false && !$hascustomer; $y++)
   {
      if($items[$y]["invc_supplier_id"] == $row["note_supplier_id"])
      {
         $hascustomer = true;
         if($row["note_type"] == 1)
         {
            $items[$y]["invc_total_netto"]         -= $row["invc_total_netto"];
            $items[$y]["invc_total_taxes"]         -= $row["invc_total_taxes"];
            $items[$y]["invc_total_taxes_exclude"] -= $row["invc_total_taxes_exclude"];
            $items[$y]["invc_total_brutto"]        -= $row["invc_total_brutto"];
         }
         else
         {
            $items[$y]["invc_total_netto"]         += $row["invc_total_netto"];
            $items[$y]["invc_total_taxes"]         += $row["invc_total_taxes"];
            $items[$y]["invc_total_taxes_exclude"] += $row["invc_total_taxes_exclude"];
            $items[$y]["invc_total_brutto"]        += $row["invc_total_brutto"];
         }
      }
   }
   if(!$hascustomer)
   {
      $newidx = count($items);
      $items[$newidx]["invc_supplier_id"]          = $row["note_supplier_id"];
      $items[$newidx]["supp_company"]              = $row["supp_company"];
      $items[$newidx]["supp_short"]                = $row["supp_short"];
      $items[$newidx]["supp_rut"]                  = $row["supp_rut"];
         
      if($row["note_type"] == 1)
      {
         $items[$newidx]["invc_total_netto"]         -= $row["invc_total_netto"];
         $items[$newidx]["invc_total_taxes"]         -= $row["invc_total_taxes"];
         $items[$newidx]["invc_total_taxes_exclude"] -= $row["invc_total_taxes_exclude"];
         $items[$newidx]["invc_total_brutto"]        -= $row["invc_total_brutto"];
      }
      else
      {
         $items[$newidx]["invc_total_netto"]         += $row["invc_total_netto"];
         $items[$newidx]["invc_total_taxes"]         += $row["invc_total_taxes"];
         $items[$newidx]["invc_total_taxes_exclude"] += $row["invc_total_taxes_exclude"];
         $items[$newidx]["invc_total_brutto"]        += $row["invc_total_brutto"];
      }
   }
}

//----------------------------------------------------------------------------------
Array("Proveedor" => "1", "Nombre" => "2", "RUT" => "3", "Extento" => "4", "Neto" => "5", "IVA" => "6", "Total" => "7");

$orderarr = Array("1" => "supp_company", "2" => "supp_short", "3" => "supp_rut", "4" => "invc_total_taxes_exclude",
                  "5" => "invc_total_netto", "6" => "invc_total_taxes", "7" => "invc_total_brutto");
$orderfield = $orderarr[$_SESSION[$_sesmodulename]["orderBy"]];
function getResultOrderCallback($a, $b)
{
   global $orderfield;
   global $_sesmodulename;
   if(trim($_SESSION[$_sesmodulename]["orderSort"]) == "asc")
      return ($a[$orderfield] > $b[$orderfield]);
   else
      return ($a[$orderfield] < $b[$orderfield]);
}
usort($items, "getResultOrderCallback");

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

$_SESSION["HEADER"][$_sesmodulename]["FROM"] = date('d.m.Y', $sql_datefrom);
$_SESSION["HEADER"][$_sesmodulename]["TO"]   = date('d.m.Y', $sql_dateto);

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Compras por proveedor</b></td>
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
      onsubmit="return checkform(new Array(this.sql_company))">
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
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
               </td>
               <td class="content_row_clear" width="205" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
                  <nobr>
                  <select class="text" name="sql_month1" id="sql_month1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year1" id="sql_year1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  &nbsp;-&nbsp;
                  <select class="text" name="sql_month2" id="sql_month2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year2" id="sql_year2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  </nobr>
               </td>
               <td class="content_row_clear" width="50">
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
               </td>
               <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:80px" id="sql_date" name="sql_date" 
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                  </nobr>
               </td>
               <td class="content_row_clear" width="75">
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
               </td>
               <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:65px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:65px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
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
      </form>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col width="90">
         <col width="75">
         <col width="75">
         <col width="75">
         <col width="75">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" style="border-right:3px double black"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_subheader content_row_os" valign="top" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
      </tr>
      <?php

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["supp_company"]?></td>
            <td class="content_row_os"><?=$items[$x]["supp_short"]?>&nbsp;</td>
            <td class="content_row_os" style="border-right:3px double black"><?=$items[$x]["supp_rut"]?>&nbsp;</td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["invc_total_taxes_exclude"], 0, true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["invc_total_netto"], 0, true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["invc_total_taxes"], 0, true)?></td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["invc_total_brutto"], 0, true)?></td>
         </tr>
         <?php
         $ges_invc_total_taxes_exclude    += $items[$x]["invc_total_taxes_exclude"];
         $ges_invc_total_netto            += $items[$x]["invc_total_netto"];
         $ges_invc_total_taxes            += $items[$x]["invc_total_taxes"];
         $ges_invc_total_brutto           += $items[$x]["invc_total_brutto"];
         
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_company"]               = $items[$x]["supp_company"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_short"]                 = $items[$x]["supp_short"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["supp_rut"]                   = $items[$x]["supp_rut"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_taxes_exclude"]   = printPrice($items[$x]["invc_total_taxes_exclude"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_netto"]           = printPrice($items[$x]["invc_total_netto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_taxes"]           = printPrice($items[$x]["invc_total_taxes"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_brutto"]          = printPrice($items[$x]["invc_total_brutto"]);
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
      else
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_row_os" colspan="3" style="border-right:3px double black">Total</td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_taxes_exclude, 0, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_netto, 0, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_taxes, 0, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_brutto, 0, true)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_taxes_exclude"]   = printPrice($ges_invc_total_taxes_exclude);
         $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_netto"]           = printPrice($ges_invc_total_netto);
         $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_taxes"]           = printPrice($ges_invc_total_taxes);
         $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_brutto"]          = printPrice($ges_invc_total_brutto);
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
  $pdffile = doc_createStatsItemSuppliers($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemSuppliers($CON);
  
if($pdffile != "")
{
   $doctitle = "Compras-por-proveedor-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Compras-por-proveedor-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>