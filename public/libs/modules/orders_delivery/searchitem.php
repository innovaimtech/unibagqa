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

//----------------------------------------------------------------------------------
$sql = " select *
         from orders_delivery
         where
         id = {$_REQUEST["dlvid"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));
echo md5(microtime());

?>
<script language="JavaScript">
var setX = -1;
var obj = parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0];

if(obj.style.display == '')
{
   obj.options.length = 0;
   <?php
   //----------------------------------------------------------------------------------
   if($_REQUEST["rowcount"] != "" &&  ($_REQUEST["search"] != "" || ($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] != "")))
   {
      //----------------------------------------------------------------------------------
      if($headdata["dlv_mode"] <= 2)
      {
         $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'I',
                         t3.itemshop_sellprice_netto, t3.itemshop_sellprice_taxes_perc, t1.item_charges_act,
                         t3.itemshop_sellprice_brutto
                  from item t1
                  LEFT OUTER JOIN item_suppliers t2   ON t1.id = t2.item_id
                  INNER JOIN item_shops t3            ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["dlv_shop_id"]} )
                  LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
                  where
                  t1.item_status       = 1 and
                  t1.item_released     = 1 and
                  t1.item_sellable     = 1 and
                  (
                     t1.item_title        like '%{$_REQUEST["search"]}%' or
                     t2.item_code         like '%{$_REQUEST["search"]}%' or
                     t1.item_number       like '%{$_REQUEST["search"]}%' or
                     t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
                     tx.item_barcode      = '{$_REQUEST["search"]}'
                  ) ";
         if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
            $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
         if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
            $sql .= " and 1 = 3 ";
         $sql .= "UNION ALL
                  select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'L',
                         t3.itemshop_sellprice_netto, t3.itemshop_sellprice_taxes_perc, t5.item_charges_act,
                         t3.itemshop_sellprice_brutto
                  from itemlist t1
                  LEFT OUTER JOIN itemlist_suppliers t2  ON t1.id = t2.item_id
                  INNER JOIN itemlist_shops t3           ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["dlv_shop_id"]} )
                  LEFT OUTER JOIN itemlist_pos t4        ON (t1.id = t4.itemlist_id)
                  LEFT OUTER JOIN item t5                ON (t4.item_id  = t5.id)
                  where
                  t1.item_status       = 1 and
                  t1.item_released     = 1 and
                  t1.item_sellable     = 1 and
                  (
                     t1.item_title        like '%{$_REQUEST["search"]}%' or
                     t2.item_code         like '%{$_REQUEST["search"]}%' or
                     t1.item_number       like '%{$_REQUEST["search"]}%' or
                     t1.item_number_prod  like '%{$_REQUEST["search"]}%'
                  ) ";
         if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
            $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
         if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
            $sql .= " and 1 = 3 ";

         $sql .= " order by 2,4
                   LIMIT 0,200";
         $items  = $CON->select($sql);

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($items) && $items != false; $x++)
         {
            if($items[$x]["item_type"] == "item_typeI")
               $items[$x]["item_type"] = "item";
            else
               $items[$x]["item_type"] = "itemlist";

            $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
            $desc          = trim(addslashes($items[$x]["item_title"]));
            $custprice     = getCustomerPLPrice($CON, $headdata["dlv_cust_id"], $items[$x]["id"], $items[$x]["item_type"]);
            
            if((int)$custprice["item_id"])
            {
               $items[$x]["itemshop_sellprice_netto"]       = (float)$custprice["item_sellprice_netto"];
               $items[$x]["itemshop_sellprice_taxes_perc"]  = (float)$custprice["item_sellprice_taxes_perc"];
               $items[$x]["itemshop_sellprice_brutto"]      = (float)$custprice["item_sellprice_brutto"];
            }
            ?>
            var newIndex = obj.options.length;
            var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)');
            newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=printPrice($items[$x]["itemshop_sellprice_netto"])?>#<?=printPrice($items[$x]["itemshop_sellprice_taxes_perc"],2)?>#<?=(int)$items[$x]["item_charges_act"]?>#<?=printPrice($items[$x]["itemshop_sellprice_brutto"])?>';
            obj.options[newIndex] = newOpt;

            if(setX == -1)
            {
               setX = newIndex;
               obj.selectedIndex  = 0;
            }
            <?php
         }
         ?>
         if(setX != -1)
         {
            
            parent.setItemInfosOrderDelivery(<?=$_REQUEST["rowcount"]?>, obj.options[setX].value);
         }
         <?php
      }
      else
      {
         $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                         t2.item_costprice_usd, 'item_type' 'I', t1.item_charges_act, t2.item_costprice_brutto
                  from item t1
                  INNER JOIN item_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["dlv_supplier_id"]} )
                  INNER JOIN item_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["dlv_shop_id"]} )
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
                  
         if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
            $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
         if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
            $sql .= " and 1 = 3 ";

         $sql .= "UNION ALL
                  select distinct t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                                  t2.item_costprice_usd, 'item_type' 'L', t5.item_charges_act, t2.item_costprice_brutto
                  from itemlist t1
                  INNER JOIN itemlist_suppliers t2    ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["dlv_supplier_id"]} )
                  INNER JOIN itemlist_shops t3        ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["dlv_shop_id"]} )
                  LEFT OUTER JOIN itemlist_pos t4     ON (t1.id = t4.itemlist_id)
                  LEFT OUTER JOIN item t5             ON (t4. item_id  = t5.id)
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
            
         $sql .= " order by 2,7
                   LIMIT 0, 200";
         $items = $CON->select($sql);

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($items) && $items != false; $x++)
         {
            if($items[$x]["item_type"] == "item_typeI")
               $items[$x]["item_type"] = "item";
            else
               $items[$x]["item_type"] = "itemlist";

            $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
            $desc          = trim(addslashes($items[$x]["item_title"]));
            ?>
            var newIndex = obj.options.length;
            var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)');
            newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=printPrice($items[$x]["item_costprice_netto"])?>#<?=printPrice($items[$x]["item_costprice_taxes_perc"],2)?>#<?=(int)$items[$x]["item_charges_act"]?>#<?=printPrice($items[$x]["item_costprice_brutto"])?>';
            obj.options[newIndex] = newOpt;

            if(setX == -1)
            {
               setX = newIndex;
               obj.selectedIndex  = 0;
            }
            <?php
         }
         ?>
         if(setX != -1)
         {
            parent.setItemInfosOrderDelivery(<?=$_REQUEST["rowcount"]?>, obj.options[setX].value);
         }
         <?php
      }
   }
   ?>
}
</script>