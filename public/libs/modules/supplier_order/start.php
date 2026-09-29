<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_SESSION["supporders"]["filter_status"] = "1,2,3";
$monedas    = $CON->select("select * from parametros where tabla = 'MONEDA'");

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $_REQUEST["mid"] = "662";
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $companies  = getCompanies($CON);
   $shops      = getShops($CON, false, true);

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
      function updateSupplierData(suppid)
      {
         $("#dataload").load('./libs/modules/supplier_order/searchsupplier.jquery.php?suppid=' +suppid);
      }
   </script>
   <style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
   <script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <form action="index.php" method="post" name="xform_shp"
    onsubmit="return checkform(new Array(this.supplier_id_0, this.company_id, this.shop_id, this.delivery_date))">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="subexec" value="">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <tr>
      <td height="30"><b class="content_header">Agregar orden de compra</b></td>
      <td align="right"><?=$savemsg?></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?=Nifty_printH("box1", "650",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="130">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Datos de orden de compra</td>
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
                  onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/supplier_order/searchsupplier.php?rowcount=0' +'&search=' +this.value;"
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
   <tr id="idx_start_moneda" style="display:none">
      <td class="content_rowl">Moneda</td>
      <td class="content_row">
            <select class="text" style="width:370px" name="sord_moneda_0" id="sord_moneda_0" 
               onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($monedas as $money)
               {  ?>
                  <option value="<?=$money["codigo"]?>"
                  <?php if($money["codigo"] == $_REQUEST["sord_moneda_0"]) echo "selected"?>><?=$money["descripcion"]?></option>
                  <?php
               }
               ?>
            </select>
      </td>
   </tr>   
   <tr id="idx_start_import" style="display:none">
      <td class="content_rowl">Importación</td>
      <td class="content_row">
         <input type="hidden" name="sord_taxes" id="sord_taxes" value="1">
         <span id="idx_start_import_text"></span>
      </td>
   </tr>
   <tr id="idx_start_delivery" style="display:none">
      <td class="content_rowl">Despacho en</td>
      <td class="content_row" id="idx_start_delivery_days"></td>
   </tr>
   <tr id="idx_start_delivery_date" style="display:none">
      <td class="content_rowl">Plazo de entrega *</td>
      <td class="content_row">
         <input type="text" style="width:80px" id="delivery_date" name="delivery_date" readonly
         class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency range-low-<?=date('Y-m-d')?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="">
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">Modo</td>
      <td class="content_row">
         <input type="radio" name="sord_type" value="0" checked> Pedido normal (Unidades)<br>
         <input type="radio" name="sord_type" value="1"> Telas (conversion a Kg via ancho, longitud, gramaje)<br>
         <input type="radio" name="sord_type" value="2"> Tintas (conversion a Kg via cantidad)<br>
         <input type="radio" name="sord_type" value="3"> Bolsas (registro de datos adicionales)
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
   <br>
   <?=Nifty_printH("boxopt_b", "650", 0)?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][10], "postnav_save", "javascript: deactivateFormChange()", "document.xform_shp.subexec.value='create';submitForm(document.xform_shp)", "arrow");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <span id="dataload"></span>
   <?php
}