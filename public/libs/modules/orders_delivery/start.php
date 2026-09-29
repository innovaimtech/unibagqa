<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $_REQUEST["mid"] = "748";
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON, false, true);
   ?>
   <script language="JavaScript">
      function checkOpenInvcNotes(custid)
      {
         if(document.getElementById('dlv_order_based_0').checked ||
            document.getElementById('dlv_order_based_2').checked)
         {
            $.get('/libs/modules/orders_delivery/checkcustomer.docs.php?invc=1&notes=1&custid=' +custid, function(data){
               $("#div_notecheck").html(data);
            });
         }
         else
            $("#div_notecheck").html('');
      }
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
         var xurl = './libs/modules/orders/searchcust.fancy.php'
         var keyCode = ('which' in event) ? event.which : event.keyCode;
         if(keyCode == 112)
            showFancybox(xurl, 'iframe', 1000, 450, 'auto');
      }

      function detectEvent2 (event)
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
      onsubmit="if(this.dlv_order_based_1.checked)
                   return checkform(new Array(this.dlv_order_id, this.dlv_delivery_date));
                else if(this.dlv_order_based_0.checked || this.dlv_order_based_2.checked)
                   return checkform(new Array(this.cust_id_0, this.dlv_delivery_date));
                else
                   return checkform(new Array(this.supplier_id_0, this.dlv_delivery_date));"
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
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Agregar guia de despacho</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "650", 0)?>
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
            $selshopcc++;
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
            <input type="radio" name="dlv_order_based" id="dlv_order_based_1" value="1" checked
            onclick="document.getElementById('cust_search').value='';
                     document.getElementById('cust_id_0').options.length=0;
                     document.all.idx_custsel.style.display='none';
                     document.all.idx_taxsel.style.display='none';
                     document.all.idx_ordersel.style.display='';
                     document.all.idx_suppsel.style.display='none';
                     document.getElementById('order_search').focus();
                     checkOpenInvcNotes('0')">
            A cliente: Basado en Confirmación de compra
            <br>
            <input type="radio" name="dlv_order_based" id="dlv_order_based_0" value="0"
            onclick="document.getElementById('cust_search').value='';
                     document.getElementById('cust_id_0').options.length=0;
                     document.all.idx_custsel.style.display='';
                     document.all.idx_taxsel.style.display='';
                     document.all.idx_ordersel.style.display='none';
                     document.all.idx_suppsel.style.display='none';
                     document.getElementById('cust_search').focus();
                     checkOpenInvcNotes(document.getElementById('cust_id_0').value)">
            A cliente: Manual
            <br>
            <input type="radio" name="dlv_order_based" id="dlv_order_based_2" value="2"
            onclick="document.getElementById('cust_search').value='';
                     document.getElementById('cust_id_0').options.length=0;
                     document.all.idx_custsel.style.display='';
                     document.all.idx_taxsel.style.display='none';
                     document.all.idx_ordersel.style.display='none';
                     document.all.idx_suppsel.style.display='none';
                     document.getElementById('cust_search').focus();
                     checkOpenInvcNotes(document.getElementById('cust_id_0').value)">
            A cliente: Traslado sin valor comercial
            <br>
            <input type="radio" name="dlv_order_based" id="dlv_order_based_3" value="3"
            onclick="document.getElementById('cust_search').value='';
                     document.getElementById('cust_id_0').options.length=0;
                     document.all.idx_custsel.style.display='none';
                     document.all.idx_ordersel.style.display='none';
                     document.all.idx_taxsel.style.display='';
                     document.all.idx_suppsel.style.display='';
                     document.getElementById('supplier_search').focus();
                     checkOpenInvcNotes('0')">
            Al proveedor: Manual
            <br>
            <input type="radio" name="dlv_order_based" id="dlv_order_based_4" value="4"
            onclick="document.getElementById('cust_search').value='';
                     document.getElementById('cust_id_0').options.length=0;
                     document.all.idx_custsel.style.display='none';
                     document.all.idx_ordersel.style.display='none';
                     document.all.idx_taxsel.style.display='none';
                     document.all.idx_suppsel.style.display='';
                     document.getElementById('supplier_search').focus();
                     checkOpenInvcNotes('0')">
            Al proveedor: Traslado sin valor comercial
         </td>
      </tr>
      <tr id="idx_ordersel">
         <td class="content_rowl" valign="top"><b>Confirm. de compra *</b></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td width="155">
                  <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)"
                  name="order_search" id="order_search" value="<?=$_REQUEST["order_search"]?>"
                  onblur="markfield(this,1); document.all.idxifrsrc.src='./libs/modules/orders_delivery/searchorder.php?company_id=<?=$_REQUEST["company_id"]?>&shop_id=<?=$_REQUEST["shop_id"]?>&search=' +this.value;">

                  <?php
                  $_SESSION["JSEXEC"] .= ";document.all.idxifrsrc.src='./libs/modules/orders_delivery/searchorder.php?company_id={$_REQUEST["company_id"]}&shop_id={$_REQUEST["shop_id"]}';";
                  ?>
               </td>
               <td>
                  <select class="text" name="dlv_order_id" style="width:340px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  </select>
               </td>
            </tr>
            <tr style="display:none">
               <td style="padding-top:5px" class="content_row_clear">
                  <input type="checkbox" class="checkbox" name="dlv_order_otherclient" value="1"
                  onclick="if(this.checked) $('#dlv_order_otherclient_sel').show(); else $('#dlv_order_otherclient_sel').hide();">
                  Utilizar otro cliente
               </td>
               <td style="padding-top:5px" class="content_row_clear">
                  <input type="checkbox" class="checkbox" name="dlv_order_noitemload" value="1">
                  No cargar productos
               </td>
            </tr>
            <tr id="dlv_order_otherclient_sel" style="display:none">
               <td>
                  <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="cust_search_order" id="cust_search_order" value=""
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/orders/searchcust.php?fromdlv=1&rowcount=1' +'&search=' +this.value;"
                  onkeyup="detectEvent(event)">
               </td>
               <td>
                  <select class="text" name="cust_id_1" id="cust_id_1" style="width:340px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                  </select>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      <tr id="idx_custsel" style="display:none">
         <td class="content_rowl"><b>Cliente *</b></td>
         <td class="content_row">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td width="160">
                  <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="cust_search" id="cust_search" value="<?=$_REQUEST["cust_search"]?>"
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/orders/searchcust.php?fromdlv=1&rowcount=0' +'&search=' +this.value;"
                  onkeyup="detectEvent(event)">
               </td>
               <td>
                  <select class="text" name="cust_id_0" id="cust_id_0" style="width:340px"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)"
                  onchange="checkOpenInvcNotes(this.value)">
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
                  onkeyup="detectEvent2(event)">
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
      <tr>
         <td class="content_rowl">Fecha *</td>
         <td class="content_row">
            <input type="text" style="width:80px" id="dlv_delivery_date" name="dlv_delivery_date"
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date('d.m.Y')?>">
         </td>
      </tr>
      <tr id="idx_taxsel" style="display:none">
         <td class="content_rowl">IVA *</td>
         <td class="content_row">
            <input type="radio" name="dlv_taxes" value="1" checked> <b class="msg_save_ok">CON IVA</b>
            <input type="radio" name="dlv_taxes" value="0"> <b class="msg_save_err">SIN IVA</b>
         </td>
      </tr>
      <?php
   }
   ?>
   <tr style="display:none">
      <td class="content_rowl">Montos *</td>
      <td class="content_row">
         <input type="radio" name="dlv_isinvcbrutto" value="0" checked>Netos</b>
         <input type="radio" name="dlv_isinvcbrutto" value="1">Brutos</b>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "650",0)?>
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
   <div id="div_notecheck"></div>
   <br>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
   if(!(int)$_REQUEST["shop_id"] && count($companies) == 1 && count($shops) > 0 && $companies != false && $shops != false)
   {
      $_SESSION["JSEXEC"] .= ";if(document.all.shop_id.options.length == 1){submitForm(document.xform_shp);}";
   }
   ?>
   <?php
}