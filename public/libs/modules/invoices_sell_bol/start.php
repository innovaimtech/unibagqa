<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_SESSION["invoicessell"]["filter_status"] = "1";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $_REQUEST["mid"] = "984";
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $companies = getCompanies($CON);
   
   //----------------------------------------------------------------------------------
   $sql = " select *
            from company_shops
            where
            shop_status       = 1 and
            shop_opt_buying   = 1 and
            shop_isremote     = 0
            order by shop_name";
   $shops = $CON->select($sql);


   ?>
   <script language="JavaScript">
      function checkOpenInvcNotes(custid)
      {
         if(document.getElementById('idx_invc_type3').checked)
         {
            $.get('/libs/modules/orders_delivery/checkcustomer.docs.php?dlvs=1&custid=' +custid, function(data){
               $("#div_notecheck").html(data);
            });
         }
         else
            $("#div_notecheck").html('');
      }
      function checkOpenInvcNotesNV(reqid)
      {
         if(document.getElementById('idx_invc_type2').checked)
         {
            $.get('/libs/modules/orders_delivery/checkcustomer.docs.php?dlvs=1&reqid=' +reqid, function(data){
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
   </script>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <form action="index.php" method="post" name="xform_shp">
   <input type="hidden" name="subexec" value="create">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <tr>
      <td height="30"><b class="content_header">Agregar boleta</b></td>
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
      <td class="content_tbl_header" colspan="2">Datos de la boleta</td>
   </tr>
   <tr>
      <td class="content_rowl">Empresa *</td>
      <td class="content_row">
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
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Sucursal *</td>
      <td class="content_row">
         <select class="text" name="shop_id" style="width:500px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Cliente</td>
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <tr>
            <td width="160">
               <input type="text" class="text" style="width:150px" onfocus="markfield(this,0)" name="cust_search" id="cust_search" value=""
               onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/orders/searchcust.php?fromdlv=0&rowcount=0' +'&search=' +this.value;"
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
   <tr style="display:none">
      <td class="content_rowl">IVA *</td>
      <td class="content_row">
         <input type="radio" name="invc_taxes" value="1" checked> <b class="msg_save_ok">CON IVA</b>
         <input type="radio" name="invc_taxes" value="0"> <b class="msg_save_err">SIN IVA</b>
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl">Montos *</td>
      <td class="content_row">
         <input type="radio" name="invc_isinvcbrutto" value="0">Netos</b>
         <input type="radio" name="invc_isinvcbrutto" value="1" checked>Brutos</b>
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
         printButton($_LANG["FORM"]["BUTTON"][10], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_shp)", "arrow");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <br>
   <div id="div_notecheck"></div>
   <br>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <?php
   $_SESSION["JSEXEC"] .= ";document.xform_shp.dlv_search.focus();";
   $_SESSION["JSEXEC"] .= ";document.all.idxifrsrc.src='./libs/modules/orders_delivery/searchorder.php?company_id=' +document.xform_shp.company_id.value +'&shop_id=' +document.xform_shp.shop_id.value;";
}