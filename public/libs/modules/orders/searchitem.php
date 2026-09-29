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

$sql = " select *
         from orders
         where
         id = {$_REQUEST["reqid"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
?>
<script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
<script language="JavaScript">
parent.document.getElementById('item_sellprice_netto_' +<?=$_REQUEST["rowcount"]?>).innerHTML = '';
var obj = parent.document.getElementsByName('item_id_<?=$_REQUEST["rowcount"]?>')[0];

if(obj.style.display == '')
   obj.options.length = 0;
var varchecks = new Object();
var selFirst  = 0;
parent.$('select[id^="item_id_"]').each(function(index, value)
{
   var ival = new String($(this).attr('value'));
   if(ival != '' && ival.length > 0)
   {
      var valarr = ival.split('#');
      if(valarr.length > 0 && valarr[0] != '' && valarr[1] != '' && valarr[1] != 'manual')
         varchecks[valarr[0]] = valarr[1];
   }
});

if(obj.style.display == '')
{
   <?php
   $_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));

   if($_REQUEST["rowcount"] != "" &&  ($_REQUEST["search"] != "" || ($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] != "")))
   {
      $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, t3.itemshop_sellprice_netto,
                      t3.itemshop_sellprice_taxes_perc, 'item_type' 'I', t2.item_code, t3.itemshop_sellprice_brutto,
                      t1.item_unitembalaje_amount
               from item t1
               LEFT OUTER JOIN item_suppliers t2   ON t1.id = t2.item_id
               INNER JOIN item_shops t3            ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["req_shop_id"]} )
               LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
               where
               t1.item_status       = 1 and
               t1.item_released     = 1 and
               t1.item_sellable     = 1 and
               t3.itemshop_sellprice_netto > 0.00 and
               (
                  t1.item_title        like '%{$_REQUEST["search"]}%' or
                  t2.item_code         like '%{$_REQUEST["search"]}%' or
                  t1.item_number       like '%{$_REQUEST["search"]}%' or
                  t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
                  tx.item_barcode      = '{$_REQUEST["search"]}'
               ) ";

      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
         $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
         $sql .= " and 1 = 3 ";

      $sql .= "UNION ALL
               select distinct t1.id, t1.item_title, t1.item_number_prod, t3.itemshop_sellprice_netto,
                      t3.itemshop_sellprice_taxes_perc, 'item_type' 'L', t2.item_code, t3.itemshop_sellprice_brutto,
                      'item_unitembalaje_amount' 'item_unitembalaje_amount'
               from itemlist t1
               LEFT OUTER JOIN item_suppliers t2   ON t1.id = t2.item_id
               INNER JOIN itemlist_shops t3        ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["req_shop_id"]} )
               where
               t1.item_status       = 1 and
               t1.item_released     = 1 and
               t1.item_sellable     = 1 and
               t3.itemshop_sellprice_netto > 0.00 and
               (
                  t1.item_title        like '%{$_REQUEST["search"]}%' or
                  t2.item_code         like '%{$_REQUEST["search"]}%' or
                  t1.item_number       like '%{$_REQUEST["search"]}%' or
                  t1.item_number_prod  like '%{$_REQUEST["search"]}%'
               ) ";

      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "itemlist")
         $sql .= " and t1.id = {$_REQUEST["itemid"]} ";
      if($_REQUEST["itemid"] != "" && $_REQUEST["itemtype"] == "item")
         $sql .= " and 1 = 3 ";

      $sql .= " order by 2
                LIMIT 0, 200";
      $items = $CON->select($sql);

      for($x = 0; $x < count($items) && $items != false; $x++)
      {
         if($items[$x]["item_type"] == "item_typeI")
            $items[$x]["item_type"] = "item";
         else
            $items[$x]["item_type"] = "itemlist";


         $unitdesc      = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         $desc          = trim(addslashes($items[$x]["item_title"]));
         $custprice     = getCustomerPLPrice($CON, $headdata["req_cust_id"], $items[$x]["id"], $items[$x]["item_type"]);

         if((int)$custprice["item_id"])
         {
            $items[$x]["itemshop_sellprice_netto"]       = (float)$custprice["item_sellprice_netto"];
            $items[$x]["itemshop_sellprice_taxes_perc"]  = (float)$custprice["item_sellprice_taxes_perc"];
            $items[$x]["itemshop_sellprice_brutto"]      = (float)$custprice["item_sellprice_brutto"];
         }
         $addcode = "";
         if($items[$x]["item_code"] != "")
            $addcode = " - ".$items[$x]["item_code"];
         ?>
         var addItem = 1;
         for(xitemid in varchecks)
         {  if(xitemid == '<?=$items[$x]["id"]?>' && varchecks[xitemid] == '<?=$items[$x]["item_type"]?>')
               addItem = 0;
         }
         if(addItem == 1)
         {
            var newIndex = obj.options.length;
            var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> <?=$addcode?> - <?=$desc?> (<?=$unitdesc?> <?=$add?>)');
            newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=printPrice($items[$x]["itemshop_sellprice_netto"])?>#<?=printPrice($items[$x]["itemshop_sellprice_taxes_perc"],2)?>#<?=printPrice($items[$x]["itemshop_sellprice_brutto"])?>#<?=printPrice($items[$x]["item_unitembalaje_amount"],2)?>';
            obj.options[newIndex] = newOpt;
            if(selFirst == 0)
            {
               obj.selectedIndex  = 0; parent.setItemInfosOrderVenta(<?=$_REQUEST["rowcount"]?>, newOpt.value); selFirst = 1;
               parent.showStockAct(<?=$_REQUEST["rowcount"]?>, '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>');
            }
         }
         <?php
      }
   }
   ?>
}
</script>
<?php
$x--;
$custprice = getCustomerPLPrice($CON, $headdata["req_cust_id"], $items[$x]["id"], $items[$x]["item_type"]);
?>