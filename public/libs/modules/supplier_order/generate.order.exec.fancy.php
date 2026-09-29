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

$_RES = $_SESSION["supporder_gen"]["GENDATA"][$_REQUEST["suppid"]];
$companyid = $_SESSION["supporder_gen"]["sql_company"];
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
<?=Nifty_printH("box1", "400")?>
<table border="0" cellpadding="3" cellspacing="0" width="100%">
<tr bgcolor="<?=getRowColor(0)?>">
   <td class="content_row_clear" align="center" height="190">
      <?php
      $next = true;
      foreach(array_keys($_RES) AS $item_id)
      {
         foreach(array_keys($_RES[$item_id]) AS $item_type)
         {
            $hasShop = false;
            $itemshops = getItemShops($CON, $item_id, $item_type);
            foreach($itemshops AS $itemshop)
               if($itemshop["shop_id"] == $_REQUEST["shopid"])
                  $hasShop = true;
            if(!$hasShop)
            {
               if($item_type == "itemlist")
               {
                  $sql = " select t1.*
                           from itemlist t1
                           where
                           t1.id = {$item_id}";
                  $itemdata = $CON->select($sql);
                  $itemdata = $itemdata[0];
               }
               else
               {
                  $sql = " select t1.*
                           from item t1
                           where
                           t1.id = {$item_id}";
                  $itemdata = $CON->select($sql);
                  $itemdata = $itemdata[0];
               }
               ?>
               <b class="msg_save_err">El artículo<br><br><u><?=$itemdata["item_title"]?></u><br><br>no tiene el sucursal asignado</b>
               <?php
               $next = false;
            }
         }
      }

      if($next)
      {
         $showSaveMsg   = false;
         $currtme       = time();
         if(!(int)$_REQUEST["sordid"])
         {
            //----------------------------------------------------------------------------------
            $sql = " select supp_paymentid, supp_dsc_finance, supp_delivery_days
                     from supplier
                     where
                     id = {$_REQUEST["suppid"]}";
            $sord_paymentid = $CON->select($sql);
            
            $sord_supplier_dsc_finance = (float)$sord_paymentid[0]["supp_dsc_finance"];
            $sord_paymentid            = (int)$sord_paymentid[0]["supp_paymentid"];
            $delivdays                 = (int)$sord_paymentid[0]["supp_delivery_days"];
            $sord_date                 = time() + ($delivdays * 86400);
            $supptax                   = getSupplierTaxes($CON, $_REQUEST["suppid"]);

            //----------------------------------------------------------------------------------
            $dsc_pay                   = getSupplierPaymentDiscount($CON, $_REQUEST["suppid"], $sord_paymentid);
            $sord_payment_dsc          = (float)$dsc_pay["dct_scale_discount"];
            $sord_payment_dsctype      = (int)$dsc_pay["dct_scale_type"];
            
            $sord_num = createTransactionNumber($CON, $companyid, "order");

            //----------------------------------------------------------------------------------
            $sql = " insert into supplier_order
                     (sord_number, sord_supplier_id, sord_company_id, sord_shop_id, sord_date,
                      sord_title, sord_taxes, sord_paymentid, sord_payment_dsc, sord_payment_dsctype,
                      sord_supplier_dsc_finance, sord_crtdat, sord_crtusr)
                     VALUES
                     ('{$sord_num}', {$_REQUEST["suppid"]}, {$companyid},
                       {$_REQUEST["shopid"]}, {$sord_date}, 'Orden de compra {$sord_num}',
                       {$supptax}, {$sord_paymentid}, {$sord_payment_dsc}, {$sord_payment_dsctype},
                       {$sord_supplier_dsc_finance}, {$currtme}, {$_SESSION["user_id"]})";
            $res = $CON->no_result($sql);

            if($res)
            {
               $sql = " select MAX(id) 'thisid'
                        from supplier_order
                        where
                        sord_crtusr = {$_SESSION["user_id"]}";
               $sorder = $CON->select($sql);
               $_REQUEST["sordid"]   = $sorder[0]["thisid"];
               
            }
         }
         if($_REQUEST["sordid"])
         {
            //----------------------------------------------------------------------------------
            $sql = " select *
                     from supplier_order
                     where
                     id = {$_REQUEST["sordid"]}";
            $headdata = $CON->select($sql);
            $headdata = $headdata[0];

            //----------------------------------------------------------------------------------
            foreach(array_keys($_RES) AS $item_id)
            {
               foreach(array_keys($_RES[$item_id]) AS $item_type)
               {
                  $item_amount = (float)$_RES[$item_id][$item_type];
                  
                  $sql = " select item_pos
                           from supplier_order_items
                           where
                           sord_id     = {$_REQUEST["sordid"]} and
                           item_id     = {$item_id} and
                           item_type   = '{$item_type}'";
                  $item_pos = $CON->select($sql);
                  if(count($item_pos) && $item_pos != false)
                  {
                     $sql = " update supplier_order_items
                              set
                              item_amount = item_amount + {$item_amount}
                              where
                              sord_id     = {$_REQUEST["sordid"]} and
                              item_id     = {$item_id} and
                              item_type   = '{$item_type}' and
                              item_pos    = {$item_pos[0]["item_pos"]}";
                     $CON->no_result($sql);
                  }
                  else
                  {
                     //----------------------------------------------------------------------------------
                     if($item_type == "itemlist")
                     {
                        $sql = " select t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                                        t2.item_costprice_usd
                                 from itemlist t1
                                 INNER JOIN itemlist_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["sord_supplier_id"]} )
                                 INNER JOIN itemlist_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["sord_shop_id"]} )
                                 where
                                 t1.id = {$item_id} ";
                        $itemdata = $CON->select($sql);
                        $itemdata = $itemdata[0];
                     }
                     else
                     {
                        $sql = " select t1.id, t1.item_title, t1.item_number_prod, t2.item_costprice_netto, t2.item_costprice_taxes_perc,
                                        t2.item_costprice_usd
                                 from item t1
                                 INNER JOIN item_suppliers t2 ON ( t1.id = t2.item_id and t2.supplier_id = {$headdata["sord_supplier_id"]} )
                                 INNER JOIN item_shops t3 ON ( t1.id = t3.item_id and t3.shop_id = {$headdata["sord_shop_id"]} )
                                 where
                                 t1.id = {$item_id} ";
                        $itemdata = $CON->select($sql);
                        $itemdata = $itemdata[0];
                     }

                     if((int)$headdata["sord_taxes"])
                     {
                        $sql_costnetto = $itemdata["item_costprice_netto"];
                        $sql_taxesperc = $itemdata["item_costprice_taxes_perc"];
                        $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costnetto / 100 * $sql_taxesperc);
                        $sql_costprice = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_costnetto + $sql_taxes);
                     }
                     else
                     {
                        $sql_costnetto = $itemdata["item_costprice_usd"];
                        $sql_taxesperc = 0.00;
                        $sql_taxes     = 0.00;
                        $sql_costprice = $sql_costnetto;
                     }

                     //----------------------------------------------------------------------------------
                     $sql = " select count(*) 'cc'
                              from supplier_order_items
                              where
                              sord_id = {$_REQUEST["sordid"]}";
                     $ccpos = $CON->select($sql);
                     $ccpos = (int)$ccpos[0]["cc"];
                     
                     $sql = " select MAX(item_pos) 'item_pos'
                              from supplier_order_items
                              where
                              sord_id = {$_REQUEST["sordid"]}";
                     $maxpos = $CON->select($sql);
                     
                     if($ccpos > 0)
                        $maxpos = $maxpos[0]["item_pos"] +1;
                     else
                        $maxpos = 0;
                        
                     $sql = " insert into supplier_order_items
                              (sord_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                               item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes)
                              VALUES
                              ({$_REQUEST["sordid"]}, {$item_id}, {$maxpos}, {$item_amount}, '{$item_type}',
                               {$sql_costprice}, {$sql_taxesperc}, {$sql_costnetto}, {$sql_taxes})";
                     $CON->no_result($sql);
                     
                     recalcSupplierOrderItem($CON, $_REQUEST["sordid"], $item_id, $maxpos);
                  }
               }
            }
            recalcSupplierOrder($CON, $_REQUEST["sordid"]);
            $showSaveMsg = true;
         }
         
         if($showSaveMsg)
         {  ?>
            <script language="Javascript">
               parent.document.getElementById('idx_tblgen_<?=$_REQUEST["suppid"]?>').style.display = 'none';
               parent.document.getElementById('idx_spangendtl_<?=$_REQUEST["suppid"]?>').innerHTML = '<b class="msg_save_ok">ORDEN DE COMPRA GENERADO: <?=$headdata["sord_number"]?></b>';
               parent.document.getElementById('idx_spangen_<?=$_REQUEST["suppid"]?>').style.display = '';
            </script>
            <img src="/images/menu/icons/tick.png">
            <b class="msg_save_ok">ORDEN DE COMPRA GENERADO<br><br><?=$headdata["sord_number"]?><br><br></b>
            <br>
            <input class="button" type="button" value="Pinchar para ir a la Orden de Compra"
            onclick="parent.location.href='/index.php?mid=662&exec=edit&subexec=edit&id=<?=$_REQUEST["sordid"]?>'">
            <?php
         }
         else
         {  ?>
            <b class="msg_save_err">ERROR INTERNO:<br><br>No se pueden guardar los datos.<br><?=$sql?></b>
            <?php
         }
      }
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
</body>
</html>