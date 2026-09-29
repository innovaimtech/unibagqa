<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../classes/page.php");
require_once("../../../classes/mysql.php");
require_once("../../../config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../functions.php");

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

$_REQUEST["commtext"]   = trim(addslashes($_REQUEST["commtext"]));
$_REQUEST["relid"]      = (int)$_REQUEST["relid"];

if($_REQUEST["relmode"] == "invoices_buy")
{
   $sql = " update invoices_buy
            set
            invc_desc = '{$_REQUEST["commtext"]}'
            where
            id = {$_REQUEST["relid"]}";
   $CON->no_result($sql);
   echo "OK";
}
if($_REQUEST["relmode"] == "invoices_notes_buy")
{
   $sql = " update invoices_notes_buy
            set
            note_desc = '{$_REQUEST["commtext"]}'
            where
            id = {$_REQUEST["relid"]}";
   $CON->no_result($sql);
   echo "OK";
}