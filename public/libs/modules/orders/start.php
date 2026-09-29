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
   $_REQUEST["mid"] = 689;
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $companies = getCompanies($CON);

   //----------------------------------------------------------------------------------
   $shops = getShops($CON, false, true);

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
         var xurl = './libs/modules/orders/searchcust.fancy.php'
         var keyCode = ('which' in event) ? event.which : event.keyCode;
         if(keyCode == 112)
            showFancybox(xurl, 'iframe', 1000, 450, 'auto');
      }
      function loadRelOrders(custid)
      {
         document.getElementById('idxifrsrc2').src='./libs/modules/orders/searchorders.php?cust_id=' +custid +'&company_id=' +$('#company_id').val() +'&shop_id=' +$('#shop_id').val();
      }
   </script>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <form action="index.php" method="post" name="xform_req"
    onsubmit="return checkform(new Array(this.cust_id_0, this.company_id, this.shop_id))">
   <input type="hidden" name="exec" value="edit">
   <input type="hidden" name="subexec" value="create">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <tr>
      <td height="30"><b class="content_header">Agregar confirmación de compra</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="130">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Datos de confirmación de compra</td>
   </tr>
   <tr>
      <td class="content_rowl">Empresa *</td>
      <td class="content_row">
         <select class="text" name="company_id" id="company_id" style="width:500px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="setCompanyShop(this.value);$('#order_id').html('');">
            <?php
            foreach($companies AS $company)
            {  ?>
               <option value="<?=$company["id"]?>">
                  <?=$company["company_short"]?>
               </option><?php
            }
            ?>
         </select>
         <?php
         $_SESSION["JSEXEC"] .= "; setCompanyShop({$companies[0]["id"]}); ";
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Sucursal *</td>
      <td class="content_row">
         <select class="text" name="shop_id" id="shop_id" style="width:500px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="$('#order_id').html('');">
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Cliente *</td>
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="160">
               <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="cust_search" id="cust_search" value="<?=$_REQUEST["cust_search"]?>"
               onblur="markfield(this,1); if(this.value != '') document.getElementById('idxifrsrc').src='./libs/modules/orders/searchcust.php?setOrders=1&rowcount=0' +'&search=' +this.value;"
               onkeyup="detectEvent(event)">
            </td>
            <td>
               <select class="text" name="cust_id_0" style="width:340px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)"
               onchange="loadRelOrders(this.value)">
               </select>
            </td>
         </tr>
         </table>
         <?php
         $_SESSION["JSEXEC"] .= "; document.xform_req.cust_search.focus();";
         ?>
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl">Conectar con Nota</td>
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="160">
               <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="order_search" id="order_search" value="<?=$_REQUEST["order_search"]?>"
               onblur="markfield(this,1); if(this.value != '') document.getElementById('idxifrsrc2').src='./libs/modules/orders/searchorders.php?search=' +this.value +'&company_id=' +$('#company_id').val() +'&shop_id=' +$('#shop_id').val()">
            </td>
            <td>
               <select class="text" name="order_id" id="order_id" style="width:340px"
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               </select>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl">Reserva *</td>
      <td class="content_row">
         <input type="radio" name="req_isreserva" value="0" checked>No</b>
         <input type="radio" name="req_isreserva" value="1">Si</b>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Montos *</td>
      <td class="content_row">
         <input type="radio" name="req_isinvcbrutto" value="0" checked>Netos</b>
         <input type="radio" name="req_isinvcbrutto" value="1">Brutos</b>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">IVA *</td>
      <td class="content_row">
         <input type="radio" name="req_taxes" value="1" checked> <b class="msg_save_ok">CON IVA</b>
         <input type="radio" name="req_taxes" value="0"> <b class="msg_save_err">SIN IVA</b>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "650")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][10], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_req)", "arrow");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <iframe id="idxifrsrc2" height="0" width="0" frameborder="0"></iframe>
   </form>
   <?php
}