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

// echo md5(microtime());


?>
<script language="JavaScript">

var obj = parent.document.getElementsByName('dlv_order_id')[0];
obj.options.length = 0;
<?php
$_REQUEST["search"] = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["search"]))));

if($_REQUEST["company_id"] != "" &&  $_REQUEST["shop_id"] != "")
{

   $sql = "select t1.dlv_num, t2.cust_name, t1.dlv_docnum, t1.dlv_delivery_date, t1.dlv_crtdat, t1.dlv_status, t1.id, t9.req_number, t10.invc_docnumber
            from orders_delivery t1
             inner join invoices_sell t10 on t1.dlv_num = t10.invc_number
             inner JOIN customer t2 ON t1.dlv_cust_id = t2.id 
             LEFT OUTER JOIN orders t9 ON t1.dlv_order_id = t9.id 
           where invc_status > 0  and invc_sgntr_end = 1 and
              t1.dlv_company_id      = {$_REQUEST["company_id"]} and
              t1.dlv_shop_id         = {$_REQUEST["shop_id"]} and
            (
               t10.invc_docnumber  like '%{$_REQUEST["search"]}%' or
               t2.cust_name       like '%{$_REQUEST["search"]}%' or
               t2.cust_company    like '%{$_REQUEST["search"]}%' or
               REPLACE(t2.cust_rut,'.','') like '{$_REQUEST["search"]}%'
            )
            order by t1.id desc ";

   $orders  = $CON->select($sql);

   for($x = 0; $x < count($orders) && $orders != false; $x++)
   {
      $desc = trim(addslashes($orders[$x]["invc_docnumber"]." - ".$orders[$x]["dlv_num"]." - ".$orders[$x]["cust_name"]));
      ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$desc?>');
      newOpt.value = '<?=$orders[$x]["id"]?>';
      obj.options[newIndex] = newOpt;
      <?php
      if($_REQUEST["fromdlv"] == "1" && !$x)
      {  ?>
         parent.checkOpenInvcSell('<?=$orders[$x]["id"]?>');
         <?php
      }
   }
   
}
?>
</script>