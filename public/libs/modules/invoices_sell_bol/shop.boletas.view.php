<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$hasprcsellperm = true;
$_HIDETAXES = true;

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xinvoicessell"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xinvoicessell"]["fullcust"] = "";

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name,
                t2.cust_notes,  t7.pay_title, t8.trans_name, t2.cust_notes,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
         from invoices_sell_bol t1
         LEFT OUTER JOIN customer t2           ON t1.invc_cust_id         = t2.id
         LEFT OUTER JOIN company_data t3       ON t1.invc_company_id      = t3.id
         LEFT OUTER JOIN company_shops t4      ON t1.invc_shop_id         = t4.id
         LEFT OUTER JOIN user t5               ON t1.invc_updusr          = t5.id
         LEFT OUTER JOIN user t6               ON t1.invc_crtusr          = t6.id
         LEFT OUTER JOIN payments t7           ON t1.invc_paymentid       = t7.id
         LEFT OUTER JOIN transports t8         ON t1.invc_transportid     = t8.id
         LEFT OUTER JOIN user t9               ON t1.invc_userid_seller   = t9.id
         LEFT OUTER JOIN user t10              ON t1.invc_userid_cashing  = t10.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from customer t1
         LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.cust_provinciaid  = t5.id
         where
         t1.id = {$headdata["invc_cust_id"]}";
$customer = $CON->select($sql);
$customer = $customer[0];

//----------------------------------------------------------------------------------
$sellers     = getSellers($CON);
$payments    = getPayments($CON, $headdata["invc_shop_id"]);
$transports  = getTransports($CON);
$invcparts   = getInvoiceSellParts($CON, $_REQUEST["id"]);

//----------------------------------------------------------------------------------
if($headdata["invc_status"] >= 2)
{
   $rdlo       = " readonly ";
   $dabl       = " disabled ";
   $rowcount   = count($posdata);
}

//----------------------------------------------------------------------------------
if($_SESSION["xinvoicessell"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

//----------------------------------------------------------------------------------
$showStorehouses  = false;
$itemselw         = "450px";
if((int)$_REQUEST["showDiscounts"])
   $itemselw = "325px";
if((int)$headdata["invc_stockchange"])
{
   $showStorehouses  = true;
   $itemselw         = "340px";

   if((int)$_REQUEST["showDiscounts"])
      $itemselw = "300px";
      
}

//----------------------------------------------------------------------------------
$posdata    = Array();
$invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"], "_bol");
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"], "", "_bol");
   for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
   {
      $posdata[] = $partposdata[$y];
      if((int)$partposdata[$y]["item_sell_withotheritems"])
         $showRelItems = true;
   }
}
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Información boleta</b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<input type="hidden" name="idx_redirect_id" value="<?=$_REQUEST["id"]?>">
<form action="index.php" method="post" name="form_shppos" id="form_shppos" onsubmit='return false'>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="invc_status" value="1">
<input type="hidden" name="setPosOrder" value="">
<input type="hidden" name="cancelDoc" value="">
<input type="hidden" name="delinvcpartid" value="0">
<input type="hidden" name="showDiscounts" value="<?=$_REQUEST["showDiscounts"]?>">
<input type="hidden" name="user_pricesell_perm" value="<?=$_REQUEST["user_pricesell_perm"]?>">
<input type="hidden" name="user_docopen_perm" value="<?=$_REQUEST["user_docopen_perm"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="130">
   <col width="360">
   <col width="130">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="4">Datos básicos</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<?php
if((int)$headdata["invc_cust_id"])
{  ?>
   <tbody>
   <tr>
      <td class="content_rowl" <?=$cdatastyle?>>Cliente</td>
      <td class="content_row" <?=$cdatastyle?>>
         <a href="javascript:void(0)" style="text-decoration:none;color:black"
         onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$headdata["id"]?>&showfullcust=<?php
         if($_SESSION["xinvoicessell"]["fullcust"] == "") echo "1"; else echo "0"?>'"> <b><?=$customer["cust_name"]?></b></a></td>
      <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
      <td class="content_row" <?=$cdatastyle?>><b><?=$customer["cust_rut"]?></b>&nbsp;</td>
   </tr>
   <tr <?php if($_SESSION["xinvoicessell"]["fullcust"] == "") echo "style='display:none'"?>>
      <td class="content_rowl">Dirección</td>
      <td class="content_row"><?=$customer["cust_street"]?>&nbsp;</td>
      <td class="content_rowl">Teléfono</td>
      <td class="content_row"><?if($customer["cust_phone"] != "") echo $customer["cust_phone"];?>&nbsp;</td>
   </tr>
   <tr <?php if($_SESSION["xinvoicessell"]["fullcust"] == "") echo "style='display:none'"?>>
      <td class="content_rowl">Región</td>
      <td class="content_row"><?=$customer["name"]?>&nbsp;</td>
      <td class="content_rowl">Whatsapp</td>
      <td class="content_row"><?=$customer["cust_fax"]?>&nbsp;</td>
   </tr>
   <tr <?php if($_SESSION["xinvoicessell"]["fullcust"] == "") echo "style='display:none'"?>>
      <td class="content_rowl">Comuna</td>
      <td class="content_row"><?=$customer["pro_name"]?> - <?=$customer["nombre"]?>&nbsp;</td>
      <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
      <td class="content_row"><?=$customer["cust_email"]?>&nbsp;</td>
   </tr>
   <tr <?php if($_SESSION["xinvoicessell"]["fullcust"] == "") echo "style='display:none'"?>>
      <td class="content_rowl">País</td>
      <td class="content_row"><?=$customer["country_name"]?>&nbsp;</td>
      <td class="content_rowl">&nbsp;</td>
      <td class="content_row">&nbsp;</td>
   </tr>
   <?php
}
?>
<tr>
   <td class="content_rowl" <?=$cdatastyle?> height="31">Número boleta</td>
   <td class="content_row" <?=$cdatastyle?>>
      <span style="float:left;line-height:24px"><?=$headdata["invc_docnumber"]?></span>
      <?php
      if($headdata["invc_sgntr_doc1"] != "")
      {  ?>
         <span style="float:right">
         <?php
         printButton("Boleta ".$headdata["invc_docnumber"], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$_REQUEST["id"]}&printsiidoc1=1", "", "document-pdf", 80);
         ?>
         </span>
         <?php
      }
      ?>
   </td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$bcss1?>Fecha *<?=$bcss2?></td>
   <td class="content_row" <?=$cdatastyle?>><?=date('d.m.Y', $headdata["invc_date"])?></td>
</tr>
<tr>
   <td class="content_rowl">Vendedor</td>
   <td class="content_row"><?=$headdata["seller_firstname"]?> <?=$headdata["seller_lastname"]?></td>
   <td class="content_rowl">IVA</td>
   <td class="content_row">
      <?php
      if((int)$headdata["invc_taxes"])
         echo 'CON IVA';
      else
         echo 'SIN IVA';
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones<br>[cliente]</td>
   <td class="content_row" valign="top"><?=$headdata["invc_desc"]?></td>
   <td class="content_rowl" valign="top">Observaciones<br>[interno]</td>
   <td class="content_row" valign="top"><?=$headdata["invc_desc_intern"]?></td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($headdata["invc_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["invc_upddat"])?></td>
</tr>
<?php
if(trim($headdata["cust_notes"]) != "")
{  ?>
   <tr>
      <td class="content_rowl" valign="top">Comentarios</td>
      <td class="content_row" colspan="3" style="color:navy"><?generateCommentToogle($headdata["cust_notes"])?></td>
   </tr>
   <?php
}
?>
</tbody>
</table>
<?=Nifty_printF()?>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<br>
<?php
$hasItems = false;
$gc = 0;
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $part       = $invcparts[$x];
   $posdata    = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $part["id"], $_REQUEST["setPosOrder"], "_bol");
   $rowcount   = count($posdata);

   $rowcount = count($posdata);
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="120">
      <col>
      <col width="80">
      <col width="80">
      <col width="80">
      <col width="80">
      <col width="80">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7">Productos</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Código</td>
      <td class="content_tbl_subheader">Artículo</td>
      <td class="content_tbl_subheader" align="center">Unidad</td>
      <td class="content_tbl_subheader" align="right">Cantidad</td>
      <td class="content_tbl_subheader" align="right">Reserva</td>
      <td class="content_tbl_subheader" align="right">Precio/Bruto</td>
      <td class="content_tbl_subheader" align="right"><nobr>Precio Total</nobr></td>
   </tr>
   <?php
   for($y = 0; $y < $rowcount; $y++)
   {
      ?>
      <tr bgcolor="<?=getRowColor($y)?>">
         <td class="content_row"><?=$posdata[$y]["item_number_prod"]?>&nbsp;</td>
         <td class="content_row"><?=$posdata[$y]["item_title"]?></td>
         <td class="content_row" align="center">
            <?php
            if((int)$posdata[$y]["item_id"])
               echo getItemUnitDesc($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
            echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" align="right" valign="top">
            <input type="text" class="text" style="width:80px;text-align:right"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
            name="item_amount_<?=$part["id"]?>_<?=$y?>" id="item_amount_<?=$part["id"]?>_<?=$y?>" <?=$rdlo?>
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_amount"],2)?>">
         </td>
         <td class="content_row" align="right" valign="top">
            <input type="text" class="text" style="width:80px;text-align:right;<?php if(!(int)$headdata["invc_cust_id"]) echo "background-color:#EEEEEE" ?>"
            <?php
            if((int)$headdata["invc_cust_id"])
            {  ?> onfocus="markfield(this,0)" onblur="markfield(this,1)" <?php } ?>
            autocomplete="off"
            name="item_reserva_amount_<?=$part["id"]?>_<?=$y?>" id="item_reserva_amount_<?=$part["id"]?>_<?=$y?>" <?=$rdlo?>
            <?php if(!(int)$headdata["invc_cust_id"]) echo "readonly" ?>
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_reserva_amount"],2)?>">
         </td>
         <td class="content_row" align="right" valign="top">
            <nobr>
            <input type="text" class="text" <?=$dscrdlo?> autocomplete="off"
            style="<?if(!(int)$headdata["invc_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right"
            name="item_sellprice_brutto_<?=$part["id"]?>_<?=$y?>" id="item_sellprice_brutto_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_brutto"])?>">
            </nobr>
         </td>
         <td class="content_row" align="right" valign="top">
            <nobr>
            <input type="text" class="text" readonly tabindex="-1"
            style="<?if(!(int)$headdata["invc_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right;background-color:<?if((int)$posdata[$y]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            name="item_sellprice_brutto_dsc_<?=$part["id"]?>_<?=$y?>" id="item_sellprice_brutto_dsc_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_brutto_dsc"])?>">
            </nobr>
         </td>
      </tr>
      <?php
      $gc++;
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
}
if(count($invcparts) && $invcparts != false)
{  ?>
   <table border="0" cellpadding="0" cellspacing="0">
   <tr>
      <td valign="top">
         <?php
         if($_REQUEST["showDiscounts"] == "1")
            $ftablewidth = 585;
         else
            $ftablewidth = 980;
         ?>
         <?=Nifty_printH("box1", $ftablewidth)?>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="padding:1px">
         <colgroup>
            <col>
            <col width="55">
            <col width="100">
         </colgroup>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_clear" colspan="2">
               <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td class="content_row_clear">DESCUENTOS</td>
                  <td class="content_row_clear" width="140">
                     <nobr>
                     <input type="text" class="text" style="text-align:center;width:100px;"
                     id="invc_discount_perc" name="invc_discount_perc"
                     value="<?=printPrice($headdata["invc_shop_discount_perc"],2)?>" <?=$rdlo?>> %
                     </nobr>
                  </td>
                  <td class="content_row_clear" align="right" width="1">
                     <nobr>
                     <input type="text" class="text" style="text-align:center;width:100px;"
                     id="invc_discount_amt" name="invc_discount_amt"
                     value="<?=printPrice($headdata["invc_shop_discount_amt"],2)?>" <?=$rdlo?>> $
                     </nobr>
                  </td>
               </tr>
               </table>
            </td>
         </tr>
         <?php
         if($headdata["invc_total_taxes"] > 0.00)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_totals content_rowl" colspan="2">Total bruto</td>
               <td class="content_row_totals content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["invc_total_brutto"])?>">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row content_rowl" colspan="2"><b>IVA</b></td>
               <td class="content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["invc_total_taxes"])?>">
               </td>
            </tr>
            <?php
         }
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_rowl" colspan="2">Total neto</td>
            <td class="content_row_totals content_row" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
               value="<?=printPrice($headdata["invc_total_netto"])?>">
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
      </td>
      <td width="15" class="content_row_clear">&nbsp;</td>
      <td valign="top">
      </td>
   </tr>
   </table>
   <br>
   <?php
}
if($_REQUEST["showDiscounts"] == "1")
   $ftablewidth = 1180;
else
   $ftablewidth = 980;
?>
<?=Nifty_printH("boxopt_b", $ftablewidth)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php

//----------------------------------------------------------------------------------
if($pdffile != "")
{
   $doctitle = "Boleta-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}

if((int)$_REQUEST["printsiidoc1"] || (int)$_REQUEST["printsiidoc2"])
{
   if((int)$_REQUEST["printsiidoc1"])
   {
      $doctitle   = "Boleta-{$headdata["invc_docnumber"]}.pdf";
      $docfile    = $headdata["invc_sgntr_doc1"];
   }
   else
   {
      $doctitle   = "Cedible-{$headdata["invc_docnumber"]}.pdf";
      $docfile    = $headdata["invc_sgntr_doc2"];
   }
   $pdflink    = "./libs/modules/structure/document_file.php?type=0&hash={$docfile}&name={$doctitle}&path=../../../docs.electrpdf/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}