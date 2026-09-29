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
$sql = " select *
         from prod_item_ext
         where
         id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$posdata = Array();
$sesdata = $_SESSION[$_REQUEST["sesmodulename"]]["ADJDATA"];
foreach(array_keys($sesdata) AS $itemid)
{
   foreach(array_keys($sesdata[$itemid]) AS $itemtype)
   {
      $temp["item_id"]     = $itemid;
      $temp["item_type"]   = $itemtype;
      $temp["item_amount"] = $sesdata[$itemid][$itemtype]["AMOUNT"];
      $temp["item_costprice_netto"] = $sesdata[$itemid][$itemtype]["COST"];

      $sql = " select item_title
               from {$temp["item_type"]}
               where
               id = {$temp["item_id"]}";
      $title = $CON->select($sql);
      
      $temp["item_title"]  = $title[0]["item_title"];
      $posdata[]           = $temp;
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save" && count($posdata))
{
   //----------------------------------------------------------------------------------
   $stk_annotation   = "Modificacion externa: ".sprintf("%07s",$headdata["id"]);
   $stk_bookdate     = (int)mktime(0, 0, 0, date('m'), date('d'), date('Y'));
   $stk_num          = createTransactionNumber($CON, $headdata["req_company_id"], "stockchange");

   //----------------------------------------------------------------------------------
   $currtme = time();
   $sql = " insert into stockchanges
            (stk_num, stk_annotation, stk_issueid, stk_companyid, stk_shopid, stk_bookdate,
             stk_negative, stk_crtdat, stk_crtusr, stk_isprodext)
            VALUES
            ('{$stk_num}', '{$stk_annotation}', 12, {$headdata["req_company_id"]}, {$headdata["req_shop_id"]},
              {$stk_bookdate}, 0, {$currtme}, {$_SESSION["user_id"]}, {$_REQUEST["id"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      //----------------------------------------------------------------------------------
      $sql = " select MAX(id) 'thisid'
               from stockchanges
               where
               stk_crtusr = {$_SESSION["user_id"]}";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["thisid"];

      $insertcounter = 0;
      foreach($posdata AS $positem)
      {
         $costdata                  = getSupplierItemCosts($CON, 0, $positem["item_id"], "item");

         $item_costprice_netto      = (float)getPrice($_REQUEST["item_costprice_netto_{$insertcounter}"],2);
         $item_costprice_taxes_perc = (float)$costdata["item_costprice_taxes_perc"];

         $item_costprice_taxes      = (float)round(($item_costprice_netto / 100 * $item_costprice_taxes_perc),2);
         $item_costprice_brutto     = (float)$item_costprice_netto + $item_costprice_taxes;

         $_REQUEST["item_stid_{$insertcounter}"] = (int)$_REQUEST["item_stid_{$insertcounter}"];

         $sql = " insert into stockchanges_items
                  (stk_id, item_id, item_pos, item_amount, item_type, item_st_id, item_costprice_brutto,
                   item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes)
                  VALUES
                  ({$thisid}, {$positem["item_id"]}, {$insertcounter}, {$positem["item_amount"]}, '{$positem["item_type"]}',
                   {$_REQUEST["item_stid_{$insertcounter}"]}, {$item_costprice_brutto}, {$item_costprice_taxes_perc},
                   {$item_costprice_netto}, {$item_costprice_taxes})";
         $res = $CON->no_result($sql);

         //----------------------------------------------------------------------------------
         if($positem["item_type"] == "itemlist")
         {
            $itemlistpos      = getItemListContent($CON, $positem["item_id"]);
            $tran_item_id     = $itemlistpos[0]["item_id"];
            $tran_packamt     = $itemlistpos[0]["item_amount"];

            $positem["item_amount"] = $tran_packamt * $positem["item_amount"];
         }
         else
            $tran_item_id = $positem["item_id"];

         //----------------------------------------------------------------------------------
         /*
         $sql = " insert into tran_average_costprices_hist
                  (tran_id, tran_type, tran_company_id, tran_shop_id, item_id, item_type, item_costprice_netto, tran_amount)
                  VALUES
                  ({$thisid}, 'itemprodext', {$headdata["req_company_id"]}, {$headdata["req_shop_id"]},
                   {$tran_item_id}, 'item', {$item_costprice_netto}, {$positem["item_amount"]})";
         $CON->no_result($sql);

         //----------------------------------------------------------------------------------
         $sql = " select *
                  from tran_average_costprices
                  where
                  item_id     = {$tran_item_id} and
                  company_id  = {$headdata["req_company_id"]}";
         $currentavgcost = $CON->select($sql);
         $currentavgcost = $currentavgcost[0];

         //----------------------------------------------------------------------------------
         if((int)$currentavgcost["item_id"])
         {
            $compstock  = 0.00;
            $shops      = getShops($CON);
            foreach($shops AS $shop)
            {
               if($shop["shop_company_id"] == $headdata["req_company_id"])
                  $compstock += getItemShopCurrentStock($CON, $shop["id"], $tran_item_id, "item");
            }

            //----------------------------------------------------------------------------------
            $compcost    = $compstock * $currentavgcost["item_costprice_avg_netto"];
            $compcost   += ($positem["item_amount"] * $item_costprice_netto);
            $newavgcost  = $compcost / ($compstock + $positem["item_amount"]);
            
            $sql = " update tran_average_costprices
                     set
                     item_costprice_avg_netto = {$newavgcost}
                     where
                     item_id     = {$tran_item_id} and
                     company_id  = {$headdata["req_company_id"]}";
            $CON->no_result($sql);
         }
         else
         {
            $sql = " insert into tran_average_costprices
                     (item_id, company_id, item_costprice_avg_netto)
                     VALUES
                     ({$tran_item_id}, {$headdata["req_company_id"]}, {$item_costprice_netto})";
            $CON->no_result($sql);
         }
         */
         $insertcounter++;
      }
      bookStockChange($CON, $thisid);
      ?>
      <script language="JavaScript">
         alert('AJUSTE <?=$stk_num?> GENERADO.');
         parent.$.fancybox.close();
      </script>
      <?php
   }
}

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
   <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>
<body class="page_content">
<input type="hidden" name="jschk_formchange" id="jschk_formchange" value="0">
<input type="hidden" name="jschk_formchange_ignore" id="jschk_formchange_ignore" value="0">
<?php
if(count($posdata))
{  ?>
   <form action="data.generate.adj.fancy.php" method="post" name="form_shppos">
   <input type="hidden" name="subexec" value="save">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="sesmodulename" value="<?=$_REQUEST["sesmodulename"]?>">
   <?=Nifty_printH("box1", "720")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="80">
      <col width="120">
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Artículos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Artículo</td>
      <td class="content_tbl_subheader" align="left">Cantidad</td>
      <td class="content_tbl_subheader">Bodega</td>
      <td class="content_tbl_subheader" align="right">Costo</td>
   </tr>
   <?php
   for($x = 0; $x < count($posdata); $x++)
   {
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stid_{$x}";

      $desc       = trim(addslashes($posdata[$x]["item_title"]));
      $unitdesc   = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
      ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row" valign="top"><?=$desc?></td>
         <td class="content_row" align="left">
            <?=printPrice($posdata[$x]["item_amount"],2)?>
         </td>
         <td class="content_row" valign="top">
            <select class="text" style="width:200px"
            name="item_stid_<?=$x?>" id="item_stid_<?=$x?>"
            onmousedown="markfield(this,0)"
            onfocus="addSelStyle(this);"
            onblur="markfield(this,1);removeSelStyle(this);">
               <?php
               if((int)$posdata[$x]["item_id"])
               {
                  if($posdata[$x]["item_type"] == "item")
                     $itemsts = getItemStorehouses($CON, $headdata["req_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
                  else
                  {
                     $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
                     $itemsts     = getItemStorehouses($CON, $headdata["req_shop_id"], $itemlistpos[0]["item_id"], "item");
                  }
                  if(count($itemsts))
                  {
                     foreach(array_keys($itemsts) AS $itemstid)
                     {
                        $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["req_shop_id"], $itemstid, $posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
                        ?>
                        <option value="<?=$itemstid?>"
                        <?php if($posdata[$x]["item_st_id"] == $itemstid) echo "selected"?>>
                           <?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)
                        </option>
                        <?php
                     }
                  }
               }
               ?>
            </select>
         </td>
         <td class="content_row" align="right">
            <input type="text" class="text" style="width:75px;text-align:right" <?=$rdlo?>
            name="item_costprice_netto_<?=$x?>" id="item_costprice_netto_<?=$x?>"
            value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_costprice_netto"], $numberlim)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "720")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="130" id="idx_fin_button">
         <?php
         printButton("Generar Ajuste", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.submit(); }", "tick-circle-frame");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <?php
}
?>