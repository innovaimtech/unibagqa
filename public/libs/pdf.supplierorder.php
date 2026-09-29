<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function doc_createSupplierOrder($CON, $orderid)
{
   global $_LANG;
   
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.supplierorder/";
   $filename   = "{$filedir}{$orderid}.{$hash}.pdf";
   $pdf        = prepareDoc($filename, "LETTER", "landscape", "supplier_order");

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.supp_company, t2.supp_email, t2.supp_street, t6.country_name 'supp_country',
                   t2.supp_rut, t3.company_name, t3.company_rut, t4.shop_name, t4.shop_street,
                   t5.country_name 'shop_country', t4.shop_email, t4.shop_phone,
                   t7.name 'shop_region', t8.name 'supp_region', t9.nombre 'shop_comuna',
                   t10.nombre 'supp_comuna', t11.giro_name, t2.supp_fax, t2.supp_phone,
                   t12.pay_title,
                   t6x.user_firstname 'crt_firstname', t6x.user_lastname 'crt_lastname',
                   t6x.user_telephone, t6x.user_mail, t6x.user_cellphone
            from supplier_order t1
            LEFT OUTER JOIN supplier t2      ON t1.sord_supplier_id  = t2.id
            LEFT OUTER JOIN company_data t3  ON t1.sord_company_id   = t3.id
            LEFT OUTER JOIN company_shops t4 ON t1.sord_shop_id      = t4.id
            LEFT OUTER JOIN country t5       ON t4.shop_countryid    = t5.id
            LEFT OUTER JOIN country t6       ON t2.supp_countryid    = t6.id
            LEFT OUTER JOIN regions t7       ON t4.shop_regionid     = t7.id
            LEFT OUTER JOIN regions t8       ON t2.supp_regionid     = t8.id
            LEFT OUTER JOIN comunas t9       ON t4.shop_comunaid     = t9.id
            LEFT OUTER JOIN comunas t10      ON t2.supp_comunaid     = t10.id
            LEFT OUTER JOIN giros   t11      ON t2.supp_giroid       = t11.id
            LEFT OUTER JOIN payments t12     ON t1.sord_paymentid    = t12.id
            LEFT OUTER JOIN user t6x          ON t1.sord_crtusr      = t6x.id
            where
            t1.id = {$orderid}";
   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];

   //----------------------------------------------------------------------------------
   if(!(int)$orderheader["sord_taxes"])
   {
      $moneystr   = "US ";
      $numberlim  = "4";
   }
   else
   {
      $moneystr  = "\$";
      $numberlim = "0";
   }

   //----------------------------------------------------------------------------------
   if($orderheader["sord_hash"] != "")
      unlink("{$filedir}{$orderid}.{$orderheader["sord_hash"]}.pdf");

   //----------------------------------------------------------------------------------
   $sql = " update supplier_order
            set
            sord_hash   = '{$hash}',
            sord_upddat = {$currtme}
            where
            id          = {$orderid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 720, 1);


   if((int)$orderheader["sord_en_lang"])
   {
      $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "70"),
                      "X2"  => Array("width" => "310"),
                      "X3"  => Array("width" => "70"),
                      "X4"  => Array("width" => "270")));
                      
      $data[0]["X1"] = "<b>Date:</b>";
      $data[0]["X2"] = "Santiago de Chile, ".date("d.m.Y");
      $data[0]["X3"] = "<b>Supplier:</b>";
      $data[0]["X4"] = $orderheader["supp_company"];

      $data[1]["X1"] = "<b>Street:</b>";
      $data[1]["X2"] = $orderheader["supp_street"];
      $data[1]["X3"] = "<b>City:</b>";
      $data[1]["X4"] = $orderheader["supp_region"];

      $data[2]["X1"] = "<b>Payment:</b>";
      $data[2]["X2"] = $orderheader["pay_title"];
      $data[2]["X3"] = "<b>Delivery:</b>";
      $data[2]["X4"] = date("d.m.Y", $orderheader["sord_date"]);

      $data[3]["X1"] = "";
      $data[3]["X2"] = "";
      $data[3]["X3"] = "";
      $data[3]["X4"] = "";
         
      if($orderheader["sord_plazo_desc"] != "")
      {
         $data[3]["X1"] = "<b>Delivery time:</b>";
         $data[3]["X2"] = $orderheader["sord_plazo_desc"];
      }
      if(trim($orderheader["sord_incoterms"]) != "")
      {
         $data[3]["X3"] = "<b>Incoterms:</b>";
         $data[3]["X4"] = $orderheader["sord_incoterms"];
      }
      elseif(trim($orderheader["supp_email"]) != "")
      {
         $data[3]["X3"] = "<b>Email:</b>";
         $data[3]["X4"] = $orderheader["supp_email"];
      }

      if($data[3]["X1"] == "" && $data[3]["X2"] == "" && $data[3]["X3"] == "" && $data[3]["X4"] == "")
      {
         unset($data[3]);
      }
   }
   else
   {
      $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "90"),
                      "X2"  => Array("width" => "270"),
                      "X3"  => Array("width" => "70"),
                      "X4"  => Array("width" => "290")));
                      
      $data[0]["X1"] = "<b>Fecha:</b>";
      $data[0]["X2"] = "Santiago, ".date('d')." de ".$_LANG["MODULE"]["CAL"][(date('m')-1)]." ".date('Y');
      $data[0]["X3"] = "<b>RUT:</b>";
      $data[0]["X4"] = $orderheader["supp_rut"];

      $data[1]["X1"] = "<b>Señor(es):</b>";
      $data[1]["X2"] = $orderheader["supp_company"];
      $data[1]["X3"] = "<b>Giro:</b>";
      $data[1]["X4"] = $orderheader["giro_name"];

      $data[2]["X1"] = "<b>Dirección:</b>";
      $data[2]["X2"] = $orderheader["supp_street"];
      $data[2]["X3"] = "<b>Región:</b>";
      $data[2]["X4"] = $orderheader["supp_region"];

      $data[3]["X1"] = "<b>Cond. de pago:</b>";
      $data[3]["X2"] = $orderheader["pay_title"];
      $data[3]["X3"] = "<b>Comuna:</b>";
      $data[3]["X4"] = $orderheader["supp_comuna"];
      
      $xx = 4;
      if(trim($orderheader["supp_fax"]) != "" || trim($orderheader["supp_phone"]) != "")
      {
         $data[4]["X1"] = "<b>Teléfono:</b>";
         $data[4]["X2"] = $orderheader["supp_phone"];
         $data[4]["X3"] = "<b>Fax:</b>";
         $data[4]["X4"] = $orderheader["supp_fax"];
         $xx++;
      }

      $data[$xx]["X1"] = "<b>Plazo de entrega:</b>";
      $data[$xx]["X2"] = $orderheader["sord_plazo_desc"];
      $data[$xx]["X3"] = "<b>EMail:</b>";
      $data[$xx]["X4"] = $orderheader["supp_email"];
   }
   
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf = doc_linedraw($pdf, 30, 720, 1);
   $pdf->ezText(" ", 12);
   unset($data);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   $_REPIMGS = Array();
   if($orderheader["sord_type"] == 0)
   {
      if((int)$orderheader["sord_en_lang"])
      {
         $attr = Array  (  "showHeadings" => 0, "shaded" => 2, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "shadeCol" => Array(0, 0.66, 0.65),
                           "shadeCol2" => Array(0, 0.66, 0.65),
                           "cols" =>   Array (
                           "SUPPCODE"       => Array("width" => "90", "justification" => "left"),
                           "QUANTITY"         => Array("width" => "60", "justification" => "center"),
                           "UNIT"       => Array("width" => "50", "justification" => "center"),
                           "PRODUCTS"      => Array("width" => "314", "justification" => "left"),
                           "PRICE"       => Array("width" => "60", "justification" => "right"),
                           "DISCOUNTS"   => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "65", "justification" => "right"),
                           )
                        );
         unset($data);
         $data[0]["SUPPCODE"]    = "SUPPCODE";
         $data[0]["QUANTITY"]    = "QUANTITY";
         $data[0]["UNIT"]        = "UNIT";
         $data[0]["PRODUCTS"]    = "PRODUCTS";
         $data[0]["PRICE"]       = "PRICE";
         $data[0]["DISCOUNTS"]   = "DISCOUNTS";
         $data[0]["SUBTOTAL"]    = "SUBTOTAL";

         $pdf->ezTable($data,$type,$dummy,$attr);

         unset($data);

         $attr = Array  (  "showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "cols" =>   Array (
                           "SUPPCODE"       => Array("width" => "90", "justification" => "left"),
                           "QUANTITY"         => Array("width" => "60", "justification" => "center"),
                           "UNIT"       => Array("width" => "50", "justification" => "center"),
                           "PRODUCTS"      => Array("width" => "314", "justification" => "left"),
                           "PRICE"       => Array("width" => "60", "justification" => "right"),
                           "DISCOUNTS"   => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "65", "justification" => "right"),
                           )
                        );
      }
      else
      {
         $attr = Array  (  "showHeadings" => 0, "shaded" => 2, 
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "shadeCol" => Array(0, 0.66, 0.65),
                           "shadeCol2" => Array(0, 0.66, 0.65),
                           "cols" =>   Array (
                           "CODIGO"       => Array("width" => "90", "justification" => "left"),
                           "CANT"         => Array("width" => "50", "justification" => "center"),
                           "UNIDAD"       => Array("width" => "50", "justification" => "center"),
                           "PRODUCTOS"    => Array("width" => "324", "justification" => "left"),
                           "PRECIO"       => Array("width" => "60", "justification" => "right"),
                           "DESCUENTOS"   => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "65", "justification" => "right"),
                           )
                        );
         unset($data);
         $data[0]["CODIGO"]      = "CODIGO";
         $data[0]["CANT"]        = "CANT";
         $data[0]["UNIDAD"]      = "UNIDAD";
         $data[0]["PRODUCTOS"]   = "PRODUCTOS";
         $data[0]["PRECIO"]      = "PRECIO";
         $data[0]["DESCUENTOS"]  = "DESCUENTOS";
         $data[0]["SUBTOTAL"]    = "SUBTOTAL";

         $pdf->ezTable($data,$type,$dummy,$attr);

         unset($data);
         $attr = Array  (  "showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "cols" =>   Array (
                           "CODIGO"       => Array("width" => "90", "justification" => "left"),
                           "CANT"         => Array("width" => "50", "justification" => "center"),
                           "UNIDAD"       => Array("width" => "50", "justification" => "center"),
                           "PRODUCTOS"    => Array("width" => "324", "justification" => "left"),
                           "PRECIO"       => Array("width" => "60", "justification" => "right"),
                           "DESCUENTOS"   => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "65", "justification" => "right"),
                           )
                        );
      }
      $posdata = getSupplierOrderPos($CON, $orderid);
      
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         if($posdata[$x]["item_type"] == "item" && $posdata[$x]["item_img"] != "")
         {
            $sql = " select count(*) 'cc'
                     from item_productcats
                     where
                     item_id = {$posdata[$x]["item_id"]} and
                     cat_id  = 7 ";
            $isrepuesto = $CON->select($sql);
            $isrepuesto = (int)$isrepuesto[0]["cc"];

            if($isrepuesto)
            {
               unset($_REPIMG);
               $_REPIMG["item_img"]    = $posdata[$x]["item_img"];
               $_REPIMG["item_code"]   = $posdata[$x]["item_code"];

               if($posdata[$x]["item_nameshop"] != "")
                  $posdata[$x]["item_title"] = $posdata[$x]["item_nameshop"];

               $_REPIMG["item_title"]   = $posdata[$x]["item_number_prod"]." - ".$posdata[$x]["item_title"];

               $_REPIMGS[] = $_REPIMG;
            }
         }

         if((int)$orderheader["sord_en_lang"])
         {
            if($posdata[$x]["item_nameshop"] != "")
               $posdata[$x]["item_title"] = $posdata[$x]["item_nameshop"];
               
            $data[$x]["SUPPCODE"]      = $posdata[$x]["item_code"];
            $data[$x]["QUANTITY"]      = printPrice($posdata[$x]["item_amount"],2);
            $data[$x]["UNIT"]          = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"], "small");
            $data[$x]["PRODUCTS"]      = $posdata[$x]["item_number_prod"]." - ".$posdata[$x]["item_title"];
            $data[$x]["PRICE"]         = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["DISCOUNTS"]     = $posdata[$x]["_dsc_str"];
            if($posdata[$x]["_dsc_str"] != "")
               $data[$x]["DISCOUNTS"] .= "%";
            $data[$x]["SUBTOTAL"]      = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
         else
         {
            $data[$x]["CODIGO"]     = $posdata[$x]["item_code"];
            $data[$x]["CANT"]       = printPrice($posdata[$x]["item_amount"],2);
            $data[$x]["UNIDAD"]     = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"], "small");
            $data[$x]["PRODUCTOS"]  = $posdata[$x]["item_number_prod"]." - ".$posdata[$x]["item_title"];
            $data[$x]["PRECIO"]     = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["DESCUENTOS"] = $posdata[$x]["_dsc_str"];
            if($posdata[$x]["_dsc_str"] != "")
               $data[$x]["DESCUENTOS"] .= "%";
            $data[$x]["SUBTOTAL"]   = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 12);
   }
   
   //----------------------------------------------------------------------------------
   if($orderheader["sord_type"] == 1)
   {
      if((int)$orderheader["sord_en_lang"])
      {
         $attr = Array  (  "showHeadings" => 0, "shaded" => 2, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "shadeCol" => Array(0, 0.66, 0.65),
                           "shadeCol2" => Array(0, 0.66, 0.65),
                           "cols" =>   Array (
                           "COLOR"        => Array("width" => "80", "justification" => "center"),
                           "MATERIAL"     => Array("width" => "80", "justification" => "center"),
                           "GR/M2"        => Array("width" => "80", "justification" => "center"),
                           "WIDTH/CM"     => Array("width" => "80", "justification" => "center"),
                           "LENGTH/M"     => Array("width" => "80", "justification" => "center"),
                           "QTY"          => Array("width" => "80", "justification" => "center"),
                           "QTY/KG"       => Array("width" => "80", "justification" => "center"),
                           "PRICE"        => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "80", "justification" => "center")
                           )
                        );
         unset($data);
         $data[0]["COLOR"]       = "COLOR";
         $data[0]["MATERIAL"]    = "MATERIAL";
         $data[0]["GR/M2"]       = "GR/M2";
         $data[0]["WIDTH/CM"]    = "WIDTH/CM";
         $data[0]["LENGTH/M"]    = "LENGTH/M";
         $data[0]["QTY"]         = "QTY";
         $data[0]["QTY/KG"]      = "QTY/KG";
         $data[0]["PRICE"]       = "PRICE";
         $data[0]["SUBTOTAL"]    = "SUBTOTAL";
         $pdf->ezTable($data,$type,$dummy,$attr);
         unset($data);
         
         $attr = Array  (  "showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "cols" =>   Array (
                           "COLOR"        => Array("width" => "80", "justification" => "center"),
                           "MATERIAL"     => Array("width" => "80", "justification" => "center"),
                           "GR/M2"        => Array("width" => "80", "justification" => "center"),
                           "WIDTH/CM"     => Array("width" => "80", "justification" => "center"),
                           "LENGTH/M"     => Array("width" => "80", "justification" => "center"),
                           "QTY"          => Array("width" => "80", "justification" => "center"),
                           "QTY/KG"       => Array("width" => "80", "justification" => "center"),
                           "PRICE"        => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "80", "justification" => "center")
                           )
                        );
      }
      else
      {
         $attr = Array  (  "showHeadings" => 0, "shaded" => 2, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "shadeCol" => Array(0, 0.66, 0.65),
                           "shadeCol2" => Array(0, 0.66, 0.65),
                           "cols" =>   Array (
                           "COLOR"        => Array("width" => "80", "justification" => "center"),
                           "MATERIAL"     => Array("width" => "80", "justification" => "center"),
                           "GR/M2"        => Array("width" => "80", "justification" => "center"),
                           "ANCHO/CM"     => Array("width" => "80", "justification" => "center"),
                           "LONG./M"      => Array("width" => "80", "justification" => "center"),
                           "CANTIDAD"     => Array("width" => "80", "justification" => "center"),
                           "CANT./KG"     => Array("width" => "80", "justification" => "center"),
                           "PRECIO"       => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "80", "justification" => "center")
                           )
                        );

         unset($data);
         $data[0]["COLOR"]       = "COLOR";
         $data[0]["MATERIAL"]    = "MATERIAL";
         $data[0]["GR/M2"]       = "GR/M2";
         $data[0]["ANCHO/CM"]    = "ANCHO/CM";
         $data[0]["LONG./M"]     = "LONG./M";
         $data[0]["CANTIDAD"]    = "CANTIDAD";
         $data[0]["CANT./KG"]    = "CANT./KG";
         $data[0]["PRECIO"]      = "PRECIO";
         $data[0]["SUBTOTAL"]    = "SUBTOTAL";
         $pdf->ezTable($data,$type,$dummy,$attr);
         unset($data);
         
         $attr = Array  (  "showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "cols" =>   Array (
                           "COLOR"        => Array("width" => "80", "justification" => "center"),
                           "MATERIAL"     => Array("width" => "80", "justification" => "center"),
                           "GR/M2"        => Array("width" => "80", "justification" => "center"),
                           "ANCHO/CM"     => Array("width" => "80", "justification" => "center"),
                           "LONG./M"      => Array("width" => "80", "justification" => "center"),
                           "CANTIDAD"     => Array("width" => "80", "justification" => "center"),
                           "CANT./KG"     => Array("width" => "80", "justification" => "center"),
                           "PRECIO"       => Array("width" => "80", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "80", "justification" => "center")
                           )
                        );
      }
      $posdata = getSupplierOrderPos($CON, $orderid);
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         //----------------------------------------------------------------------------------
         $item_reg_width   = 0;
         $item_reg_gsm     = 0;
         $item_reg_length  = 0;
         $item_kgs         = 0;
         $item_colorname   = "";
         $item_matname     = "";
      
         $sql = " select *
                  from item
                  where
                  id = {$posdata[$x]["item_id"]}";
         $allitemdata = $CON->select($sql);
         $allitemdata = $allitemdata[0];
         
         $item_reg_width   = (float)$allitemdata["item_reg_width"];
         $item_reg_gsm     = (float)$allitemdata["item_reg_gsm"];
         $item_reg_length  = (float)$allitemdata["item_reg_length"];
         $item_kgs         = (float)$posdata[$x]["item_kgs"];

         $sql = " select *
                  from item_productcats
                  where
                  item_id   = {$posdata[$x]["item_id"]}";
         $pcatdata = $CON->select($sql);
         $pcatdata = $pcatdata[0];

         $sql = " select *
                  from tran_comments_item_vals
                  where
                  item_id = {$posdata[$x]["item_id"]}";
         $comvals = $CON->select($sql);
         unset($_COMVALS);
         foreach($comvals AS $comval)
            $_COMVALS[$comval["com_id"]] = $comval["val_id"];

         $sql = " select t1.com_name, t3.*
                  from tran_comments t1
                  INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                  INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                  where
                  t1.com_status  > 0 and
                  t2.cat_id      = {$pcatdata["cat_id"]} and
                  t3.add_status  > 0
                  order by t1.com_name, t3.add_name";
         $trancoms = $CON->select($sql);
         foreach($trancoms AS $trancom)
         {
            if(strpos(strtoupper($trancom["com_name"]), "MATERIAL") !== false && $_COMVALS[$trancom["add_com_id"]] == $trancom["id"])
            {
               if((int)$orderheader["sord_en_lang"])
                  $item_matname = $trancom["add_name_eng"];
               else
                  $item_matname = $trancom["add_name"];
            }
               
            if(strpos(strtoupper($trancom["com_name"]), "COLOR") !== false && $_COMVALS[$trancom["add_com_id"]] == $trancom["id"])
            {
               if((int)$orderheader["sord_en_lang"])
                  $item_colorname = $trancom["add_name_eng"];
               else
                  $item_colorname = $trancom["add_name"];
            }
         }
         
         if((int)$orderheader["sord_en_lang"])
         {
            $data[$x]["COLOR"]      = $item_colorname;
            $data[$x]["MATERIAL"]   = $item_matname;
            $data[$x]["GR/M2"]      = printPrice($item_reg_gsm);  
            $data[$x]["WIDTH/CM"]   = printPrice($item_reg_width);
            $data[$x]["LENGTH/M"]   = printPrice($item_reg_length);
            $data[$x]["QTY"]        = printPrice($posdata[$x]["item_amount"],2);
            $data[$x]["QTY/KG"]     = printPrice($item_kgs);
            $data[$x]["PRICE"]      = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["SUBTOTAL"]   = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
         else
         {
            $data[$x]["COLOR"]      = $item_colorname;
            $data[$x]["MATERIAL"]   = $item_matname;
            $data[$x]["GR/M2"]      = printPrice($item_reg_gsm);  
            $data[$x]["ANCHO/CM"]   = printPrice($item_reg_width);
            $data[$x]["LONG./M"]    = printPrice($item_reg_length);
            $data[$x]["CANTIDAD"]   = printPrice($posdata[$x]["item_amount"], 2);
            $data[$x]["CANT./KG"]   = printPrice($item_kgs);
            $data[$x]["PRECIO"]     = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["SUBTOTAL"]   = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 12);
   }
   //----------------------------------------------------------------------------------
   if($orderheader["sord_type"] == 2)
   {
      if((int)$orderheader["sord_en_lang"])
      {
         $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "cols" =>   Array (
                           "QUANTITY"     => Array("width" => "80", "justification" => "center"),
                           "QUANTITY/KG"  => Array("width" => "80", "justification" => "center"),
                           "COLOR"        => Array("width" => "380", "justification" => "left"),
                           "PRICE"        => Array("width" => "90", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "90", "justification" => "center")
                           )
                        );
      }
      else
      {
         $attr = Array  (  "showHeadings" => 1
                          , "shaded" => 1
                          , "shadeCol" => Array(0.95,0.95,0.95)
                          , "xpos" => "left"
                          , "showLines" => 2
                          , "rowGap" => 2
                          , "colGap" => 3
                          , "fontSize"=> 9
                          , "cols"      => Array 
                                          (
                                             "ITEM"        => Array("width" => "60", "justification" => "center"),
                                             "DESCRIPCION" => Array("width" => "380", "justification" => "left"),
                                             "CANTIDAD"    => Array("width" => "70", "justification" => "center"),
                                             "CANT./KG"    => Array("width" => "50", "justification" => "center"),
                                             "COLOR"       => Array("width" => "380", "justification" => "left"),
                                             "PRECIO"      => Array("width" => "70", "justification" => "center"),
                                             "SUBTOTAL"    => Array("width" => "70", "justification" => "center")
                                          ) 
                        );
      }
      $posdata = getSupplierOrderPos($CON, $orderid);
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         //----------------------------------------------------------------------------------
         $item_reg_width   = 0;
         $item_reg_gsm     = 0;
         $item_reg_length  = 0;
         $item_kgs         = 0;
         $item_colorname   = "";
         $item_matname     = "";
      
         $sql = " select *
                  from item
                  where
                  id = {$posdata[$x]["item_id"]}";
         $allitemdata = $CON->select($sql);
         $allitemdata = $allitemdata[0];
         
         $item_number_prod = $allitemdata["item_number_prod"];
         $item_title       = $allitemdata["item_title"];
         $item_reg_width   = (float)$allitemdata["item_reg_width"];
         $item_reg_gsm     = (float)$allitemdata["item_reg_gsm"];
         $item_reg_length  = (float)$allitemdata["item_reg_length"];
         $item_kgs         = (float)$posdata[$x]["item_kgs"];

         $sql = " select *
                  from item_productcats
                  where
                  item_id   = {$posdata[$x]["item_id"]}";
         $pcatdata = $CON->select($sql);
         $pcatdata = $pcatdata[0];

         $sql = " select *
                  from tran_comments_item_vals
                  where
                  item_id = {$posdata[$x]["item_id"]}";
         $comvals = $CON->select($sql);
         unset($_COMVALS);
         foreach($comvals AS $comval)
            $_COMVALS[$comval["com_id"]] = $comval["val_id"];

         $sql = " select t1.com_name, t3.*
                  from tran_comments t1
                  INNER JOIN tran_comments_cats t2 ON t1.id = t2.com_id
                  INNER JOIN tran_comments_vals t3 ON t1.id = t3.add_com_id
                  where
                  t1.com_status  > 0 and
                  t2.cat_id      = {$pcatdata["cat_id"]} and
                  t3.add_status  > 0
                  order by t1.com_name, t3.add_name";
         $trancoms = $CON->select($sql);
         foreach($trancoms AS $trancom)
         {
            if(strpos(strtoupper($trancom["com_name"]), "COLOR") !== false && $_COMVALS[$trancom["add_com_id"]] == $trancom["id"])
            {
               if((int)$orderheader["sord_en_lang"])
                  $item_colorname = $trancom["add_name_eng"];
               else
                  $item_colorname = $trancom["add_name"];
            }
         }
         
         if((int)$orderheader["sord_en_lang"])
         {
           
            $data[$x]["ITEM"]         = $item_number_prod;
            $data[$x]["DESCRIPCION"]  = $item_colorname;
            $data[$x]["QUANTITY"]     = printPrice($posdata[$x]["item_amount"],2);
            $data[$x]["QUANTITY/KG"]  = printPrice($item_kgs);
            $data[$x]["PRICE"]        = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["SUBTOTAL"]     = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
         else
         {
            $data[$x]["ITEM"]        = $item_number_prod;;
            $data[$x]["DESCRIPCION"] = $item_title;
            $data[$x]["CANTIDAD"]    = printPrice($posdata[$x]["item_amount"], 2);
            $data[$x]["CANT./KG"]    = printPrice($item_kgs);
            $data[$x]["PRECIO"]      = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["SUBTOTAL"]    = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 12);
   }
   //----------------------------------------------------------------------------------
   if($orderheader["sord_type"] == 3)
   {
      $_specopts = getSuppOrderCharacts();
      
      if((int)$orderheader["sord_en_lang"])
      {
         $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "cols" =>   Array (
                           "POS"          => Array("width" => "40", "justification" => "center"),
                           "QUANTITY"     => Array("width" => "80", "justification" => "center"),
                           "PRODUCT"      => Array("width" => "419", "justification" => "left"),
                           "PRICE"        => Array("width" => "90", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "90", "justification" => "center")
                           )
                        );
      }
      else
      {
         $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                           "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                           "cols" =>   Array (
                           "POS"          => Array("width" => "40", "justification" => "center"),
                           "CANTIDAD"     => Array("width" => "80", "justification" => "center"),
                           "DESCRIPCION"  => Array("width" => "419", "justification" => "left"),
                           "PRECIO"       => Array("width" => "90", "justification" => "center"),
                           "SUBTOTAL"     => Array("width" => "90", "justification" => "center")
                           )
                        );
      }
      $posdata = getSupplierOrderPos($CON, $orderid);
      for($x = 0; $x < count($posdata) && $posdata != false; $x++)
      {
         unset($_ITEMSPECS);
         $item_specs = "";
         $sql = " select *
                  from supplier_order_items_specs
                  where
                  sord_id     = {$posdata[$x]["sord_id"]} and
                  item_id     = {$posdata[$x]["item_id"]} and
                  item_pos    = {$posdata[$x]["item_pos"]}";
         $itemspecs = $CON->select($sql);
         foreach($itemspecs AS $itemspec)
            $_ITEMSPECS[$itemspec["spec_id"]] = $itemspec["spec_value"];

         $sql = "select * from item where id = {$itemspecs[0]["item_id"]}";
         $sku = $CON->select($sql);
         $sku = $sku[0];
      
         if (!empty($sku)) 
         {
             $item_specs .= "<b>SKU:</b> {$sku["item_number_prod"]} - {$sku["item_title"]}\n";
         }
         foreach($_specopts AS $_specopt)
         {
            if($_ITEMSPECS[$_specopt["id"]] != "")
               $item_specs .= "<b>{$_specopt["name"]}:</b> {$_ITEMSPECS[$_specopt["id"]]}\n";
         }
         $item_specs = trim($item_specs);

         if($posdata[$x]["item_image"] != "")
            $_ITEMIMAGES[($x +1)] = $posdata[$x]["item_image"];

         if((int)$orderheader["sord_en_lang"])
         {
            $data[$x]["POS"]        = $x +1;
            $data[$x]["QUANTITY"]   = printPrice($posdata[$x]["item_amount"],2);
            $data[$x]["PRODUCT"]    = $item_specs;
            $data[$x]["PRICE"]      = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["SUBTOTAL"]   = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
         else
         {
            $data[$x]["POS"]        = $x +1;
            $data[$x]["CANTIDAD"]      = printPrice($posdata[$x]["item_amount"], 2);
            $data[$x]["DESCRIPCION"]   = $item_specs;
            $data[$x]["PRECIO"]        = printPrice($posdata[$x]["item_costprice_netto"], $numberlim);
            $data[$x]["SUBTOTAL"]      = printPrice($posdata[$x]["item_costprice_netto_dsc"], $numberlim);
         }
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 12);
   }

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "protectRows" => 20,
                     "xpos" => "left", "showLines" => 0, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                     "cols" =>   Array (
                     "X1"  => Array("width" => "579", "justification" => "right"),
                     "X2"  => Array("width" => "55", "justification" => "center"),
                     "X3"  => Array("width" => "85", "justification" => "right")
                     )
                  );
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $data[$pcounter]["X1"] = "SUB.TOTAL";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = $moneystr." ".printPrice($orderheader["sord_item_netto_total"], $numberlim);

   //----------------------------------------------------------------------------------
   if($orderheader["sord_value_dsc_netto_total"] > 0.00)
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "DESCUENTO POR MONTO";
      $data[$pcounter]["X2"] = printPrice($orderheader["sord_value_dsc_netto_total"] / $orderheader["sord_item_netto_total"] * 100,2)." %";
      $data[$pcounter]["X3"] = $moneystr." ".printPrice($orderheader["sord_value_dsc_netto_total"], $numberlim);
   }

   //----------------------------------------------------------------------------------
   if($orderheader["sord_payment_dsc_netto_total"] > 0.00)
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "DESCUENTO POR COND. DE PAGO";
      $data[$pcounter]["X2"] = printPrice($orderheader["sord_payment_dsc_netto_total"] / ($orderheader["sord_item_netto_total"] - $orderheader["sord_value_dsc_netto_total"]) * 100, 2)." %";
      $data[$pcounter]["X3"] = $moneystr." ".printPrice($orderheader["sord_payment_dsc_netto_total"], $numberlim);
   }

   //----------------------------------------------------------------------------------
   if($orderheader["sord_supplier_dsc_finance_total"] > 0.00)
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "DESCUENTO FINANCIERO";
      $data[$pcounter]["X2"] = printPrice($orderheader["sord_supplier_dsc_finance_total"] / ($orderheader["sord_item_netto_total"] - $orderheader["sord_value_dsc_netto_total"] - $orderheader["sord_payment_dsc_netto_total"]) * 100, 2)." %";
      $data[$pcounter]["X3"] = $moneystr." ".printPrice($orderheader["sord_supplier_dsc_finance_total"], $numberlim);
   }

   //----------------------------------------------------------------------------------
   $pcounter++;
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = "------------------";

   //----------------------------------------------------------------------------------
   if($orderheader["sord_total_taxes"] > 0.00)
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "NETO";
      $data[$pcounter]["X2"] = " ";
      $data[$pcounter]["X3"] = $moneystr." ".printPrice($orderheader["sord_total_netto"]);
      $pcounter++;
      $data[$pcounter]["X1"] = "IVA";
      $data[$pcounter]["X2"] = " ";
      $data[$pcounter]["X3"] = $moneystr." ".printPrice($orderheader["sord_total_taxes"]);
   }

   //----------------------------------------------------------------------------------
   $pcounter++;
   $data[$pcounter]["X1"] = "<b>TOTAL</b>";
   $data[$pcounter]["X2"] = " ";
   $data[$pcounter]["X3"] = "<b>".$moneystr." ".printPrice($orderheader["sord_total_brutto"], $numberlim)."</b>";

   $ycomment = $pdf->ezTable($data,$type,$dummy,$attr);

   if($orderheader["sord_total_taxes"] > 0.00)
      $pdf->ezSetY($ycomment + 90);
   else
      $pdf->ezSetY($ycomment + 70);

   unset($data);
   $pcounter = 0;
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "xpos" => "left", "showLines" => 0, "rowGap" => 0, "colGap" => 0, "fontSize"=> 10,
                     "protectRows" => 99, "cols" =>   Array (
                     "X1"  => Array("width" => "350", "justification" => "left"),
                     "X2"  => Array("width" => "375")
                     )
                  );
   if(!(int)$orderheader["sord_en_lang"])
   {
      $data[$pcounter]["X1"] = "\nPOR FAVOR MENCIONAR N° DE ORDEN DE COMPRA EN LA FACTURA";
      $data[$pcounter]["X2"] = " ";
      $pcounter++;
   }
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";

   //----------------------------------------------------------------------------------
   if(trim($orderheader["sord_desc"]) != "")
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "<b>{$orderheader["sord_desc"]}</b>";
      $data[$pcounter]["X2"] = " ";
   }
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";

   $pcounter++;
   if(!(int)$orderheader["sord_en_lang"])
      $data[$pcounter]["X1"] = "<b>ACEPTACIÓN</b>";
   else
      $data[$pcounter]["X1"] = "<b>Best regards</b>";
   $data[$pcounter]["X2"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = "<b>{$orderheader["crt_firstname"]} {$orderheader["crt_lastname"]}</b>";
   $data[$pcounter]["X2"] = " ";

   if($orderheader["user_telephone"] != "")
   {
      $pcounter++;
      if(!(int)$orderheader["sord_en_lang"])
         $data[$pcounter]["X1"] = "Teléfono: {$orderheader["user_telephone"]}";
      else
         $data[$pcounter]["X1"] = "Phone: {$orderheader["user_telephone"]}";
      $data[$pcounter]["X2"] = " ";
   }
   if($orderheader["user_cellphone"] != "")
   {
      $pcounter++;
      if(!(int)$orderheader["sord_en_lang"])
         $data[$pcounter]["X1"] = "Celular: {$orderheader["user_cellphone"]}";
      else
         $data[$pcounter]["X1"] = "Cellphone: {$orderheader["user_cellphone"]}";
      $data[$pcounter]["X2"] = " ";
   }
   if($orderheader["user_mail"] != "")
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "Email: {$orderheader["user_mail"]}";
      $data[$pcounter]["X2"] = " ";
   }

   $ty = $pdf->ezTable($data,$type,$dummy,$attr);

   if(count($_ITEMIMAGES) > 0)
   {
      foreach(array_keys($_ITEMIMAGES) AS $_IMGPOS)
      {
         $pdf->ezNewPage();
         $pdf->ezText(" ", 16);
         $pdf->ezText("<b>IMAGE REFERENCE: POS {$_IMGPOS}</b>", 16, Array("justification"  => "center"));
         $pdf->ezText(" ", 16);
         $pdf->ezText(" ", 16);

         if(strpos(strtoupper($_ITEMIMAGES[$_IMGPOS]), ".PNG") !== false)
            remove_png_alpha("./docs.supplierorder/{$_ITEMIMAGES[$_IMGPOS]}", "./docs.supplierorder/{$_ITEMIMAGES[$_IMGPOS]}", array(255,255,255));

         $pdf->ezImage("./docs.supplierorder/{$_ITEMIMAGES[$_IMGPOS]}", 0, 300, 'none', 'center', 0); // 290 -> se cambia el tamaño
      }
   }

   $_CREATEOCV2 = false;
   if(count($_REPIMGS) > 0)
   {
      $_CREATEOCV2 = true;
      foreach($_REPIMGS AS $_REPIMGS)
      {
         $pdf->ezNewPage();
         $pdf->ezText(" ", 16);
         $pdf->ezText("<b>{$_REPIMGS["item_title"]}</b>", 16, Array("justification"  => "center"));
         $pdf->ezText(" ", 16);
         $pdf->ezText(" ", 16);

         if(strpos(strtoupper($_REPIMGS["item_img"]), ".PNG") !== false)
            remove_png_alpha("./images/items/{$_REPIMGS["item_img"]}", "./images/items/{$_REPIMGS["item_img"]}", array(255,255,255));

         $pdf->ezImage("./images/items/{$_REPIMGS["item_img"]}", 0, 300, 'none', 'center', 0); // 290 -> se cambia el tamaño
      }
   }

   //----------------------------------------------------------------------------------
   if(!(int)$orderheader["sord_en_lang"])
      $pdf = printPDFFooter($CON, $pdf, $orderheader["sord_company_id"], "supplier_order", $orderheader["sord_number"], $orderheader["sord_shop_id"]);
   else
      $pdf = printPDFFooter($CON, $pdf, $orderheader["sord_company_id"], "supplier_order_en", $orderheader["sord_number"], $orderheader["sord_shop_id"]);

   //----------------------------------------------------------------------------------
   $fp = fopen($filename, "w");
   if($fp)
   {
      $pdfdata = $pdf->output();
      fwrite($fp, $pdfdata);
      fclose($fp);
   }

   //OVERRIDE OC PDF: REPUESTOS CON IMAGENES
   if($_CREATEOCV2)
   {
      define('FPDF_FONTPATH','./libs/thirdparty/fpdf17/font/');
      require('./libs/thirdparty/fpdf17/lib/pdftable.inc.php');

      $html = file_get_contents("{$_SESSION["_CONF"]["conf_shopadmin_url"]}/pdfgenerate.supporder.repuestos.php?orderid={$orderheader["id"]}");

      // echo "{$_SESSION["_CONF"]["conf_shopadmin_url"]}/pdfgenerate.supporder.repuestos.php?orderid={$orderheader["id"]}";
      // echo "<hr>";
      // var_dump($html);
      // exit;
      //----------------------------------------------------------------------------------
      $p = new PDFTable();
      $p->setfont('Helvetica','',10);
      $p->AddFont('comesinhandy','','comesinhandy.php');
      $p->SetPadding(2);
      $p->SetSpacing(2);
      $p->SetMargins(10,10,10,10);
      $p->AddPage("P", array(216,356));
      $p->SetPadding(2);
      $p->SetSpacing(1);
      $p->SetMargins(0,0,0,0);
      $p->htmltable($html);
      $buffer     = $p->output('','S');
      file_put_contents($filename, $buffer);
   }
}



function png_has_alpha($path, $sampleStep = 1) {
    if (!is_file($path)) return false;

    $img = @imagecreatefrompng($path);
    if (!$img) return false;

    $w = imagesx($img);
    $h = imagesy($img);

    // sampleStep=1 revisa todos los píxeles; 2 revisa 1 de cada 4; 4 revisa 1 de cada 16, etc.
    $step = max(1, (int)$sampleStep);

    for ($y = 0; $y < $h; $y += $step) {
        for ($x = 0; $x < $w; $x += $step) {
            $rgba = imagecolorat($img, $x, $y);

            // En GD para truecolor: alpha son los 7 bits altos (0 = opaco, 127 = totalmente transparente)
            $alpha = ($rgba & 0x7F000000) >> 24;

            if ($alpha > 0) { // hay transparencia (semi o total)
                imagedestroy($img);
                return true;
            }
        }
    }

    imagedestroy($img);
    return false;
}

/**
 * Quita alpha “aplanando” el PNG sobre un color de fondo y guardando un PNG sin alpha.
 * $bgColorRGB: array(r,g,b)
 */
function remove_png_alpha($srcPath, $dstPath, $bgColorRGB = array(255,255,255)) {
    if (!is_file($srcPath)) {
        throw new RuntimeException("No existe: $srcPath");
    }

    $src = @imagecreatefrompng($srcPath);
    if (!$src) {
        throw new RuntimeException("No pude abrir PNG: $srcPath");
    }

    $w = imagesx($src);
    $h = imagesy($src);

    // Lienzo destino SIN alpha
    $dst = imagecreatetruecolor($w, $h);

    // Rellenar fondo (ej. blanco)
    $bg = imagecolorallocate($dst, $bgColorRGB[0], $bgColorRGB[1], $bgColorRGB[2]);
    imagefilledrectangle($dst, 0, 0, $w, $h, $bg);

    // Importante: desactivar alpha en destino antes de copiar
    imagealphablending($dst, true);
    imagesavealpha($dst, false);

    // Copiar encima: GD mezcla alpha del src sobre el fondo del dst
    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);

    // Guardar PNG sin alpha (compresión 0-9)
    if (!imagepng($dst, $dstPath, 6)) {
        imagedestroy($src);
        imagedestroy($dst);
        throw new RuntimeException("No pude guardar: $dstPath");
    }

    imagedestroy($src);
    imagedestroy($dst);
}
?>