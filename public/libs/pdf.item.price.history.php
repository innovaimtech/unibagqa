<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function doc_createItemPriceHistory($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "hist{$_SESSION[$_sesmodulename]["sql_type"]}";
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
   if($_SESSION[$_sesmodulename]["sql_item_type"] == "item")
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $item = $CON->select($sql);
      $item = $item[0];
   }
   else
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

   if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 8,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "FECHA"          => Array("width" => "250", "justification" => "left"),
                    "USUARIO"        => Array("width" => "230", "justification" => "left"),
                    "PRECIO/BASICO\nNETO"  => Array("width" => "80", "justification" => "right"),
                    "IVA"        => Array("width" => "80", "justification" => "right"),
                    "PRECIO/BASICO\nBRUTO" => Array("width" => "80", "justification" => "right")));
   }
   else
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 8,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "PROVEEDOR"      => Array("width" => "300", "justification" => "left"),
                    "FECHA"          => Array("width" => "80", "justification" => "left"),
                    "USUARIO"        => Array("width" => "100", "justification" => "left"),
                    "PRECIO/BASICO\nNETO"  => Array("width" => "80", "justification" => "right"),
                    "IVA"            => Array("width" => "80", "justification" => "right"),
                    "PRECIO/BASICO\nBRUTO" => Array("width" => "80", "justification" => "right")));
   }

   $counter = 0;

   foreach($items AS $sqlrow)
   {
      if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
      {
         $data[$counter]["FECHA"]          = $sqlrow["prc_crtdat"];
         $data[$counter]["USUARIO"]        = $sqlrow["user_lastname"];
         $data[$counter]["PRECIO/BASICO\nNETO"]  = $sqlrow["prc_sellprice_netto"];
         $data[$counter]["IVA"]            = $sqlrow["prc_sellprice_taxes"];
         $data[$counter]["PRECIO/BASICO\nBRUTO"] = $sqlrow["prc_sellprice_brutto"];
      }
      else
      {
         $data[$counter]["PROVEEDOR"]      = $sqlrow["supp_company"];
         $data[$counter]["FECHA"]          = $sqlrow["prc_crtdat"];
         $data[$counter]["USUARIO"]        = $sqlrow["user_lastname"];
         $data[$counter]["PRECIO/BASICO\nNETO"]  = $sqlrow["prc_costprice_netto"];
         $data[$counter]["IVA"]            = $sqlrow["prc_costprice_taxes_perc"];
         $data[$counter]["PRECIO/BASICO\nBRUTO"] = $sqlrow["prc_costprice_brutto"];
      }

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
   if($type == "selling")
      $HEADER["DATA"][$pcounter]["x2"] = "HISTORIA DE PRECIOS DE VENTA";
   else
      $HEADER["DATA"][$pcounter]["x2"] = "HISTORIA DE PRECIOS DE COMPRA";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>ARTÍCULO</b>";
   $HEADER["DATA"][$pcounter]["x2"] = "{$item["item_number_prod"]} {$item["item_title"]} ({$unitdesc})";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PERIODO</b>";
   if($_SESSION[$_sesmodulename]["sql_datefrom"] != "" && $_SESSION[$_sesmodulename]["sql_dateto"]["sql_dateto"] != "")
      $HEADER["DATA"][$pcounter]["x4"] = "{$_SESSION[$_sesmodulename]["sql_datefrom"]} - {$_SESSION[$_sesmodulename]["sql_dateto"]}";
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   
   $pcounter++;

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