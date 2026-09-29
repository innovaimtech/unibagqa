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

$sql_catid  = sprintf("%03s", $_REQUEST["catid"]);
$sql_numid  = sprintf("%04s", $_REQUEST["itemnumber"]);

$sql = " select t1.id, t1.item_title, t4.unit_name, t4.unit_desc
         from item t1
         LEFT OUTER JOIN item_units t4 ON t1.item_unit = t4.id
         where
         t1.item_number_prod = '{$sql_catid}{$sql_numid}' and
         t1.item_status > 0";
$item = $CON->select($sql);
$item = $item[0];

header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

echo md5(microtime());
?>
<script language="JavaScript">
   
   var obj1 = parent.document.getElementById('idx_item_name');
   var obj2 = parent.document.getElementById('idx_item_unit');
   var obj3 = parent.document.getElementById('component_item_id');

   obj1.innerHTML = '- - -';
   obj2.innerHTML = '- - -';
   obj3.value     = '';

   <?php
   if((int)$item["id"])
   {  ?>
      obj1.innerHTML = '<?=trim(addslashes($item["item_title"]))?>';
      obj2.innerHTML = '<?=trim(addslashes("{$item["unit_name"]} - {$item["unit_desc"]}"))?>';
      obj3.value     = '<?=$item["id"]?>';
      <?php
   }
   ?>
</script>