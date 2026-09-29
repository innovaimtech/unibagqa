<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "invc_refs";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array();
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
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_paystate"]      = (int)$_REQUEST["sql_paystate"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$sellers    = getSellers($CON);

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

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

// Asignar valor por defecto si está vacío, null o sin valor

if (empty($_REQUEST["invc_periodo"]))
{
   $_REQUEST["invc_periodo"] = "vencimiento";
}
$periodo = "t1.invc_estpay_date";
if($_REQUEST["invc_periodo"] == "fecha")
   $periodo = "t1.invc_date";   
else
   $periodo = "t1.invc_estpay_date";   

//----------------------------------------------------------------------------------
$datsql = " select t1.invc_docnumber
                 , t1.invc_date
                 , t6.cust_company
                 , t6.cust_rut
                 , t1.invc_total_brutto
                 , t1.id, t1.invc_payed
                 , t1.invc_cust_id
                 , t1.id
                 , t1.invc_estpay_date
            from invoices_sell t1
            INNER JOIN company_data t4                ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t6               ON ( t1.invc_cust_id = t6.id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            {$periodo} between {$sql_datefrom} and {$sql_dateto}";

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $seasql .= " and t1.invc_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $seasql .= " and t1.invc_cust_id   = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_paystate"] == 1)
   $seasql .= " and t1.invc_payed = 1 ";
if((int)$_SESSION[$_sesmodulename]["sql_paystate"] == 2)
   $seasql .= " and t1.invc_payed = 0 ";
$datsql .= $seasql;
$datsql .= " order by 2,1";
$invoices = $CON->select($datsql);

// echo($datsql);

//----------------------------------------------------------------------------------
foreach($invoices AS $row)
{
   if(!is_array($_RES[$row["invc_cust_id"]]))
      $_RES[$row["invc_cust_id"]] = Array();

   $_RES[$row["invc_cust_id"]][] = $row;

   $_CUST[$row["invc_cust_id"]]["NAME"] = $row["cust_company"];
   $_CUST[$row["invc_cust_id"]]["RUT"]  = $row["cust_rut"];
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
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
{
   $sql = " select cust_name
            from customer
            where
            id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["cust_name"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";
   
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
{
   $sql = " select user_firstname, user_lastname
            from user
            where
            id = {$_SESSION[$_sesmodulename]["sql_seller"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["SELLER"] = $custdata[0]["user_firstname"]." ".$custdata[0]["user_lastname"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["SELLER"] = "TODO";

$_SESSION["STATS"][$_sesmodulename]["_CUST"] = $_CUST;

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$sql = " select *
         from payments";
$xpayments = $CON->select($sql);
foreach($xpayments AS $xpayment)
   $_PAYMENTS[$xpayment["id"]] = $xpayment["pay_title"];
printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Cobranza Vendedor</b></td>
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
         <col width="90">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">
            <select name="invc_periodo" id="invc_periodo" class="text">
               <option value="fecha" <?php echo ($_REQUEST["invc_periodo"] == "fecha") ? "selected" : ""; ?>>Fecha</option>
               <option value="vencimiento" <?php echo (empty($_REQUEST["invc_periodo"]) || $_REQUEST["invc_periodo"] == "vencimiento") ? "selected" : ""; ?>>Vencimiento</option>
            </select>
         </td>
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
               <td class="content_row_clear" width="195" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
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
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Vendedor</td>
         <td class="content_row">
            <select name="sql_seller" id="sql_seller" class="text" style="width:375px">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($sellers AS $seller)
               {  ?>
                  <option value="<?=$seller["id"]?>" <?php if($seller["id"] == $_SESSION[$_sesmodulename]["sql_seller"]) echo "selected"; ?>>
                  <?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?>
                  </option>
                  <?php
               }
               ?>
            </select>
         </td>
         <td class="content_rowl">Estado</td>
         <td class="content_row">
            <input type="radio" value="0" name="sql_paystate" <?php if((int)$_SESSION[$_sesmodulename]["sql_paystate"] == 0) echo "checked"?>> Todas
            <input type="radio" value="1" name="sql_paystate" <?php if((int)$_SESSION[$_sesmodulename]["sql_paystate"] == 1) echo "checked"?>> Facturas canceladas
            <input type="radio" value="2" name="sql_paystate" <?php if((int)$_SESSION[$_sesmodulename]["sql_paystate"] == 2) echo "checked"?>> Facturas pendientes
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
                  if($_RES)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($_RES)
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
      //----------------------------------------------------------------------------------
      function xgetStatsResultOrderCallbackRes($a, $b)
      {
         if((int)$a["note_date"] != (int)$b["note_date"])
            return ((int)$a["note_date"] > (int)$b["note_date"]);
         else
            return ($a["type"] > $b["type"]);
      }
      foreach(array_keys($_RES) AS $custid)
      {
         $_TOTAL_DEBE   = 0.00;
         $_TOTAL_HABER  = 0.00;
         $basesaldo     = 0.00;
         $invoices      = $_RES[$custid];
         ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="130">
            <col width="150">
            <col>
            <col width="130">
            <col width="150">
            <col width="150">
            <col width="150">
         </colgroup>
         <tr>
            <td class="content_tbl_header" valign="top" colspan="7"><?=$_CUST[$custid]["NAME"]?>: <?=$_CUST[$custid]["RUT"]?></td>
         </tr>
         <tr>
            <td class="content_tbl_subheader " valign="top">Fecha</td>
            <td class="content_tbl_subheader " valign="top">Tipo Doc.</td>
            <td class="content_tbl_subheader " valign="top">Número Doc.</td>
            <td class="content_tbl_subheader " valign="top">Fecha Vencimiento</td>
            <td class="content_tbl_subheader " valign="top" align="right">Debe</td>
            <td class="content_tbl_subheader " valign="top" align="right">Haber</td>
            <td class="content_tbl_subheader " valign="top" align="right">Saldo</td>
         </tr>
         <?php
         $grpcss = "border-top:2px solid #666666";
         $gc = 0;
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($invoices) && $invoices != false; $x++)
         {
            if((int)$invoices[$x]["invc_estpay_date"])
               $invc_estpay_date = date("d.m.Y", $invoices[$x]["invc_estpay_date"]);
            else
               $invc_estpay_date = date("d.m.Y", $invoices[$x]["invc_date"]);
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_clear" style="<?=$grpcss?>"><?=date('d.m.Y', $invoices[$x]["invc_date"])?></td>
               <td class="content_row_clear" style="<?=$grpcss?>">FACTURA</td>
               <td class="content_row_clear" style="<?=$grpcss?>"><?=$invoices[$x]["invc_docnumber"]?></td>
               <td class="content_row_clear" style="<?=$grpcss?>"><?=$invc_estpay_date?></td>
               <td class="content_row_clear" style="<?=$grpcss?>" align="right"><?=printPrice($invoices[$x]["invc_total_brutto"])?></td>
               <td class="content_row_clear" style="<?=$grpcss?>" align="right">&nbsp;</td>
               <td class="content_row_clear" style="<?=$grpcss?>" align="right"><?=printPrice($invoices[$x]["invc_total_brutto"])?></td>
            </tr>
            <?php

            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = date('d.m.Y', $invoices[$x]["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = "FACTURA";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = $invoices[$x]["invc_docnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = $invc_estpay_date;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = printPrice($invoices[$x]["invc_total_brutto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = 0;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($invoices[$x]["invc_total_brutto"]);
            $gc++;

            $_TOTAL_DEBE += $invoices[$x]["invc_total_brutto"];
            $basesaldo = $invoices[$x]["invc_total_brutto"];

            //----------------------------------------------------------------------------------
            $addondata  = Array();
            $xtranpayed = getTranPayments($CON, $invoices[$x]["id"], "invoices_sell");

            //----------------------------------------------------------------------------------
            //, SUM(t2.item_sellprice_brutto * t2.item_amount) 'note_total_brutto' ,
            // no aplica descuento por eso saca falta descuento
            //----------------------------------------------------------------------------------

            $sql = " select distinct t1.*
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
                     where
                     t1.note_status       > 1 and
                     t1.note_status       < 4 and
                     t1.note_type_contype = 0 and
                     t2.item_invc_docnumber like '{$invoices[$x]["invc_docnumber"]}-%'
                     group by t1.id
                     order by t1.note_date desc, t1.note_estpay_date desc, t1.id desc";
            $notes = $CON->select($sql);

            //----------------------------------------------------------------------------------
            foreach($xtranpayed AS $tranpayedrow)
            {
               $tranpayedrow["type"]      = "payment";
               $tranpayedrow["note_date"] = $tranpayedrow["adm_deposit_status_date"];
               $addondata[] = $tranpayedrow;
               $_ALLOWEDDEPS[$tranpayedrow["pay_depid"]] = 1;
            }
            foreach($notes AS $note)
            {
               $addondata[] = $note;

               $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
               foreach($xtranpayed AS $tranpayedrow)
               {
                  if($tranpayedrow["pay_brutto"] != 0.00 &&
                     (int)$_ALLOWEDDEPS[$tranpayedrow["pay_depid"]])
                  {
                     if($note["note_type"] == 1)
                        $tranpayedrow["type"] = "payment_nc";
                     else
                        $tranpayedrow["type"] = "payment";
                        
                     $tranpayedrow["note_date"] = $tranpayedrow["adm_deposit_status_date"];
                     $addondata[] = $tranpayedrow;
                  }
               }
            }
            usort($addondata, "xgetStatsResultOrderCallbackRes");

            //----------------------------------------------------------------------------------
            foreach($addondata AS $addonrow)
            {
               if($addonrow["type"] == "payment")
               {

                  // echo($sql);

                  $tranpayedrow = $addonrow;
                  $basesaldo -= $tranpayedrow["pay_brutto"];
                  ?>
                  <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_clear" ><?=date('d.m.Y', $tranpayedrow["adm_deposit_status_date"])?></td>
                     <td class="content_row_clear">RECIBO</td>
                     <td class="content_row_clear" colspan="2"><?=$_PAYMENTS[$tranpayedrow["pay_paymentid"]]?>&nbsp;|&nbsp;<?=$tranpayedrow["pay_docnum"]?></td>
                     <td class="content_row_clear" align="right">&nbsp;</td>
                     <td class="content_row_clear" align="right"><?=printPrice($tranpayedrow["pay_brutto"])?>&nbsp;</td>
                     <td class="content_row_clear" align="right"><?=printPrice($basesaldo)?></td>
                  </tr>
                  <?php
                  

                  //----------------------------------------------------------------------------------
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = date('d.m.Y', $tranpayedrow["adm_deposit_status_date"]);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = "RECIBO";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = $_PAYMENTS[$tranpayedrow["pay_paymentid"]]." | ".$tranpayedrow["pay_docnum"];
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = "";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = 0;
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = printPrice($tranpayedrow["pay_brutto"]);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($basesaldo);
                  $gc++;
                  
                  $_TOTAL_HABER += $tranpayedrow["pay_brutto"];
               }
               elseif($addonrow["type"] == "payment_nc")
               {
                  $tranpayedrow = $addonrow;
                  $basesaldo += $tranpayedrow["pay_brutto"];
                  ?>
                  <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_clear" ><?=date('d.m.Y', $tranpayedrow["adm_deposit_status_date"])?></td>
                     <td class="content_row_clear">RECIBO</td>
                     <td class="content_row_clear" colspan="2"><?=$_PAYMENTS[$tranpayedrow["pay_paymentid"]]?>&nbsp;|&nbsp;<?=$tranpayedrow["pay_docnum"]?></td>
                     <td class="content_row_clear" align="right"><?=printPrice($tranpayedrow["pay_brutto"])?>&nbsp;</td>
                     <td class="content_row_clear" align="right">&nbsp;</td>
                     <td class="content_row_clear" align="right"><?=printPrice($basesaldo)?></td>
                  </tr>
                  <?php

                  //----------------------------------------------------------------------------------
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = date('d.m.Y', $tranpayedrow["adm_deposit_status_date"]);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = "RECIBO";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = $_PAYMENTS[$tranpayedrow["pay_paymentid"]]." | ".$tranpayedrow["pay_docnum"];
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = "";
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = printPrice($tranpayedrow["pay_brutto"]);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = 0;
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($basesaldo);
                  $gc++;
                  
                  $_TOTAL_HABER -= $tranpayedrow["pay_brutto"];
               }
               else
               {
                  $note = $addonrow;
                  if($note["note_type"] == 1)
                  {
                     $ndesc = "NOTA DE CREDITO";
                     $basesaldo -= $note["note_total_brutto"];
                     $hcol = printPrice($note["note_total_brutto"]);
                     $dcol = "";
                     $_TOTAL_HABER += $note["note_total_brutto"];
                  }
                  else
                  {
                     $ndesc = "NOTA DE DEBITO";
                     $basesaldo += $note["note_total_brutto"];
                     $dcol = printPrice($note["note_total_brutto"]);
                     $hcol = "";
                     $_TOTAL_DEBE += $note["note_total_brutto"];
                  }

                  if((int)$note["note_estpay_date"])
                     $note_estpay_date = date("d.m.Y", $note["note_estpay_date"]);
                  else
                     $note_estpay_date = date("d.m.Y", $note["note_date"]);
                  ?>
                  <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                     <td class="content_row_clear"><?=date('d.m.Y', $note["note_date"])?></td>
                     <td class="content_row_clear"><?=$ndesc?></td>
                     <td class="content_row_clear"><?=$note["note_docnumber"]?></td>
                     <td class="content_row_clear"><?=$note_estpay_date?>&nbsp;</td>
                     <td class="content_row_clear" align="right"><?=$dcol?>&nbsp;</td>
                     <td class="content_row_clear" align="right"><?=$hcol?>&nbsp;</td>
                     <td class="content_row_clear" align="right"><?=printPrice($basesaldo)?></td>
                  </tr>
                  <?php
                  $_NOTESSHOWED[$note["note_type"]][$note["note_docnumber"]] = 1;
                  
                  //----------------------------------------------------------------------------------
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = date('d.m.Y', $note["note_date"]);
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = $ndesc;
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = $note["note_docnumber"];
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = $note_estpay_date;
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = $dcol;
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = $hcol;
                  $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($basesaldo);
                  $gc++;
               }
            }
         }

         //----------------------------------------------------------------------------------
         $sql = " select distinct t1.*
                  from invoices_notes_sell t1
                  INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
                  where
                  t1.note_status       > 1 and
                  t1.note_status       < 4 and
                  t1.note_type_contype = 0 and
                  t1.note_cust_id      = {$custid} ";

         //----------------------------------------------------------------------------------
         if((int)$_SESSION[$_sesmodulename]["sql_company"])
            $sql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
         if($_SESSION[$_sesmodulename]["sql_shop"])
            $sql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
         if((int)$_SESSION[$_sesmodulename]["sql_seller"])
            $sql .= " and t1.note_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
         if((int)$_SESSION[$_sesmodulename]["sql_customer"])
            $sql .= " and t1.note_cust_id   = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
         if((int)$_SESSION[$_sesmodulename]["sql_paystate"] == 1)
            $sql .= " and t1.note_payed = 1 ";
         if((int)$_SESSION[$_sesmodulename]["sql_paystate"] == 2)
            $sql .= " and t1.note_payed = 0 ";
         $sql .= " order by t1.note_date asc";
         $xnotes = $CON->select($sql);
         foreach($xnotes AS $xnote)
         {
            if(!(int)$_NOTESSHOWED[$xnote["note_type"]][$xnote["note_docnumber"]])
            {
               if($xnote["note_type"] == 1)
               {
                  $ndesc = "NOTA DE CREDITO";
                  $basesaldo -= $xnote["note_total_brutto"];
                  $hcol = printPrice($xnote["note_total_brutto"]);
                  $dcol = "";
                  $_TOTAL_HABER += $xnote["note_total_brutto"];
               }
               else
               {
                  $ndesc = "NOTA DE DEBITO";
                  $basesaldo += $xnote["note_total_brutto"];
                  $dcol = printPrice($xnote["note_total_brutto"]);
                  $hcol = "";
                  $_TOTAL_DEBE += $xnote["note_total_brutto"];
               }

               if((int)$xnote["note_estpay_date"])
                  $note_estpay_date = date("d.m.Y", $xnote["note_estpay_date"]);
               else
                  $note_estpay_date = date("d.m.Y", $xnote["note_date"]);
                  
               ?>
               <tr bgcolor="#B1E5F5" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_clear" ><?=date('d.m.Y', $xnote["note_date"])?></td>
                  <td class="content_row_clear"><?=$ndesc?></td>
                  <td class="content_row_clear"><?=$xnote["note_docnumber"]?></td>
                  <td class="content_row_clear"><?=$note_estpay_date?>&nbsp;</td>
                  <td class="content_row_clear" align="right"><?=$dcol?>&nbsp;</td>
                  <td class="content_row_clear" align="right"><?=$hcol?>&nbsp;</td>
                  <td class="content_row_clear" align="right"><?=printPrice($basesaldo)?></td>
               </tr>
               <?php

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = date('d.m.Y', $xnote["note_date"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = $ndesc;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = $xnote["note_docnumber"];
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = $note_estpay_date;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = $dcol;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = $hcol;
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($basesaldo);
               $gc++;

               $xtranpayed = getTranPayments($CON, $xnote["id"], "invoices_notes_sell");
               $xaddondata = Array();
               foreach($xtranpayed AS $tranpayedrow)
               {
                  if($tranpayedrow["pay_brutto"] != 0.00 &&
                     (int)$_ALLOWEDDEPS[$tranpayedrow["pay_depid"]])
                  {
                     if($xnote["note_type"] == 1)
                        $tranpayedrow["type"] = "payment_nc";
                     else
                        $tranpayedrow["type"] = "payment";

                     $tranpayedrow["note_date"] = $tranpayedrow["adm_deposit_status_date"];
                     $xaddondata[] = $tranpayedrow;
                  }
               }
               //----------------------------------------------------------------------------------
               foreach($xaddondata AS $addonrow)
               {
                  if($addonrow["type"] == "payment")
                  {
                     $tranpayedrow = $addonrow;
                     $basesaldo -= $tranpayedrow["pay_brutto"];
                     ?>
                     <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                        <td class="content_row_clear" ><?=date('d.m.Y', $tranpayedrow["adm_deposit_status_date"])?></td>
                        <td class="content_row_clear">RECIBO</td>
                        <td class="content_row_clear" colspan="2"><?=$_PAYMENTS[$tranpayedrow["pay_paymentid"]]?>&nbsp;|&nbsp;<?=$tranpayedrow["pay_docnum"]?></td>
                        <td class="content_row_clear" align="right">&nbsp;</td>
                        <td class="content_row_clear" align="right"><?=printPrice($tranpayedrow["pay_brutto"])?>&nbsp;</td>
                        <td class="content_row_clear" align="right"><?=printPrice($basesaldo)?></td>
                     </tr>
                     <?php


                     //----------------------------------------------------------------------------------
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = date('d.m.Y', $tranpayedrow["adm_deposit_status_date"]);
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = "RECIBO";
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = $_PAYMENTS[$tranpayedrow["pay_paymentid"]]." | ".$tranpayedrow["pay_docnum"];
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = "";
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = 0;
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = printPrice($tranpayedrow["pay_brutto"]);
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($basesaldo);
                     $gc++;

                     $_TOTAL_HABER += $tranpayedrow["pay_brutto"];
                  }
                  elseif($addonrow["type"] == "payment_nc")
                  {
                     $tranpayedrow = $addonrow;
                     $basesaldo += $tranpayedrow["pay_brutto"];
                     ?>
                     <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                        <td class="content_row_clear" ><?=date('d.m.Y', $tranpayedrow["adm_deposit_status_date"])?></td>
                        <td class="content_row_clear">RECIBO</td>
                        <td class="content_row_clear" colspan="2"><?=$_PAYMENTS[$tranpayedrow["pay_paymentid"]]?>&nbsp;|&nbsp;<?=$tranpayedrow["pay_docnum"]?></td>
                        <td class="content_row_clear" align="right"><?=printPrice($tranpayedrow["pay_brutto"])?>&nbsp;</td>
                        <td class="content_row_clear" align="right">&nbsp;</td>
                        <td class="content_row_clear" align="right"><?=printPrice($basesaldo)?></td>
                     </tr>
                     <?php

                     //----------------------------------------------------------------------------------
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = date('d.m.Y', $tranpayedrow["adm_deposit_status_date"]);
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = "RECIBO";
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = $_PAYMENTS[$tranpayedrow["pay_paymentid"]]." | ".$tranpayedrow["pay_docnum"];
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = "";
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = printPrice($tranpayedrow["pay_brutto"]);
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = 0;
                     $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($basesaldo);
                     $gc++;

                     $_TOTAL_HABER -= $tranpayedrow["pay_brutto"];
                  }
               }
            }
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
               <td class="content_row_totals" colspan="4">TOTAL</td>
               <td class="content_row_totals" align="right"><nobr><?=printPrice($_TOTAL_DEBE)?></nobr></td>
               <td class="content_row_totals" align="right"><nobr><?=printPrice($_TOTAL_HABER)?></nobr></td>
               <td class="content_row_totals" align="right"><nobr><?=printPrice($_TOTAL_DEBE - $_TOTAL_HABER)?></nobr></td>
            </tr>
            <?php
            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_date"]         = "TOTAL";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["type"]              = " ";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_docnumber"]    = " ";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["invc_estpay_date"]  = " ";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["debe"]              = printPrice($_TOTAL_DEBE);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["haber"]             = printPrice($_TOTAL_HABER);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$gc]["saldo"]             = printPrice($_TOTAL_DEBE - $_TOTAL_HABER);
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      ?>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsReferencias($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsReferencias($CON);
  
if($pdffile != "")
{
   $doctitle = "Cobranza-Vendedor-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Cobranza-Vendedor-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>