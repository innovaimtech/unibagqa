<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

unset($_TOTAL);
unset($_XDATA);
//----------------------------------------------------------------------------------
if((int)$_REQUEST["sendmail"])
{
   $filedir  = "./docs.print/";
   $filename = $_REQUEST["docattach"];
   $fileext  = ".pdf";
   $xpdffile  = "{$filedir}{$filename}{$fileext}";

   $temp["NAME"]  = "Cuenta-Corriente-".date("d-m-Y")."{$fileext}";
   $temp["FILE"]  = $xpdffile;

   $attachfiles[0] = $temp;

   $text    = '<html>
               <head><style type="text/css">body{font-family:Arial;font-size:12px;}</style></head>
               <body style="margin:10px" class="page">'.$_REQUEST["msg_body"].'</body></html>';

   $sentmails = sendExternalMail($_REQUEST["msg_header"],
                                 $text,
                                 $_REQUEST["msg_toaddr"],
                                 $_REQUEST["msg_toname"],
                                 "",
                                 "",
                                 $attachfiles);
   if($sentmails <= 0)
      $savemsg = getSaveMessage(false);
   else
      $savemsg = getSaveMessage(true);
   ?>
   <script language="Javascript">
      alert('ENVIO DE CORREO EJECUTADO EXITOSAMENTE');
   </script>
   <?php
}

//----------------------------------------------------------------------------------
$_sesmodulename         = "sell_invoices";
$_sesbasefilterstatus   = "0";
$_sesbaseorderby        = "2";
$_sesbaseordersort      = " desc, 11 desc, 1 desc";
$_SESSION[$_sesmodulename]["sql_company"] = $_SESSION["user_company_id"];
$_sortlinks             = Array("Fecha"            => 2,
                                "DOCTO."           => 8,
                                "Número int."      => 3,
                                "Cliente"          => 10,
                                "N° DOCTO."        => 4,
                                "Factura Rel."     => 9,
                                "Monto DOCTO."     => 5,
                                "Pago/Monto"       => 0,
                                "Deuda/Monto"      => 0,
                                "Fecha Venc."      => 11,
                                "Pagado"           => 6);

unset($_SESSION["STATS"][$_sesmodulename]);

//----------------------------------------------------------------------------------
if($_REQUEST["_MODE"] == "customer")
{
   $_REQUEST["subexec"]       = "search";
   $_REQUEST["sql_customer"]  = $_REQUEST["id"];

   if($_REQUEST["sql_company"] == "")
      $_REQUEST["sql_company"] = $_SESSION["user_company_id"];
}

//----------------------------------------------------------------------------------
resetOverviewSession($_sesmodulename);

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "search")
{
   $_SESSION[$_sesmodulename]["sql_company"]       = (int)$_REQUEST["sql_company"];
   $_SESSION[$_sesmodulename]["sql_shop"]          = (int)$_REQUEST["sql_shop"];
   $_SESSION[$_sesmodulename]["sql_customer"]      = (int)$_REQUEST["sql_customer"];
   $_SESSION[$_sesmodulename]["sql_month1"]        = trim($_REQUEST["sql_month1"]);
   $_SESSION[$_sesmodulename]["sql_month2"]        = trim($_REQUEST["sql_month2"]);
   $_SESSION[$_sesmodulename]["sql_year1"]         = trim($_REQUEST["sql_year1"]);
   $_SESSION[$_sesmodulename]["sql_year2"]         = trim($_REQUEST["sql_year2"]);
   $_SESSION[$_sesmodulename]["sql_paystatus"]     = $_REQUEST["sql_paystatus"];
   $_SESSION[$_sesmodulename]["sql_depstatus"]     = $_REQUEST["sql_depstatus"];
   $_SESSION[$_sesmodulename]["sql_vencstatus"]    = $_REQUEST["sql_vencstatus"];
   $_SESSION[$_sesmodulename]["sql_mainstat"]      = $_REQUEST["sql_mainstat"];
   $_SESSION[$_sesmodulename]["sql_doctype"]       = (int)$_REQUEST["sql_doctype"];
   $_SESSION[$_sesmodulename]["sql_selmode"]       = (int)$_REQUEST["sql_selmode"];
   $_SESSION[$_sesmodulename]["sql_selmode_type"]  = (int)$_REQUEST["sql_selmode_type"];
   $_SESSION[$_sesmodulename]["sql_date"]          = trim($_REQUEST["sql_date"]);
   $_SESSION[$_sesmodulename]["sql_date_pfrom"]    = trim($_REQUEST["sql_date_pfrom"]);
   $_SESSION[$_sesmodulename]["sql_date_pto"]      = trim($_REQUEST["sql_date_pto"]);
   $_SESSION[$_sesmodulename]["sql_number"]        = trim(addslashes(str_replace("*","%",$_REQUEST["sql_number"])));
   $_SESSION[$_sesmodulename]["page"]              = 0;
   $_SESSION[$_sesmodulename]["search_active"]     = 1;
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

// $_SESSION[$_sesmodulename]["sql_doctype"] = 2;

//----------------------------------------------------------------------------------
if(!is_array($_SESSION[$_sesmodulename]["sql_paystatus"]))
   $_SESSION[$_sesmodulename]["sql_paystatus"] = Array(0=>0);
if(!is_array($_SESSION[$_sesmodulename]["sql_vencstatus"]))
   $_SESSION[$_sesmodulename]["sql_vencstatus"] = Array(0=>0,1=>1);
if(!is_array($_SESSION[$_sesmodulename]["sql_depstatus"]))
   $_SESSION[$_sesmodulename]["sql_depstatus"] = Array(0=>0,1=>1);
if(!(int)$_SESSION[$_sesmodulename]["sql_doctype"])
   $_SESSION[$_sesmodulename]["sql_doctype"] = 1;
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
prepareOverviewSession($_sesmodulename, $_sesbasefilterstatus, $_sesbaseorderby, $_sesbaseordersort, 200);

$shopsqlexclude = "";
for($x = 2; $x <= 20; $x++)
   $shopsqlexclude .= " !( t1.invc_desc LIKE 'FACTURA {$x}/%, VALE:%' and t1.invc_shoprefid > 0 ) and ";
$shopsqlexclude = substr($shopsqlexclude, 0, -4);

//----------------------------------------------------------------------------------
if(!(int)$_SESSION[$_sesmodulename]["sql_mainstat"])
{
   $datsql = " select distinct t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber, t1.invc_total_brutto,
                      t1.invc_payed, t4.company_short, 'type' 'invcoice', 'note_invcnumber' 'note_invcnumber',
                      t6.cust_name, t1.invc_estpay_date, t1.invc_company_id, t1.invc_cust_id
            from invoices_sell t1
            INNER JOIN company_data             t4  ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer            t6  ON ( t1.invc_cust_id = t6.id )
            where
            t1.invc_status = 2 and
            {$shopsqlexclude} ";
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.invc_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";

   if($_SESSION[$_sesmodulename]["sql_doctype"] == 1)
   {
      $datsql .= " UNION ALL
                     select distinct t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber', t1.note_total_brutto 'invc_total_brutto',
                            t1.note_payed 'invc_payed', t4.company_short, 'type' '1', 'note_invcnumber' 'note_invcnumber',
                            t6.cust_name, t1.note_estpay_date 'invc_estpay_date', t1.note_company_id 'invc_company_id', t1.note_cust_id 'invc_cust_id'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
                     INNER JOIN company_data              t4  ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
                     LEFT OUTER JOIN customer             t6  ON ( t1.note_cust_id = t6.id )
                     where
                     t1.note_status       = 2 and
                     t1.note_type         = 1 and
                     t1.note_type_contype >= 0 ";
      //----------------------------------------------------------------------------------
      if((int)$_SESSION[$_sesmodulename]["sql_company"])
         $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if((int)$_SESSION[$_sesmodulename]["sql_customer"])
         $datsql .= " and t1.note_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
      if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.note_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
      $datsql .= "   UNION ALL
                     select distinct t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber', t1.note_total_brutto 'invc_total_brutto',
                            t1.note_payed 'invc_payed', t4.company_short, 'type' '2', 'note_invcnumber' 'note_invcnumber',
                            t6.cust_name, t1.note_estpay_date 'invc_estpay_date', t1.note_company_id 'invc_company_id', t1.note_cust_id 'invc_cust_id'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
                     INNER JOIN company_data              t4  ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
                     LEFT OUTER JOIN customer             t6  ON ( t1.note_cust_id = t6.id )
                     where
                     t1.note_status       = 2 and
                     t1.note_type         = 2 and
                     t1.note_type_contype >= 0 ";
      //----------------------------------------------------------------------------------
      if((int)$_SESSION[$_sesmodulename]["sql_company"])
         $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if((int)$_SESSION[$_sesmodulename]["sql_customer"])
         $datsql .= " and t1.note_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
      if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      $datsql .= " and t1.note_shop_id  = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   }
   $datsql .= " order by 2 desc, 11 desc, 1 desc ";
   $trans = $CON->select($datsql);
   for($x = 0; $x < count($trans) && $trans != false; $x++)
   {
      $xtranpayed = getTranPayments($CON, $trans[$x]["id"], "invoices_sell");
      /*
      if(count($xtranpayed) && $xtranpayed != false)
         $_IGNOREX[$x] = 1;
      else
      {
      */
      if($_SESSION[$_sesmodulename]["sql_doctype"] == 1)
      {
         if($trans[$x]["type"] == "typeinvcoice")
         {
            $sql = " select distinct t1.*
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
                     where
                     t1.note_status          > 1 and
                     t1.note_status          < 4 and
                     t1.note_type_contype    = 0 and
                     t2.item_invc_docnumber  like '{$trans[$x]["invc_docnumber"]}-%' and
                     t1.note_company_id      = {$trans[$x]["invc_company_id"]} and
                     t1.note_cust_id         = {$trans[$x]["invc_cust_id"]}
                     group by t1.id
                     order by t1.note_date desc, t1.note_estpay_date desc, t1.id desc";
            $notes = $CON->select($sql);

            $trans[$x]["_notes"] = $notes;

            $trans[$x]["invc_total_brutto_calc"] = $trans[$x]["invc_total_brutto"];
            foreach($notes AS $note)
            {
               $subx = 0;
               foreach($trans AS $temptr)
               {
                  if($temptr["type"] != "typeinvcoice" && $temptr["id"] == $note["id"])
                     $_IGNOREX[$subx] = 1;
                  $subx++;
               }
                     
               if($note["note_type"] == 1)
               {
                  $trans[$x]["invc_total_brutto_calc"] -= $note["note_total_brutto"];
                  $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");

                  foreach($xtranpayed AS $xtranpayedrow)
                  {
                     if($xtranpayedrow["pay_brutto"] > $note["note_total_brutto"])
                        $xtranpayedrow["pay_brutto"] = $note["note_total_brutto"];
                     $trans[$x]["invc_total_brutto_calc"] += $xtranpayedrow["pay_brutto"];
                  }
               }
               else
               {
                  $trans[$x]["invc_total_brutto_calc"] += $note["note_total_brutto"];
                  $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
                  foreach($xtranpayed AS $xtranpayedrow)
                  {
                     if($xtranpayedrow["pay_brutto"] > $note["note_total_brutto"])
                        $xtranpayedrow["pay_brutto"] = $note["note_total_brutto"];
                     $trans[$x]["invc_total_brutto_calc"] -= $xtranpayedrow["pay_brutto"];
                  }
               }
            }
         }
      }
   }
   $temp = $trans;
   unset($trans);
   $nc = 0;
   for($x = 0; $x < count($temp) && $temp != false; $x++)
   {
      if(!(int)$_IGNOREX[$x])
      {
         $trans[$nc] = $temp[$x];
         $nc++;
      }
   }
}
else
{
   $datsql = " select distinct t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber, t1.invc_total_brutto,
                      t1.invc_payed,
                      t4.company_short, 'type' 'invcoice', 'note_invcnumber' 'note_invcnumber',
                      t6.cust_name, t1.invc_estpay_date, 'contype' '', t1.invc_company_id, t1.invc_cust_id
            from invoices_sell t1
            INNER JOIN company_data             t4  ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
            LEFT OUTER JOIN customer            t6  ON ( t1.invc_cust_id = t6.id )
            where
            t1.invc_status > 1 and
            t1.invc_status < 4 and
            {$shopsqlexclude} and ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 1)
      $datsql .= " t1.invc_date between {$sql_datefrom} and {$sql_dateto} ";
   else
      $datsql .= " t1.invc_estpay_date between {$sql_datefrom} and {$sql_dateto} ";

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
      $datsql .= " and t1.invc_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
      $datsql .= " and t1.invc_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
   if($_SESSION[$_sesmodulename]["sql_number"] != "")
      $datsql .= " and t1.invc_docnumber = '{$_SESSION[$_sesmodulename]["sql_number"]}' ";
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
   if($_SESSION[$_sesmodulename]["sql_doctype"] == 1)
   {           
      $datsql .= "UNION ALL
                  select t1.id, t1.note_date 'invc_date', t1.note_number 'invc_number', t1.note_docnumber 'invc_docnumber',
                         t1.note_total_brutto 'invc_total_brutto',
                         t1.note_payed 'invc_payed',
                         t4.company_short, t1.note_type 'type', t1.note_invcnumber,
                         t6.cust_name, t1.note_estpay_date 'invc_estpay_date', t1.note_type_contype 'contype',
                         t1.note_company_id 'invc_company_id', t1.note_cust_id 'invc_cust_id'
                  from invoices_notes_sell t1
                  INNER JOIN company_data t4    ON ( t1.note_company_id = t4.id and t4.company_status = 1 )
                  LEFT OUTER JOIN customer t6   ON ( t1.note_cust_id = t6.id )
                  where
                  t1.note_status > 1 and
                  t1.note_status < 4 and ";

      //----------------------------------------------------------------------------------
      if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 1)
         $datsql .= " t1.note_date between {$sql_datefrom} and {$sql_dateto} ";
      else
         $datsql .= " t1.note_estpay_date between {$sql_datefrom} and {$sql_dateto} ";

      //----------------------------------------------------------------------------------
      if((int)$_SESSION[$_sesmodulename]["sql_company"])
         $datsql .= " and t1.note_company_id   = {$_SESSION[$_sesmodulename]["sql_company"]} ";
      if((int)$_SESSION[$_sesmodulename]["sql_customer"])
         $datsql .= " and t1.note_cust_id  = {$_SESSION[$_sesmodulename]["sql_customer"]} ";
      if($_SESSION[$_sesmodulename]["sql_number"] != "")
         $datsql .= " and 1 = 2 ";

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
    }


      
   //----------------------------------------------------------------------------------
   $datsql .= " order by 2 desc, 11 desc, 1 desc";
   $trans = $CON->select($datsql);

   if($_SESSION[$_sesmodulename]["sql_doctype"] == 1)
   {
      $temp = Array();
      for($x = 0; $x < count($trans) && $trans != false; $x++)
      {
         if($trans[$x]["type"] == "typeinvcoice")
         {
            $sql = " select distinct t1.*
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
                     where
                     t1.note_status          > 1 and
                     t1.note_status          < 4 and
                     t1.note_type_contype    = 0 and
                     t2.item_invc_docnumber  like '{$trans[$x]["invc_docnumber"]}-%' and
                     t1.note_company_id      = {$trans[$x]["invc_company_id"]} and
                     t1.note_cust_id         = {$trans[$x]["invc_cust_id"]}
                     group by t1.id
                     order by t1.note_date desc, t1.note_estpay_date desc, t1.id desc";
            $notes = $CON->select($sql);
            $trans[$x]["_notes"] = $notes;

            $trans[$x]["invc_total_brutto_calc"] = $trans[$x]["invc_total_brutto"];
            foreach($notes AS $note)
            {
               if($note["note_type"] == 1)
               {
                  $trans[$x]["invc_total_brutto_calc"] -= $note["note_total_brutto"];

                  $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
                  foreach($xtranpayed AS $xtranpayedrow)
                  {
                     if($xtranpayedrow["pay_brutto"] > $note["note_total_brutto"])
                        $xtranpayedrow["pay_brutto"] = $note["note_total_brutto"];
                     $trans[$x]["invc_total_brutto_calc"] += $xtranpayedrow["pay_brutto"];
                  }
               }
               else
               {
                  $trans[$x]["invc_total_brutto_calc"] += $note["note_total_brutto"];
                  $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
                  foreach($xtranpayed AS $xtranpayedrow)
                  {
                     if($xtranpayedrow["pay_brutto"] > $note["note_total_brutto"])
                        $xtranpayedrow["pay_brutto"] = $note["note_total_brutto"];
                     $trans[$x]["invc_total_brutto_calc"] -= $xtranpayedrow["pay_brutto"];
                  }
               }
            }

            $temp[] = $trans[$x];
         }
         else
         {
            //if((int)$trans[$x]["contype"] > 0)
               $temp[] = $trans[$x];
            /*
            else
            {
               $sql = " select distinct t7a.item_invc_docnumber
                        from invoices_notes_sell_items t7a
                        where
                        note_id = {$trans[$x]["id"]} and
                        item_invc_docnumber != ''";
               $relinvcids = $CON->select($sql);

               foreach($relinvcids AS $relinvcid)
               {
                  $existsInArray = false;
                  for($zz = 0; $zz < count($trans) && $trans != false; $zz++)
                  {
                     if($trans[$zz]["invc_docnumber"] == $relinvcid["item_invc_docnumber"] && $trans[$zz]["type"] == "typeinvcoice")
                        $existsInArray = true;
                        
                  }

                  if(!$existsInArray)
                  {
                     $sql = " select distinct t1.id, t1.invc_date, t1.invc_number, t1.invc_docnumber, t1.invc_total_brutto,
                                     t1.invc_payed,
                                     t4.company_short, 'type' 'invcoice', 'note_invcnumber' 'note_invcnumber',
                                     t6.cust_name, t1.invc_estpay_date, 'parent_invcid' ''
                              from invoices_sell t1
                              INNER JOIN company_data             t4  ON ( t1.invc_company_id = t4.id and t4.company_status = 1 )
                              LEFT OUTER JOIN customer            t6  ON ( t1.invc_cust_id = t6.id )
                              where
                              t1.invc_docnumber       = '{$relinvcid["item_invc_docnumber"]}' and
                              t1.invc_company_id      = {$trans[$x]["invc_company_id"]} and
                              t1.invc_cust_id         = {$trans[$x]["invc_cust_id"]}";
                     $currinvc = $CON->select($sql);
                     $currinvc = $currinvc[0];

                     $sql = " select distinct t1.*, SUM(t2.item_sellprice_brutto * t2.item_amount) 'note_total_brutto'
                              from invoices_notes_sell t1
                              INNER JOIN invoices_notes_sell_items t2 ON ( t1.id = t2.note_id )
                              where
                              t1.note_status          > 1 and
                              t1.note_status          < 4 and
                              t2.item_invc_docnumber  like '{$currinvc["invc_docnumber"]}-%' and
                              t1.note_company_id      = {$trans[$x]["invc_company_id"]} and
                              t1.note_cust_id         = {$trans[$x]["invc_cust_id"]}
                              group by t1.id
                              order by t1.note_date desc, t1.note_estpay_date desc, t1.id desc";
                     $notes = $CON->select($sql);
               
                     $currinvc["_notes"] = $notes;

                     $currinvc["invc_total_brutto_calc"] = $currinvc["invc_total_brutto"];
                     foreach($notes AS $note)
                     {
                        if($note["note_type"] == 1)
                        {
                           $currinvc["invc_total_brutto_calc"] -= $note["note_total_brutto"];

                           $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
                           foreach($xtranpayed AS $xtranpayedrow)
                              $currinvc["invc_total_brutto_calc"] += $xtranpayedrow["pay_brutto"];
                        }
                        else
                        {
                           $currinvc["invc_total_brutto_calc"] += $note["note_total_brutto"];
                           $xtranpayed = getTranPayments($CON, $note["id"], "invoices_notes_sell");
                           foreach($xtranpayed AS $xtranpayedrow)
                              $currinvc["invc_total_brutto_calc"] -= $xtranpayedrow["pay_brutto"];
                        }
                     }
                     
                     $temp[] = $currinvc;
                  }
               }
            }
            */
         }
      }
      $trans = $temp;
   }
}

//----------------------------------------------------------------------------------
for($x = 0; $x < count($trans) && $trans != false; $x++)
{
   $row = $trans[$x];
   if($row["type"] == "typeinvcoice")
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
/*
for($x = 0; $x < count($trans) && $trans != false; $x++)
{
   $row = $trans[$x];
   if($row["type"] == "typeinvcoice")
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

         if($_RET["factcount"] != "1/1" && $_RET["factcount"] != "")
         {
            if(strpos($_RET["factcount"], "1/") !== false)
            {
               ;;
            }
            else
            {
               for($y = 0; $y < count($trans) && $trans != false; $y++)
               {
                  $sql = " select *
                           from invoices_sell
                           where
                           id = {$trans[$y]["id"]}";
                  $compdata = $CON->select($sql);
                  $compdata = $compdata[0];
         
                  if((int)$compdata["invc_shoprefid"] && strpos($compdata["invc_desc"], "VALE: ") !== false && strpos($compdata["invc_desc"], "FACTURA ") !== false && strpos($compdata["invc_desc"], "/") !== false)
                  {
                     $_CMP = getValeData($compdata);

                     if($_CMP["factcount"] != "1/1" && $_CMP["factcount"] != "" && $compdata["id"] != $invcdata["id"] && $_RET["valenum"] == $_CMP["valenum"] && !(int)$_INVCCONVERTED[$row["id"]])
                     {
                        $_XDATA[$_hash]["invc_docnumber"]         .= ", ".$row["invc_docnumber"];
                        $_XDATA[$_hash]["invc_total_brutto"]      += $row["invc_total_brutto"];
                        $_XDATA[$_hash]["invc_total_brutto_calc"] += $row["invc_total_brutto_calc"];
                        $_INVCCONVERTED[$row["id"]] = 1;
                     }
                  }
               }
            }
         }
      }
   }
}
*/

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
$companies  = getCompanies($CON);
$shops      = getShops($CON);

$sql_today = mktime(15, 0, 0, date("m"), date("d"), date("Y"));

if((int)$_SESSION[$_sesmodulename]["sql_customer"])
{
   $sql = " select cust_company
            from customer
            where
            id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
   $custdata = $CON->select($sql);
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = $custdata[0]["cust_company"];
}
else
   $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"] = "TODO";
//----------------------------------------------------------------------------------
?>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<?php
printJSsetCompanyShop($shops);
?>
<?php
if($_REQUEST["_MODE"] != "customer")
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td height="30"><b class="content_header">Facturas a cobrar</b></td>
      <td align="right" class="content_row_clear"><?php if($savemsg == "") printOverviewResults(count($trans)); else echo $savemsg;?></td>
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
      <form action="index.php" method="post" name="xform_itemsearch" class="fokusfirst">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="80">
         <col>
         <col width="100">
         <col width="300">
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="4">Opciones de búsqueda</td>
      </tr>
      <tr>
         <?php
         if($_REQUEST["_MODE"] != "customer")
         {  ?>
            <td class="content_rowl">Cliente</td>
            <td class="content_row"><?php printOverviewCustomerSelect($_sesmodulename) ?></td>
            <?php
         }
         else
         {  ?>
            <td class="content_rowl">&nbsp;</td>
            <td class="content_row">&nbsp;</td>
            <?php
         }
         ?>
         <td class="content_rowl">Empresa</td>
         <td class="content_row"><?php printOverviewCompanySelect($companies, $_sesmodulename) ?></td>
      </tr>
      <tr>
         <td class="content_rowl">Periodo</td>
         <td class="content_row">
            <input type="radio" name="sql_mainstat" value="0" <?php if(!(int)$_SESSION[$_sesmodulename]["sql_mainstat"]) echo "checked" ?>
            onclick="$('#idx_trx1').hide();$('#idx_trx2').hide();$('#idx_trx3').hide();"> Pendiente hasta hoy
            <input type="radio" name="sql_mainstat" value="1" <?php if((int)$_SESSION[$_sesmodulename]["sql_mainstat"] == 1) echo "checked" ?>
            onclick="$('#idx_trx1').show();$('#idx_trx2').show();$('#idx_trx3').show();"> Especificar periodo
         </td>
         <td class="content_rowl">Sucursal</td>
         <td class="content_row"><?php printOverviewShopSelect($shops, $_sesmodulename) ?></td>
      </tr>
      <tr style="display:none">
         <td class="content_rowl">Tipo Doc</td>
         <td class="content_row">
            <input type="radio" name="sql_doctype" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_doctype"] == 1) echo "checked"?>> Todos
            <input type="radio" name="sql_doctype" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_doctype"] == 2) echo "checked"?>> Solamente Facturas
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
      </tr>
      <tr id="idx_trx1" style="<?if(!(int)$_SESSION[$_sesmodulename]["sql_mainstat"]) echo "display:none"?>">
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
                     $startyear  = date('Y') -20;
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
                     $startyear  = date('Y') -20;
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
         </td>
         <td class="content_rowl">Filtrar por</td>
         <td class="content_row">
            <input type="radio" name="sql_selmode_type" value="1"
            <?php if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 1) echo "checked"?>> Fecha emission
            <input type="radio" name="sql_selmode_type" value="2"
            <?php if($_SESSION[$_sesmodulename]["sql_selmode_type"] == 2) echo "checked"?>> Fecha vencimiento
         </td>
      </tr>
      <tr id="idx_trx2" style="<?if(!(int)$_SESSION[$_sesmodulename]["sql_mainstat"]) echo "display:none"?>">
         <td class="content_rowl">Número</td>
         <td class="content_row">
            <input type="text" class="text" style="width:195px"
            name="sql_number" value="<?=str_replace("%","*",$_SESSION[$_sesmodulename]["sql_number"])?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
      </tr>
      <tr id="idx_trx3" style="<?if(!(int)$_SESSION[$_sesmodulename]["sql_mainstat"]) echo "display:none"?>">
         <td class="content_rowl">Pagado</td>
         <td class="content_row">
            <input type="checkbox" name="sql_paystatus[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>No pagado
            <input type="checkbox" name="sql_paystatus[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_paystatus"]) !== false) echo "checked"?>>Pagado
         </td>
         <td class="content_rowl">Vencido</td>
         <td class="content_row">
            <input type="checkbox" name="sql_vencstatus[]" value="0" <?php if(array_search(0, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false) echo "checked"?>>No vencido
            <input type="checkbox" name="sql_vencstatus[]" value="1" <?php if(array_search(1, $_SESSION[$_sesmodulename]["sql_vencstatus"]) !== false) echo "checked"?>>Vencido
         </td>
      </tr>
      <tr>
         <td class="content_row" align="right" colspan="4">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
               <td align="left" width="1" style="padding-right:5px">
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
               <?php
               if($_REQUEST["_MODE"] == "customer")
               {  ?>
                  <td align="right" style="padding-right:5px" width="1">
                     <?php
                     printButton("Enviar via Correo", "postnav", "javascript:document.all.idx_mail.style.display='';void(0)", "", "mail", 130);
                     ?>
                  </td>
                  <?php
               }
               if((int)$_SESSION[$_sesmodulename]["search_active"] && $_REQUEST["_MODE"] != "customer")
               {  ?>
                  <td align="right" style="padding-right:5px" width="1">
                  <?php
                  printButton("Resetear", "postnav", "index.php?mid={$_REQUEST["mid"]}&searchexec=reset", "", "arrow-circle-double-135", 130);
                  ?>
                  </td>
                  <?php
               }
               ?>
               <td align="right" width="1">
                  <?php
                  printButton("Actualizar", "postnav_save", "javascript: deactivateFormChange()", "submitForm(document.xform_itemsearch)", "magnifier", 130);
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
<tr id="idx_mail" style="display:none">
   <td>
      <script type="text/javascript" src="./libs/jscripts/tinymce_3_2_2_3/jscripts/tiny_mce/tiny_mce.js"></script>
      <script type="text/javascript">
         tinyMCE.init({
            mode : "specific_textareas",
            editor_selector : "mceEditor",
            theme : "advanced",
            plugins : "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",
            theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,bullist,numlist,outdent,indent,blockquote,|,forecolor,backcolor,tablecontrols",
            theme_advanced_buttons2 : "", theme_advanced_buttons3 : "", theme_advanced_buttons4 : "",
            theme_advanced_toolbar_location : "top", theme_advanced_toolbar_align : "left",
            content_css : "css/content.css", template_external_list_url : "lists/template_list.js", external_link_list_url : "lists/link_list.js", external_image_list_url : "lists/image_list.js", media_external_list_url : "lists/media_list.js",
            width: "810px", height: "150px", force_br_newlines: true, forced_root_block: ''
         });
      </script>
      <form action="index.php" method="post" name="xform_docsend"
      onsubmit="return checkform(new Array(this.msg_header, this.msg_body))">
      <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
      <input type="hidden" name="subcatexec" value="<?=$_REQUEST["subcatexec"]?>">
      <input type="hidden" name="subexec" value="search">
      <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
      <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
      <input type="hidden" name="sendmail" value="1">
      <input type="hidden" name="printpdf" value="0">
      <input type="hidden" name="printxls" value="0">
      <?php
      $msg_header = "Cuenta corriente";

      $sql = " select user_mail_signature_html
               from user
               where
               id = {$_SESSION["user_id"]}";
      $mailsig = $CON->select($sql);
      $mailsig = $mailsig[0]["user_mail_signature_html"];
      if(trim($mailsig) != "")
         $msg_body = "<br><br>".$mailsig;
      ?>
      <?=Nifty_printH("box2", "980")?>
      <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="150">
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_header" colspan="2">Enviar correo</td>
      </tr>
      <tr>
         <td class="content_rowl">Nombre *</td>
         <td class="content_row">
            <input type="text" name="msg_toname" class="text" style="width:280px" value="<?=$_CUSTMAILNAME?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl">E-Mail *</td>
         <td class="content_row">
            <input type="text" name="msg_toaddr" class="text" style="width:280px" value="<?=$_CUSTMAILADDR?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl"><?=$_LANG["MODULE"]["MSG"][29]?> *</td>
         <td class="content_row">
            <input type="text" class="text" style="width:810px" maxlength="254" name="msg_header" value="<?=$msg_header?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
      </tr>
      <tr>
         <td class="content_rowl" valign="top"><?=$_LANG["MODULE"]["MSG"][30]?> *</td>
         <td class="content_row">
            <textarea class="text mceEditor" style="width:810px; height:150px" name="msg_body"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($msg_body)?></textarea>
         </td>
      </tr>
      </table>
      <?=Nifty_printF()?>
      <br>
      <table border="0" cellspacing="0" cellpadding="0" width="980">
      <tr>
         <td>&nbsp;</td>
         <td width="130" style="padding-right:5px">
            <?php
            printButton("Enviar", "postnav_save", "javascript: deactivateFormChange()", "tinyMCE.triggerSave();submitForm(document.xform_docsend)", "mail");
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>
<tr>
   <td>
      <?=Nifty_printH("box1", "980")?>
      <table border="0" cellpadding="3" cellspacing="0" width="100%">
      <colgroup>
         <col width="85">
         <col>
         <?php
         if($_REQUEST["_MODE"] == "")
         {  ?>
            <col>
            <?php
         }
         ?>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
         <col>
      </colgroup>
      <tr>
         <td class="content_tbl_subheader content_row_os">Fecha</td>
         <td class="content_tbl_subheader content_row_os">DOCTO.</td>
         <?php
         if($_REQUEST["_MODE"] == "")
         {  ?>
            <td class="content_tbl_subheader content_row_os">Cliente</td>
            <?php
         }
         ?>
         <td class="content_tbl_subheader content_row_os">N° DOCTO.</td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Total/Venta</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>A cuenta</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Total/Deuda</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="center">Dias</td>
         <td class="content_tbl_subheader content_row_os" align="right"><nobr>Fecha Venc.</nobr></td>
         <td class="content_tbl_subheader content_row_os" align="center">Aprob</td>
         <td class="content_tbl_subheader content_row_os" align="left">Comentarios</td>
      </tr>
      <?php
      $sc = 0;
      //----------------------------------------------------------------------------------
      for($x = 0; $x < count($trans) && $trans != false; $x++)
      {
         $tran = $trans[$x];

         //----------------------------------------------------------------------------------
         $tran_type = "";
         if($tran["type"] == "typeinvcoice")
         {
            $tran_type = "Factura";
            $_relmid   = "752";
            $_relid    = $tran["id"];
            $_relmode  = "invoices_sell";
         }
         elseif($tran["type"] == "type1" || $tran["type"] == "1")
         {
            $tran_type = "N/C";
            $_relmid   = "755";
            $_relid    = $tran["id"];
            $_relmode  = "invoices_notes_sell";
         }
         elseif($tran["type"] == "type2" || $tran["type"] == "2")
         {
            $tran_type = "N/D";
            $_relmid   = "755";
            $_relid    = $tran["id"];
            $_relmode  = "invoices_notes_sell";
         }

         //----------------------------------------------------------------------------------
         $trannopayed = 0.00;
         $transtat    = "No";
         $paybgcss    = "#FFD6D8";
         $deptat      = "Sin Depos.";
         $depbgcss    = "#FFD6D8";

         //----------------------------------------------------------------------------------
         $tranpayed  = 0.00;
         $xtranpayed = getTranPayments($CON, $tran["id"], $_relmode);

         $paydays     = 0;
         $paydivide   = 0;
         $pay_comments = "";
         foreach($xtranpayed AS $xtranpayxrow)
         {
            $lastpaydate = (int)$xtranpayxrow["adm_deposit_status_date"];
            if($lastpaydate && $tran["type"] == "typeinvcoice")
            {
               $paydays += (int)(($lastpaydate - $tran["invc_date"]) / 86400);
               $paydivide++;
            }

            if($xtranpayxrow["pay_comment"] != "")
               $pay_comments .= $xtranpayxrow["pay_comment"]."\n";
         }
         $paydays = round($paydays / $paydivide,0);
         
            
         if(count($xtranpayed) && $xtranpayed != false)
         {
            $deptat     = "Depositado";
            $depbgcss   = "#E1FFD6";
         }

         //----------------------------------------------------------------------------------
         foreach($xtranpayed AS $tranpayedrow)
         {
            if((int)$tranpayedrow["pay_payed"])
               $tranpayed += $tranpayedrow["pay_brutto"];
         }

         if((int)$tran["invc_payed"])
         {
            $trannopayed = 0.00;
            $transtat    = "Si";
            $paybgcss    = "#E1FFD6";
         }
         else
            $trannopayed = $tran["invc_total_brutto"] - $tranpayed;

         //----------------------------------------------------------------------------------
         $cssprefix = "";
         $csssuffix = "";
         if(!(int)$tran["invc_payed"] && $tran["invc_estpay_date"] > 0 && $tran["invc_estpay_date"] <= time())
         {
            $cssprefix = "<b class='msg_save_err'>";
            $csssuffix = "</b>";

            $_TOTAL[$tran_type]["VENC"] += $trannopayed;
            
         }
         $_TOTAL[$tran_type]["TOTAL"] += $tran["invc_total_brutto"];
         $_TOTAL[$tran_type]["REALNOPAYED"] += $trannopayed;

         

         //----------------------------------------------------------------------------------
         ?>
         <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)" style="cursor:pointer"
         <?php
         if(!(int)$tran["_NOCLICK"])
         {  ?>
            onclick="showFancybox('iframe.edit.php?mid=<?=$_relmid?>&id=<?=$_relid?>&mode=<?=$_relmode?>&exec=edit&subcatexec=payment&from=report', 'iframe', 1016, 450, 'auto')"
            <?php
         }
         ?>>
            <td class="content_row_os" height="25"><?=date('d.m.Y', $tran["invc_date"])?></td>
            <td class="content_row_os"><nobr><?=$tran_type?></nobr></td>
            <?php
            if($_REQUEST["_MODE"] == "")
            {  ?>
               <td class="content_row_os"><?=$tran["cust_name"]?></td>
               <?php
            }
            ?>
            <td class="content_row_os"><?=$tran["invc_docnumber"]?></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($tran["invc_total_brutto"])?></nobr></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($tranpayed)?></nobr></td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($trannopayed)?></nobr></td>
            <td class="content_row_os" align="center"><?=(int)(($sql_today - $tran["invc_date"]) / 86400)?></td>
            <td class="content_row_os" align="right"><?=$cssprefix?><?php if($tran["invc_estpay_date"] > 0) echo date('d.m.Y', $tran["invc_estpay_date"]); else echo "&nbsp;"?><?=$csssuffix?></td>
            <td class="content_row_os" align="center" style="background-color:<?=$paybgcss?>"><?=$transtat?></td>
            <td class="content_row_os" align="left"><?=nl2br(trim($pay_comments))?></td>
         </tr>
         <?php
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_date"]         = date('d.m.Y', $tran["invc_date"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["tran_type"]         = $tran_type;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["cust_name"]         = $tran["cust_name"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_docnumber"]    = $tran["invc_docnumber"];
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_total_brutto"] = printPrice($tran["invc_total_brutto"]);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["trannopayed"]       = printPrice($trannopayed);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["tranpayed"]         = printPrice($tranpayed);
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["paydays"]           = (int)(($sql_today - $tran["invc_date"]) / 86400);
         if($tran["invc_estpay_date"] > 0)
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_estpay_date"]  = date('d.m.Y', $tran["invc_estpay_date"]);
            
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["transtat"]          = $transtat;
         $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["pay_comments"]      = trim($pay_comments);
         $sc++;
         
         for($y = 0; $y < count($tran["_notes"]) && $tran["_notes"] != false; $y++)
         {
            if((int)$tran["_notes"][$y]["note_type"] == 1)
            {
               $tran_type = "N/C";
               $_relmid   = "755";
               $_relid    = $tran["_notes"][$y]["id"];
               $_relmode  = "invoices_notes_sell";
            }
            elseif((int)$tran["_notes"][$y]["note_type"] == 2)
            {
               $tran_type = "N/D";
               $_relmid   = "755";
               $_relid    = $tran["_notes"][$y]["id"];
               $_relmode  = "invoices_notes_sell";
            }

            //----------------------------------------------------------------------------------
            $trannopayed = 0.00;
            $transtat    = "No";
            $paybgcss    = "#FFD6D8";
            $deptat      = "Sin Depos.";
            $depbgcss    = "#FFD6D8";

            //----------------------------------------------------------------------------------
            $tranpayed  = 0.00;
            $xtranpayed = getTranPayments($CON, $tran["_notes"][$y]["id"], $_relmode);
            if(count($xtranpayed) && $xtranpayed != false)
            {
               $deptat     = "Depositado";
               $depbgcss   = "#E1FFD6";
            }

            $pay_comments = "";
            foreach($xtranpayed AS $xtranpayxrow)
            {
               if($xtranpayxrow["pay_comment"] != "")
                  $pay_comments .= $xtranpayxrow["pay_comment"]."\n";
            }

            $paydays     = 0;
            $lastpaydate = (int)$xtranpayed[0]["adm_deposit_status_date"];
            if($lastpaydate)
               $paydays = (int)(($lastpaydate - $tran["_notes"][$y]["note_date"]) / 86400);

            //----------------------------------------------------------------------------------
            foreach($xtranpayed AS $tranpayedrow)
            {
               if((int)$tranpayedrow["pay_payed"])
                  $tranpayed += $tranpayedrow["pay_brutto"];
            }

            if((int)$tran["_notes"][$y]["note_payed"])
            {
               $trannopayed = 0.00;
               $transtat    = "Si";
               $paybgcss    = "#E1FFD6";
            }
            else
               $trannopayed = $tran["_notes"][$y]["note_total_brutto"] - $tranpayed;

            //----------------------------------------------------------------------------------
            $cssprefix = "";
            $csssuffix = "";
            if(!(int)$tran["_notes"][$y]["invc_payed"] && $tran["_notes"][$y]["invc_estpay_date"] > 0 && $tran["_notes"][$y]["invc_estpay_date"] <= time())
            {
               $cssprefix = "<b class='msg_save_err'>";
               $csssuffix = "</b>";
            }
            $_TOTAL[$tran_type]["TOTAL"] += $tran["_notes"][$y]["note_total_brutto"];
            ?>
            <tr bgcolor="<?=getRowColor($x +1)?>" onmouseover="mark(this, 0)" onmouseout="mark(this,1)" style="cursor:pointer"
            <?php
            if(!(int)$tran["_NOCLICK"])
            {  ?>
               onclick="showFancybox('iframe.edit.php?mid=<?=$_relmid?>&id=<?=$_relid?>&mode=<?=$_relmode?>&exec=edit&subcatexec=payment&from=report', 'iframe', 1016, 450, 'auto')"
               <?php
            }
            ?>>
               <td class="content_row_os" height="25">
                  <nobr>
                  <img src="./images/menu/icons/arrow-turn-000-left.png"
                  style="vertical-align:bottom">&nbsp;<?=date('d.m.Y', $tran["_notes"][$y]["note_date"])?>
                  </nobr>
               </td>
               <td class="content_row_os"><nobr><?=$tran_type?></nobr></td>
               <?php
               if($_REQUEST["_MODE"] == "")
               {  ?>
                  <td class="content_row_os"><?=$tran["cust_name"]?>&nbsp;</td>
                  <?php
               }
               ?>
               <td class="content_row_os"><?=$tran["_notes"][$y]["note_docnumber"]?></td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($tran["_notes"][$y]["note_total_brutto"])?></nobr></td>
               <td class="content_row_os" align="right"><nobr><?=printPrice($tranpayed)?></nobr></td>
               <td class="content_row_os" align="right"><nobr>&nbsp;</nobr></td>
               <td class="content_row_os" align="center"><?=(int)(($sql_today - $tran["invc_date"]) / 86400)?></td>
               <td class="content_row_os" align="right"><?=$cssprefix?><?php if($tran["_notes"][$y]["note_estpay_date"] > 0) echo date('d.m.Y', $tran["_notes"][$y]["note_estpay_date"]); else echo "&nbsp;"?><?=$csssuffix?></td>
               <td class="content_row_os" align="center" style="background-color:<?=$paybgcss?>"><?=$transtat?></td>
               <td class="content_row_os" align="left"><?=nl2br(trim($pay_comments))?></td>
            </tr>
            <?php
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_date"]         = date('d.m.Y', $tran["_notes"][$y]["note_date"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["tran_type"]         = $tran_type;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["cust_name"]         = $tran["cust_name"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_docnumber"]    = $tran["_notes"][$y]["note_docnumber"];
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_total_brutto"] = printPrice($tran["_notes"][$y]["note_total_brutto"]);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["trannopayed"]       = " ";
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["tranpayed"]         = printPrice($tranpayed);
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["paydays"]           = (int)(($sql_today - $tran["invc_date"]) / 86400);
            if($tran["_notes"][$y]["note_estpay_date"] > 0)
               $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["invc_estpay_date"]  = date('d.m.Y', $tran["_notes"][$y]["note_estpay_date"]);
               
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["transtat"]          = $transtat;
            $_SESSION["STATS"][$_sesmodulename]["DATA"][$sc]["pay_comments"]      = trim($pay_comments);
            $sc++;
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
      ?>
      </table>
      <?=Nifty_printF()?>
      <br>
      <?php
      if($x)
      {  ?>
         <?=Nifty_printH("box2", "980")?>
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col>
            <col width="150">
            <col width="150">
            <col width="150">
         </colgroup>
         <tr>
            <td class="content_tbl_header" colspan="4"><img src="./images/menu/icons/balance.png" style="vertical-align:bottom">&nbsp;&nbsp;TOTALES</td>
         </tr>
         <tr>
            <td class="content_tbl_subheader content_row_os">&nbsp;</td>
            <td class="content_tbl_subheader content_row_os" align="right">Total/Venta</td>
            <td class="content_tbl_subheader content_row_os" align="right">Total/Deuda</td>
            <td class="content_tbl_subheader content_row_os" align="right">Total Facturas vencidas</td>
         </tr>
         <tr>
            <td class="content_row_os">FACTURAS</td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFD6D8"><nobr><?=printPrice($_TOTAL["Factura"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFEBC9"><nobr><?=printPrice($_TOTAL["Factura"]["VENC"], 2)?></nobr></td>
         </tr>
         <tr>
            <td class="content_row_os">NOTAS DE CREDITO</td>
            <td class="content_row_os" align="right"><nobr>-<?=printPrice($_TOTAL["N/C"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFD6D8"><nobr>-<?=printPrice($_TOTAL["N/C"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFEBC9"><nobr>-<?=printPrice($_TOTAL["N/C"]["VENC"], 2)?></nobr></td>
         </tr>
         <tr>
            <td class="content_row_os">NOTAS DE DEBITO</td>
            <td class="content_row_os" align="right"><nobr><?=printPrice($_TOTAL["N/D"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFD6D8"><nobr><?=printPrice($_TOTAL["N/D"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_os" align="right" bgcolor="#FFEBC9"><nobr><?=printPrice($_TOTAL["N/D"]["VENC"], 2)?></nobr></td>
         </tr>
         <tr>
            <td class="content_row_totals content_row_os">TOTAL</td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["TOTAL"]       - $_TOTAL["N/C"]["TOTAL"]        + $_TOTAL["N/D"]["TOTAL"], 2)?></nobr></td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["REALNOPAYED"] - $_TOTAL["N/C"]["REALNOPAYED"]  + $_TOTAL["N/D"]["REALNOPAYED"], 2)?></nobr></td>
            <td class="content_row_totals content_row_os" align="right"><nobr><?=printPrice($_TOTAL["Factura"]["VENC"]        - $_TOTAL["N/C"]["VENC"]         + $_TOTAL["N/D"]["VENC"], 2)?></nobr></td>
         </tr>
         </table>
         <?=Nifty_printF()?>
         <?php
      }
      ?>
   </td>
</tr>
</table>
<?php
$_SESSION[$_sesmodulename]["_TOTALS"] = $_TOTAL;

if($_REQUEST["_MODE"] == "customer")
{
   $mailpdffile = doc_createStatsSellInvoices($CON);
   ?>
   <input type="hidden" name="docattach" value="<?=$mailpdffile?>">
   <?php
}
?>
</form>
<?php
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createStatsSellInvoices($CON);
if($_REQUEST["printxls"])
  $xlsfile = xls_createStatsSellInvoices($CON);

if($pdffile != "")
{
   $doctitle = "Facturas-a-cobrar-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
if($xlsfile != "")
{
   $doctitle = "Facturas-a-cobrar-".time().".xls";
   $xlslink = "./libs/modules/structure/document_file.php?type=0&hash={$xlsfile}.xls&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$xlslink?>"></iframe>
   <?php
}
?>
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>