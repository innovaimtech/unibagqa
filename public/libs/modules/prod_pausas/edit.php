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
   $_REQUEST["pause_name"]      = trim(addslashes($_REQUEST["pause_name"]));
   $_REQUEST["pause_code"]      = trim(addslashes($_REQUEST["pause_code"]));
   $_REQUEST["pause_clasifica"] = trim(addslashes($_REQUEST["pause_clasifica"]));

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into prod_pause_types
               (pause_name, pause_crtusr, pause_crtdat, pause_code, pause_clasifica)
               VALUES
               ('{$_REQUEST["pause_name"]}', {$_SESSION["user_id"]}, {$currtme}, '{$_REQUEST["pause_code"]}', '{$_REQUEST["pause_clasifica"]}')";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from prod_pause_types
                  where
                  pause_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];
         $_REQUEST["id"] = $thisid;
      }
   }
   else
   {
      $sql = " update prod_pause_types
               set
               pause_name   = '{$_REQUEST["pause_name"]}',
               pause_code   = '{$_REQUEST["pause_code"]}',
               pause_updusr = {$_SESSION["user_id"]},
               pause_upddat = {$currtme},
               pause_clasifica = '{$_REQUEST["pause_clasifica"]}'
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar pausa";
}
else
{
   $title = "Cambiar pausa";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from prod_pause_types t1
            LEFT OUTER JOIN user t2 ON t1.pause_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.pause_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $business = $CON->select($sql);
}

/* tabla de parametros */
$sql = " select * from parametros where tabla = 'CLASIFICA'";
$clasificas = $CON->select($sql);

?>
<table border="0" cellpadding="0" cellspacing="0" width="822">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="idx_giro" class="fokusfirst" 
onsubmit="return checkform(new Array(this.pause_code, this.pause_name))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<?=Nifty_printH("box1", "822")?>
<table cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos</td>
</tr>
<tr>
   <td class="content_rowl">Código *</td>
   <td class="content_row">
      <input type="text" class="text" name="pause_code" style="width:160px" value="<?=$business[0]["pause_code"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="pause_name" style="width:100%" value="<?=$business[0]["pause_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Clasificacion</td>
   <td class="content_row">
      <select class="text" style="width:280px" name="pause_clasifica" id="pause_clasifica" 
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
            foreach($clasificas as $clasifi)
            {
               ?>
                  <option value="<?=$clasifi["codigo"]?>"
                  <?php if($clasifi["codigo"] == $business[0]["pause_clasifica"]) echo "selected"?>><?=$clasifi["descripcion"]?></option>
               <?php
            }
         ?>
      </select>
   </td>
</tr>

<?php
if($business[0]["pause_crtusr"] != "")
{  ?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
	   <td class="content_row"><?php if($business[0]["pause_crtusr"] != "") echo "{$business[0]["crt_firstname"]} {$business[0]["crt_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
	   <td class="content_row"><?php if($business[0]["pause_crtusr"] != "") echo displayDate($business[0]["pause_crtdat"])?>&nbsp;</td>
	</tr>
	<?php
}
if($business[0]["pause_updusr"] != "")
{	?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
	   <td class="content_row"><?php if($business[0]["pause_updusr"] != "") echo "{$business[0]["upd_firstname"]} {$business[0]["upd_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
	   <td class="content_row"><?php if($business[0]["pause_updusr"] != "") echo displayDate($business[0]["pause_upddat"])?>&nbsp;</td>
	</tr>
	<?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?=Nifty_printH("boxopt_b", "822")?>
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
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.idx_giro)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('idx_giro');" ?>