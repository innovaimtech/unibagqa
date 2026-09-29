<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------


if($_REQUEST["ccom"] == "save")
{
   $currtme = time();

   $_REQUEST["ant_desc"]         = trim(addslashes($_REQUEST["ant_desc"]));
   $_REQUEST["ant_amount"]       = getPrice(trim(addslashes($_REQUEST["ant_amount"])));
   $_REQUEST["ant_recepdate"]    = trim(addslashes($_REQUEST["ant_recepdate"]));

   $_REQUEST["ant_recepdate"] = explode(".", $_REQUEST["ant_recepdate"]);
   $_REQUEST["ant_recepdate"] = (int)mktime(date('H'), date('i'), date('s'), $_REQUEST["ant_recepdate"][1], $_REQUEST["ant_recepdate"][0], $_REQUEST["ant_recepdate"][2]);
   
   if((int)$_REQUEST["cid"])
   {
      $sql = " update orders_anticipos
               set
               ant_desc       = '{$_REQUEST["ant_desc"]}',
               ant_amount     = {$_REQUEST["ant_amount"]},
               ant_recepdate  = {$_REQUEST["ant_recepdate"]},
               ant_upddat     = {$currtme},
               ant_updusr     = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
      
      $savemsg = getSaveMessage($res);
   }
   else
   {
      $sql = " insert into orders_anticipos
               (ant_req_id, ant_desc, ant_amount, ant_recepdate, ant_crtdat, ant_crtusr)
               VALUES
               ({$_REQUEST["id"]}, '{$_REQUEST["ant_desc"]}', {$_REQUEST["ant_amount"]},
                {$_REQUEST["ant_recepdate"]}, {$currtme}, {$_SESSION["user_id"]})";
      $res = $CON->no_result($sql);
      $savemsg = getSaveMessage($res);
      if($res)
         $_REQUEST["cid"] = mysql_insert_id();
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["cid"] != "")
{
   $sql = " select t1.*, t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
            from orders_anticipos t1
            LEFT OUTER JOIN user t5             ON t1.ant_updusr           = t5.id
            LEFT OUTER JOIN user t6             ON t1.ant_crtusr           = t6.id
            where
            t1.id = {$_REQUEST["cid"]}";
   $data = $CON->select($sql);
   $data = $data[0];
}

//----------------------------------------------------------------------------------
$sql = " select count(*) 'cc'
         from invoices_sell_parts t1
         INNER JOIN invoices_sell t2 ON t1.part_invc_id = t2.id
         where
         t1.part_req_id = {$_REQUEST["id"]} and
         t2.invc_status > 0";
$hasinvcrel = $CON->select($sql);
$hasinvcrel = (int)$hasinvcrel[0]["cc"];

$_CANEDIT = true;
if($hasinvcrel)
   $_CANEDIT = false;
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_customer"
onsubmit="<?if($_CANEDIT) { ?> return checkform(new Array(this.ant_amount, this.ant_recepdate)) <?php } else { ?> return false; <?php } ?>">
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
   <td class="content_tbl_header" colspan="2">Datos Básicos</td>
</tr>
<tr>
   <td class="content_rowl">Monto *</td>
   <td class="content_row">
      <input name="ant_amount" type="text" class="text" style="width:100px" placeholder="$"
      value="<?if((int)$data["ant_amount"]) echo printPrice($data["ant_amount"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Fecha recepción ¨</td>
   <td class="content_row">
      <input type="text" style="width:80px" id="ant_recepdate" name="ant_recepdate"
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if($data["ant_recepdate"] > 0) echo date('d.m.Y', $data["ant_recepdate"]); else echo date('d.m.Y')?>">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Comentarios</td>
   <td class="content_row">
      <textarea class="text" style="width:100%; height:80px" name="ant_desc"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($data["ant_desc"])?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($data["ant_crtusr"] != "") echo "{$data["crt_firstname"]} {$data["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($data["ant_crtusr"] != "") echo displayDate($data["ant_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($data["ant_updusr"] != "") echo "{$data["upd_firstname"]} {$data["upd_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($data["ant_updusr"] != "") echo displayDate($data["ant_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
if($_CANEDIT)
{  ?>
   <?=Nifty_printH("boxopt_b", "650")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td align="left" width="130" valign="top">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=anticipos", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
      if($_REQUEST["cid"] != "")
      {  ?>
         <td align="right" width="130" style="padding-right:5px" valign="top">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=anticipos&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
            ?>
         </td>
         <?php
      }
      ?>
      <td align="right" width="130" valign="top">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_customer)", "disk-black");
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
?>
</form>
</center>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_customer');" ?>