<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_SESSION["shipments"]["filter_status"] = "1";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $_REQUEST["mid"] = "10072";
   $_REQUEST["exec"] = "editshipment";
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.id, t1.sord_number, t2.supp_company
            from supplier_order t1
            LEFT OUTER JOIN supplier t2 ON t1.sord_supplier_id = t2.id
            where
            t1.sord_status          IN (2,3) and
            t1.sord_company_id      = {$_REQUEST["company_id"]} and
            t1.sord_shop_id         = {$_REQUEST["shop_id"]} and
            t1.sord_order_shipped   = 0
            order by t1.id desc";
   $preorders = $CON->select($sql);

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
   <form action="index.php" method="post" class="fokusfirst" name="xform_shp"
   <?php
   if((int)$_REQUEST["shop_id"])
   {  ?>
      onsubmit="if(this.shp_supporder_based_1.checked)
                   return checkform(new Array(this.shp_supporder_id, this.shp_delivery_date));
                else
                   return checkform(new Array(this.supplier_id_0, this.shp_delivery_date));"
      <?php
   }
   ?>>
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <?php
   if((int)$_REQUEST["shop_id"])
   {  ?>
      <input type="hidden" name="subexec" value="create">
      <?php
   }
   else
   {  ?>
      <input type="hidden" name="subexec" value="refresh">
      <?php
   }
   ?>
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <input type="hidden" name="seldoctype" value="<?=$_REQUEST["seldoctype"]?>">
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="130">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Datos de guia de despacho</td>
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
   <?php
   if((int)$_REQUEST["shop_id"])
   {  ?>
      <tr>
         <td class="content_rowl" valign="top">Tipo</td>
         <td class="content_row">
            <input type="radio" name="shp_supporder_based" id="shp_supporder_based_1" value="1" checked
            onclick="document.all.idx_suppsel.style.display='none';
                     document.all.idx_ordersel.style.display='';
                     document.all.idx_suppdoc.style.display='';
                     document.getElementById('order_search').focus()">
            Del proveedor: Basado en Orden de compra
            <br>
            <input type="radio" name="shp_supporder_based" id="shp_supporder_based_0" value="0"
            onclick="document.all.idx_suppsel.style.display='';
                     document.all.idx_ordersel.style.display='none';
                     document.all.idx_suppdoc.style.display='';
                     document.getElementById('supplier_search').focus()">
            Del proveedor: Manual
         </td>
      </tr>
      <tr id="idx_ordersel">
         <td class="content_rowl"><b>Orden de compra *</b></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td width="155">
                  <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)"
                  name="order_search" id="order_search" value="<?=$_REQUEST["order_search"]?>"
                  onblur="markfield(this,1); document.all.idxifrsrc.src='./libs/modules/shipments/searchorder.php?company_id=<?=$_REQUEST["company_id"]?>&shop_id=<?=$_REQUEST["shop_id"]?>&search=' +this.value;">

                  <?php
                  $_SESSION["JSEXEC"] .= ";document.all.idxifrsrc.src='./libs/modules/shipments/searchorder.php?company_id={$_REQUEST["company_id"]}&shop_id={$_REQUEST["shop_id"]}';";
                  ?>
               </td>
               <td>
                  <select class="text" name="shp_supporder_id" style="width:340px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  </select>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr id="idx_suppsel" style="display:none">
         <td class="content_rowl"><b>Proveedor *</b></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td width="155">
                  <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)"
                  name="supplier_search" id="supplier_search" value="<?=$_REQUEST["supplier_search"]?>"
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
         </td>
      </tr>
      <tr id="idx_suppdoc">
         <td class="content_rowl">Numero de guia</td>
         <td class="content_row">
            <input type="text" style="width:150px" name="shp_supplier_docnum" class="text"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Fecha *</td>
         <td class="content_row">
            <input type="text" style="width:80px" id="shp_delivery_date" name="shp_delivery_date"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date('d.m.Y')?>">
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "650")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         if((int)$_REQUEST["shop_id"])
            printButton($_LANG["FORM"]["BUTTON"][10], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_shp)", "arrow");
         else
            printButton($_LANG["FORM"]["BUTTON"][8], "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_shp)", "arrow");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <br><br>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
   if(!(int)$_REQUEST["shop_id"] && count($companies) == 1 && count($shops) > 0 && $companies != false && $shops != false)
   {
      $_SESSION["JSEXEC"] .= ";if(document.all.shop_id.options.length == 1){submitForm(document.xform_shp);}";
   }
   ?>
   <?php
}