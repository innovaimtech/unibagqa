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
   
?>
<script language="JavaScript">
   var idx_timer = -1;
   var idx_set   = 1;
   var idx_x     = 1;
   function Timer()
   {
      idx_set=1;
      if(idx_x==0 && idx_set==1)
      {
         document.getElementById('idx_modetd').bgColor='#F05151';
         idx_x=1;
         idx_set=0;
      }
      if(idx_x==1 && idx_set==1)
      {
         document.getElementById('idx_modetd').bgColor='#259492';
         idx_x=0;
         idx_set=0;
      }
   }
</script>
<table border="0" cellpadding="0" cellspacing="0" width="100" id="idx_modetd" bgcolor="#259492" style="border:1px solid #259492">
<tr>
   <td class="page_subheader" style="cursor:pointer" onclick="document.getElementById('idx_frame_content').src='index.php?mid=797'" width="25" align="center"><img src="./images/menu/icons/mail.png" style="margin-top:2px"></td>
   <td class="page_subheader" style="cursor:pointer" onclick="document.getElementById('idx_frame_content').src='index.php?mid=797'"><?=$msgcount?> Mensajes</td>
</tr>
</table>
<script language="JavaScript">
<?php
if($msgcount)
{  ?>
   clearInterval(idx_timer);
   idx_timer = setInterval("Timer()", 500);
   <?php
}
else
{  ?>
   clearInterval(idx_timer);
   document.getElementById('idx_modetd').bgColor = '#259492';
   <?php
}
?>
</script>