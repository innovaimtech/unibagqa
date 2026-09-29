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

$sql = " select t1.*, t3.user_firstname, t3.user_lastname, t2.msg_status, t2.msg_type
         from message_data t1, message_users t2, user t3
         where
         t1.id = t2.msg_id and
         t1.msg_crtusr = t3.id and
         t2.user_id = {$_REQUEST["uid"]} and
         t2.msg_type IN (0) and
         t2.msg_status IN (0)
         order by t1.msg_crtdat desc ";
$messages = $CON->select($sql);
if(!count($messages) || $messages == false)
   $msgcount = 0;
else
   $msgcount = count($messages);
   
//----------------------------------------------------------------------------------
?>
<div style="position:relative">
<div style="position:absolute;top:-9px;left:-28px">
<table border="0" cellpadding="0" cellspacing="0" id="idx_modetd" bgcolor="#666666" style='background-color:#FFFFFF;text-align:center;padding-bottom:2px;padding-top:2px;padding-left:6px;padding-right:6px;border-radius:25px;font-family:Arial;font-size:10px;'>
<tr>
   <td align="center" class="page_subheader" style="cursor:pointer" onclick="document.getElementById('incmsg_idx_data').src='ad.menu.php?idx_currentmid=7'"><?=$msgcount?></td>
</tr>
</table>
</div>
</div>
