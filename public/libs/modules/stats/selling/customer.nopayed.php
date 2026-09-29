<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "custnopayed";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "asc, 2 asc";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
$_sortlinks             = Array();
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
if($_REQUEST["_MODE"] == "customer")
{
   $_REQUEST["subexec"]       = "search";
   $_REQUEST["sql_customer"]  = $_REQUEST["id"];

   if($_REQUEST["sql_company"] == "")
      $_REQUEST["sql_company"] = $_SESSION["user_company_id"];
}

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_doctypes"]      = $_REQUEST["sql_doctypes"];
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

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(count($_SESSION[$_sesmodulename]["sql_doctypes"]) == 0)
{
   $_SESSION[$_sesmodulename]["sql_doctypes"] = Array();
   $_SESSION[$_sesmodulename]["sql_doctypes"][] = "1";
   $_SESSION[$_sesmodulename]["sql_doctypes"][] = "2";
   $_SESSION[$_sesmodulename]["sql_doctypes"][] = "3";
}
   
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = 1;
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = $_SESSION[$_sesmodulename]["sql_year1"];
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
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

$currtme = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.invc_date, t1.invc_estpay_date, t1.invc_total_brutto,
                   t1.invc_docnumber, t1.invc_cust_id, t2.cust_company, t2.cust_rut,
                   t3.pay_title
            from invoices_sell t1
            INNER JOIN customer t2        ON t1.invc_cust_id = t2.id
            LEFT OUTER JOIN payments t3   ON t1.invc_paymentid = t3.id
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date         > 0 and
            t1.invc_payed        = 0 and
            t1.invc_estpay_date  <= {$currtme} and
            t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto} ";
if($_SESSION[$_sesmodulename]["sql_customer"] != "")
   $datsql .= " and t1.invc_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.invc_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
$datsql .= " order by t2.cust_company asc, t1.invc_date desc";
$trans = $CON->select($datsql);

for($x = 0; $x < count($trans) && $trans != false; $x++)
{
   $row = $trans[$x];

   $sql = " select *
            from invoices_sell 
            where
            id = {$row["id"]}";
   $invcdata = $CON->select($sql);
   $invcdata = $invcdata[0];

   if((int)$invcdata["invc_shoprefid"] && strpos($invcdata["invc_desc"], "VALE: ") !== false && strpos($invcdata["invc_desc"], "FACTURA ") !== false && strpos($invcdata["invc_desc"], "/") !== false)
   {
      $_RET    = getValeData($invcdata);
      $_hash   = "{$_RET["shopid"]}_{$_RET["custid"]}_{$_RET["valenum"]}";

      if($_RET["factcount"] != "1/1" && $_RET["factcount"] != "" && strpos($_RET["factcount"], "1/") !== false)
      {
         $row["_NOCLICK"]  = 1;
         $_XDATA[$_hash]   = $row;

         $sql = " select *
                  from invoices_sell
                  where
                  invc_shop_id   = {$_RET["shopid"]} and
                  invc_cust_id   = {$_RET["custid"]} and
                  invc_shoprefid > 0 and
                  invc_desc      like 'FACTURA _/%VALE: {$_RET["valenum"]}%' and
                  invc_desc not  like 'FACTURA 1/%'";
         $relinvcs = $CON->select($sql);

         foreach($relinvcs AS $relinvc)
         {
            $trans[$x]["invc_docnumber"]         .= ", ".$relinvc["invc_docnumber"];
            $trans[$x]["invc_total_brutto"]      += $relinvc["invc_total_brutto"];
            $trans[$x]["invc_total_brutto_calc"] += $relinvc["invc_total_brutto_calc"];

            $row["invc_docnumber"]         .= ", ".$relinvc["invc_docnumber"];
            $row["invc_total_brutto"]      += $relinvc["invc_total_brutto"];
            $row["invc_total_brutto_calc"] += $relinvc["invc_total_brutto_calc"];
         }
      }
      elseif($_RET["factcount"] == "1/1")
      {
         $_hash = $x.md5(microtime());
         $_XDATA[$_hash] = $row;
      }
   }
   else
   {
      $_hash = $x.md5(microtime());
      $_XDATA[$_hash] = $row;
   }

   $sql = " select SUM(t2.item_sellprice_brutto * t2.item_amount) 'note_total_brutto'
            from invoices_notes_sell t1
            INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
            where
            t1.note_status       > 1 and
            t1.note_status       < 4 and
            t1.note_type_contype = 0 and
            t2.item_invc_docnumber like '{$row["invc_docnumber"]}-%' ";
   $notes = $CON->select($sql);
   $row["notesbrutto"] = (float)$notes[0]["note_total_brutto"];
   
   $_CUST[$row["invc_cust_id"]][$row["id"]]  = $row;
   $_CUSTNAMES[$row["invc_cust_id"]]["NAME"] = $row["cust_company"];
   $_CUSTNAMES[$row["invc_cust_id"]]["RUT"]  = $row["cust_rut"];
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

$_SESSION["STATS"][$_sesmodulename]["_CUST"] = $_CUSTNAMES;
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);

if($_REQUEST["_MODE"] != "customer")
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Clientes atrasados</b></td>
      <td align="right" class="content_row_clear"></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?php
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
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
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row" colspan="3">
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
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="135">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if((count($trans) > 0 && $trans != false))
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if((count($trans) > 0 && $trans != false))
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"] && $_REQUEST["_MODE"] != "customer")
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
   <td class="content_row_clear">
      <?php
      foreach(array_keys($_CUST) AS $custid)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="105">
            <col width="100">
            <col>
            <col width="100">
            <col width="100">
            <col width="100">
            <col width="100">
            <col width="100">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="8">
               <?=$_CUSTNAMES[$custid]["NAME"]?>,&nbsp;<?=$_CUSTNAMES[$custid]["RUT"]?>
            </td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">Factura</td>
            <td class="content_tbl_subheader content_row_os">Fecha</td>
            <td class="content_tbl_subheader content_row_os">Forma de Pago</td>
            <td class="content_tbl_subheader content_row_os">Vencimiento</td>
            <td class="content_tbl_subheader content_row_os">Atraso</td>
            <td class="content_tbl_subheader content_row_os" align="right">Monto/Factura</td>
            <td class="content_tbl_subheader content_row_os" align="right">Monto/Notas</td>
            <td class="content_tbl_subheader content_row_os" align="right">Monto/Final</td>
         </tr>
         <?php
         $trans = $_CUST[$custid];
         $x = 0;
         $ges_brutto = 0.00;
         $ges_notes  = 0.00;
         $ges_final  = 0.00;
         //----------------------------------------------------------------------------------
         foreach($trans AS $tran)
         {
            if(!(int)$tran["invc_estpay_date"])
               $tran["invc_estpay_date"] = $tran["invc_date"];
               
            $waitdays = round(($currtme - $tran["invc_estpay_date"]) / 86400,0);
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$tran["invc_docnumber"]?></td>
               <td class="content_row_os"><?=date('d.m.Y', $tran["invc_date"])?></td>
               <td class="content_row_os"><?=$tran["pay_title"]?></td>
               <td class="content_row_os"><?=date('d.m.Y', $tran["invc_estpay_date"])?></td>
               <td class="content_row_os"><?=(int)$waitdays?> Dias</td>
               <td class="content_row_os" align="right"><?=printPrice($tran["invc_total_brutto"])?></td>
               <td class="content_row_os" align="right"><?=printPrice($tran["notesbrutto"])?></td>
               <td class="content_row_os" align="right"><?=printPrice($tran["invc_total_brutto"] - $tran["notesbrutto"])?></td>
            </tr>
            <?php
            $ges_brutto += $tran["invc_total_brutto"];
            $ges_notes  += $tran["notesbrutto"];
            $ges_final  += ($tran["invc_total_brutto"] - $tran["notesbrutto"]);

            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_docnumber"]    = $tran["invc_docnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_date"]         = date('d.m.Y', $tran["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["pay_title"]         = $tran["pay_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_estpay_date"]  = date('d.m.Y', $tran["invc_estpay_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["waitdays"]          = (int)$waitdays." Dias";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_total_brutto"] = printPrice($tran["invc_total_brutto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["notesbrutto"]       = printPrice($tran["notesbrutto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_total_final"]  = printPrice($tran["invc_total_brutto"] - $tran["notesbrutto"]);
                  
            $x++;
         }
         ?>
         <tr>
            <td class="content_row_totals content_row_os" colspan="5">TOTAL</td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_brutto)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_notes)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_final)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_docnumber"]    = "Total";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_date"]         = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_total_brutto"] = printPrice($ges_brutto);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["notesbrutto"]       = printPrice($ges_notes);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$custid][$x]["invc_total_final"]  = printPrice($ges_final);
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
  $pdffile = doc_createStatsCustNoPayed($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsCustNoPayed($CON);
  
if($pdffile != "")
{
   $doctitle = "Clientes-atrasados-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Clientes-atrasados-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>