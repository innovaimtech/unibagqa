<?php
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");

//----------------------------------------------------------------------------------
error_reporting($_CONFIG[$_CONFIG["_MODUS"]]["ERROR_REPORTING"]);

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

require_once("../../../libs/functions.php");
require_once("../../../libs/functions.erp.php");

//----------------------------------------------------------------------------------
$_REQUEST["itemid"]  = (int)$_REQUEST["itemid"];
$_REQUEST["rowid"]   = (int)$_REQUEST["rowid"];
$_REQUEST["itemamt"] = (int)$_REQUEST["itemamt"];

//----------------------------------------------------------------------------------
if((int)$_REQUEST["itemid"] && $_REQUEST["xmode"] == "one")
{
   $sql = " select *
            from item_volprices
            where
            item_id = {$_REQUEST["itemid"]}
            order by item_amount_from";
   $vols = $CON->select($sql);
   if(count($vols) && $vols != false)
   {
      $found = false;
      ?>
      <script language="JavaScript">
         <?php
         foreach($vols AS $vol)
         {
            if($_REQUEST["itemamt"] >= $vol["item_amount_from"] && $_REQUEST["itemamt"] <= $vol["item_amount_to"])
            {  ?>
               $('#item_sellprice_netto_<?=$_REQUEST["rowid"]?>').val('<?=printPrice($vol["item_prices_netto"])?>');
               $('#idx_item_sellprice_netto_dsc_<?=$_REQUEST["rowid"]?>').val('<?=printPrice(round($vol["item_prices_netto"] * $_REQUEST["itemamt"]))?>');
               <?php
               $found = true;
            }
         }
         if(!$found)
         {  ?>
            $('#item_sellprice_netto_<?=$_REQUEST["rowid"]?>').val('<?=printPrice($vol["item_prices_netto"])?>');
            $('#idx_item_sellprice_netto_dsc_<?=$_REQUEST["rowid"]?>').val('<?=printPrice(round($vol["item_prices_netto"] * $_REQUEST["itemamt"]))?>');
            <?php
         }
         ?>
      </script>
      <?php
   }
}
//----------------------------------------------------------------------------------
if((int)$_REQUEST["itemid"] && $_REQUEST["xmode"] == "multi")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from offers
            where
            id = {$_REQUEST["offerid"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = " select *
            from item_volprices
            where
            item_id = {$_REQUEST["itemid"]}
            order by item_amount_from";
   $vols = $CON->select($sql);

   ?>
   <script language="JavaScript">
   <?php
   if(count($vols) && $vols != false)
   {
      if((int)$headdata["req_prices_cnt"] > 1)
      {
         for($xx = 1; $xx <= $headdata["req_prices_cnt"]; $xx++)
         {
            $checkamt   = $headdata["req_prices_ccval{$xx}"];
            $found      = false;
            foreach($vols AS $vol)
            {
               if($checkamt >= $vol["item_amount_from"] && $checkamt <= $vol["item_amount_to"])
               {  ?>
                  $('#item_sellprice_nettocnt_<?=$_REQUEST["rowid"]?>_<?=$xx?>').val('<?=printPrice($vol["item_prices_netto"])?>');
                  $('#item_sellprice_nettoccval_<?=$_REQUEST["rowid"]?>_<?=$xx?>').val('<?=printPrice(round($vol["item_prices_netto"] * $checkamt))?>');
                  <?php
                  $found = true;
               }
            }
            if(!$found)
            {  ?>
               $('#item_sellprice_nettocnt_<?=$_REQUEST["rowid"]?>_<?=$xx?>').val('<?=printPrice($vol["item_prices_netto"])?>');
               $('#item_sellprice_nettoccval_<?=$_REQUEST["rowid"]?>_<?=$xx?>').val('<?=printPrice(round($vol["item_prices_netto"] * $checkamt))?>');
               <?php
            }
         }
      }
   }
   else
   {
      if((int)$headdata["req_prices_cnt"] > 1)
      {
         for($xx = 1; $xx <= $headdata["req_prices_cnt"]; $xx++)
         {  ?>
            $('#item_sellprice_nettocnt_<?=$_REQUEST["rowid"]?>_<?=$xx?>').val('0');
            $('#item_sellprice_nettoccval_<?=$_REQUEST["rowid"]?>_<?=$xx?>').val('0');
            <?php
         }
      }
   }
   ?>
   </script>
   <?php
}
?>