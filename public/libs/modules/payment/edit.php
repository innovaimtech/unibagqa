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

   $_REQUEST["pay_title"]         = trim(addslashes($_REQUEST["pay_title"]));
   $_REQUEST["pay_desc"]          = trim(addslashes($_REQUEST["pay_desc"]));
   $_REQUEST["pay_sii_code"]      = trim(addslashes($_REQUEST["pay_sii_code"]));
   $_REQUEST["pay_days"]          = (int)$_REQUEST["pay_days"];
   $_REQUEST["pay_days_extra"]    = (int)$_REQUEST["pay_days_extra"];
   $_REQUEST["pay_boleta_act"]    = (int)$_REQUEST["pay_boleta_act"];
   $_REQUEST["pay_factura_act"]   = (int)$_REQUEST["pay_factura_act"];
   $_REQUEST["pay_offer_default"] = (int)$_REQUEST["pay_offer_default"];
   $_REQUEST["pay_monto_credito"] = (int)$_REQUEST["pay_monto_credito"];
   $_REQUEST["pay_cod_contable"]  = trim(addslashes($_REQUEST["pay_cod_contable"]));
   $_REQUEST["pay_sol_documento"] = (int)$_REQUEST["pay_sol_documento"];


   if((int)$_REQUEST["pay_offer_default"])
   {
      $sql = " update payments
               set
               pay_offer_default = 0";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if($_REQUEST["id"] == "")
   {
      $sql = " insert into payments
               (pay_title, pay_desc, pay_days, pay_days_extra, pay_crtusr, pay_crtdat,
                pay_sii_code, pay_boleta_act, pay_factura_act, pay_offer_default,pay_monto_credito,pay_cod_contable, pay_sol_documento)
               VALUES
               ('{$_REQUEST["pay_title"]}', '{$_REQUEST["pay_desc"]}', {$_REQUEST["pay_days"]},
                 {$_REQUEST["pay_days_extra"]}, {$_SESSION["user_id"]}, {$currtme},
                 '{$_REQUEST["pay_sii_code"]}', {$_REQUEST["pay_boleta_act"]}, {$_REQUEST["pay_factura_act"]},
                 {$_REQUEST["pay_offer_default"]}, {$_REQUEST["pay_monto_credito"]},'{$_REQUEST["pay_cod_contable"]}',{$_REQUEST["pay_sol_documento"]})";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select MAX(id) 'maxid'
                  from payments";
         $thisid = $CON->select($sql);
         $thisid = (int)$thisid[0]["maxid"];
         $redirect = true;
      }
   }

   //----------------------------------------------------------------------------------
   else
   {
      $sql = " update payments
               set
               pay_title      = '{$_REQUEST["pay_title"]}',
               pay_desc       = '{$_REQUEST["pay_desc"]}',
               pay_days       = {$_REQUEST["pay_days"]},
               pay_days_extra = {$_REQUEST["pay_days_extra"]},
               pay_sii_code      = '{$_REQUEST["pay_sii_code"]}',
               pay_boleta_act    = {$_REQUEST["pay_boleta_act"]},
               pay_factura_act   = {$_REQUEST["pay_factura_act"]},
               pay_offer_default = {$_REQUEST["pay_offer_default"]},
               pay_updusr     = {$_SESSION["user_id"]},
               pay_upddat     = {$currtme},
               pay_monto_credito = {$_REQUEST["pay_monto_credito"]},
               pay_cod_contable  = '{$_REQUEST["pay_cod_contable"]}',
               pay_sol_documento = {$_REQUEST["pay_sol_documento"]}
               where
               id = {$_REQUEST["id"]}";
      $res = $CON->no_result($sql);

      $thisid = $_REQUEST["id"];
      
   }

   $sql = " delete from payments_shops
            where
            pay_id = {$thisid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   foreach($_REQUEST["shop_act"] AS $shopid)
   {
      $sql = " insert into payments_shops
               (pay_id, shop_id)
               VALUES
               ({$thisid}, {$shopid})";
      $res = $CON->no_result($sql);
   }

   if($redirect)
   {  ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=487&exec=edit&id=<?=$thisid?>';
      </script>
      <?php
   }
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["id"] != "")
{
   $sql = " select t1.*,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from payments t1
            LEFT OUTER JOIN user t2 ON t1.pay_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.pay_crtusr = t3.id
            where
            t1.id = {$_REQUEST["id"]}";
   $payment = $CON->select($sql);

   $title = "Cambiar forma de pago";
}
else
   $title = "Agregar forma de pago";

//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         company_status = 1
         order by company_short";
$companies = $CON->select($sql);
//----------------------------------------------------------------------------------
?>
<form action="index.php" method="post" class="fokusfirst" name="xform_payment"
 onsubmit="return checkform(new Array(this.pay_title))">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<table border="0" cellpadding="0" cellspacing="0" width="650">
<tr>
   <td height="30"><b class="content_header"><?=$title?></b></td>
   <td align="right"><?=$savemsg?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Datos de forma de pago</td>
</tr>
<tr>
   <td class="content_rowl">Nombre *</td>
   <td class="content_row">
      <input name="pay_title" type="text" class="text" style="width:510px" value="<?=$payment[0]["pay_title"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr style="display:none">
   <td class="content_rowl">Tipo Documento</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="pay_boleta_act" checked>
      Boleta fiscal
      <input type="checkbox" value="1" name="pay_factura_act" checked>
      Factura
   </td>
</tr>
<tr>
   <td class="content_rowl">Cotizaciones</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="pay_offer_default"
      <?if((int)$payment[0]["pay_offer_default"]) echo "checked"?>>
      Activar por defecto en cotizaciones
   </td>
</tr>
<tr>
   <td class="content_rowl">Credito en Cliente</td>
   <td class="content_row">
      <input type="checkbox" value="1" name="pay_monto_credito"
      <?if((int)$payment[0]["pay_monto_credito"]) echo "checked"?>>
      Solicitar Monto de Crédito en Cliente
   </td>
</tr>
<tr>
   <td class="content_rowl">Código Contable</td>
   <td class="content_row">
      <input name="pay_cod_contable" type="text" class="text" style="width:510px" value="<?=$payment[0]["pay_cod_contable"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Solicitar Deposito</td>
   <td class="content_row">
      <label>
         <input type="radio" 
                name="pay_sol_documento" 
                value="1" 
                <?php if($payment[0]["pay_sol_documento"] == 1) echo "checked"; ?>>
         Sí
      </label>
      <label style="margin-left:20px;">
         <input type="radio" 
                name="pay_sol_documento" 
                value="0" 
                <?php if($payment[0]["pay_sol_documento"] == 0) echo "checked"; ?>>
         No
      </label>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Descripción</td>
   <td class="content_row">
      <textarea name="pay_desc" class="text" style="width:510px;height:50px"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$payment[0]["pay_desc"]?></textarea>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$payment[0]["crt_firstname"]?> <?=$payment[0]["crt_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($payment[0]["pay_crtdat"])?></td>
</tr>
<tr>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$payment[0]["upd_firstname"]?> <?=$payment[0]["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($payment[0]["pay_upddat"])?></td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Opciones: Factura</td>
</tr>
<tr>
   <td class="content_rowl">Dias Vencimiento</td>
   <td class="content_row">
      <input name="pay_days" type="text" class="text" style="width:80px" value="<?=$payment[0]["pay_days"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Codigo SII</td>
   <td class="content_row">
      <input name="pay_sii_code" type="text" class="text" style="width:80px" value="<?=$payment[0]["pay_sii_code"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<div style="display:none">
<?=Nifty_printH("box1", "650")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="2">Opciones: Boleta fiscal</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
</div>
<?php
foreach($companies AS $company)
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from company_shops t1
            where
            t1.shop_status = 1 and
            t1.shop_company_id = {$company["id"]}
            order by t1.shop_name";
   $shops = $CON->select($sql);

   //----------------------------------------------------------------------------------
   ?>
   <?=Nifty_printH("box1", "650")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="30">
      <col width="100">
      <col width="200">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="4"><?=$company["company_short"]?>, RUT: <?=$company["company_rut"]?></td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" align="center">Act.</td>
      <td class="content_tbl_subheader">Número</td>
      <td class="content_tbl_subheader">Nombre</td>
      <td class="content_tbl_subheader">Dirección</td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   for($x = 0; $x < count($shops) && $shops != false; $x++)
   {
      //----------------------------------------------------------------------------------
      $shopid = $shops[$x]["id"];

      //----------------------------------------------------------------------------------
      $sql = " select *
               from payments_shops
               where
               pay_id   = {$_REQUEST["id"]} and
               shop_id  = {$shopid}";
      $seldata = $CON->select($sql);
      $seldata = $seldata[0];

      //----------------------------------------------------------------------------------
      if((int)$seldata["shop_id"])
         $rowstyle = "style='color:#000000'";
      else
         $rowstyle = "style='color:#888888'";

      //----------------------------------------------------------------------------------
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row" align="center">
            <input type="checkbox" name="shop_act[]" value="<?=$shops[$x]["id"]?>"
            <?php if((int)$seldata["shop_id"]) echo "checked" ?>>
         </td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["id"]?>&nbsp;</td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["shop_name"]?>&nbsp;</td>
         <td class="content_row" <?=$rowstyle?>><?=$shops[$x]["shop_street"]?>&nbsp;</td>
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
      <td width="130">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <td width="130" align="right" style="padding-right:5px">
         <?php
         if($_REQUEST["id"] > 9)
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
      printButton($_LANG["FORM"]["BUTTON"][0], "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_payment)", "disk-black");
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('xform_payment');" ?>