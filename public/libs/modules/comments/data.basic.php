<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2017 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["com_name"]      = trim(addslashes($_REQUEST["com_name"]));
   $_REQUEST["com_desc"]      = trim(addslashes($_REQUEST["com_desc"]));
   $_REQUEST["com_atrib_cc"]  = (int)$_REQUEST["com_atrib_cc"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into tran_comments
               (com_name, com_desc, com_crtusr, com_crtdat, com_atrib_cc)
               VALUES
               ('{$_REQUEST["com_name"]}', '{$_REQUEST["com_desc"]}',
                 {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["com_atrib_cc"]} )";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'thisid'
                  from tran_comments
                  where
                  com_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);

         ?>
         <script language="JavaScript">
            location.href = 'index.php?mid=977&exec=edit&id=<?=$thisid[0]["thisid"]?>';
         </script>
         <?php
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update tran_comments
               set
               com_name         = '{$_REQUEST["com_name"]}',
               com_desc         = '{$_REQUEST["com_desc"]}',
               com_updusr       = {$_SESSION["user_id"]},
               com_upddat       = {$currtme},
               com_atrib_cc     = {$_REQUEST["com_atrib_cc"]}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $sql = " delete from tran_comments_cats
               where
               com_id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      foreach($_REQUEST["catids"] AS $catid)
      {
         $sql = " insert into tran_comments_cats
                  (com_id, cat_id)
                  VALUES
                  ({$_REQUEST["id"]}, {$catid})";
         $CON->no_result($sql);
      }
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from tran_comments t1
            LEFT OUTER JOIN user t2 ON t1.com_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.com_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $tran_comments = $CON->select($sql);

   $sql = " select *
            from tran_comments_cats
            where
            com_id = {$_REQUEST["id"]}";
   $selcats = $CON->select($sql);
   foreach($selcats AS $selcat)
      $_SELCATS[$selcat["cat_id"]] = 1;

   /*
   $sql = " select distinct t2.cat_id
            from tran_comments t1
            INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
            where
            t1.com_status > 0 and
            t1.id != {$_REQUEST["id"]}";
   $blockedcatids = $CON->select($sql);
   foreach($blockedcatids AS $blockedcatid)
      $_BLKCATS[$blockedcatid["cat_id"]] = 1;
   */
}

if($_REQUEST["clearData"] != "")
{
   $sql = " update tran_comments_vals
            set
            add_status  = 0
            where
            id          = {$_REQUEST["clearData"]}";
   $CON->no_result($sql);

   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_cust"
onsubmit="return checkform(new Array(this.com_name))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="125">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="com_name" type="text" class="text" style="width:100%" value="<?=$tran_comments[0]["com_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="com_desc" class="text" style="width:100%; height:80px"><?=stripslashes($tran_comments[0]["com_desc"])?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top"></td>
   <td class="content_row">
      <label>
         <input type="checkbox" name="com_atrib_cc" value="1" <?= ($tran_comments[0]["com_atrib_cc"] == '1') ? 'checked' : '' ?>>
         Característica será atributo en Confirmación de compra         
      </label>
   </td>

</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($tran_comments[0]["com_crtusr"] != "") echo "{$tran_comments[0]["crt_firstname"]} {$tran_comments[0]["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($tran_comments[0]["com_crtusr"] != "") echo displayDate($tran_comments[0]["com_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($tran_comments[0]["com_updusr"] != "") echo "{$tran_comments[0]["upd_firstname"]} {$tran_comments[0]["upd_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl" height="24"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($tran_comments[0]["com_updusr"] != "") echo displayDate($tran_comments[0]["com_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?php
if($_REQUEST["id"] != "")
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from tran_comments_vals
            where
            add_com_id = {$_REQUEST["id"]} and
            add_status > 0
            order by add_order, add_name";
   $campos = $CON->select($sql);

   printButton("Agregar Campo", "postnav_save", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=add&id={$_REQUEST["id"]}", "", "plus", 150);
   ?>
   <br>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="120">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Resumen de campos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader" align="center"><?=$_LANG["MODULE"]["CUST"][40]?></td>
   </tr>
   <?php
   for($x = 0; $x < count($campos) && $campos != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$campos[$x]["add_name"]?>&nbsp;</td>
         <td class="content_row" align="center">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][3], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec=add&id={$_REQUEST["id"]}&exec=edit&cid={$campos[$x]["id"]}", "", "pencil", 146);
            ?>
         </td>
      </tr>
      <?php
   }
   if(!$x)
   {  ?>
      <tr bgcolor="<?=getRowColor(0)?>">
         <td class="content_row" colspan="2" align="center" valign="middle" height="30">
            <b class="msg_save_err">No hay datos disponibles</b>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <?php
      if($_REQUEST["id"] != "")
      {
         ?>
         <td align="left" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180", 150);
            ?>
         </td>
         <td>&nbsp;</td>
         <?php
      }
      else
      {  ?>
         <td>&nbsp;</td>
         <?php
      }
      ?>
      <td align="right" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cust)", "disk-black", 150);
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
   //----------------------------------------------------------------------------------
   $sql = " select *
            from productcats
            where
            cat_status = 1
            order by cat_title";
   $pcats = $CON->select($sql);
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="25">
      <col>
      <col width="80">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Asignar familias</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Activado</td>
      <td class="content_tbl_subheader">Familia</td>
      <td class="content_tbl_subheader">ID Familia</td>
   </tr>
   <?php
   $x = 0;
   foreach($pcats AS $pcat)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center">
            <input type="checkbox" value="<?=$pcat["id"]?>" name="catids[]"
            <?php if((int)$_BLKCATS[$pcat["id"]]) echo "disabled"?>
            <?php if((int)$_SELCATS[$pcat["id"]]) echo "checked"?>>
         </td>
         <td class="content_row"><?=$pcat["cat_title"]?>&nbsp;</td>
         <td class="content_row"><?=sprintf("%03s", $pcat["id"])?></td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {
      ?>
      <td align="left" width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180", 150);
         ?>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame", 150);
         ?>
      </td>
      <?php
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cust)", "disk-black", 150);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
</center>
<br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cust');" ?>