<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "save")
{
   $currtme = time();

   $_REQUEST["idtransports"]                = (int)$_REQUEST["idtransports"];
   $_REQUEST["transports_vh_marca"]         = trim(addslashes($_REQUEST["transports_vh_marca"]));
   $_REQUEST["transports_vh_patente"]       = trim(addslashes($_REQUEST["transports_vh_patente"]));
   $_REQUEST["transports_vh_descripcion"]   = trim(addslashes($_REQUEST["transports_vh_descripcion"]));

   //----------------------------------------------------------------------------------

   if($_REQUEST["vhid"] == "")
   {
      $sql = " insert into transports_vehiculo
               (idtransports_vh
               ,transports_vh_marca 
               ,transports_vh_patente 
               ,transports_vh_descripcion 
               ,transports_vh_cr_date 
               ,transports_vh_cr_user 
               ,transports_vh_up_date 
               ,transports_vh_up_user 
               ,transports_vh_status)
               VALUES
               ( {$_REQUEST["id"]}
               , '{$_REQUEST["transports_vh_marca"]}'
               , '{$_REQUEST["transports_vh_patente"]}'
               , '{$_REQUEST["transports_vh_descripcion"]}'
               , {$currtme}
               , {$_SESSION["user_id"]}
               , {$currtme}
               , {$_SESSION["user_id"]}
               , 1
                )";

      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'maxid'
                  from transports_vehiculo
                  where
                  transports_vh_cr_user = {$_SESSION["user_id"]}";
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["maxid"];
         $_REQUEST["vhid"] = $thisid;
      }
   }
   else
   {
      $sql = " update transports_vehiculo
               set
                transports_vh_marca          = '{$_REQUEST["transports_vh_marca"]}'
               ,transports_vh_patente        = '{$_REQUEST["transports_vh_patente"]}'
               ,transports_vh_descripcion    = '{$_REQUEST["transports_vh_descripcion"]}'
               ,transports_vh_up_date        = {$currtme}
               ,transports_vh_up_user        = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["vhid"]}";
      $res = $CON->no_result($sql);
     
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql   = "select * from parametros where tabla = 'VEHICULOS' order by descripcion ";
$autos = $CON->select($sql);
//----------------------------------------------------------------------------------

$sql = "select transports_vehiculo.*, parametros.descripcion,
           cr.user_firstname as crt_firstname, cr.user_lastname  as crt_lastname,
           up.user_firstname as upd_firstname , up.user_lastname  as upd_lastname
        from transports_vehiculo 
            inner join parametros on tabla = 'VEHICULOS' and codigo = transports_vh_marca
            inner join user cr on cr.id = transports_vh_cr_user
            inner join user up on up.id = transports_vh_up_user
         where transports_vehiculo.id = {$_REQUEST["vhid"]} 
            and transports_vh_status > 0 ";

$vehiculos = $CON->select($sql);
$vehiculos = $vehiculos[0];

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_vehiculos">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="vhid" value="<?=$_REQUEST["vhid"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="add">
<input type="hidden" name="subsubexec" value="save">

<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de Vehículos</td>
</tr>
<tr>
   <td class="content_rowl">Marca</td>
   <td class="content_row">
       <select class="text" style="width:500px" name="transports_vh_marca" id="transports_vh_marca" colspan="2"
            onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <option value=" ">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($autos as $auto)
               {  ?>
                  <option value="<?=$auto["codigo"]?>"
                  <?php if($auto["codigo"] == $vehiculos["transports_vh_marca"]) echo "selected"?>><?=$auto["descripcion"]?></option>
                  <?php
               }
            ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Patente</td>
   <td class="content_row">
      <input name="transports_vh_patente" type="text" class="text" style="width:280px" value="<?=$vehiculos["transports_vh_patente"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Descripción</td>
   <td class="content_row">
      <textarea name="transports_vh_descripcion" class="text" style="width:100%;height:130px"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$vehiculos["transports_vh_descripcion"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$vehiculos["crt_firstname"]?> <?=$vehiculos["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($vehiculos["transports_vh_cr_date"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$vehiculos["upd_firstname"]?> <?=$vehiculos["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($vehiculos["transports_vh_up_date"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
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
   if($_REQUEST["vhid"] != "")
   {
         ?>
         <td width="130" align="right" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&vhid={$_REQUEST["vhid"]}&subexec=del')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_vehiculos)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_vehiculos');" ?>
<br><br>