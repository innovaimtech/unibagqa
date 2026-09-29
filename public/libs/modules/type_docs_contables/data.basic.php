<?php
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["typedoc_cont_nameid"]  = trim(addslashes($_REQUEST["typedoc_cont_nameid"]));
   $_REQUEST["typedoc_cont_code"]  = (int)$_REQUEST["typedoc_cont_code"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into typedoc_contables
               (typedoc_cont_nameid, typedoc_cont_code, typedoc_cont_crtusr, typedoc_cont_crtdat)
               VALUES
               ('{$_REQUEST["typedoc_cont_nameid"]}', {$_REQUEST["typedoc_cont_code"]},
               {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from typedoc_contables
                  where
                  cc_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
         $_REQUEST["id"] = $thisid;
      }
   }
   else
   {
      $sql = " update typedoc_contables
               set
               typedoc_cont_nameid  = '{$_REQUEST["typedoc_cont_nameid"]}',
               typedoc_cont_code    =  {$_REQUEST["typedoc_cont_code"]},
               typedoc_cont_updusr  =  {$_SESSION["user_id"]},
               typedoc_cont_upddat  =  {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }
   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar tipo de documentos contables";
}
else
{
   $title = "Cambiar tipo de documentos contables";

   $sql = " select t1.*, 
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from typedoc_contables t1
            LEFT OUTER JOIN user t2 ON t1.typedoc_cont_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.typedoc_cont_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $typedoc = $CON->select($sql);
   $typedoc = $typedoc[0]; 
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
<form action="index.php" method="post" name="idx_typedoc_cont" class="fokusfirst"
onsubmit="return checkform(new Array(this.typedoc_cont_nameid, this.typedoc_cont_code))">
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
   <td class="content_tbl_header" colspan="4">Datos de tipo de documentos contables</td>
</tr>
<tr>
   <td class="content_rowl">Tipo *</td>
   <td class="content_row">
      <select type="text" class="text" name="typedoc_cont_nameid" style="width:506px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <option value="1" <?if((int)$typedoc["typedoc_cont_nameid"] == 1) echo "selected"?>>Factura (manual)</option>
         <option value="2" <?if((int)$typedoc["typedoc_cont_nameid"] == 2) echo "selected"?>>Factura exenta</option>
         <option value="3" <?if((int)$typedoc["typedoc_cont_nameid"] == 3) echo "selected"?>>Factura (electr.)</option>
         <option value="4" <?if((int)$typedoc["typedoc_cont_nameid"] == 4) echo "selected"?>>Factura exenta (electr.)</option>
         <option value="5" <?if((int)$typedoc["typedoc_cont_nameid"] == 5) echo "selected"?>>Nota de credito (manual)</option>
         <option value="6" <?if((int)$typedoc["typedoc_cont_nameid"] == 6) echo "selected"?>>Nota de debito (manual)</option>
         <option value="7" <?if((int)$typedoc["typedoc_cont_nameid"] == 7) echo "selected"?>>Nota de credito (electr.)</option>
         <option value="8" <?if((int)$typedoc["typedoc_cont_nameid"] == 8) echo "selected"?>>Nota de debito (electr.)</option>
         <option value="9" <?if((int)$typedoc["typedoc_cont_nameid"] == 9) echo "selected"?>>Factura mixta</option>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Codigo *</td>
   <td class="content_row">
      <input type="text" class="text" name="typedoc_cont_code" style="width:150px" value="<?=$typedoc["typedoc_cont_code"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
   <td class="content_row"><?php if($typedoc["typedoc_cont_crtusr"] != "") echo "{$typedoc["crt_firstname"]} {$typedoc["crt_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
   <td class="content_row"><?php if($typedoc["typedoc_cont_crtusr"] != "") echo displayDate($typedoc["typedoc_cont_crtdat"])?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
   <td class="content_row"><?php if($typedoc["typedoc_cont_updusr"] != "") echo "{$typedoc["upd_firstname"]} {$typedoc["upd_lastname"]}"?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
   <td class="content_row"><?php if($typedoc["typedoc_cont_updusr"] != "") echo displayDate($typedoc["typedoc_cont_upddat"])?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td align="left" width="130" style="padding-right:5px">
         <ul class="postnav">
            <a href="index.php?mid=<?=$_REQUEST["mid"]?>"><?=$_LANG["FORM"]["BUTTON"][1]?></a>
         </ul>
      </td>
      <td>&nbsp;</td>
      <td align="right" width="130" style="padding-right:5px">
         <ul class="postnav_del">
            <a href="javascript: deactivateFormChange()" onclick="askDel('index.php?mid=<?=$_REQUEST["mid"]?>&exec=del&id=<?=$_REQUEST["id"]?>')"><?=$_LANG["FORM"]["BUTTON"][2]?></a>
         </ul>
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
      <ul class="postnav_save">
         <a href="javascript: deactivateFormChange()" onclick="submitForm(document.idx_typedoc_cont)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_typedoc_cont');" ?>