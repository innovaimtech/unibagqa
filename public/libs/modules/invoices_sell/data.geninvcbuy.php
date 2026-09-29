<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
if((int)$_REQUEST["savefact"])
{
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_factor_") !== false && strpos($reqkey, "item_factor_") == 0)
      {
         $itemid = substr($reqkey, strrpos($reqkey, "_") +1);
         $item_factorshop = getPrice($_REQUEST[$reqkey]);

         $sql = " update item
                  set
                  item_factorshop = {$item_factorshop}
                  where
                  id = {$itemid}";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.shop_rel_suppid, t3.shop_rel_suppdelivid
         from invoices_sell t1
         LEFT OUTER JOIN customer t2 ON t1.invc_cust_id = t2.id
         INNER JOIN company_shops t3 ON t1.invc_shop_id = t3.id
         where
         t1.id = {$_REQUEST["id"]} ";
$invoice = $CON->select($sql);
$invoice = $invoice[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.company_short
         from company_shops t1
         INNER JOIN company_data t2 ON t1.shop_company_id = t2.id
         where
         t1.shop_status          > 0 and
         t1.shop_rel_custid      = {$invoice["invc_cust_id"]} and
         t1.shop_rel_custdelivid = {$invoice["invc_cust_delivid"]} and
         t1.shop_rel_suppid      > 0
         order by t1.shop_name";
$genshop = $CON->select($sql);
$genshop = $genshop[0];

//----------------------------------------------------------------------------------
$sql = " select *
         from supplier
         where
         id = {$invoice["shop_rel_suppid"]}";
$suppdata = $CON->select($sql);
$suppdata = $suppdata[0];

//----------------------------------------------------------------------------------
$sql = " select type_factor
         from matrix_factor
         where
         orig_shop_id   = {$invoice["invc_shop_id"]} and
         dest_shop_id   = {$genshop["id"]}";
$factortype = $CON->select($sql);
$factortype = $factortype[0]["type_factor"];

//----------------------------------------------------------------------------------
$posdata    = Array();
$invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"]);
   for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
      $posdata[] = $partposdata[$y];
}
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="100">
   <col>
   <col width="100">
   <col>
   <col width="100">
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="6">Empresa detectada</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$genshop["company_short"]?>&nbsp;</td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$genshop["shop_name"]?>&nbsp;</td>
   <td class="content_rowl">Proveedor</td>
   <td class="content_row"><?=$suppdata["supp_company"]?>&nbsp;</td>
</tr>
</table>
<?=Nifty_printF()?>
<br>
<form action="index.php" method="post" name="form_shppos" id="form_shppos">
<input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
<input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="savefact" value="1">
<input type="hidden" name="geninvc" value="0">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="120">
   <col>
   <col width="90">
   <col width="90">
   <col width="90">
   <col width="90">
   <col width="100">
</colgroup>
<tr>
   <td class="content_tbl_subheader content_row_os" valign="top">Codigo</td>
   <td class="content_tbl_subheader content_row_os" valign="top">Artículo</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Unidad</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Cantidad</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Factor</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="center">Cantidad/Dest</td>
   <td class="content_tbl_subheader content_row_os" valign="top" align="right"><nobr>Total/Neto</nobr></td>
</tr>
<?php
for($x = 0; $x < count($posdata) && $posdata != false; $x++)
{
   $unitdesc      = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
   $amt_dest      = 0.00;
   ?>
   <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
      <td class="content_row_os"><?=$posdata[$x]["item_number_prod"]?>&nbsp;</td>
      <td class="content_row_os"><?=$posdata[$x]["item_title"]?>&nbsp;</td>
      <td class="content_row_os" align="center"><?=$unitdesc?>&nbsp;</td>
      <td class="content_row_os" align="center"><?=printPrice($posdata[$x]["item_amount"],2)?>&nbsp;</td>
      <td class="content_row_os" align="center">
         <?php
         if($posdata[$x]["item_type"] == "item")
         {  ?>
            <input type="text" class="text" style="width:90px;text-align:center" name="item_factor_<?=$posdata[$x]["item_id"]?>" id="item_factor"
            value="<?=$posdata[$x]["item_factorshop"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <?php
            if($factortype == "M")
               $amt_dest = $posdata[$x]["item_amount"] * $posdata[$x]["item_factorshop"];
            elseif($factortype == "D")
               $amt_dest = round($posdata[$x]["item_amount"] / $posdata[$x]["item_factorshop"],2);
            else
               $amt_dest = $posdata[$x]["item_amount"];
               
         }
         else
         {  ?>
            <?=printPrice($posdata[$x]["item_amount"],2)?>
            <?php
            $amt_dest = $posdata[$x]["item_amount"];
         }
         ?>
      </td>
      <td class="content_row_os" align="center"><?=printPrice($amt_dest)?>&nbsp;</td>
      <td class="content_row_os" align="right"><?=printPrice($posdata[$x]["item_sellprice_netto_dsc"])?>&nbsp;</td>
   </tr>
   <?php
   $posdata[$x]["_amt_dest"] = $amt_dest;
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?php
if((int)$genshop["id"] && (int)$suppdata["id"])
{  ?>
   <?=Nifty_printH("boxopt_b", "980")?>
   <table border="0" cellspacing="0" cellpadding="0" width="100%">
   <tr>
      <td>&nbsp;</td>
      <td align="right" width="160" style="padding-right:5px">
         <?php
         printButton("Guardar Factores", "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black", 160);
         ?>
      </td>
      <td align="right" width="130">
         <?php
         printButton("Generar Factura", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')) { document.form_shppos.geninvc.value='1';submitForm(document.form_shppos) }", "gear", 160);
         ?>
      </td>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <?php
}
?>
</form>
<?php
if((int)$_REQUEST["geninvc"])
{
   //----------------------------------------------------------------------------------
   $invc_number      = createTransactionNumber($CON, $genshop["shop_company_id"], "invoicebuy");
   $invc_intnumber   = createInternalBuyNumber($CON, $genshop["shop_company_id"], $genshop["id"], "invoices_buy");
   $invc_date        = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
   $currtme          = time();
   
   $isremote = getShops($CON, false, false, 0, $genshop["id"]);
   $isremote = $isremote[0]["shop_isremote"];
   if($isremote)
      $invc_stockchange = 0;

   $sql = " select supp_paymentid
            from supplier
            where
            id = {$suppdata["id"]}";
   $supppaymentid = $CON->select($sql);
   $supppaymentid = (int)$supppaymentid[0]["supp_paymentid"];
   
   $sql = " insert into invoices_buy
            (invc_number, invc_supplier_id, invc_company_id, invc_shop_id, invc_date, invc_receipt_date, invc_contab_date,
             invc_taxes, invc_type, invc_stockchange, invc_importation, invc_exc_rate, invc_paymentid,
             invc_crtdat, invc_crtusr, invc_intnumber, invc_docnumber)
            VALUES
            ('{$invc_number}', {$suppdata["id"]}, {$genshop["shop_company_id"]},
              {$genshop["id"]}, {$invc_date}, {$invc_date}, {$invc_date}, {$invoice["invc_taxes"]}, 3,
              {$invc_stockchange}, 0, 0, {$supppaymentid}, {$currtme}, {$_SESSION["user_id"]},
              '{$invc_intnumber}', '{$invoice["invc_docnumber"]}')";
   $res = $CON->no_result($sql);
   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_buy
               where
               invc_crtusr = {$_SESSION["user_id"]}";
      $buyinvcid = $CON->select($sql);
      $buyinvcid = $buyinvcid[0]["thisid"];
      
      $sql = " insert into invoices_buy_parts
               (part_invc_id, part_crtdat, part_crtusr, part_shp_id, part_sord_id)
               VALUES
               ({$buyinvcid}, {$currtme}, {$_SESSION["user_id"]}, 0, 0)";
      $res = $CON->no_result($sql);

      if($res)
      {
         $sql = " select *
                  from invoices_buy_parts
                  where
                  part_invc_id = {$buyinvcid}";
         $partid = $CON->select($sql);
         $partid = $partid[0]["id"];

         $poscounter = 0;
         
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            $item_costprice_netto      = round($posdata[$x]["item_sellprice_netto_dsc"] / $posdata[$x]["_amt_dest"],2);
            $item_costprice_taxes_perc = $posdata[$x]["item_sellprice_taxes_perc"];
            $item_costprice_taxes      = round($item_costprice_netto / 100 * $item_costprice_taxes_perc,0);
            $item_costprice_brutto     = round($item_costprice_netto + $item_costprice_taxes,0);
            $item_costprice_netto_dsc  = round($item_costprice_netto * $posdata[$x]["_amt_dest"],0);
            
            $posdata[$x]["item_desc"]  = trim(addslashes($posdata[$x]["item_desc"]));
            
            $sql = " insert into invoices_buy_parts_items
                     (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                      item_costprice_netto_dsc, item_desc)
                     VALUES
                     ({$buyinvcid}, {$partid}, {$posdata[$x]["item_id"]}, {$poscounter}, {$posdata[$x]["_amt_dest"]}, '{$posdata[$x]["item_type"]}',
                      {$item_costprice_brutto}, {$item_costprice_taxes_perc}, {$item_costprice_netto}, {$item_costprice_taxes}, {$item_costprice_netto_dsc},
                      '{$posdata[$x]["item_desc"]}')";
            $CON->no_result($sql);
            $poscounter++;
         }

         recalcInvoiceBuy($CON, $buyinvcid);

         $sql = " select invc_total_netto
                  from invoices_buy
                  where
                  id = {$buyinvcid}";
         $invc_total_netto = $CON->select($sql);
         $invc_total_netto = (float)$invc_total_netto[0]["invc_total_netto"];

         $tlt_diff = ($invoice["invc_total_netto"] - $invc_total_netto) * -1;
         if($tlt_diff != 0.00)
         {
            $sql = " update invoices_buy
                     set
                     invc_discount5       = {$tlt_diff},
                     invc_discount_type5  = 1
                     where
                     id = {$buyinvcid}";
            $CON->no_result($sql);
            recalcInvoiceBuy($CON, $buyinvcid);
         }
      }
      else
         $_ERR = true;
   }
   else
      $_ERR = true;

   if($_ERR)
   {  ?>
      <script language="JavaScript">
         alert('ERROR: NO SE PUEDE GENERAR LA FACTURA DE COMPRA');
      </script>
      <?php
   }
   else
   {  ?>
      <script language="JavaScript">
         alert('LA FACTURA DE COMPRA "<?=$invc_number?>" FUE GENERADO EXITOSAMENTE');
      </script>
      <?php
   }
}
?>