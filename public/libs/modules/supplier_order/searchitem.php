<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

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

echo md5(microtime());

$sql = " select *
         from supplier_order
         where
         id = {$_REQUEST["sordid"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$_FILTERCATSQL = "";
if($_REQUEST["sord_type"] == 0) //NORMAL
   $_FILTERCATSQL = " and t6.cat_id NOT IN (2,3,6) ";
elseif($_REQUEST["sord_type"] == 1) //TELAS
   $_FILTERCATSQL = " and t6.cat_id IN (6) ";
elseif($_REQUEST["sord_type"] == 2) //TINTAS
   $_FILTERCATSQL = " and t6.cat_id IN (2) ";
elseif($_REQUEST["sord_type"] == 3) //BOLSAS
   $_FILTERCATSQL = " and t6.cat_id IN (3) ";
?>
<script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
<script language="JavaScript">
parent.document.getElementById('item_costprice_netto_' +<?=$_REQUEST["rowcount"]?>).value = '';
parent.document.getElementById('item_costprice_taxes_perc_' +<?=$_REQUEST["rowcount"]?>).value = '';

var obj = parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0];

if(obj.style.display == '')
   obj.options.length = 0;
var varchecks = new Object();
var selFirst  = 0;
parent.$('select[id^="item_id_"]').each(function(index, value)
{
   var ival = new String($(this).attr('value'));
   if(ival != '' && ival.length > 0)
   {
      var valarr = ival.split('#');
      if(valarr.length > 0 && valarr[0] != '' && valarr[1] != '' && valarr[1] != 'manual')
         varchecks[valarr[0]] = valarr[1];
   }
});

if(obj.style.display == '')
{
   obj.options.length = 0;
   <?php
   $_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));

   if($_REQUEST["rowcount"] != "" && ($_REQUEST["search"] != "" || ($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] != "")))
   {
      if((int)$headdata["sord_taxes"])
      {
         $prcfield = "item_costprice_netto";
         $decimals = 2;
      }
      else
      {
         $prcfield = "item_costprice_usd";
         $decimals = 2;
      }
      
      $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                      t2.item_costprice_usd, 'item_type' 'I', t2.item_code
               from item t1
               INNER JOIN item_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["sord_supplier_id"]} )
               INNER JOIN item_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["sord_shop_id"]} )
               LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
               LEFT OUTER JOIN item_productcats t6 ON t1.id = t6.item_id
               where
               t1.item_status       = 1 and
               t1.item_released     = 1 and
               t1.item_purchasable  = 1 and
               (
                  t1.item_title        like '%{$_REQUEST["search"]}%' or
                  t1.item_number       like '%{$_REQUEST["search"]}%' or
                  t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
                  t2.item_code         like '%{$_REQUEST["search"]}%' or
                  tx.item_barcode      = '{$_REQUEST["search"]}'
               ) {$_FILTERCATSQL} ";
               
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
         $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
         $sql .= " and 1 = 3 ";

      $sql .= "UNION ALL
               select distinct t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                               t2.item_costprice_usd, 'item_type' 'L', t2.item_code
               from itemlist t1
               INNER JOIN itemlist_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["sord_supplier_id"]} )
               INNER JOIN itemlist_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["sord_shop_id"]} )
               where
               t1.item_status       = 1 and
               t1.item_released     = 1 and
               t1.item_purchasable  = 1 and
               (
                  t1.item_title        like '%{$_REQUEST["search"]}%' or
                  t1.item_number       like '%{$_REQUEST["search"]}%' or
                  t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
                  t2.item_code         like '%{$_REQUEST["search"]}%'
               ) ";
               
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
         $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
         $sql .= " and 1 = 3 ";
         
      $sql .= " order by 2
                LIMIT 0, 200";
      $items = $CON->select($sql);

      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($items[$x]["item_type"] == "item_typeI")
            $items[$x]["item_type"] = "item";
         else
            $items[$x]["item_type"] = "itemlist";

         $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         $orderamount   = getSupplierAutoOrderAmount($CON, $headdata["sord_shop_id"], $items[$x]["id"], $items[$x]["item_type"]);
         $desc          = trim(addslashes($items[$x]["item_title"]));

         $addcode = "";
         if($items[$x]["item_code"] != "")
            $addcode = " - ".$items[$x]["item_code"];
         ?>
         var addItem = 1;
         <?php
         if(!(int)$_REQUEST["allowdoubles"])
         {  ?>
            for(xitemid in varchecks)
            {  if(xitemid == '<?=$items[$x]["id"]?>' && varchecks[xitemid] == '<?=$items[$x]["item_type"]?>')
                  addItem = 0;
            }
            <?php
         }
         ?>
         if(addItem == 1)
         {
            var newIndex = obj.options.length;
            var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> <?=$addcode?> - <?=$desc?> (<?=$unitdesc?>)');
            newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=printPrice($items[$x][$prcfield], $decimals)?>#<?=printPrice($items[$x]["item_costprice_taxes_perc"],2)?>#<?=$orderamount?>';
            obj.options[newIndex] = newOpt;
            if(selFirst == 0)
            {
               obj.selectedIndex  = 0;
               parent.setItemInfos(<?=$_REQUEST["rowcount"]?>, newOpt.value);
               selFirst = 1;
            }
         }
         <?php
      }
   }
   ?>
}
</script>