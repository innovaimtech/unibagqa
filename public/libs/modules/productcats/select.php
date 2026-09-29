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
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <script type="text/javascript" src="../../../libs/jscripts/NiftyCube/niftycube.js"></script>
   <link href="../../../libs/jscripts/NiftyCube/niftyCorners.css" rel="stylesheet" type="text/css" />
   <script language="Javascript">
      <?php require_once("../../../libs/jscripts/sourcen.php") ?>
   </script>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
</head>
<body class="page" style="margin:5px" onload="<?=Nifty_printJS("ALL")?>">
<script language="JavaScript">
   showLoading();
</script>
<div id="idx_loadinghide" style="display:none">
<?=Nifty_printH("box1", "100%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="30">
   <col width="60">
   <col>
   <col width="95">
   <col width="85">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Resumen de familias</td>
</tr>
<tr>
   <td class="content_tbl_subheader">&nbsp;</td>
   <td class="content_tbl_subheader">Numero</td>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader">Creado</td>
   <td class="content_tbl_subheader" align="center">Opcion</td>
</tr>
<?php
if($_REQUEST["id"] != "")
{
   $sql = " select t2.id
            from item_productcats{$_REQUEST["tbl_suffix"]} t1, productcats t2
            where
            t1.item_id     = {$_REQUEST["id"]} and
            t1.cat_id      = t2.id and
            t2.cat_status  = 1";
   $selitemcatids = $CON->select($sql);
   foreach($selitemcatids AS $selitemcatid)
      $selitemcatidarr[$selitemcatid["id"]] = 1;
   $selitemcatidarr = array_keys($selitemcatidarr);
}
printProdSubcatsSelect($CON, 0, 0, $selitemcatidarr);
?>
</table>
<?=Nifty_printF(false)?>
<br>
<script language="Javascript">
   function execAfterLoad()
   {  <?php
         echo $_SESSION["JSEXEC"];
         $_SESSION["JSEXEC"] = "";
      ?>
   }
</script>
<table border="0" cellpadding="0" cellspacing="0" width="130" align="right">
<tr>
   <td>
      <ul class="postnav_save">
         <a href="javascript: setProductcats('<?=$_REQUEST["tbl_suffix"]?>')"><?=$_LANG["FORM"]["BUTTON"][10]?></a>
      </ul>
   </td>
</tr>
</table>
<br><br>
</body>
</html>