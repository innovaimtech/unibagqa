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
?>
<script language="JavaScript">

var obj = parent.document.getElementsByName('item_id<?php if($_REQUEST["rowcount"] != "") echo "_".$_REQUEST["rowcount"]?>')[0];
obj.options.length = 0;
<?php
$_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));

if($_REQUEST["search"] != "" || ($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] != ""))
{
   $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'I'
            from item t1
            LEFT OUTER JOIN item_suppliers t2 ON t1.id = t2.item_id
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
            where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
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
   $sql .= " UNION ALL
            select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'L'
            from itemlist t1
            LEFT OUTER JOIN itemlist_suppliers t2 ON t1.id = t2.item_id 
            where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            (
               t1.item_title        like '%{$_REQUEST["search"]}%' or
               t1.item_number       like '%{$_REQUEST["search"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
               t2.item_code         like '%{$_REQUEST["search"]}%'
            ) ";
   if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
      $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
   if(($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item") || $_REQUEST["itemsonly"] == 1)
      $sql .= " and 1 = 3 ";
   $sql .= "order by 2
            LIMIT 0, 200";
   $items = $CON->select($sql);

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
      newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=printPrice($items[$x]["item_sellprice_brutto"])?>';
      obj.options[newIndex] = newOpt;
      obj.selectedIndex  = 0;
      <?php
   }
}
?>
</script>