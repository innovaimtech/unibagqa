<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["ccom"] == "save")
{
   
   $currtme = time();

   if($_REQUEST["cid"] != "")
   {
      $sql = " delete from supplier_contacts_financieros
               where
               id          = {$_REQUEST["cid"]} and
               supplier_cf_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   $_REQUEST["supplier_cf_codigo_bco"] = trim(addslashes($_REQUEST["supplier_cf_codigo_bco"]));
   $_REQUEST["supplier_cf_tipocta"]    = trim(addslashes($_REQUEST["supplier_cf_tipocta"]));
   $_REQUEST["supplier_cf_ctacte"]     = trim(addslashes($_REQUEST["supplier_cf_ctacte"]));



   $sql = " insert into supplier_contacts_financieros
            (supplier_cf_id, 
             supplier_cf_codigo_bco, 
             supplier_cf_tipocta, 
             supplier_cf_ctacte)
            VALUES
            ({$_REQUEST["id"]},
            '{$_REQUEST["supplier_cf_codigo_bco"]}', 
            '{$_REQUEST["supplier_cf_tipocta"]}', 
            '{$_REQUEST["supplier_cf_ctacte"]}')";
 
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from supplier_contacts_financieros
               where
               supplier_cf_id = {$_REQUEST["id"]}";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["thisid"];

      if($_REQUEST["add_pos"] != "")
      {
         $sql = " update supplier_contacts_financieros
                  set
                  supplier_cf_pos     = {$_REQUEST["supplier_cf_pos"]}
                  where
                  supplier_cf_id = {$_REQUEST["id"]} and
                  id          = {$thisid}";
         $CON->no_result($sql);
      }
      else
      {
         $sql = " select count(id) 'idcount'
                  from supplier_contacts_financieros
                  where
                  supplier_cf_id = {$_REQUEST["id"]} and
                  id          != {$thisid}";
         $idcount = $CON->select($sql);
         $idcount = (int)$idcount[0]["idcount"];
   
         $sql = " update supplier_contacts_financieros
                  set
                  supplier_cf_pos     = {$idcount}
                  where
                  supplier_cf_id = {$_REQUEST["id"]} and
                  id          = {$thisid}";
         $CON->no_result($sql);
      }
   }

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from supplier_contacts_financieros
               where
               supplier_cf_id = {$_REQUEST["id"]}";
      $thisid = $CON->select($sql);
      $_REQUEST["cid"] = $thisid[0]["thisid"];
   }

   $savemsg = getSaveMessage($res);

   $sql = " update supplier
            set
            supp_updusr = {$_SESSION["user_id"]},
            supp_upddat = {$currtme}
            where
            id          = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

if($_REQUEST["cid"] != "")
{
   $sql = " select *
            from supplier_contacts_financieros
            where
            id          = {$_REQUEST["cid"]} and
            supplier_cf_id = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}

$bancos      = getBancos($CON);
$tipocuentas = getTipoCuentas($CON);

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_supplier"
onsubmit="return checkform(new Array(this.supplier_cf_codigo_bco, this.supplier_cf_tipocta))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="add_pos" value="<?=$data["add_pos"]?>">
<table cellpadding="0" border="0" cellspacing="0" width="980" style="table-layout:fixed">
<colgroup>
   <col width="500" valign="top">
   <col width="15">
   <col valign="top">
</colgroup>
<tr>
   <td valign="top">
      <?=Nifty_printH("box1", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="120">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Datos Financieros</td>
      </tr>
      <tr>
         <td class="content_rowl">Banco *</td>
         <td class="content_row">
            <select class="text" style="width:305px" name="supplier_cf_codigo_bco" id="supplier_cf_codigo_bco"
             onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php 
               foreach($bancos as $banco) 
               {
                  ?>
                  <option value="<?=$banco["codigo"]?>"
                  <?php if($banco["codigo"] == $data["supplier_cf_codigo_bco"]) echo "selected"?>><?=$banco["descripcion"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tipo de Cuenta *</td>
         <td class="content_row">
            <select class="text" style="width:305px" name="supplier_cf_tipocta" id="supplier_cf_tipocta"
             onmousedown="markfield(this,0)" onblur="markfield(this,1)">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php 
               foreach($tipocuentas as $tipocuenta) 
               {
                  ?>
                  <option value="<?=$tipocuenta["codigo"]?>"
                  <?php if($tipocuenta["codigo"] == $data["supplier_cf_codigo_bco"]) echo "selected"?>><?=$tipocuenta["descripcion"]?></option>
                  <?php
               }
               ?>
            </select>
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Numero de Cuenta</td>
         <td class="content_row">
            <input name="supplier_cf_ctacte" type="text" class="text" style="width:100px" value="<?=$data["supplier_cf_ctacte"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td></td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=additionalcf", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=additionalcf&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_supplier)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_supplier');" ?>