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
$_REQUEST["newcostprc"] = getPrice($_REQUEST["newcostprc"],4);
$_REQUEST["newamt"]     = getPrice($_REQUEST["newamt"],4);
$_REQUEST["company_id"] = (int)$_REQUEST["company_id"];
$_REQUEST["itemid"]     = (int)$_REQUEST["itemid"];
$_REQUEST["stamt"]      = (float)$_REQUEST["stamt"];

$sql = " select *
         from tran_average_costprices
         where
         item_id     = {$_REQUEST["itemid"]} and
         company_id  = {$_REQUEST["company_id"]}";
$chist = $CON->select($sql);
$cc = (int)$chist[0]["item_id"];

//----------------------------------------------------------------------------------
if($cc)
{

   if($_REQUEST["newamt"] < $_REQUEST["stamt"])
   {
      $costtotal = $chist[0]["item_costprice_avg_netto"] * $_REQUEST["stamt"];
      $costtotal = $costtotal - ($_REQUEST["newamt"] * $chist[0]["item_costprice_avg_netto"]);
      $costtotal = $costtotal + ($_REQUEST["newamt"] * $_REQUEST["newcostprc"]);
      $_REQUEST["newcostprc"] = sprintf("%.4f",$costtotal / $_REQUEST["stamt"]);
   }
   $sql = " update tran_average_costprices
            set
            item_costprice_avg_netto = {$_REQUEST["newcostprc"]}
            where
            item_id     = {$_REQUEST["itemid"]} and
            company_id  = {$_REQUEST["company_id"]}";
   $CON->no_result($sql);

   $currtme = time();
   $sql = " insert into tran_average_costprices_log
            (item_id, company_id, log_crtdat, tran_type, tran_id, item_costprice_avg_netto)
            VALUES
            ({$_REQUEST["itemid"]}, {$_REQUEST["company_id"]}, {$currtme}, 'manual', 0, {$_REQUEST["newcostprc"]})";
   $CON->no_result($sql);
}
else
{
   $sql = " insert into tran_average_costprices
            (item_id, company_id, item_costprice_avg_netto)
            VALUES
            ({$_REQUEST["itemid"]}, {$_REQUEST["company_id"]}, {$_REQUEST["newcostprc"]})";
   $CON->no_result($sql);

   $currtme = time();
   $sql = " insert into tran_average_costprices_log
            (item_id, company_id, log_crtdat, tran_type, tran_id, item_costprice_avg_netto)
            VALUES
            ({$_REQUEST["itemid"]}, {$_REQUEST["company_id"]}, {$currtme}, 'manual', 0, {$_REQUEST["newcostprc"]})";
   $CON->no_result($sql);
}
echo printPrice($_REQUEST["newcostprc"] * $_REQUEST["stamt"]);
?>
