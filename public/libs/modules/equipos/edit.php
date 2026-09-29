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
   $_REQUEST["equipo_name"]    = trim(addslashes($_REQUEST["equipo_name"]));
   $_REQUEST["equipo_desc"]    = trim(addslashes($_REQUEST["equipo_desc"]));
   $_REQUEST["equipo_code"]    = trim(addslashes($_REQUEST["equipo_code"]));
   $_REQUEST["equipo_prod_printer_metrotype"] = trim(addslashes($_REQUEST["equipo_prod_printer_metrotype"]));
   $_REQUEST["equipo_type_id"]    = (int)$_REQUEST["equipo_type_id"];
   $_REQUEST["equipo_planta_id"]  = (int)$_REQUEST["equipo_planta_id"];
   $_REQUEST["equipo_prod_dabl"]  = (int)$_REQUEST["equipo_prod_dabl"];
   $_REQUEST["equipo_prod_serimulticolors_act"]    = (int)$_REQUEST["equipo_prod_serimulticolors_act"];
   $_REQUEST["equipo_prod_isprinter_seri"]         = (int)$_REQUEST["equipo_prod_isprinter_seri"];
   $_REQUEST["equipo_prod_isprinter_flexo"]        = (int)$_REQUEST["equipo_prod_isprinter_flexo"];
   $_REQUEST["equipo_prod_divisor_perc"]           = (float)getPrice(trim($_REQUEST["equipo_prod_divisor_perc"]), 4);

   if($_REQUEST["id"] == "")
   {
      $sql = " insert into equipo
               (equipo_name, equipo_type_id, equipo_crtusr, equipo_crtdat, equipo_desc, equipo_planta_id, equipo_prod_dabl,
                equipo_prod_serimulticolors_act, equipo_code,
                equipo_prod_isprinter_seri, equipo_prod_isprinter_flexo, equipo_prod_divisor_perc,
                equipo_prod_printer_metrotype)
               VALUES
               ('{$_REQUEST["equipo_name"]}', {$_REQUEST["equipo_type_id"]}, {$_SESSION["user_id"]},
                {$currtme}, '{$_REQUEST["equipo_desc"]}', {$_REQUEST["equipo_planta_id"]}, {$_REQUEST["equipo_prod_dabl"]},
                {$_REQUEST["equipo_prod_serimulticolors_act"]}, '{$_REQUEST["equipo_code"]}',
                {$_REQUEST["equipo_prod_isprinter_seri"]}, {$_REQUEST["equipo_prod_isprinter_flexo"]},
                {$_REQUEST["equipo_prod_divisor_perc"]}, '{$_REQUEST["equipo_prod_printer_metrotype"]}')";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'id'
                  from equipo
                  where
                  equipo_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = $thisid[0]["id"];

         $_REQUEST["id"] = $thisid;
      }
   }
   else
   {
      $sql = " update equipo
               set
               equipo_name   = '{$_REQUEST["equipo_name"]}',
               equipo_desc   = '{$_REQUEST["equipo_desc"]}', 
               equipo_code   = '{$_REQUEST["equipo_code"]}',
               equipo_type_id    = {$_REQUEST["equipo_type_id"]},
               equipo_planta_id = {$_REQUEST["equipo_planta_id"]},
               equipo_prod_dabl = {$_REQUEST["equipo_prod_dabl"]},
               equipo_prod_serimulticolors_act  = {$_REQUEST["equipo_prod_serimulticolors_act"]},
               equipo_prod_isprinter_seri       = {$_REQUEST["equipo_prod_isprinter_seri"]},
               equipo_prod_isprinter_flexo      = {$_REQUEST["equipo_prod_isprinter_flexo"]},
               equipo_prod_divisor_perc         = {$_REQUEST["equipo_prod_divisor_perc"]},
               equipo_prod_printer_metrotype    = '{$_REQUEST["equipo_prod_printer_metrotype"]}',
               equipo_updusr = {$_SESSION["user_id"]},
               equipo_upddat = {$currtme}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);
   }

   $sql = " delete from equipo_shops
            where
            equipo_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $sql = " delete from equipo_colors
            where
            equipo_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   foreach($_REQUEST["shop_act"] AS $shopid)
   {
      $sql = " insert into equipo_shops
               (equipo_id, shop_id)
               VALUES
               ({$_REQUEST["id"]}, {$shopid})";
      $res = $CON->no_result($sql);
   }
   foreach($_REQUEST["color_act"] AS $colorid)
   {
      $sql = " insert into equipo_colors
               (equipo_id, color_id)
               VALUES
               ({$_REQUEST["id"]}, {$colorid})";
      $res = $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from equipo_params
            where
            param_equipo_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "param_medida_") !== false && strpos($reqkey, "param_medida_") == 0)
      {
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);

         $param_medida  = (int)getPrice(trim($_REQUEST["param_medida_{$idx}"]));
         $param_corte   = (float)getPrice(trim($_REQUEST["param_corte_{$idx}"]),4);
         $param_z       = (int)getPrice(trim($_REQUEST["param_z_{$idx}"]));
         $param_poly28  = (float)getPrice(trim($_REQUEST["param_poly28_{$idx}"]),4);
         $param_poly17  = (float)getPrice(trim($_REQUEST["param_poly17_{$idx}"]),4);

         if($param_medida != 0 || $param_corte != 0 || $param_z != 0 || $param_poly28 != 0 || $param_poly17 != 0)
         {
            $sql = " insert into equipo_params
                     (param_equipo_id, param_medida, param_corte, param_z, param_poly28, param_poly17)
                     VALUES
                     ({$_REQUEST["id"]}, {$param_medida}, {$param_corte}, {$param_z},
                      {$param_poly28}, {$param_poly17})";
            $CON->no_result($sql);
         }
      }
   }



   $savemsg = getSaveMessage($res);
}

if($_REQUEST["id"] == "")
{
   $title = "Agregar máquina";
}
else
{
   $title = "Cambiar máquina";

   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from equipo t1
            LEFT OUTER JOIN user t2 ON t1.equipo_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.equipo_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]} ";
   $equipo = $CON->select($sql);
}

$equipos = getTiposEquipo($CON);
$plantas = getPlantas($CON);
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
onsubmit="return checkform(new Array(this.equipo_code, this.equipo_name, this.equipo_planta_id, this.equipo_type_id))">
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
   <td class="content_tbl_header" colspan="2">Datos de máquina</td>
</tr>
<tr>
   <td class="content_rowl">Tipo *</td>
   <td class="content_row">
      <select class="text" name="equipo_type_id" id="equipo_type_id" style="width:100%"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach ($equipos as $tipoAntena)
         {  ?>
            <option value="<?=$tipoAntena["id"]?>" <?php if($tipoAntena["id"] == $equipo[0]["equipo_type_id"]) echo "selected" ?>>
               <?=$tipoAntena["type_ant_title"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Planta *</td>
   <td class="content_row">
      <select class="text" name="equipo_planta_id" id="equipo_planta_id" style="width:100%"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
         foreach($plantas as $planta)
         {  ?>
            <option value="<?=$planta["id"]?>" <?php if($planta["id"] == $equipo[0]["equipo_planta_id"]) echo "selected" ?>>
               <?=$planta["planta_name"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Código *</td>
   <td class="content_row">
      <input type="text" class="text" name="equipo_code" style="width:160px" value="<?=$equipo[0]["equipo_code"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input type="text" class="text" name="equipo_name" style="width:510px" value="<?=$equipo[0]["equipo_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="equipo_desc" class="text" style="width:510px;height:130px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$equipo[0]["equipo_desc"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Producción</td>
   <td class="content_row">
      <input type="checkbox" class="checkbox" name="equipo_prod_dabl" value="1"
      <?if((int)$equipo[0]["equipo_prod_dabl"]) echo "checked"?>>
      Desactivar para producción
   </td>
</tr>
<tr>
   <td class="content_rowl">Producción</td>
   <td class="content_row">
      <input type="checkbox" class="checkbox" name="equipo_prod_serimulticolors_act" value="1"
      <?if((int)$equipo[0]["equipo_prod_serimulticolors_act"]) echo "checked"?>>
      Activar impresiones separados por color (en impresoras serigrafia)
   </td>
</tr>
<tr>
   <td class="content_rowl">Producción</td>
   <td class="content_row">
      <input type="checkbox" class="checkbox" name="equipo_prod_isprinter_seri" value="1"
      <?if((int)$equipo[0]["equipo_prod_isprinter_seri"]) echo "checked"?>>
      Es una impresora de serigrafia
   </td>
</tr>
<tr>
   <td class="content_rowl">Producción</td>
   <td class="content_row">
      <input type="checkbox" class="checkbox" name="equipo_prod_isprinter_flexo" value="1"
      <?if((int)$equipo[0]["equipo_prod_isprinter_flexo"]) echo "checked"?>>
      Es una impresora de flexografia
   </td>
</tr>
<tr>
   <td class="content_rowl">Producción</td>
   <td class="content_row">
      <input type="radio" class="checkbox" name="equipo_prod_printer_metrotype" value="mtrs/máquina"
      <?if($equipo[0]["equipo_prod_printer_metrotype"] == "" || $equipo[0]["equipo_prod_printer_metrotype"] == "mtrs/máquina") echo "checked"?>> mtrs máquina
      <input type="radio" class="checkbox" name="equipo_prod_printer_metrotype" value="mtrs lineales"
      <?if($equipo[0]["equipo_prod_printer_metrotype"] == "mtrs lineales") echo "checked"?>> mtrs lineales
   </td>
</tr>
<tr>
   <td class="content_rowl">Producción</td>
   <td class="content_row">
      <input type="text" class="text" name="equipo_prod_divisor_perc" style="width:80px"
      value="<?if((float)$equipo[0]["equipo_prod_divisor_perc"]) echo printPrice($equipo[0]["equipo_prod_divisor_perc"], 4)?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"> % (Divisor <?=$equipo[0]["equipo_prod_printer_metrotype"]?>)
   </td>
</tr>
<?php
if($equipo[0]["equipo_crtusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][14]?></td>
      <td class="content_row"><?php if($equipo[0]["equipo_crtusr"] != "") echo "{$equipo[0]["crt_firstname"]} {$equipo[0]["crt_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][15]?></td>
      <td class="content_row"><?php if($equipo[0]["equipo_crtusr"] != "") echo displayDate($equipo[0]["equipo_crtdat"])?>&nbsp;</td>
   </tr>
   <?php
}
if($equipo[0]["equipo_updusr"] != "")
{  ?>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][16]?></td>
      <td class="content_row"><?php if($equipo[0]["equipo_updusr"] != "") echo "{$equipo[0]["upd_firstname"]} {$equipo[0]["upd_lastname"]}"?>&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl"><?=$_LANG["MODULE"]["CUST"][17]?></td>
      <td class="content_row"><?php if($equipo[0]["equipo_updusr"] != "") echo displayDate($equipo[0]["equipo_upddat"])?>&nbsp;</td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF(false)?>
<br>
<?php
if((int)$_REQUEST["id"] && ((int)$equipo[0]["equipo_prod_isprinter_seri"] || (int)$equipo[0]["equipo_prod_isprinter_flexo"]))
{  ?>
   <?=Nifty_printH("box1", "650")?>
   <table cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="20%">
      <col width="20%">
      <col width="20%">
      <col width="20%">
      <col width="20%">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Registrar parámetros de corte</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="center">Medida comercial</td>
      <td class="content_tbl_subheader" align="center">Corte</td>
      <td class="content_tbl_subheader" align="center">Z (Cantidad dientes)</td>
      <td class="content_tbl_subheader" align="center">Pol.2,84mm</td>
      <td class="content_tbl_subheader" align="center">Pol.1,7mm</td>
   </tr>
   <?php
   $sql = " select *
            from equipo_params
            where
            param_equipo_id = {$_REQUEST["id"]}
            order by param_medida desc";
   $params = $CON->select($sql);
   $rowcount = count($params) +3;
   for($x = 0; $x < $rowcount; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center">
            <input type="text" class="text" style="width:80px;text-align:center"
            name="param_medida_<?=$x?>" value="<?if((int)$params[$x]["param_medida"]) echo printPrice($params[$x]["param_medida"])?>">
         </td>
         <td class="content_row" align="center">
            <input type="text" class="text" style="width:80px;text-align:center"
            name="param_corte_<?=$x?>" value="<?if((float)$params[$x]["param_medida"]) echo printPrice($params[$x]["param_corte"],4)?>">
         </td>
         <td class="content_row" align="center">
            <input type="text" class="text" style="width:80px;text-align:center"
            name="param_z_<?=$x?>" value="<?if((int)$params[$x]["param_medida"]) echo printPrice($params[$x]["param_z"])?>">
         </td>
         <td class="content_row" align="center">
            <input type="text" class="text" style="width:80px;text-align:center"
            name="param_poly28_<?=$x?>" value="<?if((float)$params[$x]["param_poly28"]) echo printPrice($params[$x]["param_poly28"],4)?>">
         </td>
         <td class="content_row" align="center">
            <input type="text" class="text" style="width:80px;text-align:center"
            name="param_poly17_<?=$x?>" value="<?if((float)$params[$x]["param_poly17"]) echo printPrice($params[$x]["param_poly17"],4)?>">
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
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