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

//----------------------------------------------------------------------------------
$sql = " select *
         from storehousechanges
         where
         id = {$_REQUEST["strcid"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$_RSPSTHIDRET     = getUserRespSthids($CON);
$_RSPSTHIDS       = $_RSPSTHIDRET["_RSPSTHIDS"];
$_RSPSTHIDS_SQL   = $_RSPSTHIDRET["_RSPSTHIDS_SQL"];

?>
<script language="JavaScript">
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["rowcount"] != "")
{
   ?>
var obj1 = parent.document.getElementsByName('item_stid_<?=$_REQUEST["rowcount"]?>')[0];
var obj2 = parent.document.getElementsByName('item_stid_dest_<?=$_REQUEST["rowcount"]?>')[0];
   obj1.options.length = 0;
   obj2.options.length = 0;
   <?php
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["itemtype"] == "item")
      $itemsts = getItemStorehouses($CON, $headdata["strc_shop_id"], $_REQUEST["itemid"], $_REQUEST["itemtype"]);
   else
   {
      $itemlistpos = getItemListContent($CON, $_REQUEST["itemid"]);
      $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_id"], $itemlistpos[0]["item_id"], "item");
   }
   if(count($itemsts))
   {
      foreach(array_keys($itemsts) AS $itemstid)
      {
         if($headdata["strc_status"] > 1 || ($headdata["strc_status"] == 1 && (int)$_RSPSTHIDS[$itemstid]))
         {
            $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_id"], $itemstid,
                                                           $_REQUEST["itemid"], $_REQUEST["itemtype"], true,
                                                           (int)$headdata["strc_order_id"]);
            ?>
            var newIndex            = obj1.options.length;
            var newOpt              = new Option('<?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)');
            newOpt.value            = '<?=$itemstid?>';
            obj1.options[newIndex]  = newOpt;
            <?php
         }
      }
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["itemtype"] == "item")
      $itemsts = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $_REQUEST["itemid"], $_REQUEST["itemtype"]);
   else
   {
      $itemlistpos = getItemListContent($CON, $_REQUEST["itemid"]);
      $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $itemlistpos[0]["item_id"], "item");
   }
   if(count($itemsts))
   {
      foreach(array_keys($itemsts) AS $itemstid)
      {
         $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_dest_id"], $itemstid,
                                                       $_REQUEST["itemid"], $_REQUEST["itemtype"], true,
                                                        (int)$headdata["strc_order_id_dest"]);
         ?>
         var newIndex            = obj2.options.length;
         var newOpt              = new Option('<?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)');
         newOpt.value            = '<?=$itemstid?>';
         obj2.options[newIndex]  = newOpt;
         <?php
      }
   }
}
?>
</script>