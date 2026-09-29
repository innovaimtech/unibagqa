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

$sql = " select *
         from productcats
         where
         id = {$_REQUEST["catid"]}";
$catdata = $CON->select($sql);
$catdata = $catdata[0];

$sql_catid = (int)$_REQUEST["catid"];
$sql = " select t1.*, t2.cat_prefix
         from item t1
         INNER JOIN item_productcats t3   ON t1.id = t3.item_id
         INNER JOIN productcats t2        ON t3.cat_id = t2.id
         where
         t3.cat_id = {$sql_catid} and
         t1.item_number_prod like '{$catdata["cat_prefix"]}%'
         order by t1.id";
$items = $CON->select($sql);
$maxid = 1;
for($x = 0; $x < count($items) && $items != false; $x++)
{
   $inumb   = $items[$x]["item_number_prod"];
   $thisid  = str_replace($items[$x]["cat_prefix"],"",$inumb);

   if($thisid +1 >= $maxid)
      $maxid = $thisid +1;

   if(!(int)$items[$x]["item_status"])
      $_ITEMIDS[$thisid] = 1;
   else
      $_ITEMUSD[$thisid] = 1;
   
}
$_ITEMIDS[$maxid] = 1;


header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

echo md5(microtime());
?>
<script language="JavaScript">
   //parent.document.getElementById('cat_iframe').src = './libs/modules/productcats/list.php?tblmode=<?=$_REQUEST["tbl_suffix"]?>&catids=<?=$_REQUEST["catid"]?>';
   //parent.document.js_item_form.item_catids.value = '<?=$_REQUEST["catid"]?>';
   
   var obj = parent.document.getElementById('item_number_prod');

   //obj.options.length = 1;

   <?php
   foreach(array_keys($_ITEMIDS) AS $newitemid)
   {
      if(!(int)$_ITEMUSD[$newitemid])
      {  ?>
         obj.value = '<?=$catdata["cat_prefix"]?><?=sprintf("%04s",$newitemid)?>';
         <?php
      }
   }
   ?>
</script>