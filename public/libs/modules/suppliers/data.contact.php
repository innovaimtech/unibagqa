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
      $sql = " delete from supplier_contacts
               where
               id          = {$_REQUEST["cid"]} and
               add_supplier_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }

   $_REQUEST["add_rut"]                = trim(addslashes($_REQUEST["add_rut"]));
   $_REQUEST["add_website"]            = trim(addslashes($_REQUEST["add_website"]));
   $_REQUEST["add_cellphone"]          = trim(addslashes($_REQUEST["add_cellphone"]));
   $_REQUEST["add_phone"]              = trim(addslashes($_REQUEST["add_phone"]));
   $_REQUEST["add_email"]              = trim(addslashes($_REQUEST["add_email"]));
   $_REQUEST["add_city"]               = trim(addslashes($_REQUEST["add_city"]));
   $_REQUEST["add_postcode"]           = trim(addslashes($_REQUEST["add_postcode"]));
   $_REQUEST["add_street"]             = trim(addslashes($_REQUEST["add_street"]));
   $_REQUEST["add_lastname"]           = trim(addslashes($_REQUEST["add_lastname"]));
   $_REQUEST["add_firstname"]          = trim(addslashes($_REQUEST["add_firstname"]));

   $sql = " insert into supplier_contacts
            (add_supplier_id, add_firstname, add_lastname, add_street, add_postcode, add_city,
             add_email, add_phone, add_cellphone, add_website, add_rut)
            VALUES
            ({$_REQUEST["id"]}, '{$_REQUEST["add_firstname"]}', '{$_REQUEST["add_lastname"]}', '{$_REQUEST["add_street"]}',
             '{$_REQUEST["add_postcode"]}', '{$_REQUEST["add_city"]}', '{$_REQUEST["add_email"]}',
             '{$_REQUEST["add_phone"]}', '{$_REQUEST["add_cellphone"]}', '{$_REQUEST["add_website"]}',
             '{$_REQUEST["add_rut"]}')";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from supplier_contacts
               where
               add_supplier_id = {$_REQUEST["id"]}";
      $thisid = $CON->select($sql);
      $thisid = (int)$thisid[0]["thisid"];

      if($_REQUEST["add_pos"] != "")
      {
         $sql = " update supplier_contacts
                  set
                  add_pos     = {$_REQUEST["add_pos"]}
                  where
                  add_supplier_id = {$_REQUEST["id"]} and
                  id          = {$thisid}";
         $CON->no_result($sql);
      }
      else
      {
         $sql = " select count(id) 'idcount'
                  from supplier_contacts
                  where
                  add_supplier_id = {$_REQUEST["id"]} and
                  id          != {$thisid}";
         $idcount = $CON->select($sql);
         $idcount = (int)$idcount[0]["idcount"];
   
         $sql = " update supplier_contacts
                  set
                  add_pos     = {$idcount}
                  where
                  add_supplier_id = {$_REQUEST["id"]} and
                  id          = {$thisid}";
         $CON->no_result($sql);
      }
   }

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from supplier_contacts
               where
               add_supplier_id = {$_REQUEST["id"]}";
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
            from supplier_contacts
            where
            id          = {$_REQUEST["cid"]} and
            add_supplier_id = {$_REQUEST["id"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}
else
{
   $data["add_city"] = "Santiago de Chile";
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_supplier"
onsubmit="return checkform(new Array(this.add_firstname, this.add_lastname))">
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
         <td class="content_tbl_header" colspan="2">Datos del contacto I</td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row">
            <input name="add_firstname" type="text" class="text" style="width:365px" value="<?=$data["add_firstname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Apellido *</td>
         <td class="content_row">
            <input name="add_lastname" type="text" class="text" style="width:365px" value="<?=$data["add_lastname"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">RUT</td>
         <td class="content_row">
            <input name="add_rut" type="text" class="text" style="width:100px" value="<?=$data["add_rut"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Direcci�n</td>
         <td class="content_row">
            <input name="add_street" type="text" class="text" style="width:365px" value="<?=$data["add_street"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Ciudad</td>
         <td class="content_row">
            <input name="add_city" type="text" class="text" style="width:365px" value="<?=$data["add_city"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
   </td>
   <td></td>
   <td valign="top">
      <?=Nifty_printH("box2", "100%")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="140">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="3">Datos del contacto II</td>
      </tr>
      <tr>
         <td class="content_rowl">Email</td>
         <td class="content_row">
            <input name="add_email" type="text" class="text" style="width:325px" value="<?=$data["add_email"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Tel�fono</td>
         <td class="content_row">
            <input name="add_phone" type="text" class="text" style="width:325px" value="<?=$data["add_phone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Celular</td>
         <td class="content_row">
            <input name="add_cellphone" type="text" class="text" style="width:325px" value="<?=$data["add_cellphone"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Sitio web</td>
         <td class="content_row">
            <input name="add_website" type="text" class="text" style="width:325px" value="<?=$data["add_website"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" height="25">&nbsp;</td>
         <td class="content_row">&nbsp;<td>
      </tr>
      </table>
      <?=Nifty_printF()?>
   </td>
</tr>
</table>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=additional1", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=additional1&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
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