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
   $_REQUEST["jorn_name"]    = trim(addslashes($_REQUEST["jorn_name"]));
   $_REQUEST["jorn_order"]   = (int)$_REQUEST["jorn_order"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into turnos_jornadas
               (jorn_name, jorn_crtusr, jorn_crtdat, jorn_order)
               VALUES
               ('{$_REQUEST["jorn_name"]}', {$_SESSION["user_id"]}, {$currtme},
                 {$_REQUEST["jorn_order"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from turnos_jornadas
                  where
                  jorn_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];

         $_REQUEST["id"] = $thisid;
      }
   }
   else
   {
      $sql = " update turnos_jornadas
               set
               jorn_name   = '{$_REQUEST["jorn_name"]}',
               jorn_order  = {$_REQUEST["jorn_order"]},
               jorn_updusr = {$_SESSION["user_id"]},
               jorn_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar Jornada";

   $sql = " select MAX(jorn_order) 'jorn_order'
            from turnos_jornadas
            where
            jorn_status > 0";
   $maxorder = $CON->select($sql);
   $business[0]["jorn_order"] = (int)$maxorder[0]["jorn_order"]+1;
   
}
else
{
   $title = "Cambiar Jornada";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from turnos_jornadas t1
            LEFT OUTER JOIN user t2 ON t1.jorn_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.jorn_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $business = $CON->select($sql);
}

?>
<script type="text/javascript" src="./libs/jscripts/jscolor/jscolor.js"></script>
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
onsubmit="return checkform(new Array(this.jorn_name))">
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
   <td class="content_tbl_header" colspan="2">Datos de tipo</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="jorn_name" style="width:100%" value="<?=$business[0]["jorn_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Orden *</td>
   <td class="content_row">
      <input type="text" class="text" name="jorn_order" style="width:60px;text-align:center" value="<?=$business[0]["jorn_order"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<?php
if($business[0]["jorn_crtusr"] != "")
{  ?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
	   <td class="content_row"><?php if($business[0]["jorn_crtusr"] != "") echo "{$business[0]["crt_firstname"]} {$business[0]["crt_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
	   <td class="content_row"><?php if($business[0]["jorn_crtusr"] != "") echo displayDate($business[0]["jorn_crtdat"])?>&nbsp;</td>
	</tr>
	<?php
}
if($business[0]["jorn_updusr"] != "")
{	?>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
	   <td class="content_row"><?php if($business[0]["jorn_updusr"] != "") echo "{$business[0]["upd_firstname"]} {$business[0]["upd_lastname"]}"?>&nbsp;</td>
	</tr>
	<tr>
	   <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
	   <td class="content_row"><?php if($business[0]["jorn_updusr"] != "") echo displayDate($business[0]["jorn_upddat"])?>&nbsp;</td>
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