<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
//----------------------------------------------------------------------------------
$sql = " select *
         from user
         where
         id = {$_SESSION["user_id"]}";
$userdata = $CON->select($sql);
$userdata = $userdata[0];

//----------------------------------------------------------------------------------
$_sesmodulename         = "invc_offer_stats";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2,1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número Doc."      => 1,
                                "Fecha"            => 2,
                                "Vencimiento"      => 3,
                                "Cliente"          => 4,
                                "RUT"              => 5,
                                "Vendedor"         => 6,
                                "Monto total"      => 7);
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
   $_SESSION[$_sesmodulename]["sql_origtype"]      = (int)$_REQUEST["sql_origtype"];
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
if(!is_array($_SESSION[$_sesmodulename]["sql_paystatus"]))
   $_SESSION[$_sesmodulename]["sql_paystatus"] = Array(0=>0);
if(!is_array($_SESSION[$_sesmodulename]["sql_vencstatus"]))
   $_SESSION[$_sesmodulename]["sql_vencstatus"] = Array(0=>0,1=>1);
   
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

$datsql = " select t1.req_crtdat 'invc_date'
               , t1.req_number 'invc_docnumber'
               , '0' AS 'invc_estpay_date'
               , CONCAT(t7.user_firstname, ' ', t7.user_lastname) 'seller_name'
               , t1.req_total_netto 'invc_total_brutto'
               , t1.id
               , '1' AS 'invc_payed'
               , 'type' 'order'
               , 'note_type' '0'
               , t7.user_comission_perc
               , t1.req_cust_company
               , t1.req_cust_rut
               , t1.req_status
               , t1.req_crtusr 'seller_id'
               , GROUP_CONCAT(t4x.req_number ORDER BY t4x.req_number SEPARATOR ', ') 'order_number' 
               , cu.cust_contacto
               , case when isnull(cust_crtdat) then t1.req_cust_fax  else cu.cust_contacto end     as cust_contacto
               , case when isnull(cust_crtdat) then t1.req_cust_email else cu.cust_email end       as cust_email
               , case when isnull(cust_crtdat) then t1.req_cust_phone else cu.cust_cellphone end   as cust_cellphone
               , case when isnull(cust_crtdat) then t1.req_cust_comunaid else cu.cust_comunaid end as cust_comunaid
               , case when isnull(cust_crtdat) then t1.req_cust_catid else cu.cust_catid end       as cust_catid
               , canal.descripcion as canal
               , t1.req_total_brutto
               , t1.req_total_netto
               , cu.cust_crtdat
               , cu.cust_crtusr
               , oi.fab_type
               , cu.cust_presupuesto
               , cu.cust_permanente
               , min(req_prices_ccval1 * item_sellprice_netto_ccval1) as minimo
               , sub_cat_name
            from offers t1
               left outer join orders t4x                ON t1.id = t4x.req_offerid and t4x.req_status > 0
               inner join company_data t4                ON ( t1.req_company_id = t4.id and t4.company_status = 1 )
               inner join user t7                        ON ( t1.req_crtusr = t7.id )
               left outer join customer cu on cu.cust_rut = t1.req_cust_rut
               left outer join parametros canal on canal.tabla = 'CANAL' and canal.codigo = cu.cust_canal
               LEFT OUTER JOIN customer_sub_cats csc1 on cu.cust_subrubro = csc1.id
               inner join offers_items oi on oi.req_id = t1.id
            where
            t1.req_status > 1 and
            t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} ";

           
 //----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.req_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_seller"])
   $datsql .= " and t1.req_crtusr = {$_SESSION[$_sesmodulename]["sql_seller"]} ";
if((int)$userdata["user_offerlimit_perm"])
   $datsql .= " and t1.req_crtusr = {$_SESSION["user_id"]} ";
if($_SESSION[$_sesmodulename]["sql_folio"] != "")
   $datsql .= " and t1.req_number = '{$_SESSION[$_sesmodulename]["sql_folio"]}' ";
if($_SESSION[$_sesmodulename]["sql_stext2"] != "")
   $datsql .= " and
                  (
                     select count(*) 'cc'
                     from orders t8
                     where
                     t8.req_offerid = t1.id and
                     t8.req_status  > 0 and
                     t8.req_number  like '%{$_SESSION[$_sesmodulename]["sql_stext2"]}%'
                  ) > 0 ";

$datsql .= " group by t1.id
             order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}";

$invoices = $CON->select($datsql);             

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
   <td height="30"><b class="content_header">Cotizaciones por vendedor</b></td>
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
         <col width="110">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Nº Cotizacion</td>
         <td class="content_row">
            <nobr>
            <input name="sql_folio" type="text" class="text" style="width:187px"
            value="<?=$_SESSION[$_sesmodulename]["sql_folio"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <input name="sql_stext2" type="text" class="text" style="width:185px"
            placeholder="Número CC"
            value="<?=$_SESSION[$_sesmodulename]["sql_stext2"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </nobr>
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
                  if(count($invoices) > 0 && $invoices != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($invoices) > 0 && $invoices != false)
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
      <?=Nifty_printH("box1", "1180")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>                                                                                                            
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Número</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Fecha</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>RUT</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Cliente</nobr></td>
         <!--
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Contacto</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Mail</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Teléfono</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Comuna</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Provincia</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Region</nobr></td>
         -->
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Categoría</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Subrubro</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Canal</nobr></td>

         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>En Presupuesto</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Permanente</nobr></td>

         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Fecha creación de Cliente</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Vendedor</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Nº CC</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Tipo Producto</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Monto Cotizado</nobr></td>
         <td class="content_tbl_subheader content_row_os" valign="top"><nobr>Estado</nobr></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($invoices) && $invoices != false; $x++)
      {
         $ccnr = "";
         if((int)$invoices[$x]["req_status"] == 2 || (int)$invoices[$x]["req_status"] == 3)
            $ccnr = $invoices[$x]["order_number"];
         
         $fecha_creacion = "";
         if( (int)$invoices[$x]["cust_crtdat"] )
         {
            $fecha_creacion = $invoices[$x]["cust_crtdat"];
         }

         $sql = " select c.nombre
                        ,p.pro_name
                        ,r.name
                  from comunas c
                     inner join provincias p on c.prov_id = p.id
                     inner join regions r on r.id = c.id_region
                  where c.id = {$invoices[$x]["cust_comunaid"]} ";
         $ubicacion = $CON->select($sql);
         $ubicacion = $ubicacion[0];

         $sql = "select cat_name from customer_cats cc where {$invoices[$x]["cust_catid"]} = cc.id ";
         $categoria = $CON->select($sql);
         $categoria = $categoria[0];

         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><nobr><?=$invoices[$x]["invc_docnumber"]?></nobr></td>
            <td class="content_row_os"><nobr><?=date('d/m/Y', $invoices[$x]["invc_date"])?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["req_cust_rut"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["req_cust_company"]?></nobr></td>
            <!--
            <td class="content_row_os"><nobr><?=$invoices[$x]["cust_contacto"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["cust_email"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["cust_cellphone"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$ubicacion["nombre"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$ubicacion["pro_name"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$ubicacion["name"]?></nobr></td>
            -->
            <td class="content_row_os"><nobr><?=$categoria["cat_name"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["sub_cat_name"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["canal"]?></nobr></td>
            <td class="content_row_os"><nobr><?=($invoices[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO'?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=($invoices[$x]["cust_permanente"] == 1) ? 'SI' : 'NO'?>&nbsp;</nobr></td>
          
            <td class="content_row_os"><nobr><?=date('d/m/Y', $fecha_creacion)?></nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["seller_name"]?></nobr></td>
            <td class="content_row_os"><nobr><?=$ccnr?>&nbsp;</nobr></td>
            <td class="content_row_os"><nobr><?=$invoices[$x]["fab_type"]?></nobr></td>
            <?php
            if($invoices[$x]["req_total_netto"]==0)
               $invoices[$x]["req_total_netto"] = $invoices[$x]["minimo"]
            ?>
            <td class="content_row_os"><nobr><?=printPrice($invoices[$x]["req_total_netto"],0)?></nobr></td>
            <td class="content_row_os"><nobr><?=getOfferStatus($invoices[$x]["req_status"], true)?></nobr></td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_docnumber"]    = $invoices[$x]["invc_docnumber"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["invc_date"]         = $invoices[$x]["invc_date"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_rut"]          = $invoices[$x]["req_cust_rut"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_company"]      = $invoices[$x]["req_cust_company"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_contacto"]     = $invoices[$x]["cust_contacto"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_email"]        = $invoices[$x]["cust_email"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_cellphone"]    = $invoices[$x]["cust_cellphone"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["nombre"]            = $ubicacion["nombre"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["pro_name"]          = $ubicacion["pro_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["name"]              = $ubicacion["name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cat_name"]          = $categoria["cat_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["canal"]             = $invoices[$x]["canal"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fecha_creacion"]    = $fecha_creacion;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["seller_name"]       = $invoices[$x]["seller_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["ccnr"]              = $ccnr;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["fab_type"]          = $invoices[$x]["fab_type"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_total_netto"]  = printPrice($invoices[$x]["req_total_netto"],0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_status"]        = getOfferStatus($invoices[$x]["req_status"], false);
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["NAME"] = $invoices[$x]["seller_name"];
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["AMT"]++;
         $_SESSION["STATS"][$_sesmodulename]["OVERW"][$invoices[$x]["seller_id"]]["STATES"][getOfferStatus($invoices[$x]["req_status"], false)]++;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_presupuesto"]  = ($invoices[$x]["cust_presupuesto"] == 1) ? 'SI' : 'NO';     
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_permanente"]   = ($invoices[$x]["cust_permanente"] == 1) ? 'SI' : 'NO';     
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["sub_cat_name"]      = $invoices[$x]["sub_cat_name"];
         
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
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
      orderSellerByNameCallback($_SESSION["STATS"][$_sesmodulename]["OVERW"]);
      ?>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="120">
         <col width="120">
         <col width="120">
         <col width="120">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="5">Resumen por vendedor</td>
      </tr>
      <tr>
         <td class="content_tbl_subheader content_row_os" align="left">Nombre Vendedor</td>
         <td class="content_tbl_subheader content_row_os" align="right">Total</td>
         <td class="content_tbl_subheader content_row_os" align="right">Finalizado</td>
         <td class="content_tbl_subheader content_row_os" align="right">Aceptado</td>
         <td class="content_tbl_subheader content_row_os" align="right">Rechazado</td>
      </tr>
      <?php
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["OVERW"]) AS $sellerid)
      {  ?>
         <tr>
            <td class="content_row_os"><?=$_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["NAME"]?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["AMT"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["STATES"]["Finalizado"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["STATES"]["Aceptado"])?></td>
            <td class="content_row_os" align="right"><?=printPrice($_SESSION["STATS"][$_sesmodulename]["OVERW"][$sellerid]["STATES"]["Rechazado"])?></td>
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
  $pdffile = doc_createStatsSellingSellersOffer($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellingSellersOffer($CON);
  
if($pdffile != "")
{
   $doctitle = "Cotizaciones-por-vendedor-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Cotizaciones-por-vendedor-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>