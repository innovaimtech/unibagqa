<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
require_once("../../../classes/page.php");
require_once("../../../classes/mysql.php");
require_once("../../../config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../functions.php");

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
$sql = " select t1.item_title, t1.item_number_prod
         from item t1
         where
         t1.id = {$_REQUEST["itemid"]}";
$itemdata = $CON->select($sql);
$itemdata = $itemdata[0];

$_REQUEST["compr"] = (int)$_REQUEST["compr"];

$sql = " select t1.id, t1.req_number, t2.item_amount, t2.item_amount_shipped, ((t2.item_amount_shipped - t2.item_amount) *-1) 'sharedamount',
                t3.cust_name, t1.req_crtdat
         from orders t1
         INNER JOIN orders_items t2 ON t1.id = t2.req_id
         INNER JOIN customer t3 ON t1.req_cust_id = t3.id
         where
         t1.req_status  > 1 and
         t1.req_status  < 4 and
         t1.req_shop_id = {$_REQUEST["shopid"]} and
         t1.req_isreserva = {$_REQUEST["compr"]} and
         t2.item_id     = {$_REQUEST["itemid"]} and
         t2.item_type   = '{$_REQUEST["itemtype"]}' and
         t2.item_amount_shipped < t2.item_amount
         order by t1.id desc ";
$nvs = $CON->select($sql);

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
   <col width="160">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Stock reservado</td>
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
   <col>
   <col>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_subheader">Nota de Venta</td>
   <td class="content_tbl_subheader">Fecha</td>
   <td class="content_tbl_subheader">Cliente</td>
   <td class="content_tbl_subheader" align="center">Cantidad</td>
</tr>
<?php
$x = 0;
$gesstock = 0;
foreach($nvs AS $notav)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row"><?=$notav["req_number"]?></td>
      <td class="content_row"><?=date("d.m.Y", $notav["req_crtdat"])?></td>
      <td class="content_row"><?=$notav["cust_name"]?></td>
      <td class="content_row" align="center"><?=printPrice($notav["sharedamount"],2)?></td>
   </tr>
   <?php
   $gesstock += $notav["sharedamount"];
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
else
{  ?>
   <tr>
      <td class="content_row"><b>TOTAL</b></td>
      <td class="content_row" align="center">&nbsp;</td>
      <td class="content_row" align="center">&nbsp;</td>
      <td class="content_row" align="center"><b><?=printPrice($gesstock,2)?></b></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
</body>
</html>