<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_HIDETAXES = true;

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xinvoicessellnotes"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xinvoicessellnotes"]["fullcust"] = "";

$note_isdiscountspec = 0;
$note_isdiscountman  = 0;
$note_isguarantee    = 0;
$note_stockchange    = 0;
$note_parent_guaid   = 0;

if($_REQUEST["reinitDocs"])
{

   $company   = getCompanies($CON, true, $headdata["invc_company_id"]);
   $company   = $company[0];
   $comp_rut  = str_replace(".", "", $company["company_rut"]);
   $file_hash = date('md').strtoupper(md5(microtime()));

   
   $token = ObtieneToken($CON, $_SESSION["user_company_id"]);
   $headers = [
      "Authorization: {$token['token_type']} {$token['access_token']} ",
      "Content-Type: application/json"
   ];


   $sql  = "select * from invoices_notes_sell where id = {$_REQUEST["id"]}";
   $nota = $CON->select($sql);
   $nota = $nota[0];

   if((int)$nota["note_type"] == 1)
   {
       $codigo = '61';
       $tipo_documento = 'NCVELECT';
       
   }
   else
   {
      $codigo = '56';
      $tipo_documento = 'NDVELECT';
   } 

   $sql = " update invoices_notes_sell
            set
            note_sgntr_doc1 = '',
            note_sgntr_doc2 = ''
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'ImprimePDF'";
   $url = $CON->select($sql);
   $api_url = $url[0]['url'];

   $filedir = "../../../docs.electrpdf/";
   $filename1 = "{$codigo}_{$nota["note_docnumber"]}_{$comp_rut}_{$file_hash}_doc1.pdf";
   $filename2 = "{$codigo}_{$nota["note_docnumber"]}_{$comp_rut}_{$file_hash}_doc2.pdf";

   /* nota normal */

   $parametros = array('documentType'   => $tipo_documento,
                       'folio'          => $nota["note_docnumber"],
                       'isCedible'      =>  true ? 'false' : 'true',
                      );

   $url_completa = $api_url . '?' . http_build_query($parametros);

   $ch = curl_init($url_completa);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
   $response = curl_exec($ch);
   $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);
   $data = json_decode($response, true);

   $parametros = array('documentType'   => $tipo_documento,
                       'folio'          => $nota["note_docnumber"],
                       'isCedible'      =>  true ? 'true' : 'false',
                      );

   $url_completa = $api_url . '?' . http_build_query($parametros);
   $ch = curl_init($url_completa);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
   $response = curl_exec($ch);
   $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
   curl_close($ch);
   $data2 = json_decode($response, true);
   

   
   if ($data && isset($data['success']) && $data['success'] === true && isset($data['document']))
   {
      if (isset($data['document'])) 
      {
          $pdffiledir  = "{$_SESSION["_CONF"]["conf_shopadmin_path"]}docs.electrpdf/";
          $filePath     = $filename1;
          $pdfBase64    = $data['document'];

          echo("Archivo ".$data['document']);

          $pdfData      = base64_decode($pdfBase64);
          file_put_contents($pdffiledir.$filePath, $pdfData);
          $filePath     = $filename2;
          $pdfBase64    = $data2['document'];
          $pdfData2     = base64_decode($pdfBase64);
          file_put_contents($pdffiledir.$filePath, $pdfData2);
      }
   
      $sql = " update invoices_sell
                   set
                   invc_sgntr_doc1    = '{$filename1}',
                   invc_sgntr_doc2    = '{$filename2}'
                   where
                id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   } 
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "createFromGuarantee")
{
   $sql = " select t1.*
            from guarantee t1
            where
            t1.id = {$_REQUEST["guaID"]}";
   $xgua = $CON->select($sql);
   $xgua = $xgua[0];
   $xpos = getGuaranteeItems($CON, $_REQUEST["guaID"]);

   $xguapos = Array();
   foreach($xpos AS $xrow)
   {
      if((int)$xrow["guat_note_act"])
      {
         $xinvcid    = $xrow["item_invc_id"];
         $xinvcdoc   = $xrow["item_sellnumber"];
         $xguapos[]  = $xrow;
      }
   }

   $sql = " select t1.*
            from invoices_sell t1
            where
            t1.id = {$xinvcid} ";
   $xinvoice1 = $CON->select($sql);
   $xinvoice1 = $xinvoice1[0];

   $_REQUEST["cust_id_0"]        = (int)$xgua["gua_cust_id"];
   $_REQUEST["company_id"]       = (int)$xgua["gua_company_id"];
   $_REQUEST["shop_id"]          = (int)$xgua["gua_shop_id"];
   $_REQUEST["invc_docnumber"]   = $xinvcdoc;
   $note_invcnumber              = $xinvcdoc;
   $invcdata["id"]               = (int)$xinvcid;
   $invcdata["invc_taxes"]       = (int)$xinvoice1["invc_taxes"];
   $_REQUEST["note_taxes"]       = (int)$xinvoice1["invc_taxes"];
   $_REQUEST["note_type"]        = 1;   
   $_FROMDISCOUNTSPEC            = true;
   $note_isguarantee             = 1;
   $note_stockchange             = 1;
   $note_parent_guaid            = (int)$_REQUEST["guaID"];
   $_REQUEST["subexec"]          = "create";
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "createFromNoteDiscount")
{
   $sql = " select t1.*
            from invoices_sell t1
            where
            t1.id = {$_REQUEST["invcidOrg"]} ";
   $xinvoice1 = $CON->select($sql);
   $xinvoice1 = $xinvoice1[0];

   $_REQUEST["cust_id_0"]        = (int)$xinvoice1["invc_cust_id"];
   $_REQUEST["company_id"]       = (int)$xinvoice1["invc_company_id"];
   $_REQUEST["shop_id"]          = (int)$xinvoice1["invc_shop_id"];
   $invcdata["id"]               = (int)$xinvoice1["id"];
   $_REQUEST["note_taxes"]       = (int)$xinvoice1["invc_taxes"];
   $_REQUEST["invc_docnumber"]   = $xinvoice1["invc_docnumber"];
   $note_invcnumber              = $xinvoice1["invc_docnumber"];
   $note_paymentid               = $xinvoice1["invc_paymentid"];
   $_REQUEST["note_type"]        = 1;   
   $_REQUEST["subexec"]          = "create";
   $_FROMDISCOUNTSPEC            = true;
   $note_isdiscountman           = 1;

   if($xinvoice1["invc_taxes"])
      $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
   else
      $sql_taxesperc = 0;
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "createFromDiscountSpec")
{
   if((int)$_REQUEST["specmulti"])
   {
      $_ARRINVCS = explode("-", $_REQUEST["invcidOrg"]);
      $_REQUEST["invcidOrg"] = $_ARRINVCS[0];
   }
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from invoices_sell t1
            where
            t1.id = {$_REQUEST["invcidOrg"]} ";
   $xinvoice1 = $CON->select($sql);
   $xinvoice1 = $xinvoice1[0];
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
            from invoices_sell t1
            where
            t1.id = {$xinvoice1["invc_childid"]} ";
   $xinvoice2 = $CON->select($sql);
   $xinvoice2 = $xinvoice2[0];

   $_REQUEST["cust_id_0"]        = (int)$xinvoice1["invc_cust_id"];
   $_REQUEST["company_id"]       = (int)$xinvoice1["invc_company_id"];
   $_REQUEST["shop_id"]          = (int)$xinvoice1["invc_shop_id"];
   $invcdata["id"]               = (int)$xinvoice1["id"];
   $_REQUEST["note_taxes"]       = (int)$xinvoice1["invc_taxes"];
   $_REQUEST["invc_docnumber"]   = $xinvoice1["invc_docnumber"];
   $note_paymentid               = $xinvoice1["invc_paymentid"];
   $note_invcnumber              = $xinvoice1["invc_docnumber"];
   $_REQUEST["note_type"]        = 1;   
   $_REQUEST["subexec"]          = "create";
   $_FROMDISCOUNTSPEC            = true;
   $note_isdiscountspec          = 1;

   if($xinvoice1["invc_taxes"])
      $sql_taxesperc = $_SESSION["_CONF"]["conf_taxes"];
   else
      $sql_taxesperc = 0;
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   
   $_REQUEST["cust_id_0"]     = (int)$_REQUEST["cust_id_0"];
   $_REQUEST["company_id"]    = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]       = (int)$_REQUEST["shop_id"];
   $_REQUEST["note_issueid"]  = (int)$_REQUEST["note_issueid"];
   $_REQUEST["note_isinvcbrutto"] = (int)$_REQUEST["note_isinvcbrutto"];
   $note_taxes                = (int)$_REQUEST["note_taxes"];
   $note_type                 = (int)$_REQUEST["note_type"];
   $note_type_contype         = (int)$_REQUEST["note_type_contype"];
   $note_paymentid            = (int)$note_paymentid;

   if(!$_REQUEST["note_issueid"])
      $_REQUEST["note_issueid"] = 3;

   if(!$note_paymentid)
   {
      //----------------------------------------------------------------------------------
      $sql = " select t1.cust_paymentid, t1.cust_sellerid, t1.cust_transportid, t2.pay_days
               from customer t1
               LEFT OUTER JOIN payments t2 ON t1.cust_paymentid = t2.id
               where
               t1.id = {$_REQUEST["cust_id_0"]}";
      $custdata = $CON->select($sql);
      $custdata = $custdata[0];

      $note_paymentid   = (int)$custdata["cust_paymentid"];
      $note_paymentdays = (int)$custdata["pay_days"];
      $note_estpay_date = 0;
   }

   if($note_paymentid)
   {
      $tmp_estpay       = time() + ($note_paymentdays * 86400);
      $note_estpay_date = mktime(15, 0, 0, date('m', $tmp_estpay), date('d', $tmp_estpay), date('Y', $tmp_estpay));
   }

   if($note_type == 1)
      $note_numbersystem = "invoicesellnotecred";
   else
      $note_numbersystem = "invoicesellnotedeb";

   //----------------------------------------------------------------------------------
   $note_number   = createTransactionNumber($CON, $_REQUEST["company_id"], $note_numbersystem);
   $note_date     = mktime(0, 0, 0, date('m'), date('d'), date('Y'));

   //----------------------------------------------------------------------------------
   $invccfg = getCompanyInvoiceConfig($CON, $_REQUEST["company_id"]);
   if((int)$invccfg["company_invc_mode"])
      $note_docnumber = $note_number;
   else
      $note_docnumber = "";
      
   //----------------------------------------------------------------------------------
   $sql = " insert into invoices_notes_sell
            (note_number, note_cust_id, note_company_id, note_shop_id, note_date,
             note_receipt_date, note_taxes, note_type, note_isdiscountspec, note_stockchange,
             note_parent_guaid, note_paymentid, note_estpay_date, note_crtdat, note_crtusr,
             note_invcnumber, note_type_contype, note_docnumber, note_issueid, note_isinvcbrutto)
            VALUES
            ('{$note_number}', {$_REQUEST["cust_id_0"]}, {$_REQUEST["company_id"]},
              {$_REQUEST["shop_id"]}, {$note_date}, {$note_date}, {$note_taxes}, {$note_type},
              {$note_isdiscountspec}, {$note_stockchange}, {$note_parent_guaid}, {$note_paymentid},
              {$note_estpay_date}, {$currtme}, {$_SESSION["user_id"]}, '{$note_invcnumber}',
              {$note_type_contype}, '{$note_docnumber}', {$_REQUEST["note_issueid"]},
              {$_REQUEST["note_isinvcbrutto"]})";
   $res = $CON->no_result($sql);

   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_notes_sell
               where
               note_crtusr = {$_SESSION["user_id"]}";
      $invoice = $CON->select($sql);
      $_REQUEST["id"]   = $invoice[0]["thisid"];

      $_REQUEST["validatedocnmum"] = (int)trim($_REQUEST["validatedocnmum"]);

      if($note_type_contype == 0 && $_REQUEST["validatedocnmum"])
      {
         $sql = " select t1.*
                  from invoices_sell t1
                  where
                  t1.invc_status    > 1 and
                  t1.invc_docnumber = '{$_REQUEST["validatedocnmum"]}'";
         $invcdata = $CON->select($sql);
         $invcdata = $invcdata[0];

         if((int)$invcdata["id"])
         {
            $sql = " update invoices_notes_sell
                     set
                     note_invcnumber = '{$invcdata["invc_docnumber"]}-".date("d.m.Y", $invcdata["invc_date"])."',
                     note_taxes      = {$invcdata["invc_taxes"]}
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);

            $invcparts  = getInvoiceSellParts($CON, $invcdata["id"]);
            $posdata    = Array();
            for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
            {
               $partposdata = getInvoiceSellPartsItems($CON, $invcdata["id"], $invcparts[$x]["id"]);
               for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
               {
                  $posdata[] = $partposdata[$y];
               }
            }

            $poscounter = 0;
            foreach($posdata AS $posdatarow)
            {
               $item_desc = trim(addslashes($posdatarow["item_desc"]));
               $sql = " insert into invoices_notes_sell_items
                        (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                         item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                         item_sellprice_netto_dsc, item_st_id, item_desc)
                        VALUES
                        ({$_REQUEST["id"]}, {$posdatarow["item_id"]}, {$poscounter}, {$posdatarow["item_amount"]}, '{$posdatarow["item_type"]}',
                         {$posdatarow["item_sellprice_brutto"]}, {$posdatarow["item_sellprice_taxes_perc"]},
                         {$posdatarow["item_sellprice_netto"]}, {$posdatarow["item_sellprice_taxes"]},
                         {$posdatarow["item_sellprice_netto_dsc"]},
                         {$posdatarow["item_st_id"]}, '{$item_desc}')";
               $CON->no_result($sql);

               recalcOrderItem($CON, $_REQUEST["id"], $posdatarow["item_id"], $poscounter, false, "INVOICENOTE");
               
               $poscounter++;
            }
            recalcOrder($CON, $_REQUEST["id"], "INVOICENOTE");
         }
      }
      elseif($note_type_contype == 8 && $_REQUEST["validatedocnmum"])
      {
         $sql = " select t1.*
                  from invoices_sell_bol t1
                  where
                  t1.invc_status    > 1 and
                  t1.invc_docnumber = '{$_REQUEST["validatedocnmum"]}'";
         $invcdata = $CON->select($sql);
         $invcdata = $invcdata[0];

         if((int)$invcdata["id"])
         {
            $sql = " update invoices_notes_sell
                     set
                     note_invcnumber = '{$invcdata["invc_docnumber"]}-".date("d.m.Y", $invcdata["invc_date"])."',
                     note_taxes      = {$invcdata["invc_taxes"]}
                     where
                     id = {$_REQUEST["id"]}";
            $CON->no_result($sql);
            
            $invcparts  = getInvoiceSellParts($CON, $invcdata["id"], "_bol");
            $posdata    = Array();
            for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
            {
               $partposdata = getInvoiceSellPartsItems($CON, $invcdata["id"], $invcparts[$x]["id"], "", "_bol");
               for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
               {
                  $posdata[] = $partposdata[$y];
               }
            }

            $poscounter = 0;
            foreach($posdata AS $posdatarow)
            {
               $item_desc = trim(addslashes($posdatarow["item_desc"]));
               $sql = " insert into invoices_notes_sell_items
                        (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                         item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                         item_sellprice_netto_dsc, item_st_id, item_desc)
                        VALUES
                        ({$_REQUEST["id"]}, {$posdatarow["item_id"]}, {$poscounter}, {$posdatarow["item_amount"]}, '{$posdatarow["item_type"]}',
                         {$posdatarow["item_sellprice_brutto"]}, {$posdatarow["item_sellprice_taxes_perc"]},
                         {$posdatarow["item_sellprice_netto"]}, {$posdatarow["item_sellprice_taxes"]},
                         {$posdatarow["item_sellprice_netto_dsc"]},
                         {$posdatarow["item_st_id"]}, '{$item_desc}')";
               $CON->no_result($sql);

               recalcOrderItem($CON, $_REQUEST["id"], $posdatarow["item_id"], $poscounter, false, "INVOICENOTE");
               
               $poscounter++;
            }
            recalcOrder($CON, $_REQUEST["id"], "INVOICENOTE");
         }
      }

      //----------------------------------------------------------------------------------
      /*
      if($_FROMDISCOUNTSPEC && $note_isguarantee)
      {
         $poscounter = 0;
         foreach($xguapos AS $xguarow)
         {
            $spec_diff_netto        = $xguarow["item_sellprice_netto"];
            $spec_diff_taxes_perc   = $xguarow["item_sellprice_taxes_perc"];
            $spec_diff_taxes        = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto / 100 * $spec_diff_taxes_perc);
            $spec_diff_brutto       = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto + $spec_diff_taxes);
            $spec_diff_netto_dsc    = $xguarow["item_amount"] * $spec_diff_netto;
            
            $sql = " insert into invoices_notes_sell_items
                     (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                      item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                      item_sellprice_netto_dsc, item_desc, item_discount, item_pcat_dsc_act, item_vol_act, item_value_act)
                     VALUES
                     ({$_REQUEST["id"]}, {$xguarow["item_id"]}, {$poscounter}, {$xguarow["item_amount"]}, '{$xguarow["item_type"]}',
                      {$spec_diff_brutto}, {$spec_diff_taxes_perc}, {$spec_diff_netto}, {$spec_diff_taxes}, {$spec_diff_netto_dsc},
                      '{$xguarow["item_desc"]}', 0, 0, 0, 0)";
            $CON->no_result($sql);

            recalcOrderItem($CON, $_REQUEST["id"], $xguarow["item_id"], $poscounter, false, "INVOICENOTE");
            $poscounter++;
         }
         recalcOrder($CON, $_REQUEST["id"], "INVOICENOTE");
      }

      //----------------------------------------------------------------------------------
      elseif($_FROMDISCOUNTSPEC && $note_isdiscountman)
      {
         //----------------------------------------------------------------------------------
         $posdata    = Array();
         $invcparts  = getInvoiceSellParts($CON, $_REQUEST["invcidOrg"]);
         for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
         {
            $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["invcidOrg"], $invcparts[$x]["id"]);
            for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
               $posdata[] = $partposdata[$y];
         }
         foreach($posdata AS $row)
            $_CATVALS[$row["cat_id"]]["ORIG"] += $row["item_sellprice_netto_dsc"];

         //----------------------------------------------------------------------------------
         $_RES = getCustomerNoteDiscounts($CON, $xinvoice1["invc_cust_id"]);

         $poscounter = 0;
         foreach(array_keys($_CATVALS) AS $catid)
         {
            $sql = " select cat_title
                     from productcats
                     where
                     id = {$catid}";
            $catname = $CON->select($sql);

            $dsc        = $_RES[$catid]["ORIG"];
            $new_netto  = round($posdata[$x]["item_sellprice_netto_dsc"] - ($posdata[$x]["item_sellprice_netto_dsc"] / 100 * $dsc),0);
            $new_diff   = $posdata[$x]["item_sellprice_netto_dsc"] - $new_netto;

            $spec_diff_netto     = round($_CATVALS[$catid]["ORIG"] / 100 * $_RES[$catid],0);
            $spec_diff_descperc  = round($_RES[$catid], 0);
            $spec_diff_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto / 100 * $sql_taxesperc);
            $spec_diff_brutto    = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto + $spec_diff_taxes);

            $itemtext  = "POR DESCUENTO ADICIONAL DE {$spec_diff_descperc}%\n";
            $itemtext .= "EN FAMILIA (".sprintf("%03s",$catid).") {$catname[0]["cat_title"]}\n";
            $itemtext .= "FACTURA AFECTA {$_REQUEST["invc_docnumber"]}\n";
            $itemtext .= "SOBRE VALOR $ ".printPrice($_CATVALS[$catid]["ORIG"]);
            
            if($spec_diff_netto > 0.00)
            {
               $catid = (int)$catid;
               $sql = " insert into invoices_notes_sell_items
                        (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                         item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                         item_sellprice_netto_dsc, item_desc, item_com_catid)
                        VALUES
                        ({$_REQUEST["id"]}, 9999999, {$poscounter}, 1, 'manual',
                         {$spec_diff_brutto}, {$sql_taxesperc}, {$spec_diff_netto}, {$spec_diff_taxes}, {$spec_diff_netto},
                         '{$itemtext}', {$catid})";
               $CON->no_result($sql);

               recalcOrderItem($CON, $_REQUEST["id"], 9999999, $poscounter, true, "INVOICENOTE");

               $poscounter++;
            }
         }
         recalcOrder($CON, $_REQUEST["id"], "INVOICENOTE");
      }
      elseif($_FROMDISCOUNTSPEC && $note_isdiscountspec)
      {
         //----------------------------------------------------------------------------------
         if((int)$_REQUEST["specmulti"] && count($_ARRINVCS) > 1)
         {
            $poscounter = 0;
            for($yyy = 0; $yyy < count($_ARRINVCS); $yyy++)
            {
               //----------------------------------------------------------------------------------
               $sql = " select t1.*
                        from invoices_sell t1
                        where
                        t1.id = {$_ARRINVCS[$yyy]} ";
               $xinvoice1 = $CON->select($sql);
               $xinvoice1 = $xinvoice1[0];
               
               //----------------------------------------------------------------------------------
               $sql = " select t1.*
                        from invoices_sell t1
                        where
                        t1.id = {$xinvoice1["invc_childid"]} ";
               $xinvoice2 = $CON->select($sql);
               $xinvoice2 = $xinvoice2[0];

               //----------------------------------------------------------------------------------
               if($yyy > 0)
               {
                  $sql = " update invoices_notes_sell
                           set
                           note_invcnumber = CONCAT(note_invcnumber,',{$xinvoice1["invc_docnumber"]}')
                           where
                           id = {$_REQUEST["id"]}";
                  $CON->no_result($sql);
               }

               unset($_CATVALS);
               $posdata    = Array();
               $invcparts  = getInvoiceSellParts($CON, $_ARRINVCS[$yyy]);
               for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
               {
                  $partposdata = getInvoiceSellPartsItems($CON, $_ARRINVCS[$yyy], $invcparts[$x]["id"]);
                  for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
                     $posdata[] = $partposdata[$y];
               }
               foreach($posdata AS $row)
                  $_CATVALS[$row["cat_id"]]["ORIG"] += $row["item_sellprice_netto_dsc"];

               //----------------------------------------------------------------------------------
               $posdata    = Array();
               $invcparts  = getInvoiceSellParts($CON, $xinvoice2["id"]);
               for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
               {
                  $partposdata = getInvoiceSellPartsItems($CON, $xinvoice2["id"], $invcparts[$x]["id"]);
                  for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
                     $posdata[] = $partposdata[$y];
               }
               foreach($posdata AS $row)
                  $_CATVALS[$row["cat_id"]]["DESC"] += $row["item_sellprice_netto_dsc"];

               //----------------------------------------------------------------------------------
               $sql = " select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                        from invoices_notes_sell t1
                        INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                        INNER JOIN item_productcats t4            ON t2.item_id = t4.item_id
                        where
                        t1.note_type    = 1 and
                        t1.note_status  > 1 and
                        t1.note_status  < 4 and
                        t2.item_invc_id = {$_ARRINVCS[$yyy]} and
                        t2.item_type    = 'item'
                        group by 1
                        UNION ALL
                        select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                        from invoices_notes_sell t1
                        INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                        INNER JOIN item_productcats_itemlist t4   ON t2.item_id = t4.item_id
                        where
                        t1.note_type    = 1 and
                        t1.note_status  > 1 and
                        t1.note_status  < 4 and
                        t2.item_invc_id = {$_ARRINVCS[$yyy]} and
                        t2.item_type    = 'itemlist'
                        group by 1
                        UNION ALL
                        select t2.item_com_catid 'cat_id', SUM(t2.item_sellprice_netto_dsc) 'netto'
                        from invoices_notes_sell t1
                        INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                        where
                        t1.note_type    = 1 and
                        t1.note_status  > 1 and
                        t1.note_status  < 4 and
                        t2.item_invc_id = {$_ARRINVCS[$yyy]} and
                        t2.item_type    = 'manual'
                        group by 1";
               $noterefs = $CON->select($sql);
               foreach($noterefs AS $noteref)
                  $_CATVALS[$noteref["cat_id"]]["NOTES"] -= $noteref["netto"];

               //----------------------------------------------------------------------------------
               $sql = " select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                        from invoices_notes_sell t1
                        INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                        INNER JOIN item_productcats t4            ON t2.item_id = t4.item_id
                        where
                        t1.note_type    = 2 and
                        t1.note_status  > 1 and
                        t1.note_status  < 4 and
                        t2.item_invc_id = {$_ARRINVCS[$yyy]} and
                        t2.item_type    = 'item'
                        group by 1
                        UNION ALL
                        select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                        from invoices_notes_sell t1
                        INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                        INNER JOIN item_productcats_itemlist t4   ON t2.item_id = t4.item_id
                        where
                        t1.note_type    = 2 and
                        t1.note_status  > 1 and
                        t1.note_status  < 4 and
                        t2.item_invc_id = {$_ARRINVCS[$yyy]} and
                        t2.item_type    = 'itemlist'
                        group by 1
                        UNION ALL
                        select t2.item_com_catid 'cat_id', SUM(t2.item_sellprice_netto_dsc) 'netto'
                        from invoices_notes_sell t1
                        INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                        where
                        t1.note_type    = 2 and
                        t1.note_status  > 1 and
                        t1.note_status  < 4 and
                        t2.item_invc_id = {$_ARRINVCS[$yyy]} and
                        t2.item_type    = 'manual'
                        group by 1";
               $noterefs = $CON->select($sql);
               foreach($noterefs AS $noteref)
                  $_CATVALS[$noteref["cat_id"]]["NOTES"] += $noteref["netto"];

               foreach(array_keys($_CATVALS) AS $catid)
               {
                  $sql = " select cat_title
                           from productcats
                           where
                           id = {$catid}";
                  $catname = $CON->select($sql);
                  
                  $spec_diff_netto     = $_CATVALS[$catid]["ORIG"] - $_CATVALS[$catid]["DESC"];
                  $spec_diff_descperc  = round($spec_diff_netto / $_CATVALS[$catid]["ORIG"] * 100, 0);
                  
                  $notes_desc  = $_CATVALS[$catid]["NOTES"];
                  if($notes_desc != 0.00)
                  {
                     $dscperc = $notes_desc / $_CATVALS[$catid]["ORIG"] * 100;
                     $dscval  = $spec_diff_netto / 100 * $dscperc;
                     $spec_diff_netto += $dscval;
                  }
               
                  
                  $spec_diff_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto / 100 * $sql_taxesperc);
                  $spec_diff_brutto    = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto + $spec_diff_taxes);

                  $itemtext  = "POR DESCUENTO ADICIONAL DE {$spec_diff_descperc}%\n";
                  $itemtext .= "EN FAMILIA (".sprintf("%03s",$catid).") {$catname[0]["cat_title"]}\n";
                  $itemtext .= "FACTURA AFECTA {$xinvoice1["invc_docnumber"]}\n";
                  $itemtext .= "SOBRE VALOR $ ".printPrice($_CATVALS[$catid]["ORIG"] + $notes_desc);

                  if($spec_diff_netto > 0.00)
                  {
                     $sql = " insert into invoices_notes_sell_items
                              (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                               item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                               item_sellprice_netto_dsc, item_desc, item_com_catid, item_invc_id, item_invc_docnumber)
                              VALUES
                              ({$_REQUEST["id"]}, 9999999, {$poscounter}, 1, 'manual',
                               {$spec_diff_brutto}, {$sql_taxesperc}, {$spec_diff_netto}, {$spec_diff_taxes}, {$spec_diff_netto},
                               '{$itemtext}', {$catid}, {$_ARRINVCS[$yyy]}, '{$xinvoice1["invc_docnumber"]}')";
                     $CON->no_result($sql);

                     recalcOrderItem($CON, $_REQUEST["id"], 9999999, $poscounter, true, "INVOICENOTE");

                     $poscounter++;
                  }
               }
            }
         }
         //----------------------------------------------------------------------------------
         else
         {
            $posdata    = Array();
            $invcparts  = getInvoiceSellParts($CON, $_REQUEST["invcidOrg"]);
            for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
            {
               $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["invcidOrg"], $invcparts[$x]["id"]);
               for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
                  $posdata[] = $partposdata[$y];
            }
            foreach($posdata AS $row)
               $_CATVALS[$row["cat_id"]]["ORIG"] += $row["item_sellprice_netto_dsc"];

            //----------------------------------------------------------------------------------
            $posdata    = Array();
            $invcparts  = getInvoiceSellParts($CON, $xinvoice2["id"]);
            for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
            {
               $partposdata = getInvoiceSellPartsItems($CON, $xinvoice2["id"], $invcparts[$x]["id"]);
               for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
                  $posdata[] = $partposdata[$y];
            }
            foreach($posdata AS $row)
               $_CATVALS[$row["cat_id"]]["DESC"] += $row["item_sellprice_netto_dsc"];

            //----------------------------------------------------------------------------------
            $sql = " select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                     INNER JOIN item_productcats t4            ON t2.item_id = t4.item_id
                     where
                     t1.note_type    = 1 and
                     t1.note_status  > 1 and
                     t1.note_status  < 4 and
                     t2.item_invc_id = {$_REQUEST["invcidOrg"]} and
                     t2.item_type    = 'item'
                     group by 1
                     UNION ALL
                     select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                     INNER JOIN item_productcats_itemlist t4   ON t2.item_id = t4.item_id
                     where
                     t1.note_type    = 1 and
                     t1.note_status  > 1 and
                     t1.note_status  < 4 and
                     t2.item_invc_id = {$_REQUEST["invcidOrg"]} and
                     t2.item_type    = 'itemlist'
                     group by 1
                     UNION ALL
                     select t2.item_com_catid 'cat_id', SUM(t2.item_sellprice_netto_dsc) 'netto'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                     where
                     t1.note_type    = 1 and
                     t1.note_status  > 1 and
                     t1.note_status  < 4 and
                     t2.item_invc_id = {$_REQUEST["invcidOrg"]} and
                     t2.item_type    = 'manual'
                     group by 1";
            $noterefs = $CON->select($sql);
            foreach($noterefs AS $noteref)
               $_CATVALS[$noteref["cat_id"]]["NOTES"] -= $noteref["netto"];

            //----------------------------------------------------------------------------------
            $sql = " select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                     INNER JOIN item_productcats t4            ON t2.item_id = t4.item_id
                     where
                     t1.note_type    = 2 and
                     t1.note_status  > 1 and
                     t1.note_status  < 4 and
                     t2.item_invc_id = {$_REQUEST["invcidOrg"]} and
                     t2.item_type    = 'item'
                     group by 1
                     UNION ALL
                     select t4.cat_id, SUM(t2.item_sellprice_netto_dsc) 'netto'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                     INNER JOIN item_productcats_itemlist t4   ON t2.item_id = t4.item_id
                     where
                     t1.note_type    = 2 and
                     t1.note_status  > 1 and
                     t1.note_status  < 4 and
                     t2.item_invc_id = {$_REQUEST["invcidOrg"]} and
                     t2.item_type    = 'itemlist'
                     group by 1
                     UNION ALL
                     select t2.item_com_catid 'cat_id', SUM(t2.item_sellprice_netto_dsc) 'netto'
                     from invoices_notes_sell t1
                     INNER JOIN invoices_notes_sell_items t2   ON t1.id = t2.note_id
                     where
                     t1.note_type    = 2 and
                     t1.note_status  > 1 and
                     t1.note_status  < 4 and
                     t2.item_invc_id = {$_REQUEST["invcidOrg"]} and
                     t2.item_type    = 'manual'
                     group by 1";
            $noterefs = $CON->select($sql);
            foreach($noterefs AS $noteref)
               $_CATVALS[$noteref["cat_id"]]["NOTES"] += $noteref["netto"];

            $poscounter = 0;
            foreach(array_keys($_CATVALS) AS $catid)
            {
               $sql = " select cat_title
                        from productcats
                        where
                        id = {$catid}";
               $catname = $CON->select($sql);

               $spec_diff_netto = $_CATVALS[$catid]["ORIG"] - $_CATVALS[$catid]["DESC"];
               $spec_diff_descperc  = round($spec_diff_netto / $_CATVALS[$catid]["ORIG"] * 100, 0);
               
               $notes_desc  = $_CATVALS[$catid]["NOTES"];
               if($notes_desc != 0.00)
               {
                  $dscperc = $notes_desc / $_CATVALS[$catid]["ORIG"] * 100;
                  $dscval  = $spec_diff_netto / 100 * $dscperc;
                  $spec_diff_netto += $dscval;
               }
               
               $spec_diff_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto / 100 * $sql_taxesperc);
               $spec_diff_brutto    = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $spec_diff_netto + $spec_diff_taxes);

               $itemtext  = "POR DESCUENTO ADICIONAL DE {$spec_diff_descperc}%\n";
               $itemtext .= "EN FAMILIA (".sprintf("%03s",$catid).") {$catname[0]["cat_title"]}\n";
               $itemtext .= "FACTURA AFECTA {$_REQUEST["invc_docnumber"]}\n";
               $itemtext .= "SOBRE VALOR $ ".printPrice($_CATVALS[$catid]["ORIG"] + $notes_desc);

               if($spec_diff_netto > 0.00)
               {
                  $sql = " insert into invoices_notes_sell_items
                           (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                            item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                            item_sellprice_netto_dsc, item_desc, item_com_catid)
                           VALUES
                           ({$_REQUEST["id"]}, 9999999, {$poscounter}, 1, 'manual',
                            {$spec_diff_brutto}, {$sql_taxesperc}, {$spec_diff_netto}, {$spec_diff_taxes}, {$spec_diff_netto},
                            '{$itemtext}', {$catid})";
                  $CON->no_result($sql);

                  recalcOrderItem($CON, $_REQUEST["id"], 9999999, $poscounter, true, "INVOICENOTE");

                  $poscounter++;
               }
            }
         }
         recalcOrder($CON, $_REQUEST["id"], "INVOICENOTE");
      }
      */
      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=755&exec=edit&id=<?=$_REQUEST["id"]?>'
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
            from invoices_notes_sell
            where
            id = {$_REQUEST["id"]}";
   $invoicetemp      = $CON->select($sql);
   $invoicetaxes     = $invoicetemp[0]["note_taxes"];
   $invoicecustomer  = $invoicetemp[0]["note_cust_id"];
   $invoicedate      = $invoicetemp[0]["note_date"];
   
   //----------------------------------------------------------------------------------
   $_REQUEST["note_desc"]        = trim(addslashes($_REQUEST["note_desc"]));
   $_REQUEST["note_date"]        = trim(addslashes($_REQUEST["note_date"]));
   $_REQUEST["note_docnumber"]   = trim(addslashes($_REQUEST["note_docnumber"]));
   $_REQUEST["note_paymentid"]   = (int)$_REQUEST["note_paymentid"];
   $_REQUEST["note_payed"]       = (int)$_REQUEST["note_payed"];
   $_REQUEST["note_stockchange"] = (int)$_REQUEST["note_stockchange"];
   $_REQUEST["note_issueid"]     = (int)$_REQUEST["note_issueid"];
   $_REQUEST["note_userid_seller"]  = (int)$_REQUEST["note_userid_seller"];
   $_REQUEST["note_userid_cashing"] = (int)$_REQUEST["note_userid_cashing"];

   if((int)$_REQUEST["note_paymentid"] != (int)$invoicetemp[0]["note_paymentid"] && !(int)$invoicetemp[0]["note_parent_guaid"])
      $itemfullupdate = true;
   else
      $itemfullupdate = false;

   //----------------------------------------------------------------------------------
   $sql = " update invoices_notes_sell
            set
            note_paymentid       = {$_REQUEST["note_paymentid"]},
            note_userid_seller   = {$_REQUEST["note_userid_seller"]},
            note_userid_cashing  = {$_REQUEST["note_userid_cashing"]}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_notes_sell
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

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

         $_REQUEST["item_stid_{$idx}"]   = (int)$_REQUEST["item_stid_{$idx}"];
         $_REQUEST["item_amount_{$idx}"] = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $_REQUEST["item_invc_docnumber_{$idx}"] = trim(addslashes($_REQUEST["item_invc_docnumber_{$idx}"]));

         $invcrelid = 0;
         //----------------------------------------------------------------------------------
         if($invoicetemp[0]["note_type_contype"] == 0 || $invoicetemp[0]["note_type_contype"] == 4)
         {
            $sql = " select id
                     from invoices_sell
                     where
                     invc_company_id   = {$invoicetemp[0]["note_company_id"]} and
                     invc_shop_id      = {$invoicetemp[0]["note_shop_id"]} and
                     invc_cust_id      = {$invoicecustomer} and
                     invc_docnumber    = '{$_REQUEST["item_invc_docnumber_{$idx}"]}' and
                     invc_status       > 1 and
                     invc_status       < 4";
            $invcrelid = $CON->select($sql);
            $invcrelid = (int)$invcrelid[0]["id"];
         }
         elseif($invoicetemp[0]["note_type_contype"] == 1 || $invoicetemp[0]["note_type_contype"] == 5)
         {
            $sql = " select id
                     from invoices_notes_sell
                     where
                     note_company_id   = {$invoicetemp[0]["note_company_id"]} and
                     note_shop_id      = {$invoicetemp[0]["note_shop_id"]} and
                     note_cust_id      = {$invoicecustomer} and
                     note_docnumber    = '{$_REQUEST["item_invc_docnumber_{$idx}"]}' and
                     note_type         = 1 and
                     note_status       > 1 and
                     note_status       < 4";
            $invcrelid = $CON->select($sql);
            $invcrelid = (int)$invcrelid[0]["id"];
         }
         elseif($invoicetemp[0]["note_type_contype"] == 2 || $invoicetemp[0]["note_type_contype"] == 6)
         {
            $sql = " select id
                     from invoices_notes_sell
                     where
                     note_company_id   = {$invoicetemp[0]["note_company_id"]} and
                     note_shop_id      = {$invoicetemp[0]["note_shop_id"]} and
                     note_cust_id      = {$invoicecustomer} and
                     note_docnumber    = '{$_REQUEST["item_invc_docnumber_{$idx}"]}' and
                     note_type         = 2 and
                     note_status       > 1 and
                     note_status       < 4";
            $invcrelid = $CON->select($sql);
            $invcrelid = (int)$invcrelid[0]["id"];
         }

         if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["item_amount_{$idx}"] > 0.00)
         {
            $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id           = (int)$itemvalues[0];
            $sql_type         = $itemvalues[1];
            $sql_charges_act  = (int)$itemvalues[5];

            if(!$_REQUEST["note_stockchange"])
               $_REQUEST["item_stid_{$idx}"] = 0;

            //----------------------------------------------------------------------------------
            if($invoicetaxes)
            {
               $sql_taxesperc = getPrice($_REQUEST["item_sellprice_taxes_perc_{$idx}"], 2);
               if(!(int)$headdata["note_isinvcbrutto"])
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

            //----------------------------------------------------------------------------------
            $item_sellprice_netto_dsc = round($sql_sellnetto * $_REQUEST["item_amount_{$idx}"], 0);

            //----------------------------------------------------------------------------------
            if((int)$_REQUEST["manual_pos_{$idx}"])
            {
               $sql_item_com_catid = (int)$_REQUEST["item_com_catid_{$idx}"];
               $_REQUEST["item_desc_{$idx}"] = trim(addslashes($_REQUEST["item_desc_{$idx}"]));
            }
            else
            {
               $_REQUEST["item_desc_{$idx}"] = "";
               $sql_item_com_catid = 0;
            }

            //----------------------------------------------------------------------------------
            if($existing_id)
            {
               $sql = " update invoices_notes_sell_items
                        set
                        item_amount                = {$_REQUEST["item_amount_{$idx}"]},
                        item_sellprice_brutto      = {$sql_sellprice},
                        item_sellprice_taxes_perc  = {$sql_taxesperc},
                        item_sellprice_netto       = {$sql_sellnetto},
                        item_sellprice_taxes       = {$sql_taxes},
                        item_sellprice_netto_dsc   = {$item_sellprice_netto_dsc},
                        item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}',
                        item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                        item_pos                   = {$poscounter},
                        item_com_catid             = {$sql_item_com_catid},
                        item_invc_docnumber        = '{$_REQUEST["item_invc_docnumber_{$idx}"]}',
                        item_invc_id               = {$invcrelid}
                        where
                        note_id  = {$_REQUEST["id"]} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);

               recalcOrderItem($CON, $_REQUEST["id"], $existing_id, $poscounter, $itemfullupdate, "INVOICENOTE");
               
               renameItemChargePos($CON, $_REQUEST["id"], "invoicesnotessell", $existing_pos, $poscounter);

               updateItemChargeData($CON, $_REQUEST["id"], "invoicesnotessell", $existing_id, $sql_type, $poscounter,
                                    $invoicetemp[0]["note_company_id"], $invoicetemp[0]["note_shop_id"], $_REQUEST["item_charges_data_{$idx}"]);
               
               

               //----------------------------------------------------------------------------------
               $_TABLENAME    = "invoices_notes_sell_items";
               $_TABLENAMEHD  = "invoices_notes_sell";
               $_COLPREFIX    = "note";
               $_AMOUNTFIELD  = "item_amount";

               //----------------------------------------------------------------------------------
               if(!$itemfullupdate)
               {
                  $_REQUEST["item_pcat_dsc_act_{$idx}"]     = (int)$_REQUEST["item_pcat_dsc_act_{$idx}"];
                  $_REQUEST["item_vol_act_{$idx}"]          = (int)$_REQUEST["item_vol_act_{$idx}"];
                  $_REQUEST["item_value_act_{$idx}"]        = (int)$_REQUEST["item_value_act_{$idx}"];

                  $_REQUEST["item_discount_{$idx}"]         = getPrice($_REQUEST["item_discount_{$idx}"],2);
                  $_REQUEST["item_discount_type_{$idx}"]    = (int)$_REQUEST["item_discount_type_{$idx}"];

                  for($y = 1; $y <= 4; $y++)
                     $_REQUEST["item_pcat_dsc_{$idx}_{$y}"] = getPrice($_REQUEST["item_pcat_dsc_{$idx}_{$y}"],2);

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
                           {$_COLPREFIX}_id     = {$_REQUEST["id"]} and
                           item_pos             = {$poscounter}";
                  $CON->no_result($sql);
               }
            }
            else
            {
               $sql = " insert into invoices_notes_sell_items
                        (note_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                         item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                         item_sellprice_netto_dsc, item_st_id, item_desc, item_charges_act, item_com_catid,
                         item_invc_docnumber, item_invc_id)
                        VALUES
                        ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                         {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes}, {$item_sellprice_netto_dsc},
                         {$_REQUEST["item_stid_{$idx}"]}, '{$_REQUEST["item_desc_{$idx}"]}', {$sql_charges_act},
                         {$sql_item_com_catid}, '{$_REQUEST["item_invc_docnumber_{$idx}"]}', {$invcrelid})";
               $CON->no_result($sql);

               //----------------------------------------------------------------------------------
               if($sql_type != "manual")
               {
                  $sql = " select *
                           from invoices_sell_parts_items
                           where
                           invc_id     = {$invcrelid} and
                           item_id     = {$sql_id} and
                           item_type   = '{$sql_type}'";
                  $reldata = $CON->select($sql);
                  $reldata = $reldata[0];

                  if((int)$reldata["invc_id"])
                  {
                     $sql = " update invoices_notes_sell_items
                              set
                              item_sellprice_brutto      = {$reldata["item_sellprice_brutto"]},
                              item_sellprice_taxes_perc  = {$reldata["item_sellprice_taxes_perc"]},
                              item_sellprice_netto       = {$reldata["item_sellprice_netto"]},
                              item_sellprice_taxes       = {$reldata["item_sellprice_taxes"]}, 
                              item_discount              = {$reldata["item_discount"]},
                              item_discount_type         = {$reldata["item_discount_type"]},
                              item_pcat_dsc_act          = {$reldata["item_pcat_dsc_act"]},
                              item_pcat_dsc1             = {$reldata["item_pcat_dsc1"]},
                              item_pcat_dsc2             = {$reldata["item_pcat_dsc2"]},
                              item_pcat_dsc3             = {$reldata["item_pcat_dsc3"]},
                              item_pcat_dsc4             = {$reldata["item_pcat_dsc4"]},
                              item_pcat_dsctype1         = {$reldata["item_pcat_dsctype1"]},
                              item_pcat_dsctype2         = {$reldata["item_pcat_dsctype2"]},
                              item_pcat_dsctype3         = {$reldata["item_pcat_dsctype3"]},
                              item_pcat_dsctype4         = {$reldata["item_pcat_dsctype4"]},
                              item_vol_act               = {$reldata["item_vol_act"]},
                              item_value_act             = {$reldata["item_value_act"]}
                              where
                              note_id                    = {$_REQUEST["id"]} and
                              item_id                    = {$sql_id} and
                              item_pos                   = {$poscounter}";
                     $CON->no_result($sql);
                  }
               }

               //----------------------------------------------------------------------------------
               recalcOrderItem($CON, $_REQUEST["id"], $sql_id, $poscounter, false, "INVOICENOTE");

               updateItemChargeData($CON, $_REQUEST["id"], "invoicesnotessell", $sql_id, $sql_type, $poscounter,
                                    $invoicetemp[0]["note_company_id"], $invoicetemp[0]["note_shop_id"], $_REQUEST["item_charges_data_{$idx}"]);
            }

            $poscounter++;
         }
         elseif($existing_id)
         {
            $sql = " delete from invoices_notes_sell_items
                     where
                     note_id  = {$_REQUEST["id"]} and
                     item_id  = {$existing_id} and
                     item_pos = {$existing_pos}";
            $CON->no_result($sql);

            clearItemChargeData($CON, $_REQUEST["id"], "invoicesnotessell", $existing_pos);
         }
      }
   }

   //----------------------------------------------------------------------------------
   $_REQUEST["dlv_weightprice_netto"] = getPrice($_REQUEST["dlv_weightprice_netto"]);
   
   $sql = " update {$_TABLENAMEHD}
            set
            {$_COLPREFIX}_weightprice_netto = {$_REQUEST["dlv_weightprice_netto"]}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   $checkinvcdate = $_REQUEST["note_date"];

   //----------------------------------------------------------------------------------
   $_REQUEST["note_date"] = explode(".", $_REQUEST["note_date"]);
   $_REQUEST["note_date"] = (int)mktime(15, 0, 0, $_REQUEST["note_date"][1], $_REQUEST["note_date"][0], $_REQUEST["note_date"][2]);
   $_REQUEST["note_receipt_date"] = explode(".", $_REQUEST["note_receipt_date"]);
   $_REQUEST["note_receipt_date"] = (int)mktime(15, 0, 0, $_REQUEST["note_receipt_date"][1], $_REQUEST["note_receipt_date"][0], $_REQUEST["note_receipt_date"][2]);
   if(!$_REQUEST["note_receipt_date"])
      $_REQUEST["note_receipt_date"] = $_REQUEST["note_date"];
      
   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_notes_sell
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $revert  = false;
   $final   = false;
   if($headdata["note_status"] == 1 && $_REQUEST["note_status"] == 2)
   {
      $_REQUEST["note_docnumber"]   = "PENDIENTE_SII";
      $_REQUEST["note_status"]      = 1;
      $final = true;
   }
   elseif($headdata["note_status"] == 2 && $_REQUEST["note_status"] == 1)
      $revert = true;

   //----------------------------------------------------------------------------------
   if($_REQUEST["note_status"] == 1)
      $_REQUEST["note_payed"] = 0;

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


   //----------------------------------------------------------------------------------
   $sql = " update invoices_notes_sell
            set
            note_status          = {$_REQUEST["note_status"]},
            note_desc            = '{$_REQUEST["note_desc"]}',
            note_docnumber       = '{$_REQUEST["note_docnumber"]}',
            note_date            = {$_REQUEST["note_date"]},
            note_paymentid       = {$_REQUEST["note_paymentid"]},
            note_payed           = {$_REQUEST["note_payed"]},
            note_estpay_date     = {$_REQUEST["note_estpay_date"]},
            note_receipt_date    = {$_REQUEST["note_receipt_date"]},
            note_stockchange     = {$_REQUEST["note_stockchange"]},
            note_issueid         = {$_REQUEST["note_issueid"]},
            note_updusr          = {$_SESSION["user_id"]},
            note_upddat          = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

   //----------------------------------------------------------------------------------
   if($headdata["note_status"] == 1)
      recalcOrder($CON, $_REQUEST["id"], "INVOICENOTE");
   if($final)
   {
      $sql = " update invoices_notes_sell
               set
               note_status    = 2,
               note_docnumber = 'PENDIENTE_SII'
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      
      bookInvoiceSellNotes($CON, $_REQUEST["id"]);
   }
   if($revert)
   {
      revertInvoiceSellNotes($CON, $_REQUEST["id"]);

      if($_REQUEST["cancelDoc"] == "1")
      {
         $sql = " update invoices_notes_sell
                  set
                  note_status = 4
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }

      if($_REQUEST["cancelDoc"] == "2")
      {
         $sql = " update invoices_notes_sell
                  set
                  note_status = 1
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name, 
                t2.cust_notes,  t7.pay_title, t8.iss_name,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
         from invoices_notes_sell t1
         LEFT OUTER JOIN customer t2      ON t1.note_cust_id      = t2.id
         LEFT OUTER JOIN company_data t3  ON t1.note_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.note_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.note_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.note_crtusr       = t6.id
         LEFT OUTER JOIN payments t7      ON t1.note_paymentid    = t7.id
         LEFT OUTER JOIN invoices_notes_sell_issues t8 ON t1.note_issueid = t8.id
         LEFT OUTER JOIN user t9          ON t1.note_userid_seller   = t9.id
         LEFT OUTER JOIN user t10         ON t1.note_userid_cashing  = t10.id
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
         t1.id = {$headdata["note_cust_id"]}";
$customer = $CON->select($sql);
$customer = $customer[0];

//----------------------------------------------------------------------------------
$payments   = getPayments($CON, $headdata["note_shop_id"]);

//----------------------------------------------------------------------------------
if($headdata["note_status"] >= 2)
{
   $rdlo       = " readonly ";
   $dabl       = " disabled ";
   $rowcount   = count($posdata);
}

//----------------------------------------------------------------------------------
if((int)$_SESSION["user_pricesell_perm"] || (int)$_REQUEST["user_pricesell_perm"])
   $hasprcsellperm = true;
else
   $hasprcsellperm = false;

//----------------------------------------------------------------------------------
if((int)$_SESSION["user_docopen_perm"] || (int)$_REQUEST["user_docopen_perm"])
   $hasdocopeperm = true;
else
   $hasdocopeperm = false;

$itemselw = "480px";
if((int)$_REQUEST["showDiscounts"])
   $itemselw = "325px";

//----------------------------------------------------------------------------------
$showStorehouses  = false;
$itemselw         = "450px";
if((int)$_REQUEST["showDiscounts"])
   $itemselw = "325px";
   
if((int)$headdata["note_stockchange"])
{
   $showStorehouses  = true;
   $itemselw         = "335px";

   if((int)$_REQUEST["showDiscounts"])
      $itemselw = "270px";
      
}
$pcats            = formatFullProductCats(getFullProductCats($CON, 0));
$sellers          = getSellers($CON);
$note_invcnumber  = $headdata["note_invcnumber"];
$note_invcnumbers = explode(",", $note_invcnumber);

//----------------------------------------------------------------------------------
$_INVCCFG    = getCompanyInvoiceConfig($CON, $headdata["note_company_id"]);
$issues      = getInvoiceSellNotesIssues($CON);

//----------------------------------------------------------------------------------
if($_SESSION["xinvoicessellnotes"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

if($_REQUEST["id"] != "" && $headdata["note_status"] == 1 && $_INVCCFG["company_invc_itf"] == "BCNCONS")
{
   if((int)$headdata["note_type"] == 2)
      $note_docnumber = (int)createSiiNumber($CON, $headdata["note_company_id"], $headdata["note_shop_id"], "itf_notedeb", true);
   else
      $note_docnumber = (int)createSiiNumber($CON, $headdata["note_company_id"], $headdata["note_shop_id"], "itf_notecred", true);

   if(!$note_docnumber)
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
      document.all.idxifrsrc.src='./libs/modules/invoices_sell_notes/searchstorehouses.php?noteid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;

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
      var xurl = './libs/modules/invoices_sell_notes/searchitem.fancy.php?rowcount=' +rowcount + '&id=' +id;
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
   onsubmit="return checkform(new Array(this.note_docnumber, this.note_date, this.note_paymentid, this.note_issueid));"
   <?php
}
?>>
<input type="hidden" name="cancelDoc" value="">
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="note_status" value="1">
<input type="hidden" name="setPosOrder" value="">
<input type="hidden" name="showDiscounts" value="<?=$_REQUEST["showDiscounts"]?>">
<input type="hidden" name="user_pricesell_perm" value="<?=$_REQUEST["user_pricesell_perm"]?>">
<input type="hidden" name="user_docopen_perm" value="<?=$_REQUEST["user_docopen_perm"]?>">
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%" id="ifx_tblheader">
<colgroup>
   <col width="140">
   <col width="360">
   <col width="120">
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
   <td class="content_row"><?=getInvoiceBuyNoteType($headdata["note_type"])?></td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<tbody>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>>Cliente</td>
   <td class="content_row" <?=$cdatastyle?>>
      <a href="javascript:void(0)" style="text-decoration:none;color:black"
      onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$headdata["id"]?>&showfullcust=<?php
      if($_SESSION["xinvoicessellnotes"]["fullcust"] == "") echo "1"; else echo "0"?>'"> <b><?=$customer["cust_name"]?></b></a></td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row" <?=$cdatastyle?>><b><?=$customer["cust_rut"]?></b>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicessellnotes"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$customer["cust_street"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?if($customer["cust_phone"] != "") echo $customer["cust_phone"];?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicessellnotes"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Región</td>
   <td class="content_row"><?=$customer["name"]?>&nbsp;</td>
   <td class="content_rowl">Whatsapp</td>
   <td class="content_row"><?=$customer["cust_fax"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicessellnotes"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Comuna</td>
   <td class="content_row"><?=$customer["pro_name"]?> - <?=$customer["nombre"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
   <td class="content_row"><?=$customer["cust_email"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xinvoicessellnotes"]["fullcust"] == "") echo "style='display:none'"?>>
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
<tr>
   <?php
   if($headdata["note_status"] == 1 && date('d.m.Y', $headdata["note_date"]) != date('d.m.Y'))
   {
      $bcss1   = "<b class='msg_save_err'><blink>";
      $bcss2   = "</blink></b>";
      $tcss    = "border:2px solid red";
   }
   ?>
   <td class="content_rowl" <?=$cdatastyle?> height="31">Número de nota *</td>
   <td class="content_row" <?=$cdatastyle?>>
      <?php
      if((int)$_INVCCFG["company_invc_mode"])
      {  ?>
         <input type="hidden" name="note_docnumber" value="<?=$headdata["note_docnumber"]?>">
         <?php
         if($headdata["note_status"] > 1)
         {  ?>
            <div id="idx_sii_refresh"></div>
            <script language="JavaScript">
               function reloadSiiState()
               {
                  $.get('/libs/modules/invoices_sell/get.siistatus.jquery.php?mode=invoices_sell_notes&id=<?=$_REQUEST["id"]?>', '', function(data)
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
            echo $headdata["note_docnumber"]. " (Temporario)";
      }
      else
      {  ?>
         <input type="text" style="width:350px" name="note_docnumber" class="text" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["note_docnumber"]?>">
         <?php
      }
      ?>
   </td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$bcss1?>Fecha *<?=$bcss2?></td>
   <td class="content_row" <?=$cdatastyle?>>
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear">
            <input type="text" style="width:80px;<?=$tcss?>" id="note_date" name="note_date" <?=$rdlo?>
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
               case 4: $statimg = "gray_active.gif"; break;
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
   <td class="content_rowl">Motivo *</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="note_issueid" id="note_issueid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "" && !(int)$headdata["note_issueid"])
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($issues as $issue)
            {  ?>
               <option value="<?=$issue["id"]?>"
               <?php if($issue["id"] == $headdata["note_issueid"]) echo "selected"?>><?=$issue["iss_name"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["note_issueid"]?>"><?=$headdata["iss_name"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">Precios</td>
   <td class="content_row">
      <?php
      if((int)$headdata["note_isinvcbrutto"])
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
   <td class="content_rowl">Vendedor</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="note_userid_seller" id="note_userid_seller"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($sellers as $seller)
            {  ?>
               <option value="<?=$seller["id"]?>"
               <?php if($seller["id"] == $headdata["note_userid_seller"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["note_userid_seller"]?>"><?=$headdata["seller_firstname"]?> <?=$headdata["seller_lastname"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
   <td class="content_rowl">IVA</td>
   <td class="content_row">
      <select class="text" style="width:120px;background-color:<?php if((int)$headdata["note_taxes"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
         <?php
         if((int)$headdata["note_taxes"])
            echo '<option value="">CON IVA</option>';
         else
            echo '<option value="">SIN IVA</option>';
         ?>
      </select>
   </td>
   <td class="content_rowl" style="display:none">Cobrador</td>
   <td class="content_row" style="display:none">
      <select class="text" style="width:350px" name="note_userid_cashing" id="note_userid_cashing"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($sellers as $seller)
            {  ?>
               <option value="<?=$seller["id"]?>"
               <?php if($seller["id"] == $headdata["note_userid_cashing"]) echo "selected"?>><?=$seller["user_firstname"]?> <?=$seller["user_lastname"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["note_userid_cashing"]?>"><?=$headdata["cashing_firstname"]?> <?=$headdata["cashing_lastname"]?></option>
            <?php
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl" valign="top">Observaciones</td>
   <td class="content_row">
      <textarea class="text" style="width:350px; height:45px" name="note_desc" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=stripslashes($headdata["note_desc"])?></textarea>
   </td>
   <td class="content_rowl" valign="top">Pagado</td>
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
         printButton("{$btntext}", $btnclass, "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&mode=invoices_notes_sell&subcatexec=payment", "", "money", 120);
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
   <?php
   $dsp_arr = explode(",", $headdata["note_invcnumber"]);
   foreach($dsp_arr AS $dsp_arrow)
   {
      $dsp_arrow   = explode("-", $dsp_arrow);
      $xdsp_invcs .= $dsp_arrow[0].",";
   }
   $xdsp_invcs = substr($xdsp_invcs,0,-1);
   $xdsp_invcs = substr($xdsp_invcs,0,50);
            
   if($headdata["note_type_contype"] == 0 || $headdata["note_type_contype"] == 4)
   {  ?>
      <td class="content_rowl">Facturas relacionadas</td>
      <td class="content_row">
         <?php
         if($headdata["note_invcnumber"] == "")
         {
            $dsp_invcs = "SIN FACTURA ASIGNADA.";
            $postnavcs = "postnav_del";
         }
         else
         {
            $dsp_invcs = substr($xdsp_invcs,0,50);
            $postnavcs = "postnav_save";
         }
         if($rdlo == "")
            printButton("{$dsp_invcs}", $postnavcs, "javascript: deactivateFormChange()", "showFancybox('/libs/modules/invoices_sell_notes/invcnumbers.fancy.php?id={$_REQUEST["id"]}', 'iframe', 450, 450, 'no')");
         else
            echo $dsp_invcs;
         ?>
      </td>
      <?php
   }
   elseif($headdata["note_type_contype"] == 1 || $headdata["note_type_contype"] == 5)
   {  ?>
      <td class="content_rowl">Notas relacionadas</td>
      <td class="content_row">
         <?php
         if($headdata["note_invcnumber"] == "")
         {
            $dsp_invcs = "SIN NOTA DE CREDITO ASIGNADA.";
            $postnavcs = "postnav_del";
         }
         else
         {
            $dsp_invcs = substr($xdsp_invcs,0,50);
            $postnavcs = "postnav_save";
         }
         if($rdlo == "")
            printButton("{$dsp_invcs}", $postnavcs, "javascript: deactivateFormChange()", "showFancybox('/libs/modules/invoices_sell_notes/invcnumbers.fancy.php?id={$_REQUEST["id"]}&note_type_contype={$headdata["note_type_contype"]}', 'iframe', 450, 450, 'no')");
         else
            echo $dsp_invcs;
         ?>
      </td>
      <?php
   }
   elseif($headdata["note_type_contype"] == 2 || $headdata["note_type_contype"] == 6)
   {  ?>
      <td class="content_rowl">Notas relacionadas</td>
      <td class="content_row">
         <?php
         if($headdata["note_invcnumber"] == "")
         {
            $dsp_invcs = "SIN NOTA DE DEBITO ASIGNADA.";
            $postnavcs = "postnav_del";
         }
         else
         {
            $dsp_invcs = substr($xdsp_invcs,0,50);
            $postnavcs = "postnav_save";
         }
         if($rdlo == "")
            printButton("{$dsp_invcs}", $postnavcs, "javascript: deactivateFormChange()", "showFancybox('/libs/modules/invoices_sell_notes/invcnumbers.fancy.php?id={$_REQUEST["id"]}&note_type_contype={$headdata["note_type_contype"]}', 'iframe', 450, 450, 'no')");
         else
            echo $dsp_invcs;
         ?>
      </td>
      <?php
   }
   elseif($headdata["note_type_contype"] == 7 || $headdata["note_type_contype"] == 8)
   {  ?>
      <td class="content_rowl">Boletas relacionadas</td>
      <td class="content_row">
         <?php
         if($headdata["note_invcnumber"] == "")
         {
            $dsp_invcs = "SIN BOLETA ASIGNADA.";
            $postnavcs = "postnav_del";
         }
         else
         {
            $dsp_invcs = substr($xdsp_invcs,0,50);
            $postnavcs = "postnav_save";
         }
         if($rdlo == "")
            printButton("{$dsp_invcs}", $postnavcs, "javascript: deactivateFormChange()", "showFancybox('/libs/modules/invoices_sell_notes/invcnumbers.fancy.php?id={$_REQUEST["id"]}&note_type_contype={$headdata["note_type_contype"]}', 'iframe', 450, 450, 'no')");
         else
            echo $dsp_invcs;
         ?>
      </td>
      <?php
   }
   elseif($headdata["note_type_contype"] == 3)
   {  ?>
      <td class="content_rowl">Doc. relacionados</td>
      <td class="content_row">- - -</td>
      <?php
   }
   ?>
   <td class="content_rowl">Cambio de stock</td>
   <td class="content_row">
      <?php
      if($headdata["note_status"] == 1)
      {  ?>
         <select class="text" name="note_stockchange" id="note_stockchange" style="width:120px;background-color:<?php if((int)$headdata["note_stockchange"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
            <option value="1" <?php if((int)$headdata["note_stockchange"]) echo "selected"?>>HABILITADO</option>
            <option value="0" <?php if(!(int)$headdata["note_stockchange"]) echo "selected"?>>DESHABILITADO</option>
         </select>
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
<br>
<?php
$hasItems = false;
$posdata    = getInvoiceSellNoteItems($CON, $_REQUEST["id"], $_REQUEST["setPosOrder"]);
$rowcount   = count($posdata);

//----------------------------------------------------------------------------------
if($headdata["note_status"] == 1)
   $rowcount = 22;
else
   $rowcount = count($posdata);
?>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<?=Nifty_printH("box1", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="90">
   <col width="28">
   <col>
   <col>
   <col <?if($_HIDETAXES) echo 'style="display:none"'?>>
   <col>
   <?php
   if($showStorehouses)
      echo "<col>";
   if($_REQUEST["showDiscounts"] == "1")
   {  ?>
      <col>
      <col>
      <col>
      <col>
      <?php
   }
   ?>
   <col>
   <col>
</colgroup>
<tr>
   <td class="content_tbl_header" colspan="14">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <colgroup>
         <col>
         <col width="50">
      </colgroup>
      <tr>
         <td class="content_tbl_header" style="border:0px;padding:0px">Artículos</td>
         <?php
         if(!(int)$_SESSION["user_pricesell_perm"] && !(int)$_REQUEST["user_pricesell_perm"] && $headdata["note_status"] == 1)
         {  ?>
            <td class="content_tbl_header" style="padding:0px" width="190">
               <img src="./images/menu/icons/currency.png" style="vertical-align:bottom">
               <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
               onclick="showFancybox('/libs/modules/orders/auth.pricechange.fancy.php?frmname=form_shppos', 'iframe', 450, 160, 'no')">Activar cambio de precios</a>
            </td>
            <?php
         }
         if($headdata["note_status"] == 1 && count($posdata) && $posdata != false)
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
            if($headdata["note_status"] == 1)
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
   <td class="content_tbl_subheader" valign="top" align="right">Cantidad</td>
   <?php
   if($showStorehouses)
   {  ?>
      <td class="content_tbl_subheader" valign="top">Bodega</td>
      <?php
   }
   $coltitle = "Precio/Neto";
   if((int)$headdata["note_isinvcbrutto"])
      $coltitle = "Precio/Bruto";
   ?>
   <td class="content_tbl_subheader" valign="top" align="right"><nobr><?=$coltitle?></nobr></td>
   <td class="content_tbl_subheader" valign="top" align="right" <?if($_HIDETAXES) echo 'style="display:none"'?>>IVA %</td>
   <?php
   if($_REQUEST["showDiscounts"] == "1")
   {  ?>
      <td class="content_tbl_subheader" align="right"><nobr>Subtotal</nobr></td>
      <td class="content_tbl_subheader" align="center"><nobr>Desc-Global</nobr></td>
      <td class="content_tbl_subheader" align="center"><nobr>Descuentos-Familia</nobr></td>
      <td class="content_tbl_subheader" align="center"><nobr>Desc-Vol.</nobr></td>
      <td class="content_tbl_subheader" align="center"><nobr>Desc-Monto</nobr></td>
      <?php
   }
   ?>
   <td class="content_tbl_subheader" valign="top" align="right"><nobr>Precio Total</nobr></td>
   <td class="content_tbl_subheader" valign="top"><nobr><?=getInvoiceSellNoteConType($headdata["note_type_contype"])?></nobr></td>
</tr>
<?php
if($headdata["note_type_contype"] == 0 && $headdata["note_invcnumber"] == "")
   $_BLOCKFIN_CHARGE = true;
for($y = 0; $y < $rowcount; $y++)
{
   $showmanual = false;
   if((int)$posdata[$y]["item_id"] && $posdata[$y]["item_type"] == "manual")
      $showmanual = true;
      
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "xf_search_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_id_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_amount_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_stid_{$y}";
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_sellprice_netto_{$y}";
   if(!$_HIDETAXES)
   {
      if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_sellprice_taxes_perc_{$y}";
   }
   if(!$_FIELDREGS[$y]) $_FIELDREGS[$y] = Array(); $_FIELDREGS[$y][] = "item_invc_docnumber_{$y}";

   ?>
   <tr bgcolor="<?=getRowColor($y)?>">
      <td class="content_row" valign="top">
         <?if($showmanual) { echo "&nbsp;";  $_FIELDIGNORES["xf_search_{$y}"] = 1; } ?>
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
                  onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/invoices_sell_notes/searchitem.php?rowcount=<?=$y?>&id=<?=$_REQUEST["id"]?><?=$urlparam?>&search=' +this.value} this.value='';"
                  onkeyup="detectEvent(event, '<?=$y?>', '<?=$_REQUEST["id"]?>')"
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
            onclick="showOrderPartPosManualEdit('<?=$y?>');">
            <?php
         }
         ?>
      </td>
      <td class="content_row" valign="top">
         <?php
         $overlibover   = "";
         $overlibover2  = "";
         $ovritemselw   = "";
         if((int)$posdata[$y]["item_id"])
         {
            $posdata[$y]["item_costprice_netto"] = precalcSupplierCost($CON, $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
            $itemrealsellprice = $posdata[$y]["item_sellprice_netto_dsc"] /  $posdata[$y]["item_amount"];
            if($itemrealsellprice < $posdata[$y]["item_costprice_netto"] || $posdata[$y]["item_invoice_note"] != "")
            {
               if($itemrealsellprice < $posdata[$y]["item_costprice_netto"])
                  $overlibover2 .= "<b class=msg_save_err>Precio de venta {$_SESSION["_CONF"]["conf_currency"]} ".printPrice($itemrealsellprice)." bajo precio de compra {$_SESSION["_CONF"]["conf_currency"]} ".printPrice($posdata[$y]["item_costprice_netto"])."</b><br>";
               if($posdata[$y]["item_invoice_note"] != "")
                  $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$y]["item_invoice_note"]))."</b>";
               if($overlibover != "")
               {  ?>
                  <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  if($itemselw == "480px")
                     $ovritemselw = "460px";
                  elseif($itemselw == "325px")
                     $ovritemselw = "305px";
               }
            }
            else
               $ovritemselw = "";
         }
         else
            $ovritemselw = "";
         ?>
         <select class="text" style="width:<?php if($ovritemselw != "") echo $ovritemselw; else echo $itemselw?>;<?php if($showmanual) echo "display:none" ?>"
         name="item_id_<?=$y?>" id="item_id_<?=$y?>"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$y]["item_id"]) echo "addSelStyle(this);" ?>"
         onmousedown="markfield(this,0)"
         onchange="setItemInfosSell('<?=$y?>', this.value);<?php
         if($showStorehouses)
            echo "updateItemStorehousesArr('{$y}', this.value);";
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
         <textarea class="text" name="item_desc_<?=$y?>" id="item_desc_<?=$y?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
         style="width:<?php if($ovritemselw != "") echo $ovritemselw; else echo $itemselw?>;height:58px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$y]["item_desc"]?></textarea>
         <input type="hidden" name="manual_pos_<?=$y?>" id="manual_pos_<?=$y?>"
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
               <select class="text" style="width:110px;<?if((int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>"
               name="item_stid_<?=$y?>" id="item_stid_<?=$y?>"
               onblur="markfield(this,1);removeSelStyle(this);"
               onfocus="addSelStyle(this);"
               onmousedown="markfield(this,0)">
                  <?php
                  if((int)$posdata[$y]["item_id"])
                  {
                     if($posdata[$y]["item_type"] == "item")
                        $itemsts = getItemStorehouses($CON, $headdata["note_shop_id"], $posdata[$y]["item_id"], $posdata[$y]["item_type"]);
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
                              $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["note_shop_id"], $itemstid, $posdata[$y]["item_id"], $posdata[$y]["item_type"], true);
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
               <div style="<?if(!(int)$posdata[$y]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$y?>">
               <?php
               $btnicon = "arrow";
               $btnname = "Lotes";
               $btnclas = "postnav";

               if(posHasItemChargeData($CON, $_REQUEST["id"], "invoicesnotessell", $y) > 0)
               {
                  $btnicon = "tick-circle-frame";
                  $btnclas = "postnav_save";

                  $charge_amount = posItemChargeDataAmount($CON, $_REQUEST["id"], "invoicesnotessell", $y);
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
               
               printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.php?trantype=invoicesnotessell&tranid={$_REQUEST["id"]}&tranpos={$y}&itemdata=' +escape($('#item_id_{$y}').val()), 'iframe', 650, 400, 'auto')", "", $btnicon)
               ?>
               <textarea id="item_charges_data_<?=$y?>" name="item_charges_data_<?=$y?>" style="display:none"></textarea>
               </div>
               <?php
            }
            ?>
         </td>
         <?php
      }
      else
         $_FIELDIGNORES["item_stid_{$y}"] = 1;
      ?>
      <td class="content_row" align="right" valign="top">
         <?php
         if(!$hasprcsellperm)
            $dscrdlo = " readonly ";
         else
            $dscrdlo = $rdlo;
         ?>
         <nobr>
         <input type="text" class="text"
         style="<?if((int)$headdata["note_isinvcbrutto"]) echo "display:none"?>;width:65px;text-align:right" <?=$dscrdlo?>
         name="item_sellprice_netto_<?=$y?>" id="item_sellprice_netto_<?=$y?>" autocomplete="off"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_netto"])?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </nobr>
         <nobr>
         <input type="text" class="text" <?=$dscrdlo?> autocomplete="off"
         style="<?if(!(int)$headdata["note_isinvcbrutto"]) echo "display:none"?>;width:65px;text-align:right"
         name="item_sellprice_brutto_<?=$y?>" id="item_sellprice_brutto_<?=$y?>"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_brutto"])?>">
         </nobr>
      </td>
      <td class="content_row" align="right" valign="top" <?if($_HIDETAXES) echo 'style="display:none"'?>>
         <?php
         if(!$hasprcsellperm)
            $dscrdlo = " readonly ";
         else
            $dscrdlo = $rdlo;
         ?>
         <input type="text" class="text" style="width:38px;text-align:right" <?=$dscrdlo?> autocomplete="off"
         name="item_sellprice_taxes_perc_<?=$y?>" id="item_sellprice_taxes_perc_<?=$y?>"
         value="<?php
         if((int)$posdata[$y]["item_id"])
            echo printPrice($posdata[$y]["item_sellprice_taxes_perc"],2);
         elseif((int)$headdata["note_taxes"])
            echo printPrice($_SESSION["_CONF"]["conf_taxes"],2);
         else
            echo "0";
         ?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
      </td>
      <td class="content_row" valign="top" align="right" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
            <?php
            if((int)$posdata[$y]["item_id"])
            {
               $ges_line = $posdata[$y]["item_sellprice_netto"] * $posdata[$y]["item_amount"];
               ?>
               <input type="text" class="text" readonly tabindex="-1"
               style="width:65px;text-align:right;"
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
               <input name="item_discount_<?=$y?>" type="text" tabindex="-1" class="text" style="width:26px;text-align:center;<?php
               if($posdata[$y]["item_discount"] > 0.00) echo "background-color:#E1FFD6"; else echo "background-color:#FFD6D8";?>"
               value="<?php if($posdata[$y]["item_discount"] > 0.00) echo printPrice($posdata[$y]["item_discount"],2);?>" <?=$dscrdlo?>>
               
               <select class="text" style="width:35px" name="item_discount_type_<?=$y?>" tabindex="-1"
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
               <input type="checkbox" class="checkbox" name="item_pcat_dsc_act_<?=$y?>" value="1" tabindex="-1"
               <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$y]["item_pcat_dsc_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
               <?php
               $isfirst = false;
               for($zz = 1; $zz <= 4; $zz++)
               {  ?>
                  <input type="text" tabindex="-1" class="text" name="item_pcat_dsc_<?=$y?>_<?=$zz?>" style="width:25px;text-align:center;<?php
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
               <input type="checkbox" class="checkbox" name="item_vol_act_<?=$y?>" value="1" tabindex="-1"
               <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$y]["item_vol_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
               
               <input name="item_vol_dsc_<?=$y?>" type="text" tabindex="-1" class="text" style="width:30px;text-align:center;<?php
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
               <input type="checkbox" class="checkbox" name="item_value_act_<?=$y?>" value="1" tabindex="-1"
               <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
               <?php if((int)$posdata[$y]["item_value_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>

               <input name="item_value_dsc_<?=$y?>" type="text" tabindex="-1" class="text" style="width:26px;text-align:center;<?php
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
         style="<?if((int)$headdata["note_isinvcbrutto"]) echo "display:none"?>;width:65px;text-align:right;background-color:<?if((int)$posdata[$y]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         name="item_sellprice_netto_dsc_<?=$y?>" id="item_sellprice_netto_dsc_<?=$y?>"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_netto_dsc"])?>">
         </nobr>
         <nobr>
         <input type="text" class="text" readonly tabindex="-1"
         style="<?if(!(int)$headdata["note_isinvcbrutto"]) echo "display:none"?>;width:65px;text-align:right;background-color:<?if((int)$posdata[$y]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         name="item_sellprice_brutto_dsc_<?=$y?>" id="item_sellprice_brutto_dsc_<?=$y?>"
         value="<?php if((int)$posdata[$y]["item_id"]) echo printPrice($posdata[$y]["item_sellprice_brutto_dsc"])?>">
         </nobr>
      </td>
      <td class="content_row" valign="top">
         <nobr>
         <select class="text" name="item_invc_docnumber_<?=$y?>" id="item_invc_docnumber_<?=$y?>"
         style="width:85px;background-color:<?if((int)$posdata[$y]["item_invc_id"]) echo "#E1FFD6"?>"
         onblur="markfield(this,1);removeSelStyle(this);"
         onfocus="addSelStyle(this);"
         onmousedown="markfield(this,0)">
            <?php
            foreach($note_invcnumbers AS $note_invcnumber)
            {
               $dspnote_invcnumber = explode("-",$note_invcnumber);
               $dspnote_invcnumber = $dspnote_invcnumber[0];
               ?>
               <option value="<?=$note_invcnumber?>" <?php if($note_invcnumber == $posdata[$y]["item_invc_docnumber"]) echo "selected"?>>
                  <?=$dspnote_invcnumber?>
               </option>
               <?php
            }
            ?>
         </select>
         </nobr>
      </td>
   </tr>
   <?php
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
               value="<?=printPrice($headdata["note_total_netto"] - $headdata["note_weightprice_netto"])?>">
            </td>
         </tr>
         <!--
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row">
               CONDUCCIÓN | SUGERENCIA: <b class="msg_save_ok"><?=printPrice($ges_weight, 2)?> Kg = $ <?=printPrice($ges_weight_price)?></b>
            </td>
            <td class="content_row" align="right">
               <?php
               if($headdata["dlv_status"] < 2)
               {  ?>
                  <input type="button" class="button" value="&gt;&gt;" style="width:25px"
                  onclick="document.getElementById('dlv_weightprice_netto').value='<?=printPrice($ges_weight_price)?>'">
                  <?php
               }
               ?>
            </td>
            <td class="content_row" align="right">
               <nobr>

               <input type="text" class="text" style="width:120px;text-align:right" <?=$rdlo?>
               name="dlv_weightprice_netto" id="dlv_weightprice_netto"
               value="<?=printPrice($headdata["note_weightprice_netto"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </nobr>
            </td>
         </tr>
         -->
         <?php
         if($headdata["note_total_taxes"] > 0.00)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_totals content_rowl" colspan="2">NETO</td>
               <td class="content_row_totals content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["note_total_netto"])?>">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row content_rowl" colspan="2">IVA</td>
               <td class="content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["note_total_taxes"])?>">
               </td>
            </tr>
            <?php
         }
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_rowl" colspan="2">TOTAL</td>
            <td class="content_row_totals content_row" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
               value="<?=printPrice($headdata["note_total_brutto"])?>">
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
         if($headdata["note_status"] < 2 && $_REQUEST["showDiscounts"] == "1" && $hasprcsellperm)
         {  ?>
            <?=Nifty_printH("box1", "535")?>
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
   $ftablewidth = 1130;
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
   <td align="right" width="140" style="padding-right:5px">
      <?php
      printButton("Imprimir", "postnav", "index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&id={$_REQUEST["id"]}&printpdf=1", "", "document-pdf");
      ?>
   </td>
   <?php
   if($headdata["note_status"] == 2 && !$_BLOCKOPEN_CHARGE && !(int)$_INVCCFG["company_invc_mode"])
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
   if(($headdata["note_status"] == 2 && !(int)$_INVCCFG["company_invc_mode"]) ||
      ($headdata["note_status"] == 2 && (int)$_INVCCFG["company_invc_mode"] && (int)$headdata["note_sgntr_end"] && !(int)$headdata["note_itf_trndat"]))
   {  ?>
      <td align="right" width="170">
         <?php
         if($hasdocopeperm)
            printButton("Anular Nota", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.note_status.value='1';document.form_shppos.cancelDoc.value='1';submitForm(document.form_shppos);}", "cross-circle-frame");
         else
            printButton("Anular Nota", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos&sStatus=1&cancelDoc=1', 'iframe', 450, 160, 'no')", "cross-circle-frame");
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
      if($headdata["note_invcnumber"] != "" && !$_BLOCKFIN_CHARGE && $hasItems)
      {  ?>
         <td align="right" width="130">
            <?php
            printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.note_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   /*
   if($headdata["note_status"] == 4)
   {  ?>
      <td align="right" width="170">
         <?php
         if($hasdocopeperm)
            printButton("Eliminar Anulación", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.note_status.value='1';document.form_shppos.cancelDoc.value='2';submitForm(document.form_shppos);}", "cross-circle-frame");
         else
            printButton("Eliminar Anulación", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos&sStatus=1&cancelDoc=2', 'iframe', 450, 160, 'no')", "cross-circle-frame");
         ?>
      </td>
      <?php
   }
   */
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
if($_REQUEST["printpdf"])
  $pdffile = doc_createInvoicesSellNotes($CON, $_REQUEST["id"]);

if($pdffile != "")
{
   $substr = str_replace(" ", "-", getInvoiceBuyNoteType($headdata["note_type"]));
   $doctitle = "{$substr}-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}

if((int)$_REQUEST["printsiidoc1"] || (int)$_REQUEST["printsiidoc2"])
{
   if((int)$_REQUEST["printsiidoc1"])
   {
      $doctitle   = "Nota-{$headdata["note_docnumber"]}.pdf";
      $docfile    = $headdata["note_sgntr_doc1"];
   }
   else
   {
      $doctitle   = "Cedible-{$headdata["note_docnumber"]}.pdf";
      $docfile    = $headdata["note_sgntr_doc2"];
   }
   $pdflink    = "./libs/modules/structure/document_file.php?type=0&hash={$docfile}&name={$doctitle}&path=../../../docs.electrpdf/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}