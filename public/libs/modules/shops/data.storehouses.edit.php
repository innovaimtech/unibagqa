<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "save")
{
   $currtme = time();

   $_REQUEST["st_name"] = trim(addslashes($_REQUEST["st_name"]));
   $_REQUEST["st_desc"] = trim(addslashes($_REQUEST["st_desc"]));
   $_REQUEST["st_repuestos_act"]       = (int)$_REQUEST["st_repuestos_act"];
   $_REQUEST["st_unibagreserva_act"]   = (int)$_REQUEST["st_unibagreserva_act"];
   $_REQUEST["st_unibagflexo_act"]     = (int)$_REQUEST["st_unibagflexo_act"];
   $_REQUEST["st_unibagseri_act"]      = (int)$_REQUEST["st_unibagseri_act"];
   $_REQUEST["st_unibagsellador_act"]  = (int)$_REQUEST["st_unibagsellador_act"];

   //----------------------------------------------------------------------------------
   if($_REQUEST["stid"] == "")
   {
      $sql = " insert into company_shops_storehouses
               (st_name, st_desc, st_shop_id, st_reserva, st_crtusr, st_crtdat, st_repuestos_act, st_unibagreserva_act,
                st_unibagflexo_act, st_unibagseri_act, st_unibagsellador_act, st_clasificacion, st_capacidad)
               VALUES
               ('{$_REQUEST["st_name"]}', '{$_REQUEST["st_desc"]}', {$_REQUEST["id"]}, {$_REQUEST["st_reserva"]},
                 {$_SESSION["user_id"]}, {$currtme}, {$_REQUEST["st_repuestos_act"]}, {$_REQUEST["st_unibagreserva_act"]},
                 {$_REQUEST["st_unibagflexo_act"]}, {$_REQUEST["st_unibagseri_act"]}, {$_REQUEST["st_unibagsellador_act"]}, 
                 '{$_REQUEST["st_clasificacion"]}',{$_REQUEST["st_capacidad"]})";

      $res = $CON->no_result($sql);

          if($res)
      {
         $sql = " select MAX(id) 'maxid'
                  from company_shops_storehouses
                  where
                  st_crtusr = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["maxid"];
         $_REQUEST["stid"] = $thisid;
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update company_shops_storehouses
               set
               st_name                 = '{$_REQUEST["st_name"]}',
               st_desc                 = '{$_REQUEST["st_desc"]}',
               st_reserva              = {$_REQUEST["st_reserva"]},
               st_repuestos_act        = {$_REQUEST["st_repuestos_act"]},
               st_unibagreserva_act    = {$_REQUEST["st_unibagreserva_act"]},
               st_unibagflexo_act      = {$_REQUEST["st_unibagflexo_act"]},
               st_unibagseri_act       = {$_REQUEST["st_unibagseri_act"]},
               st_unibagsellador_act   = {$_REQUEST["st_unibagsellador_act"]},
               st_updusr               = {$_SESSION["user_id"]},
               st_upddat               = {$currtme},
               st_clasificacion        = '{$_REQUEST["st_clasificacion"]}',
               st_capacidad            = {$_REQUEST["st_capacidad"]}
               where
               id = {$_REQUEST["stid"]}";
      $res = $CON->no_result($sql);

      $sql = " delete from company_shops_storehouses_rspuids
               where
               st_id = {$_REQUEST["stid"]}";
      $CON->no_result($sql);

      foreach($_REQUEST["rspseluids"] AS $user_id)
      {
         $sql = " insert into company_shops_storehouses_rspuids
                  (st_id, user_id)
                  VALUES
                  ({$_REQUEST["stid"]}, {$user_id})";
         $CON->no_result($sql);
      }
      
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["stid"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from company_shops_storehouses t1
            LEFT OUTER JOIN user t2 ON t1.st_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.st_crtusr = t3.id
            where
            t1.id = {$_REQUEST["stid"]}";
   $storehouse = $CON->select($sql);
   $storehouse = $storehouse[0];

   $sql = " select distinct t1.item_number_prod, t1.item_title, t2.iss_inventory
            from item t1
            INNER JOIN item_shops_storehouses t2 ON t1.id = t2.item_id
            where
            t1.item_status    = 1 and
            t2.st_id          = {$_REQUEST["stid"]} and
            t2.iss_inventory != 0.00
            order by t1.item_number_prod
            LIMIT 0,50";
   $itemchecks = $CON->select($sql);

   $sql = " select *
            from company_shops_storehouses_rspuids
            where
            st_id = {$_REQUEST["stid"]}";
   $rspuids = $CON->select($sql);
   foreach($rspuids AS $rspuid)
      $_SELRSPUIDS[$rspuid["user_id"]] = 1;
}

//----------------------------------------------------------------------------------
$sql = "select * from parametros where tabla = 'CANAL' ";
$canales = $CON->select($sql);
//----------------------------------------------------------------------------------
$sql = "select * from parametros where tabla = 'CLASI_BODEGA' ";
$clasi_bodegas = $CON->select($sql);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["opcanal"])
{

   $_REQUEST["st_canal"]          = trim(addslashes($_REQUEST["st_canal"]));
   $_REQUEST["st_repuestos_act"]  = (int)$_REQUEST["st_repuestos_act"];

   $sql = " insert into bodegacanal(bodegacanal_id_bodega, bodegacanal_codigo_canal  ) 
               values({$_REQUEST["stid"]}, {$_REQUEST["st_canal"]}) ";
   $CON->no_result($sql);
}

if((int)$_REQUEST["opeliminar"])
{

   $sql = " delete from bodegacanal where idbodegacanal = {$_REQUEST["opeliminar"]}";
   $CON->no_result($sql);
   
}

$sql = "select idbodegacanal
              ,bodegacanal_id_bodega
              ,ltrim(rtrim(bodegacanal_codigo_canal)) as bodegacanal_codigo_canal
              ,descripcion
        from bodegacanal
           inner join parametros on tabla = 'CANAL' and codigo = bodegacanal_codigo_canal
         where bodegacanal_id_bodega = {$_REQUEST["stid"]}";
$bodega_canales = $CON->select($sql);

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_storehouse"
 onsubmit="return checkform(new Array(this.st_name))">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="stid" value="<?=$_REQUEST["stid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subsubexec" value="save">
<input type="hidden" name="opcanal" value="">
<input type="hidden" name="opeliminar" value="">

<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de bodega</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="st_name" type="text" class="text" style="width:100%" value="<?=$storehouse["st_name"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="st_desc" class="text" style="width:100%;height:130px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$storehouse["st_desc"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Clasificación</td>
   <td class="content_row">
      <select class="text" style="width:500px" name="st_clasificacion" id="st_clasificacion" colspan="2"
          onmousedown="markfield(this,0)" onblur="markfield(this,1)">
          <option value=" ">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
             <?php
             foreach($clasi_bodegas as $cla_bod)
             {  ?>
                <option value="<?=$cla_bod["codigo"]?>"
                <?php if($cla_bod["codigo"] == $storehouse["st_clasificacion"]) echo "selected"?>><?=$cla_bod["descripcion"]?></option>
                <?php
             }
          ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Capacidad</td>
   <td class="content_row">
      <input type="text" name="st_capacidad" class="text" style="width:150px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=printPrice($storehouse["st_capacidad"])?>">
   </td>
</tr>

<tr>
   <td class="content_rowl">Bodega Repuestos</td>
   <td class="content_row">
      <input type="radio" name="st_repuestos_act" value="0" <?if(!(int)$storehouse["st_repuestos_act"]) echo "checked"?>>&nbsp;No
      <input type="radio" name="st_repuestos_act" value="1" <?if((int)$storehouse["st_repuestos_act"]) echo "checked"?>>&nbsp;Si
   </td>
</tr>
<tr>
   <td class="content_rowl">Bodega Reserva</td>
   <td class="content_row">
      <input type="radio" name="st_unibagreserva_act" value="0" <?if(!(int)$storehouse["st_unibagreserva_act"]) echo "checked"?>>&nbsp;No
      <input type="radio" name="st_unibagreserva_act" value="1" <?if((int)$storehouse["st_unibagreserva_act"]) echo "checked"?>>&nbsp;Si
   </td>
</tr>
<tr>
   <td class="content_rowl">Bodega Flexografia</td>
   <td class="content_row">
      <input type="radio" name="st_unibagflexo_act" value="0" <?if(!(int)$storehouse["st_unibagflexo_act"]) echo "checked"?>>&nbsp;No
      <input type="radio" name="st_unibagflexo_act" value="1" <?if((int)$storehouse["st_unibagflexo_act"]) echo "checked"?>>&nbsp;Si
   </td>
</tr>
<tr>
   <td class="content_rowl">Bodega Serigrafia</td>
   <td class="content_row">
      <input type="radio" name="st_unibagseri_act" value="0" <?if(!(int)$storehouse["st_unibagseri_act"]) echo "checked"?>>&nbsp;No
      <input type="radio" name="st_unibagseri_act" value="1" <?if((int)$storehouse["st_unibagseri_act"]) echo "checked"?>>&nbsp;Si
   </td>
</tr>
<tr>
   <td class="content_rowl">Bodega Sellador</td>
   <td class="content_row">
      <input type="radio" name="st_unibagsellador_act" value="0" <?if(!(int)$storehouse["st_unibagsellador_act"]) echo "checked"?>>&nbsp;No
      <input type="radio" name="st_unibagsellador_act" value="1" <?if((int)$storehouse["st_unibagsellador_act"]) echo "checked"?>>&nbsp;Si
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl" valign="top">Reserva</td>
   <td class="content_row">
      <input type="radio" name="st_reserva" value="0" <?if(!(int)$storehouse["st_reserva"]) echo "checked"?>>&nbsp;No
      <input type="radio" name="st_reserva" value="1" <?if((int)$storehouse["st_reserva"]) echo "checked"?>>&nbsp;Si
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$storehouse["crt_firstname"]?> <?=$storehouse["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($storehouse["st_crtdat"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$storehouse["upd_firstname"]?> <?=$storehouse["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($storehouse["st_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="4" cellspacing="0" width="100%">
      <td class="content_rowl">Canal</td>
      <td class="content_row">
         <select class="text" style="width:500px" name="st_canal" id="st_canal" colspan="2"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value=" ">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($canales as $canal)
               {  ?>
                  <option value="<?=$canal["codigo"]?>"
                  <?php if($canal["codigo"] == $storehouse["st_canal"]) echo "selected"?>><?=$canal["descripcion"]?></option>
                  <?php
               }
            ?>
         </select>
      </td>
      <td>
         <input type="button" class="button" value="Agregar Canal" style="width:200px"
            onclick="document.xform_storehouse.opcanal.value = '1';submitForm(document.xform_storehouse);">
      </td>
   </table>      
   <table border="0" class="content_table" cellpadding="4" cellspacing="0" width="100%">
      <colgroup>
         <col width="20">
         <col width="20">
         <col width="500">
         <col width="50">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Canales asignados</td>
      </tr>
      <tr>
         <td class="content_row_os content_tbl_subheader">id</td>
         <td class="content_row_os content_tbl_subheader">Codigo</td>
         <td class="content_row_os content_tbl_subheader">Canal</td>
         <td class="content_row_os content_tbl_subheader" align="center">Opción</td>
      </tr>
      <?php
         for($x = 0; $x < count($bodega_canales) && $bodega_canales != false; $x++)
         {  ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$bodega_canales[$x]["idbodegacanal"]?></td>
               <td class="content_row_os"><?=trim($bodega_canales[$x]["bodegacanal_codigo_canal"])?></td>
               <td class="content_row_os"><norb><?=$bodega_canales[$x]["descripcion"]?></norb></td>
               <td class="content_row_os" align="center">
                   <input type="button" class="button" value="Eliminar" style="width:200px"
                      onclick="document.xform_storehouse.opeliminar.value='<?=$bodega_canales[$x]['idbodegacanal']?>';submitForm(document.xform_storehouse);">
               </td>
            </tr>
            <?php
         }
      ?>
   </table>
<?=Nifty_printF(false)?>
<br>
<?php
if($_REQUEST["stid"] != "")
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from user t1
            where
            t1.user_status > 0
            order by t1.user_type asc, t1.user_login asc";
   $users = $CON->select($sql);
   ?>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" cellspacing="0" cellpadding="3" width="100%">
   <colgroup>
      <col width="20">
      <col>
      <col width="250">
      <col width="100">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4">Asignar responsables</td>
   </tr>
   <tr>
      <td class="content_row_os content_tbl_subheader" align="center">
         <input type="checkbox" name="xdummy" value="1"
         onclick="$('.clsseluids').attr('checked', this.checked)">
      </td>
      <td class="content_row_os content_tbl_subheader">Nombre</td>
      <td class="content_row_os content_tbl_subheader">Usuario</td>
      <td class="content_row_os content_tbl_subheader">Rol</td>
   </tr>
   <?php
   for($x = 0; $x < count($users) && $users != false; $x++)
   {
      if($users[$x]["user_type"] == "1")
         $disp_type = $_LANG["MODULE"]["USER"][34];
      else
         $disp_type = $_LANG["MODULE"]["USER"][33];
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os" align="center">
            <input type="checkbox" class="clsseluids" name="rspseluids[]" value="<?=$users[$x]["id"]?>"
            <?if($_SELRSPUIDS[$users[$x]["id"]]) echo "checked"?>>
         </td>
         <td class="content_row_os"><?=$users[$x]["user_firstname"]?> <?=$users[$x]["user_lastname"]?></td>
         <td class="content_row_os"><?=$users[$x]["user_login"]?></td>
         <td class="content_row_os"><?=$disp_type?></td>
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
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&id={$_REQUEST["id"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   if($_REQUEST["stid"] != "")
   {
      if(!count($itemchecks) || $itemchecks == false)
      {  ?>
         <td width="130" align="right" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&stid={$_REQUEST["stid"]}&subexec=del')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_storehouse)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_storehouse');" ?>
<?php
if(count($itemchecks) && $itemchecks != false)
{  ?>
   <br>
   <b class="msg_save_err">Aviso: La bodega no se puede borrar, porque tiene productos con stock.</b>
   <br><br>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="100">
      <col>
      <col width="80">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="3">Productos asignados</td>
   </tr>
   <tr>
      <td class="content_row_os content_tbl_subheader">Codigo</td>
      <td class="content_row_os content_tbl_subheader">Nombre</td>
      <td class="content_row_os content_tbl_subheader">Stock</td>
   </tr>
   <?php
   $x = 0;
   foreach($itemchecks AS $itemcheck)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row_os"><?=$itemcheck["item_number_prod"]?>&nbsp;</td>
         <td class="content_row_os"><?=$itemcheck["item_title"]?>&nbsp;</td>
         <td class="content_row_os"><?=printPrice($itemcheck["iss_inventory"],2)?></td>
      </tr>
      <?php
      $x++;
   }
   ?>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}

?>

<br><br>