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
   $title = "Cambiar empresa";
else
   $title = "Agregar empresa";
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
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "building");
      ?>
   </td>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "invc")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Documentos tributarios ", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=invc&id={$_REQUEST["id"]}", "", "document");
      }
      ?>
   </td>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "shops")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Sucursales", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=shops&id={$_REQUEST["id"]}", "", "home");
      }
      ?>
   </td>
   <td width="20%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "pix")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Logo", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=pix&id={$_REQUEST["id"]}", "", "image");
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
elseif($_REQUEST["subcatexec"] == "shops")
   require_once("data.shops.php");
elseif($_REQUEST["subcatexec"] == "pix")
   require_once("data.pix.php");
elseif($_REQUEST["subcatexec"] == "invc")
   require_once("data.invc.php");
?>