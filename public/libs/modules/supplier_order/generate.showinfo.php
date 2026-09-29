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
$suppliers  = getItemSuppliers($CON, $_REQUEST["item_id"], $_REQUEST["item_type"]);
   
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
<?=Nifty_printH("box1", "600")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col>
   <col width="100">
   <col width="90">
   <col width="110">
</colgroup>
<tr>
   <td class="content_tbl_header" style="border-radius:0px">Proveedor</td>
   <td class="content_tbl_header" style="border-radius:0px">Precio/actual</td>
   <td class="content_tbl_header" style="border-radius:0px">Ult.compra</td>
   <td class="content_tbl_header" style="border-radius:0px">Ult.compra/Final</td>
</tr>
<?php
//----------------------------------------------------------------------------------
for($x = 0; $x < count($suppliers) && $suppliers != false; $x++)
{
   $sql = " select t1.invc_importation, t2.item_costprice_netto_dsc2, t2.item_amount, t2.item_costprice_netto
            from invoices_buy t1
            INNER JOIN invoices_buy_parts_items t2 ON t1.id = t2.invc_id
            where
            t1.invc_status       > 1 and
            t1.invc_supplier_id  = {$suppliers[$x]["supplier_id"]} and
            t2.item_id           = {$_REQUEST["item_id"]} and 
            t2.item_type         = '{$_REQUEST["item_type"]}'
            order by t1.invc_date desc
            LIMIT 0,1";
   $lastbuy = $CON->select($sql);
   $desc = "";
   $rnd  = 0;
   
   if((int)$lastbuy[0]["invc_importation"])
   {
      $desc = "US";
      $rnd  = 2;
      $suppliers[$x]["item_costprice_netto"] = $suppliers[$x]["item_costprice_usd"];
   }
   $lastbuy_netto = $lastbuy[0]["item_costprice_netto"];
   $lastbuy_desc  = round($lastbuy[0]["item_costprice_netto_dsc2"] / $lastbuy[0]["item_amount"],$rnd);
   ?>
   <tr bgcolor="<?=getRowColor(1)?>">
      <td class="content_row"><?=$suppliers[$x]["supp_short"]?>&nbsp;</td>
      <td class="content_row"><?=$desc?> <?=printPrice($suppliers[$x]["item_costprice_netto"],$rnd)?></td>
      <td class="content_row"><?=$desc?> <?=printPrice($lastbuy_netto,$rnd)?></td>
      <td class="content_row"><?=$desc?> <?=printPrice($lastbuy_desc,$rnd)?></td>
   </tr>
   <?php
   if(count($suppliers) == 1)
      $grheight = 170;
   elseif(count($suppliers) == 2)
      $grheight = 70;
   else
      $grheight = 0;
   if($grheight > 0)
   {  ?>
      <tr bgcolor="#FFFFFF">
         <td class="content_row" colspan="4">
            <img src="/libs/modules/supplier_order/generate.showinfo.graph.php?item_id=<?=$_REQUEST["item_id"]?>&item_type=<?=$_REQUEST["item_type"]?>&suppid=<?=$suppliers[$x]["supplier_id"]?>&grheight=<?=$grheight?>">
         </td>
      </tr>
      <?php
   }
}
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>">
      <td class="content_row" colspan="4" align="center" style="height:40px">
         <b class="msg_save_err"><?=$_LANG["FORM"]["MESSAGE"][5]?></b>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
</body>
</html>