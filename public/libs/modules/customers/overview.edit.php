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
   $title = $_LANG["MODULE"]["CUST"][0];
else
   $title = $_LANG["MODULE"]["CUST"][1];
?>
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
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td style="padding-right:5px" width="25%">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos basicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "user-green"); 
      ?>
   </td>
   <td style="padding-right:5px;display:none" width="25%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "ccos")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Centros de costo", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=ccos&id={$_REQUEST["id"]}", "", "home");
      }
      ?>
   </td>
   <td style="padding-right:5px" width="25%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "comments")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Comentarios", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=comments&id={$_REQUEST["id"]}", "", "balloon-ellipsis"); 
      }
      ?>
   </td>
   <td style="padding-right:5px;display:none">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "discounts" || $_REQUEST["subcatexec"] == "discountsedit")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Descuentos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=discounts&id={$_REQUEST["id"]}", "", "calculator"); 
      }
      ?>
   </td>
   <td style="padding-right:5px;">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "delivaddr")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Despacho", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=delivaddr&id={$_REQUEST["id"]}", "", "car");
      }
      ?>
   </td>
   <td style="padding-right:5px;display:none">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "balance")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Cuenta corriente", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=balance&id={$_REQUEST["id"]}", "", "balance");
      }
      ?>
   </td>
   <td style="padding-right:5px;display:none">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "creditlimit")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Tope Credito", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=creditlimit&id={$_REQUEST["id"]}", "", "megaphone");
      }
      ?>
   </td>
   <td style="padding-right:5px;display:none">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "invoicepending")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Facturas por cobrar", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=invoicepending&id={$_REQUEST["id"]}", "", "balance");
      }
      ?>
   </td>
   <td style="padding-right:5px;display:none">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "movements")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Movimientos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=movements&id={$_REQUEST["id"]}", "", "balance");
      }
      ?>
   </td>
   <td style="padding-right:5px;display:none" id="idx_ul_prices">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "prices")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Lista de precios", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=prices&id={$_REQUEST["id"]}", "", "chart");
      }
      ?>
   </td>
   <td width="25%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "basic")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Contactos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=additional1&id={$_REQUEST["id"]}", "", "telephone");
      }
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "additional1")
   require_once("data.additional1.php");
elseif($_REQUEST["subcatexec"] == "delivaddr")
   require_once("data.delivaddr.php");
elseif($_REQUEST["subcatexec"] == "comments")
   require_once("data.comments.php");
elseif($_REQUEST["subcatexec"] == "discounts")
   require_once("data.discounts.php");
elseif($_REQUEST["subcatexec"] == "discountsedit")
   require_once("data.discounts.edit.php");
elseif($_REQUEST["subcatexec"] == "balance")
   require_once("data.balance.php");
elseif($_REQUEST["subcatexec"] == "prices")
   require_once("data.prices.php");
elseif($_REQUEST["subcatexec"] == "creditlimit")
   require_once("data.creditlimit.php");
elseif($_REQUEST["subcatexec"] == "ccos")
   require_once("data.ccos.php");
/*
elseif($_REQUEST["subcatexec"] == "invoicepending")
{
   $sql = " select *
            from customer
            where
            id = {$_REQUEST["id"]} ";
   $customermail = $CON->select($sql);
   $customermail = $customermail[0];
   $_CUSTMAILADDR = str_replace("'", "", str_replace('"', "", $customermail["cust_email"]));
   $_CUSTMAILNAME = str_replace("'", "", str_replace('"', "", $customermail["cust_name"]));
   
   $_REQUEST["_MODE"] = "customer";
   require_once("./libs/modules/stats/invoices/sell.invoices.php");
}
elseif($_REQUEST["subcatexec"] == "movements")
{
   $_REQUEST["_MODE"] = "customer";
   require_once("./libs/modules/stats/selling/customer.movements.php");
}
*/
//----------------------------------------------------------------------------------
/*
if($_REQUEST["id"] != "")
{  ?>
   <script language="JavaScript">
   <?php

   //----------------------------------------------------------------------------------
   $sql = " select cust_plid
            from customer
            where
            id = {$_REQUEST["id"]}";
   $cust_plid = $CON->select($sql);
   if(!(int)$cust_plid[0]["cust_plid"])
   {  ?>
      document.all.idx_ul_prices.style.display = 'none';
      <?php
   }
   ?>
   </script>
   <?php
}
*/
?>