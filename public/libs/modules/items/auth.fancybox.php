<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2018 by 1BIT LTDA. All Rights Reserved.
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

//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript">
      <?php
      require_once("../../../libs/jscripts/sourcen.php");
      ?>
      function loginExec()
      {
         if(checkform(new Array(document.xform_log.xuser_login, document.xform_log.xuser_pass)))
         {
            document.xform_log.user_login.value    = document.xform_log.xuser_login.value;
            document.xform_log.user_pass.value     = document.xform_log.xuser_pass.value;
            document.xform_log.xuser_login.value   = '';
            document.xform_log.xuser_pass.value    = '';
            document.xform_log.xuser_pass.type     ='text';
            return true;
         }
         return false;
      }
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="document.xform_log.xuser_login.focus()">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "login")
{
   $_REQUEST["user_pass"]  = md5($_REQUEST["user_pass"]);
   $_REQUEST["user_login"] = trim(addslashes($_REQUEST["user_login"]));
   
   $sql = " select count(*) 'cc'
            from user
            where
            user_status          > 0 and
            user_type            = 1 and
            user_login           = '{$_REQUEST["user_login"]}' and
            user_pass            = '{$_REQUEST["user_pass"]}'";
   $auth = $CON->select($sql);
   $auth = (int)$auth[0]["cc"];

   if($auth)
   {  ?>
      <script language="JavaScript">
         parent.location.href = '/index.php?mid=<?=$_REQUEST["mid"]?>&exec=del&id=<?=$_REQUEST["id"]?>';
      </script>
      <?php
   }
   else
   {  ?>
      <script language="JavaScript">
         alert('USUARIO NO VALIDO.');
      </script>
      <?php
   }
   
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
<tr>
   <td align="center" valign="top" height="100%">
      <form action="auth.fancybox.php" method="post" class="fokusfirst" name="xform_log" autocomplete="off"
      onsubmit="return loginExec()">
      <input type="hidden" name="exec" value="login">
      <input type="hidden" name="frmname" value="<?=$_REQUEST["frmname"]?>">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input name="user_login" id="user_login" type="text" style="display:none" value="">
      <input name="user_pass" id="user_pass" type="text" style="display:none" value="">
      <br>
      <?=Nifty_printH("box1", "350")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header" colspan="2">Credenciales administrativas</td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["LOGIN"][2]?></td>
         <td class="content_row">
            <input name="xuser_login" type="text" class="text" style="width:180px"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["LOGIN"][3]?></td>
         <td class="content_row">
            <input name="xuser_pass" type="text" class="text" style="width:180px"
            onfocus="markfield(this,0);this.type='password'" onblur="markfield(this,1)" value="">
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?=Nifty_printH("boxopt_b", "350")?>
      <tr>
         <td class="content_row_clear" align="right">
            <?php
            printButton("Borrar el producto", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_log)", "tick-circle-frame", 200);
            ?>
         </td>
      </tr>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
</table>
</body>
</html>