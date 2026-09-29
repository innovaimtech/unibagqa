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

$sql = " select distinct t1.id, t1.sord_number, t1.sord_crtdat
         from supplier_order t1
         where
         t1.sord_status       = 1 and
         t1.sord_supplier_id  = {$_REQUEST["suppid"]} and
         t1.sord_shop_id      = {$_REQUEST["shopid"]}
         order by 1 desc";
$openorders = $CON->select($sql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($openorders) && $openorders != false; $x++)
{
   $row = $openorders[$x];
   ?>
   <option value="<?=$row["id"]?>">AGREGAR A: <?=$row["sord_number"]?> - <?=date('d.m.Y', $row["sord_crtdat"])?></option>
   <?php
}
?>
<option value="0">CREAR NUEVA ORDEN DE COMPRA</option>
