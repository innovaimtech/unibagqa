<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xinvoicesbuy"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xinvoicesbuy"]["fullcust"] = "";

$_sesmodulename = "invoice_buy";
unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();

   $_REQUEST["supplier_id_0"] = (int)$_REQUEST["supplier_id_0"];
   $_REQUEST["company_id"]    = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]       = (int)$_REQUEST["shop_id"];
   $_REQUEST["invc_taxes"]    = (int)$_REQUEST["invc_taxes"];
   $_REQUEST["invc_type"]     = (int)$_REQUEST["invc_type"];
   $_REQUEST["invc_date"]     = trim($_REQUEST["invc_date"]);
   $_REQUEST["invc_is_contenedorproduct_act"] = (int)$_REQUEST["invc_is_contenedorproduct_act"];
   $_REQUEST["invc_date"]     = explode(".", $_REQUEST["invc_date"]);
   $_REQUEST["invc_date"]     = (int)mktime(15, 0, 0, $_REQUEST["invc_date"][1], $_REQUEST["invc_date"][0], $_REQUEST["invc_date"][2]);
   
   $supppaymentid             = 0;

   //----------------------------------------------------------------------------------
   $supptax = getSupplierTaxes($CON, $_REQUEST["supplier_id_0"]);
   if(!$supptax)
   {
      $invc_importation = 1;
      $_REQUEST["invc_taxes"] = 0;
   }
   else
      $invc_importation = 0;

   //----------------------------------------------------------------------------------
   if($_REQUEST["invc_type"] == 2 || $_REQUEST["invc_type"] == 3 || $_REQUEST["invc_type"] == 4)
      $invc_stockchange = 1;
   else
      $invc_stockchange = 0;

   //----------------------------------------------------------------------------------
   $isremote = getShops($CON, false, false, 0, $_REQUEST["shop_id"]);
   $isremote = $isremote[0]["shop_isremote"];
   if($isremote)
      $invc_stockchange = 0;

   if($_REQUEST["invc_type"] == 3 || $_REQUEST["invc_type"] == 4)
   {
      $sql = " select supp_paymentid
               from supplier
               where
               id = {$_REQUEST["supplier_id_0"]}";
      $supppaymentid = $CON->select($sql);
      $supppaymentid = (int)$supppaymentid[0]["supp_paymentid"];
   }

   if((int)$_REQUEST["invc_is_contenedorproduct_act"])
      $invc_stockchange = 0;

   //----------------------------------------------------------------------------------
   $invc_number   = createTransactionNumber($CON, $_REQUEST["company_id"], "invoicebuy");
   $invc_date     = $_REQUEST["invc_date"];

   if($invc_importation)
      $usdval = getMoneyExchangeRate($CON, date('d.m.Y'));
   else
      $usdval = 0;

   $invc_intnumber = createInternalBuyNumber($CON, $_REQUEST["company_id"], $_REQUEST["shop_id"], "invoices_buy", $_REQUEST["invc_date"]);

   //----------------------------------------------------------------------------------
   $sql = " insert into invoices_buy
            (invc_number, invc_supplier_id, invc_company_id, invc_shop_id, invc_date, invc_receipt_date, invc_contab_date,
             invc_taxes, invc_type, invc_stockchange, invc_importation, invc_exc_rate, invc_paymentid,
             invc_crtdat, invc_crtusr, invc_intnumber, invc_is_contenedorproduct_act)
            VALUES
            ('{$invc_number}', {$_REQUEST["supplier_id_0"]}, {$_REQUEST["company_id"]},
              {$_REQUEST["shop_id"]}, {$invc_date}, {$invc_date}, {$invc_date}, {$_REQUEST["invc_taxes"]}, {$_REQUEST["invc_type"]},
              {$invc_stockchange}, {$invc_importation}, {$usdval}, {$supppaymentid}, {$currtme}, {$_SESSION["user_id"]},
              '{$invc_intnumber}', {$_REQUEST["invc_is_contenedorproduct_act"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_buy
               where
               invc_crtusr = {$_SESSION["user_id"]}";
      $invoice = $CON->select($sql);
      $_REQUEST["id"]   = $invoice[0]["thisid"];

      //----------------------------------------------------------------------------------
      if($_REQUEST["invc_type"] == 3 || $_REQUEST["invc_type"] == 4)
      {
         $sql = " insert into invoices_buy_parts
                  (part_invc_id, part_crtdat, part_crtusr, part_shp_id, part_sord_id)
                  VALUES
                  ({$_REQUEST["id"]}, {$currtme}, {$_SESSION["user_id"]}, 0, 0)";
         $CON->no_result($sql);
      }

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=10072&exec=editinvoice&id=<?=$_REQUEST["id"]?>'
      </script>
      <?php
   }

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["addshp"] != "")
   invoiceBuyAddShipment($CON, $_REQUEST["id"], $_REQUEST["addshp"]);
if($_REQUEST["addsord"] != "")
   invoiceBuyAddSupplierOrder($CON, $_REQUEST["id"], $_REQUEST["addsord"], 0);
if($_REQUEST["addparentinvcid"] != "")
{
   $sql = " update invoices_buy
            set
            invc_parent_invcid = {$_REQUEST["addparentinvcid"]}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $_REQUEST["invc_desc"]            = trim(addslashes($_REQUEST["invc_desc"]));
   $_REQUEST["invc_date"]            = trim(addslashes($_REQUEST["invc_date"]));
   $_REQUEST["invc_docnumber"]       = trim(addslashes($_REQUEST["invc_docnumber"]));
   $_REQUEST["invc_intnumber"]       = trim(addslashes($_REQUEST["invc_intnumber"]));
   $_REQUEST["invc_paymentid"]       = (int)$_REQUEST["invc_paymentid"];
   $_REQUEST["invc_stockchange"]     = (int)$_REQUEST["invc_stockchange"];
   $_REQUEST["invc_mark_obs"]        = (int)$_REQUEST["invc_mark_obs"];
   $_REQUEST["invc_mark_nodsc"]      = (int)$_REQUEST["invc_mark_nodsc"];
   $_REQUEST["invc_total_taxes_add"] = getPrice($_REQUEST["invc_total_taxes_add"]);
   $_REQUEST["invc_code_cont_id"]    = (int)$_REQUEST["invc_code_cont_id"];

   if($_REQUEST["delinvcpartid"] != "")
   {
      $sql = " delete from invoices_buy_parts
               where
               id = {$_REQUEST["delinvcpartid"]}";
      $CON->no_result($sql);
      
      $sql = " delete from invoices_buy_parts_items
               where
               invc_id  = {$_REQUEST["id"]} and
               part_id  = {$_REQUEST["delinvcpartid"]}";
      $CON->no_result($sql);

      $sql = " delete from tran_charges
               where
               tran_id        = {$_REQUEST["id"]} and
               tran_partid    = {$_REQUEST["delinvcpartid"]} and
               tran_type      = 'invoicesbuy'";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_buy
            where
            id = {$_REQUEST["id"]}";
   $invoicetemp      = $CON->select($sql);
   $invoiceimport    = $invoicetemp[0]["invc_importation"];
   $invoicetaxes     = $invoicetemp[0]["invc_taxes"];
   $invoicesupplier  = $invoicetemp[0]["invc_supplier_id"];
   $invoicedate      = $invoicetemp[0]["invc_date"];
   $invoiceusdval    = $invoicetemp[0]["invc_exc_rate"];
   $shopid           = $invoicetemp[0]["invc_shop_id"];

   //----------------------------------------------------------------------------------
   if((int)$invoicetemp[0]["invc_is_contenedorproduct_act"])
   {
      $_REQUEST["invc_stockchange"] = 0;
   }

   //----------------------------------------------------------------------------------
   $isremote = getShops($CON, false, false, 0, $shopid);
   $isremote = $isremote[0]["shop_isremote"];
   if($isremote)
      $_REQUEST["invc_stockchange"] = 0;

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
   $lastpartid = -1;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idxpos        = substr($reqkey, strrpos($reqkey, "_") +1);
         $subreqkey     = substr($reqkey, 0, strrpos($reqkey, "_"));
         $idxpartid     = substr($subreqkey, strrpos($subreqkey, "_") +1);
         $idx           = $idxpartid."_".$idxpos;

         //----------------------------------------------------------------------------------
         if($idxpartid != $lastpartid)
         {
            $lastpartid = $idxpartid;
            $poscounter = 0;
         }

         //----------------------------------------------------------------------------------
         $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
         $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];

         
         $_REQUEST["item_stid_{$idx}"]          = (int)$_REQUEST["item_stid_{$idx}"];
         $_REQUEST["item_amount_{$idx}"]        = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $_REQUEST["item_discount_{$idx}"]      = getPrice($_REQUEST["item_discount_{$idx}"],8);
         $_REQUEST["item_no_costcalc_{$idx}"]   = (int)$_REQUEST["item_no_costcalc_{$idx}"];

         if(!$_REQUEST["invc_stockchange"])
            $_REQUEST["item_stid_{$idx}"] = 0;

         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id           = (int)$itemvalues[0];
            $sql_type         = $itemvalues[1];
            $sql_charges_act  = (int)$itemvalues[5];

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
            if($_REQUEST["item_discount_{$idx}"] != 0.00)
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
               $sql = " update invoices_buy_parts_items
                        set
                        item_amount                = {$_REQUEST["item_amount_{$idx}"]},
                        item_costprice_brutto      = {$sql_costprice},
                        item_costprice_taxes_perc  = {$sql_taxesperc},
                        item_costprice_netto       = {$sql_costnetto},
                        item_costprice_taxes       = {$sql_taxes},
                        item_costprice_netto_dsc   = {$item_costprice_netto_dsc},
                        item_discount              = {$_REQUEST["item_discount_{$idx}"]},
                        item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                        item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}',
                        item_no_costcalc           = {$_REQUEST["item_no_costcalc_{$idx}"]},
                        item_pos                   = {$poscounter}
                        where
                        invc_id  = {$_REQUEST["id"]} and
                        part_id  = {$idxpartid} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);

               renameItemChargePos($CON, $_REQUEST["id"], "invoicesbuy", $existing_pos, $poscounter, $idxpartid);

               updateItemChargeData($CON, $_REQUEST["id"], "invoicesbuy", $existing_id, $sql_type, $poscounter,
                                    $invoicetemp[0]["invc_company_id"], $invoicetemp[0]["invc_shop_id"],
                                    $_REQUEST["item_charges_data_{$idx}"], $idxpartid);
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
               
               //----------------------------------------------------------------------------------
               $sql = " insert into invoices_buy_parts_items
                        (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                         item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes,
                         item_costprice_netto_dsc, item_discount, item_st_id, item_desc, item_charges_act,
                         item_no_costcalc, item_subitem_id, item_subitem_amount)
                        VALUES
                        ({$_REQUEST["id"]}, {$idxpartid}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                         {$sql_costprice}, {$sql_taxesperc}, {$sql_costnetto}, {$sql_taxes}, {$item_costprice_netto_dsc},
                         {$_REQUEST["item_discount_{$idx}"]}, {$_REQUEST["item_stid_{$idx}"]}, '{$_REQUEST["item_desc_{$idx}"]}',
                         {$sql_charges_act}, {$_REQUEST["item_no_costcalc_{$idx}"]}, {$item_subitem_id}, {$item_subitem_amount})";
               $CON->no_result($sql);

               //----------------------------------------------------------------------------------
               $sql = " update invoices_buy_parts_items
                        set
                        item_costprice_netto_dsc = {$itemprctot},
                        item_discount = {$netto_dsc}
                        where
                        invc_id  = {$_REQUEST["id"]} and
                        part_id  = {$idxpartid} and
                        item_id  = {$sql_id} and
                        item_pos = {$poscounter}";
               $CON->no_result($sql);

               //----------------------------------------------------------------------------------
               updateItemChargeData($CON, $_REQUEST["id"], "invoicesbuy", $sql_id, $sql_type, $poscounter,
                                    $invoicetemp[0]["invc_company_id"], $invoicetemp[0]["invc_shop_id"],
                                    $_REQUEST["item_charges_data_{$idx}"], $idxpartid);
            }

            $poscounter++;
         }
         elseif($existing_id)
         {
            $sql = " delete from invoices_buy_parts_items
                     where
                     invc_id  = {$_REQUEST["id"]} and
                     part_id  = {$idxpartid} and
                     item_id  = {$existing_id} and
                     item_pos = {$existing_pos}";
            $CON->no_result($sql);

            clearItemChargeData($CON, $_REQUEST["id"], "invoicesbuy", $existing_pos, $idxpartid);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $checkinvcdate = $_REQUEST["invc_date"];

   //----------------------------------------------------------------------------------
   $_REQUEST["invc_date"] = explode(".", $_REQUEST["invc_date"]);
   $_REQUEST["invc_date"] = (int)mktime(15, 0, 0, $_REQUEST["invc_date"][1], $_REQUEST["invc_date"][0], $_REQUEST["invc_date"][2]);
   $_REQUEST["invc_receipt_date"] = explode(".", $_REQUEST["invc_receipt_date"]);
   $_REQUEST["invc_receipt_date"] = (int)mktime(15, 0, 0, $_REQUEST["invc_receipt_date"][1], $_REQUEST["invc_receipt_date"][0], $_REQUEST["invc_receipt_date"][2]);
   $_REQUEST["invc_contab_date"]  = explode(".", $_REQUEST["invc_contab_date"]);
   $_REQUEST["invc_contab_date"]  = (int)mktime(15, 0, 0, $_REQUEST["invc_contab_date"][1], $_REQUEST["invc_contab_date"][0], $_REQUEST["invc_contab_date"][2]);
   
   if(!$_REQUEST["invc_receipt_date"])
      $_REQUEST["invc_receipt_date"] = $_REQUEST["invc_date"];
   if(!$_REQUEST["invc_contab_date"])
      $_REQUEST["invc_contab_date"] = $_REQUEST["invc_receipt_date"];
      
   if($invoiceimport)
      $_REQUEST["invc_exc_rate"] = getPrice($_REQUEST["invc_exc_rate"],8);
   else
      $_REQUEST["invc_exc_rate"] = 0.00;

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_buy
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $revert  = false;
   $final   = false;
   if($headdata["invc_status"] == 1 && $_REQUEST["invc_status"] == 2)
      $final = true;
   if($headdata["invc_status"] == 2 && $_REQUEST["invc_status"] == 1)
      $revert = true;

   //----------------------------------------------------------------------------------
   if($_REQUEST["invc_status"] == 1)
      $_REQUEST["invc_payed"] = 0;

   //----------------------------------------------------------------------------------
   if($headdata["invc_paymentid"] != $_REQUEST["invc_paymentid"] || $headdata["invc_receipt_date"] != $_REQUEST["invc_receipt_date"])
   {
      $sql = " select pay_days
               from payments
               where
               id = {$_REQUEST["invc_paymentid"]}";
      $paydays = $CON->select($sql);
      $paydays = (int)$paydays[0]["pay_days"];

      if($paydays == 0)
         $_REQUEST["invc_estpay_date"] = 0;
      else
         $_REQUEST["invc_estpay_date"] = date('d.m.Y', $_REQUEST["invc_receipt_date"] + (86400 * $paydays));
   }
   if($_REQUEST["invc_estpay_date"] == "")
      $_REQUEST["invc_estpay_date"] = 0;
   else
   {
      $_REQUEST["invc_estpay_date"] = explode(".", $_REQUEST["invc_estpay_date"]);
      $_REQUEST["invc_estpay_date"] = (int)mktime(15, 0, 0, $_REQUEST["invc_estpay_date"][1], $_REQUEST["invc_estpay_date"][0], $_REQUEST["invc_estpay_date"][2]);
   }

   //----------------------------------------------------------------------------------
   for($y = 1; $y <= 6; $y++)
   {
      $_REQUEST["invc_discount{$y}"]      = getPrice($_REQUEST["invc_discount{$y}"],2);
      $_REQUEST["invc_discount_type{$y}"] = (int)$_REQUEST["invc_discount_type{$y}"];
   }

   $_REQUEST["invc_contdoctype_id"] = (int)$_REQUEST["invc_contdoctype_id"];

   //----------------------------------------------------------------------------------
   $sql = " update invoices_buy
            set
            invc_status          = {$_REQUEST["invc_status"]},
            invc_intnumber       = '{$_REQUEST["invc_intnumber"]}',
            invc_desc            = '{$_REQUEST["invc_desc"]}',
            invc_docnumber       = '{$_REQUEST["invc_docnumber"]}',
            invc_date            = {$_REQUEST["invc_date"]},
            invc_paymentid       = {$_REQUEST["invc_paymentid"]},
            invc_payed           = {$_REQUEST["invc_payed"]},
            invc_stockchange     = {$_REQUEST["invc_stockchange"]},
            invc_estpay_date     = {$_REQUEST["invc_estpay_date"]},
            invc_receipt_date    = {$_REQUEST["invc_receipt_date"]},
            invc_contab_date     = {$_REQUEST["invc_contab_date"]},
            invc_exc_rate        = {$_REQUEST["invc_exc_rate"]},
            invc_mark_obs        = {$_REQUEST["invc_mark_obs"]},
            invc_mark_nodsc      = {$_REQUEST["invc_mark_nodsc"]},
            invc_total_taxes_add = {$_REQUEST["invc_total_taxes_add"]},
            invc_contdoctype_id  = {$_REQUEST["invc_contdoctype_id"]},
            invc_code_cont_id    = {$_REQUEST["invc_code_cont_id"]}, ";

   //----------------------------------------------------------------------------------
   for($y = 1; $y <= 6; $y++)
   {
      $sql .= " invc_discount{$y}      = {$_REQUEST["invc_discount{$y}"]},
                invc_discount_type{$y} = {$_REQUEST["invc_discount_type{$y}"]}, ";
   }

   //----------------------------------------------------------------------------------
   $sql .= "invc_updusr          = {$_SESSION["user_id"]},
            invc_upddat          = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   $sql = " select count(*) 'cc'
            from invoices_buy t1
            INNER JOIN invoices_buy t2 ON (  t1.invc_supplier_id = t2.invc_supplier_id and
                                             t1.invc_status > 0 and t2.invc_status > 0 and
                                             t1.invc_docnumber = t2.invc_docnumber)
            where
            t1.id = {$_REQUEST["id"]}";
   $shpdoccheck = $CON->select($sql);
   if((int)$shpdoccheck[0]["cc"] > 1)
   {
      $sql = " update invoices_buy
               set
               invc_docnumber = ''
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      $_SESSION["JSEXEC"] .= ";alert('EL NUMERO DE FACTURA YA EXISTE PARA EL PROVEEDOR');";
   }

   //----------------------------------------------------------------------------------
   if($final)
   {
      recalcInvoiceBuy($CON, $_REQUEST["id"]);
      bookInvoiceBuy($CON, $_REQUEST["id"]);
   }
   if($revert)
   {
      revertInvoiceBuy($CON, $_REQUEST["id"]);

      $sql = " update invoices_buy
               set
               invc_remote_synced_wait = 1
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

   }
   if(!$final)
      recalcInvoiceBuy($CON, $_REQUEST["id"]);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.supp_company, t2.supp_email, t3.company_short, t4.shop_name, t1.invc_supplier_id, t1.invc_taxes,
                t2.supp_notes,  t7.pay_title,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t11.typedoc_cont_nameid, t11.typedoc_cont_code
         from invoices_buy t1
         LEFT OUTER JOIN supplier t2      ON t1.invc_supplier_id  = t2.id
         LEFT OUTER JOIN company_data t3  ON t1.invc_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.invc_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.invc_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.invc_crtusr       = t6.id
         LEFT OUTER JOIN payments t7      ON t1.invc_paymentid    = t7.id
         LEFT OUTER JOIN typedoc_contables t11 ON t1.invc_contdoctype_id = t11.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$isremote = getShops($CON, false, false, 0, $headdata["invc_shop_id"]);
$isremote = (int)$isremote[0]["shop_isremote"];

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
         from supplier t1
         LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
         LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
         LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
         LEFT OUTER JOIN provincias t5 ON t1.supp_provinciaid  = t5.id
         where
         t1.id = {$headdata["invc_supplier_id"]}";
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
$invcparts   = getInvoiceBuyParts($CON, $_REQUEST["id"]);

if((int)$headdata["invc_taxes"])
   $typedoccont = getTypeDocContList($CON, "FACTURA");
else
   $typedoccont = getTypeDocContList($CON, "FACTURAEXT");

//----------------------------------------------------------------------------------
$rowcount = 0;
if($posdata != false && count($posdata))
   $rowcount = count($posdata);

if($rowcount <= 6)
   $rowcount = 10;
else
   $rowcount += 5;

//----------------------------------------------------------------------------------
if($headdata["invc_status"] >= 2)
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
if($_SESSION["xinvoicesbuy"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

if((int)$headdata["invc_importation"])
{
   $inputw    = "50px";
   $inputw2   = "55px";
   $moneystr  = "US&nbsp;";
   $numberlim = "4";
}
else
{
   $inputw    = "60px";
   $inputw2   = "70px";
   $moneystr  = "";
   $numberlim = "2";
}

//----------------------------------------------------------------------------------
$showStorehouses  = false;
$itemselw         = "420px";
if((int)$headdata["invc_stockchange"])
{
   $showStorehouses  = true;
   $itemselw         = "340px";
}

?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/invoices_buy/searchstorehouses.php?invcid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;

      var valarr  = $('#item_id_' +idx).val().split('#');
      valarr[4]   = valarr[5];
      switchShpChargeMode(idx, valarr);
   }

   function updateItemStorehousesArr(idx, xval)
   {
      var valarr = xval.split('#');
      updateItemStorehouses(idx, valarr[0], valarr[1]);
   }

   function detectEvent (event, rowcount, id, storehousemode)
   {
      var xurl = './libs/modules/invoices_buy/searchitem.fancy.php?rowcount=' +rowcount + '&id=' +id +'&storehousemode=' +storehousemode;
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
   onsubmit="return checkform(new Array(this.invc_contdoctype_id, this.invc_docnumber, this.invc_date, this.invc_paymentid <?php
   if((int)$headdata["invc_importation"])
      echo ", this.invc_exc_rate";
   ?>));"
   <?php
}
?>>
<input type="hidden" name="exec" value="editinvoice">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="invc_status" value="1">
<input type="hidden" name="delinvcpartid" value="0">
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
   <td class="content_row"><?=$headdata["invc_number"]?></td>
   <td class="content_rowl">Tipo</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear"><?=getInvoiceBuyType($headdata["invc_type"])?></td>
         <td class="content_row_clear" align="right" style="padding-right:30px">
            Correlativo:
            <input type="text" style="width:80px;text-align:center" name="invc_intnumber" class="text" <?=$rdlo?>
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["invc_intnumber"]?>">
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
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_number"]     = $headdata["invc_number"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["type"]            = getInvoiceBuyType($headdata["invc_type"])." ".$headdata["invc_intnumber"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["company_short"]   = $headdata["company_short"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["shop_name"]       = $headdata["shop_name"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_company_id"] = $headdata["invc_company_id"];
?>
<tbody>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Proveedor</td>
   <td class="content_row" <?=$cdatastyle?>>
      <a href="javascript:void(0)" style="text-decoration:none;color:black"
      onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=editinvoice&subexec=edit&id=<?=$headdata["id"]?>&showfullcust=<?php
      if($_SESSION["xinvoicesbuy"]["fullcust"] == "") echo "1"; else echo "0"?>'"> <b><?=$headdata["supp_company"]?></b></a></td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row" <?=$cdatastyle?>><b><?=$supplier["supp_rut"]?></b>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicesbuy"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$supplier["supp_street"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?if($supplier["supp_phone"] != "") echo $supplier["supp_phone"];?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicesbuy"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Región</td>
   <td class="content_row"><?=$supplier["name"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][16]?></td>
   <td class="content_row"><?=$supplier["supp_fax"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicesbuy"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Comuna</td>
   <td class="content_row"><?=$supplier["pro_name"]?> - <?=$supplier["nombre"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
   <td class="content_row"><?=$supplier["supp_email"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicesbuy"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">País</td>
   <td class="content_row"><?=$supplier["country_name"]?>&nbsp;</td>
   <td class="content_rowl">Opciones</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear">
            <?php
            printButton("Cambiar datos del proveedor", "postnav", "index.php?mid=497&exec=edit&fromdocexec=editinvoice&id={$supplier["id"]}&registerback={$_REQUEST["mid"]}-{$_REQUEST["id"]}", "", "disk-black", 200);
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
   <td class="content_rowl" <?=$cdatastyle?> valign="top">Número factura *</td>
   <td class="content_row" <?=$cdatastyle?> valign="top">
      <input type="text" style="width:350px" name="invc_docnumber" class="text" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["invc_docnumber"]?>">
      <br>
      <div style="padding-top:3px">
      <input type="checkbox" name="invc_mark_obs" value="1" class="checkbox"
      <?php if((int)$headdata["invc_mark_obs"]) echo "checked"?>> Con observaciones
      </div>
   </td>
   <td class="content_rowl" <?=$cdatastyle?> valign="top">Fecha *</td>
   <td class="content_row" <?=$cdatastyle?>>
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <input type="text" style="width:80px" id="invc_date" name="invc_date" <?=$rdlo?>
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date('d.m.Y', $headdata["invc_date"])?>">
         </td>
         <td class="content_row_clear" align="right" style="padding-right:10px">
            Fecha recepción:
            <input type="text" style="width:80px;<?php if($rdlo != "") echo "margin-right:20px"?>" id="invc_receipt_date" name="invc_receipt_date"
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            onchange="document.form_shppos.onsubmit='';submitForm(document.form_shppos);"
            value="<?php if($headdata["invc_receipt_date"] > 0) echo date('d.m.Y', $headdata["invc_receipt_date"])?>">
         </td>
      </tr>
      <tr>
         <td class="content_row_clear">&nbsp;</td>
         <td class="content_row_clear" align="right" style="padding-right:10px;padding-top:3px">
            Fecha contable:
            <input type="text" style="width:80px;<?php if($rdlo != "") echo "margin-right:20px"?>" id="invc_contab_date" name="invc_contab_date"
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            value="<?php if($headdata["invc_contab_date"] > 0) echo date('d.m.Y', $headdata["invc_contab_date"])?>">
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl">Forma de pago *</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="invc_paymentid" id="invc_paymentid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($payments as $payment)
            {  ?>
               <option value="<?=$payment["id"]?>"
               <?php if($payment["id"] == $headdata["invc_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["invc_paymentid"]?>"><?=$headdata["pay_title"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <?php
            $statimg = "";
            switch((int)$headdata["invc_status"])
            {
               case 1: $statimg = "red_active.gif"; break;
               case 2: $statimg = "orange_active.gif"; break;
               case 3: $statimg = "green_active.gif"; break;
            }
            ?>
            <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
            <?=getInvoiceBuyStatus($headdata["invc_status"], true)?>
         </td>
         <td class="content_row_clear" align="right" style="padding-right:10px">
            Fecha vencimiento:
            <input type="text" style="width:80px;<?php if($rdlo != "") echo "margin-right:20px"?>" id="invc_estpay_date" name="invc_estpay_date"
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            value="<?php if($headdata["invc_estpay_date"] > 0) echo date('d.m.Y', $headdata["invc_estpay_date"])?>">
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top" rowspan="2">Observaciones</td>
   <td class="content_row" valign="top" rowspan="2">
      <textarea class="text" style="width:350px; height:50px" name="invc_desc" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["invc_desc"])?></textarea>
   </td>
   <td class="content_rowl">IVA</td>
   <td class="content_row">
      <select class="text" style="width:120px;background-color:<?php if((int)$headdata["invc_taxes"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
         <?php
         if((int)$headdata["invc_taxes"])
         {
            echo '<option value="">CON IVA</option>';
            $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_taxes"] = "CON IVA";
         }
         else
         {
            echo '<option value="">SIN IVA</option>';
            $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_taxes"] = "SIN IVA";
         }
         ?>
      </select>
      <?php
      if(!(int)$headdata["invc_taxes"])
      {
         if((int)$headdata["invc_importation"])
         {  ?>
            <nobr>
            <span style="padding-left:7px">
               <b class="msg_save_err">[IMPORT]</b>
               <nobr>
               1 USD =
               <input type="text" style="width:80px" name="invc_exc_rate" class="text" <?=$rdlo?>
               onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=printPrice($headdata["invc_exc_rate"],8)?>">
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
<tr>
   <td class="content_rowl">Pagado</td>
   <td class="content_row">
      <input type="hidden" name="invc_payed" value="<?=(int)$headdata["invc_payed"]?>">
      <?php
      if((int)$headdata["invc_status"] > 1)
      {
         if((int)$headdata["invc_payed"])
         {
            $btnclass = "postnav_save";
            $btntext  = "Pagado";
         }
         else
         {
            $btnclass = "postnav_del";
            $btntext  = "Ingresar pago";
         }
         $_SESSION["STATS"][$_sesmodulename]["HEAD"]["payed"] = $btntext;
         printButton("{$btntext}", $btnclass, "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&id={$_REQUEST["id"]}&mode=invoices_buy&subcatexec=payment", "", "money", 120);
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
<tr>
   <td class="content_rowl">Codigo contable </td>
   <td class="content_row">
      <select class="text" name="invc_code_cont_id" id="invc_code_cont_id" style="width:100%"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?foreach($cods AS $cod)
         {  ?>
            <option value="<?=$cod["id"]?>"<?if($cod["id"] == $headdata["invc_code_cont_id"]) echo "selected"?>>
               <?=$cod["cc_code"]?> | <?=$cod["cc_title"]?>
            </option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">Cambio de stock</td>
   <td class="content_row">
      <select class="text" name="invc_stockchange" style="width:120px;background-color:<?php if((int)$headdata["invc_stockchange"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
      <?php
      if($headdata["invc_status"] == 1)
      {
         if($headdata["invc_type"] != 1)
         {  ?>
            <option value="1" <?php if((int)$headdata["invc_stockchange"]) echo "selected"?>>HABILITADO</option>
            <?php
         }
         ?>
         <option value="0" <?php if(!(int)$headdata["invc_stockchange"]) echo "selected"?>>DESHABILITADO</option>
         <?php
      }
      else
      {
         if((int)$headdata["invc_stockchange"])
         {  ?>
            <option value="1" selected>HABILITADO</option>
            <?php
         }
         else
         {  ?>
            <option value="0" selected>DESHABILITADO</option>
            <?php
         }
      }
      ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Tipo Doc. Cont. *</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="invc_contdoctype_id" id="invc_contdoctype_id"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($typedoccont as $cont)
            {  ?>
               <option value="<?=$cont["id"]?>"
               <?php if($cont["id"] == $headdata["invc_contdoctype_id"]) echo "selected"?>><?=$cont["typedoc_cont_code"]?> - <?=getTypeDocContName($cont["typedoc_cont_nameid"])?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["invc_contdoctype_id"]?>"><?=$headdata["typedoc_cont_code"]?> - <?=getTypeDocContName($headdata["typedoc_cont_nameid"])?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">&nbsp;</td>
   <td class="content_row">&nbsp;</td>
</tr>
<?php
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_docnumber"]    = $headdata["invc_docnumber"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_date"]         = date('d.m.Y', $headdata["invc_date"]);
if($headdata["invc_contab_date"])
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_contab_date"]  = date('d.m.Y', $headdata["invc_contab_date"]);
if($headdata["invc_receipt_date"])
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_receipt_date"] = date('d.m.Y', $headdata["invc_receipt_date"]);
if($headdata["invc_estpay_date"])
   $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_estpay_date"]  = date('d.m.Y', $headdata["invc_estpay_date"]);
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_desc"]         = $headdata["invc_desc"];
$_SESSION["STATS"][$_sesmodulename]["HEAD"]["pay_title"]         = $headdata["pay_title"];
?>
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
<?php
//----------------------------------------------------------------------------------
// ADJUNTOS
//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.docto_title,
         t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
         from tran_docs t1
         LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
         LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
         where
         t1.doc_tran_id    = {$_REQUEST["id"]} and
         t1.doc_tran_type  = 'invoices_buy'
         order by t1.id";
$docs = $CON->select($sql);
if(count($docs) && $docs != false)
{  ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col>
      <col>
      <col>
      <col>
      <col width="85">
      <col width="100">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="7">Documentos relacionados</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader">Documento</td>
      <td class="content_tbl_subheader">Tipo</td>
      <td class="content_tbl_subheader">Descripción</td>
      <td class="content_tbl_subheader">Creado por</td>
      <td class="content_tbl_subheader">Creado</td>
      <td class="content_tbl_subheader" align="center">Opciones</td>
   </tr>
   <?php
   for($x = 0; $x < count($docs) && $docs != false; $x++)
   {  ?>
      <tr bgcolor="<?=getRowColor($x)?>">
         <td class="content_row"><nobr><?=$docs[$x]["doc_name"]?>&nbsp;</nobr></td>
         <td class="content_row"><?=$docs[$x]["docto_title"]?>&nbsp;</td>
         <td class="content_row"><?=$docs[$x]["doc_desc"]?>&nbsp;</td>
         <td class="content_row"><?=$docs[$x]["crt_lastname"]?>&nbsp;</td>
         <td class="content_row"><?=date('d.m.Y',$docs[$x]["doc_crtdat"])?></td>
         <td class="content_row" align="center">
            <?php
            $docext = strtoupper(substr($docs[$x]["doc_file"], strrpos($docs[$x]["doc_file"], ".")+1));
            if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
               printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/invoices_buy/{$docs[$x]["doc_file"]}')", "image");
            else
               printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$docs[$x]["doc_file"]}&name={$docs[$x]["doc_name"]}&path=../../../docs.tran/invoices_buy/'", "navigation-270-white");
            ?>
         </td>
      </tr>
      <?php
   }
   ?>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
   $sql = " select invc_type
            from invoices_buy
            where
            id = {$_REQUEST["id"]}";
   $invc_type = $CON->select($sql);
   $invc_type = (int)$invc_type[0]["invc_type"];

   //DLV
   $_REFDOCS = Array();
   if($invc_type == 1)
   {
      $sql = " select part_shp_id
               from invoices_buy_parts
               where
               part_invc_id = {$_REQUEST["id"]} and
               part_shp_id > 0
               order by id";
      $spart_shp_ids = $CON->select($sql);
      foreach($spart_shp_ids AS $spart_shp_idrow)
      {
         $sql = " select t1.*, t2.docto_title,
                  t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                  from tran_docs t1
                  LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                  LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
                  where
                  t1.doc_tran_id    = {$spart_shp_idrow["part_shp_id"]} and
                  t1.doc_tran_type  = 'shipments'
                  order by t1.id";
         $refdocs = $CON->select($sql);
         for($x = 0; $x < count($refdocs) && $refdocs != false; $x++)
         {
            $_REFDOCS[] = $refdocs[$x];
         }
      }
      if(count($_REFDOCS) && $_REFDOCS != false)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="85">
            <col width="100">
         </colgroup>
         <tr>
         </tr>
            <td class="content_tbl_header" colspan="7" style="background-color:#00A9A6;color:#FFFFFF;text-shadow:none">Documentos referenciados</td>
         <tr>
            <td class="content_tbl_subheader">Origen</td>
            <td class="content_tbl_subheader">Documento</td>
            <td class="content_tbl_subheader">Tipo</td>
            <td class="content_tbl_subheader">Descripción</td>
            <td class="content_tbl_subheader">Creado por</td>
            <td class="content_tbl_subheader">Creado</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($_REFDOCS) && $_REFDOCS != false; $x++)
         {
            $sql = " select shp_supplier_docnum
                     from shipment
                     where
                     id = {$_REFDOCS[$x]["doc_tran_id"]}";
            $shp_supplier_docnum = $CON->select($sql);
            $shp_supplier_docnum = $shp_supplier_docnum[0]["shp_supplier_docnum"];
            ?>
            <tr bgcolor="<?=getRowColor($x)?>">
               <td class="content_row"><nobr>Guia: <?=$shp_supplier_docnum?></nobr></td>
               <td class="content_row"><nobr><?=$_REFDOCS[$x]["doc_name"]?>&nbsp;</nobr></td>
               <td class="content_row"><?=$_REFDOCS[$x]["docto_title"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["doc_desc"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["crt_lastname"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y',$_REFDOCS[$x]["doc_crtdat"])?></td>
               <td class="content_row" align="center">
                  <?php
                  $docext = strtoupper(substr($_REFDOCS[$x]["doc_file"], strrpos($_REFDOCS[$x]["doc_file"], ".")+1));
                  if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                     printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/shipments/{$_REFDOCS[$x]["doc_file"]}')", "image");
                  else
                     printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$_REFDOCS[$x]["doc_file"]}&name={$_REFDOCS[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
                  ?>
               </td>
            </tr>
            <?php
            $sql = " select shp_supporder_id
                     from shipment
                     where
                     id = {$_REFDOCS[$x]["doc_tran_id"]}";
            $shp_supporder_id = $CON->select($sql);
            $shp_supporder_id = (int)$shp_supporder_id[0]["shp_supporder_id"];

            $sql = " select t1.*, t2.docto_title,
                     t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                     from tran_docs t1
                     LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                     LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
                     where
                     t1.doc_tran_id    = {$shp_supporder_id} and
                     t1.doc_tran_type  = 'supplier_order'
                     order by t1.id";
            $subrefdocs = $CON->select($sql);
            for($z = 0; $z < count($subrefdocs) && $subrefdocs != false; $z++)
            {
               $sql = " select sord_number
                        from supplier_order
                        where
                        id = {$subrefdocs[$z]["doc_tran_id"]}";
               $sord_number = $CON->select($sql);
               $sord_number = $sord_number[0]["sord_number"];
               ?>
               <tr bgcolor="<?=getRowColor($x)?>">
                  <td class="content_row"><nobr><?=$sord_number?></nobr></td>
                  <td class="content_row"><nobr><?=$subrefdocs[$z]["doc_name"]?>&nbsp;</nobr></td>
                  <td class="content_row"><?=$subrefdocs[$z]["docto_title"]?>&nbsp;</td>
                  <td class="content_row"><?=$subrefdocs[$z]["doc_desc"]?>&nbsp;</td>
                  <td class="content_row"><?=$subrefdocs[$z]["crt_lastname"]?>&nbsp;</td>
                  <td class="content_row"><?=date('d.m.Y',$subrefdocs[$z]["doc_crtdat"])?></td>
                  <td class="content_row" align="center">
                     <?php
                     $docext = strtoupper(substr($subrefdocs[$x]["doc_file"], strrpos($subrefdocs[$x]["doc_file"], ".")+1));
                     if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                        printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/supplier_order/{$subrefdocs[$x]["doc_file"]}')", "image");
                     else
                        printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$subrefdocs[$x]["doc_file"]}&name={$subrefdocs[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
                     ?>
                  </td>
               </tr>
               <?php
            }
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php

      }
   }
   elseif($invc_type == 2)
   {
      $sql = " select part_sord_id
               from invoices_buy_parts
               where
               part_invc_id = {$_REQUEST["id"]} and
               part_sord_id > 0
               order by id";
      $part_sord_ids = $CON->select($sql);
      foreach($part_sord_ids AS $part_sord_idrow)
      {
         $sql = " select t1.*, t2.docto_title,
                  t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
                  from tran_docs t1
                  LEFT OUTER JOIN tran_docs_types t2 ON t1.doc_typeid = t2.id
                  LEFT OUTER JOIN user            t3 ON t1.doc_crtusr = t3.id
                  where
                  t1.doc_tran_id    = {$part_sord_idrow["part_sord_id"]} and
                  t1.doc_tran_type  = 'supplier_order'
                  order by t1.id";
         $refdocs = $CON->select($sql);
         for($x = 0; $x < count($refdocs) && $refdocs != false; $x++)
         {
            $_REFDOCS[] = $refdocs[$x];
         }
      }

      if(count($_REFDOCS) && $_REFDOCS != false)
      {  ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" class="navigateable" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col>
            <col>
            <col>
            <col>
            <col width="85">
            <col width="100">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="7" style="background-color:#00A9A6;color:#FFFFFF;text-shadow:none">Documentos referenciados</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader">Origen</td>
            <td class="content_tbl_subheader">Documento</td>
            <td class="content_tbl_subheader">Tipo</td>
            <td class="content_tbl_subheader">Descripción</td>
            <td class="content_tbl_subheader">Creado por</td>
            <td class="content_tbl_subheader">Creado</td>
            <td class="content_tbl_subheader" align="center">Opciones</td>
         </tr>
         <?php
         for($x = 0; $x < count($_REFDOCS) && $_REFDOCS != false; $x++)
         {
            $sql = " select sord_number
                     from supplier_order
                     where
                     id = {$_REFDOCS[$x]["doc_tran_id"]}";
            $sord_number = $CON->select($sql);
            $sord_number = $sord_number[0]["sord_number"];
            ?>
            <tr bgcolor="<?=getRowColor($x)?>">
               <td class="content_row"><nobr><?=$sord_number?></nobr></td>
               <td class="content_row"><nobr><?=$_REFDOCS[$x]["doc_name"]?>&nbsp;</nobr></td>
               <td class="content_row"><?=$_REFDOCS[$x]["docto_title"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["doc_desc"]?>&nbsp;</td>
               <td class="content_row"><?=$_REFDOCS[$x]["crt_lastname"]?>&nbsp;</td>
               <td class="content_row"><?=date('d.m.Y',$_REFDOCS[$x]["doc_crtdat"])?></td>
               <td class="content_row" align="center">
                  <?php
                  $docext = strtoupper(substr($_REFDOCS[$x]["doc_file"], strrpos($_REFDOCS[$x]["doc_file"], ".")+1));
                  if($docext == "JPG" || $docext == "JPEG" || $docext == "BMP" || $docext == "PNG" || $docext == "GIF" || $docext == "PDF")
                     printButton("Ver", "postnav_save", "javascript: deactivateFormChange()", "window.open('/docs.tran/supplier_order/{$_REFDOCS[$x]["doc_file"]}')", "image");
                  else
                     printButton("Descargar", "postnav_save", "javascript: deactivateFormChange()", "document.all.idxifrsrc.src = '/libs/modules/structure/document_file.php?type=0&hash={$_REFDOCS[$x]["doc_file"]}&name={$_REFDOCS[$x]["doc_name"]}&path=../../../docs.tran/supplier_order/'", "navigation-270-white");
                  ?>
               </td>
            </tr>
            <?php
         }
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
   }
}
?>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<?php
if((int)$headdata["invc_parent_invcid"])
{
   $sql = " select t1.*, t4.supp_company
            from invoices_buy t1
            LEFT OUTER JOIN supplier t4 ON t1.invc_supplier_id  = t4.id
            where
            t1.id = {$headdata["invc_parent_invcid"]}";
   $parentheaddata = $CON->select($sql);
   $parentheaddata = $parentheaddata[0];
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <tr>
      <td class="content_row_clear" align="center">
         <b class="msg_save_ok">
         ASIGNADA COMO GASTO A FACTURA: <?=$parentheaddata["invc_docnumber"]?> [<?=$parentheaddata["invc_number"]?>],
         <?=$parentheaddata["supp_company"]?>
         </b>
      </td>
   </tr>
   </table>
   <?=Nifty_printF()?>
   <br>
   <?php
   $_SESSION["STATS"][$_sesmodulename]["ITEMSHEAD"] = "ASIGNADA COMO GASTO A FACTURA: ".$parentheaddata["invc_docnumber"]." [".$parentheaddata["invc_number"]."], ".$parentheaddata["supp_company"];
   $sthwidth = "100px";
}
else
   $sthwidth = "120px";
$hasItems = false;
$gc = 0;
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $part       = $invcparts[$x];
   $posdata    = getInvoiceBuyPartsItems($CON, $_REQUEST["id"], $part["id"]);
   $rowcount   = count($posdata);

   //----------------------------------------------------------------------------------
   $rowcount = 0;
   if($posdata != false && count($posdata))
      $rowcount = count($posdata);

   if($headdata["invc_status"] == 1)
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
      <col width="1">
      <?php
      if($showStorehouses)
         echo "<col>";
      ?>
      <col width="1">
      <col width="1">
      <col width="1">
      <col width="1">
      <?php
      if((int)$headdata["invc_parent_invcid"])
         echo "<col width='60'>";
      ?>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="11">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col width="50">
         </colgroup>
         <tr>
            <td class="content_tbl_header" style="border:0px;padding:0px">
               <?php
               if($part["part_shp_id"] > 0)
               {  ?>
                  Número int: <?=$part["shp_num"]?> |
                  Guia de despacho: <?=$part["shp_supplier_docnum"]?> |
                  <?php
                  $_SESSION["STATS"][$_sesmodulename]["ITEMS1"][$x]["val1"] = "Número int: {$part["shp_num"]} | Guia de despacho: {$part["shp_supplier_docnum"]} |";
               }
               if($part["part_sord_id"] > 0)
               {  ?>
                  Orden de compra: <?=$part["sord_number"]?>
                  <?php
                  $_SESSION["STATS"][$_sesmodulename]["ITEMS1"][$x]["val1"] = "Orden de compra: {$part["sord_number"]}";
               }
               if($headdata["invc_type"] == 3 || $headdata["invc_type"] == 4)
               {
                  echo "Artículos";
                  $_SESSION["STATS"][$_sesmodulename]["ITEMS1"][$x]["val1"] = "Artículos";
               }
               ?>
            </td>
            <td class="content_tbl_header" style="border:0px;padding:0px" align="right">
               <?php
               if($headdata["invc_status"] == 1 && $headdata["invc_type"] != 3 && $headdata["invc_type"] != 4)
               {  ?>
                  <img src="./images/menu/icons/cross-circle-frame.png" style="cursor:pointer"
                  onclick="if(askDel('')) { document.form_shppos.delinvcpartid.value='<?=$part["id"]?>';submitForm(document.form_shppos); }">
                  <?php
               }
               ?>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" valign="top">Busqueda</td>
      <td class="content_tbl_subheader" valign="top">Act.</td>
      <td class="content_tbl_subheader" valign="top">Artículo</td>
      <td class="content_tbl_subheader" valign="top">Cod.Prov</td>
      <td class="content_tbl_subheader" valign="top" align="center">Unidad</td>
      <td class="content_tbl_subheader" valign="top" align="right">Cantidad</td>
      <?php
      if($showStorehouses)
      {  ?>
         <td class="content_tbl_subheader" valign="top">Bodega</td>
         <?php
      }
      ?>
      <td class="content_tbl_subheader" valign="top" align="right">Precio/N</td>
      <td class="content_tbl_subheader" valign="top" align="right">Desc.</td>
      <td class="content_tbl_subheader" valign="top" align="right">IVA %</td>
      <td class="content_tbl_subheader" valign="top" align="right">Valor Total</td>
      <?php
      if((int)$headdata["invc_parent_invcid"])
      {  ?>
         <td class="content_tbl_subheader" valign="top" align="center">Excluir</td>
         <?php
      }
      ?>
   </tr>
   <?php
   for($y = 0; $y < $rowcount; $y++)
   {
      $showmanual = false;
      if((int)$posdata[$y]["item_id"] && $posdata[$y]["item_type"] == "manual")
         $showmanual = true;

      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "xf_search_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_id_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_amount_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_stid_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_costprice_netto_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_discount_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_costprice_taxes_perc_{$part["id"]}_{$y}";
      ?>
      <tr bgcolor="<?=getRowColor($y)?>">
         <td class="content_row">
            <?if($showmanual) echo "&nbsp;"?>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$part["id"]?>_<?=$y?>" <?php if($showmanual) echo "style='display:none'" ?>>
            <tr>
               <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
               <td>
                  <input type="text" class="text" style="width:60px" name="xf_search_<?=$part["id"]?>_<?=$y?>" id="xf_search_<?=$part["id"]?>_<?=$y?>"
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
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/invoices_buy/searchitem.php?rowcount=<?=$part["id"]?>_<?=$y?>&id=<?=$_REQUEST["id"]?><?=$urlparam?>&search=' +this.value} this.value='';"
                     onkeyup="detectEvent(event, '<?=$part["id"]?>_<?=$y?>', '<?=$_REQUEST["id"]?>', '<?=substr($urlparam, -1)?>')"
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
               <input type="hidden" name="existing_id_<?=$part["id"]?>_<?=$y?>" value="<?=$posdata[$y]["item_id"]?>">
               <input type="hidden" name="existing_pos_<?=$part["id"]?>_<?=$y?>" value="<?=$posdata[$y]["item_pos"]?>">
               <input type="button" class="buttonred" value="x" style="width:20px"
               onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)" <?=$dabl?>
               onclick="if(askDel('')) { document.form_shppos.item_amount_<?=$part["id"]?>_<?=$y?>.value='0'; submitForm(document.form_shppos); }">
               <?php
            }
            else
            {  ?>
               <img src="/images/menu/icons/notebook--plus.png" border="0" style="cursor:pointer"
               onclick="showOrderPartPosManualEdit('<?=$part["id"]?>_<?=$y?>')">
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
                  elseif($itemselw == "340px")
                     $ovritemselw = "320px";
               }
            }
            ?>
            <select class="text" style="width:<?=$ovritemselw?>;<?php if($showmanual) echo "display:none" ?>"
            name="item_id_<?=$part["id"]?>_<?=$y?>" id="item_id_<?=$part["id"]?>_<?=$y?>"
            onmousedown="markfield(this,0)"
            onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
            onfocus="<?php if(!(int)$posdata[$y]["item_id"]) echo "addSelStyle(this);" ?>"
            onchange="setItemInfos('<?=$part["id"]?>_<?=$y?>', this.value);<?php
            if($showStorehouses)
               echo "updateItemStorehousesArr('{$part["id"]}_{$y}', this.value);";
            ?>">
               <?php
               if((int)$posdata[$y]["item_id"])
               {
                  $desc       = trim(addslashes($posdata[$y]["item_title"]));
                  ?>
                  <option value="<?=$posdata[$y]["item_id"]?>#<?=$posdata[$y]["item_type"]?>#0#0#0#<?=(int)$posdata[$y]["item_charges_act"]?>"><?=$posdata[$y]["item_number_prod"]?> - <?=$desc?></option>
                  <?php
               }
               ?>
            </select>
            <textarea class="text" name="item_desc_<?=$part["id"]?>_<?=$y?>" id="item_desc_<?=$part["id"]?>_<?=$y?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            style="width:<?=$ovritemselw?>;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$y]["item_desc"]?></textarea>
            <input type="hidden" name="manual_pos_<?=$part["id"]?>_<?=$y?>" id="manual_pos_<?=$part["id"]?>_<?=$y?>"
            value="<?php if($showmanual) echo "1"; else echo "0" ?>">
         </td>
         <td class="content_row" valign="top" align="center"><?=$posdata[$y]["item_code"]?>&nbsp;</td>
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
            name="item_amount_<?=$part["id"]?>_<?=$y?>" id="item_amount_<?=$part["id"]?>_<?=$y?>" <?=$rdlo?>
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_amount"],2)?>">
         </td>
         <?php
         if($showStorehouses)
         {  ?>
            <td class="content_row" valign="top">
               <?php
               if($showmanual)
               {
                  $_FIELDIGNORES["item_stid_{$part["id"]}_{$y}"] = 1;
                  echo "&nbsp;";
               }
               else
               {  ?>
                  <select class="text" style="width:<?=$sthwidth?>;<?if((int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>"
                  name="item_stid_<?=$part["id"]?>_<?=$y?>" id="item_stid_<?=$part["id"]?>_<?=$y?>"
                  onmousedown="markfield(this,0)"
                  onblur="markfield(this,1);removeSelStyle(this);"
                  onfocus="addSelStyle(this);">
                     <?php
                     if((int)$posdata[$y]["item_id"])
                     {
                        if($posdata[$y]["item_type"] == "item")
                        {
                           $checkitemid = $posdata[$y]["item_id"];
                           if((int)$posdata[$y]["item_subitem_id"])
                              $checkitemid = $posdata[$y]["item_subitem_id"];

                           $itemsts = getItemStorehouses($CON, $headdata["invc_shop_id"], $checkitemid, $posdata[$y]["item_type"]);
                        }
                        else
                        {
                           $itemlistpos = getItemListContent($CON, $posdata[$y]["item_id"]);
                           $itemsts     = getItemStorehouses($CON, $headdata["invc_shop_id"], $itemlistpos[0]["item_id"], "item");
                        }
                        if(count($itemsts))
                        {
                           foreach(array_keys($itemsts) AS $itemstid)
                           {
                              if($rdlo == "" || ($rdlo != "" && $posdata[$y]["item_st_id"] == $itemstid))
                              {
                                 $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $itemstid, $checkitemid, $posdata[$y]["item_type"], true);
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
               <div style="<?if(!(int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$part["id"]?>_<?=$y?>">
               <?php
               $btnicon = "arrow";
               $btnname = "Lotes";
               $btnclas = "postnav";

               if(posHasItemChargeData($CON, $_REQUEST["id"], "invoicesbuy", $y, $part["id"]) > 0)
               {
                  $btnicon = "tick-circle-frame";
                  $btnclas = "postnav_save";

                  $charge_amount = posItemChargeDataAmount($CON, $_REQUEST["id"], "invoicesbuy", $y, $part["id"]);
                  if($charge_amount["tran_amount"] != $posdata[$y]["item_amount"])
                  {
                     $btnicon = "cross-circle-frame";
                     $btnclas = "postnav_del";
                     $_BLOCKFIN_CHARGE = true;
                  }
                  if($charge_amount["tran_amount_used"] > 0.00)
                     $_BLOCKOPEN_CHARGE = true;
               }
               elseif((int)$posdata[$y]["item_charges_act"])
                  $_BLOCKFIN_CHARGE = true;
               
               printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.php?trantype=invoicesbuy&tranid={$_REQUEST["id"]}&tranpos={$part["id"]}_{$y}&itemdata=' +escape($('#item_id_{$part["id"]}_{$y}').val()), 'iframe', 650, 400, 'auto')", "", $btnicon)
               ?>
               <textarea id="item_charges_data_<?=$part["id"]?>_<?=$y?>" name="item_charges_data_<?=$part["id"]?>_<?=$y?>" style="display:none"></textarea>
               </div>
            </td>
            <?php
         }
         else
            $_FIELDIGNORES["item_stid_{$part["id"]}_{$y}"] = 1;
         ?>
         <td class="content_row" align="right" valign="top">
            <nobr>
            <?=$moneystr?>
            <input type="text" class="text" style="width:<?=$inputw?>;text-align:right" <?=$rdlo?> autocomplete="off"
            name="item_costprice_netto_<?=$part["id"]?>_<?=$y?>" id="item_costprice_netto_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_costprice_netto"], $numberlim)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </nobr>
         </td>
         <td class="content_row" align="right" valign="top">
            <input type="text" class="text" style="width:30px;text-align:right" <?=$rdlo?> autocomplete="off"
            name="item_discount_<?=$part["id"]?>_<?=$y?>" id="item_discount_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_discount"],8)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="right" valign="top">
            <input type="text" class="text" style="width:36px;text-align:right" <?=$rdlo?> autocomplete="off"
            name="item_costprice_taxes_perc_<?=$part["id"]?>_<?=$y?>" id="item_costprice_taxes_perc_<?=$part["id"]?>_<?=$y?>"
            value="<?php
            if((int)$posdata[$y]["item_id"])
            {
               echo printPrice($posdata[$y]["item_costprice_taxes_perc"],2);
               if((int)$posdata[$y]["item_amount"])
                  $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val8"] = printPrice($posdata[$x]["item_costprice_taxes_perc"], 2);
            }
            elseif((int)$headdata["invc_taxes"])
            {
               echo printPrice($_SESSION["_CONF"]["conf_taxes"],2);
               if((int)$posdata[$y]["item_amount"])
                  $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val8"] = printPrice($_SESSION["_CONF"]["conf_taxes"], 2);
            }
            else
            {
               echo "0";
               if((int)$posdata[$y]["item_amount"])
                  $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val8"] = "0";
            }
            ?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" align="right" valign="top">
            <nobr>
            <?=$moneystr?>
            <input type="text" class="text" readonly
            style="width:<?=$inputw2?>;text-align:right;background-color:<?if((int)$posdata[$y]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            name="item_costprice_netto_dsc_<?=$part["id"]?>_<?=$y?>" id="item_costprice_netto_dsc_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_costprice_netto_dsc"], $numberlim)?>">
            </nobr>
         </td>
         <?php
         if((int)$headdata["invc_parent_invcid"])
         {  ?>
            <td class="content_row" align="center" valign="top">
               <input type="checkbox" name="item_no_costcalc_<?=$part["id"]?>_<?=$y?>" value="1" title="Excluir de la valorizacion"
               <?php if((int)$posdata[$y]["item_no_costcalc"]) echo "checked"?>>
            </td>
            <?php
         }
         ?>
      </tr>
      <?php
      if((int)$posdata[$y]["item_amount"])
      {
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val1"] = $desc;
         if($showmanual)
            $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val1"] = $posdata[$y]["item_desc"];
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val2"] = $posdata[$y]["item_code"];
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val3"] = getItemUnitDesc($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val4"] = printPrice($posdata[$y]["item_amount"],2);
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val5"] = $posdata[$y]["item_number_prod"];
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val6"] = $moneystr." ".printPrice($posdata[$y]["item_costprice_netto"], $numberlim);
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val7"] = printPrice($posdata[$y]["item_discount"], 8);
         $_SESSION["STATS"][$_sesmodulename]["ITEMS2"][$x][$y]["val9"] = $moneystr." ".printPrice($posdata[$y]["item_costprice_netto_dsc"], $numberlim);
      }
      if($y == 0 && !(int)$posdata[$y]["item_id"])
         $_SESSION["JSEXEC"] .= "document.form_shppos.xf_search_{$part["id"]}_{$y}.focus();";

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
   <?=Nifty_printH("box1", "980")?>
   <table border="0" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="120">
      <col>
      <col>
      <col>
      <col>
      <col>
      <col width="100">
      <col width="130">
   </colgroup>
   <tr>
      <td class="content_tbl_subheader" align="center">Sub Total</td>
      <td class="content_tbl_subheader" align="center">Descuento 1</td>
      <td class="content_tbl_subheader" align="center">Descuento 2</td>
      <td class="content_tbl_subheader" align="center">Descuento 3</td>
      <td class="content_tbl_subheader" align="center">Descuento 4</td>
      <td class="content_tbl_subheader" align="center">Descuento 5</td>
      <td class="content_tbl_subheader">&nbsp;</td>
      <td class="content_tbl_subheader">&nbsp;</td>
   </tr>
   <tr>
      <td class="content_rowl" align="center"><?=printPrice($headdata["invc_item_netto_total"], $numberlim)?></td>
      <?php
      $cc = 0;
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val1"] = printPrice($headdata["invc_item_netto_total"], $numberlim);
      for($x = 1; $x <= 5; $x++)
      {  ?>
         <td class="content_rowl" align="center">
            <input type="text" class="text" <?=$rdlo?>
            style="width:55px;text-align:center;background-color:<?if($headdata["invc_discount{$x}"] > 0.00) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            name="invc_discount<?=$x?>" id="invc_discount<?=$x?>"
            value="<?php if($headdata["invc_discount{$x}"] != 0.00) echo printPrice($headdata["invc_discount{$x}"],2)?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
            <select class="text" style="width:40px" name="invc_discount_type<?=$x?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dabl?>>
               <option value="0" <?php if(!(int)$headdata["invc_discount_type{$x}"]) echo "selected" ?>>%</option>
               <option value="1" <?php if( (int)$headdata["invc_discount_type{$x}"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
            </select>
         </td>
         <?php
         $tmpx = $x+1;
         if(!(int)$headdata["invc_discount_type{$x}"])
            $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val{$tmpx}"] = printPrice($headdata["invc_discount{$x}"],2)." %";
         else
            $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val{$tmpx}"] = printPrice($headdata["invc_discount{$x}"],2)." ".$_SESSION["_CONF"]["conf_currency"];
      }
      ?>
      <td class="content_rowl" align="right"><b>MONTO NETO</b></td>
      <td class="content_rowl" align="right">
         <input type="text" class="text" style="width:120px;text-align:right" readonly
         value="<?=printPrice($headdata["invc_total_netto_dsc"],$numberlim)?>">
         <?php
         $cc++;
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val7"] = "<b>MONTO NETO</b>";
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val8"] = printPrice($headdata["invc_total_netto_dsc"],$numberlim);
         ?>
      </td>
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
      <td class="content_row" align="right"><b class="msg_save_err">DESCUENTO</b></td>
      <td class="content_row" align="right">
         <input type="text" class="text" <?=$rdlo?>
         style="width:75px;text-align:center;background-color:<?if($headdata["invc_discount{$x}"] > 0.00) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         name="invc_discount6" id="invc_discount6"
         value="<?php if($headdata["invc_discount6"] != 0.00) echo printPrice($headdata["invc_discount6"],2)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <select class="text" style="width:40px" name="invc_discount_type6"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dabl?>>
            <option value="0" <?php if(!(int)$headdata["invc_discount_type6"]) echo "selected" ?>>%</option>
            <option value="1" <?php if( (int)$headdata["invc_discount_type6"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
         </select>
      </td>
      <?php
      $cc++;
      $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val7"] = "<b>DESCUENTO</b>";
      if(!(int)$headdata["invc_discount_type6"])
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val8"] = printPrice($headdata["invc_discount6"],2)." %";
      else
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val8"] = printPrice($headdata["invc_discount6"],2)." ".$_SESSION["_CONF"]["conf_currency"];
      ?>
   </tr>
   <tr>
      <td class="content_row" align="right">
         <?php
         if((float)$headdata["invc_total_taxes_add"] == 0.00 && !(int)$headdata["invc_importation"] && $headdata["invc_status"] < 2)
            printButton("Impuestos manuales &gt;&gt;", "postnav", "javascript: deactivateFormChange()",
                        "document.getElementById('invc_total_taxes_add').style.display='';document.getElementById('invc_total_taxes_add').focus()",
                        "currency", 180);
         else
            echo "Impuestos manuales&nbsp;";
         ?>
      </td>
      <td class="content_row" align="right">
         <input type="text" class="text" style="width:110px;text-align:right;<?php if(!(float)$headdata["invc_total_taxes_add"]) echo "display:none"?>"
         name="invc_total_taxes_add" id="invc_total_taxes_add" <?=$rdlo?>
         value="<?php if((float)$headdata["invc_total_taxes_add"] > 0.00) echo printPrice($headdata["invc_total_taxes_add"])?>">&nbsp;
         <b>IVA</b>
         <?php
         $cc++;
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val7"] = "<b>IMP. MAN.</b>";
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val8"] = printPrice($headdata["invc_total_taxes_add"],2);
         ?>
      </td>
      <td class="content_row" align="right">
         <input type="text" class="text" style="width:120px;text-align:right" readonly
         value="<?php
         $cc++;
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val7"] = "<b>IVA</b>";
         if((float)$headdata["invc_total_taxes_add"] > 0.00)
         {
            echo printPrice($headdata["invc_total_taxes_orig"]);
            $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val8"] = printPrice($headdata["invc_total_taxes_orig"]);
         }
         else
         {
            echo printPrice($headdata["invc_total_taxes"]);
            $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val8"] = printPrice($headdata["invc_total_taxes"]);
         }
         ?>">
      </td>
   </tr>
   <tr>
      <td class="content_row">&nbsp;</td>
      <td class="content_row" align="right"><b class="msg_save_ok">TOTAL</b></td>
      <td class="content_row" align="right">
         <input type="text" class="text" style="width:120px;text-align:right;background-color:#E1FFD6" readonly
         value="<?=printPrice($headdata["invc_total_brutto"],$numberlim)?>">
         <?php
         $cc++;
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val7"] = "<b>TOTAL</b>";
         $_SESSION["STATS"][$_sesmodulename]["TOTAL"][$cc]["val8"] = printPrice($headdata["invc_total_brutto"],$numberlim);
         ?>
      </td>
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
   if($isremote && $headdata["invc_status"] > 1 && !(int)$headdata["invc_remote_synced"])
   {  ?>
      <td width="130" style="padding-right:5px;color:red" class="content_row_clear">
         Esperando sucursal.
      </td>
      <?php
      $_BLOCKSYNC = true;
   }
   if($headdata["invc_status"] == 2 && !$_BLOCKOPEN_CHARGE && !$_BLOCKSYNC)
   {  ?>
      <td width="130" style="padding-right:5px">
         <?php
         if($hasdocopeperm)
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.invc_status.value='1';submitForm(document.form_shppos);}", "arrow-circle-045-left");
         else
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos&sStatus=1', 'iframe', 450, 160, 'no')", "arrow-circle-045-left");
         ?>
      </td>
      <?php
   }
   if($headdata["invc_status"] == 1)
   {
      if($isremote && (int)$headdata["invc_remote_synced_wait"] && !(int)$headdata["invc_remote_synced"])
      {  ?>
         <td width="130" style="padding-right:5px;color:red" class="content_row_clear">
            Esperando sucursal.
         </td>
         <?php
      }
      else
      {
         $sql = " update invoices_buy
                  set
                  invc_remote_synced_wait = 0
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
         ?>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=delinvc&id={$_REQUEST["id"]}')", "cross-circle-frame");
            ?>
         </td>
         <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
            ?>
         </td>
         <?php
         if(!$_BLOCKFIN_CHARGE && $hasItems && (($headdata["invc_type"] == 4 && (int)$headdata["invc_parent_invcid"]) || $headdata["invc_type"] != 4))
         {  ?>
            <td align="right" width="130" id="idx_fin_button">
               <?php
               printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.invc_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
               ?>
            </td>
            <?php
         }
      }
   }
   else
   {  ?>
      <td align="right" width="140" style="padding-left:5px">
         <?php
         printButton("Imprimir", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=editinvoice&subexec=edit&id={$_REQUEST["id"]}&printpdf=1", "", "document-pdf");
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
  $pdffile = doc_createInvoiceBuyPDF($CON);

if($pdffile != "")
{
   $doctitle = "Facturacion-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
