<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../classes/mysql.php");
require_once("../../config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
require_once("../../functions.php");

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
         t2.user_id = {$_SESSION["user_id"]} and
         t2.msg_type IN (0) and
         t2.msg_status IN (0)
         order by t1.msg_crtdat desc ";
$messages = $CON->select($sql);
if(!count($messages) || $messages == false)
   $msgcount = 0;
else
   $msgcount = count($messages);

//----------------------------------------------------------------------------------
if($msgcount > 0)
{  ?>
   <div style="position:relative">
   <div style='position:absolute;background-color:#3FA4D8;top:-21px;left:65px;text-align:center;padding-bottom:2px;padding-top:2px;padding-left:6px;padding-right:6px;border-radius:60px;font-family:Arial;font-size:10px;'>
      <blink><b><?=$msgcount?></b></blink>
   </div>
   <?php
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.user_firstname, t3.user_lastname, t2.msg_status, t2.msg_type
         from message_data t1, message_users t2, user t3
         where
         t1.id = t2.msg_id and
         t1.msg_crtusr = t3.id and
         t2.user_id = {$_SESSION["user_id"]} and
         t2.msg_type IN (0) and
         t2.msg_status IN (0) and
         t1.msg_popupact = 1
         order by t1.msg_crtdat desc ";
$messages = $CON->select($sql);
if(!count($messages) || $messages == false)
   $msgcount = 0;
else
   $msgcount = count($messages);

//----------------------------------------------------------------------------------
if($msgcount > 0)
{  ?>
   <script language="Javascript">
      if(!$('#idx_messagepop').length)
      {
         var htmlstr =  '<div style="position:fixed;bottom:0px;left:0px" id="idx_messagepop">';
         htmlstr = htmlstr + '<div style="background-color:#FFFFFF;border:3px solid red;width:214px;height:200px">';
         htmlstr = htmlstr + '<div style="text-align:center;height:20px;background-color:red;color:white;font-family:Arial;font-size:14px">';
         htmlstr = htmlstr + '<i class="fa fa-fw fa-exclamation-circle" style="color:white;font-size:12px"></i>';
         htmlstr = htmlstr + '<font style="-webkit-animation-name: xblink; -webkit-animation-iteration-count: infinite;-webkit-animation-timing-function: cubic-bezier(1.0,0,0,1.0);-webkit-animation-duration: 1s;">';
         htmlstr = htmlstr + '   MENSAJE URGENTE';
         htmlstr = htmlstr + '</font>';
         htmlstr = htmlstr + '<i class="fa fa-fw fa-exclamation-circle" style="color:white;font-size:12px"></i>';
         htmlstr = htmlstr + '</div>';
         htmlstr = htmlstr + '<div style="text-align:center;margin-top:30px;font-family:Arial;font-size:14px;color:#999999;">';
         htmlstr = htmlstr + '   <i class="fa fa-fw fa-refresh fa-spin" style="color:#999999;font-size:64px"></i>';
         htmlstr = htmlstr + '   <br>';
         htmlstr = htmlstr + '<div style="height:10px"></div>';
         htmlstr = htmlstr + '<font style="color:red">El mensaje se abre en</font><br>';
         htmlstr = htmlstr + '<center>';
         htmlstr = htmlstr + '<div style="height:10px"></div>';
         htmlstr = htmlstr + '<div id="idx_looptimer" style="font-weight:bold;border-radius:50%;font-size:16px;background-color:red;color:white;width:25px;height:25px;line-height:25px;text-align:center">';
         htmlstr = htmlstr + '5';
         htmlstr = htmlstr + '</div>';
         htmlstr = htmlstr + '</center>';

         htmlstr = htmlstr + '<script language="JavaScript">';
         htmlstr = htmlstr + 'curridx = 5;';
         htmlstr = htmlstr + 'function loopTimer()';
         htmlstr = htmlstr + '{';
         htmlstr = htmlstr + 'curridx--;';
         htmlstr = htmlstr + '$("#idx_looptimer").html(curridx);';
         htmlstr = htmlstr + 'if(curridx == 0)';
         htmlstr = htmlstr + '{';
         htmlstr = htmlstr + '   clearInterval(currint);';
         htmlstr = htmlstr + '   $("#idx_messagepop").fadeOut(300);';
         htmlstr = htmlstr + '   parent.frames["idx_main_app"].openMessagePopup(<?=$messages[0]["id"]?>);';
         htmlstr = htmlstr + '}';
         htmlstr = htmlstr + '}';
         htmlstr = htmlstr + 'currint = window.setInterval(loopTimer, 1000);';
         htmlstr = htmlstr + '<\/script>';

         htmlstr = htmlstr + '</div>';
         htmlstr = htmlstr + '</div>';
         htmlstr = htmlstr + '</div>';
         $('#idx_messagepop_wrapper').append(htmlstr);
      }
   </script>
   <div style="display:none"><?=md5(microtime())?></div>
   <?php
}
?>
