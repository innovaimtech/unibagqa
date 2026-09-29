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
   $title = "Cambiar sucursal";
else
   $title = "Agregar sucursal";

if($_REQUEST["id"] != "")
{
   $title = "Cambiar sucursal";
   
   $sql = " select t1.*
            from company_shops t1
            where
            t1.id = {$_REQUEST["id"]}";
   $tmpshop = $CON->select($sql);
   $tmpshop = $tmpshop[0];
}
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
   <td width="25%" style="padding-right:5px">
      <?php
      if($_REQUEST["subcatexec"] == "basic")
         $btype = "postnav_act";
      else
         $btype = "postnav";

      printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "home");
      ?>
   </td>
   <?php
   if((int)$tmpshop["shop_isremote"])
   {  ?>
      <td width="25%" style="padding-right:5px">
         <?php
         if($_REQUEST["id"] != "")
         {
            if($_REQUEST["subcatexec"] == "cashhours")
               $btype = "postnav_act";
            else
               $btype = "postnav";

            printButton("Horarios", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=cashhours&id={$_REQUEST["id"]}", "", "clock");
         }
         ?>
      </td>
      <td width="25%" style="padding-right:5px">
         <?php
         if($_REQUEST["id"] != "")
         {
            if($_REQUEST["subcatexec"] == "cashing")
               $btype = "postnav_act";
            else
               $btype = "postnav";

            printButton("Cajas", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=cashing&id={$_REQUEST["id"]}", "", "money");
         }
         ?>
      </td>
      <?php
   }
   ?>
   <td width="25%">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "storehouses")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Bodegas", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=storehouses&id={$_REQUEST["id"]}", "", "drawer");
      }
      ?>
   </td>
   <td width="25%" style="padding-right:5px;display:none">
      <?php
      if($_REQUEST["id"] != "")
      {
         if($_REQUEST["subcatexec"] == "puntos")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Puntos de ventas", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=puntos&id={$_REQUEST["id"]}", "", "home-network");
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
elseif($_REQUEST["subcatexec"] == "storehouses")
   require_once("data.storehouses.php");
elseif($_REQUEST["subcatexec"] == "cashing")
   require_once("data.cashing.overview.php");
elseif($_REQUEST["subcatexec"] == "puntos")
   require_once("data.puntos.php");
elseif($_REQUEST["subcatexec"] == "cashhours")
   require_once("data.cashhours.php");
?>