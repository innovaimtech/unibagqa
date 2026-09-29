<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["ccom"] == "save")
{
   $currtme = time();
   $_REQUEST["add_name"]      = trim(addslashes($_REQUEST["add_name"]));
   $_REQUEST["add_name_eng"]  = trim(addslashes($_REQUEST["add_name_eng"]));
   $_REQUEST["add_order"]     = (int)$_REQUEST["add_order"];
   $_REQUEST["add_internact"] = (int)$_REQUEST["add_internact"];
   $_REQUEST["add_onlinefilternact"] = (int)$_REQUEST["add_onlinefilternact"];
   

   if($_REQUEST["cid"] == "")
   {
      $sql = " insert into tran_comments_vals
               (add_com_id, add_name, add_name_eng, add_order, add_crtusr, add_crtdat, add_internact, add_onlinefilternact)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["add_name"]}', '{$_REQUEST["add_name_eng"]}', {$_REQUEST["add_order"]},
                {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["add_internact"]}, {$_REQUEST["add_onlinefilternact"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $savemsg = getSaveMessage($res);
      }
   }
   else
   {
      $sql = " update tran_comments_vals
               set
               add_name       = '{$_REQUEST["add_name"]}',
               add_name_eng   = '{$_REQUEST["add_name_eng"]}',
               add_order      = {$_REQUEST["add_order"]},
               add_internact  = {$_REQUEST["add_internact"]},
               add_onlinefilternact = {$_REQUEST["add_onlinefilternact"]},
               add_upddat     = {$currtme},
               add_updusr     = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["cid"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from tran_comments_vals t1
            LEFT OUTER JOIN user t2 ON t1.add_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.add_crtusr = t3.id
            where
            t1.id = {$_REQUEST["cid"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}
else
{
   $sql = " select count(*) 'cc'
            from tran_comments_vals
            where
            add_com_id = {$_REQUEST["id"]} and
            add_status > 0";
   $ccorder = $CON->select($sql);
   if(count($ccorder) && $ccorder != false)
      $data["add_order"] = (int)$ccorder[0]["cc"];
   else
      $data["add_order"] = 0;
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_customer"
onsubmit="return checkform(new Array(this.add_name, this.add_name_eng))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="add_pos" value="<?=$data["add_pos"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos del Campo</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="add_name" type="text" class="text" style="width:100%" value="<?=$data["add_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre ingles *</td>
   <td class="content_row">
      <input name="add_name_eng" type="text" class="text" style="width:100%" value="<?=$data["add_name_eng"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($data["add_crtusr"] != "") echo "{$data["crt_firstname"]} {$data["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($data["add_crtusr"] != "") echo displayDate($data["add_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($data["add_updusr"] != "") echo "{$data["upd_firstname"]} {$data["upd_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl" height="24"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($data["add_updusr"] != "") echo displayDate($data["add_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_customer)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_customer');" ?>