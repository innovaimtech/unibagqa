<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "sell_customer";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "1";
$_sesbaseordersort      = "asc";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
//----------------------------------------------------------------------------------
$companies  = getCompanies($CON);

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
if($_REQUEST["_MODE"] == "customer")
{
   $_REQUEST["subexec"]       = "search";
   $_REQUEST["sql_customer"]  = $_REQUEST["id"];

   if($_REQUEST["sql_company"] == "")
      $_REQUEST["sql_company"] = $companies[0]["id"];
}

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_dateto"]        = trim($_REQUEST["sql_dateto"]);
   $_SESSION[$_sesmodulename]["sql_datefrom"]      = trim($_REQUEST["sql_datefrom"]);
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_customer"] != "")
{
   $datsql = " select t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber, t1.invc_total_brutto,
                      t4.company_short, 'type' 'invoice', 'note_invcnumber' 'note_invcnumber', 'mode' 'trans',
                      t1.invc_estpay_date 'invc_estpay_date', t5.pay_title, t1.invc_payed, t1.invc_sgntr_doc1
               from invoices_sell t1
               INNER JOIN company_data t4 ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN payments t5 ON t1.invc_paymentid = t5.id
               where
               t1.invc_cust_id      = {$_SESSION[$_sesmodulename]["sql_customer"]} and
               t1.invc_status       > 1 and
               t1.invc_status       < 4 and
               t1.invc_date         > 0 and
               t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]}
               UNION ALL
               select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                      t1.note_total_brutto 'invc_total_brutto', t4.company_short, t1.note_type 'invoice', t1.note_invcnumber, 'mode' 'trans',
                      t1.note_estpay_date 'invc_estpay_date', t5.pay_title, t1.note_payed 'invc_payed', t1.note_sgntr_doc1 'invc_sgntr_doc1'
               from invoices_notes_sell t1
               INNER JOIN company_data t4 ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
               LEFT OUTER JOIN payments t5 ON t1.note_paymentid = t5.id
               where
               t1.note_cust_id      = {$_SESSION[$_sesmodulename]["sql_customer"]} and
               t1.note_status       > 1 and
               t1.note_status       < 4 and
               t1.note_date         > 0 and
               t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]}
               order by 2 asc, 7 desc, 1 asc";
   $trans = $CON->select($datsql);
}

//----------------------------------------------------------------------------------
for($x = 0; $x < count($trans) && $trans != false; $x++)
{
   $row = $trans[$x];
   if($row["type"] == "typeinvoice")
   {
      $sql = " select *
               from invoices_sell
               where
               id = {$row["id"]}";
      $invcdata = $CON->select($sql);
      $invcdata = $invcdata[0];
      if((int)$invcdata["invc_shoprefid"] && strpos($invcdata["invc_desc"], "VALE: ") !== false && strpos($invcdata["invc_desc"], "FACTURA ") !== false && strpos($invcdata["invc_desc"], "/") !== false)
      {
         
         $_RET    = getValeData($invcdata);
         $_hash   = "{$_RET["shopid"]}_{$_RET["custid"]}_{$_RET["valenum"]}";

         if($_RET["factcount"] != "1/1" && $_RET["factcount"] != "" && strpos($_RET["factcount"], "1/") !== false)
         {
            $row["_NOCLICK"]  = 1;
            $_XDATA[$_hash]   = $row;

            $sql = " select *
                     from invoices_sell
                     where
                     invc_shop_id   = {$_RET["shopid"]} and
                     invc_cust_id   = {$_RET["custid"]} and
                     invc_shoprefid > 0 and
                     invc_desc      like 'FACTURA _/%VALE: {$_RET["valenum"]}%' and
                     invc_desc not  like 'FACTURA 1/%'";
            $relinvcs = $CON->select($sql);

            foreach($relinvcs AS $relinvc)
            {
               $_XDATA[$_hash]["invc_docnumber"]         .= ", ".$relinvc["invc_docnumber"];
               $_XDATA[$_hash]["invc_total_brutto"]      += $relinvc["invc_total_brutto"];
               $_XDATA[$_hash]["invc_total_brutto_calc"] += $relinvc["invc_total_brutto_calc"];
            }
         }
         elseif($_RET["factcount"] == "1/1")
         {
            $_hash = $x.md5(microtime());
            $_XDATA[$_hash] = $row;
         }
      }
      else
      {
         $_hash = $x.md5(microtime());
         $_XDATA[$_hash] = $row;
      }
   }
   else
   {
      $_hash = $x.md5(microtime());
      $_XDATA[$_hash] = $row;
   }
}
//----------------------------------------------------------------------------------
unset($temp);
$hashx = 0;
foreach(array_keys($_XDATA) AS $hash)
{
   $temp[$hashx] = $_XDATA[$hash];
   $hashx++;
}
unset($trans);
$trans = $temp;


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

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_customer"] != "")
{
   $sql = " select init_balance
            from customer_company_balance
            where
            cust_id     = {$_SESSION[$_sesmodulename]["sql_customer"]} and
            company_id  = {$_SESSION[$_sesmodulename]["sql_company"]}";
   $ges_saldo  = $CON->select($sql);
}

//----------------------------------------------------------------------------------
$ges_saldo = (float)$ges_saldo[0]["init_balance"];

//----------------------------------------------------------------------------------
/*
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
         $row_desc   = "Pago: Nota de credito";
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
         $row_desc   = "Recibo: Factura";
      if($tran["type"] == "1" && $tran["_type"] != "payment")
         $row_desc   = "Nota de credito";
      if($tran["type"] == "2" && $tran["_type"] == "payment")
         $row_desc   = "Recibo: Nota de debito";

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
      $results[$poscounter]["type"]             = $tran["_type"];

      $poscounter++;
   }
}
*/

$sql = " select cust_company
         from
         customer
         where
         id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
$custname = $CON->select($sql);
$custname = $custname[0]["cust_company"];

$_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custname;
//----------------------------------------------------------------------------------
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
      var xurl = './libs/modules/orders/searchcust.fancy.php'
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
if($_REQUEST["_MODE"] != "customer")
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="1080">
   <tr>
      <td height="30"><b class="content_header">Cuentas corrientes de clientes</b></td>
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
      onsubmit="return checkform(new Array(this.sql_customer, this.sql_company))">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="invc_id" id="invc_id" value="0">
      <input type="hidden" name="printpdf" id="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <?=Nifty_printH("box2", "1080")?>
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
         if($_REQUEST["_MODE"] == "customer")
         {  ?>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?printOverviewPeriodSelect($_sesmodulename)?></td>
            <input type="hidden" name="sql_customer" value="<?=$_SESSION[$_sesmodulename]["sql_customer"]?>">
            <?php
         }
         else
         {  ?>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
            <?php
         }
         ?>
         <td class="content_rowl">Empresa *</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <?php
      if($_REQUEST["_MODE"] != "customer")
      {  ?>
         <tr>
            <td class="content_rowl">Periodo</td>
            <td class="content_row"><?printOverviewPeriodSelect($_sesmodulename)?></td>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
         </tr>
         <?php
      }
      ?>
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
                  if(count($trans) > 0)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($trans) > 0)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"] && $_REQUEST["_MODE"] != "customer")
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
      <?=Nifty_printH("box1", "1080")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="85">
         <col width="110">
         <col width="160">
         <col width="90">
         <col width="85">
         <col width="85">
         <col width="110">
         <col width="100">
         <col>
         <col width="100">
         <col width="100">
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os">Fecha</td>
         <td class="content_tbl_subheader content_row_os">Documento</td>
         <td class="content_tbl_subheader content_row_os">N° de documento</td>
         <td class="content_tbl_subheader content_row_os">Monto</td>
         <td class="content_tbl_subheader content_row_os">Fecha Venc.</td>
         <td class="content_tbl_subheader content_row_os">Fecha Pago</td>
         <td class="content_tbl_subheader content_row_os">Forma Pago</td>
         <td class="content_tbl_subheader content_row_os">Estado Pago</td>
         <td class="content_tbl_subheader content_row_os">Detalles</td>
         <td class="content_tbl_subheader content_row_os">Saldo Documento</td>
         <td class="content_tbl_subheader content_row_os">Saldo Total</td>
      </tr>
      <?php
      if(count($trans) > 23)
      {
         ?>
         </table>
         <div class="fijar">
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="85">
            <col width="110">
            <col width="100">
            <col width="90">
            <col width="85">
            <col width="85">
            <col width="110">
            <col width="100">
            <col>
            <col width="100">
            <col width="100">
         </colgroup>
         <?php
      }
      ?>
      <?php
      $firstshow = false;
      $_SESSION["STATS"][$_sesmodulename]["COUNTER"] = 0;

      //----------------------------------------------------------------------------------
      $cc = 0;
      for($x = 0; $x < count($trans); $x++)
      {
         $showrow = false;
         $tran    = $trans[$x];
         $tran["day_raw"] = mktime(0,0,0,date("m", $tran["invc_date"]),date("d", $tran["invc_date"]),date("Y", $tran["invc_date"]));
         if($sqldate_from > 0 && $sqldate_to > 0)
         {
            if($tran["day_raw"] >= $sqldate_from && $tran["day_raw"] <= $sqldate_to)
               $showrow = true;
         }
         else
            $showrow = true;

         if($showrow)
         {
            if(!$firstshow)
            {  ?>
               <tr bgcolor="<?=getRowColor(1)?>">
                  <td class="content_row_os" colspan="10"><b>Saldo inicial</b></td>
                  <td class="content_row_os"><b><?=printPrice($results[$x]["ges_last_saldo"])?></b></td>
               </tr>
               <?php
               $_SESSION["STATS"][$_sesmodulename]["SALDO"] = printPrice($results[$x]["ges_last_saldo"], $numberlim);

               $firstshow  = true;
               $init_saldo = $results[$x]["ges_last_saldo"];
            }
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$_SESSION["STATS"][$_sesmodulename]["COUNTER"]] = $results[$x];
            $_SESSION["STATS"][$_sesmodulename]["COUNTER"]++;

            //----------------------------------------------------------------------------------
            if($tran["type"] == "typeinvoice")
            {
               $doctype    = "Factura";
               $_relmode   = "invoices_sell";
               $prefix     = "";
            }
            elseif($tran["type"] == "1")
            {
               $doctype    = "Nota de Credito";
               $_relmode   = "invoices_notes_sell";
               $prefix     = "-";
            }
            elseif($tran["type"] == "2")
            {
               $doctype    = "Nota de Debito";
               $_relmode   = "invoices_notes_sell";
               $prefix     = "";
            }

            //----------------------------------------------------------------------------------
            $xcomments  = "";
            $xtranpayed = getTranPayments($CON, $tran["id"], $_relmode);
            foreach($xtranpayed AS $xtranpayedrow)
            {
               if($xtranpayedrow["pay_docnum"] != "" || $xtranpayedrow["pay_bank"] != "" || $xtranpayedrow["pay_comment"] != "")
               {
                  if($xtranpayedrow["pay_docnum"] != "")
                     $xcomments .= $xtranpayedrow["pay_docnum"].", ";
                  if($xtranpayedrow["pay_bank"] != "")
                     $xcomments .= $xtranpayedrow["pay_bank"].", ";
                  if($xtranpayedrow["pay_comment"] != "")
                     $xcomments .= $xtranpayedrow["pay_comment"].", ";
               }
            }
            $xcomments  = substr($xcomments, 0, -2);
            $payedamt   = 0.00;
            if((int)$tran["invc_payed"])
            {
               $saldo = 0.00;
               $paystate = "CANCELADO";
            }
            else
            {
               foreach($xtranpayed AS $xtranpayedrow)
               {
                  if((int)$xtranpayedrow["pay_payed"])
                     $payedamt += $xtranpayedrow["pay_brutto"];
               }
               $saldo = $tran["invc_total_brutto"] - $payedamt;
               if($saldo < 0.00)
                  $saldo = 0.00;
               $paystate = "PENDIENTE";
            }

            //----------------------------------------------------------------------------------
            if($tran["type"] == "typeinvoice")
            {
               $init_saldo += $saldo;
               $ges_ventas += $tran["invc_total_brutto"];
            }
            elseif($tran["type"] == "1")
            {
               $init_saldo -= $saldo;
               $ges_ventas -= $tran["invc_total_brutto"];
            }
            elseif($tran["type"] == "2")
            {
               $init_saldo += $saldo;
               $ges_ventas += $tran["invc_total_brutto"];
            }
            //----------------------------------------------------------------------------------
            ?>
            <tr bgcolor="<?=getRowColor($x)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os"><?=date('d.m.Y', $tran["invc_date"])?></td>
               <td class="content_row_os"><?=$doctype?></td>
               <td class="content_row_os">
                  <?php
                  //----------------------------------------------------------------------------------
                  if($tran["type"] == "typeinvoice")
                  {
                     $_tablename = "invoices_sell";
                     $_prefix    = "invc";
                     $_docnum    = "invc_docnumber";
                     $_fancymode = "invoicesell";
                     $_btnname   = "Factura";
                  }
                  //----------------------------------------------------------------------------------
                  elseif($tran["type"] == "1" || $tran["type"] == "2")
                  {
                     $_tablename = "invoices_notes_sell";
                     $_prefix    = "note";
                     $_docnum    = "note_docnumber";
                     $_fancymode = "invoicesellnote";

                     if($tran["type"] == "1")
                        $_btnname   = "Nota-de-Credito";
                     else
                        $_btnname   = "Nota-de-Debito";
                  }
                  if($tran["invc_sgntr_doc1"] != "")
                  {
                     $doctitle = "{$_btnname}-{$tran["invc_docnumber"]}.pdf";
                     $docfile  = $tran["invc_sgntr_doc1"];
                     ?>
                     <a class="link" href="javascript: deactivateFormChange()"
                     onclick="document.getElementById('idxifrsrc').src='./libs/modules/structure/document_file.php?type=0&hash=<?=$docfile?>&name=<?=$doctitle?>&path=../../../docs.electrpdf/'">
                        <?=$tran["invc_docnumber"]?>
                     </a>
                     <?php
                  }
                  else
                     echo $tran["invc_docnumber"];
                  ?>
               </td>
               <td class="content_row_os"><?=$prefix?><?=printPrice($tran["invc_total_brutto"])?></td>
               <td class="content_row_os"><?php if($tran["invc_estpay_date"] > 0) echo date('d.m.Y', $tran["invc_estpay_date"]); else echo "&nbsp;";?></td>
               <td class="content_row_os"><?php if($xtranpayed[0]["pay_date"] > 0) echo date('d.m.Y', $xtranpayed[0]["pay_date"]); else echo "&nbsp;";?></td>
               <td class="content_row_os"><?=$tran["pay_title"]?>&nbsp;</td>
               <td class="content_row_os"><?=$paystate?>&nbsp;</td>
               <td class="content_row_os"><?=$xcomments?>&nbsp;</td>
               <td class="content_row_os"><?=$prefix?><?=printPrice($saldo)?></td>
               <td class="content_row_os"><?=printPrice($init_saldo)?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val1"]  = date('d.m.Y', $tran["invc_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val2"]  = $doctype;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val3"]  = $tran["invc_docnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val4"]  = printPrice($tran["invc_total_brutto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val5"]  = $tran["invc_estpay_date"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val6"]  = $xtranpayed[0]["pay_date"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val7"]  = $tran["pay_title"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val8"]  = $paystate;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val9"]  = printPrice($saldo);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val10"] = printPrice($init_saldo);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$cc]["val11"] = $xcomments;
            $cc++;
         }
      }

      if(!$x)
      {  ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row" colspan="11" align="center">
               <br>
               <b class="msg_save_err">No hay datos disponibles.</b>
               <br><br>
            </td>
         </tr>
         <?php
      }
      else
      {
         ?>
         <tr bgcolor="<?=getRowColor(1)?>">
            <td class="content_row_totals content_row_os" colspan="4"><b>TOTAL POR PAGAR</b></td>
            <td class="content_row_totals content_row_os" colspan="7"><b><?=printPrice($init_saldo)?></td>
         </tr>
         <tr bgcolor="<?=getRowColor(1)?>">
            <td class="content_row_totals content_row_os" colspan="4"><b>TOTAL DE VENTAS</b></td>
            <td class="content_row_totals content_row_os" colspan="7"><b><?=printPrice($ges_ventas)?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["init_saldo"]  = printPrice($init_saldo);
         $_SESSION["STATS"][$_sesmodulename]["ges_ventas"] = printPrice($ges_ventas);
      }
      ?>
      </table>
      <?php
      if(count($trans) > 23 );
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
if($_REQUEST["printpdf"] && !(int)$_REQUEST["invc_id"])
  $pdffile = doc_createStatsPaymentSellCustomer($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsPaymentSellCustomer($CON);

if($pdffile != "")
{
   $doctitle = "Cuenta-Corriente-Cliente-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Cuenta-Corriente-Cliente-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}

/*
if($_REQUEST["printpdf"] == 1 && (int)$_REQUEST["invc_id"])
  $pdffile2 = doc_createInvoicesSell($CON, $_REQUEST["invc_id"]);
elseif($_REQUEST["printpdf"] == 2 && (int)$_REQUEST["invc_id"])
   $pdffile2 = doc_createInvoicesSellNotes($CON, $_REQUEST["invc_id"]);
if($pdffile2 != "")
{
   if($_REQUEST["printpdf"] == 1)
      $doctitle = "Factura-".time().".pdf";
   else
      $doctitle = "Nota-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile2}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
*/
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>