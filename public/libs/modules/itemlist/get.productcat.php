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

$sql_catid = sprintf("%03s", $_REQUEST["catid"]);

$sql = " select *
         from item
         where
         item_number_prod like '{$sql_catid}%' and
         item_status > 0
         order by item_number_prod asc";
$items = $CON->select($sql);

for($x = 0; $x < count($items) && $items != false; $x++)
{
   $inumb   = $items[$x]["item_number_prod"];
   $thisid  = (int)substr($inumb, 3);
   $_ITEMIDS[$thisid] = 1;
}

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

echo md5(microtime());
?>
<script language="JavaScript">
   parent.document.getElementById('cat_iframe').src = './libs/modules/productcats/list.php?tblmode=<?=$_REQUEST["tbl_suffix"]?>&catids=<?=$_REQUEST["catid"]?>';
   parent.document.js_item_form.item_catids.value = '<?=$_REQUEST["catid"]?>';
   
   var obj = parent.document.getElementById('item_number_prod2');
   obj.options.length = 1;

   <?php
   foreach(array_keys($_ITEMIDS) AS $newitemid)
   {  ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=sprintf("%04s",$newitemid)?>');
      newOpt.value = '<?=sprintf("%04s",$newitemid)?>';
      obj.options[newIndex] = newOpt;
      <?php
   }
   ?>
</script>