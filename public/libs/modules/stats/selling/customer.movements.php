<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "statsbooksselling";
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
   $_SESSION[$_sesmodulename]["sql_paystatus"]     = $_REQUEST["sql_paystatus"];
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
if(!is_array($_SESSION[$_sesmodulename]["sql_paystatus"]))
   $_SESSION[$_sesmodulename]["sql_paystatus"] = Array(0=>0,1=>1);
   
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
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

function getStatsResultOrderCallbackRes($a, $b)
{
   return ((int)$a["invc_docnumber"] > (int)$b["invc_docnumber"]);
}

//----------------------------------------------------------------------------------
if(array_search(1, $_SESSION[$_sesmodulename]["sql_doctypes"]) !== false)
{
   $datsql = " select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber,
                      t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_exclude,
                      t1.invc_total_brutto, t4.company_short, t6.cust_name, t6.cust_rut,
                      t1.invc_status, t6.cust_company, t1.invc_payed
            from invoices_sell t1
            INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t6   ON ( t1.invc_cust_id = t6.id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto}  ";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.invc_shop_id   = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.invc_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";

   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_paystatus"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $datsql .= " and t1.invc_payed IN ({$seastatstr}) ";

   $datsql .= " UNION ALL
                select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber,
                      t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_taxes_exclude,
                      t1.invc_total_brutto, t4.company_short, t6.cust_name, t6.cust_rut,
                      t1.invc_status, t6.cust_company, t1.invc_payed
            from invoices_sell_bol t1
            INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t6   ON ( t1.invc_cust_id = t6.id )
            where
            t1.invc_status       > 1 and
            t1.invc_status       < 4 and
            t1.invc_date         between {$sql_datefrom} and {$sql_dateto}  ";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.invc_shop_id   = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.invc_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";

   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_paystatus"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $datsql .= " and t1.invc_payed IN ({$seastatstr}) ";
   
   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
   $trans = $CON->select($datsql);


   usort($trans, "getStatsResultOrderCallbackRes");
}

//----------------------------------------------------------------------------------
if(array_search(2, $_SESSION[$_sesmodulename]["sql_doctypes"]) !== false)
{
   $datsql = " select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                      t1.note_total_netto 'invc_total_netto', t1.note_total_taxes 'invc_total_taxes',
                      t1.note_total_taxes_exclude 'invc_total_taxes_exclude',
                      t1.note_total_brutto 'invc_total_brutto', t4.company_short, t6.cust_name, t6.cust_rut,
                      t1.note_type, t1.note_status 'invc_status', t6.cust_company, t1.note_payed 'invc_payed'
               from invoices_notes_sell t1
               INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN customer t6   ON ( t1.note_cust_id = t6.id )
               where
               t1.note_status       > 1 and
               t1.note_status       < 4 and
               t1.note_date         between {$sql_datefrom} and {$sql_dateto}  ";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.note_shop_id   = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.note_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.note_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
      
   $seastatstr = "";
   foreach($_SESSION[$_sesmodulename]["sql_paystatus"] AS $seastat)
      $seastatstr .= $seastat.",";
   $seastatstr = substr($seastatstr, 0, -1);
   $datsql .= " and t1.note_payed IN ({$seastatstr}) ";
   
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
}


//----------------------------------------------------------------------------------
if(array_search(3, $_SESSION[$_sesmodulename]["sql_doctypes"]) !== false)
{
   $datsql = " select t1.id, t1.dlv_delivery_date 'invc_date', t1.dlv_num 'invc_number', t1.dlv_docnum 'invc_docnumber',
                      t1.dlv_total_netto 'invc_total_netto',
                      t4.company_short, t6.cust_name, t6.cust_rut,
                      t1.dlv_status 'invc_status', t6.cust_company, t1.dlv_cust_id
               from orders_delivery t1
               INNER JOIN company_data t4    ON ( t1.dlv_company_id = t4.id and t4.company_status = 1 )
               INNER JOIN customer t6        ON ( t1.dlv_cust_id = t6.id )
               where
               t1.dlv_status       > 1 and
               t1.dlv_status       <= 4 and
               t1.dlv_invoice_generated = 0 and
               t1.dlv_invoice_bol_generated  = 0 and
               t1.dlv_delivery_date         between {$sql_datefrom} and {$sql_dateto}  ";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.dlv_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.dlv_shop_id   = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.dlv_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_seller"])
      $datsql .= " and t1.dlv_userid_seller   = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
      
   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";
   $delivs = $CON->select($datsql);
   usort($delivs, "getStatsResultOrderCallbackRes");
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
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);

if($_REQUEST["_MODE"] != "customer")
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Movimientos</b></td>
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
         <?php
         if($_REQUEST["_MODE"] != "customer")
         {  ?>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
            <?php
         }
         else
         {  ?>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
            <?php
         }
         ?>
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
         <td class="content_rowl">Documentos</td>
         <td class="content_row">
            <input type="checkbox" name="sql_doctypes[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_doctypes"]) !== false) echo "checked"?>> Facturas
            <input type="checkbox" name="sql_doctypes[]" value="2" <?php if(array_search(2, $_SESSION[$_sesmodulename]["sql_doctypes"]) !== false) echo "checked"?>> Notas de credito/debito
            <input type="checkbox" name="sql_doctypes[]" value="3" <?php if(array_search(3, $_SESSION[$_sesmodulename]["sql_doctypes"]) !== false) echo "checked"?>> Guias de Despacho
         </td>
         <td class="content_rowl">Pagado</td>
         <td class="content_row">
            <input type="checkbox" name="sql_paystatus[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>No pagado
            <input type="checkbox" name="sql_paystatus[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>Pagado
         </td>
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
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
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
                  if((count($trans) > 0 && $trans != false) || count($resnotes) > 0 || count($delivs) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if((count($trans) > 0 && $trans != false) || count($resnotes) > 0 || count($delivs) > 0)
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
      if(count($trans) && $trans != false)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="105">
            <col width="75">
            <col>
            <col width="85">
            <col width="50">
            <col width="85">
            <col width="85">
            <col width="85">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="8"><img src="./images/menu/icons/money.png" style="vertical-align:bottom">&nbsp;&nbsp;BOLETAS/FACTURAS</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">Folio</td>
            <td class="content_tbl_subheader content_row_os">Fecha</td>
            <td class="content_tbl_subheader content_row_os">Cliente</td>
            <td class="content_tbl_subheader content_row_os">RUT</td>
            <td class="content_tbl_subheader content_row_os" align="center">Pagado</td>
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

            $paystate = "No";
            if((int)$tran["invc_payed"])
               $paystate = "Si";

            //----------------------------------------------------------------------------------
            ?>
            <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" style="<?=$css?>"><?=$tran["invc_docnumber"]?></td>
               <td class="content_row_os" style="<?=$css?>"><?=date('d.m.Y', $tran["invc_date"])?></td>
               <td class="content_row_os" style="<?=$css?>"><?=$tran["cust_company"]?>&nbsp;</td>
               <td class="content_row_os"><?=$tran["cust_rut"]?>&nbsp;</td>
               <td class="content_row_os" align="center"><?=$paystate?></td>
               <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=printPrice($tran_netto)?></nobr></td>
               <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=printPrice($tran["invc_total_taxes"])?></nobr></td>
               <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=printPrice($tran["invc_total_brutto"])?></nobr></td>
            </tr>
            <?php
            $ges_total_taxes_exclude   += $tran["invc_total_taxes_exclude"];
            $ges_total_netto           += $tran_netto;
            $ges_total_taxes           += $tran["invc_total_taxes"];
            $ges_total_brutto          += $tran["invc_total_brutto"];

            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]                              = $tran;
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["tran_type"]                 = $tran_type;
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["paystate"]                  = $paystate;
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_date"]                 = date('d.m.Y', $tran["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_total_taxes_exclude"]  = printPrice($tran["invc_total_taxes_exclude"], $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["tran_netto"]                = printPrice($tran_netto, $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_total_taxes"]          = printPrice($tran["invc_total_taxes"], $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$x]["invc_total_brutto"]         = printPrice($tran["invc_total_brutto"], $numberlim);
         }
         ?>
         <tr>
            <td class="content_row_totals" colspan="5">TOTAL FACTURAS</td>
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
      
for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
{
   if($xnotetype == 1)
   {
      $sectitle = "NOTAS DE CREDITO";
      $secinit  = "";
      $prcsign  = "";
   }
   else
   {
      $sectitle = "NOTAS DE DEBITO";
      $secinit  = "";
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
               <col width="50">
               <col width="85">
               <col width="85">
               <col width="85">
            </colgroup>
            <tr>
               <td class="content_tbl_header" colspan="8"><img src="./images/menu/icons/documents.png" style="vertical-align:bottom">&nbsp;&nbsp;<?=$secinit?> <?=$sectitle?></td>
            </tr>
            <tr>
               <td class="content_tbl_subheader content_row_os">Nota</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Cliente</td>
               <td class="content_tbl_subheader content_row_os">RUT</td>
               <td class="content_tbl_subheader content_row_os" align="center">Pagado</td>
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

               $paystate = "No";
               if((int)$tran["invc_payed"])
                  $paystate = "Si";
               
               //----------------------------------------------------------------------------------
               ?>
               <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os" style="<?=$css?>"><?=$tran["invc_docnumber"]?></td>
                  <td class="content_row_os" style="<?=$css?>"><?=date('d.m.Y', $tran["invc_date"])?></td>
                  <td class="content_row_os" style="<?=$css?>"><?=$tran["cust_company"]?>&nbsp;</td>
                  <td class="content_row_os"><?=$tran["cust_rut"]?>&nbsp;</td>
                  <td class="content_row_os" align="center"><?=$paystate?></td>
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

               //----------------------------------------------------------------------------------
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]                              = $tran;
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["sectitle"]                  = $sectitle;
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["tran_type"]                 = $tran_type;
               $_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$x]["paystate"]                  = $paystate;
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
               <td class="content_row_totals" colspan="5">TOTAL <?=$sectitle?></td>
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
?>
<tr>
   <td class="content_row_clear">
      <?php
      if(count($delivs) && $delivs != false)
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
            <td class="content_tbl_header" colspan="7"><img src="./images/menu/icons/car.png" style="vertical-align:bottom">&nbsp;&nbsp;GUIAS DE DESPACHO</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">Guias</td>
            <td class="content_tbl_subheader content_row_os">Fecha</td>
            <td class="content_tbl_subheader content_row_os">Cliente</td>
            <td class="content_tbl_subheader content_row_os">RUT</td>
            <td class="content_tbl_subheader content_row_os" align="right">Neto</td>
            <td class="content_tbl_subheader content_row_os" align="right">Nº Factura</td>
            <td class="content_tbl_subheader content_row_os" align="right">Fecha Factura</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($delivs) && $delivs != false; $x++)
         {
            $tran       = $delivs[$x];
            $tran_netto = $tran["invc_total_netto"];

            $sql = " select t1.id, t1.invc_docnumber, t1.invc_date
                     from invoices_sell t1
                     INNER JOIN invoices_sell_parts t2 ON t1.id = t2.part_invc_id
                     where
                     t1.invc_status       > 1 and
                     t1.invc_status      != 4 and
                     t1.invc_cust_id      = {$tran["dlv_cust_id"]} and
                     t2.part_dlv_id       = {$tran["id"]}
                     order by t1.invc_date asc, t1.invc_docnumber asc";
            $invcs = $CON->select($sql);
            if((int)$invcs[0]["id"])
            {
               $first_num  = $invcs[0]["invc_docnumber"];
               $first_date = date('d.m.Y', $invcs[0]["invc_date"]);
               $css        = "";
            }
            else
            {
               $first_num  = "Pendiente";
               $first_date = "Pendiente";
               $css        = "color:red";
            }
            //----------------------------------------------------------------------------------
            ?>
            <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$tran["invc_docnumber"]?></td>
               <td class="content_row_os"><?=date('d.m.Y', $tran["invc_date"])?></td>
               <td class="content_row_os"><?=$tran["cust_company"]?>&nbsp;</td>
               <td class="content_row_os"><?=$tran["cust_rut"]?>&nbsp;</td>
               <td class="content_row_os" align="right" style="<?=$css?>"><nobr><?=printPrice($tran_netto)?></nobr></td>
               <td class="content_row_os" align="right" style="<?=$css?>"><?=$first_num?></td>
               <td class="content_row_os" align="right" style="<?=$css?>"><?=$first_date?></td>
            </tr>
            <?php
            $ges_total_taxes_exclude   += $tran["invc_total_taxes_exclude"];
            $ges_total_netto           += $tran_netto;

            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]                              = $tran;
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]["tran_type"]                 = $tran_type;
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]["paystate"]                  = "N/A";
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]["invc_date"]                 = date('d.m.Y', $tran["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]["invc_total_taxes_exclude"]  = printPrice($tran["invc_total_taxes_exclude"], $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]["tran_netto"]                = printPrice($tran_netto, $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]["invc_total_taxes"]          = $first_num;
            $_SESSION["STATS"][$_sesmodulename]["DLVDATA"][$x]["invc_total_brutto"]         = $first_date;
         }
         ?>
         <tr>
            <td class="content_row_totals" colspan="4">TOTAL GUIAS</td>
            <td class="content_row_totals" align="right"><nobr><?=printPrice($ges_total_netto)?></nobr></td>
            <td class="content_row_totals" align="right"><nobr>&nbsp;</nobr></td>
            <td class="content_row_totals" align="right"><nobr>&nbsp;</nobr></td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["TDLVDATA"]["invc_total_taxes_exclude"]  = "<b>".printPrice($ges_total_taxes_exclude, $numberlim)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["TDLVDATA"]["tran_netto"]                = "<b>".printPrice($ges_total_netto, $numberlim)."</b>";
         $_SESSION["STATS"][$_sesmodulename]["TDLVDATA"]["invc_total_taxes"]          = " ";
         $_SESSION["STATS"][$_sesmodulename]["TDLVDATA"]["invc_total_brutto"]         = " ";
      }
      ?>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsBooksSelling($CON, 1);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsBooksSelling($CON, 1);
  
if($pdffile != "")
{
   $doctitle = "Movimientos-clientes-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Movimientos-clientes-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>