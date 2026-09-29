<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);


//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
$itemdata = explode("#", $_REQUEST["itemid"]);

$stockComp   = getItemStockComp($CON, $_REQUEST["req_shop_id"],$itemdata[0],$itemdata[1]);
$stock       = getItemShopCurrentStock($CON, $_REQUEST["req_shop_id"], $itemdata[0], $itemdata[1], true, 2);
$shareamount = getStockShared($CON, $itemdata[0], $itemdata[1], $_REQUEST["req_shop_id"]);

printFancyBoxStock($CON, $_REQUEST["req_shop_id"], $itemdata[0], $itemdata[1], "storehousestock", 2);
echo "#!#!#!#!";
printFancyBoxStock($CON, $_REQUEST["req_shop_id"], $itemdata[0], $itemdata[1], "transstock");
echo "#!#!#!#!";
echo printPrice($stockComp, 2);
echo "#!#!#!#!";
echo printPrice($shareamount, 2);
echo "#!#!#!#!";
echo printPrice($stock - $shareamount - $stockComp, 2);
echo "#!#!#!#!";

?>
