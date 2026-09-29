<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_transit_cumpl";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo" => "2", "Unidad" => "3", "Número OC" => "4",
                                "Proveedor" => "5", "Fecha" => "6", "Pendiente" => "7", "Solicitado" => "10",
                                "Recibido" => "11");

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
   $_SESSION[$_sesmodulename]["sql_supplier"]  = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_format"]    = (int)$_REQUEST["sql_format"];
   $_SESSION[$_sesmodulename]["sql_occumpl"]   = (int)$_REQUEST["sql_occumpl"];
   $_SESSION[$_sesmodulename]["sql_stext"]     = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["sql_stext"]))));
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["page"]          = 0;
   $_SESSION[$_sesmodulename]["search_active"] = 1;
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
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort);

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON, true);
$shops      = getShops($CON);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.sord_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.sord_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_supplier"])
   $seasql .= " and t1.sord_supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t6.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   $seasql .= " and t1.sord_number like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' ";

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
$sql = " select t7.item_number_prod, t7.item_title, t8.unit_name, t1.sord_number, t5.supp_short, t1.sord_date, t1.sord_hash,
                 SUM(t2.item_amount - t2.item_amount_shipped) 'transstock', t2.item_id, t1.id,
                 SUM(t2.item_amount) 'item_amount',
                 SUM(t2.item_amount_shipped) 'item_amount_shipped'
         from supplier_order t1
         INNER JOIN supplier_order_items  t2 ON t1.id       = t2.sord_id
         INNER JOIN item_suppliers        t4 ON ( t2.item_id = t4.item_id and t4.supplier_id = t1.sord_supplier_id )
         INNER JOIN supplier              t5 ON ( t4.supplier_id = t5.id )
         LEFT OUTER JOIN item_productcats t6 ON t2.item_id = t6.item_id
         INNER JOIN item                  t7 ON t2.item_id = t7.id
         LEFT OUTER JOIN item_units       t8 ON t7.item_unit = t8.id
         where
         t1.sord_date between    {$sql_datefrom} and {$sql_dateto} and
         t2.item_type            = 'item' ";
if($_REQUEST["datamode"] == "archive")
{
   if((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 0)
      $sql .= " and
                t1.sord_order_shipped   = 1 and
                t2.item_amount          > t2.item_amount_shipped and
                t1.sord_status          IN (4) ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 1 || (int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 2)
      $sql .= " and t1.sord_status      = 4 ";
}
else
{
   if((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 0)
      $sql .= " and
                t1.sord_order_shipped   = 0 and
                t2.item_amount          > t2.item_amount_shipped and
                t1.sord_status          IN (2,3) ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 1 || (int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 2)
      $sql .= " and t1.sord_status      > 1 ";
}
$sql .= " {$seasql1}
         group by 1,2,3,4,5,6,7,9,10
         UNION ALL
         select t7.item_number_prod, t7.item_title, t8.unit_name, t1.sord_number, t5.supp_short, t1.sord_date, t1.sord_hash,
                 SUM(t2.item_amount - t2.item_amount_shipped) 'transstock', t2.item_id, t1.id,
                 SUM(t2.item_amount) 'item_amount',
                 SUM(t2.item_amount_shipped) 'item_amount_shipped'
         from supplier_order t1
         INNER JOIN supplier_order_items  t2 ON t1.id       = t2.sord_id
         INNER JOIN itemlist_suppliers    t4 ON ( t2.item_id = t4.item_id and t4.supplier_id = t1.sord_supplier_id )
         INNER JOIN supplier              t5 ON ( t4.supplier_id = t5.id )
         LEFT OUTER JOIN item_productcats_itemlist t6 ON t2.item_id = t6.item_id
         INNER JOIN itemlist              t7 ON t2.item_id = t7.id
         LEFT OUTER JOIN item_units       t8 ON t7.item_unit = t8.id
         where
         t1.sord_date between    {$sql_datefrom} and {$sql_dateto} and
         t2.item_type            = 'itemlist' ";
if($_REQUEST["datamode"] == "archive")
{
   if((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 0)
      $sql .= " and
                t1.sord_order_shipped   = 1 and
                t2.item_amount          > t2.item_amount_shipped and
                t1.sord_status          IN (4) ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 1 || (int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 2)
      $sql .= " and t1.sord_status      = 4 ";
}
else
{
   if((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 0)
      $sql .= " and
                t1.sord_order_shipped   = 0 and
                t2.item_amount          > t2.item_amount_shipped and
                t1.sord_status          IN (2,3) ";
   elseif((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 1 || (int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 2)
      $sql .= " and t1.sord_status      > 1 ";
}
$sql .= " {$seasql2}
         group by 1,2,3,4,5,6,7,9,10
         order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
$items = $CON->select($sql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $row = $items[$x];
   if(!is_array($_RES[$row["id"]]))
      $_RES[$row["id"]] = Array();
      
   $_RES[$row["id"]][]  = $row;
   $_NUM[$row["id"]]    = $row["sord_number"];
   $_SUP[$row["id"]]    = $row["supp_short"];
   $_DAT[$row["id"]]    = date('d.m.Y', $row["sord_date"]);
   $_HSH[$row["id"]]    = $row["sord_hash"];
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_format"])
{
   foreach(array_keys($_RES) AS $sordid)
   {
      $posdata = getSupplierOrderPos($CON, $sordid);
      unset($_TMP);
      $itemsfailed = false;
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $posdata[$x]["transstock"] = $posdata[$x]["item_amount"] - $posdata[$x]["item_amount_shipped"];
         $posdata[$x]["unit_name"]  = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
         $_TMP[$x] = $posdata[$x];

         if($posdata[$x]["transstock"] > 0.00)
            $itemsfailed = true;
      }

      if((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 0 || (int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 2)
      {
         $_RES[$sordid] = $_TMP;
      }
      if((int)$_SESSION[$_sesmodulename]["sql_occumpl"] == 1 && $itemsfailed)
      {
         unset($_RES[$sordid]);
         unset($_NUM[$sordid]);
         unset($_SUP[$sordid]);
         unset($_DAT[$sordid]);
      }
   }
}

$_INVC = array();
foreach(array_keys($_RES) AS $sordid)
{
   unset($ipos);
   unset($fdat);
   $sql = " select t1.invc_docnumber, t1.invc_total_netto, t1.invc_total_taxes, t1.invc_total_brutto, t3.*, t1.id
            from invoices_buy t1
            INNER JOIN invoices_buy_parts t2       ON ( t1.id = t2.part_invc_id )
            INNER JOIN invoices_buy_parts_items t3 ON ( t2.part_invc_id = t3.invc_id and t3.part_id = t2.id )
            where
            t1.invc_status          > 1 and
            t1.invc_status          < 4 and
            t2.part_sord_id         = {$sordid} ";
   $invoices = $CON->select($sql);
   foreach($invoices AS $invoice)
   {
      $fdat[$invoice["invc_docnumber"]]["ID"]      = $invoice["id"];
      $fdat[$invoice["invc_docnumber"]]["NETTO"]   = $invoice["invc_total_netto"];
      $fdat[$invoice["invc_docnumber"]]["BRUTTO"]  = $invoice["invc_total_brutto"];
      $fdat[$invoice["invc_docnumber"]]["TAXES"]   = $invoice["invc_total_taxes"];
   }
   $_INVC[$sordid] = $fdat;
}

//----------------------------------------------------------------------------------
$_SESSION["STATS"][$_sesmodulename]["_RES"] = $_RES;
$_SESSION["STATS"][$_sesmodulename]["_NUM"] = $_NUM;
$_SESSION["STATS"][$_sesmodulename]["_SUP"] = $_SUP;
$_SESSION["STATS"][$_sesmodulename]["_DAT"] = $_DAT;

//----------------------------------------------------------------------------------
if($items != false && count($items))
   $itemcount = count($items);
else
   $itemcount = 0;
//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

printJSsetCompanyShop($shops);
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
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
   <td height="30"><b class="content_header">Cumplimiento Ordenes de compra</b></td>
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
      <input type="hidden" name="datamode" value="<?=$_REQUEST["datamode"]?>">
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
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
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
         <td class="content_rowl">Numero OC</td>
         <td class="content_row">
            <input name="sql_stext" type="text" class="text" style="width:375px"
            value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_stext"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <select class="text" name="sql_occumpl" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="0" <?php if(0 == $_SESSION[$_sesmodulename]["sql_occumpl"]) echo "selected"?>>Solamente OC en estado incumplido</option>
               <option value="1" <?php if(1 == $_SESSION[$_sesmodulename]["sql_occumpl"]) echo "selected"?>>Solamente OC en estado cumplido</option>
               <option value="2" <?php if(2 == $_SESSION[$_sesmodulename]["sql_occumpl"]) echo "selected"?>>Todos</option>
            </select>
         </td>
         <td class="content_rowl">Formato OC</td>
         <td class="content_row">
            <select class="text" name="sql_format" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="0" <?php if(0 == $_SESSION[$_sesmodulename]["sql_format"]) echo "selected"?>>Solamente productos pendientes</option>
               <option value="1" <?php if(1 == $_SESSION[$_sesmodulename]["sql_format"]) echo "selected"?>>Orden de compra entero</option>
            </select>
         </td>
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
      <?php
      foreach(array_keys($_RES) AS $sordid)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="90">
            <col>
            <col width="100">
            <col width="100">
            <col width="100">
            <col width="100">
            <col width="100">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="7">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td class="content_tbl_header" style="padding:0px;margin:0px"><?=$_SUP[$sordid]?>, <?=$_NUM[$sordid]?>, <?=$_DAT[$sordid]?></td>
                  <td class="content_tbl_header" style="padding:0px;margin:0px" align="right">
                     <a class="link" style="color:#FFE3BA" href="javascript: deactivateFormChange()"
                     onclick="document.getElementById('idxifrsrc').src='./libs/modules/structure/document_file.php?type=0&id=<?=$sordid?>&hash=<?=$_HSH[$sordid]?>.pdf&name=<?=$_NUM[$sordid]?>.pdf&path=../../../docs.supplierorder/'"><img
                     src="./images/menu/icons/document-pdf.png" style="vertical-align:bottom">&nbsp;Imprimir OC</a>
                  </td>
               </tr>
               </table>
            </td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
            <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
            <td class="content_tbl_subheader content_row_os" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
            <td class="content_tbl_subheader content_row_os" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
            <td class="content_tbl_subheader content_row_os" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
            <td class="content_tbl_subheader content_row_os" align="center">Estado</td>
         </tr>
         <?php
         $x = 0;
         $ges_amount = 0.00;
         $ges_shipped = 0.00;
         $ges_trans = 0.00;
         foreach($_RES[$sordid] AS $item)
         {
            if(!(int)$item["transstock"])
            {
               $statecss = "#ADFFB6";
               $statetxt = "Cumplido";
            }
            else
            {
               $statecss = "#FFCED4";
               $statetxt = "Incumplido";
            }
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$item["item_number_prod"]?></td>
               <td class="content_row_os"><?=$item["item_title"]?></td>
               <td class="content_row_os"><?=$item["unit_name"]?>&nbsp;</td>
               <td class="content_row_os" align="right"><?=printPrice($item["item_amount"],2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($item["item_amount_shipped"],2)?></td>
               <td class="content_row_os" align="right"><?=printPrice($item["transstock"],2)?></td>
               <td class="content_row_os" align="center" style="background-color:<?=$statecss?>"><?=$statetxt?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_number_prod"]     = $item["item_number_prod"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_title"]           = $item["item_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["unit_name"]            = $item["unit_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_amount"]          = printPrice($item["item_amount"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_amount_shipped"]  = printPrice($item["item_amount_shipped"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["transstock"]           = printPrice($item["transstock"],2);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["state"]                = $statetxt;
            
            $ges_amount  += $item["item_amount"];
            $ges_shipped += $item["item_amount_shipped"];
            $ges_trans   += $item["transstock"];
            $x++;
         }
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_row_os" colspan="3">Total</td>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_number_prod"]     = "<b>Total</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_amount"]          = "<b>".printPrice($ges_amount,2)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_amount_shipped"]  = "<b>".printPrice($ges_shipped,2)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["transstock"]           = "<b>".printPrice($ges_trans,2)."</b>";
            ?>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_amount,2)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_shipped,2)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($ges_trans,2)?></td>
            <td class="content_row_totals content_row_os" align="right">&nbsp;</td>
         </tr>
         <?php
         foreach(array_keys($_INVC[$sordid]) AS $sinvc)
         {  ?>
            <tr>
               <td class="content_row_os" colspan="3">
                  Factura: <a href="javascript: deactivateFormChange()" class="link" onclick="document.all.idxifrsrc.src='index.php?mid=723&exec=edit&subexec=edit&id=<?=$_INVC[$sordid][$sinvc]["ID"]?>&printpdf=1'"><?=$sinvc?></a>
               </td>
               <td class="content_row_os" align="center"><nobr>NETO:  $ <?=printPrice($_INVC[$sordid][$sinvc]["NETTO"],2)?></nobr></td>
               <td class="content_row_os" align="center"><nobr>IVA:   $ <?=printPrice($_INVC[$sordid][$sinvc]["TAXES"],2)?></nobr></td>
               <td class="content_row_os" align="center"><nobr>BRUTO: $ <?=printPrice($_INVC[$sordid][$sinvc]["BRUTTO"],2)?></nobr></td>
               <td class="content_row_os" align="center">&nbsp;</td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_number_prod"]    = "<b>Factura: {$sinvc}</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_amount"]         = "<b>".printPrice($_INVC[$sordid][$sinvc]["NETTO"],2)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["item_amount_shipped"] = "<b>".printPrice($_INVC[$sordid][$sinvc]["TAXES"],2)."</b>";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid][$x]["transstock"]          = "<b>".printPrice($_INVC[$sordid][$sinvc]["BRUTTO"],2)."</b>";
            $x++;
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
  $pdffile = doc_createStatsItemTransitState($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemTransitState($CON);
  
if($pdffile != "")
{
   $doctitle = "Cumplimiento-OC-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Cumplimiento-OC-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>