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
   $title = "Cambiar familia";
else
   $title = "Agregar familia";
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
<div style="display:none">
<?=Nifty_printH("boxopt_t", "980")?>
<table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="25%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&subexec={$_REQUEST["subexec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "block");
      ?>
   </td>
   <!--
   <td width="25%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "discounts")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Condiciones de venta", $btype, "index.php?mid={$_REQUEST["mid"]}&subexec={$_REQUEST["subexec"]}&subcatexec=discounts&id={$_REQUEST["id"]}", "", "calculator");
      }
      ?>
   </td>
   -->
   <td width="75%"></td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
</div>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "discounts")
   require_once("data.discounts.php");
?>