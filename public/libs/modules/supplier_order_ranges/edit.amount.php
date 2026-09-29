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
   $_REQUEST["rng_amt_init"]  = getPrice($_REQUEST["rng_amt_init"]);
   $_REQUEST["rng_amt_end"]   = getPrice($_REQUEST["rng_amt_end"]);

   if($_REQUEST["cid"] == "")
   {
      $sql = " insert into supplier_order_ranges
               (rng_amt_init, rng_amt_end, rng_crtusr, rng_crtdat)
               VALUES
               ({$_REQUEST["rng_amt_init"]}, {$_REQUEST["rng_amt_end"]},
                {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $_REQUEST["cid"] = mysql_insert_id();
         $thisid = $_REQUEST["cid"];
      }
   }
   else
   {
      $sql = " update supplier_order_ranges
               set
               rng_amt_init   = {$_REQUEST["rng_amt_init"]},
               rng_amt_end    = {$_REQUEST["rng_amt_end"]},
               rng_upddat     = {$currtme},
               rng_updusr     = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["cid"]}";
      $res = $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["cid"])
   {
      $sql = " delete from supplier_order_ranges_aprobusers
               where
               rng_id = {$_REQUEST["cid"]}";
      $CON->no_result($sql);

      foreach($_REQUEST["uids"] AS $uid)
      {
         $sql = " insert into supplier_order_ranges_aprobusers
                  (rng_id, user_id)
                  VALUES
                  ({$_REQUEST["cid"]}, {$uid})";
         $CON->no_result($sql);
      }
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["cid"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from supplier_order_ranges t1
            LEFT OUTER JOIN user t2 ON t1.rng_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.rng_crtusr = t3.id
            where
            t1.id = {$_REQUEST["cid"]}";
   $data = $CON->select($sql);
   $data = $data[0];

   $sql = " select *
            from supplier_order_ranges_aprobusers
            where
            rng_id = {$_REQUEST["cid"]}";
   $aprobuids = $CON->select($sql);
   foreach($aprobuids AS $aprobuid)
      $_APROBUIDS[$aprobuid["user_id"]] = 1;
}

$sql = " select   t1.*
         from user t1
         where
         t1.user_status >= 0
         order by t1.user_type asc, t1.user_login asc";
$users = $CON->select($sql);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_customer"
onsubmit="return checkform(new Array(this.rng_amt_init, this.rng_amt_end))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="ccom" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="cid" value="<?=$_REQUEST["cid"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="rng_pos" value="<?=$data["rng_pos"]?>">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos del Rango</td>
</tr>
<tr>
   <td class="content_rowl">Inicio *</td>
   <td class="content_row">
      $ <input name="rng_amt_init" type="text" class="text" style="width:100px"
      value="<?php if($_REQUEST["cid"] != "") echo printPrice($data["rng_amt_init"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Termino *</td>
   <td class="content_row">
      $ <input name="rng_amt_end" type="text" class="text" style="width:100px"
      value="<?php if($_REQUEST["cid"] != "") echo printPrice($data["rng_amt_end"])?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($data["rng_crtusr"] != "") echo "{$data["crt_firstname"]} {$data["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($data["rng_crtusr"] != "") echo displayDate($data["rng_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($data["rng_updusr"] != "") echo "{$data["upd_firstname"]} {$data["upd_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl" height="24"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($data["rng_updusr"] != "") echo displayDate($data["rng_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="20">
   <col>
   <col width="120">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="3">Asignar aprobadores</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Nombre</td>
   <td class="content_tbl_subheader">Tipo</td>
</tr>
<?php
//----------------------------------------------------------------------------------
for($x = 0; $x < count($users) && $users != false; $x++)
{
   if($users[$x]["user_type"] == "1")
      $disp_type = $_LANG["MODULE"]["USER"][34];
   else
      $disp_type = $_LANG["MODULE"]["USER"][33];
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row" align="center">
         <input type="checkbox" name="uids[]" value="<?=$users[$x]["id"]?>"
         <?if((int)$_APROBUIDS[$users[$x]["id"]]) echo "checked"?>>
      </td>
      <td class="content_row"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></td>
      <td class="content_row"><?=$disp_type?></td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&subcatexec=amounts", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["cid"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=amounts&id={$_REQUEST["id"]}&clearData={$_REQUEST["cid"]}')", "cross-circle-frame");
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