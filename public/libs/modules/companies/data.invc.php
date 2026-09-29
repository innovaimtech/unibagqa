<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   $_REQUEST["company_invc_mode"]          = (int)$_REQUEST["company_invc_mode"];
   $_REQUEST["company_invc_ftp_port"]      = (int)$_REQUEST["company_invc_ftp_port"];
   $_REQUEST["company_invc_itf"]           = trim(addslashes($_REQUEST["company_invc_itf"]));
   $_REQUEST["company_invc_ftp_host"]      = trim(addslashes($_REQUEST["company_invc_ftp_host"]));
   $_REQUEST["company_invc_ftp_dir"]       = trim(addslashes($_REQUEST["company_invc_ftp_dir"]));
   $_REQUEST["company_invc_ftp_user"]      = trim(addslashes($_REQUEST["company_invc_ftp_user"]));
   $_REQUEST["company_invc_ftp_pass"]      = trim(addslashes($_REQUEST["company_invc_ftp_pass"]));
   $_REQUEST["company_invc_num_resolsii"]  = trim(addslashes($_REQUEST["company_invc_num_resolsii"]));

   $_REQUEST["company_invc_date_resolsii"]  = explode(".", $_REQUEST["company_invc_date_resolsii"]);
   $_REQUEST["company_invc_date_resolsii"]  = mktime(0, 0, 0, $_REQUEST["company_invc_date_resolsii"][1], $_REQUEST["company_invc_date_resolsii"][0], $_REQUEST["company_invc_date_resolsii"][2]);

   $sql = " update company_data
            set
            company_invc_mode          = {$_REQUEST["company_invc_mode"]},
            company_invc_ftp_port      = {$_REQUEST["company_invc_ftp_port"]},
            company_invc_itf           = '{$_REQUEST["company_invc_itf"]}',
            company_invc_ftp_host      = '{$_REQUEST["company_invc_ftp_host"]}',
            company_invc_ftp_dir       = '{$_REQUEST["company_invc_ftp_dir"]}',
            company_invc_ftp_user      = '{$_REQUEST["company_invc_ftp_user"]}',
            company_invc_ftp_pass      = '{$_REQUEST["company_invc_ftp_pass"]}',
            company_invc_num_resolsii  = {$_REQUEST["company_invc_num_resolsii"]},
            company_invc_date_resolsii = {$_REQUEST["company_invc_date_resolsii"]},
            company_updusr             =  {$_SESSION["user_id"]},
            company_upddat             =  {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from company_data t1
         where
         t1.id = {$_REQUEST["id"]}";
$company = $CON->select($sql);
$company = $company[0];
   
//----------------------------------------------------------------------------------
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="xform_cdata" class="fokusfirst">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="160">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Documentos tributarios</td>
</tr>
<tr>
   <td class="content_rowl">Modo facturación</td>
   <td class="content_row">
      <input type="radio" name="company_invc_mode" id="company_invc_mode1" value="0"
      onchange="$('.idx_tr_f1').toggle()"
      <?php if(!(int)$company["company_invc_mode"]) echo "checked"?>> Sin factura electronica
      <input type="radio" name="company_invc_mode" id="company_invc_mode1" value="1"
      onchange="$('.idx_tr_f1').toggle()"
      <?php if((int)$company["company_invc_mode"]) echo "checked"?>> Factura electronica
   </td>
</tr>
<tr class="idx_tr_f1" style="<?php if(!(int)$company["company_invc_mode"]) echo "display:none"?>">
   <td class="content_rowl">Formato/Proveedor</td>
   <td class="content_row">
      <select class="text" style="width:200px" name="company_invc_itf" id="company_invc_itf"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="BCNCONS">CONTALINE.CL</option>
      </select>
   </td>
</tr>
<tr class="idx_tr_f1" style="<?php if(!(int)$company["company_invc_mode"]) echo "display:none"?>">
   <td class="content_rowl">Werbservice: Servidor</td>
   <td class="content_row">
      <input name="company_invc_ftp_host" type="text" class="text" style="width:200px" value="<?=$company["company_invc_ftp_host"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr class="idx_tr_f1" style="<?php if(!(int)$company["company_invc_mode"]) echo "display:none"?>">
   <td class="content_rowl">Werbservice: Puerto</td>
   <td class="content_row">
      <input name="company_invc_ftp_port" type="text" class="text" style="width:200px" value="<?=$company["company_invc_ftp_port"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>

<tr class="idx_tr_f1" style="<?php if(!(int)$company["company_invc_mode"]) echo "display:none"?>">
   <td class="content_rowl">Werbservice: Usuario</td>
   <td class="content_row">
      <input name="company_invc_ftp_user" type="text" class="text" style="width:200px" value="<?=$company["company_invc_ftp_user"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr class="idx_tr_f1" style="<?php if(!(int)$company["company_invc_mode"]) echo "display:none"?>">
   <td class="content_rowl">Werbservice: Contraseña</td>
   <td class="content_row">
      <input name="company_invc_ftp_pass" type="password" class="text" style="width:200px" value="<?=$company["company_invc_ftp_pass"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr class="idx_tr_f1" style="<?php if(!(int)$company["company_invc_mode"]) echo "display:none"?>">
   <td class="content_rowl">Nº Resolucion SII</td>
   <td class="content_row">
      <input name="company_invc_num_resolsii" type="text" class="text" style="width:200px" value="<?=$company["company_invc_num_resolsii"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr class="idx_tr_f1" style="<?php if(!(int)$company["company_invc_mode"]) echo "display:none"?>">
   <td class="content_rowl">Fecha Resolucion SII</td>
   <td class="content_row">
      <input name="company_invc_date_resolsii" type="text" class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      style="width:85px" value="<?if((int)$company["company_invc_date_resolsii"]) echo date("d.m.Y", $company["company_invc_date_resolsii"]);?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_cdata)", "disk-black");
      ?>
   </td>
</tr>
</table>
</form>
<?=Nifty_printF(false)?>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cdata');" ?>