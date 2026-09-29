<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function doc_createItemBuyPrice($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "pricelist";
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
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"                 => Array("width" => "40", "justification" => "left"),
                 "ARTÍCULO"               => Array("width" => "170", "justification" => "left"),
                 "CODIGO/PROV"            => Array("width" => "70", "justification" => "left"),
                 "UNIDAD"                 => Array("width" => "40", "justification" => "left"),
                 "PROVEEDOR"              => Array("width" => "120", "justification" => "left"),
                 "PRECIO\nBASICO"         => Array("width" => "40", "justification" => "right"),
                 "DESC\n%"                => Array("width" => "40", "justification" => "right"),
                 "PRECIO\nFINAL"          => Array("width" => "45", "justification" => "right"),
                 "PRECIO ULT.\nCOMPRA"    => Array("width" => "50", "justification" => "right"),
                 "FECHA ULT.\nCOMPRA"     => Array("width" => "50", "justification" => "right"),
                 "FACTURA"                => Array("width" => "45", "justification" => "right")));

   $counter = 0;
   foreach($items AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTÍCULO"]            = $sqlrow["item_title"];
      $data[$counter]["CODIGO/PROV"]         = $sqlrow["item_code"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unitdesc"];
      $data[$counter]["PROVEEDOR"]           = $sqlrow["supp_company"];
      $data[$counter]["PRECIO\nBASICO"]      = $sqlrow["item_costprice_netto"];
      $data[$counter]["DESC\n%"]             = $sqlrow["descperc"];
      $data[$counter]["PRECIO\nFINAL"]       = $sqlrow["buyval"];
      $data[$counter]["PRECIO ULT.\nCOMPRA"] = $sqlrow["buycostprice"];
      $data[$counter]["FECHA ULT.\nCOMPRA"]  = $sqlrow["buytstamp"];
      $data[$counter]["FACTURA"]             = $sqlrow["sinvc"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "LISTA DE PRECIOS DE COMPRA";
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
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $supplier = $CON->select($sql);
      $supplier = $supplier[0];
      $HEADER["DATA"][$pcounter]["x2"] = $supplier["supp_company"];
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