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
         from stockchanges
         where
         id = {$_REQUEST["stkid"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

?>
<script language="JavaScript">
<?php
if($_REQUEST["rowcount"] != "")
{  ?>
   var obj = parent.document.getElementsByName('item_stid_<?=$_REQUEST["rowcount"]?>')[0];
   obj.options.length = 0;
   <?php
}
//----------------------------------------------------------------------------------
if($_REQUEST["rowcount"] != "")
{
   //----------------------------------------------------------------------------------
   $sql = " select t2.id, t2.st_name
            from company_shops_storehouses t2 
            where
            t2.st_status = 1 and
            t2.st_shop_id = {$headdata["stk_shopid"]} and
            t2.st_repuestos_act = 1
            order by t2.st_name";
   $itemstsels = $CON->select($sql);
   foreach($itemstsels AS $itemstsel)
      $itemsts[$itemstsel["id"]] = $itemstsel["st_name"];
   
   if(count($itemsts))
   {
      foreach(array_keys($itemsts) AS $itemstid)
      {
         if($itemstid == $headdata["stk_fixedsthid"] || !(int)$headdata["stk_fixedsthid"])
         {
            $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"], $itemstid, $_REQUEST["itemid"], $_REQUEST["itemtype"], true);
            ?>
            var newIndex            = obj.options.length;
            var newOpt              = new Option('<?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)');
            newOpt.value            = '<?=$itemstid?>';
            obj.options[newIndex]   = newOpt;
            <?php
         }
      }
   }
}
?>
</script>