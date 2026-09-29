<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function doc_createOrder($CON, $orderid)
{
   global $_LANG;
   
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.order/";
   $filename   = "{$filedir}{$orderid}.{$hash}.pdf";
   $pdf        = prepareDoc($filename, "LETTER", "", "offer");

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.cust_company, t2.cust_email, t2.cust_street, t6.country_name 'cust_country',
                   t2.cust_rut, t3.company_name, t3.company_rut, t4.shop_name, t4.shop_street,
                   t5.country_name 'shop_country', t4.shop_email, t4.shop_phone,
                   t7.name 'shop_region', t8.name 'cust_region', t9.nombre 'shop_comuna',
                   t10.nombre 'cust_comuna', t11.giro_name, t2.cust_fax, t2.cust_phone,
                   t12.pay_title, CONCAT(t13.user_firstname, ' ', t13.user_lastname) 'sellername', t1.req_shop_id,
                   t14.trans_name
            from orders t1
            LEFT OUTER JOIN customer t2      ON t1.req_cust_id       = t2.id
            LEFT OUTER JOIN company_data t3  ON t1.req_company_id    = t3.id
            LEFT OUTER JOIN company_shops t4 ON t1.req_shop_id       = t4.id
            LEFT OUTER JOIN country t5       ON t4.shop_countryid    = t5.id
            LEFT OUTER JOIN country t6       ON t2.cust_countryid    = t6.id
            LEFT OUTER JOIN regions t7       ON t4.shop_regionid     = t7.id
            LEFT OUTER JOIN regions t8       ON t2.cust_regionid     = t8.id
            LEFT OUTER JOIN comunas t9       ON t4.shop_comunaid     = t9.id
            LEFT OUTER JOIN comunas t10      ON t2.cust_comunaid     = t10.id
            LEFT OUTER JOIN giros   t11      ON t2.cust_giroid       = t11.id
            LEFT OUTER JOIN payments t12     ON t1.req_paymentid     = t12.id
            LEFT OUTER JOIN user t13         ON t1.req_userid_seller = t13.id
            LEFT OUTER JOIN transports t14   ON t1.req_transportid      = t14.id
            where
            t1.id = {$orderid}";
   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.pro_name
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            LEFT OUTER JOIN provincias t5 ON t1.cust_provinciaid  = t5.id
            where
            t1.id = {$orderheader["req_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];

   if((int)$orderheader["req_cust_delivid"])
   {
      $sql = " select t1.delivery_name 'cust_company', t1.delivery_street 'cust_street', t5.cust_rut, t2.country_name, t3.name, t4.nombre, t6.giro_name
               from customer_deliveryaddr t1
               LEFT OUTER JOIN country t2    ON t1.delivery_countryid = t2.id
               LEFT OUTER JOIN regions t3    ON t1.delivery_regionid  = t3.id
               LEFT OUTER JOIN comunas t4    ON t1.delivery_comunaid  = t4.id
               LEFT OUTER JOIN customer t5   ON t1.cust_id            = t5.id
               LEFT OUTER JOIN giros   t6    ON t5.cust_giroid        = t6.id
               where
               t1.id = {$orderheader["req_cust_delivid"]}
               order by t1.id asc";
      $deliveryaddr = $CON->select($sql);
      $deliveryaddr = $deliveryaddr[0];

      $customer["cust_street"]   = $deliveryaddr["cust_street"];
      $customer["nombre"]        = $deliveryaddr["nombre"];
      $customer["name"]          = $deliveryaddr["name"];
   }

   //----------------------------------------------------------------------------------
   if(trim($orderheader["req_custnameprov"]) != "")
   {
      $customer["cust_company"]  = $orderheader["req_custnameprov"];
      $customer["cust_rut"]      = "";
      $customer["cust_phone"]    = "";
      $customer["cust_fax"]      = "";
      $customer["cust_street"]   = "";
      $customer["nombre"]        = "";
      $customer["name"]          = "";
   }

   $is_reserva = "No";
   if((int)$orderheader["req_isreserva"])
      $is_reserva = "Si";

   //----------------------------------------------------------------------------------
   if($orderheader["req_hash"] != "")
      unlink("{$filedir}{$orderid}.{$orderheader["req_hash"]}.pdf");

   //----------------------------------------------------------------------------------
   $sql = " update orders
            set
            req_hash   = '{$hash}',
            req_upddat = {$currtme},
            req_updusr = {$_SESSION["user_id"]}
            where
            id          = {$orderid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "250", "justification" => "left"),
                      "X2"  => Array("width" => "150", "justification" => "left"),
                      "X3"  => Array("width" => "150", "justification" => "right")));
   $x = 0;
   $data[$x]["X1"] = $orderheader["company_name"];
   $data[$x]["X2"] = " ";
   $data[$x]["X3"] = date('d', $orderheader["req_crtdat"])." de ".$_LANG["MODULE"]["CAL"][(date('m', $orderheader["req_crtdat"])-1)]." de ".date('Y', $orderheader["req_crtdat"]);
   $x++;
   $data[$x]["X1"] = $orderheader["company_rut"];
   $data[$x]["X2"] = " ";
   $data[$x]["X3"] = " ";
   $x++;
   $data[$x]["X1"] = $orderheader["shop_street"];
   $data[$x]["X2"] = "PEDIDO BOD.N° ".$orderheader["req_number"];
   $data[$x]["X3"] = " ";
   $x++;
   $data[$x]["X1"] = "Fono: ".$orderheader["shop_phone"]." Fax: ".$orderheader["shop_fax"];
   $data[$x]["X2"] = "______________________";
   $data[$x]["X3"] = " ";
   $x++;

   $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 25, 555, 1);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "70"),
                      "X2"  => Array("width" => "5"),
                      "X3"  => Array("width" => "215"),
                      "X4"  => Array("width" => "70"),
                      "X5"  => Array("width" => "5"),
                      "X6"  => Array("width" => "185")));
   $x = 0;
   $data[$x]["X1"] = "Señor(es)";
   $data[$x]["X2"] = ":";
   $data[$x]["X3"] = $customer["cust_company"];
   $data[$x]["X4"] = "Rut";
   $data[$x]["X5"] = ":";
   $data[$x]["X6"] = $customer["cust_rut"];
   $x++;
   $data[$x]["X1"] = "Dirección";
   $data[$x]["X2"] = ":";
   $data[$x]["X3"] = $customer["cust_street"];
   $data[$x]["X4"] = "Telefono";
   $data[$x]["X5"] = ":";
   $data[$x]["X6"] = $customer["cust_phone"];
   $x++;
   $data[$x]["X1"] = "Ciudad";
   $data[$x]["X2"] = ":";
   $data[$x]["X3"] = $customer["nombre"];
   $data[$x]["X4"] = "Whatsapp";
   $data[$x]["X5"] = ":";
   $data[$x]["X6"] = $customer["cust_fax"];
   $x++;
   $data[$x]["X1"] = "Atención";
   $data[$x]["X2"] = ":";
   $data[$x]["X3"] = $customer["req_desc_intern"];
   $data[$x]["X4"] = "Form.Pago";
   $data[$x]["X5"] = ":";
   $data[$x]["X6"] = $orderheader["pay_title"];
   $x++;
   $data[$x]["X1"] = "Vendedor";
   $data[$x]["X2"] = ":";
   $data[$x]["X3"] = $orderheader["sellername"];
   $data[$x]["X4"] = "Transporte";
   $data[$x]["X5"] = ":";
   $data[$x]["X6"] = $orderheader["trans_name"];
   $x++;
   $data[$x]["X1"] = "Plazo de Despacho";
   $data[$x]["X2"] = ":";
   $data[$x]["X3"] = $orderheader["req_despacho_desc"];
   $data[$x]["X4"] = "Numero OC";
   $data[$x]["X5"] = ":";
   $data[$x]["X6"] = $orderheader["req_oc_number"];
   $x++;

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf = doc_linedraw($pdf, 25, 555, 1);

   $colx1 = "Neto\nunitario";
   $colx2 = "Precio\nsin IVA";
   $fldx1 = "item_sellprice_netto";
   $fldx2 = "item_sellprice_netto_dsc";
   $fldx3 = "req_total_netto";

   if((int)$orderheader["req_isinvcbrutto"])
   {
      $colx1 = "Bruto\nunitario";
      $colx2 = "Precio\ncon IVA";
      $fldx1 = "item_sellprice_brutto";
      $fldx2 = "item_sellprice_brutto_dsc";
      $fldx3 = "req_total_brutto";
   }
   
   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 0, "rowGap" => 0, "colGap" => 3, "fontSize"=> 7,
                     "cols" =>   Array (
                     "Cant."           => Array("width" => "30", "justification" => "right"),
                     "Código"          => Array("width" => "80", "justification" => "left"),
                     "Descripción"     => Array("width" => "255", "justification" => "left"),
                     "Descto."         => Array("width" => "40", "justification" => "left"),
                     $colx1            => Array("width" => "50", "justification" => "right"),
                     $colx2            => Array("width" => "50", "justification" => "right"),
                     "Bod."            => Array("width" => "30", "justification" => "center"),
                     "Rev."            => Array("width" => "30", "justification" => "center")
                     )
                  );
   $data[0]["Cant."]           = " ";
   $data[0]["Código"]          = " ";
   $data[0]["Descripción"]     = " ";
   $data[0]["Descto."]         = " ";
   $data[0][$colx1]            = " ";
   $data[0][$colx2]            = " ";
   $data[0]["Bod."]            = " ";
   $data[0]["Rev."]            = " ";
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf = doc_linedraw($pdf, 25, 555, 1);

   //----------------------------------------------------------------------------------719
   unset($data);
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 0, "rowGap" => 2, "colGap" => 3, "fontSize"=> 7,
                     "cols" =>   Array (
                     "Cant."           => Array("width" => "30", "justification" => "center"),
                     "Código"          => Array("width" => "80", "justification" => "left"),
                     "Descripción"     => Array("width" => "255", "justification" => "left"),
                     "Descto."         => Array("width" => "40", "justification" => "left"),
                     $colx1            => Array("width" => "50", "justification" => "right"),
                     $colx2            => Array("width" => "50", "justification" => "right"),
                     "Bod."            => Array("width" => "30", "justification" => "center"),
                     "Rev."            => Array("width" => "30", "justification" => "center")
                     )
                  );


   $posdata = getOrderPos($CON, $orderid, "prodnumber");

   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $data[$x]["Cant."]           = printPrice($posdata[$x]["item_amount"],2);
      $data[$x]["Código"]          = reformatProdNumber($posdata[$x]["item_number_prod"]);
      $data[$x]["Descripción"]     = $posdata[$x]["item_title"];
      $data[$x]["Descto."]         = $posdata[$x]["_dsc_str"];
      $data[$x][$colx1]            = printPrice($posdata[$x][$fldx1]);
      $data[$x][$colx2]            = printPrice($posdata[$x][$fldx2]);

      $sts    = array();
      $tmpsts = getItemStorehouses($CON, $orderheader["req_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"], false,  0, 2);
      foreach($tmpsts AS $st)
         $sts[] = $st;

      $data[$x]["Bod."]            = "[  ]";
      $data[$x]["Rev."]            = "[  ]";
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   unset($data);
   $pcounter = 0;
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "xpos" => "left", "showLines" => 0, "rowGap" => 0, "colGap" => 0, "fontSize"=> 10,
                     "protectRows" => 99, "cols" =>   Array (
                     "X1"  => Array("width" => "250", "justification" => "left"),
                     "X2"  => Array("width" => "275")
                     )
                  );
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";

   //----------------------------------------------------------------------------------
   if(trim($orderheader["req_desc"]) != "")
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "<b>{$orderheader["req_desc"]}</b>";
      $data[$pcounter]["X2"] = " ";
   }
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";

   $ty = $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "protectRows" => 20,
                     "xpos" => "left", "showLines" => 0, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                     "cols" =>   Array (
                     "X1"  => Array("width" => "469", "justification" => "right"),
                     "X2"  => Array("width" => "15", "justification" => "center"),
                     "X3"  => Array("width" => "65", "justification" => "right")
                     )
                  );
   $pcounter = 0;
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = "TOTAL";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = printPrice($orderheader[$fldx3] + $orderheader["req_discount_amount_netto"]);
   $pcounter++;
   
   if($orderheader["req_trazlogoprice_netto"] != 0.00)
   {
      $data[$pcounter]["X1"] = "TRAZADO LOGO";
      $data[$pcounter]["X2"] = " ";
      $data[$pcounter]["X3"] = printPrice($orderheader["req_trazlogoprice_netto"]);
      $pcounter++;
   }
   if($orderheader["req_weightprice_netto"] != 0.00)
   {
      $data[$pcounter]["X1"] = "COSTO DESPACHO";
      $data[$pcounter]["X2"] = " ";
      $data[$pcounter]["X3"] = printPrice($orderheader["req_weightprice_netto"]);
      $pcounter++;
   }

   $_DSCPRC = $orderheader[$fldx3] - $orderheader["req_trazlogoprice_netto"] - $orderheader["req_weightprice_netto"];
   $_DSCPRC = $orderheader[$fldx3] + $orderheader["req_discount_amount_netto"] - $_DSCPRC;

   if($_DSCPRC != 0.00)
   {
      $data[$pcounter]["X1"] = "DESCUENTO";
      $data[$pcounter]["X2"] = " ";
      $data[$pcounter]["X3"] = printPrice($_DSCPRC * -1);
      $pcounter++;
   }
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = " ";
   $pcounter++;


//    //----------------------------------------------------------------------------------
//    if($orderheader["req_weightprice_netto"] > 0.00)
//    {
//       $data[$pcounter]["X1"] = "SUBTOTAL";
//       $data[$pcounter]["X2"] = " ";
//       $data[$pcounter]["X3"] = printPrice($orderheader["req_total_netto"] - $orderheader["req_weightprice_netto"]);
//       $pcounter++;
//       $data[$pcounter]["X1"] = "CONDUCCION";
//       $data[$pcounter]["X2"] = " ";
//       $data[$pcounter]["X3"] = printPrice($orderheader["req_weightprice_netto"]);
//       $pcounter++;
//    }
   
   $data[$pcounter]["X1"] = "NETO";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = printPrice($orderheader["req_total_netto"]);
   $pcounter++;
   $data[$pcounter]["X1"] = "IVA";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = printPrice($orderheader["req_total_taxes"]);

   //----------------------------------------------------------------------------------
   $pcounter++;
   $data[$pcounter]["X1"] = "<b>TOTAL</b>";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = "<b>".printPrice($orderheader["req_total_brutto"])."</b>";

    
   $ycomment = $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezSetY($ycomment + 55);

   unset($data);
   $pcounter = 0;
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "xpos" => "left", "showLines" => 0, "rowGap" => 0, "colGap" => 0, "fontSize"=> 10,
                     "protectRows" => 99, "cols" =>   Array (
                     "X1"  => Array("width" => "250", "justification" => "left"),
                     "X2"  => Array("width" => "275")
                     )
                  );
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";

   //----------------------------------------------------------------------------------
   if(trim($orderheader["req_desc"]) != "")
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "<b>{$orderheader["req_desc"]}</b>";
      $data[$pcounter]["X2"] = " ";
   }
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";

   $pdf->ezSetY($ycomment);

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "520", "justification" => "left")));
   $x = 0;
   $data[$x]["X1"] = "Firma _____________________________________";
   $x++;
   $data[$x]["X1"] = "Estamos a sus órdenes, no dude en llamarnos si tiene cualquier inquietud.";
   $x++;

   $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $orderheader["req_company_id"], "order2", $orderheader["req_number"]);

   //----------------------------------------------------------------------------------
   $fp = fopen($filename, "w");
   if($fp)
   {
      $pdfdata = $pdf->output();
      fwrite($fp, $pdfdata);
      fclose($fp);
   }
}
?>