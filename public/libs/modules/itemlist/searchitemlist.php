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
var obj = parent.document.getElementsByName('item_ext_prod_item_id<?php if($_REQUEST["rowcount"] != "") echo "_".$_REQUEST["rowcount"]?>')[0];
var selFirst  = 0;
if(obj.style.display == '')
{
   obj.options.length = 0;
   <?php
   $_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));

   if($_REQUEST["search"] != "")
   {
      $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod
               from itemlist t1
               LEFT OUTER JOIN itemlist_suppliers t2 ON t1.id        = t2.item_id
               where
               t1.item_status       = 1 and
               t1.item_released     = 1 and
               t1.id               != {$_REQUEST["id"]} and
               (
                  t1.item_title        like '%{$_REQUEST["search"]}%' or
                  t1.item_number       like '%{$_REQUEST["search"]}%' or
                  t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
                  t2.item_code         like '%{$_REQUEST["search"]}%'
               ) ";
      $sql .= " order by 2";
      $items = $CON->select($sql);

      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], "item");
         $desc          = trim(addslashes($items[$x]["item_title"]));

         ?>
         var newIndex = obj.options.length;
         var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)');
         newOpt.value = '<?=$items[$x]["id"]?>';
         obj.options[newIndex] = newOpt;
         if(selFirst == 0)
         {
            obj.selectedIndex = 0; selFirst = 1;
         }
         <?php
      }
   }
   ?>
}
</script>