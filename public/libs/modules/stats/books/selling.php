<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "book_selling";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "4";
$_sesbaseordersort      = "asc, 2 asc";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
$_sortlinks             = Array("Número int."      => 3,
                                "Comprobante"      => 4,
                                "Fecha"            => 2,
                                "Cliente"          => 10,
                                "RUT"              => 11,
                                "Extento"          => 7,
                                "Neto"             => 5,
                                "IVA"              => 6,
                                "Total"            => 8);
unset($_SESSION["STATS"][$_sesmodulename]);

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
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

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

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
   
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m') -1;
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');

   if($_SESSION[$_sesmodulename]["sql_month1"] == 0)
   {
      $_SESSION[$_sesmodulename]["sql_month1"] = 12;
      $_SESSION[$_sesmodulename]["sql_year1"]--;
   }
   
   $_SESSION[$_sesmodulename]["sql_month2"]  = $_SESSION[$_sesmodulename]["sql_month1"];
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

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber,
                   t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_exclude,
                   t1.invc_total_brutto, t4.company_short, t6.cust_name, t6.cust_rut,
                   t1.invc_status, t6.cust_company
            from invoices_sell t1
            INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t6   ON ( t1.invc_cust_id = t6.id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto}  ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";

//----------------------------------------------------------------------------------
$datsql .= " UNION ALL
            select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber,
                   t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_exclude,
                   t1.invc_total_brutto, t4.company_short, t6.cust_name, t6.cust_rut,
                   t1.invc_status, t6.cust_company
            from invoices_sell_bol t1
            INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t6   ON ( t1.invc_cust_id = t6.id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_iscredito    = 0 and
            t1.invc_vtype        = 1 and
            t1.invc_anulado_act  = 0 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto}  ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   
$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
$trans = $CON->select($datsql);

//----------------------------------------------------------------------------------
function getStatsResultOrderCallbackRes($a, $b)
{
   return ((int)$a["invc_docnumber"] > (int)$b["invc_docnumber"]);
}
usort($trans, "getStatsResultOrderCallbackRes");

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                   t1.note_total_netto 'invc_total_netto', t1.note_total_taxes 'invc_total_taxes',
                   t1.note_total_taxes_exclude 'invc_total_taxes_exclude',
                   t1.note_total_brutto 'invc_total_brutto', t4.company_short, t6.cust_name, t6.cust_rut,
                   t1.note_type, t1.note_status 'invc_status', t6.cust_company
            from invoices_notes_sell t1
            INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t6   ON ( t1.note_cust_id = t6.id )
            where
            t1.note_status       > 1 and
            t1.note_status       < 4 and
            t1.note_date         between {$sql_datefrom} and {$sql_dateto}  ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.note_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.note_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
$notes = $CON->select($datsql);

for($x = 0; $x < count($notes) && $notes != false; $x++)
{
   if(!is_array($resnotes[$notes[$x]["note_type"]]))
      $resnotes[$notes[$x]["note_type"]] = Array();

   array_push($resnotes[$notes[$x]["note_type"]], $notes[$x]);
}
foreach(array_keys($resnotes) AS $notetype)
{
   $temp = $resnotes[$notetype];
   usort($temp, "getStatsResultOrderCallbackRes");
   $resnotes[$notetype] = $temp;
}


//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber,
                   t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_exclude,
                   t1.invc_total_brutto, t4.company_short, t1.invc_status, t1.invc_caid,
                   t5.ca_name
         from invoices_sell_bol t1
         INNER JOIN company_data t4 ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
         LEFT OUTER JOIN company_shops_cashings t5 ON t1.invc_caid = t5.id
         where
         t1.invc_status       > 1 and
         t1.invc_status       < 4 and
         t1.invc_iscredito    = 0 and
         t1.invc_vtype        = 0 and
         t1.invc_anulado_act  = 0 and
         t1.invc_date         between {$sql_datefrom} and {$sql_dateto}  ";
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
$datsql .= " order by 1 asc, 3 asc";
$bols = $CON->select($datsql);

foreach($bols AS $bol)
{
   $idx1 = date('d.m.Y', $bol["invc_date"]);
   $idx2 = $bol["invc_caid"];

   $_BOLS[$idx1][$idx2]["COUNT"]++;
   $_BOLS[$idx1][$idx2]["AMOUNT"]   += $bol["invc_total_brutto"];
   $_BOLS[$idx1][$idx2]["NUMBERS"]  .= $bol["invc_docnumber"].",";

   $_BOLS_DAY[$idx1]["COUNT"]++;
   $_BOLS_DAY[$idx1]["AMOUNT"]      += $bol["invc_total_brutto"];

   $_BOLS_TLT["COUNT"]++;
   $_BOLS_TLT["AMOUNT"]             += $bol["invc_total_brutto"];
   $_BOLS_TLT["invc_total_netto"]   += $bol["invc_total_netto"];
   $_BOLS_TLT["invc_total_taxes"]   += $bol["invc_total_taxes"];
   $_BOLS_TLT["invc_total_brutto"]  += $bol["invc_total_brutto"];

   $_BOLS_CAJ[$idx2]["COUNT"]++;
   $_BOLS_CAJ[$idx2]["AMOUNT"]       += $bol["invc_total_brutto"];

   $_CAJAS[$idx2] = $bol["ca_name"];
}

//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);
$shops      = getShops($CON);

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
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Libro de venta</b></td>
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
               <col width="135">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if($trans  || ( $resnotes || $_BOLS))
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($trans  || ( $resnotes || $_BOLS))
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
<?php
$bol_total_netto  = 0.00;
$bol_total_taxes  = 0.00;
$bol_total_brutto = 0.00;
if(count($_BOLS))
{  ?>
   <tr>
      <td class="content_row_clear">
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="110">
            <?php
            foreach(array_keys($_CAJAS) AS $caid)
            {  ?>
               <col>
               <col width="75">
               <col width="75">
               <?php
            }
            ?>
            <col width="85">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="<?=(3 + count($_CAJAS) * 3)?>"><img src="./images/menu/icons/document.png" style="vertical-align:bottom">&nbsp;&nbsp;BOLETAS</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os" rowspan="2" style="border-right:3px double #333333" valign="bottom">Fecha</td>
            <?php
            foreach(array_keys($_CAJAS) AS $caid)
            {  ?>
               <td class="content_tbl_subheader content_row_os" colspan="3" align="center" style="border-right:3px double #333333"><b>CAJA CENTAL</b></td>
               <?php
            }
            ?>
            <td class="content_tbl_subheader content_row_os" align="center" colspan="2"><b>TOTAL</b></td>
         </tr>
         <tr>
            <?php
            $x = 0;
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["name"]  = "Fecha";
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["width"] = 80;
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["align"] = "left";
            $x++;
            $col1w = 260;
            $col2w = 140;
            if(count($_CAJAS) == 2)
            {
               $col1w = 130;
               $col2w = 70;
            }
            elseif(count($_CAJAS) > 2)
            {
               $col1w = 80;
               $col2w = 50;
            }
            foreach(array_keys($_CAJAS) AS $caid)
            {  ?>
               <td class="content_tbl_subheader content_row_os">Rango</td>
               <td class="content_tbl_subheader content_row_os" align="right">Cantidad</td>
               <td class="content_tbl_subheader content_row_os" align="right" style="border-right:3px double #333333">Monto</td>
               <?php
               $cajastr = $_CAJAS[$caid];
               if($cajastr == "")
                  $cajastr = "CAJA";
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["name"]  = "{$cajastr}\nRango";
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["width"] = $col1w;
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["align"] = "left";
               $x++;
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["name"]  = "{$cajastr}\nCantidad";
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["width"] = $col2w;
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["align"] = "right";
               $x++;
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["name"]  = "{$cajastr}\nMonto";
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["width"] = $col2w;
               $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["align"] = "right";
               $x++;
            }
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["name"]  = "TOTAL\nCantidad";
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["width"] = 45;
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["align"] = "right";
            $x++;
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["name"]  = "TOTAL\nMonto";
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["width"] = 50;
            $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$x]["align"] = "right";
            $x++;
            ?>
            <td class="content_tbl_subheader content_row_os" align="right">Cantidad</td>
            <td class="content_tbl_subheader content_row_os" align="right">Monto</td>
         </tr>
         <?php
         $x = 0;
         foreach(array_keys($_BOLS_DAY) AS $didx)
         {  ?>
            <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" style="border-right:3px double #333333"><?=$didx?></td>
               <?php
               $cc = 1;
               $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = $didx;
               $cc++;
               foreach(array_keys($_CAJAS) AS $caid)
               {
                  $valarr  = explode(",", substr($_BOLS[$didx][$caid]["NUMBERS"], 0, -1));
                  asort($valarr);
                  $valstr  = get_number_ranges($valarr);
                  ?>
                  <td class="content_row_os"><?=$valstr?>&nbsp;</td>
                  <td class="content_row_os" align="right"><?=(int)$_BOLS[$didx][$caid]["COUNT"]?></td>
                  <td class="content_row_os" style="border-right:3px double #333333" align="right"><?=printPrice($_BOLS[$didx][$caid]["AMOUNT"])?></td>
                  <?php
                  $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = $valstr;
                  $cc++;
                  $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = (int)$_BOLS[$didx][$caid]["COUNT"];
                  $cc++;
                  $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = printPrice($_BOLS[$didx][$caid]["AMOUNT"]);
                  $cc++;
               }
               $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = (int)$_BOLS_DAY[$didx]["COUNT"];
               $cc++;
               $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = printPrice($_BOLS_DAY[$didx]["AMOUNT"]);
               $cc++;
               ?>
               <td class="content_row_os" align="right"><?=(int)$_BOLS_DAY[$didx]["COUNT"]?></td>
               <td class="content_row_os" align="right"><?=printPrice($_BOLS_DAY[$didx]["AMOUNT"])?></td>
            </tr>
            <?php
            $x++;
         }
         ?>
         <tr>
            <td class="content_row_os content_row_totals" style="border-right:3px double #333333">TOTAL BOLETAS</td>
            <?php
            $cc = 1;
            $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = "<b>TOTAL BOLETAS</b>";
            $cc++;
            foreach(array_keys($_CAJAS) AS $caid)
            {  ?>
               <td class="content_row_os content_row_totals">&nbsp;</td>
               <td class="content_row_os content_row_totals" align="right"><?=(int)$_BOLS_CAJ[$caid]["COUNT"]?></td>
               <td class="content_row_os content_row_totals" style="border-right:3px double #333333" align="right"><?=printPrice($_BOLS_CAJ[$caid]["AMOUNT"])?></td>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = " ";
               $cc++;
               $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = "<b>".(int)$_BOLS_CAJ[$caid]["COUNT"]."</b>";
               $cc++;
               $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = "<b>".printPrice($_BOLS_CAJ[$caid]["AMOUNT"])."</b>";
               $cc++;
            }
            $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = "<b>".(int)$_BOLS_TLT["COUNT"]."</b>";
            $cc++;
            $_SESSION["STATS"][$_sesmodulename]["BOLDAT"][$x]["val{$cc}"] = "<b>".printPrice($_BOLS_TLT["AMOUNT"])."</b>";
            $cc++;
            ?>
            <td class="content_row_os content_row_totals" align="right"><?=(int)$_BOLS_TLT["COUNT"]?></td>
            <td class="content_row_os content_row_totals" align="right"><?=printPrice($_BOLS_TLT["AMOUNT"])?></td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_row_clear">
      <?php
      if(count($trans) && $trans != false)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="105">
            <col width="75">
            <col>
            <col width="85">
            <col width="85">
            <col width="85">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="7"><img src="./images/menu/icons/money.png" style="vertical-align:bottom">&nbsp;&nbsp;FACTURAS</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">Facturas</td>
            <td class="content_tbl_subheader content_row_os">Fecha</td>
            <td class="content_tbl_subheader content_row_os">Cliente</td>
            <td class="content_tbl_subheader content_row_os">RUT</td>
            <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
            <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
            <td class="content_tbl_subheader content_row_os" align="right">Total</td>
         </tr>
         <?php
         $res_total_taxes_exclude   = 0.00;
         $res_total_netto           = 0.00;
         $res_total_taxes           = 0.00;
         $res_total_brutto          = 0.00;
         
         $ges_total_taxes_exclude   = 0.00;
         $ges_total_netto           = 0.00;
         $ges_total_taxes           = 0.00;
         $ges_total_brutto          = 0.00;
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($trans) && $trans != false; $x++)
         {
            $tran       = $trans[$x];
            $tran_netto = $tran["invc_total_netto"];
            if($tran["invc_total_taxes_exclude"] > 0.00)
               $tran_netto = $tran["invc_total_taxes_exclude"];

            $css = "";
            if($tran["invc_status"] == 4)
            {
               $css = "text-decoration:line-through;color:red";
               $tran_netto = 0.00;
               $tran["invc_total_taxes_exclude"] = 0.00;
               $tran["invc_total_taxes"] = 0.00;
               $tran["invc_total_brutto"] = 0.00;
               $tran["cust_name"] = "NULA";
               $tran["cust_rut"]  = "";
            }
            //----------------------------------------------------------------------------------
            ?>
            <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" style="<?=$css?>"><?=$tran["invc_docnumber"]?></td>
               <td class="content_row_os" style="<?=$css?>"><?=date('d.m.Y', $tran["invc_date"])?></td>
               <td class="content_row_os" style="<?=$css?>"><?=$tran["cust_company"]?>&nbsp;</td>
               <td class="content_row_os"><?=$tran["cust_rut"]?>&nbsp;</td>
               <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=printPrice($tran_netto)?></nobr></td>
               <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=printPrice($tran["invc_total_taxes"])?></nobr></td>
               <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=printPrice($tran["invc_total_brutto"])?></nobr></td>
            </tr>
            <?php
            $ges_total_invccc++;
            $ges_total_taxes_exclude   += $tran["invc_total_taxes_exclude"];
            $ges_total_netto           += $tran_netto;
            $ges_total_taxes           += $tran["invc_total_taxes"];
            $ges_total_brutto          += $tran["invc_total_brutto"];

            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]                              = $tran;
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["tran_type"]                 = $tran_type;
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_date"]                 = date('d.m.Y', $tran["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_total_taxes_exclude"]  = printPrice($tran["invc_total_taxes_exclude"], $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["tran_netto"]                = printPrice($tran_netto, $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_total_taxes"]          = printPrice($tran["invc_total_taxes"], $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_total_brutto"]         = printPrice($tran["invc_total_brutto"], $numberlim);
         }
         ?>
         <tr>
            <td class="content_row_totals" colspan="4">TOTAL FACTURAS</td>
            <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_netto)?></nobr></td>
            <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_taxes)?></nobr></td>
            <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_brutto)?></nobr></td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["invc_total_taxes_exclude"]  = "<b>".printPrice($ges_total_taxes_exclude, $numberlim)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["tran_netto"]                = "<b>".printPrice($ges_total_netto, $numberlim)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["invc_total_taxes"]          = "<b>".printPrice($ges_total_taxes, $numberlim)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["invc_total_brutto"]         = "<b>".printPrice($ges_total_brutto, $numberlim)."</b>";
      }
      ?>
   </td>
</tr>
<?php
$res_total_taxes_exclude   = $ges_total_taxes_exclude;
$res_total_netto           = $ges_total_netto;
$res_total_taxes           = $ges_total_taxes;
$res_total_brutto          = $ges_total_brutto;

$_TOTAL["INVC"]["res_total_taxes_exclude"]   = $res_total_taxes_exclude;
$_TOTAL["INVC"]["res_total_netto"]           = $res_total_netto;
$_TOTAL["INVC"]["res_total_taxes"]           = $res_total_taxes;
$_TOTAL["INVC"]["res_total_brutto"]          = $res_total_brutto;
$_TOTAL["INVC"]["COUNT"]                     = (int)$ges_total_invccc;

for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
{
   if($xnotetype == 1)
   {
      $sectitle = "NOTAS DE CREDITO";
      $secinit  = "MENOS";
      $prcsign  = "-";
   }
   else
   {
      $sectitle = "NOTAS DE DEBITO";
      $secinit  = "MAS";
      $prcsign  = "";
   }

   $trans = $resnotes[$xnotetype];

   if(count($trans) && $trans != false)
   {  ?>
      <tr>
         <td class="content_row_clear">
            <?=Nifty_printH("box1", "980")?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="105">
               <col width="75">
               <col>
               <col width="85">
               <col width="85">
               <col width="85">
               <col width="85">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="7"><img src="./images/menu/icons/documents.png" style="vertical-align:bottom">&nbsp;&nbsp;<?=$secinit?> <?=$sectitle?></td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os">Nota</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Cliente</td>
               <td class="content_tbl_subheader content_row_os">RUT</td>
               <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
               <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
               <td class="content_tbl_subheader content_row_os" align="right">Bruto</td>
            </tr>
            <?php
            $ges_total_taxes_exclude   = 0.00;
            $ges_total_netto           = 0.00;
            $ges_total_taxes           = 0.00;
            $ges_total_brutto          = 0.00;
            
            //----------------------------------------------------------------------------------
            for($x = 0; $x < count($trans) && $trans != false; $x++)
            {
               $tran       = $trans[$x];
               $tran_netto = $tran["invc_total_netto"] - $tran["invc_total_taxes_exclude"];

               $css = "";
               if($tran["invc_status"] == 4)
               {
                  $css = "text-decoration:line-through;color:red";
                  $tran_netto = 0.00;
                  $tran["invc_total_taxes_exclude"] = 0.00;
                  $tran["invc_total_taxes"] = 0.00;
                  $tran["invc_total_brutto"] = 0.00;
                  $tran["cust_name"] = "NULA";
                  $tran["cust_rut"]  = "";
               }
               //----------------------------------------------------------------------------------
               ?>
               <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os" style="<?=$css?>"><?=$tran["invc_docnumber"]?></td>
                  <td class="content_row_os" style="<?=$css?>"><?=date('d.m.Y', $tran["invc_date"])?></td>
                  <td class="content_row_os" style="<?=$css?>"><?=$tran["cust_company"]?>&nbsp;</td>
                  <td class="content_row_os"><?=$tran["cust_rut"]?>&nbsp;</td>
                  <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=$prcsign?><?=printPrice($tran_netto)?></nobr></td>
                  <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=$prcsign?><?=printPrice($tran["invc_total_taxes"])?></nobr></td>
                  <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=$prcsign?><?=printPrice($tran["invc_total_brutto"])?></nobr></td>
               </tr>
               <?php
               //----------------------------------------------------------------------------------
               $ges_total_taxes_exclude   += $tran["invc_total_taxes_exclude"];
               $ges_total_netto           += $tran_netto;
               $ges_total_taxes           += $tran["invc_total_taxes"];
               $ges_total_brutto          += $tran["invc_total_brutto"];

               $_TOTAL["NOTE"][$xnotetype]["COUNT"]++;

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]                              = $tran;
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["sectitle"]                  = $sectitle;
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["tran_type"]                 = $tran_type;
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["invc_date"]                 = date('d.m.Y', $tran["invc_date"]);
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["invc_total_taxes_exclude"]  = printPrice($prcsign.$tran["invc_total_taxes_exclude"], $numberlim);
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["tran_netto"]                = printPrice($prcsign.$tran_netto, $numberlim);
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["invc_total_taxes"]          = printPrice($prcsign.$tran["invc_total_taxes"], $numberlim);
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["invc_total_brutto"]         = printPrice($prcsign.$tran["invc_total_brutto"], $numberlim);

            }

            //----------------------------------------------------------------------------------
            if($xnotetype == 1)
            {
               $res_total_taxes_exclude   -= $ges_total_taxes_exclude;
               $res_total_netto           -= $ges_total_netto;
               $res_total_taxes           -= $ges_total_taxes;
               $res_total_brutto          -= $ges_total_brutto;
            }
            else
            {
               $res_total_taxes_exclude   += $ges_total_taxes_exclude;
               $res_total_netto           += $ges_total_netto;
               $res_total_taxes           += $ges_total_taxes;
               $res_total_brutto          += $ges_total_brutto;
            }

            //----------------------------------------------------------------------------------
            $_TOTAL["NOTE"][$xnotetype]["res_total_taxes_exclude"]   = $ges_total_taxes_exclude;
            $_TOTAL["NOTE"][$xnotetype]["res_total_netto"]           = $ges_total_netto;
            $_TOTAL["NOTE"][$xnotetype]["res_total_taxes"]           = $ges_total_taxes;
            $_TOTAL["NOTE"][$xnotetype]["res_total_brutto"]          = $ges_total_brutto;

            //----------------------------------------------------------------------------------
            ?>
            <tr>
               <td class="content_row_totals" colspan="4">TOTAL <?=$sectitle?></td>
               <td class="content_row_totals" align="right"><nobr><?=$prcsign?><?=printPrice($ges_total_netto)?></nobr></td>
               <td class="content_row_totals" align="right"><nobr><?=$prcsign?><?=printPrice($ges_total_taxes)?></nobr></td>
               <td class="content_row_totals" align="right"><nobr><?=$prcsign?><?=printPrice($ges_total_brutto)?></nobr></td>
            </tr>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["invc_total_taxes_exclude"]  = "<b>{$prcsign}".printPrice($ges_total_taxes_exclude, $numberlim)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["tran_netto"]                = "<b>{$prcsign}".printPrice($ges_total_netto, $numberlim)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["invc_total_taxes"]          = "<b>{$prcsign}".printPrice($ges_total_taxes, $numberlim)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["invc_total_brutto"]         = "<b>{$prcsign}".printPrice($ges_total_brutto, $numberlim)."</b>";
            ?>
         </td>
      </tr>
      <?php
   }
}
$res_total_netto  += $_BOLS_TLT["invc_total_netto"];
$res_total_taxes  += $_BOLS_TLT["invc_total_taxes"];
$res_total_brutto += $_BOLS_TLT["invc_total_brutto"];
?>
<tr>
   <td>
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="85">
         <col width="85">
         <col width="85">
         <col width="85">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5"><img src="./images/menu/icons/balance.png" style="vertical-align:bottom">&nbsp;&nbsp;TOTALES</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os">&nbsp;</td>
         <td class="content_tbl_subheader content_row_os" align="center">Cantidad</td>
         <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
         <td class="content_tbl_subheader content_row_os" align="right">IVA</td>
         <td class="content_tbl_subheader content_row_os" align="right">Total</td>
      </tr>
      <tr>
         <td class="content_row_os">BOLETAS</td>
         <td class="content_row_os" align="center"><nobr><?=printPrice($_BOLS_TLT["COUNT"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_BOLS_TLT["invc_total_netto"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_BOLS_TLT["invc_total_taxes"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_BOLS_TLT["invc_total_brutto"])?></nobr></td>
      </tr>
      <tr>
         <td class="content_row_os">FACTURAS</td>
         <td class="content_row_os" align="center"><nobr><?=printPrice($_TOTAL["INVC"]["COUNT"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["INVC"]["res_total_netto"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["INVC"]["res_total_taxes"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["INVC"]["res_total_brutto"])?></nobr></td>
      </tr>
      <tr>
         <td class="content_row_os">MENOS NOTAS DE CREDITO</td>
         <td class="content_row_os" align="center"><nobr><?=printPrice($_TOTAL["NOTE"][1]["COUNT"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr>-<?=printPrice($_TOTAL["NOTE"][1]["res_total_netto"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr>-<?=printPrice($_TOTAL["NOTE"][1]["res_total_taxes"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr>-<?=printPrice($_TOTAL["NOTE"][1]["res_total_brutto"])?></nobr></td>
      </tr>
      <tr>
         <td class="content_row_os">MAS NOTAS DE DEBITO</td>
         <td class="content_row_os" align="center"><nobr><?=printPrice($_TOTAL["NOTE"][2]["COUNT"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["NOTE"][2]["res_total_netto"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["NOTE"][2]["res_total_taxes"])?></nobr></td>
         <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["NOTE"][2]["res_total_brutto"])?></nobr></td>
      </tr>
      <tr>
         <td class="content_row_totals">TOTAL GENERAL</td>
         <td class="content_row_totals" align="center">&nbsp;</td>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($res_total_netto)?></nobr></td>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($res_total_taxes)?></nobr></td>
         <td class="content_row_totals" align="right"><nobr><?=printPrice($res_total_brutto)?></nobr></td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php

      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][0]["NAME"]                      = "BOLETAS";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][0]["count"]                     = printPrice($_BOLS_TLT["COUNT"]);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][0]["res_total_taxes_exclude"]   = printPrice(0, $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][0]["res_total_netto"]           = printPrice($_BOLS_TLT["invc_total_netto"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][0]["res_total_taxes"]           = printPrice($_BOLS_TLT["invc_total_taxes"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][0]["res_total_brutto"]          = printPrice($_BOLS_TLT["invc_total_brutto"], $numberlim);

      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][1]["NAME"]                      = "FACTURAS";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][1]["count"]                     = printPrice($_TOTAL["INVC"]["COUNT"]);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][1]["res_total_taxes_exclude"]   = printPrice($_TOTAL["INVC"]["res_total_taxes_exclude"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][1]["res_total_netto"]           = printPrice($_TOTAL["INVC"]["res_total_netto"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][1]["res_total_taxes"]           = printPrice($_TOTAL["INVC"]["res_total_taxes"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][1]["res_total_brutto"]          = printPrice($_TOTAL["INVC"]["res_total_brutto"], $numberlim);

      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][2]["NAME"]                      = "MENOS NOTAS DE CREDITO";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][2]["count"]                     = printPrice($_TOTAL["NOTE"][1]["COUNT"]);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][2]["res_total_taxes_exclude"]   = printPrice("-".$_TOTAL["NOTE"][1]["res_total_taxes_exclude"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][2]["res_total_netto"]           = printPrice("-".$_TOTAL["NOTE"][1]["res_total_netto"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][2]["res_total_taxes"]           = printPrice("-".$_TOTAL["NOTE"][1]["res_total_taxes"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][2]["res_total_brutto"]          = printPrice("-".$_TOTAL["NOTE"][1]["res_total_brutto"], $numberlim);

      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][3]["NAME"]                      = "MAS NOTAS DE DEBITO";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][3]["count"]                     = printPrice($_TOTAL["NOTE"][2]["COUNT"]);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][3]["res_total_taxes_exclude"]   = printPrice($_TOTAL["NOTE"][2]["res_total_taxes_exclude"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][3]["res_total_netto"]           = printPrice($_TOTAL["NOTE"][2]["res_total_netto"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][3]["res_total_taxes"]           = printPrice($_TOTAL["NOTE"][2]["res_total_taxes"], $numberlim);
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][3]["res_total_brutto"]          = printPrice($_TOTAL["NOTE"][2]["res_total_brutto"], $numberlim);

      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][4]["NAME"]                      = "<b>TOTAL GENERAL</b>";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][4]["count"]                     = " ";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][4]["res_total_taxes_exclude"]   = "<b>".printPrice($res_total_taxes_exclude, $numberlim)."</b>";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][4]["res_total_netto"]           = "<b>".printPrice($res_total_netto, $numberlim)."</b>";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][4]["res_total_taxes"]           = "<b>".printPrice($res_total_taxes, $numberlim)."</b>";
      $_SESSION["STATS"][$_sesmodulename]["TOTALS"][4]["res_total_brutto"]          = "<b>".printPrice($res_total_brutto, $numberlim)."</b>";
      ?>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsBooksSelling($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsBooksSelling($CON);

if($pdffile != "")
{
   $doctitle = "Libro-de-venta-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Libro-de-venta-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>