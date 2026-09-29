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

//----------------------------------------------------------------------------------
$_REQUEST["agid"]    = (int)$_REQUEST["agid"];
$_REQUEST["active"]  = (int)$_REQUEST["active"];

$sql = " select t0.*
         from prod_agenda t0
         where
         t0.id = {$_REQUEST["agid"]}";
$agdata = $CON->select($sql);
$agdata = $agdata[0];

//----------------------------------------------------------------------------------
$sql = " update prod_agenda
         set
         ag_active = 0
         where
         id             != {$_REQUEST["agid"]} and
         ag_plantaid    = {$agdata["ag_plantaid"]} and
         ag_active      = 1 and
         ag_equipo_id   = {$agdata["ag_equipo_id"]} ";
$CON->no_result($sql);

//----------------------------------------------------------------------------------
$sql = " update prod_agenda
         set
         ag_active = {$_REQUEST["active"]}
         where
         id = {$_REQUEST["agid"]}";
$CON->no_result($sql);
?>
<div style="display:none"><?=md5(microtime())?></div>
<script language="JavaScript">
   $('.clstd_<?=$agdata["ag_equipo_id"]?>').css({'background-color':'#00A9A6'});
   <?php
   if((int)$_REQUEST["active"])
   {  ?>
      $('#idx_agitem_<?=$_REQUEST["agid"]?>').css({'background-color':'#6EBE6C'});
      $('.clschk_<?=$agdata["ag_equipo_id"]?>').not('.clsthischk_<?=$_REQUEST["agid"]?>').attr('checked', false);
      <?php
   }
   else
   {  ?>
      $('#idx_agitem_<?=$_REQUEST["agid"]?>').css({'background-color':'#00A9A6'});
      $('.clschk_<?=$agdata["ag_equipo_id"]?>').attr('checked', false);
      <?php
   }
   ?>
</script>
<?php