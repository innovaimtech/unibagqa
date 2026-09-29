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
   $_REQUEST["type_ant_title"]      = trim(addslashes($_REQUEST["type_ant_title"]));
   $_REQUEST["type_ant_flexo_act"]  = (int)$_REQUEST["type_ant_flexo_act"];
   $_REQUEST["type_ant_seri_act"]   = (int)$_REQUEST["type_ant_seri_act"];
   $_REQUEST["type_ant_prod_dabl"]  = (int)$_REQUEST["type_ant_prod_dabl"];
   $_REQUEST["type_createstock_act"]  = (int)$_REQUEST["type_createstock_act"];
   $_REQUEST["type_ant_inpmedidas_act"]  = (int)$_REQUEST["type_ant_inpmedidas_act"];

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into equipo_type
               (type_ant_title, type_ant_crtusr, type_ant_crtdat,
                type_ant_flexo_act, type_ant_seri_act, type_ant_prod_dabl,
                type_createstock_act, type_ant_inpmedidas_act)
               VALUES
               ('{$_REQUEST["type_ant_title"]}', {$_SESSION["user_id"]}, {$currtme},
                {$_REQUEST["type_ant_flexo_act"]}, {$_REQUEST["type_ant_seri_act"]},
                {$_REQUEST["type_ant_prod_dabl"]}, {$_REQUEST["type_createstock_act"]},
                {$_REQUEST["type_ant_inpmedidas_act"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from equipo_type
                  where
                  type_ant_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];

         $_REQUEST["id"] = $thisid; 
      }
   }
   else
   {
      $sql = " update equipo_type
               set
               type_ant_title   = '{$_REQUEST["type_ant_title"]}',
               type_ant_flexo_act = {$_REQUEST["type_ant_flexo_act"]},
               type_ant_seri_act  = {$_REQUEST["type_ant_seri_act"]},
               type_ant_prod_dabl = {$_REQUEST["type_ant_prod_dabl"]},
               type_createstock_act = {$_REQUEST["type_createstock_act"]},
               type_ant_inpmedidas_act = {$_REQUEST["type_ant_inpmedidas_act"]},
               type_ant_updusr  = {$_SESSION["user_id"]},
               type_ant_upddat  = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar tipo";
}
else
{
   $title = "Cambiar tipo";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from equipo_type t1
            LEFT OUTER JOIN user t2 ON t1.type_ant_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.type_ant_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $type_ant = $CON->select($sql);
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
onsubmit="return checkform(new Array(this.type_ant_title))">
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
      <input type="text" class="text" name="type_ant_title" style="width:510px" value="<?=$type_ant[0]["type_ant_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Tipo Impresión *</td>
   <td class="content_row">
      <input type="checkbox" name="type_ant_flexo_act" value="1"
      <?if((int)$type_ant[0]["type_ant_flexo_act"]) echo "checked"?>> Flexografia
      <input type="checkbox" name="type_ant_seri_act" value="1"
      <?if((int)$type_ant[0]["type_ant_seri_act"]) echo "checked"?>> Serigrafia
   </td>
</tr>
<tr>
   <td class="content_rowl">Stock</td>
   <td class="content_row">
      <input type="checkbox" name="type_createstock_act" value="1"
      <?if((int)$type_ant[0]["type_createstock_act"]) echo "checked"?>>
      Cantidades registradas aumentan inventario (p.e. en embalaje)
   </td>
</tr>
<tr>
   <td class="content_rowl">Producción</td>
   <td class="content_row">
      <input type="checkbox" name="type_ant_prod_dabl" value="1"
      <?if((int)$type_ant[0]["type_ant_prod_dabl"]) echo "checked"?>>
      Desactivar para producción
   </td>
</tr>
<tr>
   <td class="content_rowl">Medidas</td>
   <td class="content_row">
      <input type="checkbox" name="type_ant_inpmedidas_act" value="1"
      <?if((int)$type_ant[0]["type_ant_inpmedidas_act"]) echo "checked"?>>
      Activar ingreso de medidas en el alistamiento
   </td>
</tr>
<?php
if($type_ant[0]["type_ant_crtusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
      <td class="content_row"><?php if($type_ant[0]["type_ant_crtusr"] != "") echo "{$type_ant[0]["crt_firstname"]} {$type_ant[0]["crt_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
      <td class="content_row"><?php if($type_ant[0]["type_ant_crtusr"] != "") echo displayDate($type_ant[0]["type_ant_crtdat"])?>&nbsp;</td>
   </tr>
   <?php
}
if($type_ant[0]["type_ant_updusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
      <td class="content_row"><?php if($type_ant[0]["type_ant_updusr"] != "") echo "{$type_ant[0]["upd_firstname"]} {$type_ant[0]["upd_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
      <td class="content_row"><?php if($type_ant[0]["type_ant_updusr"] != "") echo displayDate($type_ant[0]["type_ant_upddat"])?>&nbsp;</td>
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