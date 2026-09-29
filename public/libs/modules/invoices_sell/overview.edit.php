<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subcatexec"] == "")
   $_REQUEST["subcatexec"] = "basic";

if($_REQUEST["id"] != "")
{
   $sql = " select t1.*, t2.cust_discount_spec
            from invoices_sell t1
            LEFT OUTER JOIN customer t2 ON t1.invc_cust_id = t2.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $invoice = $CON->select($sql);

   $title = "Cambiar factura: {$invoice[0]["invc_number"]}";
}
else
   $title = "Agregar factura";


if($_REQUEST["from"] != "report")
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header"><?=$title?></b></td>
      <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
   <?=Nifty_printH("boxopt_t", "980")?>
   <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <?php
      if($_REQUEST["from"] != "report")
      {  ?>
         <td width="20%" style="padding-right:5px">
            <?php
            if($_REQUEST["subcatexec"] == "basic")
               $btype = "postnav_act";
            else
               $btype = "postnav";

            printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "cookies");
            ?>
         </td>
         <td width="20%" style="padding-right:5px;display:none" id="idx_genfactbuy">
         <?php
            if($_REQUEST["id"] != "")
            {
               if($_REQUEST["subcatexec"] == "geninvcbuy")
                  $btype = "postnav_act";
               else
                  $btype = "postnav";

               printButton("Generar Factura de compra", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=geninvcbuy&id={$_REQUEST["id"]}", "", "gear");
            }
            ?>
         </td>
         <?php
         $ndscs = getCustomerNoteDiscounts($CON, $invoice[0]["invc_cust_id"]);
         if(count($ndscs) && $ndscs != false)
         {  ?>
            <td width="20%" style="padding-right:5px">
               <?php
               if($_REQUEST["id"] != "")
               {
                  if($_REQUEST["subcatexec"] == "notediscount")
                     $btype = "postnav_act";
                  else
                     $btype = "postnav";

                  printButton("Descuento por Nota/Cred.", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=notediscount&id={$_REQUEST["id"]}", "", "calculator");
               }
               ?>
            </td>
            <?php
         }
         ?>
         
         <td width="20%" style="padding-right:5px;display:none">
            <?php
            if($_REQUEST["id"] != "")
            {
               if($_REQUEST["subcatexec"] == "calculate")
                  $btype = "postnav_act";
               else
                  $btype = "postnav";

               printButton("Condiciones de venta", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=calculate&id={$_REQUEST["id"]}", "", "calculator");
            }
            ?>
         </td>
         <?php
         if($_REQUEST["id"] != "")
         {
            if((int)$invoice[0]["cust_discount_spec"])
            {  ?>
               <td width="20%" style="padding-right:5px">
               <?php
               if($_REQUEST["subcatexec"] == "discountspec")
                  $btype = "postnav_act";
               else
                  $btype = "postnav";

               printButton("Nota adjunta", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=discountspec&id={$_REQUEST["id"]}", "", "document");
               ?>
               </td>
               <?php
            }
         }
         ?>
         <td class="content_row_clear">&nbsp;</td>
         <?php
         if($invoice[0]["invc_type"] == 1)
         {  ?>
            <td width="20%" style="padding-right:5px" id="idx_ulovw_orders_delivery">
            <?php
            if($_REQUEST["subcatexec"] == "orders_delivery")
               $btype = "postnav_act";
            else
               $btype = "postnav_save";

            printButton("Agregar guias de despacho", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=orders_delivery&id={$_REQUEST["id"]}", "", "folder-share");
            ?>
            </td>
            <?php
         }
         if($invoice[0]["invc_type"] == 2)
         {  ?>
            <td width="20%" style="padding-right:5px" id="idx_ulovw_orders">
            <?php
            if($_REQUEST["subcatexec"] == "orders")
               $btype = "postnav_act";
            else
               $btype = "postnav_save";

            printButton("Agregar confirm. compra", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=orders&id={$_REQUEST["id"]}", "", "folder-share");
            ?>
            </td>
            <?php
         }
      }
      ?>
      <td width="110" align="right" style="padding-right:5px">
         <?php
         if($_REQUEST["from"] != "report")
            $windowheight = "450";
         else
            $windowheight = "380";
            
         if($_REQUEST["id"] != "")
            printButton("Anexos", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=invoices_sell&id={$_REQUEST["id"]}', 'iframe', 850, {$windowheight}, 'auto')", "scanner--plus", 110);
         ?>
      </td>
      <?php
      if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"] && $_REQUEST["from"] != "report")
      {  ?>
         <td class="content_row_clear" width="53" style="padding-right:5px">
         <?php
         printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"]}", "", "arrow-180", 53);
         ?>
         </td>
         <?php
      }
      if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"] && $_REQUEST["from"] != "report")
      {  ?>
         <td class="content_row_clear" width="53" style="padding-right:2px">
         <?php
         printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"]}", "", "arrow", 53);
         ?>
         </td>
         <?php
      }
      ?>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "orders_delivery")
   require_once("data.orders_delivery.php");
elseif($_REQUEST["subcatexec"] == "geninvcbuy")
   require_once("data.geninvcbuy.php");
elseif($_REQUEST["subcatexec"] == "orders")
   require_once("data.orders.php");
elseif($_REQUEST["subcatexec"] == "discountspec")
   require_once("data.discountspec.php");
elseif($_REQUEST["subcatexec"] == "payment")
   require_once("./libs/modules/invoices_buy/data.payment.php");
elseif($_REQUEST["subcatexec"] == "notediscount")
   require_once("data.notediscount.php");
elseif($_REQUEST["subcatexec"] == "calculate")
{
   $_REQUEST["_CALCMODE"] = "INVOICE";
   require_once("./libs/modules/orders/data.calculate.php");
}
elseif($_REQUEST["subcatexec"] == "orders_delivery_show")
{
   $sql = " select id
            from orders_delivery
            where
            dlv_invoice_generated = {$_REQUEST["id"]}";
   $dlvid = $CON->select($sql);
   $dlvid = (int)$dlvid[0]["id"];

   $thisid           = $_REQUEST["id"];
   $_REQUEST["id"]   = $dlvid;
   require_once("./libs/modules/orders_delivery/data.basic.php");
   $_REQUEST["id"]   = $thisid;
}
?>
<script language="JavaScript">
<?php
if($_REQUEST["from"] != "report")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell
            where
            id = {$_REQUEST["id"]} ";
   $invoice = $CON->select($sql);

   $sql = " select *
            from company_shops
            where
            shop_status          > 0 and
            shop_rel_custid      = {$invoice[0]["invc_cust_id"]} and
            shop_rel_custdelivid = {$invoice[0]["invc_cust_delivid"]} and
            shop_rel_suppid      > 0
            order by shop_name";
   $genshops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   if((int)$invoice[0]["invc_status"] > 1 && count($genshops) && $genshops != false)
   {  ?>
      document.getElementById('idx_genfactbuy').style.display = '';
      <?php
   }
   
   //----------------------------------------------------------------------------------
   if($invoice[0]["invc_type"] == 1 && (int)$invoice[0]["invc_status"] > 1)
   {  ?>
      document.getElementById('idx_ulovw_orders_delivery').style.display = 'none';
      <?php
   }

   //----------------------------------------------------------------------------------
   if($invoice[0]["invc_type"] == 2 && (int)$invoice[0]["invc_status"] > 1)
   {  ?>
      document.getElementById('idx_ulovw_orders').style.display = 'none';
      <?php
   }

   //----------------------------------------------------------------------------------
   if($invoice[0]["invc_type"] != 1 && ((int)$invoice[0]["invc_status"] == 1 || !(int)$invoice[0]["invc_stockchange"]))
   {  ?>
      document.getElementById('idx_ulovw_orders_delivery_show').style.display = 'none';
      <?php
   }
}
?>
</script>