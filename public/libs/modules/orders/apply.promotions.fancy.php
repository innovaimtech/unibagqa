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

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.id 'company_id', t2.company_short
         from company_shops t1
         LEFT OUTER JOIN company_data t2 ON t1.shop_company_id = t2.id
         where
         t1.id = {$_REQUEST["shopid"]}";
$shop = $CON->select($sql);
$shop = $shop[0];
   
//----------------------------------------------------------------------------------
$proms = getActivePromotions($CON, $_REQUEST["shopid"]);
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
   </script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col>
   <col width="80">
   <col width="160">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Promociones disponibles</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$shop["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$shop["shop_name"]?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="200">
   <col>
   <col width="80">
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_subheader">Promoción</td>
   <td class="content_tbl_subheader">Descripción</td>
   <td class="content_tbl_subheader">Descuento</td>
   <td class="content_tbl_subheader" align="center">Opciones</td>
</tr>
<?php
$x = 0;
foreach($proms AS $prom)
{
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row" valign="top"><?=$prom["prom_name"]?></td>
      <td class="content_row" valign="top"><?=$prom["prom_desc"]?></td>
      <td class="content_row" valign="top"><?=printprice($prom["prom_dsc"])?> %</td>
      <td class="content_row" valign="top" align="center">
         <?php
         printButton("Configurar", "postnav_save", "apply.promotions.config.fancy.php?promid={$prom["id"]}&frmname={$_REQUEST["frmname"]}&shopid={$_REQUEST["shopid"]}&id={$_REQUEST["id"]}&partid={$_REQUEST["partid"]}&mode={$_REQUEST["mode"]}", "", "script", 85);
         ?>
      </td>
   </tr>
   <?php
   $x++;
}
if(!$x)
{  ?>
   <tr>
      <td class="content_row" colspan="4" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles</b>
         <br><br>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
</body>
</html>