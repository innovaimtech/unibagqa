<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_SESSION["salary"]["filter_status"] = "1";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
//    $_REQUEST["mid"] = "778";
   require_once("overview.edit.php");
}
else
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from company_data
            where
            company_status = 1
            order by company_short";
   $companies = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from company_shops
            where
            shop_status       = 1
            order by shop_name";
   $shops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select t1.id, t1.user_firstname, t1.user_lastname, t2.shop_id
            from user t1
            LEFT OUTER JOIN user_shops t2 ON t1.id = t2.user_id
            where
            t1.user_status = 1
            order by t1.user_firstname, t1.user_lastname";
   $users = $CON->select($sql);

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

      function setPersons(shopid)
      {
         var obj = document.all.user_id;
         obj.options.length = 0;
         <?php
         foreach($users AS $user)
         {  ?>
            if(shopid == '<?=$user["shop_id"]?>')
            {
               var newIndex   = obj.options.length;
               var newOpt     = new Option('<?=addslashes($user["user_firstname"]." ".$user["user_lastname"])?>');
               newOpt.value   = '<?=$user["id"]?>';
               obj.options[newIndex] = newOpt;
            }
            <?php
         }
         ?>
      }
   </script>
   <table border="0" cellpadding="0" cellspacing="0" width="822">
   <form action="index.php" method="post" name="xform_salary"
    onsubmit="return checkform(new Array(this.user_id, this.company_id, this.shop_id))">
   <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="subexec" value="create">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <tr>
      <td height="30"><b class="content_header">Agregar abono</b></td>
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
      <td class="content_tbl_header" colspan="2">Datos del abono</td>
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
         <?php $_SESSION["JSEXEC"] .= "; setCompanyShop({$companies[0]["id"]}); "; ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Sucursal *</td>
      <td class="content_row">
         <select class="text" name="shop_id" style="width:500px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)"
         onchange="setPersons(this.value)">
         </select>
         <?php $_SESSION["JSEXEC"] .= "; setPersons({$shops[0]["id"]}); "; ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Persona *</td>
      <td class="content_row">
         <select class="text" name="user_id" id="user_id" style="width:500px"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         </select>
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
         printButton($_LANG["FORM"]["BUTTON"][8], "postnav", "javascript: deactivateFormChange()", "submitForm(document.xform_salary)", "arrow");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   </form>
   <?php
}