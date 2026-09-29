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
if($_REQUEST["itemtype"] == "item")
{
   $sql = " select t1.item_title, t1.item_number_prod
            from item t1
            where
            t1.id = {$_REQUEST["itemid"]}";
   $itemdata = $CON->select($sql);
}
elseif($_REQUEST["itemtype"] == "itemlist")
{
   $sql = " select t1.item_title, t1.item_number_prod
            from itemlist t1
            where
            t1.id = {$_REQUEST["itemid"]}";
   $itemdata = $CON->select($sql);
}
$itemdata = $itemdata[0];

$posdata = getItemShopTransOrders($CON, $_REQUEST["shopid"], $_REQUEST["itemid"], $_REQUEST["itemtype"], true);
//----------------------------------------------------------------------------------
?>
<html>
<head>
   <title><?=$_SESSION["_CONF"]["conf_title"]?></title>
   <style type="text/css">
      <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="80">
   <col>
   <col width="80">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Stock en transito</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$shop["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$shop["shop_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Artículo</td>
   <td class="content_row"><?=$itemdata["item_number_prod"]?> - <?=$itemdata["item_title"]?></td>
   <td class="content_rowl">Unidad</td>
   <td class="content_row"><?=getItemUnitDesc($CON, $_REQUEST["itemid"], $_REQUEST["itemtype"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "99%")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="70">
   <col width="80">
   <col>
   <col width="90">
   <col width="90">
</colgroup>
<tr>
   <td class="content_tbl_subheader">Nº OC</td>
   <td class="content_tbl_subheader" align="center">Cantidad</td>
   <td class="content_tbl_subheader">Proveedor</td>
   <td class="content_tbl_subheader">Creado</td>
   <td class="content_tbl_subheader">Entrega</td>
</tr>
<?php
$gestrans = 0;
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row"><?=$posdata[$x]["sord_number"]?></td>
      <td class="content_row" align="center"><?=printPrice($posdata[$x]["transstock"],2)?></td>
      <td class="content_row"><?=$posdata[$x]["supp_company"]?></td>
      <td class="content_row"><?=date('d.m.Y', $posdata[$x]["sord_crtdat"])?></td>
      <td class="content_row"><?=date('d.m.Y', $posdata[$x]["sord_date"])?></td>
   </tr>
   <?php
   $gestrans += $posdata[$x]["transstock"];
}
if(!$x)
{  ?>
   <tr>
      <td class="content_row" colspan="5" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles</b>
         <br><br>
      </td>
   </tr>
   <?php
}
else
{  ?>
   <tr>
      <td class="content_row"><b>TOTAL</b></td>
      <td class="content_row" align="center"><b><?=printPrice($gestrans,2)?></b></td>
      <td class="content_row" align="center" colspan="3">&nbsp;</td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
</body>
</html>