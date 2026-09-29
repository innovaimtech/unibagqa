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
         from invoices_buy
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$deftaxes = "0";
if((int)$headdata["invc_taxes"])
   $deftaxes = printPrice($_SESSION["_CONF"]["conf_taxes"],2);
?>
<script language="JavaScript">
parent.document.getElementById('item_costprice_netto_<?=$_REQUEST["rowcount"]?>').value = '';
parent.document.getElementById('item_costprice_taxes_perc_<?=$_REQUEST["rowcount"]?>').value = '<?=$deftaxes?>';

var obj = parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0];

if(obj.style.display == '')
{
   obj.options.length = 0;
   <?php
   $_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));

   if($_REQUEST["rowcount"] != "" && ($_REQUEST["search"] != "" || ($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] != "")))
   {
      if(!(int)$headdata["invc_importation"])
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
                      t2.item_costprice_usd, 'item_type' 'I', t1.item_charges_act
               from item t1
               INNER JOIN item_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["invc_supplier_id"]} )
               INNER JOIN item_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["invc_shop_id"]} )
               LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
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
               ) ";
               
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
         $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
         $sql .= " and 1 = 3 ";
         
      $sql .= "UNION ALL
               select distinct t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                               t2.item_costprice_usd, 'item_type' 'L', t5.item_charges_act
               from itemlist t1
               INNER JOIN itemlist_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["invc_supplier_id"]} )
               INNER JOIN itemlist_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["invc_shop_id"]} )
               LEFT OUTER JOIN itemlist_pos t4 ON (t1.id = t4.itemlist_id)
               LEFT OUTER JOIN item t5 ON (t4. item_id  = t5.id)
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

      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         $sql = " SELECT t2.invc_crtdat, t1.*
                  FROM invoices_buy_parts_items t1
                  INNER JOIN invoices_buy t2 ON t1.invc_id = t2.id
                  INNER JOIn item         t3 ON t1.item_id = t3.id
                  WHERE
                  t1.item_id = {$items[$x]["id"]} and
                  t2.id     <> {$_REQUEST["id"]}
                  order by t2.invc_crtdat DESC";
         $cost_item = $CON->select($sql);

         if($items[$x]["item_type"] == "item_typeI")
            $items[$x]["item_type"] = "item";
         else
            $items[$x]["item_type"] = "itemlist";

         if($items[$x]["item_costprice_taxes_perc"] == false)
            $items[$x]["item_costprice_taxes_perc"] = 19.00;

         $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         $desc          = trim(addslashes($items[$x]["item_title"]));
         ?>
         var newIndex = obj.options.length;
         var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)');
         newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=printPrice($cost_item[0][item_costprice_netto], $decimals)?>#<?=printPrice($items[$x]["item_costprice_taxes_perc"],2)?>#0#<?=(int)$items[$x]["item_charges_act"]?>';
         obj.options[newIndex] = newOpt;
         <?php
         if($x == 0)
         {  ?>
            obj.selectedIndex  = 0;
            parent.setItemInfos('<?=$_REQUEST["rowcount"]?>', newOpt.value);
            <?php
            if($_REQUEST["storehousemode"] == "1")
            {  ?>
               parent.updateItemStorehouses('<?=$_REQUEST["rowcount"]?>', <?=$items[$x]["id"]?>, '<?=$items[$x]["item_type"]?>');
               <?php
            }
         }
      }
      
   }
   ?>
}
</script>