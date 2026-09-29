<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "statsinvoicesshops";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Cliente" => "1", "Nombre" => "2", "RUT" => "3", "Extento" => "4", "Neto" => "5", "IVA" => "6", "Total" => "7");
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_type"]          = (int)$_REQUEST["sql_type"];
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_reptype"]       = (int)$_REQUEST["sql_reptype"];
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
$companies  = getCompanies($CON, true);
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
   $_SESSION[$_sesmodulename]["sql_selmode"] = 1;
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
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

$_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_date"] = date("d.m.Y", $sql_datefrom)." - ".date("d.m.Y", $sql_dateto);
if(!(int)$_SESSION[$_sesmodulename]["sql_type"])
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "Todo";
elseif((int)$_SESSION[$_sesmodulename]["sql_type"] == 1)
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "Factura";
elseif((int)$_SESSION[$_sesmodulename]["sql_type"] == 2)
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "Boleta";
elseif((int)$_SESSION[$_sesmodulename]["sql_type"] == 3)
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"] = "NC/ND";

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$datsql = " select t1.invc_number, t1.invc_shop_id, t1.invc_docnumber, t1.invc_date, t1.invc_total_netto, t1.invc_total_taxes,
            t1.invc_total_taxes_exclude, t1.invc_total_brutto, 'invc' 'invc', t2.shop_name, t3.company_short,
            t4.cust_name, t5.user_firstname, t5.user_lastname, t1.invc_upddat
            from invoices_sell t1
            LEFT OUTER JOIN company_shops t2 ON t1.invc_shop_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.invc_company_id = t3.id
            LEFT OUTER JOIN customer t4 ON t1.invc_cust_id = t4.id
            LEFT OUTER JOIN user t5 ON t1.invc_userid_seller = t5.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto} ";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";

//----------------------------------------------------------------------------------
$datsql .= " UNION ALL
            select t1.invc_number, t1.invc_shop_id, t1.invc_docnumber, t1.invc_date, t1.invc_total_netto, t1.invc_total_taxes,
            t1.invc_total_taxes_exclude, t1.invc_total_brutto, 'invc' 'invc', t2.shop_name, t3.company_short,
            t4.cust_name, t5.user_firstname, t5.user_lastname, t1.invc_upddat
            from invoices_sell_bol t1
            LEFT OUTER JOIN company_shops t2 ON t1.invc_shop_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.invc_company_id = t3.id
            LEFT OUTER JOIN customer t4 ON t1.invc_cust_id = t4.id
            LEFT OUTER JOIN user t5 ON t1.invc_userid_seller = t5.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_iscredito    = 0 and
            t1.invc_vtype        = 1 and
            t1.invc_anulado_act  = 0 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto} ";

if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
$datsql .= " order by 11, 10, 4, 3";
$invcs = $CON->select($datsql);

$_RES = array();
if(!(int)$_SESSION[$_sesmodulename]["sql_type"] || $_SESSION[$_sesmodulename]["sql_type"] == 1)
{
   foreach($invcs AS $invc)
      $_RES[$invc["invc_shop_id"]][] = $invc;
}

//----------------------------------------------------------------------------------
$datsql = " select t1.invc_number, t1.invc_shop_id, t1.invc_docnumber, t1.invc_date, t1.invc_total_netto, t1.invc_total_taxes,
            t1.invc_total_taxes_exclude, t1.invc_total_brutto, 'invc' 'bol', t2.shop_name, t3.company_short,
            t4.cust_name, t5.user_firstname, t5.user_lastname, t1.invc_upddat
            from invoices_sell_bol t1
            LEFT OUTER JOIN company_shops t2 ON t1.invc_shop_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.invc_company_id = t3.id
            LEFT OUTER JOIN customer t4 ON t1.invc_cust_id = t4.id
            LEFT OUTER JOIN user t5 ON t1.invc_userid_seller = t5.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_vtype        = 0 and
            t1.invc_anulado_act  = 0 and
            t1.invc_iscredito    = 0 and
            t1.invc_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
$datsql .= " order by t3.company_short, t2.shop_name, t1.invc_date, t1.invc_docnumber";
$invcsbol = $CON->select($datsql);

if(!(int)$_SESSION[$_sesmodulename]["sql_type"] || $_SESSION[$_sesmodulename]["sql_type"] == 2)
{
   foreach($invcsbol AS $invc)
      $_RES[$invc["invc_shop_id"]][] = $invc;
}

//----------------------------------------------------------------------------------
$datsql = " select t1.note_number 'invc_number', t1.note_shop_id 'invc_shop_id', t1.note_docnumber 'invc_docnumber', t1.note_date 'invc_date',
            t1.note_total_netto 'invc_total_netto', t1.note_total_taxes 'invc_total_taxes',
            t1.note_total_taxes_exclude 'invc_total_taxes_exclude', t1.note_total_brutto 'invc_total_brutto', 'invc' 'nc',
            t2.shop_name, t3.company_short, t1.note_type,
            t4.cust_name, t5.user_firstname, t5.user_lastname, t1.note_upddat 'invc_upddat'
            from invoices_notes_sell t1
            LEFT OUTER JOIN company_shops t2 ON t1.note_shop_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.note_company_id = t3.id
            LEFT OUTER JOIN customer t4 ON t1.note_cust_id = t4.id
            LEFT OUTER JOIN user t5 ON t1.note_userid_seller = t5.id
            where
            t1.note_status       > 1 and
            t1.note_status       < 4 and
            t1.note_date between {$sql_datefrom} and {$sql_dateto}";
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
$datsql .= " order by t3.company_short, t2.shop_name, t1.note_date, t1.note_docnumber";
$notes = $CON->select($datsql);

if(!(int)$_SESSION[$_sesmodulename]["sql_type"] || $_SESSION[$_sesmodulename]["sql_type"] == 3)
{
   foreach($notes AS $invc)
      $_RES[$invc["invc_shop_id"]][] = $invc;
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
         array_push($selshops, $shop);
}

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_reptype"])
{
   foreach(array_keys($_RES) AS $shopid)
   {
      foreach($_RES[$shopid] AS $row)
      {
         $typestr = "Factura";
         if($row["invc"] == "invcbol")
            $typestr = "Boleta";
         if($row["invc"] == "invcnc")
            $typestr = "NC/ND";

         

         if($row["invc"] == "invcnc" && $row["note_type"] == 1)
         {
            $ges_invc_total_netto  -= $row["invc_total_netto"];
            $ges_invc_total_taxes  -= $row["invc_total_taxes"];
            $ges_invc_total_brutto -= $row["invc_total_brutto"];

            $_TOTALS[$shopid][$typestr]["invc_total_netto"]    -= $row["invc_total_netto"];
            $_TOTALS[$shopid][$typestr]["invc_total_taxes"]    -= $row["invc_total_taxes"];
            $_TOTALS[$shopid][$typestr]["invc_total_brutto"]   -= $row["invc_total_brutto"];
         }
         else
         {
            $ges_invc_total_netto  += $row["invc_total_netto"];
            $ges_invc_total_taxes  += $row["invc_total_taxes"];
            $ges_invc_total_brutto += $row["invc_total_brutto"];

            $_TOTALS[$shopid][$typestr]["invc_total_netto"]    += $row["invc_total_netto"];
            $_TOTALS[$shopid][$typestr]["invc_total_taxes"]    += $row["invc_total_taxes"];
            $_TOTALS[$shopid][$typestr]["invc_total_brutto"]   += $row["invc_total_brutto"];
         }

         $_SHOPS[$shopid]["SHOPNAME"] = $row["shop_name"];
         $_SHOPS[$shopid]["COMPNAME"] = $row["company_short"];
      }
   }
}

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Ventas por sucursal</b></td>
   <td align="right" class="content_row_clear"></td>
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
         <col width="80">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
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
               <td class="content_row_clear" width="185" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
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
                  -
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
         <td class="content_rowl">Empresa</td>
         <td class="content_row">
            <select class="text" name="sql_company" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShop(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($companies AS $company)
               {  ?>
                  <option value="<?=$company["id"]?>"
                  <?php if($company["id"] == $_SESSION[$_sesmodulename]["sql_company"]) echo "selected"?>><?=$company["company_short"]?></option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <input type="radio" name="sql_type" value="0"
            <?if(!(int)$_SESSION[$_sesmodulename]["sql_type"]) echo "checked"?>>&nbsp;Todo
            <input type="radio" name="sql_type" value="1"
            <?if((int)$_SESSION[$_sesmodulename]["sql_type"] == 1) echo "checked"?>>&nbsp;Factura
            <input type="radio" name="sql_type" value="2"
            <?if((int)$_SESSION[$_sesmodulename]["sql_type"] == 2) echo "checked"?>>&nbsp;Boleta
            <input type="radio" name="sql_type" value="3"
            <?if((int)$_SESSION[$_sesmodulename]["sql_type"] == 3) echo "checked"?>>&nbsp;Nota Credito/Debito
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Informe</td>
         <td class="content_row">
            <input type="radio" name="sql_reptype" value="0"
            <?if(!(int)$_SESSION[$_sesmodulename]["sql_reptype"]) echo "checked"?>>&nbsp;Total
            <input type="radio" name="sql_reptype" value="1"
            <?if((int)$_SESSION[$_sesmodulename]["sql_reptype"] == 1) echo "checked"?>>&nbsp;Detalle
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
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
                  if(count($_RES) > 0 && $_RES != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($_RES) > 0 && $_RES != false)
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
      <?php
      if(!(int)$_SESSION[$_sesmodulename]["sql_reptype"])
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="6">Totales por sucursal</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">Empresa</td>
            <td class="content_tbl_subheader content_row_os">Sucursal</td>
            <td class="content_tbl_subheader content_row_os">Tipo</td>
            <td class="content_tbl_subheader content_row_os" align="right">Total/Neto</td>
            <td class="content_tbl_subheader content_row_os" align="right">Total/IVA</td>
            <td class="content_tbl_subheader content_row_os" align="right">Total/Bruto</td>
         </tr>
         <?php
         $x = 0;
         foreach(array_keys($_SHOPS) AS $shopid)
         {
            foreach(array_keys($_TOTALS[$shopid]) AS $doctype)
            {  ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$_SHOPS[$shopid]["COMPNAME"]?></td>
                  <td class="content_row_os"><?=$_SHOPS[$shopid]["SHOPNAME"]?></td>
                  <td class="content_row_os"><?=$doctype?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_TOTALS[$shopid][$doctype]["invc_total_netto"])?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_TOTALS[$shopid][$doctype]["invc_total_taxes"])?></td>
                  <td class="content_row_os" align="right"><?=printPrice($_TOTALS[$shopid][$doctype]["invc_total_brutto"])?></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["compname"] = $_SHOPS[$shopid]["COMPNAME"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["shopname"] = $_SHOPS[$shopid]["SHOPNAME"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["type"]     = $doctype;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_netto"]  = printPrice($_TOTALS[$shopid][$doctype]["invc_total_netto"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_taxes"]  = printPrice($_TOTALS[$shopid][$doctype]["invc_total_taxes"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_brutto"] = printPrice($_TOTALS[$shopid][$doctype]["invc_total_brutto"]);
               $x++;
            }
         }
         ?>
         <tr bgcolor="<?=getRowColor($x)?>">
            <td class="content_row_totals content_row_os" colspan="3">Total</td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_netto, 0, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_taxes, 0, true)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_brutto, 0, true)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["compname"] = "<b>TOTAL</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_netto"]  = "<b>".printPrice($ges_invc_total_netto)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_taxes"]  = "<b>".printPrice($ges_invc_total_taxes)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_total_brutto"] = "<b>".printPrice($ges_invc_total_brutto)."</b>";
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      else
      {
         foreach(array_keys($_RES) AS $shopid)
         {  ?>
            <?=Nifty_printH("box1", "980")?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="75">
               <col width="90">
               <col width="75">
               <col>
               <col>
               <col width="100">
               <col width="100">
               <col width="100">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="8"><?=$_RES[$shopid][0]["shop_name"]?></td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os">Tipo</td>
               <td class="content_tbl_subheader content_row_os">Número</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Cliente</td>
               <td class="content_tbl_subheader content_row_os">Vendedor</td>
               <td class="content_tbl_subheader content_row_os" align="right">Total/Neto</td>
               <td class="content_tbl_subheader content_row_os" align="right">Total/IVA</td>
               <td class="content_tbl_subheader content_row_os" align="right">Total/Bruto</td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["HEAD"][$shopid]["shop_name"] = $_RES[$shopid][0]["shop_name"];
            $invc = $_RES[$shopid];

            $ges_invc_total_taxes_exclude = 0;
            $ges_invc_total_netto         = 0;
            $ges_invc_total_taxes         = 0;
            $ges_invc_total_brutto        = 0;

            //----------------------------------------------------------------------------------
            for($x = 0; $x < count($invc); $x++)
            {
               ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os">
                     <?php
                     $typestr = "Factura";
                     if($invc[$x]["invc"] == "invcbol")
                        $typestr = "Boleta";
                     if($invc[$x]["invc"] == "invcnc")
                        $typestr = "NC/ND";
                     echo $typestr;

                     if($invc[$x]["invc"] == "invcnc" && $invc[$x]["note_type"] == 1)
                     {
                        $invc[$x]["invc_total_taxes_exclude"]  = $invc[$x]["invc_total_taxes_exclude"] * -1;
                        $invc[$x]["invc_total_netto"]          = $invc[$x]["invc_total_netto"] * -1;
                        $invc[$x]["invc_total_taxes"]          = $invc[$x]["invc_total_taxes"] * -1;
                        $invc[$x]["invc_total_brutto"]         = $invc[$x]["invc_total_brutto"] * -1;
                     }
                     ?>
                  </td>
                  <td class="content_row_os"><?=$invc[$x]["invc_docnumber"]?></td>
                  <td class="content_row_os"><nobr><?=date("d.m.Y", $invc[$x]["invc_date"])?>&nbsp;<?=date("H:i", $invc[$x]["invc_upddat"])?></nobr></td>
                  <td class="content_row_os"><?=$invc[$x]["cust_name"]?>&nbsp;</td>
                  <td class="content_row_os"><?=$invc[$x]["user_firstname"]?>&nbsp;<?=$invc[$x]["user_lastname"]?></td>
                  <td class="content_row_os" align="right"><?=printPrice($invc[$x]["invc_total_netto"], 0, true)?></td>
                  <td class="content_row_os" align="right"><?=printPrice($invc[$x]["invc_total_taxes"], 0, true)?></td>
                  <td class="content_row_os" align="right"><?=printPrice($invc[$x]["invc_total_brutto"], 0, true)?></td>
               </tr>
               <?php
               $ges_invc_total_taxes_exclude    += $invc[$x]["invc_total_taxes_exclude"];
               $ges_invc_total_netto            += $invc[$x]["invc_total_netto"];
               $ges_invc_total_taxes            += $invc[$x]["invc_total_taxes"];
               $ges_invc_total_brutto           += $invc[$x]["invc_total_brutto"];

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val1"] = $typestr;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val2"] = $invc[$x]["invc_docnumber"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val3"] = date("d.m.Y", $invc[$x]["invc_date"])." ".date("H:i", $invc[$x]["invc_upddat"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["vala"] = $invc[$x]["cust_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["valb"] = $invc[$x]["user_firstname"]." ".$invc[$x]["user_lastname"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val5"] = printPrice($invc[$x]["invc_total_netto"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val6"] = printPrice($invc[$x]["invc_total_taxes"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val7"] = printPrice($invc[$x]["invc_total_brutto"]);
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>">
               <td class="content_row_totals content_row_os" colspan="5">Total</td>
               <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_netto, 0, true)?></td>
               <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_taxes, 0, true)?></td>
               <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_invc_total_brutto, 0, true)?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val1"]   = "<b>TOTAL</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val5"]   = "<b>".printPrice($ges_invc_total_netto)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val6"]   = "<b>".printPrice($ges_invc_total_taxes)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid][$x]["val7"]   = "<b>".printPrice($ges_invc_total_brutto)."</b>";
            ?>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?php
         }
      }
      ?>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsSellingShops($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellingShops($CON);

if($pdffile != "")
{
   $doctitle = "Ventas-por-sucursal-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Ventas-por-sucursal-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>