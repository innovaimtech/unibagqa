<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "company_numcounter_itf_invc_tax_") !== false && strpos($reqkey, "company_numcounter_itf_invc_tax_") == 0)
      {
         $idxpos  = substr($reqkey, strrpos($reqkey, "_") +1);

         $company_numcounter_itf_invc_tax       = (int)$_REQUEST["company_numcounter_itf_invc_tax_{$idxpos}"];
         $company_numcounter_itf_invc_tax_rinit = (int)$_REQUEST["rinit_company_numcounter_itf_invc_tax_{$idxpos}"];
         $company_numcounter_itf_invc_tax_rend  = (int)$_REQUEST["rend_company_numcounter_itf_invc_tax_{$idxpos}"];
         
         $company_numcounter_itf_invc_ext       = (int)$_REQUEST["company_numcounter_itf_invc_ext_{$idxpos}"];
         $company_numcounter_itf_invc_ext_rinit = (int)$_REQUEST["rinit_company_numcounter_itf_invc_ext_{$idxpos}"];
         $company_numcounter_itf_invc_ext_rend  = (int)$_REQUEST["rend_company_numcounter_itf_invc_ext_{$idxpos}"];

         $company_numcounter_itf_bol_tax       = (int)$_REQUEST["company_numcounter_itf_bol_tax_{$idxpos}"];
         $company_numcounter_itf_bol_tax_rinit = (int)$_REQUEST["rinit_company_numcounter_itf_bol_tax_{$idxpos}"];
         $company_numcounter_itf_bol_tax_rend  = (int)$_REQUEST["rend_company_numcounter_itf_bol_tax_{$idxpos}"];
         
         $company_numcounter_itf_bol_ext       = (int)$_REQUEST["company_numcounter_itf_bol_ext_{$idxpos}"];
         $company_numcounter_itf_bol_ext_rinit = (int)$_REQUEST["rinit_company_numcounter_itf_bol_ext_{$idxpos}"];
         $company_numcounter_itf_bol_ext_rend  = (int)$_REQUEST["rend_company_numcounter_itf_bol_ext_{$idxpos}"];
         
         $company_numcounter_itf_notecred       = (int)$_REQUEST["company_numcounter_itf_notecred_{$idxpos}"];
         $company_numcounter_itf_notecred_rinit = (int)$_REQUEST["rinit_company_numcounter_itf_notecred_{$idxpos}"];
         $company_numcounter_itf_notecred_rend  = (int)$_REQUEST["rend_company_numcounter_itf_notecred_{$idxpos}"];
         
         $company_numcounter_itf_notedeb        = (int)$_REQUEST["company_numcounter_itf_notedeb_{$idxpos}"];
         $company_numcounter_itf_notedeb_rinit  = (int)$_REQUEST["rinit_company_numcounter_itf_notedeb_{$idxpos}"];
         $company_numcounter_itf_notedeb_rend   = (int)$_REQUEST["rend_company_numcounter_itf_notedeb_{$idxpos}"];
         
         $company_numcounter_itf_dlv            = (int)$_REQUEST["company_numcounter_itf_dlv_{$idxpos}"];
         $company_numcounter_itf_dlv_rinit      = (int)$_REQUEST["rinit_company_numcounter_itf_dlv_{$idxpos}"];
         $company_numcounter_itf_dlv_rend       = (int)$_REQUEST["rend_company_numcounter_itf_dlv_{$idxpos}"];

         $sql = " update company_data
                  set
                  company_numcounter_itf_invc_tax        = {$company_numcounter_itf_invc_tax},
                  company_numcounter_itf_invc_tax_rinit  = {$company_numcounter_itf_invc_tax_rinit},
                  company_numcounter_itf_invc_tax_rend   = {$company_numcounter_itf_invc_tax_rend},
                  company_numcounter_itf_invc_ext        = {$company_numcounter_itf_invc_ext},
                  company_numcounter_itf_invc_ext_rinit  = {$company_numcounter_itf_invc_ext_rinit},
                  company_numcounter_itf_invc_ext_rend   = {$company_numcounter_itf_invc_ext_rend},
                  company_numcounter_itf_notecred        = {$company_numcounter_itf_notecred},
                  company_numcounter_itf_notecred_rinit  = {$company_numcounter_itf_notecred_rinit},
                  company_numcounter_itf_notecred_rend   = {$company_numcounter_itf_notecred_rend},
                  company_numcounter_itf_notedeb         = {$company_numcounter_itf_notedeb},
                  company_numcounter_itf_notedeb_rinit   = {$company_numcounter_itf_notedeb_rinit},
                  company_numcounter_itf_notedeb_rend    = {$company_numcounter_itf_notedeb_rend},
                  company_numcounter_itf_dlv             = {$company_numcounter_itf_dlv},
                  company_numcounter_itf_dlv_rinit       = {$company_numcounter_itf_dlv_rinit},
                  company_numcounter_itf_dlv_rend        = {$company_numcounter_itf_dlv_rend},
                  company_numcounter_itf_bol_tax         = {$company_numcounter_itf_bol_tax},
                  company_numcounter_itf_bol_tax_rinit   = {$company_numcounter_itf_bol_tax_rinit},
                  company_numcounter_itf_bol_tax_rend    = {$company_numcounter_itf_bol_tax_rend},
                  company_numcounter_itf_bol_ext         = {$company_numcounter_itf_bol_ext},
                  company_numcounter_itf_bol_ext_rinit   = {$company_numcounter_itf_bol_ext_rinit},
                  company_numcounter_itf_bol_ext_rend    = {$company_numcounter_itf_bol_ext_rend}
                  where
                  id = {$idxpos}";
         $CON->no_result($sql);

         /*
         $sql = " update company_data
                  set
                  company_numcounter_itf_invc_tax_rend   = {$company_numcounter_itf_invc_tax_rend},
                  company_numcounter_itf_invc_ext_rend   = {$company_numcounter_itf_invc_ext_rend},
                  company_numcounter_itf_notecred_rend   = {$company_numcounter_itf_notecred_rend},
                  company_numcounter_itf_notedeb_rend    = {$company_numcounter_itf_notedeb_rend},
                  company_numcounter_itf_dlv_rend        = {$company_numcounter_itf_dlv_rend},
                  company_numcounter_itf_bol_tax_rend    = {$company_numcounter_itf_bol_tax_rend},
                  company_numcounter_itf_bol_ext_rend    = {$company_numcounter_itf_bol_ext_rend}
                  where
                  id = {$idxpos}";
         $CON->no_result($sql);
         */
      }
   }
   $savemsg = getSaveMessage(true);
}

$comps = getCompanies($CON, true);
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";
</script>
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header">Correlativos SII</b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<form action="index.php" method="post" name="xform_cdata" class="fokusfirst">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<?php
foreach($comps AS $comp)
{  ?>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
   <col>
   <col width="110">
   <col width="110">
   <col width="110">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4"><?=$comp["company_name"]?></td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Tipo de transacción</td>
      <td class="content_tbl_subheader" align="center">Correlativo actual</td>
      <td class="content_tbl_subheader" align="center">Rango inicio</td>
      <td class="content_tbl_subheader" align="center">Rango termino</td>
   </tr>
   <tr>
      <td class="content_rowl"><b>Boleta Electronica / IVA</b></td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>company_numcounter_itf_bol_tax_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_bol_tax"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rinit_company_numcounter_itf_bol_tax_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_bol_tax_rinit"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rend_company_numcounter_itf_bol_tax_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_bol_tax_rend"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr style="display:none">
      <td class="content_rowl"><b>Boleta Electronica / EXENTA</b></td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>company_numcounter_itf_bol_ext_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_bol_ext"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rinit_company_numcounter_itf_bol_ext_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_bol_ext_rinit"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rend_company_numcounter_itf_bol_ext_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_bol_ext_rend"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   
   <tr>
      <td class="content_rowl"><b>Factura Electronica / IVA</b></td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>company_numcounter_itf_invc_tax_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_invc_tax"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rinit_company_numcounter_itf_invc_tax_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_invc_tax_rinit"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rend_company_numcounter_itf_invc_tax_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_invc_tax_rend"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      <td class="content_rowl"><b>Factura Electronica / EXTENTA</b></td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>company_numcounter_itf_invc_ext_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_invc_ext"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rinit_company_numcounter_itf_invc_ext_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_invc_ext_rinit"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rend_company_numcounter_itf_invc_ext_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_invc_ext_rend"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      <td class="content_rowl"><b>Nota de Credito Electronica</b></td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>company_numcounter_itf_notecred_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_notecred"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rinit_company_numcounter_itf_notecred_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_notecred_rinit"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rend_company_numcounter_itf_notecred_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_notecred_rend"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      <td class="content_rowl"><b>Nota de Debito Electronica</b></td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>company_numcounter_itf_notedeb_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_notedeb"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rinit_company_numcounter_itf_notedeb_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_notedeb_rinit"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rend_company_numcounter_itf_notedeb_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_notedeb_rend"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   <tr>
      <td class="content_rowl"><b>Guia de Despacho Electronica</b></td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>company_numcounter_itf_dlv_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_dlv"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rinit_company_numcounter_itf_dlv_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_dlv_rinit"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="center">
         <input name="<?=$prefix?>rend_company_numcounter_itf_dlv_<?=$comp["id"]?>" type="text" class="text" style="width:80px;text-align:center;<?=$cssbg?>"
         value="<?=(int)$comp["company_numcounter_itf_dlv_rend"]?>" <?=$dabled?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}
?>
<?=Nifty_printH("boxopt_b", "650")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td>&nbsp;</td>
   <td align="right" width="130">
      <ul class="postnav_save">
         <a href="javascript: deactivateFormChange()" onclick="submitForm(document.xform_cdata)"><?=$_LANG["FORM"]["BUTTON"][0]?></a>
      </ul>
   </td>
</tr>
</table>
</form>
<?=Nifty_printF(false)?>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_cdata');" ?>