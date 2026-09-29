<?php
$_REQUEST["id"] = (int)$_REQUEST["id"];

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
/*
$sql = " select t1.*, t3.company_short, t4.shop_name, t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t8.country_name, t9.name, t10.nombre, t11.pro_name
         from offers t1
         LEFT OUTER JOIN company_data t3     ON t1.req_company_id       = t3.id
         LEFT OUTER JOIN company_shops t4    ON t1.req_shop_id          = t4.id
         LEFT OUTER JOIN user t5             ON t1.req_updusr           = t5.id
         LEFT OUTER JOIN user t6             ON t1.req_crtusr           = t6.id
         LEFT OUTER JOIN payments t7         ON t1.req_paymentid        = t7.id
         LEFT OUTER JOIN country t8          ON t1.req_cust_countryid   = t8.id
         LEFT OUTER JOIN regions t9          ON t1.req_cust_regionid    = t9.id
         LEFT OUTER JOIN comunas t10         ON t1.req_cust_comunaid    = t10.id
         LEFT OUTER JOIN provincias t11      ON t1.req_cust_provinciaid = t11.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
$posdata  = getOfferPos($CON, $_REQUEST["id"]);

   

*/

$sql = " select * from orders t4 where {$_REQUEST["id"]} = t4.req_offerid ";
$orders = $CON->select($sql);

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
   </script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.table_navigation.js"></script>
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content" onload="<?php if(count($items) == 0 || $items == false) echo "document.xform_itemsearch.sql_stext.focus()"?>">
<?=Nifty_printH("box2", "100%",0)?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" align="center">Numero de CC</td>
      <td class="content_tbl_header" align="center">Monto</td>
   </tr>
   <tr>
   <?php
      for($x = 0; $x < count($orders) && $orders != false; $x++)
      {
         ?>
         <tr>
            <td class="content_row"><?=$orders[$x]["req_number"]?></td>
            <td class="content_row"><?=printPrice($orders[$x]["req_total_brutto"],0)?></td>
         </tr>
         <?php
      }
   ?>
   </tr>
   <?php
   ?>
</table>
<?=Nifty_printF()?>
<br>
</html>