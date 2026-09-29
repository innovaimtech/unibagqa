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
$_REQUEST["equipotype_id"] = (int)$_REQUEST["equipotype_id"];
$_REQUEST["plan_date"]     = trim($_REQUEST["plan_date"]);
$_REQUEST["equipo_id"]     = (int)$_REQUEST["equipo_id"];
$_REQUEST["plan_amount"]   = getPrice(trim($_REQUEST["plan_amount"]));
$_REQUEST["prdid"]         = (int)$_REQUEST["prdid"];
$_REQUEST["reqid"]         = (int)$_REQUEST["reqid"];
$_REQUEST["plantaid"]      = (int)$_REQUEST["plantaid"];
$ag_date_stamp             = explode(".", $_REQUEST["plan_date"]);
$ag_date_stamp             = mktime(15, 0, 0, $ag_date_stamp[1], $ag_date_stamp[0], $ag_date_stamp[2]);
$ag_crtdat                 = time();
$ag_crtusr                 = (int)$_SESSION["user_id"];

//----------------------------------------------------------------------------------
$sql = " insert into prod_agenda
         (ag_date, ag_date_stamp, ag_equipo_id, ag_equipotype_id, ag_amount, ag_prdid, ag_reqid, ag_crtdat, ag_crtusr, ag_plantaid)
         VALUES
         ('{$_REQUEST["plan_date"]}', {$ag_date_stamp}, {$_REQUEST["equipo_id"]}, {$_REQUEST["equipotype_id"]},
          {$_REQUEST["plan_amount"]}, {$_REQUEST["prdid"]}, {$_REQUEST["reqid"]}, {$ag_crtdat}, {$ag_crtusr},
          {$_REQUEST["plantaid"]})";
$res = $CON->no_result($sql);
?>
<div style="display:none"><?=md5(microtime())?></div>
<script language="JavaScript">
   $('#idxequipotype_<?=$_REQUEST["equipotype_id"]?>').css({'background-color':'#999999'});
   $('#idxamount_<?=$_REQUEST["equipotype_id"]?>').css({'background-color':'#CCCCCC'});

   var tdtarget = $('#droptarget_<?=str_replace(".", "\\\\.", $_REQUEST["plan_date"])?>_<?=$_REQUEST["equipo_id"]?>_<?=$_REQUEST["equipotype_id"]?>');
   var tddivtxt = '<div style="background-color:#80D689;border:1px solid #007472;margin-bottom:3px;color:white;padding:4px;padding-left:6px;padding-right:6px;text-shadow:none">';
   tddivtxt = tddivtxt +'<b>Agendado</b>';
   tddivtxt = tddivtxt +'</div>';
   tdtarget.append(tddivtxt);
</script>