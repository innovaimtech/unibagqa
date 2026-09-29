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
   $title = "Cambiar transportista";
else
   $title = "Agregar transportista";
?>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="modulo" value="<?=$_REQUEST["modulo"]?>">
<?=Nifty_printH("boxopt_t", "980")?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "home");
      ?>
   </td>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "vehiculos")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Vehiculos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=vehiculos&id={$_REQUEST["id"]}", "", "car--arrow");
      }
      ?>
   </td>
   <td width="20%" style="padding-right:5px">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "chofer")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Choferes", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=chofer&id={$_REQUEST["id"]}", "", "user");
      }
      ?>
   </td>
   <td width="20%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "rango")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Rangos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=rango&id={$_REQUEST["id"]}", "", "currency");
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
if($_REQUEST["subcatexec"] == "vehiculos")
   require_once("data.vehiculos.php");
if($_REQUEST["subcatexec"] == "chofer")
   require_once("data.chofer.php");
if($_REQUEST["subcatexec"] == "rango")
   require_once("data.rango.php");
?>