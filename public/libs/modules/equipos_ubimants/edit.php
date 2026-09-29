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
   $_REQUEST["ubim_title"]      = trim(addslashes($_REQUEST["ubim_title"]));

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into equipos_ubimants
               (ubim_title, ubim_crtusr, ubim_crtdat)
               VALUES
               ('{$_REQUEST["ubim_title"]}', {$_SESSION["user_id"]}, {$currtme})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from equipos_ubimants
                  where
                  ubim_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];

         $_REQUEST["id"] = $thisid; 
      }
   }
   else
   {
      $sql = " update equipos_ubimants
               set
               ubim_title   = '{$_REQUEST["ubim_title"]}',
               ubim_updusr  = {$_SESSION["user_id"]},
               ubim_upddat  = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $sql = " delete from equipos_ubimants_types
               where
               ubimid = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      foreach($_REQUEST["equipotypeids"] AS $equipotypeid)
      {
         $sql = " insert into equipos_ubimants_types
                  (ubimid, typeid)
                  VALUES
                  ({$_REQUEST["id"]}, {$equipotypeid})";
         $CON->no_result($sql);
      }
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar ubicaciones de mantención";
}
else
{
   $title = "Cambiar ubicaciones de mantención";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from equipos_ubimants t1
            LEFT OUTER JOIN user t2 ON t1.ubim_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.ubim_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $type_ant = $CON->select($sql);

   $sql = " select *
            from equipos_ubimants_types
            where
            ubimid = {$_REQUEST["id"]} ";
   $ubimants_types = $CON->select($sql);
   foreach($ubimants_types AS $ubimants_type)
      $_SELEQTYPES[$ubimants_type["typeid"]] = 1;
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
<form action="index.php" method="post" name="idx_giro" class="fokusfirst" 
onsubmit="return checkform(new Array(this.ubim_title))">
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
   <td class="content_tbl_header" colspan="2">Datos</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="ubim_title" style="width:510px" value="<?=$type_ant[0]["ubim_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<?php
if($type_ant[0]["ubim_crtusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
      <td class="content_row"><?php if($type_ant[0]["ubim_crtusr"] != "") echo "{$type_ant[0]["crt_firstname"]} {$type_ant[0]["crt_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
      <td class="content_row"><?php if($type_ant[0]["ubim_crtusr"] != "") echo displayDate($type_ant[0]["ubim_crtdat"])?>&nbsp;</td>
   </tr>
   <?php
}
if($type_ant[0]["ubim_updusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
      <td class="content_row"><?php if($type_ant[0]["ubim_updusr"] != "") echo "{$type_ant[0]["upd_firstname"]} {$type_ant[0]["upd_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
      <td class="content_row"><?php if($type_ant[0]["ubim_updusr"] != "") echo displayDate($type_ant[0]["ubim_upddat"])?>&nbsp;</td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*
            from equipo_type t1
            where
            t1.type_ant_status > 0
            order by t1.type_ant_title ";
   $equipo_types = $CON->select($sql);
   ?>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="20">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header">Activar</td>
      <td class="content_tbl_header">Tipo máquina</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($equipo_types) && $equipo_types != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center">
            <input type="checkbox" name="equipotypeids[]" value="<?=$equipo_types[$x]["id"]?>"
            <?if((int)$_SELEQTYPES[$equipo_types[$x]["id"]]) echo "checked"?>>
         <td class="content_row"><?=$equipo_types[$x]["type_ant_title"]?></td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
?>
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