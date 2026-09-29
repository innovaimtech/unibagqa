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
{
   if($_REQUEST["destobj"] != "")
   {  ?>
      var obj = parent.document.getElementsByName('<?=$_REQUEST["destobj"]?>')[0];
      obj.options.length = 0;
      <?php
   }
   else
   {  ?>
      var obj = parent.document.getElementsByName('cust_id_<?=$_REQUEST["rowcount"]?>')[0];
      obj.options.length = 0;
      <?php
   }
}

$_REQUEST["search"] = trim(addslashes(str_replace("*","%", $_REQUEST["search"])));

if($_REQUEST["rowcount"] != "" && ($_REQUEST["search"] != "" || $_REQUEST["custid"] != ""))
{
   $sql = " select t1.*
            from customer t1
            where
            t1.cust_status = 1 and
            (
               t1.cust_company like '%{$_REQUEST["search"]}%' or
               t1.cust_name    like '%{$_REQUEST["search"]}%' or
               REPLACE(t1.cust_rut,'.','') like '{$_REQUEST["search"]}%'
            ) ";
   if($_REQUEST["custid"] != "")
      $sql .= " and t1.id = {$_REQUEST["custid"]} ";
   $sql .= " order by t1.cust_company, t1.cust_rut";
   $customers  = $CON->select($sql);

   for($x = 0; $x < count($customers) && $customers != false; $x++)
   {
      $desc = trim(addslashes($customers[$x]["cust_name"]));
      ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$desc?>');
      newOpt.value = '<?=$customers[$x]["id"]?>';
      obj.options[newIndex] = newOpt;
      <?php
      if($_REQUEST["fromdlv"] == "1" && !$x)
      {  ?>
         parent.checkOpenInvcNotes('<?=$customers[$x]["id"]?>');
         <?php
      }
      if((int)$_REQUEST["setOrders"] && !$x)
      {  ?>
         parent.loadRelOrders('<?=$customers[$x]["id"]?>');
         <?php
      }
   }
   
}
?>
</script>
