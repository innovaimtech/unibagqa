<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

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

   function setCompanyShopDest(companyidx)
   {
      var obj = document.all.shop_id_dest;
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
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<form action="index.php" method="post" class="fokusfirst" name="xform_str"
 onsubmit="return checkform(new Array(this.company_id, this.shop_id, this.company_id_dest, this.shop_id_dest))">
<input type="hidden" name="subexec" value="create">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<tr>
   <td height="30"><b class="content_header">Agregar traspaso</b></td>
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
   <td class="content_tbl_header" colspan="2">Origin</td>
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
   <td class="content_rowl">N° CC</td>
   <td class="content_row">
      <input type="text" class="text" style="width:150px" name="strc_order_num" id="strc_order_num"
      value="" onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off">
      (opcional)
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Destino</td>
</tr>
<tr>
   <td class="content_rowl">Empresa *</td>
   <td class="content_row">
      <select class="text" name="company_id_dest" style="width:500px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)"
      onchange="setCompanyShopDest(this.value)">
         <?php
         foreach($companies AS $company)
         {  ?>
            <option value="<?=$company["id"]?>"
            <?php if($company["id"] == $_REQUEST["company_id"]) echo "selected"?>><?=$company["company_short"]?></option><?php
         }
         ?>
      </select>
      <?php
      $_SESSION["JSEXEC"] .= "; setCompanyShopDest({$companies[0]["id"]}); ";
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Sucursal *</td>
   <td class="content_row">
      <select class="text" name="shop_id_dest" style="width:500px"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">N° CC</td>
   <td class="content_row">
      <input type="text" class="text" style="width:150px" name="strc_order_num_dest" id="strc_order_num_dest"
      value="" onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off">
      (opcional)
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][10], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_str)", "arrow", 130);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>