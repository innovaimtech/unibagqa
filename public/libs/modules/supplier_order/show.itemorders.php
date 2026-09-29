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
         t1.id = {$_REQUEST["sord_shop_id"]}";
$shop = $CON->select($sql);
$shop = $shop[0];

//----------------------------------------------------------------------------------
if($_REQUEST["sql_item_type"] == "item")
{
   $sql = " select t1.item_title, t1.item_number_prod
            from item t1
            where
            t1.id = {$_REQUEST["sql_itemid"]}";
   $itemdata = $CON->select($sql);
}
elseif($_REQUEST["sql_item_type"] == "itemlist")
{
   $sql = " select t1.item_title, t1.item_number_prod
            from itemlist t1
            where
            t1.id = {$_REQUEST["sql_itemid"]}";
   $itemdata = $CON->select($sql);
}
$itemdata = $itemdata[0];

//----------------------------------------------------------------------------------
$sqldate_from           = getDateFromString($_REQUEST["sql_datefrom"]);
$sqldate_to             = getDateFromString($_REQUEST["sql_dateto"], false);
   
//----------------------------------------------------------------------------------
if($_REQUEST["sql_item_type"] == "item")
   $sql = " select t2.item_id, t2.item_type, t3.item_title, t3.item_number_prod, t4.item_costprice_netto,
                   t4.item_costprice_taxes_perc, t2.item_amount 'amount', t1.req_number, t7.cust_company, t1.req_crtdat
            from orders t1
            INNER JOIN orders_items t2          ON t1.id = t2.req_id
            INNER JOIN item t3                  ON t2.item_id = t3.id
            INNER JOIN item_suppliers t4        ON ( t2.item_id = t4.item_id and t4.supplier_id = {$_REQUEST["sord_supplier_id"]} )
            INNER JOIN item_shops t5            ON ( t2.item_id = t5.item_id and t5.shop_id = {$_REQUEST["sord_shop_id"]} )
            LEFT OUTER JOIN item_productcats t6 ON ( t2.item_id = t6.item_id )
            LEFT OUTER JOIN customer t7         ON t1.req_cust_id = t7.id
            where
            t1.req_status        > 1 and
            t2.item_type         = 'item' and
            t3.item_status       = 1 and
            t3.item_released     = 1 and
            t3.item_purchasable  = 1 and
            t2.item_id           = {$_REQUEST["sql_itemid"]} and
            t1.req_crtdat between {$sqldate_from} and {$sqldate_to}
            order by t1.id desc";
elseif($_REQUEST["sql_item_type"] == "itemlist")
   $sql = " select t2.item_id, t2.item_type, t3.item_title, t3.item_number_prod, t4.item_costprice_netto,
                   t4.item_costprice_taxes_perc, t2.item_amount 'amount', t1.req_number, t7.cust_company, t1.req_crtdat
            from orders t1
            INNER JOIN orders_items t2                   ON t1.id = t2.req_id
            INNER JOIN itemlist t3                       ON t2.item_id = t3.id
            INNER JOIN itemlist_suppliers t4             ON ( t2.item_id = t4.item_id and t4.supplier_id = {$_REQUEST["sord_supplier_id"]} )
            INNER JOIN itemlist_shops t5                 ON ( t2.item_id = t5.item_id and t5.shop_id = {$_REQUEST["sord_shop_id"]} )
            LEFT OUTER JOIN item_productcats_itemlist t6 ON ( t2.item_id = t6.item_id )
            LEFT OUTER JOIN customer t7                  ON t1.req_cust_id = t7.id
            where
            t1.req_status        > 1 and
            t2.item_type         = 'itemlist' and
            t3.item_status       = 1 and
            t3.item_released     = 1 and
            t3.item_purchasable  = 1 and
            t2.item_id           = {$_REQUEST["sql_itemid"]} and
            t1.req_crtdat between {$sqldate_from} and {$sqldate_to}
            order by t1.id desc";
$orders = $CON->select($sql);
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
   <td class="content_tbl_header" colspan="6">Notas de venta</td>
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
   <td class="content_row"><?=getItemUnitDesc($CON, $_REQUEST["sql_itemid"], $_REQUEST["sql_item_type"])?></td>
</tr>
<tr>
   <td class="content_rowl">Periodo</td>
   <td class="content_row" colspan="3"><?=$_REQUEST["sql_datefrom"]?> - <?=$_REQUEST["sql_dateto"]?></td>
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
</colgroup>
<tr>
   <td class="content_tbl_subheader">Nº Venta</td>
   <td class="content_tbl_subheader" align="center">Cantidad</td>
   <td class="content_tbl_subheader">Cliente</td>
   <td class="content_tbl_subheader">Creado</td>
</tr>
<?php
$gestrans = 0;
for($x = 0; $x < count($orders) && $orders != false; $x++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=$orders[$x]["req_number"]?></td>
      <td class="content_row" align="center"><?=printPrice($orders[$x]["amount"],2)?></td>
      <td class="content_row"><?=$orders[$x]["cust_company"]?></td>
      <td class="content_row"><?=date('d.m.Y', $orders[$x]["req_crtdat"])?></td>
   </tr>
   <?php
   $gestrans += $orders[$x]["amount"];
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