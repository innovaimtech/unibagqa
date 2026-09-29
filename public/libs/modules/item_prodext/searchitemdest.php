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
         from prod_item_ext
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

print_r($_REQUEST);
?>
<script language="JavaScript">
var obj     = parent.document.getElementsByName('item_iddest_<?=$_REQUEST["rowcount"]?>')[0];

if(obj.style.display == '')
{
   obj.options.length = 0;
   <?php
   $_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));

   if($_REQUEST["rowcount"] != "" && ($_REQUEST["search"] != "" || ($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] != "")))
   {
      $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'I'
               from item t1
               LEFT OUTER JOIN item_suppliers t2   ON ( t1.id = t2.item_id )
               INNER JOIN item_shops t3            ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["req_shop_id"]} )
               where
               t1.item_status       = 1 and
               t1.item_released     = 1  ";
               
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
         $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
         $sql .= " and 1 = 3 ";
         
      $sql .= "UNION ALL
               select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'L'
               from itemlist t1
               LEFT OUTER JOIN itemlist_suppliers t2     ON ( t1.id = t2.item_id )
               INNER JOIN itemlist_shops t3              ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["req_shop_id"]} )
               where
               t1.item_status       = 1 and
               t1.item_released     = 1  ";
               
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
         //$destunitdesc  = getItemUnitDesc($CON, $items[$x]["iddest"], $items[$x]["item_type"]);
         $desc          = trim(addslashes($items[$x]["item_title"]));
         //$destdesc      = trim(addslashes($items[$x]["item_titledest"]));
         ?>
         var newIndex = obj.options.length;
         var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)');
         newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=$items[$x]["destid"]?>';
         obj.options[newIndex] = newOpt;
         <?php
         if($x == 0)
         {  ?>
            obj.selectedIndex  = 0;
            parent.updateItemStorehousesDest('<?=$_REQUEST["rowcount"]?>', <?=$items[$x]["id"]?>, '<?=$items[$x]["item_type"]?>');
            <?php
         }
      }
      
   }
   ?>
}
</script>