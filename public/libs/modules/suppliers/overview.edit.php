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
   $sql = " select supp_company, supp_marketing_act
            from supplier
            where
            id = {$_REQUEST["id"]} ";
   $title = $CON->select($sql);
   $supp_marketing_act = (int)$title[0]["supp_marketing_act"];
   
   $title = "Cambiar proveedor: {$title[0]["supp_company"]}";
}
else
   $title = "Agregar proveedor";
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
   <td width="14%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos basicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "car");
      ?>
   </td>
   <td width="14%" style="padding-right:5px">
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
   <td width="14%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "discounts" || $_REQUEST["subcatexec"] == "discountsedit" || $_REQUEST["subcatexec"] == "discountseditpayment" || $_REQUEST["subcatexec"] == "discountseditvalue")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Condiciones/Compra", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=discounts&id={$_REQUEST["id"]}", "", "calculator");
      }
      ?>
   </td>
   <td width="14%" style="padding-right:5px;display:none">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "paymentmulti" || $_REQUEST["subcatexec"] == "paymentmultiedit")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Forma de Pago/Familia", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=paymentmulti&id={$_REQUEST["id"]}", "", "block");
      }
      ?>
   </td>
   <td width="14%" style="padding-right:5px;display:none">
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
   <td width="14%" style="padding-right:5px">
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
   <td width="14%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "additionalcf")
            $btype = "postnav_act";
         else
            $btype = "postnav";
         printButton("Datos Financieros", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=additionalcf&id={$_REQUEST["id"]}", "", "telephone");
      }
      ?>
   </td>
   <td width="14%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "additional1")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Contactos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=additional1&id={$_REQUEST["id"]}", "", "telephone");
      }
      ?>
   </td>
   <td width="14%" id="idx_menu_marketing" style="<?if(!$supp_marketing_act) echo "display:none"?>">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "marketing")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Rebate", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=marketing&id={$_REQUEST["id"]}", "", "currency");
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
elseif($_REQUEST["subcatexec"] == "discounts")
   require_once("data.discounts.php");
elseif($_REQUEST["subcatexec"] == "marketing")
   require_once("data.marketing.php");
elseif($_REQUEST["subcatexec"] == "discountsedit")
   require_once("data.discounts.edit.php");
elseif($_REQUEST["subcatexec"] == "discountseditpayment")
   require_once("data.discounts.payment.edit.php");
elseif($_REQUEST["subcatexec"] == "discountseditvalue")
   require_once("data.discounts.value.edit.php");
elseif($_REQUEST["subcatexec"] == "comments")
   require_once("data.comments.php");
elseif($_REQUEST["subcatexec"] == "balance")
   require_once("data.balance.php");
elseif($_REQUEST["subcatexec"] == "prices")
   require_once("data.prices.php");
elseif($_REQUEST["subcatexec"] == "paymentmulti")
   require_once("data.paymentmulti.php");
elseif($_REQUEST["subcatexec"] == "additionalcf")
   require_once("data.additionalcf.php");
?>