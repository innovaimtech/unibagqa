<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------
// Consulta SQL

$sqlautoriza = "select * from user where id = {$_SESSION['user_id']}";
$factura_autoriza = $CON->select($sqlautoriza);
// Asegúrate de que $factura_autoriza[0]['user_autoriza_fact_perm'] exista antes de convertir
$user_autoriza_fact_perm = isset($factura_autoriza[0]['user_autoriza_fact_perm'])
                           ? (int)$factura_autoriza[0]['user_autoriza_fact_perm']
                           : 0;

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

   $sql = " update invoices_sell
            set
            invc_sgntr_doc1 = '',
            invc_sgntr_doc2 = ''
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $sql = "select * from invoices_sell where id = {$_REQUEST["id"]}";
   $factura = $CON->select($sql);
   $factura = $factura[0];

   error_reporting(0);

   $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'ImprimePDF'";
   $url = $CON->select($sql);
   $api_url = $url[0]['url'];

   $filedir = "../../../docs.electrpdf/";
   $filename1 = "33_{$factura["id"]}_{$comp_rut}_{$file_hash}_doc1.pdf";
   $filename2 = "33_{$factura["id"]}_{$comp_rut}_{$file_hash}_doc2.pdf";
 
   $parametros = array('documentType'   => 'FVAELECT',
                       'folio'          => $factura["invc_docnumber"],
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

   /* Factura Cedible */
   
   $parametros = array(
                        'documentType'   => 'FVAELECT',
                        'folio'          => $factura["invc_docnumber"],
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

$_HIDETAXES = true;
$requestaprobed = true;

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
   if($_REQUEST["invc_type"] == 2 && $_REQUEST["dlv_order_id"] > 0)
   {
      $sql = " select req_cust_id
               from orders
               where
               id = {$_REQUEST["dlv_order_id"]}";
      $custid = $CON->select($sql);
      $_REQUEST["cust_id_0"] = (int)$custid[0]["req_cust_id"];

      if((int)$_REQUEST["invc_order_otherclient"] && (int)$_REQUEST["cust_id_1"])
      {
         $_REQUEST["cust_id_0"] = (int)$_REQUEST["cust_id_1"];
      }
   }
   if($_REQUEST["invc_type"] == 1 && $_REQUEST["dlv_id"] > 0)
   {
      $sql = " select dlv_cust_id
               from orders_delivery
               where
               id = {$_REQUEST["dlv_id"]}";
      $custid = $CON->select($sql);
      $_REQUEST["cust_id_0"] = (int)$custid[0]["dlv_cust_id"];
   }

   //----------------------------------------------------------------------------------
   $invc_number         = createTransactionNumber($CON, $_REQUEST["company_id"], "invoicesell");
   $invc_date           = mktime(0, 0, 0, date('m'), date('d'), date('Y'));
   $invc_delivery_date  = $invc_date;
   $invc_dlv_docnum     = $invc_number;
   $invc_stockchange    = 0;
   $mensajestocknegativo="";

   //----------------------------------------------------------------------------------
   if($_REQUEST["invc_type"] == 2 || $_REQUEST["invc_type"] == 3)
      $invc_stockchange = 1;

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
   $sql = " insert into invoices_sell
            (invc_number, invc_cust_id, invc_company_id, invc_shop_id, invc_date, invc_receipt_date,
             invc_taxes, invc_type, invc_stockchange, invc_userid_seller, invc_userid_cashing,
             invc_paymentid, invc_transportid, invc_delivery_date, invc_dlv_docnum, invc_estpay_date,
             invc_docnumber, invc_crtdat, invc_crtusr, invc_dlv_addtxt, invc_isinvcbrutto)
            VALUES
            ('{$invc_number}', {$_REQUEST["cust_id_0"]}, {$_REQUEST["company_id"]},
              {$_REQUEST["shop_id"]}, {$invc_date}, {$invc_date}, {$_REQUEST["invc_taxes"]}, {$_REQUEST["invc_type"]},
              {$invc_stockchange}, {$invc_userid_seller}, {$invc_userid_cashing}, {$invc_paymentid},
              {$invc_transportid}, {$invc_delivery_date}, '{$invc_dlv_docnum}', {$invc_estpay_date},
              '{$invc_docnumber}', {$currtme}, {$_SESSION["user_id"]}, {$_REQUEST["invc_dlv_addtxt"]},
              {$_REQUEST["invc_isinvcbrutto"]})";
   $res = $CON->no_result($sql);
   if($res)
   {
      $sql = " select MAX(id) 'thisid'
               from invoices_sell
               where
               invc_crtusr = {$_SESSION["user_id"]}";
      $invoice = $CON->select($sql);
      $_REQUEST["id"]   = $invoice[0]["thisid"];

      //----------------------------------------------------------------------------------
      if($_REQUEST["invc_type"] == 3)
      {
         $sql = " insert into invoices_sell_parts
                  (part_invc_id, part_crtdat, part_crtusr, part_dlv_id, part_req_id)
                  VALUES
                  ({$_REQUEST["id"]}, {$currtme}, {$_SESSION["user_id"]}, 0, 0)";
         $CON->no_result($sql);
      }
      elseif($_REQUEST["invc_type"] == 2 && $_REQUEST["dlv_order_id"] > 0)
         invoiceSellAddOrder($CON, $_REQUEST["id"], $_REQUEST["dlv_order_id"], $_REQUEST["invc_order_noitemload"]);
      elseif($_REQUEST["invc_type"] == 1 && $_REQUEST["dlv_id"] > 0)
      {
         invoiceSellAddDelivery($CON, $_REQUEST["id"], $_REQUEST["dlv_id"]);
         if((int)$_REQUEST["invc_dlv_addtxt"])
            invoiceSellConvertShpToDesc($CON, $_REQUEST["id"], $_REQUEST["dlv_id"]);
      }
      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=751&exec=edit&id=<?=$_REQUEST["id"]?>'
      </script>
      <?php
   }
   
   $savemsg = getSaveMessage($res);
}


//----------------------------------------------------------------------------------
if($_REQUEST["addshp"] != "")
{
   invoiceSellAddDelivery($CON, $_REQUEST["id"], $_REQUEST["addshp"]);

   $sql = " select *
            from invoices_sell
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];
   
   if((int)$headdata["invc_dlv_addtxt"])
      invoiceSellConvertShpToDesc($CON, $_REQUEST["id"], $_REQUEST["addshp"]);
}
if($_REQUEST["addsord"] != "")
   invoiceSellAddOrder($CON, $_REQUEST["id"], $_REQUEST["addsord"]);
//

//----------------------------------------------------------------------------------
$sql = " select t1.invc_aprobblocked from invoices_sell t1 where t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

$mensaje_stock = '';
$sql = "select distinct a.item_id
                      , a.item_sellprice_netto
                      , a.item_st_id
                      , a.item_amount
                      , c.item_controla_stock
                      , sum(b.iss_inventory) as iss_inventory 
         from invoices_sell_parts_items a
            inner join invoices_sell_parts xt1 on xt1.part_invc_id = a.invc_id
	         inner join item_shops_storehouses b on a.item_id = b.item_id and b.st_id = a.item_st_id and iss_order_id = part_req_id
            inner join invoices_sell_parts xt1 on xt1.part_invc_id = a.invc_id
      where a.invc_id = {$_REQUEST["id"]} 
      group by a.item_id, a.item_sellprice_netto, a.item_st_id,a.item_amount, c.item_controla_stock";

$revisa_stock = $CON->select($sql);
if((int)$revisa_stock[0]['item_id'] >= 1)
{  
   foreach($revisa_stock AS $rstock)
   {
      if($rstock["iss_inventory"] < $rstock["item_amount"] && $rstock["item_controla_stock"] == 1 && $headdata["invc_aprobblocked"] != 2)
         // $mensaje_stock = "Sin Stock";
         $mensaje_stock = $sql;
   }
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "save")
{
   $currtme = time();
 
   if( $mensaje_stock != '')
      $_REQUEST["invc_status"] = 1;

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell
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
   if($_REQUEST["invc_oc_number"] != "" && trim($_REQUEST["invc_oc_dat"]) == "")
      $_REQUEST["invc_oc_dat"] = date("d.m.Y");
   $_REQUEST["invc_oc_dat"]            = trim(addslashes($_REQUEST["invc_oc_dat"]));
   $_REQUEST["invc_oc_dat"]            = explode(".", $_REQUEST["invc_oc_dat"]);
   $_REQUEST["invc_oc_dat"]            = (int)mktime(date('H'), date('i'), date('s'), $_REQUEST["invc_oc_dat"][1], $_REQUEST["invc_oc_dat"][0], $_REQUEST["invc_oc_dat"][2]);
   $_REQUEST["invc_discount_perc"]      = getPrice($_REQUEST["invc_discount_perc"],2);
   $_REQUEST["invc_discount_amt"]       = getPrice($_REQUEST["invc_discount_amt"]);

   $_REQUEST["invc_hes_number"]        = trim(addslashes($_REQUEST["invc_hes_number"]));
   if($_REQUEST["invc_hes_number"] != "" && trim($_REQUEST["invc_hes_date"]) == "")
      $_REQUEST["invc_hes_date"] = date("d.m.Y");
   $_REQUEST["invc_hes_date"]          = trim(addslashes($_REQUEST["invc_hes_date"]));
   $_REQUEST["invc_hes_date"]          = explode(".", $_REQUEST["invc_hes_date"]);
   $_REQUEST["invc_hes_date"]          = (int)mktime(15, 0, 0, $_REQUEST["invc_hes_date"][1], $_REQUEST["invc_hes_date"][0], $_REQUEST["invc_hes_date"][2]);

   $_REQUEST["invc_guia_number"]       = trim(addslashes($_REQUEST["invc_guia_number"]));
   if($_REQUEST["invc_guia_number"] != "" && trim($_REQUEST["invc_guia_dat"]) == "")
      $_REQUEST["invc_guia_dat"] = date("d.m.Y");
   $_REQUEST["invc_guia_dat"]          = trim(addslashes($_REQUEST["invc_guia_dat"]));
   $_REQUEST["invc_guia_dat"]          = explode(".", $_REQUEST["invc_guia_dat"]);
   $_REQUEST["invc_guia_dat"]          = (int)mktime(15, 0, 0, $_REQUEST["invc_guia_dat"][1], $_REQUEST["invc_guia_dat"][0], $_REQUEST["invc_guia_dat"][2]);

   $_REQUEST["invc_vehiculo_id"]         = (int)$_REQUEST["invc_vehiculo_id"];
   $_REQUEST["invc_chofer_id"]           = (int)$_REQUEST["invc_chofer_id"];
   $_REQUEST["invc_rango_id"]            = (int)$_REQUEST["invc_rango_id"];
   $_REQUEST["invc_comuna_origen"]       = (int)$_REQUEST["invc_comuna_origen"];
   $_REQUEST["invc_comuna_destino"]      = (int)$_REQUEST["invc_comuna_destino"];
   $_REQUEST["invc_direccion_origen"]    = trim(addslashes($_REQUEST["invc_direccion_origen"]));
   $_REQUEST["invc_direccion_destino"]   = trim(addslashes($_REQUEST["invc_direccion_destino"]));
   
   if((int)$_REQUEST["invc_paymentid"] != (int)$headdata["invc_paymentid"])
      $itemfullupdate = true;
   else
      $itemfullupdate = false;

   //----------------------------------------------------------------------------------
   if($_REQUEST["delinvcpartid"] != "")
   {
      $sql = " select part_dlv_id
               from invoices_sell_parts
               where
               id = {$_REQUEST["delinvcpartid"]}";
      $part_dlv_id = $CON->select($sql);
      $part_dlv_id = (int)$part_dlv_id[0]["part_dlv_id"];

      //----------------------------------------------------------------------------------
      if($part_dlv_id)
      {
         $sql = " select dlv_weightprice_netto
                  from orders_delivery
                  where
                  id = {$part_dlv_id}";
         $dlv_weightprice_netto = $CON->select($sql);
         $dlv_weightprice_netto = (float)$dlv_weightprice_netto[0]["dlv_weightprice_netto"];

         $sql = " update invoices_sell
                  set
                  invc_weightprice_netto = invc_weightprice_netto - {$dlv_weightprice_netto}
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " delete from invoices_sell_parts
               where
               id = {$_REQUEST["delinvcpartid"]}";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " delete from invoices_sell_parts_items
               where
               invc_id  = {$_REQUEST["id"]} and
               part_id  = {$_REQUEST["delinvcpartid"]}";
      $CON->no_result($sql);

      //----------------------------------------------------------------------------------
      $sql = " delete from tran_charges_used
               where
               tran_id        = {$_REQUEST["id"]} and
               tran_partid    = {$_REQUEST["delinvcpartid"]} and
               tran_type      = 'invoicesell'";
      $CON->no_result($sql);
   }

   //----------------------------------------------------------------------------------
   $sql = " select *
            from invoices_sell
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
            from invoices_sell
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
   $sql = " update invoices_sell
            set
            invc_status              = {$_REQUEST["invc_status"]},
            invc_desc                = '{$_REQUEST["invc_desc"]}',
            invc_desc_intern         = '{$_REQUEST["invc_desc_intern"]}',
            invc_docnumber           = '{$_REQUEST["invc_docnumber"]}',
            invc_date                = {$_REQUEST["invc_date"]},
            invc_paymentid           = {$_REQUEST["invc_paymentid"]},
            invc_payed               = {$_REQUEST["invc_payed"]},
            invc_stockchange         = {$_REQUEST["invc_stockchange"]},
            invc_estpay_date         = {$_REQUEST["invc_estpay_date"]},
            invc_receipt_date        = {$_REQUEST["invc_receipt_date"]},
            invc_userid_seller       = {$_REQUEST["invc_userid_seller"]},
            invc_userid_cashing      = {$_REQUEST["invc_userid_cashing"]},
            invc_transportid         = {$_REQUEST["invc_transportid"]},
            invc_cust_delivid        = {$_REQUEST["invc_cust_delivid"]},
            invc_delivery_date       = {$_REQUEST["invc_delivery_date"]},
            invc_dlv_docnum          = '{$_REQUEST["invc_dlv_docnum"]}',
            invc_oc_number           = '{$_REQUEST["invc_oc_number"]}',
            invc_oc_dat              = {$_REQUEST["invc_oc_dat"]},
            invc_discount_perc       = {$_REQUEST["invc_discount_perc"]},
            invc_discount_amt        = {$_REQUEST["invc_discount_amt"]},
            invc_hes_number          = '{$_REQUEST["invc_hes_number"]}',
            invc_hes_date            = {$_REQUEST["invc_hes_date"]},
            invc_guia_number         = '{$_REQUEST["invc_guia_number"]}',
            invc_guia_dat            = {$_REQUEST["invc_guia_dat"]},
            invc_vehiculo_id         = {$_REQUEST["invc_vehiculo_id"]},
            invc_chofer_id           = {$_REQUEST["invc_chofer_id"]},
            invc_rango_id            = {$_REQUEST["invc_rango_id"]},
            invc_comuna_origen       = {$_REQUEST["invc_comuna_origen"]},
            invc_comuna_destino      = {$_REQUEST["invc_comuna_destino"]},
            invc_direccion_origen    = '{$_REQUEST["invc_direccion_origen"]}',
            invc_direccion_destino   = '{$_REQUEST["invc_direccion_destino"]}',
            invc_bultos              = {$_REQUEST["invc_bultos"]},
            invc_updusr              = {$_SESSION["user_id"]},
            invc_upddat              = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);
   $savemsg = getSaveMessage($res);

   clearRelItems($CON, $_REQUEST["id"], "invoicesell");

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

         $_REQUEST["item_stid_{$idx}"]       = (int)$_REQUEST["item_stid_{$idx}"];
         $_REQUEST["item_amount_{$idx}"]     = getPrice($_REQUEST["item_amount_{$idx}"],2);

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
                     /*
                     $sql_sellnetto = getPrice($_REQUEST["item_sellprice_netto_{$idx}"]);
                     $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto / 100 * $sql_taxesperc);
                     $sql_sellprice = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto + $sql_taxes);
                     */
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
                  $sql = " update invoices_sell_parts_items
                           set
                           item_amount                = {$_REQUEST["item_amount_{$idx}"]},
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

                  recalcOrderItem($CON, $_REQUEST["id"], $existing_id, $poscounter, $itemfullupdate, "INVOICE", $idxpartid);

                  renameItemChargePosUsed($CON, $_REQUEST["id"], "invoicesell", $existing_pos, $poscounter, $idxpartid);

                  updateItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesell", $existing_id, $sql_type, $poscounter,
                                          $invoicetemp[0]["invc_company_id"], $invoicetemp[0]["invc_shop_id"],
                                          $_REQUEST["item_charges_data_{$idx}"], $idxpartid);

                  //----------------------------------------------------------------------------------
                  $_TABLENAME    = "invoices_sell_parts_items";
                  $_TABLENAMEHD  = "invoices_sell";
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
                           from invoices_sell_parts
                           where
                           id           = {$idxpartid} and
                           part_invc_id = {$_REQUEST["id"]}";
                  $parttemp = $CON->select($sql);
                  $part_req_id = $parttemp[0]["part_req_id"];
                  $part_dlv_id = $parttemp[0]["part_dlv_id"];

                  $item_order_pos = -1;
                  $item_dlv_pos   = -1;
                  if($part_req_id > 0)
                  {
                     $temppos = getOrderPos($CON, $part_req_id);
                     foreach($temppos AS $temprow)
                     {
                        if($temprow["item_id"] == $sql_id && $temprow["item_type"] == $sql_type)
                           $item_order_pos = $temprow["item_pos"];
                     }
                  }
                  if($part_dlv_id > 0)
                  {
                     $temppos = getOrderDeliveryPos($CON, $part_dlv_id);
                     foreach($temppos AS $temprow)
                     {
                        if($temprow["item_id"] == $sql_id && $temprow["item_type"] == $sql_type)
                           $item_dlv_pos = $temprow["item_pos"];
                     }
                  }

                  //----------------------------------------------------------------------------------
                  $sql = " insert into invoices_sell_parts_items
                           (invc_id, part_id, item_id, item_pos, item_amount, item_type, item_sellprice_brutto,
                            item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes,
                            item_st_id, item_desc, item_charges_act, item_order_pos, item_dlv_pos)
                           VALUES
                           ({$_REQUEST["id"]}, {$idxpartid}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, '{$sql_type}',
                            {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes},
                            {$_REQUEST["item_stid_{$idx}"]}, '{$_REQUEST["item_desc_{$idx}"]}', {$sql_charges_act},
                            {$item_order_pos}, {$item_dlv_pos})";
                  $CON->no_result($sql);

                  recalcOrderItem($CON, $_REQUEST["id"], $sql_id, $poscounter, true, "INVOICE", $idxpartid);

                  updateItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesell", $sql_id, $sql_type, $poscounter,
                                          $invoicetemp[0]["invc_company_id"], $invoicetemp[0]["invc_shop_id"],
                                          $_REQUEST["item_charges_data_{$idx}"], $idxpartid);
               }

               $poscounter++;
            }
            elseif($existing_id)
            {
               $sql = " delete from invoices_sell_parts_items
                        where
                        invc_id  = {$_REQUEST["id"]} and
                        part_id  = {$idxpartid} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);

               clearItemChargeDataUsed($CON, $_REQUEST["id"], "invoicesell", $existing_pos, $idxpartid);
            }
         }
         else
         {
            $idx           = substr($reqkey, strrpos($reqkey, "_") +1);
            $itemvalues    = explode("#", $_REQUEST["item_id_{$idx}"]);
            $sql_id        = (int)$itemvalues[0];
            $sql_type      = $itemvalues[1];
            if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["tran_amount_{$idx}"] > 0 && $sql_type == "item")
            {
               addRelItem($CON, $_REQUEST["id"], "invoicesell", $sql_id, $_REQUEST["tran_amount_{$idx}"], $_REQUEST["item_stid_{$idx}"], $idx);
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " delete from tran_references
            where
            tran_id     = {$_REQUEST["id"]} and
            tran_type   = 'invc'";
   $CON->no_result($sql);

   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "ref_type_") !== false && strpos($reqkey, "ref_type_") == 0)
      {
         $idx           = substr($reqkey, strrpos($reqkey, "_") +1);
         $ref_type      = trim($_REQUEST["ref_type_{$idx}"]);
         $ref_number    = trim(addslashes($_REQUEST["ref_number_{$idx}"]));
         $ref_date      = trim(addslashes($_REQUEST["ref_date_{$idx}"]));

         if($ref_type != "")
         {
            $sql = " insert into tran_references
                     (ref_type, ref_number, ref_date, tran_id, tran_type)
                     VALUES
                     ('{$ref_type}', '{$ref_number}', '{$ref_date}', {$_REQUEST["id"]}, 'invc')";
            $CON->no_result($sql);
         }
      }
   }

   $_SESSION["invcsell_RELOADCALC"]++;
   $lastreloadcalc = $_SESSION["invcsell_RELOADCALC"];
   if($_SESSION["invcsell_RELOADCALC"] > 1)
      $_SESSION["invcsell_RELOADCALC"] = 0;

   if($final)
   {
          $sql = " update invoices_sell
                    set
                    invc_aprobblocked = 0
                    where
                    id = {$_REQUEST["id"]}";
           $CON->no_result($sql);
   }
   //----------------------------------------------------------------------------------
   if($final || (int)$_REQUEST["printpdf"])
      doc_createSupplierOrder($CON, $_REQUEST["id"]);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "send")
{
      $currtme = time();
      
      $sql = " update invoices_sell
               set
               invc_updusr = {$_SESSION["user_id"]},
               invc_upddat = {$currtme},
               invc_status = 3
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      $sql = " select *
               from invoices_sell
               where
               id = {$_REQUEST["id"]}";
      $attachfile = $CON->select($sql);
      
      $filedir = "./docs.invcsell/";
      $filename   = "{$attachfile[0]["id"]}.{$attachfile[0]["invc_hash"]}";
      $fileext    = ".pdf";
      $pdffile    = "{$filedir}{$filename}{$fileext}";
      
      $temp["NAME"]  = "{$attachfile[0]["invc_number"]}{$fileext}";
      $temp["FILE"]  = $pdffile;

      
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
}

//----------------------------------------------------------------------------------
if((int)$_REQUEST["aprobsend"] && $_SESSION["invcsell_RELOADCALC"] == 0)
{
   generateInvoiceSellAprobMail($CON, $_REQUEST["id"],$_REQUEST['mensaje_stock']);
   $_REQUEST['mensaje_stock']="";
   $sql = " update invoices_sell
            set
            invc_aprobblocked = 1
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name,
                t2.cust_notes,  t7.pay_title, t8.trans_name, t2.cust_notes,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
            from invoices_sell t1
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

if((int)$headdata["invc_aprobblocked"] == 1 && (int)$_SESSION["user_type"] != 1)
{  ?>
   <script language="JavaScript">
      alert("La factura se encuentra a la espera de aprobacion.");
      location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>';
   </script>
   <?php
   exit;
}
//----------------------------------------------------------------------------------
$posdata    = Array();
$invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"]);
   for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
      $posdata[] = $partposdata[$y];
}
   
//----------------------------------------------------------------------------------
$_REQUEST["dlv_weightprice_netto"] = getPrice($_REQUEST["dlv_weightprice_netto"]);
   
$sql = " update invoices_sell
            set
            invc_weightprice_netto = {$_REQUEST["dlv_weightprice_netto"]}
            where
            id = {$_REQUEST["id"]}";
$CON->no_result($sql);


   //----------------------------------------------------------------------------------
   if($headdata["invc_status"] == 1)
      recalcOrder($CON, $_REQUEST["id"], "INVOICE");

   //----------------------------------------------------------------------------------
   if($final)
   {
      $sql = " update invoices_sell
               set
               invc_status    = 2,
               invc_docnumber = 'PENDIENTE_SII'
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      bookInvoiceSell($CON, $_REQUEST["id"]);
   }

   //----------------------------------------------------------------------------------
   if($revert)
   {
      revertInvoiceSell($CON, $_REQUEST["id"]);

      if($_REQUEST["cancelDoc"] == "1")
      {
         $sql = " update invoices_sell
                  set
                  invc_status = 4
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }
      
      if($_REQUEST["cancelDoc"] == "2")
      {
         $sql = " update invoices_sell
                  set
                  invc_status = 1
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " select id
               from invoices_sell
               where
               invc_parentid = {$_REQUEST["id"]}";
      $invcnoteid = $CON->select($sql);
      $invcnoteid = (int)$invcnoteid[0]["id"];

      if($invcnoteid > 0)
      {
         $sql = " delete from invoices_sell where id = {$invcnoteid}";
         $CON->no_result($sql);
         $sql = " delete from invoices_sell_parts where part_invc_id = {$invcnoteid}";
         $CON->no_result($sql);
         $sql = " delete from invoices_sell_parts_items where invc_id = {$invcnoteid}";
         $CON->no_result($sql);
      }

      //----------------------------------------------------------------------------------
      $sql = " update invoices_sell
               set
               invc_childid = 0
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
   }
// } --> este cierre me sale de más

//----------------------------------------------------------------------------------
$sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name,
                t2.cust_notes,  t7.pay_title, t8.trans_name, t2.cust_notes,
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
         from invoices_sell t1
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
if($_REQUEST["subexec"] == "send")
{
   $currtme = time();
   if($headdata["invc_sgntr_doc1"] != "")
   {
      $filedir    = "./docs.electrpdf/";
      $filename   = "{$headdata["invc_sgntr_doc1"]}";
      $xpdffile   = "{$filedir}{$filename}";
      
      $temp["NAME"]  = "Factura-{$headdata["invc_docnumber"]}.pdf";
      $temp["FILE"]  = $xpdffile;
      $attachfiles[0] = $temp;
   
      $_OVERWRITE_MAILS = Array();
      foreach($_REQUEST["xrecpt"] AS $xrecptrow)
      {
         $rarr = explode("###", $xrecptrow);
         $rtmp["NAME"] = $rarr[0];
         $rtmp["ADDR"] = $rarr[1];
         $_OVERWRITE_MAILS[] = $rtmp;
      }

      $_CCOADDR = Array();
      unset($temp);
      foreach($_REQUEST["xcco"] AS $xccouid)
      {
         $sql = " select *
                  from user
                  where
                  id = {$xccouid}";
         $ccouser = $CON->select($sql);
         $ccouser = $ccouser[0];

         $temp["NAME"] = $ccouser["user_firstname"]." ".$ccouser["user_lastname"];
         $temp["ADDR"] = $ccouser["user_mail"];
         $_CCOADDR[]   = $temp;
      }

      if(count($_OVERWRITE_MAILS))
      {
         $text    = '<html>
                     <head><style type="text/css">body{font-family:Arial;font-size:12px;}</style></head>
                     <body style="margin:10px" class="page">'.$_REQUEST["msg_body"].'</body></html>';

         //$_OVERWRITENAME = "Sistema Unibag";
         $sentmails = sendExternalMail($_REQUEST["msg_header"],
                                       $text,
                                       $_REQUEST["msg_toaddr"],
                                       $_REQUEST["msg_toname"],
                                       "",
                                       "",
                                       $attachfiles);
      }
      
      if($sentmails <= 0)
         $savemsg = getSaveMessage(false);
      else
         $savemsg = getSaveMessage(true);

      ?>
      <script language="JavaScript">
         location.href = 'index.php?mid=<?=$_REQUEST["mid"]?>';
      </script>
      <?php
   }
   else
   {  ?>
      <script language="JavaScript">
         alert("Documento tributario aun no disponible. Favor reintente...");
      </script>
      <?php
   }
}

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
if($headdata["invc_status"] >= 2)
{
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
            from customer_deliveryaddr t1
            LEFT OUTER JOIN country t2 ON t1.delivery_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.delivery_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.delivery_comunaid  = t4.id
            where
            t1.id = {$headdata["invc_cust_delivid"]}
            order by t1.id asc";
   $deliveryaddr = $CON->select($sql);
   $deliveryaddr = $deliveryaddr[0];
}
else
{
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
            from customer_deliveryaddr t1
            LEFT OUTER JOIN country t2 ON t1.delivery_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.delivery_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.delivery_comunaid  = t4.id
            where
            t1.cust_id = {$headdata["invc_cust_id"]} and
            t1.delivery_status = 1
            order by t1.id asc";
   $deliveryaddrs = $CON->select($sql);
}


//----------------------------------------------------------------------------------
// PRODUCTOS QUE FALTAN COMPLETAMENTE EN LA FACTURA DE LA NOTA
//----------------------------------------------------------------------------------
if($headdata["invc_status"] == 2)
{
   $sql = " select t3.*, t1.item_amount 'order_amount', t1.item_amount_shipped 'order_amount_shipped'
            from orders_items  t1
            INNER JOIN invoices_sell_parts t2            ON ( t1.req_id    = t2.part_req_id )
            LEFT OUTER JOIN invoices_sell_parts_items t3 ON ( t3.invc_id   = t2.part_invc_id and
                                                              t3.part_id   = t2.id and
                                                              t1.item_pos  = t3.item_order_pos and
                                                              t1.item_id   = t3.item_id and
                                                              t1.item_type = t3.item_type and
                                                              t3.item_type   IN ('item', 'itemlist'))
            where
            t2.part_invc_id = {$_REQUEST["id"]} and
            t1.item_amount_shipped_stop = 0";
   $checkorderdata = $CON->select($sql);
   for($x = 0; $x < count($checkorderdata) && $checkorderdata != false; $x++)
   {
      if(!(int)$checkorderdata[$x]["invc_id"] && !(int)$checkorderdata[$x]["part_id"] && !(int)$checkorderdata[$x]["item_id"])
         $showBTNDiff = true;
   }
}

//----------------------------------------------------------------------------------
   $sql = " SELECT *
            FROM company_shops
            WHERE shop_status > 0 
            AND shop_rel_custid = {$invoice[0]["invc_cust_id"]}
            AND shop_rel_suppid > 0
            ORDER BY shop_name";
   $genshops = $CON->select($sql);
//----------------------------------------------------------------------------------
$sellers     = getSellers($CON);
// $payments    = getPayments($CON, $headdata["invc_shop_id"]);

$sql = "select * from payments where pay_cod_contable != '' and pay_status > 0 order by pay_title";
$payments = $CON->select($sql);

$transports  = getTransports($CON);
$invcparts   = getInvoiceSellParts($CON, $_REQUEST["id"]);

//----------------------------------------------------------------------------------
$rowcount = 22;
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
   
//----------------------------------------------------------------------------------
if($headdata["invc_status"] >= 2)
{
   $rdlo       = " readonly ";
   $dabl       = " disabled ";
   $rowcount   = count($posdata);
}

//----------------------------------------------------------------------------------
$custselarr = Array();
   
$custname = str_replace(",","", str_replace("'","", str_replace('"',"", trim($headdata["cust_name"]))));
$custmail = str_replace(",","", str_replace("'","", str_replace('"',"", trim($customer["cust_email"]))));
if($custmail != "")
{
   $temp["NAME"] = $custname;
   $temp["MAIL"] = $custmail;

   array_push($custselarr, $temp);
}

$sql = " select t2.add_email, t2.add_firstname, t2.add_lastname
         from customer t1
         INNER JOIN customer_contacts t2 ON t1.id = t2.add_cust_id
         where
         t1.cust_status > 0 and
         t1.id = {$customer["id"]}
         order by t2.add_pos asc";
$suppcontacts = $CON->select($sql);

//----------------------------------------------------------------------------------
for($x = 0; $x < count($suppcontacts) && $suppcontacts != false; $x++)
{
   $custname = str_replace(",","", str_replace("'","", str_replace('"',"", trim($suppcontacts[$x]["add_firstname"]." ".$suppcontacts[$x]["add_lastname"]))));
   $custmail = str_replace(",","", str_replace("'","", str_replace('"',"", trim($suppcontacts[$x]["add_email"]))));

   if($custmail != "")
   {
      $temp["NAME"] = $custname;
      $temp["MAIL"] = $custmail;

      array_push($custselarr, $temp);
   }
}

$sql = " select id, user_firstname, user_lastname, user_mail
         from user
         where
         user_status > 0 and
         user_mail like '%@%'
         order by user_firstname, user_lastname";
$ccousers = $CON->select($sql);

//----------------------------------------------------------------------------------
if($_SESSION["xinvoicessell"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

//----------------------------------------------------------------------------------
$showStorehouses  = false;
$itemselw         = "480px";
if((int)$_REQUEST["showDiscounts"])
   $itemselw = "325px";
if((int)$headdata["invc_stockchange"])
{
   $showStorehouses  = true;
   $itemselw         = "375px";

   if((int)$_REQUEST["showDiscounts"])
      $itemselw = "325px";
      
}

//----------------------------------------------------------------------------------
$posdata    = Array();
$invcparts  = getInvoiceSellParts($CON, $_REQUEST["id"]);
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $partposdata = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $invcparts[$x]["id"]);
   for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
   {
      $posdata[] = $partposdata[$y];
      if((int)$partposdata[$y]["item_sell_withotheritems"])
         $showRelItems = true;
   }
}

$proms = getActivePromotions($CON, $headdata["invc_shop_id"]);

//----------------------------------------------------------------------------------
$_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["invc_company_id"]);

if($_REQUEST["id"] != "" && $headdata["invc_status"] == 1 && $_INVCCFG["company_invc_itf"] == "BCNCONS")
{
   if((int)$headdata["invc_taxes"])
      $invc_docnumber = (int)createSiiNumber($CON, $headdata["invc_company_id"], $headdata["invc_shop_id"], "itf_invc_tax", true);
   else
      $invc_docnumber = (int)createSiiNumber($CON, $headdata["invc_company_id"], $headdata["invc_shop_id"], "itf_invc_ext", true);

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

if($headdata["invc_status"] != 2)
{
   if($headdata["invc_aprobblocked"] != 2)
   {
      if((int)$requestaprobed == 1 && (int)$user_autoriza_fact_perm==0 && $headdata["invc_aprobblocked"] == 0 && $mensaje_stock != "" )
      {
         $mensaje  = $mensaje_stock; // 'La cantidad seleccionada supera el stock disponible. Por favor, ajusta la cantidad o solicita aprobación para continuar con el pedido. : ';
         ?>
            <div style="color:red;text-align:center;padding:10px;width:950px;border:3px solid red;font-family:Arial;font-size:12px">
               <u><b>ADVERTENCIA:</b></u> <?=$mensaje?>
            </div>
            <br>
         <?php
      }
   }
   else
   {
      if((int)$requestaprobed && (int)$user_autoriza_fact_perm==0 && $_REQUEST['mensaje_stock'] != "" && $headdata["invc_aprobblocked"] == 2)
      {
         $headdata["invc_user_aprobado"];
         $sql = "select * from user where id = {$headdata["invc_user_aprobado"]}";
         $usuario = $CON->select($sql);
         $usuario = $usuario[0]["user_firstname"]." ".$usuario[0]["user_lastname"]; 

         $mensaje = '';
         if (!empty($headdata["invc_aprobcomments"])) {
            $mensaje .= "Comentario : " . $headdata["invc_aprobcomments"] . ", ";
         }
         $mensaje .= "Aprobado por : " . $usuario . " con Fecha/Hora : " . date("d.m.Y h:i", $headdata["invc_date_aprobado"]);
         ?>
            <div style="color:green;text-align:center;padding:10px;width:950px;border:3px solid green;font-family:Arial;font-size:12px">
               <u><b>APROBADO, </b></u> <?=$mensaje?>
            </div>
            <br>
         <?php
      }
   }
}   
/* Valida el numero o cantidad para informar diferencia */
$sql    = "select company_numcounter_itf_invc_tax_rend - company_numcounter_itf_invc_tax as difference 
                  from company_data cd 
                  where id = {$_SESSION["user_company_id"]}";
$resultalert = $CON->select($sql);
$resultalert = $resultalert[0];

$sql = "select valor1 from parametros where tabla = 'ALERTANROSII' and codigo = 'FACTURA'";
$resultadoparametro   = $CON->select($sql);
$resultadoparametro   = $resultadoparametro[0];

$difference = isset($resultalert["difference"]) ? $resultalert["difference"] : 0;
$valor1     = isset($resultadoparametro["valor1"]) ? $resultadoparametro["valor1"] : 0; 

if($resultalert["difference"] > 0 && $resultadoparametro["valor1"] >= $resultalert["difference"] and $headdata["invc_status"] != 2) 
{
   $mensaje = ' Le restan ' . $difference . ' numeros de correlativos de folios, contactese con el administrador ';
   ?>
   <div style="color:red;text-align:center;padding:10px;width:950px;border:3px solid red;font-family:Arial;font-size:12px">
      <u><b>ADVERTENCIA:</b></u> <?=$mensaje?>
   </div>
   <br>
   <?php
}
$queryDias = "select valor1 from parametros where tabla = 'DIASFACTURAS' and codigo = 'DIASFACTURAS'";
$resultDias = $CON->select($queryDias);
$diasPermitidos = isset($resultDias[0]['valor1']) ? intval($resultDias[0]['valor1']) : 5; // Default to 5 if no value found

if($_REQUEST["printpdf"])
{
  $pdffile = doc_createViewFacturas($CON, $_REQUEST["id"]);
} 

//---------------------------------------------------------------------------------------------------
$sql = "select id
   	       , transports_chofer_rut
	          , concat(transports_chofer_nombre,' ',transports_chofer_paterno) as Chofer
             , transports_chofer_trans_id
         from transports_chofer where transports_chofer_status > 0";
$chofer = $CON->select($sql);
//---------------------------------------------------------------------------------------------------
$sql = "select id
             , transports_rango_codigo
             , transports_rango_monto
             , transports_rango_trans_id
         from transports_rango where transports_rango_status > 0";
$rango = $CON->select($sql);
//---------------------------------------------------------------------------------------------------
$sql = "select id
	          , idtransports_vh
             , transports_vh_patente
         from transports_vehiculo where transports_vh_status > 0";
$vehiculo = $CON->select($sql);
//-------------------------------------------------------------------------------------------------
$sql = "select id, nombre from comunas";
$comunas = $CON->select($sql);
//----------------------------------------------------------------------------------
$custcredit = checkCustomerCreditLimit($CON, $headdata["invc_cust_id"], $headdata["id"], $headdata["invc_company_id"]);
if($custcredit["BLOCKED"])
{  ?>
   <table border="0" cellpadding="0" cellspacing="0" width="980">
   <tr>
      <td style="border-radius:5px;border:3px solid red">
         <table border="0" cellpadding="3" cellspacing="0" width="100%">
         <colgroup>
            <col width="120">
            <col width="190">
            <col width="120">
            <col width="190">
            <col width="120">
            <col>
         </colgroup>
         <tr>
            <td class="content_rowl" style="background-color:#FFDBDB">Limite Credito</td>
            <td class="content_row">$ <?=printPrice($custcredit["LIMIT"])?></td>
            <td class="content_rowl" style="background-color:#FFDBDB">Deuda Cliente</td>
            <td class="content_row">$ <?=printPrice($custcredit["AMOUNT"])?></td>
            <td class="content_rowl" style="background-color:#FFDBDB">Superado</td>
            <td class="content_row">$ <?=printPrice($custcredit["DIFF"])?></td>
         </tr>
         </table>
      </td>
   </tr>
   </table>
   <br>
   <?php
}
?>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/invoices_sell/searchstorehouses.php?invcid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;

      var valarr  = $('#item_id_' +idx).val().split('#');
      valarr[4]   = valarr[5];
      switchShpChargeMode(idx, valarr);
   }
   function submitPreviewForm() {
      // Configuras lo necesario para el modo de previsualización
      const form = document.form_shppos;
      form.previewprintmode.value = '1';
      form.submit();
      form.previewprintmode.value = '1';
   }

   function submitSaveForm(form) {
      // Aquí procesas el envío normal del formulario
      form.submit();
   }

   function updateItemStorehousesArr(idx, xval)
   {
      var valarr = xval.split('#');
      updateItemStorehouses(idx, valarr[0], valarr[1]);
   }

   function detectEvent (event, rowcount, id, storehousemode)
   {
      var xurl = './libs/modules/invoices_sell/searchitem.fancy.php?rowcount=' +rowcount + '&id=' +id +'&storehousemode=' +storehousemode;
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }

</script>
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<form action="index.php" method="post" name="form_shppos" id="form_shppos" enctype="multipart/form-data"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  $relationedCustShop = "";
   if($genshops)
   {
      $relatedCustShop = ", this.invc_cust_delivid";
   }
   ?>
   onsubmit="return checkform(new Array(this.invc_docnumber, this.invc_date, this.invc_paymentid, this.invc_userid_seller <?=$relatedCustShop?>))"
   <?php
}
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="previewprintmode" value="1">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="invc_status" value="1">
<input type="hidden" name="mensaje_articulos" value="<?=$_REQUEST["mensaje_articulos"]?>">
<input type="hidden" name="aprobsend" value="<?if((int)$_SESSION["invcsell_RELOADCALC"] == 1 && (int)$_REQUEST["aprobsend"]) echo "1"?>">
<input type="hidden" name="setPosOrder" value="">
<input type="hidden" name="cancelDoc" value="">
<input type="hidden" name="delinvcpartid" value="0">
<input type="hidden" name="showDiscounts" value="<?=$_REQUEST["showDiscounts"]?>">
<input type="hidden" name="user_pricesell_perm" value="<?=$_REQUEST["user_pricesell_perm"]?>">
<input type="hidden" name="user_docopen_perm" value="<?=$_REQUEST["user_docopen_perm"]?>">
<input type="hidden" name="mensaje_stock" id="mensaje_stock" value="<?=$_REQUEST["mensaje_stock"]?>">
<?=Nifty_printH("box1", "980",0)?>
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
   <td class="content_row"><?=$headdata["invc_number"]?>
      <!--
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear" width="120"><?=$headdata["invc_number"]?></td>
         <td class="content_row_clear" width="120">
            <nobr>
            OC
            <input type="text" class="text" style="width:90px" name="invc_oc_number" id="invc_oc_number" <?=$rdlo?>
            value="<?=$headdata["invc_oc_number"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </nobr>
         </td>
         <td class="content_row_clear">
            <nobr>
            <input type="text" style="width:80px" id="invc_oc_dat" name="invc_oc_dat" <?=$rdlo?>
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if($headdata["invc_oc_dat"] > 0) echo date('d.m.Y', $headdata["invc_oc_dat"])?>">
            </nobr>
         </td>
      </tr>
      </table>
      -->
   </td>
   <td class="content_rowl">Tipo</td>
   <td class="content_row"><?=getInvoiceSellType($headdata["invc_type"])?>
      <!--
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear" width="120"><?=getInvoiceSellType($headdata["invc_type"])?></td>
         <td class="content_row_clear" width="120">
            <nobr>
            HES
            <input type="text" class="text" style="width:90px" name="invc_hes_number" id="invc_hes_number" <?=$rdlo?>
            value="<?=$headdata["invc_hes_number"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </nobr>
         </td>
         <td class="content_row_clear">
            <nobr>
            <input type="text" style="width:80px" id="invc_hes_date" name="invc_hes_date" <?=$rdlo?>
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if($headdata["invc_hes_date"] > 0) echo date('d.m.Y', $headdata["invc_hes_date"])?>">
            </nobr>
         </td>
      </tr>
      </table>
      -->
   </td>
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
<tr>
   <?php
   if($headdata["invc_status"] == 1 && date('d.m.Y', $headdata["invc_date"]) != date('d.m.Y'))
   {
      $bcss1   = "<b class='msg_save_err'><blink>";
      $bcss2   = "</blink></b>";
      $tcss    = "border:2px solid red";
   }
   ?>
   <td class="content_rowl" <?=$cdatastyle?> height="31">Número factura *</td>
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
                  $.get('/libs/modules/invoices_sell/get.siistatus.jquery.php?mode=invoices_sell&id=<?=$_REQUEST["id"]?>', '', function(data)
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
            echo $headdata["invc_docnumber"]. " (Temporario)";
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
         <input type="text" 
               style="width:80px;<?=$tcss?>" 
               id="invc_date" 
               name="invc_date" 
               <?=$rdlo?>
               class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
               onfocus="markfield(this,0)" 
               onblur="markfield(this,1); validateDateRange(this.value);"
               value="<?=date('d.m.Y', $headdata["invc_date"])?>"
               max="<?php echo date('Y-m-d', strtotime('+' . $diasPermitidos . ' days')); ?>"
               min="<?php echo date('Y-m-d'); ?>">
      </td>
         <td class="content_row_clear" align="right" style="padding-right:10px">
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
   <td class="content_rowl">Transportista</td>
   <td class="content_row">
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

   <script>
         function filtrarOpciones(selectId, transId, reset = true) {
            var sel = document.getElementById(selectId);
            Array.from(sel.options).forEach(opt => {
               if (opt.value === "") return; // dejar opción vacía
               opt.style.display = (opt.dataset.trans.trim() === transId.trim()) ? 'block' : 'none';
            });

            // Solo reiniciar si se pidió explícitamente (cuando cambia transportista)
            if (reset) {
               sel.selectedIndex = 0;
            }
         }

         document.getElementById('invc_transportid').addEventListener('change', function() {
            var transId = this.value;

            // Reiniciar y filtrar cada combo dependiente
            filtrarOpciones('invc_vehiculo_id', transId, true);
            filtrarOpciones('invc_chofer_id', transId, true);
            filtrarOpciones('invc_rango_id', transId, true);
         });

         window.addEventListener('DOMContentLoaded', function() {
            var transSelect = document.getElementById('invc_transportid');
            var transId = transSelect.value;

            if (transId) {
               // Filtrar pero NO resetear (mantener valores grabados)
               filtrarOpciones('invc_vehiculo_id', transId, false);
               filtrarOpciones('invc_chofer_id', transId, false);
               filtrarOpciones('invc_rango_id', transId, false);
            } else {
               ['invc_vehiculo_id','invc_chofer_id','invc_rango_id'].forEach(id => {
                     var sel = document.getElementById(id);
                     Array.from(sel.options).forEach(opt => {
                        if (opt.value !== "") opt.style.display = 'none';
                     });
                     sel.selectedIndex = 0;
               });
            }
         });
   </script>


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
         printButton("{$btntext}", $btnclass, "index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&mode=invoices_sell&subcatexec=payment", "", "money", 120);
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
   <td class="content_rowl">Vehículo</td>
   <td class="content_row">
      <select name="invc_vehiculo_id" id="invc_vehiculo_id" class="text" style="width:350px" <?=$rdlo?>>
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php foreach ($vehiculo as $v) { ?>
            <option value="<?=$v['id']?>"
                  data-trans="<?=$v['idtransports_vh']?>"
                  <?php if($v["id"] == $headdata["invc_vehiculo_id"]) echo "selected"; ?>>
                  <?=$v["transports_vh_patente"]?>
            </option>
         <?php } ?>
      </select>
   </td>

   <td class="content_rowl">Chofer</td>
   <td class="content_row">
      <select name="invc_chofer_id" id="invc_chofer_id" class="text" style="width:350px" <?=$rdlo?>>
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php foreach ($chofer as $c) { ?>
            <option value="<?=$c['id']?>" 
                  data-trans="<?=$c['transports_chofer_trans_id']?>"
                  <?php if($c["id"] == $headdata["invc_chofer_id"]) echo "selected"; ?>>
               <?=$c['Chofer']?> (<?=$c['transports_chofer_rut']?>)
            </option>
         <?php } ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Rango</td>
   <td class="content_row">
      <select name="invc_rango_id" id="invc_rango_id" class="text" style="width:350px" <?=$rdlo?>>
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php foreach ($rango as $r) { ?>
            <option value="<?=$r['id']?>"
                    data-trans="<?=$r['transports_rango_trans_id']?>"
                    <?php if($r["id"] == $headdata["invc_rango_id"]) echo "selected"; ?>>
               <?=$r['transports_rango_codigo']?> - Monto $ <?=printPrice($r['transports_rango_monto'])?>
            </option>
         <?php } ?>
      </select>
   </td>
   <td class="content_rowl"></td>
   <td class="content_row"></td>
</tr>

<tr>
   <td class="content_rowl">Dirección Origen</td>
   <td class="content_row">
         <input type="text" class="text" style="width:350px" name="invc_direccion_origen" id="invc_direccion_origen" <?=$rdlo?>
           value="<?=$headdata["invc_direccion_origen"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
   <td class="content_rowl">Dirección Destino</td>
   <td class="content_row">
         <input type="text" class="text" style="width:350px" name="invc_direccion_destino" id="invc_direccion_destino" <?=$rdlo?>
         value="<?=$headdata["invc_direccion_destino"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Comuna Origen</td>
   <td class="content_row">
      <select name="invc_comuna_origen" id="invc_comuna_origen" class="text" style="width:350px" <?=$rdlo?> 
              onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
            foreach ($comunas as $c) 
            {
               ?>
               <option value="<?=$c['id']?>" 
                  <?php if($c["id"] == $headdata["invc_comuna_origen"]) echo "selected"?>><?=$c['nombre']?>
               </option>
               <?php
            }
         ?>
      </select>
   </td>
   <td class="content_rowl">Comuna Destino</td>
   <td class="content_row">
      <select name="invc_comuna_destino" id="invc_comuna_destino" class="text" style="width:350px" <?=$rdlo?> 
              onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
            foreach ($comunas as $c) 
            {
               ?>
               <option value="<?=$c['id']?>" 
                  <?php if($c["id"] == $headdata["invc_comuna_destino"]) echo "selected"?>><?=$c['nombre']?>
               </option>
               <?php
            }
         ?>
      </select>
   </td>
</tr>

<tr>
   <td class="content_rowl">Bultos</td>
   <td class="content_row">
      <input type="text" style="width:350px;" id="invc_bultos" name="invc_bultos" <?=$rdlo?> class="text"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if((int)$headdata["invc_bultos"]) echo $headdata["invc_bultos"]?>">
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
</tr>
<tr id="idf_shpdata">
   <?php
      $require = "";
      if($genshops)
      {
         $require = "*";
      }
   ?>
   <td class="content_rowl">Dirección factura <?=$require;?></td>
   <td class="content_row">
      <select class="text" style="width:350px;" name="invc_cust_delivid" id="invc_cust_delivid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <option value="0" <?php if($headdata["invc_cust_delivid"] == 0 && (int)$headdata["invc_upddat"]) echo "selected"?>>DIRECCIÓN PRINCIPAL</option>
            <?php
            foreach($deliveryaddrs as $deliveryaddr)
            {  ?>
               <option value="<?=$deliveryaddr["id"]?>"
               <?php if($deliveryaddr["id"] == $headdata["invc_cust_delivid"]) echo "selected"?>><?=$deliveryaddr["delivery_street"]?>, <?=$deliveryaddr["nombre"]?>, <?=$deliveryaddr["name"]?></option>
               <?php
            }
         }
         else
         {
            if(!(int)$headdata["invc_cust_delivid"])
            {  ?>
               <option value="0">DIRECCIÓN PRINCIPAL</option>
               <?php
            }
            else
            {  ?>
               <option value="<?=$headdata["invc_cust_delivid"]?>"><?=$deliveryaddr["delivery_street"]?>, <?=$deliveryaddr["nombre"]?>, <?=$deliveryaddr["name"]?></option>
               <?php
            }
         }
         ?>
      </select>
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
<!--
<tr>
   <td class="content_rowl">Referencia Guia</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear" width="125">
            <nobr>
            <input type="text" class="text" style="width:120px" name="invc_guia_number" id="invc_guia_number" <?=$rdlo?>
            value="<?=$headdata["invc_guia_number"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)"
            placeholder="Número Guia">
            </nobr>
         </td>
         <td class="content_row_clear">
            <nobr>
            <input type="text" style="width:80px" id="invc_guia_dat" name="invc_guia_dat" <?=$rdlo?>
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            placeholder="Fecha Guia"
            value="<?php if($headdata["invc_guia_dat"] > 0) echo date('d.m.Y', $headdata["invc_guia_dat"])?>">
            </nobr>
         </td>
      </tr>
      </table>
   </td>
   <td class="content_rowl" valign="top">&nbsp;</td>
   <td class="content_row" valign="top">&nbsp;</td>
</tr>
-->
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
<script type="text/javascript">
const DIAS_PERMITIDOS = <?php echo $diasPermitidos; ?>; // Pass the PHP variable to JavaScript

function validateDateRange(dateStr) {
    // Convert date string from dd.mm.yyyy to Date object
   let parts = dateStr.split('.');
   let inputDate = new Date(parts[2], parts[1] - 1, parts[0]);
   
   let today = new Date();
   today.setHours(0,0,0,0);
   
   let maxDate = new Date();
   maxDate.setDate(maxDate.getDate() + DIAS_PERMITIDOS); // Use the dynamic value
   maxDate.setHours(0,0,0,0);
   
   if (inputDate > maxDate) {
      alert('La fecha no puede ser mayor a ' + DIAS_PERMITIDOS + ' días desde hoy');
      // Reset to today's date
      let dd = String(today.getDate()).padStart(2, '0');
      let mm = String(today.getMonth() + 1).padStart(2, '0');
      let yyyy = today.getFullYear();
      document.getElementById('invc_date').value = dd + '.' + mm + '.' + yyyy;
      return false;
   }
   
   
   return true;
}

// Add validation to form submission
if (typeof document.form_shppos !== 'undefined') {
   let originalSubmit = document.form_shppos.onsubmit;
   document.form_shppos.onsubmit = function(e) {
      if (!validateDateRange(document.getElementById('invc_date').value)) {
         e.preventDefault();
         return false;
      }
      if (originalSubmit) {
         return originalSubmit.call(this, e);
      }
      return true;
   };
}
</script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<br>
<?php
//----------------------------------------------------------------------------------
$sql = " select *
         from tran_references
         where
         tran_id     = {$_REQUEST["id"]} and
         tran_type   = 'invc'
         order by id asc";
$refs = $CON->select($sql);
$refcc = count($refs)+2;
if($rdlo != "")
   $refcc = count($refs);
   
if($rdlo == "" || ($rdlo != "" && count($refs) && $refs != false))
{  ?>
   <?=Nifty_printH("box2", "980",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="230">
      <col width="160">
      <col width="125">
      <col>
      <col width="160">
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="5">Referencias adicionales</td>
   </tr>
   <tr>
      <td class="content_tbl_subheader content_row_os">Tipo</td>
      <td class="content_tbl_subheader content_row_os">Número</td>
      <td class="content_tbl_subheader content_row_os">Fecha</td>
      <td class="content_tbl_subheader content_row_os" align="center">Valido</td>
      <td class="content_tbl_subheader content_row_os" align="center">Opciones</td>
   </tr>
   <?php
   for($refx = 0; $refx < $refcc; $refx++)
   {  ?>
      <tr bgcolor="<?=getRowColor($refx)?>">
         <td class="content_row_os">
            <select id="ref_type_<?=$refx?>" name="ref_type_<?=$refx?>" class="text" style="width:100%">
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <option value="52"  <?php if($refs[$refx]["ref_type"] == "52") echo "selected"?>>Guía de despacho electrónica</option>
               <option value="801" <?php if($refs[$refx]["ref_type"] == "801") echo "selected"?>>Orden de Compra</option>
               <option value="802" <?php if($refs[$refx]["ref_type"] == "802") echo "selected"?>>Nota de Pedido</option>
               <option value="803" <?php if($refs[$refx]["ref_type"] == "803") echo "selected"?>>Contrato</option>
               <option value="814" <?php if($refs[$refx]["ref_type"] == "814") echo "selected"?>>Certificado de depósito bolsa prod. chile</option>
               <option value="815" <?php if($refs[$refx]["ref_type"] == "815") echo "selected"?>>Vale de prenda bolsa prod. chile</option>
               <option value="HAS" <?php if($refs[$refx]["ref_type"] == "HAS") echo "selected"?>>Hoja de aceptación de servicio (HAS)</option>
               <option value="HEM" <?php if($refs[$refx]["ref_type"] == "HEM") echo "selected"?>>Recepción de material (HEM)</option>
               <option value="HES" <?php if($refs[$refx]["ref_type"] == "HES") echo "selected"?>>Hoja de estado de servicio (HES)</option>
            </select>
         </td>
         <td class="content_row_os">
            <input type="text" class="text" name="ref_number_<?=$refx?>" style="width:100%"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            value="<?=$refs[$refx]["ref_number"]?>">
         </td>
         <td class="content_row_os">
            <input type="text" style="width:85px" name="ref_date_<?=$refx?>" readonly
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            value="<?php if($refs[$refx]["ref_date"] != "") echo $refs[$refx]["ref_date"]?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </td>
         <td class="content_row_os" align="center">
            <?php
            if($refs[$refx]["ref_type"] != "")
            {
               if($refs[$refx]["ref_number"] == "")
                  echo "<b class=msg_save_err>Incompleto: Ingrese el Número</b>";
               elseif(!(int)$refs[$refx]["ref_date"])
                  echo "<b class=msg_save_err>Incompleto: Ingrese el Fecha</b>";
               else
                  echo "<b class=msg_save_ok>Valido</b>";
            }
            else
               echo "- - -";
            ?>
         </td>
         <td class="content_row_os" align="center">
            <?php
            if($refs[$refx]["ref_type"] != "" && $rdlo == "")
               printButton($_LANG["FORM"]["BUTTON"][2], "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')) { $('#ref_type_{$refx}').val(''); document.form_shppos.submit(); }", "cross-circle-frame");
            else
               echo "- - -";
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

if($showStorehouses && $showRelItems)
   printRelItems($CON, $_REQUEST["id"], "invoicesell", $headdata["invc_shop_id"], $headdata["invc_status"]);

$hasItems = false;
$gc = 0;
for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
{
   $_HASFABITEMFOUND = false;
   $part       = $invcparts[$x];
   $posdata    = getInvoiceSellPartsItems($CON, $_REQUEST["id"], $part["id"], $_REQUEST["setPosOrder"]);
   $rowcount   = count($posdata);

   //----------------------------------------------------------------------------------
   if($headdata["invc_status"] == 1)
   {
      $rowcount = 22;
      if((int)$part["part_req_id"] > 0 || (int)$part["part_dlv_id"] > 0)
         $rowcount = count($posdata) + 2;
         
      if($headdata["invc_type"] == 1 && (int)$headdata["invc_dlv_addtxt"])
         $rowcount = count($posdata);
   }
   else
      $rowcount = count($posdata);
   ?>
   <?=Nifty_printH("box1", "980",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="85">
      <col width="28">
      <col>
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
                  Confirmación de compra: <?=$part["req_number"]?>
                  <?php
               }
               if($headdata["invc_type"] == 3)
                  echo "Artículos";
               ?>
            </td>
            <?php
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
            <td class="content_tbl_header" style="border:0px;padding:0px" align="right" width="30">
               <?php
               if($headdata["invc_status"] == 1 && $headdata["invc_type"] != 3)
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
      <td class="content_tbl_subheader" valign="top">Artículos</td>
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
      $orderstockmode = false;
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
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/invoices_sell/searchitem.php?rowcount=<?=$part["id"]?>_<?=$y?>&id=<?=$_REQUEST["id"]?><?=$urlparam?>&search=' +this.value} this.value='';"
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
                  $lindesc = $posdata[$y]["item_number_prod"]." - ".$desc;

                  if((int)$part["part_req_id"])
                  {
                     $sql = " select t1.*, t4.cust_name, t2.*
                              from orders t1
                              INNER JOIN orders_items t2 ON t1.id = t2.req_id
                              INNER JOIN customer t4     ON t1.req_cust_id = t4.id
                              where
                              t1.id = {$part["part_req_id"]} and
                              t1.req_isfabricate = 1";
                     $orderinfo = $CON->select($sql);
                     $orderinfo = $orderinfo[0];
                     if((int)$orderinfo["id"] && (int)$orderinfo["item_id"] == $posdata[$y]["item_id"] && !$_HASFABITEMFOUND)
                     {
                        $lindesc  = $posdata[$y]["item_number_prod"]."/";
                        $lindesc .= $posdata[$y]["item_title"]."/";
                        $lindesc .= $orderinfo["fab_type"]."/";
                        $lindesc .= (int)$orderinfo["fab_mat_gramms"]."/";
                        $lindesc .= (int)$orderinfo["fab_med_width"]."x".(int)$orderinfo["fab_med_height"]."x".(int)$orderinfo["fab_med_fuelle"]."/";
                        $lindesc .= $orderinfo["cust_name"]."/";
                        if($orderinfo["fab_design_name"] != "")
                           $lindesc .= $orderinfo["fab_design_name"]."/";
                        $lindesc .= $orderinfo["req_number"];
                        $lindesc  = mb_convert_case($lindesc, MB_CASE_UPPER, "ISO-8859-1");
                        $_HASFABITEMFOUND = true;
                        $orderstockmode = true;
                     }
                  }
                  ?>
                  <option value="<?=$posdata[$y]["item_id"]?>#<?=$posdata[$y]["item_type"]?>"><?=$lindesc?></option>
                  <?php
               }
               ?>
            </select>
            <?php
            if(trim($posdata[$y]["item_adddesc"]) != "" && $posdata[$y]["item_type"] != 'manual')
            {  ?>
               <br>
               <?=$posdata[$y]["item_adddesc"]?>
               <?php
            }
            ?>
            <?php if($overlibover2 != "") echo "<br><img src='/images/menu/icons/exclamation-red.png' style='valign:bottom'>&nbsp;{$overlibover2}" ?>
            <textarea class="text" name="item_desc_<?=$part["id"]?>_<?=$y?>" id="item_desc_<?=$part["id"]?>_<?=$y?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
            style="width:<?php if($ovritemselw != "") echo $ovritemselw; else echo $itemselw?>;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$y]["item_desc"]?></textarea>
            <input type="hidden" name="manual_pos_<?=$part["id"]?>_<?=$y?>" id="manual_pos_<?=$part["id"]?>_<?=$y?>"
            value="<?php if($showmanual) echo "1"; else echo "0" ?>">
            <?php
            if((int)$posdata[$y]["item_id"] && (int)$posdata[$y]["item_order_pos"] > -1)
            {
               $sql = " select t1.item_compdesc, t3.req_number
                        from orders_items t1
                        INNER JOIN orders t2 ON t1.req_id = t2.id
                        INNER JOIN offers t3 ON t2.req_offerid = t3.id
                        where
                        t1.req_id      = {$part["part_req_id"]} and
                        t1.item_id     = {$posdata[$y]["item_id"]} and
                        t1.item_pos    = {$posdata[$y]["item_order_pos"]}";
               $item_compdesc = $CON->select($sql);
               $offernumber   = trim($item_compdesc[0]["req_number"]);
               $item_compdesc = trim($item_compdesc[0]["item_compdesc"]);

               if($item_compdesc != "")
               {  ?>
                  <div style="margin-top:5px;border:1px solid #666666;width:<?php if($ovritemselw != "") echo $ovritemselw; else echo $itemselw?>">
                     <div style="padding:10px">
                        <b><?=$offernumber?></b>:<br>
                        <?=strip_tags($item_compdesc)?>
                     </div>
                  </div>
                  <?php
               }
            }
            ?>
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
         {  
            ?>
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
                  onmousedown="markfield(this,0)"
                  onchange="mostrarStock(this, 'item_amount_<?=$part["id"]?>_<?=$y?>')">
                  >
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
                           $justonce = true;
                           $justoncemensaje = true;
                           foreach(array_keys($itemsts) AS $itemstid)
                           {
                              if($rdlo == "" || ($rdlo != "" && $posdata[$y]["item_st_id"] == $itemstid))
                              {
                                 if($orderstockmode)
                                 {
                                    $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $itemstid, $posdata[$y]["item_id"], $posdata[$y]["item_type"], true, (int)$part["part_req_id"]);
                                 }
                                 else
                                 {
                                    $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["invc_shop_id"], $itemstid, $posdata[$y]["item_id"], $posdata[$y]["item_type"], true);
                                 }
                                 if($justonce){
                                    $firststock = $currstock;
                                    $justonce = false;
                                 }
                                 $sql = "select * from item where id = {$posdata[$y]["item_id"]}";
                                 $item2 = $CON->select($sql);
                                 $item2 = $item2[0];
                                 $mensaje_stock = "";
                                 ?>
                                 <option value    ="<?=$itemstid?>"
                                      data-stock    = "<?=printPrice($currstock,2)?>" 
                                      data-idbodega = "<?=$posdata[$y]["item_st_id"]?>" 
                                      data-controla_stock = "<?=$item2["item_controla_stock"]?>
                                      data-bodega   = "<?=$itemsts[$itemstid]?>" 
                                                    <?php if($posdata[$y]["item_st_id"] == $itemstid && $item2["item_controla_stock"] == 1)
                                                       { 
                                                         echo "selected"; 
                                                         $mensaje_stock = "";
                                                         if($currstock < $posdata[$y]["item_amount"]) 
                                                         {  
                                                            $mensaje_stock = "Stock insuficiente";
                                                            $_REQUEST["mensaje_stock"] = $mensaje_stock;
                                                         }
                                                       }
                                                    ?>
                                 >(<?=printPrice($currstock,2)?>) / <?=$itemsts[$itemstid]?>
                                 </option>
                                 <?php
                              }
                              /*
                              $sql = "select cat_id from item_productcats where item_id = {$posdata[$y]["item_id"]}";
                              $nofind = $CON->select($sql);
                              $nofind = $nofind[0]["cat_id"];
                              if((int)$currstock < (int)$posdata[$y]["item_amount"] && $nofind != 14 )
                              {
                                 $requestaprobed = false;
                                 if($justoncemensaje)
                                 {
                                    $mensajestocknegativo .= $posdata[$y]["item_amount"] 
                                                          . " unidades del artículo "
                                                          . $posdata[$y]["item_title"]
                                                          . " y se encuentran disponibles " 
                                                          . $currstock 
                                                          . " unidades en stock.  ";
                                    $justoncemensaje = false;
                                 }
                                 $_REQUEST["mensaje_articulos"] = $_SESSION['mensajestocknegativo'];
                              
                              }
                              */ 
                           }
                        }
                     }
                     ?>
                  </select>
                  <?php 
                     if ($mensaje_stock != "")
                     {
                        ?>
                        <script>
                           document.getElementById("mensaje_stock").value = "<?= $mensaje_stock ?>";
                        </script>
                        <?php
                     }
                  ?>
                  <script>
                     /*
                     window.addEventListener('DOMContentLoaded', function () {
                        const select = document.querySelector('select[name="item_stid_<?= $part["id"] ?>"]');
                        const referencia = 'item_amount_<?= $part["id"] ?>_<?= $y ?>';
                        if (select) {
                           console.log("? Select encontrado:", select);
                           mostrarStock(select, referencia);
                        } else {
                           console.warn("?? Select no encontrado");
                        }

                     });
                     */

                     function mostrarStock(selectElement, referencia)
                     {
                        const selectedOption = selectElement.options[selectElement.selectedIndex];
                        const bodega = selectedOption.getAttribute("data-bodega") || "Sin bodega";
                        const rawStock = selectedOption.getAttribute("data-stock") || "0";
                        const ramIdbodeda = selectedOption.getAttribute("data-idbodega") || "0";
                        const controlastock = selectedOption.getAttribute("data-controla_stock"); 

                        const campoCantidad = document.getElementById(referencia);
                        const rawCantidad = campoCantidad ? campoCantidad.value : "";

                        // Validar que ambos valores existan y no estén vacíos
                        if (rawCantidad.trim() === "" || rawStock.trim() === "") {
                           alert("No se pudo obtener valores válidos para cantidad o stock.");
                           return;
                        }

                        const stock = parseFloat(rawStock.replace(/\./g, "").replace(",", "."));
                        const cantidad = parseFloat(rawCantidad.replace(/\./g, "").replace(",", "."));
                        if (isNaN(stock) || isNaN(cantidad)) {
                           alert("Los valores ingresados no son numéricos.");
                           return;
                        }

                        let mensaje = ""; // Declaración global dentro del contexto actual

                        if (cantidad > stock && (controlastock == 1)) 
                        {
                           mensaje = "Stock insuficiente";
                              
                        }
                        else
                        {
                           mensaje = "";
                        }
                        /*
                        console.log("Mensaje actual:", mensaje); // Visualización en consola
                        console.log("Mensaje stock:", controlastock); // Visualización en consola
                        console.log("Mensaje bodega:", ramIdbodeda); // Visualización en consola
                        */
                        document.getElementById("mensaje_stock").value = mensaje;
                        return;
                     }
                  </script>

                  <span id="stock_info_<?=$part["id"]?>_<?=$y?>"></span>
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
               if($posdata[$y]["item_discount"] != 0.00) echo "background-color:#E1FFD6"; else echo "background-color:#FFD6D8";?>"
               value="<?php if($posdata[$y]["item_discount"] != 0.00) echo printPrice($posdata[$y]["item_discount"],2);?>" <?=$dscrdlo?>>
               
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
         <?=Nifty_printH("box1", $ftablewidth,0)?>
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
         <!--
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row">
               CONDUCCIÓN | SUGERENCIA: <b class="msg_save_ok"><?=printPrice($ges_weight, 2)?> Kg = $ <?=printPrice($ges_weight_price)?></b>
            </td>
            <td class="content_row" align="right">
               <?php
               if($headdata["invc_status"] < 2)
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
               value="<?=printPrice($headdata["invc_weightprice_netto"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </nobr>
            </td>
         </tr>
         -->
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
            <?=Nifty_printH("box1", "578", 0)?>
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
<?=Nifty_printH("boxopt_b", $ftablewidth, 0)?>
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
   {
         if( (int)$_SESSION["user_type"] != 1)
         {
            $id = $_REQUEST["id"];
            $sql = " select *
                     from invoices_sell
                     where
                     id = {$id}";
            $sodata = $CON->select($sql);
            $sodata = $sodata[0];

            $invc_total_brutto = $sodata["invc_total_brutto"];
            if(!(int)$sodata["invc_taxes"])
            {
               $usdval = getMoneyExchangeRate($CON, date('d.m.Y'));
               $invc_total_brutto = $invc_total_brutto * $usdval;
            }
            // if($invc_total_brutto >= 3000000)
            //    $canfinalize = false;
         }
         ?>
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

         if($hasItems)
         {
            /*
            ?>
            <td align="right" width="130" style="padding-right:5px">
            <?php
            printButton("Previsualizar", "postnav", "javascript: deactivateFormChange()", "submitPreviewForm()", "eye");
            ?>
            </td>
            <?php
            */
         if($user_autoriza_fact_perm)
         {
            ?>
            <td align="right" width="130" style="padding-right:5px" id="idx_finalbtn1">
            <?php
               printButton("Finalizar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.invc_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
               ?>
            </td>
            <?php
         } else
         {
            if($headdata["invc_aprobblocked"] == 2 || $_REQUEST['mensaje_stock'] == "")
            { ?>
               <td align="right" width="130" style="padding-right:5px" id="idx_finalbtn1">
               <?php
                  printButton("Finalizar", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.invc_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
                  ?>
               </td>
               <?php
            }
            else
            { 
               ?>
               <td align="right" width="130" style="padding-right:5px" id="idx_finalbtn2">
               <?php
                  printButton("Pedir aprobación", "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.aprobsend.value='1';submitForm(document.form_shppos);}", "tick-circle-frame");
               ?>
               </td>
               <?php
            }
         }
      }
   }
   if($headdata["invc_status"] >= 2)
   {  ?>
      <td align="right" width="130" id="idx_fin_button">
         <?php
         printButton("Enviar", "postnav_save", "javascript:document.getElementById('idx_mail').style.display='';$('html,body').animate({scrollTop:$('#idx_mail').offset().top}, 600);void(0)", "", "mail");
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
<iframe id="idxifrsrc" height="0" width="0" frameborder="0"></iframe>
<?php
if($_REQUEST["setPosOrder"] == "prodnumber")
{  ?>
   <script language="JavaScript">
      submitForm(document.form_shppos);
   </script>
   <?php
}
if($_SESSION["invcsell_RELOADCALC"] == 1)
{  ?>
   <script language="JavaScript">
      submitForm(document.form_shppos);
   </script>
   <?php
   $_SESSION["invcsell_RELOADCALC"] = 2;
}
//----------------------------------------------------------------------------------
if($_REQUEST["printpdf"])
  $pdffile = doc_createInvoicesSell($CON, $_REQUEST["id"]);
  
if($pdffile != "")
{
   $doctitle = "Factura-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}

if((int)$_REQUEST["previewprintmode"]==10)
{
   // $xfilename = doc_createInvoicesSellPreview($CON, $_REQUEST["id"], 0);
   $xfilename = doc_createInvoicesSell_multiPage($CON, $_REQUEST["id"]);
   $xfilename = str_replace("/tmp/", "", $xfilename);
   $doctitle = "Factura-".time().".pdf";
   ?>
   <script language="JavaScript">
   $(document).ready(function()
   {
      // Definir las variables PHP en JavaScript de manera segura para ISO-8859-1
      const xfilename = "<?= htmlspecialchars($xfilename, ENT_QUOTES, 'ISO-8859-1') ?>";
      const doctitle = "<?= htmlspecialchars($doctitle, ENT_QUOTES, 'ISO-8859-1') ?>";

   // Usar Template Literals para construir la URL
      window.open(`/libs/modules/structure/document_file.php?type=0&hash=${xfilename}.pdf&name=${doctitle}&path=../../../docs.print/`);
      /* window.open('/libs/modules/structure/document_file_invoice.php?type=0&hash=<?=$xfilename?>&name=Previsualizacion-<?=$headdata["invc_number"]?>.pdf&loadtemppath=1'); */
   });
   </script>
   <?php
}
if((int)$_REQUEST["printsiidoc1"] || (int)$_REQUEST["printsiidoc2"])
{
   if((int)$_REQUEST["printsiidoc1"])
   {
      $doctitle   = "Factura-{$headdata["invc_docnumber"]}.pdf";
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

if(count($custselarr))
{  ?>
   <div id="idx_mail" style="display:none">
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
      function reloadTINY(newtxt)
      {
         $('#msg_body').val(newtxt);
         tinyMCE.get('msg_body').setContent('');
         tinyMCE.get('msg_body').execCommand('insertHTML', false, newtxt);
         tinyMCE.triggerSave();
      }
   </script>
   <form action="index.php" method="post" name="xform_docsend"
    onsubmit="return checkform(new Array(this.msg_header, this.msg_body))">
    <input type="hidden" name="exec" value="<?=$_REQUEST["exec"]?>">
   <input type="hidden" name="subexec" value="send">
   <input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
   <input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
   <?=Nifty_printH("box2", "980",0)?>
   <table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
   <colgroup>
      <col width="150">
      <col>
   </colgroup>
   <tr>
      <td class="content_tbl_header" colspan="2">Enviar correo</td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top"><?=$_LANG["MODULE"]["MSG"][19]?></td>
      <td class="content_row">
         <?php
         for($yy = 0; $yy < count($custselarr); $yy++)
         {  ?>
            <input type="checkbox" name="xrecpt[]" <?php if($yy == 0) echo "checked"?>
            value="<?=$custselarr[$yy]["NAME"]?>###<?=$custselarr[$yy]["MAIL"]?>">
            <?=$custselarr[$yy]["NAME"]?> &lt;<?=$custselarr[$yy]["MAIL"]?>&gt;
            <br>
            <?php
         }

         $msg_header = "Factura de Unibag a {$customer["cust_name"]}";
         $msg_header = str_replace("'", "", str_replace('"', "", $msg_header));

         $sql = " select user_mail_signature_html
                  from user
                  where
                  id = {$_SESSION["user_id"]}";
         $mailsig = $CON->select($sql);
         $mailsig = $mailsig[0]["user_mail_signature_html"];
         if(trim($mailsig) != "")
            $msg_body = "<br><br>".$mailsig;
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl" valign="top">CCO</td>
      <td class="content_row">
         <?php
         for($yy = 0; $yy < count($ccousers); $yy++)
         {  ?>
            <input type="checkbox" name="xcco[]" value="<?=$ccousers[$yy]["id"]?>">
            <?=$ccousers[$yy]["user_firstname"]?> <?=$ccousers[$yy]["user_lastname"]?> &lt;<?=$ccousers[$yy]["user_mail"]?>&gt;
            <br>
            <?php
         }
         ?>
      </td>
   </tr>
   <tr>
      <td class="content_rowl">Tipo respuesta</td>
      <td class="content_row">
      <?php
      $_AMT_INVC = $headdata["invc_total_brutto"];
      $_AMT_PAY  = 0;
      $payposdata = getTranPayments($CON, $_REQUEST["id"], "invoices_sell");
      foreach($payposdata AS $posdatarow)
      {
         if((int)$posdatarow["pay_payed"])
            $_AMT_PAY += $posdatarow["pay_brutto"];
      }
      $_AMT_PEND = $headdata["invc_total_brutto"] - $_AMT_PAY;
      ?>
         <textarea class="text" style="display:none" id="idx_default_text0">Estimado/a Cliente;<br><br>
Junto con saludar confirmamos que su pedido de bolsas reutilizables está listo para despacho.<br><br>
Adjunto factura la cual solicitamos por favor realizar pago pendiente para poder despachar.
Los despachos se coordinan para el día siguiente una vez recibido el comprobante de depósito ó transferencia.<br><br>
Importe a transferir $ <?=printPrice($_AMT_PEND)?><br><br>
<b>Datos para pago:</b><br>
UNIBAG SPA<br>
RUT: 76.283.675-0<br>
BANCO BCI<br>
CTA CTE. 70122202<br>
Mail: arnaldo.vilches@unibag.cl<br><br>
Le solicitamos por favor enviar copia del comprobante de depósito o transferencia.<br><br>
Muchas gracias.<br>
<?=stripslashes($msg_body)?></textarea>

<textarea class="text" style="display:none" id="idx_default_text1">Estimado/a cliente;<br><br>
Junto con saludar informo a ud. que hoy se realiza despacho de su pedido con la Factura Electrónica adjunta,
la cual agradeceremos enviar al sector de contabilidad.<br><br>
<b>Datos para pago:</b><br>
UNIBAG SPA<br>
RUT: 76.283.675-0<br>
BANCO BCI<br>
CTA CTE. 70122202<br>
Mail: arnaldo.vilches@unibag.cl<br><br>
Muchas gracias.
<br>
<?=stripslashes($msg_body)?></textarea>

<?php
$prodname = "";
if((int)$posdata[0]["item_id"] && (int)$posdata[0]["item_id"] != 9999999)
   $prodname = " de ".$posdata[0]["item_title"];
?>
<textarea class="text" style="display:none" id="idx_default_text2">Estimado/a Cliente;<br><br>
Junto con saludar comunico a Ud. que está disponible para retirar su pedido<?=$prodname?>. Adjunto Factura Electrónica.<br><br>
<b>Información para retiro:</b><br>
Dirección : Caupolicán 9400 bodega 3 , Quilicura<br>
Contacto : Antonio Laya<br>
Horario  : 09:00 a 16:30 Hrs<br><br>
<b>Datos para pago:</b><br>
UNIBAG SPA<br>
RUT: 76.283.675-0<br>
BANCO BCI<br>
CTA CTE. 70122202<br>
Mail: arnaldo.vilches@unibag.cl<br><br>
Muchas gracias.
<br>
<?=stripslashes($msg_body)?></textarea>

         <input type="radio" value="0" name="xdummy_msg_typ" id="xdummy_msg_typ0" onclick="reloadTINY($('#idx_default_text0').val());"> Pagar antes de despachar
         <input type="radio" value="1" name="xdummy_msg_typ" id="xdummy_msg_typ1" onclick="reloadTINY($('#idx_default_text1').val());"> Cliente con Cta Cte
         <input type="radio" value="2" name="xdummy_msg_typ" id="xdummy_msg_typ2" onclick="reloadTINY($('#idx_default_text2').val());"> Cliente que retira
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
         <textarea class="text mceEditor" style="width:810px; height:150px" name="msg_body" id="msg_body"
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
   </form>
   </table>
   <br><br><br>
   </div>
   <?php
}