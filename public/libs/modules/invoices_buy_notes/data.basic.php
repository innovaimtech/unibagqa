<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xinvoicesbuynote"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xinvoicesbuynote"]["fullcust"] = "";

$_sesmodulename = "invoice_buynote";
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "createFromSuppFinanceDiscount")
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.supp_dsc_finance
            from invoices_buy t1
            INNER JOIN supplier t2 ON t1.invc_supplier_id = t2.id
            where
            t1.id = {$_REQUEST["invcidOrg"]} ";
   $xinvoice1 = $CON->select($sql);
   $xinvoice1 = $xinvoice1[0];
   
   $_REQUEST["supplier_id_0"]    = (int)$xinvoice1["invc_supplier_id"];
   $_REQUEST["company_id"]       = (int)$xinvoice1["invc_company_id"];
   $_REQUEST["shop_id"]          = (int)$xinvoice1["invc_shop_id"];
   $invcdata["id"]               = (int)$xinvoice1["id"];
   $invcdata["invc_taxes"]       = (int)$xinvoice1["invc_taxes"];
   $invcdata["invc_importation"] = (int)$xinvoice1["invc_importation"];
   $_REQUEST["invc_docnumber"]   = $xinvoice1["invc_docnumber"];
   $_REQUEST["note_is_discount"] = 1;
   $_REQUEST["note_type"]        = 1;
   $_FROMDISCOUNTSPEC            = true;
   $_REQUEST["subexec"]          = "create";

   if($xinvoice1["invc_taxes"])
      $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
   else
      $sql_taxesperc = 0;

   $spec_diff_netto     = round($xinvoice1["invc_total_netto_dsc"] / 100 * $xinvoice1["supp_dsc_finance"],0);
   $spec_diff_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto / 100 * $sql_taxesperc);
   $spec_diff_brutto    = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto + $spec_diff_taxes);
   
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   
   $_REQUEST["supplier_id_0"]    = (int)$_REQUEST["supplier_id_0"];
   $_REQUEST["company_id"]       = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]          = (int)$_REQUEST["shop_id"];
   $_REQUEST["note_is_discount"] = (int)$_REQUEST["note_is_discount"];
   $note_parent_invcid           = (int)$invcdata["id"];
   $note_importation             = (int)$invcdata["invc_importation"];
   $note_taxes                   = (int)$invcdata["invc_taxes"];
   $note_type                    = (int)$_REQUEST["note_type"];
   $xinvoice1["supp_dsc_finance"]= (float)$xinvoice1["supp_dsc_finance"];

   $_REQUEST["note_date"]     = trim($_REQUEST["note_date"]);
   $_REQUEST["note_date"]     = explode(".", $_REQUEST["note_date"]);
   $_REQUEST["note_date"]     = (int)mktime(15, 0, 0, $_REQUEST["note_date"][1], $_REQUEST["note_date"][0], $_REQUEST["note_date"][2]);

   if($note_type == 1)
      $note_numbersystem = "invoicebuynotecred";
   else
      $note_numbersystem = "invoicebuynotedeb";

   //----------------------------------------------------------------------------------
   $note_number   = createTransactionNumber($CON, $_REQUEST["company_id"], $note_numbersystem);
   $note_date     = $_REQUEST["note_date"];

   if($note_importation)
      $usdval = getMoneyExchangeRate($CON, date('d.m.Y'));
   else
      $usdval = 0;

   $note_intnumber = createInternalBuyNumber($CON, $_REQUEST["company_id"], $_REQUEST["shop_id"], $note_numbersystem, $_REQUEST["note_date"]);

   //----------------------------------------------------------------------------------
   $sql = " insert into invoices_notes_buy
            (note_number, note_invcnumber, note_supplier_id, note_company_id, note_shop_id, note_date,
             note_receipt_date, note_taxes, note_type, note_importation, note_exc_rate, note_parent_invcid,
             note_is_discount, note_discount_perc, note_crtdat, note_crtusr, note_intnumber)
            VALUES
            ('{$note_number}', '{$_REQUEST["invc_docnumber"]}', {$_REQUEST["supplier_id_0"]}, {$_REQUEST["company_id"]},
              {$_REQUEST["shop_id"]}, {$note_date}, {$note_date}, {$note_taxes}, {$note_type},
              {$note_importation}, {$usdval}, {$note_parent_invcid},
              {$_REQUEST["note_is_discount"]}, {$xinvoice1["supp_dsc_finance"]}, {$currtme}, {$_SESSION["user_id"]},
              {$note_intnumber})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_notes_buy
               where
               note_crtusr = {$_SESSION["user_id"]}";
      $invoice = $CON->select($sql);
      $_REQUEST["id"]   = $invoice[0]["thisid"];

      if($_FROMDISCOUNTSPEC)
      {
         $itemtext  = "POR DESCUENTO FINANCIERO DE {$xinvoice1["supp_dsc_finance"]}%\n";
         $itemtext .= "FACTURA AFECTA {$xinvoice1["invc_docnumber"]}\n";
         $itemtext .= "SOBRE VALOR $ ".printPrice($xinvoice1["invc_total_netto_dsc"]);

         $sql = " insert into invoices_notes_buy_items
                  (note_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                   item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                   item_costprice_netto_dsc, item_desc)
                  VALUES
                  ({$_REQUEST["id"]}, 9999999, 0, 1, 'manual',
                   {$spec_diff_brutto}, {$sql_taxesperc}, {$spec_diff_netto}, {$spec_diff_taxes}, {$spec_diff_netto},
                   '{$itemtext}')";
         $CON->no_result($sql);
         
         recalcInvoiceBuyNote($CON, $_REQUEST["id"]);
      }

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=729&exec=edit&id=<?=$_REQUEST["id"]?>'
      </script>
      <?php
   }
   
   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $_REQUEST["note_desc"]        = trim(addslashes($_REQUEST["note_desc"]));
   $_REQUEST["note_date"]        = trim(addslashes($_REQUEST["note_date"]));
   $_REQUEST["note_docnumber"]   = trim(addslashes($_REQUEST["note_docnumber"]));
   $_REQUEST["note_intnumber"]   = trim(addslashes($_REQUEST["note_intnumber"]));
   $_REQUEST["note_paymentid"]   = (int)$_REQUEST["note_paymentid"];
   $_REQUEST["note_payed"]       = (int)$_REQUEST["note_payed"];
   $_REQUEST["note_stockchange"] = (int)$_REQUEST["note_stockchange"];
   $_REQUEST["note_mark_pendiente"] = (int)$_REQUEST["note_mark_pendiente"];
   $_REQUEST["note_code_cont_id"] = (int)$_REQUEST["note_code_cont_id"];

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_notes_buy
            where
            id = {$_REQUEST["id"]}";
   $invoicetemp      = $CON->select($sql);
   $invoiceimport    = $invoicetemp[0]["note_importation"];
   $invoicetaxes     = $invoicetemp[0]["note_taxes"];
   $invoicesupplier  = $invoicetemp[0]["note_supplier_id"];
   $invoicedate      = $invoicetemp[0]["note_date"];
   $invoiceusdval    = $invoicetemp[0]["note_exc_rate"];
   $shopid           = $invoicetemp[0]["note_shop_id"];

   //----------------------------------------------------------------------------------
   $isremote = getShops($CON, false, false, 0, $shopid);
   $isremote = $isremote[0]["shop_isremote"];
   if($isremote)
      $_REQUEST["note_stockchange"] = 0;
      
   //----------------------------------------------------------------------------------
   if((int)$invoiceimport)
   {
      $numberlim = "4";
      $numberlim2 = "2";
   }
   else
   {
      $numberlim = "2";
      $numberlim2 = "0";
   }

   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         //----------------------------------------------------------------------------------
         $idx = substr($reqkey, strrpos($reqkey, "_") +1);
         $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
         $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];

         $_REQUEST["item_stid_{$idx}"]       = (int)$_REQUEST["item_stid_{$idx}"];
         $_REQUEST["item_amount_{$idx}"]     = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $_REQUEST["item_discount_{$idx}"]   = getPrice($_REQUEST["item_discount_{$idx}"],8);

         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id           = (int)$itemvalues[0];
            $sql_type         = $itemvalues[1];
            $sql_charges_act  = (int)$itemvalues[5];

            if(!$_REQUEST["note_stockchange"])
               $_REQUEST["item_stid_{$idx}"] = 0;

            //----------------------------------------------------------------------------------
            if(!(int)$invoiceimport)
            {
               if($invoicetaxes)
               {
                  $sql_costnetto = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
                  $sql_taxesperc = getPrice($_REQUEST["item_costprice_taxes_perc_{$idx}"], 2);
                  $sql_taxes     = (float)sprintf("%.2f", $sql_costnetto / 100 * $sql_taxesperc);
                  $sql_costprice = (float)sprintf("%.2f", $sql_costnetto + $sql_taxes);
               }
               else
               {
                  $sql_costnetto = getPrice($_REQUEST["item_costprice_netto_{$idx}"],2);
                  $sql_taxesperc = 0;
                  $sql_taxes     = 0;
                  $sql_costprice = $sql_costnetto;
               }
            }
            else
            {
               $sql_costnetto = getPrice($_REQUEST["item_costprice_netto_{$idx}"], 4);
               $sql_taxesperc = 0;
               $sql_taxes     = 0;
               $sql_costprice = $sql_costnetto;
            }

            //----------------------------------------------------------------------------------
            $item_costprice_netto_dsc = round($sql_costnetto * $_REQUEST["item_amount_{$idx}"], $numberlim2);

            if($_REQUEST["item_discount_{$idx}"] > 0.00)
            {
               $item_costprice_netto_dsc = $sql_costnetto * $_REQUEST["item_amount_{$idx}"];
               $item_costprice_netto_dsc = $item_costprice_netto_dsc - ($item_costprice_netto_dsc / 100 * $_REQUEST["item_discount_{$idx}"]);
               $item_costprice_netto_dsc = round($item_costprice_netto_dsc, $numberlim2);
            }

            //----------------------------------------------------------------------------------
            if((int)$_REQUEST["manual_pos_{$idx}"])
               $_REQUEST["item_desc_{$idx}"] = trim(addslashes($_REQUEST["item_desc_{$idx}"]));
            else
               $_REQUEST["item_desc_{$idx}"] = "";

            //----------------------------------------------------------------------------------
            if($existing_id)
            {
               $sql = " update invoices_notes_buy_items
                        set
                        item_amount                = {$_REQUEST["item_amount_{$idx}"]},
                        item_costprice_brutto      = {$sql_costprice},
                        item_costprice_taxes_perc  = {$sql_taxesperc},
                        item_costprice_netto       = {$sql_costnetto},
                        item_costprice_taxes       = {$sql_taxes},
                        item_costprice_netto_dsc   = {$item_costprice_netto_dsc},
                        item_discount              = {$_REQUEST["item_discount_{$idx}"]},
                        item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}',
                        item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                        item_pos                   = {$poscounter}
                        where
                        note_id  = {$_REQUEST["id"]} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);

               renameItemChargePosUsed($CON, $_REQUEST["id"], "invoicesnotessbuy", $existing_pos, $poscounter);

               updateItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesnotessbuy", $existing_id, $sql_type, $poscounter,
                                    $invoicetemp[0]["note_company_id"], $invoicetemp[0]["note_shop_id"],
                                    $_REQUEST["item_charges_data_{$idx}"]);
            }
            else
            {
               $item_subitem_id     = 0;
               $item_subitem_amount = 0;
               $subitem             = getItemSubItem($CON, $sql_id);
               if((int)$subitem["prod_item_id"])
               {
                  $item_subitem_id     = $subitem["prod_item_id"];
                  $item_subitem_amount = $subitem["prod_item_amount"];
               }
               
               $sql = " insert into invoices_notes_buy_items
                        (note_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                         item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                         item_costprice_netto_dsc, item_st_id, item_desc, item_charges_act, item_discount,
                         item_subitem_id, item_subitem_amount)
                        VALUES
                        ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                         {$sql_costprice}, {$sql_taxesperc}, {$sql_costnetto}, {$sql_taxes}, {$item_costprice_netto_dsc},
                         {$_REQUEST["item_stid_{$idx}"]}, '{$_REQUEST["item_desc_{$idx}"]}', {$sql_charges_act},
                         {$_REQUEST["item_discount_{$idx}"]}, {$item_subitem_id}, {$item_subitem_amount})";
               $CON->no_result($sql);

               updateItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesnotessbuy", $sql_id, $sql_type, $poscounter,
                                    $invoicetemp[0]["note_company_id"], $invoicetemp[0]["note_shop_id"],
                                    $_REQUEST["item_charges_data_{$idx}"]);
            }

            $poscounter++;
         }
         elseif($existing_id)
         {
            $sql = " delete from invoices_notes_buy_items
                     where
                     note_id  = {$_REQUEST["id"]} and
                     item_id  = {$existing_id} and
                     item_pos = {$existing_pos}";
            $CON->no_result($sql);

            clearItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesnotessbuy", $existing_pos);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $checkinvcdate = $_REQUEST["note_date"];

   //----------------------------------------------------------------------------------
   $_REQUEST["note_date"] = explode(".", $_REQUEST["note_date"]);
   $_REQUEST["note_date"] = (int)mktime(15, 0, 0, $_REQUEST["note_date"][1], $_REQUEST["note_date"][0], $_REQUEST["note_date"][2]);
   $_REQUEST["note_receipt_date"] = explode(".", $_REQUEST["note_receipt_date"]);
   $_REQUEST["note_receipt_date"] = (int)mktime(15, 0, 0, $_REQUEST["note_receipt_date"][1], $_REQUEST["note_receipt_date"][0], $_REQUEST["note_receipt_date"][2]);
   if(!$_REQUEST["note_receipt_date"])
      $_REQUEST["note_receipt_date"] = $_REQUEST["note_date"];

   if($invoiceimport)
      $_REQUEST["note_exc_rate"] = getPrice($_REQUEST["note_exc_rate"],8);
   else
      $_REQUEST["note_exc_rate"] = 0.00;

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_notes_buy
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $revert  = false;
   $final   = false;
   if($headdata["note_status"] == 1 && $_REQUEST["note_status"] == 2)
      $final = true;
   if(($headdata["note_status"] == 2 || $headdata["note_status"] == 3) && $_REQUEST["note_status"] == 1)
      $revert = true;

   //----------------------------------------------------------------------------------
   if($_REQUEST["note_status"] == 1)
      $_REQUEST["note_payed"]  = 0;

   //----------------------------------------------------------------------------------
   if($headdata["note_paymentid"] != $_REQUEST["note_paymentid"] || $headdata["note_receipt_date"] != $_REQUEST["note_receipt_date"])
   {
      $sql = " select pay_days
               from payments
               where
               id = {$_REQUEST["note_paymentid"]}";
      $paydays = $CON->select($sql);
      $paydays = (int)$paydays[0]["pay_days"];

      if($paydays == 0)
         $_REQUEST["note_estpay_date"] = 0;
      else
         $_REQUEST["note_estpay_date"] = date('d.m.Y', $_REQUEST["note_receipt_date"] + (86400 * $paydays));
   }
   if($_REQUEST["note_estpay_date"] == "")
      $_REQUEST["note_estpay_date"] = 0;
   else
   {
      $_REQUEST["note_estpay_date"] = explode(".", $_REQUEST["note_estpay_date"]);
      $_REQUEST["note_estpay_date"] = (int)mktime(15, 0, 0, $_REQUEST["note_estpay_date"][1], $_REQUEST["note_estpay_date"][0], $_REQUEST["note_estpay_date"][2]);
   }

   $_REQUEST["note_contdoctype_id"] = (int)$_REQUEST["note_contdoctype_id"];

   //----------------------------------------------------------------------------------
   $sql = " update invoices_notes_buy
            set
            note_status          = {$_REQUEST["note_status"]},
            note_intnumber       = '{$_REQUEST["note_intnumber"]}',
            note_desc            = '{$_REQUEST["note_desc"]}',
            note_docnumber       = '{$_REQUEST["note_docnumber"]}',
            note_date            = {$_REQUEST["note_date"]},
            note_paymentid       = {$_REQUEST["note_paymentid"]},
            note_payed           = {$_REQUEST["note_payed"]},
            note_estpay_date     = {$_REQUEST["note_estpay_date"]},
            note_receipt_date    = {$_REQUEST["note_receipt_date"]},
            note_exc_rate        = {$_REQUEST["note_exc_rate"]},
            note_stockchange     = {$_REQUEST["note_stockchange"]},
            note_mark_pendiente  = {$_REQUEST["note_mark_pendiente"]},
            note_contdoctype_id  = {$_REQUEST["note_contdoctype_id"]},
            note_code_cont_id    = {$_REQUEST["note_code_cont_id"]},
            note_updusr          = {$_SESSION["user_id"]},
            note_upddat          = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from invoices_notes_buy t1
            INNER JOIN invoices_notes_buy t2 ON (  t1.note_supplier_id = t2.note_supplier_id and
                                                   t1.note_status > 0 and t2.note_status > 0 and
                                                   t1.note_docnumber = t2.note_docnumber and
                                                   t1.note_type = t2.note_type)
            where
            t1.id = {$_REQUEST["id"]}";
   $shpdoccheck = $CON->select($sql);
   if((int)$shpdoccheck[0]["cc"] > 1)
   {
      $sql = " update invoices_notes_buy
               set
               note_docnumber = ''
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      $_SESSION["JSEXEC"] .= ";alert('EL NUMERO DE NOTA YA EXISTE PARA EL PROVEEDOR');";
   }

   //----------------------------------------------------------------------------------
   if($headdata["note_status"] == 1)
      recalcInvoiceBuyNote($CON, $_REQUEST["id"]);
   if($final)
   {
      bookInvoiceBuyNotes($CON, $_REQUEST["id"]);

      $sql = " select *
               from invoices_notes_buy_otherdscinvc
               where
               note_id = {$_REQUEST["id"]}";
      $relinvcs = $CON->select($sql);
      foreach($relinvcs AS $relinvc)
      {
         $amtbrutto = round($relinvc["note_parent_invcdsc"] / 100 * (100 + $_SESSION["_CONF"]["conf_taxes"]),0);
         $sql = " insert into tran_payments
                  (pay_tran_id, pay_tran_type, pay_paymentid, pay_brutto, pay_date, pay_payed,
                   pay_docnum, pay_bank, pay_comment, pay_import_total)
                  VALUES
                  ({$relinvc["note_parent_invcid"]}, 'invoices_buy', 100000, {$amtbrutto},
                   {$invoicedate}, 1, 'Nota de Credito {$_REQUEST["note_docnumber"]}', '', '', 0)";
         $CON->no_result($sql);
      }
      
      $sql = " update invoices_notes_buy
               set
               note_payed = 1,
               note_status = 3
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
               
   }
   if($revert)
   {
      revertInvoiceBuyNotes($CON, $_REQUEST["id"]);

      $sql = " select *
               from invoices_notes_buy_otherdscinvc
               where
               note_id = {$_REQUEST["id"]}";
      $relinvcs = $CON->select($sql);
      foreach($relinvcs AS $relinvc)
      {
         $sql = " delete from tran_payments
                  where
                  pay_tran_id    = {$relinvc["note_parent_invcid"]} and
                  pay_tran_type  = 'invoices_buy' and
                  pay_paymentid  = 100000 ";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_company, t2.supp_email, t3.company_short, t4.shop_name, t1.note_supplier_id, t1.note_taxes,
                t2.supp_notes,  t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t11.typedoc_cont_nameid, t11.typedoc_cont_code
         from invoices_notes_buy t1
         LEFT OUTER JOIN supplier t2      ON t1.note_supplier_id  = t2.id
         LEFT OUTER JOIN company_data t3  ON t1.note_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.note_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.note_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.note_crtusr       = t6.id
         LEFT OUTER JOIN payments t7      ON t1.note_paymentid    = t7.id
         LEFT OUTER JOIN typedoc_contables t11 ON t1.note_contdoctype_id = t11.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$isremote = getShops($CON, false, false, 0, $headdata["note_shop_id"]);
$isremote = (int)$isremote[0]["shop_isremote"];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from supplier t1
         LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.supp_provinciaid  = t5.id
         where
         t1.id = {$headdata["note_supplier_id"]}";
$supplier = $CON->select($sql);
$supplier = $supplier[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from codigo_contable t1
         where
         t1.cc_status = 1
         order by cc_code";
$cods = $CON->select($sql);

//----------------------------------------------------------------------------------
$payments    = getPayments($CON);

if((int)$headdata["note_type"] == 1)
   $typedoccont = getTypeDocContList($CON, "NOTACRED");
else
   $typedoccont = getTypeDocContList($CON, "NOTADEB");

//----------------------------------------------------------------------------------
if($headdata["note_status"] >= 2)
{
   $rdlo       = " readonly ";
   $dabl       = " disabled ";
   $rowcount   = count($posdata);
}

//----------------------------------------------------------------------------------
if((int)$_SESSION["user_docopen_perm"] || (int)$_REQUEST["user_docopen_perm"])
   $hasdocopeperm = true;
else
   $hasdocopeperm = false;
   
//----------------------------------------------------------------------------------
if($_SESSION["xinvoicesbuynote"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

if((int)$headdata["note_importation"])
{
   $inputw    = "50px";
   $inputw2   = "55px";
   $moneystr  = "US&nbsp;";
   $numberlim = "4";
}
else
{
   $inputw    = "75px";
   $inputw2   = "75px";
   $moneystr  = "";
   $numberlim = "2";
}

//----------------------------------------------------------------------------------
$itemselw = "480px";

if((int)$headdata["note_stockchange"])
{
   $showStorehouses  = true;
   $itemselw = "360px";
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/invoices_buy_notes/searchstorehouses.php?noteid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;

      var valarr  = $('#item_id_' +idx).val().split('#');
      valarr[4]   = valarr[5];
      switchShpChargeMode(idx, valarr);
   }

   function updateItemStorehousesArr(idx, xval)
   {
      var valarr = xval.split('#');
      updateItemStorehouses(idx, valarr[0], valarr[1]);
   }
   
   function detectEvent (event, rowcount, id)
   {
      var xurl = './libs/modules/invoices_buy_notes/searchitem.fancy.php?rowcount=' +rowcount + '&id=' +id;
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  ?>
   onsubmit="return checkform(new Array(this.note_contdoctype_id, this.note_docnumber, this.note_date <?php
   if((int)$headdata["note_importation"])
      echo ", this.note_exc_rate";
   ?>));"
   <?php
}
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="note_status" value="1">
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
   <td class="content_tbl_header" colspan="4">
      <img src="./images/menu/icons/arrow-move.png" height="14" style="cursor:pointer;vertical-align:bottom"
      onclick="var x=0;var brows = $('#ifx_tblheader > tbody');
               brows.each(function(){x++;if(x > 1)
               {if($(this).is(':hidden')) $(this).show(); else $(this).hide();}});">
      Datos básicos
   </td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row"><?=$headdata["note_number"]?></td>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear"><?=getInvoiceBuyNoteType($headdata["note_type"])?></td>
         <td class="content_row_clear" align="right" style="padding-right:30px">
            Correlativo:
            <input type="text" style="width:80px;text-align:center" name="note_intnumber" class="text" <?=$rdlo?>
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["note_intnumber"]?>">
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<?php
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_number"]     = $headdata["note_number"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["type"]            = getInvoiceBuyNoteType($headdata["note_type"])." ".$headdata["note_intnumber"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["company_short"]   = $headdata["company_short"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shop_name"]       = $headdata["shop_name"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_company_id"] = $headdata["note_company_id"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_type"]       = $headdata["note_type"];
?>
<tbody>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Proveedor</td>
   <td class="content_row" <?=$cdatastyle?>>
      <a href="javascript:void(0)" style="text-decoration:none;color:black"
      onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$headdata["id"]?>&showfullcust=<?php
      if($_SESSION["xinvoicesbuynote"]["fullcust"] == "") echo "1"; else echo "0"?>'"> <b><?=$headdata["supp_company"]?></b></a></td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row" <?=$cdatastyle?>><b><?=$supplier["supp_rut"]?></b>&nbsp;</td>
</tr>
   <tr <?php if($_SESSION["xinvoicesbuynote"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$supplier["supp_street"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?if($supplier["supp_phone"] != "") echo $supplier["supp_phone"];?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicesbuynote"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Región</td>
   <td class="content_row"><?=$supplier["name"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][16]?></td>
   <td class="content_row"><?=$supplier["supp_fax"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicesbuynote"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Comuna</td>
   <td class="content_row"><?=$supplier["pro_name"]?> - <?=$supplier["nombre"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
   <td class="content_row"><?=$supplier["supp_email"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicesbuynote"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">País</td>
   <td class="content_row"><?=$supplier["country_name"]?>&nbsp;</td>
   <td class="content_rowl">Opciones</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear">
            <?php
            printButton("Cambiar datos del proveedor", "postnav", "index.php?mid=497&exec=edit&id={$supplier["id"]}&registerback={$_REQUEST["mid"]}-{$_REQUEST["id"]}", "", "disk-black", 200);
            ?>
         </td>
         <td class="content_row_clear" style="padding-left:5px">
            <?php
            printGooglemapsButton($supplier["supp_street"], $supplier["name"], $supplier["country_name"]);
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
<?php
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_company"] = $supplier["supp_company"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_rut"]     = $supplier["supp_rut"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_street"]  = $supplier["supp_street"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_phone"]   = $supplier["supp_phone"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["name"]         = $supplier["name"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_fax"]     = $supplier["supp_fax"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["nombre"]       = $supplier["nombre"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_email"]   = $supplier["supp_email"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["country_name"] = $supplier["country_name"];
?>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Número de nota *</td>
   <td class="content_row" <?=$cdatastyle?>>
      <input type="text" style="width:350px" name="note_docnumber" class="text" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["note_docnumber"]?>">
   </td>
   <td class="content_rowl" <?=$cdatastyle?>>Fecha *</td>
   <td class="content_row" <?=$cdatastyle?>>
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <input type="text" style="width:80px" id="note_date" name="note_date" <?=$rdlo?>
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date('d.m.Y', $headdata["note_date"])?>">
         </td>
         <td class="content_row_clear" align="right" style="padding-right:10px">
            Fecha recepción:
            <input type="text" style="width:80px;<?php if($rdlo != "") echo "margin-right:20px"?>" id="note_receipt_date" name="note_receipt_date"
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            onchange="document.form_shppos.onsubmit='';submitForm(document.form_shppos);"
            value="<?php if($headdata["note_receipt_date"] > 0) echo date('d.m.Y', $headdata["note_receipt_date"])?>">
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top" rowspan="2">Observaciones</td>
   <td class="content_row" rowspan="2">
      <textarea class="text" style="width:350px; height:45px" name="note_desc" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["note_desc"])?></textarea>
   </td>
   <!--
   <td class="content_rowl">Forma de pago *</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="note_paymentid" id="note_paymentid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($payments as $payment)
            {  ?>
               <option value="<?=$payment["id"]?>"
               <?php if($payment["id"] == $headdata["note_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["note_paymentid"]?>"><?=$headdata["pay_title"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   -->
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <?php
            $statimg = "";
            switch((int)$headdata["note_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "orange_active.gif"; break;
               case 3: $statimg = "green_active.gif"; break;
            }
            ?>
            <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
            <?=getInvoiceBuyStatus($headdata["note_status"], true)?>
         </td>
         <td class="content_row_clear" align="right" style="padding-right:10px">
            Fecha vencimiento:
            <input type="text" style="width:80px;<?php if($rdlo != "") echo "margin-right:20px"?>" id="note_estpay_date" name="note_estpay_date"
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            value="<?php if($headdata["note_estpay_date"] > 0) echo date('d.m.Y', $headdata["note_estpay_date"])?>">
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   
   <td class="content_rowl" valign="top">IVA</td>
   <td class="content_row" valign="top">
      <select class="text" style="width:120px;background-color:<?php if((int)$headdata["note_taxes"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
         <?php
         if((int)$headdata["note_taxes"])
         {
            echo '<option value="">CON IVA</option>';
            $_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_taxes"] = "CON IVA";
         }
         else
         {
            echo '<option value="">SIN IVA</option>';
            $_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_taxes"] = "SIN IVA";
         }
         ?>
      </select>
      <?php
      if(!(int)$headdata["note_taxes"])
      {
         if((int)$headdata["note_importation"])
         {  ?>
            <nobr>
            <span style="padding-left:7px">
               <b class="msg_save_err">[IMPORT]</b>
               <nobr>
               1 USD =
               <input type="text" style="width:80px" name="note_exc_rate" class="text" <?=$rdlo?>
               onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=printPrice($headdata["note_exc_rate"],8)?>">
               CLP
               </nobr>
             </span>
            </nobr>
            <?php
         }
      }
      ?>
   </td>
</tr>
<!--
<tr>
   <td class="content_rowl" valign="top">Pagada</td>
   <td class="content_row" valign="top">
      <input type="hidden" name="note_payed" value="<?=(int)$headdata["note_payed"]?>">
      <?php
      if((int)$headdata["note_status"] > 1)
      {
         if((int)$headdata["note_payed"])
         {
            $btnclass = "postnav_save";
            $btntext  = "Pagado";
         }
         else
         {
            $btnclass = "postnav_del";
            $btntext  = "Ingresar pago";
         }
         printButton("{$btntext}", $btnclass, "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&mode=invoices_notes_buy&subcatexec=payment", "", "money", 120);
      }
      else
      {  ?>
         <select class="text" style="width:120px;background-color:#FFD6D8; ">
            <option value="">NO</option>
         </select>
         <?php
      }
      ?>
   </td>
</tr>
-->
<tr>
   <td class="content_rowl">Factura relacionada</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear" width="200"><?=$headdata["note_invcnumber"]?>&nbsp;</td>
         <?php
         if((int)$headdata["note_is_discount"])
         {  ?>
            <td class="content_row_clear" align="right">
               <?php
               printButton("Asignar mas facturas", "postnav_save", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/invoices_buy_notes/invcnumbers.fancy.php?id={$_REQUEST["id"]}', 'iframe', 450, 450, 'no')", "document",120);
               ?>
            </td>
            <?php
         }
         ?>
      </tr>
      </table>
   </td>
   <td class="content_rowl">Cambio de stock</td>
   <td class="content_row">
      <?php
      if($headdata["note_status"] == 1)
      {  ?>
         <select class="text" name="note_stockchange" id="note_stockchange" style="width:120px;background-color:<?php if((int)$headdata["note_stockchange"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
         <?php
         if($headdata["note_type"] == 1)
         {  ?>
            <option value="1" <?php if((int)$headdata["note_stockchange"]) echo "selected"?>>HABILITADO</option>
            <?php
         }
         ?>
         <option value="0" <?php if(!(int)$headdata["note_stockchange"]) echo "selected"?>>DESHABILITADO</option>
         <?php
      }
      else
      {
         if((int)$headdata["note_stockchange"])
         {  ?>
            <select class="text" id="note_stockchange" name="note_stockchange" style="width:120px;background-color:#BFE6C3;">
               <option value="1">HABILITADO</option>
            </select>
            <?php
         }
         else
         {  ?>
            <select class="text" id="note_stockchange" name="note_stockchange" style="width:120px;background-color:#FFD6D8;">
               <option value="0" selected>DESHABILITADO</option>
            </select>
            <?php
         }
      }
      ?>
   </td>
</tr> 
<?php
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_docnumber"]    = $headdata["note_docnumber"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_date"]         = date('d.m.Y', $headdata["note_date"]);
if($headdata["note_receipt_date"])
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_receipt_date"] = date('d.m.Y', $headdata["note_receipt_date"]);
if($headdata["note_estpay_date"])
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_estpay_date"]  = date('d.m.Y', $headdata["note_estpay_date"]);
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_desc"]         = $headdata["note_desc"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_invcnumber"]   = $headdata["note_invcnumber"];
?>
<tr>
   <td class="content_rowl">Por descontar</td>
   <td class="content_row">
      <input type="checkbox" name="note_mark_pendiente" value="1" class="checkbox"
      <?php if((int)$headdata["note_mark_pendiente"]) echo "checked"?>>
   </td>
   <td class="content_rowl">Codigo contable </td>
   <td class="content_row">
      <select class="text" name="note_code_cont_id" id="note_code_cont_id" style="width:100%"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?foreach($cods AS $cod)
         {  ?>
            <option value="<?=$cod["id"]?>"<?if($cod["id"] == $headdata["note_code_cont_id"]) echo "selected"?>>
               <?=$cod["cc_code"]?> | <?=$cod["cc_title"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">&nbsp;</td>
   <td class="content_row">&nbsp;</td>
   <td class="content_rowl">Tipo Doc. Cont. *</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="note_contdoctype_id" id="note_contdoctype_id"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($typedoccont as $cont)
            {  ?>
               <option value="<?=$cont["id"]?>"
               <?php if($cont["id"] == $headdata["note_contdoctype_id"]) echo "selected"?>><?=$cont["typedoc_cont_code"]?> - <?=getTypeDocContName($cont["typedoc_cont_nameid"])?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["note_contdoctype_id"]?>"><?=$headdata["typedoc_cont_code"]?> - <?=getTypeDocContName($headdata["typedoc_cont_nameid"])?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Creado por</td>
   <td class="content_row"><?=$headdata["crt_firstname"]?> <?=$headdata["crt_lastname"]?>&nbsp;</td>
   <td class="content_rowl">Cambiado por</td>
   <td class="content_row"><?=$headdata["upd_firstname"]?> <?=$headdata["upd_lastname"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Creado</td>
   <td class="content_row"><?=displayDate($headdata["note_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["note_upddat"])?></td>
</tr>
<?php
if(trim($headdata["supp_notes"]) != "")
{  ?>
   <tr>
      <td class="content_rowl" valign="top">Comentarios</td>
      <td class="content_row" colspan="3" style="color:navy"><?generateCommentToogle($headdata["supp_notes"])?></td>
   </tr>
   <?php
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp_notes"] = $headdata["supp_notes"];
}
?>
</tbody>
</table>
<?=Nifty_printF()?>
<br>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<?php
$hasItems = false;
$posdata    = getInvoiceBuyNoteItems($CON, $_REQUEST["id"]);
$rowcount   = count($posdata);

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($headdata["note_status"] == 1)
   $rowcount += 8;
?>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="1">
   <col width="1">
   <col width="1">
   <col width="1">
   <col width="1">
   <?php
   if($showStorehouses)
      echo "<col>";
   ?>
   <col width="1">
   <col width="1">
   <col width="1">
   <col width="1">
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="10">Artículos</td>
</tr>
<tr>
   <td class="content_tbl_subheader" valign="top">Busqueda</td>
   <td class="content_tbl_subheader" valign="top">Act.</td>
   <td class="content_tbl_subheader" valign="top">Artículo</td>
   <td class="content_tbl_subheader" align="center">Unidad</td>
   <td class="content_tbl_subheader" valign="top" align="right">Cantidad</td>
   <?php
   if($showStorehouses)
   {  ?>
      <td class="content_tbl_subheader" valign="top">Bodega</td>
      <?php
   }
   ?>
   <td class="content_tbl_subheader" valign="top" align="right">Precio (neto)</td>
   <td class="content_tbl_subheader" valign="top" align="right">Descuento</td>
   <td class="content_tbl_subheader" valign="top" align="right">IVA %</td>
   <td class="content_tbl_subheader" valign="top" align="right">Valor Total</td>
</tr>
<?php
for($y = 0; $y < $rowcount; $y++)
{
   $showmanual = false;
   if((int)$posdata[$y]["item_id"] && $posdata[$y]["item_type"] == "manual")
      $showmanual = true;

   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "xf_search_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_id_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_amount_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_stid_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_costprice_netto_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_costprice_taxes_perc_{$y}";
   ?>
   <tr bgcolor="<?=getRowColor($y)?>">
      <td class="content_row">
         <?if($showmanual) echo "&nbsp;"?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$y?>" <?php if($showmanual) echo "style='display:none'" ?>>
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td>
               <input type="text" class="text" style="width:60px" name="xf_search_<?=$y?>" id="xf_search_<?=$y?>"
               onfocus="markfield(this,0)" autocomplete="off"
               
               <?=$rdlo?>
               <?php
               if(!(int)$posdata[$y]["item_id"])
               {
                  if($showStorehouses)
                     $urlparam = "&storehousemode=1";
                  else
                     $urlparam = "&storehousemode=0";
                  ?>
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/invoices_buy_notes/searchitem.php?rowcount=<?=$y?>&id=<?=$_REQUEST["id"]?><?=$urlparam?>&search=' +this.value} this.value='';"
                  onkeyup="detectEvent(event, '<?=$y?>', '<?=$_REQUEST["id"]?>')"
                  <?php
               }
               else
               {  ?>
                  onblur="markfield(this,1)"
                  <?php
                  $hasItems = true;
               }
               ?>>
            </td>
         </tr>
         </table>
      </td>
      <td class="content_row" valign="top">
         <?php
         if((int)$posdata[$y]["item_id"])
         {  ?>
            <input type="hidden" name="existing_id_<?=$y?>" value="<?=$posdata[$y]["item_id"]?>">
            <input type="hidden" name="existing_pos_<?=$y?>" value="<?=$posdata[$y]["item_pos"]?>">
            <input type="button" class="buttonred" value="x" style="width:20px"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)" <?=$dabl?>
            onclick="if(askDel('')) { document.form_shppos.item_amount_<?=$y?>.value='0'; submitForm(document.form_shppos); }">
            <?php
         }
         else
         {  ?>
            <img src="/images/menu/icons/notebook--plus.png" border="0" style="cursor:pointer"
            onclick="showOrderPartPosManualEdit('<?=$y?>')">
            <?php
         }
         ?>
      </td>
      <td class="content_row" valign="top">
         <?php
         $overlibover = "";
         $ovritemselw = $itemselw;
         if((int)$posdata[$y]["item_id"])
         {
            if($posdata[$y]["item_invoicebuy_note"] != "")
               $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$y]["item_invoicebuy_note"]))."</b>";

            if($overlibover != "")
            {  ?>
               <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
               onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
               onmouseout="return nd()">
               <?php
               if($itemselw == "480px")
                  $ovritemselw = "460px";
               elseif($itemselw == "360px")
                  $ovritemselw = "340px";
            }
         }
         ?>
         <select class="text" style="width:<?=$ovritemselw?>;<?php if($showmanual) echo "display:none" ?>"
         name="item_id_<?=$y?>" id="item_id_<?=$y?>"
         onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$y]["item_id"]) echo "addSelStyle(this);" ?>"
         onchange="setItemInfos('<?=$y?>', this.value);<?php
         if($showStorehouses)
            echo "updateItemStorehousesArr('{$y}', this.value);";
         ?>">
            <?php
            if((int)$posdata[$y]["item_id"])
            {
               $desc       = trim(addslashes($posdata[$y]["item_title"]));
               $unitdesc   = getItemUnitDesc($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
               ?>
               <option value="<?=$posdata[$y]["item_id"]?>#<?=$posdata[$y]["item_type"]?>"><?=$posdata[$y]["item_number_prod"]?> - <?=$desc?> (<?=$unitdesc?>)</option>
               <?php
            }
            ?>
         </select>
         <textarea class="text" name="item_desc_<?=$y?>" id="item_desc_<?=$y?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
         style="width:<?=$ovritemselw?>;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$y]["item_desc"]?></textarea>
         <input type="hidden" name="manual_pos_<?=$y?>" id="manual_pos_<?=$y?>"
         value="<?php if($showmanual) echo "1"; else echo "0" ?>">
      </td>
      <td class="content_row" valign="top" align="center">
         <?php
         if((int)$posdata[$y]["item_id"])
            echo getItemUnitDesc($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
         echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="right" valign="top">
         <input type="text" class="text" style="width:50px;text-align:right"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
         name="item_amount_<?=$y?>" id="item_amount_<?=$y?>" <?=$rdlo?>
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_amount"],2)?>">
      </td>
      <?php
      if($showStorehouses)
      {  ?>
         <td class="content_row" valign="top">
            <?php
            if($showmanual)
            {
               $_FIELDIGNORES["item_stid_{$y}"] = 1;
               echo "&nbsp;";
            }
            else
            {  ?>
               <select class="text" style="width:120px;<?if((int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>"
               name="item_stid_<?=$y?>" id="item_stid_<?=$y?>"
               onblur="markfield(this,1);removeSelStyle(this);"
               onfocus="addSelStyle(this);"
               onmousedown="markfield(this,0)">
                  <?php
                  if((int)$posdata[$y]["item_id"])
                  {
                     if($posdata[$y]["item_type"] == "item")
                     {
                        $checkitemid = $posdata[$y]["item_id"];
                        if((int)$posdata[$y]["item_subitem_id"])
                           $checkitemid = $posdata[$y]["item_subitem_id"];
                              
                        $itemsts = getItemStorehouses($CON, $headdata["note_shop_id"], $checkitemid, $posdata[$y]["item_type"]);
                     }
                     else
                     {
                        $itemlistpos = getItemListContent($CON, $posdata[$y]["item_id"]);
                        $itemsts     = getItemStorehouses($CON, $headdata["note_shop_id"], $itemlistpos[0]["item_id"], "item");
                     }
                     if(count($itemsts))
                     {
                        foreach(array_keys($itemsts) AS $itemstid)
                        {
                           if($rdlo == "" || ($rdlo != "" && $posdata[$y]["item_st_id"] == $itemstid))
                           {
                              $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $itemstid, $checkitemid, $posdata[$y]["item_type"], true);
                              ?>
                              <option value="<?=$itemstid?>"
                              <?php if($posdata[$y]["item_st_id"] == $itemstid) echo "selected"?>>
                                 <?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)
                              </option>
                              <?php
                           }
                        }
                     }
                  }
                  ?>
               </select>
               <?php
            }
            ?>
            <div style="<?if(!(int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$y?>">
            <?php
            $btnicon = "arrow";
            $btnname = "Lotes";
            $btnclas = "postnav";

            if(posHasItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesnotessbuy", $y) > 0)
            {
               $btnicon = "tick-circle-frame";
               $btnclas = "postnav_save";

               $charge_amount = posItemChargeDataAmountUsed($CON, $_REQUEST["id"], "invoicesnotessbuy", $y);
               if($charge_amount["tran_amount"] != $posdata[$y]["item_amount"])
               {
                  $btnicon = "cross-circle-frame";
                  $btnclas = "postnav_del";
                  $_BLOCKFIN_CHARGE = true;
               }
            }
            elseif((int)$posdata[$y]["item_charges_act"])
               $_BLOCKFIN_CHARGE = true;
   
            printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.select.php?trantype=invoicesnotessbuy&needamount=' +$('#item_amount_{$y}').val() +'&tranid={$_REQUEST["id"]}&tranpos={$y}&itemdata=' +escape($('#item_id_{$y}').val()), 'iframe', 750, 400, 'auto')", "", $btnicon)
            ?>
            <textarea id="item_charges_data_<?=$y?>" name="item_charges_data_<?=$y?>" style="display:none"></textarea>
            </div>
         </td>
         <?php
      }
      else
         $_FIELDIGNORES["item_stid_{$y}"] = 1;
      ?>
      <td class="content_row" align="right" valign="top">
         <nobr>
         <?=$moneystr?>
         <input type="text" class="text" style="width:<?=$inputw?>;text-align:right" <?=$rdlo?>
         name="item_costprice_netto_<?=$y?>" id="item_costprice_netto_<?=$y?>" autocomplete="off"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_costprice_netto"], $numberlim)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </nobr>
      </td>
      <td class="content_row" align="right" valign="top">
         <nobr>
         <input type="text" class="text" style="width:40px;text-align:right" <?=$rdlo?>
         name="item_discount_<?=$y?>" id="item_discount_<?=$y?>" autocomplete="off"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_discount"], $numberlim)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">%
         </nobr>
      </td>
      <td class="content_row" align="right" valign="top">
         <input type="text" class="text" style="width:38px;text-align:right" <?=$rdlo?> autocomplete="off"
         name="item_costprice_taxes_perc_<?=$y?>" id="item_costprice_taxes_perc_<?=$y?>"
         value="<?php
         if((int)$posdata[$y]["item_id"])
         {
            echo printPrice($posdata[$y]["item_costprice_taxes_perc"],2);
            $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val7"] = printPrice($posdata[$y]["item_costprice_taxes_perc"],2);
         }
         elseif((int)$headdata["note_taxes"])
         {
            echo printPrice($_SESSION["_CONF"]["conf_taxes"],2);
            $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val7"] = printPrice($_SESSION["_CONF"]["conf_taxes"],2);
         }
         else
         {
            echo "0";
            $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val7"] = "0";
         }
         ?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" align="right" valign="top">
         <nobr>
         <?=$moneystr?>
         <input type="text" class="text" readonly
         style="width:<?=$inputw2?>;text-align:right;background-color:<?if((int)$posdata[$y]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         name="item_costprice_netto_dsc_<?=$y?>" id="item_costprice_netto_dsc_<?=$y?>"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_costprice_netto_dsc"], $numberlim)?>">
         </nobr>
      </td>
   </tr>
   <?php
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val1"] = $desc;
   if($showmanual)
      $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val1"] = $posdata[$y]["item_desc"];
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val2"] = getItemUnitDesc($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val3"] = printPrice($posdata[$y]["item_amount"],2);
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val4"] = $posdata[$y]["item_code"];
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val5"] = $moneystr." ".printPrice($posdata[$y]["item_costprice_netto"], $numberlim);
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val6"] = printPrice($posdata[$y]["item_discount"], $numberlim);
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val8"] = $moneystr." ".printPrice($posdata[$y]["item_costprice_netto_dsc"], $numberlim);
   $_SESSION["STATS"][$_sesmodulename]["ITEMS"][$y]["val9"] = $posdata[$y]["item_number_prod"];

   if($y == 0 && !(int)$posdata[$y]["item_id"])
      $_SESSION["JSEXEC"] .= "document.form_shppos.xf_search_{$y}.focus();";
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?php
if($y != 0)
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col width="130">
   </colgroup>
   <tr>
      <td class="content_rowl" align="right"><b>MONTO NETO</b></td>
      <td class="content_rowl" align="right">
         <input type="text" class="text" style="width:120px;text-align:right" readonly
         value="<?=printPrice($headdata["note_total_netto"],$numberlim)?>">
      </td>
      <?php
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][0]["val1"] = "<b>MONTO NETO</b>";
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][0]["val2"] = "<b>".printPrice($headdata["note_total_netto"],$numberlim)."</b>";
      ?>
   </tr>
   </table>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="700">
      <col>
      <col width="130">
   </colgroup>
   <tr>
      <td class="content_row">&nbsp;</td>
      <td class="content_row" align="right"><b>IVA</b></td>
      <td class="content_row" align="right">
         <input type="text" class="text" style="width:120px;text-align:right" readonly
         value="<?=printPrice($headdata["note_total_taxes"])?>">
      </td>
      <?php
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][1]["val1"] = "<b>IVA</b>";
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][1]["val2"] = "<b>".printPrice($headdata["note_total_taxes"])."</b>";
      ?>
   </tr>
   <tr>
      <td class="content_row">&nbsp;</td>
      <td class="content_row" align="right"><b class="msg_save_ok">TOTAL</b></td>
      <td class="content_row" align="right">
         <input type="text" class="text" style="width:120px;text-align:right;background-color:#E1FFD6" readonly
         value="<?=printPrice($headdata["note_total_brutto"],$numberlim)?>">
      </td>
      <?php
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][2]["val1"] = "<b>TOTAL</b>";
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][2]["val2"] = "<b>".printPrice($headdata["note_total_brutto"], $numberlim)."</b>";
      ?>
   </tr>
   </table>
   <?=Nifty_printF(false)?>
   <br>
   <?php
}
?>
<?=Nifty_printH("boxopt_b", "980")?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <td width="130">
      <?php
      printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
      ?>
   </td>
   <td>&nbsp;</td>
   <?php
   $_BLOCKSYNC = false;
   if($isremote && $headdata["note_status"] > 1 && !(int)$headdata["note_remote_synced"])
   {  ?>
      <td width="130" style="padding-right:5px;color:red" class="content_row_clear">
         Esperando sucursal.
      </td>
      <?php
      $_BLOCKSYNC = true;
   }
   if(($headdata["note_status"] == 2 || $headdata["note_status"] == 3) && !$_BLOCKOPEN_CHARGE && !$_BLOCKSYNC)
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         if($hasdocopeperm)
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.note_status.value='1';submitForm(document.form_shppos);}", "arrow-circle-045-left");
         else
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos&nStatus=1', 'iframe', 450, 160, 'no')", "arrow-circle-045-left");
         ?>
      </td>
      <?php
   }
   if($headdata["note_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
      </td>
      <?php
      if(!$_BLOCKFIN_CHARGE && $hasItems && (($headdata["note_type"] == 4 && (int)$headdata["note_parent_invcid"]) || $headdata["note_type"] != 4))
      {  ?>
         <td align="right" width="130" id="idx_fin_button">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.note_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   else
   {  ?>
      <td align="right" width="140" style="padding-left:5px">
         <?php
         printButton("Imprimir", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$_REQUEST["id"]}&printpdf=1", "", "document-pdf");
         ?>
      </td>
      <?php
   }
   ?>
</tr>
</table>
<?php
if($rdlo == "")
   $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');";
?>
<?=Nifty_printF(false)?>
</form>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createInvoiceBuyNotePDF($CON);

if($pdffile != "")
{
   if($headdata["note_type"] == 1)
      $doctitle = "Nota-de-credito-".time().".pdf";
   else
      $doctitle = "Nota-de-dedito-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
