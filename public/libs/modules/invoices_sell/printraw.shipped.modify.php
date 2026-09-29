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

session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");
require_once("../../../libs/functions.php");

//----------------------------------------------------------------------------------
if($_REQUEST["exec"] == "save")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "amtpend_") !== false && strpos($reqkey, "amtpend_") == 0)
      {
         $arr = explode("_", $reqkey);
         $act = (int)$_REQUEST[str_replace("amtpend_","deact_",$reqkey)];

         $amtpend    = getPrice($_REQUEST[$reqkey],2);
         $amtorig    = (float)$_REQUEST[str_replace("amtpend_","amtorig_",$reqkey)];
         $amtorder   = (float)$_REQUEST[str_replace("amtpend_","amtorder_",$reqkey)];
         $commpend   = trim(addslashes($_REQUEST[str_replace("amtpend_","commpend_",$reqkey)]));
         
         
         if($amtpend != $amtorig && !$act && $amtpend <= $amtorder)
         {
            
            $amtdiff = $amtorig - $amtpend;
            $sql = " update orders_items
                     set
                     item_amount_shipped = item_amount_shipped + {$amtdiff}
                     where
                     req_id      = {$arr[1]} and
                     item_id     = {$arr[3]} and
                     item_type   = '{$arr[2]}' and
                     item_pos    = {$arr[4]}";
            $CON->no_result($sql);
         }

         $sql = " update orders_items
                  set
                  item_amount_shipped_stop      = {$act},
                  item_amount_shipped_comment   = '{$commpend}'
                  where
                  req_id      = {$arr[1]} and
                  item_id     = {$arr[3]} and
                  item_type   = '{$arr[2]}' and
                  item_pos    = {$arr[4]}";
         $CON->no_result($sql);
         $res = autocloseSupplierOrder($CON, $arr[1], "order");

         if(!$res)
         {
            $currtme = time();
            $sql = " update orders
                     set
                     req_status          = 3,
                     req_order_shipped   = 0,
                     req_updusr          = {$_SESSION["user_id"]},
                     req_upddat          = {$currtme}
                     where
                     id = {$arr[1]}";
            $CON->no_result($sql);
         }
      }
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["diffMode"] == "ordersdelivery")
{
   $sql = " select t1.*
            from orders_delivery t1
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $shopid  = $headdata["dlv_shop_id"];
   $posdata = getOrderDeliveryPos($CON, $_REQUEST["id"], "prodnumber");

   for($y = 0; $y < count($posdata) && $posdata != false; $y++)
   {
      if($posdata[$y]["item_amount"] > 0 && $posdata[$y]["item_amount_shipped_orig"] < $posdata[$y]["order_amount_orig"])
      {
         $posdata[$y]["item_amount_open"]          = $posdata[$y]["order_amount_orig"] - $posdata[$y]["item_amount_shipped_orig"];
         $posdata[$y]["item_amount"]               = $posdata[$y]["order_amount_orig"];
         $posdata[$y]["item_sellprice_netto_orig"] = $posdata[$y]["item_sellprice_netto_orig"];

         $posdata[$y]["part_req_id"]               = $headdata["dlv_order_id"];
         $posdata[$y]["order_item_pos"]            = $posdata[$y]["item_order_pos"];
         $resposdata[] = $posdata[$y];
      }
   }
   $posdata = $resposdata;
}
//----------------------------------------------------------------------------------
elseif($_REQUEST["diffMode"] == "invoicessell")
{
   $sql = " select t1.*
            from invoices_sell t1
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
   $invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
   $shopid     = $headdata["invc_shop_id"];

   $resposdata = Array();
   
   if($headdata["invc_status"] == 2)
   {
      $sql = " select t3.*, t1.item_amount 'order_amount', t1.item_amount_shipped 'order_amount_shipped',
                      t1.req_id 'order_req_id', t1.item_id 'order_item_id', t1.item_pos 'order_item_pos',
                      t2.part_req_id
               from orders_items  t1
               INNER JOIN invoices_sell_parts t2            ON ( t1.req_id    = t2.part_req_id )
               LEFT OUTER JOIN invoices_sell_parts_items t3 ON ( t3.invc_id   = t2.part_invc_id and
                                                                 t3.part_id   = t2.id and
                                                                 t1.item_pos  = t3.item_order_pos and
                                                                 t1.item_id   = t3.item_id and
                                                                 t1.item_type = t3.item_type and
                                                                 t3.item_type   IN ('item', 'itemlist'))
               where
               t2.part_invc_id = {$_REQUEST["id"]}";
      $checkorderdata = $CON->select($sql);
      for($x = 0; $x < count($checkorderdata) && $checkorderdata != false; $x++)
      {
         if(!(int)$checkorderdata[$x]["invc_id"] && !(int)$checkorderdata[$x]["part_id"] && !(int)$checkorderdata[$x]["item_id"])
         {
            $orderposdata = getOrderPos($CON, $checkorderdata[$x]["order_req_id"]);
            foreach($orderposdata AS $orderposrow)
            {
               if($orderposrow["item_id"] == $checkorderdata[$x]["order_item_id"] &&
                  $orderposrow["item_pos"] == $checkorderdata[$x]["order_item_pos"])
               {
                  $temp["item_number_prod"]     = $orderposrow["item_number_prod"];
                  $temp["item_title"]           = $orderposrow["item_title"];
                  $temp["unit_name"]            = $orderposrow["unit_name"];
                  $temp["item_amount"]          = $orderposrow["item_amount"];
                  $temp["item_amount_shipped"]  = 0.00;
                  $temp["item_amount_open"]     = $orderposrow["item_amount"] - $orderposrow["item_amount_shipped"];
                  $temp["item_id"]              = $orderposrow["item_id"];
                  $temp["item_type"]            = $orderposrow["item_type"];
                  $temp["part_req_id"]          = $checkorderdata[$x]["part_req_id"];
                  $temp["order_item_pos"]       = $orderposrow["item_pos"];
                  $temp["item_amount_shipped_comment"] = $orderposrow["item_amount_shipped_comment"];

                  $sql = " select item_code
                           from {$orderposrow["item_type"]}_suppliers
                           where
                           item_id = {$orderposrow["item_id"]} and
                           item_supp_act = 1";
                  $item_code = $CON->select($sql);
                  
                  $temp["item_code"]                  = $item_code[0]["item_code"];
                  $temp["item_sellprice_netto_orig"]  = $orderposrow["item_sellprice_netto"];
                  $temp["item_sellprice_netto"]       = $orderposrow["item_sellprice_netto"];

                  if($temp["item_amount_open"] > 0.00)
                     $resposdata[] = $temp;
               }
            }
         }
      }
   }
      
   foreach($invcparts AS $invcpart)
   {
      $posdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcpart["id"], "prodnumber");
      for($y = 0; $y < count($posdata) && $posdata != false; $y++)
      {
         if($posdata[$y]["order_amount"] > 0 && $posdata[$y]["order_amount_shipped"] < $posdata[$y]["order_amount"])
         {
            $tempamt = $posdata[$y]["item_amount"];
            $tempshp = $posdata[$y]["order_amount_shipped"];
            $posdata[$y]["item_amount_shipped"] = $posdata[$y]["item_amount"];
            $posdata[$y]["item_amount"]         = $posdata[$y]["order_amount"];
            $posdata[$y]["item_amount_open"]    = $posdata[$y]["order_amount"] - $posdata[$y]["order_amount_shipped"];
            $posdata[$y]["part_req_id"]         = $invcpart["part_req_id"];
            $posdata[$y]["order_item_pos"]      = $posdata[$y]["item_order_pos"];

            $sql = " select item_code
                     from {$posdata[$y]["item_type"]}_suppliers
                     where
                     item_id = {$posdata[$y]["item_id"]} and
                     item_supp_act = 1";
            $item_code = $CON->select($sql);

            $temp["item_code"] = $item_code[0]["item_code"];
            $resposdata[] = $posdata[$y];
         }
      }
   }
   $posdata = $resposdata;

   usort($posdata, "getProdnumerCallbackRes");
}

?>
<html>
<head>
   <title>Modificación: Pendientes</title>
   <style type="text/css">
   html { height: 100%; }
   <?php $_SESSION["_PAGE"]->printStyle() ?>
   </style>
   <script language="Javascript"><?php require_once("../../../libs/jscripts/sourcen.php") ?></script>
   <script type="text/javascript" src="/libs/jscripts/jquery-1.4.2.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.mousewheel-3.0.4.pack.js"></script>
   <script type="text/javascript" src="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.pack.js"></script>
   <link rel="stylesheet" type="text/css" href="/libs/jscripts/jquery.fancybox-1.3.4/fancybox/jquery.fancybox-1.3.4.css" media="screen" />
</head>
<body class="page_content" style="background-color:#EEEEEE">
<center>
<br>
<form action="printraw.shipped.modify.php" method="post" name="xform_mod">
<input type="hidden" name="exec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="diffMode" value="<?=$_REQUEST["diffMode"]?>">
<?=Nifty_printH("box1", "860")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<tr>
   <td class="content_tbl_header" valign="top" colspan="11">Pendientes</td>
</tr>
<tr>
   <td class="content_tbl_subheader" valign="top">Nota</td>
   <td class="content_tbl_subheader" valign="top">Número</td>
   <td class="content_tbl_subheader" valign="top">Codigo/Prov</td>
   <td class="content_tbl_subheader" valign="top">Artículo</td>
   <td class="content_tbl_subheader" valign="top">Unidad</td>
   <td class="content_tbl_subheader" valign="top" align="center">Pedido</td>
   <td class="content_tbl_subheader" valign="top" align="center">Pendiente</td>
   <td class="content_tbl_subheader" valign="top" align="center">Observaciones</td>
   <td class="content_tbl_subheader" valign="top" align="center">Desact.</td>
</tr>
<?php
for($y = 0; $y < count($posdata) && $posdata != false; $y++)
{
   $sql = " select req_number
            from orders
            where
            id = {$posdata[$y]["part_req_id"]}";
   $reqnum = $CON->select($sql);

   $sql = " select item_amount_shipped_stop
            from orders_items
            where
            req_id      = {$posdata[$y]["part_req_id"]} and
            item_id     = {$posdata[$y]["item_id"]} and
            item_type   = '{$posdata[$y]["item_type"]}' and
            item_pos    = {$posdata[$y]["order_item_pos"]}";
   $item_amount_shipped_stop = $CON->select($sql);
   $item_amount_shipped_stop = (int)$item_amount_shipped_stop[0]["item_amount_shipped_stop"];
   ?>
   <tr bgcolor="<?=getRowColor($y)?>">
      <td class="content_row"><?=$reqnum[0]["req_number"]?>&nbsp;</td>
      <td class="content_row"><?=$posdata[$y]["item_number_prod"]?>&nbsp;</td>
      <td class="content_row"><?=$posdata[$y]["item_code"]?>&nbsp;</td>
      <td class="content_row"><?=$posdata[$y]["item_title"]?></td>
      <td class="content_row"><?=$posdata[$y]["unit_name"]?></td>
      <td class="content_row" align="center"><?=printPrice($posdata[$y]["item_amount"],2)?></td>
      <td class="content_row" align="center">
         <input type="text" class="text" style="width:60px;text-align:center"
         name="amtpend_<?=$posdata[$y]["part_req_id"]?>_<?=$posdata[$y]["item_type"]?>_<?=$posdata[$y]["item_id"]?>_<?=$posdata[$y]["order_item_pos"]?>"
         value="<?=printPrice($posdata[$y]["item_amount_open"],2)?>">
         <input type="hidden"
         name="amtorig_<?=$posdata[$y]["part_req_id"]?>_<?=$posdata[$y]["item_type"]?>_<?=$posdata[$y]["item_id"]?>_<?=$posdata[$y]["order_item_pos"]?>"
         value="<?=(float)$posdata[$y]["item_amount_open"]?>">
         <input type="hidden"
         name="amtorder_<?=$posdata[$y]["part_req_id"]?>_<?=$posdata[$y]["item_type"]?>_<?=$posdata[$y]["item_id"]?>_<?=$posdata[$y]["order_item_pos"]?>"
         value="<?=(float)$posdata[$y]["item_amount"]?>">
      </td>
      <td class="content_row" align="center" valign="top">
         <input type="text" class="text" style="width:82px;text-align:center"
         name="commpend_<?=$posdata[$y]["part_req_id"]?>_<?=$posdata[$y]["item_type"]?>_<?=$posdata[$y]["item_id"]?>_<?=$posdata[$y]["order_item_pos"]?>"
         value="<?=$posdata[$y]["item_amount_shipped_comment"]?>">
      </td>
      <td class="content_row" align="center" valign="top">
         <input type="checkbox" class="checkbox mchk" value="1"
         name="deact_<?=$posdata[$y]["part_req_id"]?>_<?=$posdata[$y]["item_type"]?>_<?=$posdata[$y]["item_id"]?>_<?=$posdata[$y]["order_item_pos"]?>"
         <?php if((int)$item_amount_shipped_stop) echo "checked"?>>
         <input type="hidden" name="hidedeact_<?=$posdata[$y]["part_req_id"]?>_<?=$posdata[$y]["item_type"]?>_<?=$posdata[$y]["item_id"]?>_<?=$posdata[$y]["order_item_pos"]?>" value="1">
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "860")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "/libs/modules/invoices_sell/printraw.data.php?id={$_REQUEST["id"]}&mode=preview&showDiff=1&diffMode={$_REQUEST["diffMode"]}", "", "arrow-180", 230);
      ?>
   </td>
   <td class="content_row_clear"><input type="checkbox" onclick="var xchk = this.checked; $('.mchk').each(function (){$(this).attr('checked', xchk);});"> Marcar todos los artículos</td>
   <td align="right" width="130">
      <?php
      printButton("Guardar", "postnav_save", "javascript: void(0)", "document.xform_mod.submit();", "tick-circle", 230);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
</center>
<br>
</body>
</html>