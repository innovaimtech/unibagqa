<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

$_HIDETAXES = true;

if($_REQUEST["showfullcust"] == "1")
   $_SESSION["xordersdlv"]["fullcust"] = "1";
if($_REQUEST["showfullcust"] == "0")
   $_SESSION["xordersdlv"]["fullcust"] = "";

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

   $sql = " update orders_delivery
            set
            dlv_sgntr_doc1 = '',
            dlv_sgntr_doc2 = ''
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   $sql = "select * from orders_delivery where id = {$_REQUEST["id"]}";
   $guia = $CON->select($sql);
   $guia = $guia[0];

   error_reporting(0);

   $sql = "select descripcion as url from parametros where tabla = 'API_DEFONTAN' and codigo = 'ImprimePDF'";
   $url = $CON->select($sql);
   $api_url = $url[0]['url'];

   $filedir = "../../../docs.electrpdf/";
   $filename1 = "52_{$guia["id"]}_{$comp_rut}_{$file_hash}_doc1.pdf";
   $filename2 = "52_{$guia["id"]}_{$comp_rut}_{$file_hash}_doc2.pdf";

   $parametros = array('documentType'  => 'GDVELECT',
                       'folio'          => $guia["dlv_docnum"],
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

   $parametros = array(
                        'documentType'   => 'GDVELECT',
                        'folio'          => $guia["dlv_docnum"],
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

      $sql = " update orders_delivery
            set
            dlv_sgntr_doc1 = '{$filename1}',
            dlv_sgntr_doc2 = '{$filename2}'
            where id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

   } 
}

//----------------------------------------------------------------------------------
if($_REQUEST["cancelinvc"] == "1")
{
   $currtme = time();

   $sql = " update orders_delivery
            set
            dlv_invoiced      = 1,
            dlv_status        = 3,
            dlv_updusr        = {$_SESSION["user_id"]},
            dlv_upddat        = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
if($_REQUEST["revertinvc"] == "1")
{
   $currtme = time();

   $sql = " update orders_delivery
            set
            dlv_invoiced      = 0,
            dlv_status        = 2,
            dlv_updusr        = {$_SESSION["user_id"]},
            dlv_upddat        = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $res = $CON->no_result($sql);

   $savemsg = getSaveMessage($res);
}

//----------------------------------------------------------------------------------
$dlv_parent_guaid = 0;
$dlv_isguarantee  = 0;

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
      if((int)$xrow["guat_dlv_act"])
      {
         $xguapos[]  = $xrow;
      }
   }

   $_REQUEST["cust_id_0"]           = (int)$xgua["gua_cust_id"];
   $_REQUEST["company_id"]          = (int)$xgua["gua_company_id"];
   $_REQUEST["shop_id"]             = (int)$xgua["gua_shop_id"];
   $_REQUEST["dlv_delivery_date"]   = date('d.m.Y');
   $_REQUEST["dlv_order_based"]     = 2;
   $_REQUEST["subexec"]             = "create";
   $dlv_parent_guaid                = $_REQUEST["guaID"];
   $dlv_isguarantee                 = 1;
   $_REQUEST["dlv_annotation"]      = "GUIA SIN VALOR COMERCIAL";
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "createFromGuaranteeSupp")
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
      if((int)$xrow["guat_dlv_act"] && $xrow["item_supplier_id"] == $_REQUEST["guasuppid"])
      {
         $xguapos[]  = $xrow;
      }
   }

   $_REQUEST["supplier_id_0"]       = (int)$_REQUEST["guasuppid"];
   $_REQUEST["company_id"]          = (int)$xgua["gua_company_id"];
   $_REQUEST["shop_id"]             = (int)$xgua["gua_shop_id"];
   $_REQUEST["dlv_delivery_date"]   = date('d.m.Y');
   $_REQUEST["dlv_order_based"]     = 4;
   $_REQUEST["subexec"]             = "create";
   $dlv_parent_guaid                = $_REQUEST["guaID"];
   $dlv_isguarantee                 = 1;
   $_REQUEST["dlv_annotation"]      = "GUIA SIN VALOR COMERCIAL";
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "create")
{
   $currtme = time();
   
   $_REQUEST["dlv_order_based"]     = (int)$_REQUEST["dlv_order_based"];
   $_REQUEST["cust_id_0"]           = (int)$_REQUEST["cust_id_0"];
   $_REQUEST["supplier_id_0"]       = (int)$_REQUEST["supplier_id_0"];
   $_REQUEST["company_id"]          = (int)$_REQUEST["company_id"];
   $_REQUEST["shop_id"]             = (int)$_REQUEST["shop_id"];
   $_REQUEST["dlv_order_id"]        = (int)$_REQUEST["dlv_order_id"];
   $_REQUEST["dlv_delivery_date"]   = trim($_REQUEST["dlv_delivery_date"]);
   $_REQUEST["dlv_delivery_date"]   = explode(".", $_REQUEST["dlv_delivery_date"]);
   $_REQUEST["dlv_delivery_date"]   = (int)mktime(0, 0, 0, $_REQUEST["dlv_delivery_date"][1], $_REQUEST["dlv_delivery_date"][0], $_REQUEST["dlv_delivery_date"][2]);
   $dlv_mode                        = (int)$_REQUEST["dlv_order_based"];
   $_REQUEST["dlv_isinvcbrutto"]    = (int)$_REQUEST["dlv_isinvcbrutto"];
   $_REQUEST["dlv_order_otherclient"]  = (int)$_REQUEST["dlv_order_otherclient"];
   $_REQUEST["dlv_order_noitemload"]   = (int)$_REQUEST["dlv_order_noitemload"];
   $_REQUEST["cust_id_1"]              = (int)$_REQUEST["cust_id_1"];
   $_REQUEST["dlv_discount_perc"]   = 0.00;
   $_REQUEST["dlv_discount_amt"]    = 0.00;
   $_REQUEST["dlv_oc_dat"]          = 0;
   
   //----------------------------------------------------------------------------------
   if($_REQUEST["dlv_order_based"] <= 2)
   {
      if($_REQUEST["dlv_order_based"] == 2)
         $_REQUEST["dlv_order_based"] = 0;

      $_REQUEST["dlv_oc_dat"] = 0;
      if($_REQUEST["dlv_order_based"])
      {
         $sql = " select req_cust_id, req_paymentid, req_taxes, req_cust_delivid, req_transportid, req_weightprice_netto,
                         req_oc_dat, req_oc_number, req_desc, req_desc_intern, req_discount_perc, req_discount_amt
                  from orders
                  where
                  id = {$_REQUEST["dlv_order_id"]}";
         $custid = $CON->select($sql);
         $_REQUEST["cust_id_0"]        = (int)$custid[0]["req_cust_id"];
         $_REQUEST["dlv_paymentid"]    = (int)$custid[0]["req_paymentid"];
         $_REQUEST["dlv_taxes"]        = (int)$custid[0]["req_taxes"];
         $_REQUEST["dlv_cust_delivid"] = (int)$custid[0]["req_cust_delivid"];
         $_REQUEST["dlv_transportid"]  = (int)$custid[0]["req_transportid"];
         $_REQUEST["dlv_weightprice_netto"]  = (float)$custid[0]["req_weightprice_netto"];
         $_REQUEST["dlv_oc_dat"]             = (int)$custid[0]["req_oc_dat"];
         $_REQUEST["dlv_oc_number"]          = trim(addslashes($custid[0]["req_oc_number"]));
         $_REQUEST["dlv_annotation"]         = trim(addslashes($custid[0]["req_desc"]));
         $_REQUEST["dlv_annotation_intern"]  = trim(addslashes($custid[0]["req_desc_intern"]));
         $_REQUEST["dlv_discount_perc"]      = (float)$custid[0]["req_discount_perc"];
         $_REQUEST["dlv_discount_amt"]       = (float)$custid[0]["req_discount_amt"];

         if((int)$_REQUEST["dlv_order_otherclient"] && (int)$_REQUEST["cust_id_1"])
         {
            $_REQUEST["cust_id_0"]        = (int)$_REQUEST["cust_id_1"];
            $_REQUEST["dlv_cust_delivid"] = 0;
         }
      }
      else
      {
         $sql = " select cust_paymentid
                  from customer
                  where
                  id = {$_REQUEST["cust_id_0"]}";
         $cust_paymentid = $CON->select($sql);

         $_REQUEST["dlv_paymentid"]    = (int)$cust_paymentid[0]["cust_paymentid"];
         $_REQUEST["dlv_order_id"]     = 0;
         $_REQUEST["dlv_cust_delivid"] = 0;
         $_REQUEST["dlv_transportid"]  = 0;
         $_REQUEST["dlv_taxes"]        = (int)$_REQUEST["dlv_taxes"];
         $_REQUEST["dlv_weightprice_netto"] = 0.00;
      }
   }
   else
   {
         $sql = " select supp_paymentid
                  from supplier
                  where
                  id = {$_REQUEST["supplier_id_0"]}";
         $supp_paymentid = $CON->select($sql);

         $_REQUEST["dlv_paymentid"]    = (int)$supp_paymentid[0]["supp_paymentid"];
         $_REQUEST["dlv_order_id"]     = 0;
         $_REQUEST["dlv_cust_delivid"] = 0;
         $_REQUEST["dlv_transportid"]  = 0;
         $_REQUEST["dlv_order_based"]  = 0;
         $_REQUEST["dlv_taxes"]        = (int)$_REQUEST["dlv_taxes"];
         $_REQUEST["dlv_weightprice_netto"] = 0.00;
   }


   //----------------------------------------------------------------------------------
   $_REQUEST["dlv_weightprice_netto"] = (float)$_REQUEST["dlv_weightprice_netto"];
   $dlv_num = createTransactionNumber($CON, $_REQUEST["company_id"], "shipmentsell");

   //----------------------------------------------------------------------------------
   $invccfg = getCompanyInvoiceConfig($CON, $_REQUEST["company_id"]);
   if((int)$invccfg["company_invc_mode"])
      $dlv_docnum = $dlv_num;
   else
      $dlv_docnum = "";
   
   $sql = " insert into orders_delivery
            (dlv_num, dlv_cust_id, dlv_delivery_date, dlv_order_based, dlv_paymentid,
             dlv_order_id, dlv_company_id, dlv_shop_id, dlv_taxes, dlv_cust_delivid,
             dlv_transportid, dlv_mode, dlv_supplier_id, dlv_parent_guaid, dlv_weightprice_netto,
             dlv_crtdat, dlv_crtusr, dlv_oc_number, dlv_oc_dat, dlv_annotation, dlv_annotation_intern,
             dlv_docnum, dlv_discount_perc, dlv_discount_amt, dlv_isinvcbrutto)
            VALUES
            ('{$dlv_num}', {$_REQUEST["cust_id_0"]}, {$_REQUEST["dlv_delivery_date"]},
              {$_REQUEST["dlv_order_based"]}, {$_REQUEST["dlv_paymentid"]},
              {$_REQUEST["dlv_order_id"]}, {$_REQUEST["company_id"]}, {$_REQUEST["shop_id"]},
              {$_REQUEST["dlv_taxes"]}, {$_REQUEST["dlv_cust_delivid"]}, {$_REQUEST["dlv_transportid"]},
              {$dlv_mode}, {$_REQUEST["supplier_id_0"]}, {$dlv_parent_guaid},
              {$_REQUEST["dlv_weightprice_netto"]}, {$currtme}, {$_SESSION["user_id"]},
              '{$_REQUEST["dlv_oc_number"]}', {$_REQUEST["dlv_oc_dat"]}, '{$_REQUEST["dlv_annotation"]}',
              '{$_REQUEST["dlv_annotation_intern"]}', '{$dlv_docnum}', {$_REQUEST["dlv_discount_perc"]},
              {$_REQUEST["dlv_discount_amt"]}, {$_REQUEST["dlv_isinvcbrutto"]})";
   $res = $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if($res)
   {
      $sql = " select MAX(id) 'id'
               from orders_delivery
               where
               dlv_crtusr = {$_SESSION["user_id"]}";
      $thisid = $CON->select($sql);
      $_REQUEST["id"] = (int)$thisid[0]["id"];

      //----------------------------------------------------------------------------------
      if($_REQUEST["dlv_order_based"] && $_REQUEST["dlv_order_id"] && !(int)$_REQUEST["dlv_order_noitemload"])
      {
         $sql = " select *
                  from orders_items
                  where
                  req_id = {$_REQUEST["dlv_order_id"]}
                  order by item_pos asc";
         $ordpos = $CON->select($sql);

         //----------------------------------------------------------------------------------
         $poscounter = 0;
         for($x = 0; $x < count($ordpos) && $ordpos != false; $x++)
         {
            $sql_item_amount           = sprintf("%.2f", $ordpos[$x]["item_amount"] - $ordpos[$x]["item_amount_shipped"]);
            $sql_item_amount_shipped   = $sql_item_amount;
            if($sql_item_amount < 0 || (int)$ordpos[$x]["item_amount_shipped_stop"])
            {
               $sql_item_amount           = 0.00;
               $sql_item_amount_shipped   = 0.00;
            }

            $ordpos[$x]["item_desc"] = trim(addslashes($ordpos[$x]["item_desc"]));
            $item_charges_act = itemHasChargeAct($CON, $ordpos[$x]["item_id"], $ordpos[$x]["item_type"]);
            
            $sql = " insert into orders_delivery_items
                     (dlv_id, item_id, item_pos, item_amount, item_amount_shipped, item_type, item_order_pos,
                      item_sellprice_brutto, item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_netto_dsc,
                      item_sellprice_taxes, item_discount, item_discount_type, item_pcat_dsc_act, item_pcat_dsc1,
                      item_pcat_dsctype1, item_pcat_dsc2, item_pcat_dsctype2, item_pcat_dsc3, item_pcat_dsctype3,
                      item_pcat_dsc4, item_pcat_dsctype4, item_vol_act, item_vol_dsc, item_vol_dsctype, item_value_act,
                      item_value_dsc, item_value_dsctype, item_desc, item_promid, item_promdsc, item_charges_act)
                     VALUES
                     ({$_REQUEST["id"]}, {$ordpos[$x]["item_id"]}, {$poscounter}, {$sql_item_amount}, {$sql_item_amount_shipped},
                      '{$ordpos[$x]["item_type"]}', {$ordpos[$x]["item_pos"]},
                      {$ordpos[$x]["item_sellprice_brutto"]}, {$ordpos[$x]["item_sellprice_taxes_perc"]}, {$ordpos[$x]["item_sellprice_netto"]}, {$ordpos[$x]["item_sellprice_netto_dsc"]},
                      {$ordpos[$x]["item_sellprice_taxes"]}, {$ordpos[$x]["item_discount"]}, {$ordpos[$x]["item_discount_type"]}, {$ordpos[$x]["item_pcat_dsc_act"]}, {$ordpos[$x]["item_pcat_dsc1"]},
                      {$ordpos[$x]["item_pcat_dsctype1"]}, {$ordpos[$x]["item_pcat_dsc2"]}, {$ordpos[$x]["item_pcat_dsctype2"]}, {$ordpos[$x]["item_pcat_dsc3"]}, {$ordpos[$x]["item_pcat_dsctype3"]},
                      {$ordpos[$x]["item_pcat_dsc4"]}, {$ordpos[$x]["item_pcat_dsctype4"]}, {$ordpos[$x]["item_vol_act"]}, {$ordpos[$x]["item_vol_dsc"]}, {$ordpos[$x]["item_vol_dsctype"]},
                      {$ordpos[$x]["item_value_act"]}, {$ordpos[$x]["item_value_dsc"]}, {$ordpos[$x]["item_value_dsctype"]}, '{$ordpos[$x]["item_desc"]}',
                      {$ordpos[$x]["item_promid"]}, {$ordpos[$x]["item_promdsc"]}, {$item_charges_act})";
            $CON->no_result($sql);
            $poscounter++;
         }

         //----------------------------------------------------------------------------------
         $posdata = getOrderDeliveryPos($CON, $_REQUEST["id"]);
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
            recalcOrderItem($CON, $_REQUEST["id"], $posdata[$x]["item_id"], $posdata[$x]["item_pos"], false, "DELIVERY");
         recalcOrder($CON, $_REQUEST["id"], "DELIVERY");
      }

      //----------------------------------------------------------------------------------
      if($dlv_isguarantee)
      {
         $poscounter = 0;
         foreach($xguapos AS $xguarow)
         {
            $item_charges_act = itemHasChargeAct($CON, $xguarow["item_id"], $xguarow["item_type"]);
            
            $sql = " insert into orders_delivery_items
                     (dlv_id, item_id, item_pos, item_amount_shipped, item_type,
                      item_desc, item_discount, item_pcat_dsc_act, item_vol_act, item_value_act,
                      item_charges_act)
                     VALUES
                     ({$_REQUEST["id"]}, {$xguarow["item_id"]}, {$poscounter}, {$xguarow["item_amount"]},
                     '{$xguarow["item_type"]}', '{$xguarow["item_desc"]}', 0, 0, 0, 0, {$item_charges_act})";
            $CON->no_result($sql);
            $poscounter++;
         }
         //----------------------------------------------------------------------------------
         $posdata = getOrderDeliveryPos($CON, $_REQUEST["id"]);
         for($x = 0; $x < count($posdata) && $posdata != false; $x++)
            recalcOrderItem($CON, $_REQUEST["id"], $posdata[$x]["item_id"], $posdata[$x]["item_pos"], false, "DELIVERY");
         recalcOrder($CON, $_REQUEST["id"], "DELIVERY");
      }

      ?>
      <script language="Javascript">
         location.href = 'index.php?mid=748&exec=edit&subexec=edit&id=<?=$_REQUEST["id"]?>';
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
            from orders_delivery 
            where
            id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   if((int)$_REQUEST["dlv_paymentid"] != (int)$headdata["dlv_paymentid"])
      $itemfullupdate = true;
   else
      $itemfullupdate = false;

   //----------------------------------------------------------------------------------
   $_REQUEST["dlv_annotation"]         = trim(addslashes($_REQUEST["dlv_annotation"]));
   $_REQUEST["dlv_annotation_intern"]  = trim(addslashes($_REQUEST["dlv_annotation_intern"]));
   $_REQUEST["dlv_oc_number"]          = trim(addslashes($_REQUEST["dlv_oc_number"]));
   $_REQUEST["dlv_docnum"]             = trim(addslashes($_REQUEST["dlv_docnum"]));
   $_REQUEST["dlv_transportid"]        = (int)$_REQUEST["dlv_transportid"];
   $_REQUEST["dlv_cust_delivid"]       = (int)$_REQUEST["dlv_cust_delivid"];
   $_REQUEST["dlv_paymentid"]          = (int)$_REQUEST["dlv_paymentid"];
   $_REQUEST["dlv_copydateinvc"]       = (int)$_REQUEST["dlv_copydateinvc"];
   $_REQUEST["dlv_delivery_date"]      = trim(addslashes($_REQUEST["dlv_delivery_date"]));
   $_REQUEST["dlv_delivery_date"]      = explode(".", $_REQUEST["dlv_delivery_date"]);
   $_REQUEST["dlv_delivery_date"]      = (int)mktime(0, 0, 0, $_REQUEST["dlv_delivery_date"][1], $_REQUEST["dlv_delivery_date"][0], $_REQUEST["dlv_delivery_date"][2]);
   $_REQUEST["dlv_oc_dat"]             = trim(addslashes($_REQUEST["dlv_oc_dat"]));
   $_REQUEST["dlv_oc_dat"]             = explode(".", $_REQUEST["dlv_oc_dat"]);
   $_REQUEST["dlv_oc_dat"]             = (int)mktime(date('H'), date('i'), date('s'), $_REQUEST["dlv_oc_dat"][1], $_REQUEST["dlv_oc_dat"][0], $_REQUEST["dlv_oc_dat"][2]);
   $_REQUEST["dlv_discount_perc"]      = getPrice($_REQUEST["dlv_discount_perc"],2);
   $_REQUEST["dlv_discount_amt"]       = getPrice($_REQUEST["dlv_discount_amt"]);
   $_REQUEST["dlv_bultos"]             = (int)$_REQUEST["dlv_bultos"];
   $_REQUEST["dlv_externprod_act"]     = (int)$_REQUEST["dlv_externprod_act"];
   $_REQUEST["dlv_externprod_ccnum"]   = trim(addslashes($_REQUEST["dlv_externprod_ccnum"]));
   $_REQUEST["dlv_externprod_ccid"]    = 0;

   $_REQUEST["dlv_vehiculo_id"]         = (int)$_REQUEST["dlv_vehiculo_id"];
   $_REQUEST["dlv_chofer_id"]           = (int)$_REQUEST["dlv_chofer_id"];
   $_REQUEST["dlv_rango_id"]            = (int)$_REQUEST["dlv_rango_id"];
   $_REQUEST["dlv_comuna_origen"]       = (int)$_REQUEST["dlv_comuna_origen"];
   $_REQUEST["dlv_comuna_destino"]      = (int)$_REQUEST["dlv_comuna_destino"];
   $_REQUEST["dlv_direccion_origen"]    = trim(addslashes($_REQUEST["dlv_direccion_origen"]));
   $_REQUEST["dlv_direccion_destino"]   = trim(addslashes($_REQUEST["dlv_direccion_destino"]));

   
   if($_REQUEST["dlv_status"] == "2")
   {
      $_REQUEST["dlv_status"] = "1";
      $_REQUEST["dlv_docnum"] = "PENDIENTE_SII";
      $final = true;
   }

   if((int)$_REQUEST["dlv_externprod_act"])
   {
      $sql = " select id
               from orders
               where
               req_status > 0 and
               req_number = '{$_REQUEST["dlv_externprod_ccnum"]}' and
               req_number != ''";
      $refordid = $CON->select($sql);
      $refordid = (int)$refordid[0]["id"];
      $_REQUEST["dlv_externprod_ccid"] = $refordid;
      if(!$refordid)
      {  ?>
         <script language="Javascript">
            alert('Advertencia: Numero C.C. no existe');
         </script>
         <?php
      }
   }

   //----------------------------------------------------------------------------------
   $sql = " update orders_delivery
            set
            dlv_annotation          = '{$_REQUEST["dlv_annotation"]}',
            dlv_annotation_intern   = '{$_REQUEST["dlv_annotation_intern"]}',
            dlv_docnum              = '{$_REQUEST["dlv_docnum"]}',
            dlv_transportid         = {$_REQUEST["dlv_transportid"]},
            dlv_delivery_date       = {$_REQUEST["dlv_delivery_date"]},
            dlv_cust_delivid        = {$_REQUEST["dlv_cust_delivid"]},
            dlv_paymentid           = {$_REQUEST["dlv_paymentid"]},
            dlv_copydateinvc        = {$_REQUEST["dlv_copydateinvc"]},
            dlv_oc_dat              = {$_REQUEST["dlv_oc_dat"]},
            dlv_oc_number           = '{$_REQUEST["dlv_oc_number"]}',
            dlv_discount_perc       = {$_REQUEST["dlv_discount_perc"]},
            dlv_discount_amt        = {$_REQUEST["dlv_discount_amt"]},
            dlv_bultos              = {$_REQUEST["dlv_bultos"]},
            dlv_externprod_act      = {$_REQUEST["dlv_externprod_act"]},
            dlv_externprod_ccnum    = '{$_REQUEST["dlv_externprod_ccnum"]}',
            dlv_externprod_ccid     = {$_REQUEST["dlv_externprod_ccid"]},
            dlv_vehiculo_id         = {$_REQUEST["dlv_vehiculo_id"]},
            dlv_chofer_id           = {$_REQUEST["dlv_chofer_id"]},
            dlv_rango_id            = {$_REQUEST["dlv_rango_id"]},
            dlv_comuna_origen       = {$_REQUEST["dlv_comuna_origen"]},
            dlv_comuna_destino      = {$_REQUEST["dlv_comuna_destino"]},
            dlv_direccion_origen    = '{$_REQUEST["dlv_direccion_origen"]}',
            dlv_direccion_destino   = '{$_REQUEST["dlv_direccion_destino"]}',
            dlv_updusr              = {$_SESSION["user_id"]},
            dlv_upddat              = {$currtme}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);


   clearRelItems($CON, $_REQUEST["id"], "ordersdelivery");

   //----------------------------------------------------------------------------------
   $_TABLENAME    = "orders_delivery_items";
   $_TABLENAMEHD  = "orders_delivery";
   $_COLPREFIX    = "dlv";
   $_AMOUNTFIELD  = "item_amount_shipped";
                  
   //----------------------------------------------------------------------------------
   $poscounter = 0;
   foreach(array_keys($_REQUEST) AS $reqkey)
   {
      if(strpos($reqkey, "item_id_") !== false && strpos($reqkey, "item_id_") == 0)
      {
         $idx           = substr($reqkey, strrpos($reqkey, "_") +1);
         $existing_id   = (int)$_REQUEST["existing_id_{$idx}"];
         $existing_pos  = (int)$_REQUEST["existing_pos_{$idx}"];

         $_REQUEST["item_amount_{$idx}"]         = getPrice($_REQUEST["item_amount_{$idx}"],2);
         $_REQUEST["item_amount_shipped_{$idx}"] = getPrice($_REQUEST["item_amount_shipped_{$idx}"],2);
         $_REQUEST["item_order_pos_{$idx}"]      = (int)$_REQUEST["item_order_pos_{$idx}"];
         $_REQUEST["item_stid_{$idx}"]           = (int)$_REQUEST["item_stid_{$idx}"];

         $itemvalues       = explode("#", $_REQUEST["item_id_{$idx}"]);
         $sql_id           = (int)$itemvalues[0];
         $sql_type         = $itemvalues[1];
         $sql_charges_act  = (int)$itemvalues[4];

         if($headdata["dlv_parent_guaid"] > 0)
            $_REQUEST["item_stid_{$idx}"] = 0;
               
         if($idx < 9000)
         {
            if($_REQUEST["item_id_{$idx}"] != "" && ($_REQUEST["item_amount_shipped_{$idx}"] > 0 || $_REQUEST["item_order_pos_{$idx}"] > -1))
            {
               //----------------------------------------------------------------------------------
               if((int)$headdata["dlv_taxes"])
                  $sql_taxesperc = getPrice($_REQUEST["item_sellprice_taxes_perc_{$idx}"], 2);
               else
                  $sql_taxesperc = 0;

               //----------------------------------------------------------------------------------
               /*
               $sql_sellnetto = getPrice($_REQUEST["item_sellprice_netto_{$idx}"]);
               $sql_taxes     = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto / 100 * $sql_taxesperc);
               $sql_sellprice = (float)sprintf("%.{$_SESSION["_CONF"]["conf_number_decimal_places"]}f", $sql_sellnetto + $sql_taxes);
               */

               //----------------------------------------------------------------------------------
               if(!(int)$headdata["dlv_isinvcbrutto"])
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

               //----------------------------------------------------------------------------------
               if((int)$_REQUEST["manual_pos_{$idx}"])
                  $_REQUEST["item_desc_{$idx}"] = trim(addslashes($_REQUEST["item_desc_{$idx}"]));
               else
                  $_REQUEST["item_desc_{$idx}"] = "";
               
               //----------------------------------------------------------------------------------
               if($existing_id)
               {
                  $sql = " update orders_delivery_items
                           set
                           item_amount_shipped        = {$_REQUEST["item_amount_shipped_{$idx}"]},
                           item_sellprice_brutto      = {$sql_sellprice},
                           item_sellprice_taxes_perc  = {$sql_taxesperc},
                           item_sellprice_netto       = {$sql_sellnetto},
                           item_sellprice_taxes       = {$sql_taxes},
                           item_pos                   = {$poscounter},
                           item_st_id                 = {$_REQUEST["item_stid_{$idx}"]},
                           item_desc                  = '{$_REQUEST["item_desc_{$idx}"]}'
                           where
                           dlv_id   = {$_REQUEST["id"]} and
                           item_id  = {$existing_id} and
                           item_pos = {$existing_pos}";
                  $CON->no_result($sql);

                  recalcOrderItem($CON, $_REQUEST["id"], $existing_id, $poscounter, $itemfullupdate, "DELIVERY");

                  renameItemChargePosUsed($CON, $_REQUEST["id"], "ordersdelivery", $existing_pos, $poscounter);

                  updateItemChargeDataUsed($CON, $_REQUEST["id"], "ordersdelivery", $existing_id, $sql_type, $poscounter,
                                          $headdata["dlv_company_id"], $headdata["dlv_shop_id"],
                                          $_REQUEST["item_charges_data_{$idx}"]);

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
                  //----------------------------------------------------------------------------------
                  $sql = " select dlv_order_id
                           from orders_delivery
                           where
                           id = {$_REQUEST["id"]}";
                  $parttemp = $CON->select($sql);
                  $part_req_id = $parttemp[0]["dlv_order_id"];

                  $item_order_pos   = -1;
                  $xsql_item_amount = 0;
                  if($part_req_id > 0)
                  {
                     $temppos = getOrderPos($CON, $part_req_id);
                     foreach($temppos AS $temprow)
                     {
                        if($temprow["item_id"] == $sql_id && $temprow["item_type"] == $sql_type)
                        {
                           $item_order_pos   = $temprow["item_pos"];
                           $xsql_item_amount = (float)sprintf("%.2f", $temprow["item_amount"] - $temprow["item_amount_shipped"]);
                           if($xsql_item_amount < 0 || (int)$temprow["item_amount_shipped_stop"])
                              $xsql_item_amount = 0.00;
                           $_REQUEST["item_amount_{$idx}"] = $xsql_item_amount;
                        }
                     }
                  }
                  
                  $sql = " insert into orders_delivery_items
                           (dlv_id, item_id, item_pos, item_amount, item_amount_shipped, item_type, item_sellprice_brutto,
                            item_sellprice_taxes_perc, item_sellprice_netto, item_sellprice_taxes, item_order_pos, item_st_id,
                            item_desc, item_charges_act)
                           VALUES
                           ({$_REQUEST["id"]}, {$sql_id}, {$poscounter}, {$_REQUEST["item_amount_{$idx}"]}, {$_REQUEST["item_amount_shipped_{$idx}"]},
                            '{$sql_type}', {$sql_sellprice}, {$sql_taxesperc}, {$sql_sellnetto}, {$sql_taxes}, {$item_order_pos},
                            {$_REQUEST["item_stid_{$idx}"]}, '{$_REQUEST["item_desc_{$idx}"]}', {$sql_charges_act})";
                  $CON->no_result($sql);

                  recalcOrderItem($CON, $_REQUEST["id"], $sql_id, $poscounter, true, "DELIVERY");

                  updateItemChargeDataUsed($CON, $_REQUEST["id"], "ordersdelivery", $sql_id, $sql_type, $poscounter,
                                          $headdata["dlv_company_id"], $headdata["dlv_shop_id"],
                                          $_REQUEST["item_charges_data_{$idx}"]);
               }
               $poscounter++;
            }
            elseif($existing_id)
            {
               $sql = " delete from orders_delivery_items
                        where
                        dlv_id   = {$_REQUEST["id"]} and
                        item_id  = {$existing_id} and
                        item_pos = {$existing_pos}";
               $CON->no_result($sql);

               clearItemChargeDataUsed($CON, $_REQUEST["id"], "ordersdelivery", $existing_pos);
            }
         }
         else
         {
            if($_REQUEST["item_id_{$idx}"] != "" && $_REQUEST["tran_amount_{$idx}"] > 0 && $sql_type == "item")
            {
               addRelItem($CON, $_REQUEST["id"], "ordersdelivery", $sql_id, $_REQUEST["tran_amount_{$idx}"], $_REQUEST["item_stid_{$idx}"], $idx);
            }
         }
      }
   }

   //----------------------------------------------------------------------------------
   $posdata = getOrderDeliveryPos($CON, $_REQUEST["id"], $_REQUEST["setPosOrder"]);

   //----------------------------------------------------------------------------------
   $_REQUEST["dlv_weightprice_netto"] = getPrice($_REQUEST["dlv_weightprice_netto"]);
   
   $sql = " update {$_TABLENAMEHD}
            set
            {$_COLPREFIX}_weightprice_netto = {$_REQUEST["dlv_weightprice_netto"]}
            where
            id = {$_REQUEST["id"]}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   if($final)
   {
      $sql = " update orders_delivery
               set
               dlv_status  = 1,
               dlv_docnum  = 'PENDIENTE_SII'
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);
      $ret = bookOrdersDelivery($CON, $_REQUEST["id"]);
   }

   //----------------------------------------------------------------------------------
   if($headdata["dlv_status"] >= 2 && $_REQUEST["dlv_status"] == 1)
   {
      delOrdersDelivery($CON, $_REQUEST["id"]);

      $sql = " update {$_TABLENAMEHD}
               set
               {$_COLPREFIX}_status = 1
               where
               id = {$_REQUEST["id"]}";
      $CON->no_result($sql);

      if($_REQUEST["cancelDoc"] == "1")
      {
         $sql = " update {$_TABLENAMEHD}
                  set
                  {$_COLPREFIX}_status = 5
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }

      if($_REQUEST["cancelDoc"] == "2")
      {
         $sql = " update {$_TABLENAMEHD}
                  set
                  {$_COLPREFIX}_status = 1
                  where
                  id = {$_REQUEST["id"]}";
         $CON->no_result($sql);
      }

      $savemsg = getSaveMessage(true);
   }

   recalcOrder($CON, $_REQUEST["id"], "DELIVERY");
   
   $savemsg = getSaveMessage(true);
}

//----------------------------------------------------------------------------------
if($_REQUEST["subexec"] == "edit" && $_REQUEST["clearOrder"] == "1")
{
   $currtme = time();
   $sql = " select dlv_order_id
            from orders_delivery
            where
            id = {$_REQUEST["id"]}";
   $reqid = $CON->select($sql);
   $reqid = (int)$reqid[0]["dlv_order_id"];

   if($reqid)
   {
      $sql = " update orders
               set
               req_order_shipped   = 1,
               req_status          = 4,
               req_updusr          = {$_SESSION["user_id"]},
               req_upddat          = {$currtme}
               where
               id = {$reqid}";
      $CON->no_result($sql);
   }
}

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.req_number, t3.req_order_shipped, t4.company_short, t5.shop_name, t8.trans_name, t9.pay_title,
                t6.user_firstname 'upd_firstname', t6.user_lastname 'upd_lastname',
                t7.user_firstname 'crt_firstname', t7.user_lastname 'crt_lastname'
         from orders_delivery t1
         LEFT OUTER JOIN orders t3           ON t1.dlv_order_id      = t3.id
         LEFT OUTER JOIN company_data t4     ON t1.dlv_company_id    = t4.id
         LEFT OUTER JOIN company_shops t5    ON t1.dlv_shop_id       = t5.id
         LEFT OUTER JOIN user t6             ON t1.dlv_updusr        = t6.id
         LEFT OUTER JOIN user t7             ON t1.dlv_crtusr        = t7.id
         LEFT OUTER JOIN transports t8       ON t1.dlv_transportid   = t8.id
         LEFT OUTER JOIN payments t9         ON t1.dlv_paymentid     = t9.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];
   
//----------------------------------------------------------------------------------
if($headdata["dlv_mode"] <= 2)
{
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            LEFT OUTER JOIN provincias t5 ON t1.cust_provinciaid  = t5.id
            where
            t1.id = {$headdata["dlv_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];

   //----------------------------------------------------------------------------------
   if($headdata["dlv_status"] >= 2)
   {
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
               from customer_deliveryaddr t1
               LEFT OUTER JOIN country t2 ON t1.delivery_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.delivery_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.delivery_comunaid  = t4.id
               where
               t1.id = {$headdata["dlv_cust_delivid"]}
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
               t1.cust_id = {$headdata["dlv_cust_id"]} and
               t1.delivery_status = 1
               order by t1.id asc";
      $deliveryaddrs = $CON->select($sql);
   }
   $addrtitle  = "Cliente";
   $changelink = "index.php?mid=628&exec=edit&id={$customer["id"]}&registerback={$_REQUEST["mid"]}-{$_REQUEST["id"]}";
}
else
{
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
            from supplier t1
            LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
            LEFT OUTER JOIN provincias t5 ON t1.supp_provinciaid  = t5.id
            where
            t1.id = {$headdata["dlv_supplier_id"]}";
   $supplier = $CON->select($sql);
   $supplier = $supplier[0];
   
   $customer["cust_name"]     = $supplier["supp_company"];
   $customer["cust_rut"]      = $supplier["supp_rut"];
   $customer["cust_street"]   = $supplier["supp_street"];
   $customer["cust_phone"]    = $supplier["supp_phone"];
   $customer["name"]          = $supplier["name"];
   $customer["cust_fax"]      = $supplier["supp_fax"];
   $customer["nombre"]        = $supplier["nombre"];
   $customer["cust_email"]    = $supplier["supp_email"];
   $customer["country_name"]  = $supplier["country_name"];
   $customer["pro_name"]      = $supplier["pro_name"];

   $addrtitle  = "Proveedor";
   $changelink = "index.php?mid=497&exec=edit&id={$supplier["id"]}&registerback={$_REQUEST["mid"]}-{$_REQUEST["id"]}";
}
//-------------------------------------------------------------------------------------------------
$sql = "select id, nombre from comunas";
$comunas = $CON->select($sql);
//--------------------------------------------------------------------------------------------------
// $payments   = getPayments($CON, $headdata["dlv_shop_id"]);
$transports = getTransports($CON);
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
//---------------------------------------------------------------------------------------------------
$sql = "select * from payments where pay_cod_contable != '' and pay_status > 0 order by pay_title";
$payments = $CON->select($sql);
//---------------------------------------------------------------------------------------------------
$posdata    = getOrderDeliveryPos($CON, $_REQUEST["id"], $_REQUEST["setPosOrder"]);
//---------------------------------------------------------------------------------------------------
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

if((int)$headdata["dlv_status"] > 1)
{
   $rdlo       = "readonly";
   $dabl       = "disabled";
   $rowcount   = count($posdata);
}
//----------------------------------------------------------------------------------
if($_SESSION["xordersdlv"]["fullcust"] == "1")
   $cdatastyle = 'style="border-top-width:3px;border-top-style:solid"';

$showRelItems = false;
foreach($posdata AS $posrow)
{
   if((int)$posrow["item_sell_withotheritems"])
      $showRelItems = true;
}
$proms = getActivePromotions($CON, $headdata["dlv_shop_id"]);

//----------------------------------------------------------------------------------
$_INVCCFG = getCompanyInvoiceConfig($CON, $headdata["dlv_company_id"]);

if($_REQUEST["id"] != "" && $headdata["dlv_status"] == 1 && $_INVCCFG["company_invc_itf"] == "BCNCONS")
{
   $dlv_docnum = (int)createSiiNumber($CON, $headdata["dlv_company_id"], $headdata["dlv_shop_id"], "itf_dlv", true);

   if(!$dlv_docnum)
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
<style type="text/css"><!-- @import url(./libs/jscripts/datepicker/datepicker.css); //--></style>
<script language="JavaScript" src="./libs/jscripts/datepicker/datepicker.js"></script>
<script language="JavaScript">
   document.all.idx_status_msg.innerHTML = "<?=$savemsg?>";

   function updateItemStorehouses(idx, itemid, itemtype)
   {
      document.all.idxifrsrc.src='./libs/modules/orders_delivery/searchstorehouses.php?dlvid=<?=$headdata["id"]?>&rowcount=' +idx +'&itemid=' +itemid +'&itemtype=' +itemtype;

      var valarr  = $('#item_id_' +idx).val().split('#');
      switchShpChargeMode(idx, valarr);
   }

   function detectEvent (event, rowcount, dlvid)
   {
      var xurl = './libs/modules/orders_delivery/searchitem.fancy.php?rowcount=' +rowcount + '&dlvid=' +dlvid;
      var keyCode = ('which' in event) ? event.which : event.keyCode;
      if(keyCode == 112)
         showFancybox(xurl, 'iframe', 1000, 450, 'auto');
   }
</script>
<form action="index.php" method="post" name="form_shppos" id="form_shppos"
<?php
if($rdlo != "")
   echo "onsubmit='return false'";
else
{  ?>
   onsubmit="return checkform(new Array(this.dlv_docnum, this.dlv_delivery_date));"
   <?php
}
?>>
<input type="hidden" name="exec" value="edit">
<input type="hidden" name="subexec" value="save">
<input type="hidden" name="id" value="<?=$_REQUEST["id"]?>">
<input type="hidden" name="mid" value="<?=$_REQUEST["mid"]?>">
<input type="hidden" name="dlv_status" value="1">
<input type="hidden" name="cancelDoc" value="">
<input type="hidden" name="showDiscounts" value="<?=$_REQUEST["showDiscounts"]?>">
<input type="hidden" name="user_pricesell_perm" value="<?=$_REQUEST["user_pricesell_perm"]?>">
<input type="hidden" name="user_docopen_perm" value="<?=$_REQUEST["user_docopen_perm"]?>">
<input type="hidden" name="setPosOrder" value="">
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
      Guia de despacho: <?=getOrdersDeliveryDlvMode($CON, $headdata["dlv_mode"])?>
   </td>
</tr>
<tr>
   <td class="content_rowl">Número</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear" width="120"><?=$headdata["dlv_num"]?></td>
         <td class="content_row_clear" width="120">
            <nobr>
            OC
            <input type="text" class="text" style="width:90px" name="dlv_oc_number" id="dlv_oc_number" <?=$rdlo?>
            value="<?=$headdata["dlv_oc_number"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
            </nobr>
         </td>
         <td class="content_row_clear">
            <nobr>
            <input type="text" style="width:80px" id="dlv_oc_dat" name="dlv_oc_dat" <?=$rdlo?>
            class="text <?php if($rdlo == "") echo "format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"?>"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            value="<?php if($headdata["dlv_oc_dat"] > 0) echo date('d.m.Y', $headdata["dlv_oc_dat"])?>">
            </nobr>
         </td>
      </tr>
      </table>
   </td>
   <td class="content_rowl">Confirmación de compra</td>
   <td class="content_row"><?=$headdata["req_number"]?>&nbsp;</td>
</tr>
<tr>
   <td class="content_rowl">Empresa</td>
   <td class="content_row"><?=$headdata["company_short"]?></td>
   <td class="content_rowl">Sucursal</td>
   <td class="content_row"><?=$headdata["shop_name"]?></td>
</tr>
<tbody>
<tr>
   <td class="content_rowl" <?=$cdatastyle?>><?=$addrtitle?></td>
   <td class="content_row" <?=$cdatastyle?>>
      <a href="javascript:void(0)" style="text-decoration:none;color:black"
      onclick="location.href='index.php?mid=<?=$_REQUEST["mid"]?>&exec=edit&subexec=edit&id=<?=$headdata["id"]?>&showfullcust=<?php
      if($_SESSION["xordersdlv"]["fullcust"] == "") echo "1"; else echo "0"?>'"> <b><?=$customer["cust_name"]?></b></a></td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$_LANG["MODULE"]["ORDER"][13]?></td>
   <td class="content_row" <?=$cdatastyle?>><b><?=$customer["cust_rut"]?></b>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xordersdlv"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Dirección</td>
   <td class="content_row"><?=$customer["cust_street"]?>&nbsp;</td>
   <td class="content_rowl">Teléfono</td>
   <td class="content_row"><?if($customer["cust_phone"] != "") echo $customer["cust_phone"];?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xordersdlv"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Región</td>
   <td class="content_row"><?=$customer["name"]?>&nbsp;</td>
   <td class="content_rowl">Whatsapp</td>
   <td class="content_row"><?=$customer["cust_fax"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xordersdlv"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">Comuna</td>
   <td class="content_row"><?=$customer["pro_name"]?> - <?=$customer["nombre"]?>&nbsp;</td>
   <td class="content_rowl"><?=$_LANG["MODULE"]["ORDER"][12]?></td>
   <td class="content_row"><?=$customer["cust_email"]?>&nbsp;</td>
</tr>
<tr <?php if($_SESSION["xordersdlv"]["fullcust"] == "") echo "style='display:none'"?>>
   <td class="content_rowl">País</td>
   <td class="content_row"><?=$customer["country_name"]?>&nbsp;</td>
   <td class="content_rowl">Opciones</td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0">
      <tr>
         <td class="content_row_clear">
            <?php
            printButton("Cambiar datos del {$addrtitle}", "postnav", $changelink, "", "disk-black", 180);
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
   if($headdata["dlv_status"] == 1 && date('d.m.Y', $headdata["dlv_delivery_date"]) != date('d.m.Y'))
   {
      $bcss1   = "<b class='msg_save_err'><blink>";
      $bcss2   = "</blink></b>";
      $tcss    = "border:2px solid red";
   }
   ?>
   <td class="content_rowl" <?=$cdatastyle?> height="31">Numero de guia *</td>
   <td class="content_row" <?=$cdatastyle?>>
      <?php
      if((int)$_INVCCFG["company_invc_mode"])
      {  ?>
         <input type="hidden" name="dlv_docnum" value="<?=$headdata["dlv_docnum"]?>">
         <?php
         if($headdata["dlv_status"] > 1)
         {  ?>
            <div id="idx_sii_refresh"></div>
            <script language="JavaScript">
               function reloadSiiState()
               {
                  $.get('/libs/modules/invoices_sell/get.siistatus.jquery.php?mode=orders_delivery&id=<?=$_REQUEST["id"]?>', '', function(data)
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
            echo $headdata["dlv_docnum"]. " (Temporario)";
      }
      else
      {  ?>
         <input type="text" style="width:350px" name="dlv_docnum" class="text" <?=$rdlo?>
         onfocus="markfield(this,0)" onblur="markfield(this,1)" value="<?=$headdata["dlv_docnum"]?>">
         <?php
      }
      ?>
   </td>
   <td class="content_rowl" <?=$cdatastyle?>><?=$bcss1?>Fecha despacho *<?=$bcss2?></td>
   <td class="content_row" <?=$cdatastyle?>>
      <input type="text" style="width:80px;<?=$tcss?>" id="dlv_delivery_date" name="dlv_delivery_date" <?=$rdlo?>
      class="text format-d-m-y divider-dot highlight-days-67 no-locale no-transparency"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?=date('d.m.Y', $headdata["dlv_delivery_date"])?>">
   </td>
</tr>
<tr>
   <td class="content_rowl">Transportista</td>
   <td class="content_row">
      <nobr>
      <select class="text" style="width:265px" name="dlv_transportid" id="dlv_transportid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)" <?=$dabl?>>
         <?php
         if($dabl == "")
         {  ?>
            <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
            <?php
            foreach($transports as $transport)
            {  ?>
               <option value="<?=$transport["id"]?>"
               <?php if($transport["id"] == $headdata["dlv_transportid"]) echo "selected"?>><?=$transport["trans_name"]?></option>
               <?php
            }
         }
         else
         {  ?>
            <option value="<?=$headdata["dlv_transportid"]?>"><?=$headdata["trans_name"]?></option>
            <?php
         }
         ?>
      </select>
      &nbsp;Bultos:
      <input type="text" style="width:35px;" id="dlv_bultos" name="dlv_bultos" <?=$rdlo?> class="text"
      onfocus="markfield(this,0)" onblur="markfield(this,1)"
      value="<?php if((int)$headdata["dlv_bultos"]) echo $headdata["dlv_bultos"]?>">
      </nobr>
   </td>
   <td class="content_rowl">Estado</td>
   <td class="content_row">
      <?php
      $statimg = "";
      switch((int)$headdata["dlv_status"])
      {
         case 1: $statimg = "red_active.gif"; break;
         case 2: $statimg = "green_active.gif"; break;
         case 3: $statimg = "blue_active.gif"; break;
         case 4: $statimg = "blue_active.gif"; break;
         case 5: $statimg = "gray_active.gif"; break;
      }
      ?>
      <img class="select" src="./images/content/<?=$statimg?>" style="vertical-align:bottom">
      <?=getShipmentStatus($headdata["dlv_status"], true)?>
   </td>
</tr>

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

document.getElementById('dlv_transportid').addEventListener('change', function() {
    var transId = this.value;

    // Reiniciar y filtrar cada combo dependiente
    filtrarOpciones('dlv_vehiculo_id', transId, true);
    filtrarOpciones('dlv_chofer_id', transId, true);
    filtrarOpciones('dlv_rango_id', transId, true);
});

window.addEventListener('DOMContentLoaded', function() {
    var transSelect = document.getElementById('dlv_transportid');
    var transId = transSelect.value;

    if (transId) {
        // Filtrar pero NO resetear (mantener valores grabados)
        filtrarOpciones('dlv_vehiculo_id', transId, false);
        filtrarOpciones('dlv_chofer_id', transId, false);
        filtrarOpciones('dlv_rango_id', transId, false);
    } else {
        ['dlv_vehiculo_id','dlv_chofer_id','dlv_rango_id'].forEach(id => {
            var sel = document.getElementById(id);
            Array.from(sel.options).forEach(opt => {
                if (opt.value !== "") opt.style.display = 'none';
            });
            sel.selectedIndex = 0;
        });
    }
});
</script>

<tr>
   <td class="content_rowl">Vehículo</td>
   <td class="content_row">
      <select name="dlv_vehiculo_id" id="dlv_vehiculo_id" class="text" style="width:350px" <?=$rdlo?>>
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php foreach ($vehiculo as $v) { ?>
               <option value="<?=$v['id']?>"
                     data-trans="<?=$v['idtransports_vh']?>"
                     <?php if($v["id"] == $headdata["dlv_vehiculo_id"]) echo "selected"; ?>>
                  <?=$v["transports_vh_patente"]?>
               </option>

         <?php } ?>
      </select>
   </td>

   <td class="content_rowl">Chofer</td>
   <td class="content_row">
      <select name="dlv_chofer_id" id="dlv_chofer_id" class="text" style="width:350px" <?=$rdlo?>>
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php foreach ($chofer as $c) { ?>
            <option value="<?=$c['id']?>"
                  data-trans="<?=$c['transports_chofer_trans_id']?>"
                  <?php if($c["id"] == $headdata["dlv_chofer_id"]) echo "selected" ?>>
               <?=$c['Chofer']?> (<?=$c['transports_chofer_rut']?>)
            </option>
         <?php } ?>
      </select>
   </td>
</tr>

<tr>
   <td class="content_rowl">Rango</td>
   <td class="content_row">
      <select name="dlv_rango_id" id="dlv_rango_id" class="text" style="width:350px" <?=$rdlo?>>
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php foreach ($rango as $r) { ?>
            <option value="<?=$r['id']?>"
               data-trans="<?=$r['transports_rango_trans_id']?>"
               <?php if($r["id"] == $headdata["dlv_rango_id"]) echo "selected"?>><?=$r['transports_rango_codigo']?> - Monto $ <?=printPrice($r['transports_rango_monto'])?>
            </option>
         <?php } ?>
      </select>
   </td>

   <td class="content_rowl">Despachar a</td>
   <td class="content_row">
      <select class="text" style="width:350px" name="dlv_cust_delivid" id="dlv_cust_delivid"
      onmousedown="markfield(this,0)" onblur="markfield(this,1)">
         <?php
         if($dabl == "")
         {  ?>
            <option value="">DIRECCIÓN PRINCIPAL</option>
            <?php
            foreach($deliveryaddrs as $deliveryaddr)
            {  ?>
               <option value="<?=$deliveryaddr["id"]?>"
               <?php if($deliveryaddr["id"] == $headdata["dlv_cust_delivid"]) echo "selected"?>><?=$deliveryaddr["delivery_street"]?>, <?=$deliveryaddr["nombre"]?>, <?=$deliveryaddr["name"]?></option>
               <?php
            }
         }
         else
         {
            if(!(int)$headdata["dlv_cust_delivid"])
            {  ?>
               <option value="">DIRECCIÓN PRINCIPAL</option>
               <?php
            }
            else
            {  ?>
               <option value="<?=$headdata["dlv_cust_delivid"]?>"><?=$deliveryaddr["delivery_street"]?>, <?=$deliveryaddr["nombre"]?>, <?=$deliveryaddr["name"]?></option>
               <?php
            }
         }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl">Dirección Origen</td>
   <td class="content_row">
         <input type="text" class="text" style="width:350px" name="dlv_direccion_origen" id="dlv_direccion_origen" <?=$rdlo?>
           value="<?=$headdata["dlv_direccion_origen"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
   <td class="content_rowl">Dirección Destino</td>
   <td class="content_row">
         <input type="text" class="text" style="width:350px" name="dlv_direccion_destino" id="dlv_direccion_destino" <?=$rdlo?>
         value="<?=$headdata["dlv_direccion_destino"]?>" onfocus="markfield(this,0)" onblur="markfield(this,1)">
   </td>
</tr>
<tr>
   <td class="content_rowl">Comuna Origen</td>
   <td class="content_row">
      <select name="dlv_comuna_origen" id="dlv_comuna_origen" class="text" style="width:350px" <?=$rdlo?> 
              onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
            foreach ($comunas as $c) 
            {
               ?>
               <option value="<?=$c['id']?>" 
                  <?php if($c["id"] == $headdata["dlv_comuna_origen"]) echo "selected"?>><?=$c['nombre']?>
               </option>
               <?php
            }
         ?>
      </select>
   </td>
   <td class="content_rowl">Comuna Destino</td>
   <td class="content_row">
      <select name="dlv_comuna_destino" id="dlv_comuna_destino" class="text" style="width:350px" <?=$rdlo?> 
              onfocus="markfield(this,0)" onblur="markfield(this,1)">
         <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
         <?php
            foreach ($comunas as $c) 
            {
               ?>
               <option value="<?=$c['id']?>" 
                  <?php if($c["id"] == $headdata["dlv_comuna_destino"]) echo "selected"?>><?=$c['nombre']?>
               </option>
               <?php
            }
         ?>
      </select>
   </td>
</tr>
<tr>
   <td class="content_rowl"></td>
   <td class="content_row"></td>
   <td class="content_rowl">
      <?php
      if($headdata["dlv_mode"] != 2 && $headdata["dlv_mode"] != 4)
         echo "IVA";
      else
         echo "&nbsp;";
      ?>
   </td>
   <td class="content_row">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_row_clear" width="120">
            <?php
            if($headdata["dlv_mode"] != 2 && $headdata["dlv_mode"] != 4)
            {  ?>
               <select class="text" style="width:120px;background-color:<?php if((int)$headdata["dlv_taxes"]) echo "#BFE6C3;"; else echo "#FFD6D8"?>">
                  <?php
                  if((int)$headdata["dlv_taxes"])
                     echo '<option value="">CON IVA</option>';
                  else
                     echo '<option value="">SIN IVA</option>';
                  ?>
               </select>
               <?php
            }
            else
               echo "&nbsp;";
            ?>
         </td>
         <td class="content_row_clear" align="right" style="padding-right:3px">
            <?php
            if((int)$headdata["dlv_isinvcbrutto"])
               echo 'MONTO BRUTO';
            else
               echo 'MONTO NETO';
            ?>
         </td>
      </tr>
      </table>
   </td>
</tr>

<?php
if(!(int)$headdata["dlv_order_based"])
{  ?>
   <tr>
      <td class="content_rowl">Forma de pago</td>
      <td class="content_row">
         <select class="text" style="width:350px" name="dlv_paymentid" id="dlv_paymentid"
         onmousedown="markfield(this,0)" onblur="markfield(this,1)">
            <?php
            if($dabl == "")
            {  ?>
               <option value="">&lt; <?=$_LANG["FORM"]["OPTION"][0]?> &gt;</option>
               <?php
               foreach($payments as $payment)
               {  ?>
                  <option value="<?=$payment["id"]?>"
                  <?php if($payment["id"] == $headdata["dlv_paymentid"]) echo "selected"?>><?=$payment["pay_title"]?></option>
                  <?php
               }
            }
            else
            {  ?>
               <option value="<?=$headdata["dlv_paymentid"]?>"><?=$headdata["pay_title"]?></option>
               <?php
            }
            ?>
         </select>
      </td>
      <?php
      if((int)$headdata["dlv_supplier_id"] && (int)$headdata["dlv_mode"] == 4)
      {  ?>
         <td class="content_rowl">Fabricación externa</td>
         <td class="content_row">
            <input type="checkbox" name="dlv_externprod_act" value="1"
            onclick="$('#dlv_externprod_ccnum').fadeToggle(300);"
            <?if((int)$headdata["dlv_externprod_act"]) echo "checked"?>> Activar
            <input type="text" style="width:160px;float:right;<?if(!(int)$headdata["dlv_externprod_act"]) echo "display:none"?>"
            id="dlv_externprod_ccnum" name="dlv_externprod_ccnum" <?=$rdlo?> class="text"
            onfocus="markfield(this,0)" onblur="markfield(this,1)"
            placeholder="Número C.C."
            value="<?php if($headdata["dlv_externprod_ccnum"] != "") echo $headdata["dlv_externprod_ccnum"]?>">
         </td>
         <?php
      }
      else
      {  ?>
         <td class="content_rowl">&nbsp;</td>
         <td class="content_row">&nbsp;</td>
         <?php
      }
      ?>
   </tr>
   <?php
}
else
{  ?>
   <input type="hidden" name="dlv_paymentid" value="<?=$headdata["dlv_paymentid"]?>">
   <?php
}
?>
<tr>
   <td class="content_rowl" valign="top">Observaciones<br>[cliente]</td>
   <td class="content_row">
      <textarea class="text" name="dlv_annotation" style="width:350px;height:45px" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$headdata["dlv_annotation"]?></textarea>
   </td>
   <td class="content_rowl" valign="top">Observaciones<br>[interno]</td>
   <td class="content_row">
      <textarea class="text" name="dlv_annotation_intern" style="width:350px;height:45px" <?=$rdlo?>
      onfocus="markfield(this,0)" onblur="markfield(this,1)"><?=$headdata["dlv_annotation_intern"]?></textarea>
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
   <td class="content_row"><?=displayDate($headdata["dlv_crtdat"])?></td>
   <td class="content_rowl">Cambiado</td>
   <td class="content_row"><?=displayDate($headdata["dlv_upddat"])?></td>
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
if($showRelItems)
   printRelItems($CON, $_REQUEST["id"], "ordersdelivery", $headdata["dlv_shop_id"], $headdata["dlv_status"]);
?>
<script language="Javascript" src="./libs/jscripts/overlib/overlib.js"></script>
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000"></div>
<?=Nifty_printH("box2", "980")?>
<table border="0" class="content_table" cellpadding="3" cellspacing="0" width="100%">
<colgroup>
   <col width="95">
   <col width="28">
   <col>
   <col>
   <col width="80">
   <col width="80" <?php if($headdata["dlv_parent_guaid"] > 0) echo "style='display:none'"?>>
   <?php
   if($_REQUEST["showDiscounts"] == "1")
   {  ?>
      <col>
      <col>
      <col>
      <col>
      <?php
   }
   else
   {  ?>
      <col width="60" <?if($_HIDETAXES) echo 'style="display:none"'?>>
      <?php
   }
   ?>
   <col width="175">
   <col width="70">
</colgroup>
<tr>
   <td colspan="15" class="content_tbl_header">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
      <tr>
         <td class="content_tbl_header" style="padding:0px">Artículos</td>
         <?php
         if(count($proms) && $headdata["dlv_status"] == 1 && $headdata["dlv_mode"] < 2)
         {  ?>
            <td class="content_tbl_header" style="padding:0px" width="160">
               <img src="./images/menu/icons/cake.png" style="vertical-align:bottom">
               <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
               onclick="showFancybox('/libs/modules/orders/apply.promotions.fancy.php?frmname=form_shppos&shopid=<?=$headdata["dlv_shop_id"]?>&id=<?=$_REQUEST["id"]?>&mode=DELIVERY', 'iframe', 950, 450, 'auto')">Agregar Promociones</a>
            </td>
            <?php
         }
         if(!(int)$_SESSION["user_pricesell_perm"] && !(int)$_REQUEST["user_pricesell_perm"] && $headdata["dlv_status"] == 1 && $headdata["dlv_mode"] < 2)
         {  ?>
            <td class="content_tbl_header" style="padding:0px" width="190">
               <img src="./images/menu/icons/currency.png" style="vertical-align:bottom">
               <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
               onclick="showFancybox('/libs/modules/orders/auth.pricechange.fancy.php?frmname=form_shppos', 'iframe', 450, 160, 'no')">Activar cambio de precios</a>
            </td>
            <?php
         }
         if($headdata["dlv_status"] == 1 && count($posdata) && $posdata != false)
         {  ?>
            <td class="content_tbl_header" style="padding:0px" width="150">
               <img src="./images/menu/icons/arrow-270-medium.png" style="vertical-align:bottom">
               <a class="link" style="color:#333333" href="javascript: deactivateFormChange()"
               onclick="document.form_shppos.setPosOrder.value='prodnumber'; submitForm(document.form_shppos);">Ordenar por codigo</a>
            </td>
            <?php
         }
         ?>
         <td class="content_tbl_header" style="padding:0px;" width="140" >
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
            if($headdata["dlv_status"] == 1)
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
   <?php
   $coltitle = "Precio/Neto";
   if((int)$headdata["dlv_isinvcbrutto"])
      $coltitle = "Precio/Bruto";
   ?>
   <td class="content_tbl_subheader">Búsqueda</td>
   <td class="content_tbl_subheader">Act.</td>
   <td class="content_tbl_subheader">Artículo</td>
   <td class="content_tbl_subheader" valign="top" align="center">Codigo/Prov</td>
   <td class="content_tbl_subheader" valign="top" align="center">Unidad</td>
   <td class="content_tbl_subheader" align="center">Pend.</td>
   <td class="content_tbl_subheader" align="right">Cantidad</td>
   <td class="content_tbl_subheader" <?php if($headdata["dlv_parent_guaid"] > 0) echo "style='display:none'"?>>Stock</td>
   <td class="content_tbl_subheader" valign="top" align="right"><?=$coltitle?></td>
   <?php
   if($_REQUEST["showDiscounts"] == "1")
   {  ?>
      <td class="content_tbl_subheader" align="center"><nobr>Desc-Global</nobr></td>
      <td class="content_tbl_subheader" align="center"><nobr>Descuentos-   Familia</nobr></td>
      <td class="content_tbl_subheader" align="center"><nobr>Desc-Vol.</nobr></td>
      <td class="content_tbl_subheader" align="center"><nobr>Desc-Monto</nobr></td>
      <?php
   }
   else
   {  ?>
      <td class="content_tbl_subheader" valign="top" align="right" <?if($_HIDETAXES) echo 'style="display:none"'?>>IVA %</td>
      <?php
   }
   ?>
   <td class="content_tbl_subheader" valign="top" align="right" <?if($headdata["dlv_mode"] == 2 || $headdata["dlv_mode"] == 4) echo "style='display:none'"?>><nobr>Precio Total</nobr></td>
</tr>
<?php
$hasItems   = false;
$firstentry = false;

for($x = 0; $x < $rowcount; $x++)
{
   $orderstockmode = false;

   $shpstyle = "";
   if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_amount_shipped"] > 0)
   {
      $item_weight       = $posdata[$x]["item_amount_shipped"] * $posdata[$x]["item_weight"];
      $ges_weight       += $item_weight;
      $ges_weight_price += ($posdata[$x]["item_amount_shipped"] * (float)$posdata[$x]["item_weight_price"]);
      
      if($posdata[$x]["item_amount"] > 0)
      {
         if($posdata[$x]["item_amount_shipped"] == $posdata[$x]["item_amount"])
            $shpstyle = "background-color:#D1FFCC";
         else
            $shpstyle = "background-color:#FFF08C";
      }
      else
         $shpstyle = "background-color:#E4C4F5";
   }
   $showmanual = false;
   if((int)$posdata[$x]["item_id"] && $posdata[$x]["item_type"] == "manual")
      $showmanual = true;

   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "xf_search_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_id_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_amount_shipped_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_stid_{$x}";
   if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_sellprice_netto_{$x}";

   if(!$_HIDETAXES)
   {
      if(!$_FIELDREGS[$x]) $_FIELDREGS[$x] = Array(); $_FIELDREGS[$x][] = "item_sellprice_taxes_perc_{$x}";
   }

   if($headdata["dlv_status"] > 1 && $posdata[$x]["item_amount"] > 0 &&
      $posdata[$x]["item_amount_shipped_orig"] < $posdata[$x]["order_amount_orig"] &&
      !(int)$posdata[$x]["item_amount_shipped_stop"])
      $showBTNDiff = true;
   ?>
   <tr bgcolor="<?=getRowColor($x)?>"<?php
   if((int)$posdata[$x]["item_promid"])
   {  ?>
      style="background-color:#C4EEFF"
      onmouseover="return overlib('PROMOCION', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#698988', ABOVE)"
      onmouseout="return nd()"
      <?php
   }
   ?>>
      <td class="content_row" valign="top">
         <?if($showmanual) { echo "&nbsp;"; $_FIELDIGNORES["xf_search_{$x}"] = 1; } ?>
         <table border="0" cellpadding="0" cellspacing="0" width="100%" id="idx_tdcol1_<?=$x?>" <?php if($showmanual) echo "style='display:none'" ?>>
         <tr>
            <td width="20"><img src="./images/menu/icons/magnifier-zoom.png"></td>
            <td class="content_row_clear">
               <?php
               if((int)$posdata[$x]["item_id"] && (int)$posdata[$x]["item_order_pos"] >= 0)
               {
                  echo "Orden";
                  $_FIELDIGNORES["xf_search_{$x}"] = 1;
                  $hasItems = true;
               }
               else
               {  ?>
                  <input type="text" class="text" style="width:60px" name="xf_search_<?=$x?>" id="xf_search_<?=$x?>"
                  onfocus="markfield(this,0)" <?=$rdlo?> autocomplete="off"
                  <?php
                  
                  if(!(int)$posdata[$x]["item_id"])
                  {  ?>
                     onblur="markfield(this,1);if(this.value!=''){document.all.idxifrsrc.src='./libs/modules/orders_delivery/searchitem.php?rowcount=<?=$x?>&dlvid=<?=$headdata["id"]?>&search=' +this.value;} this.value='';"
                     onkeyup="detectEvent(event, '<?=$x?>', '<?=$_REQUEST["id"]?>')"
                     <?php
                  }
                  else
                  {  ?>
                     onblur="markfield(this,1)"
                     <?php
                     $hasItems = true;
                  }
                  ?>>
                  <?php
               }
               ?>
            </td>
         </tr>
         </table>
      </td>
      <td class="content_row" valign="top">
         <?php
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <input type="hidden" name="existing_id_<?=$x?>" value="<?=$posdata[$x]["item_id"]?>">
            <input type="hidden" name="existing_pos_<?=$x?>" value="<?=$posdata[$x]["item_pos"]?>">
            <input type="button" class="buttonred" value="x" style="width:20px;<?php if((int)$posdata[$x]["item_order_pos"] >= 0 || $dabl != "") echo "display:none" ?>"
            onmouseover="markbtn(this,0)" onmouseout="markbtn(this,1)"
            onclick="if(askDel('')) { document.form_shppos.item_amount_shipped_<?=$x?>.value='0'; submitForm(document.form_shppos); }">
            <?php
            if((int)$posdata[$x]["item_order_pos"] >= 0 || $dabl != "")
               echo "&nbsp;";
         }
         else
         {  ?>
            <img src="/images/menu/icons/notebook--plus.png" border="0" style="cursor:pointer"
            onclick="showOrderPartPosManualEdit('<?=$x?>')">
            <?php
         }
         ?>
         <input type="hidden" name="item_order_pos_<?=$x?>"
         value="<?if((int)$posdata[$x]["item_id"]) echo (int)$posdata[$x]["item_order_pos"]; else echo "-1";?>">
         <input type="hidden" name="item_amount_<?=$x?>" value="<?=printPrice($posdata[$x]["item_amount"],2)?>">
      </td>
      <td class="content_row" valign="top">
         <nobr>
         <?php
         $overlibover   = "";
         $overlibover2  = "";
         $selwidth = "365px";
         $noHasMinAmount = false;
         if((int)$posdata[$x]["item_id"] && $headdata["dlv_mode"] < 2)
         {
            $posdata[$x]["item_costprice_netto"] = precalcSupplierCost($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
            if((float)$posdata[$x]["item_sell_amountmin"] && $posdata[$x]["item_amount_shipped"] < $posdata[$x]["item_sell_amountmin"])
               $noHasMinAmount = true;
            $itemrealsellprice = $posdata[$x]["item_sellprice_netto_dsc"] /  $posdata[$x]["item_amount_shipped"];
            if(!(int)$posdata[$x]["item_promid"] && (($itemrealsellprice < $posdata[$x]["item_costprice_netto"] && $posdata[$x]["item_amount_shipped"] > 0) || $posdata[$x]["item_invoice_note"] != "" || $noHasMinAmount))
            {
               if($itemrealsellprice < $posdata[$x]["item_costprice_netto"])
                  $overlibover2 .= "<b class=msg_save_err>Precio de venta {$_SESSION["_CONF"]["conf_currency"]} ".printPrice($itemrealsellprice)." bajo precio de compra {$_SESSION["_CONF"]["conf_currency"]} ".printPrice($posdata[$x]["item_costprice_netto"])."</b><br>";
               if($noHasMinAmount)
                  $overlibover2 = "<b class=msg_save_err>Cantidad minima de venta: ".printPrice($posdata[$x]["item_sell_amountmin"],2)."</b><br>";
               if($posdata[$x]["item_invoice_note"] != "")
                  $overlibover .= "<b class=msg_save_err>".str_replace("'","",str_replace('"',"",$posdata[$x]["item_invoice_note"]))."</b>";
               if($overlibover != "")
               {  ?>
                  <img src="/images/menu/icons/exclamation-button.png" style="vertical-align:middle"
                  onmouseover="return overlib('<?=$overlibover?>', WIDTH, 350, RIGHT, FGCOLOR, '#FFFFFF', BGCOLOR, '#FF0000', ABOVE)"
                  onmouseout="return nd()">
                  <?php
                  $selwidth = "345px";
               }
            }
         }
         ?>
         <select class="text" style="width:<?=$selwidth?>;<?php if($showmanual) echo "display:none" ?>"
         name="item_id_<?=$x?>" id="item_id_<?=$x?>"
         onmousedown="markfield(this,0)"
         onblur="markfield(this,1);removeSelStyle(this);removeUnSelected(this)"
         onfocus="<?php if(!(int)$posdata[$x]["item_id"]) echo "addSelStyle(this);" ?>"
         onchange="setItemInfosOrderDelivery('<?=$x?>', this.value)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               $desc = trim(addslashes($posdata[$x]["item_title"]));
               $lindesc = $posdata[$x]["item_number_prod"]." - ".$desc;
               if((int)$headdata["dlv_order_id"])
               {
                  $sql = " select t1.*, t4.cust_name, t2.*
                           from orders t1
                           INNER JOIN orders_items t2 ON t1.id = t2.req_id
                           INNER JOIN customer t4     ON t1.req_cust_id = t4.id
                           where
                           t1.id = {$headdata["dlv_order_id"]} and
                           t1.req_isfabricate = 1";
                  $orderinfo = $CON->select($sql);
                  $orderinfo = $orderinfo[0];
                  if((int)$orderinfo["id"] && (int)$orderinfo["item_id"] == $posdata[$x]["item_id"] && !$_HASFABITEMFOUND)
                  {
                     $lindesc  = $posdata[$x]["item_number_prod"]."/";
                     $lindesc .= $posdata[$x]["item_title"]."/";
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
               <option value="<?=$posdata[$x]["item_id"]?>#<?=$posdata[$x]["item_type"]?>"><?=$lindesc?></option>
               <?php
            }
            ?>
         </select>
         <?php if($overlibover2 != "") echo "<br><img src='/images/menu/icons/exclamation-red.png' style='valign:bottom'>&nbsp;{$overlibover2}" ?>
         <textarea class="text" name="item_desc_<?=$x?>" id="item_desc_<?=$x?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$rdlo?>
         style="width:<?=$selwidth?>;height:30px;<?php if(!$showmanual) echo "display:none" ?>"><?=$posdata[$x]["item_desc"]?></textarea>
         <input type="hidden" name="manual_pos_<?=$x?>" id="manual_pos_<?=$x?>"
         value="<?php if($showmanual) echo "1"; else echo "0" ?>">
         </nobr>
      </td>
      <td class="content_row" valign="top" align="center"><?=$posdata[$x]["item_code"]?>&nbsp;</td>
      <td class="content_row" valign="top" align="center">
         <?php
         if((int)$posdata[$x]["item_id"])
            echo getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
         echo "&nbsp;";
         ?>
      </td>
      <td class="content_row" align="center" valign="top">
         <?=printPrice($posdata[$x]["item_amount"],2)?>
      </td>
      <td class="content_row" align="right" valign="top">
         <input type="text" class="text" style="width:50px;text-align:right;<?=$shpstyle?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)" autocomplete="off"
         name="item_amount_shipped_<?=$x?>" id="item_amount_shipped_<?=$x?>" <?=$rdlo?>
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_amount_shipped"],2)?>">
      </td>
      <td class="content_row" valign="top" <?php if($headdata["dlv_parent_guaid"] > 0) echo "style='display:none'"?>>
         <select class="text" style="width:100px;<?if((int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>"
         name="item_stid_<?=$x?>" id="item_stid_<?=$x?>"
         onblur="markfield(this,1);removeSelStyle(this);"
         onfocus="addSelStyle(this);"
         onmousedown="markfield(this,0)">
            <?php
            if((int)$posdata[$x]["item_id"])
            {
               if($posdata[$x]["item_type"] == "item")
                  $itemsts = getItemStorehouses($CON, $headdata["dlv_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"], true, 0, 0, true);
               else
               {
                  $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
                  $itemsts     = getItemStorehouses($CON, $headdata["dlv_shop_id"], $itemlistpos[0]["item_id"], "item");
               }
               if(count($itemsts))
               {
                  foreach(array_keys($itemsts) AS $itemstid)
                  {
                     if($rdlo == "" || ($rdlo != "" && $posdata[$x]["item_st_id"] == $itemstid))
                     {
                        if($orderstockmode)
                           $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["dlv_shop_id"], $itemstid, $posdata[$x]["item_id"], $posdata[$x]["item_type"], true, (int)$headdata["dlv_order_id"]);
                        else
                           $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["dlv_shop_id"], $itemstid, $posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
                        ?>
                        <option value="<?=$itemstid?>"
                        <?php if($posdata[$x]["item_st_id"] == $itemstid) echo "selected"?>>
                           <?=$itemsts[$itemstid]?> (<?=printPrice($currstock,2)?>)
                        </option>
                        <?php
                     }
                  }
               }
            }
            ?>
         </select>
         <div style="<?if(!(int)$posdata[$x]["item_charges_act"]) echo 'display:none'?>" id="item_charges_<?=$x?>">
         <?php
         $btnicon = "arrow";
         $btnname = "Lotes";
         $btnclas = "postnav";

         if(posHasItemChargeDataUsed($CON, $_REQUEST["id"], "ordersdelivery", $x) > 0)
         {
            $btnicon = "tick-circle-frame";
            $btnclas = "postnav_save";

            $charge_amount = posItemChargeDataAmountUsed($CON, $_REQUEST["id"], "ordersdelivery", $x);
            if($charge_amount["tran_amount"] != $posdata[$x]["item_amount_shipped"])
            {
               $btnicon = "cross-circle-frame";
               $btnclas = "postnav_del";
               $_BLOCKFIN_CHARGE = true;
            }
         }
         elseif((int)$posdata[$x]["item_charges_act"])
            $_BLOCKFIN_CHARGE = true;

         printButton($btnname, $btnclas, "javascript: showFancybox('/libs/modules/invoices_buy/data.chargenumbers.select.php?trantype=ordersdelivery&needamount=' +$('#item_amount_shipped_{$x}').val() +'&tranid={$_REQUEST["id"]}&tranpos={$x}&itemdata=' +escape($('#item_id_{$x}').val()), 'iframe', 750, 400, 'auto')", "", $btnicon, 120)
         ?>
         <textarea id="item_charges_data_<?=$x?>" name="item_charges_data_<?=$x?>" style="display:none"></textarea>
         </div>
      </td>
      <td class="content_row" align="right" valign="top">
         <?php
         if(!$hasprcsellperm)
            $dscrdlo = " readonly ";
         else
            $dscrdlo = $rdlo;
         ?>
         <nobr>
         <input type="text" class="text" <?=$dscrdlo?>
         style="<?if((int)$headdata["dlv_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right;"
         name="item_sellprice_netto_<?=$x?>" id="item_sellprice_netto_<?=$x?>" autocomplete="off"
         value="<?if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_sellprice_netto"],4)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </nobr>
         <nobr>
         <input type="text" class="text" <?=$dscrdlo?>
         style="<?if(!(int)$headdata["dlv_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right;"
         name="item_sellprice_brutto_<?=$x?>" id="item_sellprice_brutto_<?=$x?>" autocomplete="off"
         value="<?if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_sellprice_brutto"],4)?>"
         onfocus="markfield(this,0)" onblur="markfield(this,1)">
         </nobr>
      </td>
      <td class="content_row" align="right" valign="top" <?php if($_REQUEST["showDiscounts"] == "1" || $_HIDETAXES) echo "style='display:none'"?>>
         <?php
            if($_REQUEST["showDiscounts"] == "1")
               $_FIELDIGNORES["item_sellprice_taxes_perc_{$x}"] = 1; 
         
            if((int)$posdata[$x]["item_id"] && (int)$posdata[$x]["item_order_pos"] > -1)
            {
               echo printPrice($posdata[$x]["item_sellprice_taxes_perc"],2);
               ?>
               <input type="hidden" name="item_sellprice_taxes_perc_<?=$x?>" id="item_sellprice_taxes_perc_<?=$x?>"
               value="<?if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_sellprice_taxes_perc"],2)?>">
               <?php
               $_FIELDIGNORES["item_sellprice_taxes_perc_{$x}"] = 1; 
            }
            else
            {
               if(!$hasprcsellperm)
                  $dscrdlo = " readonly ";
               else
                  $dscrdlo = $rdlo;
               ?>
               <input type="text" class="text" style="width:38px;text-align:right" <?=$dscrdlo?> autocomplete="off"
               name="item_sellprice_taxes_perc_<?=$x?>" id="item_sellprice_taxes_perc_<?=$x?>"
               value="<?php
                  if((int)$posdata[$x]["item_id"])
                     echo printPrice($posdata[$x]["item_sellprice_taxes_perc"],2);
                  elseif((int)$headdata["dlv_taxes"])
                     echo printPrice($_SESSION["_CONF"]["conf_taxes"], 2);
                  else
                     echo "0";
                  ?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               <?php
            }
         ?>
      </td>
      <td class="content_row" valign="top" align="center" <?php if($_REQUEST["showDiscounts"] != "1") echo "style='display:none'"?>>
         <?php
         if((int)$posdata[$x]["item_id"])
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
            <input name="item_discount_<?=$x?>" type="text" tabindex="-1" autocomplete="off" class="text" style="width:26px;text-align:center;<?php
            if($posdata[$x]["item_discount"] > 0.00) echo "background-color:#E1FFD6"; else echo "background-color:#FFD6D8";?>"
            value="<?php if($posdata[$x]["item_discount"] > 0.00) echo printPrice($posdata[$x]["item_discount"],2);?>" <?=$dscrdlo?>>
            
            <select class="text" style="width:35px" name="item_discount_type_<?=$x?>" tabindex="-1"
            onfocus="markfield(this,0)" onblur="markfield(this,1)" <?=$dscdabl?>>
               <option value="0" <?php if(!(int)$posdata[$x]["item_discount_type"]) echo "selected" ?>>%</option>
               <option value="1" <?php if( (int)$posdata[$x]["item_discount_type"]) echo "selected" ?>><?=$_SESSION["_CONF"]["conf_currency"]?></option>
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
         if((int)$posdata[$x]["item_id"])
         {
            if(!$hasprcsellperm)
               $dscrdlo = " readonly ";
            else
               $dscrdlo = $rdlo;
            ?>
            <nobr>
            <input type="checkbox" class="checkbox" name="item_pcat_dsc_act_<?=$x?>" value="1" tabindex="-1"
            <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
            <?php if((int)$posdata[$x]["item_pcat_dsc_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
            <?php
            $isfirst = false;
            for($y = 1; $y <= 4; $y++)
            {  ?>
               <input type="text" class="text" tabindex="-1" name="item_pcat_dsc_<?=$x?>_<?=$y?>" style="width:25px;text-align:center;<?php
               if((int)$posdata[$x]["item_pcat_dsc_act"] && $posdata[$x]["item_pcat_dsc{$y}"] > 0.00)
                  echo "background-color:#E1FFD6";
               else
                  echo "background-color:#FFD6D8";?>"
               value="<?=printPrice($posdata[$x]["item_pcat_dsc{$y}"],2)?>" <?=$dscrdlo?>>
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
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <nobr>
            <input type="checkbox" class="checkbox" name="item_vol_act_<?=$x?>" value="1" tabindex="-1"
            <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
            <?php if((int)$posdata[$x]["item_vol_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>
            
            <input name="item_vol_dsc_<?=$x?>" type="text" tabindex="-1" class="text" style="width:30px;text-align:center;<?php
            if((int)$posdata[$x]["item_vol_act"] && $posdata[$x]["item_vol_dsc"] > 0.00)
               echo "background-color:#E1FFD6";
            else
               echo "background-color:#FFD6D8";?>"
            value="<?php if($posdata[$x]["item_vol_dsc"] > 0.00) echo printPrice($posdata[$x]["item_vol_dsc"],2);?>" readonly>
            
            <?php
            if((int)$posdata[$x]["item_vol_dsctype"])
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
         if((int)$posdata[$x]["item_id"])
         {  ?>
            <nobr>
            <input type="checkbox" class="checkbox" name="item_value_act_<?=$x?>" value="1" tabindex="-1"
            <?php if(!$hasprcsellperm) echo 'onclick="return false"'?>
            <?php if((int)$posdata[$x]["item_value_act"]) echo "checked"?> <?php if($dabl != "") echo "onclick='this.checked=!this.checked'"?>>

            <input name="item_value_dsc_<?=$x?>" type="text" tabindex="-1" class="text" style="width:26px;text-align:center;<?php
            if((int)$posdata[$x]["item_value_act"] && $posdata[$x]["item_value_dsc"] > 0.00)
               echo "background-color:#E1FFD6";
            else
               echo "background-color:#FFD6D8";?>"
            value="<?php if($posdata[$x]["item_value_dsc"] > 0.00) echo printPrice($posdata[$x]["item_value_dsc"],2);?>" readonly>
            <?php
            if((int)$posdata[$x]["item_value_dsctype"])
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
      <td class="content_row" align="right" valign="top" <?if($headdata["dlv_mode"] == 2 || $headdata["dlv_mode"] == 4) echo "style='display:none'"?>>
         <nobr>
         <input type="text" class="text" tabindex="-1" readonly
         style="<?if((int)$headdata["dlv_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_sellprice_netto_dsc"])?>">
         </nobr>
         <nobr>
         <input type="text" class="text" tabindex="-1" readonly
         style="<?if(!(int)$headdata["dlv_isinvcbrutto"]) echo "display:none"?>;width:75px;text-align:right;background-color:<?if((int)$posdata[$x]["item_id"]) echo "#E1FFD6"; else echo "#FFD6D8"?>"
         value="<?php if((int)$posdata[$x]["item_id"]) echo printPrice($posdata[$x]["item_sellprice_brutto_dsc"])?>">
         </nobr>
      </td>
   </tr>
   <?php
   if(!(int)$posdata[$x]["item_id"] && $_REQUEST["subexec"] == "save" && !$focusexec)
   {
      $_SESSION["JSEXEC"] .= "document.form_shppos.xf_search_{$x}.focus();$('html,body').animate({scrollTop:$(window).scrollTop() + 100}, 200);";
      $focusexec = true;
   }
}
?>
</table>
<?=Nifty_printF()?>
<br>
<?php
if($headdata["dlv_mode"] != 2 && $headdata["dlv_mode"] != 4)
{  ?>
   <table border="0" cellpadding="0" cellspacing="0">
   <tr>
      <td valign="top">
         <?php
         if($_REQUEST["showDiscounts"] == "1")
            $ftablewidth = 665;
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
               value="<?=printPrice($headdata["dlv_total_netto"] + $headdata["dlv_discount_amount_netto"] - $headdata["dlv_weightprice_netto"])?>">
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
                     id="dlv_discount_perc" name="dlv_discount_perc"
                     value="<?=printPrice($headdata["dlv_discount_perc"],2)?>" <?=$rdlo?>> %
                     </nobr>
                  </td>
                  <td class="content_row_clear" align="right" width="1">
                     <nobr>
                     <input type="text" class="text" style="text-align:center;width:100px;"
                     id="dlv_discount_amt" name="dlv_discount_amt"
                     value="<?=printPrice($headdata["dlv_discount_amt"],2)?>" <?=$rdlo?>> $
                     </nobr>
                  </td>
               </tr>
               </table>
            </td>
            <td class="content_row_clear" align="right">
               <input type="text" class="text" style="width:120px;text-align:right" readonly
               value="<?=printPrice($headdata["dlv_discount_amount_netto"])?>">
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
               value="<?=printPrice($headdata["dlv_weightprice_netto"])?>"
               onfocus="markfield(this,0)" onblur="markfield(this,1)">
               </nobr>
            </td>
         </tr>
         -->
         <?php
         if($headdata["dlv_total_taxes"] > 0.00)
         {  ?>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row_totals content_rowl" colspan="2">NETO</td>
               <td class="content_row_totals content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["dlv_total_netto"])?>">
               </td>
            </tr>
            <tr bgcolor="<?=getRowColor(0)?>">
               <td class="content_row content_rowl" colspan="2">IVA</td>
               <td class="content_row" align="right">
                  <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold" readonly
                  value="<?=printPrice($headdata["dlv_total_taxes"])?>">
               </td>
            </tr>
            <?php
         }
         ?>
         <tr bgcolor="<?=getRowColor(0)?>">
            <td class="content_row_totals content_rowl" colspan="2">TOTAL</td>
            <td class="content_row_totals content_row" align="right">
               <input type="text" class="text" style="width:120px;text-align:right;font-weight:bold;background-color:#E1FFD6" readonly
               value="<?=printPrice($headdata["dlv_total_brutto"])?>">
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
         if($headdata["dlv_status"] < 2 && $_REQUEST["showDiscounts"] == "1" && $hasprcsellperm)
         {  ?>
            <?=Nifty_printH("box1", "590")?>
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
   $ftablewidth = 1270;
else
   $ftablewidth = 980;
?>
<?=Nifty_printH("boxopt_b", $ftablewidth)?>
<table border="0" cellspacing="0" cellpadding="0" width="100%">
<tr>
   <?php
   if($_REQUEST["id"] != "")
   {  ?>
      <td width="130">
         <?php
         if(!(int)$headdata["dlv_invoice_generated"] && !(int)$headdata["dlv_prodext_generated"])
            printButton($_LANG["FORM"]["BUTTON"][1], "postnav", "index.php?mid={$_REQUEST["mid"]}", "", "arrow-180");
         ?>
      </td>
      <td>&nbsp;</td>
      <?php
      /*
      if((int)$headdata["dlv_status"] == 1 && $hasItems)
      {  ?>
         <td align="right" width="140" style="padding-right:5px">
            <?php
            printButton("Imprimir Pendientes", "postnav", "javascript: deactivateFormChange()", "openDocWindow('./libs/modules/invoices_sell/printraw.data.php?id={$_REQUEST["id"]}&mode=preview&showDiff=1&diffMode=ordersdelivery&preCalc=1', 900, 650);", "script");
            ?>
         </td>
         <?php
      }
      */
      ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         if((int)$headdata["dlv_status"] == 1)
            printButton("Borrar", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&subexec=del&id={$_REQUEST["id"]}')", "cross-circle-frame");
         ?>
      </td>
      <?php
      if((int)$headdata["dlv_invoiced"] && $headdata["dlv_status"] >= 2 && $headdata["dlv_status"] <= 4 && $_REQUEST["subcatexec"] != "orders_delivery_show")
      {  ?>
         <td align="right" width="160" style="padding-right:5px">
            <?php
            printButton("Abrir para facturación", "postnav_save", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&revertinvc=1')", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
      if(!(int)$headdata["dlv_invoiced"] && $headdata["dlv_status"] >= 2  && $headdata["dlv_status"] <= 4 && $_REQUEST["subcatexec"] != "orders_delivery_show")
      {  ?>
         <td align="right" width="160" style="padding-right:5px">
            <?php
            printButton("Cerrar para facturación", "postnav_del", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&id={$_REQUEST["id"]}&cancelinvc=1')", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   if((int)$headdata["dlv_status"] == 1)
   {  ?>
      <td align="right" width="130" style="padding-right:5px">
         <?php
         printButton($_LANG["FORM"]["BUTTON"][0], "postnav", "javascript: deactivateFormChange()", "submitForm(document.form_shppos)", "disk-black");
         ?>
      </td>
      <?php
      if(!$_BLOCKFIN_CHARGE && $hasItems)
      {  ?>
         <td align="right" width="130" style="padding-right:5px" id="idx_fin_button">
            <?php
            
            printButton($_LANG["FORM"]["BUTTON"][11], "postnav_save", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.dlv_status.value='2';submitForm(document.form_shppos);}", "tick-circle-frame");
            ?>
         </td>
         <?php
      }
   }
   elseif((int)$headdata["dlv_status"] == 2 || (int)$headdata["dlv_status"] == 3)
   {
      if((int)$headdata["dlv_order_id"] && !(int)$headdata["req_order_shipped"])
      {  ?>
         <td align="right" width="140" style="padding-right:5px">
            <?php
            printButton("Cerrar nota", "postnav_save", "javascript: deactivateFormChange()", "askDel('index.php?mid={$_REQUEST["mid"]}&exec=edit&subexec=edit&clearOrder=1&id={$_REQUEST["id"]}')", "tick-circle-frame");
            ?>
         </td>
         <?php
      }

      if(!(int)$headdata["dlv_invoice_generated"] && !(int)$headdata["dlv_prodext_generated"] && !$_BLOCKOPEN_CHARGE && !(int)$_INVCCFG["company_invc_mode"])
      {  ?>
         <td align="right" width="140" style="padding-right:5px">
         <?php
         if($hasdocopeperm)
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';submitForm(document.form_shppos);}", "arrow-circle-045-left");
         else
            printButton("Editar", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos', 'iframe', 450, 160, 'no')", "arrow-circle-045-left");
         ?>
         </td>
         <?php
      }

      if($showBTNDiff)
      {  ?>
         <td align="right" width="140" style="padding-right:5px">
            <?php
            printButton("Imprimir Pendientes", "postnav", "javascript: deactivateFormChange()", "openDocWindow('./libs/modules/orders_delivery/printraw.data.php?id={$_REQUEST["id"]}&mode=preview&showDiff=1&diffMode=ordersdelivery', 900, 650);", "script");
            ?>
         </td>
         <?php
      }
      ?>
      <td align="right" width="170">
         <?php
         if($hasdocopeperm)
            printButton("Anular Guia", "postnav_del", "javascript: deactivateFormChange()", "if(askDel('')){document.form_shppos.onsubmit='';document.form_shppos.dlv_status.value='1';document.form_shppos.cancelDoc.value='1';submitForm(document.form_shppos);}", "cross-circle-frame");
         else
            printButton("Anular Guia", "postnav_del", "javascript: deactivateFormChange()", "showFancybox('/libs/modules/orders/auth.docopen.fancy.php?frmname=form_shppos&sStatus=1&cancelDoc=1', 'iframe', 450, 160, 'no')", "cross-circle-frame");
         ?>
      </td>
      <td align="right" width="140" style="padding-left:5px;display:none">
         <?php
         printButton("Generar Ajuste Devolución", "postnav", "javascript: deactivateFormChange()", "if(askDel('')){ location.href='/index.php?mid=679&exec=edit&subexec=edit&subexec=createFromOrdersDelivery&dlvid={$_REQUEST["id"]}'; }", "gear");
         ?>
      </td>
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
<?=Nifty_printF(false)?>
</form>
<br><br>
<?php $_SESSION["JSEXEC"] .= "addFormListeners('form_shppos');" ?>
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
  $pdffile = doc_createOrderDelivery($CON, $_REQUEST["id"]);

if($pdffile != "")
{
   $doctitle = "Guia-de-Despacho-".time().".pdf";
   $pdflink = "./libs/modules/structure/document_file.php?type=0&hash={$pdffile}.pdf&name={$doctitle}&path=../../../docs.print/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}

if((int)$_REQUEST["printsiidoc1"] || (int)$_REQUEST["printsiidoc2"])
{
   if((int)$_REQUEST["printsiidoc1"])
   {
      $doctitle   = "Guia-{$headdata["dlv_docnum"]}.pdf";
      $docfile    = $headdata["dlv_sgntr_doc1"];
   }
   else
   {
      $doctitle   = "Cedible-{$headdata["dlv_docnum"]}.pdf";
      $docfile    = $headdata["dlv_sgntr_doc2"];
   }
   $pdflink    = "./libs/modules/structure/document_file.php?type=0&hash={$docfile}&name={$doctitle}&path=../../../docs.electrpdf/";
   ?>
   <iframe height="0" width="0" frameborder="0" src="<?=$pdflink?>"></iframe>
   <?php
}
?>