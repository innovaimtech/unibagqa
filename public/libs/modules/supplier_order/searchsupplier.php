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
      var obj = parent.document.getElementsByName('supplier_id_<?=$_REQUEST["rowcount"]?>')[0];
      obj.options.length = 0;
      <?php
   }
}

$_REQUEST["search"] = trim(addslashes(str_replace("*","%",str_replace(".","",$_REQUEST["search"]))));

if($_REQUEST["rowcount"] != "" && ($_REQUEST["search"] != "" || $_REQUEST["suppid"] != ""))
{
   $sql = " select t1.*
            from supplier t1
            where
            t1.supp_status = 1 and
            (
               t1.supp_company like '%{$_REQUEST["search"]}%' or
               t1.supp_short   like '%{$_REQUEST["search"]}%' or
               REPLACE(t1.supp_rut,'.','') like '{$_REQUEST["search"]}%'
            ) ";
            
   if($_REQUEST["suppid"] != "")
      $sql .= " and t1.id = {$_REQUEST["suppid"]} ";

   $sql .= " order by t1.supp_company, t1.supp_rut";
   $suppliers  = $CON->select($sql);

   for($x = 0; $x < count($suppliers) && $suppliers != false; $x++)
   {
      $desc = trim(addslashes($suppliers[$x]["supp_company"]));
      ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$desc?>');
      newOpt.value = '<?=$suppliers[$x]["id"]?>';
      obj.options[newIndex] = newOpt;
      <?php
   }
   
}
?>
parent.updateSupplierData(obj.options[0].value);
</script>
