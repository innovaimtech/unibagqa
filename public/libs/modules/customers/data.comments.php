<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["cust_notes"] = trim(addslashes($_REQUEST["cust_notes"]));
   
   $sql = " update customer
            set
            cust_notes     = '{$_REQUEST["cust_notes"]}',
            cust_updusr    = {$_SESSION["user_id"]},
            cust_upddat    = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

$sql = " select cust_notes
         from customer
         where
         id = {$_REQUEST["id"]} ";
$customer = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_cust">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="fromreq" value="<?=$_REQUEST["fromreq"]?>">
<input type="hidden" name="frommid" value="<?=$_REQUEST["frommid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Comentarios - Observaciones</td>
</tr>
<tr>
   <td class="content_rowl">
      <textarea name="cust_notes" class="text" style="width:970px; height:300px"><?=$customer[0]["cust_notes"]?></textarea>
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: submitForm(document.xform_cust)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cust');" ?>