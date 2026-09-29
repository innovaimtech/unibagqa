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

//----------------------------------------------------------------------------------
$sql = " select t1.*,t3.company_short, t4.shop_name, t7.item_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from stockcounts t1
         LEFT OUTER JOIN company_data t3  ON t1.stc_companyid    = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.stc_shopid       = t4.id
         LEFT OUTER JOIN user t5          ON t1.stc_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.stc_crtusr       = t6.id
         LEFT OUTER JOIN item t7          ON t1.stc_itemid       = t7.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));
echo md5(microtime());

?>
<script language="JavaScript">
var obj = parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0];
obj.options.length = 0;
<?php
if($_REQUEST["rowcount"] != "" &&  $_REQUEST["search"] != "")
{
   //----------------------------------------------------------------------------------
   $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod
            from item t1
            INNER JOIN item_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["stc_shopid"]} )
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
            where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            (
               t1.item_title        like '%{$_REQUEST["search"]}%' or
               t1.item_number       like '%{$_REQUEST["search"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
               tx.item_barcode      = '{$_REQUEST["search"]}'
            )
            order by 2
            LIMIT 0, 200";
   $items  = $CON->select($sql);

   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      $desc = trim(addslashes($items[$x]["item_title"]));
      ?>
      var newIndex = obj.options.length;
      var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?>');
      newOpt.value = '<?=$items[$x]["id"]?>';
      obj.options[newIndex] = newOpt;
      <?php
   }
   ?>
   <?php
}
?>
</script>