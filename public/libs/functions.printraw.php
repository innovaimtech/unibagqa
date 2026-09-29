<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function printDeliveryDiff($CON, $id, $mode = "", $precalc = 0)
{
   $currtme = time();

   //----------------------------------------------------------------------------------
   if($mode == "ordersdelivery")
   {
      $sql = " select t1.*, t2.cust_name, t3.req_number, t3.req_order_shipped, t4.company_short, t5.shop_name, t8.trans_name, t2.cust_notes, t9.pay_title,
                      t6.user_firstname 'upd_firstname', t6.user_lastname 'upd_lastname',
                      t7.user_firstname 'crt_firstname', t7.user_lastname 'crt_lastname',
                      t10.user_firstname 'sell_firstname', t10.user_lastname 'sell_lastname'
               from orders_delivery t1
               INNER JOIN customer t2              ON t1.dlv_cust_id       = t2.id
               LEFT OUTER JOIN orders t3           ON t1.dlv_order_id      = t3.id
               LEFT OUTER JOIN company_data t4     ON t1.dlv_company_id    = t4.id
               LEFT OUTER JOIN company_shops t5    ON t1.dlv_shop_id       = t5.id
               LEFT OUTER JOIN user t6             ON t1.dlv_updusr        = t6.id
               LEFT OUTER JOIN user t7             ON t1.dlv_crtusr        = t7.id
               LEFT OUTER JOIN transports t8       ON t1.dlv_transportid   = t8.id
               LEFT OUTER JOIN payments t9         ON t1.dlv_paymentid     = t9.id
               LEFT OUTER JOIN user t10            ON t3.req_userid_seller = t10.id
               where
               t1.id = {$id}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
               from customer t1
               LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
               where
               t1.id = {$headdata["dlv_cust_id"]}";
      $customer = $CON->select($sql);
      $customer = $customer[0];

      if($customer["name"] != "SANTIAGO")
         $customer["name"] = $customer["nombre"];

      //----------------------------------------------------------------------------------
      $headertopspc  = 1;
      $headerposx1   = 5;
      $headerwidth1  = 10;
      $headerposx2   = 16;
      $headerwidth2  = 60;
      $headerposx3   = 90;
      $headerwidth3  = 13;
      $headerposx4   = 103;
      $headerwidth4  = 20;

      echo $lineheight1;

      for($x = $x; $x < $headertopspc; $x++)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $headerposx1;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      }

      //----------------------------------------------------------------------------------
      // HEADER
      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = "GUIA";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $headdata["dlv_docnum"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "GUIA INT";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $headdata["dlv_num"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "CLIENTE";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["cust_name"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "RUT";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $customer["cust_rut"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "DIRECCION";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["cust_street"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "VENDEDOR";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $headdata["sell_firstname"]." ".$headdata["sell_lastname"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "CIUDAD";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["name"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "COMUNA";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $customer["nombre"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "VENTA";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $headdata["req_number"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "FECHA";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = displaydate($currtme);
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;

      $posdata = getOrderDeliveryPos($CON, $id, "prodnumber");

      for($y = 0; $y < count($posdata) && $posdata != false; $y++)
      {
         if($posdata[$y]["item_amount"] > 0 && $posdata[$y]["item_amount_shipped_orig"] < $posdata[$y]["order_amount_orig"] && !(int)$posdata[$y]["item_amount_shipped_stop"])
         {
            $posdata[$y]["item_amount_open"]          = $posdata[$y]["order_amount_orig"] - $posdata[$y]["item_amount_shipped_orig"];
            $posdata[$y]["item_amount"]               = $posdata[$y]["order_amount_orig"];
            $posdata[$y]["item_sellprice_netto_orig"] = $posdata[$y]["item_sellprice_netto_orig"];

            if((int)$precalc)
            {
               $posdata[$y]["item_amount_open"] -= $posdata[$y]["item_amount_shipped"];
               if($posdata[$y]["item_amount_open"] > 0.00)
                  $resposdata[] = $posdata[$y];
            }
            else
               $resposdata[] = $posdata[$y];
         }
      }
      $posdata = $resposdata;
      usort($posdata, "orderByProductNumberCallback");
   }
   //----------------------------------------------------------------------------------
   elseif($mode == "invoicessell")
   {
      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name,
                      t2.cust_notes,  t7.pay_title, t8.trans_name, t2.cust_notes,
                      t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                      t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname',
                      t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                      t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
               from invoices_sell t1
               LEFT OUTER JOIN customer t2      ON t1.invc_cust_id         = t2.id
               LEFT OUTER JOIN company_data t3  ON t1.invc_company_id      = t3.id
               LEFT OUTER JOIN company_shops t4 ON t1.invc_shop_id         = t4.id
               LEFT OUTER JOIN user t5          ON t1.invc_updusr          = t5.id
               LEFT OUTER JOIN user t6          ON t1.invc_crtusr          = t6.id
               LEFT OUTER JOIN payments t7      ON t1.invc_paymentid       = t7.id
               LEFT OUTER JOIN transports t8    ON t1.invc_transportid     = t8.id
               LEFT OUTER JOIN user t9          ON t1.invc_userid_seller   = t9.id
               LEFT OUTER JOIN user t10         ON t1.invc_userid_cashing  = t10.id
               where
               t1.id = {$id}";
      $headdata = $CON->select($sql);
      $headdata = $headdata[0];

      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
               from customer t1
               LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
               where
               t1.id = {$headdata["invc_cust_id"]}";
      $customer = $CON->select($sql);
      $customer = $customer[0];

      if($customer["name"] != "SANTIAGO")
         $customer["name"] = $customer["nombre"];

      $invcparts  = getInvoiceSellParts($CON, $id);

      //----------------------------------------------------------------------------------
      // PRODUCTOS QUE FALTAN COMPLETAMENTE EN LA FACTURA DE LA NOTA
      //----------------------------------------------------------------------------------
      $resposdata = Array();
      
      if($headdata["invc_status"] == 2 || $precalc == 1)
      {
         $sql = " select t3.*, t1.item_amount 'order_amount', t1.item_amount_shipped 'order_amount_shipped',
                         t1.req_id 'order_req_id', t1.item_id 'order_item_id', t1.item_pos 'order_item_pos'
                  from orders_items  t1
                  INNER JOIN invoices_sell_parts t2            ON ( t1.req_id    = t2.part_req_id )
                  LEFT OUTER JOIN invoices_sell_parts_items t3 ON ( t3.invc_id   = t2.part_invc_id and
                                                                    t3.part_id   = t2.id and
                                                                    t1.item_pos  = t3.item_order_pos and
                                                                    t1.item_id   = t3.item_id and
                                                                    t1.item_type = t3.item_type and
                                                                    t3.item_type   IN ('item', 'itemlist'))
                  where
                  t2.part_invc_id = {$id} and
                  t1.item_amount_shipped_stop = 0";
         $checkorderdata = $CON->select($sql);
         for($x = 0; $x < count($checkorderdata) && $checkorderdata != false; $x++)
         {
            if(!(int)$checkorderdata[$x]["invc_id"] && !(int)$checkorderdata[$x]["part_id"] && !(int)$checkorderdata[$x]["item_id"])
            {
               $orderposdata = getOrderPos($CON, $checkorderdata[$x]["order_req_id"]);
               foreach($orderposdata AS $orderposrow)
               {
                  if($orderposrow["item_id"] == $checkorderdata[$x]["order_item_id"] &&
                     $orderposrow["item_pos"] == $checkorderdata[$x]["order_item_pos"])
                  {
                     $temp["item_number_prod"]     = $orderposrow["item_number_prod"];
                     $temp["item_title"]           = $orderposrow["item_title"];
                     $temp["unit_name"]            = $orderposrow["unit_name"];
                     $temp["item_amount"]          = $orderposrow["item_amount"];
                     $temp["item_amount_shipped_comment"] = $orderposrow["item_amount_shipped_comment"];

                     $sql = " select item_code
                              from {$orderposrow["item_type"]}_suppliers
                              where
                              item_id = {$orderposrow["item_id"]} and
                              item_supp_act = 1";
                     $item_code = $CON->select($sql);
                     
                     $temp["item_code"]            = $item_code[0]["item_code"];
                     $temp["item_amount_shipped"]  = 0.00;
                     $temp["item_amount_open"]     = $orderposrow["item_amount"] - $orderposrow["item_amount_shipped"];

                     $temp["item_sellprice_netto_orig"]  = $orderposrow["item_sellprice_netto"];
                     $temp["item_sellprice_netto"]       = $orderposrow["item_sellprice_netto"];

                     if($temp["item_amount_open"] > 0.00)
                        $resposdata[] = $temp;
                  }
               }
            }
         }
      }
      foreach($invcparts AS $invcpart)
      {
         $_REQNUMBERS[$invcpart["req_number"]] = 1;
         $posdata = getInvoiceSellPartsItems($CON, $id, $invcpart["id"], "prodnumber");
         for($y = 0; $y < count($posdata) && $posdata != false; $y++)
         {
            if($posdata[$y]["order_amount"] > 0 && $posdata[$y]["order_amount_shipped"] < $posdata[$y]["order_amount"] && !(int)$posdata[$y]["item_amount_shipped_stop"])
            {
               $tempamt = $posdata[$y]["item_amount"];
               $tempshp = $posdata[$y]["order_amount_shipped"];
               $posdata[$y]["item_amount_shipped"] = $posdata[$y]["item_amount"];
               $posdata[$y]["item_amount"]         = $posdata[$y]["order_amount"];
               $posdata[$y]["item_amount_open"]    = $posdata[$y]["order_amount"] - $posdata[$y]["order_amount_shipped"];

               if((int)$precalc)
               {
                  $posdata[$y]["item_amount_open"] -= $posdata[$y]["item_amount_shipped"];
                  if($posdata[$y]["item_amount_open"] > 0.00)
                     $resposdata[] = $posdata[$y];
               }
               else
                  $resposdata[] = $posdata[$y];
            }
         }
      }
      
      foreach(array_keys($_REQNUMBERS) AS $reqnum)
         $reqnumstr .= $reqnum.",";
      $reqnumstr = substr($reqnumstr, 0, -1);
      $posdata = $resposdata;
      
      usort($posdata, "orderByProductNumberCallback");

      //----------------------------------------------------------------------------------
      $headertopspc  = 1;
      $headerposx1   = 5;
      $headerwidth1  = 10;
      $headerposx2   = 16;
      $headerwidth2  = 60;
      $headerposx3   = 90;
      $headerwidth3  = 13;
      $headerposx4   = 103;
      $headerwidth4  = 20;

      echo $lineheight1;

      for($x = $x; $x < $headertopspc; $x++)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $headerposx1;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      }

      //----------------------------------------------------------------------------------
      // HEADER
      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = "FACTURA";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $headdata["invc_docnumber"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      /*
      $data[$x]["COL3"]["DATA"]     = "FACTURA INT";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $headdata["invc_number"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      */
      $x++;
      $data[$x]["COL1"]["DATA"]     = "CLIENTE";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["cust_name"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "RUT";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $customer["cust_rut"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "DIRECCION";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["cust_street"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "VENDEDOR";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $headdata["seller_firstname"]." ".$headdata["seller_lastname"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "CIUDAD";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["name"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "COMUNA";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = $customer["nombre"];
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "VENTA";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $reqnumstr;
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $data[$x]["COL3"]["DATA"]     = "FECHA";
      $data[$x]["COL3"]["XPOS"]     = $headerposx3;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth3;
      $data[$x]["COL4"]["DATA"]     = displaydate($currtme);
      $data[$x]["COL4"]["XPOS"]     = $headerposx4;
      $data[$x]["COL4"]["WIDTH"]    = $headerwidth4;
      $x++;
   }

   //----------------------------------------------------------------------------------
   // ITEMS
   //----------------------------------------------------------------------------------
   $itemstopspc   = 0;
   $itemposx1     = 5;
   $itemwidth1    = 9;
   $itemposx2     = 16;
   $itemwidth2    = 14;
   $itemposx3     = 31;
   $itemwidth3    = 52;
   $itemposx4     = 84;
   $itemwidth4    = 8;
   $itemposx5     = 87;
   $itemwidth5    = 8;
   $itemposx6     = 95;
   $itemwidth6    = 8;
   $itemposx7     = 103;
   $itemwidth7    = 10;
   $itemposx8     = 113;
   $itemwidth8    = 18;

   $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

   $data[$x]["COL1"]["DATA"]     = "NUMERO";
   $data[$x]["COL1"]["XPOS"]     = $itemposx1;
   $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;
   $data[$x]["COL2"]["DATA"]     = "CODIGO/PROV";
   $data[$x]["COL2"]["XPOS"]     = $itemposx2;
   $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
   $data[$x]["COL3"]["DATA"]     = "ARTICULO";
   $data[$x]["COL3"]["XPOS"]     = $itemposx3;
   $data[$x]["COL3"]["WIDTH"]    = $itemwidth3;
   $data[$x]["COL4"]["DATA"]     = "UNIDAD";
   $data[$x]["COL4"]["XPOS"]     = $itemposx4;
   $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;

   /*
   $data[$x]["COL4"]["DATA"]     = "ORDEN";
   $data[$x]["COL4"]["XPOS"]     = $itemposx4;
   $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;
   $data[$x]["COL5"]["DATA"]     = "DESP";
   $data[$x]["COL5"]["XPOS"]     = $itemposx5;
   $data[$x]["COL5"]["WIDTH"]    = $itemwidth5;
   */
   $data[$x]["COL6"]["DATA"]     = "PEND";
   $data[$x]["COL6"]["XPOS"]     = $itemposx6;
   $data[$x]["COL6"]["WIDTH"]    = $itemwidth6;
   $data[$x]["COL7"]["DATA"]     = "PRECIO/U";
   $data[$x]["COL7"]["XPOS"]     = $itemposx7;
   $data[$x]["COL7"]["WIDTH"]    = $itemwidth7;
   $data[$x]["COL8"]["DATA"]     = "OBSERVACIONES";
   $data[$x]["COL8"]["XPOS"]     = $itemposx8;
   $data[$x]["COL8"]["WIDTH"]    = $itemwidth8;

   $x++;

   $itemlines = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($posdata) && $posdata != false; $y++)
   {
      $data[$x]["COL1"]["DATA"]     = $posdata[$y]["item_number_prod"];
      $data[$x]["COL1"]["XPOS"]     = $itemposx1;
      $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;

      $data[$x]["COL2"]["DATA"]     = $posdata[$y]["item_code"];
      $data[$x]["COL2"]["XPOS"]     = $itemposx2;
      $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;

      $data[$x]["COL3"]["DATA"]     = $posdata[$y]["item_title"];
      $data[$x]["COL3"]["XPOS"]     = $itemposx3;
      $data[$x]["COL3"]["WIDTH"]    = $itemwidth3;

      $data[$x]["COL4"]["DATA"]     = $posdata[$y]["unit_name"];
      $data[$x]["COL4"]["XPOS"]     = $itemposx4;
      $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;

      /*
      $data[$x]["COL4"]["DATA"]     = printPrice($posdata[$y]["item_amount"],2);
      $data[$x]["COL4"]["XPOS"]     = $itemposx4;
      $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;

      $data[$x]["COL5"]["DATA"]     = printPrice($posdata[$y]["item_amount_shipped"],2);
      $data[$x]["COL5"]["XPOS"]     = $itemposx5;
      $data[$x]["COL5"]["WIDTH"]    = $itemwidth5;
      */
      
      $data[$x]["COL6"]["DATA"]     = printPrice($posdata[$y]["item_amount_open"],2);
      $data[$x]["COL6"]["XPOS"]     = $itemposx6;
      $data[$x]["COL6"]["WIDTH"]    = $itemwidth6;

      $data[$x]["COL7"]["DATA"]     = printPrice($posdata[$y]["item_sellprice_netto_orig"]);
      $data[$x]["COL7"]["XPOS"]     = $itemposx7;
      $data[$x]["COL7"]["WIDTH"]    = $itemwidth7;

      $data[$x]["COL8"]["DATA"]     = $posdata[$y]["item_amount_shipped_comment"];
      $data[$x]["COL8"]["XPOS"]     = $itemposx8;
      $data[$x]["COL8"]["WIDTH"]    = $itemwidth8;

      if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
      {
         $titlearr = explode("\n", $posdata[$y]["item_title"]);
         $data[$x]["COL2"]["DATA"] = trim($titlearr[0]);
         for($zz = 1; $zz < count($titlearr); $zz++)
         {
            $x++;
            $itemlines++;
            $data[$x]["COL2"]["DATA"]     = trim($titlearr[$zz]);
            $data[$x]["COL2"]["XPOS"]     = $itemposx2;
            $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
         }
      }

      if($posdata[$y]["item_sellprice_netto_orig"] != $posdata[$y]["item_sellprice_netto"])
      {
         $x++;
         $itemlines++;
         $data[$x]["COL2"]["DATA"] = "!!! NUEVO PRECIO: ".printPrice($posdata[$y]["item_sellprice_netto"]);
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
      }

      $x++;
      $itemlines++;
   }

   printRAWTable($data, $form, $mode);
   formFeed();
}

//----------------------------------------------------------------------------------
function printInvoiceSellNote($CON, $noteid, $mode = "")
{
   global $_LANG;
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name, 
                   t2.cust_notes,  t7.pay_title,
                   t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                   t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
            from invoices_notes_sell t1
            LEFT OUTER JOIN customer t2      ON t1.note_cust_id      = t2.id
            LEFT OUTER JOIN company_data t3  ON t1.note_company_id   = t3.id
            LEFT OUTER JOIN company_shops t4 ON t1.note_shop_id      = t4.id
            LEFT OUTER JOIN payments t7      ON t1.note_paymentid    = t7.id
            LEFT OUTER JOIN user t9          ON t1.note_userid_seller   = t9.id
            LEFT OUTER JOIN user t10         ON t1.note_userid_cashing  = t10.id
            where
            t1.id = {$noteid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $sql = " select distinct item_invc_id
            from invoices_notes_sell_items
            where
            note_id = {$noteid} and
            item_invc_id > 0";
   $pinvcid = $CON->select($sql);
   $pinvcid = $pinvcid[0]["item_invc_id"];

   if($headdata["note_type_contype"] == 0)
   {
      $sql = " select t9.user_firstname, t9.user_lastname
               from invoices_sell t1
               INNER JOIN user t9  ON t1.invc_userid_seller = t9.id
               where
               t1.id = {$pinvcid}";
      $seller = $CON->select($sql);
      $seller = $seller[0];
   }
   else
   {
      $seller["user_firstname"]  = $headdata["seller_firstname"];
      $seller["user_lastname"]   = $headdata["seller_lastname"];
   }

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            LEFT OUTER JOIN giros   t5 ON t1.cust_giroid    = t5.id
            where
            t1.id = {$headdata["note_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];

   if($customer["name"] != "SANTIAGO")
      $customer["name"] = $customer["nombre"];

   // NOTA DE CREDITO
   //if($headdata["note_type"] == "1")
   //{
      //----------------------------------------------------------------------------------
      $lend       = "\r\n";
      $date_day   = date('d', $headdata["note_date"]);
      $date_month = date('m', $headdata["note_date"]) -1;
      $date_month = $_LANG["MODULE"]["CAL"][$date_month];
      $date_year  = date('Y', $headdata["note_date"]);

      //----------------------------------------------------------------------------------
      $x = 0;
      $lineheight1 = chr(27).chr(37).chr(57).chr(23);
      $lineheight2 = chr(27).chr(37).chr(57).chr(38);
      $lineheight3 = chr(27).chr(37).chr(57).chr(41);
      $lineheight4 = chr(27).chr(37).chr(57).chr(36);
      $lineheight5 = chr(27).chr(37).chr(57).chr(30);
      $lineheight6 = chr(27).chr(37).chr(57).chr(44);
      
      //----------------------------------------------------------------------------------
      if($mode == "preview")
      {
         $lineheight1 = "";
         $lineheight2 = "";
         $lineheight3 = "";
         $lineheight4 = "";
         $lineheight5 = "";
         $lineheight6 = "";
      }

      //----------------------------------------------------------------------------------
      $headertopspc  = 5;
      $headerposx1   = 28;
      $headerwidth1  = 60;
      $headerposx2   = 103;
      $headerwidth2  = 34;

      if($mode == "")
      {
         echo $lineheight1;
         
         for($x = $x; $x < $headertopspc; $x++)
         {
            $data[$x]["COL1"]["DATA"]     = " ";
            $data[$x]["COL1"]["XPOS"]     = $headerposx1;
            $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
         }
      }
      if($headdata["note_status"] < 2)
      {
         $data[$x]["COL1"]["DATA"]     = $headdata["note_number"];
         $data[$x]["COL1"]["XPOS"]     = $headerposx2;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth2;
         $x++;
      }
      else
      {
         $data[$x]["COL1"]["DATA"]     = $headdata["note_docnumber"];
         $data[$x]["COL1"]["XPOS"]     = $headerposx2;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth2;
         $x++;
      }
      
      $data[$x]["COL1"]["DATA"]     = " ";$data[$x]["COL1"]["XPOS"] = $headerposx1; $data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = " ";$data[$x]["COL1"]["XPOS"] = $headerposx1; $data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight6." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

      //----------------------------------------------------------------------------------
      // HEADER
      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = $lineheight5."{$date_day} de {$date_month} de {$date_year}";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = "    ".$customer["cust_phone"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["cust_company"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["cust_email"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["cust_street"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["cust_rut"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["giro_name"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $headdata["pay_title"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["nombre"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $seller["user_firstname"]." ".$seller["user_lastname"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["name"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
   
      //----------------------------------------------------------------------------------
      // ITEMS
      //----------------------------------------------------------------------------------
      $posdata = getInvoiceSellNoteItems($CON, $noteid, "prodnumber");

      $itemstopspc   = 0;
      $itemsmaxlines = 27;
      $itemposx1     = 10;
      $itemwidth1    = 9;
      $itemposx2     = 20;
      $itemwidth2    = 50;
      $itemposx3     = 75;
      $itemwidth3    = 8;
      $itemposx4     = 85;
      $itemwidth4    = 8;
      $itemposx5     = 95;
      $itemwidth5    = 10;
      $itemposx6     = 110;
      $itemwidth6    = 3;
      $itemposx10    = 114;
      $itemwidth10   = 9;
      
      $spacerows = $x + $itemstopspc;
      for($x = $x; $x < $spacerows; $x++)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $headerposx1;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      }

      $data[$x]["COL1"]["DATA"]     = $lineheight4." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

      $itemlines = 0;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($posdata) && $posdata != false; $y++)
      {
         $item_price = $posdata[$y]["item_sellprice_netto_dsc"] / $posdata[$y]["item_amount"];

         $data[$x]["COL1"]["DATA"]     = reformatProdNumber($posdata[$y]["item_number_prod"]);
         $data[$x]["COL1"]["XPOS"]     = $itemposx1;
         $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;

         $data[$x]["COL2"]["DATA"]     = $posdata[$y]["item_title"];
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
         if($posdata[$y]["item_code"] != "")
            $data[$x]["COL2"]["DATA"] .= " - {$posdata[$y]["item_code"]}";

         $data[$x]["COL3"]["DATA"]     = $posdata[$y]["unit_name"];
         $data[$x]["COL3"]["XPOS"]     = $itemposx3;
         $data[$x]["COL3"]["WIDTH"]    = $itemwidth3;

         $data[$x]["COL4"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_amount"],2),5);
         $data[$x]["COL4"]["XPOS"]     = $itemposx4;
         $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;

         $data[$x]["COL5"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_sellprice_netto"]),9);
         $data[$x]["COL5"]["XPOS"]     = $itemposx5;
         $data[$x]["COL5"]["WIDTH"]    = $itemwidth5;

         $dscarr = explode("-",$posdata[$y]["_dsc_str"]);

         $dsccol = 6;
         for($zz = 0; $zz < 4; $zz++)
         {
            $data[$x]["COL{$dsccol}"]["DATA"]     = $dscarr[$zz];
            $data[$x]["COL{$dsccol}"]["XPOS"]     = $itemposx6 + ($zz * 5);
            $data[$x]["COL{$dsccol}"]["WIDTH"]    = $itemwidth6;
            $dsccol++;
         }

         $data[$x]["COL10"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_sellprice_netto_dsc"]),9);
         $data[$x]["COL10"]["XPOS"]     = $itemposx10;
         $data[$x]["COL10"]["WIDTH"]    = $itemwidth10;

         if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
         {
            $titlearr = explode("\n", $posdata[$y]["item_title"]);
            $data[$x]["COL2"]["DATA"] = trim($titlearr[0]);
            for($zz = 1; $zz < count($titlearr); $zz++)
            {
               $x++;
               $itemlines++;
               $data[$x]["COL2"]["DATA"]     = trim($titlearr[$zz]);
               $data[$x]["COL2"]["XPOS"]     = $itemposx2;
               $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
            }
         }
      
         $x++;
         $itemlines++;
      }

      //----------------------------------------------------------------------------------
      $spacerows = $itemsmaxlines - $itemlines;
      $spacerows += 4;
      for($y = 0; $y < $spacerows; $y++)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $headerposx1;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
         $x++;
      }
      
      $lastxpos   = $x;
      $moneytext  = "Son: ".Num2Text($headdata["note_total_brutto"])." PESOS";
      $moneytext  = str_replace("  ", " ", $moneytext);
      $marr       = Array();
      
      if(strpos($moneytext, " MIL ") !== false && strlen($moneytext) > 90)
      {
         $marr = explode(" MIL ", $moneytext);
         $marr[0] .= " MIL";
      }
      else
         $marr[0] = $moneytext;

      foreach($marr AS $moneystr)
      {
         $data[$x]["COL1"]["DATA"]     = $moneystr;
         $data[$x]["COL1"]["XPOS"]     = 10;
         $data[$x]["COL1"]["WIDTH"]    = 90;
         $x++;
      }

      $x = $lastxpos;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      
      $data[$x]["COL2"]["DATA"]     = printPrice($headdata["note_total_netto"] - $headdata["note_weightprice_netto"]);
      $data[$x]["COL2"]["XPOS"]     = 59;
      $data[$x]["COL2"]["WIDTH"]    = 13;
      $data[$x]["COL3"]["DATA"]     = printPrice($headdata["note_weightprice_netto"]);
      $data[$x]["COL3"]["XPOS"]     = 73;
      $data[$x]["COL3"]["WIDTH"]    = 13;
      $data[$x]["COL4"]["DATA"]     = printPrice($headdata["note_total_netto"]);
      $data[$x]["COL4"]["XPOS"]     = 89;
      $data[$x]["COL4"]["WIDTH"]    = 13;
      $data[$x]["COL5"]["DATA"]     = printPrice($headdata["note_total_taxes"]);
      $data[$x]["COL5"]["XPOS"]     = 110;
      $data[$x]["COL5"]["WIDTH"]    = 13;
      $data[$x]["COL6"]["DATA"]     = printPrice($headdata["note_total_brutto"]);
      $data[$x]["COL6"]["XPOS"]     = 128;
      $data[$x]["COL6"]["WIDTH"]    = 9;
      //----------------------------------------------------------------------------------
   /*
   }
   else
   {
      //----------------------------------------------------------------------------------
      $lend       = "\r\n";
      $date_day   = date('d', $headdata["note_date"]);
      $date_month = date('m', $headdata["note_date"]) -1;
      $date_month = $_LANG["MODULE"]["CAL"][$date_month];
      $date_year  = date('Y', $headdata["note_date"]);

      //----------------------------------------------------------------------------------
      $x = 0;
      $lineheight1 = chr(27).chr(37).chr(57).chr(23);
      $lineheight2 = chr(27).chr(37).chr(57).chr(38);
      $lineheight3 = chr(27).chr(37).chr(57).chr(41);
      $lineheight4 = chr(27).chr(37).chr(57).chr(36);
      $lineheight5 = chr(27).chr(37).chr(57).chr(30);
      $lineheight6 = chr(27).chr(37).chr(57).chr(44);
      $lineheight7 = chr(27).chr(37).chr(57).chr(27);
      
      //----------------------------------------------------------------------------------
      if($mode == "preview")
      {
         $lineheight1 = "";
         $lineheight2 = "";
         $lineheight3 = "";
         $lineheight4 = "";
         $lineheight5 = "";
         $lineheight6 = "";
         $lineheight7 = "";
      }

      //----------------------------------------------------------------------------------
      $headertopspc  = 8;
      $headerposx1   = 28;
      $headerwidth1  = 60;
      $headerposx2   = 103;
      $headerwidth2  = 34;

      if($mode == "")
      {
         echo $lineheight1;
         
         for($x = $x; $x < $headertopspc; $x++)
         {
            $data[$x]["COL1"]["DATA"]     = " ";
            $data[$x]["COL1"]["XPOS"]     = $headerposx1;
            $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
         }
      }

      $data[$x]["COL1"]["DATA"]     = $lineheight7." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

      //----------------------------------------------------------------------------------
      // HEADER
      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = $lineheight6."{$date_day}            {$date_month}";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = 30;
      $data[$x]["COL2"]["DATA"]     = "    ".$date_year;
      $data[$x]["COL2"]["XPOS"]     = 68;
      $data[$x]["COL2"]["WIDTH"]    = 9;
      $data[$x]["COL3"]["DATA"]     = "    ".$customer["name"];
      $data[$x]["COL3"]["XPOS"]     = $headerposx2;
      $data[$x]["COL3"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["cust_company"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["cust_rut"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["cust_street"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $customer["giro_name"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

      //----------------------------------------------------------------------------------
      // ITEMS
      //----------------------------------------------------------------------------------
      $posdata = getInvoiceSellNoteItems($CON, $noteid, "prodnumber");
      
      $itemstopspc   = 0;
      $itemsmaxlines = 12;
      $itemposx1     = 17;
      $itemwidth1    = 9;
      $itemposx2     = 26;
      $itemwidth2    = 75;
      $itemposx5     = 105;
      $itemwidth5    = 10;
      $itemposx6     = 126;
      $itemwidth6    = 10;
      
      $spacerows = $x + $itemstopspc;
      for($x = $x; $x < $spacerows; $x++)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $headerposx1;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      }

      $data[$x]["COL1"]["DATA"]     = $lineheight4." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

      $itemlines = 0;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($posdata) && $posdata != false; $y++)
      {
         $item_price = $posdata[$y]["item_sellprice_netto_dsc"] / $posdata[$y]["item_amount"];

         $data[$x]["COL1"]["DATA"]     = $posdata[$y]["item_number_prod"];
         $data[$x]["COL1"]["XPOS"]     = $itemposx1;
         $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;

         $data[$x]["COL2"]["DATA"]     = $posdata[$y]["item_title"];
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;

         $data[$x]["COL3"]["DATA"]     = fillBlanksResverse(printPrice($item_price),10);
         $data[$x]["COL3"]["XPOS"]     = $itemposx5;
         $data[$x]["COL3"]["WIDTH"]    = $itemwidth5;

         $data[$x]["COL4"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_sellprice_netto_dsc"]),10);
         $data[$x]["COL4"]["XPOS"]     = $itemposx6;
         $data[$x]["COL4"]["WIDTH"]    = $itemwidth6;

         if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
         {
            $titlearr = explode("\n", $posdata[$y]["item_title"]);
            $data[$x]["COL2"]["DATA"] = trim($titlearr[0]);
            for($zz = 1; $zz < count($titlearr); $zz++)
            {
               $x++;
               $itemlines++;
               $data[$x]["COL2"]["DATA"]     = trim($titlearr[$zz]);
               $data[$x]["COL2"]["XPOS"]     = $itemposx2;
               $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
            }
         }
      
         $x++;
         $itemlines++;
      }
      if($headdata["note_weightprice_netto"] > 0.00)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $itemposx1;
         $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;

         $data[$x]["COL2"]["DATA"]     = "CONDUCCION";
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;

         $data[$x]["COL3"]["DATA"]     = fillBlanksResverse(printPrice($headdata["note_weightprice_netto"]),10);
         $data[$x]["COL3"]["XPOS"]     = $itemposx5;
         $data[$x]["COL3"]["WIDTH"]    = $itemwidth5;

         $data[$x]["COL4"]["DATA"]     = fillBlanksResverse(printPrice($headdata["note_weightprice_netto"]),10);
         $data[$x]["COL4"]["XPOS"]     = $itemposx6;
         $data[$x]["COL4"]["WIDTH"]    = $itemwidth6;
      
         $x++;
         $itemlines++;
      }
      if($headdata["note_total_taxes"] > 0.00)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $itemposx1;
         $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;

         $data[$x]["COL2"]["DATA"]     = "IVA";
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;

         $data[$x]["COL3"]["DATA"]     = fillBlanksResverse(printPrice($headdata["note_total_taxes"]),10);
         $data[$x]["COL3"]["XPOS"]     = $itemposx5;
         $data[$x]["COL3"]["WIDTH"]    = $itemwidth5;

         $data[$x]["COL4"]["DATA"]     = fillBlanksResverse(printPrice($headdata["note_total_taxes"]),10);
         $data[$x]["COL4"]["XPOS"]     = $itemposx6;
         $data[$x]["COL4"]["WIDTH"]    = $itemwidth6;
      
         $x++;
         $itemlines++;
      }

      //----------------------------------------------------------------------------------
      $spacerows = $itemsmaxlines - $itemlines;
      for($y = 0; $y < $spacerows; $y++)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $headerposx1;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
         $x++;
      }
      
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      

      $data[$x]["COL4"]["DATA"]     = fillBlanksResverse(printPrice($headdata["note_total_brutto"]),10);
      $data[$x]["COL4"]["XPOS"]     = 126;
      $data[$x]["COL4"]["WIDTH"]    = 10;
      //----------------------------------------------------------------------------------
   }
   */
   
   printRAWTable($data, $form, $mode);
   formFeed();
}

//----------------------------------------------------------------------------------
function printInvoiceSell($CON, $invcid, $mode = "")
{
   global $_LANG;
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name, 
                   t2.cust_notes,  t7.pay_title, t8.trans_name, t2.cust_notes,
                   t9.user_firstname 'seller_firstname', t9.user_lastname 'seller_lastname',
                   t10.user_firstname 'cashing_firstname', t10.user_lastname 'cashing_lastname'
            from invoices_sell t1
            LEFT OUTER JOIN customer t2      ON t1.invc_cust_id         = t2.id
            LEFT OUTER JOIN company_data t3  ON t1.invc_company_id      = t3.id
            LEFT OUTER JOIN company_shops t4 ON t1.invc_shop_id         = t4.id
            LEFT OUTER JOIN payments t7      ON t1.invc_paymentid       = t7.id
            LEFT OUTER JOIN transports t8    ON t1.invc_transportid     = t8.id
            LEFT OUTER JOIN user t9          ON t1.invc_userid_seller   = t9.id
            LEFT OUTER JOIN user t10         ON t1.invc_userid_cashing  = t10.id
            where
            t1.id = {$invcid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            LEFT OUTER JOIN giros   t5 ON t1.cust_giroid    = t5.id
            where
            t1.id = {$headdata["invc_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];
   

   if($customer["name"] != "SANTIAGO")
      $customer["name"] = $customer["nombre"];
      

   $invcparts  = getInvoiceSellParts($CON, $invcid);
   $posdata    = Array();
   for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
   {
      $partposdata = getInvoiceSellPartsItems($CON, $invcid, $invcparts[$x]["id"], "prodnumber");
      for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
         $posdata[] = $partposdata[$y];

      if((int)$invcparts[$x]["part_dlv_id"])
      {
         $sql = " select dlv_docnum
                  from orders_delivery
                  where
                  id = {$invcparts[$x]["part_dlv_id"]}";
         $dlvdoc = $CON->select($sql);
         if($dlvdoc[0]["dlv_docnum"] != "")
            $_DLVNUMSTR .= $dlvdoc[0]["dlv_docnum"].",";
      }
   }

   //Grupe same items
   //----------------------------------------------------------------------------------
   $xtemp = $posdata;
   unset($posdata);
   $posdata = Array();
   for($x = 0; $x < count($xtemp) && $xtemp != false; $x++)
   {
      if($xtemp[$x]["item_type"] == "item" || $xtemp[$x]["item_type"] == "itemlist")
      {
         for($y = $x+1; $y < count($xtemp); $y++)
         {
            if($xtemp[$x]["item_type"] == $xtemp[$y]["item_type"] &&
               $xtemp[$x]["item_id"] == $xtemp[$y]["item_id"] &&
               round($xtemp[$x]["item_sellprice_netto_dsc"]/$xtemp[$x]["item_amount"],0) ==
               round($xtemp[$y]["item_sellprice_netto_dsc"]/$xtemp[$y]["item_amount"],0) &&
               !(int)$xtemp[$y]["_IGNORE"])
            {
               $xtemp[$x]["item_amount"]              += $xtemp[$y]["item_amount"];
               $xtemp[$x]["item_sellprice_netto_dsc"] += $xtemp[$y]["item_sellprice_netto_dsc"];
               $xtemp[$y]["_IGNORE"] = 1;
            }
         }
      }
   }
   for($x = 0; $x < count($xtemp) && $xtemp != false; $x++)
      if(!(int)$xtemp[$x]["_IGNORE"])
         $posdata[] = $xtemp[$x];

   //----------------------------------------------------------------------------------
   usort($posdata, "orderByProductNumberCallback");
   
   $_DLVNUMSTR = substr($_DLVNUMSTR, 0, -1);

   //----------------------------------------------------------------------------------
   $lend       = "\r\n";
   $date_day   = date('d', $headdata["invc_date"]);
   $date_month = date('m', $headdata["invc_date"]) -1;
   $date_month = $_LANG["MODULE"]["CAL"][$date_month];
   $date_year  = date('Y', $headdata["invc_date"]);

   //----------------------------------------------------------------------------------
   $x = 0;
   $lineheight1 = chr(27).chr(37).chr(57).chr(23);
   $lineheight2 = chr(27).chr(37).chr(57).chr(38);
   $lineheight3 = chr(27).chr(37).chr(57).chr(41);
   $lineheight4 = chr(27).chr(37).chr(57).chr(36);

   //----------------------------------------------------------------------------------
   if($mode == "preview")
   {
      $lineheight1 = "";
      $lineheight2 = "";
      $lineheight3 = "";
      $lineheight4 = "";
   }

   if($headdata["invc_status"] == -10)
      $isNotaAdjunta = true;
   else
      $isNotaAdjunta = false;

   //----------------------------------------------------------------------------------
   if($isNotaAdjunta)
   {
      //----------------------------------------------------------------------------------
      $headertopspc  = 3;
      $headerposx1   = 15;
      $headerwidth1  = 60;
      $headerposx2   = 103;
      $headerwidth2  = 34;

      if($mode == "")
      {
         echo $lineheight1;
         
         for($x = $x; $x < $headertopspc; $x++)
         {
            $data[$x]["COL1"]["DATA"]     = " ";
            $data[$x]["COL1"]["XPOS"]     = $headerposx1;
            $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
         }
      }

      $data[$x]["COL1"]["DATA"]     = "NOTA ADJUNTA A LA FACTURA {$headdata["invc_docnumber"]}";
      $data[$x]["COL1"]["XPOS"]     = 40;
      $data[$x]["COL1"]["WIDTH"]    = 80;
      $x++;

      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = $lineheight1."{$date_day} de {$date_month} de {$date_year}";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $x++;
      $data[$x]["COL1"]["DATA"]     = $customer["cust_company"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = 90;
      $x++;
      
      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      
      $data[$x]["COL1"]["DATA"]     = "Estimado cliente:";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = 90;
      $x++;

      $sql = " select pay_days
               from payments
               where
               id = {$headdata["invc_paymentid"]}";
      $pay_days = $CON->select($sql);
      $pay_days = (int)$pay_days[0]["pay_days"];

      $maxdate =  $headdata["invc_receipt_date"] + ($pay_days * 86400);
      $data[$x]["COL1"]["DATA"]     = "Si la presente factura es cancelada antes del   ".date('d/m/Y', $maxdate)."   , se otorgaran los siguientes descuentos:";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = 110;
      $x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
   }
   else
   {

      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t7.pay_title
               from invoices_sell t1
               LEFT OUTER JOIN payments t7 ON t1.invc_paymentid = t7.id
               where
               t1.invc_parentid = {$invcid} and
               t1.invc_status   = -10";
      $hasnote = $CON->select($sql);
      $hasnote = $hasnote[0];

      //----------------------------------------------------------------------------------
      $headertopspc  = 6;
      $headerposx1   = 22;
      $headerwidth1  = 60;
      $headerposx2   = 103;
      $headerwidth2  = 34;

      if($mode == "")
      {
         echo $lineheight1;
         
         for($x = $x; $x < $headertopspc; $x++)
         {
            $data[$x]["COL1"]["DATA"]     = " ";
            $data[$x]["COL1"]["XPOS"]     = $headerposx1;
            $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
         }
      }

      if($headdata["invc_status"] < 2)
      {
         $data[$x]["COL1"]["DATA"]     = $headdata["invc_number"];
         $data[$x]["COL1"]["XPOS"]     = $headerposx2;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth2;
         $x++;
      }
      else
      {
         $data[$x]["COL1"]["DATA"]     = $headdata["invc_docnumber"];
         $data[$x]["COL1"]["XPOS"]     = $headerposx2;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth2;
         $x++;
      }
      $data[$x]["COL1"]["DATA"]     = " ";$data[$x]["COL1"]["XPOS"] = $headerposx1; $data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL1"]["DATA"]     = $lineheight3." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

      //----------------------------------------------------------------------------------
      // HEADER
      //----------------------------------------------------------------------------------
      $data[$x]["COL1"]["DATA"]     = $lineheight1."{$date_day} de {$date_month} de {$date_year}";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = "    ".$customer["cust_rut"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["cust_company"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $headdata["pay_title"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;
      
      $data[$x]["COL1"]["DATA"]     = $customer["cust_street"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = "{$headdata["seller_firstname"]} {$headdata["seller_lastname"]}";
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["giro_name"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      if((int)$hasnote["id"])
      {
         $data[$x]["COL2"]["DATA"]     = "SI";
         $data[$x]["COL2"]["XPOS"]     = $headerposx2;
         $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      }
      else
      {
         $data[$x]["COL2"]["DATA"]     = "NO";
         $data[$x]["COL2"]["XPOS"]     = $headerposx2;
         $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      }
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["nombre"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      if($headdata["invc_estpay_date"] > 0)
      {
         $data[$x]["COL2"]["DATA"]     = date('d/m/Y', $headdata["invc_estpay_date"]);
         $data[$x]["COL2"]["XPOS"]     = $headerposx2;
         $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      }
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["name"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $headdata["trans_name"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;

      if((int)$headdata["invc_cust_delivid"])
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
      
         $data[$x]["COL2"]["DATA"]     = "{$deliveryaddr["delivery_street"]}, {$deliveryaddr["nombre"]}, {$deliveryaddr["name"]}";
         $data[$x]["COL2"]["XPOS"]     = $headerposx2;
         $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      }
      $x++;

      $data[$x]["COL1"]["DATA"]     = $customer["cust_phone"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $headdata["invc_oc_number"];
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;

      $data[$x]["COL1"]["DATA"]     = "    ".$customer["cust_email"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $data[$x]["COL2"]["DATA"]     = $_DLVNUMSTR;
      $data[$x]["COL2"]["XPOS"]     = $headerposx2;
      $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
      $x++;
      
      $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;


      //----------------------------------------------------------------------------------
   }

   //----------------------------------------------------------------------------------
   // ITEMS
   //----------------------------------------------------------------------------------
   $itemstopspc   = 0;
   $itemsmaxlines = 27;
   $itemposx1     = 9;
   $itemwidth1    = 9;
   $itemposx2     = 19;
   $itemwidth2    = 54;
   $itemposx3     = 74;
   $itemwidth3    = 8;
   $itemposx4     = 83;
   $itemwidth4    = 10;
   $itemposx5     = 94;
   $itemwidth5    = 13;
   $itemposx6     = 100;
   $itemwidth6    = 5;
   $itemposx10    = 125;
   $itemwidth10   = 10;

   $spacerows = $x + $itemstopspc;
   for($x = $x; $x < $spacerows; $x++)
   {
      $data[$x]["COL1"]["DATA"]     = " ";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   }

   $data[$x]["COL1"]["DATA"]     = $lineheight4." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
   $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

   if($isNotaAdjunta)
   {
      $data[$x]["COL1"]["DATA"]     = "--------------------------------------------------------------------------------------------------------------------------------";
      $data[$x]["COL1"]["XPOS"]     = $itemposx1;
      $data[$x]["COL1"]["WIDTH"]    = 128;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "Codigo";
      $data[$x]["COL1"]["XPOS"]     = $itemposx1;
      $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;
      $data[$x]["COL2"]["DATA"]     = "Nombre Producto";
      $data[$x]["COL2"]["XPOS"]     = $itemposx2;
      $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
      $data[$x]["COL3"]["DATA"]     = "Unidad";
      $data[$x]["COL3"]["XPOS"]     = $itemposx3;
      $data[$x]["COL3"]["WIDTH"]    = $itemwidth3;
      $data[$x]["COL4"]["DATA"]     = "Cantidad";
      $data[$x]["COL4"]["XPOS"]     = $itemposx4;
      $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;
      $data[$x]["COL5"]["DATA"]     = "Precio";
      $data[$x]["COL5"]["XPOS"]     = $itemposx5;
      $data[$x]["COL5"]["WIDTH"]    = $itemwidth5;
      $data[$x]["COL6"]["DATA"]     = "Descuentos";
      $data[$x]["COL6"]["XPOS"]     = $itemposx6;
      $data[$x]["COL6"]["WIDTH"]    = 16;
      $data[$x]["COL10"]["DATA"]    = "Total";
      $data[$x]["COL10"]["XPOS"]    = $itemposx10;
      $data[$x]["COL10"]["WIDTH"]   = $itemwidth10;
      $x++;
      $data[$x]["COL5"]["DATA"]     = "Unitario";
      $data[$x]["COL5"]["XPOS"]     = $itemposx5;
      $data[$x]["COL5"]["WIDTH"]    = $itemwidth5;
      $data[$x]["COL6"]["DATA"]     = "1 -  2  - 3 ";
      $data[$x]["COL6"]["XPOS"]     = $itemposx6;
      $data[$x]["COL6"]["WIDTH"]    = 16;
      $data[$x]["COL10"]["DATA"]    = "Neto";
      $data[$x]["COL10"]["XPOS"]    = $itemposx10;
      $data[$x]["COL10"]["WIDTH"]   = $itemwidth10;
      $x++;
      $data[$x]["COL1"]["DATA"]     = "--------------------------------------------------------------------------------------------------------------------------------";
      $data[$x]["COL1"]["XPOS"]     = $itemposx1;
      $data[$x]["COL1"]["WIDTH"]    = 128;
      $x++;
   }
   
   $itemlines = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($posdata) && $posdata != false; $y++)
   {
      $item_price = $posdata[$y]["item_sellprice_netto_dsc"] / $posdata[$y]["item_amount"];

      $data[$x]["COL1"]["DATA"]     = reformatProdNumber($posdata[$y]["item_number_prod"]);
      $data[$x]["COL1"]["XPOS"]     = $itemposx1;
      $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;

      $data[$x]["COL2"]["DATA"]     = $posdata[$y]["item_title"];
      $data[$x]["COL2"]["XPOS"]     = $itemposx2;
      $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;

      if($posdata[$y]["item_code"] != "")
         $data[$x]["COL2"]["DATA"] .= " - {$posdata[$y]["item_code"]}";

      $data[$x]["COL3"]["DATA"]     = $posdata[$y]["unit_name"];
      $data[$x]["COL3"]["XPOS"]     = $itemposx3;
      $data[$x]["COL3"]["WIDTH"]    = $itemwidth3;

      $data[$x]["COL4"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_amount"],2),6);
      $data[$x]["COL4"]["XPOS"]     = $itemposx4;
      $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;

      $data[$x]["COL5"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_sellprice_netto"]),10);
      $data[$x]["COL5"]["XPOS"]     = $itemposx5;
      $data[$x]["COL5"]["WIDTH"]    = $itemwidth5;

      $dscarr = explode("-",$posdata[$y]["_dsc_str"]);

      $dsccol = 6;
      for($zz = 0; $zz < 4; $zz++)
      {
         $data[$x]["COL{$dsccol}"]["DATA"]     = $dscarr[$zz];
         $data[$x]["COL{$dsccol}"]["XPOS"]     = $itemposx6 + ($zz * 4);
         $data[$x]["COL{$dsccol}"]["WIDTH"]    = $itemwidth6;
         $dsccol++;
      }

      $data[$x]["COL10"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_sellprice_netto_dsc"]),10);
      $data[$x]["COL10"]["XPOS"]     = $itemposx10;
      $data[$x]["COL10"]["WIDTH"]    = $itemwidth10;

      if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
      {
         $titlearr = explode("\n", $posdata[$y]["item_title"]);
         $data[$x]["COL2"]["DATA"] = trim($titlearr[0]);
         for($zz = 1; $zz < count($titlearr); $zz++)
         {
            $x++;
            $itemlines++;
            $data[$x]["COL2"]["DATA"]     = trim($titlearr[$zz]);
            $data[$x]["COL2"]["XPOS"]     = $itemposx2;
            $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
         }
      }

      $x++;
      $itemlines++;
   }

   //----------------------------------------------------------------------------------
   $spacerows = $itemsmaxlines - $itemlines;
   for($y = 0; $y < $spacerows; $y++)
   {
      $data[$x]["COL1"]["DATA"]     = " ";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      $x++;
   }

   //----------------------------------------------------------------------------------
   //$data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

   $lastxpos   = $x;
   $moneytext  = "Son: ".Num2Text($headdata["invc_total_brutto"])." PESOS";
   $moneytext  = str_replace("  ", " ", $moneytext);
   $marr       = Array();

   if(strpos($moneytext, " MIL ") !== false && strlen($moneytext) > 90)
   {
      $marr = explode(" MIL ", $moneytext);
      $marr[0] .= " MIL";
   }
   else
      $marr[0] = $moneytext;

   foreach($marr AS $moneystr)
   {
      $data[$x]["COL1"]["DATA"]     = $moneystr;
      $data[$x]["COL1"]["XPOS"]     = 9;
      $data[$x]["COL1"]["WIDTH"]    = 90;
      $x++;
   }

   //----------------------------------------------------------------------------------
   if($isNotaAdjunta)
   {
      $x = $lastxpos;
      $data[$x]["COL2"]["DATA"]     = "Subtotal";
      $data[$x]["COL2"]["XPOS"]     = 100;
      $data[$x]["COL2"]["WIDTH"]    = 16;
      $data[$x]["COL3"]["DATA"]     = printPrice($headdata["invc_total_netto"] - $headdata["invc_weightprice_netto"]);
      $data[$x]["COL3"]["XPOS"]     = 116;
      $data[$x]["COL3"]["WIDTH"]    = 16;
      $x++;
      $data[$x]["COL2"]["DATA"]     = "Conduccion";
      $data[$x]["COL2"]["XPOS"]     = 100;
      $data[$x]["COL2"]["WIDTH"]    = 16;
      $data[$x]["COL3"]["DATA"]     = printPrice($headdata["invc_weightprice_netto"]);
      $data[$x]["COL3"]["XPOS"]     = 116;
      $data[$x]["COL3"]["WIDTH"]    = 16;
      $x++;
      $data[$x]["COL2"]["DATA"]     = "Neto";
      $data[$x]["COL2"]["XPOS"]     = 100;
      $data[$x]["COL2"]["WIDTH"]    = 16;
      $data[$x]["COL3"]["DATA"]     = printPrice($headdata["invc_total_netto"]);
      $data[$x]["COL3"]["XPOS"]     = 116;
      $data[$x]["COL3"]["WIDTH"]    = 16;
      $x++;
      $data[$x]["COL2"]["DATA"]     = "I.V.A.";
      $data[$x]["COL2"]["XPOS"]     = 100;
      $data[$x]["COL2"]["WIDTH"]    = 16;
      $data[$x]["COL3"]["DATA"]     = printPrice($headdata["invc_total_taxes"]);
      $data[$x]["COL3"]["XPOS"]     = 116;
      $data[$x]["COL3"]["WIDTH"]    = 16;
      $x++;
      $data[$x]["COL2"]["DATA"]     = "Total";
      $data[$x]["COL2"]["XPOS"]     = 100;
      $data[$x]["COL2"]["WIDTH"]    = 16;
      $data[$x]["COL3"]["DATA"]     = printPrice($headdata["invc_total_brutto"]);
      $data[$x]["COL3"]["XPOS"]     = 116;
      $data[$x]["COL3"]["WIDTH"]    = 16;
      $x++;
   }
   else
   {
   
      if(trim($headdata["invc_desc"]) != "")
      {
         

         if((int)$headdata["invc_transportid"])
         {
            $sql = " select t1.*, t2.name, t3.nombre
                     from transports t1
                     LEFT OUTER JOIN regions t2 ON t1.trans_regionid = t2.id
                     LEFT OUTER JOIN comunas t3 ON t1.trans_comunaid = t3.id
                     where
                     t1.id = {$headdata["invc_transportid"]}";
            $transp = $CON->select($sql);
            $transp = $transp[0];

            $headdata["invc_desc"] = trim($headdata["invc_desc"]);
            if($headdata["invc_desc"] != "")
               $headdata["invc_desc"] .= ",";
            $headdata["invc_desc"] .= $transp["trans_street"];
            if($transp["name"] != "")
               $headdata["invc_desc"] .= ",".$transp["name"];
            if($transp["nombre"] != "")
               $headdata["invc_desc"] .= ",".$transp["nombre"];
         }
         $data[$x]["COL1"]["DATA"]     = $headdata["invc_desc"];
         $data[$x]["COL1"]["XPOS"]     = 9;
         $data[$x]["COL1"]["WIDTH"]    = 90;
         $x++;
      }

      $x = $lastxpos;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;
      $data[$x]["COL2"]["DATA"]     = $lineheight1." "; $data[$x]["COL2"]["XPOS"] = 1;$data[$x]["COL2"]["WIDTH"] = 1;$x++;

      $data[$x]["COL2"]["DATA"]     = printPrice($headdata["invc_total_netto"] - $headdata["invc_weightprice_netto"]);
      $data[$x]["COL2"]["XPOS"]     = 57;
      $data[$x]["COL2"]["WIDTH"]    = 13;
      $data[$x]["COL3"]["DATA"]     = printPrice($headdata["invc_weightprice_netto"]);
      $data[$x]["COL3"]["XPOS"]     = 73;
      $data[$x]["COL3"]["WIDTH"]    = 13;
      $data[$x]["COL4"]["DATA"]     = printPrice($headdata["invc_total_netto"]);
      $data[$x]["COL4"]["XPOS"]     = 86;
      $data[$x]["COL4"]["WIDTH"]    = 13;
      $data[$x]["COL5"]["DATA"]     = printPrice($headdata["invc_total_taxes"]);
      $data[$x]["COL5"]["XPOS"]     = 108;
      $data[$x]["COL5"]["WIDTH"]    = 13;
      $data[$x]["COL6"]["DATA"]     = printPrice($headdata["invc_total_brutto"]);
      $data[$x]["COL6"]["XPOS"]     = 127;
      $data[$x]["COL6"]["WIDTH"]    = 10;
   }

   printRAWTable($data, $form, $mode);
   
   formFeed();
}

//----------------------------------------------------------------------------------
function printOrdersDelivery($CON, $dlvid, $mode = "", $printmode = 0, $useSubdetailid = 0, $subdetailrows = NULL)
{
   global $_LANG;
   $printmode = (int)$printmode;

   

   $sql = " select t1.*, t3.req_number, t3.req_order_shipped, t4.company_short, t5.shop_name, t8.trans_name, t9.pay_title
            from orders_delivery t1
            LEFT OUTER JOIN orders t3           ON t1.dlv_order_id      = t3.id
            LEFT OUTER JOIN company_data t4     ON t1.dlv_company_id    = t4.id
            LEFT OUTER JOIN company_shops t5    ON t1.dlv_shop_id       = t5.id
            LEFT OUTER JOIN transports t8       ON t1.dlv_transportid   = t8.id
            LEFT OUTER JOIN payments t9         ON t1.dlv_paymentid     = t9.id
            where
            t1.id = {$_REQUEST["id"]}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $headerdate = time();
   if($printmode && is_array($subdetailrows))
   {
      foreach($subdetailrows AS $numpos)
      {
         if($numpos["id"] == $useSubdetailid)
         {
            $useposdata = $numpos;
            $hassubdata = true;

            $headdata["dlv_delivery_date"]   = $useposdata["dlv_modedate"];
            $headerdate                      = $useposdata["dlv_modedate"];
            $headdata["dlv_modetext"]        = $useposdata["dlv_modetext"];
         }
      }
   }

   //----------------------------------------------------------------------------------
   if($headdata["dlv_mode"] <= 2)
   {
      //----------------------------------------------------------------------------------
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
               from customer t1
               LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
               LEFT OUTER JOIN giros   t5 ON t1.cust_giroid    = t5.id
               where
               t1.id = {$headdata["dlv_cust_id"]}";
      $customer = $CON->select($sql);
      $customer = $customer[0];

      //----------------------------------------------------------------------------------
      if((int)$headdata["dlv_cust_delivid"])
      {
         $sql = " select t1.delivery_name 'cust_company', t1.delivery_street 'cust_street', t5.cust_rut, t2.country_name, t3.name, t4.nombre, t6.giro_name
                  from customer_deliveryaddr t1
                  LEFT OUTER JOIN country t2    ON t1.delivery_countryid = t2.id
                  LEFT OUTER JOIN regions t3    ON t1.delivery_regionid  = t3.id
                  LEFT OUTER JOIN comunas t4    ON t1.delivery_comunaid  = t4.id
                  LEFT OUTER JOIN customer t5   ON t1.cust_id            = t5.id
                  LEFT OUTER JOIN giros   t6    ON t5.cust_giroid        = t6.id
                  where
                  t1.id = {$headdata["dlv_cust_delivid"]}
                  order by t1.id asc";
         $customer = $CON->select($sql);
         $customer = $customer[0];
      }
   }
   else
   {
      $sql = " select t1.*, t2.country_name, t3.name, t4.nombre, t5.giro_name
               from supplier t1
               LEFT OUTER JOIN country t2 ON t1.supp_countryid = t2.id
               LEFT OUTER JOIN regions t3 ON t1.supp_regionid  = t3.id
               LEFT OUTER JOIN comunas t4 ON t1.supp_comunaid  = t4.id
               LEFT OUTER JOIN giros   t5 ON t1.supp_giroid    = t5.id
               where
               t1.id = {$headdata["dlv_supplier_id"]}";
      $supplier = $CON->select($sql);
      $supplier = $supplier[0];
      
      $customer["cust_company"]  = $supplier["supp_company"];
      $customer["cust_rut"]      = $supplier["supp_rut"];
      $customer["cust_street"]   = $supplier["supp_street"];
      $customer["cust_phone"]    = $supplier["supp_phone"];
      $customer["name"]          = $supplier["name"];
      $customer["cust_fax"]      = $supplier["supp_fax"];
      $customer["nombre"]        = $supplier["nombre"];
      $customer["cust_email"]    = $supplier["supp_email"];
      $customer["country_name"]  = $supplier["country_name"];
      $customer["giro_name"]     = $supplier["giro_name"];
   }

   if($customer["name"] != "SANTIAGO")
      $customer["name"] = $customer["nombre"];

   //----------------------------------------------------------------------------------
   $posdata = getOrderDeliveryPos($CON, $dlvid, "prodnumber");
   usort($posdata, "orderByProductNumberCallback");
   
   //----------------------------------------------------------------------------------
   $lend       = "\r\n";

   if($headdata["dlv_invoice_generated"] > 0)
   {
      $date_day   = date('d', $headerdate);
      $date_month = date('m', $headerdate) -1;
      $date_month = $_LANG["MODULE"]["CAL"][$date_month];
      $date_year  = date('Y', $headerdate);
   }
   else
   {
      $date_day   = date('d', $headdata["dlv_delivery_date"]);
      $date_month = date('m', $headdata["dlv_delivery_date"]) -1;
      $date_month = $_LANG["MODULE"]["CAL"][$date_month];
      $date_year  = date('Y', $headdata["dlv_delivery_date"]);
   }

   //----------------------------------------------------------------------------------
   $x = 0;
   $lineheight1 = chr(27).chr(37).chr(57).chr(23);
   $lineheight2 = chr(27).chr(37).chr(57).chr(38);
   $lineheight3 = chr(27).chr(37).chr(57).chr(41);
   $lineheight4 = chr(27).chr(37).chr(57).chr(36);

   //----------------------------------------------------------------------------------
   if($mode == "preview")
   {
      $lineheight1 = "";
      $lineheight2 = "";
      $lineheight3 = "";
      $lineheight4 = "";
   }

   //----------------------------------------------------------------------------------
   $headertopspc  = 5;
   $headerposx1   = 22;
   $headerwidth1  = 60;
   $headerposx2   = 104;
   $headerwidth2  = 33;

   if($mode == "")
   {
      echo $lineheight1;
      
      for($x = $x; $x < $headertopspc; $x++)
      {
         $data[$x]["COL1"]["DATA"]     = " ";
         $data[$x]["COL1"]["XPOS"]     = $headerposx1;
         $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
      }
   }

   if($headdata["dlv_status"] < 2)
   {
      $data[$x]["COL1"]["DATA"]     = $headdata["dlv_num"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx2;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth2;
      $x++;
   }
   else
   {
      $data[$x]["COL1"]["DATA"]     = $headdata["dlv_docnum"];
      $data[$x]["COL1"]["XPOS"]     = $headerposx2;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth2;
      $x++;
   }

   $data[$x]["COL1"]["DATA"]     = " ";$data[$x]["COL1"]["XPOS"] = $headerposx1; $data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
   $data[$x]["COL1"]["DATA"]     = " ";$data[$x]["COL1"]["XPOS"] = $headerposx1; $data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
   $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

   //----------------------------------------------------------------------------------
   // HEADER
   //----------------------------------------------------------------------------------
   $data[$x]["COL1"]["DATA"]     = $lineheight1."{$date_day} de {$date_month} de {$date_year}";
   $data[$x]["COL1"]["XPOS"]     = $headerposx1;
   $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   $data[$x]["COL2"]["DATA"]     = "    ".$customer["cust_rut"];
   $data[$x]["COL2"]["XPOS"]     = $headerposx2;
   $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
   $x++;

   $data[$x]["COL1"]["DATA"]     = $customer["cust_company"];
   $data[$x]["COL1"]["XPOS"]     = $headerposx1;
   $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   $data[$x]["COL2"]["DATA"]     = $headdata["pay_title"];
   $data[$x]["COL2"]["XPOS"]     = $headerposx2;
   $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
   $x++;

   $data[$x]["COL1"]["DATA"]     = $customer["cust_street"];
   $data[$x]["COL1"]["XPOS"]     = $headerposx1;
   $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   $data[$x]["COL2"]["DATA"]     = " ";
   $data[$x]["COL2"]["XPOS"]     = $headerposx2;
   $data[$x]["COL2"]["WIDTH"]    = $headerwidth2;
   $x++;

   $data[$x]["COL1"]["DATA"]     = $customer["giro_name"];
   $data[$x]["COL1"]["XPOS"]     = $headerposx1;
   $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   $x++;

   $data[$x]["COL1"]["DATA"]     = $customer["nombre"];
   $data[$x]["COL1"]["XPOS"]     = $headerposx1;
   $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   $x++;

   $data[$x]["COL1"]["DATA"]     = $customer["name"];
   $data[$x]["COL1"]["XPOS"]     = $headerposx1;
   $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   $x++;

   //----------------------------------------------------------------------------------
   // ITEMS
   //----------------------------------------------------------------------------------
   $itemstopspc   = 3;
   $itemsmaxlines = 27;
   $itemposx1     = 9;
   $itemwidth1    = 9;
   $itemposx2     = 20;
   $itemwidth2    = 45;
   $itemposx3     = 66;
   $itemwidth3    = 7;
   $itemposx4     = 78;
   $itemwidth4    = 10;
   $itemposx5     = 89;
   $itemwidth5    = 13;
   $itemposx6     = 96;
   $itemwidth6    = 5;
   
   $spacerows = $x + $itemstopspc;
   for($x = $x; $x < $spacerows; $x++)
   {
      $data[$x]["COL1"]["DATA"]     = " ";
      $data[$x]["COL1"]["XPOS"]     = $headerposx1;
      $data[$x]["COL1"]["WIDTH"]    = $headerwidth1;
   }

   $data[$x]["COL1"]["DATA"]     = $lineheight4." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
   $data[$x]["COL1"]["DATA"]     = $lineheight1." "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;

   if($printmode)
   {
      if($headdata["dlv_modetext"] != "")
      {
         $marr = explode("\n", $headdata["dlv_modetext"]);
         foreach($marr AS $mrow)
         {
            $data[$x]["COL2"]["DATA"]     = trim($mrow);
            $data[$x]["COL2"]["XPOS"]     = $itemposx2;
            $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
            $x++;
         }
      }
      else
      {
         if($headdata["dlv_invoice_generated"] > 0)
         {
            $sql = " select invc_docnumber
                     from invoices_sell
                     where
                     id = {$headdata["dlv_invoice_generated"]} and
                     invc_status > 0";
            $refinvcdoc = $CON->select($sql);
            $refinvcdoc = $refinvcdoc[0]["invc_docnumber"];
         }
         else
         {
            $sql = " select t1.invc_docnumber
                     from invoices_sell t1
                     INNER JOIN invoices_sell_parts t2 ON t1.id = t2.part_invc_id
                     where
                     t1.invc_status > 0 and
                     t2.part_dlv_id = {$headdata["id"]}";
            $refinvcdoc = $CON->select($sql);
            $refinvcdoc = $refinvcdoc[0]["invc_docnumber"];
         }
         $data[$x]["COL2"]["DATA"]     = "GUIA SIN VALOR COMERCIAL";
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
         $x++;
         $data[$x]["COL2"]["DATA"]     = "EMITIDA PARA RESPALDO FACTURA {$refinvcdoc}";
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
         $x++;
      }
   }
   
   for($y = 0; $y < count($posdata) && $posdata != false; $y++)
   {
      if($posdata[$y]["item_amount_shipped"] > 0)
      {
         $item_price = $posdata[$y]["item_sellprice_netto_dsc"] / $posdata[$y]["item_amount_shipped"];

         $data[$x]["COL1"]["DATA"]     = reformatProdNumber($posdata[$y]["item_number_prod"]);
         $data[$x]["COL1"]["XPOS"]     = $itemposx1;
         $data[$x]["COL1"]["WIDTH"]    = $itemwidth1;

         $data[$x]["COL2"]["DATA"]     = $posdata[$y]["item_title"];
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
         if($posdata[$y]["item_code"] != "")
            $data[$x]["COL2"]["DATA"] .= " - {$posdata[$y]["item_code"]}";

         $data[$x]["COL3"]["DATA"]     = $posdata[$y]["unit_name"];
         $data[$x]["COL3"]["XPOS"]     = $itemposx3;
         $data[$x]["COL3"]["WIDTH"]    = $itemwidth3;

         $data[$x]["COL4"]["DATA"]     = fillBlanksResverse(printPrice($posdata[$y]["item_amount_shipped"],2),6);
         $data[$x]["COL4"]["XPOS"]     = $itemposx4;
         $data[$x]["COL4"]["WIDTH"]    = $itemwidth4;

         //if($headdata["dlv_mode"] != 2 && $headdata["dlv_mode"] != 4)
         $data[$x]["COL5"]["DATA"]  = fillBlanksResverse(printPrice($posdata[$y]["item_sellprice_netto"]),10);
         //else
         //   $data[$x]["COL5"]["DATA"]  = " ";
         $data[$x]["COL5"]["XPOS"]     = $itemposx5;
         $data[$x]["COL5"]["WIDTH"]    = $itemwidth5;

         $dscarr = explode("-",$posdata[$y]["_dsc_str"]);

         $dsccol = 6;
         for($zz = 0; $zz < 4; $zz++)
         {
            $data[$x]["COL{$dsccol}"]["DATA"]     = $dscarr[$zz];
            $data[$x]["COL{$dsccol}"]["XPOS"]     = $itemposx6 + ($zz * 4);
            $data[$x]["COL{$dsccol}"]["WIDTH"]    = $itemwidth6;
            $dsccol++;
         }

         if($posdata[$y]["item_type"] == "manual" && strpos($posdata[$y]["item_title"], "\n") !== false)
         {
            $titlearr = explode("\n", $posdata[$y]["item_title"]);
            $data[$x]["COL2"]["DATA"] = trim($titlearr[0]);
            for($zz = 1; $zz < count($titlearr); $zz++)
            {
               $x++;
               $itemlines++;
               $data[$x]["COL2"]["DATA"]     = trim($titlearr[$zz]);
               $data[$x]["COL2"]["XPOS"]     = $itemposx2;
               $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
            }
         }
      }
      $x++;
   }

   if((int)$headdata["dlv_transportid"])
   {
      $sql = " select t1.*, t2.name, t3.nombre
               from transports t1
               LEFT OUTER JOIN regions t2 ON t1.trans_regionid = t2.id 
               LEFT OUTER JOIN comunas t3 ON t1.trans_comunaid = t3.id
               where
               t1.id = {$headdata["dlv_transportid"]}";
      $transp = $CON->select($sql);
      $transp = $transp[0];

      $headdata["dlv_annotation"] = trim($headdata["dlv_annotation"]);
      $headdata["dlv_annotation"] .= "\n".$transp["trans_name"];
      if($transp["trans_street"] != "")
         $headdata["dlv_annotation"] .= ",".$transp["trans_street"];
      if($transp["name"] != "")
         $headdata["dlv_annotation"] .= ",".$transp["name"];
      if($transp["nombre"] != "")
         $headdata["dlv_annotation"] .= ",".$transp["nombre"];
   }

   if(trim($headdata["dlv_annotation"]) != "")
   {
      
      $data[$x]["COL1"]["DATA"]     = " "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
      $data[$x]["COL2"]["DATA"]     = "OBSERVACIONES:";
      $data[$x]["COL2"]["XPOS"]     = $itemposx2;
      $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;

      $titlearr = explode("\n", $headdata["dlv_annotation"]);
      foreach($titlearr AS $titlerow)
      {
         $data[$x]["COL1"]["DATA"]     = " "; $data[$x]["COL1"]["XPOS"] = $headerposx1;$data[$x]["COL1"]["WIDTH"] = $headerwidth1;$x++;
         $data[$x]["COL2"]["DATA"]     = $titlerow;
         $data[$x]["COL2"]["XPOS"]     = $itemposx2;
         $data[$x]["COL2"]["WIDTH"]    = $itemwidth2;
      }
   }
   
   printRAWTable($data, $form, $mode);
   formFeed();
   

}

//----------------------------------------------------------------------------------
function latrepl($str, $size)
{
   $str = str_replace("ñ", "n", $str);
   $str = str_replace("Ñ", "N", $str);
   $str = str_replace("á", "n", $str);
   $str = str_replace("Á", "n", $str);
   $str = str_replace("é", "e", $str);
   $str = str_replace("É", "E", $str);
   $str = str_replace("í", "i", $str);
   $str = str_replace("Í", "I", $str);
   $str = str_replace("ó", "o", $str);
   $str = str_replace("Ó", "O", $str);
   $str = str_replace("ú", "u", $str);
   $str = str_replace("Ú", "U", $str);
   $str = str_replace("º", "o", $str);
   
   return $str;
}

//----------------------------------------------------------------------------------
function printRAWTable($data, $form, $mode = "")
{
   $poscounter = 0;
   $lend       = "\r\n";
   foreach($data AS $row)
   {
      $_line = "";

      //----------------------------------------------------------------------------------
      foreach(array_keys($row) AS $col)
      {
         $_data   = $row[$col]["DATA"];
         $_xpos   = $row[$col]["XPOS"];
         $_width  = $row[$col]["WIDTH"];

         if(strlen($line) < $_xpos)
            $_line = fillBlanks($_line, $_xpos);

         $_data   = substr($_data, 0, $_width);
         $_data   = fillBlanks($_data, $_width);

         $_line   .= $_data;
      }
      
      //----------------------------------------------------------------------------------
      if($mode == "")
      {
         if($form[$poscounter] == "LARGE")
            largeFont();
         else
            smallFont();
      }

      //----------------------------------------------------------------------------------
      echo latrepl($_line);
      echo $lend;
      $poscounter++;
   }
}

//----------------------------------------------------------------------------------
function smallFont()
{
   echo chr(18);
   echo chr(27).chr(15);
   //echo chr(27).chr(73).chr(2);
   //echo chr(27).chr(69);  // Emphasized
}

//----------------------------------------------------------------------------------
function largeFont()
{
   echo chr(18);
   echo chr(27).chr(58);
   //echo chr(27).chr(73).chr(2);
   //echo chr(27).chr(69);  // Emphasized
}

//----------------------------------------------------------------------------------
function formFeed()
{
   echo chr(12);
}

//----------------------------------------------------------------------------------
function fillBlanks($str, $len)
{
   $retstr  = $str;
   $strlen  = strlen($str);

   for($x = $strlen; $x < $len; $x++)
      $retstr .= " ";
   return $retstr;
}

//----------------------------------------------------------------------------------
function fillBlanksResverse($str, $len)
{
   $retstr  = $str;
   $strlen  = strlen($str);

   for($x = $strlen; $x < $len; $x++)
      $retstr = " ".$retstr;
   return $retstr;
}
?>