<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_invoiced";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Unidad" => "3", "Guia" => "4",
                                "Cliente" => "5", "Fecha" => "6", "Cantidad" => "7", "Codigo/Prov." => "9");

$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]   = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]      = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]   = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"] = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]      = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_customer"]  = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_equvals"]    = $_REQUEST["sql_equvals"];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_xhasstock"]     = (int)$_REQUEST["sql_xhasstock"];

   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);

   $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["sql_stext"]))));
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;

   unset($_SESSION[$_sesmodulename]["sql_comvals"]);
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "sql_comvals_") !== false && strpos($reqkey, "sql_comvals_") == 0)
      {
         $compid = substr($reqkey, strrpos($reqkey, "_") +1);

         foreach($_REQUEST[$reqkey] AS $compvalid)
            $_SESSION[$_sesmodulename]["sql_comvals"][$compid][(int)$compvalid] = 1;
      }
   }
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

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
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;

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
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.dlv_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.dlv_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_customer"])
   $seasql .= " and t1.dlv_cust_id = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t6.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   $seasql .= " and t1.dlv_docnum like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";

//----------------------------------------------------------------------------------
$seasql1 = $seasql;
$seasql2 = $seasql;
if($_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
{
   $seasql1 .= " and 1 = 2 ";
   $seasql2 .= " and t2.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
}
elseif($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
{
   $seasql1 .= " and t2.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]} ";
   $seasql2 .= " and 1 = 2 ";
}

//----------------------------------------------------------------------------------
$sql_filter_equval = "";
foreach($_SESSION[$_sesmodulename]["sql_equvals"] AS $sql_equval)
   $sql_filter_equval .= "{$sql_equval},";
$sql_filter_equval = substr($sql_filter_equval, 0, -1);
if($sql_filter_equval != "")
{
   $seasql1 .= " and
                (
                   select count(*) 'cc'
                   from item_equipos_rel txx10
                   where
                   txx10.item_id    = t7.id and
                   txx10.equipo_id  IN ({$sql_filter_equval})
                ) > 0 ";
}

//----------------------------------------------------------------------------------
$sql_filter_comval = "";
foreach(array_keys($_SESSION[$_sesmodulename]["sql_comvals"]) AS $sql_comid)
{
   $sql_subfilter_comvalids = implode(",", array_keys($_SESSION[$_sesmodulename]["sql_comvals"][$sql_comid]));
   $sql_filter_comval .= " and
                           (
                              select count(*) 'cc'
                              from tran_comments_item_vals txx11
                              where
                              txx11.item_id = t7.id and
                              txx11.val_id  IN ({$sql_subfilter_comvalids})
                           ) > 0 ";
}
if($sql_filter_comval != "")
   $seasql1 .= $sql_filter_comval;

if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $seasql1 .= " and t7.item_ventaonline_act = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $seasql1 .= " and t7.item_ventaonline_act = 0 ";

if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 1)
{
   $seasql1 .= " and
                  (
                     select SUM(tsuba.iss_inventory)
                     from item_shops_storehouses tsuba
                     INNER JOIN company_shops_storehouses tsubb ON tsuba.st_id = tsubb.id
                     where
                     tsuba.item_id = t7.id and
                     tsuba.shop_id = t1.dlv_shop_id and
                     tsubb.st_status > 0
                  ) > 0 ";
}
elseif($_SESSION[$_sesmodulename]["sql_xhasstock"] == 2)
{
   $seasql1 .= " and
                  (
                     select SUM(tsuba.iss_inventory)
                     from item_shops_storehouses tsuba
                     INNER JOIN company_shops_storehouses tsubb ON tsuba.st_id = tsubb.id
                     where
                     tsuba.item_id = t7.id and
                     tsuba.shop_id = t1.dlv_shop_id and
                     tsubb.st_status > 0
                  ) <= 0 ";
}

//----------------------------------------------------------------------------------
$sql = " select t7.item_number_prod, t7.item_title, t8.unit_name, t1.dlv_docnum 'req_number', t5.cust_name, t1.dlv_delivery_date 'req_crtdat',
                 SUM(t2.item_amount_shipped - t2.item_amount_invoiced) 'transstock', t2.item_id, t9.item_code
         from orders_delivery t1
         INNER JOIN orders_delivery_items t2 ON t1.id = t2.dlv_id
         INNER JOIN item_shops            t3 ON t2.item_id  = t3.item_id
         INNER JOIN customer              t5 ON t1.dlv_cust_id = t5.id
         LEFT OUTER JOIN item_productcats t6 ON t2.item_id = t6.item_id
         INNER JOIN item                  t7 ON t2.item_id = t7.id
         LEFT OUTER JOIN item_units       t8 ON t7.item_unit = t8.id
         LEFT OUTER JOIN item_suppliers   t9 ON ( t2.item_id = t9.item_id and t9.item_supp_act = 1 )
         where
         t1.dlv_delivery_date    between {$sql_datefrom} and {$sql_dateto} and
         t1.dlv_status           > 1 and
         t1.dlv_invoiced         = 0 and
         t1.dlv_invoice_generated = 0 and
         t1.dlv_mode             <= 2 and
         t2.item_type            = 'item' and
         t2.item_amount_shipped  > t2.item_amount_invoiced
         {$seasql1}
         group by 1,2,3,4,5,6,8
         UNION ALL
         select t7.item_number_prod, t7.item_title, t8.unit_name, t1.dlv_docnum 'req_number', t5.cust_name, t1.dlv_delivery_date 'req_crtdat',
                 SUM(t2.item_amount_shipped - t2.item_amount_invoiced) 'transstock', t2.item_id, t9.item_code
         from orders_delivery t1
         INNER JOIN orders_delivery_items t2 ON t1.id = t2.dlv_id
         INNER JOIN itemlist_shops        t3 ON t2.item_id  = t3.item_id
         INNER JOIN customer              t5 ON t1.dlv_cust_id = t5.id
         LEFT OUTER JOIN item_productcats_itemlist t6 ON t2.item_id = t6.item_id
         INNER JOIN itemlist              t7 ON t2.item_id = t7.id
         LEFT OUTER JOIN item_units       t8 ON t7.item_unit = t8.id
         LEFT OUTER JOIN itemlist_suppliers t9 ON ( t2.item_id = t9.item_id and t9.item_supp_act = 1 )
         where
         t1.dlv_delivery_date    between {$sql_datefrom} and {$sql_dateto} and
         t1.dlv_status           > 1 and
         t1.dlv_invoiced         = 0 and
         t1.dlv_invoice_generated = 0 and
         t1.dlv_mode             <= 2 and
         t2.item_type            = 'itemlist' and
         t2.item_amount          > t2.item_amount_shipped
         {$seasql2}
         group by 1,2,3,4,5,6,8
         order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$items = $CON->select($sql);

if($items != false && count($items))
   $itemcount = count($items);
else
   $itemcount = 0;
//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

if(!$_SESSION[$_sesmodulename]["sql_customer"])
   $tcols = 8;
else
   $tcols = 7;
   
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
   <td height="30"><b class="content_header">Stock por facturar</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults($itemcount); else echo $savemsg;?></td>
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
         <td class="content_rowl">Artículo</td>
         <td class="content_row"><?php printOverviewItemSelect($_sesmodulename) ?></td>
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
         <td class="content_rowl">Familia</td>
         <td class="content_row">
            <select class="text" name="sql_pcat" style="width:375px"
            onchange="unibLoadSpecCharFilters(this.value)"
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
         <td class="content_rowl">Numero Guia</td>
         <td class="content_row">
            <input name="sql_stext" type="text" class="text" style="width:375px"
            value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr id="idx_charact_opts" style="<?if(!(int)$_SESSION[$_sesmodulename]["sql_pcat"]) echo "display:none"?>">
         <td class="content_rowl" valign="top">Caracteristicas</td>
         <td class="content_row" colspan="4">
            <div id="idx_charact_jqres">
               <?php
               printPcatFilters($CON, $_SESSION[$_sesmodulename]["sql_pcat"], $_sesmodulename)
               ?>
            </div>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo producto</td>
         <td class="content_row">
            <input type="radio" name="sql_xitemtype" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xitemtype" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 1) echo "checked"?>> Solo venta online
            <input type="radio" name="sql_xitemtype" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xitemtype"] == 2) echo "checked"?>> Solo otros
         </td>
         <td class="content_rowl">Stock</td>
         <td class="content_row">
            <input type="radio" name="sql_xhasstock" value="0" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 0) echo "checked"?>> Todos
            <input type="radio" name="sql_xhasstock" value="1" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 1) echo "checked"?>> Solo con stock
            <input type="radio" name="sql_xhasstock" value="2" <?php if($_SESSION[$_sesmodulename]["sql_xhasstock"] == 2) echo "checked"?>> Solo sin stock
         </td>
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
                  <input type="text" style="width:75px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:75px" id="sql_date_pto" name="sql_date_pto"
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
               <col width="132">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if($itemcount > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if($itemcount > 0)
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
         <col width="65">
         <col>
         <col>
         <col>
         <col width="90">
         <?php
         if(!$_SESSION[$_sesmodulename]["sql_customer"])
         {  ?>
            <col>
            <?php
         }
         ?>
         <col width="75">
         <col width="60">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <?php
         if(!$_SESSION[$_sesmodulename]["sql_customer"])
         {  ?>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
            <?php
         }
         ?>

         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_subheader content_row_os" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {  ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$items[$x]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$items[$x]["item_title"]?></td>
            <td class="content_row_os"><?=$items[$x]["unit_name"]?>&nbsp;</td>
            <td class="content_row_os"><?=$items[$x]["item_code"]?>&nbsp;</td>
            <td class="content_row_os"><?=$items[$x]["req_number"]?>&nbsp;</td>
            <?php
            if(!$_SESSION[$_sesmodulename]["sql_customer"])
            {  ?>
               <td class="content_row_os"><?=$items[$x]["cust_name"]?>&nbsp;</td>
               <?php
            }
            ?>
            <td class="content_row_os"><?=date('d.m.Y', $items[$x]["req_crtdat"])?>&nbsp;</td>
            <td class="content_row_os" align="right"><?=printPrice($items[$x]["transstock"],2)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $items[$x]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $items[$x]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_name"]         = $items[$x]["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_code"]         = $items[$x]["item_code"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_number"]        = $items[$x]["req_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["cust_name"]         = $items[$x]["cust_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["req_crtdat"]        = date('d.m.Y', $items[$x]["req_crtdat"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["transstock"]        = printPrice($items[$x]["transstock"],2);
         
         $_TOTAL += $items[$x]["transstock"];
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="<?=$tcols?>" align="center">
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
            <td class="content_row_totals content_row_os" colspan="<?=($tcols -1)?>">Total</td>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = "<b>Total</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["transstock"]        = "<b>".printPrice($_TOTAL,2)."</b>";
            ?>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_TOTAL,2)?></td>
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
  $pdffile = doc_createStatsItemInvoiced($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemInvoiced($CON);
  
if($pdffile != "")
{
   $doctitle = "Stock-por-facturar-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Stock-por-facturar-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>