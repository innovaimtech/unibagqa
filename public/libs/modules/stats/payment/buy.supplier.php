<?php

//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "buy_supplier";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
if($_REQUEST["_MODE"] == "supplier")
{
   $_REQUEST["subexec"]          = "search";
   $_REQUEST["supplier_id_0"]    = $_REQUEST["id"];

   if($_REQUEST["sql_company"] == "")
      $_REQUEST["sql_company"] = $companies[0]["id"];
}

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["supplier_id_0"]     = (int)$_REQUEST["supplier_id_0"];
   $_SESSION[$_sesmodulename]["sql_venc"]          = (int)$_REQUEST["sql_venc"];
   $_SESSION[$_sesmodulename]["sql_dateto"]        = trim($_REQUEST["sql_dateto"]);
   $_SESSION[$_sesmodulename]["sql_datefrom"]      = trim($_REQUEST["sql_datefrom"]);
   $_SESSION[$_sesmodulename]["sql_money"]         = trim($_REQUEST["sql_money"]);
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if($_SESSION[$_sesmodulename]["sql_venc"]." " == " ")
   $_SESSION[$_sesmodulename]["sql_venc"] = 1;

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["supplier_id_0"] != "")
{
   $datsql = " select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber, t1.invc_total_brutto,
                      t1.invc_import_total, t1.invc_exc_rate,
                      t4.company_short, 'type' 'invcoice', 'note_invcnumber' 'note_invcnumber',
                      t1.invc_importation, 'mode' 'trans', t1.invc_estpay_date 'invc_estpay_date'
               from invoices_buy t1
               INNER JOIN company_data t4 ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
               where
               t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["supplier_id_0"]} and
               t1.invc_status       > 1 and
               t1.invc_status       < 4 and
               t1.invc_date         > 0 and
               t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]}
               UNION ALL
               select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                      t1.note_total_brutto 'invc_total_brutto', t1.note_import_total 'invc_import_total',
                      t1.note_exc_rate 'invc_exc_rate', 
                      t4.company_short, t1.note_type 'invcoice', t1.note_invcnumber, t1.note_importation 'invc_importation',
                      'mode' 'trans', t1.note_estpay_date 'invc_estpay_date'
               from invoices_notes_buy t1
               INNER JOIN company_data t4 ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
               where
               t1.note_supplier_id  = {$_SESSION[$_sesmodulename]["supplier_id_0"]} and
               t1.note_status       > 1 and
               t1.note_date         > 0 and
               t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]}
               UNION ALL
               select t1.id, t5.pay_date 'invc_date', t1.invc_number, t1.invc_docnumber, t5.pay_brutto 'invc_total_brutto',
                      t5.pay_import_total 'invc_import_total', t1.invc_exc_rate,
                      t4.company_short, 'type' 'invcoice', 'note_invcnumber' 'note_invcnumber',
                      t1.invc_importation, 'mode' 'payment', t1.invc_estpay_date 'invc_estpay_date'
               from invoices_buy t1
               INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
               INNER JOIN tran_payments t5   ON ( t5.pay_tran_id = t1.id and t5.pay_tran_type = 'invoices_buy' and t5.pay_payed = 1)
               where
               t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["supplier_id_0"]} and
               t1.invc_status       > 1 and
               t1.invc_status       < 4 and
               t1.invc_date         > 0 and
               t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]}
               UNION ALL
               select t1.id, t5.pay_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                      t5.pay_brutto 'invc_total_brutto', t5.pay_import_total 'invc_import_total',
                      t1.note_exc_rate 'invc_exc_rate', 
                      t4.company_short, t1.note_type 'invcoice', t1.note_invcnumber, t1.note_importation 'invc_importation',
                      'mode' 'payment', t1.note_estpay_date 'invc_estpay_date'
               from invoices_notes_buy t1
               INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
               INNER JOIN tran_payments t5   ON ( t5.pay_tran_id = t1.id and t5.pay_tran_type = 'invoices_notes_buy' and t5.pay_payed = 1)
               where
               t1.note_supplier_id  = {$_SESSION[$_sesmodulename]["supplier_id_0"]} and
               t1.note_status       > 1 and
               t1.note_status       < 4 and
               t1.note_date         > 0 and
               t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]}
               order by 2 asc, 11 desc, 1 asc";
   $transtemp = $CON->select($datsql);
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" || $_SESSION[$_sesmodulename]["sql_dateto"] != "")
{
   if($_SESSION[$_sesmodulename]["sql_dateto"] == "")
      $_SESSION[$_sesmodulename]["sql_dateto"] = date('d.m.Y');
   if($_SESSION[$_sesmodulename]["sql_datefrom"] == "")
      $_SESSION[$_sesmodulename]["sql_datefrom"] = "01.01.".date('Y');

   $sqldate_from = getDateFromString($_SESSION[$_sesmodulename]["sql_datefrom"]);
   $sqldate_to   = getDateFromString($_SESSION[$_sesmodulename]["sql_dateto"], false);
}

foreach($transtemp AS $transtemprow)
{
   $doc_date = $transtemprow["invc_date"];
   if($transtemprow["mode"] == "modetrans")
   {
      if(!is_array($trans[$doc_date]))
         $trans[$doc_date] = Array();
      array_push($trans[$doc_date], $transtemprow);
   }
   elseif($transtemprow["mode"] == "modepayment")
   {
      $transtemprow["_type"] = "payment";
      if(!is_array($trans[$doc_date]))
         $trans[$doc_date] = Array();
      array_push($trans[$doc_date], $transtemprow);
   }
}

ksort($trans);

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["supplier_id_0"] != "")
   $supptaxes = getSupplierTaxes($CON, $_SESSION[$_sesmodulename]["supplier_id_0"]);
   
if($supptaxes && $_SESSION[$_sesmodulename]["sql_money"] == "USD")
   $_SESSION[$_sesmodulename]["sql_money"] = "CLP";
if($_SESSION[$_sesmodulename]["sql_money"] == "")
   $_SESSION[$_sesmodulename]["sql_money"] = "CLP";
   
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["supplier_id_0"] != "")
{
   $sql = " select init_balance, init_balance_usd
            from supplier_company_balance
            where
            supplier_id = {$_SESSION[$_sesmodulename]["supplier_id_0"]} and
            company_id  = {$_SESSION[$_sesmodulename]["sql_company"]}";
   $ges_saldo  = $CON->select($sql);
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_money"] == "CLP")
{
   $numberlim = 0;
   $ges_saldo = (float)$ges_saldo[0]["init_balance"];
}
else
{
   $numberlim = 2;
   $ges_saldo = (float)$ges_saldo[0]["init_balance_usd"];
}


//----------------------------------------------------------------------------------
$poscounter = 0;
$ges_debe   = 0;
$ges_haber  = 0;
foreach(array_keys($trans) AS $dayidx)
{
   foreach($trans[$dayidx] AS $tran)
   {
      $trsym      = "";
      $row_desc   = "";
      $val_debe   = "";
      $val_haber  = "";

      //----------------------------------------------------------------------------------
      $results[$poscounter]["ges_last_saldo"] = $ges_saldo;

      //----------------------------------------------------------------------------------
      if($tran["invc_importation"] && $_SESSION[$_sesmodulename]["sql_money"] == "CLP")
      {
         $tran["invc_total_brutto"] = $tran["invc_import_total"];
      }

      //----------------------------------------------------------------------------------
      if(($tran["type"] == "typeinvcoice" && $tran["_type"] != "payment") ||
         ($tran["type"] == "1" && $tran["_type"] == "payment") ||
         ($tran["type"] == "2" && $tran["_type"] != "payment"))
      {
         $trsym      = "minus-circle-frame.png";
         $ges_saldo -= $tran["invc_total_brutto"];
         $val_debe   = "&nbsp;";
         $val_haber  = $tran["invc_total_brutto"];

      }
      //----------------------------------------------------------------------------------
      if($tran["type"] == "typeinvcoice" && $tran["_type"] != "payment")
         $row_desc   = "Factura";
      if($tran["type"] == "1" && $tran["_type"] == "payment")
         $row_desc   = "Recibo: Nota de credito";
      if($tran["type"] == "2" && $tran["_type"] != "payment")
         $row_desc   = "Nota de debito";

      //----------------------------------------------------------------------------------
      if(($tran["type"] == "typeinvcoice" && $tran["_type"] == "payment") ||
         ($tran["type"] == "1" && $tran["_type"] != "payment") ||
         ($tran["type"] == "2" && $tran["_type"] == "payment"))
      {
         $trsym      = "plus-circle-frame.png";
         $ges_saldo += $tran["invc_total_brutto"];
         $val_debe   = $tran["invc_total_brutto"];
         $val_haber  = "&nbsp;";
      }

      //----------------------------------------------------------------------------------
      if($tran["type"] == "typeinvcoice" && $tran["_type"] == "payment")
         $row_desc   = "Pago: Factura";
      if($tran["type"] == "1" && $tran["_type"] != "payment")
         $row_desc   = "Nota de credito";
      if($tran["type"] == "2" && $tran["_type"] == "payment")
         $row_desc   = "Pago: Nota de debito";

      //----------------------------------------------------------------------------------
      if($tran["note_invcnumber"] == "note_invcnumbernote_invcnumber")
         $tran["note_invcnumber"] = "";

      //----------------------------------------------------------------------------------
      $results[$poscounter]["day_raw"]           = $dayidx;
      $results[$poscounter]["day"]               = date('d.m.Y', $dayidx);
      $results[$poscounter]["symbol"]            = $trsym;
      $results[$poscounter]["desc"]              = $row_desc;
      $results[$poscounter]["invc_number"]       = $tran["invc_number"];
      $results[$poscounter]["invc_docnumber"]    = $tran["invc_docnumber"];
      $results[$poscounter]["note_invcnumber"]   = $tran["note_invcnumber"];
      $results[$poscounter]["company_short"]     = $tran["company_short"];
      $results[$poscounter]["invc_estpay_date"]  = $tran["invc_estpay_date"];
      $results[$poscounter]["val_debe"]          = $val_debe;
      $results[$poscounter]["val_haber"]         = $val_haber;
      $results[$poscounter]["ges_saldo"]         = $ges_saldo;

      $poscounter++;
   }
}

?>
<style type="text/css">
   .fijar
   {
      display: block;
      height: 500px !important;
      overflow: auto;
      width: 100%;
   }
</style>
<script language="JavaScript">
   function detectEvent (event)
   {
      var xurl = './libs/modules/supplier_order/searchsupplier.fancy.php'
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
if($_REQUEST["_MODE"] != "supplier")
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Cuentas corrientes de proveedores</b></td>
      <td align="right" class="content_row_clear"></td>
   </tr>
   <tr>
      <td class="content_headerline" colspan="2">&nbsp;</td>
   </tr>
   </table>
   <?php
}
?>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst"
      onsubmit="return checkform(new Array(this.supplier_id_0, this.sql_company))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="100">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <?php
         if($_REQUEST["_MODE"] == "supplier")
         {  ?>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?printOverviewPeriodSelect($_sesmodulename)?></td>
            <input type="hidden" name="supplier_id_0" value="<?=$_SESSION[$_sesmodulename]["supplier_id_0"]?>">
            <?php
            $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = $supplier["supp_company"];
         }
         else
         {  ?>
            <td class="content_rowl"><b>Proveedor *</b></td>
            <td class="content_row">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td width="105">
                     <input type="text" class="text" style="width:100px" onfocus="markfield(this,0)"
                     name="supplier_search" id="supplier_search"
                     onblur="markfield(this,1); if(this.value != '') document.all.idxifrsrc.src='./libs/modules/items/searchsupplier.php?rowcount=0' +'&search=' +this.value;"
                     onkeyup="detectEvent(event)">
                  </td>
                  <td>
                     <select class="text" name="supplier_id_0" style="width:270px"
                     onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                        <?php
                        if((int)$_SESSION[$_sesmodulename]["supplier_id_0"])
                        {
                           $sql = " select *
                                    from supplier
                                    where
                                    id = {$_SESSION[$_sesmodulename]["supplier_id_0"]}";
                           $supplier = $CON->select($sql);
                           $supplier = $supplier[0];
                           ?>
                           <option value="<?=$_SESSION[$_sesmodulename]["supplier_id_0"]?>"><?=$supplier["supp_company"]?></option>
                           <?php
                           $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = $supplier["supp_company"];
                        }
                        ?>
                     </select>
                  </td>
               </tr>
               </table>
            </td>
            <?php
         }
         ?>
         <td class="content_rowl">Empresa *</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <?php
         if($_REQUEST["_MODE"] != "supplier")
         {  ?>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?printOverviewPeriodSelect($_sesmodulename)?></td>
            <?php
         }
         else
         {  ?>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
            <?php
         }
         ?>
         <td class="content_rowl">Moneda</td>
         <td class="content_row">
            <input type="radio" name="sql_money" value="CLP" <?php if($_SESSION[$_sesmodulename]["sql_money"] == "CLP") echo "checked"?>> Pesos
            <input type="radio" name="sql_money" value="USD" <?php if($_SESSION[$_sesmodulename]["sql_money"] == "USD") echo "checked"?> <?php if($supptaxes) echo "disabled"?>> US Dollar
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Vencimiento</td>
         <td class="content_row">
            <input type="checkbox" name="sql_venc" value="1" <?php if((int)$_SESSION[$_sesmodulename]["sql_venc"]) echo "checked"?>> Mostrar
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <colgroup>
               <col width="135">
               <col>
               <col width="132">
               <col width="132">
            </colgroup>
            <tr>
               <td align="left">
                  <?php
                  if(count($results) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($results) > 0)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"] && $_REQUEST["_MODE"] != "supplier")
                     printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
               </td>
               <td align="right">
                  <?php
                  printButton("Buscar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
                  $_SESSION["_SUBMITBTN"] = 1;
                  ?>
               </td>
            </tr>
            </table>
         </td>
      </tr>
      </table>
      <?=Nifty_printF(false)?>
      </form>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="85">
         <col width="20">
         <col>
         <col>
         <col>
         <?php
         if((int)$_SESSION[$_sesmodulename]["sql_venc"])
         {  ?>
            <col>
            <?php
         }
         ?>
         <col>
         <col width="85">
         <col width="85">
         <col width="85">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os">Fecha</td>
         <td class="content_tbl_subheader content_row_os" colspan="2">Tipo</td>
         <td class="content_tbl_subheader content_row_os">Número interno</td>
         <td class="content_tbl_subheader content_row_os">Comprobante Prov.</td>
         <td class="content_tbl_subheader content_row_os">Factura Rel.</td>
         <?php
         if((int)$_SESSION[$_sesmodulename]["sql_venc"])
         {  ?>
            <td class="content_tbl_subheader content_row_os">Fecha Venc.</td>
            <?php
         }
         ?>
         <td class="content_tbl_subheader content_row_os" align="right">Debe</td>
         <td class="content_tbl_subheader content_row_os" align="right">Haber</td>
         <td class="content_tbl_subheader content_row_os" align="right">Saldo</td>
      </tr>
      <?php
      if(count($results) > 23)
      {
         ?>
         </table>
         <div class="fijar">
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="85">
            <col width="20">
            <col>
            <col>
            <col>
            <?php
            if((int)$_SESSION[$_sesmodulename]["sql_venc"])
            {  ?>
               <col>
               <?php
            }
            ?>
            <col>
            <col width="85">
            <col width="85">
            <col width="85">
         </colgroup>
         <?php
      }

      $firstshow = false;
      $colspan1 = "8";
      $colspan2 = "6";
      if((int)$_SESSION[$_sesmodulename]["sql_venc"])
      {
         $colspan1 = "9";
         $colspan2 = "7";
      }

      //----------------------------------------------------------------------------------
      $_SESSION["STATS"][$_sesmodulename]["COUNTER"] = 0;

      for($x = 0; $x < count($results); $x++)
      {
         $showrow = false;

         if($sqldate_from > 0 && $sqldate_to > 0)
         {
            if($results[$x]["day_raw"] >= $sqldate_from && $results[$x]["day_raw"] <= $sqldate_to)
               $showrow = true;
         }
         else
            $showrow = true;

         if($showrow)
         {
            if(!$firstshow)
            {  ?>
               <tr bgcolor="<?=getRowColor(1)?>">
                  <td class="content_row_os" colspan="<?=$colspan1?>"><b>Saldo inicial</b></td>
                  <td class="content_row_os" align="right"><b><?=printPrice($results[$x]["ges_last_saldo"], $numberlim)?></b></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["SALDO"] = printPrice($results[$x]["ges_last_saldo"], $numberlim);

               $firstshow  = true;
               $init_saldo = $results[$x]["ges_last_saldo"];
            }
            
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$_SESSION["STATS"][$_sesmodulename]["COUNTER"]] = $results[$x];
            $_SESSION["STATS"][$_sesmodulename]["COUNTER"]++;
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=$results[$x]["day"]?></td>
               <td class="content_row_os"><img src="./images/menu/icons/<?=$results[$x]["symbol"]?>"></td>
               <td class="content_row_os"><?=$results[$x]["desc"]?></td>
               <td class="content_row_os"><?=$results[$x]["invc_number"]?></td>
               <td class="content_row_os"><?=$results[$x]["invc_docnumber"]?></td>
               <td class="content_row_os"><?=$results[$x]["note_invcnumber"]?>&nbsp;</td>
               <?php
               if((int)$_SESSION[$_sesmodulename]["sql_venc"])
               {  ?>
                  <td class="content_row_os"><?php if($results[$x]["invc_estpay_date"] > 0) echo date('d.m.Y', $results[$x]["invc_estpay_date"]); else echo "&nbsp;";?></td>
                  <?php
               }
               ?>
               <td class="content_row_os" align="right"><?php if($results[$x]["val_debe"] != 0) echo printPrice($results[$x]["val_debe"], $numberlim); else echo "&nbsp;";?></td>
               <td class="content_row_os" align="right"><?php if($results[$x]["val_haber"] != 0) echo printPrice($results[$x]["val_haber"], $numberlim); else echo "&nbsp;";?></td>
               <td class="content_row_os" align="right"><?=printPrice($results[$x]["ges_saldo"], $numberlim)?></td>
            </tr>
            <?php
            $last_saldo = $results[$x]["ges_saldo"];
            $ges_debe   += $results[$x]["val_debe"];
            $ges_haber  += $results[$x]["val_haber"];
         }
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="10" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      else
      {
         if($init_saldo < 0)
            $ges_haber -= $init_saldo;
         elseif($init_saldo > 0)
            $ges_debe += $init_saldo;
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_row_os" colspan="<?=$colspan2?>"><b>Total</b></td>
            <td class="content_row_totals content_row_os" align="right"><b><?=printPrice($ges_debe, $numberlim)?></b></td>
            <td class="content_row_totals content_row_os" align="right"><b><?=printPrice($ges_haber, $numberlim)?></b></td>
            <td class="content_row_totals content_row_os" align="right"><b><?=printPrice($last_saldo, $numberlim)?></b></td>
         </tr>
         <?php

         $_SESSION["STATS"][$_sesmodulename]["SALDO_DEBE"]  = printPrice($ges_debe, $numberlim);
         $_SESSION["STATS"][$_sesmodulename]["SALDO_HABER"] = printPrice($ges_haber, $numberlim);
         $_SESSION["STATS"][$_sesmodulename]["SALDO_LAST"]  = printPrice($last_saldo, $numberlim);
      }
      ?>
      </table>
      <?php
      if(count($results) > 23 );
      {  ?>
         </div>
         <?php
      }
      ?>
      <?=Nifty_printF()?>
      <br>
   </td>
</tr>
</table>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsPaymentBuySupplier($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsPaymentBuySupplier($CON);
  
if($pdffile != "")
{
   $doctitle = "Cuenta-Corriente-Proveedor-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Cuenta-Corriente-Proveedor-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>