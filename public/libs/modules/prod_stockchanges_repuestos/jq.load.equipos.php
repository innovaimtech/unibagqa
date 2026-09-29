<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2021 by 1BIT LTDA. All Rights Reserved.
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

//----------------------------------------------------------------------------------
header ('Last-Modified: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Expires: '.gmdate("D, d M Y H:i:s").' GMT');
header ('Cache-Control: no-cache, must-revalidate');
header ('Pragma: no-cache');

$_REQUEST["plantaid"] = (int)$_REQUEST["plantaid"];

$sql = " select *
         from equipo
         where
         equipo_status     > 0 and
         equipo_planta_id  = {$_REQUEST["plantaid"]}
         order by equipo_name";
$equipos = $CON->select($sql);

?>
<option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
<?php
foreach($equipos AS $equipo)
{  ?>
   <option value="<?=$equipo["id"]?>"><?=$equipo["equipo_name"]?></option>
   <?php
}