<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
$_sesmodulename         = "buy_invoices";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = " asc, 10 desc, 1 asc";
$_sortlinks             = Array();
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
unset($_SESSION["STATS"][$_sesmodulename]);

unset($_SUPNAMES);
unset($_SUPPTOTAL);
unset($_SUPPTRANS);
unset($_TOTAL);
//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_supplier"]      = (int)$_REQUEST["sql_supplier"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_calcdate1"]      = trim($_REQUEST["sql_calcdate1"]);
   $_SESSION[$_sesmodulename]["sql_calcdate"]      = trim($_REQUEST["sql_calcdate"]);
   $_SESSION[$_sesmodulename]["sql_paystatus"]     = $_REQUEST["sql_paystatus"];
   $_SESSION[$_sesmodulename]["sql_vencstatus"]    = $_REQUEST["sql_vencstatus"];
   $_SESSION[$_sesmodulename]["sql_trantype"]      = $_REQUEST["sql_trantype"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_selmode_type"]  = (int)$_REQUEST["sql_selmode_type"];
   $_SESSION[$_sesmodulename]["sql_note_pendiente"]  = (int)$_REQUEST["sql_note_pendiente"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_number"]        = trim(addslashes(str_replace("*","%",$_REQUEST["sql_number"])));
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;

   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "supp_stats_apply_dscfinance_") !== false && strpos($reqkey, "supp_stats_apply_dscfinance_") == 0)
      {
         $suppid = substr($reqkey, strrpos($reqkey, "_") +1);
         $supp_stats_apply_dscfinance = (int)$_REQUEST[$reqkey];
         
         $sql = " update supplier
                  set
                  supp_stats_apply_dscfinance = {$supp_stats_apply_dscfinance}
                  where
                  id = {$suppid}";
         $CON->no_result($sql);
      }
   }

   //----------------------------------------------------------------------------------
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "supp_stats_apply_paymentid_") !== false && strpos($reqkey, "supp_stats_apply_paymentid_") == 0)
      {
         $suppid = substr($reqkey, strrpos($reqkey, "_") +1);
         $supp_stats_apply_paymentid = (int)$_REQUEST[$reqkey];
         
         $sql = " update supplier
                  set
                  supp_stats_apply_paymentid = {$supp_stats_apply_paymentid}
                  where
                  id = {$suppid}";
         $CON->no_result($sql);
      }
   }
}

$companies  = getCompanies($CON, true);
$shops      = getShops($CON);
if(!(int)$_SESSION[$_sesmodulename]["sql_company"])
   $_SESSION[$_sesmodulename]["sql_company"] = $companies[0]["id"];
if(!(int)$_SESSION[$_sesmodulename]["sql_shop"])
{
   $first = false;
   foreach($shops AS $shop)
      if($shop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"] && !$first)
      {
         $_SESSION[$_sesmodulename]["sql_shop"] = $shop["id"];
         $first = true;
      }
}

if($_SESSION[$_sesmodulename]["sql_calcdate1"] == "")
   $_SESSION[$_sesmodulename]["sql_calcdate1"] = date('d.m.Y');

if($_SESSION[$_sesmodulename]["sql_calcdate"] != "")
{
   $_USETIME = explode(".", $_SESSION[$_sesmodulename]["sql_calcdate"]);
   $_USETIME = mktime(date('H'), date('i'), date('s'), $_USETIME[1], $_USETIME[0], $_USETIME[2]);
   
   $_FRMTIME = explode(".", $_SESSION[$_sesmodulename]["sql_calcdate1"]);
   $_FRMTIME = mktime(date('H'), date('i'), date('s'), $_FRMTIME[1], $_FRMTIME[0], $_FRMTIME[2]);
}
else
{
   $_USETIME = explode(".", $_SESSION[$_sesmodulename]["sql_calcdate1"]);
   $_USETIME = mktime(date('H'), date('i'), date('s'), $_USETIME[1], $_USETIME[0], $_USETIME[2]);
   $_FRMTIME = 0;
}

//----------------------------------------------------------------------------------
if(!is_array($_SESSION[$_sesmodulename]["sql_paystatus"]))
   $_SESSION[$_sesmodulename]["sql_paystatus"] = Array(0=>0);
if(!is_array($_SESSION[$_sesmodulename]["sql_vencstatus"]))
   $_SESSION[$_sesmodulename]["sql_vencstatus"] = Array(0=>0,1=>1);
if(!is_array($_SESSION[$_sesmodulename]["sql_trantype"]))
   $_SESSION[$_sesmodulename]["sql_trantype"] = Array(0=>0,1=>1,2=>2);
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode"])
   $_SESSION[$_sesmodulename]["sql_selmode"] = 2;
if(!(int)$_SESSION[$_sesmodulename]["sql_selmode_type"])
   $_SESSION[$_sesmodulename]["sql_selmode_type"] = 1;
      
//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_month1"] == "")
{
   $_SESSION[$_sesmodulename]["sql_month1"]  = 1;
   $_SESSION[$_sesmodulename]["sql_year1"]   = (int)date('Y');
   $_SESSION[$_sesmodulename]["sql_month2"]  = (int)date('m');
   $_SESSION[$_sesmodulename]["sql_year2"]   = (int)date('Y');
}
if($_SESSION[$_sesmodulename]["sql_date"] == "")
   $_SESSION[$_sesmodulename]["sql_date"] = date('d.m.Y');
if($_SESSION[$_sesmodulename]["sql_date_pfrom"] == "")
{
   $_SESSION[$_sesmodulename]["sql_date_pfrom"] = date('d.m.Y', time() - (86400 * 7));
   $_SESSION[$_sesmodulename]["sql_date_pto"]   = date('d.m.Y');
}

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode"] == 1)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 2)
{
   $sql_datefrom  = mktime(0, 0, 0, $_SESSION[$_sesmodulename]["sql_month1"], 1, $_SESSION[$_sesmodulename]["sql_year1"]);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], 1, $_SESSION[$_sesmodulename]["sql_year2"]);
   $datedays      = date('t', $sql_dateto);
   $sql_dateto    = mktime(23, 59, 59, $_SESSION[$_sesmodulename]["sql_month2"], $datedays, $_SESSION[$_sesmodulename]["sql_year2"]);
}
elseif($_SESSION[$_sesmodulename]["sql_selmode"] == 3)
{
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pfrom"]);
   $sql_datefrom  = mktime(0, 0, 0, $datearr[1], $datearr[0], $datearr[2]);
   $datearr       = explode(".", $_SESSION[$_sesmodulename]["sql_date_pto"]);
   $sql_dateto    = mktime(23, 59, 59, $datearr[1], $datearr[0], $datearr[2]);
}

//----------------------------------------------------------------------------------
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 100);

//----------------------------------------------------------------------------------
if((int)$_REQUEST["NoteUnmarkPendiente"])
{
   $sql = " update invoices_notes_buy
            set
            note_mark_pendiente = 0
            where
            id = {$_REQUEST["NoteUnmarkPendiente"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$datsql = " select t1.id, t1.invc_receipt_date, t1.invc_number, t1.invc_docnumber, t1.invc_total_brutto,
                t1.invc_import_total, t1.invc_exc_rate, t1.invc_payed, 
                t4.company_short, 'type' 'invcoice', 'note_invcnumber' 'note_invcnumber',
                t1.invc_importation, t6.supp_company, t1.invc_estpay_date,
                t1.invc_supplier_id, t6.supp_rut, 'note_parent_invcid' '0',
                t6.supp_dsc_finance, t6.supp_dsc_finance_calc, t1.invc_total_netto_dsc,
                t1.invc_taxes, t6.supp_stats_apply_dscfinance, t6.supp_stats_apply_paymentid,
                t1.invc_paymentid, t1.invc_company_id, t1.invc_shop_id, t1.invc_desc,
                t1.invc_mark_nodsc, t1.invc_mark_obs, 'note_mark_pendiente' '0'
         from invoices_buy t1
         INNER JOIN company_data t4    ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
         LEFT OUTER JOIN supplier t6   ON ( t1.invc_supplier_id = t6.id )
         where
         t1.invc_status       > 1 and ";

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 1)
   $datsql .= " t1.invc_receipt_date between {$sql_datefrom} and {$sql_dateto} ";
else
   $datsql .= " t1.invc_estpay_date between {$sql_datefrom} and {$sql_dateto} ";
   
//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.invc_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if($_SESSION[$_sesmodulename]["sql_number"] != "")
   $datsql .= " and ( t1.invc_number    like '%{$_SESSION[$_sesmodulename]["sql_number"]}%' or
                      t1.invc_docnumber like '%{$_SESSION[$_sesmodulename]["sql_number"]}%' ) ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.invc_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
      
//----------------------------------------------------------------------------------
$seastatstr = "";
foreach($_SESSION[$_sesmodulename]["sql_paystatus"] AS $seastat)
   $seastatstr .= $seastat.",";
$seastatstr = substr($seastatstr, 0, -1);
$datsql .= " and t1.invc_payed IN ({$seastatstr}) ";

//----------------------------------------------------------------------------------
$currtme = time();
if(array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
   array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
   $datsql .= " and !(t1.invc_payed = 0
                and t1.invc_estpay_date > 0
                and t1.invc_estpay_date <= {$currtme}) ";
if(array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
   array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
   $datsql .= " and t1.invc_payed = 0
                and t1.invc_estpay_date > 0
                and t1.invc_estpay_date <= {$currtme} ";
                
//----------------------------------------------------------------------------------
if(array_search(0, $_SESSION[$_sesmodulename]["sql_trantype"]) === false)
   $datsql .= " and 1 = 3 ";

$datsql .= "UNION ALL
            select t1.id, t1.note_receipt_date 'invc_receipt_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                   t1.note_total_brutto 'invc_total_brutto', t1.note_import_total 'invc_import_total',
                   t1.note_exc_rate 'invc_exc_rate', t1.note_payed 'invc_payed', 
                   t4.company_short, t1.note_type 'invcoice', t1.note_invcnumber, t1.note_importation 'invc_importation',
                   t6.supp_company, t1.note_estpay_date 'invc_estpay_date',
                   t1.note_supplier_id 'invc_supplier_id', t6.supp_rut, t1.note_parent_invcid 'note_parent_invcid',
                   t6.supp_dsc_finance, t6.supp_dsc_finance_calc, t1.note_total_netto 'invc_total_netto_dsc',
                   t1.note_taxes 'invc_taxes', t6.supp_stats_apply_dscfinance, t6.supp_stats_apply_paymentid,
                   t1.note_paymentid 'invc_paymentid', t1.note_company_id 'invc_company_id', t1.note_shop_id 'invc_shop_id',
                   t1.note_desc 'invc_desc', '' '', '' '', t1.note_mark_pendiente
            from invoices_notes_buy t1
            INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN supplier t6   ON ( t1.note_supplier_id = t6.id )
            where
            t1.note_status       > 1 and ";

//----------------------------------------------------------------------------------
if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 1)
   $datsql .= " t1.note_date between {$sql_datefrom} and {$sql_dateto} ";
else
   $datsql .= " t1.note_estpay_date between {$sql_datefrom} and {$sql_dateto} ";

//----------------------------------------------------------------------------------
if((int)$_SESSION[$_sesmodulename]["sql_company"])
   $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   $datsql .= " and t1.note_supplier_id  = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   $datsql .= " and t1.note_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
      
//----------------------------------------------------------------------------------
$datsql .= " and t1.note_payed = 1 ";

/*
if($_SESSION[$_sesmodulename]["sql_number"] != "")
   $datsql .= " and ( t1.note_number    like '%{$_SESSION[$_sesmodulename]["sql_number"]}%' or
                      t1.note_docnumber like '%{$_SESSION[$_sesmodulename]["sql_number"]}%' ) ";

//----------------------------------------------------------------------------------
$seastatstr = "";
foreach($_SESSION[$_sesmodulename]["sql_paystatus"] AS $seastat)
   $seastatstr .= $seastat.",";
$seastatstr = substr($seastatstr, 0, -1);
$datsql .= " and t1.note_payed IN ({$seastatstr}) ";

//----------------------------------------------------------------------------------
$currtme = time();
if(array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
   array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
   $datsql .= " and !(t1.note_payed = 0
                and t1.note_estpay_date > 0
                and t1.note_estpay_date <= {$currtme}) ";
if(array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false &&
   array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) === false)
   $datsql .= " and t1.note_payed = 0
                and t1.note_estpay_date > 0
                and t1.note_estpay_date <= {$currtme} ";
*/

//----------------------------------------------------------------------------------
if(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) === false &&
   array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) === false)
   $datsql .= " and 1 = 3 ";
elseif(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false &&
       array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) === false)
   $datsql .= " and t1.note_type = 1 ";
elseif(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) === false &&
       array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false)
   $datsql .= " and t1.note_type = 2 ";
   
//----------------------------------------------------------------------------------
$datsql .= " order by 13 asc";
$trans = $CON->select($datsql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($trans) && $trans != false; $x++)
{
   $row = $trans[$x];
   
   if((int)$row["supp_stats_apply_paymentid"] &&
      $row["supp_stats_apply_paymentid"] != $row["invc_paymentid"] &&
      $row["type"] == "typeinvcoice")
   {
      //----------------------------------------------------------------------------------
      $sql = " delete from supplier_order where id = -2"; $CON->no_result($sql);
      $sql = " delete from supplier_order_items where sord_id = -2"; $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $dsc_pay                = getSupplierPaymentDiscount($CON, $row["invc_supplier_id"], $row["supp_stats_apply_paymentid"]);
      $sord_payment_dsc       = (float)$dsc_pay["dct_scale_discount"];
      $sord_payment_dsctype   = (int)$dsc_pay["dct_scale_type"];

      //----------------------------------------------------------------------------------
      $sql = " insert into supplier_order
               (id, sord_status, sord_company_id, sord_shop_id, sord_supplier_id, sord_taxes, sord_paymentid,
                sord_payment_dsc, sord_payment_dsctype)
               VALUES
               (-2, -1, {$row["invc_company_id"]}, {$row["invc_shop_id"]},
                {$row["invc_supplier_id"]}, {$row["invc_taxes"]},
                {$row["supp_stats_apply_paymentid"]}, {$sord_payment_dsc}, {$sord_payment_dsctype} )";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $tempparts     = getInvoiceBuyParts($CON, $row["id"]);
      $suppitempos   = 0;
      for($z = 0; $z < count($tempparts) && $tempparts != false; $z++)
      {
         $tempposdata = getInvoiceBuyPartsItems($CON, $row["id"], $tempparts[$z]["id"]);
         foreach($tempposdata AS $tempposrow)
         {
            $sql = " insert into supplier_order_items
                     (sord_id, item_id, item_pos, item_amount, item_type, item_costprice_brutto,
                      item_costprice_taxes_perc, item_costprice_netto, item_costprice_taxes)
                     VALUES
                     (-2, {$tempposrow["item_id"]}, {$suppitempos}, {$tempposrow["item_amount"]}, '{$tempposrow["item_type"]}',
                      {$tempposrow["item_costprice_brutto"]}, {$tempposrow["item_costprice_taxes_perc"]},
                      {$tempposrow["item_costprice_netto"]}, {$tempposrow["item_costprice_taxes"]})";
             $CON->no_result($sql);
             recalcSupplierOrderItem($CON, -2, $tempposrow["item_id"], $suppitempos);
             $suppitempos++;
          }
      }
      recalcSupplierOrder($CON, -2);

      //----------------------------------------------------------------------------------
      $sql = " select sord_total_brutto, sord_total_netto
               from supplier_order
               where
               id = -2";
      $calcinvcbrutto = $CON->select($sql);
      $calcinvcnetto  = (float)$calcinvcbrutto[0]["sord_total_netto"];
      $calcinvcbrutto = (float)$calcinvcbrutto[0]["sord_total_brutto"];

      //----------------------------------------------------------------------------------
      $row["invc_total_brutto_recalc"] = $calcinvcbrutto;
      $row["invc_total_netto_recalc"]  = $calcinvcnetto;

      //----------------------------------------------------------------------------------
      $sql = " delete from supplier_order where id = -2"; $CON->no_result($sql);
      $sql = " delete from supplier_order_items where sord_id = -2"; $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   if($row["type"] == "typeinvcoice" && (int)$row["supp_stats_apply_paymentid"])
   {
      $sql = " select pay_days
               from payments
               where
               id = {$row["supp_stats_apply_paymentid"]}";
      $recalcpaydays = $CON->select($sql);

      $row["invc_estpay_date"] = $row["invc_receipt_date"] + ((int)$recalcpaydays[0]["pay_days"] * 86400);
   }

   //----------------------------------------------------------------------------------
   if(!is_array($_SUPPTRANS[$row["invc_supplier_id"]]))
      $_SUPPTRANS[$row["invc_supplier_id"]] = Array();
      
   $_SUPPTRANS[$row["invc_supplier_id"]][] = $row;
      
   $_SUPNAMES[$row["invc_supplier_id"]]["NAME"]             = $row["supp_company"];
   $_SUPNAMES[$row["invc_supplier_id"]]["RUT"]              = $row["supp_rut"];
   $_SUPNAMES[$row["invc_supplier_id"]]["PAYMENTDEFAULT"]   = $row["supp_stats_apply_paymentid"];

   //----------------------------------------------------------------------------------
   if($row["supp_dsc_finance_calc"] == "NC" && $row["supp_dsc_finance"] > 0.00 && $row["type"] == "typeinvcoice")
   {
      $_SUPNAMES[$row["invc_supplier_id"]]["DSCFINANCE"] = true;
      $_SUPNAMES[$row["invc_supplier_id"]]["DSCFINANCEAPPLY"] = $row["supp_stats_apply_dscfinance"];
   }
}

//----------------------------------------------------------------------------------
foreach(array_keys($_SUPPTRANS) AS $suppid)
{
   $temparr = Array();
   $trans   = $_SUPPTRANS[$suppid];
   usort($trans, "buygetStatsResultOrderCallbackRes");
   
   for($x = 0; $x < count($trans) && $trans != false; $x++)
   {
      if($trans[$x]["type"] == "typeinvcoice")
      {
         if($trans[$x]["invc_total_brutto_recalc"] > 0.00)
         {
            $base_invc_price = $trans[$x]["invc_total_brutto_recalc"];
            $base_invc_netto = $trans[$x]["invc_total_netto_recalc"];
         }
         else
         {
            $base_invc_price = $trans[$x]["invc_total_brutto"];
            $base_invc_netto = $trans[$x]["invc_total_netto_dsc"];
         }
         
         if((int)$_SUPNAMES[$suppid]["DSCFINANCE"] && (int)$_SUPNAMES[$suppid]["DSCFINANCEAPPLY"])
         {
            $numberlim = 0;
            if((int)$trans[$x]["invc_importation"])
               $numberlim = 2;

            $diff = round($base_invc_netto / 100 * $trans[$x]["supp_dsc_finance"], $numberlim);
            if((int)$trans[$x]["invc_taxes"])
               $diff = round($diff / 100 * (100 + $_SESSION["_CONF"]["conf_taxes"]),$numberlim);
            $trans[$x]["real_value"] = $base_invc_price - $diff;
         }
         else
            $trans[$x]["real_value"] = $base_invc_price;
            
         $temparr[] = $trans[$x];
         $lastidx   = count($temparr) -1;
         for($y = 0; $y < count($trans) && $trans != false; $y++)
         {
            if(($trans[$y]["type"] == "1" || $trans[$y]["type"] == "2") && (int)$trans[$y]["note_parent_invcid"] == (int)$trans[$x]["id"])
            {
               $trans[$y]["_ARROW"] = true;

               if($trans[$y]["type"] == "1")
                  $temparr[$lastidx]["_NCADDMNT"] -= $trans[$y]["invc_total_brutto"];
               elseif($trans[$y]["type"] == "2")
                  $temparr[$lastidx]["_NCADDMNT"] += $trans[$y]["invc_total_brutto"];

               $temparr[] = $trans[$y];
            }
         }
      }
      else
      {
         $found = false;
         for($y = 0; $y < count($trans) && $trans != false; $y++)
         {
            if((int)$trans[$x]["note_parent_invcid"] == (int)$trans[$y]["id"])
               $found = true;
         }
         if(!$found && (int)$trans[$x]["note_mark_pendiente"] == 1 && (int)$_SESSION[$_sesmodulename]["sql_note_pendiente"])
         {
            $trans[$x]["_PENDIENTEMARK"] = true;
            $temparr[] = $trans[$x];
         }
      }
   }
   $_SUPPTRANS[$suppid] = $temparr;
}

//----------------------------------------------------------------------------------
$suppliers  = getSuppliers($CON);
$companies  = getCompanies($CON);
$shops      = getShops($CON);
$payments   = getPayments($CON);

if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
{
   $sql = " select supp_company
            from supplier
            where
            id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
   $suppdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = $suppdata[0]["supp_company"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"] = "TODO";

//----------------------------------------------------------------------------------
$_SESSION["STATS"][$_sesmodulename]["_SUPPTRANS"]  = $_SUPPTRANS;
$_SESSION["STATS"][$_sesmodulename]["_SUPNAMES"]   = $_SUPNAMES;
$_SESSION["STATS"][$_sesmodulename]["_PAYMENTS"]   = $payments;
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<script language="JavaScript">
function setNoteMarkPendiente(noteid)
{
   if(askDel(''))
   {
      document.xform_itemsearch.NoteUnmarkPendiente.value=noteid;
      document.xform_itemsearch.submit();
   }
}
</script>
<form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
<input type="hidden" name="subexec" value="search">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="printpdf" value="0">
<input type="hidden" name="printxls" value="0">
<input type="hidden" name="NoteUnmarkPendiente" value="">
<table border="0" cellpadding="0" cellspacing="0" width="980">
<tr>
   <td height="30"><b class="content_header">Facturas a pagar</b></td>
   <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults(count($trans)); else echo $savemsg;?></td>
</tr>
<tr>
   <td class="content_headerline" colspan="2">&nbsp;</td>
</tr>
</table>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
<tr>
   <td>
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
         <td class="content_rowl">Proveedor</td>
         <td class="content_row"><?php printOverviewSupplierSelect($suppliers, $_sesmodulename) ?></td>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <table border="0" class="content_table" cellpadding="0" cellspacing="0">
            <tr>
               <td class="content_row_clear" width="70">
                  <input type="radio" name="sql_selmode" value="2"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 2) echo "checked"?>> Meses
               </td>
               <td class="content_row_clear" width="205" id="idx_selmode2" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 2) echo "style='display:none'"?>>
                  <nobr>
                  <select class="text" name="sql_month1" id="sql_month1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month1"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year1" id="sql_year1"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year1"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  &nbsp;-&nbsp;
                  <select class="text" name="sql_month2" id="sql_month2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     for($x = 1; $x <= 12; $x++)
                     {
                        $dsp_month = $x;
                        if($dsp_month < 10)
                           $dsp_month = "0{$dsp_month}";
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_month2"]) echo "selected" ?>><?=$dsp_month?></option>
                        <?php
                     }
                     ?>
                  </select>
                  <select class="text" name="sql_year2" id="sql_year2"
                  onmousedown="markfield(this,0)" onblur="markfield(this,1)">
                     <?php
                     $startyear  = date('Y') -3;
                     $endyear    = date('Y');

                     for($x = $startyear; $x <= $endyear; $x++)
                     {
                        ?>
                        <option value="<?=$x?>"
                        <?php if($x == $_SESSION[$_sesmodulename]["sql_year2"]) echo "selected" ?>><?=$x?></option>
                        <?php
                     }
                     ?>
                  </select>
                  </nobr>
               </td>
               <td class="content_row_clear" width="50">
                  <input type="radio" name="sql_selmode" value="1"
                  onclick="document.getElementById('idx_selmode1').style.display='';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='none';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 1) echo "checked"?>> Dia
               </td>
               <td class="content_row_clear" width="110" id="idx_selmode1" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 1) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:80px" id="sql_date" name="sql_date" 
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date"]?>">
                  </nobr>
               </td>
               <td class="content_row_clear" width="75">
                  <input type="radio" name="sql_selmode" value="3"
                  onclick="document.getElementById('idx_selmode1').style.display='none';
                           document.getElementById('idx_selmode2').style.display='none';
                           document.getElementById('idx_selmode3').style.display='';"
                  <?php if($_SESSION[$_sesmodulename]["sql_selmode"] == 3) echo "checked"?>> Periodo
               </td>
               <td class="content_row_clear" width="180" id="idx_selmode3" <?php if($_SESSION[$_sesmodulename]["sql_selmode"] != 3) echo "style='display:none'"?>>
                  <nobr>
                  <input type="text" style="width:65px" id="sql_date_pfrom" name="sql_date_pfrom"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pfrom"]?>">
                  -
                  <input type="text" style="width:65px" id="sql_date_pto" name="sql_date_pto"
                  class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
                  onfocus="markfield(this,0)" onblur="markfield(this,1)"
                  value="<?=$_SESSION[$_sesmodulename]["sql_date_pto"]?>">
                  </nobr>
               </td>
            </tr>
            </table>
            Filtrar por:
            <input type="radio" name="sql_selmode_type" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 1) echo "checked"?>> Fecha emission
            <input type="radio" name="sql_selmode_type" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 2) echo "checked"?>> Fecha vencimiento
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Número</td>
         <td class="content_row">
            <input type="text" class="text" style="width:195px"
            name="sql_number" value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_number"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">Tipo</td>
         <td class="content_row">
            <input type="checkbox" name="sql_trantype[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Facturas&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <input type="checkbox" name="sql_trantype[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Notas de credito
            <input type="checkbox" name="sql_trantype[]" value="2" <?php if(array_search(2, $_SESSION[$_sesmodulename]["sql_trantype"]) !== false) echo "checked"?>>Notas de debito
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Pagado</td>
         <td class="content_row">
            <input type="checkbox" name="sql_paystatus[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>No pagados
            <input type="checkbox" name="sql_paystatus[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>Pagados
         </td>
         <td class="content_rowl">Vencido</td>
         <td class="content_row">
            <input type="checkbox" name="sql_vencstatus[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false) echo "checked"?>>No vencidos
            <input type="checkbox" name="sql_vencstatus[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false) echo "checked"?>>Vencidos
         </td>
      </tr>
      <tr>
         <td class="content_rowl">Demora</td>
         <td class="content_row">
            1. Fecha: <input type="text" style="width:80px" id="sql_calcdate1" name="sql_calcdate1" 
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=$_SESSION[$_sesmodulename]["sql_calcdate1"]?>">&nbsp;&nbsp;
            
            2. Fecha: <input type="text" style="width:80px" id="sql_calcdate" name="sql_calcdate" 
            class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?=$_SESSION[$_sesmodulename]["sql_calcdate"]?>">&nbsp;&nbsp;
         </td>
         <td class="content_rowl">Notas</td>
         <td class="content_row">
            <input type="checkbox" name="sql_note_pendiente" value="1"
            <?php if((int)$_SESSION[$_sesmodulename]["sql_note_pendiente"]) echo "checked"?>>
            Por descontar
         </td>
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
                  if(count($trans) > 0 && $trans != false)
                  {
                     printButton("Imprimir PDF", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printpdf.value='1';submitForm(document.xform_itemsearch)", "document-pdf", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="left">
                  <?php
                  if(count($trans) > 0 && $trans != false)
                  {
                     printButton("Imprimir XLS", "postnav", "javascript: deactivateFormChange()", "document.xform_itemsearch.printxls.value='1';submitForm(document.xform_itemsearch)", "document-excel", 130);
                     $_SESSION["_SUBMITBTN"] = 1;
                  }
                  ?>
               </td>
               <td align="right">
                  <?php
                  if((int)$_SESSION[$_sesmodulename]["search_active"])
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
      <br>
   </td>
</tr>
<tr>
   <td>
      <script language="JavaScript">

      //----------------------------------------------------------------------------------
      function saveComments(xform, relid, relmode)
      {
         $.get('/libs/modules/stats/invoices/buy.savecomment.php?relid=' +relid +'&relmode=' +relmode +'&commtext=' +$('#' +xform).val(), function(html) {
            $('#btn' +xform).css('backgroundColor', '#AAFFA6');
         });
      }
      </script>
      <?php
      foreach(array_keys($_SUPNAMES) AS $suppid)
      {
         $trans = $_SUPPTRANS[$suppid];
         ?>
         <?=Nifty_printH("box1", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="110">
            <col width="120">
            <col width="110">
            <col width="100">
            <col width="85">
            <col width="85">
            <col width="85">
            <col width="85">
            <col width="85">
            <col width="50">
            <col>
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="11">
               <table border="0" cellpadding="0" cellspacing="0" width="100%">
               <colgroup>
                  <col>
                  <col width="200">
                  <col width="330">
               </colgroup>
               <tr>
                  <td class="content_tbl_header" style="padding:0px">
                     <?=$_SUPNAMES[$suppid]["NAME"]?>, RUT: <?=$_SUPNAMES[$suppid]["RUT"]?>
                  </td>
                  <td class="content_tbl_header" style="padding:0px" align="right">
                     <?php
                     if($_SUPNAMES[$suppid]["DSCFINANCE"])
                     {  ?>
                        <nobr>
                        Descuento financ.:
                        <input type="radio" class="checkbox" name="supp_stats_apply_dscfinance_<?=$suppid?>" value="1"
                        onclick="submitForm(document.xform_itemsearch)" <?php if((int)$_SUPNAMES[$suppid]["DSCFINANCEAPPLY"]) echo "checked"?>> SI
                        <input type="radio" class="checkbox" name="supp_stats_apply_dscfinance_<?=$suppid?>" value="0"
                        onclick="submitForm(document.xform_itemsearch)" <?php if(!(int)$_SUPNAMES[$suppid]["DSCFINANCEAPPLY"]) echo "checked"?>> NO
                        </nobr>
                        <?php
                     }
                     ?>
                  </td>
                  <td class="content_tbl_header" style="padding:0px;display:none" align="right">
                     <nobr>
                     Forma de pago:
                     <select class="text" style="width:200px" name="supp_stats_apply_paymentid_<?=$suppid?>"
                     onchange="submitForm(document.xform_itemsearch)">
                        <option>----- SEGUN DOCUMENTO -----</option>
                        <?php
                        foreach($payments as $payment)
                        {  ?>
                           <option value="<?=$payment["id"]?>"
                           <?php if($payment["id"] == $_SUPNAMES[$suppid]["PAYMENTDEFAULT"]) echo "selected"?>><?=$payment["pay_title"]?></option>
                           <?php
                        }
                        ?>
                     </select>
                     </nobr>
                  </td>
               </tr>
               </table>
            </td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os"><nobr>Fecha Recepción</nobr></td>
            <td class="content_tbl_subheader content_row_os"><nobr>DOCTO.<nobr></td>
            <td class="content_tbl_subheader content_row_os"><nobr>N° DOCTO.</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="right"><nobr>Monto DOCTO.</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="right"><nobr>Valor real</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="left"><nobr>Factura/Rel.</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="left"><nobr>Observaciones</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="right"><nobr>Deuda/Monto</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="right"><nobr>Fecha Venc.</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="center"><nobr>Dem.</nobr></td>
            <td class="content_tbl_subheader content_row_os" align="center">Pagado</td>
         </tr>
         <?php
         unset($_SUPPTOTAL["monto"]);
         unset($_SUPPTOTAL["real"]);
         unset($_SUPPTOTAL["deuda"]);
         //----------------------------------------------------------------------------------
         for($x = 0; $x < count($trans) && $trans != false; $x++)
         {
            $tran = $trans[$x];

            if(!(int)$tran["invc_estpay_date"])
               $tran["invc_estpay_date"] = $tran["invc_receipt_date"];

            //----------------------------------------------------------------------------------
            $tran_type = "";
            if($tran["type"] == "typeinvcoice")
            {
               $tran_type = "Factura";
               $_relmid   = "723";
               $_relid    = $tran["id"];
               $_relmode  = "invoices_buy";
            }
            elseif($tran["type"] == "1")
            {
               $tran_type = "Nota de credito";
               $_relmid   = "729";
               $_relid    = $tran["id"];
               $_relmode  = "invoices_notes_buy";
            }
            elseif($tran["type"] == "2")
            {
               $tran_type = "Nota de debito";
               $_relmid   = "729";
               $_relid    = $tran["id"];
               $_relmode  = "invoices_notes_buy";
            }
            
            $icon = "";
            if($tran["_ARROW"])
               $icon = "<img src='/images/menu/icons/arrow-turn-000-left.png' style='vertical-align:bottom'>&nbsp;";
            if($tran["type"] == "typeinvcoice")
            {
               $value_real = $tran["real_value"];

               if($value_real != $tran["invc_total_brutto"])
               {
                  $value_pre  = "<b class='msg_save_ok'>";
                  $value_suf  = "</b>";
               }
               else
               {
                  $value_pre  = "";
                  $value_suf  = "";
               }
            }
            else
            {
               $value_real = $tran["invc_total_brutto"];
               $value_pre  = "";
               $value_suf  = "";
            }

            //----------------------------------------------------------------------------------
            if($tran["note_invcnumber"] == "note_invcnumbernote_invcnumber")
               $tran["note_invcnumber"] = "";

            //----------------------------------------------------------------------------------
            $tranpayed = getTranPayments($CON, $tran["id"], $_relmode, true);
            $_TOTAL[$tran_type]["REALPAYED"] += $tranpayed;

            //----------------------------------------------------------------------------------
            $cssprefix = "";
            $csssuffix = "";
            if(!(int)$tran["invc_payed"] && $tran["invc_estpay_date"] > 0 && $tran["invc_estpay_date"] <= $_USETIME)
            {
               $cssprefix = "<b class='msg_save_err'>";
               $csssuffix = "</b>";
               $_TOTAL[$tran_type]["VENC"] += $value_real;
            }

            //----------------------------------------------------------------------------------
            $_TOTAL[$tran_type]["TOTAL"] += $tran["invc_total_brutto"];
            $_TOTAL[$tran_type]["REAL"]  += $value_real;
               
            //----------------------------------------------------------------------------------
            if((int)$tran["invc_payed"])
            {
               $paybgcss = "#E1FFD6";
               $_TOTAL[$tran_type]["PAYED"] += $value_real;
               $trannopayed = 0.00;
            }
            else
            {
               $paybgcss = "#FFD6D8";
               $_TOTAL[$tran_type]["NOPAYED"] += $value_real;

               $trannopayed = $value_real - $tranpayed + $tran["_NCADDMNT"];
               
               $_TOTAL[$tran_type]["REALNOPAYED"] += $trannopayed;
               $_TOTAL[$tran_type]["REALVENC"] += $trannopayed;
            }

            //----------------------------------------------------------------------------------
            if((int)$tran["invc_importation"])
            {
               $numberlim = 2;
               $moneystr  = "&nbsp;[US]";
            }
            else
            {
               $numberlim = 0;
               $moneystr  = "";
            }

            //----------------------------------------------------------------------------------
            if($_FRMTIME && $_USETIME)
               $demstr = ((int)(($_FRMTIME - $tran["invc_receipt_date"]) / 86400)+1)."-".((int)(($_USETIME - $tran["invc_receipt_date"]) / 86400)+1);
            else
               $demstr = (int)(($_USETIME - $tran["invc_receipt_date"]) / 86400)+1;

            //----------------------------------------------------------------------------------
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_receipt_date"] = date('d.m.Y', $tran["invc_receipt_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["tran_type"]         = $tran_type;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_docnumber"]    = $tran["invc_docnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_total_brutto"] = printPrice($tran["invc_total_brutto"], $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["value_real"]        = printPrice($value_real, $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["note_invcnumber"]   = $tran["note_invcnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["tranpayed"]         = printPrice($tranpayed, $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["trannopayed"]       = printPrice($trannopayed, $numberlim);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["paydays"]           = $demstr;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_desc"]         = $tran["invc_desc"];
            if($tran["invc_estpay_date"] > 0)
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_estpay_date"] = date('d.m.Y', $tran["invc_estpay_date"]);
            else
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_estpay_date"] = " ";
               
            if((int)$tran["invc_payed"])
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_payed"] = "SI";
            else
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_payed"] = "NO";
            $sx++;

            //----------------------------------------------------------------------------------
            $alertimg = "";
            $alertcss = "";
            $alertimg2 = "";
            $alertcss2 = "";
            $alertobs2 = "";
            if((int)$tran["invc_mark_obs"])
            {
               $alertimg = "<img style='vertical-align:bottom' src='/images/menu/icons/exclamation-button.png' title='Con observaciones'>";
               $alertcss = "color:red;font-weight:bold";
            }
            if((int)$tran["invc_mark_nodsc"])
            {
               $value_real = $tran["invc_total_brutto"];
               $value_pre  = "";
               $value_suf  = "";
            }

            if($_relmode == "invoices_buy")
            {
               $sql = " select distinct comments
                        from stat_invoice_buy_comments
                        where
                        invc_id = {$tran["id"]}";
               $invccompcomments = $CON->select($sql);
               foreach($invccompcomments AS $invccompcomment)
                  $alertobs2 .= $invccompcomment["comments"]."\n";
               $alertobs2 = trim($alertobs2);

               if($alertobs2 != "")
               {
                  $alertobs2 = str_replace("'","", $alertobs2);
                  $alertimg2 = "<img style='vertical-align:bottom' src='/images/menu/icons/exclamation-button.png' title='{$alertobs2}'>";
                  $alertcss2 = "color:red;font-weight:bold";
               }
            }

            $_SUPPTOTAL["monto"] += $tran["invc_total_brutto"];
            $_SUPPTOTAL["real"]  += $value_real;
            $_SUPPTOTAL["deuda"] += $trannopayed;

            //----------------------------------------------------------------------------------
            ?>
            <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)">
               <td class="content_row_os" height="25"><?=$icon?><?=date('d.m.Y', $tran["invc_receipt_date"])?></td>
               <td class="content_row_os"><nobr><?=$tran_type?></nobr></td>
               <td class="content_row_os" style="<?=$alertcss?>;<?=$alertcss2?>"><?=$tran["invc_docnumber"]?>&nbsp;<?=$alertimg?><?=$alertimg2?></td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($tran["invc_total_brutto"], $numberlim)?><?=$moneystr?></nobr></td>
               <td class="content_row_os" align="right"><nobr><?=$value_pre?><?=printPrice($value_real, $numberlim, true)?><?=$moneystr?><?=$value_suf?></nobr></td>
               <td class="content_row_os"><?=$tran["note_invcnumber"]?>&nbsp;</td>
               <td class="content_row_os">
                  <nobr>
                  <?php
                  $formname = "xcomm_".$tran["id"]."_".md5(microtime());
                  ?>
                  <input type="text" class="text" style="width:80px" id="<?=$formname?>" value="<?=$tran["invc_desc"]?>">
                  <input type="button" class="button" value="OK" style="width:30px" id="btn<?=$formname?>"
                  onclick="saveComments('<?=$formname?>', '<?=$_relid?>', '<?=$_relmode?>')">
                  </nobr>
               </td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($trannopayed, $numberlim)?></nobr></td>
               <td class="content_row_os" align="right"><?=$cssprefix?><?php if($tran["invc_estpay_date"] > 0) echo date('d.m.Y', $tran["invc_estpay_date"]); else echo "&nbsp;";?><?=$csssuffix?></td>
               <td class="content_row_os" align="center"><?=$demstr?></td>
               <?php
               if($_relmode == "invoices_buy")
               {  ?>
                  <td class="content_row_os" align="center" style="background-color:<?=$paybgcss?>">
                     <input type="button" class="button" value="<?php if((int)$tran["invc_payed"]) echo "SI"; else echo "NO"?>" style="width:40px"
                     onclick="showFancybox('iframe.edit.php?mid=<?=$_relmid?>&id=<?=$_relid?>&mode=<?=$_relmode?>&exec=edit&subcatexec=payment&from=report', 'iframe', 1016, 510, 'auto')"
                     onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)">
                  </td>
                  <?php
               }
               else
               {
                  if((int)$trans[$x]["note_mark_pendiente"] == 1)
                  {  ?>
                     <td class="content_row_os" align="center" style="background-color:red;color:white;text-shadow:0px 0px 0px black">
                        <input type="checkbox" class="checkbox" checked onclick="setNoteMarkPendiente('<?=$tran["id"]?>')"><br>
                        Por Descontar
                     </td>
                     <?php
                  }
                  else
                  {  ?>
                     <td class="content_row_os" align="center">&nbsp;</td>
                     <?php
                  }
               }
               ?>
            </tr>
            <?php
         }
         ?>
         <tr>
            <td class="content_row_totals content_row_os" colspan="3">Total</td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_SUPPTOTAL["monto"], $numberlim)?></td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_SUPPTOTAL["real"], $numberlim)?></td>
            <td class="content_row_totals content_row_os" align="right">&nbsp;</td>
            <td class="content_row_totals content_row_os" align="right">&nbsp;</td>
            <td class="content_row_totals content_row_os" align="right"><?=printPrice($_SUPPTOTAL["deuda"], $numberlim)?></td>
            <td class="content_row_totals content_row_os" align="right">&nbsp;</td>
            <td class="content_row_totals content_row_os" align="right">&nbsp;</td>
            <td class="content_row_totals content_row_os" align="right">&nbsp;</td>
         </tr>
         <?php
         //----------------------------------------------------------------------------------
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_receipt_date"] = "Total";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["tran_type"]         = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_docnumber"]    = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_total_brutto"] = printPrice($_SUPPTOTAL["monto"], $numberlim);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["value_real"]        = printPrice($_SUPPTOTAL["real"], $numberlim);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["note_invcnumber"]   = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["tranpayed"]         = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["trannopayed"]       = printPrice($_SUPPTOTAL["deuda"], $numberlim);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["paydays"]           = " ";
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid][$x]["invc_desc"]         = " ";
         ?>
         </table>
         <?=Nifty_printF()?>
         <br>
         <?php
      }
      if($x)
      {  ?>
         <?=Nifty_printH("box2", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col width="110">
            <col width="110">
            <col width="110">
            <col width="110">
            <col width="110">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="6"><img src="./images/menu/icons/balance.png" style="vertical-align:bottom">&nbsp;&nbsp;TOTALES</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">&nbsp;</td>
            <td class="content_tbl_subheader content_row_os" align="right">Monto DOCTO.</td>
            <td class="content_tbl_subheader content_row_os" align="right">Valor real</td>
            <td class="content_tbl_subheader content_row_os" align="right">Pago/Monto</td>
            <td class="content_tbl_subheader content_row_os" align="right">Deuda/Monto</td>
            <td class="content_tbl_subheader content_row_os" align="right">Vencido/Monto</td>
         </tr>
         <tr>
            <td class="content_row_os">FACTURAS</td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["REAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#E1FFD6"><nobr><?=printPrice($_TOTAL["Factura"]["REALPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFD6D8"><nobr><?=printPrice($_TOTAL["Factura"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFEBC9"><nobr><?=printPrice($_TOTAL["Factura"]["VENC"], 2)?></nobr></td>
         </tr>
         <tr>
            <td class="content_row_os">NOTAS DE CREDITO</td>
            <td class="content_row_os" align="right"><nobr>-<?=printPrice($_TOTAL["Nota de credito"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right"><nobr>-<?=printPrice($_TOTAL["Nota de credito"]["REAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#E1FFD6"><nobr>-<?=printPrice($_TOTAL["Nota de credito"]["REALPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFD6D8"><nobr>-<?=printPrice($_TOTAL["Nota de credito"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFEBC9"><nobr>-<?=printPrice($_TOTAL["Nota de credito"]["VENC"], 2)?></nobr></td>
         </tr>
         <tr>
            <td class="content_row_os">NOTAS DE DEBITO</td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Nota de debito"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Nota de debito"]["REAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#E1FFD6"><nobr><?=printPrice($_TOTAL["Nota de debito"]["REALPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFD6D8"><nobr><?=printPrice($_TOTAL["Nota de debito"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFEBC9"><nobr><?=printPrice($_TOTAL["Nota de debito"]["VENC"], 2)?></nobr></td>
         </tr>
         <tr>
            <td class="content_row_totals content_row_os">TOTAL</td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["TOTAL"]       - $_TOTAL["Nota de credito"]["TOTAL"]        + $_TOTAL["Nota de debito"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["REAL"]        - $_TOTAL["Nota de credito"]["REAL"]         + $_TOTAL["Nota de debito"]["REAL"], 2)?></nobr></td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["REALPAYED"]   - $_TOTAL["Nota de credito"]["REALPAYED"]    + $_TOTAL["Nota de debito"]["REALPAYED"], 2)?></nobr></td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["REALNOPAYED"] - $_TOTAL["Nota de credito"]["REALNOPAYED"]  + $_TOTAL["Nota de debito"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["VENC"]        - $_TOTAL["Nota de credito"]["VENC"]         + $_TOTAL["Nota de debito"]["VENC"], 2)?></nobr></td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <?php
      }
      ?>
   </td>
</tr>
</table>
</form>
<?php
$_SESSION[$_sesmodulename]["_TOTALS"] = $_TOTAL;

//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsBuyInvoices($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsBuyInvoices($CON);
  
if($pdffile != "")
{
   $doctitle = "Facturas-a-pagar-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Facturas-a-pagar-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
