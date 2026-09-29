<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subcatexec"] == "edit")
{
   require_once("values.calculation.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   if($_REQUEST["subexec"] == "search")
   {
      //----------------------------------------------------------------------------------
      $_REQUEST["invc_number"]         = trim(addslashes($_REQUEST["invc_number"]));

      //----------------------------------------------------------------------------------
      $sql = " select t1.id, t1.invc_number, t1.invc_docnumber, t1.invc_date, t4.supp_company, t2.company_short,
                         t3.shop_name
               from invoices_buy t1
               LEFT OUTER JOIN company_data t2  ON t1.invc_company_id   = t2.id
               LEFT OUTER JOIN company_shops t3 ON t1.invc_shop_id      = t3.id
               LEFT OUTER JOIN supplier t4 ON t1.invc_supplier_id  = t4.id
               where
               t1.invc_status > 1 ";
               
      if($_REQUEST["invc_number"] != "")
         $sql .= " and ( t1.invc_docnumber like '%{$_REQUEST["invc_number"]}%' or
                         t1.invc_number like '%{$_REQUEST["invc_number"]}%' ) ";
      if($_REQUEST["sql_supplier"] != "")
         $sql .= " and t1.invc_supplier_id = {$_REQUEST["sql_supplier"]}";

      $sql .= " order by t1.id desc";
      $invoices = $CON->select($sql);
   }

   $suppliers  = getSuppliers($CON);
   ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Valorización de facturas</b></td>
      <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <table border="0" cellpadding="0" cellspacing="0" width="100%">
   <tr>
      <td>
         <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
         onsubmit="return checkform(new Array(this.invc_number))">
         <input type="hidden" name="subexec" value="search">
         <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
         <?=Nifty_printH("box2", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="130">
            <col>
            <col width="130">
            <col>
            <col width="160">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="8">Opciones de búsqueda</td>
         </tr>
         <tr>
            <td class="content_rowl">Factura *</td>
            <td class="content_row">
               <input name="invc_number" type="text" class="text" style="width:100px"
               value="<?=str_replace("%","*",$_REQUEST["invc_number"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </td>
            <td class="content_rowl">Proveedor</td>
            <td class="content_row">
               <select class="text" name="sql_supplier" style="width:375px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
                  <?php
                  foreach($suppliers AS $supplier)
                  {  ?>
                     <option value="<?=$supplier["id"]?>"
                     <?php if($supplier["id"] == $_REQUEST["sql_supplier"]) echo "selected"?>>
                        <?=$supplier["supp_company"]?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
            </td>
            <td class="content_row" align="right">
               <?php
               printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier");
               $_SESSION["_SUBMITBTN"] = 1;
               ?>
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
         </form>
      </td>
   </tr>
   <tr>
      <td>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="160">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader">Numero int</td>
            <td class="content_tbl_subheader">Factura</td>
            <td class="content_tbl_subheader">Fecha</td>
            <td class="content_tbl_subheader">Proveedor</td>
            <td class="content_tbl_subheader">Empresa/Sucursal</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($invoices) && $invoices != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$invoices[$x]["invc_number"]?>&nbsp;</td>
               <td class="content_row"><?=$invoices[$x]["invc_docnumber"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y', $invoices[$x]["invc_date"])?></td>
               <td class="content_row"><?=$invoices[$x]["supp_company"]?>&nbsp;</td>
               <td class="content_row"><?=$invoices[$x]["company_short"]?>: <?=$invoices[$x]["shop_name"]?></td>
               <td class="content_row" align="center">
                  <?php
                  printButton("Calcular", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&subcatexec=edit&id={$invoices[$x]["id"]}", "", "pencil");
                  ?>
               </td>
            </tr>
            <?php
         }

         if(!$x)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row" colspan="6" align="center">
                  <br>
                  <b class="msg_save_err">No hay datos disponibles.</b>
                  <br><br>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
      </td>
   </tr>
   </table>
   <?php
}