<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if((int)$_REQUEST["setprices"])
{
   $currtme = time();
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "itemchangeprice_") !== false && strpos($reqkey, "itemchangeprice_") == 0 && (int)$_REQUEST[$reqkey])
      {
         $idx        = substr($reqkey, strpos($reqkey, "_") +1);
         $idxarr     = explode("_", $idx);
         $newprice   = getPrice($_REQUEST["itemnewprice_{$idx}"]);
         $newmarge   = getPrice($_REQUEST["itemmargen_{$idx}"],2);
         $itemid     = $idxarr[1];
         $itemtype   = $idxarr[2];

         if($newprice > 0.00 && $itemid > 0)
         {
            $sql = " select item_sellprice_taxes_perc
                     from {$itemtype}
                     where
                     id = {$itemid}";
            $item_sellprice_taxes_perc = $CON->select($sql);
            $item_sellprice_taxes_perc = $item_sellprice_taxes_perc[0]["item_sellprice_taxes_perc"];
            
            $item_sellprice_taxes      = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $newprice / 100 * $item_sellprice_taxes_perc);
            $item_sellprice_brutto     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $newprice + $item_sellprice_taxes);

            $sql = " update {$itemtype}
                     set
                     item_sellprice_brutto      = {$item_sellprice_brutto},
                     item_sellprice_taxes       = {$item_sellprice_taxes},
                     item_sellprice_netto       = {$newprice},
                     item_updusr                = {$_SESSION["user_id"]},
                     item_upddat                = {$currtme}
                     where
                     id = {$itemid}";
            $res = $CON->no_result($sql);

            if($itemtype == "itemlist")
               updateItemlistStorePrices($CON, $itemid);
            else
               updateItemStorePrices($CON, $itemid);
               
            registerSellPriceHistory($CON, $itemid, $itemtype);
            
            $_PRICECHANGES[$itemtype][$itemid] = $newprice;
            $_PRICEMARGENS[$itemtype][$itemid] = $newmarge;
         }
      }
   }
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["savecosts"])
{
   $invc_base_total = (float)$_REQUEST["invc_base_total"];
   
   $sql = " delete from invoices_buy_valuecosts
            where
            invc_id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $currtme    = time();
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "cost_value_") !== false && strpos($reqkey, "cost_value_") == 0)
      {
         $idx                 = substr($reqkey, strrpos($reqkey, "_") +1);
         $cost_value          = getPrice($_REQUEST["cost_value_{$idx}"],4);
         $cost_money          = $_REQUEST["cost_money_{$idx}"];
         $cost_exchangevalue  = getPrice($_REQUEST["cost_exchangevalue_{$idx}"],4);
         $cost_docnum         = trim(addslashes($_REQUEST["cost_docnum_{$idx}"]));
         $cost_date           = trim(addslashes($_REQUEST["cost_date_{$idx}"]));
         $cost_date           = explode(".", $cost_date);
         $cost_date           = (int)mktime(15, 0, 0, $cost_date[1], $cost_date[0], $cost_date[2]);
         $cost_desc           = trim(addslashes($_REQUEST["cost_desc_{$idx}"]));
         $cost_type           = (int)$_REQUEST["cost_type_{$idx}"];

         if(!$cost_type)
         {
            $cost_total = 0.00;
            if($cost_money == "CLP")
               $cost_total = $cost_value;
            else
               $cost_total = round($cost_value * $cost_exchangevalue,0);
         }
         else
         {
            $cost_money = "CLP";
            $cost_total = round($invc_base_total / 100 * $cost_value,0);
         }

         if($cost_value > 0.00)
         {
            $sql = " insert into invoices_buy_valuecosts
                     (invc_id, cost_pos, cost_value, cost_money, cost_exchangevalue, cost_docnum,
                      cost_date, cost_desc, cost_total, cost_type)
                     VALUES
                     ({$_REQUEST["id"]}, {$poscounter}, {$cost_value}, '{$cost_money}', {$cost_exchangevalue},
                      '{$cost_docnum}', {$cost_date}, '{$cost_desc}', {$cost_total}, {$cost_type})";
            $CON->no_result($sql);
            $poscounter++;
         }
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_company, t2.supp_email, t3.company_short, t4.shop_name, t1.invc_supplier_id, t1.invc_taxes,
                t2.supp_notes,  t7.pay_title
         from invoices_buy t1
         LEFT OUTER JOIN supplier t2      ON t1.invc_supplier_id  = t2.id
         LEFT OUTER JOIN company_data t3  ON t1.invc_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.invc_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.invc_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.invc_crtusr       = t6.id
         LEFT OUTER JOIN payments t7      ON t1.invc_paymentid    = t7.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = "select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                t1.note_total_netto 'invc_total_netto', t1.note_import_total 'invc_import_total',
                t1.note_exc_rate 'invc_exc_rate', t1.note_payed 'invc_payed', 
                t1.note_type 'invcoice', t1.note_invcnumber, t1.note_importation 'invc_importation', t4.supp_company
         from invoices_notes_buy t1
         LEFT OUTER JOIN supplier t4 ON t1.note_supplier_id  = t4.id
         where
         t1.note_status          > 1 and
         t1.note_parent_invcid   = {$headdata["id"]}
         order by 2 desc, 11 desc, 1 desc";
$headnotes = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre
         from supplier t1
         LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
         where
         t1.id = {$headdata["invc_supplier_id"]}";
$supplier = $CON->select($sql);
$supplier = $supplier[0];

//----------------------------------------------------------------------------------
$sql = " select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber, t1.invc_total_netto,
                t1.invc_import_total, t1.invc_exc_rate, t1.invc_payed, 
                'type' 'invcoice', 'note_invcnumber' 'note_invcnumber',
                t1.invc_importation, t4.supp_company
         from invoices_buy t1
         LEFT OUTER JOIN supplier t4 ON t1.invc_supplier_id  = t4.id
         where
         t1.invc_status > 1 and
         t1.invc_parent_invcid = {$_REQUEST["id"]}
         order by t1.id asc";
$costinvoices = $CON->select($sql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($costinvoices) && $costinvoices != false; $x++)
{
   $sql = " select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                   t1.note_total_netto 'invc_total_netto', t1.note_import_total 'invc_import_total',
                   t1.note_exc_rate 'invc_exc_rate', t1.note_payed 'invc_payed', 
                   t1.note_type 'invcoice', t1.note_invcnumber, t1.note_importation 'invc_importation', t4.supp_company
            from invoices_notes_buy t1
            LEFT OUTER JOIN supplier t4 ON t1.note_supplier_id  = t4.id
            where
            t1.note_status          > 1 and
            t1.note_parent_invcid   = {$costinvoices[$x]["id"]}
            order by 2 desc, 11 desc, 1 desc";
   $costnotes = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select SUM(item_costprice_import_total) 'excl_item_costprice_import',
                   SUM(item_costprice_netto_dsc2) 'excl_item_costprice'
            from invoices_buy_parts_items
            where
            invc_id = {$costinvoices[$x]["id"]} and
            item_no_costcalc = 1";
   $excludes = $CON->select($sql);
   $excludes = $excludes[0];

   //----------------------------------------------------------------------------------
   if((int)$costinvoices[$x]["invc_importation"])
      $costinvoices[$x]["invc_import_total"] -= (float)$excludes["excl_item_costprice_import"];
   else
      $costinvoices[$x]["invc_total_netto"]  -= (float)$excludes["excl_item_costprice"];

   $costinvoices[$x]["_notes"] = $costnotes;
}

//----------------------------------------------------------------------------------
$sql = " select *
         from invoices_buy_valuecosts
         where
         invc_id     = {$_REQUEST["id"]} and
         cost_type   = 0
         order by cost_pos asc";
$valuecosts = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from invoices_buy_valuecosts
         where
         invc_id     = {$_REQUEST["id"]} and
         cost_type   = 1";
$perccost = $CON->select($sql);
$perccost = $perccost[0];
?>
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Valorización de facturas: Calculo</b></td>
   <td align="right"><div id="idx_status_msg"><?=$savemsg?></div></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
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
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$headdata["invc_number"]?></td>
   <td class="content_rowl">Número factura</td>
   <td class="content_row"><?=$headdata["invc_docnumber"]?></td>
</tr>
<tr>
   <td class="content_rowl">Fecha factura</td>
   <td class="content_row"><?=date('d.m.Y', $headdata["invc_date"])?></td>
   <td class="content_rowl">IVA</td>
   <td class="content_row">
      <?php
      if((int)$headdata["invc_taxes"])
         echo "<b class='msg_save_ok'>CON IVA</b>";
      else
      {
         echo "<b class='msg_save_err'>SIN IVA</b>";
         if((int)$headdata["invc_importation"])
         {  ?>
            <b class="msg_save_err">[ IMPORTACIÓN ]</b>
            <span style="padding-left:44px">
               1 USD = <?=printPrice($headdata["invc_exc_rate"],8)?> CLP
            </span>
            <?php
         }
      }
      ?>
   </td>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<tr>
   <td class="content_rowl">Proveedor</td>
   <td class="content_row"><?=$headdata["supp_company"]?></td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row"><?=$supplier["supp_rut"]?>&nbsp;</td>
</tr>
<?php
if((int)$headdata["invc_importation"])
{  ?>
   <tr>
      <td class="content_rowl">Monto Neto</td>
      <td class="content_row"><?=printPrice($headdata["invc_import_total"])?></td>
      <td class="content_rowl">Monto Neto (USD)</td>
      <td class="content_row"><?=printPrice($headdata["invc_total_netto"],2)?>&nbsp;</td>
   </tr>
   <?php
   $invc_base_total = $headdata["invc_import_total"];
}
else
{  ?>
   <tr>
      <td class="content_rowl">Monto Neto</td>
      <td class="content_row"><?=printPrice($headdata["invc_total_netto"])?></td>
      <td class="content_rowl">Monto Neto (USD)</td>
      <td class="content_row">- - - </td>
   </tr>
   <?php
   $invc_base_total = $headdata["invc_total_netto"];
}
if(count($headnotes) && $headnotes != false)
{  ?>
   <tr>
      <td class="content_row" colspan="4">
         <b>Notas relacionadas</b><br>
         <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="margin-left:-2px">
         <colgroup>
            <col width="138">
            <col width="180">
            <col width="120">
            <col>
            <col width="120">
         </colgroup>
         <tr>
            <td class="content_tbl_subheader">Número int</td>
            <td class="content_tbl_subheader">Comprobante Prov.</td>
            <td class="content_tbl_subheader">Fecha</td>
            <td class="content_tbl_subheader">Proveedor</td>
            <td class="content_tbl_subheader" align="right">Costo total (neto)</td>
         </tr>
         <?php
         $ges_headnotes = 0.00;
         for($y = 0; $y < count($headnotes) && $headnotes != false; $y++)
         {
            $note = $headnotes[$y];
            if((int)$note["invc_importation"])
               $invc_total = $note["invc_import_total"];
            else
               $invc_total = $note["invc_total_netto"];

            if($note["invcoice"] == "2")
            {
               $ges_headnotes    += $invc_total;
               $dspdign = "+";
            }
            else
            {
               $ges_headnotes   -= $invc_total;
               $dspdign = "-";
            }
            ?>
            <tr bgcolor="<?=getRowColor($y)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row"><?=$note["invc_number"]?></td>
               <td class="content_row"><?=$note["invc_docnumber"]?></td>
               <td class="content_row"><?=date('d.m.Y', $note["invc_date"])?></td>
               <td class="content_row"><?=$note["supp_company"]?>&nbsp;</td>
               <td class="content_row" align="right"><?=$dspdign?> <?=printPrice($invc_total)?></td>
            </tr>
            <?php
         }
         $adjust_perc = round($ges_headnotes / $invc_base_total * 100, 4);
         ?>
         <tr bgcolor="#FFFFFF">
            <td class="content_row_totals" align="right" colspan="4">AJUSTE ( <?=printPrice($adjust_perc, 4)?> % )</td>
            <td class="content_row_totals" align="right"><?=printPrice($ges_headnotes)?></td>
         </tr>
         </table>
      </td>
   </tr>
   <?php
}
?>
</table>
<?=Nifty_printF()?>
<br>
<script language="Javascript">
function loadMoneyChange(idx, xdate, cost_money)
{
   document.getElementById('idxifrsrc').src='./libs/modules/invoices_buy/values.calculation.getmoney.php?xdate=' +xdate +'&rowcount=' +idx +'&cost_money=' +cost_money;
}
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="xform_costvals">
<input type="hidden" name="subcatexec" value="edit">
<input type="hidden" name="exec" value="editinvoice">
<input type="hidden" name="savecosts" value="1">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="invc_base_total" value="<?=$invc_base_total?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="200">
   <col width="120">
   <col width="100">
   <col>
   <col width="120">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="5">Gastos relacionados</td>
</tr>
<tr>
   <td class="content_tbl_subheader">Número int</td>
   <td class="content_tbl_subheader">Comprobante Prov.</td>
   <td class="content_tbl_subheader">Fecha</td>
   <td class="content_tbl_subheader">Proveedor</td>
   <td class="content_tbl_subheader" align="right">Costo total (neto)</td>
</tr>
<?php
//----------------------------------------------------------------------------------
$ges_total = 0;
for($x = 0; $x < count($costinvoices) && $costinvoices != false; $x++)
{
   if((int)$costinvoices[$x]["invc_importation"])
      $invc_total = $costinvoices[$x]["invc_import_total"];
   else
      $invc_total = $costinvoices[$x]["invc_total_netto"];

   $ges_total += $invc_total;
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row"><?=$costinvoices[$x]["invc_number"]?></td>
      <td class="content_row"><?=$costinvoices[$x]["invc_docnumber"]?></td>
      <td class="content_row"><?=date('d.m.Y', $costinvoices[$x]["invc_date"])?></td>
      <td class="content_row"><?=$costinvoices[$x]["supp_company"]?>&nbsp;</td>
      <td class="content_row" align="right">+ <?=printPrice($invc_total)?></td>
   </tr>
   <?php
   for($y = 0; $y < count($costinvoices[$x]["_notes"]) && $costinvoices[$x]["_notes"] != false; $y++)
   {
      $note = $costinvoices[$x]["_notes"][$y];
      if((int)$note["invc_importation"])
         $invc_total = $note["invc_import_total"];
      else
         $invc_total = $note["invc_total_netto"];

      if($note["invcoice"] == "2")
      {
         $ges_total += $invc_total;
         $dspdign = "+";
      }
      else
      {
         $ges_total -= $invc_total;
         $dspdign = "-";
      }
      ?>
      <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
         <td class="content_row"><?=$note["invc_number"]?></td>
         <td class="content_row"><?=$note["invc_docnumber"]?></td>
         <td class="content_row"><?=date('d.m.Y', $note["invc_date"])?></td>
         <td class="content_row"><?=$note["supp_company"]?>&nbsp;</td>
         <td class="content_row" align="right"><?=$dspdign?> <?=printPrice($invc_total)?></td>
      </tr>
      <?php
   }
}
?>
<tr>
   <td class="content_tbl_subheader" colspan="5"><b>Gastos adicionales / no facturados</b></td>
</tr>
<tr>
   <td class="content_tbl_subheader">Monto&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;|&nbsp;Moneda&nbsp;| Cambio</td>
   <td class="content_tbl_subheader">Nº Documento</td>
   <td class="content_tbl_subheader">Fecha</td>
   <td class="content_tbl_subheader">Descripción</td>
   <td class="content_tbl_subheader" align="right">Monto Total CLP</td>
</tr>
<?php
$moneytypes = getMoneyTypes();

$rowcount = 1;
if($valuecosts != false && count($valuecosts))
   $rowcount = count($valuecosts) +1;
   
for($y = 0; $y < $rowcount; $y++)
{  ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row">
         <input type="text" class="text" style="width:60px;text-align:right" autocomplete="off"
         name="cost_value_<?=$y?>" id="cost_value_<?=$y?>"
         value="<?php if($valuecosts[$y]["cost_value"] > 0.00) echo printPrice($valuecosts[$y]["cost_value"],4)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <select class="text" name="cost_money_<?=$y?>" id="cost_money_<?=$y?>"
         onchange="if(this.value != 'CLP') $('#cost_exchangevalue_<?=$y?>').show(); else $('#cost_exchangevalue_<?=$y?>').hide();
                   loadMoneyChange('<?=$y?>', $('#cost_date_<?=$y?>').val(), this.value)">
            <?php
            foreach($moneytypes AS $moneytype)
            {  ?>
               <option value="<?=$moneytype?>"
               <?php if($moneytype == $valuecosts[$y]["cost_money"]) echo "selected"?>><?=$moneytype?></option>
               <?php
            }
            ?>
         </select>
         <input type="text" class="text" autocomplete="off"
         style="width:70px;text-align:right;display:<?if($valuecosts[$y]["cost_money"] == "" || $valuecosts[$y]["cost_money"] == "CLP") echo "none"?>"
         name="cost_exchangevalue_<?=$y?>" id="cost_exchangevalue_<?=$y?>"
         value="<?php if($valuecosts[$y]["cost_exchangevalue"] > 0.00) echo printPrice($valuecosts[$y]["cost_exchangevalue"],4)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input type="text" class="text" style="width:100px" autocomplete="off"
         name="cost_docnum_<?=$y?>" id="cost_docnum_<?=$y?>"
         value="<?=$valuecosts[$y]["cost_docnum"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input type="text" class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
         style="width:70px;" autocomplete="off"
         onchange="loadMoneyChange('<?=$y?>', this.value, $('#cost_money_<?=$y?>').val())"
         name="cost_date_<?=$y?>" id="cost_date_<?=$y?>"
         value="<?php if((int)$valuecosts[$y]["cost_date"]) echo date('d.m.Y', $valuecosts[$y]["cost_date"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row">
         <input type="text" class="text" style="width:400px;" autocomplete="off"
         name="cost_desc_<?=$y?>" id="cost_desc_<?=$y?>"
         value="<?=$valuecosts[$y]["cost_desc"]?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right">
         <input type="text" class="text" style="background-color:#EEEEEE;width:100px;text-align:right" readonly
         name="cost_total_<?=$y?>" id="cost_total_<?=$y?>"
         value="<?php if($valuecosts[$y]["cost_total"] > 0.00) echo printPrice($valuecosts[$y]["cost_total"],0)?>">
      </td>
   </tr>
   <?php
   $ges_total += $valuecosts[$y]["cost_total"];
}
?>
<tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
   <td class="content_row">Base: $ <?=printPrice($invc_base_total,2)?></td>
   <td class="content_row">
      %
      <input type="text" class="text" style="width:85px" autocomplete="off"
      name="cost_value_<?=$y?>" id="cost_value_<?=$y?>"
      value="<?=printPrice($perccost["cost_value"],4)?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
      <input type="hidden" name="cost_type_<?=$y?>" value="1">
   </td>
   <td class="content_row" align="center">Otros gastos</td>
   <td class="content_row">
      <input type="text" class="text" style="width:400px;" autocomplete="off"
      name="cost_desc_<?=$y?>" id="cost_desc_<?=$y?>"
      value="<?=$perccost["cost_desc"]?>"
      onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
   <td class="content_row" align="right">
      <input type="text" class="text" style="background-color:#EEEEEE;width:100px;text-align:right" readonly
      name="cost_total_<?=$y?>" id="cost_total_<?=$y?>"
      value="<?php if($perccost["cost_total"] > 0.00) echo printPrice($perccost["cost_total"],0)?>">
   </td>
</tr>
<?php
$ges_total += $perccost["cost_total"];
if(!$x)
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row" colspan="5" align="center">
         <br>
         <b class="msg_save_err">No hay datos disponibles.</b>
         <br><br>
      </td>
   </tr>
   <?php
}
else
{  ?>
   <tr bgcolor="<?=getRowColor(0)?>">
      <td class="content_row_totals" colspan="4"><b>TOTAL</b></td>
      <td class="content_row_totals" align="right"><b><?=printPrice($ges_total)?></b></td>
   </tr>
   <?php
}
?>
<tr bgcolor="<?=getRowColor(0)?>">
   <td class="content_row_clear" colspan="5" align="right">
      <?php
      $_SESSION["_SUBMITBTN"] = 1;
      printButton("Guardar gastos", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_costvals)", "tick-circle-frame", 160);
      ?>
   </td>
</tr>
</table>
<?=Nifty_printF(false)?>
</form>
<br>
<?php
//----------------------------------------------------------------------------------
if($x)
{
   $invcparts = getInvoiceBuyParts($CON, $_REQUEST["id"]);
   ?>
   <script language="JavaScript">
      function setMainMargen()
      {
         var mvalue  = document.getElementById('main_margen').value;
         var xmvalue = mvalue.replace('.','');
         xmvalue = xmvalue.replace(',','.');
         xmvalue = parseFloat(xmvalue);

         if(isNaN(xmvalue))
         {
            xmvalue = 0;
            mvalue  = 0;
         }
         $(".mval").each(function ()
         {
            $(this).val(mvalue);
            var itemid  = $(this).attr('id');
            var idarr   = itemid.split('_');
            var ididx   = idarr[1] + '_' + idarr[2] + '_' + idarr[3] + '_' + idarr[4];

            var costprice  = parseFloat(document.getElementById('itemcostprice_' +ididx).value);
            var newprice   = document.getElementById('itemnewprice_' +ididx);
            newprice.value = Math.round(costprice + costprice / 100 * xmvalue);
         });
      }
      function setItemMargen(ididx)
      {
         var mvalue  = document.getElementById('itemmargen_' + ididx).value;
         var xmvalue = mvalue.replace('.','');
         xmvalue = xmvalue.replace(',','.');
         xmvalue = parseFloat(xmvalue);
         if(isNaN(xmvalue))
         {
            xmvalue = 0;
            mvalue  = 0;
         }
         var costprice  = parseFloat(document.getElementById('itemcostprice_' +ididx).value);
         var newprice   = document.getElementById('itemnewprice_' +ididx);
         newprice.value = Math.round(costprice + costprice / 100 * xmvalue);
      }
      function markAllInvcItems(stat)
      {
         $(".mchk").each(function ()
         {
            $(this).attr('checked', stat);
         });
      }
   </script>
   <?php
   if(!(int)$_REQUEST["setprices"])
   {  ?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="980">
      <colgroup>
         <col width="70">
         <col width="160">
         <col>
      </colgroup>
      <tr>
         <td class="content_row_clear">
            <nobr>
            <input type="text" class="text" style="width:50px;text-align:center" id="main_margen"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"> %
            </nobr>
         </td>
         <td class="content_row_clear">
            <?php
            printButton("Aplicar % utilidad", "postnav_save", "javascript: deactivateFormChange()", "setMainMargen()", "tick-circle-frame");
            ?>
         </td>
         <td class="content_row_clear" align="right">
            <input type="checkbox" onclick="markAllInvcItems(this.checked)"> Marcar todos los artículos
         </td>
      </tr>
      </table>
      <br>
      <?php
   }
   if(!(int)$_REQUEST["setprices"])
   {  ?>
      <form action="index.php" method="post" name="xform_invcvalues">
      <input type="hidden" name="subcatexec" value="edit">
      <input type="hidden" name="exec" value="editinvoice">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="setprices" value="">
      <?php
   }
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="30">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="12">Valorización por artículo</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader content_row_os" align="center">Pos</td>
      <td class="content_tbl_subheader content_row_os">Artículo</td>
      <td class="content_tbl_subheader content_row_os" align="right">Cantidad</td>
      <td class="content_tbl_subheader content_row_os" align="right"><nobr>Precio total</nobr></td>
      <td class="content_tbl_subheader content_row_os" align="right"><nobr>% Gastos</nobr></td>
      <td class="content_tbl_subheader content_row_os" align="right">Gastos</td>
      <td class="content_tbl_subheader content_row_os" align="right">FOB</td>
      <td class="content_tbl_subheader content_row_os" align="right">Ajustes</td>
      <td class="content_tbl_subheader content_row_os" align="right"><nobr>Costo/U</nobr></td>
      <td class="content_tbl_subheader content_row_os" align="center"><nobr>% Utilidad</nobr></td>
      <td class="content_tbl_subheader content_row_os" align="right"><nobr>Precio/V act</nobr></td>
      <td class="content_tbl_subheader content_row_os" align="right"><nobr>Precio/V nuevo</nobr></td>
   </tr>
   <?php
   //----------------------------------------------------------------------------------
   $poscounter = 1;
   for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
   {
      $part       = $invcparts[$x];
      $posdata    = getInvoiceBuyPartsItems($CON, $_REQUEST["id"], $part["id"]);

      for($y = 0; $y < count($posdata) && $posdata != false; $y++)
      {
         $idx = "{$part["id"]}_{$posdata[$y]["item_id"]}_{$posdata[$y]["item_type"]}_{$posdata[$y]["item_pos"]}";

         //----------------------------------------------------------------------------------
         if((int)$headdata["invc_importation"])
         {
            $item_price_total = $posdata[$y]["item_costprice_import_total"];
            $item_cost_perc   = $item_price_total / $headdata["invc_import_total"] * 100;
         }
         else
         {
            $item_price_total = $posdata[$y]["item_costprice_netto_dsc2"];
            $item_cost_perc   = $item_price_total / $headdata["invc_total_netto"] * 100;
         }

         //----------------------------------------------------------------------------------
         $item_cost_total  = round($ges_total / 100 * $item_cost_perc,0);
         $adjust_val       = round($item_price_total / 100 * $adjust_perc, 0);
         $item_cost_fob    = 0.00;

         $sql = " select item_supp_fob
                  from {$posdata[$y]["item_type"]}_suppliers
                  where
                  item_id     = {$posdata[$y]["item_id"]} and
                  supplier_id = {$headdata["invc_supplier_id"]}";
         $itemfob = $CON->select($sql);
         $itemfob = (int)$itemfob[0]["item_supp_fob"];

         if($itemfob)
            $item_cost_fob = round($item_price_total / 100 * 6.0);

         //----------------------------------------------------------------------------------
         $item_cost_value  = round(($item_price_total + $item_cost_total + $item_cost_fob + $adjust_val) / $posdata[$y]["item_amount"], 0);
         $sellprice        = getShopItemStorePrice($CON, $headdata["invc_shop_id"], $posdata[$y]["item_id"], $posdata[$y]["item_type"]);

         //----------------------------------------------------------------------------------
         $sql = " select item_sellprice_calc
                  from {$posdata[$y]["item_type"]}
                  where
                  id = {$posdata[$y]["item_id"]}";
         $item_price_calced = $CON->select($sql);
         $item_price_calced = (int)$item_price_calced[0]["item_sellprice_calc"];

         if($posdata[$y]["item_type"] == "manual")
            $item_price_calced = 1;

         
         //$item_cost_value  += $adjust_val;

         $total_item_price_total += $item_price_total;
         $total_item_cost_perc   += $item_cost_perc;
         $total_item_cost_total  += $item_cost_total;
         $total_item_cost_value  += $item_cost_value;
         $total_item_cost_fob    += $item_cost_fob;
         $total_adjust_val       += $adjust_val;
         ?>
         <tr bgcolor="<?=getRowColor($poscounter +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
            <td class="content_row_os" align="center"><?=$poscounter?></td>
            <td class="content_row_os"><?=$posdata[$y]["item_title"]?></td>
            <td class="content_row_os" align="right"><?=printPrice($posdata[$y]["item_amount"],2)?></td>
            <td class="content_row_os" align="right"><?=printPrice($item_price_total)?></td>
            <td class="content_row_os" align="right"><?=printPrice($item_cost_perc,4)?></td>
            <td class="content_row_os" align="right"><?=printPrice($item_cost_total)?></td>
            <td class="content_row_os" align="right"><?=printPrice($item_cost_fob)?></td>
            <td class="content_row_os" align="right"><?=printPrice($adjust_val)?></td>
            <td class="content_row_os" align="right"><?=printPrice($item_cost_value)?></td>
            <td class="content_row_os" align="center">
               <?php
               if((int)$_REQUEST["setprices"])
               {
                  if($_PRICEMARGENS[$posdata[$y]["item_type"]][$posdata[$y]["item_id"]] != "")
                     echo "<b class='msg_save_ok'>{$_PRICEMARGENS[$posdata[$y]["item_type"]][$posdata[$y]["item_id"]]}</b>";
                  else
                     echo "<b class='msg_save_err'>- - -</b>";
               }
               elseif(!$item_price_calced)
               {  ?>
                  <input type="text" class="text mval" style="width:50px;text-align:center"
                  name="itemmargen_<?=$idx?>" id="itemmargen_<?=$idx?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  onchange="setItemMargen('<?=$idx?>')">
                  <?php
               }
               else
                  echo "<b class='msg_save_err'>- - -</b>";
               ?>
            </td>
            <td class="content_row_os" align="right"><?=printPrice($sellprice["itemshop_sellprice_netto"])?></td>
            <td class="content_row_os" align="right">
               <?php
               if((int)$_REQUEST["setprices"])
               {
                  if($_PRICECHANGES[$posdata[$y]["item_type"]][$posdata[$y]["item_id"]] > 0.00)
                     echo "<b class='msg_save_ok'>CAMBIADO</b>";
                  else
                     echo "<b class='msg_save_err'>NO CAMBIADO</b>";
               }
               elseif(!$item_price_calced)
               {  ?>
                  <input type="checkbox" class="checkbox mchk" value="1"
                  name="itemchangeprice_<?=$idx?>" id="item_change_price_<?=$idx?>">

                  <input type="text" class="text" style="width:80px;text-align:right"
                  name="itemnewprice_<?=$idx?>" id="itemnewprice_<?=$idx?>"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">

                  <input type="hidden" id="itemcostprice_<?=$idx?>" value="<?=$item_cost_value?>">
                  <?php
               }
               else
                  echo "<b class='msg_save_err'>- - -</b>";
               ?>
            </td>
         </tr>
         <?php
         $poscounter++;
      }
   }
   ?>
   <tr bgcolor="<?=getRowColor(0)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_totals" colspan="3">TOTAL</td>
      <td class="content_row_totals" align="right"><?=printPrice($total_item_price_total)?></td>
      <td class="content_row_totals" align="right"><?=printPrice($total_item_cost_perc,4)?> %</td>
      <td class="content_row_totals" align="right"><?=printPrice($total_item_cost_total)?></td>
      <td class="content_row_totals" align="right"><?=printPrice($total_item_cost_fob)?></td>
      <td class="content_row_totals" align="right"><?=printPrice($total_adjust_val)?></td>
      <td class="content_row_totals" colspan="4">&nbsp;</td>
   <tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
   if(!(int)$_REQUEST["setprices"])
   {  ?>
      <table border="0" cellspacing="0" cellpadding="0" width="980">
      <tr>
         <td align="right">
            <?php
            printButton("Aplicar precios", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.xform_invcvalues.setprices.value='1';submitForm(document.xform_invcvalues);}", "tick-circle-frame", 160);
            ?>
         </td>
      </tr>
      </table>
      </form>
      <?php
   }
   else
   {  ?>
      <table border="0" cellspacing="0" cellpadding="0" width="980">
      <tr>
         <td align="right">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&subcatexec=edit&id={$_REQUEST["id"]}", "", "arrow-180", 160);
            ?>
         </td>
      </tr>
      </table>
      <?php
   }
}
?>
<iframe id="idxifrsrc" height="660" width="660" frameborder="0"></iframe>
<?php