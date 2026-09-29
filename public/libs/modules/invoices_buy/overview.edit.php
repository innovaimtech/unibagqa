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
   $sql = " select t1.*, t2.supp_dsc_finance, t2.supp_dsc_finance_calc
            from invoices_buy t1
            INNER JOIN supplier t2 ON t1.invc_supplier_id = t2.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $invoice = $CON->select($sql);
   
   $title = "Cambiar factura: {$invoice[0]["invc_number"]}";
}
else
   $title = "Agregar factura";
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
   <?php
   if($_REQUEST["from"] != "report")
   {  ?>
      <td width="20%" style="padding-right:5px;">
         <?php
         if($_REQUEST["subcatexec"] == "basic")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Datos básicos", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=basic&id={$_REQUEST["id"]}", "", "cookies");
         ?>
      </td>
      <?php
      if(!(int)$invoice[0]["invc_is_contenedorproduct_act"])
      {  ?>
         <td width="20%" style="padding-right:5px" id="idx_ulovw_supporder">
            <?php
            if($_REQUEST["subcatexec"] == "contassign")
               $btype = "postnav_act";
            else
               $btype = "postnav";

            printButton("Asignar a contenedores", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=contassign&id={$_REQUEST["id"]}", "", "server");
            ?>
         </td>
         <?php
      }
      if($invoice[0]["invc_importation"] == 1)
      {  ?>
         <td width="20%" style="padding-right:5px" id="idx_ulovw_money">
         <?php
         if($_REQUEST["subcatexec"] == "moneyexchange")
            $btype = "postnav_act";
         else
            $btype = "postnav";

         printButton("Cambio de moneda", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=moneyexchange&id={$_REQUEST["id"]}", "", "counter");
         ?>
         </td>
         <?php
      }
      ?>
      <td class="content_row_clear">&nbsp;</td>
      <?php
      if($invoice[0]["invc_type"] == 1)
      {  ?>
         <td width="20%" style="padding-right:5px" id="idx_ulovw_shipments">
         <?php
         if($_REQUEST["subcatexec"] == "shipments")
            $btype = "postnav_act";
         else
            $btype = "postnav_save";

         printButton("Agregar guias de despacho", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=shipments&id={$_REQUEST["id"]}", "", "folder-share");
         ?>
         </td>
         <?php
      }
      if($invoice[0]["invc_type"] == 2)
      {  ?>
         <td width="20%" style="padding-right:5px" id="idx_ulovw_supporder">
         <?php
         if($_REQUEST["subcatexec"] == "supporders")
            $btype = "postnav_act";
         else
            $btype = "postnav_save";

         printButton("Agregar ordenes de compra", $btype, "index.php?mid={$_REQUEST["mid"]}&exec={$_REQUEST["exec"]}&subcatexec=supporders&id={$_REQUEST["id"]}", "", "folder-share");
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
         printButton("Anexos", "postnav", "javascript:void(0)", "showFancybox('/libs/modules/docs_management/overview.php?mode=invoices_buy&id={$_REQUEST["id"]}', 'iframe', 850, {$windowheight}, 'auto')", "scanner--plus", 110);
      ?>
   </td>
   <?php
   if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"] && $_REQUEST["from"] != "report")
   {  ?>
      <td class="content_row_clear" width="53" style="padding-right:5px">
      <?php
      printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["L"]}", "", "arrow-180", 53);
      ?>
      </td>
      <?php
   }
   if((int)$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"] && $_REQUEST["from"] != "report")
   {  ?>
      <td class="content_row_clear" width="53" style="padding-right:2px">
      <?php
      printButton("", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&id={$_SESSION[$_sesmodulename]["FLW"][$_REQUEST["id"]]["N"]}", "", "arrow", 53);
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
//----------------------------------------------------------------------------------
if($_REQUEST["subcatexec"] == "basic")
   require_once("data.basic.php");
elseif($_REQUEST["subcatexec"] == "shipments")
   require_once("data.shipments.php");
elseif($_REQUEST["subcatexec"] == "supporders")
   require_once("data.supporders.php");
elseif($_REQUEST["subcatexec"] == "moneyexchange")
   require_once("data.moneyexchange.php");
elseif($_REQUEST["subcatexec"] == "parentinvoice")
   require_once("data.parentinvoice.php");
elseif($_REQUEST["subcatexec"] == "payment")
   require_once("data.payment.php");
elseif($_REQUEST["subcatexec"] == "contassign")
   require_once("data.contassign.php");
?>
<script language="JavaScript">
<?php
if($_REQUEST["from"] != "report")
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.invc_status, t1.invc_type, t2.supp_dsc_finance
            from invoices_buy t1
            INNER JOIN supplier t2 ON t1.invc_supplier_id = t2.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $invoice = $CON->select($sql);

   //----------------------------------------------------------------------------------
   if($invoice[0]["invc_type"] == 1 && (int)$invoice[0]["invc_status"] > 1)
   {  ?>
      document.getElementById('idx_ulovw_shipments').style.display = 'none';
      <?php
   }

   //----------------------------------------------------------------------------------
   if($invoice[0]["supp_dsc_finance"] > 0.00 && (int)$invoice[0]["invc_status"] < 2)
   {
      $_SESSION["JSEXEC"] .= "$('#idx_ulovw_nc').hide();";
   }

   //----------------------------------------------------------------------------------
   if($invoice[0]["invc_type"] == 2 && (int)$invoice[0]["invc_status"] > 1)
   {  ?>
      document.getElementById('idx_ulovw_supporder').style.display = 'none';
      <?php
   }

   //----------------------------------------------------------------------------------
   if($invoice[0]["invc_type"] == 4 && (int)$invoice[0]["invc_status"] > 1)
   {  ?>
      document.getElementById('idx_ulovw_parentinvoice').style.display = 'none';
      <?php
   }
}
?>
</script>