<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
   $_REQUEST["fabt_name"]     = trim(addslashes($_REQUEST["fabt_name"]));
   $_REQUEST["fabt_desc"]     = trim(addslashes($_REQUEST["fabt_desc"]));
   $_REQUEST["fabt_code"]     = strtoupper(trim(addslashes(str_replace("_", "", $_REQUEST["fabt_code"]))));
   $_REQUEST["fabt_tipo"]     = trim(addslashes($_REQUEST["fabt_tipo"]));

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into fabric_types
               (fabt_code, fabt_name, fabt_crtusr, fabt_crtdat, fabt_desc, fabt_tipo)
               VALUES
               ('{$_REQUEST["fabt_code"]}', '{$_REQUEST["fabt_name"]}', {$_SESSION["user_id"]},
                {$currtme}, '{$_REQUEST["fabt_desc"]}', '{$_REQUEST["fabt_tipo"]}')";
      $res = $CON->no_result($sql);

      if($res)
      {
         $_REQUEST["id"] = $_REQUEST["fabt_code"];
      }
   }
   else
   {
      $sql = " update fabric_types
               set
               fabt_name   = '{$_REQUEST["fabt_name"]}',
               fabt_desc   = '{$_REQUEST["fabt_desc"]}',
               fabt_updusr = {$_SESSION["user_id"]},
               fabt_upddat = {$currtme},
               fabt_tipo   = '{$_REQUEST["fabt_tipo"]}'
               where
               fabt_code = '{$_REQUEST["id"]}'";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar tipo de tela";
}
else
{
   $title = "Cambiar tipo de tela";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from fabric_types t1
            LEFT OUTER JOIN user t2 ON t1.fabt_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.fabt_crtusr = t3.id
            where
            t1.fabt_code = '{$_REQUEST["id"]}' ";
   $fabric = $CON->select($sql);
}

$sql = "select * from parametros where tabla = 'TIPO_TELA'";
$tipo_tela = $CON->select($sql);


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
<form action="index.php" method="post" name="idx_giro"
onsubmit="return checkform(new Array(this.fabt_code, this.fabt_name))">
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
   <td class="content_tbl_header" colspan="2">Datos del tipo de tela</td>
</tr>
<tr>
   <td class="content_rowl">Código *</td>
   <td class="content_row">
      <input type="text" class="text" name="fabt_code" maxlength="5"
      style="width:120px;<?if($_REQUEST["id"] != "") echo ";background-color:#EEEEEE"?>"
      value="<?=$fabric[0]["fabt_code"]?>" <?if($_REQUEST["id"] != "") echo "readonly"?>> (Debe ser único)
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="fabt_name" style="width:510px" value="<?=$fabric[0]["fabt_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>

<tr>
   <td class="content_rowl">Tipo de Tela *</td>
   <td class="content_row">
      <select name="fabt_tipo" class="text" style="width:510px"
              onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <?php foreach ($tipo_tela as $t) { 
             $selected = ($fabric[0]["fabt_tipo"] == $t["codigo"]) ? "selected" : ""; ?>
             <option value="<?= htmlspecialchars($t["codigo"]) ?>" <?= $selected ?>>
                 <?= htmlspecialchars($t["descripcion"]) ?>
             </option>
         <?php } ?>
      </select>
   </td>
</tr>

<tr>
   <td class="content_rowl" valign="top">Texto predefinido *</td>
   <td class="content_row">
      <textarea class="text" name="fabt_desc" style="width:510px;height:80px" value=""
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($fabric[0]["fabt_desc"])?></textarea>
   </td>
</tr>
<?php
if((int)$fabric[0]["fabt_crtusr"])
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
      <td class="content_row"><?php if((int)$fabric[0]["fabt_crtusr"]) echo "{$fabric[0]["crt_firstname"]} {$fabric[0]["crt_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
      <td class="content_row"><?php if((int)$fabric[0]["fabt_crtusr"]) echo displayDate($fabric[0]["fabt_crtdat"])?>&nbsp;</td>
   </tr>
   <?php
}
if((int)$fabric[0]["fabt_updusr"])
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
      <td class="content_row"><?php if((int)$fabric[0]["fabt_updusr"]) echo "{$fabric[0]["upd_firstname"]} {$fabric[0]["upd_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
      <td class="content_row"><?php if((int)$fabric[0]["fabt_updusr"]) echo displayDate($fabric[0]["fabt_upddat"])?>&nbsp;</td>
   </tr>
   <?php
}
?>
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
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
      if($fabric[0]["fabt_code"] != "PLA" && $fabric[0]["fabt_code"] != "TNT")
      {  ?>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   else
   {  ?>
      <td>&nbsp;</td>
      <?php
   }
   ?>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_giro)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_giro');" ?>