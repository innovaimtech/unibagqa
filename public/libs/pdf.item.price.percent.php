<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function doc_createItemPricePercent($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "perc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   $items = $_SESSION["STATS"][$_sesmodulename]["DATA"];

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"] && $_SESSION[$_sesmodulename]["sql_item_type"] == "item")
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $item = $CON->select($sql);
      $item = $item[0];
   }
   elseif((int)$_SESSION[$_sesmodulename]["sql_item_id"] && $_SESSION[$_sesmodulename]["sql_item_type"] == "itemlist")
   {
      $sql = " select *
               from itemlist
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $item = $CON->select($sql);
      $item = $item[0];
   }
   //----------------------------------------------------------------------------------
   $unitdesc = getItemUnitDesc($CON, $_SESSION[$_sesmodulename]["sql_item_id"], $_SESSION[$_sesmodulename]["sql_item_type"]);

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"           => Array("width" => "45", "justification" => "left"),
                 "ARTÍCULO"         => Array("width" => "190", "justification" => "left"),
                 "UNIDAD"           => Array("width" => "50", "justification" => "left"),
                 "COD/PROV."        => Array("width" => "55", "justification" => "left"),
                 "P/VENTA\nBASICO"  => Array("width" => "40", "justification" => "right"),
                 "P/VENTA\nFINAL"   => Array("width" => "40", "justification" => "right"),
                 "COSTO\nBASICO"    => Array("width" => "40", "justification" => "right"),
                 "COSTO\nFINAL"     => Array("width" => "40", "justification" => "right"),
                 "MARGEN/C"         => Array("width" => "50", "justification" => "right"),
                 "MARGEN/V"         => Array("width" => "50", "justification" => "right"),
                 "PROVEEDOR"        => Array("width" => "110", "justification" => "right")));

   $counter = 0;
   foreach($items AS $sqlrow)
   {
      if($sqlrow["item_type"] == "item_typeI")
         $sqlrow["item_type"] = "item";
      else
         $sqlrow["item_type"] = "itemlist";


      $data[$counter]["NUMERO"]           = $sqlrow["item_number_prod"];
      $data[$counter]["ARTÍCULO"]         = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]           = $sqlrow["unitdesc"];
      $data[$counter]["COD/PROV."]        = $sqlrow["item_code"];
      $data[$counter]["P/VENTA\nBASICO"]  = $sqlrow["itemshop_sellprice_netto"];
      $data[$counter]["P/VENTA\nFINAL"]   = $sqlrow["selval"];
      $data[$counter]["COSTO\nBASICO"]    = $sqlrow["item_costprice_netto"];
      $data[$counter]["COSTO\nFINAL"]     = $sqlrow["buyval"];
      $data[$counter]["MARGEN/C"]         = $sqlrow["percent"];
      $data[$counter]["MARGEN/V"]         = $sqlrow["percent_sell"];
      $data[$counter]["PROVEEDOR"]        = $sqlrow["supp_company"];

      $counter++;
   }
   
   $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8, 
                   "showLines" => 0, "rowGap" => 1, "colGap" => 0,
                   "cols" => Array (
                   "x1" => Array("width" => "60", "justification" => "left"),
                   "x2" => Array("width" => "300", "justification" => "left"),
                   "x3" => Array("width" => "60", "justification" => "left"),
                   "x4" => Array("width" => "300", "justification" => "left")));

   //----------------------------------------------------------------------------------
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME</b>";
   $HEADER["DATA"][$pcounter]["x2"] = "COSTOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>ARTÍCULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
      $HEADER["DATA"][$pcounter]["x2"] = "{$item["item_number_prod"]} {$item["item_title"]} ({$unitdesc})";
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select id, cat_title
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $pcat = $CON->select($sql);
      $pcat = $pcat[0];
      $HEADER["DATA"][$pcounter]["x4"] = sprintf("%03s", $pcat["id"])." - ".$pcat["cat_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select supp_company
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $supplier = $CON->select($sql);
      $supplier = $supplier[0];
      $HEADER["DATA"][$pcounter]["x2"] = $supplier["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";
      
   $HEADER["DATA"][$pcounter]["x3"] = " ";
   $HEADER["DATA"][$pcounter]["x4"] = " ";
   $pcounter++;

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_invcsellnum"] != "")
   {
      $sql = " select t1.*, t2.cust_name
               from invoices_sell t1
               LEFT OUTER JOIN customer t2 ON t1.invc_cust_id = t2.id
               where
               invc_docnumber = '{$_SESSION[$_sesmodulename]["sql_invcsellnum"]}' and
               invc_status > 1
               order by id desc
               LIMIT 0,1";
      $invcdata = $CON->select($sql);
      $invcdata = $invcdata[0];

      $HEADER["DATA"][$pcounter]["x1"] = "<b>FECHA FACT.</b>";
      $HEADER["DATA"][$pcounter]["x2"] = date('d.m.Y', $invcdata["invc_date"]);
      $HEADER["DATA"][$pcounter]["x3"] = "<b>CLIENTE</b>";
      $HEADER["DATA"][$pcounter]["x4"] = $invcdata["cust_name"];
      $HEADER["DATA"][($pcounter -1)]["x3"] = "<b>FACTURA</b>";
      $HEADER["DATA"][($pcounter -1)]["x4"] = $invcdata["invc_docnumber"];
   }
   else
   {

      //----------------------------------------------------------------------------------
      $HEADER["DATA"][$pcounter]["x1"] = "<b>EMPRESA</b>";
      if((int)$_SESSION[$_sesmodulename]["sql_company"])
      {
         $sql = " select *
                  from company_data
                  where
                  id = {$_SESSION[$_sesmodulename]["sql_company"]}";
         $company = $CON->select($sql);
         $company = $company[0];
         $HEADER["DATA"][$pcounter]["x2"] = $company["company_short"];
      }
      else
         $HEADER["DATA"][$pcounter]["x2"] = "TODO";

      //----------------------------------------------------------------------------------
      $HEADER["DATA"][$pcounter]["x3"] = "<b>SUCURSAL</b>";
      if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      {
         $sql = " select *
                  from company_shops
                  where
                  id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
         $shop = $CON->select($sql);
         $shop = $shop[0];
         $HEADER["DATA"][$pcounter]["x4"] = $shop["shop_name"];
      }
      else
         $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   }
   
   $pdf = printPDFFooter($CON, $pdf, NULL, "list", $HEADER);

   //----------------------------------------------------------------------------------
   $fp = fopen($pdffile, "w");
   if($fp)
   {
      $pdfdata = $pdf->output();
      fwrite($fp, $pdfdata);
      fclose($fp);

      return $filename;
   }

   return false;
}
?>