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

$sql = " select *
         from stockchanges
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));
echo md5(microtime());

?>
<script language="JavaScript">
var setX = -1;
parent.document.getElementById('item_stid_<?=$_REQUEST["rowcount"]?>').options.length = 0;
parent.document.getElementById('item_costprice_netto_' +<?=$_REQUEST["rowcount"]?>).value = '';
parent.document.getElementById('item_costprice_taxes_perc_' +<?=$_REQUEST["rowcount"]?>).value = '';
var obj = parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0];
obj.options.length = 0;
<?php
if($_REQUEST["rowcount"] != "" &&  $_REQUEST["search"] != "")
{
   //----------------------------------------------------------------------------------
   $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                   t2.item_costprice_usd, 'item_type' 'I', t1.item_charges_act
            from item t1
            LEFT OUTER JOIN item_suppliers t2   ON ( t1.id = t2.item_id and t2.item_supp_act = 1 )
            INNER JOIN item_shops t3            ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["stk_shopid"]} )
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
            INNER JOIN item_productcats t4      ON t1.id = t4.item_id
            INNER JOIN productcats t5           ON t4.cat_id = t5.id
            where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            t5.cat_repuestos_act = 1 and
            t1.item_prodwrk_act  = 1 and
            (
               t1.item_title        like '%{$_REQUEST["search"]}%' or
               t2.item_code         like '%{$_REQUEST["search"]}%' or
               t1.item_number       like '%{$_REQUEST["search"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
               tx.item_barcode      = '{$_REQUEST["search"]}'
            ) and
            (
               t5.cat_itemreg_machine_assign = 0 or
               (
                  t5.cat_itemreg_machine_assign = 1 and
                  (
                     select count(*)
                     from item_equipos_rel trel
                     where
                     trel.item_id   = t1.id and 
                     trel.equipo_id = {$headdata["sth_assign_equipoid"]}
                  ) > 0
               )
            )
            order by 2,7
            LIMIT 0, 200"; 
   $items  = $CON->select($sql);

   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      if($items[$x]["item_type"] == "item_typeI")
      {
         $avgcost = getItemAverageCost($CON, $headdata["stk_companyid"], $items[$x]["id"]);
         if($avgcost == false)
            $avgcost = getSupplierFinalCostNetto($CON, 0, $items[$x]["id"]);
            
         $items[$x]["item_type"] = "item";
      }
      else
      {
         $items[$x]["item_type"] = "itemlist";
         $avgcost = $items[$x]["item_costprice_netto"];
      }

      $shopprc    = getShopItemStorePrice($CON, $headdata["stk_shopid"], $items[$x]["id"], $items[$x]["item_type"]);
      $unitdesc   = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
      $desc       = trim(addslashes($items[$x]["item_title"]));
      ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)');
      newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=printPrice($avgcost)?>#<?=printPrice($items[$x]["item_costprice_taxes_perc"],2)?>#<?=(int)$items[$x]["item_charges_act"]?>#<?=printPrice($shopprc["itemshop_sellprice_netto"])?>';
      obj.options[newIndex] = newOpt;

      if(setX == -1)
      {
         obj.selectedIndex  = 0;
         setX = newIndex;
      }
      <?php
   }
   ?>
   if(setX != -1)
   {
      parent.setItemInfosStk(<?=$_REQUEST["rowcount"]?>, obj.options[setX].value);
   }
   <?php
}
?>
</script>