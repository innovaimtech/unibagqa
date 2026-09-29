<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "stock_trans";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_sortlinks             = Array("Número" => "1", "Artículo/Familia" => "2");
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
// if((int)$_SESSION[$_sesmodulename]["sql_shop"] == 30010 && !(int)$_SESSION[$_sesmodulename]["sql_storehouse"])
   // $_SESSION[$_sesmodulename]["sql_storehouse"] = $_CONFIG["_REPORTS_DEFAULT_STHID"];

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

if($_REQUEST["subexec"] == "search")
{
   //----------------------------------------------------------------------------------
   $joisql = " INNER JOIN item t2               ON t1.item_id = t2.id
               INNER JOIN item_productcats t3   ON t1.item_id = t3.item_id
               LEFT OUTER JOIN item_units t4    ON t2.item_unit = t4.id
               LEFT OUTER JOIN productcats t6   ON t3.cat_id = t6.id ";

   $datsql = " select t2.item_number_prod, t2.item_title, t1.item_id, t4.unit_name, t1.tran_amount,
                      t1.tran_type, t3.cat_id, t6.cat_title, t1.tran_id, t1.tran_day, t1.tran_month,
                      t1.tran_year, t1.tran_number
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
   $datsql   .= $seasql;

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
   {
      $_SESSION[$_sesmodulename]["orderBy"] = "7";
      $_SESSION[$_sesmodulename]["orderSort"] = "asc";
   }
   $datsql .= " order by t2.item_title, t1.tran_year asc, t1.tran_month asc, t1.tran_day asc ";

   //----------------------------------------------------------------------------------
   $items = $CON->select($datsql);
}

//----------------------------------------------------------------------------------
$_RES[0] = Array();
$_RES[1] = Array();

$xcounter = 0;
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $row = $items[$x];
   $itemtran = $row;
   $idx = (int)$_CONFIGTRANTYPES[$row["tran_type"]];
   $_IGNOREDATA = false;
   $_CUSTNAME   = "";
   $_DESTNAME   = "";
   $_OBSERV     = "";
   switch($itemtran["tran_type"])
   {
      case "invoicesbuy":
         $sql = " select invc_docnumber
                  from invoices_buy
                  where
                  id = {$itemtran["tran_id"]}";
         $docnum = $CON->select($sql);
         $docnum = "F/C ".$docnum[0]["invc_docnumber"];
         break;
      case "shipment":
         $sql = " select shp_supplier_docnum
                  from shipment
                  where
                  id = {$itemtran["tran_id"]}";
         $docnum = $CON->select($sql);
         $docnum = "G/C ".$docnum[0]["shp_supplier_docnum"];
         break;
      case "ordersdelivery":
         $sql = " select id, dlv_docnum, dlv_invoice_generated
                  from orders_delivery
                  where
                  id = {$itemtran["tran_id"]}";
         $docnum = $CON->select($sql);
         $docnum = $docnum[0];
         if($docnum["dlv_invoice_generated"] > 0)
         {
            $sql = " select t1.invc_docnumber, t3.cust_name
                     from invoices_sell t1
                     LEFT OUTER JOIN customer t3 ON t1.invc_cust_id = t3.id
                     where
                     t1.id = {$docnum["dlv_invoice_generated"]}";
            $docnum = $CON->select($sql);
            $_CUSTNAME = $docnum[0]["cust_name"];
            $docnum = "F/V ".$docnum[0]["invc_docnumber"];
         }
         else
         {
            $sql = " select distinct t1.invc_docnumber, t3.cust_name
                     from invoices_sell t1
                     INNER JOIN invoices_sell_parts t2 ON t1.id = t2.part_invc_id
                     LEFT OUTER JOIN customer t3 ON t1.invc_cust_id = t3.id
                     where
                     t1.invc_status IN (2,3) and
                     t2.part_dlv_id = {$itemtran["tran_id"]}";
            $idocs = $CON->select($sql);
            $istr  = "";
            foreach($idocs AS $idoc)
            {
               $istr .= $idoc["invc_docnumber"].",";
               $_CUSTNAME = $idoc["cust_name"];
            }
            $istr = substr($istr, 0, -1);
            $docnum = "G/V ".$docnum["dlv_docnum"];
            if($istr != "")
               $docnum .= ", F/V {$istr}";
         }
         break;
      case "sthdown":
      case "sthup":
         $sql = " select t1.strc_shop_id, t1.strc_shop_dest_id, t2.shop_name, t1.strc_bodegero, t3.cust_name, t1.strc_desc
                  from storehousechanges t1
                  LEFT OUTER JOIN company_shops t2 ON t1.strc_shop_dest_id = t2.id
                  LEFT OUTER JOIN customer t3 ON t1.strc_custid = t3.id
                  where
                  t1.id = {$itemtran["tran_id"]}";
         $destdata = $CON->select($sql);
         $destdata = $destdata[0];
         if((int)$destdata["strc_shop_id"] == (int)$destdata["strc_shop_dest_id"])
            $_IGNOREDATA = true;
         else
         {
            $docnum = "TRASPASO ".$itemtran["tran_number"];
            $_DESTNAME = $destdata["shop_name"];
            $_CUSTNAME = $destdata["cust_name"];
            $_OBSERV   = $destdata["strc_desc"];
         }
         break;
      case "stockchangeup":
      case "stockchangedown":
         $sql = " select t1.stk_annotation
                  from stockchanges t1
                  where
                  t1.id = {$itemtran["tran_id"]}";
         $destdata = $CON->select($sql);
         $destdata = $destdata[0];
         $_OBSERV  = $destdata["stk_annotation"];
         $docnum = "AJUSTE ".$itemtran["tran_number"];
         break;
      default:
         $docnum = "AJUSTE ".$itemtran["tran_number"];
   }
   if(!$_IGNOREDATA)
   {
      $_RES_TOTAL[$idx]                              += $row["tran_amount"];
      $_RES_ITEMS[$row["item_id"]][$idx]             += $row["tran_amount"];
      $_ITEMS[$row["item_id"]]["item_number_prod"]    = $row["item_number_prod"];
      $_ITEMS[$row["item_id"]]["item_title"]          = $row["item_title"];
      $_ITEMS[$row["item_id"]]["unit_name"]           = $row["unit_name"];

      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["item_number_prod"] = $row["item_number_prod"];
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["item_title"] = $row["item_title"];
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["unit_name"] = $row["unit_name"];
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["FECHA"] = sprintf("%02s",$itemtran["tran_day"]).".".sprintf("%02s",$itemtran["tran_month"]).".".$itemtran["tran_year"];
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["NUMERO DOCTO."] = $docnum;
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["CUSTNAME"] = $_CUSTNAME;
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["DESTNAME"] = $_DESTNAME;
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["OBSERV"] = $_OBSERV;
      if($idx)
         $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["ENTRADA"] = $itemtran["tran_amount"];
      else
         $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$row["item_id"]][$xcounter]["SALIDA"] = $itemtran["tran_amount"];
      $xcounter++;
   }
}
foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DETAILS"]) AS $itemid)
{
   $saldo = 0.00;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DETAILS"][$itemid]) AS $idx)
   {
      $saldo += $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$itemid][$idx]["ENTRADA"];
      $saldo -= $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$itemid][$idx]["SALIDA"];
      $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$itemid][$idx]["SALDO"] = $saldo;
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
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Movimientos del stock</b></td>
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
      onsubmit="return checkform(new Array(this.sql_company, this.sql_shop))">
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
                  <nobr>
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
                  </nobr>
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
                     $startyear  = date('Y') -10;
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
                  <nobr>
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
                  </nobr>
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
                  <nobr>
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
                  </nobr>
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
            onchange="unibLoadSpecCharFilters(this.value)"
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
                  if(count($_ITEMS) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($_ITEMS) > 0)
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
         <col width="75">
         <col>
         <col width="80">
         <col width="80">
         <col width="80">
         <col width="45">
         <col width="45">
         <col width="45">
         <col width="45">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 0)?></td>
         <td class="content_tbl_subheader content_row_os"><?=printSortLink($_sesmodulename, $_sortlinks, 1)?></td>
         <td class="content_tbl_subheader content_row_os">Unidad</td>
         <td class="content_tbl_subheader content_row_os" align="right">Entradas</td>
         <td class="content_tbl_subheader content_row_os" align="right">Salidas</td>
         
         <td class="content_tbl_subheader content_row_os" align="right">S/Act.</td>
         <td class="content_tbl_subheader content_row_os" align="right">S/Res.</td>
         <td class="content_tbl_subheader content_row_os" align="right">S/Com.</td>
         <td class="content_tbl_subheader content_row_os" align="right"><b>S/Disp.</b></td>

         <!-- <td class="content_tbl_subheader content_row_os" align="right"><b>Saldo</b></td>  -->
      </tr>
      <?php
      $x = 0;
      //----------------------------------------------------------------------------------
      foreach(array_keys($_ITEMS) AS $itemid)
      {
         $shareAmount  = getStockShared($CON, $itemid, "item", $_SESSION[$_sesmodulename]["sql_shop"]);
         $shareComp    = getItemStockComp($CON, $_SESSION[$_sesmodulename]["sql_shop"], $itemid, "item");
         $currentStock = getItemShopCurrentStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $itemid, "item", true, 2);
         $stock_dispo = $currentStock - $shareAmount - $shareComp;

         $tot_amount  += $shareAmount;
         $tot_comp    += $shareComp;
         $tot_current += $currentStock;
         $tot_disp    += $stock_dispo;

         ?>
         <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)" style="cursor:pointer"
         onclick="showFancybox('/iframe.fancy.php?module=itemtrans&itemid=<?=$itemid?>', 'iframe', 1000, 450, 'auto');">
            <td class="content_row_os"><?=$_ITEMS[$itemid]["item_number_prod"]?></td>
            <td class="content_row_os"><?=$_ITEMS[$itemid]["item_title"]?></td>
            <td class="content_row_os"><?=$_ITEMS[$itemid]["unit_name"]?>&nbsp;</td>
            <td class="content_row_os" align="right"><font color="green"><?=printPrice($_RES_ITEMS[$itemid][1],2)?></font></td>
            <td class="content_row_os" align="right"><font color="red"><?=printPrice($_RES_ITEMS[$itemid][0],2)?></font></td>
            <td class="content_row_os" align="right"><?=printPrice($currentStock, 0)?></td>
            <td class="content_row_os" align="right"><?=printPrice($shareAmount, 0)?></td>
            <td class="content_row_os" align="right"><?=printPrice($shareComp, 0)?></td>
            <td class="content_row_os" align="right"><b><?=printPrice($stock_dispo, 0)?></b></td>
            <?/*<td class="content_row_os" align="right"><b><?=printPrice($_RES_ITEMS[$itemid][1] - $_RES_ITEMS[$itemid][0],2)?></b></td>*/?>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = $_ITEMS[$itemid]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = $_ITEMS[$itemid]["item_title"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]          = $_ITEMS[$itemid]["unit_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["plus"]              = printPrice($_RES_ITEMS[$itemid][1],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["minus"]             = printPrice($_RES_ITEMS[$itemid][0],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["act"]               = printPrice($currentStock, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["res"]               = printPrice($shareAmount, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["comp"]               = printPrice($shareComp, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["disp"]              = printPrice($stock_dispo, 0);
         //$_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["total"]             = printPrice($_RES_ITEMS[$itemid][1] - $_RES_ITEMS[$itemid][0],2);

         $x++;
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="6" align="center">
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
            <td class="content_row_totals" colspan="3">Total</td>
            <td class="content_row_totals" align="right"><?=printPrice($_RES_TOTAL[1],2)?></td>
            <td class="content_row_totals" align="right"><?=printPrice($_RES_TOTAL[0],2)?></td>
            <td class="content_row_totals" align="right"><?=printPrice($tot_current, 0)?></td>
            <td class="content_row_totals" align="right"><?=printPrice($tot_amount,0)?></td>
            <td class="content_row_totals" align="right"><?=printPrice($tot_comp,0)?></td>
            <td class="content_row_totals" align="right"><?=printPrice($tot_disp,0)?></td>
            <?/*<td class="content_row_totals" align="right"><?=printPrice($_RES_TOTAL[1] - $_RES_TOTAL[0],2)?></td>*/?>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_number_prod"]  = "TOTAL";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["item_title"]        = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["unitdesc"]          = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["plus"]              = printPrice($_RES_TOTAL[1],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["minus"]             = printPrice($_RES_TOTAL[0],2);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["act"]               = printPrice($tot_current, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["res"]               = printPrice($tot_amount, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["comp"]               = printPrice($tot_comp, 0);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["disp"]              = printPrice($tot_disp, 0);

         // $_SESSION["STATS"][$_sesmodulename]["DATA"][$x]["total"]             = printPrice($_RES_TOTAL[1] - $_RES_TOTAL[0],2);
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
  $pdffile = doc_createStatsItemTrans($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsItemTrans($CON);
  
if($pdffile != "")
{
   $doctitle = "Movimientos-del-stock-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Movimientos-del-stock-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>