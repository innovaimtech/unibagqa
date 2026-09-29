<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2019 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

if($_REQUEST["reinitDocs"])
{
   $sql = " update invoices_sell_bol
            set
            invc_sgntr_doc1 = '',
            invc_sgntr_doc2 = ''
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}
$hasprcsellperm = true;
$_HIDETAXES = true;

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xinvoicessell"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xinvoicessell"]["fullcust"] = "";

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();

   $_REQUEST["cust_id_0"]     = (int)$_REQUEST["cust_id_0"];
   $_REQUEST["company_id"]    = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]       = (int)$_REQUEST["shop_id"];
   $_REQUEST["invc_taxes"]    = (int)$_REQUEST["invc_taxes"];
   $_REQUEST["invc_type"]     = (int)$_REQUEST["invc_type"];
   $_REQUEST["dlv_order_id"]  = (int)$_REQUEST["dlv_order_id"];
   $_REQUEST["dlv_id"]        = (int)$_REQUEST["dlv_id"];
   $_REQUEST["invc_dlv_addtxt"]        = (int)$_REQUEST["invc_dlv_addtxt"];
   $_REQUEST["invc_isinvcbrutto"]      = (int)$_REQUEST["invc_isinvcbrutto"];
   $_REQUEST["invc_order_otherclient"] = (int)$_REQUEST["invc_order_otherclient"];
   $_REQUEST["invc_order_noitemload"]  = (int)$_REQUEST["invc_order_noitemload"];
   $_REQUEST["cust_id_1"]              = (int)$_REQUEST["cust_id_1"];

   //----------------------------------------------------------------------------------
   $invc_number         = "";
   $invc_date           = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
   $invc_delivery_date  = $invc_date;
   $invc_dlv_docnum     = $invc_number;
   $invc_stockchange    = 1;

   //----------------------------------------------------------------------------------
   $sql = " select t1.cust_paymentid, t1.cust_sellerid, t1.cust_transportid, t2.pay_days
            from customer t1
            LEFT OUTER JOIN payments t2 ON t1.cust_paymentid = t2.id
            where
            t1.id = {$_REQUEST["cust_id_0"]}";
   $custdata = $CON->select($sql);
   $custdata = $custdata[0];

   $invc_userid_seller  = (int)$custdata["cust_sellerid"];
   $invc_userid_cashing = (int)$custdata["cust_sellerid"];
   $invc_paymentid      = (int)$custdata["cust_paymentid"];
   $invc_paymentdays    = (int)$custdata["pay_days"];
   $invc_transportid    = (int)$custdata["cust_transportid"];
   $invc_estpay_date    = 0;

   //----------------------------------------------------------------------------------
   $_SELLRET = sellerHasReplacement($CON, $invc_userid_seller, time());
   $seller_repluserid   = $_SELLRET["SELLER"];
   $cashing_repluserid  = $_SELLRET["CASHER"];

   //----------------------------------------------------------------------------------
   if((int)$seller_repluserid)
      $invc_userid_seller = $seller_repluserid;
   if((int)$cashing_repluserid)
      $invc_userid_cashing = $cashing_repluserid;

   if($invc_paymentid)
   {
      $tmp_estpay       = time() + ($invc_paymentdays * 86400);
      $invc_estpay_date = mktime(15, 0, 0, date('m', $tmp_estpay), date('d', $tmp_estpay), date('Y', $tmp_estpay));
   }

   //----------------------------------------------------------------------------------
   $invccfg = getCompanyInvoiceConfig($CON, $_REQUEST["company_id"]);
   if((int)$invccfg["company_invc_mode"])
      $invc_docnumber = $invc_number;
   else
      $invc_docnumber = "PROFORMA {$invc_number}";

   //----------------------------------------------------------------------------------
   $sql = " insert into invoices_sell_bol
            (invc_number, invc_cust_id, invc_company_id, invc_shop_id, invc_date, invc_receipt_date,
             invc_taxes, invc_type, invc_stockchange, invc_userid_seller, invc_userid_cashing,
             invc_paymentid, invc_transportid, invc_delivery_date, invc_dlv_docnum, invc_estpay_date,
             invc_docnumber, invc_crtdat, invc_crtusr, invc_isinvcbrutto)
            VALUES
            ('{$invc_number}', {$_REQUEST["cust_id_0"]}, {$_REQUEST["company_id"]},
              {$_REQUEST["shop_id"]}, {$invc_date}, {$invc_date}, {$_REQUEST["invc_taxes"]}, {$_REQUEST["invc_type"]},
              {$invc_stockchange}, {$invc_userid_seller}, {$invc_userid_cashing}, {$invc_paymentid},
              {$invc_transportid}, {$invc_delivery_date}, '{$invc_dlv_docnum}', {$invc_estpay_date},
              '{$invc_docnumber}', {$currtme}, {$_SESSION["user_id"]}, 
              {$_REQUEST["invc_isinvcbrutto"]})";
   $res = $CON->no_result($sql);
   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_sell_bol
               where
               invc_crtusr = {$_SESSION["user_id"]}";
      $invoice = $CON->select($sql);
      $_REQUEST["id"]   = $invoice[0]["thisid"];

      $sql = " insert into invoices_sell_bol_parts
               (part_invc_id, part_crtdat, part_crtusr, part_dlv_id, part_req_id)
               VALUES
               ({$_REQUEST["id"]}, {$currtme}, {$_SESSION["user_id"]}, 0, 0)";
      $CON->no_result($sql);

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=984&exec=edit&id=<?=$_REQUEST["id"]?>'
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
   $sql = " select *
            from invoices_sell_bol
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
   //----------------------------------------------------------------------------------
   $_REQUEST["invc_desc"]              = trim(addslashes($_REQUEST["invc_desc"]));
   $_REQUEST["invc_desc_intern"]       = trim(addslashes($_REQUEST["invc_desc_intern"]));
   $_REQUEST["invc_date"]              = trim(addslashes($_REQUEST["invc_date"]));
   $_REQUEST["invc_docnumber"]         = trim(addslashes($_REQUEST["invc_docnumber"]));
   $_REQUEST["invc_dlv_docnum"]        = trim(addslashes($_REQUEST["invc_dlv_docnum"]));
   $_REQUEST["invc_paymentid"]         = (int)$_REQUEST["invc_paymentid"];
   $_REQUEST["invc_payed"]             = (int)$_REQUEST["invc_payed"];
   $_REQUEST["invc_stockchange"]       = (int)$_REQUEST["invc_stockchange"];
   $_REQUEST["invc_userid_seller"]     = (int)$_REQUEST["invc_userid_seller"];
   $_REQUEST["invc_userid_cashing"]    = (int)$_REQUEST["invc_userid_cashing"];
   $_REQUEST["invc_transportid"]       = (int)$_REQUEST["invc_transportid"];
   $_REQUEST["invc_cust_delivid"]      = (int)$_REQUEST["invc_cust_delivid"];
   $_REQUEST["invc_bultos"]            = (int)$_REQUEST["invc_bultos"];
   $_REQUEST["invc_oc_number"]         = trim(addslashes($_REQUEST["invc_oc_number"]));
   $_REQUEST["invc_oc_dat"]            = trim(addslashes($_REQUEST["invc_oc_dat"]));
   $_REQUEST["invc_oc_dat"]            = explode(".", $_REQUEST["invc_oc_dat"]);
   $_REQUEST["invc_oc_dat"]            = (int)mktime(date('H'), date('i'), date('s'), $_REQUEST["invc_oc_dat"][1], $_REQUEST["invc_oc_dat"][0], $_REQUEST["invc_oc_dat"][2]);
   $_REQUEST["invc_discount_perc"]      = getPrice($_REQUEST["invc_discount_perc"],2);
   $_REQUEST["invc_discount_amt"]       = getPrice($_REQUEST["invc_discount_amt"]);

   $_REQUEST["invc_hes_number"]        = trim(addslashes($_REQUEST["invc_hes_number"]));
   $_REQUEST["invc_hes_date"]          = trim(addslashes($_REQUEST["invc_hes_date"]));
   $_REQUEST["invc_hes_date"]          = explode(".", $_REQUEST["invc_hes_date"]);
   $_REQUEST["invc_hes_date"]          = (int)mktime(15, 0, 0, $_REQUEST["invc_hes_date"][1], $_REQUEST["invc_hes_date"][0], $_REQUEST["invc_hes_date"][2]);

   if((int)$_REQUEST["invc_paymentid"] != (int)$headdata["invc_paymentid"])
      $itemfullupdate = true;
   else
      $itemfullupdate = false;

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell_bol
            where
            id = {$_REQUEST["id"]}";
   $invoicetemp      = $CON->select($sql);
   $invoicetaxes     = $invoicetemp[0]["invc_taxes"];
   $invoicecustomer  = $invoicetemp[0]["invc_cust_id"];
   $invoicedate      = $invoicetemp[0]["invc_date"];

   //----------------------------------------------------------------------------------
   $_REQUEST["invc_receipt_date"] = $_REQUEST["invc_date"];
   $_REQUEST["invc_date"] = explode(".", $_REQUEST["invc_date"]);
   $_REQUEST["invc_date"] = (int)mktime(15, 0, 0, $_REQUEST["invc_date"][1], $_REQUEST["invc_date"][0], $_REQUEST["invc_date"][2]);
   $_REQUEST["invc_receipt_date"] = explode(".", $_REQUEST["invc_receipt_date"]);
   $_REQUEST["invc_receipt_date"] = (int)mktime(15, 0, 0, $_REQUEST["invc_receipt_date"][1], $_REQUEST["invc_receipt_date"][0], $_REQUEST["invc_receipt_date"][2]);
   if(!$_REQUEST["invc_receipt_date"])
      $_REQUEST["invc_receipt_date"] = $_REQUEST["invc_date"];
      
   //----------------------------------------------------------------------------------
   $_REQUEST["invc_delivery_date"] = explode(".", $_REQUEST["invc_delivery_date"]);
   $_REQUEST["invc_delivery_date"] = (int)mktime(15, 0, 0, $_REQUEST["invc_delivery_date"][1], $_REQUEST["invc_delivery_date"][0], $_REQUEST["invc_delivery_date"][2]);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell_bol
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

      //----------------------------------------------------------------------------------
   $revert  = false;
   $final   = false;
   if($headdata["invc_status"] == 1 && $_REQUEST["invc_status"] == 2)
   {
      $_REQUEST["invc_docnumber"] = "PENDIENTE_SII";
      $_REQUEST["invc_status"]    = 1;  
      $final = true;
   }
   elseif($headdata["invc_status"] == 2 && $_REQUEST["invc_status"] == 1)
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
   $sql = " update invoices_sell_bol
            set
            invc_status          = {$_REQUEST["invc_status"]},
            invc_desc            = '{$_REQUEST["invc_desc"]}',
            invc_desc_intern     = '{$_REQUEST["invc_desc_intern"]}',
            invc_docnumber       = '{$_REQUEST["invc_docnumber"]}',
            invc_date            = {$_REQUEST["invc_date"]},
            invc_paymentid       = {$_REQUEST["invc_paymentid"]},
            invc_payed           = {$_REQUEST["invc_payed"]},
            invc_stockchange     = {$_REQUEST["invc_stockchange"]},
            invc_estpay_date     = {$_REQUEST["invc_estpay_date"]},
            invc_receipt_date    = {$_REQUEST["invc_receipt_date"]},
            invc_userid_seller   = {$_REQUEST["invc_userid_seller"]},
            invc_userid_cashing  = {$_REQUEST["invc_userid_cashing"]},
            invc_transportid     = {$_REQUEST["invc_transportid"]},
            invc_cust_delivid    = {$_REQUEST["invc_cust_delivid"]},
            invc_delivery_date   = {$_REQUEST["invc_delivery_date"]},
            invc_dlv_docnum      = '{$_REQUEST["invc_dlv_docnum"]}',
            invc_oc_number       = '{$_REQUEST["invc_oc_number"]}',
            invc_oc_dat          = {$_REQUEST["invc_oc_dat"]},
            invc_discount_perc   = {$_REQUEST["invc_discount_perc"]},
            invc_discount_amt    = {$_REQUEST["invc_discount_amt"]},
            invc_updusr          = {$_SESSION["user_id"]},
            invc_upddat          = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

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

         $_REQUEST["item_stid_{$idx}"]             = (int)$_REQUEST["item_stid_{$idx}"];
         $_REQUEST["item_amount_{$idx}"]           = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $_REQUEST["item_reserva_amount_{$idx}"]   = getPrice($_REQUEST["item_reserva_amount_{$idx}"],2);
         if($_REQUEST["item_reserva_amount_{$idx}"] > $_REQUEST["item_amount_{$idx}"])
            $_REQUEST["item_reserva_amount_{$idx}"] = 0;

         $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
         $sql_id           = (int)$itemvalues[0];
         $sql_type         = $itemvalues[1];
         $sql_charges_act  = (int)$itemvalues[5];

         if(!$_REQUEST["invc_stockchange"])
            $_REQUEST["item_stid_{$idx}"] = 0;

         if($idxpartid != "id")
         {
            if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
            {
               //----------------------------------------------------------------------------------
               if(!(int)$invoiceimport)
               {
                  if($invoicetaxes)
                  {
                     //----------------------------------------------------------------------------------
                     $sql_taxesperc = getPrice($_REQUEST["item_sellprice_taxes_perc_{$idx}"], 2);
                     
                     if(!(int)$headdata["invc_isinvcbrutto"])
                     {
                        $sql_sellnetto = getPrice($_REQUEST["item_sellprice_netto_{$idx}"]);
                        $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto / 100 * $sql_taxesperc);
                        $sql_sellprice = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto + $sql_taxes);
                     }
                     else
                     {
                        $sql_sellprice = getPrice($_REQUEST["item_sellprice_brutto_{$idx}"]);
                        $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellprice / (100 + $sql_taxesperc) * $sql_taxesperc);
                        $sql_sellnetto = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellprice - $sql_taxes);
                     }
                  }
                  else
                  {
                     $sql_sellnetto = getPrice($_REQUEST["item_sellprice_netto_{$idx}"]);
                     $sql_taxesperc = 0;
                     $sql_taxes     = 0;
                     $sql_sellprice = $sql_sellnetto;
                  }
               }
               else
               {
                  $sql_sellnetto = getPrice($_REQUEST["item_sellprice_netto_{$idx}"], 4);
                  $sql_taxesperc = 0;
                  $sql_taxes     = 0;
                  $sql_sellprice = $sql_sellnetto;
               }

               //----------------------------------------------------------------------------------
               if((int)$_REQUEST["manual_pos_{$idx}"])
                  $_REQUEST["item_desc_{$idx}"] = trim(addslashes($_REQUEST["item_desc_{$idx}"]));
               else
                  $_REQUEST["item_desc_{$idx}"] = "";

               //----------------------------------------------------------------------------------
               if($existing_id)
               {
                  $sql = " update invoices_sell_bol_parts_items
                           set
                           item_amount                = {$_REQUEST["item_amount_{$idx}"]},
                           item_reserva_amount        = {$_REQUEST["item_reserva_amount_{$idx}"]},
                           item_sellprice_brutto      = {$sql_sellprice},
                           item_sellprice_taxes_perc  = {$sql_taxesperc},
                           item_sellprice_netto       = {$sql_sellnetto},
                           item_sellprice_taxes       = {$sql_taxes},
                           item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                           item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}',
                           item_pos                   = {$poscounter}
                           where
                           invc_id  = {$_REQUEST["id"]} and
                           part_id  = {$idxpartid} and
                           item_id  = {$existing_id} and
                           item_pos = {$existing_pos}";
                  $CON->no_result($sql);

                  recalcOrderItem($CON, $_REQUEST["id"], $existing_id, $poscounter, $itemfullupdate, "INVOICEBOL", $idxpartid);

                  //----------------------------------------------------------------------------------
                  $_TABLENAME    = "invoices_sell_bol_parts_items";
                  $_TABLENAMEHD  = "invoices_sell_bol";
                  $_COLPREFIX    = "invc";
                  $_AMOUNTFIELD  = "item_amount";

                  //----------------------------------------------------------------------------------
                  if(!$itemfullupdate)
                  {
                     $_REQUEST["item_pcat_dsc_act_{$idx}"]     = (int)$_REQUEST["item_pcat_dsc_act_{$idx}"];
                     $_REQUEST["item_vol_act_{$idx}"]          = (int)$_REQUEST["item_vol_act_{$idx}"];
                     $_REQUEST["item_value_act_{$idx}"]        = (int)$_REQUEST["item_value_act_{$idx}"];

                     $_REQUEST["item_discount_{$idx}"]         = getPrice($_REQUEST["item_discount_{$idx}"],2);
                     $_REQUEST["item_discount_type_{$idx}"]    = (int)$_REQUEST["item_discount_type_{$idx}"];

                     for($zz = 1; $zz <= 4; $zz++)
                        $_REQUEST["item_pcat_dsc_{$idx}_{$zz}"] = getPrice($_REQUEST["item_pcat_dsc_{$idx}_{$zz}"],2);

                     $sql = " update {$_TABLENAME}
                              set
                              item_discount        = {$_REQUEST["item_discount_{$idx}"]},
                              item_discount_type   = {$_REQUEST["item_discount_type_{$idx}"]},
                              item_pcat_dsc_act    = {$_REQUEST["item_pcat_dsc_act_{$idx}"]},
                              item_pcat_dsc1       = {$_REQUEST["item_pcat_dsc_{$idx}_1"]},
                              item_pcat_dsc2       = {$_REQUEST["item_pcat_dsc_{$idx}_2"]},
                              item_pcat_dsc3       = {$_REQUEST["item_pcat_dsc_{$idx}_3"]},
                              item_pcat_dsc4       = {$_REQUEST["item_pcat_dsc_{$idx}_4"]},
                              item_vol_act         = {$_REQUEST["item_vol_act_{$idx}"]},
                              item_value_act       = {$_REQUEST["item_value_act_{$idx}"]}
                              where
                              invc_id              = {$_REQUEST["id"]} and
                              part_id              = {$idxpartid} and
                              item_pos             = {$poscounter}";
                     $CON->no_result($sql);
                  }
               }
               else
               {
                  //----------------------------------------------------------------------------------
                  $sql = " select part_req_id, part_dlv_id
                           from invoices_sell_bol_parts
                           where
                           id           = {$idxpartid} and
                           part_invc_id = {$_REQUEST["id"]}";
                  $parttemp = $CON->select($sql);
                  $part_req_id = $parttemp[0]["part_req_id"];
                  $part_dlv_id = $parttemp[0]["part_dlv_id"];

                  $item_order_pos = -1;
                  $item_dlv_pos   = -1;

                  //----------------------------------------------------------------------------------
                  $sql = " insert into invoices_sell_bol_parts_items
                           (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                            item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                            item_st_id, item_desc, item_charges_act, item_order_pos, item_dlv_pos,
                            item_reserva_amount)
                           VALUES
                           ({$_REQUEST["id"]}, {$idxpartid}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                            {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes},
                            {$_REQUEST["item_stid_{$idx}"]}, '{$_REQUEST["item_desc_{$idx}"]}', {$sql_charges_act},
                            {$item_order_pos}, {$item_dlv_pos}, {$_REQUEST["item_reserva_amount_{$idx}"]})";
                  $CON->no_result($sql);

                  recalcOrderItem($CON, $_REQUEST["id"], $sql_id, $poscounter, true, "INVOICEBOL", $idxpartid);
               }

               $poscounter++;
            }
            elseif($existing_id)
            {
               $sql = " delete from invoices_sell_bol_parts_items
                        where
                        invc_id  = {$_REQUEST["id"]} and
                        part_id  = {$idxpartid} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);

            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   $posdata    = Array();
   $invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"], "_bol");
   for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
   {
      $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"], "", "_bol");
      for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
         $posdata[] = $partposdata[$y];
   }
   
   //----------------------------------------------------------------------------------
   $_REQUEST["dlv_weightprice_netto"] = getPrice($_REQUEST["dlv_weightprice_netto"]);
   
   $sql = " update invoices_sell_bol
            set
            invc_weightprice_netto = {$_REQUEST["dlv_weightprice_netto"]}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if($headdata["invc_status"] == 1)
      recalcOrder($CON, $_REQUEST["id"], "INVOICEBOL");

   //----------------------------------------------------------------------------------
   if($final)
   {
      $sql = " update invoices_sell_bol
               set
               invc_status    = 2,
               invc_docnumber = 'PENDIENTE_SII'
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      bookInvoiceSellBol($CON, $_REQUEST["id"]);

      if((int)$headdata["invc_resv_id"])
      {
         $currtme = time();
         $sql = " update reservas_header
                  set
                  res_status  = 2,
                  res_updusr  = {$_SESSION["user_id"]},
                  res_upddat  = {$currtme}
                  where
                  id = {$headdata["invc_resv_id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " select count(*) 'cc'
               from invoices_sell_bol_parts_items
               where
               invc_id              = {$_REQUEST["id"]} and
               item_reserva_amount  > 0";
      $hasRevItems = $CON->select($sql);
      $hasRevItems = (int)$hasRevItems[0]["cc"];

      if($hasRevItems)
      {
         //----------------------------------------------------------------------------------
         $sql = " select t1.*
                  from customer t1
                  where
                  t1.id = {$headdata["invc_cust_id"]}";
         $customer = $CON->select($sql);
         $customer = $customer[0];

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

         $currtme = time();
         $res_date = date("d.m.Y H:i:s");
         $invc_resv_custname = trim(addslashes($customer["cust_name"]));
         $res_street = "VENTA EN SUCURSAL: {$headdata["shop_name"]}";
         $sql = " insert into reservas_header
                  (res_type, res_id, res_date, res_custname, res_city, res_street, res_plz, res_phone, res_mail,
                   res_delivprice, res_amount, res_paydesc, res_state, res_fulfilled, res_crtdat, res_crtusr,
                   res_paystate, res_retiroshopid)
                  VALUES
                  ('VENTA', '{$_REQUEST["id"]}', '{$res_date}', '{$invc_resv_custname}', '', '{$res_street}', '', '', '',
                   0, {$headdata["invc_total_brutto"]}, '', '',
                   '', {$currtme}, {$_SESSION["user_id"]}, 1, -1)";
         $xret = $CON->no_result($sql);
         if($xret)
         {
            $pos_header_id = mysql_insert_id();
            
            $sql = " select t1.*, t2.item_title
                     from invoices_sell_bol_parts_items t1
                     LEFT OUTER JOIN item t2 ON t1.item_id = t2.id
                     where
                     t1.invc_id              = {$_REQUEST["id"]} and
                     t1.item_reserva_amount  > 0
                     order by t1.item_pos";
            $resvitems = $CON->select($sql);
            $_RESVTOTAMT = 0.00;
            foreach($resvitems AS $subitem)
            {
               $subitem["item_title"] = trim(addslashes($subitem["item_title"]));
               $pos_itemprice = round($subitem["item_sellprice_brutto_dsc"] / $subitem["item_amount"] * $subitem["item_reserva_amount"]);
               
               $sql = " insert into reservas_pos
                        (pos_header_id, pos_itemdesc, pos_itemamt, pos_itemprice)
                        VALUES
                        ({$pos_header_id}, '{$subitem["item_title"]}', {$subitem["item_reserva_amount"]},
                         {$pos_itemprice})";
               $CON->no_result($sql);
               $_RESVTOTAMT += $pos_itemprice;
            }

            $sql = " update reservas_header
                     set
                     res_amount = {$_RESVTOTAMT}
                     where
                     id = {$pos_header_id}";
            $CON->no_result($sql);
         }
      }
   }

   //----------------------------------------------------------------------------------
   if($revert)
   {
      revertInvoiceSellBol($CON, $_REQUEST["id"]);

      if($_REQUEST["cancelDoc"] == "1")
      {
         $sql = " update invoices_sell_bol
                  set
                  invc_status = 4
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
      
      if($_REQUEST["cancelDoc"] == "2")
      {
         $sql = " update invoices_sell_bol
                  set
                  invc_status = 1
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }
}

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

//----------------------------------------------------------------------------------
$_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["invc_company_id"]);

if($_REQUEST["id"] != "" && $headdata["invc_status"] == 1 && $_INVCCFG["company_invc_itf"] == "BCNCONS")
{
   if((int)$headdata["invc_taxes"])
      $invc_docnumber = (int)createSiiNumber($CON, $headdata["invc_company_id"], $headdata["invc_shop_id"], "itf_bol_tax", true);
   else
      $invc_docnumber = (int)createSiiNumber($CON, $headdata["invc_company_id"], $headdata["invc_shop_id"], "itf_bol_tax", true);

   if(!$invc_docnumber)
   {  ?>
      <div style="color:red;text-align:center;padding:10px;width:950px;border:3px solid red;font-family:Arial;font-size:12px">
         <u><b>ADVERTENCIA:</b></u> NO QUEDAN FOLIOS DISPONIBLES. POR FAVOR ASIGNAR NUEVOS RANGOS.
      </div>
      <br>
      <?php
      $_BLOCKFIN_CHARGE = true;
   }
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/invoices_sell_bol/searchstorehouses.php?invcid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;

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
      var xurl = './libs/modules/invoices_sell_bol/searchitem.fancy.php?rowcount=' +rowcount + '&id=' +id +'&storehousemode=' +storehousemode;
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos" id="form_shppos"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  ?>
   onsubmit="return checkform(new Array(this.invc_date, this.invc_paymentid, this.invc_userid_seller))"
   <?php
}
?>>
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
      <td class="content_rowl">Opciones</td>
      <td class="content_row">
         <table border="0" cellpadding="0" cellspacing="0">
         <tr>
            <td class="content_row_clear">
               <?php
               printButton("Cambiar datos del cliente", "postnav", "index.php?mid=628&exec=edit&id={$customer["id"]}&registerback={$_REQUEST["mid"]}-{$_REQUEST["id"]}", "", "disk-black", 180);
               ?>
            </td>
            <td class="content_row_clear" style="padding-left:5px">
               <?php
               printGooglemapsButton($customer["cust_street"], $customer["name"], $customer["country_name"]);
               ?>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   <?php
}
?>
<tr>
   <?php
   if($headdata["invc_status"] == 1 && date('d.m.Y', $headdata["invc_date"]) != date('d.m.Y'))
   {
      $bcss1   = "<b class='msg_save_err'><blink>";
      $bcss2   = "</blink></b>";
      $tcss    = "border:2px solid red";
   }
   ?>
   <td class="content_rowl" <?=$cdatastyle?> height="31">Número boleta</td>
   <td class="content_row" <?=$cdatastyle?>>
      <?php
      if((int)$_INVCCFG["company_invc_mode"])
      {  ?>
         <input type="hidden" name="invc_docnumber" value="<?=$headdata["invc_docnumber"]?>">
         <?php
         if($headdata["invc_status"] > 1)
         {  ?>
            <div id="idx_sii_refresh"></div>
            <script language="JavaScript">
               function reloadSiiState()
               {
                  $.get('/libs/modules/invoices_sell/get.siistatus.jquery.php?mode=invoices_sell_bol&id=<?=$_REQUEST["id"]?>', '', function(data)
                  {
                     $("#idx_sii_refresh").html(data);
                  });
               }
               $(document).ready(function()
               {
                  reloadSiiState();
               });
            </script>
            <?php
         }
         else
            echo $headdata["invc_docnumber"]. " Pendiente";
      }
      else
      {  ?>
         <input type="text" style="width:350px" name="invc_docnumber" class="text" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["invc_docnumber"]?>">
         <?php
      }
      ?>
   </td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$bcss1?>Fecha *<?=$bcss2?></td>
   <td class="content_row" <?=$cdatastyle?>>
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <input type="text" style="width:80px;<?=$tcss?>" id="invc_date" name="invc_date" <?=$rdlo?>
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=date('d.m.Y', $headdata["invc_date"])?>">
         </td>
         <td class="content_row_clear" align="right" style="padding-right:10px;display:none">
            Fecha vencimiento:
            <input type="text" style="width:80px;<?php if($rdlo != "") echo "margin-right:20px"?>" id="invc_estpay_date" name="invc_estpay_date"
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            value="<?php if($headdata["invc_estpay_date"] > 0) echo date('d.m.Y', $headdata["invc_estpay_date"])?>">
         </td>
         <td class="content_row_clear" align="right" style="padding-right:10px;display:none">
            Fecha recepción:
            <input type="text" style="width:80px;<?php if($rdlo != "") echo "margin-right:20px"?>" id="invc_receipt_date" name="invc_receipt_date"
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            onchange="document.form_shppos.onsubmit='';submitForm(document.form_shppos);"
            value="<?php if($headdata["invc_receipt_date"] > 0) echo date('d.m.Y', $headdata["invc_receipt_date"])?>">
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
               case 4: $statimg = "gray_active.gif"; break;
            }
            ?>
            <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
            <?=getInvoiceBuyStatus($headdata["invc_status"], true)?>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl">Vendedor *</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="invc_userid_seller" id="invc_userid_seller"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($sellers as $seller)
            {  ?>
               <option value="<?=$seller["id"]?>"
               <?php if($seller["id"] == $headdata["invc_userid_seller"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["invc_userid_seller"]?>"><?=$headdata["seller_firstname"]?> <?=$headdata["seller_lastname"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">IVA</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear" width="120">
            <select class="text" style="width:120px;background-color:<?php if((int)$headdata["invc_taxes"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
               <?php
               if((int)$headdata["invc_taxes"])
                  echo '<option value="">CON IVA</option>';
               else
                  echo '<option value="">SIN IVA</option>';
               ?>
            </select>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td class="content_rowl" style="display:none">Transportista</td>
   <td class="content_row" style="display:none">
      <nobr>
      <select class="text" style="width:350px" name="invc_transportid" id="invc_transportid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($transports as $transport)
            {  ?>
               <option value="<?=$transport["id"]?>"
               <?php if($transport["id"] == $headdata["invc_transportid"]) echo "selected"?>><?=$transport["trans_name"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["invc_transportid"]?>"><?=$headdata["trans_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl" style="display:none">Cobrador *</td>
   <td class="content_row" style="display:none">
      <select class="text" style="width:350px" name="invc_userid_cashing" id="invc_userid_cashing"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($sellers as $seller)
            {  ?>
               <option value="<?=$seller["id"]?>"
               <?php if($seller["id"] == $headdata["invc_userid_cashing"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["invc_userid_cashing"]?>"><?=$headdata["cashing_firstname"]?> <?=$headdata["cashing_lastname"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">Cambio de stock</td>
   <td class="content_row">
      <?php
      if($headdata["invc_status"] == 1)
      {  ?>
         <select class="text" name="invc_stockchange" id="invc_stockchange" style="width:120px;background-color:<?php if((int)$headdata["invc_stockchange"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
         <?php
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
            <select class="text" id="invc_stockchange" name="invc_stockchange" style="width:120px;background-color:#BFE6C3;">
               <option value="1">HABILITADO</option>
            </select>
            <?php
         }
         else
         {  ?>
            <select class="text" id="invc_stockchange" name="invc_stockchange" style="width:120px;background-color:#FFD6D8;">
               <option value="0" selected>DESHABILITADO</option>
            </select>
            <?php
         }
      }
      ?>
   </td>
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
         printButton("{$btntext}", $btnclass, "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&mode=invoices_sell_bol&subcatexec=payment", "", "money", 120);
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
   <td class="content_rowl" style="display:none">Bultos</td>
   <td class="content_row" style="display:none">
      <input type="text" style="width:350px;" id="invc_bultos" name="invc_bultos" <?=$rdlo?> class="text"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if((int)$headdata["invc_bultos"]) echo $headdata["invc_bultos"]?>">
   </td>
</tr>
<tr id="idf_shpdata" style="display:none">
   <?php
      $require = "";
      if($genshops)
      {
         $require = "*";
      }
   ?>
   <td class="content_rowl">X</td>
   <td class="content_row">
   </td>
   <td class="content_rowl">Precios</td>
   <td class="content_row">
      <?php
      if((int)$headdata["invc_isinvcbrutto"])
      {  ?>
         <select class="text" style="width:120px;background-color:#EEEEEE">
            <option value="1">PRECIO BRUTO</option>
         </select>
         <?php
      }
      else
      {  ?>
         <select class="text" style="width:120px;background-color:#EEEEEE">
            <option value="1">PRECIO NETO</option>
         </select>
         <?php
      }
      ?>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones<br>[cliente]</td>
   <td class="content_row">
      <textarea class="text" style="width:350px; height:45px" name="invc_desc" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["invc_desc"])?></textarea>
   </td>
   <td class="content_rowl" valign="top">Observaciones<br>[interno]</td>
   <td class="content_row">
      <textarea class="text" style="width:350px; height:45px" name="invc_desc_intern" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["invc_desc_intern"])?></textarea>
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

   //----------------------------------------------------------------------------------
   if($headdata["invc_status"] == 1)
   {
      $rowcount = 22;
      if($headdata["invc_type"] == 1 && (int)$headdata["invc_dlv_addtxt"])
         $rowcount = count($posdata);
   }
   else
      $rowcount = count($posdata);
   ?>
   <?=Nifty_printH("box1", "980")?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="85">
      <col width="28">
      <col>
      <col>
      <?php
      if($headdata["invc_type"] == 1)
         echo "<col>";
      ?>
      <?php
      if($showStorehouses)
         echo "<col>";
      if($_REQUEST["showDiscounts"] == "1")
      {  ?>
         <col>
         <col>
         <col <?if($_HIDETAXES) echo 'style="display:none"'?>>
         <col>
         <?php
      }
      ?>
      <col>
      <col>
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="15">
         <table border="0" cellpadding="0" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col width="50">
         </colgroup>
         <tr>
            <td class="content_tbl_header" style="border:0px;padding:0px">
               <?php
               if($part["part_dlv_id"] > 0)
               {  ?>
                  Número int: <?=$part["dlv_num"]?> |
                  Guia de despacho: <?=$part["dlv_docnum"]?>
                  <?php
               }
               if($part["part_req_id"] > 0)
               {  ?>
                  Nota de venta: <?=$part["req_number"]?>
                  <?php
               }
               if($headdata["invc_type"] == 3)
                  echo "Artículos";
               ?>
            </td>
            <?php
            if(count($proms) && $headdata["invc_status"] == 1)
            {  ?>
               <td class="content_tbl_header" style="padding:0px" width="160">
                  <img src="./images/menu/icons/cake.png" style="vertical-align:bottom">
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="showFancybox('/libs/modules/orders/apply.promotions.fancy.php?frmname=form_shppos&shopid=<?=$headdata["invc_shop_id"]?>&id=<?=$_REQUEST["id"]?>&partid=<?=$part["id"]?>&mode=INVOICE', 'iframe', 950, 450, 'auto')">Agregar Promociones</a>
               </td>
               <?php
            }
            if(!(int)$_SESSION["user_pricesell_perm"] && !(int)$_REQUEST["user_pricesell_perm"] && $headdata["invc_status"] == 1)
            {  ?>
               <td class="content_tbl_header" style="padding:0px" width="190">
                  <img src="./images/menu/icons/currency.png" style="vertical-align:bottom">
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="showFancybox('/libs/modules/orders/auth.pricechange.fancy.php?frmname=form_shppos', 'iframe', 450, 160, 'no')">Activar cambio de precios</a>
               </td>
               <?php
            }
            if($headdata["invc_status"] == 1 && count($posdata) && $posdata != false)
            {  ?>
               <td class="content_tbl_header" style="padding:0px" width="150">
                  <img src="./images/menu/icons/arrow-270-medium.png" style="vertical-align:bottom">
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="document.form_shppos.setPosOrder.value='prodnumber'; submitForm(document.form_shppos);">Ordenar por codigo</a>
               </td>
               <?php
            }
            ?>
            <td class="content_tbl_header" style="padding:0px" width="140">
               <nobr>
               <img src="./images/menu/icons/calculator.png" style="vertical-align:bottom">
               <?php
               if((int)$_REQUEST["showDiscounts"])
               {
                  $newshowDiscountsTxt = "Ocultar";
                  $newshowDiscounts    = "";
               }
               else
               {
                  $newshowDiscountsTxt = "Mostrar";
                  $newshowDiscounts    = 1;
               }
               if($headdata["invc_status"] == 1)
               {  ?>
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="document.form_shppos.showDiscounts.value='<?=$newshowDiscounts?>'; submitForm(document.form_shppos);"><?=$newshowDiscountsTxt?> condiciones</a>
                  <?php
               }
               else
               {  ?>
                  <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
                  onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subcatexec=basic&id=<?=$_REQUEST["id"]?>&showDiscounts=<?=$newshowDiscounts?>';"><?=$newshowDiscountsTxt?> condiciones</a>
                  <?php
               }
               ?>
               </nobr>
            </td>
         </tr>
         </table>
      </td>
   </tr>
   <tr>
      <td class="content_tbl_subheader" valign="top">Busqueda</td>
      <td class="content_tbl_subheader" valign="top">Act.</td>
      <td class="content_tbl_subheader" valign="top">Artículo</td>
      <td class="content_tbl_subheader" valign="top" align="center">Codigo/Prov</td>
      <td class="content_tbl_subheader" valign="top" align="center">Unidad</td>
      <?php
      if($headdata["invc_type"] == 1)
      {  ?>
         <td class="content_tbl_subheader" valign="top">Pend.</td>
         <?php
      }
      ?>
      <td class="content_tbl_subheader" valign="top" align="right">Cantidad</td>
      <?php
      if($showStorehouses)
      {  ?>
         <td class="content_tbl_subheader" valign="top">Bodega</td>
         <?php
      }
      $coltitle = "Precio/Neto";
      if((int)$headdata["invc_isinvcbrutto"])
         $coltitle = "Precio/Bruto";
      ?>
      <td class="content_tbl_subheader" valign="top" align="right"><?=$coltitle?></td>
      <?php
      if(!(int)$_REQUEST["showDiscounts"] || ($_REQUEST["showDiscounts"] == "1" && !(int)$headdata["invc_stockchange"]))
      {  ?>
         <td class="content_tbl_subheader" valign="top" align="right" <?if($_HIDETAXES) echo 'style="display:none"'?>>IVA %</td>
         <?php
      }
      if($_REQUEST["showDiscounts"] == "1")
      {
         if(!(int)$headdata["invc_stockchange"])
         {  ?>
            <td class="content_tbl_subheader" align="right"><nobr>Subtotal</nobr></td>
            <?php
         }
         ?>
         <td class="content_tbl_subheader" align="center"><nobr>Desc-Global</nobr></td>
         <td class="content_tbl_subheader" align="center"><nobr>Descuentos-Familia</nobr></td>
         <td class="content_tbl_subheader" align="center"><nobr>Desc-Vol.</nobr></td>
         <td class="content_tbl_subheader" align="center"><nobr>Desc-Monto</nobr></td>
         <?php
      }
      ?>
      <td class="content_tbl_subheader" valign="top" align="right"><nobr>Precio Total</nobr></td>
   </tr>
   <?php
   for($y = 0; $y < $rowcount; $y++)
   {
      $showmanual = false;
      if((int)$posdata[$y]["item_id"] && $posdata[$y]["item_type"] == "manual")
         $showmanual = true;

      if($headdata["invc_status"] > 1 && $posdata[$y]["order_amount"] > 0 &&
         $posdata[$y]["order_amount_shipped"] < $posdata[$y]["order_amount"] &&
         !(int)$posdata[$y]["item_amount_shipped_stop"])
         $showBTNDiff = true;

      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "xf_search_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_id_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_amount_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_stid_{$part["id"]}_{$y}";
      if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_sellprice_netto_{$part["id"]}_{$y}";
      if(!$_HIDETAXES)
      {
         if(!$_FIELDREGS[$gc]) $_FIELDREGS[$gc] = Array(); $_FIELDREGS[$gc][] = "item_sellprice_taxes_perc_{$part["id"]}_{$y}";
      }
      ?>
      <tr bgcolor="<?=getRowColor($y)?>"<?php
      if((int)$posdata[$y]["item_promid"])
      {  ?>
         style="background-color:#C4EEFF"
         onmouseover="return overlib('PROMOCION', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#698988', ABOVE)"
         onmouseout="return nd()"
         <?php
      }
      ?>>
         <td class="content_row" valign="top">
            <?if($showmanual) { echo "&nbsp;";  $_FIELDIGNORES["xf_search_{$part["id"]}_{$y}"] = 1; } ?>
            <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$part["id"]?>_<?=$y?>" <?php if($showmanual) echo "style='display:none'" ?>>
            <tr>
               <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
               <td>
                  <input type="text" class="text" style="width:60px"
                  name="xf_search_<?=$part["id"]?>_<?=$y?>" id="xf_search_<?=$part["id"]?>_<?=$y?>"
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
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/invoices_sell_bol/searchitem.php?rowcount=<?=$part["id"]?>_<?=$y?>&id=<?=$_REQUEST["id"]?><?=$urlparam?>&search=' +this.value} this.value='';"
                     onkeyup="detectEvent(event, '<?=$part["id"]?>_<?=$y?>', '<?=$_REQUEST["id"]?>', '<?=substr($urlparam, -1)?>')"
                     <?php
                  }
                  else
                  {  ?>
                     onblur="markfield(this,1)"
                     <?php
                     $hasItems = true;

                     $item_weight       = $posdata[$y]["item_amount"] * $posdata[$y]["item_weight"];
                     $ges_weight       += $item_weight;
                     $ges_weight_price += ($posdata[$y]["item_amount"] * (float)$posdata[$y]["item_weight_price"]);
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
            $overlibover   = "";
            $overlibover2  = "";
            $ovritemselw   = "";
            $noHasMinAmount = false;
            if((int)$posdata[$y]["item_id"])
            {
               if((float)$posdata[$y]["item_sell_amountmin"] && $posdata[$y]["item_amount"] < $posdata[$y]["item_sell_amountmin"])
                  $noHasMinAmount = true;

               $posdata[$y]["item_costprice_netto"] = precalcSupplierCost($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
               $itemrealsellprice = $posdata[$y]["item_sellprice_netto_dsc"] /  $posdata[$y]["item_amount"];
               if(!(int)$posdata[$y]["item_promid"] && ($itemrealsellprice < $posdata[$y]["item_costprice_netto"] || $posdata[$y]["item_invoice_note"] != "" || $noHasMinAmount))
               {
                  if($noHasMinAmount)
                     $overlibover2 = "<b class=msg_save_err>Cantidad minima de venta: ".printPrice($posdata[$y]["item_sell_amountmin"],2)."</b><br>";
                  if($posdata[$y]["item_invoice_note"] != "")
                     $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$y]["item_invoice_note"]))."</b>";
                     
                  if($overlibover != "")
                  {  ?>
                     <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
                     onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
                     onmouseout="return nd()">
                     <?php
                     if($itemselw == "480px")      $ovritemselw = "460px";
                     elseif($itemselw == "375px")  $ovritemselw = "355px";
                     elseif($itemselw == "325px")  $ovritemselw = "305px";
                  }
               }
               else
                  $ovritemselw = "";
            }
            else
               $ovritemselw = "";
            ?>
            <select class="text" style="width:<?php if($ovritemselw != "") echo $ovritemselw; else echo $itemselw?>;<?php if($showmanual) echo "display:none" ?>"
            name="item_id_<?=$part["id"]?>_<?=$y?>" id="item_id_<?=$part["id"]?>_<?=$y?>"
            onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
            onfocus="<?php if(!(int)$posdata[$y]["item_id"]) echo "addSelStyle(this);" ?>"
            onmousedown="markfield(this,0)"
            onchange="setItemInfosInvc('<?=$part["id"]?>_<?=$y?>', this.value);<?php
            if($showStorehouses)
               echo "updateItemStorehousesArr('{$part["id"]}_{$y}', this.value);";
            ?>">
               <?php
               if((int)$posdata[$y]["item_id"])
               {
                  $desc = trim(addslashes($posdata[$y]["item_title"]));
                  ?>
                  <option value="<?=$posdata[$y]["item_id"]?>#<?=$posdata[$y]["item_type"]?>"><?=$posdata[$y]["item_number_prod"]?> - <?=$desc?></option>
                  <?php
               }
               ?>
            </select>
            <?php if($overlibover2 != "") echo "<br><img src='/images/menu/icons/exclamation-red.png' style='valign:bottom'>&nbsp;{$overlibover2}" ?>
            <textarea class="text" name="item_desc_<?=$part["id"]?>_<?=$y?>" id="item_desc_<?=$part["id"]?>_<?=$y?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            style="width:<?php if($ovritemselw != "") echo $ovritemselw; else echo $itemselw?>;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$y]["item_desc"]?></textarea>
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
         <?php
         if($headdata["invc_type"] == 1)
         {  ?>
            <td class="content_row" valign="top" align="center">
            <?php
            if((int)$posdata[$y]["item_id"])
            {
               $noinvoiced = getItemShopDeliveryNoInvoice($CON, $part["part_dlv_id"], $posdata[$y]["item_dlv_pos"], $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
               echo printPrice($noinvoiced,2);
            }
            else
               echo "&nbsp;";
            ?>
            </td>
            <?php
         }
         ?>
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
                  <select class="text" style="width:120px;<?if((int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>"
                  name="item_stid_<?=$part["id"]?>_<?=$y?>" id="item_stid_<?=$part["id"]?>_<?=$y?>"
                  onblur="markfield(this,1);removeSelStyle(this);"
                  onfocus="addSelStyle(this);"
                  onmousedown="markfield(this,0)">
                     <?php
                     if((int)$posdata[$y]["item_id"])
                     {
                        if($posdata[$y]["item_type"] == "item")
                           $itemsts = getItemStorehouses($CON, $headdata["invc_shop_id"], $posdata[$y]["item_id"], $posdata[$y]["item_type"], true, 0, 0, true);
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
                                 $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $itemstid, $posdata[$y]["item_id"], $posdata[$y]["item_type"], true);
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
                  <div style="<?if(!(int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$part["id"]?>_<?=$y?>">
                  <?php
                  $btnicon = "arrow";
                  $btnname = "Lotes";
                  $btnclas = "postnav";

                  if(posHasItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesell", $y, $part["id"]) > 0)
                  {
                     $btnicon = "tick-circle-frame";
                     $btnclas = "postnav_save";

                     $charge_amount = posItemChargeDataAmountUsed($CON, $_REQUEST["id"], "invoicesell", $y, $part["id"]);
                     if($charge_amount["tran_amount"] != $posdata[$y]["item_amount"])
                     {
                        $btnicon = "cross-circle-frame";
                        $btnclas = "postnav_del";
                        $_BLOCKFIN_CHARGE = true;
                     }
                  }
                  elseif((int)$posdata[$y]["item_charges_act"])
                     $_BLOCKFIN_CHARGE = true;

                  printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.select.php?trantype=invoicesell&needamount=' +$('#item_amount_{$part["id"]}_{$y}').val() +'&tranid={$_REQUEST["id"]}&tranpos={$part["id"]}_{$y}&itemdata=' +escape($('#item_id_{$part["id"]}_{$y}').val()), 'iframe', 750, 400, 'auto')", "", $btnicon, 120)
                  ?>
                  <textarea id="item_charges_data_<?=$part["id"]?>_<?=$y?>" name="item_charges_data_<?=$part["id"]?>_<?=$y?>" style="display:none"></textarea>
                  </div>
                  <?php
               }
               ?>
            </td>
            <?php
         }
         else
            $_FIELDIGNORES["item_stid_{$part["id"]}_{$y}"] = 1;
         ?>
         <td class="content_row" align="right" valign="top">
            <?php
            if(!$hasprcsellperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;
            ?>
            <nobr>
            <input type="text" class="text" autocomplete="off"
            style="<?if((int)$headdata["invc_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right" <?=$dscrdlo?>
            name="item_sellprice_netto_<?=$part["id"]?>_<?=$y?>" id="item_sellprice_netto_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_netto"])?>">
            </nobr>
            <nobr>
            <input type="text" class="text" <?=$dscrdlo?> autocomplete="off"
            style="<?if(!(int)$headdata["invc_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right"
            name="item_sellprice_brutto_<?=$part["id"]?>_<?=$y?>" id="item_sellprice_brutto_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_brutto"])?>">
            </nobr>
         </td>
         <td class="content_row" align="right" valign="top" <?php if(((int)$_REQUEST["showDiscounts"] && (int)$headdata["invc_stockchange"]) || $_HIDETAXES) echo "style='display:none'"?>>
            <?php
            if((int)$_REQUEST["showDiscounts"] && (int)$headdata["invc_stockchange"])
               $_FIELDIGNORES["item_sellprice_taxes_perc_{$part["id"]}_{$y}"] = 1;
               
            if(!$hasprcsellperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;
            ?>
            <input type="text" class="text" style="width:38px;text-align:right" <?=$dscrdlo?> autocomplete="off"
            name="item_sellprice_taxes_perc_<?=$part["id"]?>_<?=$y?>" id="item_sellprice_taxes_perc_<?=$part["id"]?>_<?=$y?>"
            value="<?php
            if((int)$posdata[$y]["item_id"])
               echo printPrice($posdata[$y]["item_sellprice_taxes_perc"],2);
            elseif((int)$headdata["invc_taxes"])
               echo printPrice($_SESSION["_CONF"]["conf_taxes"],2);
            else
               echo "0";
            ?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row" valign="top" align="right" <?php if($_REQUEST["showDiscounts"] != "1" || ((int)$_REQUEST["showDiscounts"] && (int)$headdata["invc_stockchange"])) echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$y]["item_id"])
            {
               $ges_line = $posdata[$y]["item_sellprice_netto"] * $posdata[$y]["item_amount"];
               ?>
               <input type="text" class="text" readonly tabindex="-1"
               style="width:75px;text-align:right;"
               value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($ges_line, $numberlim)?>">
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$y]["item_id"])
            {
               if(!$hasprcsellperm)
               {
                  $dscrdlo = " readonly ";
                  $dscdabl = " disabled ";
               }
               else
               {
                  $dscrdlo = $rdlo;
                  $dscdabl = $dabl;
               }
               ?>
               <nobr>
               <input name="item_discount_<?=$part["id"]?>_<?=$y?>" type="text" tabindex="-1" class="text" style="width:26px;text-align:center;<?php
               if($posdata[$y]["item_discount"] > 0.00) echo "background-color:#E1FFD6"; else echo "background-color:#FFD6D8";?>"
               value="<?php if($posdata[$y]["item_discount"] > 0.00) echo printPrice($posdata[$y]["item_discount"],2);?>" <?=$dscrdlo?>>
               
               <select class="text" style="width:35px" name="item_discount_type_<?=$part["id"]?>_<?=$y?>" tabindex="-1"
               onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dscdabl?>>
                  <option value="0" <?php if(!(int)$posdata[$y]["item_discount_type"]) echo "selected" ?>>%</option>
                  <option value="1" <?php if( (int)$posdata[$y]["item_discount_type"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
               </select>
               </nobr>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$y]["item_id"])
            {
               if(!$hasprcsellperm)
                  $dscrdlo = " readonly ";
               else
                  $dscrdlo = $rdlo;
               ?>
               <nobr>
               <input type="checkbox" class="checkbox" name="item_pcat_dsc_act_<?=$part["id"]?>_<?=$y?>" value="1" tabindex="-1"
               <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$y]["item_pcat_dsc_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
               <?php
               $isfirst = false;
               for($zz = 1; $zz <= 4; $zz++)
               {  ?>
                  <input type="text" tabindex="-1" class="text" name="item_pcat_dsc_<?=$part["id"]?>_<?=$y?>_<?=$zz?>" style="width:25px;text-align:center;<?php
                  if((int)$posdata[$y]["item_pcat_dsc_act"] && $posdata[$y]["item_pcat_dsc{$zz}"] > 0.00)
                     echo "background-color:#E1FFD6";
                  else
                     echo "background-color:#FFD6D8";?>"
                  value="<?=printPrice($posdata[$y]["item_pcat_dsc{$zz}"],2)?>" <?=$dscrdlo?>>
                  <?php
               }
               ?>
               </nobr>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$y]["item_id"])
            {  ?>
               <nobr>
               <input type="checkbox" class="checkbox" name="item_vol_act_<?=$part["id"]?>_<?=$y?>" value="1" tabindex="-1"
               <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$y]["item_vol_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
               
               <input name="item_vol_dsc_<?=$part["id"]?>_<?=$y?>" type="text" tabindex="-1" class="text" style="width:30px;text-align:center;<?php
               if((int)$posdata[$y]["item_vol_act"] && $posdata[$y]["item_vol_dsc"] > 0.00)
                  echo "background-color:#E1FFD6";
               else
                  echo "background-color:#FFD6D8";?>"
               value="<?php if($posdata[$y]["item_vol_dsc"] > 0.00) echo printPrice($posdata[$y]["item_vol_dsc"],2);?>" readonly>
               
               <?php
               if((int)$posdata[$y]["item_vol_dsctype"])
                  echo $_SESSION["_CONF"]["conf_currency"];
               else
                  echo "%";
               ?>
               </nobr>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$y]["item_id"])
            {  ?>
               <nobr>
               <input type="checkbox" class="checkbox" name="item_value_act_<?=$part["id"]?>_<?=$y?>" value="1" tabindex="-1"
               <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$y]["item_value_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>

               <input name="item_value_dsc_<?=$part["id"]?>_<?=$y?>" type="text" tabindex="-1" class="text" style="width:26px;text-align:center;<?php
               if((int)$posdata[$y]["item_value_act"] && $posdata[$y]["item_value_dsc"] > 0.00)
                  echo "background-color:#E1FFD6";
               else
                  echo "background-color:#FFD6D8";?>"
               value="<?php if($posdata[$y]["item_value_dsc"] > 0.00) echo printPrice($posdata[$y]["item_value_dsc"],2);?>" readonly>
               <?php
               if((int)$posdata[$y]["item_value_dsctype"])
                  echo $_SESSION["_CONF"]["conf_currency"];
               else
                  echo "%";
               ?>
               </nobr>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row" align="right" valign="top">
            <nobr>
            <input type="text" class="text" readonly tabindex="-1"
            style="<?if((int)$headdata["invc_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right;background-color:<?if((int)$posdata[$y]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            name="item_sellprice_netto_dsc_<?=$part["id"]?>_<?=$y?>" id="item_sellprice_netto_dsc_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_netto_dsc"])?>">
            </nobr>
            <nobr>
            <input type="text" class="text" readonly tabindex="-1"
            style="<?if(!(int)$headdata["invc_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right;background-color:<?if((int)$posdata[$y]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
            name="item_sellprice_brutto_dsc_<?=$part["id"]?>_<?=$y?>" id="item_sellprice_brutto_dsc_<?=$part["id"]?>_<?=$y?>"
            value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_brutto_dsc"])?>">
            </nobr>
         </td>
      </tr>
      <?php
      if((int)$posdata[$y]["item_id"] && $posdata[$y]["item_woo_itemcode"] != "")
      {  ?>
         <tr>
            <td class="content_row" bgcolor="#B2E2FA" colspan="9">
               <img src="/images/menu/icons/information.png" style="vertical-align:bottom"> WOOCOMMERCE:
               SKU <?=$posdata[$y]["item_woo_itemcode"]?>, <?=$posdata[$y]["item_woo_itemdesc"]?>, Descuento Stock ERP: <?=printPrice($posdata[$y]["item_override_stockdsc_amt"],2)?> C/U
            </td>
         </tr>
         <?php
      }

      if(!(int)$posdata[$y]["item_id"] && $_REQUEST["subexec"] == "save" && !$focusexec)
      {
         $_SESSION["JSEXEC"] .= "document.form_shppos.xf_search_{$part["id"]}_{$y}.focus();$('html,body').animate({scrollTop:$(window).scrollTop() + 100}, 200);";
         $focusexec = true;
      }
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
            <td class="content_row_clear" colspan="2">SUBTOTAL</td>
            <td class="content_row_clear" align="right">
               <input type="text" class="text" style="width:120px;text-align:right" readonly
               value="<?=printPrice($headdata["invc_total_netto"] + $headdata["invc_discount_amount_netto"] - $headdata["invc_weightprice_netto"])?>">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_clear" colspan="2">
               <table border="0" class="content_table" cellpadding="0" cellspacing="0" width="100%">
               <tr>
                  <td class="content_row_clear">DESCUENTOS</td>
                  <td class="content_row_clear" width="140">
                     <nobr>
                     <input type="text" class="text" style="text-align:center;width:100px;"
                     id="invc_discount_perc" name="invc_discount_perc"
                     value="<?=printPrice($headdata["invc_discount_perc"],2)?>" <?=$rdlo?>> %
                     </nobr>
                  </td>
                  <td class="content_row_clear" align="right" width="1">
                     <nobr>
                     <input type="text" class="text" style="text-align:center;width:100px;"
                     id="invc_discount_amt" name="invc_discount_amt"
                     value="<?=printPrice($headdata["invc_discount_amt"],2)?>" <?=$rdlo?>> $
                     </nobr>
                  </td>
               </tr>
               </table>
            </td>
            <td class="content_row_clear" align="right">
               <input type="text" class="text" style="width:120px;text-align:right" readonly
               value="<?=printPrice($headdata["invc_discount_amount_netto"])?>">
            </td>
         </tr>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_clear" colspan="2">SUBTOTAL</td>
            <td class="content_row_clear" align="right">
               <input type="text" class="text" style="width:120px;text-align:right" readonly
               value="<?=printPrice($headdata["invc_total_netto"] - $headdata["invc_weightprice_netto"])?>">
            </td>
         </tr>
         <?php
         if($headdata["invc_total_taxes"] > 0.00)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_totals content_rowl" colspan="2">NETO</td>
               <td class="content_row_totals content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["invc_total_netto"])?>">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row content_rowl" colspan="2">IVA</td>
               <td class="content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["invc_total_taxes"])?>">
               </td>
            </tr>
            <?php
         }
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_rowl" colspan="2">TOTAL</td>
            <td class="content_row_totals content_row" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
               value="<?=printPrice($headdata["invc_total_brutto"])?>">
            </td>
         </tr>
         </table>
         <?=Nifty_printF(false)?>
      </td>
      <td width="15" class="content_row_clear">&nbsp;</td>
      <td valign="top">
         <script language="JavaScript">
            function setCalcDiscountsGLB()
            {
               var val = document.getElementById('glb_item_discount').value;
               var mod = document.getElementById('glb_item_discount_type').value;

               var $inputs = $('#form_shppos :input');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf('item_discount_') > -1)
                     $(this).val(val);
               });

               var $inputs = $('#form_shppos select');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf('item_discount_type_') > -1)
                     $(this).val(mod);
               });
            }

            function setCalcDiscountsCAT()
            {
               var val1 = document.getElementById('glb_item_pcat_dsc_1').value;
               var val2 = document.getElementById('glb_item_pcat_dsc_2').value;
               var val3 = document.getElementById('glb_item_pcat_dsc_3').value;
               var val4 = document.getElementById('glb_item_pcat_dsc_4').value;

               var $inputs = $('#form_shppos :input');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_1') == objname.length - 2)
                     $(this).val(val1);
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_2') == objname.length - 2)
                     $(this).val(val2);
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_3') == objname.length - 2)
                     $(this).val(val3);
                  if(objname.indexOf('item_pcat_dsc_') > -1 && objname.indexOf('item_pcat_dsc_act_') == -1 && objname.lastIndexOf('_4') == objname.length - 2)
                     $(this).val(val4);
               });
            }

            function setCalcDiscountsACT(mode)
            {
               var sfield = '';
               var ofield = '';
               if(mode == 'CAT') { ofield = 'glb_item_pcat_act';  sfield = 'item_pcat_dsc_act_'; }
               if(mode == 'VOL') { ofield = 'glb_item_vol_act';   sfield = 'item_vol_act_'; }
               if(mode == 'VAL') { ofield = 'glb_item_val_act';   sfield = 'item_value_act_'; }
                  
               var act  = document.getElementById(ofield).checked;
               var $inputs = $('#form_shppos :checkbox');
               $inputs.each(function()
               {
                  var objname = $(this).attr('name');
                  if(objname.indexOf(sfield) > -1)
                     $(this).attr('checked', act);
               });
            }
         </script>
         <?php
         if($headdata["invc_status"] < 2 && $_REQUEST["showDiscounts"] == "1" && $hasprcsellperm)
         {  ?>
            <?=Nifty_printH("box1", "578")?>
            <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" style="padding:1px">
            <colgroup>
               <col>
               <col width="180">
               <col width="75">
            </colgroup>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_rowl" style="border-top:0px">DESCUENTOS GLOBALES</td>
               <td class="content_row" style="border-top:0px" align="right">
                  <input id="glb_item_discount" type="text" class="text" style="width:75px;text-align:center;">
                  
                  <select class="text" style="width:35px" id="glb_item_discount_type"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)">
                     <option value="0">%</option>
                     <option value="1"><?=$_SESSION["_CONF"]["conf_currency"]?></option>
                  </select>
               </td>
               <td class="content_row" style="border-top:0px" align="right">
                  <input type="button" class="button" value="&gt;&gt;" style="width:65px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="setCalcDiscountsGLB()">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_rowl">DESCUENTOS POR FAMILIA / COND.</td>
               <td class="content_row" align="right">
                  <?php
                  for($y = 1; $y <= 4; $y++)
                  {  ?>
                     <input type="text" class="text" id="glb_item_pcat_dsc_<?=$y?>" style="width:26px;text-align:center;" value="">
                     <?php
                  }
                  ?>
               </td>
               <td class="content_row" align="right">
                  <input type="button" class="button" value="&gt;&gt;" style="width:65px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="setCalcDiscountsCAT()">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
               <td class="content_row" align="right">
                  POR FAMILIA / CONDICIÓN
                  <input type="checkbox" class="checkbox" id="glb_item_pcat_act" value="1" checked>
               </td>
               <td class="content_row" align="right" valign="top">
                  <input type="button" class="button" value="&gt;&gt;" style="width:65px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="setCalcDiscountsACT('CAT')">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
               <td class="content_row" align="right">
                  POR VOLUMEN
                  <input type="checkbox" class="checkbox" id="glb_item_vol_act" value="1" checked>
               </td>
               <td class="content_row" align="right" valign="top">
                  <input type="button" class="button" value="&gt;&gt;" style="width:65px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="setCalcDiscountsACT('VOL')">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_rowl" valign="top">ACTIVACIÓN DESCUENTOS</td>
               <td class="content_row" align="right">
                  POR MONTO
                  <input type="checkbox" class="checkbox" id="glb_item_val_act" value="1" checked>
               </td>
               <td class="content_row" align="right" valign="top">
                  <input type="button" class="button" value="&gt;&gt;" style="width:65px"
                  onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
                  onclick="setCalcDiscountsACT('VAL')">
               </td>
            </tr>
            </table>
            <?=Nifty_printF(false)?>
            <?php
         }
         else
            echo "&nbsp;";
         ?>
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
   <?php
   if($headdata["invc_status"] == 2 && !$_BLOCKOPEN_CHARGE)
   {
      if(!(int)$_INVCCFG["company_invc_mode"])
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
      if(!(int)$_INVCCFG["company_invc_mode"] ||
          ((int)$_INVCCFG["company_invc_mode"] && (int)$headdata["invc_sgntr_end"] && !(int)$headdata["invc_itf_trndat"]))
      {  ?>
         <td align="right" width="170">
         <?php
         if($hasdocopeperm)
            printButton("Anular Factura", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.invc_status.value='1';document.form_shppos.cancelDoc.value='1';submitForm(document.form_shppos);}", "cross-circle-frame");
         else
            printButton("Anular Factura", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos&sStatus=1&cancelDoc=1', 'iframe', 450, 160, 'no')", "cross-circle-frame");
         ?>
         </td>
         <?php
      }
   }

   if($headdata["invc_status"] == 1)
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
   ?>
</tr>
</table>
<?php
if($rdlo == "")
   $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');";
?>
<?=Nifty_printF(false)?>
</form>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
if($_REQUEST["setPosOrder"] == "prodnumber")
{  ?>
   <script language="JavaScript">
      submitForm(document.form_shppos);
   </script>
   <?php
}

//----------------------------------------------------------------------------------
// if($_REQUEST["printpdf"])
//   $pdffile = doc_createInvoicesSell($CON, $_REQUEST["id"]);

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