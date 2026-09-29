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
<?php
if($_REQUEST["rowcount"] != "")
{  ?>
var obj = parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0];
   obj.options.length = 0;
   <?php
}
$_REQUEST["search"] = trim($_REQUEST["search"]);

if($_REQUEST["rowcount"] != "" &&  $_REQUEST["search"] != "")
{
   $sql = " select distinct t1.id, t1.item_title
            from item t1
            where
            t1.item_status = 1 and
            (
               t1.item_title        like '%{$_REQUEST["search"]}%' or
               t1.item_number       like '%{$_REQUEST["search"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["search"]}%'
            )
            order by t1.item_title";
   $items  = $CON->select($sql);

   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      $desc = trim(addslashes($items[$x]["item_title"]));
      ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$desc?>');
      newOpt.value = '<?=$items[$x]["id"]?>';
      obj.options[newIndex] = newOpt;
      <?php
   }
   
}
?>
</script>
<?=$sql?>