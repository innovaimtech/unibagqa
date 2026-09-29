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

var obj = parent.document.getElementsByName('shp_supporder_id')[0];
obj.options.length = 0;
<?php
$_REQUEST["search"] = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["search"]))));

if($_REQUEST["company_id"] != "" &&  $_REQUEST["shop_id"] != "")
{
   $sql = " select t1.id, t1.sord_number, t2.supp_company
            from supplier_order t1
            LEFT OUTER JOIN supplier t2 ON t1.sord_supplier_id = t2.id
            where
            t1.sord_company_id      = {$_REQUEST["company_id"]} and
            t1.sord_shop_id         = {$_REQUEST["shop_id"]} and
            t1.sord_status          IN (2,3) and
            t1.sord_order_shipped   = 0 and
            (
               t1.sord_number    like '%{$_REQUEST["search"]}%' or
               t1.sord_title     like '%{$_REQUEST["search"]}%' or
               t1.sord_desc      like '%{$_REQUEST["search"]}%' or
               t2.supp_company   like '%{$_REQUEST["search"]}%' or
               REPLACE(t2.supp_rut,'.','') like '{$_REQUEST["search"]}%'
            )
            order by t1.sord_number desc";
   $orders  = $CON->select($sql);

   for($x = 0; $x < count($orders) && $orders != false; $x++)
   {
      $desc = trim(addslashes($orders[$x]["sord_number"]." - ".$orders[$x]["supp_company"]));
      ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$desc?>');
      newOpt.value = '<?=$orders[$x]["id"]?>';
      obj.options[newIndex] = newOpt;
      <?php
   }
   
}
?>
</script>