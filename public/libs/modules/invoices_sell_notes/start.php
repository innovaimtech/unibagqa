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
   $_REQUEST["mid"] = "755";
   require_once("overview.edit.php");
}
if($_REQUEST["subexec"] == "")
{
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON, false, true);
   $issues     = getInvoiceSellNotesIssues($CON);
   ?>
   <script language="JavaScript">
      function checkOpenInvcNotes(custid)
      {
         $.get('/libs/modules/orders_delivery/checkcustomer.docs.php?invc=1&dlvs=1&custid=' +custid, function(data){
            $("#div_notecheck").html(data);
         });
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
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <form action="index.php" method="post" name="xform_shp"
    onsubmit="return checkform(new Array(this.cust_id_0, this.company_id, this.shop_id, this.note_issueid))">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="subexec" value="create">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <tr>
      <td height="30"><b class="content_header">Agregar nota</b></td>
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
      <td class="content_tbl_header" colspan="2">Datos de nota</td>
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
      <td class="content_rowl">Cliente *</td>
      <td class="content_row">
         <?php
         if((int)$_REQUEST["cust_id_0"])
         {
            $sql = " select t1.*
                     from customer t1
                     where
                     t1.cust_status = 1 and
                     t1.id = {$_REQUEST["cust_id_0"]}";
            $customer  = $CON->select($sql);
            echo $customer[0]["cust_name"];
            ?>
            <input type="hidden" name="cust_id_0" value="<?=$_REQUEST["cust_id_0"]?>">
            <?php
         }
         else
         {  ?>
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
            <?php
            $_SESSION["JSEXEC"] .= "; document.xform_shp.cust_search.focus();";
         }
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Motivo *</td>
      <td class="content_row">
         <select class="text" style="width:500px" name="note_issueid" id="note_issueid"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($issues as $issue)
            {  ?>
               <option value="<?=$issue["id"]?>"
               <?php if($issue["id"] == $_REQUEST["note_issueid"]) echo "selected"?>><?=$issue["iss_name"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">Tipo de nota *</td>
      <td class="content_row">
         <input type="radio" name="note_type" value="1" checked
         onclick="$('#idx_contype_1').hide();$('#idx_contype_5').hide();$('#idx_contype_2').show();$('#idx_contype_6').show();document.getElementById('idx_deltype').checked=true;$('#idx_validatedocnmum').show(0);"> <?=getInvoiceBuyNoteType(1)?><br>
         <input type="radio" name="note_type" value="2"
         onclick="$('#idx_contype_1').show();$('#idx_contype_5').show();$('#idx_contype_2').hide();$('#idx_contype_6').hide();document.getElementById('idx_deltype').checked=true;$('#idx_validatedocnmum').show(0);"> <?=getInvoiceBuyNoteType(2)?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">Doc. relacionado</td>
      <td class="content_row">
         <span id="idx_contype_0"><input type="radio" name="note_type_contype" value="0" id="idx_deltype" checked
         onclick="$('#idx_validatedocnmum').show(0);"> <?=getInvoiceSellNoteConType(0)?></span>
         <span id="idx_contype_8"><input type="radio" name="note_type_contype" value="8"
         onclick="$('#idx_validatedocnmum').show(0);"> <?=getInvoiceSellNoteConType(8)?></span>
         <span id="idx_contype_1" style="display:none"><input type="radio" name="note_type_contype" value="1"
         onclick="$('#idx_validatedocnmum').hide(0);"> <?=getInvoiceSellNoteConType(1)?></span>
         <span id="idx_contype_2"><input type="radio" name="note_type_contype" value="2"
         onclick="$('#idx_validatedocnmum').hide(0);"> <?=getInvoiceSellNoteConType(2)?></span>
         <span id="idx_contype_3" style="display:none"><input type="radio" name="note_type_contype" value="3"
         onclick="$('#idx_validatedocnmum').hide(0);"> <?=getInvoiceSellNoteConType(3)?></span>
         <br>
         <span id="idx_contype_4"><input type="radio" name="note_type_contype" value="4"
         onclick="$('#idx_validatedocnmum').hide(0);"> <?=getInvoiceSellNoteConType(4)?></span>
         <span id="idx_contype_5" style="display:none"><input type="radio" name="note_type_contype" value="5"
         onclick="$('#idx_validatedocnmum').hide(0);"> <?=getInvoiceSellNoteConType(5)?></span>
         <span id="idx_contype_6"><input type="radio" name="note_type_contype" value="6"
         onclick="$('#idx_validatedocnmum').hide(0);"> <?=getInvoiceSellNoteConType(6)?></span>
         <span id="idx_contype_7"><input type="radio" name="note_type_contype" value="7"
         onclick="$('#idx_validatedocnmum').hide(0);"> <?=getInvoiceSellNoteConType(7)?></span>
      </td>
   </tr>
   <tr id="idx_validatedocnmum">
      <td class="content_rowl">Folio</td>
      <td class="content_row">
         <input type="text" class="text" name="validatedocnmum" id="validatedocnmum"
         style="width:120px">
      </td>
   </tr>
   <tr>
      <td class="content_rowl">IVA *</td>
      <td class="content_row">
         <input type="radio" name="note_taxes" value="1" checked> <b class="msg_save_ok">CON IVA</b>
         <input type="radio" name="note_taxes" value="0"> <b class="msg_save_err">SIN IVA</b>
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl">Montos *</td>
      <td class="content_row">
         <input type="radio" name="note_isinvcbrutto" value="0" checked>Netos</b>
         <input type="radio" name="note_isinvcbrutto" value="1">Brutos</b>
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
   <br>
   <div id="div_notecheck"></div>
   <br>
   <?php
}