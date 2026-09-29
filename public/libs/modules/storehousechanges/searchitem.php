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
         from storehousechanges
         where
         id = {$_REQUEST["strcid"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$_REQUEST["search"] = trim(addslashes(str_replace("*","%",$_REQUEST["search"])));
echo md5(microtime());
?>
<script language="JavaScript">

var setX = -1;

parent.document.getElementById('item_stid_<?=$_REQUEST["rowcount"]?>').options.length = 0;
parent.document.getElementById('item_stid_dest_<?=$_REQUEST["rowcount"]?>').options.length = 0;

var obj = parent.document.getElementById('item_id_<?=$_REQUEST["rowcount"]?>');
obj.options.length = 0;

<?php
if($_REQUEST["rowcount"] != "" &&  $_REQUEST["search"] != "")
{
   if($headdata["strc_shop_id"] == $headdata["strc_shop_dest_id"])
      $havingres = 1;
   else
      $havingres = 2;
      
   $sql = " select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'I', t1.item_charges_act,
                   count(t3.shop_id) 'shopcount'
            from item t1
            LEFT OUTER JOIN item_suppliers t2   ON ( t1.id = t2.item_id and t2.item_supp_act = 1 )
            INNER JOIN item_shops t3            ON ( t1.id = t3.item_id and t3.shop_id IN ({$headdata["strc_shop_id"]},{$headdata["strc_shop_dest_id"]}))
            LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item')
            where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            (
               t1.item_title        like '%{$_REQUEST["search"]}%' or
               t1.item_number       like '%{$_REQUEST["search"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
               t2.item_code         like '%{$_REQUEST["search"]}%' or
               tx.item_barcode      = '{$_REQUEST["search"]}'
            )
            group by 1,2,3,4,5
            having count(t3.shop_id) >= {$havingres}
            UNION ALL
            select distinct t1.id, t1.item_title, t1.item_number_prod, 'item_type' 'L', t5.item_charges_act,
                   count(t3.shop_id) 'shopcount'
            from itemlist t1
            LEFT OUTER JOIN itemlist_suppliers t2  ON ( t1.id = t2.item_id and t2.item_supp_act = 1 )
            INNER JOIN itemlist_shops t3           ON ( t1.id = t3.item_id and t3.shop_id IN ({$headdata["strc_shop_id"]},{$headdata["strc_shop_dest_id"]}))
            LEFT OUTER JOIN itemlist_pos t4        ON (t1.id = t4.itemlist_id)
            LEFT OUTER JOIN item t5                ON (t4. item_id  = t5.id)
            where
            t1.item_status       = 1 and
            t1.item_released     = 1 and
            (
               t1.item_title        like '%{$_REQUEST["search"]}%' or
               t1.item_number       like '%{$_REQUEST["search"]}%' or
               t1.item_number_prod  like '%{$_REQUEST["search"]}%' or
               t2.item_code         like '%{$_REQUEST["search"]}%'
            )
            group by 1,2,3,4,5
            having count(t3.shop_id) >= {$havingres}
            order by 2,4
            LIMIT 0, 200";
   $items  = $CON->select($sql);

   $realx = 0;
   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      if($items[$x]["item_type"] == "item_typeI")
      {
         $items[$x]["item_type"] = "item";
         $checkstitemid = $items[$x]["id"];
      }
      else
      {
         $items[$x]["item_type"] = "itemlist";
         $itemlistpos = getItemListContent($CON, $items[$x]["id"]);
         $checkstitemid = $itemlistpos[0]["item_id"];
      }

      $o_itemsts    = getItemStorehouses($CON, $headdata["strc_shop_id"], $checkstitemid, "item");
      $o_itemstid   = array_keys($o_itemsts);
      $o_itemstid   = (int)$o_itemstid[0];

      $d_itemsts    = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $checkstitemid, "item");
      $d_itemstid   = array_keys($d_itemsts);
      $d_itemstid   = (int)$d_itemstid[0];

      if($o_itemstid && $d_itemstid)
      {
         $unitdesc   = getItemUnitDesc($CON, $items[$x]["id"], $items[$x]["item_type"]);
         $desc       = trim(addslashes($items[$x]["item_title"]));
         ?>
         var newIndex = obj.options.length;
         var newOpt = new Option('<?=$items[$x]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)');
         newOpt.value = '<?=$items[$x]["id"]?>#<?=$items[$x]["item_type"]?>#<?=(int)$items[$x]["item_charges_act"]?>';
         obj.options[newIndex] = newOpt;

         if(setX == -1)
         {
            obj.selectedIndex  = 0;
            setX = newIndex;
         }
         <?php
      }
   }
   ?>
   if(setX != -1)
   {
      parent.setItemInfosSth(<?=$_REQUEST["rowcount"]?>, obj.options[setX].value);
   }
   <?php
}
?>
</script>
<?php