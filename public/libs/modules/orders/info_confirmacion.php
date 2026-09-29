<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "ccfms";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2,1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("");
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_seller"]        = (int)$_REQUEST["sql_seller"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_origtype"]      = (int)$_REQUEST["sql_origtype"];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_stext2"]        = trim(addslashes($_REQUEST["sql_stext2"]));
   $_SESSION[$_sesmodulename]["sql_folio"]         = trim(addslashes($_REQUEST["sql_folio"]));
   $_SESSION[$_sesmodulename]["sql_paystatus"]     = $_REQUEST["sql_paystatus"];
   $_SESSION[$_sesmodulename]["sql_vencstatus"]    = $_REQUEST["sql_vencstatus"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_cctype"]        = (int)$_REQUEST["sql_cctype"];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
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

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.req_number, t1.req_total_netto, t1.req_total_taxes, t1.req_total_brutto, t1.req_crtdat,
                   t7.user_lastname 'sellername',
                   t4x.cust_name, t4x.cust_rut, t1.req_isreserva, t1.req_isfabricate,
                   t8.fab_type, t8.fab_med_width, t8.fab_med_height, t8.fab_med_fuelle,
                   t8.item_id, t8.item_amount, t9.item_number_prod, t9.item_title,
                   t8.item_desc, t8.item_type, t8.item_sellprice_netto_dsc, t8.item_sellprice_taxes_perc,
                   t11.cat_title,
                   (
                     select MAX(tsub2.invc_docnumber)
                     from invoices_sell_parts tsub1
                     INNER JOIN invoices_sell tsub2 ON tsub1.part_invc_id = tsub2.id
                     where
                     tsub1.part_req_id = t1.id and
                     tsub1.part_invc_id = tsub2.id and
                     tsub2.invc_status > 1
                   ) AS 'invc_docnumber'
            from orders t1
            INNER JOIN company_data t4                ON ( t1.req_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer t4x              ON t1.req_cust_id    = t4x.id
            INNER JOIN user t7                        ON ( t1.req_userid_seller = t7.id )
            INNER JOIN orders_items t8                ON t1.id = t8.req_id
            LEFT OUTER JOIN item t9                   ON t8.item_id = t9.id and t8.item_type = 'item'
            LEFT OUTER JOIN item_productcats t10      ON t9.id = t10.item_id
            LEFT OUTER JOIN productcats t11           ON t10.cat_id = t11.id
            inner join prod_header p1                 ON p1.prd_reqid = t1.id
            where
            t1.req_status > 1 and req_isfabricate > 0 and 
            t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} ";

 //----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.req_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.req_userid_seller = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if($_SESSION[$_sesmodulename]["sql_stext2"] != "")
   $datsql .= " and t1.req_number like '{$_SESSION[$_sesmodulename]["sql_stext2"]}%' ";
if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   $datsql .= " and t1.req_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";

if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   $datsql .= " and t10.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";

if($_SESSION[$_sesmodulename]["sql_item_id"])
   $datsql .= " and t8.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t8.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
$datsql .= " order by t1.id, t8.item_pos";
$data = $CON->select($datsql);

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


//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);

//----------------------------------------------------------------------------------
function orderSellerByNameCallback($a, $b)
{
   return (strcmp($a["seller_name"], $b["seller_name"]));
}

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Confirmaciones de compra</b></td>
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
      <?=Nifty_printH("box2", "980",0)?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="110">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Nº CC</td>
         <td class="content_row">
            <input name="sql_stext2" type="text" class="text" style="width:100%"
            value="<?=$_SESSION[$_sesmodulename]["sql_stext2"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
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
                     $startyear  = date('Y') -10;
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
         <td class="content_rowl">Cliente</td>
         <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Producto</td>
         <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
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
      </tr>
      <tr style="display:none">
      <td class="content_rowl">Tipo CC</td>
         <td class="content_row">
            <input type="radio" name="sql_cctype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_cctype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_cctype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_cctype"] == 1) echo "checked"?>> Solo fabricación
            <input type="radio" name="sql_cctype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_cctype"] == 2) echo "checked"?>> Solo manual
         </td>
         <td class="content_rowl">Tipo producto</td>
         <td class="content_row">
            <input type="radio" name="sql_xitemtype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xitemtype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 1) echo "checked"?>> Solo venta online
            <input type="radio" name="sql_xitemtype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 2) echo "checked"?>> Solo otros
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
                  /*
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  */
                  ?>
               </td>
               <td align="left">
                  <?php
                  /*
                  if(count($data) > 0 && $data != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  */
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
      <style>
         .content_row_os { font-size:11px !important; }
      </style>
      <?=Nifty_printH("box1", "99%",0)?>
      <table border="0" cellpadding="2" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="70">
         <col width="90">
         <col>
         <col width="120">
         <col width="100">
         <col>
         <col>
         <col width="60">
         <col width="60">
         <col width="80">
      </colgroup>
      <tr>
         <td class="content_tbl_header content_row_os">N° CC</td>
         <td class="content_tbl_header content_row_os">Fecha</td>
         <td class="content_tbl_header content_row_os">RUT</td>
         <td class="content_tbl_header content_row_os">Cliente</td>
         <td class="content_tbl_header content_row_os">Vendedor</td>
         <td class="content_tbl_header content_row_os">Código</td>
         <td class="content_tbl_header content_row_os">Producto</td>
         <td class="content_tbl_header content_row_os">Familia</td>
         <td class="content_tbl_header content_row_os" align="center">Material</td>
         <td class="content_tbl_header content_row_os" align="center">Medidas</td>
         <td class="content_tbl_header content_row_os" align="center">Cantidad</td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($data) && $data != false; $x++)
      {
         if($data[$x]["item_type"] == "manual")
         {
            $data[$x]["item_number_prod"] = "[MANUAL]";
            $data[$x]["item_title"] = $data[$x]["item_desc"];
         }
         $medidas = (int)$data[$x]["fab_med_width"]."x".(int)$data[$x]["fab_med_height"];
         if(!(int)$data[$x]["fab_med_width"] || !(int)$data[$x]["fab_med_height"])
            $medidas = "- - -";
         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os">
                <a href="javascript:void(0);" class="link"
                   onclick="detectEvent(<?=$data[$x]['id']?>);"><?=$data[$x]["req_number"]?>
                </a>
            </td>
            <td class="content_row_os"><?=date('d.m.Y', $data[$x]["req_crtdat"])?></td>
            <td class="content_row_os"><?=$data[$x]["cust_rut"]?></td>
            <td class="content_row_os"><?=$data[$x]["cust_name"]?></td>
            <td class="content_row_os"><?=$data[$x]["sellername"]?></td>
            <td class="content_row_os"><?=$data[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$data[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$data[$x]["cat_title"]?></td>
            <td class="content_row_os" align="center"><?=$data[$x]["fab_type"]?>&nbsp;</td>
            <td class="content_row_os" align="center"><nobr><?=$medidas?></nobr></td>
            <td class="content_row_os" align="center"><?=printPrice($data[$x]["item_amount"])?></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_number"]           = $data[$x]["req_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_crtdat"]           = date('d-m-Y', $data[$x]["req_crtdat"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_rut"]             = $data[$x]["cust_rut"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_name"]            = $data[$x]["cust_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["sellername"]           = $data[$x]["sellername"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]     = $data[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]           = $data[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cat_title"]            = $data[$x]["cat_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_type"]             = $data[$x]["fab_type"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["medidas"]              = $medidas;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_amount"]          = (float)$data[$x]["item_amount"];
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="15" align="center">
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
$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
//----------------------------------------------------------------------------------
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellingCCs($CON);

if($xlsfile != "")
{
   $doctitle = "Confirmaciones-de-compra-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>

<script>

   // function detectEvent (event, rowcount, reqid)
   function detectEvent (reqid)
   {
      var rowcount = 1;
      var xurl = './libs/modules/orders/detallecc.fancy.php?reqid='+reqid;
      showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }


</script>


<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>