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

$_REQUEST["brutto"]  = getPrice($_REQUEST["brutto"]);
$_REQUEST["brutto2"] = getPrice($_REQUEST["brutto2"]);
$_REQUEST["taxperc"] = (float)$_REQUEST["taxperc"];

$sql = " select *
         from price_lists
         where
         pl_status > 0";
$plists = $CON->select($sql);
foreach($plists AS $plist)
{
   $sql = " delete from price_lists_items
            where
            pl_id       = {$plist["id"]} and
            item_id     = {$_REQUEST["itemid"]} and
            item_type   = '{$_REQUEST["itemtype"]}'";
   $CON->no_result($sql);

   $item_brutto      = $_REQUEST["brutto"];
   $item_taxes_perc  = $_REQUEST["taxperc"];
   $item_taxes       = round($item_brutto / (100 + $item_taxes_perc) * $item_taxes_perc, 0);
   $item_netto       = $item_brutto - $item_taxes;
            
   $item_brutto2     = $_REQUEST["brutto2"];
   $item_taxes_perc2 = $_REQUEST["taxperc"];
   $item_taxes2      = round($item_brutto2 / (100 + $item_taxes_perc2) * $item_taxes_perc2, 0);
   $item_netto2      = $item_brutto2 - $item_taxes2;
   
   $sql = " insert into price_lists_items
            (pl_id, item_id, item_type,
            item_sellprice_brutto, item_sellprice_netto, item_sellprice_taxes, item_sellprice_taxes_perc,
            item_sellprice_brutto2, item_sellprice_netto2, item_sellprice_taxes2, item_sellprice_taxes_perc2)
            VALUES
            ({$plist["id"]}, {$_REQUEST["itemid"]}, '{$_REQUEST["itemtype"]}',
             {$item_brutto}, {$item_netto}, {$item_taxes}, {$item_taxes_perc},
             {$item_brutto2}, {$item_netto2}, {$item_taxes2}, {$item_taxes_perc2})";
   $CON->no_result($sql);
}
?>
<script language="JavaScript">
   parent.document.getElementById('btn_<?=$_REQUEST["itemtype"]?>_<?=$_REQUEST["itemid"]?>').style.backgroundColor='#A8E0FF';
</script>