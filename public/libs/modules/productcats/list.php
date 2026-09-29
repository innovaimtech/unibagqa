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
?>
<html>
<head>
<style type="text/css">
   <?php $_SESSION["_PAGE"]->printStyle() ?>
</style>
<body class="page" style="margin:0px">
<table border="0" cellpadding="3" class="content_table" cellspacing="0" width="100%" height="100%" bgcolor="#F5F8F9">
<?php
if($_REQUEST["catids"] != "")
{
   $idarr = explode("_", $_REQUEST["catids"]);
   foreach($idarr AS $catid)
   {  ?>
      <tr>
         <td class="content_row_clear" style="padding-left:10px" width="90"><?=sprintf("%03s", $catid)?></td>
         <td class="content_row_clear" style="padding-left:10px"><li type="square"><?=substr(printProdSubcatsList($CON, $catid), 0, -6)?></td>
      </tr>
      <?php
   }
   ?>
   </table>
   <script language="Javascript">
parent.document.getElementById('cat_iframe').height='<?=count($idarr) * 22?>px';
      //parent.document.getElementsByName('cat_iframe').length ? parent.document.getElementsByName('cat_iframe')[0].height='<?=count($idarr) * 22?>px' : parent.document.getElementById('cat_iframe').height='<?=count($idarr) * 22?>px';

   </script>
   <?php
}
else
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="8" align="center">
         <br>
         <b class="msg_save_err">No hay familias seleccionadas</b>
         <br><br>
      </td>
   </tr>
   </table>
   <script language="Javascript">
parent.document.getElementById('cat_iframe').height='52px';
   </script>
   <?php
}