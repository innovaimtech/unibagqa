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

$_sesmodulename  = "supporder_gen";
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $seasql .= " and t1.req_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if($_SESSION[$_sesmodulename]["sql_shop"])
   $seasql .= " and t1.req_shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
if($_SESSION[$_sesmodulename]["sql_pcat"])
   $seasql .= " and t6.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";
if($_SESSION[$_sesmodulename]["sql_item_id"])
   $seasql .= " and t2.item_id = {$_SESSION[$_sesmodulename]["sql_item_id"]}
                and t2.item_type = '{$_SESSION[$_sesmodulename]["sql_item_type"]}' ";
                   
//----------------------------------------------------------------------------------
if($_REQUEST["item_type"] == "item")
{
   $datsql = " select t2.item_id, t2.item_type, t10.id, t10.cust_company, t1.req_number, t3.item_title, t3.item_number_prod,
                      t7.unit_name, t1.req_shop_id, t9.supp_short, t8.item_code,
                      SUM(t2.item_order_genamount) 'amount',
                      SUM(t2.item_amount - t2.item_amount_shipped) 'vamount'
               from orders t1
               INNER JOIN orders_items t2          ON t1.id = t2.req_id
               INNER JOIN item t3                  ON t2.item_id = t3.id
               INNER JOIN item_shops t5            ON ( t2.item_id = t5.item_id and t5.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} )
               LEFT OUTER JOIN item_productcats t6 ON ( t2.item_id = t6.item_id )
               LEFT OUTER JOIN item_units t7       ON t3.item_unit = t7.id
               INNER JOIN item_suppliers t8        ON ( t2.item_id = t8.item_id and t8.item_supp_act = 1 )
               INNER JOIN supplier t9              ON ( t8.supplier_id = t9.id )
               INNER JOIN customer t10             ON t1.req_cust_id = t10.id
               where
               t1.req_status        > 1 and
               t2.item_type         = 'item' and
               t3.item_status       = 1 and
               t3.item_released     = 1 and
               t3.item_purchasable  = 1 and
               t1.req_order_shipped = 0 and
               t2.item_amount_shipped_stop = 0 and
               t2.item_amount > t2.item_amount_shipped and
               t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} and
               t2.item_id           = {$_REQUEST["item_id"]} ";
   $datsql .= $seasql;
   $datsql .= " group by 1, 2,3,4,5 ";
}
else
{
   //----------------------------------------------------------------------------------
   $datsql = " select t2.item_id, t2.item_type, t10.id, t10.cust_company, t1.req_number, t3.item_title, t3.item_number_prod,
                      t7.unit_name, t1.req_shop_id, t9.supp_short, t8.item_code,
                      SUM(t2.item_order_genamount) 'amount',
                      SUM(t2.item_amount - t2.item_amount_shipped) 'vamount'
               from orders t1
               INNER JOIN orders_items t2                   ON t1.id = t2.req_id
               INNER JOIN itemlist t3                       ON t2.item_id = t3.id
               INNER JOIN itemlist_shops t5                 ON ( t2.item_id = t5.item_id and t5.shop_id = {$_SESSION[$_sesmodulename]["sql_shop"]} )
               LEFT OUTER JOIN item_productcats_itemlist t6 ON ( t2.item_id = t6.item_id )
               LEFT OUTER JOIN item_units t7                ON t3.item_unit = t7.id
               INNER JOIN itemlist_suppliers t8             ON ( t2.item_id = t8.item_id and t8.item_supp_act = 1 )
               INNER JOIN supplier t9                       ON ( t8.supplier_id = t9.id )
               INNER JOIN customer t10                      ON t1.req_cust_id = t10.id
               where
               t1.req_status        > 1 and
               t2.item_type         = 'itemlist' and
               t3.item_status       = 1 and
               t3.item_released     = 1 and
               t3.item_purchasable  = 1 and
               t1.req_order_shipped = 0 and
               t2.item_amount_shipped_stop = 0 and
               t2.item_amount > t2.item_amount_shipped and
               t1.req_crtdat between {$sql_datefrom} and {$sql_dateto} and
               t2.item_id           = {$_REQUEST["item_id"]}";
   $datsql .= $seasql;
   $datsql .= " group by 1, 2,3,4,5 ";
}

$datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]}  ";

$items = $CON->select($datsql);

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
   <col>
   <col>
   <col width="90">
</colgroup>
<tr>
   <td class="content_tbl_subheader">Cliente</td>
   <td class="content_tbl_subheader">Numero Nota</td>
   <td class="content_tbl_subheader" align="center">Comprado</td>
</tr>
<?php
$x = 0;
$gesstock = 0;
foreach($items AS $item)
{
   ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row"><?=$item["cust_company"]?></td>
      <td class="content_row"><?=$item["req_number"]?></td>
      <td class="content_row" align="center"><?=printPrice($item["vamount"],2)?></td>
   </tr>
   <?php
   $gesstock += $item["vamount"];
   $x++;
}
if(!$x)
{  ?>
   <tr>
      <td class="content_row" colspan="3" align="center">
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
      <td class="content_row" colspan="2"><b>TOTAL</b></td>
      <td class="content_row" align="center"><b><?=printPrice($gesstock,2)?></b></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
</body>
</html>