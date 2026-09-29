<?php
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["docto_title"]    = trim(addslashes($_REQUEST["docto_title"]));
   $_REQUEST["docto_number"]   = (int)$_REQUEST["docto_number"];
   $_REQUEST["docto_comment"]  = (int)$_REQUEST["docto_comment"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into tran_docs_types
               (docto_title,  docto_crtusr, docto_crtdat)
               VALUES
               ('{$_REQUEST["docto_title"]}', 
               {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from tran_docs_types
                  where
                  docto_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
         $_REQUEST["id"] = $thisid;
      }
   }
   else
   {
      $sql = " update tran_docs_types
               set
               docto_title   = '{$_REQUEST["docto_title"]}',
               docto_updusr  = {$_SESSION["user_id"]},
               docto_upddat  = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar tipo de documentos";
}
else
{
   $title = "Cambiar tipo de documentos";

   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from tran_docs_types t1
            LEFT OUTER JOIN user t2 ON t1.docto_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.docto_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $types = $CON->select($sql);
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="idx_doctipo" class="fokusfirst" onsubmit="return checkform(new Array(this.docto_title))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box1", "650")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos del tipo de documentos</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row" colspan="3">
      <input type="text" class="text" name="docto_title" style="width:506px" value="<?=$types[0]["docto_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($types[0]["docto_crtusr"] != "") echo "{$types[0]["crt_firstname"]} {$types[0]["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($types[0]["docto_crtusr"] != "") echo displayDate($types[0]["docto_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($types[0]["docto_updusr"] != "") echo "{$types[0]["upd_firstname"]} {$types[0]["upd_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($types[0]["docto_updusr"] != "") echo displayDate($types[0]["docto_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td align="left" width="130" style="padding-right:5px">
      <ul class="postnav">
         <a href="index.php?mid=<?=$_REQUEST["mid"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
      </ul>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <ul class="postnav_del">
            <a href="javascript: deactivateFormChange()" onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=del&id=<?=$_REQUEST["id"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
         </ul>
      </td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: deactivateFormChange()" onclick="submitForm(document.idx_doctipo)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_doctipo');" ?>