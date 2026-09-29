<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_SESSION["invoicesbuy"]["filter_status"] = "1,2,3";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $_REQUEST["mid"] = "10072";
   $_REQUEST["exec"] = "editinvoice";
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON);
   
   ?>
   <script language="JavaScript">
      function setCompanyShop(companyidx)
      {
         var obj = document.all.shop_id;
         obj.options.length = 0;
         <?php
         foreach($shops AS $shop)
         {  ?>
            if(companyidx == '<?=$shop["shop_company_id"]?>')
            {
               var newIndex   = obj.options.length;
               var newOpt     = new Option('<?=addslashes($shop["shop_name"])?>');
               newOpt.value   = '<?=$shop["id"]?>';
               obj.options[newIndex] = newOpt;
            }
            <?php
         }
         ?>
      }

      function detectEvent (event)
      {
         var xurl = './libs/modules/supplier_order/searchsupplier.fancy.php'
         var keyCode = ('which' in event) ? event.which : event.keyCode;
         if(keyCode == 112)
            showFancybox(xurl, 'iframe', 1000, 450, 'auto');
      }
   </script>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <form action="index.php" method="post" name="xform_shp"
    onsubmit="return checkform(new Array(this.supplier_id_0, this.company_id, this.shop_id, this.invc_date))">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="subexec" value="create">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="seldoctype" value="<?=$_REQUEST["seldoctype"]?>">
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="130">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Datos de factura</td>
   </tr>
   <tr>
      <td class="content_rowl">Empresa *</td>
      <td class="content_row">
         <?php
         if((int)$_REQUEST["company_id"])
         {
            $sql = " select t1.*
                     from company_data t1
                     where
                     t1.company_status = 1 and
                     t1.id = {$_REQUEST["company_id"]}";
            $company  = $CON->select($sql);
            echo $company[0]["company_short"];
            ?>
            <input type="hidden" name="company_id" value="<?=$_REQUEST["company_id"]?>">
            <?php
         }
         else
         {  ?>
            <select class="text" name="company_id" style="width:500px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)"
            onchange="setCompanyShop(this.value)">
               <?php
               foreach($companies AS $company)
               {  ?>
                  <option value="<?=$company["id"]?>"
                  <?php if($company["id"] == $_REQUEST["company_id"]) echo "selected"?>><?=$company["company_short"]?></option><?php
               }
               ?>
            </select>
            <?php
            $_SESSION["JSEXEC"] .= "; setCompanyShop({$companies[0]["id"]}); ";
         }
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Sucursal *</td>
      <td class="content_row">
         <?php
         if((int)$_REQUEST["shop_id"])
         {
            $sql = " select t1.*
                     from company_shops t1
                     where
                     t1.shop_status = 1 and
                     t1.id = {$_REQUEST["shop_id"]}";
            $shop  = $CON->select($sql);
            echo $shop[0]["shop_name"];
            ?>
            <input type="hidden" name="shop_id" value="<?=$_REQUEST["shop_id"]?>">
            <?php
         }
         else
         {  ?>
            <select class="text" name="shop_id" style="width:500px"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            </select>
            <?php
         }
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Proveedor *</td>
      <td class="content_row">
         <?php
         if((int)$_REQUEST["supplier_id_0"])
         {
            $sql = " select t1.*
                     from supplier t1
                     where
                     t1.supp_status = 1 and
                     t1.id = {$_REQUEST["supplier_id_0"]}";
            $supplier  = $CON->select($sql);
            echo $supplier[0]["supp_company"];
            ?>
            <input type="hidden" name="supplier_id_0" value="<?=$_REQUEST["supplier_id_0"]?>">
            <?php
         }
         else
         {  ?>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td width="160">
                  <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="supplier_search" value="<?=$_REQUEST["supplier_search"]?>"
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/items/searchsupplier.php?rowcount=0' +'&search=' +this.value;"
                  onkeyup="detectEvent(event)">
               </td>
               <td>
                  <select class="text" name="supplier_id_0" style="width:340px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  </select>
               </td>
            </tr>
            </table>
            <?php
            $_SESSION["JSEXEC"] .= "; document.xform_shp.supplier_search.focus();";
         }
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">Tipo de factura *</td>
      <td class="content_row">
         <span id="idx_invc_type_1"><input type="radio" name="invc_type" id="invc_type1" value="1" checked> <?=getInvoiceBuyType(1)?><br></span>
         <span id="idx_invc_type_2"><input type="radio" name="invc_type" id="invc_type2" value="2"> <?=getInvoiceBuyType(2)?><br></span>
         <span id="idx_invc_type_3"><input type="radio" name="invc_type" id="invc_type3" value="3"> <?=getInvoiceBuyType(3)?></span>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Fecha de factura *</td>
      <td class="content_row">
         <input type="text" style="width:80px" id="invc_date" name="invc_date"
         class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="">
      </td>
   </tr>
   <tr>
      <td class="content_rowl">IVA *</td>
      <td class="content_row">
         <input type="radio" name="invc_taxes" id="invc_taxes_1" value="1" checked> <b class="msg_save_ok">CON IVA</b>
         <input type="radio" name="invc_taxes" id="invc_taxes_0" value="0"> <b class="msg_save_err">SIN IVA</b>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Via contenedor</td>
      <td class="content_row">
         <input type="checkbox" name="invc_is_contenedorproduct_act" value="1"
         onclick="if(this.checked)
         {
            $('#idx_invc_type_1, #idx_invc_type_3').hide(0);
            $('#invc_type2').attr('checked', true);
            $('#invc_taxes_0').attr('checked', true);
         }
         else
         {
            $('#idx_invc_type_1, #idx_invc_type_3').show(0);
            $('#invc_type1').attr('checked', true);
            $('#invc_taxes_1').attr('checked', true);
         }">
         Activar, si la mercaderia viene via un contenedor (no registra stock)
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <br>
   <?=Nifty_printH("boxopt_b", "650")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][10], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_shp)", "arrow");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <?php
}