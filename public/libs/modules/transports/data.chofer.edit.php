<?php
//----------------------------------------------------------------------------------
if($_REQUEST["subsubexec"] == "save")
{
   $currtme = time();

   $_REQUEST["transports_chofer_trans_id"]  = (int)$_REQUEST["transports_chofer_trans_id"];
   $_REQUEST["transports_chofer_rut"]       = trim(addslashes($_REQUEST["transports_chofer_rut"]));
   $_REQUEST["transports_chofer_nombre"]    = trim(addslashes($_REQUEST["transports_chofer_nombre"]));
   $_REQUEST["transports_chofer_paterno"]   = trim(addslashes($_REQUEST["transports_chofer_paterno"]));
   $_REQUEST["transports_chofer_materno"]   = trim(addslashes($_REQUEST["transports_chofer_materno"]));
      
   //----------------------------------------------------------------------------------

   if($_REQUEST["vhid"] == "")
   {
      $sql = " insert into transports_chofer
               (transports_chofer_trans_id
               ,transports_chofer_rut 
               ,transports_chofer_nombre 
               ,transports_chofer_paterno 
               ,transports_chofer_materno 
               ,transports_chofer_cr_date 
               ,transports_chofer_cr_user 
               ,transports_chofer_up_date 
               ,transports_chofer_up_user
               ,transports_chofer_status)
               VALUES
               ( {$_REQUEST["id"]}
               , '{$_REQUEST["transports_chofer_rut"]}'
               , '{$_REQUEST["transports_chofer_nombre"]}'
               , '{$_REQUEST["transports_chofer_paterno"]}'
               , '{$_REQUEST["transports_chofer_materno"]}'
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
                  from transports_chofer" ;
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["maxid"];
         $_REQUEST["vhid"] = $thisid;
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update transports_chofer
               set transports_chofer_nombre     = '{$_REQUEST["transports_chofer_nombre"]}'
                  ,transports_chofer_materno    = '{$_REQUEST["transports_chofer_materno"]}'
                  ,transports_chofer_paterno    = '{$_REQUEST["transports_chofer_paterno"]}'
                  ,transports_chofer_up_date    = {$currtme}
                  ,transports_chofer_up_user    = {$_SESSION["user_id"]}
               where
               id = {$_REQUEST["vhid"]}";
      $res = $CON->no_result($sql);
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------

$sql = "select transports_chofer.*,
           cr.user_firstname as crt_firstname, cr.user_lastname  as crt_lastname,
           up.user_firstname as upd_firstname , up.user_lastname  as upd_lastname
        from transports_chofer 
            inner join user cr on cr.id = transports_chofer_cr_user
            inner join user up on up.id = transports_chofer_up_user
         where transports_chofer.id = {$_REQUEST["vhid"]} 
            and transports_chofer.transports_chofer_status > 0 ";


$choferes = $CON->select($sql);
$choferes = $choferes[0];

?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
      document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
      function choferformcheck(obj)
      {
         var rutchk = Rut(obj.transports_chofer_rut, obj.transports_chofer_rut.value);
         if(!rutchk)
         {
            return false;
         }
         var frmchk = checkform(new Array(obj.transports_chofer_rut, obj.transports_chofer_nombre)); 
         if(!frmchk)
            return false;

         return true;
      }
</script>
<?php

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<form action="index.php" method="post" class="fokusfirst" name="xform_chofer" onsubmit="return choferformcheck(this)">
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
   <td class="content_tbl_header" colspan="2">Datos de Chofer</td>
</tr>
<tr>
   <td class="content_rowl">Rut *</td>
   <td class="content_row">
      <input name="transports_chofer_rut" id="transports_chofer_rut" type="text" class="text" style="width:280px" value="<?=$choferes["transports_chofer_rut"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Nombre *</td>
   <td class="content_row">
      <input name="transports_chofer_nombre" type="text" class="text" style="width:280px" value="<?=$choferes["transports_chofer_nombre"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Apellido Paterno</td>
   <td class="content_row">
      <input name="transports_chofer_paterno" type="text" class="text" style="width:280px" value="<?=$choferes["transports_chofer_paterno"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Apellido Materno</td>
   <td class="content_row">
      <input name="transports_chofer_materno" type="text" class="text" style="width:280px" value="<?=$choferes["transports_chofer_materno"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>

<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$choferes["crt_firstname"]?> <?=$choferes["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($choferes["transports_chofer_cr_date"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$choferes["upd_firstname"]?> <?=$choferes["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($choferes["transports_chofer_up_date"])?></td>
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_chofer)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<?$_SESSION["JSEXEC"] .= "addFormListeners('xform_chofer');" ?>
<br><br>