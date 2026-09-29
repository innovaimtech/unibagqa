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

var obj = parent.document.getElementsByName('order_id')[0];
obj.options.length = 0;

var newIndex = obj.options.length;
var newOpt = new Option('< Por favor seleccione >');
newOpt.value = '0';
obj.options[newIndex] = newOpt;
<?php
$_REQUEST["search"] = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["search"]))));

if($_REQUEST["company_id"] != "" &&  $_REQUEST["shop_id"] != "")
{
   $sql = " select t1.id, t1.req_number, t2.cust_name
            from orders t1
            LEFT OUTER JOIN customer t2 ON t1.req_cust_id = t2.id
            where
            t1.req_company_id      = {$_REQUEST["company_id"]} and
            t1.req_shop_id         = {$_REQUEST["shop_id"]} and
            t1.req_status          IN (2,3) and
            t1.req_order_shipped   = 0 and
            (
               t1.req_number     like '%{$_REQUEST["search"]}%' or
               t2.cust_name      like '%{$_REQUEST["search"]}%' or
               t2.cust_company   like '%{$_REQUEST["search"]}%' or
               REPLACE(t2.cust_rut,'.','') like '{$_REQUEST["search"]}%'
            ) and
            (
               select count(t1x.req_id_2) 'cc'
               from orders_rels t1x
               INNER JOIN orders t2x ON t1x.req_id_2 = t2x.id
               where
               t1x.req_id_1 = t1.id and
               t2x.req_status > 0
            ) = 0
            ";
   if((int)$_REQUEST["cust_id"])
      $sql .= " and t1.req_cust_id = {$_REQUEST["cust_id"]} ";
   $sql .= " order by t1.req_number desc
             LIMIT 0,100";
   $orders  = $CON->select($sql);

   for($x = 0; $x < count($orders) && $orders != false; $x++)
   {
      $desc = trim(addslashes($orders[$x]["req_number"]." - ".$orders[$x]["cust_name"]));
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