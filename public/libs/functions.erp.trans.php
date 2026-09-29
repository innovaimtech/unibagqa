<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function revertInvoiceSell($CON, $invcid)
{
   $currtme = time();
   
   $sql = " select t1.*
            from invoices_sell t1
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   if((int)$headdata["invc_type"] == 1)
   {
      $invcparts  = getInvoiceSellParts($CON, $invcid);
      for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
      {
         if((int)$invcparts[$x]["part_dlv_id"])
         {
            $partposdata = getInvoiceSellPartsItems($CON, $invcid, $invcparts[$x]["id"]);

            for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
            {
               $row = $partposdata[$y];
               if((float)$row["item_amount"] > 0.00 && (int)$row["item_dlv_pos"] > -1)
               {
                  $sql = " update orders_delivery_items
                           set
                           item_amount_invoiced = item_amount_invoiced - {$row["item_amount"]}
                           where
                           dlv_id   = {$invcparts[$x]["part_dlv_id"]} and
                           item_id  = {$row["item_id"]} and
                           item_pos = {$row["item_dlv_pos"]}";
                  $CON->no_result($sql);
               }
            }
            //----------------------------------------------------------------------------------
            $sql = " update orders_delivery
                     set
                     dlv_status     = 2,
                     dlv_invoiced   = 0
                     where
                     id = {$invcparts[$x]["part_dlv_id"]}";
            $CON->no_result($sql);
         }
      }
   }
   
   if(($headdata["invc_type"] == 2 || $headdata["invc_type"] == 3) && (int)$headdata["invc_stockchange"])
   {
      $sql = " select id
               from orders_delivery
               where
               dlv_invoice_generated = {$invcid}";
      $dlvid = $CON->select($sql);
      $dlvid = (int)$dlvid[0]["id"];

      if($dlvid > 0)
         delOrdersDelivery($CON, $dlvid);
   }
}

//----------------------------------------------------------------------------------
function revertProdExt($CON, $id)
{
   $currtme = time();
   
   $sql = " select t1.*
            from prod_item_ext t1
            where
            t1.id = {$id}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = " select id
            from orders_delivery
            where
            dlv_prodext_generated = {$id}";
   $dlvid = $CON->select($sql);
   $dlvid = (int)$dlvid[0]["id"];

   if($dlvid > 0)
      delOrdersDelivery($CON, $dlvid);
}

//----------------------------------------------------------------------------------
function bookItemsRel($CON, $basetran)
{
   $currtme = time();
   
   $sql = " select t1.*, t2.item_title
            from tran_rel_items t1
            INNER JOIN item t2 ON t1.tran_item_id = t2.id
            where
            t1.tran_id        = {$basetran["tran_id"]} and
            t1.tran_type      = '{$basetran["tran_type"]}'";
   $relitems = $CON->select($sql);
   $trancounter = 10000;
   
   for($x = 0; $x < count($relitems) && $relitems != false; $x++)
   {
      $row        = $relitems[$x];
      $itemstid   = (int)$row["tran_st_id"];
      $st_shipped = (float)$row["tran_amount"];

      if($st_shipped > 0.00)
      {
         $newtran                               = $basetran;
         $newtran["tran_pos"]                   = $trancounter;
         $newtran["tran_amount"]                = $st_shipped;
         $newtran["tran_st_id"]                 = $itemstid;
         $newtran["item_id"]                    = $row["tran_item_id"];
         $newtran["item_type"]                  = "item";
         $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $basetran["tran_shop_id"], $itemstid, $row["tran_item_id"], "item");
         createTransaction($CON, $newtran, true);
         $trancounter++;
      }
   }
}

//----------------------------------------------------------------------------------
function bookInvoiceSell($CON, $invcid)
{
   $currtme = time();

   $sql = " select t1.*
            from invoices_sell t1
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["invc_company_id"]);

   if($headdata["invc_type"] == 1 && (int)$headdata["invc_status"] == 2)
      updateItemsInProcess($CON, $invcid, "invoicesell");

   //----------------------------------------------------------------------------------
   if(($headdata["invc_type"] == 2 || $headdata["invc_type"] == 3) && (int)$headdata["invc_stockchange"] && (int)$headdata["invc_status"] == 2)
   {
      $headdata["invc_docnumber"] = addslashes($headdata["invc_docnumber"]);

      //----------------------------------------------------------------------------------
      $invcparts  = getInvoiceSellParts($CON, $invcid);
      for($xx = 0; $xx < count($invcparts) && $invcparts != false; $xx++)
      {
         $posdata    = Array();
         $partposdata = getInvoiceSellPartsItems($CON, $invcid, $invcparts[$xx]["id"]);
         for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
            $posdata[] = $partposdata[$y];

         if(count($posdata))
         {
            $dlv_order_id = (int)$invcparts[$xx]["part_req_id"];
            $dlv_num = $headdata["invc_number"];

            $sql = " insert into orders_delivery
                     (dlv_num, dlv_cust_id, dlv_delivery_date, dlv_paymentid, dlv_status,
                      dlv_company_id, dlv_shop_id, dlv_taxes, dlv_cust_delivid,
                      dlv_transportid, dlv_docnum, dlv_weightprice_netto,
                      dlv_total_netto, dlv_total_taxes, dlv_total_brutto,
                      dlv_invoice_generated, dlv_annotation_intern, dlv_crtdat, dlv_crtusr,
                      dlv_order_id)
                     VALUES
                     ('{$dlv_num}', {$headdata["invc_cust_id"]}, {$headdata["invc_date"]}, {$headdata["invc_paymentid"]}, 1,
                      {$headdata["invc_company_id"]}, {$headdata["invc_shop_id"]}, {$headdata["invc_taxes"]},
                      {$headdata["invc_cust_delivid"]}, {$headdata["invc_transportid"]},
                      '{$headdata["invc_dlv_docnum"]}', {$headdata["invc_weightprice_netto"]}, {$headdata["invc_total_netto"]},
                      {$headdata["invc_total_taxes"]}, {$headdata["invc_total_brutto"]}, {$headdata["id"]},
                      'GENERADO POR FACTURA {$headdata["invc_number"]} / {$headdata["invc_docnumber"]}',
                      {$currtme}, {$_SESSION["user_id"]}, {$dlv_order_id})";
            $res = $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            if($res)
            {
               $dlvid = mysql_insert_id();

               //----------------------------------------------------------------------------------
               for($x = 0; $x < count($posdata) && $posdata != false; $x++)
               {
                  $posrow = $posdata[$x];
                  $posrow["item_desc"] = addslashes($posrow["item_desc"]);

                  if((int)$posrow["item_override_stockdsc_amt"] > 0)
                  {
                     $posrow["item_amount"] = $posrow["item_override_stockdsc_amt"];
                     $posrow["item_sellprice_brutto"] = round($posrow["item_sellprice_brutto"] / $posrow["item_override_stockdsc_amt"]);
                     $posrow["item_sellprice_taxes"] = round($posrow["item_sellprice_brutto"] / (100 + $posrow["item_sellprice_taxes_perc"]) * $posrow["item_sellprice_taxes_perc"]);
                     $posrow["item_sellprice_netto"] = round($posrow["item_sellprice_brutto"] - $posrow["item_sellprice_taxes"]);
                  }

                  $sql = " insert into orders_delivery_items
                           (dlv_id, item_id, item_pos, item_amount_shipped, item_type, item_sellprice_brutto, item_sellprice_taxes_perc,
                           item_sellprice_netto, item_sellprice_netto_dsc, item_sellprice_taxes, item_discount, item_discount_type,
                           item_pcat_dsc_act, item_pcat_dsc1, item_pcat_dsctype1, item_pcat_dsc2, item_pcat_dsctype2, item_pcat_dsc3,
                           item_pcat_dsctype3, item_pcat_dsc4, item_pcat_dsctype4, item_vol_act, item_vol_dsc, item_vol_dsctype,
                           item_value_act, item_value_dsc, item_value_dsctype, item_order_pos, item_st_id, item_desc, item_charges_act)
                           VALUES
                           ({$dlvid}, {$posrow["item_id"]}, {$x}, {$posrow["item_amount"]},
                            '{$posrow["item_type"]}', {$posrow["item_sellprice_brutto"]}, {$posrow["item_sellprice_taxes_perc"]},
                            {$posrow["item_sellprice_netto"]}, {$posrow["item_sellprice_netto_dsc"]}, {$posrow["item_sellprice_taxes"]},
                            {$posrow["item_discount"]}, {$posrow["item_discount_type"]}, {$posrow["item_pcat_dsc_act"]},
                            {$posrow["item_pcat_dsc1"]}, {$posrow["item_pcat_dsctype1"]}, {$posrow["item_pcat_dsc2"]}, {$posrow["item_pcat_dsctype2"]},
                            {$posrow["item_pcat_dsc3"]}, {$posrow["item_pcat_dsctype3"]}, {$posrow["item_pcat_dsc4"]}, {$posrow["item_pcat_dsctype4"]},
                            {$posrow["item_vol_act"]}, {$posrow["item_vol_dsc"]}, {$posrow["item_vol_dsctype"]}, {$posrow["item_value_act"]},
                            {$posrow["item_value_dsc"]}, {$posrow["item_value_dsctype"]}, {$posrow["item_order_pos"]}, {$posrow["item_st_id"]},
                            '{$posrow["item_desc"]}', {$posrow["item_charges_act"]})";
                  $CON->no_result($sql);

                  if((int)$posrow["item_charges_act"])
                  {
                     $sql = " select *
                              from tran_charges_used
                              where
                              tran_id        = {$invcid} and
                              tran_type      = 'invoicesell' and
                              tran_partid    = {$posrow["part_id"]} and
                              tran_pos       = {$posrow["item_pos"]}";
                     $invccharges = $CON->select($sql);

                     foreach($invccharges AS $invccharge)
                     {
                        $sql = " insert into tran_charges_used
                                 (tran_id, tran_pos, tran_type, tran_company_id, tran_shop_id,
                                  tran_amount, tran_amount_used, tran_booked, tran_partid, tran_refid, tran_crtdat, tran_crtusr)
                                 VALUES
                                 ({$dlvid}, {$x}, 'ordersdelivery', {$headdata["invc_company_id"]}, {$headdata["invc_shop_id"]},
                                  {$invccharge["tran_amount"]}, {$invccharge["tran_amount_used"]}, 0, 0, {$invccharge["tran_refid"]},
                                  {$currtme}, {$_SESSION["user_id"]})";
                        $CON->no_result($sql);
                     }
                  }
               }
               copyRelItemsToObject($CON, $invcid, "invoicesell", $dlvid, "ordersdelivery");

               $_REQUEST["_NOITF"] = 1;
               bookOrdersDelivery($CON, $dlvid);
               $_REQUEST["_NOITF"] = 0;

               $sql = " update orders_delivery
                        set
                        dlv_invoiced            = 1,
                        dlv_status              = 4
                        where
                        id = {$dlvid}";
               $CON->no_result($sql);
            }
         }
      }
   }

   /*
   //----------------------------------------------------------------------------------
   if(($headdata["invc_type"] == 2 || $headdata["invc_type"] == 3) && (int)$headdata["invc_stockchange"] && (int)$headdata["invc_status"] == 2)
   {
      $headdata["invc_docnumber"] = addslashes($headdata["invc_docnumber"]);

      //----------------------------------------------------------------------------------
      $sql = " select id
               from orders_delivery
               where
               dlv_invoice_generated = {$invcid} and
               dlv_status            = 0";
      $dlvid = $CON->select($sql);
      $dlvid = (int)$dlvid[0]["id"];

      //----------------------------------------------------------------------------------
      if($dlvid > 0)
      {
         $sql = " update orders_delivery
                  set
                  dlv_delivery_date       = {$headdata["invc_date"]},
                  dlv_paymentid           = {$headdata["invc_paymentid"]},
                  dlv_status              = 1,
                  dlv_cust_delivid        = {$headdata["invc_cust_delivid"]},
                  dlv_transportid         = {$headdata["invc_transportid"]},
                  dlv_docnum              = '{$headdata["invc_dlv_docnum"]}',
                  dlv_weightprice_netto   = {$headdata["invc_weightprice_netto"]},
                  dlv_total_netto         = {$headdata["invc_total_netto"]},
                  dlv_total_taxes         = {$headdata["invc_total_taxes"]},
                  dlv_total_brutto        = {$headdata["invc_total_brutto"]},
                  dlv_annotation_intern   = 'GENERADO POR FACTURA {$headdata["invc_number"]} / {$headdata["invc_docnumber"]}',
                  dlv_crtdat              = {$currtme}, 
                  dlv_crtusr              = {$_SESSION["user_id"]}
                  where
                  id = {$dlvid}";
         $res = $CON->no_result($sql);
                  
         $sql = " delete from orders_delivery_items
                  where
                  dlv_id = {$dlvid}";
         $CON->no_result($sql);

         $sql = " delete from tran_charges_used
                  where
                  tran_id     = {$dlvid} and
                  tran_type   = 'ordersdelivery'";
         $CON->no_result($sql);
      }
      else
      {
         //$dlv_num = createTransactionNumber($CON, $headdata["invc_company_id"], "shipmentsell");
         $dlv_num = $headdata["invc_number"];
         $sql = " insert into orders_delivery
                  (dlv_num, dlv_cust_id, dlv_delivery_date, dlv_paymentid, dlv_status,
                   dlv_company_id, dlv_shop_id, dlv_taxes, dlv_cust_delivid,
                   dlv_transportid, dlv_docnum, dlv_weightprice_netto,
                   dlv_total_netto, dlv_total_taxes, dlv_total_brutto,
                   dlv_invoice_generated, dlv_annotation_intern, dlv_crtdat, dlv_crtusr)
                  VALUES
                  ('{$dlv_num}', {$headdata["invc_cust_id"]}, {$headdata["invc_date"]}, {$headdata["invc_paymentid"]}, 1,
                   {$headdata["invc_company_id"]}, {$headdata["invc_shop_id"]}, {$headdata["invc_taxes"]},
                   {$headdata["invc_cust_delivid"]}, {$headdata["invc_transportid"]},
                   '{$headdata["invc_dlv_docnum"]}', {$headdata["invc_weightprice_netto"]}, {$headdata["invc_total_netto"]},
                   {$headdata["invc_total_taxes"]}, {$headdata["invc_total_brutto"]}, {$headdata["id"]},
                   'GENERADO POR FACTURA {$headdata["invc_number"]} / {$headdata["invc_docnumber"]}', {$currtme}, {$_SESSION["user_id"]})";
         $res = $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      if($res)
      {
         //----------------------------------------------------------------------------------
         if(!(int)$dlvid)
         {
            $sql = " select MAX(id) 'dlvid'
                     from orders_delivery
                     where
                     dlv_crtusr = {$_SESSION["user_id"]}";
            $dlvid = $CON->select($sql);
            $dlvid = $dlvid[0]["dlvid"];
         }

         //----------------------------------------------------------------------------------
         $posdata    = Array();
         $invcparts  = getInvoiceSellParts($CON, $invcid);
         for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
         {
            $partposdata = getInvoiceSellPartsItems($CON, $invcid, $invcparts[$x]["id"]);
            for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
               $posdata[] = $partposdata[$y];
         }

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            $posrow = $posdata[$x];
            $posrow["item_desc"] = addslashes($posrow["item_desc"]);

            if((int)$posrow["item_override_stockdsc_amt"] > 0)
            {
               $posrow["item_amount"] = $posrow["item_override_stockdsc_amt"];
               $posrow["item_sellprice_brutto"] = round($posrow["item_sellprice_brutto"] / $posrow["item_override_stockdsc_amt"]);
               $posrow["item_sellprice_taxes"] = round($posrow["item_sellprice_brutto"] / (100 + $posrow["item_sellprice_taxes_perc"]) * $posrow["item_sellprice_taxes_perc"]);
               $posrow["item_sellprice_netto"] = round($posrow["item_sellprice_brutto"] - $posrow["item_sellprice_taxes"]);
            }

            $sql = " insert into orders_delivery_items
                     (dlv_id, item_id, item_pos, item_amount_shipped, item_type, item_sellprice_brutto, item_sellprice_taxes_perc,
                     item_sellprice_netto, item_sellprice_netto_dsc, item_sellprice_taxes, item_discount, item_discount_type,
                     item_pcat_dsc_act, item_pcat_dsc1, item_pcat_dsctype1, item_pcat_dsc2, item_pcat_dsctype2, item_pcat_dsc3,
                     item_pcat_dsctype3, item_pcat_dsc4, item_pcat_dsctype4, item_vol_act, item_vol_dsc, item_vol_dsctype,
                     item_value_act, item_value_dsc, item_value_dsctype, item_order_pos, item_st_id, item_desc, item_charges_act)
                     VALUES
                     ({$dlvid}, {$posrow["item_id"]}, {$x}, {$posrow["item_amount"]},
                      '{$posrow["item_type"]}', {$posrow["item_sellprice_brutto"]}, {$posrow["item_sellprice_taxes_perc"]},
                      {$posrow["item_sellprice_netto"]}, {$posrow["item_sellprice_netto_dsc"]}, {$posrow["item_sellprice_taxes"]},
                      {$posrow["item_discount"]}, {$posrow["item_discount_type"]}, {$posrow["item_pcat_dsc_act"]},
                      {$posrow["item_pcat_dsc1"]}, {$posrow["item_pcat_dsctype1"]}, {$posrow["item_pcat_dsc2"]}, {$posrow["item_pcat_dsctype2"]},
                      {$posrow["item_pcat_dsc3"]}, {$posrow["item_pcat_dsctype3"]}, {$posrow["item_pcat_dsc4"]}, {$posrow["item_pcat_dsctype4"]},
                      {$posrow["item_vol_act"]}, {$posrow["item_vol_dsc"]}, {$posrow["item_vol_dsctype"]}, {$posrow["item_value_act"]},
                      {$posrow["item_value_dsc"]}, {$posrow["item_value_dsctype"]}, {$posrow["item_order_pos"]}, {$posrow["item_st_id"]},
                      '{$posrow["item_desc"]}', {$posrow["item_charges_act"]})";
            $CON->no_result($sql);

            if((int)$posrow["item_charges_act"])
            {
               $sql = " select *
                        from tran_charges_used
                        where
                        tran_id        = {$invcid} and
                        tran_type      = 'invoicesell' and
                        tran_partid    = {$posrow["part_id"]} and
                        tran_pos       = {$posrow["item_pos"]}";
               $invccharges = $CON->select($sql);

               foreach($invccharges AS $invccharge)
               {
                  $sql = " insert into tran_charges_used
                           (tran_id, tran_pos, tran_type, tran_company_id, tran_shop_id,
                            tran_amount, tran_amount_used, tran_booked, tran_partid, tran_refid, tran_crtdat, tran_crtusr)
                           VALUES
                           ({$dlvid}, {$x}, 'ordersdelivery', {$headdata["invc_company_id"]}, {$headdata["invc_shop_id"]},
                            {$invccharge["tran_amount"]}, {$invccharge["tran_amount_used"]}, 0, 0, {$invccharge["tran_refid"]},
                            {$currtme}, {$_SESSION["user_id"]})";
                  $CON->no_result($sql);
               }
            }
         }
         copyRelItemsToObject($CON, $invcid, "invoicesell", $dlvid, "ordersdelivery");

         $_REQUEST["_NOITF"] = 1;
         bookOrdersDelivery($CON, $dlvid);
         $_REQUEST["_NOITF"] = 0;

         $sql = " update orders_delivery
                  set
                  dlv_invoiced            = 1,
                  dlv_status              = 4
                  where
                  id = {$dlvid}";
         $CON->no_result($sql);
      }
   }
   */
}

//----------------------------------------------------------------------------------
function bookInvoiceSellBol($CON, $invcid)
{
   $currtme = time();
   
   $sql = " select t1.*
            from invoices_sell_bol t1
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["invc_company_id"]);

   //----------------------------------------------------------------------------------
   if((int)$headdata["invc_stockchange"] && (int)$headdata["invc_status"] == 2)
   {
      $headdata["invc_docnumber"] = addslashes($headdata["invc_docnumber"]);

      //----------------------------------------------------------------------------------
      $sql = " select id
               from orders_delivery
               where
               dlv_invoice_bol_generated = {$invcid} and
               dlv_status            = 0";
      $dlvid = $CON->select($sql);
      $dlvid = (int)$dlvid[0]["id"];

      //----------------------------------------------------------------------------------
      if($dlvid > 0)
      {
         $sql = " update orders_delivery
                  set
                  dlv_delivery_date       = {$headdata["invc_date"]},
                  dlv_paymentid           = {$headdata["invc_paymentid"]},
                  dlv_status              = 1,
                  dlv_cust_delivid        = {$headdata["invc_cust_delivid"]},
                  dlv_transportid         = {$headdata["invc_transportid"]},
                  dlv_docnum              = '{$headdata["invc_dlv_docnum"]}',
                  dlv_weightprice_netto   = {$headdata["invc_weightprice_netto"]},
                  dlv_total_netto         = {$headdata["invc_total_netto"]},
                  dlv_total_taxes         = {$headdata["invc_total_taxes"]},
                  dlv_total_brutto        = {$headdata["invc_total_brutto"]},
                  dlv_annotation_intern   = 'GENERADO POR BOLETA {$headdata["invc_number"]} / {$headdata["invc_docnumber"]}',
                  dlv_crtdat              = {$currtme}, 
                  dlv_crtusr              = {$_SESSION["user_id"]}
                  where
                  id = {$dlvid}";
         $res = $CON->no_result($sql);
                  
         $sql = " delete from orders_delivery_items
                  where
                  dlv_id = {$dlvid}";
         $CON->no_result($sql);

         $sql = " delete from tran_charges_used
                  where
                  tran_id     = {$dlvid} and
                  tran_type   = 'ordersdelivery'";
         $CON->no_result($sql);
      }
      else
      {
         //$dlv_num = createTransactionNumber($CON, $headdata["invc_company_id"], "shipmentsell");
         $dlv_num = $headdata["invc_number"];
         $sql = " insert into orders_delivery
                  (dlv_num, dlv_cust_id, dlv_delivery_date, dlv_paymentid, dlv_status,
                   dlv_company_id, dlv_shop_id, dlv_taxes, dlv_cust_delivid,
                   dlv_transportid, dlv_docnum, dlv_weightprice_netto,
                   dlv_total_netto, dlv_total_taxes, dlv_total_brutto,
                   dlv_invoice_bol_generated, dlv_annotation_intern, dlv_crtdat, dlv_crtusr)
                  VALUES
                  ('{$dlv_num}', {$headdata["invc_cust_id"]}, {$headdata["invc_date"]}, {$headdata["invc_paymentid"]}, 1,
                   {$headdata["invc_company_id"]}, {$headdata["invc_shop_id"]}, {$headdata["invc_taxes"]},
                   {$headdata["invc_cust_delivid"]}, {$headdata["invc_transportid"]},
                   '{$headdata["invc_dlv_docnum"]}', {$headdata["invc_weightprice_netto"]}, {$headdata["invc_total_netto"]},
                   {$headdata["invc_total_taxes"]}, {$headdata["invc_total_brutto"]}, {$headdata["id"]},
                   'GENERADO POR BOLETA {$headdata["invc_number"]} / {$headdata["invc_docnumber"]}', {$currtme}, {$_SESSION["user_id"]})";
         $res = $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      if($res)
      {
         //----------------------------------------------------------------------------------
         if(!(int)$dlvid)
         {
            $sql = " select MAX(id) 'dlvid'
                     from orders_delivery
                     where
                     dlv_crtusr = {$_SESSION["user_id"]}";
            $dlvid = $CON->select($sql);
            $dlvid = $dlvid[0]["dlvid"];
         }

         //----------------------------------------------------------------------------------
         $posdata    = Array();
         $invcparts  = getInvoiceSellParts($CON, $invcid, "_bol");
         for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
         {
            $partposdata = getInvoiceSellPartsItems($CON, $invcid, $invcparts[$x]["id"], "", "_bol");
            for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
               $posdata[] = $partposdata[$y];
         }

         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            $posrow = $posdata[$x];
            $posrow["item_desc"] = addslashes($posrow["item_desc"]);

            if((int)$posrow["item_override_stockdsc_amt"] > 0)
            {
               $posrow["item_amount"] = $posrow["item_override_stockdsc_amt"];
               $posrow["item_sellprice_brutto"] = round($posrow["item_sellprice_brutto"] / $posrow["item_override_stockdsc_amt"]);
               $posrow["item_sellprice_taxes"] = round($posrow["item_sellprice_brutto"] / (100 + $posrow["item_sellprice_taxes_perc"]) * $posrow["item_sellprice_taxes_perc"]);
               $posrow["item_sellprice_netto"] = round($posrow["item_sellprice_brutto"] - $posrow["item_sellprice_taxes"]);
            }

            $sql = " insert into orders_delivery_items
                     (dlv_id, item_id, item_pos, item_amount_shipped, item_type, item_sellprice_brutto, item_sellprice_taxes_perc,
                     item_sellprice_netto, item_sellprice_netto_dsc, item_sellprice_taxes, item_discount, item_discount_type,
                     item_pcat_dsc_act, item_pcat_dsc1, item_pcat_dsctype1, item_pcat_dsc2, item_pcat_dsctype2, item_pcat_dsc3,
                     item_pcat_dsctype3, item_pcat_dsc4, item_pcat_dsctype4, item_vol_act, item_vol_dsc, item_vol_dsctype,
                     item_value_act, item_value_dsc, item_value_dsctype, item_order_pos, item_st_id, item_desc, item_charges_act)
                     VALUES
                     ({$dlvid}, {$posrow["item_id"]}, {$x}, {$posrow["item_amount"]},
                      '{$posrow["item_type"]}', {$posrow["item_sellprice_brutto"]}, {$posrow["item_sellprice_taxes_perc"]},
                      {$posrow["item_sellprice_netto"]}, {$posrow["item_sellprice_netto_dsc"]}, {$posrow["item_sellprice_taxes"]},
                      {$posrow["item_discount"]}, {$posrow["item_discount_type"]}, {$posrow["item_pcat_dsc_act"]},
                      {$posrow["item_pcat_dsc1"]}, {$posrow["item_pcat_dsctype1"]}, {$posrow["item_pcat_dsc2"]}, {$posrow["item_pcat_dsctype2"]},
                      {$posrow["item_pcat_dsc3"]}, {$posrow["item_pcat_dsctype3"]}, {$posrow["item_pcat_dsc4"]}, {$posrow["item_pcat_dsctype4"]},
                      {$posrow["item_vol_act"]}, {$posrow["item_vol_dsc"]}, {$posrow["item_vol_dsctype"]}, {$posrow["item_value_act"]},
                      {$posrow["item_value_dsc"]}, {$posrow["item_value_dsctype"]}, {$posrow["item_order_pos"]}, {$posrow["item_st_id"]},
                      '{$posrow["item_desc"]}', {$posrow["item_charges_act"]})";
            $CON->no_result($sql);

         }

         $_REQUEST["_NOITF"] = 1;
         bookOrdersDelivery($CON, $dlvid);
         $_REQUEST["_NOITF"] = 0;

         $sql = " update orders_delivery
                  set
                  dlv_invoiced            = 1,
                  dlv_status              = 4
                  where
                  id = {$dlvid}";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
function bookInvoiceBuyNotes($CON, $noteid)
{
   $currtme = time();
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from invoices_notes_buy t1
            where
            t1.id = {$noteid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $basetran["tran_id"]          = $headdata["id"];
   $basetran["tran_type"]        = "invoicesnotessbuy";
   $basetran["tran_number"]      = $headdata["note_number"];
   $basetran["tran_company_id"]  = $headdata["note_company_id"];
   $basetran["tran_shop_id"]     = $headdata["note_shop_id"];
   $basetran["tran_supplier_id"] = $headdata["note_supplier_id"];
   $basetran["tran_day"]         = (int)date('d', $headdata["note_date"]);
   $basetran["tran_month"]       = (int)date('m', $headdata["note_date"]);
   $basetran["tran_year"]        = (int)date('Y', $headdata["note_date"]);
   $basetran["tran_crtusr"]      = $_SESSION["user_id"];
   $basetran["tran_crtdat"]      = $currtme;

   //----------------------------------------------------------------------------------
   if((int)$headdata["note_status"] == 2 && $headdata["note_is_discount"] == 1 &&
      (int)$headdata["note_parent_invcid"] && $headdata["note_discount_perc"] > 0.00)
   {
      $_INVCSDESC[$headdata["note_parent_invcid"]]  = 1;

      $sql = " select distinct note_parent_invcid
               from invoices_notes_buy_otherdscinvc
               where
               note_id = {$headdata["id"]}";
      $otherinvcs = $CON->select($sql);
      foreach($otherinvcs AS $otherinvc)
         $_INVCSDESC[$otherinvc["note_parent_invcid"]]  = 1;

      foreach(array_keys($_INVCSDESC) AS $invcdescid)
      {
         $sql = " select *
                  from tran_average_costprices_hist
                  where
                  tran_id = {$invcdescid} and
                  tran_type = 'invoicesbuy'";
         $avgitems = $CON->select($sql);
         foreach($avgitems AS $avgitem)
         {
            //----------------------------------------------------------------------------------
            $old_netto = $avgitem["item_costprice_netto"] * $avgitem["tran_amount"];
            $new_netto = ($avgitem["item_costprice_netto"] - ($avgitem["item_costprice_netto"] / 100 * $headdata["note_discount_perc"])) * $avgitem["tran_amount"];
            $dif_netto = $old_netto - $new_netto;

            //----------------------------------------------------------------------------------
            $sql = " update tran_average_costprices_hist
                     set
                     item_costprice_netto = item_costprice_netto - (item_costprice_netto / 100 * {$headdata["note_discount_perc"]})
                     where
                     id = {$avgitem["id"]}";
            $CON->no_result($sql);

            //----------------------------------------------------------------------------------
            $sql = " select *
                     from tran_average_costprices
                     where
                     item_id     = {$avgitem["item_id"]} and
                     company_id  = {$headdata["note_company_id"]}";
            $currentavgcost = $CON->select($sql);
            $currentavgcost = $currentavgcost[0];

            //----------------------------------------------------------------------------------
            $compstock  = 0.00;
            $shops      = getShops($CON);
            foreach($shops AS $shop)
            {
               if($shop["shop_company_id"] == $headdata["note_company_id"])
                  $compstock += getItemShopCurrentStock($CON, $shop["id"], $avgitem["item_id"], "item");
            }

            //----------------------------------------------------------------------------------
            $compcost    = $compstock * $currentavgcost["item_costprice_avg_netto"];
            $compcost   -= $dif_netto;
            $newavgcost  = $compcost / $compstock;

         }
      }
   }

   //----------------------------------------------------------------------------------
   if((int)$headdata["note_status"] == 2 && (int)$headdata["note_stockchange"])
   {
      //----------------------------------------------------------------------------------
      $posdata    = getInvoiceBuyNoteItems($CON, $noteid);
      $trancounter = 0;

      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row        = $posdata[$x];
         $itemstid   = (int)$row["item_st_id"];
         $st_shipped = (float)$row["item_amount"];

         if($st_shipped > 0.00)
         {
            if($row["item_type"] == "item")
            {
               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTransUsed($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     $newtran                               = $basetran;
                     $newtran["tran_pos"]                   = $trancounter;
                     $newtran["tran_amount"]                = $item_charge["tran_amount_used"];
                     $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                     $newtran["item_id"]                    = $item_charge["tran_item_id"];
                     $newtran["item_type"]                  = "item";
                     $newtran["item_charges_id"]            = $item_charge["tran_refid"];
                     $newtran["item_charges_used_id"]       = $item_charge["id"];
                     $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");
                     createTransaction($CON, $newtran, true);
                     $trancounter++;
                  }
               }
               else
               {
                  if((int)$row["item_subitem_id"])
                  {
                     $row["item_id"] = $row["item_subitem_id"];
                     $st_shipped     = round($st_shipped * $row["item_subitem_amount"],2);
                  }
                     
                  $newtran                               = $basetran;
                  $newtran["tran_pos"]                   = $trancounter;
                  $newtran["tran_amount"]                = $st_shipped;
                  $newtran["tran_st_id"]                 = $itemstid;
                  $newtran["item_id"]                    = $row["item_id"];
                  $newtran["item_type"]                  = $row["item_type"];
                  $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $itemstid, $row["item_id"], $row["item_type"]);
                  createTransaction($CON, $newtran, true);
                  $trancounter++;
               }
            }

            //----------------------------------------------------------------------------------
            elseif($row["item_type"] == "itemlist")
            {
               $itemlistpos = getItemListContent($CON, $row["item_id"]);
               
               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     if($item_charge["tran_amount_used"] > 0.00)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_used"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_parent_id"]             = $row["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["tran_refid"];
                        $newtran["item_charges_used_id"]       = $item_charge["id"];

                        /*
                        $item_fullprice_netto                  = $row["item_costprice_netto_dsc"];
                        $newtran["item_costprice_netto"]       = $item_fullprice_netto / $itemlistpos[0]["item_amount"] * $st_shipped;
                        $newtran["item_costprice_taxes_perc"]  = (float)$row["item_costprice_taxes_perc"];
                        $newtran["item_costprice_taxes"]       = $newtran["item_costprice_netto"] / 100 * $newtran["item_costprice_taxes_perc"];
                        $newtran["item_costprice_brutto"]      = $newtran["item_costprice_netto"] + $newtran["item_costprice_taxes"];
                        */
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");

                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
               else
               {
                  foreach($itemlistpos AS $itemlistrow)
                  {
                     $amount_calced = (float)$itemlistrow["item_amount"] * $row["item_amount"];

                     if($amount_calced > 0.00)
                     {
                        //$costdata = getSupplierItemCosts($CON, 0, $itemlistrow["item_id"], "item");

                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $amount_calced;
                        $newtran["tran_st_id"]                 = $itemstid;
                        $newtran["item_id"]                    = $itemlistrow["item_id"];
                        $newtran["item_parent_id"]             = $row["item_id"];
                        $newtran["item_type"]                  = "item";
                        /*
                        $item_fullprice_netto                  = $row["item_costprice_netto_dsc"];
                        $newtran["item_costprice_netto"]       = $item_fullprice_netto / $amount_calced;
                        $newtran["item_costprice_taxes_perc"]  = (float)$row["item_costprice_taxes_perc"];
                        $newtran["item_costprice_taxes"]       = $newtran["item_costprice_netto"] / 100 * $newtran["item_costprice_taxes_perc"];
                        $newtran["item_costprice_brutto"]      = $newtran["item_costprice_netto"] + $newtran["item_costprice_taxes"];
                        */
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $itemstid, $itemlistrow["item_id"], "item");
                        
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
            }
         }
      }

      $sql = " update invoices_notes_buy
               set
               note_remote_synced = 0
               where
               id = {$noteid}";
      $CON->no_result($sql);
      
      return true;
   }
   else
   {
      $sql = " update invoices_notes_buy
               set
               note_remote_synced = 0
               where
               id = {$noteid}";
      $CON->no_result($sql);
      return false;
   }
}


//----------------------------------------------------------------------------------
function bookInvoiceSellNotes($CON, $noteid)
{
   $currtme = time();
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from invoices_notes_sell t1
            where
            t1.id = {$noteid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $basetran["tran_id"]          = $headdata["id"];

   if((int)$headdata["note_type"] == 2)
      $basetran["tran_type"] = "invoicesnotesselb";
   else
      $basetran["tran_type"] = "invoicesnotessell";
   $basetran["tran_number"]      = $headdata["note_number"];
   $basetran["tran_company_id"]  = $headdata["note_company_id"];
   $basetran["tran_shop_id"]     = $headdata["note_shop_id"];
   $basetran["tran_cust_id"]     = $headdata["note_cust_id"];
   $basetran["tran_day"]         = (int)date('d', $headdata["note_date"]);
   $basetran["tran_month"]       = (int)date('m', $headdata["note_date"]);
   $basetran["tran_year"]        = (int)date('Y', $headdata["note_date"]);
   $basetran["tran_crtusr"]      = $_SESSION["user_id"];
   $basetran["tran_crtdat"]      = $currtme;

   //----------------------------------------------------------------------------------
   if((int)$headdata["note_status"] == 2)
   {
      $_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["note_company_id"]);
      /*
      if((int)$_INVCCFG["company_invc_mode"])
      {
         $sql = " update invoices_notes_sell
                  set
                  note_docnumber = 'PENDIENTE_SII'
                  where
                  id = {$noteid}";
         $CON->no_result($sql);
      }
      */
   }

   if((int)$headdata["note_status"] == 2 && (int)$headdata["note_stockchange"])
   {
      //----------------------------------------------------------------------------------
      $posdata    = getInvoiceSellNoteItems($CON, $noteid);
      $trancounter = 0;

      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row        = $posdata[$x];
         $itemstid   = (int)$row["item_st_id"];
         $st_shipped = (float)$row["item_amount"];

         if($st_shipped > 0.00)
         {
            if($row["item_type"] == "item")
            {
                if((int)$row["item_charges_act"])
                {
                   $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                   foreach($item_charges AS $item_charge)
                   {
                      $newtran                               = $basetran;
                      $newtran["tran_pos"]                   = $trancounter;
                      $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                      $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                      $newtran["item_id"]                    = $item_charge["tran_item_id"];
                      $newtran["item_type"]                  = "item";
                      $newtran["item_charges_id"]            = $item_charge["id"];
                      $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");
                      createTransaction($CON, $newtran, true);
                      $trancounter++;
                   }
                }
                else
                {
                  $newtran                               = $basetran;
                  $newtran["tran_pos"]                   = $trancounter;
                  $newtran["tran_amount"]                = $st_shipped;
                  $newtran["tran_st_id"]                 = $itemstid;
                  $newtran["item_id"]                    = $row["item_id"];
                  $newtran["item_type"]                  = $row["item_type"];
                  $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $itemstid, $row["item_id"], $row["item_type"]);
                  createTransaction($CON, $newtran, true);
                  $trancounter++;
               }
            }

            //----------------------------------------------------------------------------------
            elseif($row["item_type"] == "itemlist")
            {
               $itemlistpos = getItemListContent($CON, $row["item_id"]);

               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     if($item_charge["tran_amount_avail"] > 0.00)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_parent_id"]             = $item_charge["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["id"];
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
               else
               {
                  foreach($itemlistpos AS $itemlistrow)
                  {
                     $amount_calced = (float)$itemlistrow["item_amount"] * $row["item_amount"];

                     if($amount_calced > 0.00)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $amount_calced;
                        $newtran["tran_st_id"]                 = $itemstid;
                        $newtran["item_id"]                    = $itemlistrow["item_id"];
                        $newtran["item_parent_id"]             = $row["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $itemstid, $itemlistrow["item_id"], "item");
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
            }
         }
      }
      return true;
   }
   else
      return false;
}

//----------------------------------------------------------------------------------
function bookProdExt($CON, $id)
{
   $currtme = time();
   
   $sql = " select t1.*
            from prod_item_ext t1
            where
            t1.id = {$id}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $headdata["req_dlv_docnum"] = addslashes($headdata["req_dlv_docnum"]);
   $headdata["req_desc"]       = addslashes($headdata["req_desc"]);

   //----------------------------------------------------------------------------------
   $sql = " select id
            from orders_delivery
            where
            dlv_prodext_generated = {$id} and
            dlv_status            = 0";
   $dlvid = $CON->select($sql);
   $dlvid = (int)$dlvid[0]["id"];

   //----------------------------------------------------------------------------------
   if($dlvid > 0)
   {
      $sql = " update orders_delivery
               set
               dlv_delivery_date       = {$headdata["req_delivery_date"]},
               dlv_paymentid           = 0,
               dlv_status              = 1,
               dlv_cust_delivid        = 0,
               dlv_transportid         = 0,
               dlv_docnum              = '{$headdata["req_dlv_docnum"]}',
               dlv_weightprice_netto   = 0,
               dlv_total_netto         = 0,
               dlv_total_taxes         = 0,
               dlv_total_brutto        = 0,
               dlv_annotation          = '{$headdata["req_desc"]}',
               dlv_annotation_intern   = 'GENERADO POR MODIFICACION EXTERNA ".sprintf("%07s",$headdata["id"])."',
               dlv_crtdat              = {$currtme},
               dlv_crtusr              = {$_SESSION["user_id"]}
               where
               id = {$dlvid}";
      $res = $CON->no_result($sql);

      $sql = " delete from orders_delivery_items
               where
               dlv_id = {$dlvid}";
      $CON->no_result($sql);
   }
   else
   {
      $dlv_num = createTransactionNumber($CON, $headdata["req_company_id"], "shipmentsell");
      $sql = " insert into orders_delivery
               (dlv_num, dlv_supplier_id, dlv_delivery_date, dlv_paymentid, dlv_status,
                dlv_company_id, dlv_shop_id, dlv_taxes, dlv_cust_delivid,
                dlv_transportid, dlv_docnum, dlv_weightprice_netto,
                dlv_total_netto, dlv_total_taxes, dlv_total_brutto,
                dlv_prodext_generated, dlv_annotation_intern, dlv_annotation,
                dlv_mode, dlv_crtdat, dlv_crtusr)
               VALUES
               ('{$dlv_num}', {$headdata["req_supplier_id"]}, {$headdata["req_delivery_date"]}, 0, 1,
                {$headdata["req_company_id"]}, {$headdata["req_shop_id"]}, 0, 0, 0,
                '{$headdata["req_dlv_docnum"]}', 0, 0,
                0, 0, {$headdata["id"]},
                'GENERADO POR MODIFICACION EXTERNA ".sprintf("%07s",$headdata["id"])."', '{$headdata["req_desc"]}',
                4, {$currtme}, {$_SESSION["user_id"]})";
      $res = $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if($res)
   {
      //----------------------------------------------------------------------------------
      if(!(int)$dlvid)
      {
         $sql = " select MAX(id) 'dlvid'
                  from orders_delivery
                  where
                  dlv_crtusr = {$_SESSION["user_id"]}";
         $dlvid = $CON->select($sql);
         $dlvid = $dlvid[0]["dlvid"];
      }

      //----------------------------------------------------------------------------------
      $posdata  = getProdExtPos($CON, $id, "prodnumber");

      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $posrow = $posdata[$x];
         $posrow["item_desc"] = addslashes($posrow["item_desc"]);

         $sql = " insert into orders_delivery_items
                  (dlv_id, item_id, item_pos, item_amount_shipped, item_type, item_st_id, item_desc)
                  VALUES
                  ({$dlvid}, {$posrow["item_id"]}, {$posrow["item_pos"]}, {$posrow["item_amount"]},
                   '{$posrow["item_type"]}',  {$posrow["item_st_id"]}, '{$posrow["item_desc"]}')";
         $CON->no_result($sql);
      }

      bookOrdersDelivery($CON, $dlvid);

      $sql = " update orders_delivery
               set
               dlv_status              = 3,
               dlv_invoiced            = 1
               where
               id = {$dlvid}";
      $res = $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function bookInvoiceBuy($CON, $invcid)
{
   $currtme = time();
   
   $sql = " select t1.*
            from invoices_buy t1
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $basetran["tran_id"]          = $headdata["id"];
   $basetran["tran_type"]        = "invoicesbuy";
   $basetran["tran_number"]      = $headdata["invc_number"];
   $basetran["tran_company_id"]  = $headdata["invc_company_id"];
   $basetran["tran_shop_id"]     = $headdata["invc_shop_id"];
   $basetran["tran_supplier_id"] = $headdata["invc_supplier_id"];
   $basetran["tran_day"]         = (int)date('d', $headdata["invc_date"]);
   $basetran["tran_month"]       = (int)date('m', $headdata["invc_date"]);
   $basetran["tran_year"]        = (int)date('Y', $headdata["invc_date"]);
   $basetran["tran_issueid"]     = 0;
   $basetran["tran_crtusr"]      = $_SESSION["user_id"];
   $basetran["tran_crtdat"]      = $currtme;

   if((int)$headdata["invc_status"] == 2)
   {
      $invcparts     = getInvoiceBuyParts($CON, $invcid);
      $trancounter   = 0;
      
      for($y = 0; $y < count($invcparts) && $invcparts != false; $y++)
      {
         $posdata = getInvoiceBuyPartsItems($CON, $invcid, $invcparts[$y]["id"]);
         
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            $row        = $posdata[$x];
            $itemstid   = (int)$row["item_st_id"];
            $st_shipped = (float)$row["item_amount"];

            if($st_shipped > 0.00)
            {
               if($row["item_type"] == "item" && (int)$headdata["invc_importation"] && (float)$headdata["invc_exc_rate"] > 0.00)
               {
                  $item_costprice_usd = round($row["item_costprice_netto"],2);
                  $sql = " update item_suppliers
                           set
                           item_costprice_netto    = {$row["item_costprice_import_item"]},
                           item_costprice_brutto   = {$row["item_costprice_import_item"]},
                           item_costprice_usd      = {$item_costprice_usd}
                           where
                           item_id     = {$row["item_id"]} and
                           supplier_id = {$headdata["invc_supplier_id"]}";
                  $CON->no_result($sql);
               }
               elseif($row["item_type"] == "itemlist" && (int)$headdata["invc_importation"] && (float)$headdata["invc_exc_rate"] > 0.00)
               {
                  $sql = " update itemlist_suppliers
                           set
                           item_costprice_netto    = {$row["item_costprice_import_item"]},
                           item_costprice_brutto   = {$row["item_costprice_import_item"]}
                           where
                           item_id     = {$row["item_id"]} and
                           supplier_id = {$headdata["invc_supplier_id"]}";
                  $CON->no_result($sql);
               }
               if($row["item_type"] == "item" && !(int)$headdata["invc_importation"])
               {
                  $sql = " update item_suppliers
                           set
                           item_costprice_netto       = {$row["item_costprice_netto"]},
                           item_costprice_brutto      = {$row["item_costprice_brutto"]},
                           item_costprice_taxes_perc  = {$row["item_costprice_taxes_perc"]},
                           item_costprice_taxes       = {$row["item_costprice_taxes"]}
                           where
                           item_id     = {$row["item_id"]} and
                           supplier_id = {$headdata["invc_supplier_id"]}";
                  $CON->no_result($sql);
                  registerCostPriceHistory($CON, $row["item_id"], "item");
               }
               elseif($row["item_type"] == "itemlist" && !(int)$headdata["invc_importation"])
               {
                  $sql = " update itemlist_suppliers
                           set
                           item_costprice_netto       = {$row["item_costprice_netto"]},
                           item_costprice_brutto      = {$row["item_costprice_brutto"]},
                           item_costprice_taxes_perc  = {$row["item_costprice_taxes_perc"]},
                           item_costprice_taxes       = {$row["item_costprice_taxes"]}
                           where
                           item_id     = {$row["item_id"]} and
                           supplier_id = {$headdata["invc_supplier_id"]}";
                  $CON->no_result($sql);
                  registerCostPriceHistory($CON, $row["item_id"], "itemlist");
               }
               
               if($row["item_type"] == "item")
               {
                  if((int)$row["item_charges_act"])
                  {
                     $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"], $invcparts[$y]["id"]);
                     foreach($item_charges AS $item_charge)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["id"];

                        $item_fullprice_netto                  = $row["item_costprice_netto_dsc2"];

                        if((int)$headdata["invc_importation"])
                           $item_fullprice_netto = $item_fullprice_netto * $headdata["invc_exc_rate"];
                           
                        $newtran["item_costprice_netto"]       = $item_fullprice_netto / $st_shipped;
                        $newtran["item_costprice_taxes_perc"]  = (float)$row["item_costprice_taxes_perc"];
                        $newtran["item_costprice_taxes"]       = $newtran["item_costprice_netto"] / 100 * $newtran["item_costprice_taxes_perc"];
                        $newtran["item_costprice_brutto"]      = $newtran["item_costprice_netto"] + $newtran["item_costprice_taxes"];
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");
                        $newtran["item_costprice_avg_netto"]   = updateItemAverageCost($CON, $newtran, $headdata["invc_stockchange"]);

                        if((int)$headdata["invc_stockchange"])
                        {
                           createTransaction($CON, $newtran, true);
                           $trancounter++;
                        }
                     }
                  }
                  else
                  {
                     if((int)$row["item_subitem_id"])
                     {
                        $row["item_id"] = $row["item_subitem_id"];
                        $st_shipped     = round($st_shipped * $row["item_subitem_amount"],2);
                     }
   
                     $newtran                               = $basetran;
                     $newtran["tran_pos"]                   = $trancounter;
                     $newtran["tran_amount"]                = $st_shipped;
                     $newtran["tran_st_id"]                 = $itemstid;
                     $newtran["item_id"]                    = $row["item_id"];
                     $newtran["item_type"]                  = $row["item_type"];
                     $item_fullprice_netto                  = $row["item_costprice_netto_dsc2"];

                     if((int)$headdata["invc_importation"])
                        $item_fullprice_netto = $item_fullprice_netto * $headdata["invc_exc_rate"];
                           
                     $newtran["item_costprice_netto"]       = $item_fullprice_netto / $st_shipped;
                     $newtran["item_costprice_taxes_perc"]  = (float)$row["item_costprice_taxes_perc"];
                     $newtran["item_costprice_taxes"]       = $newtran["item_costprice_netto"] / 100 * $newtran["item_costprice_taxes_perc"];
                     $newtran["item_costprice_brutto"]      = $newtran["item_costprice_netto"] + $newtran["item_costprice_taxes"];
                     $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $itemstid, $row["item_id"], $row["item_type"]);
                     $newtran["item_costprice_avg_netto"]   = updateItemAverageCost($CON, $newtran, $headdata["invc_stockchange"]);

                     if((int)$headdata["invc_stockchange"])
                     {
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }

               //----------------------------------------------------------------------------------
               elseif($row["item_type"] == "itemlist")
               {
                  $itemlistpos = getItemListContent($CON, $row["item_id"]);

                  if((int)$row["item_charges_act"])
                  {
                     $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"], $invcparts[$y]["id"]);
                     foreach($item_charges AS $item_charge)
                     {
                        if($item_charge["tran_amount_avail"] > 0.00)
                        {
                           $newtran                               = $basetran;
                           $newtran["tran_pos"]                   = $trancounter;
                           $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                           $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                           $newtran["item_id"]                    = $item_charge["tran_item_id"];
                           $newtran["item_parent_id"]             = $item_charge["item_id"];
                           $newtran["item_type"]                  = "item";
                           $newtran["item_charges_id"]            = $item_charge["id"];

                           $item_fullprice_netto                  = $row["item_costprice_netto_dsc2"];
                           
                           if((int)$headdata["invc_importation"])
                              $item_fullprice_netto = $item_fullprice_netto * $headdata["invc_exc_rate"];
                           
                           $newtran["item_costprice_netto"]       = $item_fullprice_netto / $itemlistpos[0]["item_amount"] / $st_shipped;
                           $newtran["item_costprice_taxes_perc"]  = (float)$row["item_costprice_taxes_perc"];
                           $newtran["item_costprice_taxes"]       = $newtran["item_costprice_netto"] / 100 * $newtran["item_costprice_taxes_perc"];
                           $newtran["item_costprice_brutto"]      = $newtran["item_costprice_netto"] + $newtran["item_costprice_taxes"];
                           $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");
                           $newtran["item_costprice_avg_netto"]   = updateItemAverageCost($CON, $newtran, $headdata["invc_stockchange"]);

                           if((int)$headdata["invc_stockchange"])
                           {
                              createTransaction($CON, $newtran, true);
                              $trancounter++;
                           }
                        }
                     }
                  }
                  else
                  {
                     foreach($itemlistpos AS $itemlistrow)
                     {
                        $amount_calced = (float)$itemlistrow["item_amount"] * $st_shipped;

                        if($amount_calced > 0.00)
                        {
                           $newtran                               = $basetran;
                           $newtran["tran_pos"]                   = $trancounter;
                           $newtran["tran_amount"]                = $amount_calced;
                           $newtran["tran_st_id"]                 = $itemstid;
                           $newtran["item_id"]                    = $itemlistrow["item_id"];
                           $newtran["item_parent_id"]             = $row["item_id"];
                           $newtran["item_type"]                  = "item";

                           $item_fullprice_netto                  = $row["item_costprice_netto_dsc2"];

                           if((int)$headdata["invc_importation"])
                              $item_fullprice_netto = $item_fullprice_netto * $headdata["invc_exc_rate"];
                           
                           $newtran["item_costprice_netto"]       = $item_fullprice_netto / $amount_calced;
                           $newtran["item_costprice_taxes_perc"]  = (float)$row["item_costprice_taxes_perc"];
                           $newtran["item_costprice_taxes"]       = $newtran["item_costprice_netto"] / 100 * $newtran["item_costprice_taxes_perc"];
                           $newtran["item_costprice_brutto"]      = $newtran["item_costprice_netto"] + $newtran["item_costprice_taxes"];
                           $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $itemstid, $itemlistrow["item_id"], "item");
                           $newtran["item_costprice_avg_netto"]   = updateItemAverageCost($CON, $newtran, $headdata["invc_stockchange"]);

                           if((int)$headdata["invc_stockchange"])
                           {
                              createTransaction($CON, $newtran, true);
                              $trancounter++;
                           }
                        }
                     }
                  }
               }

               if($headdata["invc_type"] == 2 && (int)$row["item_supporder_pos"] > -1)
               {
                  $sql = " update supplier_order_items
                           set
                           item_amount_shipped = item_amount_shipped + {$st_shipped}
                           where
                           sord_id  = {$invcparts[$y]["part_sord_id"]} and
                           item_id  = {$row["item_id"]} and
                           item_pos = {$row["item_supporder_pos"]}";
                  $CON->no_result($sql);
               }
            }
         }

         if($headdata["invc_type"] == 2)
            autocloseSupplierOrder($CON, $invcparts[$y]["part_sord_id"]);
         if($headdata["invc_type"] == 1)
         {
            $invcparts = getInvoiceBuyParts($CON, $invcid);
            foreach($invcparts AS $invcpart)
            {
               if((int)$invcpart["part_shp_id"])
               {
                  $sql = " update shipment
                           set
                           shp_status = 3
                           where
                           id = {$invcpart["part_shp_id"]}";
                  $CON->no_result($sql);
               }
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " update invoices_buy
            set
            invc_remote_synced = 0
            where
            id = {$invcid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function updateItemAverageCost($CON, $newtran, $invc_stockchange)
{
   $item_id                = $newtran["item_id"];
   $company_id             = $newtran["tran_company_id"];
   $item_costprice_netto   = $newtran["item_costprice_netto"];
   $item_amount            = $newtran["tran_amount"];
   $trandate               = mktime(15,0,0,$newtran["tran_month"], $newtran["tran_day"], $newtran["tran_year"]);
   

   //----------------------------------------------------------------------------------
   $sql = " insert into tran_average_costprices_hist
            (tran_id, tran_type, tran_company_id, tran_shop_id, item_id, item_type, item_costprice_netto, tran_amount)
            VALUES
            ({$newtran["tran_id"]}, '{$newtran["tran_type"]}', {$newtran["tran_company_id"]}, {$newtran["tran_shop_id"]},
             {$item_id}, '{$newtran["item_type"]}', {$item_costprice_netto}, {$item_amount})";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from tran_average_costprices
            where
            item_id     = {$item_id} and
            company_id  = {$company_id}";
   $currentavgcost = $CON->select($sql);
   $currentavgcost = $currentavgcost[0];

   //----------------------------------------------------------------------------------
   if((int)$currentavgcost["item_id"])
   {
      $compstock  = 0.00;
      $shops      = getShops($CON);
      foreach($shops AS $shop)
      {
         if($shop["shop_company_id"] == $company_id)
            $compstock += getItemShopCurrentStock($CON, $shop["id"], $item_id, "item");
      }

      //----------------------------------------------------------------------------------
      if(!$invc_stockchange)
         $compstock -= $item_amount;

      //----------------------------------------------------------------------------------
      $compcost    = $compstock * $currentavgcost["item_costprice_avg_netto"];
      $compcost   += ($item_amount * $item_costprice_netto);
      $newavgcost  = $compcost / ($compstock + $item_amount);
      /*
      $sql = " update tran_average_costprices
               set
               item_costprice_avg_netto = {$newavgcost}
               where
               item_id     = {$item_id} and
               company_id  = {$company_id}";
      $CON->no_result($sql);

      $sql = " insert into tran_average_costprices_log
               (item_id, company_id, log_crtdat, tran_id, tran_type, item_costprice_avg_netto)
               VALUES
               ({$item_id}, {$company_id}, {$trandate}, {$newtran["tran_id"]},
               '{$newtran["tran_type"]}', {$newavgcost})";
      $CON->no_result($sql);
      */

      return $newavgcost;
   }
   else
   {
      /*
      $sql = " insert into tran_average_costprices
               (item_id, company_id, item_costprice_avg_netto)
               VALUES
               ({$item_id}, {$company_id}, {$item_costprice_netto})";
      $CON->no_result($sql);

      $sql = " insert into tran_average_costprices_log
               (item_id, company_id, log_crtdat, tran_id, tran_type, item_costprice_avg_netto)
               VALUES
               ({$item_id}, {$company_id}, {$trandate}, {$newtran["tran_id"]},
               '{$newtran["tran_type"]}', {$item_costprice_netto})";
      $CON->no_result($sql);
      */
      
      return $item_costprice_netto;
   }
}

//----------------------------------------------------------------------------------
//($CON, $item_id, $company_id, $item_costprice_netto, $item_amount)
function revertItemAverageCost($CON, $invcid)
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from tran_average_costprices_hist
            where
            tran_id     = {$invcid} and
            tran_type   = 'invoicesbuy'";
   $pitems = $CON->select($sql);

   foreach($pitems AS $pitem)
   {
      $sql = " select *
               from tran_average_costprices
               where
               item_id     = {$pitem["item_id"]} and
               company_id  = {$pitem["tran_company_id"]}";
      $currentavgcost = $CON->select($sql);
      $currentavgcost = $currentavgcost[0];

      if((int)$currentavgcost["item_id"])
      {
         $compstock  = 0.00;
         $shops      = getShops($CON);
         foreach($shops AS $shop)
         {
            if($shop["shop_company_id"] == $pitem["tran_company_id"])
               $compstock += getItemShopCurrentStock($CON, $shop["id"], $pitem["item_id"], "item");
         }
         $compcost    = $compstock * $currentavgcost["item_costprice_avg_netto"];
         $compcost   -= ($pitem["tran_amount"] * $pitem["item_costprice_netto"]);
         $newavgcost  = $compcost / ($compstock - $pitem["tran_amount"]);

         /*
         if($compstock - $pitem["tran_amount"] <= 0)
         {
            $sql = " delete from tran_average_costprices
                     where
                     item_id     = {$pitem["item_id"]} and
                     company_id  = {$pitem["tran_company_id"]}";
            $CON->no_result($sql);
         }
         else
         {
            $sql = " update tran_average_costprices
                     set
                     item_costprice_avg_netto = {$newavgcost}
                     where
                     item_id     = {$pitem["item_id"]} and
                     company_id  = {$pitem["tran_company_id"]}";
            $CON->no_result($sql);
         }
         */
      }
   }

   $sql = " delete
            from tran_average_costprices_hist
            where
            tran_id     = {$invcid} and
            tran_type   = 'invoicesbuy'";
   $CON->no_result($sql);

   /*
   $sql = " delete
            from tran_average_costprices_log
            where
            tran_id     = {$invcid} and
            tran_type   = 'invoicesbuy'";
   $CON->no_result($sql);
   */
}


//----------------------------------------------------------------------------------
function bookStockChange($CON, $stockchangeid)
{
   $currtme = time();
   
   $sql = " select t1.*
            from stockchanges t1
            where
            t1.id = {$stockchangeid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["stk_negative"])
      $tran_type = "stockchangedown";
   else
      $tran_type = "stockchangeup";

   $basetran["tran_id"]          =  $headdata["id"];
   $basetran["tran_type"]        =  $tran_type;
   $basetran["tran_number"]      =  $headdata["stk_num"];
   $basetran["tran_company_id"]  =  $headdata["stk_companyid"];
   $basetran["tran_shop_id"]     =  $headdata["stk_shopid"];
   $basetran["tran_supplier_id"] =  0;
   $basetran["tran_day"]         =  (int)date('d', $headdata["stk_bookdate"]);
   $basetran["tran_month"]       =  (int)date('m', $headdata["stk_bookdate"]);
   $basetran["tran_year"]        =  (int)date('Y', $headdata["stk_bookdate"]);
   $basetran["tran_issueid"]     =  $headdata["stk_issueid"];
   $basetran["tran_crtusr"]      =  $_SESSION["user_id"];
   $basetran["tran_crtdat"]      =  $currtme;
   $basetran["tran_order_id"]    = (int)$headdata["sth_order_id"];

   if((int)$headdata["stk_status"] == 1)
   {
      $sql = " update stockchanges
               set
               stk_status     = 2,
               stk_updusr     = {$_SESSION["user_id"]},
               stk_upddat     = {$currtme}
               where
               id = {$stockchangeid}";
      $CON->no_result($sql);

      $sql = " select t2.*, t3.item_title
               from stockchanges_items t2
               LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
               where
               t2.stk_id      = {$stockchangeid} and
               t2.item_type   = 'item'
               UNION ALL
               select t2.*, t3.item_title
               from stockchanges_items t2
               LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
               where
               t2.stk_id      = {$stockchangeid} and
               t2.item_type   = 'itemlist'";
      $posdata = $CON->select($sql);

      $trancounter = 0;
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row = $posdata[$x];

         $itemstid   = (int)$row["item_st_id"];
         $st_amount  = (float)$row["item_amount"];

         if($st_amount > 0.00)
         {
            if($row["item_type"] == "item")
            {
               if((int)$row["item_charges_act"])
               {
                  if($tran_type == "stockchangedown")
                  {
                     $item_charges = getItemChargeTransUsed($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                     foreach($item_charges AS $item_charge)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_used"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["tran_refid"];
                        $newtran["item_charges_used_id"]       = $item_charge["id"];
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"],
                                                                 $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                                 $basetran["tran_order_id"]);
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
                  else
                  {
                     $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                     foreach($item_charges AS $item_charge)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["id"];
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"],
                                                                 $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                                 $basetran["tran_order_id"]);
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
               else
               {
                  $newtran                               = $basetran;
                  $newtran["tran_pos"]                   = $trancounter;
                  $newtran["tran_amount"]                = $st_amount;
                  $newtran["tran_st_id"]                 = $itemstid;
                  $newtran["item_id"]                    = $row["item_id"];
                  $newtran["item_type"]                  = $row["item_type"];
                  $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"], $itemstid,
                                                           $row["item_id"], $row["item_type"], false,
                                                           $basetran["tran_order_id"]);
                  createTransaction($CON, $newtran, true);
                  $trancounter++;
               }
            }
            
            //----------------------------------------------------------------------------------
            elseif($row["item_type"] == "itemlist")
            {
               $itemlistpos = getItemListContent($CON, $row["item_id"]);

               if((int)$row["item_charges_act"])
               {
                  if($tran_type == "stockchangedown")
                  {
                     $item_charges = getItemChargeTransUsed($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                     foreach($item_charges AS $item_charge)
                     {
                        if($item_charge["tran_amount_used"] > 0.00)
                        {
                           $newtran                               = $basetran;
                           $newtran["tran_pos"]                   = $trancounter;
                           $newtran["tran_amount"]                = $item_charge["tran_amount_used"];
                           $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                           $newtran["item_id"]                    = $item_charge["tran_item_id"];
                           $newtran["item_parent_id"]             = $row["item_id"];
                           $newtran["item_type"]                  = "item";
                           $newtran["item_charges_id"]            = $item_charge["tran_refid"];
                           $newtran["item_charges_used_id"]       = $item_charge["id"];
                           $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"],
                                                                    $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                                    $basetran["tran_order_id"]);
                           createTransaction($CON, $newtran, true);
                           $trancounter++;
                        }
                     }
                  }
                  else
                  {
                     $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                     foreach($item_charges AS $item_charge)
                     {
                        if($item_charge["tran_amount_avail"] > 0.00)
                        {
                           $newtran                               = $basetran;
                           $newtran["tran_pos"]                   = $trancounter;
                           $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                           $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                           $newtran["item_id"]                    = $item_charge["tran_item_id"];
                           $newtran["item_parent_id"]             = $item_charge["item_id"];
                           $newtran["item_type"]                  = "item";
                           $newtran["item_charges_id"]            = $item_charge["id"];
                           $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"],
                                                                    $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                                    $basetran["tran_order_id"]);
                           createTransaction($CON, $newtran, true);
                           $trancounter++;
                        }
                     }
                  }
               }
               else
               {
                  foreach($itemlistpos AS $itemlistrow)
                  {
                     $amount_calced = (float)$itemlistrow["item_amount"] * $st_amount;

                     if($amount_calced > 0.00)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $amount_calced;
                        $newtran["tran_st_id"]                 = $itemstid;
                        $newtran["item_id"]                    = $itemlistrow["item_id"];
                        $newtran["item_parent_id"]             = $row["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["shp_shop_id"],
                                                                 $itemstid, $itemlistrow["item_id"], "item", false,
                                                                 $basetran["tran_order_id"]);
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
            }
         }
      }
   }
}

//----------------------------------------------------------------------------------
function bookOrdersDelivery($CON, $dlvid)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from orders_delivery t1
            where
            t1.id = {$dlvid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $basetran["tran_id"]          = $headdata["id"];
   $basetran["tran_type"]        = "ordersdelivery";
   $basetran["tran_number"]      = $headdata["dlv_num"];
   $basetran["tran_company_id"]  = $headdata["dlv_company_id"];
   $basetran["tran_shop_id"]     = $headdata["dlv_shop_id"];
   $basetran["tran_cust_id"]     = $headdata["dlv_cust_id"];
   $basetran["tran_day"]         = (int)date('d', $headdata["dlv_delivery_date"]);
   $basetran["tran_month"]       = (int)date('m', $headdata["dlv_delivery_date"]);
   $basetran["tran_year"]        = (int)date('Y', $headdata["dlv_delivery_date"]);
   $basetran["tran_crtusr"]      = $_SESSION["user_id"];
   $basetran["tran_crtdat"]      = $currtme;
   $basetran["tran_order_id"]    = (int)$headdata["dlv_order_id"];

   if((int)$headdata["dlv_status"] == 1)
   {
      bookItemsRel($CON, $basetran);
      
      $sql = " update orders_delivery
               set
               dlv_status     = 2,
               dlv_updusr     = {$_SESSION["user_id"]},
               dlv_upddat     = {$currtme}
               where
               id = {$dlvid}";
      $CON->no_result($sql);

      if(!(int)$_REQUEST["_NOITF"])
      {
         $_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["dlv_company_id"]);
      }

      updateItemsInProcess($CON, $dlvid, "order");
      
      //----------------------------------------------------------------------------------
      $posdata = getOrderDeliveryPos($CON, $dlvid);
      $trancounter = 0;

      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row        = $posdata[$x];
         $itemstid   = (int)$row["item_st_id"];
         $st_shipped = (float)$row["item_amount_shipped"];

         if($st_shipped > 0.00)
         {
            if($row["item_type"] == "item")
            {
               $sql = " select t1.*, t4.cust_name, t2.*
                        from orders t1
                        INNER JOIN orders_items t2 ON t1.id = t2.req_id
                        INNER JOIN customer t4     ON t1.req_cust_id = t4.id
                        where
                        t1.id = {$basetran["tran_order_id"]} and
                        t1.req_isfabricate = 1";
               $orderinfo = $CON->select($sql);
               $orderinfo = $orderinfo[0];

               if(!(int)$orderinfo["id"] || (int)$orderinfo["item_id"] != $row["item_id"] || $_HASFABITEMFOUND)
                  $basetran["tran_order_id"] = 0;

               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTransUsed($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     $newtran                               = $basetran;
                     $newtran["tran_pos"]                   = $trancounter;
                     $newtran["tran_amount"]                = $item_charge["tran_amount_used"];
                     $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                     $newtran["item_id"]                    = $item_charge["tran_item_id"];
                     $newtran["item_type"]                  = "item";
                     $newtran["item_charges_id"]            = $item_charge["tran_refid"];
                     $newtran["item_charges_used_id"]       = $item_charge["id"];
                     $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["dlv_shop_id"],
                                                              $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                              $basetran["tran_order_id"]);
                     createTransaction($CON, $newtran, true);
                     $trancounter++;
                     $_HASFABITEMFOUND = true;
                  }
               }
               else
               {
                  $newtran                               = $basetran;
                  $newtran["tran_pos"]                   = $trancounter;
                  $newtran["tran_amount"]                = $st_shipped;
                  $newtran["tran_st_id"]                 = $itemstid;
                  $newtran["item_id"]                    = $row["item_id"];
                  $newtran["item_type"]                  = $row["item_type"];
                  $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["dlv_shop_id"],
                                                           $itemstid, $row["item_id"], $row["item_type"], false,
                                                           $basetran["tran_order_id"]);
                  createTransaction($CON, $newtran, true);
                  $trancounter++;
                  $_HASFABITEMFOUND = true;
               }
            }

            //----------------------------------------------------------------------------------
            elseif($row["item_type"] == "itemlist")
            {
               $itemlistpos = getItemListContent($CON, $row["item_id"]);

               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTransUsed($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     if($item_charge["tran_amount_used"] > 0.00)
                     {
                        //$costdata = getSupplierItemCosts($CON, 0, $itemlistpos[0]["item_id"], "item");

                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_used"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_parent_id"]             = $row["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["tran_refid"];
                        $newtran["item_charges_used_id"]       = $item_charge["id"];
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["dlv_shop_id"],
                                                                 $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                                 $basetran["tran_order_id"]);

                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                        $_HASFABITEMFOUND = true;
                     }
                  }
               }
               else
               {
                  foreach($itemlistpos AS $itemlistrow)
                  {
                     $amount_calced = (float)$itemlistrow["item_amount"] * $row["item_amount_shipped"];

                     if($amount_calced > 0.00)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $amount_calced;
                        $newtran["tran_st_id"]                 = $itemstid;
                        $newtran["item_id"]                    = $itemlistrow["item_id"];
                        $newtran["item_parent_id"]             = $row["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["dlv_shop_id"],
                                                                 $itemstid, $itemlistrow["item_id"], "item", false,
                                                                 $basetran["tran_order_id"]);
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                        $_HASFABITEMFOUND = true;
                     }
                  }
               }
            }
         }
      }
      return true;
   }
   else
      return false;
}

//----------------------------------------------------------------------------------
function bookShipment($CON, $shipment_id)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from shipment t1
            where
            t1.id = {$shipment_id}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $basetran["tran_id"]          = $headdata["id"];
   $basetran["tran_type"]        = "shipment";
   $basetran["tran_number"]      = $headdata["shp_num"];
   $basetran["tran_company_id"]  = $headdata["shp_company_id"];
   $basetran["tran_shop_id"]     = $headdata["shp_shop_id"];
   $basetran["tran_supplier_id"] = $headdata["shp_supplier_id"];
   $basetran["tran_day"]         = (int)date('d', $headdata["shp_delivery_date"]);
   $basetran["tran_month"]       = (int)date('m', $headdata["shp_delivery_date"]);
   $basetran["tran_year"]        = (int)date('Y', $headdata["shp_delivery_date"]);
   $basetran["tran_crtusr"]      = $_SESSION["user_id"];
   $basetran["tran_crtdat"]      = $currtme;

   if((int)$headdata["shp_status"] == 1)
   {
      $sql = " update shipment
               set
               shp_status        = 2,
               shp_updusr        = {$_SESSION["user_id"]},
               shp_upddat        = {$currtme}
               where
               id = {$shipment_id}";
      $CON->no_result($sql);

      updateItemsInProcess($CON, $shipment_id);
      
      //----------------------------------------------------------------------------------
      $posdata = getShipmentPos($CON, $shipment_id);
      $trancounter = 0;

      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row        = $posdata[$x];
         $itemstid   = (int)$row["item_st_id"];
         $st_shipped = (float)$row["item_amount_shipped"];

         if($st_shipped > 0.00)
         {
            if($row["item_type"] == "item")
            {
               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     $newtran                               = $basetran;
                     $newtran["item_id"]                    = $item_charge["tran_item_id"];
                     $newtran["item_type"]                  = "item";
                     $newtran["item_charges_id"]            = $item_charge["id"];
                     $newtran["tran_pos"]                   = $trancounter;
                     $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                     $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                     $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["shp_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");
                     createTransaction($CON, $newtran, true);
                     $trancounter++;
                  }
               }
               else
               {
                  if((int)$row["item_subitem_id"])
                  {
                     $row["item_id"] = $row["item_subitem_id"];
                     $st_shipped     = round($st_shipped * $row["item_subitem_amount"],2);
                  }
                  
                  $newtran                               = $basetran;
                  $newtran["item_id"]                    = $row["item_id"];
                  $newtran["item_type"]                  = $row["item_type"];
                  $newtran["tran_pos"]                   = $trancounter;
                  $newtran["tran_amount"]                = $st_shipped;
                  $newtran["tran_st_id"]                 = $itemstid;
                  $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["shp_shop_id"], $itemstid, $row["item_id"], $row["item_type"]);
                  createTransaction($CON, $newtran, true);
                  $trancounter++;
               }
            }

            //----------------------------------------------------------------------------------
            elseif($row["item_type"] == "itemlist")
            {
               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     if($item_charge["tran_amount_avail"] > 0.00)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_parent_id"]             = $item_charge["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["id"];
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["shp_shop_id"], $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item");
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
               else
               {
                  foreach($itemlistpos AS $itemlistrow)
                  {
                     $amount_calced = (float)$itemlistrow["item_amount"] * $row["item_amount_shipped"];

                     if($amount_calced > 0.00)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $amount_calced;
                        $newtran["tran_st_id"]                 = $itemstid;
                        $newtran["item_id"]                    = $itemlistrow["item_id"];
                        $newtran["item_parent_id"]             = $row["item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["shp_shop_id"], $itemstid, $itemlistrow["item_id"], "item");
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
               }
            }
         }
      }

      $sql = " update shipment
               set
               shp_remote_synced = 0
               where
               id = {$shipment_id}";
      $CON->no_result($sql);
      
      return true;
   }
   else
      return false;
}

//----------------------------------------------------------------------------------
function bookStorehouseChange($CON, $strcid)
{
   $currtme = time();
   
   $sql = " select t1.*
            from storehousechanges t1
            where
            t1.id = {$strcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $basetran["tran_id"]          = $headdata["id"];
   $basetran["tran_type"]        = "sthdown";
   $basetran["tran_number"]      = $headdata["strc_number"];
   $basetran["tran_company_id"]  = $headdata["strc_company_id"];
   $basetran["tran_shop_id"]     = $headdata["strc_shop_id"];
   $basetran["tran_supplier_id"] = 0;
   $basetran["tran_day"]         = (int)date('d', $headdata["strc_date"]);
   $basetran["tran_month"]       = (int)date('m', $headdata["strc_date"]);
   $basetran["tran_year"]        = (int)date('Y', $headdata["strc_date"]);
   $basetran["tran_crtusr"]      = $_SESSION["user_id"];
   $basetran["tran_crtdat"]      = $currtme;

   //----------------------------------------------------------------------------------
   if((int)$headdata["strc_status"] == 1)
   {
      //----------------------------------------------------------------------------------
      $sql = " update storehousechanges
               set
               strc_status       = 2,
               strc_shopreceived = 0,
               strc_updusr       = {$_SESSION["user_id"]},
               strc_upddat       = {$currtme}
               where
               id = {$strcid}";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.item_title
               from storehousechanges_items t1, item t2
               where
               t1.strc_id    = {$strcid} and
               t1.item_id    = t2.id and
               t1.item_type  = 'item'
               UNION ALL
               select t1.*, t2.item_title
               from storehousechanges_items t1, itemlist t2
               where
               t1.strc_id    = {$strcid} and
               t1.item_id    = t2.id and
               t1.item_type  = 'itemlist'
               order by 3 asc";
      $posdata = $CON->select($sql);

      //----------------------------------------------------------------------------------
      $trancounter = 0;
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row              = $posdata[$x];
         $itemstid_from    = (int)$row["item_st_id"];
         $itemstid_to      = (int)$row["item_st_dest_id"];
         $st_amount        = (float)$row["item_amount"];

         if($st_amount > 0.00 && ($itemstid_from || $itemstid_to))
         {
            //----------------------------------------------------------------------------------
            if($row["item_type"] == "item")
            {
               //----------------------------------------------------------------------------------
               if((int)$row["item_charges_act"])
               {
                  $item_charges = getItemChargeTransUsed($CON, $basetran["tran_id"], $basetran["tran_type"], $row["item_pos"]);
                  foreach($item_charges AS $item_charge)
                  {
                     $newtran                               = $basetran;
                     $newtran["tran_order_id"]              = (int)$headdata["strc_order_id"];
                     $newtran["tran_pos"]                   = $trancounter;
                     $newtran["tran_amount"]                = $item_charge["tran_amount_used"];
                     $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                     $newtran["item_id"]                    = $item_charge["tran_item_id"];
                     $newtran["item_type"]                  = "item";
                     $newtran["item_charges_id"]            = $item_charge["tran_refid"];
                     $newtran["item_charges_used_id"]       = $item_charge["id"];
                     $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_id"],
                                                             $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                             $basetran["strc_order_id"]);
                     createTransaction($CON, $newtran, true);
                     $trancounter++;
                  }
               }
               else
               {
                  $newtran                               = $basetran;
                  $newtran["tran_order_id"]              = (int)$headdata["strc_order_id"];
                  $newtran["tran_pos"]                   = $trancounter;
                  $newtran["tran_amount"]                = $st_amount;
                  $newtran["tran_st_id"]                 = $itemstid_from;
                  $newtran["item_id"]                    = $row["item_id"];
                  $newtran["item_type"]                  = $row["item_type"];
                  $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_id"],
                                                           $itemstid_from, $row["item_id"], $row["item_type"], false,
                                                           $basetran["strc_order_id"]);
                  createTransaction($CON, $newtran, true);
                  $trancounter++;
               }

               //----------------------------------------------------------------------------------
               if(!(int)$headdata["strc_shopsent"])
               {
                  if((int)$row["item_charges_act"])
                  {
                     $item_charges = getItemChargeTrans($CON, $basetran["tran_id"], "sthup", $row["item_pos"]);
                     foreach($item_charges AS $item_charge)
                     {
                        $newtran                               = $basetran;
                        $newtran["tran_order_id"]              = (int)$headdata["strc_order_id_dest"];
                        $newtran["tran_type"]                  = "sthup";
                        $newtran["tran_company_id"]            = $headdata["strc_company_dest_id"];
                        $newtran["tran_shop_id"]               = $headdata["strc_shop_dest_id"];
                        $newtran["tran_pos"]                   = $trancounter;
                        $newtran["tran_amount"]                = $item_charge["tran_amount_avail"];
                        $newtran["tran_st_id"]                 = $item_charge["tran_st_id"];
                        $newtran["item_id"]                    = $item_charge["tran_item_id"];
                        $newtran["item_type"]                  = "item";
                        $newtran["item_charges_id"]            = $item_charge["id"];
                        $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_dest_id"],
                                                                 $item_charge["tran_st_id"], $item_charge["tran_item_id"], "item", false,
                                                                 $basetran["strc_order_id_dest"]);
                        createTransaction($CON, $newtran, true);
                        $trancounter++;
                     }
                  }
                  else
                  {
                     $newtran["tran_order_id"]              = (int)$headdata["strc_order_id_dest"];
                     $newtran["tran_pos"]                   = $trancounter;
                     $newtran["tran_st_id"]                 = $itemstid_to;
                     $newtran["tran_type"]                  = "sthup";
                     $newtran["tran_company_id"]            = $headdata["strc_company_dest_id"];
                     $newtran["tran_shop_id"]               = $headdata["strc_shop_dest_id"];
                     $newtran["tran_currstock"]             = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_dest_id"],
                                                              $itemstid_to, $row["item_id"], $row["item_type"], false,
                                                              $basetran["strc_order_id_dest"]);
                     createTransaction($CON, $newtran, true);
                     $trancounter++;
                  }
               }
            }
         }
      }
   }

   $sql = " update storehousechanges
            set
            strc_remote_synced = 0
            where
            id = {$strcid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function delStorehouseChange($CON, $strcid)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from storehousechanges t1
            where
            t1.id = {$strcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["strc_status"] == 2)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id           = {$strcid} and
               tran_type         = 'sthup' and
               item_charges_id   > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id                 = {$strcid} and
               tran_type               = 'sthdown' and
               item_charges_used_id    > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_amount_used  = tran_amount_used - {$invbooking["tran_amount"]},
                  tran_amount_avail = tran_amount_avail + {$invbooking["tran_amount"]}
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      
         $sql = " update tran_charges_used
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_used_id"]}";
         $CON->no_result($sql);
      }
      
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$strcid} and
               tran_type   IN ('sthup','sthdown') and
               tran_st_id  != 0";
      $strcbookings = $CON->select($sql);

      foreach($strcbookings AS $strcbooking)
      {
         if($strcbooking["tran_type"] == "sthdown")
            $addamount = true;
         else
            $addamount = false;

         changeItemShopStorehouseStock($CON, (int)$strcbooking["tran_shop_id"], (int)$strcbooking["tran_st_id"],
                                       (int)$strcbooking["item_id"], $strcbooking["item_type"],
                                       (float)$strcbooking["tran_amount"], $addamount, (int)$strcbooking["tran_order_id"]);
      }

      $sql = " delete from tran_data
               where
               tran_id     = {$strcid} and
               tran_type   IN ('sthup','sthdown')";
      $CON->no_result($sql);
   }
   
   //----------------------------------------------------------------------------------
   $currtme = time();
   $sql = " update storehousechanges
            set
            strc_status     = 1,
            strc_updusr     = {$_SESSION["user_id"]},
            strc_upddat     = {$currtme},
            strc_remote_synced = 0
            where
            id = {$strcid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function delStockChange($CON, $stockchangeid)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from stockchanges t1
            where
            t1.id = {$stockchangeid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["stk_status"] == 2)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id           = {$stockchangeid} and
               tran_type         = 'stockchangeup' and
               item_charges_id   > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id                 = {$stockchangeid} and
               tran_type               = 'stockchangedown' and
               item_charges_used_id    > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_amount_used  = tran_amount_used - {$invbooking["tran_amount"]},
                  tran_amount_avail = tran_amount_avail + {$invbooking["tran_amount"]}
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      
         $sql = " update tran_charges_used
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_used_id"]}";
         $CON->no_result($sql);
      }
      
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$stockchangeid} and
               tran_type   IN ('stockchangeup','stockchangedown') and
               tran_st_id  != 0";
      $stkbookings = $CON->select($sql);

      foreach($stkbookings AS $stkbooking)
      {
         if($stkbooking["tran_type"] == "stockchangedown")
            $addamount = true;
         else
            $addamount = false;
            
         changeItemShopStorehouseStock($CON, (int)$stkbooking["tran_shop_id"], (int)$stkbooking["tran_st_id"],
                                       (int)$stkbooking["item_id"], $stkbooking["item_type"],
                                       (float)$stkbooking["tran_amount"], $addamount,
                                       (int)$stkbooking["tran_order_id"]);
      }

      $sql = " delete from tran_data
               where
               tran_id     = {$stockchangeid} and
               tran_type   IN ('stockchangeup','stockchangedown')";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " update stockchanges
            set
            stk_status     = 1,
            stk_updusr     = {$_SESSION["user_id"]},
            stk_upddat     = {$currtme}
            where
            id = {$stockchangeid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function delShipment($CON, $shipment_id)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from shipment t1
            where
            t1.id = {$shipment_id}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["shp_status"] == 2)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id           = {$shipment_id} and
               tran_type         = 'shipment' and
               item_charges_id   > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      }
      
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$shipment_id} and
               tran_type   = 'shipment' and
               tran_st_id  != 0";
      $invbookings = $CON->select($sql);

      foreach($invbookings AS $invbooking)
      {
         changeItemShopStorehouseStock($CON, (int)$invbooking["tran_shop_id"], (int)$invbooking["tran_st_id"],
                                       (int)$invbooking["item_id"], $invbooking["item_type"],
                                       (float)$invbooking["tran_amount"], false);
                      
      }

      $sql = " delete from tran_data
               where
               tran_id     = {$shipment_id} and
               tran_type   = 'shipment'";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $posdata = getShipmentPos($CON, $shipment_id);
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         $row = $posdata[$x];

         if((float)$row["item_amount"] > 0.00 && (float)$row["item_amount_shipped"] > 0.00 && (int)$row["item_supporder_pos"] > -1)
         {
            $sql = " update supplier_order_items
                     set
                     item_amount_shipped = item_amount_shipped - {$row["item_amount_shipped"]}
                     where
                     sord_id  = {$headdata["shp_supporder_id"]} and
                     item_id  = {$row["item_id"]} and
                     item_pos = {$row["item_supporder_pos"]}";
            $CON->no_result($sql);

            $sql = " update supplier_order
                     set
                     sord_status        = 3,
                     sord_order_shipped = 0
                     where
                     id = {$headdata["shp_supporder_id"]}";
            $CON->no_result($sql);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " update shipment
            set
            shp_status     = 0,
            shp_updusr     = {$_SESSION["user_id"]},
            shp_upddat     = {$currtme},
            shp_remote_synced = 0
            where
            id = {$shipment_id}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function delOrdersDelivery($CON, $dlvid)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from orders_delivery t1
            where
            t1.id = {$dlvid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["dlv_status"] >= 2 && (int)$headdata["dlv_status"] != 5)
   {

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id                 = {$dlvid} and
               tran_type               = 'ordersdelivery' and
               item_charges_used_id    > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_amount_used  = tran_amount_used - {$invbooking["tran_amount"]},
                  tran_amount_avail = tran_amount_avail + {$invbooking["tran_amount"]}
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);

         $sql = " update tran_charges_used
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_used_id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$dlvid} and
               tran_type   = 'ordersdelivery' and
               tran_st_id  != 0";
      $invbookings = $CON->select($sql);

      foreach($invbookings AS $invbooking)
      {
         changeItemShopStorehouseStock($CON, (int)$invbooking["tran_shop_id"], (int)$invbooking["tran_st_id"],
                                       (int)$invbooking["item_id"], $invbooking["item_type"],
                                       (float)$invbooking["tran_amount"], true);

      }

      $sql = " delete from tran_data
               where
               tran_id     = {$dlvid} and
               tran_type   = 'ordersdelivery'";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      if((int)$headdata["dlv_order_based"] && (int)$headdata["dlv_order_id"])
      {
         $posdata = getOrderDeliveryPos($CON, $dlvid);
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
         {
            $row = $posdata[$x];

            if((float)$row["item_amount"] > 0.00 && (float)$row["item_amount_shipped"] > 0.00 && (int)$row["item_order_pos"] > -1)
            {
               $sql = " update orders_items
                        set
                        item_amount_shipped = item_amount_shipped - {$row["item_amount_shipped"]}
                        where
                        req_id   = {$headdata["dlv_order_id"]} and
                        item_id  = {$row["item_id"]} and
                        item_pos = {$row["item_order_pos"]}";
               $CON->no_result($sql);
            }
         }

         //----------------------------------------------------------------------------------
         $sql = " update orders
                  set
                  req_status        = 3,
                  req_order_shipped = 0
                  where
                  id = {$headdata["dlv_order_id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      if($headdata["dlv_invoice_generated"] > 0)
      {
         $sql = " select t1.*
                  from invoices_sell t1
                  where
                  t1.id = {$headdata["dlv_invoice_generated"]}";
         $invcdata = $CON->select($sql);
         $invcdata = $invcdata[0];

         if($invcdata["invc_type"] == 2)
         {
            //----------------------------------------------------------------------------------
            $invcparts  = getInvoiceSellParts($CON, $headdata["dlv_invoice_generated"]);
            for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
            {
               if((int)$invcparts[$x]["part_req_id"])
               {
                  $partposdata = getInvoiceSellPartsItems($CON, $headdata["dlv_invoice_generated"], $invcparts[$x]["id"]);
                  for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
                  {
                     $row = $partposdata[$y];
                     if((float)$row["item_amount"] > 0.00 && (int)$row["item_order_pos"] > -1)
                     {
                        $sql = " update orders_items
                                 set
                                 item_amount_shipped = item_amount_shipped - {$row["item_amount"]}
                                 where
                                 req_id   = {$invcparts[$x]["part_req_id"]} and
                                 item_id  = {$row["item_id"]} and
                                 item_pos = {$row["item_order_pos"]}";
                        $CON->no_result($sql);
                     }
                  }

                  //----------------------------------------------------------------------------------
                  $sql = " update orders
                           set
                           req_status        = 3,
                           req_order_shipped = 0
                           where
                           id = {$invcparts[$x]["part_req_id"]}";
                  $CON->no_result($sql);
               }
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " update orders_delivery
            set
            dlv_status     = 0,
            dlv_updusr     = {$_SESSION["user_id"]},
            dlv_upddat     = {$currtme}
            where
            id = {$dlvid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function createTransaction($CON, $objarr, $stockchange = false)
{
   global $_CONFIGTRANTYPES;

   //----------------------------------------------------------------------------------
   $objarr["tran_id"]            = (int)$objarr["tran_id"];
   $objarr["tran_pos"]           = (int)$objarr["tran_pos"];
   $objarr["tran_type"]          = trim(addslashes($objarr["tran_type"]));
   $objarr["tran_number"]        = trim(addslashes($objarr["tran_number"]));
   $objarr["tran_currstock"]     = (float)$objarr["tran_currstock"];
   $objarr["tran_amount"]        = (float)$objarr["tran_amount"];
   $objarr["tran_company_id"]    = (int)$objarr["tran_company_id"];
   $objarr["tran_shop_id"]       = (int)$objarr["tran_shop_id"];
   $objarr["tran_st_id"]         = (int)$objarr["tran_st_id"];
   $objarr["tran_supplier_id"]   = (int)$objarr["tran_supplier_id"];
   $objarr["tran_cust_id"]       = (int)$objarr["tran_cust_id"];
   $objarr["tran_issueid"]       = (int)$objarr["tran_issueid"];
   $objarr["item_id"]            = (int)$objarr["item_id"];
   $objarr["item_parent_id"]     = (int)$objarr["item_parent_id"];

   $objarr["item_type"]          = trim(addslashes($objarr["item_type"]));

   //----------------------------------------------------------------------------------
   $objarr["item_charges_id"]          = (int)$objarr["item_charges_id"];
   $objarr["item_charges_used_id"]     = (int)$objarr["item_charges_used_id"];

   //----------------------------------------------------------------------------------
   $objarr["item_costprice_brutto"]       = (float)$objarr["item_costprice_brutto"];
   $objarr["item_costprice_taxes_perc"]   = (float)$objarr["item_costprice_taxes_perc"];
   $objarr["item_costprice_netto"]        = (float)$objarr["item_costprice_netto"];
   $objarr["item_costprice_taxes"]        = (float)$objarr["item_costprice_taxes"];
   $objarr["item_costprice_avg_netto"]    = (float)$objarr["item_costprice_avg_netto"];
   $objarr["item_sellprice_brutto"]       = (float)$objarr["item_sellprice_brutto"];
   $objarr["item_sellprice_taxes_perc"]   = (float)$objarr["item_sellprice_taxes_perc"];
   $objarr["item_sellprice_netto"]        = (float)$objarr["item_sellprice_netto"];
   $objarr["item_sellprice_taxes"]        = (float)$objarr["item_sellprice_taxes"];

   //----------------------------------------------------------------------------------
   $objarr["tran_day"]     = (int)$objarr["tran_day"];
   $objarr["tran_month"]   = (int)$objarr["tran_month"];
   $objarr["tran_year"]    = (int)$objarr["tran_year"];
   $objarr["tran_crtusr"]  = (int)$objarr["tran_crtusr"];
   $objarr["tran_crtdat"]  = (int)$objarr["tran_crtdat"];

   $objarr["tran_order_id"] = (int)$objarr["tran_order_id"];

   //----------------------------------------------------------------------------------
   $sql = " insert into tran_data
            (tran_id, tran_pos, tran_type, tran_number, tran_currstock, tran_amount, tran_company_id, tran_shop_id, tran_st_id, tran_supplier_id,
            tran_cust_id, tran_issueid, item_id, item_parent_id, item_type, item_costprice_brutto, item_costprice_taxes_perc, item_costprice_netto,
            item_costprice_taxes, item_costprice_avg_netto, item_sellprice_brutto, item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
            item_charges_id, item_charges_used_id, tran_day, tran_month, tran_year, tran_crtusr, tran_crtdat, tran_order_id)
            VALUES
            ({$objarr["tran_id"]}, {$objarr["tran_pos"]}, '{$objarr["tran_type"]}', '{$objarr["tran_number"]}', {$objarr["tran_currstock"]}, {$objarr["tran_amount"]},
             {$objarr["tran_company_id"]}, {$objarr["tran_shop_id"]}, {$objarr["tran_st_id"]}, {$objarr["tran_supplier_id"]}, {$objarr["tran_cust_id"]}, {$objarr["tran_issueid"]},
             {$objarr["item_id"]}, {$objarr["item_parent_id"]}, '{$objarr["item_type"]}', {$objarr["item_costprice_brutto"]}, {$objarr["item_costprice_taxes_perc"]},
             {$objarr["item_costprice_netto"]}, {$objarr["item_costprice_taxes"]}, {$objarr["item_costprice_avg_netto"]}, {$objarr["item_sellprice_brutto"]}, {$objarr["item_sellprice_taxes_perc"]}, 
             {$objarr["item_sellprice_netto"]}, {$objarr["item_sellprice_taxes"]}, {$objarr["item_charges_id"]}, {$objarr["item_charges_used_id"]}, {$objarr["tran_day"]}, {$objarr["tran_month"]}, {$objarr["tran_year"]},
             {$objarr["tran_crtusr"]}, {$objarr["tran_crtdat"]}, {$objarr["tran_order_id"]})";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if($objarr["item_charges_id"] > 0 && !$objarr["item_charges_used_id"])
   {
      $sql = " update tran_charges
               set
               tran_booked = 1
               where
               id = {$objarr["item_charges_id"]}";
      $CON->no_result($sql);
   }
   elseif($objarr["item_charges_id"] > 0 && $objarr["item_charges_used_id"] > 0)
   {
      $sql = " update tran_charges_used
               set
               tran_booked = 1
               where
               id = {$objarr["item_charges_used_id"]}";
      $CON->no_result($sql);

      $sql = " update tran_charges
               set
               tran_amount_used  = tran_amount_used + {$objarr["tran_amount"]},
               tran_amount_avail = tran_amount_avail - {$objarr["tran_amount"]}
               where
               id = {$objarr["item_charges_id"]}";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if($stockchange && $objarr["item_id"] && $objarr["tran_shop_id"] && $objarr["tran_st_id"])
   {
      $addamount = true;

      if($_CONFIGTRANTYPES[$objarr["tran_type"]] === true || $_CONFIGTRANTYPES[$objarr["tran_type"]] === false)
         $addamount = $_CONFIGTRANTYPES[$objarr["tran_type"]];

      changeItemShopStorehouseStock($CON, $objarr["tran_shop_id"], $objarr["tran_st_id"], $objarr["item_id"],
                                   $objarr["item_type"], $objarr["tran_amount"], $addamount, $objarr["tran_order_id"]);
   }
}  

//----------------------------------------------------------------------------------
function changeItemShopStorehouseStock($CON, $shop_id, $st_id, $item_id, $item_type, $item_amount, $addamount = true, $tran_order_id = 0)
{
   if($addamount)
      $calcsign = "+";
   else
      $calcsign = "-";
      
   $tran_order_id = (int)$tran_order_id;

   if($item_type == "item")
   {
      $sql = " select count(*) 'cc'
               from item_shops_storehouses
               where
               item_id  = {$item_id} and
               shop_id  = {$shop_id} and
               st_id    = {$st_id} and
               iss_order_id = {$tran_order_id}";
      $exists = $CON->select($sql);
      if(!(int)$exists[0]["cc"])
      {
         $sql = " insert into item_shops_storehouses
                  (item_id, shop_id, st_id, iss_inventory, iss_order_id)
                  VALUES
                  ({$item_id}, {$shop_id}, {$st_id}, 0 {$calcsign} {$item_amount}, {$tran_order_id})";
         return $CON->no_result($sql);
      }
      else
      {
         $sql = " update item_shops_storehouses
                  set
                  iss_inventory = iss_inventory {$calcsign} {$item_amount}
                  where
                  item_id  = {$item_id} and
                  shop_id  = {$shop_id} and
                  st_id    = {$st_id} and
                  iss_order_id = {$tran_order_id}";
         return $CON->no_result($sql);
      }
   }
   else
   {
      $sql = " select count(*) 'cc'
               from itemlist_shops_storehouses
               where
               item_id  = {$item_id} and
               shop_id  = {$shop_id} and
               st_id    = {$st_id}";
      $exists = $CON->select($sql);
      if(!(int)$exists[0]["cc"])
      {
         $sql = " insert into itemlist_shops_storehouses
                  (item_id, shop_id, st_id, iss_inventory)
                  VALUES
                  ({$item_id}, {$shop_id}, {$st_id}, 0 {$calcsign} {$item_amount})";
         return $CON->no_result($sql);
      }
      else
      {
         $sql = " update itemlist_shops_storehouses
                  set
                  iss_inventory = iss_inventory {$calcsign} {$item_amount}
                  where
                  item_id  = {$item_id} and
                  shop_id  = {$shop_id} and
                  st_id    = {$st_id}";
         return $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
function revertInvoiceBuy($CON, $invcid)
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from invoices_buy t1
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["invc_status"] == 1)
   {
      //----------------------------------------------------------------------------------
      if($headdata["invc_type"] == 2)
      {
         $invcparts = getInvoiceBuyParts($CON, $invcid);

         for($y = 0; $y < count($invcparts) && $invcparts != false; $y++)
         {
            $posdata = getInvoiceBuyPartsItems($CON, $invcid, $invcparts[$y]["id"]);
            
            for($x = 0; $x < count($posdata) && $posdata != false; $x++)
            {
               $row        = $posdata[$x];
               $itemstid   = (int)$row["item_st_id"];
               $st_shipped = (float)$row["item_amount"];

               if((int)$row["item_supporder_pos"] > -1 && $st_shipped > 0.00)
               {
                  $sql = " update supplier_order_items
                           set
                           item_amount_shipped = item_amount_shipped - {$st_shipped}
                           where
                           sord_id  = {$invcparts[$y]["part_sord_id"]} and
                           item_id  = {$row["item_id"]} and
                           item_pos = {$row["item_supporder_pos"]}";
                  $CON->no_result($sql);

                  $sql = " update supplier_order
                           set
                           sord_status        = 3,
                           sord_order_shipped = 0
                           where
                           id = {$invcparts[$y]["part_sord_id"]}";
                  $CON->no_result($sql);
               }
            }
         }
      }

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id           = {$invcid} and
               tran_type         = 'invoicesbuy' and
               item_charges_id   > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      revertItemAverageCost($CON, $invcid);

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$invcid} and
               tran_type   = 'invoicesbuy' and
               tran_st_id  > 0";
      $invbookings = $CON->select($sql);

      //----------------------------------------------------------------------------------
      foreach($invbookings AS $invbooking)
      {
         changeItemShopStorehouseStock($CON, (int)$invbooking["tran_shop_id"], (int)$invbooking["tran_st_id"],
                                       (int)$invbooking["item_id"], $invbooking["item_type"],
                                       (float)$invbooking["tran_amount"], false);
      }

      //----------------------------------------------------------------------------------
      $sql = " delete from tran_data
               where
               tran_id     = {$invcid} and
               tran_type   = 'invoicesbuy'";
      $CON->no_result($sql);

      if($headdata["invc_type"] == 1)
      {
         $invcparts = getInvoiceBuyParts($CON, $invcid);
         foreach($invcparts AS $invcpart)
         {
            if((int)$invcpart["part_shp_id"])
            {
               $sql = " update shipment
                        set
                        shp_status = 2
                        where
                        id = {$invcpart["part_shp_id"]}";
               $CON->no_result($sql);
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " update invoices_buy
            set
            invc_remote_synced = 0
            where
            id = {$invcid}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
function revertInvoiceSellNotes($CON, $noteid)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_notes_sell 
            where
            id = {$noteid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["note_status"] == 1)
   {
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id           = {$noteid} and
               tran_type         = 'invoicesnotessell' and
               item_charges_id   > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      }
      
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$noteid} and
               tran_type   = 'invoicesnotessell' and
               tran_st_id  != 0";
      $invbookings = $CON->select($sql);

      foreach($invbookings AS $invbooking)
      {
         changeItemShopStorehouseStock($CON, (int)$invbooking["tran_shop_id"], (int)$invbooking["tran_st_id"],
                                       (int)$invbooking["item_id"], $invbooking["item_type"],
                                       (float)$invbooking["tran_amount"], false);
                      
      }
      $sql = " delete from tran_data
               where
               tran_id     = {$noteid} and
               tran_type   = 'invoicesnotessell'";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$noteid} and
               tran_type   = 'invoicesnotesselb' and
               tran_st_id  != 0";
      $invbookings = $CON->select($sql);

      foreach($invbookings AS $invbooking)
      {
         changeItemShopStorehouseStock($CON, (int)$invbooking["tran_shop_id"], (int)$invbooking["tran_st_id"],
                                       (int)$invbooking["item_id"], $invbooking["item_type"],
                                       (float)$invbooking["tran_amount"], true);
                      
      }
      $sql = " delete from tran_data
               where
               tran_id     = {$noteid} and
               tran_type   = 'invoicesnotesselb'";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
function revertInvoiceBuyNotes($CON, $noteid)
{
   $currtme = time();
   
   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_notes_buy
            where
            id = {$noteid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$headdata["note_status"] == 1)
   {
      //----------------------------------------------------------------------------------
      if($headdata["note_is_discount"] == 1 && (int)$headdata["note_parent_invcid"] && $headdata["note_discount_perc"] > 0.00)
      {
         $_INVCSDESC[$headdata["note_parent_invcid"]]  = 1;

         $sql = " select distinct note_parent_invcid
                  from invoices_notes_buy_otherdscinvc
                  where
                  note_id = {$headdata["id"]}";
         $otherinvcs = $CON->select($sql);
         foreach($otherinvcs AS $otherinvc)
            $_INVCSDESC[$otherinvc["note_parent_invcid"]]  = 1;

         foreach(array_keys($_INVCSDESC) AS $invcdescid)
         {
            $sql = " select *
                     from tran_average_costprices_hist
                     where
                     tran_id = {$invcdescid} and
                     tran_type = 'invoicesbuy'";
            $avgitems = $CON->select($sql);
            foreach($avgitems AS $avgitem)
            {
               //----------------------------------------------------------------------------------
               $old_netto = $avgitem["item_costprice_netto"] * $avgitem["tran_amount"];
               $new_netto = ($avgitem["item_costprice_netto"] / (100 - $headdata["note_discount_perc"]) * 100) * $avgitem["tran_amount"];
               $dif_netto = $new_netto - $old_netto;
               
               $sql = " update tran_average_costprices_hist
                        set
                        item_costprice_netto = (item_costprice_netto / (100 - {$headdata["note_discount_perc"]}) * 100)
                        where
                        id = {$avgitem["id"]}";
               $CON->no_result($sql);

               //----------------------------------------------------------------------------------
               $sql = " select *
                        from tran_average_costprices
                        where
                        item_id     = {$avgitem["item_id"]} and
                        company_id  = {$headdata["note_company_id"]}";
               $currentavgcost = $CON->select($sql);
               $currentavgcost = $currentavgcost[0];

               //----------------------------------------------------------------------------------
               $compstock  = 0.00;
               $shops      = getShops($CON);
               foreach($shops AS $shop)
               {
                  if($shop["shop_company_id"] == $headdata["note_company_id"])
                     $compstock += getItemShopCurrentStock($CON, $shop["id"], $avgitem["item_id"], "item");
               }

               //----------------------------------------------------------------------------------
               $compcost    = $compstock * $currentavgcost["item_costprice_avg_netto"];
               $compcost   += $dif_netto;
               $newavgcost  = $compcost / $compstock;

               /*
               $sql = " update tran_average_costprices
                        set
                        item_costprice_avg_netto = {$newavgcost}
                        where
                        item_id     = {$avgitem["item_id"]} and
                        company_id  = {$headdata["note_company_id"]}";
               $CON->no_result($sql);
               */
            }
         }
      }
   
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id                 = {$noteid} and
               tran_type               = 'invoicesnotessbuy' and
               item_charges_used_id    > 0";
      $invbookings = $CON->select($sql);
      foreach($invbookings AS $invbooking)
      {
         $sql = " update tran_charges
                  set
                  tran_amount_used  = tran_amount_used - {$invbooking["tran_amount"]},
                  tran_amount_avail = tran_amount_avail + {$invbooking["tran_amount"]}
                  where
                  id = {$invbooking["item_charges_id"]}";
         $CON->no_result($sql);
      
         $sql = " update tran_charges_used
                  set
                  tran_booked = 0
                  where
                  id = {$invbooking["item_charges_used_id"]}";
         $CON->no_result($sql);
      }
      
      //----------------------------------------------------------------------------------
      $sql = " select *
               from tran_data
               where
               tran_id     = {$noteid} and
               tran_type   = 'invoicesnotessbuy' and
               tran_st_id  != 0";
      $invbookings = $CON->select($sql);

      foreach($invbookings AS $invbooking)
      {
         changeItemShopStorehouseStock($CON, (int)$invbooking["tran_shop_id"], (int)$invbooking["tran_st_id"],
                                       (int)$invbooking["item_id"], $invbooking["item_type"],
                                       (float)$invbooking["tran_amount"], true);
                      
      }
      $sql = " delete from tran_data
               where
               tran_id     = {$noteid} and
               tran_type   = 'invoicesnotessbuy'";
      $CON->no_result($sql);
   }

   $sql = " update invoices_notes_buy
            set
            note_remote_synced = 0
            where
            id = {$noteid}";
   $CON->no_result($sql);
}
