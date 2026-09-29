<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "item_stockchanges";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo/Familia" => "2");

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql_item = explode("#", $_REQUEST["item_id"]);

   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_item_id"]       = $sql_item[0];
   $_SESSION[$_sesmodulename]["sql_item_type"]     = $sql_item[1];
   $_SESSION[$_sesmodulename]["sql_pcat"]          = (int)$_REQUEST["sql_pcat"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_dspmode"]       = (int)$_REQUEST["sql_dspmode"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
   $_SESSION[$_sesmodulename]["sql_equvals"]       = $_REQUEST["sql_equvals"];
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;

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
if(!(int)$_SESSION[$_sesmodulename]["sql_dspmode"])
   $_SESSION[$_sesmodulename]["sql_dspmode"] = 1;

$shops = getShops($CON, false, false, $_SESSION[$_sesmodulename]["sql_company"]);

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

$sql_year_init    = (int)date('Y', $sql_datefrom);
$sql_month_init   = (int)date('m', $sql_datefrom);
$sql_day_init     = (int)date('d', $sql_datefrom);
$sql_year_end     = (int)date('Y', $sql_dateto);
$sql_month_end    = (int)date('m', $sql_dateto);
$sql_day_end      = (int)date('d', $sql_dateto);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.strc_company_dest_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.strc_shop_dest_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t3.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $seasql .= " and t1b.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t1b.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";

if((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 1)
   $seasql .= " and t2.item_ventaonline_act = 1 ";
elseif((int)$_SESSION[$_sesmodulename]["sql_xitemtype"] == 2)
   $seasql .= " and t2.item_ventaonline_act = 0 ";

//----------------------------------------------------------------------------------
$sql_filter_equval = "";
foreach($_SESSION[$_sesmodulename]["sql_equvals"] AS $sql_equval)
   $sql_filter_equval .= "{$sql_equval},";
$sql_filter_equval = substr($sql_filter_equval, 0, -1);
if($sql_filter_equval != "")
{
   $seasql .= " and
                (
                   select count(*) 'cc'
                   from item_equipos_rel txx10
                   where
                   txx10.item_id    = t2.id and
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
                              txx11.item_id = t2.id and
                              txx11.val_id  IN ({$sql_subfilter_comvalids})
                           ) > 0 ";
}
if($sql_filter_comval != "")
   $seasql .= $sql_filter_comval;

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.strc_date, t1.strc_shop_dest_id, t1b.item_st_dest_id 'strc_shop_stid_dest', 
                   t1b.item_id, t1b.item_type, t2.item_title, t4.unit_name, t1.strc_number, t1b.item_amount, 
                   t7.st_name, t8.shop_name 'shop_short_name',
                   t7x.st_name 'from_st_name', t8x.shop_name 'from_shop_short_name'
            from storehousechanges t1
            INNER JOIN storehousechanges_items t1b ON t1.id = t1b.strc_id
            INNER JOIN item t2               ON t1b.item_id = t2.id
            INNER JOIN item_productcats t3   ON t1b.item_id = t3.item_id
            LEFT OUTER JOIN item_units t4    ON t2.item_unit = t4.id
            LEFT OUTER JOIN productcats t6   ON t3.cat_id = t6.id
            INNER JOIN company_shops_storehouses t7 ON t1b.item_st_dest_id = t7.id
            INNER JOIN company_shops t8 ON t1.strc_shop_dest_id = t8.id
            INNER JOIN company_shops_storehouses t7x ON t1b.item_st_id = t7x.id
            INNER JOIN company_shops t8x ON t1.strc_shop_id = t8x.id
            where
            t1.strc_status > 1 and
            t1b.item_type  = 'item' and
            t1.strc_date between {$sql_datefrom} and {$sql_dateto} ";
$datsql .= $seasql;

//----------------------------------------------------------------------------------
$datsql .= " order by 2,1 asc";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

for($x = 0; $x < count($items) && $items != false; $x++)
{
   $row = $items[$x];
   if(!is_array($_TRANS[$row["id"]]))
      $_TRANS[$row["id"]] = Array();
      
   $_TRANS[$row["id"]][] = $row;
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
      {
         if(!(int)$_SESSION[$_sesmodulename]["sql_shop"] || ((int)$_SESSION[$_sesmodulename]["sql_shop"] && $_SESSION[$_sesmodulename]["sql_shop"] == $shop["id"]))
            array_push($selshops, $shop);
      }
}

$sql = " select *
         from storehousechanges t1
         INNER JOIN company_shops_storehouses t7 ON t1.strc_shop_stid_dest = t7.id
         INNER JOIN company_shops t8 ON t1.strc_shop_dest_id = t8.id
         where
         strc_status = 2
         order by strc_date";
$stcspends = $CON->select($sql);
         

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));
  
printJSsetCompanyShop($shops);

//----------------------------------------------------------------------------------
$sql = " select user_printxls
         from user
         where
         id = {$_SESSION["user_id"]}";
$xlsuser = $CON->select($sql);
$xlsuser = $xlsuser[0]["user_printxls"];
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Traspasos</b></td>
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
         <col width="80">
         <col>
         <col width="120">
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
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y') +1;

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
                     $endyear    = date('Y') +1;

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
         <td class="content_rowl">Sucursal destino</td>
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
         <td class="content_rowl">Vista</td>
         <td class="content_row">
            <input type="radio" name="sql_dspmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1) echo "checked"?>> Traspaso
            <input type="radio" name="sql_dspmode" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2) echo "checked"?>> Detalle
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
                  if(count($items) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($items) > 0)
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
      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="75">
            <col width="80">
            <col>
            <col>
            <col width="80">
            <col width="80">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="6">Traspasos recibidos</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader">Traspaso</td>
            <td class="content_tbl_subheader">Fecha</td>
            <td class="content_tbl_subheader">Origen</td>
            <td class="content_tbl_subheader">Destino</td>
            <td class="content_tbl_subheader" align="right">Cantidad</td>
            <td class="content_tbl_subheader" align="right"><b>Neto/Total</b></td>
         </tr>
         <?php
      }
      $x = 0;
      $z = 0;
      $y = 0;
      //----------------------------------------------------------------------------------
      foreach(array_keys($_TRANS) AS $stcid)
      {
         $items  = $_TRANS[$stcid];
         $header = $items[0];

         if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
         {  ?>
            <?=Nifty_printH("box1", "980")?>
            <table border="0" cellpadding="3" cellspacing="0" width="100%">
            <colgroup>
               <col width="75">
               <col width="80">
               <col width="150">
               <col>
               <col>
               <col>
               <col width="70">
               <col width="70">
               <col width="70">
               <col width="70">
            </colgroup>
            <?php
            if(!$z)
            {  ?>
               <tr>
                  <td class="content_tbl_header" colspan="10">Traspasos recibidos</td>
               </tr>
               <?php
            }
            ?>
            <tr>
               <td class="content_tbl_subheader content_row_os">Traspaso</td>
               <td class="content_tbl_subheader content_row_os">Fecha</td>
               <td class="content_tbl_subheader content_row_os">Origen</td>
               <td class="content_tbl_subheader content_row_os">Destino</td>
               <td class="content_tbl_subheader content_row_os" colspan="2">Artículo</td>
               <td class="content_tbl_subheader content_row_os" align="center">Unidad</td>
               <td class="content_tbl_subheader content_row_os" align="right">Cantidad</td>
               <td class="content_tbl_subheader content_row_os" align="right">Neto/U</td>
               <td class="content_tbl_subheader content_row_os" align="right"><b>Neto/Total</b></td>
            </tr>
            <?php
         }
         $x = 0;
         $gesnetto = 0;
         $gesamount = 0;
         foreach($items AS $item)
         {
            $price = getSupplierItemCosts($CON, 0, $item["item_id"], "item");
            $item["item_costprice_netto"] = $price["item_costprice_netto"];
            $item["item_costprice_netto_total"] = $price["item_costprice_netto"] * $item["item_amount"];
            
            if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
            {  ?>
               <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
                  <td class="content_row_os"><?=$item["strc_number"]?></td>
                  <td class="content_row_os"><?=date('d.m.Y', $item["strc_date"])?></td>
                  <td class="content_row_os"><?=$item["from_shop_short_name"]?> | <?=$header["from_st_name"]?></td>
                  <td class="content_row_os"><?=$item["shop_short_name"]?> | <?=$header["st_name"]?></td>
                  <td class="content_row_os" colspan="2"><?=$item["item_title"]?>&nbsp;</td>
                  <td class="content_row_os" align="center"><?=$item["unit_name"]?></td>
                  <td class="content_row_os" align="right"><?=printPrice($item["item_amount"],2)?></td>
                  <td class="content_row_os" align="right"><?=printPrice($item["item_costprice_netto"])?></td>
                  <td class="content_row_os" align="right"><b><?=printPrice($item["item_costprice_netto_total"])?></b></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["strc_number"]                = $item["strc_number"];
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["strc_date"]                  = date('d.m.Y', $item["strc_date"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["from_st_name"]               = $item["from_shop_short_name"]." | ".$header["from_st_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["st_name"]                    = $item["shop_short_name"]." | ".$header["st_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["item_title"]                 = $item["item_title"];
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["unit_name"]                  = $item["unit_name"];
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["item_amount"]                = printPrice($item["item_amount"],2);
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["item_costprice_netto"]       = printPrice($item["item_costprice_netto"]);
               $_SESSION["STATS"][$_sesmodulename]["DATA2"][$z][$x]["item_costprice_netto_total"] = printPrice($item["item_costprice_netto_total"]);
            }

            
            $gesnetto  += $price["item_costprice_netto"] * $item["item_amount"];
            $gesamount += $item["item_amount"];
            $x++;
         }

         if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
         {
            $rcidx   = $z;
            $totcss  = "content_row";
         }
         else
         {
            $rcidx   = 0;
            $totcss = "content_row_totals";
         }

         ?>
         <tr bgcolor="<?=getRowColor($rcidx)?>">
            <td class="<?=$totcss?>"><?=$header["strc_number"]?></td>
            <td class="<?=$totcss?>"><?=date('d.m.Y', $header["strc_date"])?></td>
            <td class="<?=$totcss?>"><?=$header["from_shop_short_name"]?> | <?=$header["from_st_name"]?></td>
            <td class="<?=$totcss?>"><?=$header["shop_short_name"]?> | <?=$header["st_name"]?></td>
            <?php
            if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
            {  ?>
               <td class="<?=$totcss?>" align="right">&nbsp;</td>
               <td class="<?=$totcss?>" align="right">&nbsp;</td>
               <td class="<?=$totcss?>" align="right">&nbsp;</td>
               <?php
            }
            ?>
            <td class="<?=$totcss?>" align="right"><?=printPrice($gesamount,2)?></td>
            <?php
            if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
            {  ?>
               <td class="<?=$totcss?>" align="right">&nbsp;</td>
               <?php
            }
            ?>
            <td class="<?=$totcss?>" align="right"><?=printPrice($gesnetto,2)?></td>
         </tr>
         <?php

         $_SESSION["STATS"][$_sesmodulename]["DATA"][$y]["strc_number"]              = $header["strc_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$y]["strc_date"]                = date('d.m.Y', $header["strc_date"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$y]["from_st_name"]             = $header["from_shop_short_name"]." | ".$header["from_st_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$y]["st_name"]                  = $header["shop_short_name"]." | ".$header["st_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$y]["gesamount"]                = printPrice($gesamount,2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$y]["gesnetto"]                 = printPrice($gesnetto,2);

         if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
         {  ?>
            </table>
            <?=Nifty_printF()?>
            <br>
            <?php
         }
         $x++;
         $z++;
         $y++;
      }
      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
      {  ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      ?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsStockchanges($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsStockchanges($CON);
  
if($pdffile != "")
{
   $doctitle = "Traspasos-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Traspasos-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>