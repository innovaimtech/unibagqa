<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
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

$_REQUEST["xrut"]    = trim($_REQUEST["xrut"]);
$_REQUEST["custid"]  = (int)$_REQUEST["custid"];

$sql_rut = str_replace(".", "", $_REQUEST["xrut"]);
$sql = " select count(*) 'cc'
         from customer
         where
         cust_status > 0 and
         REPLACE(cust_rut,'.','') = '{$sql_rut}' ";
if((int)$_REQUEST["custid"])
   $sql .= " and id != {$_REQUEST["custid"]} ";
$check = $CON->select($sql);
$check = (int)$check[0]["cc"];
      
if($check)
{
   ?>
   <b class=msg_save_err><img src="/images/menu/icons/cross-circle-frame.png" style="vertical-align:bottom"> RUT existente en nuestros Registros</b>
   <?php
}
else
{  ?>
   <b class=msg_save_ok><img src="/images/menu/icons/tick-circle-frame.png" style="vertical-align:bottom"> RUT nuevo, Esperando Ingreso</b>
   <?php
}