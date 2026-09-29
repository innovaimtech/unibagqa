<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_details";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo/Familia" => "2", "Unidad" => "4", "Fecha/Hora" => "13,12,11,15", "Bodega" => "14",
                                "Transacción" => "7", "Tipo" => "6", "Cantidad" => "9", "Usuario" => "19,20" );
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
   $_SESSION[$_sesmodulename]["sql_storehouse"]    = (int)$_REQUEST["sql_storehouse"];
   $_SESSION[$_sesmodulename]["sql_equvals"]       = $_REQUEST["sql_equvals"];
   $_SESSION[$_sesmodulename]["sql_xitemtype"]     = (int)$_REQUEST["sql_xitemtype"];
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
if((int)$_SESSION[$_sesmodulename]["sql_shop"] == 30010 && !(int)$_SESSION[$_sesmodulename]["sql_storehouse"])
   $_SESSION[$_sesmodulename]["sql_storehouse"] = $_CONFIG["_REPORTS_DEFAULT_STHID"];

if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
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

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$sql_year_init    = (int)date('Y', $sql_datefrom);
$sql_month_init   = (int)date('m', $sql_datefrom);
$sql_day_init     = (int)date('d', $sql_datefrom);
$sql_year_end     = (int)date('Y', $sql_dateto);
$sql_month_end    = (int)date('m', $sql_dateto);
$sql_day_end      = (int)date('d', $sql_dateto);

//----------------------------------------------------------------------------------
$stop = false;
$xsql_date = " and ( ";
for($xmonth = $sql_month_init, $xyear = $sql_year_init; $stop == false; $xmonth++)
{
   if($xmonth == 13)
   {
      $xmonth = 1;
      $xyear++;
   }

   $xsql_date .= " ( t1.tran_month = {$xmonth} and t1.tran_year = {$xyear}  ";
   if($xmonth == $sql_month_init && $xyear == $sql_year_init)
      $xsql_date .= " and t1.tran_day >= {$sql_day_init} ";
   if($xmonth == $sql_month_end && $xyear == $sql_year_end)
      $xsql_date .= " and t1.tran_day <= {$sql_day_end} ";
   $xsql_date .= " ) or ";
   
   if($xyear > $sql_year_end || ($xyear == $sql_year_end && $xmonth == $sql_month_end))
      $stop = true;
}
$xsql_date = substr($xsql_date, 0, -3);
$xsql_date .= " ) ";

//----------------------------------------------------------------------------------
$joisql = " INNER JOIN item t2                              ON t1.item_id = t2.id
            INNER JOIN item_productcats t3                  ON t1.item_id = t3.item_id
            LEFT OUTER JOIN item_units t4                   ON t2.item_unit = t4.id
            LEFT OUTER JOIN company_shops_storehouses t5    ON t1.tran_st_id = t5.id
            LEFT OUTER JOIN productcats t6                  ON t3.cat_id = t6.id
            LEFT OUTER JOIN user t7                         ON t1.tran_crtusr = t7.id
            LEFT OUTER JOIN stockchanges t20                ON t1.tran_id = t20.id and t1.tran_type IN ('stockchangeup', 'stockchangedown')
            LEFT OUTER JOIN stockchanges_issues t21         ON t20.stk_issueid = t21.id";

//----------------------------------------------------------------------------------
$cntsql = " select count(*) 'cc'
            from tran_data t1
            {$joisql}
            where
            t1.tran_st_id > 0 
            {$xsql_date} ";
            
$datsql = " select t2.item_number_prod, t2.item_title, t1.item_id, t4.unit_name, t1.tran_amount, t1.tran_type,
                   t1.tran_number, t1.tran_currstock, t1.tran_amount, t1.tran_st_id, t1.tran_day, t1.tran_month,
                   t1.tran_year, t5.st_name, t1.tran_id, t3.cat_id, t6.cat_title, t1.tran_crtdat,
                   t7.user_firstname, t7.user_lastname, t20.stk_annotation, t21.stkis_title
            from tran_data t1
            {$joisql}
            where
            t1.tran_st_id > 0 
            {$xsql_date} ";

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.tran_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.tran_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t3.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $seasql .= " and t1.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t1.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
if($_SESSION[$_sesmodulename]["sql_storehouse"])
   $seasql .= " and t1.tran_st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]}  ";
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
$cntsql   .= $seasql;
$datsql   .= $seasql;

$itemcount = $CON->select($cntsql);
$itemcount = (int)$itemcount[0]["cc"];

//----------------------------------------------------------------------------------
$_SESSION[$_sesmodulename]["startrow"] = $_SESSION[$_sesmodulename]["page"] * $_SESSION[$_sesmodulename]["rows_per_page"];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
{
   $_SESSION[$_sesmodulename]["orderBy"] = "16";
   $_SESSION[$_sesmodulename]["orderSort"] = "asc";
}

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";

//----------------------------------------------------------------------------------
$items = $CON->select($datsql);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
{
   $temp = $items;
   unset($items);

   for($x = 0; $x < count($temp) && $temp != false; $x++)
   {
      $row = $temp[$x];
      $_PCAT[$row["cat_id"]][$row["tran_st_id"]]["st_name"]            = $row["st_name"];
      $_PCAT[$row["cat_id"]][$row["tran_st_id"]]["item_title"]         = $row["cat_title"];
      $_PCAT[$row["cat_id"]][$row["tran_st_id"]]["item_number_prod"]   = sprintf("%03s",$row["cat_id"]);

      if(!$_CONFIGTRANTYPES[$row["tran_type"]])
         $_PCAT[$row["cat_id"]][$row["tran_st_id"]]["tran_amount"]    -= $row["tran_amount"];
      else
         $_PCAT[$row["cat_id"]][$row["tran_st_id"]]["tran_amount"]    += $row["tran_amount"];
   }
   $xcounter = 0;
   foreach(array_keys($_PCAT) AS $catid)
   {
      foreach(array_keys($_PCAT[$catid]) AS $stid)
      {
         $items[$xcounter]["st_name"]           = $_PCAT[$catid][$stid]["st_name"];
         $items[$xcounter]["item_number_prod"]  = $_PCAT[$catid][$stid]["item_number_prod"];
         $items[$xcounter]["item_title"]        = $_PCAT[$catid][$stid]["item_title"];
         $items[$xcounter]["tran_amount"]       = $_PCAT[$catid][$stid]["tran_amount"];
         $ges_total += $_PCAT[$catid][$stid]["tran_amount"];
         $xcounter++;
      }
   }
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
{
   $selshops = Array();
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
      {
            array_push($selshops, $shop);
      }
}

$sql = " select *
         from company_shops_storehouses
         where
         st_status  = 1 ";
$sql .= "order by st_name";
$storehouses = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $selstorehouses = Array();
   foreach($storehouses AS $storehouse)
      if($storehouse["st_shop_id"] == $_SESSION[$_sesmodulename]["sql_shop"])
         array_push($selstorehouses, $storehouse);
}
$xselstorehouses = $selstorehouses;

//----------------------------------------------------------------------------------
$pcats = formatFullProductCats(getFullProductCats($CON, 0));

$_SESSION["JSEXEC"] .= ';$("#obitpanel").html("");';
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
<script language="JavaScript">
function setCompanyShop(companyidx)
{
   var obj = document.all.sql_shop;
   obj.options.length = 1;
   document.all.sql_storehouse.options.length = 1;
   <?php
   foreach($shops AS $shop)
   {  ?>
      if(companyidx == '<?=$shop["shop_company_id"]?>')
      {
         var newIndex   = obj.options.length;
         var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
         newOpt.value   = '<?=$shop["id"]?>';
         obj.options[newIndex] = newOpt;
      }
      <?php
   }
   ?>
}
function setCompanyShopStorehouse(shopidx)
{
   var obj = document.all.sql_storehouse;
   obj.options.length = 1;

   <?php
   foreach($storehouses AS $storehouse)
   {  ?>
      if(shopidx == '<?=$storehouse["st_shop_id"]?>')
      {
         var newIndex   = obj.options.length;
         var newOpt     = new Option('<?=addslashes($storehouse["st_name"])?>');
         newOpt.value   = '<?=$storehouse["id"]?>';
         obj.options[newIndex] = newOpt;
      }
      <?php
   }
   ?>
}
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="99%">
<tr>
   <td height="30"><b class="content_header">Ajuste de inventario</b></td>
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
         <td class="content_row">
            <select class="text" name="sql_shop" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShopStorehouse(this.value)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($selshops AS $selshop)
               {  ?>
                  <option value="<?=$selshop["id"]?>"
                  <?php if($selshop["id"] == $_SESSION[$_sesmodulename]["sql_shop"]) echo "selected"?>><?=$selshop["shop_name"]?>
                  </option><?php
               }
               ?>
            </select>
         </td>
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
         <td class="content_rowl">Bodega</td>
         <td class="content_row">
            <select class="text" name="sql_storehouse" style="width:375px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($xselstorehouses AS $selstorehouse)
               {  ?>
                  <option value="<?=$selstorehouse["id"]?>"
                  <?php if($selstorehouse["id"] == $_SESSION[$_sesmodulename]["sql_storehouse"]) echo "selected"?>><?=$selstorehouse["st_name"]?>
                  </option><?php
               }
               ?>
            </select>
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
         <td class="content_rowl">Agrupar por</td>
         <td class="content_row">
            <input type="radio" name="sql_dspmode" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1) echo "checked"?>> Productos
            <input type="radio" name="sql_dspmode" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2) echo "checked"?>> Familia

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
               <col width="135">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($items) > 0 && $items != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($items) > 0 && $items != false)
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
      <?=Nifty_printH("box1", "99%")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="75">
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 2)?></td>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 3)?></td>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 4)?></td>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 5)?></td>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 6)?></td>
         <td class="content_tbl_header content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 8)?></td>
         <td class="content_tbl_header content_row_os">Tipo ajuste</td>
         <td class="content_tbl_header content_row_os">Observación</td>
         <td class="content_tbl_header content_row_os" align="right"><?=printSortLink($_sesmodulename, $_sortlinks, 7)?></td>
      </tr>
      <?php
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         $row = $items[$x];

         if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
         {
            if($row["tran_day"] < 10)
               $row["tran_day"] = "0".$row["tran_day"];
            if($row["tran_month"] < 10)
               $row["tran_month"] = "0".$row["tran_month"];
            $datstr = "{$row["tran_day"]}.{$row["tran_month"]}.{$row["tran_year"]}";
            $datstr .= " ".date("H:i", $row["tran_crtdat"]);

            if(!$_CONFIGTRANTYPES[$row["tran_type"]])
            {
               $cssprefix  = "<b class='msg_save_err'>";
               $csssign    = "-";
               $csssuffix  = "</b>";
               $ges_total -= $row["tran_amount"];
            }
            else
            {
               $cssprefix  = "<b class='msg_save_ok'>";
               $csssign    = "+";
               $csssuffix  = "</b>";
               $ges_total  += $row["tran_amount"];
            }
         }
         else
         {
            if($row["tran_amount"] < 0)
            {
               $cssprefix  = "<b class='msg_save_err'>";
               $csssign    = "";
               $csssuffix  = "</b>";
            }
            else
            {
               $cssprefix  = "<b class='msg_save_ok'>";
               $csssign    = "+";
               $csssuffix  = "</b>";
            }
         }

         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os"><?=$row["item_number_prod"]?></td>
            <td class="content_row_os"><?=$row["item_title"]?></td>
            <td class="content_row_os"><?=$row["unit_name"]?>&nbsp;</td>
            <td class="content_row_os"><?=$datstr?>&nbsp;</td>
            <td class="content_row_os"><?=$row["st_name"]?>&nbsp;</td>
            <td class="content_row_os"><?=$row["tran_number"]?>&nbsp;</td>
            <td class="content_row_os"><?=$_CONFIGTRANTYPESNAMES[$row["tran_type"]]?>&nbsp;</td>
            <td class="content_row_os"><?=$row["user_firstname"]?>&nbsp;<?=$row["user_lastname"]?></td>
            <td class="content_row_os"><?=$row["stkis_title"]?>&nbsp;</td>
            <td class="content_row_os"><?=$row["stk_annotation"]?>&nbsp;</td>
            <td class="content_row_os" align="right"><?=$cssprefix?><?=$csssign?><?=printPrice($row["tran_amount"],2)?><?=$cssprefix?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $row["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $row["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_name"]         = $row["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["datstr"]            = $datstr;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["st_name"]           = $row["st_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tran_number"]       = $row["tran_number"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tran_type"]         = $_CONFIGTRANTYPESNAMES[$row["tran_type"]];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tran_amount"]       = printPrice($row["tran_amount"],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["user_firstname"]    = $row["user_firstname"]." ".$row["user_lastname"];

         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["stkis_title"]       = $row["stkis_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["stk_annotation"]    = $row["stk_annotation"];
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="11" align="center">
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
            <td class="content_row_totals" colspan="10">Total</td>
            <td class="content_row_totals" align="right"><?=printPrice($ges_total,2)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = "TOTAL";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unit_name"]         = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["datstr"]            = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["st_name"]           = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tran_number"]       = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tran_type"]         = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["tran_amount"]       = printPrice($ges_total,2);
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
  $pdffile = doc_createStatsItemValues($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemValues($CON);
  
if($pdffile != "")
{
   $doctitle = "Ajuste-de-inventario-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Ajuste-de-inventario-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>