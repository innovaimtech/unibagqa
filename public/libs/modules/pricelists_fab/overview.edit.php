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
   $title = "Cambiar lista de precios";
else
   $title = "Agregar lista de precios";
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
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "cookies");
      ?>
   </td>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "amounts")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Cantidades", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=amounts&id={$_REQUEST["id"]}", "", "counter-reset");
      }
      ?>
   </td>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "increment")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Configurar incrementos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=increment&id={$_REQUEST["id"]}", "", "block");
      }
      ?>
   </td>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "prices")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Configurar precios", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=prices&id={$_REQUEST["id"]}", "", "calculator");
      }
      ?>
   </td>
   <td width="20%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "itemconfig")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Productos predefinidos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=itemconfig&id={$_REQUEST["id"]}", "", "clipboard-list");
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
elseif($_REQUEST["subcatexec"] == "prices")
   require_once("data.prices.php");
elseif($_REQUEST["subcatexec"] == "amounts")
   require_once("data.amounts.php");
elseif($_REQUEST["subcatexec"] == "increment")
   require_once("data.increment.php");
elseif($_REQUEST["subcatexec"] == "itemconfig")
   require_once("data.itemconfig.php");

?>