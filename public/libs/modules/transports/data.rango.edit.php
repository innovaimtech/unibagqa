<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "save")
{
   $currtme = time();

   $_REQUEST["transports_chofer_trans_id"]    = (int)$_REQUEST["transports_chofer_trans_id"];
   $_REQUEST["transports_rango_codigo"]       = trim(addslashes($_REQUEST["transports_rango_codigo"]));
   $_REQUEST["transports_rango_descripcion"]  = trim(addslashes($_REQUEST["transports_rango_descripcion"]));
   $_REQUEST["transports_rango_monto"]        = getPrice($_REQUEST["transports_rango_monto"]);
         
   //----------------------------------------------------------------------------------

   if($_REQUEST["vhid"] == "")
   {
      $sql = " insert into transports_rango
               (transports_rango_trans_id
               ,transports_rango_codigo 
               ,transports_rango_descripcion 
               ,transports_rango_cr_date 
               ,transports_rango_cr_user 
               ,transports_rango_up_date 
               ,transports_rango_up_user
               ,transports_rango_status
               ,transports_rango_monto)
               VALUES
               ( {$_REQUEST["id"]}
               , '{$_REQUEST["transports_rango_codigo"]}'
               , '{$_REQUEST["transports_rango_descripcion"]}'
               , {$currtme}
               , {$_SESSION["user_id"]}
               , {$currtme}
               , {$_SESSION["user_id"]}
               , 1
               ,{$_REQUEST["transports_rango_monto"]}
                )";
      $res = $CON->no_result($sql);
    
      if($res)
      {
         $sql = " select MAX(id) 'maxid'
                  from transports_rango" ;
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["maxid"];
         $_REQUEST["vhid"] = $thisid;
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update transports_rango
               set transports_rango_codigo       = '{$_REQUEST["transports_rango_codigo"]}'
                  ,transports_rango_descripcion  = '{$_REQUEST["transports_rango_descripcion"]}'
                  ,transports_rango_monto        = '{$_REQUEST["transports_rango_monto"]}'
                  ,transports_rango_up_date    = {$currtme}
                  ,transports_rango_up_user    = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["vhid"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------

$sql = "select transports_rango.*,
           cr.user_firstname as crt_firstname, cr.user_lastname  as crt_lastname,
           up.user_firstname as upd_firstname , up.user_lastname  as upd_lastname
        from transports_rango
            inner join user cr on cr.id = transports_rango_cr_user
            inner join user up on up.id = transports_rango_up_user
         where transports_rango.id = {$_REQUEST["vhid"]} 
            and transports_rango.transports_rango_status > 0 ";

$rangos = $CON->select($sql);
$rangos = $rangos[0];

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_rango" onsubmit="return checkform(new Array(this.transports_rango_codigo, this.transports_rango_descripcion, this.transports_rango_monto))">
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
   <td class="content_tbl_header" colspan="2">Datos de Rangos</td>
</tr>
<tr>
   <td class="content_rowl">Codigo *</td>
   <td class="content_row">
      <input name="transports_rango_codigo" id="transports_rango_codigo" type="text" class="text" style="width:280px" value="<?=$rangos["transports_rango_codigo"]?>"
        onfocus="markfield(this,0)" onblur="markfield(this,1)">
         
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción *</td>
   <td class="content_row">
      <textarea name="transports_rango_descripcion" class="text" style="width:360px; height:148px"
         onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$rangos["transports_rango_descripcion"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Monto *</td>
   <td class="content_row">
      <input name="transports_rango_monto" type="text" class="text" style="width:280px" value="<?=printPrice($rangos["transports_rango_monto"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>

<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$rangos["crt_firstname"]?> <?=$rangos["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($rangos["transports_rango_cr_date"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$rangos["upd_firstname"]?> <?=$rangos["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($rangos["transports_rango_up_date"])?></td>
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
      {  ?>
         <td width="130" align="right" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subcatexec={$_REQUEST["subcatexec"]}&id={$_REQUEST["id"]}&vhid={$_REQUEST["vhid"]}&subexec=del')", "cross-circle-frame");
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_rango)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?$_SESSION["JSEXEC"] .= "addFormListeners('xform_chofer');" ?>
<br><br>