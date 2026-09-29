<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../classes/page.php");
require_once("../../../classes/mysql.php");
require_once("../../../config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
@session_start();

require_once("../../../lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../functions.php");

$_sesmodulename = "show_item_dispo";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_storehouse"]    = (int)$_REQUEST["sql_storehouse"];
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

//----------------------------------------------------------------------------------
$pcats      = formatFullProductCats(getFullProductCats($CON, 0));
$companies  = getCompanies($CON);
$shops      = getShops($CON);

$_REQUEST["sql_stext"] = trim(addslashes(str_replace("*","%",$_REQUEST["sql_stext"])));
if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $assigned = false;
   foreach($shops AS $shop)
   {
      
      if($shop["shop_company_id"] == $companies[0]["id"] && !$assigned)
      {
         $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
         $assigned = true;
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $sql = " select distinct t1.id, t1.item_title, t3.itemshop_sellprice_netto,
                   t3.itemshop_sellprice_brutto, t1.item_number_prod, 'item_type' 'I',
                   t1.item_unitembalaje_amount, t11.ubi_name
            from item t1
            LEFT OUTER JOIN item_suppliers t2     ON t1.id = t2.item_id
            LEFT OUTER JOIN ubicacion t11     ON t1.item_ubicacion = t11.id
            INNER JOIN item_shops t3         ON ( t1.id = t3.item_id and t3.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} )
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item') ";
   if($_REQUEST["sql_pcat"])
      $sql .= " INNER JOIN item_productcats t6 ON ( t1.id = t6.item_id and t6.cat_id = {$_REQUEST["sql_pcat"]}) ";
   if($_SESSION[$_sesmodulename]["sql_storehouse"])
      $sql .= " INNER JOIN item_shops_storehouses t10 ON (t3.item_id = t10.item_id and t3.shop_id = t10.shop_id and t10.st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} and t10.iss_inventory != 0) ";
   $sql .= "where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            t1.item_sellable     = 1 and
            (
               t1.item_title        like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number       like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["sql_stext"]}%' or
               t2.item_code         like '%{$_REQUEST["sql_stext"]}%' or
               tx.item_barcode      = '{$_REQUEST["sql_stext"]}'
            )
            UNION ALL
            select distinct t1.id, t1.item_title, t3.itemshop_sellprice_netto, t3.itemshop_sellprice_brutto,
                   t1.item_number_prod, 'item_type' 'L', t1.item_unitembalaje_amount, '' ''
            from itemlist t1
            LEFT OUTER JOIN itemlist_suppliers t2    ON t1.id = t2.item_id
            INNER JOIN itemlist_shops t3        ON ( t1.id = t3.item_id and t3.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ) ";
   if($_REQUEST["sql_pcat"])
      $sql .= " INNER JOIN item_productcats_itemlist t6 ON ( t1.id = t6.item_id and t6.cat_id = {$_REQUEST["sql_pcat"]}) ";
   $sql .= "where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            t1.item_sellable     = 1 and
            (
               t1.item_title        like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number       like '%{$_REQUEST["sql_stext"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["sql_stext"]}%' or
               t2.item_code         like '%{$_REQUEST["sql_stext"]}%'
            )
            order by 2
            LIMIT 0, 200";
   $items = $CON->select($sql);
}

$sql = " select *
         from company_shops_storehouses
         where
         st_status  = 1
         order by st_name";
$storehouses = $CON->select($sql);

$selstorehouses = Array();
foreach($storehouses AS $storehouse)
   if($storehouse["st_shop_id"] == $_SESSION[$_sesmodulename]["sql_shop"])
      array_push($selstorehouses, $storehouse);
//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../jscripts/sourcen.php");
      ?>
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
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.mousewheel-3.0.4.pack.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.pack.js"></script>
   <link rel="stylesheet" type="text/css" href="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.css" media="screen" />
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($items) == 0 || $items == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="show.item.dispo.fancy.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.sql_stext, this.sql_company, this.sql_shop))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="rowcount" value="<?=$_REQUEST["rowcount"]?>">
      <input type="hidden" name="reqid" value="<?=$_REQUEST["reqid"]?>">
      <?=Nifty_printH("box1", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="385">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <td class="content_rowl">Palabra</td>
         <td class="content_row">
            <input name="sql_stext" type="text" class="text" style="width:375px"
            value="<?=str_replace("%","*",$_REQUEST["sql_stext"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Cliente</td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="105">
               <col>
            </colgroup>
            <tr>
               <td>
                  <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)" name="xf_custsearch"
                  onblur="markfield(this,1);if(this.value != '') document.all.idxifrsrc.src='/libs/modules/orders/searchcust.php?rowcount=0&destobj=sql_customer&search=' +this.value"
                  onkeyup="detectCustEvent(event, 'orders')">
               </td>
               <td>
                  <select class="text" style="width:270px" name="sql_customer"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     if((int)$_SESSION[$_sesmodulename]["sql_customer"])
                     {
                        $sql = " select cust_name
                                 from customer
                                 where
                                 id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
                        $selcustomer = $CON->select($sql);
                        ?>
                        <option value="<?=$_SESSION[$_sesmodulename]["sql_customer"]?>">
                           <?=$selcustomer[0]["cust_name"]?>
                        </option>
                        <?php
                     }
                     ?>
                  </select>
               </td>
            </tr>
            </table>
         </td>
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
                  <?php if($pcat["id"] == $_REQUEST["sql_pcat"]) echo "selected"?>><?=sprintf("%03s", $pcat["id"])?> - <?=$pcat["cat_title"]?>
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
               foreach($selstorehouses AS $selstorehouse)
               {  ?>
                  <option value="<?=$selstorehouse["id"]?>"
                  <?php if($selstorehouse["id"] == $_SESSION[$_sesmodulename]["sql_storehouse"]) echo "selected"?>><?=$selstorehouse["st_name"]?>
                  </option><?php
               }
               ?>
            </select>
         </td>
      </tr>
      <script language="JavaScript">
         $('#xidx_sql_shop').change(function () {
            setCompanyShopStorehouse($(this).val());
         });
      </script>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="270">
            <tr>
               <td align="right">&nbsp;</td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
                     printButton("Resetear", "postnav", "show.item.dispo.fancy.php?searchexec=reset", "", "arrow-circle-double-135", 130);
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
</table>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
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
</colgroup>
<tr>
   <td class="content_tbl_subheader content_row_os">Número</td>
   <td class="content_tbl_subheader content_row_os">Nombre</td>
   <td class="content_tbl_subheader content_row_os">P/Neto</td>
   <td class="content_tbl_subheader content_row_os">P/Bruto</td>
   <td class="content_tbl_subheader content_row_os" align="center">Unidad</td>
   <td class="content_tbl_subheader content_row_os" align="center">Embal.</td>
   <td class="content_tbl_subheader content_row_os">Ubicación</td>
   <td class="content_tbl_subheader content_row_os">Ult./Mov</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center"><nobr>S/Act</nobr></td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center"><nobr>S/Res.</nobr></td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center"><nobr>S/Com.</nobr></td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center"><nobr>S/Disp</nobr></td>
</tr>
<?php
for($x = 0; $x < count($items) && $items != false; $x++)
{
   if($items[$x]["item_type"] == "item_typeI")
      $items[$x]["item_type"] = "item";
   else
      $items[$x]["item_type"] = "itemlist";
      
   $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
   $custprice     = getCustomerPLPrice($CON, $_SESSION[$_sesmodulename]["sql_customer"], $items[$x]["id"], $items[$x]["item_type"]);

   if((int)$custprice["item_id"])
   {
      $items[$x]["itemshop_sellprice_netto"]       = (float)$custprice["item_sellprice_netto"];
      $items[$x]["itemshop_sellprice_taxes_perc"]  =  (float)$custprice["item_sellprice_taxes_perc"];
   }

   $currloc = "";
   $stock   = getItemShopCurrentStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $items[$x]["id"], $items[$x]["item_type"], true, 2);
   if($stock != 0.00)
   {
      $itemsts = getItemStorehouses($CON, $_SESSION[$_sesmodulename]["sql_shop"], $items[$x]["id"], $items[$x]["item_type"], false, 0, 2, true);
      $stkeys  = array_keys($itemsts);
      $currloc = $itemsts[$stkeys[0]];
   }
   if($items[$x]["ubi_name"] != "")
      $currloc .= " - ".$items[$x]["ubi_name"];

   $lastmovedate = "- - -";
   if($items[$x]["item_type"] == "item")
   {
      $sql = " select tran_day, tran_month, tran_year
               from tran_data
               where
               item_id      = {$items[$x]["id"]} and
               tran_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]}
               order by tran_year desc, tran_month desc, tran_day desc
               LIMIT 0,1";
      $lasttran = $CON->select($sql);
      $lasttran = $lasttran[0];
      if((int)$lasttran["tran_year"])
         $lastmovedate = sprintf("%02s", $lasttran["tran_day"]).".".sprintf("%02s", $lasttran["tran_month"]).".".substr($lasttran["tran_year"],-2);
   }
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><nobr><?=$items[$x]["item_number_prod"]?></nobr></td>
      <td class="content_row_os"><?=$items[$x]["item_title"]?>&nbsp;</td>
      <td class="content_row_os" align="left">$&nbsp;<?=printprice($items[$x]["itemshop_sellprice_netto"])?></td>
      <td class="content_row_os" align="left">$&nbsp;<?=printprice($items[$x]["itemshop_sellprice_brutto"])?></td>
      <td class="content_row_os" align="center"><?=$unitdesc?>&nbsp;</td>
      <td class="content_row_os" align="center"><?=printprice($items[$x]["item_unitembalaje_amount"],2)?></td>
      <td class="content_row_os" align="left"><?=$currloc?>&nbsp;</td>
      <td class="content_row_os" align="left"><?=$lastmovedate?></td>
      <td class="content_row_os" align="center" valign="top">
         <?php
         $st_actual = printFancyBoxStock($CON, $_SESSION[$_sesmodulename]["sql_shop"], $items[$x]["id"], $items[$x]["item_type"], "storehousestock", 2);
         ?>
      </td>
      <td class="content_row_os" align="center" valign="top">
         <?php
         $shareamount = getStockShared($CON, $items[$x]["id"], $items[$x]["item_type"], $_SESSION[$_sesmodulename]["sql_shop"]);
         $xurlparams = "shopid={$_SESSION[$_sesmodulename]["sql_shop"]}&itemid={$items[$x]["id"]}&itemtype={$items[$x]["item_type"]}&compr=1";
         ?>
         <div style="cursor:pointer;" onclick="showFancybox('/libs/modules/stats/stock/show.sharedstock.php?<?=$xurlparams?>', 'iframe', 600, 400, 'auto')">
            <?=printPrice($shareamount, 2)?>
         </div>
         <?php
         $stockdispo = $stock-$shareamount;
         ?>
      </td>
      <td class="content_row_os" align="center" valign="top">
         <?php
         $stock_comp = getItemStockComp($CON, $_SESSION[$_sesmodulename]["sql_shop"], $items[$x]["id"], $items[$x]["item_type"]);
         $xurlparams = "shopid={$_SESSION[$_sesmodulename]["sql_shop"]}&itemid={$items[$x]["id"]}&itemtype={$items[$x]["item_type"]}&compr=0";
         ?>
         <div style="cursor:pointer;" onclick="showFancybox('/libs/modules/stats/stock/show.sharedstock.php?<?=$xurlparams?>', 'iframe', 600, 400, 'auto')">
            <?=printPrice($stock_comp, 2)?>
         </div>
      </td>
      <td class="content_row_os" align="center" valign="top"
      style="<?if($stockdispo < 0.00) echo "background-color:red;color:white"; elseif($stockdispo > 0.00) echo "background-color:lime;color:#222222"?>">
         <?=printPrice($stock - $shareamount - $stock_comp, 2)?>
      </td>
   </tr>
   <?php
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="10" align="center" valign="middle" height="30">
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
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
</body>
</html>