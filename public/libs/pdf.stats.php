<?php
//------------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2023 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//------------------------------------------------------------------------------------

//----------------------------------------------------------------------------------
function doc_createStatsStockchangesUnibag($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "item_sths_unibag";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }



   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Folio"            => Array("width" => "50", "justification" => "left"),
                 "Fecha"            => Array("width" => "50", "justification" => "left"),
                 "Sucursal origen"  => Array("width" => "60", "justification" => "left"),
                 "Bodega origen"    => Array("width" => "100", "justification" => "left"),
                 "Sucursal destino" => Array("width" => "60", "justification" => "left"),
                 "Bodega destino"   => Array("width" => "100", "justification" => "left"),
                 "Código"           => Array("width" => "50", "justification" => "left"),
                 "Producto"         => Array("width" => "110", "justification" => "left"),
                 "Cantidad"         => Array("width" => "45", "justification" => "left"),
                 "Creador"          => Array("width" => "35", "justification" => "left"),
                 "Aprobador"        => Array("width" => "35", "justification" => "left"),
                 "Observaciones"    => Array("width" => "40", "justification" => "left")
                 ));

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $row)
   {
      $data[$counter]["Folio"]            = $row["strc_number"];
      $data[$counter]["Fecha"]            = $row["strc_date"];
      $data[$counter]["Sucursal origen"]  = $row["shop_name_orig"];
      $data[$counter]["Bodega origen"]    = $row["st_name_orig"];
      $data[$counter]["Sucursal destino"] = $row["shop_name_dest"];
      $data[$counter]["Bodega destino"]   = $row["st_name_dest"];
      $data[$counter]["Código"]           = $row["item_number_prod"];
      $data[$counter]["Producto"]         = $row["item_title"];
      $data[$counter]["Cantidad"]         = $row["item_amount"];
      $data[$counter]["Creador"]          = $row["user_crt"];
      $data[$counter]["Aprobador"]        = $row["user_aprob"];
      $data[$counter]["Observaciones"]    = $row["strc_desc"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "TRASPASOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsTrazaCC($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodtraza";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }



   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Nro. CC"                           => Array("width" => "55", "justification" => "left"),
                 "Impresor"                        => Array("width" => "90", "justification" => "left"),
                 "Total mts/lineales programados"  => Array("width" => "70", "justification" => "left"),
                 "Seudonimo clisï¿½s"                => Array("width" => "120", "justification" => "left"),
                 "Fecha creación montaje"          => Array("width" => "60", "justification" => "left"),
                 "Cilindro (z)"                    => Array("width" => "40", "justification" => "left"),
                 "Desarrollo de impresión"         => Array("width" => "60", "justification" => "left"),
                 "Ancho rollo tela"                => Array("width" => "40", "justification" => "left"),
                 "Metros lineales impresos"        => Array("width" => "70", "justification" => "left"),
                 "% Merma"                         => Array("width" => "35", "justification" => "left"),
                 "Diseñador responsable"           => Array("width" => "90", "justification" => "left")
                 ));

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $row)
   {
      $data[$counter]["Nro. CC"]                         = $row["req_number"];
      $data[$counter]["Impresor"]                        = $row["mlin_user"];
      $data[$counter]["Total mts/lineales programados"]  = $row["mlin_prog"];
      $data[$counter]["Seudonimo clisé"]                 = $row["seudonimo"];
      $data[$counter]["Fecha creación montaje"]          = $row["recep_dat"];
      $data[$counter]["Cilindro (z)"]                    = $row["corte_z"];
      $data[$counter]["Desarrollo de impresión"]         = $row["devprints_cc"];
      $data[$counter]["Ancho rollo tela"]                = $row["tela_width"];
      $data[$counter]["Metros lineales impresos"]        = $row["mlin_impresos"];
      $data[$counter]["% Merma"]                         = $row["mermaperc"];
      $data[$counter]["Diseñador responsable"]           = $row["designername"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "TRAZABILIDAD DE CC";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsSolicsCC($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodsolicscc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }



   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 6,
                 "rowGap" => 1, "colGap" => 2, "cols" => Array(
                 "Nï¿½ CC"                  => Array("width" => "35", "justification" => "center"),
                 "Ingreso"                => Array("width" => "35", "justification" => "center"),
                 "Vendedor"               => Array("width" => "40", "justification" => "left"),
                 "Seudï¿½nimo"              => Array("width" => "40", "justification" => "left"),
                 "Tipo bolsa"             => Array("width" => "40", "justification" => "left"),
                 "Medida bolsa"           => Array("width" => "40", "justification" => "left"),
                 "Tipo tela"              => Array("width" => "40", "justification" => "left"),
                 "Tipo Impresiï¿½n"         => Array("width" => "40", "justification" => "left"),
                 "Impreso en"             => Array("width" => "40", "justification" => "left"),
                 "Lados impresiï¿½n"        => Array("width" => "40", "justification" => "left"),
                 "Color tela"             => Array("width" => "40", "justification" => "left"),
                 "Color manillas"         => Array("width" => "40", "justification" => "left"),
                 "Color 1"                => Array("width" => "30", "justification" => "left"),
                 "Color 2"                => Array("width" => "30", "justification" => "left"),
                 "Color 3"                => Array("width" => "30", "justification" => "left"),
                 "Color 4"                => Array("width" => "30", "justification" => "left"),
                 "Largo manillas"         => Array("width" => "40", "justification" => "left"),
                 "Pie de imprenta"        => Array("width" => "40", "justification" => "left"),
                 "Cï¿½digo de barras"       => Array("width" => "40", "justification" => "left"),
                 "Diseï¿½ador responsable"  => Array("width" => "40", "justification" => "left")
                 ));

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $row)
   {
      $data[$counter]["Nï¿½ CC"]                  = $row["req_number"];
      $data[$counter]["Ingreso"]                = $row["date"];
      $data[$counter]["Vendedor"]               = $row["user_lastname"];
      $data[$counter]["Seudï¿½nimo"]              = $row["req_solic_supp_seudonimo"];
      $data[$counter]["Tipo bolsa"]             = $row["item_title"];
      $data[$counter]["Medida bolsa"]           = $row["fab_med_width"];
      $data[$counter]["Tipo tela"]              = $row["fab_type"];
      $data[$counter]["Tipo Impresiï¿½n"]         = $row["fab_printtype"];
      $data[$counter]["Impreso en"]             = $row["supp_short"];
      $data[$counter]["Lados impresiï¿½n"]        = $row["printsides"];
      $data[$counter]["Color tela"]             = $row["fabric_color"];
      $data[$counter]["Color manillas"]         = $row["manilla_color"];
      $data[$counter]["Color 1"]                = $row["colors1"];
      $data[$counter]["Color 2"]                = $row["colors2"];
      $data[$counter]["Color 3"]                = $row["colors3"];
      $data[$counter]["Color 4"]                = $row["colors4"];
      $data[$counter]["Largo manillas"]         = $row["fab_manilla_length"];
      $data[$counter]["Pie de imprenta"]        = $row["req_dsgnchk_pieimprenta_act"];
      $data[$counter]["Cï¿½digo de barras"]       = $row["req_dsgnchk_barcode_act"];
      $data[$counter]["Diseï¿½ador responsable"]  = $row["designername"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "SOLICITUDES DE CC";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsAprobPartidas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodaprob";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Nï¿½ CC"            => Array("width" => "50", "justification" => "center"),
                 "Nï¿½ Prod."         => Array("width" => "40", "justification" => "center"),
                 "Cliente"          => Array("width" => "45", "justification" => "center"),
                 "Diseï¿½o"           => Array("width" => "45", "justification" => "left"),
                 "Cï¿½digo"           => Array("width" => "45", "justification" => "center"),
                 "Producto"         => Array("width" => "45", "justification" => "left"),
                 "Cant./Venta"      => Array("width" => "45", "justification" => "left"),
                 "Mï¿½quina"          => Array("width" => "45", "justification" => "left"),
                 "Operador"         => Array("width" => "45", "justification" => "left"),
                 "Inicio OT"        => Array("width" => "45", "justification" => "center"),
                 "Aprob./Oper."     => Array("width" => "45", "justification" => "left"),
                 "Supervisor"       => Array("width" => "45", "justification" => "left"),
                 "Aprob./Super."    => Array("width" => "45", "justification" => "left"),
                 "Termino OT"       => Array("width" => "45", "justification" => "left"),
                 "Estado"           => Array("width" => "45", "justification" => "left"),
                 "Producido"        => Array("width" => "50", "justification" => "left"),
                 ));

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $row)
   {
      $data[$counter]["Nï¿½ CC"]            = $row["req_number"];
      $data[$counter]["Nï¿½ Prod."]         = $row["prd_number"];
      $data[$counter]["Cliente"]          = $row["cust_name"];
      $data[$counter]["Diseï¿½o"]           = $row["fab_design_name"];
      $data[$counter]["Cï¿½digo"]           = $row["item_number_prod"];
      $data[$counter]["Producto"]         = $row["item_title"];
      $data[$counter]["Cant./Venta"]      = $row["item_amount"];
      $data[$counter]["Mï¿½quina"]          = $row["equipo_name"];
      $data[$counter]["Operador"]         = $row["wuser_lastname"];
      $data[$counter]["Inicio OT"]        = $row["wok_crtdat"];
      $data[$counter]["Aprob./Oper."]     = $row["wctr_ctrdat"];
      $data[$counter]["Supervisor"]       = $row["suser_lastname"];
      $data[$counter]["Aprob./Super."]    = $row["sctr_ctrdat"];
      $data[$counter]["Termino OT"]       = $row["wok_enddat"];
      $data[$counter]["Estado"]           = $row["xstate"];
      $data[$counter]["Producido"]        = $row["_PROD_AMOUNT"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "APROBACIï¿½N PARTIDAS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsProdJornadas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodwrk";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   if((int)$_SESSION[$_sesmodulename]["sql_viewmode"] == 0)
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Entrada"          => Array("width" => "45", "justification" => "center"),
                    "Salida"           => Array("width" => "45", "justification" => "center"),
                    "Nombres"          => Array("width" => "120", "justification" => "left"),
                    "Apellidos"        => Array("width" => "120", "justification" => "left"),
                    "RUT"              => Array("width" => "60", "justification" => "left"),
                    "Mï¿½quina"          => Array("width" => "120", "justification" => "left"),
                    "Incidencia"       => Array("width" => "90", "justification" => "center"),
                    "Comentarios"      => Array("width" => "120", "justification" => "left")
                    ));

      //----------------------------------------------------------------------------------
      $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

      unset($data);
      $data = Array();
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $row)
      {
         $data[$counter]["Entrada"]    = $row["ass_init_hour"];
         $data[$counter]["Salida"]     = $row["ass_end_hour"];
         $data[$counter]["Nombres"]    = $row["wrk_firstname"];
         $data[$counter]["Apellidos"]  = $row["wrk_lastname"];
         $data[$counter]["RUT"]        = $row["wrk_rut"];
         $data[$counter]["Mï¿½quina"]    = $row["equipo_name"];
         $data[$counter]["Incidencia"] = $row["INC"];
         $data[$counter]["Comentarios"] = $row["ass_comments"];
         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }
   else
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Fecha"            => Array("width" => "60", "justification" => "center"),
                    "Dia"              => Array("width" => "40", "justification" => "center"),
                    "Entrada"          => Array("width" => "55", "justification" => "center"),
                    "Salida"           => Array("width" => "55", "justification" => "center"),
                    "Mï¿½quina"          => Array("width" => "180", "justification" => "left"),
                    "Incidencia"       => Array("width" => "135", "justification" => "center"),
                    "Comentarios"      => Array("width" => "190", "justification" => "left")
                    ));

      //----------------------------------------------------------------------------------
      $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $eqname)
      {
         unset($data);
         $data = Array();
         $counter = 0;
         $pdf->ezText("<b>{$eqname}</b> ", 11);
         $pdf->ezText(" ", 3);
         
         foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$eqname] AS $row)
         {
            $data[$counter]["Fecha"]      = $row["datestr"];
            $data[$counter]["Dia"]        = $row["datestm"];
            $data[$counter]["Entrada"]    = $row["ass_init_hour"];
            $data[$counter]["Salida"]     = $row["ass_end_hour"];
            $data[$counter]["Mï¿½quina"]    = $row["equipo_name"];
            $data[$counter]["Incidencia"] = $row["inc_name"];
            $data[$counter]["Comentarios"] = $row["ass_comments"];
            $counter++;
         }
         $pdf->ezTable($data,$type,$dummy,$attr);
         $pdf->ezText(" ", 11);
      }
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "JORNADAS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsProdMaquinas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodmaq";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Fecha"            => Array("width" => "50", "justification" => "center"),
                 "Dia"              => Array("width" => "40", "justification" => "center"),
                 "Entrada"          => Array("width" => "45", "justification" => "center"),
                 "Salida"           => Array("width" => "45", "justification" => "center"),
                 "Nombres"          => Array("width" => "90", "justification" => "left"),
                 "Apellidos"        => Array("width" => "90", "justification" => "left"),
                 "RUT"              => Array("width" => "60", "justification" => "left"),
                 "Cargo"            => Array("width" => "105", "justification" => "left"),
                 "Incidencia"       => Array("width" => "80", "justification" => "center"),
                 "Comentarios"      => Array("width" => "110", "justification" => "left")
                 ));

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $eqname)
   {
      unset($data);
      $data = Array();
      $counter = 0;
      $pdf->ezText("<b>{$eqname}</b> ", 11);
      $pdf->ezText(" ", 3);
      
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$eqname] AS $row)
      {
         $data[$counter]["Fecha"]      = $row["datestr"];
         $data[$counter]["Dia"]        = $row["day"];
         $data[$counter]["Entrada"]    = $row["ass_init_hour"];
         $data[$counter]["Salida"]     = $row["ass_end_hour"];
         $data[$counter]["Nombres"]    = $row["wrk_firstname"];
         $data[$counter]["Apellidos"]  = $row["wrk_lastname"];
         $data[$counter]["RUT"]        = $row["wrk_rut"];
         $data[$counter]["Cargo"]      = $row["cargo"];
         $data[$counter]["Incidencia"] = $row["incidencia"];
         $data[$counter]["Comentarios"] = $row["ass_comments"];
         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "PERSONAL POR MAQUINA";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsEncuestas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 6,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Fecha"                              => Array("width" => "40", "justification" => "left"),
                 "RUT"                                => Array("width" => "50", "justification" => "left"),
                 "Nombre"                             => Array("width" => "75", "justification" => "left"),
                 "Email"                              => Array("width" => "75", "justification" => "left"),
                 "Monto"                              => Array("width" => "35", "justification" => "center"),
                 "Fecha\nEncuesta"                    => Array("width" => "40", "justification" => "center"),
                 "Organizaciï¿½n"                       => Array("width" => "30", "justification" => "center"),
                 "Calidad\nproducto"                  => Array("width" => "30", "justification" => "center"),
                 "Rapidez\nenvï¿½o"                     => Array("width" => "30", "justification" => "center"),
                 "Cumpl.\ndiseï¿½o"                     => Array("width" => "30", "justification" => "center"),
                 "Experiencia\ncompra"                => Array("width" => "30", "justification" => "center"),
                 "Atenciï¿½n"                           => Array("width" => "30", "justification" => "center"),
                 "Precio/\nCalidad"                   => Array("width" => "30", "justification" => "center"),
                 "Recomen\ndaciï¿½n"                    => Array("width" => "30", "justification" => "center"),
                 "Como se\nenterï¿½"                    => Array("width" => "50", "justification" => "center"),
                 "Comentarios"                        => Array("width" => "30", "justification" => "center"),
                 "Productos\ncomprados"               => Array("width" => "50", "justification" => "center"),
                 "Gustaria\ncomprar"                  => Array("width" => "50", "justification" => "center"),
                 "Otros\nproductos"                   => Array("width" => "30", "justification" => "center"),
                 ));

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   unset($data);
   $data = Array();
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $row)
   {
      $data[$counter]["Fecha"]                  = $row["invc_date"];
      $data[$counter]["RUT"]                    = $row["cust_rut"];
      $data[$counter]["Nombre"]                 = $row["cust_name"];
      $data[$counter]["Email"]                  = $row["cust_email"];
      $data[$counter]["Monto"]                  = $row["invc_total_brutto"];
      $data[$counter]["Fecha\nEncuesta"]        = $row["enc_upddat"];
      $data[$counter]["Organización"]           = $row["enc_resp_1"];
      $data[$counter]["Calidad\nproducto"]      = $row["enc_resp_2"];
      $data[$counter]["Rapidez\nenvío"]         = $row["enc_resp_3"];
      $data[$counter]["Cumpl.\ndiseño"]         = $row["enc_resp_4"];
      $data[$counter]["Experiencia\ncompra"]    = $row["enc_resp_5"];
      $data[$counter]["Atención"]               = $row["enc_resp_6"];
      $data[$counter]["Precio/\nCalidad"]       = $row["enc_resp_7"];
      $data[$counter]["Recomen\ndación"]        = $row["enc_resp_8"];
      $data[$counter]["Como se\nenteró"]        = $row["enc_resp_9"];
      $data[$counter]["Comentarios"]            = $row["enc_resp_10"];
      $data[$counter]["Productos\ncomprados"]   = $row["enc_resp_11"];
      $data[$counter]["Gustaria\ncomprar"]      = $row["enc_resp_12"];
      $data[$counter]["Otros\nproductos"]       = $row["enc_resp_13"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "ENCUESTAS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsItemCharactStock($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_charact";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 1)
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Cï¿½DIGO"     => Array("width" => "50", "justification" => "left"),
                    "PRODUCTO"   => Array("width" => "120", "justification" => "left"),

                    "UNIDAD"   => Array("width" => "40", "justification" => "left"),
                    "PROVEEDOR"   => Array("width" => "80", "justification" => "left"),
                    "Cï¿½DIGO/PROV."   => Array("width" => "60", "justification" => "left"),

                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}" => Array("width" => "120", "justification" => "left"),
                    "ANCHO"      => Array("width" => "50", "justification" => "center"),
                    "GSM"        => Array("width" => "50", "justification" => "center"),
                    "KG UNIT"    => Array("width" => "50", "justification" => "center"),
                    "LONGITUD"   => Array("width" => "50", "justification" => "center"),
                    "STOCK"      => Array("width" => "50", "justification" => "center")));
   }
   elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 2)
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Cï¿½DIGO"     => Array("width" => "50", "justification" => "left"),
                    "PRODUCTO"   => Array("width" => "120", "justification" => "left"),
                    "UNIDAD"   => Array("width" => "40", "justification" => "left"),
                    "PROVEEDOR"   => Array("width" => "80", "justification" => "left"),
                    "Cï¿½DIGO/PROV."   => Array("width" => "60", "justification" => "left"),

                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}" => Array("width" => "60", "justification" => "left"),
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][1]}" => Array("width" => "60", "justification" => "left"),
                    "ANCHO"      => Array("width" => "50", "justification" => "center"),
                    "GSM"        => Array("width" => "50", "justification" => "center"),
                    "KG UNIT"    => Array("width" => "50", "justification" => "center"),
                    "LONGITUD"   => Array("width" => "50", "justification" => "center"),
                    "STOCK"      => Array("width" => "50", "justification" => "center")));
   }
   elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 3)
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Cï¿½DIGO"     => Array("width" => "50", "justification" => "left"),
                    "PRODUCTO"   => Array("width" => "110", "justification" => "left"),
                    "UNIDAD"   => Array("width" => "40", "justification" => "left"),
                    "PROVEEDOR"   => Array("width" => "80", "justification" => "left"),
                    "Cï¿½DIGO/PROV."   => Array("width" => "60", "justification" => "left"),

                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}" => Array("width" => "60", "justification" => "left"),
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][1]}" => Array("width" => "60", "justification" => "left"),
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][2]}" => Array("width" => "60", "justification" => "left"),
                    "ANCHO"      => Array("width" => "40", "justification" => "center"),
                    "GSM"        => Array("width" => "40", "justification" => "center"),
                    "KG UNIT"    => Array("width" => "40", "justification" => "center"),
                    "LONGITUD"   => Array("width" => "40", "justification" => "center"),
                    "STOCK"      => Array("width" => "40", "justification" => "center")));
   }
   


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      if(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 1)
      {
         $data[$counter]["Cï¿½DIGO"]     = $sqlrow["CODE"];
         $data[$counter]["PRODUCTO"]   = $sqlrow["NAME"];
         $data[$counter]["UNIDAD"]        = $sqlrow["UNIT"];
         $data[$counter]["PROVEEDOR"]     = $sqlrow["SUPP"];
         $data[$counter]["Cï¿½DIGO/PROV."]  = $sqlrow["SCOD"];

         $data[$counter]["{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}"]        = $sqlrow["valid0"];
         $data[$counter]["ANCHO"]      = $sqlrow["xwidth"];
         $data[$counter]["GSM"]        = $sqlrow["xgsm"];
         $data[$counter]["KG UNIT"]    = $sqlrow["xkg"];
         $data[$counter]["LONGITUD"]   = $sqlrow["xlength"];
         $data[$counter]["STOCK"]      = $sqlrow["xstock"];
      }
      elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 2)
      {
         $data[$counter]["Cï¿½DIGO"]     = $sqlrow["CODE"];
         $data[$counter]["PRODUCTO"]   = $sqlrow["NAME"];
         $data[$counter]["UNIDAD"]        = $sqlrow["UNIT"];
         $data[$counter]["PROVEEDOR"]     = $sqlrow["SUPP"];
         $data[$counter]["Cï¿½DIGO/PROV."]  = $sqlrow["SCOD"];

         $data[$counter]["{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}"]        = $sqlrow["valid0"];
         $data[$counter]["{$_SESSION["STATS"][$_sesmodulename]["HEADER"][1]}"]        = $sqlrow["valid1"];
         $data[$counter]["ANCHO"]      = $sqlrow["xwidth"];
         $data[$counter]["GSM"]        = $sqlrow["xgsm"];
         $data[$counter]["KG UNIT"]    = $sqlrow["xkg"];
         $data[$counter]["LONGITUD"]   = $sqlrow["xlength"];
         $data[$counter]["STOCK"]      = $sqlrow["xstock"];
      }
      elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 3)
      {
         $data[$counter]["Cï¿½DIGO"]     = $sqlrow["CODE"];
         $data[$counter]["PRODUCTO"]   = $sqlrow["NAME"];
         $data[$counter]["UNIDAD"]        = $sqlrow["UNIT"];
         $data[$counter]["PROVEEDOR"]     = $sqlrow["SUPP"];
         $data[$counter]["Cï¿½DIGO/PROV."]  = $sqlrow["SCOD"];

         $data[$counter]["{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}"]        = $sqlrow["valid0"];
         $data[$counter]["{$_SESSION["STATS"][$_sesmodulename]["HEADER"][1]}"]        = $sqlrow["valid1"];
         $data[$counter]["{$_SESSION["STATS"][$_sesmodulename]["HEADER"][2]}"]        = $sqlrow["valid2"];
         $data[$counter]["ANCHO"]      = $sqlrow["xwidth"];
         $data[$counter]["GSM"]        = $sqlrow["xgsm"];
         $data[$counter]["KG UNIT"]    = $sqlrow["xkg"];
         $data[$counter]["LONGITUD"]   = $sqlrow["xlength"];
         $data[$counter]["STOCK"]      = $sqlrow["xstock"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK POR CARACTERISTICA";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsStockchanges($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockchanges";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   $attr1 = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "TRASPASO"               => Array("width" => "50", "justification"  => "left"),
                 "FECHA"          => Array("width" => "50", "justification" => "left"),
                 "ORIGEN"            => Array("width" => "305", "justification" => "left"),
                 "DESTINO"            => Array("width" => "305", "justification" => "left")
                 ));

   $attr2 = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "TRASPASO"               => Array("width" => "80", "justification"  => "left"),
                 "FECHA"          => Array("width" => "80", "justification" => "left"),
                 "ORIGEN"            => Array("width" => "195", "justification" => "left"),
                 "DESTINO"            => Array("width" => "195", "justification" => "left"),
                 "CANTIDAD"        => Array("width" => "100", "justification" => "left"),
                 "NETO/TOTAL"        => Array("width" => "100", "justification" => "left")
                 ));

   $attr3 = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "TRASPASO"               => Array("width" => "50", "justification"  => "left"),
                 "FECHA"          => Array("width" => "50", "justification" => "left"),
                 "ORIGEN"            => Array("width" => "105", "justification" => "left"),
                 "DESTINO"            => Array("width" => "105", "justification" => "left"),
                 "ARTï¿½CULO" => Array("width" => "200", "justification" => "left"),
                 "UNIDAD"     => Array("width" => "50", "justification" => "left"),
                 "CANTIDAD"        => Array("width" => "50", "justification" => "left"),
                 "NETO/U"        => Array("width" => "50", "justification" => "left"),
                 "NETO TOTAL"        => Array("width" => "50", "justification" => "left")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA3"] AS $row)
   {
      $data[$counter]["TRASPASO"] = $row["strc_number"];
      $data[$counter]["FECHA"]    = $row["strc_date"];
      $data[$counter]["ORIGEN"]   = $row["from_st_name"];
      $data[$counter]["DESTINO"]   = $row["st_name"];
      $counter++;
   }

   //----------------------------------------------------------------------------------
   $pdf->ezTable($data,$type,$dummy,$attr1);
   unset($data);
   $data = Array();
   $pdf->ezText(" ", 11);

   $counter = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $idx1)
   {
      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
      {
         $counter = 0;
         foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $row)
         {
            $data[$counter]["TRASPASO"]       = $row["strc_number"];
            $data[$counter]["FECHA"]          = $row["strc_date"];
            $data[$counter]["ORIGEN"] = $row["from_st_name"];
            $data[$counter]["DESTINO"] = $row["st_name"];
            $data[$counter]["ARTï¿½CULO"]       = $row["item_title"];
            $data[$counter]["UNIDAD"]         = $row["unit_name"];
            $data[$counter]["CANTIDAD"]       = $row["item_amount"];
            $data[$counter]["NETO/U"]       = $row["item_costprice_netto"];
            $data[$counter]["NETO TOTAL"]   = $row["item_costprice_netto_total"];
            $counter++;
         }

         $data[$counter]["TRASPASO"]       = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_number"];
         $data[$counter]["FECHA"]          = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_date"];
         $data[$counter]["ORIGEN"] = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["from_st_name"];
         $data[$counter]["DESTINO"] = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["st_name"];
         $data[$counter]["ARTï¿½CULO"]       = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_shop_stid_dest_val"]." | ".$_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_shop_stid_dest_val2"];
         $data[$counter]["UNIDAD"]         = "";
         $data[$counter]["CANTIDAD"]       = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesamount"];
         $data[$counter]["NETO/U"]       = "";
         $data[$counter]["NETO TOTAL"]   = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesnetto"];

         //----------------------------------------------------------------------------------
         $pdf->ezTable($data,$type,$dummy,$attr3);
         unset($data);
         $data = Array();
         $pdf->ezText(" ", 11);
      }

      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
      {
         $data[$counter]["TRASPASO"]       = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_number"];
         $data[$counter]["FECHA"]          = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_date"];
         $data[$counter]["ORIGEN"]         = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["from_st_name"];
         $data[$counter]["DESTINO"]         = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["st_name"];
         $data[$counter]["CANTIDAD"]       = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesamount"];
         $data[$counter]["NETO TOTAL"]   = $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesnetto"];
         $counter++;
      }
   }

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
   {
      $pdf->ezTable($data,$type,$dummy,$attr2);
      unset($data);
      $data = Array();
      $pdf->ezText(" ", 11);
   }
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
   $HEADER["DATA"][$pcounter]["x2"] = "TRASPASOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["company_id"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["company_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["company_short"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $cat = $CON->select($sql);
      $cat = $cat[0];
      $HEADER["DATA"][$pcounter]["x4"] = $cat["cat_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   //----------------------------------------------------------------------------------
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["shop_id"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["shop_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = " ";
   $HEADER["DATA"][$pcounter]["x4"] = " ";

   //----------------------------------------------------------------------------------
   $pcounter++;

   //----------------------------------------------------------------------------------
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

//-------------------------------------------------------------------------------------------
function doc_createStatsSthPedidos($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "cpps_stats_pedidos";
   $_sesmodulename = $doctype;
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   
   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "730", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap"  => 3, "cols" => Array(
                 "PEDIDO"               => Array("width" => "40", "justification" => "left"),
                 "FECHA"                => Array("width" => "50", "justification" => "left"),
                 "ESTADO"               => Array("width" => "65", "justification" => "left"),
                 "SUCURSAL"             => Array("width" => "90", "justification" => "left"),
                 "USUARIO"              => Array("width" => "90", "justification" => "left"),
                 "Cï¿½DIGO"               => Array("width" => "70", "justification" => "left"),
                 "PRODUCTO"             => Array("width" => "200", "justification" => "left"),
                 "CANT/PEDIDO"          => Array("width" => "60", "justification" => "right"),
                 "CANT/ENTREGA"         => Array("width" => "65", "justification" => "right")));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      
      $data[$counter]["PEDIDO"]     = $sqlrow["xid"];
      $data[$counter]["FECHA"]      = $sqlrow["strc_date"];
      $data[$counter]["ESTADO"]     = $sqlrow["xstate"];
      $data[$counter]["SUCURSAL"]   = $sqlrow["shop"];
      $data[$counter]["USUARIO"]    = $sqlrow["user"];
      $data[$counter]["Cï¿½DIGO"]     = $sqlrow["itemnumber"];
      $data[$counter]["PRODUCTO"]   = $sqlrow["item_title"];
      $data[$counter]["CANT/PEDIDO"]     = $sqlrow["item_amount"];
      $data[$counter]["CANT/ENTREGA"]    = $sqlrow["order_amount"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "PEDIDOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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

//----------------------------------------------------------------------------------
function doc_createStatsCashClose($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "cash_close";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "invc_bonus");

   $fontSize = 9;

   if($_REQUEST["mode"] == "general")
   {
      $addtitle = " GENERAL";
      $fontSize = 8.5;
   }

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "520", "xpos" => "left", "showLines" => 2, "fontSize" => $fontSize,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "90", "justification"  => "left"),
                 "X2" => Array("width" => "190", "justification"  => "left"),
                 "X3" => Array("width" => "80", "justification"  => "left"),
                 "X4" => Array("width" => "160", "justification"  => "left")
                 ));
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA1"] AS $sqlrow)
   {
      for($y = 1; $y <= 4; $y++)
         $data[$counter]["X{$y}"] = $sqlrow["val{$y}"];
      $counter++;
   }
   //----------------------------------------------------------------------------------
   $pdf->ezTable($data,$type,"CIERRE DE CAJA".$addtitle,$attr);
   unset($data);
   $data = Array();
   $pdf->ezText(" ", $fontSize);

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "520", "xpos" => "left", "showLines" => 2, "fontSize" => $fontSize,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "280", "justification"  => "left"),
                 "X2" => Array("width" => "80", "justification"  => "right"),
                 "X3" => Array("width" => "80", "justification"  => "right"),
                 "X4" => Array("width" => "80", "justification"  => "right")
                 ));
   $counter = 0;
   $data[$counter]["X1"] = "<b>MEDIO PAGO</b>";
   $data[$counter]["X2"] = "<b>SISTEMA</b>";
   $data[$counter]["X3"] = "<b>CAJERA</b>";
   $data[$counter]["X4"] = "<b>DIFERENCIA</b>";
   $counter++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"] AS $sqlrow)
   {
      for($y = 1; $y <= 4; $y++)
         $data[$counter]["X{$y}"] = trim($sqlrow["val{$y}"]);
      $counter++;
   }

   //----------------------------------------------------------------------------------
   $pdf->ezTable($data,$type,"",$attr);
   unset($data);
   $data = Array();
   $pdf->ezText(" ", $fontSize);

   //----------------------------------------------------------------------------------
   if($_SESSION["STATS"][$_sesmodulename]["ADMINCLOSE"] != "")
   {
      $attr = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "520", "xpos" => "left", "showLines" => 2, "fontSize" => $fontSize,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "X1" => Array("width" => "440", "justification"  => "left"),
                    "X2" => Array("width" => "80", "justification"  => "right")
                    ));
      $counter = 0;
      $data[$counter]["X1"] = "<b>VALES CERRADOS POR ADMIN</b>";
      $data[$counter]["X2"] = $_SESSION["STATS"][$_sesmodulename]["ADMINCLOSE"];
      $pdf->ezTable($data,$type,"",$attr);
      $pdf->ezText(" ", $fontSize);
      unset($data);
      $data = Array();
   }

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "520", "xpos" => "left", "showLines" => 2, "fontSize" => $fontSize,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "360", "justification"  => "left"),
                 "X2" => Array("width" => "80", "justification"  => "right"),
                 "X3" => Array("width" => "80", "justification"  => "right")
                 ));
   $counter = 0;
   $data[$counter]["X1"] = "<b>EFECTIVO</b>";
   $data[$counter]["X2"] = "<b>CANTIDAD</b>";
   $data[$counter]["X3"] = "<b>MONTO</b>";
   $counter++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA3"] AS $sqlrow)
   {
      for($y = 1; $y <= 3; $y++)
         $data[$counter]["X{$y}"] = strtoupper($sqlrow["val{$y}"]);
      $counter++;
   }

   //----------------------------------------------------------------------------------
   $pdf->ezTable($data,$type,"",$attr);
   unset($data);
   $data = Array();
   $pdf->ezText(" ", $fontSize);

   /*
   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "520", "xpos" => "left", "showLines" => 2, "fontSize" => $fontSize,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "360", "justification"  => "left"),
                 "X2" => Array("width" => "80", "justification"  => "center"),
                 "X3" => Array("width" => "80", "justification"  => "right")
                 ));
   $counter = 0;
   $data[$counter]["X1"] = "<b>TIPO DOCUMENTO</b>";
   $data[$counter]["X2"] = "<b>CANTIDAD</b>";
   $data[$counter]["X3"] = "<b>MONTO</b>";
   $counter++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA4"] AS $sqlrow)
   {
      for($y = 1; $y <= 3; $y++)
         $data[$counter]["X{$y}"] = $sqlrow["val{$y}"];
      $counter++;
   }

   //----------------------------------------------------------------------------------
   $pdf->ezTable($data,$type,"",$attr);
   */
   unset($data);
   $data = Array();
   $pdf->ezText(" ", $fontSize);

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

function doc_createStatsBreak($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "adm_payments";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "730", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap"  => 3, "cols" => Array(
                 "<b>CODIGO</b>"      => Array("width" => "75",  "justification" => "left"),
                 "<b>DESCRIPCION</b>" => Array("width" => "220", "justification" => "left"),
                 "<b>UNIDAD</b>"      => Array("width" => "45",  "justification" => "left"),
                 "<b>STOCK</b>"       => Array("width" => "55",  "justification" => "right"),
                 "<b>VENTAS</b>"      => Array("width" => "55",  "justification" => "right"),
                 "<b>VALOR UNIT.</b>" => Array("width" => "55",  "justification" => "right"),
                 "<b>VALOR ACUM.</b>" => Array("width" => "60",  "justification" => "right"),
                 "<b>PORCENTAJE</b>"  => Array("width" => "55",  "justification" => "right"),
                 "<b>PROYECTADO</b>"  => Array("width" => "60",  "justification" => "right"),
                 "<b>QUIEBRE</b>"     => Array("width" => "50",  "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["<b>CODIGO</b>"]       = $sqlrow["CODE"];
      $data[$counter]["<b>DESCRIPCION</b>"]  = $sqlrow["TITLE"];
      $data[$counter]["<b>UNIDAD</b>"]       = $sqlrow["UNIT"];
      $data[$counter]["<b>STOCK</b>"]        = $sqlrow["STOCK"];
      $data[$counter]["<b>VENTAS</b>"]       = $sqlrow["AMOUNT"];
      $data[$counter]["<b>VALOR UNIT.</b>"]  = $sqlrow["VAL_UNIT"];
      $data[$counter]["<b>VALOR ACUM.</b>"]  = $sqlrow["VAL_ACUM"];
      $data[$counter]["<b>PORCENTAJE</b>"]   = $sqlrow["PERC"];
      $data[$counter]["<b>PROYECTADO</b>"]   = $sqlrow["PROYEC"];
      $data[$counter]["<b>QUIEBRE</b>"]      = $sqlrow["BREAK"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   //----------------------------------------------------------------------------------
   $data = array();
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8,
                   "showLines" => 2, "rowGap" => 2, "colGap" => 3,
                   "cols" => Array (
                   "x1" => Array("width" => "340", "justification" => "left"),
                   "x2" => Array("width" => "55", "justification" => "right"),
                   "x3" => Array("width" => "55", "justification" => "right"),
                   "x4" => Array("width" => "55", "justification" => "right"),
                   "x5" => Array("width" => "60", "justification" => "right"),
                   "x6" => Array("width" => "55", "justification" => "right"),
                   "x7" => Array("width" => "60", "justification" => "right"),
                   "x8" => Array("width" => "50", "justification" => "right")
                  ));

   //----------------------------------------------------------------------------------
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $data[$pcounter]["x1"] = "<b>TOTAL</b>";
   $data[$pcounter]["x2"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["STOCK"]."</b>";
   $data[$pcounter]["x3"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["SELL"]."</b>";
   $data[$pcounter]["x4"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["VAL_UNIT"]."</b>";
   $data[$pcounter]["x5"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["VAL_ACUM"]."</b>";
   $data[$pcounter]["x6"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["PERC"]."</b>";
   $data[$pcounter]["x7"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["PROYEC"]."</b>";
   $data[$pcounter]["x8"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["TOTAL"]["BREAK"]."</b>";

   $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8,
                   "showLines" => 0, "rowGap" => 1, "colGap" => 0,
                   "cols" => Array (
                   "x1" => Array("width" => "100", "justification" => "left"),
                   "x2" => Array("width" => "260", "justification" => "left"),
                   "x3" => Array("width" => "100", "justification" => "left"),
                   "x4" => Array("width" => "260", "justification" => "left")));

   //----------------------------------------------------------------------------------
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME</b>";
   $HEADER["DATA"][$pcounter]["x2"] = " : QUIEBRE";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA </b>";
   $HEADER["DATA"][$pcounter]["x4"] = " : ".displaydate($currtme);
   $pcounter++;
   $HEADER["DATA"][$pcounter]["x1"] = "<b>% INCREMENTO</b>";
   $HEADER["DATA"][$pcounter]["x2"] = " : ".$_SESSION[$_sesmodulename]["sql_percentage"];

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
      $HEADER["DATA"][$pcounter]["x4"] = " : ".$company["company_short"];
   }
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $shop = $CON->select($sql);
      $shop = $shop[0];
      $HEADER["DATA"][$pcounter]["x3"] = "<b>SUCURSAL</b>";
      $HEADER["DATA"][$pcounter]["x4"] = " : ".$shop["shop_name"];
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

function doc_createStatsPayment($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "adm_payments";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "730", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap"  => 3, "cols" => Array(
                 "<b>ITEM</b>"            => Array("width" => "500", "justification" => "left"),
                 "<b>CANTIDAD</b>"        => Array("width" => "115", "justification" => "left"),
                 "<b>MONTO TOTAL</b>"     => Array("width" => "115", "justification" => "right")
                 ));

   $counter = 0;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["<b>ITEM</b>"]             = $sqlrow["name"];
      $data[$counter]["<b>CANTIDAD</b>"]         = printPrice($sqlrow["cant"]);
      $data[$counter]["<b>MONTO TOTAL</b>"]      = printPrice($sqlrow["value"]);
      $counter++;
   }
   $data[$counter]["<b>ITEM</b>"]      = "<b>TOTAL MONTO</b>";    
   $data[$counter]["<b>CANTIDAD</b>"]  = $_SESSION["STATS"][$_sesmodulename]["amount"];    
   $data[$counter]["<b>MONTO TOTAL</b>"]     = "<b>$ ". $_SESSION["STATS"][$_sesmodulename]["total"]. "</b>"; 

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
   $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME: </b>";
   $HEADER["DATA"][$pcounter]["x2"] = "PAGOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA: </b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>EMPRESA: </b>";
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
   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $shop = $CON->select($sql);
      $shop = $shop[0];
      $HEADER["DATA"][$pcounter]["x3"] = "<b>SUCURSAL: </b>";
      $HEADER["DATA"][$pcounter]["x4"] = $shop["shop_name"];
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
//------------------------------------------------------------------------------------
function doc_createStatsStockPrice($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_prices";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();
   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "730", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap"  => 3, "cols" => Array(
                 "<b>COD. PRODUCTO</b>"     => Array("width" => "100", "justification" => "left"),
                 "<b>NOMBRE</b>"            => Array("width" => "230", "justification" => "justify"),
                 "<b>CANTIDAD</b>"          => Array("width" => "100", "justification" => "right"),
                 "<b>PRECIO BASE/NETO</b>"  => Array("width" => "100", "justification" => "right"),
                 "<b>MAYORISTA/BRUTO</b>"   => Array("width" => "100", "justification" => "right"),
                 "<b>DETALLE/BRUTO</b>"     => Array("width" => "100", "justification" => "right")
                 ));

   $counter = 0;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["<b>COD. PRODUCTO</b>"]        = $sqlrow["num"];
      $data[$counter]["<b>NOMBRE</b>"]               = $sqlrow["title"];
      $data[$counter]["<b>CANTIDAD</b>"]             = $sqlrow["stock"];
      $data[$counter]["<b>PRECIO BASE/NETO</b>"]     = $sqlrow["neto"];
      $data[$counter]["<b>MAYORISTA/BRUTO</b>"]      = $sqlrow["bruto"];
      $data[$counter]["<b>DETALLE/BRUTO</b>"]        = $sqlrow["dbruto"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);

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
   $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME :</b>";
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK Y PRECIOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA :</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;
   $HEADER["DATA"][$pcounter]["x1"] = "<b>EMPRESA :</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["COMPANY"];
   $HEADER["DATA"][$pcounter]["x3"] = "<b>SUCURSAL :</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["STATS"][$_sesmodulename]["SHOP"];


   //----------------------------------------------------------------------------------

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

//------------------------------------------------------------------------------------
function doc_createStatsAdminPayments($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "createadminpayment";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "730", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap"  => 3, "cols" => Array(
                 "ITEM"     => Array("width" => "500", "justification" => "left"),
                 "CANTIDAD" => Array("width" => "115", "justification" => "left"),
                 "TOTAL"    => Array("width" => "115", "justification" => "right")
                 ));

   $counter = 0;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["ITEM"]       = $sqlrow["name"];
      $data[$counter]["CANTIDAD"]   = printPrice($sqlrow["cant"]);
      $data[$counter]["TOTAL"]      = printPrice($sqlrow["value"]);
      $counter++;
   }
   $data[$counter]["ITEM"]      = "<b>TOTAL MONTO</b>";    
   $data[$counter]["CANTIDAD"]  = $_SESSION["STATS"][$_sesmodulename]["amount"];    
   $data[$counter]["TOTAL"]     = "<b>". $_SESSION["STATS"][$_sesmodulename]["total"]. "</b>"; 

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
   $HEADER["DATA"][$pcounter]["x2"] = "PAGOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "";
   $HEADER["DATA"][$pcounter]["x2"] = "";
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsUbicacionchange($CON, $uid)
{
   global $_LANG;

   //----------------------------------------------------------------------------------
   $sql = " select t1.*,
                   t6.user_firstname 'upd_firstname', t6.user_lastname 'upd_lastname',
                   t7.user_firstname 'crt_firstname', t7.user_lastname 'crt_lastname'
            from ubicacionchanges t1
            LEFT OUTER JOIN user t6 ON t1.ubic_updusr = t6.id
            LEFT OUTER JOIN user t7 ON t1.ubic_crtusr = t7.id
            where
            t1.id = {$uid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select t2.*, t3.item_title, t3.item_number_prod, t4.ubi_name 'ubi_name_orig', t5.ubi_name 'ubi_name_dest'
            from ubicacionchanges_items t2
            LEFT OUTER JOIN item      t3 ON t2.item_id        = t3.id
            LEFT OUTER JOIN ubicacion t4 ON t2.item_ubic_orig = t4.id
            LEFT OUTER JOIN ubicacion t5 ON t2.item_ubic_dest = t5.id
            where
            t2.ubic_id = {$uid}
            order by t2.id asc";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $doctype    = "itemtrans_detail";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "ARTICULO"          => Array("width" => "410", "justification" => "left"),
                 "UBICACION ORIGEN"  => Array("width" => "150", "justification" => "left"),
                 "UBICACION DESTINO" => Array("width" => "150", "justification" => "left")
                 ));

   $counter = 0;
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row = $posdata[$x];

      $data[$counter]["ARTICULO"]          = $row["item_number_prod"]." - ".$row["item_title"];
      $data[$counter]["UBICACION ORIGEN"]  = $row["ubi_name_orig"];
      $data[$counter]["UBICACION DESTINO"] = $row["ubi_name_dest"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "CAMBIO UBICACION";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>NUMERO</b>";
   $HEADER["DATA"][$pcounter]["x2"] = sprintf("%05s", $headdata["id"]);
   $HEADER["DATA"][$pcounter]["x3"] = "<b>CREADO</b>";
   $HEADER["DATA"][$pcounter]["x4"] = date("d.m.Y", $headdata["ubic_crtdat"]);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsItemTransDetail($CON, $itemid)
{
   global $_LANG;

   $_sesmodulename = "stock_trans";
   $items = $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$itemid];
   $key   = array_keys($items);
   $key   = $key[0];

   //----------------------------------------------------------------------------------
   $doctype    = "itemtrans_detail";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FECHA"         => Array("width" => "50", "justification" => "left"),
                 "DOCUMENTO"     => Array("width" => "100", "justification" => "left"),
                 "ENTRADA"       => Array("width" => "50", "justification" => "center"),
                 "SALIDA"        => Array("width" => "50", "justification" => "center"),
                 "SALDO"         => Array("width" => "50", "justification" => "center"),
                 "CLIENTE"       => Array("width" => "140", "justification" => "left"),
                 "OBSERVACIONES" => Array("width" => "150", "justification" => "left"),
                 "DESTINO"       => Array("width" => "125", "justification" => "left"),
                 ));

   $counter = 0;
   $items = array_reverse($items);
   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      $row = $items[$x];

      $data[$counter]["FECHA"]           = $row["FECHA"];
      $data[$counter]["DOCUMENTO"]       = $row["NUMERO DOCTO."];
      $data[$counter]["ENTRADA"]         = printprice($row["ENTRADA"]);
      $data[$counter]["SALIDA"]          = printprice($row["SALIDA"]);
      $data[$counter]["SALDO"]           = printprice($row["SALDO"]);
      $data[$counter]["CLIENTE"]         = $row["CUSTNAME"];
      $data[$counter]["OBSERVACIONES"]   = $row["OBSERV"];
      $data[$counter]["DESTINO"]         = $row["DESTNAME"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

   $items = $_SESSION["STATS"][$_sesmodulename]["DETAILS"][$itemid];
   $key   = array_keys($items);
   $key   = $key[0];

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
   $HEADER["DATA"][$pcounter]["x2"] = "MOVIMIENTOS DEL STOCK DETALLE";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>NOMBRE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $items[$key]["item_number_prod"]." - ".$items[$key]["item_title"];
   $HEADER["DATA"][$pcounter]["x3"] = "<b>UNIDAD</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $items[$key]["unit_name"];
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsSellingEvo($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_sell_evolution";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $init_month = $_SESSION[$_sesmodulename]["sql_month1"];
   $end_month  = $_SESSION[$_sesmodulename]["sql_month2"];
   $end_year   = $_SESSION[$_sesmodulename]["sql_year1"];
   $init_year  = $end_year - $_SESSION[$_sesmodulename]["sql_yearcount"];

   $colidx1 = "MES NOMBRE";
   $colidx2 = "MES";
   if((int)$_SESSION[$_sesmodulename]["sql_mode"] != 0)
   {
      $colidx1 = "DIA";
      $colidx2 = "MES";
   }

   $_cols = Array($colidx1         => Array("width" => "60", "justification" => "left"),
                  $colidx2                => Array("width" => "30", "justification" => "center"));
   for($y = $init_year; $y <= $end_year; $y++)
   {
      $_cols["{$y}\nCant"]   = Array("width" => "35", "justification" => "center");
      $_cols["{$y}\nMonto"]  = Array("width" => "55", "justification" => "center");
      $_cols["{$y}\n%"]      = Array("width" => "35", "justification" => "center");
   }

   //----------------------------------------------------------------------------------
   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => $_cols);


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter][$colidx1] = $sqlrow["MONTHNAME"];

      if((int)$_SESSION[$_sesmodulename]["sql_mode"] == 0)
         $data[$counter][$colidx2] = $sqlrow["MONTH"];
      else
         $data[$counter][$colidx2] = $_SESSION[$_sesmodulename]["sql_month1"];

      for($y = $init_year; $y <= $end_year; $y++)
      {
         $data[$counter]["{$y}\nCant"]    = $sqlrow[$sqlrow["MONTH"]."-".$y]["COUNT"];
         $data[$counter]["{$y}\nMonto"]   = $sqlrow[$sqlrow["MONTH"]."-".$y]["VALUE"];
         $data[$counter]["{$y}\n%"]       = $sqlrow[$sqlrow["MONTH"]."-".$y]["DIFF"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPARACIï¿½N ANUAL";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsStockItems($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockitems";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if((int)$_REQUEST["mid"] != 1215)
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "NUMERO"        => Array("width" => "50", "justification" => "left"),
                    "NOMBRE"        => Array("width" => "170", "justification" => "left"),
                    "UNIDAD"        => Array("width" => "45", "justification" => "left"),
                    "BODEGA"        => Array("width" => "105", "justification" => "left"),
                    "CANT."         => Array("width" => "50", "justification" => "right"),
                    "CANT./TOTAL"   => Array("width" => "50", "justification" => "right"),
                    "\$/UNIT"       => Array("width" => "60", "justification" => "right"),
                    "\$/TOTAL"      => Array("width" => "60", "justification" => "right"),
                    "CANT./TRANS."   => Array("width" => "60", "justification" => "right"),
                    "CANT./DISPO."   => Array("width" => "60", "justification" => "right")
                    ));
      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
      {
         $data[$counter]["NUMERO"] = $sqlrow["num"];
         $data[$counter]["NOMBRE"]  = $sqlrow["title"];
         $data[$counter]["UNIDAD"]  = $sqlrow["unit"];
         $data[$counter]["BODEGA"] = $sqlrow["sth"];
         $data[$counter]["CANT."]   = $sqlrow["inv"];
         $data[$counter]["CANT./TOTAL"] = $sqlrow["amount"];
         $data[$counter]["\$/UNIT"] = $sqlrow["price"];
         $data[$counter]["\$/TOTAL"] = $sqlrow["totalprice"];

         $data[$counter]["CANT./TRANS."] = $sqlrow["_TRANSSTOCK"];
         $data[$counter]["CANT./DISPO."] = $sqlrow["_DISPOSTOCK"];

         $counter++;
      }
   }
   else
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "NUMERO"        => Array("width" => "60", "justification" => "left"),
                    "NOMBRE"        => Array("width" => "280", "justification" => "left"),
                    "UNIDAD"        => Array("width" => "45", "justification" => "left"),
                    "BODEGA"        => Array("width" => "160", "justification" => "left"),
                    "CANT."         => Array("width" => "50", "justification" => "right"),
                    "CANT./TRANS."   => Array("width" => "60", "justification" => "right"),
                    "CANT./DISPO."   => Array("width" => "60", "justification" => "right")
                    ));
      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
      {
         $data[$counter]["NUMERO"] = $sqlrow["num"];
         $data[$counter]["NOMBRE"]  = $sqlrow["title"];
         $data[$counter]["UNIDAD"]  = $sqlrow["unit"];
         $data[$counter]["BODEGA"] = $sqlrow["sth"];
         $data[$counter]["CANT."]   = $sqlrow["inv"];
         $data[$counter]["CANT./TRANS."] = $sqlrow["_TRANSSTOCK"];
         $data[$counter]["CANT./DISPO."] = $sqlrow["_DISPOSTOCK"];

         $counter++;
      }
   }


   

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK POR PRODUCTO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
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
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   

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

//----------------------------------------------------------------------------------
function doc_createStatsSellingShops($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsinvoicesshops";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if(!(int)$_SESSION[$_sesmodulename]["sql_reptype"])
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "EMPRESA"       => Array("width" => "170", "justification" => "left"),
                 "SUCURSAL"      => Array("width" => "150", "justification" => "left"),
                 "TIPO"          => Array("width" => "100", "justification" => "left"),
                 "TOTAL/NETO"    => Array("width" => "100", "justification" => "right"),
                 "TOTAL/IVA"     => Array("width" => "100", "justification" => "right"),
                 "TOTAL/BRUTO"   => Array("width" => "100", "justification" => "right")));

      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
      {
         $data[$counter]["EMPRESA"]       = $sqlrow["compname"];
         $data[$counter]["SUCURSAL"]      = $sqlrow["shopname"];
         $data[$counter]["TIPO"]          = $sqlrow["type"];
         $data[$counter]["TOTAL/NETO"]    = $sqlrow["invc_total_netto"];
         $data[$counter]["TOTAL/IVA"]     = $sqlrow["invc_total_taxes"];
         $data[$counter]["TOTAL/BRUTO"]   = $sqlrow["invc_total_brutto"];
         $counter++;
      }
      $pdf->ezTable($data,$type,$_SESSION["STATS"][$_sesmodulename]["HEAD"][$shopid]["shop_name"],$attr);
      $pdf->ezText(" ", 11);
   }
   else
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "TIPO"          => Array("width" => "80", "justification" => "left"),
                    "Nï¿½MERO"        => Array("width" => "80", "justification" => "left"),
                    "FECHA"         => Array("width" => "70", "justification" => "left"),
                    "CLIENTE"       => Array("width" => "120", "justification" => "left"),
                    "VENDEDOR"      => Array("width" => "100", "justification" => "left"),
                    "TOTAL/NETO"    => Array("width" => "90", "justification" => "right"),
                    "TOTAL/IVA"     => Array("width" => "90", "justification" => "right"),
                    "TOTAL/BRUTO"   => Array("width" => "90", "justification" => "right")));


      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["HEAD"]) AS $shopid)
      {
         $counter = 0;
         foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid] AS $sqlrow)
         {
            $data[$counter]["TIPO"]          = $sqlrow["val1"];
            $data[$counter]["Nï¿½MERO"]        = $sqlrow["val2"];
            $data[$counter]["FECHA"]         = $sqlrow["val3"];
            $data[$counter]["CLIENTE"]       = $sqlrow["vala"];
            $data[$counter]["VENDEDOR"]      = $sqlrow["valb"];
            $data[$counter]["TOTAL/NETO"]    = $sqlrow["val5"];
            $data[$counter]["TOTAL/IVA"]     = $sqlrow["val6"];
            $data[$counter]["TOTAL/BRUTO"]   = $sqlrow["val7"];

            $counter++;
         }
         $pdf->ezTable($data,$type,$_SESSION["STATS"][$_sesmodulename]["HEAD"][$shopid]["shop_name"],$attr);
         $pdf->ezText(" ", 11);
      }
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "VENTAS POR SUCURSAL";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PERIODO</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_date"];
   $HEADER["DATA"][$pcounter]["x3"] = "<b>TIPO</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["sql_type"];
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createPricelistData($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "pricelistdata";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   $items = $CON->select($sql);
   
   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Nï¿½MERO"           => Array("width" => "80", "justification" => "left"),
                 "ARTICULO"         => Array("width" => "200", "justification" => "left"),
                 "UNIDAD"           => Array("width" => "35", "justification" => "left"),
                 "STOCK"            => Array("width" => "30", "justification" => "center"),
                 "P/BASE\nNETO"      => Array("width" => "40", "justification" => "right"),
                 "P/BASE\nIVA"       => Array("width" => "40", "justification" => "right"),
                 "P/BASE\nBRUTO"     => Array("width" => "40", "justification" => "right"),
                 "P/DETALLE\nNETO"   => Array("width" => "45", "justification" => "right"),
                 "P/DETALLE\nIVA"    => Array("width" => "45", "justification" => "right"),
                 "P/DETALLE\nBRUTO"  => Array("width" => "45", "justification" => "right"),
                 "P/MAYOR\nNETO"     => Array("width" => "40", "justification" => "right"),
                 "P/MAYOR\nIVA"      => Array("width" => "40", "justification" => "right"),
                 "P/MAYOR\nBRUTO"    => Array("width" => "40", "justification" => "right")
                 ));


   $counter = 0;
   $plres = $_SESSION["_STATS"]["pricelistdata"]["plres"];
   foreach($items AS $sqlrow)
   {
      $sqlrow["item_type"] = "item";
      $unitdesc = getItemUnitDesc($CON, $sqlrow["id"], "item");

      $pl_item_sellprice_brutto  = 0.00;
      $pl_item_sellprice_netto   = 0.00;
      $pl_item_sellprice_taxes   = 0.00;
      $pl_item_sellprice_brutto2 = 0.00;
      $pl_item_sellprice_netto2  = 0.00;
      $pl_item_sellprice_taxes2  = 0.00;

      if(is_array($plres[$sqlrow["item_type"]][$sqlrow["id"]]))
      {
         $pldata = $plres[$sqlrow["item_type"]][$sqlrow["id"]];

         $pl_item_sellprice_brutto  = $pldata["item_sellprice_brutto"];
         $pl_item_sellprice_netto   = $pldata["item_sellprice_netto"];
         $pl_item_sellprice_taxes   = $pldata["item_sellprice_taxes"];

         $pl_item_sellprice_brutto2 = $pldata["item_sellprice_brutto2"];
         $pl_item_sellprice_netto2  = $pldata["item_sellprice_netto2"];
         $pl_item_sellprice_taxes2  = $pldata["item_sellprice_taxes2"];
      }

      $stock = getItemShopCurrentStock($CON, $_SESSION["_STATS"]["pricelistdata"]["shopid"], $items[$x]["id"], $items[$x]["item_type"]);

      $data[$counter]["Nï¿½MERO"]           = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO"]         = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]           = $unitdesc;
      $data[$counter]["STOCK"]            = printPrice($stock,2);
      $data[$counter]["P/BASE\nNETO"]      = printPrice($sqlrow["item_sellprice_netto"]);
      $data[$counter]["P/BASE\nIVA"]       = printPrice($sqlrow["item_sellprice_taxes"]);
      $data[$counter]["P/BASE\nBRUTO"]     = printPrice($sqlrow["item_sellprice_brutto"]);
      $data[$counter]["P/DETALLE\nNETO"]   = printPrice($pl_item_sellprice_netto);
      $data[$counter]["P/DETALLE\nIVA"]    = printPrice($pl_item_sellprice_taxes);
      $data[$counter]["P/DETALLE\nBRUTO"]  = printPrice($pl_item_sellprice_brutto);
      $data[$counter]["P/MAYOR\nNETO"]     = printPrice($pl_item_sellprice_netto2);
      $data[$counter]["P/MAYOR\nIVA"]      = printPrice($pl_item_sellprice_taxes2);
      $data[$counter]["P/MAYOR\nBRUTO"]    = printPrice($pl_item_sellprice_brutto2);

      $counter++;
   }
   $pdf->ezTable($data,$type,$_SESSION["STATS"][$_sesmodulename]["HEAD"][$shopid]["shop_name"],$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "LISTA DE PRECIOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;
   $HEADER["DATA"][$pcounter]["x1"] = "<b>LISTA</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["_STATS"]["pricelistdata"]["plname"];

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

function doc_createStatsEstadoResultado($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "estado_resultado";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FECHA"       => Array("width" => "66", "justification" => "left"),
                 "DIA"         => Array("width" => "66", "justification" => "left"),
                 "HABIL"       => Array("width" => "66", "justification" => "center"),
                 "CALCULO"     => Array("width" => "64", "justification" => "center"),
                 "$ VENTA"     => Array("width" => "64", "justification" => "right"),
                 "$ COSTO PMP" => Array("width" => "64", "justification" => "right"),
                 "$ N/C"       => Array("width" => "64", "justification" => "right"),
                 "$ N/D"       => Array("width" => "64", "justification" => "right"),
                 "$ GASTOS"    => Array("width" => "64", "justification" => "right"),
                 "$ MARGEN"    => Array("width" => "64", "justification" => "right"),
                 "% MARGEN"    => Array("width" => "64", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["FECHA"]       = $sqlrow["val1"];
      $data[$counter]["DIA"]         = $sqlrow["val2"];
      $data[$counter]["HABIL"]       = $sqlrow["val3"];
      $data[$counter]["CALCULO"]     = $sqlrow["val4"];
      $data[$counter]["$ VENTA"]     = $sqlrow["val5"];
      $data[$counter]["$ COSTO PMP"] = $sqlrow["val6"];
      $data[$counter]["$ N/C"]       = $sqlrow["val7"];
      $data[$counter]["$ N/D"]       = $sqlrow["val8"];
      $data[$counter]["$ GASTOS"]    = $sqlrow["val9"];
      $data[$counter]["$ MARGEN"]    = $sqlrow["val10"];
      $data[$counter]["% MARGEN"]    = $sqlrow["val11"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "720", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "119", "justification" => "left"),
                 "X2" => Array("width" => "119", "justification" => "left"),
                 "X3" => Array("width" => "119", "justification" => "left"),
                 "X4" => Array("width" => "119", "justification" => "left"),
                 "X5" => Array("width" => "119", "justification" => "left"),
                 "X6" => Array("width" => "117", "justification" => "left")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"] AS $sqlrow)
   {
      $data[$counter]["X1"] = $sqlrow["val1"];
      $data[$counter]["X2"] = $sqlrow["val2"];
      $data[$counter]["X3"] = $sqlrow["val3"];
      $data[$counter]["X4"] = $sqlrow["val4"];
      $data[$counter]["X5"] = $sqlrow["val5"];
      $data[$counter]["X6"] = $sqlrow["val6"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "ESTADO RESULTADO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>TIPO</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION[$_sesmodulename]["_HEADFILTER"]["TYPE"];
   $HEADER["DATA"][$pcounter]["x3"] = "<b>Aï¿½O</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION[$_sesmodulename]["_HEADFILTER"]["YEAR"];
   
   $pcounter++;
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PERIODO</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION[$_sesmodulename]["_HEADFILTER"]["TIME"];
   $HEADER["DATA"][$pcounter]["x3"] = " ";
   $HEADER["DATA"][$pcounter]["x4"] = " ";

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

//----------------------------------------------------------------------------------
function doc_createInvoicesSellNotes($CON, $orderid)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.cust_name, t3.company_short, t4.shop_name, 
                   t2.cust_notes,  t7.pay_title,
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
            LEFT OUTER JOIN user t9          ON t1.note_userid_seller   = t9.id
            LEFT OUTER JOIN user t10         ON t1.note_userid_cashing  = t10.id
            where
            t1.id = {$orderid}";
   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            where
            t1.id = {$orderheader["note_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];

   //----------------------------------------------------------------------------------
   $doctype    = "invoicessellnotes";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $posdata = getInvoiceSellNoteItems($CON, $orderid, $_REQUEST["setPosOrder"]);

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 540);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "70"),
                      "X2"  => Array("width" => "200"),
                      "X3"  => Array("width" => "70"),
                      "X4"  => Array("width" => "200")));
   $data[0]["X1"] = "<b>Nï¿½mero</b>";
   $data[0]["X2"] = $orderheader["note_number"];
   $data[0]["X3"] = "<b>Nï¿½mero Fact.</b>";
   $data[0]["X4"] = $orderheader["note_invcnumber"];

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $orderheader["company_short"];
   $data[1]["X3"] = "<b>Sucursal</b>";
   $data[1]["X4"] = $orderheader["shop_name"];

   $data[2]["X1"] = "<b>Cliente</b>";
   $data[2]["X2"] = $customer["cust_company"];
   $data[2]["X3"] = "<b>RUT</b>";
   $data[2]["X4"] = $customer["cust_rut"];

   $data[3]["X1"] = "<b>Direcciï¿½n</b>";
   $data[3]["X2"] = $customer["cust_street"];
   $data[3]["X3"] = "<b>Telï¿½fono</b>";
   $data[3]["X4"] = $customer["cust_phone"];

   $data[4]["X1"] = "<b>Regiï¿½n</b>";
   $data[4]["X2"] = $customer["name"];
   $data[4]["X3"] = "<b>Whatsapp</b>";
   $data[4]["X4"] = $customer["cust_fax"];

   $data[5]["X1"] = "<b>Comuna</b>";
   $data[5]["X2"] = $customer["nombre"];
   $data[5]["X3"] = "<b>Email</b>";
   $data[5]["X4"] = $customer["cust_email"];

   $data[6]["X1"] = "<b>Paï¿½s</b>";
   $data[6]["X2"] = $customer["country_name"];
   $data[6]["X3"] = " ";
   $data[6]["X4"] = " ";

   $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezText($headdata["note_desc"], 11);

   $pdf = doc_linedraw($pdf, 30, 540);
   $pdf->ezText(" ", 11);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 8,
                     "cols" =>   Array (
                     "NUM."          => Array("width" => "50", "justification" => "left"),
                     "ARTICULO"      => Array("width" => "295", "justification" => "left"),
                     "PROV.\nCOD."   => Array("width" => "50", "justification" => "left"),
                     "UNIDAD"        => Array("width" => "50", "justification" => "center"),
                     "CANT."         => Array("width" => "40", "justification" => "center"),
                     "PRECIO (NETO)" => Array("width" => "55", "justification" => "right")
                     )
                  );

   $x = 0;
   foreach($posdata AS $row)
   {
      $data[$x]["NUM."]           = $row["item_number_prod"];
      $data[$x]["ARTICULO"]       = $row["item_title"];
      $data[$x]["PROV.\nCOD."]    = $row["item_code"];
      $data[$x]["UNIDAD"]         = getItemUnitDesc($CON, $row["item_id"], $row["item_type"]);
      $data[$x]["CANT."]          = printPrice($row["item_amount"]);
      $data[$x]["PRECIO (NETO)"]  = printPrice($row["item_sellprice_netto"],4);
      $x++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   unset($data);
   $pcounter = 0;

   if($orderheader["note_type"] == 1)
      $typestr = "invoicebuynote1";
   else
      $typestr = "invoicebuynote2";

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $orderheader["note_company_id"], $typestr, $orderheader["note_docnumber"]);

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

//----------------------------------------------------------------------------------
function doc_createInvoicesSell($CON, $orderid)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

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
            t1.id = {$orderid}";
   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            where
            t1.id = {$orderheader["invc_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];

   //----------------------------------------------------------------------------------
   $doctype    = "invoicessell";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $posdata    = Array();
   $invcparts  = getInvoiceSellParts($CON, $orderid);
   for($x = 0; $x < count($invcparts) && $invcparts != false; $x++)
   {
      $partposdata = getInvoiceSellPartsItems($CON, $orderid, $invcparts[$x]["id"]);
      for($y = 0; $y < count($partposdata) && $partposdata != false; $y++)
      {
         $posdata[] = $partposdata[$y];
         if((int)$partposdata[$y]["item_sell_withotheritems"])
            $showRelItems = true;
      }
   }

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 540);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "70"),
                      "X2"  => Array("width" => "200"),
                      "X3"  => Array("width" => "70"),
                      "X4"  => Array("width" => "200")));
   $data[0]["X1"] = "<b>Nï¿½mero</b>";
   $data[0]["X2"] = $orderheader["invc_number"];
   $data[0]["X3"] = "<b>Nï¿½mero OC</b>";
   $data[0]["X4"] = $orderheader["invc_oc_number"];

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $orderheader["company_short"];
   $data[1]["X3"] = "<b>Sucursal</b>";
   $data[1]["X4"] = $orderheader["shop_name"];

   $data[2]["X1"] = "<b>Cliente</b>";
   $data[2]["X2"] = $customer["cust_company"];
   $data[2]["X3"] = "<b>RUT</b>";
   $data[2]["X4"] = $customer["cust_rut"];

   $data[3]["X1"] = "<b>Direcciï¿½n</b>";
   $data[3]["X2"] = $customer["cust_street"];
   $data[3]["X3"] = "<b>Telï¿½fono</b>";
   $data[3]["X4"] = $customer["cust_phone"];

   $data[4]["X1"] = "<b>Regiï¿½n</b>";
   $data[4]["X2"] = $customer["name"];
   $data[4]["X3"] = "<b>Whatsapp</b>";
   $data[4]["X4"] = $customer["cust_fax"];

   $data[5]["X1"] = "<b>Comuna</b>";
   $data[5]["X2"] = $customer["nombre"];
   $data[5]["X3"] = "<b>Email</b>";
   $data[5]["X4"] = $customer["cust_email"];

   $data[6]["X1"] = "<b>Paï¿½s</b>";
   $data[6]["X2"] = $customer["country_name"];
   $data[6]["X3"] = " ";
   $data[6]["X4"] = " ";

   $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezText($headdata["invc_desc"], 11);

   $pdf = doc_linedraw($pdf, 30, 540);
   $pdf->ezText(" ", 11);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 8,
                     "cols" =>   Array (
                     "NUM."          => Array("width" => "50", "justification" => "left"),
                     "ARTICULO"      => Array("width" => "295", "justification" => "left"),
                     "PROV.\nCOD."   => Array("width" => "50", "justification" => "left"),
                     "UNIDAD"        => Array("width" => "50", "justification" => "center"),
                     "CANT."         => Array("width" => "40", "justification" => "center"),
                     "PRECIO (NETO)" => Array("width" => "55", "justification" => "right")
                     )
                  );

   $x = 0;
   foreach($posdata AS $row)
   {
      $data[$x]["NUM."]           = $row["item_number_prod"];
      $data[$x]["ARTICULO"]       = $row["item_title"];
      $data[$x]["PROV.\nCOD."]    = $row["item_code"];
      $data[$x]["UNIDAD"]         = getItemUnitDesc($CON, $row["item_id"], $row["item_type"]);
      $data[$x]["CANT."]          = printPrice($row["item_amount"]);
      $data[$x]["PRECIO (NETO)"]  = printPrice($row["item_sellprice_netto"],4);
      $x++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   unset($data);
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $orderheader["invc_company_id"], "invoicebuy", $orderheader["invc_docnumber"]);

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

//----------------------------------------------------------------------------------
function doc_createOrderDelivery($CON, $orderid)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

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
            t1.id = {$orderid}";
   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];

   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            where
            t1.id = {$orderheader["dlv_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];

   //----------------------------------------------------------------------------------
   $doctype    = "shipments";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $posdata = getOrderDeliveryPos($CON, $orderid, $_REQUEST["setPosOrder"]);

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 540);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "70"),
                      "X2"  => Array("width" => "200"),
                      "X3"  => Array("width" => "70"),
                      "X4"  => Array("width" => "200")));
   $data[0]["X1"] = "<b>Nï¿½mero</b>";
   $data[0]["X2"] = $orderheader["dlv_num"];
   $data[0]["X3"] = "<b>Nï¿½mero OC</b>";
   $data[0]["X4"] = $orderheader["dlv_oc_number"];

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $orderheader["company_short"];
   $data[1]["X3"] = "<b>Sucursal</b>";
   $data[1]["X4"] = $orderheader["shop_name"];

   $data[2]["X1"] = "<b>Cliente</b>";
   $data[2]["X2"] = $customer["cust_company"];
   $data[2]["X3"] = "<b>RUT</b>";
   $data[2]["X4"] = $customer["cust_rut"];

   $data[3]["X1"] = "<b>Direcciï¿½n</b>";
   $data[3]["X2"] = $customer["cust_street"];
   $data[3]["X3"] = "<b>Telï¿½fono</b>";
   $data[3]["X4"] = $customer["cust_phone"];

   $data[4]["X1"] = "<b>Regiï¿½n</b>";
   $data[4]["X2"] = $customer["name"];
   $data[4]["X3"] = "<b>Whatsapp</b>";
   $data[4]["X4"] = $customer["cust_fax"];

   $data[5]["X1"] = "<b>Comuna</b>";
   $data[5]["X2"] = $customer["nombre"];
   $data[5]["X3"] = "<b>Email</b>";
   $data[5]["X4"] = $customer["cust_email"];

   $data[6]["X1"] = "<b>Paï¿½s</b>";
   $data[6]["X2"] = $customer["country_name"];
   $data[6]["X3"] = " ";
   $data[6]["X4"] = " ";

   $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezText($headdata["dlv_annotation"], 11);

   $pdf = doc_linedraw($pdf, 30, 540);
   $pdf->ezText(" ", 11);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 8,
                     "cols" =>   Array (
                     "NUM."          => Array("width" => "50", "justification" => "left"),
                     "ARTICULO"      => Array("width" => "255", "justification" => "left"),
                     "PROV.\nCOD."   => Array("width" => "50", "justification" => "left"),
                     "UNIDAD"        => Array("width" => "50", "justification" => "center"),
                     "PEND."         => Array("width" => "40", "justification" => "center"),
                     "CANT."         => Array("width" => "40", "justification" => "center"),
                     "PRECIO (NETO)" => Array("width" => "55", "justification" => "right")
                     )
                  );

   $x = 0;
   foreach($posdata AS $row)
   {
      $data[$x]["NUM."]           = $row["item_number_prod"];
      $data[$x]["ARTICULO"]       = $row["item_title"];
      $data[$x]["PROV.\nCOD."]    = $row["item_code"];
      $data[$x]["UNIDAD"]         = getItemUnitDesc($CON, $row["item_id"], $row["item_type"]);
      $data[$x]["PEND."]          = printPrice($row["item_amount"]);
      $data[$x]["CANT."]          = printPrice($row["item_amount_shipped"]);
      $data[$x]["PRECIO (NETO)"]  = printPrice($row["item_sellprice_netto"],4);
      $x++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   unset($data);
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $orderheader["dlv_company_id"], "shipment", $orderheader["dlv_docnum"]);

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

function doc_createShipmentApproveList($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "ship_app_list";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list_ship_app");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NOMBRE"     => Array("width" => "200", "justification" => "left"),
                 "COD. CONT." => Array("width" => "50", "justification" => "left"),
                 "COD. PROV"  => Array("width" => "50", "justification" => "left"),
                 "UNIDAD"     => Array("width" => "60", "justification" => "left"),
                 "VENTA"      => Array("width" => "35", "justification" => "center"),
                 "STOCK"      => Array("width" => "35", "justification" => "center"),
                 "O.C."       => Array("width" => "35", "justification" => "center"),
                 "CLIENTES"   => Array("width" => "245", "justification" => "left")
                 ));

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $catid)
   {
      $counter = 0;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$catid] AS $sqlrow)
      {
         $stck = "";
         $occk = "";
         if(getPrice($sqlrow["item_stock"],2) > 0.00)
            $stck = $sqlrow["item_stock"];
         if(getPrice($sqlrow["item_oc"],2) > 0.00)
            $occk = $sqlrow["item_oc"];
         $data[$counter]["NOMBRE"]     = $sqlrow["item_title"];
         $data[$counter]["COD. CONT."] = $sqlrow["item_number_prod"];
         $data[$counter]["COD. PROV"]  = $sqlrow["item_code"];
         $data[$counter]["UNIDAD"]     = $sqlrow["unit_name"];
         $data[$counter]["VENTA"]      = $sqlrow["gesamount"];
         $data[$counter]["STOCK"]      = $stck ;
         $data[$counter]["O.C."]       = $occk;
         $data[$counter]["CLIENTES"]   = $sqlrow["custs"];
         $counter++;
      }

      $pdf->ezTable($data,$type,"<b>".$_SESSION["STATS"][$_sesmodulename]["DATA1"][$catid]["cat_title"]."</b>",$attr);
      $pdf->ezText(" ", 11);
      unset($data);
      $data = Array();
   }



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
   $HEADER["DATA"][$pcounter]["x2"] = "SOLICITUD";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>EMPRESA</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["comp"];

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["supp"];
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["shop"];

   $pdf = printPDFFooter($CON, $pdf, NULL, "list_ship_app", $HEADER);

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

function doc_createStatsItemProductsInvcComp($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_buying_products_invclist";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if((int)$_SESSION[$_sesmodulename]["sql_prccomp"])
      $prccol = "PRECIO/U\nLISTA";
   else
      $prccol = "PRECIO/U\nOC";

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"              => Array("width" => "40", "justification" => "left"),
                 "ARTï¿½CULO"            => Array("width" => "170", "justification" => "left"),
                 "UNIDAD"              => Array("width" => "35", "justification" => "left"),
                 "COD/PROV"            => Array("width" => "60", "justification" => "left"),
                 "CANTIDAD\nFACTURA"   => Array("width" => "45", "justification" => "right"),
                 "CANTIDAD\nOC"        => Array("width" => "45", "justification" => "right"),
                 "CANTIDAD\nGUIA"      => Array("width" => "45", "justification" => "right"),
                 "PRECIO/U\nFACTURA"   => Array("width" => "45", "justification" => "right"),
                 $prccol               => Array("width" => "45", "justification" => "right"),
                 "DIF\n$"              => Array("width" => "35", "justification" => "right"),
                 "DIF\n%"              => Array("width" => "35", "justification" => "right"),
                 "OBSERVACIONES"       => Array("width" => "120", "justification" => "left")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTï¿½CULO"]            = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unit_name"];
      $data[$counter]["COD/PROV"]            = $sqlrow["item_code"];
      $data[$counter]["CANTIDAD\nFACTURA"]   = $sqlrow["item_amount"];
      $data[$counter]["CANTIDAD\nOC"]        = $sqlrow["amt_oc"];
      $data[$counter]["CANTIDAD\nGUIA"]      = $sqlrow["amt_shp"];
      $data[$counter]["PRECIO/U\nFACTURA"]   = $sqlrow["prc_unit"];
      $data[$counter][$prccol]               = $sqlrow["prc_oc"];
      $data[$counter]["DIF\n$"]              = $sqlrow["diffunit"];
      $data[$counter]["DIF\n%"]              = $sqlrow["diffperc"];
      $data[$counter]["OBSERVACIONES"]       = $sqlrow["comment"];

      $counter++;
   }
   $data[$counter]["NUMERO"]              = " ";
   $data[$counter]["ARTï¿½CULO"]            = $_SESSION["STATS"][$_sesmodulename]["FOOT"];
   $data[$counter]["UNIDAD"]              = " ";
   $data[$counter]["COD/PROV"]            = " ";
   $data[$counter]["CANTIDAD\nFACTURA"]   = " ";
   $data[$counter]["CANTIDAD\nOC"]        = " ";
   $data[$counter]["CANTIDAD\nGUIA"]      = " ";
   $data[$counter]["PRECIO/U\nFACTURA"]   = " ";
   $data[$counter][$prccol]               = " ";
   $data[$counter]["DIF\n$"]              = " ";
   $data[$counter]["DIF\n%"]              = " ";
   $data[$counter]["OBSERVACIONES"]       = " ";

   $counter++;

   $pdf->ezTable($data,$type,$_SESSION["STATS"][$_sesmodulename]["HEAD"],$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPARA FACTURAS / LISTA DE PRECIOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>Nï¿½ FACTURA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION[$_sesmodulename]["sql_invcnum"];

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["supp_company"];
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

//----------------------------------------------------------------------------------
function doc_createStatsBuyNoteDiscounts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buy_discounts";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "NUMERO"           => Array("width" => "40", "justification" => "left"),
              "ARTICULO"         => Array("width" => "240", "justification" => "left"),
              "COD/PROV"         => Array("width" => "50", "justification" => "left"),
              "UNIDAD"           => Array("width" => "40", "justification" => "center"),
              "CANTIDAD"         => Array("width" => "50", "justification" => "right"),
              "P/FACTURA BASICO" => Array("width" => "50", "justification" => "right"),
              "P/FACTURA FINAL"  => Array("width" => "50", "justification" => "right"),
              "NC"               => Array("width" => "60", "justification" => "left"),
              "DESC $"           => Array("width" => "40", "justification" => "right"),
              "DESC %"           => Array("width" => "40", "justification" => "right"),
              "P/FINAL"          => Array("width" => "50", "justification" => "right")
              ));


   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $idx1)
   {
      unset($data);
      $data = Array();

      $pdf->ezText(" <b>{$_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["val1"]}</b>", 10);
      $pdf->ezText(" ", 6);

      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $sqlrow)
      {
         $data[$counter]["NUMERO"]           = $sqlrow["val1"];
         $data[$counter]["ARTICULO"]         = $sqlrow["val2"];
         $data[$counter]["COD/PROV"]         = $sqlrow["val3"];
         $data[$counter]["UNIDAD"]           = $sqlrow["val4"];
         $data[$counter]["CANTIDAD"]         = $sqlrow["val5"];
         $data[$counter]["P/FACTURA BASICO"] = $sqlrow["val6"];
         $data[$counter]["P/FACTURA FINAL"]  = $sqlrow["val7"];
         $data[$counter]["NC"]               = $sqlrow["val8"];
         $data[$counter]["DESC $"]           = $sqlrow["val9"];
         $data[$counter]["DESC %"]           = $sqlrow["val10"];
         $data[$counter]["P/FINAL"]          = $sqlrow["val11"];

         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "PRODUCTOS / FACTURAS CON DESCUENTO FINANCIERO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createPriceCompareSupplier($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "compare_supplier";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "X1" => Array("width" => "170", "justification" => "center"),
              "X2" => Array("width" => "180", "justification" => "center"),
              "X3" => Array("width" => "180", "justification" => "center"),
              "X4" => Array("width" => "180", "justification" => "center")
              ));
   $data[0]["X1"] = " ";
   $data[0]["X2"] = "<b>PROVEEDOR #1</b>";
   $data[0]["X3"] = "<b>PROVEEDOR #2</b>";
   $data[0]["X4"] = "<b>PROVEEDOR #3</b>";

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 2);

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 4,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "NUMERO"      => Array("width" => "25", "justification" => "left"),
              "ARTICULO"    => Array("width" => "75", "justification" => "left"),
              "COD/PROV"    => Array("width" => "25", "justification" => "left"),
              "UNIDAD"      => Array("width" => "25", "justification" => "left"),
              "NOMBRE"      => Array("width" => "60", "justification" => "left"),
              "F.D.PAGO"    => Array("width" => "40", "justification" => "left"),
              "P/BASICO"    => Array("width" => "25", "justification" => "right"),
              "P/FINAL"     => Array("width" => "25", "justification" => "right"),
              "P/ULT/COMPR"  => Array("width" => "20", "justification" => "right"),
              "F/ULT/COMPR"  => Array("width" => "20", "justification" => "right"),
              "NOMBRE "     => Array("width" => "60", "justification" => "left"),
              "F.D.PAGO "   => Array("width" => "40", "justification" => "left"),
              "P/BASICO "   => Array("width" => "25", "justification" => "right"),
              "P/FINAL "    => Array("width" => "25", "justification" => "right"),
              "P/ULT/COMPR "  => Array("width" => "20", "justification" => "right"),
              "F/ULT/COMPR "  => Array("width" => "20", "justification" => "right"),
              "NOMBRE  "    => Array("width" => "50", "justification" => "left"),
              "F.D.PAGO  "  => Array("width" => "40", "justification" => "left"),
              "P/BASICO  "  => Array("width" => "25", "justification" => "right"),
              "P/FINAL  "   => Array("width" => "25", "justification" => "right"),
              "P/ULT/COMPR  "  => Array("width" => "20", "justification" => "right"),
              "F/ULT/COMPR  "  => Array("width" => "20", "justification" => "right")
              ));

   unset($data);
   $data = Array();
   $counter = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $idx1)
   {
      $sqlrow = $_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1];

      $data[$counter]["NUMERO"]     = $sqlrow["val1"];
      $data[$counter]["ARTICULO"]   = $sqlrow["val2"];
      $data[$counter]["COD/PROV"]   = $sqlrow["val3"];
      $data[$counter]["UNIDAD"]     = $sqlrow["val4"];

      $delim = "";
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $row)
      {
         $data[$counter]["NOMBRE{$delim}"]     = $row["val1"];
         $data[$counter]["F.D.PAGO{$delim}"]   = $row["val2"];
         $data[$counter]["P/BASICO{$delim}"]   = $row["val3"];
         $data[$counter]["P/FINAL{$delim}"]    = $row["val4"];
         $data[$counter]["P/ULT/COMPR{$delim}"]    = $row["val5"];
         $data[$counter]["F/ULT/COMPR{$delim}"]    = $row["val6"];
         $delim .= " ";
      }

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPARAR PROVEEDORES";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsOrderItems($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "order_items";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "NOTA DE VENTA" => Array("width" => "100", "justification" => "left"),
              "FECHA"         => Array("width" => "60", "justification" => "left"),
              "CLIENTE"       => Array("width" => "490", "justification" => "left"),
              "CANTIDAD"      => Array("width" => "60", "justification" => "center")));


   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $idx1)
   {
      unset($data);
      $data = Array();

      $pdf->ezText(" <b>{$_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["title"]}</b>", 10);
      $pdf->ezText(" ", 6);

      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $sqlrow)
      {
         $data[$counter]["NOTA DE VENTA"] = $sqlrow["req_number"];
         $data[$counter]["FECHA"]         = $sqlrow["req_crtdat"];
         $data[$counter]["CLIENTE"]       = $sqlrow["cust_name"];
         $data[$counter]["CANTIDAD"]      = $sqlrow["item_amount"];

         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "NOTA DE VENTA POR PRODUCTOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["cat_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createInvoiceBuyNotePDF($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $doctype    = "invoice_buynote";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $headdata = $_SESSION["STATS"][$_sesmodulename]["HEAD"];
   $posdata  = $_SESSION["STATS"][$_sesmodulename]["ITEMS"];

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 540);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "100"),
                      "X2"  => Array("width" => "180"),
                      "X3"  => Array("width" => "90"),
                      "X4"  => Array("width" => "170")));
   $data[0]["X1"] = "<b>Nï¿½mero</b>";
   $data[0]["X2"] = $headdata["note_number"];
   $data[0]["X3"] = "<b>Tipo</b>";
   $data[0]["X4"] = $headdata["type"];

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $headdata["company_short"];
   $data[1]["X3"] = "<b>Sucursal</b>";
   $data[1]["X4"] = $headdata["shop_name"];

   $data[2]["X1"] = "<b>Proveedor</b>";
   $data[2]["X2"] = $headdata["supp_company"];
   $data[2]["X3"] = "<b>RUT</b>";
   $data[2]["X4"] = $headdata["supp_rut"];

   $data[3]["X1"] = "<b>Direcciï¿½n</b>";
   $data[3]["X2"] = $headdata["supp_street"];
   $data[3]["X3"] = "<b>Telï¿½fono</b>";
   $data[3]["X4"] = $headdata["supp_phone"];

   $data[4]["X1"] = "<b>Regiï¿½n</b>";
   $data[4]["X2"] = $headdata["name"];
   $data[4]["X3"] = "<b>Fax</b>";
   $data[4]["X4"] = $headdata["supp_fax"];

   $data[5]["X1"] = "<b>Comuna</b>";
   $data[5]["X2"] = $headdata["nombre"];
   $data[5]["X3"] = "<b>Email</b>";
   $data[5]["X4"] = $headdata["supp_email"];

   $data[6]["X1"] = "<b>Paï¿½s</b>";
   $data[6]["X2"] = $headdata["country_name"];
   $data[6]["X3"] = " ";
   $data[6]["X4"] = " ";

   $data[7]["X1"] = "<b>Fecha</b>";
   $data[7]["X2"] = $headdata["note_date"];
   $data[7]["X3"] = "<b>Fecha recepciï¿½n</b>";
   $data[7]["X4"] = $headdata["note_receipt_date"];

   $data[8]["X1"] = "<b>Fecha vencimiento</b>";
   $data[8]["X2"] = $headdata["note_estpay_date"];
   $data[8]["X3"] = "<b>IVA</b>";
   $data[8]["X4"] = $headdata["note_taxes"];

   $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezText($headdata["note_desc"], 11);

   $pdf = doc_linedraw($pdf, 30, 540);
   $pdf->ezText(" ", 11);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------70
   unset($data);
   $pdf->setColor(0,0,0);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95), "textCol" => array(0,0,0),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 8,
                     "cols" =>   Array (
                     "NUM."           => Array("width" => "50", "justification" => "left"),
                     "ARTICULO"       => Array("width" => "180", "justification" => "left"),
                     "COD.\nPROV."    => Array("width" => "45", "justification" => "center"),
                     "UNIDAD"         => Array("width" => "45", "justification" => "center"),
                     "CANT."          => Array("width" => "40", "justification" => "center"),
                     "PRECIO\n(NETO)" => Array("width" => "50", "justification" => "right"),
                     "DESC."          => Array("width" => "45", "justification" => "right"),
                     "IVA %"          => Array("width" => "35", "justification" => "right"),
                     "VALOR\nTOTAL"   => Array("width" => "50", "justification" => "right"),
                     )
                  );
   $x = 0;
   foreach($posdata AS $row)
   {
      $data[$x]["NUM."]           = $row["val9"];
      $data[$x]["ARTICULO"]       = $row["val1"];
      $data[$x]["COD.\nPROV."]    = $row["val4"];
      $data[$x]["UNIDAD"]         = $row["val2"];
      $data[$x]["CANT."]          = $row["val3"];
      $data[$x]["PRECIO\n(NETO)"] = $row["val5"];
      $data[$x]["DESC."]          = $row["val6"];
      $data[$x]["IVA %"]          = $row["val7"];
      $data[$x]["VALOR\nTOTAL"]   = $row["val8"];
      $x++;
   }
   $pdf->setColor(0,0,0);
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   unset($data);
   $pcounter = 0;

   $attr = Array  (  "showHeadings" => 1, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95), "textCol" => array(0,0,0),
                     "xpos" => "left", "showLines" => 0, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                     "cols" =>   Array (
                     " "         => Array("width" => "458", "justification" => "right"),
                     "  "        => Array("width" => "70", "justification" => "right"),
                     )
                  );

   $x = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["TOTAL"] AS $row)
   {
      $data[$x][" "]         = $row["val1"];
      $data[$x]["  "]        = $row["val2"];
      $x++;
   }


   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   //----------------------------------------------------------------------------------
   $notetype = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["note_type"];
   $pdf = printPDFFooter($CON, $pdf, $headdata["note_company_id"], "invoicebuynote{$notetype}", $headdata["note_docnumber"]);

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

//----------------------------------------------------------------------------------
function doc_createInvoiceBuyPDF($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $doctype    = "invoice_buy";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $headdata = $_SESSION["STATS"][$_sesmodulename]["HEAD"];
   $posdata1 = $_SESSION["STATS"][$_sesmodulename]["ITEMS1"];
   $posdata2 = $_SESSION["STATS"][$_sesmodulename]["ITEMS2"];

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 540);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "100"),
                      "X2"  => Array("width" => "180"),
                      "X3"  => Array("width" => "90"),
                      "X4"  => Array("width" => "170")));
   $data[0]["X1"] = "<b>Nï¿½mero</b>";
   $data[0]["X2"] = $headdata["invc_number"];
   $data[0]["X3"] = "<b>Tipo</b>";
   $data[0]["X4"] = $headdata["type"];

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $headdata["company_short"];
   $data[1]["X3"] = "<b>Sucursal</b>";
   $data[1]["X4"] = $headdata["shop_name"];

   $data[2]["X1"] = "<b>Proveedor</b>";
   $data[2]["X2"] = $headdata["supp_company"];
   $data[2]["X3"] = "<b>RUT</b>";
   $data[2]["X4"] = $headdata["supp_rut"];

   $data[3]["X1"] = "<b>Direcciï¿½n</b>";
   $data[3]["X2"] = $headdata["supp_street"];
   $data[3]["X3"] = "<b>Telï¿½fono</b>";
   $data[3]["X4"] = $headdata["supp_phone"];

   $data[4]["X1"] = "<b>Regiï¿½n</b>";
   $data[4]["X2"] = $headdata["name"];
   $data[4]["X3"] = "<b>Fax</b>";
   $data[4]["X4"] = $headdata["supp_fax"];

   $data[5]["X1"] = "<b>Comuna</b>";
   $data[5]["X2"] = $headdata["nombre"];
   $data[5]["X3"] = "<b>Email</b>";
   $data[5]["X4"] = $headdata["supp_email"];

   $data[6]["X1"] = "<b>Paï¿½s</b>";
   $data[6]["X2"] = $headdata["country_name"];
   $data[6]["X3"] = "<b>Pagado</b>";
   $data[6]["X4"] = $headdata["payed"];

   $data[7]["X1"] = "<b>Forma de pago</b>";
   $data[7]["X2"] = $headdata["pay_title"];
   $data[7]["X3"] = "<b>Fecha</b>";
   $data[7]["X4"] = $headdata["invc_date"];

   $data[8]["X1"] = "<b>Fecha recepciï¿½n</b>";
   $data[8]["X2"] = $headdata["invc_receipt_date"];
   $data[8]["X3"] = "<b>Fecha contable</b>";
   $data[8]["X4"] = $headdata["invc_contab_date"];

   $data[9]["X1"] = "<b>Fecha vencimiento</b>";
   $data[9]["X2"] = $headdata["invc_estpay_date"];
   $data[9]["X3"] = "<b>IVA</b>";
   $data[9]["X4"] = $headdata["invc_taxes"];

   $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezText("\n ".$headdata["invc_desc"], 11);

   $pdf = doc_linedraw($pdf, 30, 540);
   $pdf->ezText(" ", 11);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf->setColor(0,0,0);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95), "textCol" => array(0,0,0),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 8,
                     "cols" =>   Array (
                     "NUM."           => Array("width" => "50", "justification" => "left"),
                     "ARTICULO"       => Array("width" => "180", "justification" => "left"),
                     "COD.\nPROV."    => Array("width" => "45", "justification" => "center"),
                     "UNIDAD"         => Array("width" => "45", "justification" => "center"),
                     "CANT."          => Array("width" => "40", "justification" => "center"),
                     "PRECIO\n(NETO)" => Array("width" => "50", "justification" => "right"),
                     "DESC."          => Array("width" => "45", "justification" => "right"),
                     "IVA %"          => Array("width" => "35", "justification" => "right"),
                     "VALOR\nTOTAL"   => Array("width" => "50", "justification" => "right"),
                     )
                  );

   if($_SESSION["STATS"][$_sesmodulename]["ITEMSHEAD"] != "")
      $pdf->ezText($_SESSION["STATS"][$_sesmodulename]["ITEMSHEAD"], 12);
   foreach(array_keys($posdata1) AS $pos1)
   {
      $pdf->ezText("<b>".$posdata1[$pos1]["val1"]."</b>", 12);
      $pdf->ezText(" ", 3);
      $x = 0;
      foreach($posdata2[$pos1] AS $row)
      {
         $data[$x]["NUM."]           = $row["val5"];
         $data[$x]["ARTICULO"]       = $row["val1"];
         $data[$x]["COD.\nPROV."]    = $row["val2"];
         $data[$x]["UNIDAD"]         = $row["val3"];
         $data[$x]["CANT."]          = $row["val4"];
         $data[$x]["PRECIO\n(NETO)"] = $row["val6"];
         $data[$x]["DESC."]          = $row["val7"];
         $data[$x]["IVA %"]          = $row["val8"];
         $data[$x]["VALOR\nTOTAL"]   = $row["val9"];
         $x++;
      }

      $pdf->setColor(0,0,0);
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 12);
   }
   unset($data);
   $pcounter = 0;

   $attr = Array  (  "showHeadings" => 1, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95), "textCol" => array(0,0,0),
                     "xpos" => "left", "showLines" => 0, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                     "cols" =>   Array (
                     "SUB TOTAL" => Array("width" => "63", "justification" => "center"),
                     "DESC. 1"   => Array("width" => "60", "justification" => "center"),
                     "DESC. 2"   => Array("width" => "60", "justification" => "center"),
                     "DESC. 3"   => Array("width" => "60", "justification" => "center"),
                     "DESC. 4"   => Array("width" => "60", "justification" => "center"),
                     "DESC. 5"   => Array("width" => "60", "justification" => "right"),
                     " "         => Array("width" => "95", "justification" => "right"),
                     "  "        => Array("width" => "70", "justification" => "right"),
                     )
                  );

   $x = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["TOTAL"] AS $row)
   {
      $data[$x]["SUB TOTAL"] = $row["val1"];
      $data[$x]["DESC. 1"]   = $row["val2"];
      $data[$x]["DESC. 2"]   = $row["val3"];
      $data[$x]["DESC. 3"]   = $row["val4"];
      $data[$x]["DESC. 4"]   = $row["val5"];
      $data[$x]["DESC. 5"]   = $row["val6"];
      $data[$x][" "]         = $row["val7"];
      $data[$x]["  "]        = $row["val8"];
      $x++;
   }


   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $headdata["invc_company_id"], "invoicebuy", $headdata["invc_docnumber"]);

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

//----------------------------------------------------------------------------------
function doc_createShipmentPDF($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $doctype    = "shipments";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $headdata = $_SESSION["STATS"][$_sesmodulename]["HEAD"];
   $posdata  = $_SESSION["STATS"][$_sesmodulename]["ITEMS"];

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 540);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "70"),
                      "X2"  => Array("width" => "200"),
                      "X3"  => Array("width" => "70"),
                      "X4"  => Array("width" => "200")));
   $data[0]["X1"] = "<b>Nï¿½mero</b>";
   $data[0]["X2"] = $headdata["shp_num"];
   $data[0]["X3"] = "<b>Nï¿½mero OC</b>";
   $data[0]["X4"] = $headdata["sord_number"];

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $headdata["company_short"];
   $data[1]["X3"] = "<b>Sucursal</b>";
   $data[1]["X4"] = $headdata["shop_name"];

   $data[2]["X1"] = "<b>Proveedor</b>";
   $data[2]["X2"] = $headdata["supp_company"];
   $data[2]["X3"] = "<b>RUT</b>";
   $data[2]["X4"] = $headdata["supp_rut"];

   $data[3]["X1"] = "<b>Direcciï¿½n</b>";
   $data[3]["X2"] = $headdata["supp_street"];
   $data[3]["X3"] = "<b>Telï¿½fono</b>";
   $data[3]["X4"] = $headdata["supp_phone"];

   $data[4]["X1"] = "<b>Regiï¿½n</b>";
   $data[4]["X2"] = $headdata["name"];
   $data[4]["X3"] = "<b>Fax</b>";
   $data[4]["X4"] = $headdata["supp_fax"];

   $data[5]["X1"] = "<b>Comuna</b>";
   $data[5]["X2"] = $headdata["nombre"];
   $data[5]["X3"] = "<b>Email</b>";
   $data[5]["X4"] = $headdata["supp_email"];

   $data[6]["X1"] = "<b>Paï¿½s</b>";
   $data[6]["X2"] = $headdata["country_name"];
   $data[6]["X3"] = " ";
   $data[6]["X4"] = " ";

   $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezText($headdata["shp_annotation"], 11);

   $pdf = doc_linedraw($pdf, 30, 540);
   $pdf->ezText(" ", 11);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 8,
                     "cols" =>   Array (
                     "NUM."          => Array("width" => "50", "justification" => "left"),
                     "ARTICULO"      => Array("width" => "195", "justification" => "left"),
                     "PROV.\nCOD."   => Array("width" => "50", "justification" => "left"),
                     "UNIDAD"        => Array("width" => "50", "justification" => "center"),
                     "CANT."         => Array("width" => "40", "justification" => "center"),
                     "PRECIO (NETO)" => Array("width" => "55", "justification" => "right"),
                     "DESC."         => Array("width" => "45", "justification" => "right"),
                     "VALOR TOTAL"   => Array("width" => "55", "justification" => "right")
                     )
                  );

   $x = 0;
   foreach($posdata AS $row)
   {
      $data[$x]["NUM."]           = $row["val3"];
      $data[$x]["ARTICULO"]       = $row["val1"];
      $data[$x]["PROV.\nCOD."]    = $row["val9"];
      $data[$x]["UNIDAD"]         = $row["val2"];
      $data[$x]["CANT."]          = $row["val4"];
      $data[$x]["PRECIO (NETO)"]  = $row["val6"];
      $data[$x]["DESC."]          = $row["val7"];
      $data[$x]["VALOR TOTAL"]    = $row["val8"];
      $x++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   unset($data);
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $headdata["shp_company_id"], "shipment", $headdata["shp_supplier_docnum"]);

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

//----------------------------------------------------------------------------------
function doc_createStatsRebates($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $doctype    = "rebates";
   $currtme    = time();
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["STATS"][$_sesmodulename2]["id"]}.{$_SESSION["STATS"][$_sesmodulename2]["uid"]}.{$doctype}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["STATS"][$_sesmodulename2]["id"]}.{$_SESSION["STATS"][$_sesmodulename2]["uid"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "listx");

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 1, "fontSize" => 8,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "<b>NUMERO</b>" => Array("width" => "140", "justification" => "left"),
                 "<b>FECHA</b>" => Array("width" => "270", "justification" => "left"),
                 "<b>TOTAL/NETO</b>" => Array("width" => "110", "justification" => "left")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"]["rebatesdet"]["DATA"] AS $row)
   {
      $data[$counter]["<b>NUMERO</b>"] = $row["val1"];
      $data[$counter]["<b>FECHA</b>"] = $row["val2"];
      $data[$counter]["<b>TOTAL/NETO</b>"] = $row["val3"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);
   $pdf->ezText(" ", 11);
   unset($data);
   $counter = 0;

   //----------------------------------------------------------------------------------
   $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8, 
                   "showLines" => 0, "rowGap" => 1, "colGap" => 0,
                   "cols" => Array (
                   "x1" => Array("width" => "60", "justification" => "left"),
                   "x2" => Array("width" => "200", "justification" => "left"),
                   "x3" => Array("width" => "60", "justification" => "left"),
                   "x4" => Array("width" => "200", "justification" => "left")));

   //----------------------------------------------------------------------------------
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME</b>";
   $HEADER["DATA"][$pcounter]["x2"] = "REBATES";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   $pdf = printPDFFooter($CON, $pdf, NULL, "inventory", $HEADER);

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

//----------------------------------------------------------------------------------
function doc_createStatsOCCompare($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_oc_cumplc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "NUMERO"                 => Array("width" => "50", "justification" => "left"),
              "ARTICULO"               => Array("width" => "200", "justification" => "left"),
              "UNIDAD"                 => Array("width" => "40", "justification" => "left"),
              "COD/PROV"               => Array("width" => "50", "justification" => "left"),
              "OC/CANT"                => Array("width" => "50", "justification" => "right"),
              "OC/PRECIO/U"            => Array("width" => "55", "justification" => "right"),
              "OC/PRECIO/F"            => Array("width" => "55", "justification" => "right"),
              "FA/CANT"                => Array("width" => "50", "justification" => "right"),
              "FA/PRECIO/U"            => Array("width" => "55", "justification" => "right"),
              "FA/PRECIO/F"            => Array("width" => "55", "justification" => "right"),
              "ESTADO"                 => Array("width" => "55", "justification" => "center")));

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["INCLUDEDATA"]) AS $sordid)
   {
      unset($data);
      $data = Array();
      $xhead = $_SESSION["STATS"][$_sesmodulename]["INCLUDEDATA"][$sordid];

      $pdf->ezText(" <b>{$xhead["SUPP"]}, {$xhead["NUMB"]}, {$xhead["DATE"]}</b>", 10);
      $pdf->ezText(" ", 6);
   
      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid] AS $sqlrow)
      {
         $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
         $data[$counter]["ARTICULO"]            = $sqlrow["item_title"];
         $data[$counter]["UNIDAD"]              = $sqlrow["unit_name"];
         $data[$counter]["COD/PROV"]            = $sqlrow["_item_code"];
         $data[$counter]["OC/CANT"]             = $sqlrow["order_amt"];
         $data[$counter]["OC/PRECIO/U"]         = $sqlrow["order_price"];
         $data[$counter]["OC/PRECIO/F"]         = $sqlrow["order_price_total"];
         $data[$counter]["FA/CANT"]             = $sqlrow["invc_amt"];
         $data[$counter]["FA/PRECIO/U"]         = $sqlrow["invc_price"];
         $data[$counter]["FA/PRECIO/F"]         = $sqlrow["invc_price_total"];
         $data[$counter]["ESTADO"]              = $sqlrow["state"];

         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPARACION OC VS FACTURA";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   

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

//----------------------------------------------------------------------------------
function doc_createStatsProductsMovis($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prod_movis";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "FECHA"                  => Array("width" => "50", "justification" => "left"),
              "TIPO"                   => Array("width" => "70", "justification" => "left"),
              "NUMERO"                 => Array("width" => "50", "justification" => "left"),
              "ENTRADA"                => Array("width" => "50", "justification" => "left"),
              "SALIDA"                 => Array("width" => "40", "justification" => "left"),
              "PRECIO/V"               => Array("width" => "45", "justification" => "left"),
              "PRECIO/C"               => Array("width" => "45", "justification" => "left"),
              "DESCUENTOS"             => Array("width" => "50", "justification" => "left"),
              "DESC/%"                 => Array("width" => "50", "justification" => "left"),
              "TOTAL"                  => Array("width" => "50", "justification" => "left"),
              "RUT"                    => Array("width" => "60", "justification" => "left"),
              "CLIENTE/PROVEEDOR"      => Array("width" => "150", "justification" => "left")));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["FECHA"]               = $sqlrow["DATE"];
      $data[$counter]["TIPO"]                = $sqlrow["TYPE"];
      $data[$counter]["NUMERO"]              = $sqlrow["NUMBR"];
      $data[$counter]["ENTRADA"]             = $sqlrow["amtpos"];
      $data[$counter]["SALIDA"]              = $sqlrow["negpos"];
      $data[$counter]["PRECIO/V"]            = $sqlrow["amtprc"];
      $data[$counter]["PRECIO/C"]            = $sqlrow["negprc"];
      $data[$counter]["DESCUENTOS"]          = $sqlrow["dscges"];
      $data[$counter]["DESC/%"]              = $sqlrow["dsc"];
      $data[$counter]["TOTAL"]               = $sqlrow["netto"];
      $data[$counter]["RUT"]                 = $sqlrow["RUT"];
      $data[$counter]["CLIENTE/PROVEEDOR"]   = $sqlrow["NAME"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);
   
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
   $HEADER["DATA"][$pcounter]["x2"] = "MOVIMIENTOS PRODUCTO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PRODUCTO</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["HEADER"][$_sesmodulename]["PROD"];
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PERIODO</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["HEADER"][$_sesmodulename]["FROM"]." - ".$_SESSION["HEADER"][$_sesmodulename]["TO"];

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

//----------------------------------------------------------------------------------
function doc_createStatsItemTransitState($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_transit_cumpl";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
              "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
              "rowGap" => 2, "colGap" => 3, "cols" => Array(
              "NUMERO"                 => Array("width" => "60", "justification" => "left"),
              "ARTICULO"               => Array("width" => "270", "justification" => "left"),
              "UNIDAD"                 => Array("width" => "60", "justification" => "left"),
              "SOLICITADO"             => Array("width" => "80", "justification" => "right"),
              "RECIBIDO"               => Array("width" => "80", "justification" => "right"),
              "PENDIENTE"              => Array("width" => "80", "justification" => "right"),
              "ESTADO"                 => Array("width" => "80", "justification" => "center")));

   $_RES = $_SESSION["STATS"][$_sesmodulename]["_RES"];
   $_NUM = $_SESSION["STATS"][$_sesmodulename]["_NUM"];
   $_SUP = $_SESSION["STATS"][$_sesmodulename]["_SUP"];
   $_DAT = $_SESSION["STATS"][$_sesmodulename]["_DAT"];

   foreach(array_keys($_RES) AS $sordid)
   {
      unset($data);
      $data = Array();

      $pdf->ezText(" <b>{$_SUP[$sordid]}, {$_NUM[$sordid]}, {$_DAT[$sordid]}</b>", 10);
      $pdf->ezText(" ", 6);
   
      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid] AS $sqlrow)
      {
         $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
         $data[$counter]["ARTICULO"]            = $sqlrow["item_title"];
         $data[$counter]["UNIDAD"]              = $sqlrow["unit_name"];
         $data[$counter]["SOLICITADO"]          = $sqlrow["item_amount"];
         $data[$counter]["RECIBIDO"]            = $sqlrow["item_amount_shipped"];
         $data[$counter]["PENDIENTE"]           = $sqlrow["transstock"];
         $data[$counter]["ESTADO"]              = $sqlrow["state"];

         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "CUMPLIMIENTO OC";
   if($_REQUEST["datamode"] == "archive")
      $HEADER["DATA"][$pcounter]["x2"] .= ": ARCHIVO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
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
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   

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

function doc_createStatsReferencias($CON, $mode = "")
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "invc_refs{$mode}";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "740", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FECHA"    => Array("width" => "100", "justification" => "left"),
                 "TIPO DOC"         => Array("width" => "100", "justification" => "left"),
                 "NUMERO DOC"   => Array("width" => "100", "justification" => "left"),
                 "FECHA VENCIMIENTO"   => Array("width" => "90", "justification" => "left"),
                 "DEBE"       => Array("width" => "110", "justification" => "right"),
                 "HABER"           => Array("width" => "110", "justification" => "right"),
                 "SALDO"      => Array("width" => "110", "justification" => "right")));


   
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $custid)
   {
      //----------------------------------------------------------------------------------
      unset($data);
      $data = Array();
      $counter = 0;
      
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$custid] AS $sqlrow)
      {
         $data[$counter]["FECHA"]         = $sqlrow["invc_date"];
         $data[$counter]["TIPO DOC"]      = $sqlrow["type"];
         $data[$counter]["NUMERO DOC"]    = $sqlrow["invc_docnumber"];
         $data[$counter]["FECHA VENCIMIENTO"] = $sqlrow["invc_estpay_date"];
         $data[$counter]["DEBE"]          = $sqlrow["debe"];
         $data[$counter]["HABER"]         = $sqlrow["haber"];
         $data[$counter]["SALDO"]         = $sqlrow["saldo"];
         $counter++;
      }

      $pdf->ezText("<b>".$_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["NAME"].": ".$_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["RUT"]."</b>", 10);
      $pdf->ezText(" ", 6);
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

   

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
   $HEADER["DATA"][$pcounter]["x2"] = "Cobranza Vendedor";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   if($mode == "")
      $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   else
      $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
   if($mode == "")
   {
      $HEADER["DATA"][$pcounter]["x3"] = "<b>VENDEDOR</b>";
      $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["STATS"][$_sesmodulename]["SELLER"];
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

function doc_createSupplierOrderGen($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "supporder_gen";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "740", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"        => Array("width" => "40", "justification" => "left"),
                 "ARTICULO"      => Array("width" => "220", "justification" => "left"),
                 "UNIDAD"        => Array("width" => "50", "justification" => "left"),
                 "CODIGO/PROV"   => Array("width" => "100", "justification" => "left"),
                 "MARCADO"       => Array("width" => "45", "justification" => "center"),
                 "VENTA"         => Array("width" => "40", "justification" => "center"),
                 "ACTUAL"        => Array("width" => "40", "justification" => "center"),
                 "TRANS"         => Array("width" => "40", "justification" => "center"),
                 "MIN"           => Array("width" => "40", "justification" => "center"),
                 "PROVEEDOR"     => Array("width" => "100", "justification" => "left")));


   unset($data);
   $data = Array();
   $counter = 0;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]        = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO"]      = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]        = $sqlrow["unit_name"];
      $data[$counter]["CODIGO/PROV"]   = $sqlrow["item_code"];
      $data[$counter]["MARCADO"]       = $sqlrow["amount"];
      $data[$counter]["VENTA"]         = $sqlrow["vamount"];
      $data[$counter]["ACTUAL"]        = $sqlrow["storehousestock"];
      $data[$counter]["TRANS"]         = $sqlrow["transstock"];
      $data[$counter]["MIN"]           = $sqlrow["stcontent"];
      $data[$counter]["PROVEEDOR"]     = $sqlrow["thissuppname"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

   

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
   $HEADER["DATA"][$pcounter]["x2"] = "GENERAR ORDENES DE COMPRA";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

function doc_createStatsCustNoPayed($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "custnopayed";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "740", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FACTURA"    => Array("width" => "90", "justification" => "left"),
                 "FECHA"         => Array("width" => "80", "justification" => "left"),
                 "FORMA DE PAGO"   => Array("width" => "130", "justification" => "left"),
                 "VENCIMIENTO"       => Array("width" => "90", "justification" => "left"),
                 "ATRASO"           => Array("width" => "80", "justification" => "left"),
                 "MONTO/FACTURA"      => Array("width" => "80", "justification" => "right"),
                 "MONTO/NOTAS"      => Array("width" => "80", "justification" => "right"),
                 "MONTO/FINAL"      => Array("width" => "80", "justification" => "right")));


   
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $custid)
   {
      //----------------------------------------------------------------------------------
      unset($data);
      $data = Array();
      $counter = 0;
      
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$custid] AS $sqlrow)
      {
         $data[$counter]["FACTURA"]       = $sqlrow["invc_docnumber"];
         $data[$counter]["FECHA"]         = $sqlrow["invc_date"];
         $data[$counter]["FORMA DE PAGO"] = $sqlrow["pay_title"];
         $data[$counter]["VENCIMIENTO"]   = $sqlrow["invc_estpay_date"];
         $data[$counter]["ATRASO"]        = $sqlrow["waitdays"];
         $data[$counter]["MONTO/FACTURA"] = $sqlrow["invc_total_brutto"];
         $data[$counter]["MONTO/NOTAS"]   = $sqlrow["notesbrutto"];
         $data[$counter]["MONTO/FINAL"]   = $sqlrow["invc_total_final"];
         $counter++;
      }

      $pdf->ezText("<b>".$_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["NAME"].": ".$_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["RUT"]."</b>", 10);
      $pdf->ezText(" ", 6);
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

   

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
   $HEADER["DATA"][$pcounter]["x2"] = "CLIENTES ATRASADOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];

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

//----------------------------------------------------------------------------------
function doc_createStatsCustomers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "customer";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 6,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "RUT"       => Array("width" => "60", "justification" => "left"),
                 "NOMBRE"    => Array("width" => "110", "justification" => "left"),
                 "DIRECCIï¿½N" => Array("width" => "130", "justification" => "left"),
                 "TELï¿½FONO"  => Array("width" => "60", "justification" => "left"),
                 "EMAIL"     => Array("width" => "60", "justification" => "left"),
                 "REGION"    => Array("width" => "90", "justification" => "left"),
                 "COMUNA"    => Array("width" => "70", "justification" => "left"),
                 "VENDEDOR"  => Array("width" => "90", "justification" => "left"),
                 "CATEGORIA"  => Array("width" => "70", "justification" => "left")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["RUT"]       = $sqlrow["cust_rut"];
      $data[$counter]["NOMBRE"]    = $sqlrow["cust_company"];
      $data[$counter]["DIRECCIï¿½N"] = $sqlrow["cust_street"];
      $data[$counter]["TELï¿½FONO"]  = $sqlrow["cust_phone"];
      $data[$counter]["EMAIL"]     = $sqlrow["cust_email"];
      $data[$counter]["REGION"]    = $sqlrow["name"];
      $data[$counter]["COMUNA"]    = $sqlrow["nombre"];
      $data[$counter]["VENDEDOR"]  = $sqlrow["username"];
      $data[$counter]["CATEGORIA"]  = $sqlrow["cat_name"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "CLIENTES";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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

//----------------------------------------------------------------------------------
function doc_createStatsSellingRotation($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buyvssellrotation";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"              => Array("width" => "70", "justification" => "left"),
                 "ARTICULO"    => Array("width" => "230", "justification" => "left"),
                 "UNIDAD"              => Array("width" => "60", "justification" => "left"),
                 "STOCK/$"            => Array("width" => "50", "justification" => "left"),
                 "STOCK/C"          => Array("width" => "50", "justification" => "left"),
                 "COMPRA/$"             => Array("width" => "50", "justification" => "right"),
                 "COMPRA/C"            => Array("width" => "50", "justification" => "right"),
                 "VENTA/$"            => Array("width" => "50", "justification" => "right"),
                 "VENTA/C"            => Array("width" => "50", "justification" => "right"),
                 "V/C/%"            => Array("width" => "50", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]     = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO"]   = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]     = $sqlrow["unitdesc"];
      $data[$counter]["STOCK/$"]    = $sqlrow["stock_avgcost"];
      $data[$counter]["STOCK/C"]    = $sqlrow["stock"];
      $data[$counter]["COMPRA/$"]   = $sqlrow["buytot"];
      $data[$counter]["COMPRA/C"]   = $sqlrow["buyamt"];
      $data[$counter]["VENTA/$"]    = $sqlrow["selltot"];
      $data[$counter]["VENTA/C"]    = $sqlrow["sellamt"];
      $data[$counter]["V/C/%"]      = $sqlrow["sellper"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "EXITO DE VENTAS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $HEADER["DATA"][$pcounter]["x3"] = " ";
   $HEADER["DATA"][$pcounter]["x4"] = " ";

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

//----------------------------------------------------------------------------------
function doc_createStatsBuyDiscounts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buy_discounts";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "TIPO"              => Array("width" => "70", "justification" => "left"),
                 "PROVEEDOR"    => Array("width" => "220", "justification" => "left"),
                 "RUT"              => Array("width" => "60", "justification" => "left"),
                 "FECHA"            => Array("width" => "60", "justification" => "left"),
                 "DOC"          => Array("width" => "60", "justification" => "left"),
                 "MONTO/N"             => Array("width" => "60", "justification" => "right"),
                 "DESCUENTO $"            => Array("width" => "60", "justification" => "right"),
                 "DESCUENTO %"            => Array("width" => "60", "justification" => "right"),
                 "MONTO/F"            => Array("width" => "60", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["TIPO"]       = $sqlrow["type"];
      $data[$counter]["PROVEEDOR"]  = $sqlrow["supp_company"];
      $data[$counter]["RUT"]        = $sqlrow["supp_rut"];
      $data[$counter]["FECHA"]      = $sqlrow["shp_delivery_date"];
      $data[$counter]["DOC"]        = $sqlrow["shp_supplier_docnum"];
      $data[$counter]["MONTO/N"]    = $sqlrow["shp_total_netto_orig"];
      $data[$counter]["DESCUENTO $"]     = $sqlrow["docdscprc"];
      $data[$counter]["DESCUENTO %"]     = $sqlrow["docdscper"];
      $data[$counter]["MONTO/F"]    = $sqlrow["shp_total_netto"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "DESCUENTOS DE COMPRA";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $HEADER["DATA"][$pcounter]["x3"] = " ";
   $HEADER["DATA"][$pcounter]["x4"] = " ";

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

function doc_createStatsSellingSellers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "invc_seller_stats";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "740", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "TIPO DOC"      => Array("width" => "50", "justification" => "left"),
                 "NUMERO DOC"    => Array("width" => "60", "justification" => "left"),
                 "FECHA"         => Array("width" => "50", "justification" => "left"),
                 "VENCIMIENTO"   => Array("width" => "55", "justification" => "left"),
                 "CLIENTE"       => Array("width" => "200", "justification" => "left"),
                 "RUT"           => Array("width" => "55", "justification" => "left"),
                 "VENDEDOR"      => Array("width" => "100", "justification" => "left"),
                 "MONTO TOTAL"   => Array("width" => "60", "justification" => "right"),
                 "% COM"   => Array("width" => "35", "justification" => "right"),
                 "$ COM"   => Array("width" => "45", "justification" => "right")));


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["TIPO DOC"]      = $sqlrow["doctype"];
      $data[$counter]["NUMERO DOC"]    = $sqlrow["invc_docnumber"];
      $data[$counter]["FECHA"]         = $sqlrow["invc_date"];
      $data[$counter]["VENCIMIENTO"]   = $sqlrow["invc_estpay_date"];
      $data[$counter]["CLIENTE"]       = $sqlrow["cust_company"];
      $data[$counter]["RUT"]           = $sqlrow["cust_rut"];
      $data[$counter]["VENDEDOR"]      = $sqlrow["seller_name"];
      $data[$counter]["MONTO TOTAL"]   = $sqlrow["invc_total_brutto"];
      $data[$counter]["% COM"]         = $sqlrow["user_comission_perc"];
      $data[$counter]["$ COM"]         = $sqlrow["comamt"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   $attr = Array  ("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                   "width" => "740", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                   "rowGap" => 2, "colGap" => 3, "cols" => Array(
                   "NOMBRE VENDEDOR"   => Array("width" => "350", "justification" => "left"),
                   "VENTA TOTAL"       => Array("width" => "90", "justification" => "right"),
                   "COMISIï¿½N  "          => Array("width" => "90", "justification" => "right"),
                   "RETENCIï¿½N  "         => Array("width" => "90", "justification" => "right"),
                   "MONTO PAGO"        => Array("width" => "90", "justification" => "right")));
   unset($data);
   $data = Array();
   $counter = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["OVERW"]) AS $sqlrowid)
   {
      $sqlrow = $_SESSION["STATS"][$_sesmodulename]["OVERW"][$sqlrowid];
      
      $data[$counter]["NOMBRE VENDEDOR"]  = $sqlrow["NAME"];
      $data[$counter]["VENTA TOTAL"]      = $sqlrow["VALUE"];
      $data[$counter]["COMISIï¿½N  "]       = $sqlrow["COMM"];
      $data[$counter]["RETENCIï¿½N  "]      = $sqlrow["RETEN"];
      $data[$counter]["MONTO PAGO"]       = $sqlrow["PAYMENT"];
      $counter++;
   }

   $pdf->ezText(" ", 11);
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
   $HEADER["DATA"][$pcounter]["x2"] = "VENTAS POR VENDEDOR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
   $HEADER["DATA"][$pcounter]["x3"] = "<b>VENDEDOR</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["STATS"][$_sesmodulename]["SELLER"];
   
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

function doc_createStatsSellingSellersOffer($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "invc_offer_stats";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "740", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Nï¿½MERO"        => Array("width" => "90", "justification" => "left"),
                 "FECHA"         => Array("width" => "60", "justification" => "left"),
                 "CLIENTE"       => Array("width" => "230", "justification" => "left"),
                 "RUT"           => Array("width" => "70", "justification" => "left"),
                 "VENDEDOR"      => Array("width" => "150", "justification" => "left"),
                 "CC"            => Array("width" => "50", "justification" => "left"),
                 "ESTADO"        => Array("width" => "70", "justification" => "left")));


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["Nï¿½MERO"]        = $sqlrow["invc_docnumber"];
      $data[$counter]["FECHA"]         = $sqlrow["invc_date"];
      $data[$counter]["CLIENTE"]       = $sqlrow["cust_company"];
      $data[$counter]["RUT"]           = $sqlrow["cust_rut"];
      $data[$counter]["VENDEDOR"]      = $sqlrow["seller_name"];
      $data[$counter]["CC"]            = $sqlrow["ccnr"];
      $data[$counter]["ESTADO"]        = $sqlrow["req_status"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   $attr = Array  ("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                   "width" => "740", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                   "rowGap" => 2, "colGap" => 3, "cols" => Array(
                   "NOMBRE VENDEDOR"   => Array("width" => "360", "justification" => "left"),
                   "TOTAL"             => Array("width" => "90", "justification" => "right"),
                   "FINALIZADO"        => Array("width" => "90", "justification" => "right"),
                   "ACEPTADO"          => Array("width" => "90", "justification" => "right"),
                   "RECHAZADO"         => Array("width" => "90", "justification" => "right")));
   unset($data);
   $data = Array();
   $counter = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["OVERW"]) AS $sqlrowid)
   {
      $sqlrow = $_SESSION["STATS"][$_sesmodulename]["OVERW"][$sqlrowid];
      
      $data[$counter]["NOMBRE VENDEDOR"]  = $sqlrow["NAME"];
      $data[$counter]["TOTAL"]            = $sqlrow["AMT"];
      $data[$counter]["FINALIZADO"]       = $sqlrow["STATES"]["Finalizado"];
      $data[$counter]["ACEPTADO"]         = $sqlrow["STATES"]["Aceptado"];
      $data[$counter]["RECHAZADO"]        = $sqlrow["STATES"]["Rechazado"];
      $counter++;
   }

   $pdf->ezText(" ", 11);
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
   $HEADER["DATA"][$pcounter]["x2"] = "COTIZACIONES POR VENDEDOR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>VENDEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SELLER"];
   
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

function doc_createItemPriceMargen($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "price_itemmargen";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 6,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FACTURA"           => Array("width" => "50", "justification" => "left"),
                 "FECHA" => Array("width" => "40", "justification" => "left"),
                 "CLIENTE"           => Array("width" => "140", "justification" => "left"),
                 "NUMERO"         => Array("width" => "40", "justification" => "left"),
                 "ARTICULO"            => Array("width" => "145", "justification" => "left"),
                 "UNIDAD"            => Array("width" => "30", "justification" => "left"),
                 "CANTIDAD"           => Array("width" => "40", "justification" => "center"),
                 "$/VENTA/U"   => Array("width" => "35", "justification" => "center"),
                 "$/COMPRA/U"           => Array("width" => "40", "justification" => "center"),
                 "$/VENTA/T"      => Array("width" => "40", "justification" => "center"),
                 "$/COMPRA/T"          => Array("width" => "40", "justification" => "center"),
                 "%/MARGEN"     => Array("width" => "40", "justification" => "center"),
                 "$/MARGEN"     => Array("width" => "40", "justification" => "center")));


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["FACTURA"]          = $sqlrow["invc_docnumber"];
      $data[$counter]["FECHA"]            = $sqlrow["invc_date"];
      $data[$counter]["CLIENTE"]          = $sqlrow["cust_name"];
      $data[$counter]["NUMERO"]           = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO"]         = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]           = $sqlrow["unitdesc"];
      $data[$counter]["CANTIDAD"]         = $sqlrow["item_amount"];
      $data[$counter]["$/VENTA/U"]        = $sqlrow["unitsellprice"];
      $data[$counter]["$/COMPRA/U"]       = $sqlrow["item_costprice"];
      $data[$counter]["$/VENTA/T"]        = $sqlrow["item_sellprice_netto_dsc"];
      $data[$counter]["$/COMPRA/T"]       = $sqlrow["item_costprice_total"];
      $data[$counter]["%/MARGEN"]         = $sqlrow["marge_perc"];
      $data[$counter]["$/MARGEN"]         = $sqlrow["marge_val"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "MARGEN DE FACTURACION";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   $pcounter++;


   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["cat_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["shop_name"];
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

//----------------------------------------------------------------------------------
function doc_createStatsItemStockcounts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_stockcounts";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   if((int)$_REQUEST["printStatRep"])
      $pdf = prepareDoc($pdffile, "LETTER", "portrait", "listx");
   else
      $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if((int)$_REQUEST["printStatRep"])
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Nï¿½MERO"           => Array("width" => "40", "justification" => "left"),
                    "ARTï¿½CULO/FAMILIA" => Array("width" => "170", "justification" => "left"),
                    "UNIDAD"           => Array("width" => "50", "justification" => "left"),
                    "COD/PROV."        => Array("width" => "50", "justification" => "left"),
                    "STOCK ANTERIOR"   => Array("width" => "45", "justification" => "center"),
                    "AJUSTE"           => Array("width" => "30", "justification" => "center"),
                    "STOCK NUEVO"      => Array("width" => "35", "justification" => "center"),
                    "VALOR/U"          => Array("width" => "40", "justification" => "center"),
                    "VALOR\nTOTAL"     => Array("width" => "45", "justification" => "center"),
                    "FACTURA"          => Array("width" => "40", "justification" => "left"),
                    "FECHA/FACT"       => Array("width" => "45", "justification" => "left")));
   }
   else
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Nï¿½MERO"           => Array("width" => "40", "justification" => "left"),
                    "ARTï¿½CULO/FAMILIA" => Array("width" => "180", "justification" => "left"),
                    "UNIDAD"           => Array("width" => "40", "justification" => "left"),
                    "COD/PROV."        => Array("width" => "50", "justification" => "left"),
                    "RECUENTO"         => Array("width" => "45", "justification" => "left"),
                    "FECHA"            => Array("width" => "45", "justification" => "left"),
                    "BODEGA"           => Array("width" => "50", "justification" => "center"),
                    "STOCK ANTERIOR"   => Array("width" => "50", "justification" => "center"),
                    "AJUSTE"           => Array("width" => "40", "justification" => "center"),
                    "STOCK NUEVO"      => Array("width" => "40", "justification" => "center"),
                    "VALOR/U"          => Array("width" => "45", "justification" => "center"),
                    "VALOR\nTOTAL"     => Array("width" => "50", "justification" => "center"),
                    "FACTURA"          => Array("width" => "40", "justification" => "left"),
                    "FECHA/FACT"       => Array("width" => "45", "justification" => "left")));
   }
   if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "Nï¿½MERO"           => Array("width" => "100", "justification" => "left"),
                    "FAMILIA"          => Array("width" => "200", "justification" => "left"),
                    "RECUENTO"         => Array("width" => "100", "justification" => "left"),
                    "FECHA"            => Array("width" => "150", "justification" => "left"),
                    "VALOR\nTOTAL"     => Array("width" => "170", "justification" => "center")));
   }

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
      {
         $data[$counter]["Nï¿½MERO"]           = $sqlrow["item_number_prod"];
         $data[$counter]["FAMILIA"]          = $sqlrow["item_title"];
         $data[$counter]["RECUENTO"]         = $sqlrow["stc_num"];
         $data[$counter]["FECHA"]            = $sqlrow["date"];
         $data[$counter]["VALOR\nTOTAL"]     = $sqlrow["value_total"];
      }
      else
      {
         if((int)$_REQUEST["printStatRep"])
         {
            $data[$counter]["Nï¿½MERO"]           = $sqlrow["item_number_prod"];
            $data[$counter]["ARTï¿½CULO/FAMILIA"] = $sqlrow["item_title"];
            $data[$counter]["UNIDAD"]           = $sqlrow["unit_name"];
            $data[$counter]["COD/PROV."]        = $sqlrow["item_code"];
            $data[$counter]["STOCK ANTERIOR"]   = $sqlrow["item_amount_stock"];
            $data[$counter]["AJUSTE"]           = $sqlrow["item_amount_book"];
            $data[$counter]["STOCK NUEVO"]      = $sqlrow["stock_new"];
            $data[$counter]["VALOR/U"]          = $sqlrow["item_costprice_avg_netto"];
            $data[$counter]["VALOR\nTOTAL"]     = $sqlrow["value_total"];
            $data[$counter]["FACTURA"]          = $sqlrow["item_costprice_docnumber"];
            $data[$counter]["FECHA/FACT"]       = $sqlrow["item_costprice_docdate"];
         }
         else
         {
            $data[$counter]["Nï¿½MERO"]           = $sqlrow["item_number_prod"];
            $data[$counter]["ARTï¿½CULO/FAMILIA"] = $sqlrow["item_title"];
            $data[$counter]["UNIDAD"]           = $sqlrow["unit_name"];
            $data[$counter]["COD/PROV."]        = $sqlrow["item_code"];
            $data[$counter]["RECUENTO"]         = $sqlrow["stc_num"];
            $data[$counter]["FECHA"]            = $sqlrow["date"];
            $data[$counter]["BODEGA"]           = $sqlrow["st_name"];
            $data[$counter]["STOCK ANTERIOR"]   = $sqlrow["item_amount_stock"];
            $data[$counter]["AJUSTE"]           = $sqlrow["item_amount_book"];
            $data[$counter]["STOCK NUEVO"]      = $sqlrow["stock_new"];
            $data[$counter]["VALOR/U"]          = $sqlrow["item_costprice_avg_netto"];
            $data[$counter]["VALOR\nTOTAL"]     = $sqlrow["value_total"];
            $data[$counter]["FACTURA"]          = $sqlrow["item_costprice_docnumber"];
            $data[$counter]["FECHA/FACT"]       = $sqlrow["item_costprice_docdate"];
         }
      }
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);



   //----------------------------------------------------------------------------------
   $pcounter = 0;

   if((int)$_REQUEST["printStatRep"])
   {
      //----------------------------------------------------------------------------------
      $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8,
                      "showLines" => 0, "rowGap" => 1, "colGap" => 0,
                      "cols" => Array (
                      "x1" => Array("width" => "60", "justification" => "left"),
                      "x2" => Array("width" => "210", "justification" => "left"),
                      "x3" => Array("width" => "60", "justification" => "left"),
                      "x4" => Array("width" => "210", "justification" => "left")));

      $xdata = $_SESSION["STATS"][$_sesmodulename]["DATA"];

      $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME</b>";
      $HEADER["DATA"][$pcounter]["x2"] = "RECUENTO";
      $HEADER["DATA"][$pcounter]["x3"] = "<b>IMPRESION</b>";
      $HEADER["DATA"][$pcounter]["x4"] = date('d.m.Y', $currtme);
      $pcounter++;
      $HEADER["DATA"][$pcounter]["x1"] = "<b>NUMERO</b>";
      $HEADER["DATA"][$pcounter]["x2"] = $xdata[0]["stc_num"];
      $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
      $HEADER["DATA"][$pcounter]["x4"] = $xdata[0]["date"];
      $pcounter++;
      $HEADER["DATA"][$pcounter]["x1"] = "<b>BODEGA</b>";
      $HEADER["DATA"][$pcounter]["x2"] = $xdata[0]["st_name"];
      $HEADER["DATA"][$pcounter]["x3"] = "<b>OBS.</b>";
      $HEADER["DATA"][$pcounter]["x4"] = $xdata[0]["stc_annotation"];

      if($xdata[0]["ubi_name"] != "")
      {
         $pcounter++;
         $HEADER["DATA"][$pcounter]["x1"] = "<b>UBICACIï¿½N</b>";
         $HEADER["DATA"][$pcounter]["x2"] = $xdata[0]["ubi_name"];
      }
   }
   else
   {
      //----------------------------------------------------------------------------------
      $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8,
                      "showLines" => 0, "rowGap" => 1, "colGap" => 0,
                      "cols" => Array (
                      "x1" => Array("width" => "60", "justification" => "left"),
                      "x2" => Array("width" => "300", "justification" => "left"),
                      "x3" => Array("width" => "60", "justification" => "left"),
                      "x4" => Array("width" => "300", "justification" => "left")));
      //----------------------------------------------------------------------------------
      $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME</b>";
      $HEADER["DATA"][$pcounter]["x2"] = "HISTORIA DE RECUENTOS";
      $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
      $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
      $pcounter++;

      //----------------------------------------------------------------------------------
      $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
      $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];

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
      $pcounter++;


      //----------------------------------------------------------------------------------
      $HEADER["DATA"][$pcounter]["x1"] = "<b>FAMILIA</b>";
      if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
      {
         $sql = " select *
                  from productcats
                  where
                  id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
         $company = $CON->select($sql);
         $company = $company[0];
         $HEADER["DATA"][$pcounter]["x2"] = $company["cat_title"];
      }
      else
         $HEADER["DATA"][$pcounter]["x2"] = "TODO";

      //----------------------------------------------------------------------------------
      $HEADER["DATA"][$pcounter]["x3"] = "<b>SURCUSAL</b>";
      if((int)$_SESSION[$_sesmodulename]["sql_shop"])
      {
         $sql = " select *
                  from company_shops
                  where
                  id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
         $company = $CON->select($sql);
         $company = $company[0];
         $HEADER["DATA"][$pcounter]["x4"] = $company["shop_name"];
      }
      else
         $HEADER["DATA"][$pcounter]["x4"] = "TODO";
   }

   if((int)$_REQUEST["printStatRep"])
      $pdf = printPDFFooter($CON, $pdf, NULL, "listx", $HEADER);
   else
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

//----------------------------------------------------------------------------------
function doc_createStatsItemStockCard($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_CONFIGTRANTYPES;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockcards";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $sql = " select *
            from company_data
            where
            id = {$_SESSION[$_sesmodulename]["sql_company"]}";
   $company = $CON->select($sql);
   $company = $company[0];

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "card");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $_ITEMS     = $_SESSION["STATS"][$_sesmodulename]["_ITEMS"];
   $datearr    = $_SESSION["STATS"][$_sesmodulename]["_SQLDATE"];
   $shops      = getShops($CON);
   $_RES       = Array();
   $firstPage  = true;

   if($_REQUEST["showItem"] != "")
      $_ITEMS[0] = $_REQUEST["showItem"];

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "565", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FECHA"              => Array("width" => "65", "justification" => "left"),
                 "NUMERO DOCTO."      => Array("width" => "70", "justification" => "left"),
                 "ENTRADA"            => Array("width" => "60", "justification" => "center"),
                 "SALIDA"             => Array("width" => "60", "justification" => "center"),
                 "SALDO"              => Array("width" => "60", "justification" => "center"),
                 "COSTO/UNIDAD/DOC"   => Array("width" => "60", "justification" => "center"),
                 "COSTO/{$_SESSION["STATS"]["stock_cards"]["sql_price_calc_name"]}"          => Array("width" => "65", "justification" => "center"),
                 "COSTO/{$_SESSION["STATS"]["stock_cards"]["sql_price_calc_name"]}"          => Array("width" => "65", "justification" => "center"),
                 "SALDO/{$_SESSION["STATS"]["stock_cards"]["sql_price_calc_name"]}"          => Array("width" => "65", "justification" => "center")));


   //----------------------------------------------------------------------------------
   foreach($_ITEMS AS $itemid)
   {
      $hasData = false;
      
      $sql = " select *
               from item
               where
               id = {$itemid}";
      $itemdata = $CON->select($sql);
      $itemdata = $itemdata[0];

      if(!(int)$_SESSION[$_sesmodulename]["sql_price_calc"])
      {
         $avgcost = getItemAverageCost($CON, $_SESSION["stock_cards"]["sql_company"], $itemid);
         if($avgcost == false || (float)$avgcost == 0.00)
            $avgcost = getSupplierFinalCostNetto($CON, 0, $itemid);

         //----------------------------------------------------------------------------------
         $item_code = getSupplierItemCode($CON, 0, $itemid, "item");
         if($item_code != "")
            $item_code = ", {$item_code}";

         //----------------------------------------------------------------------------------
         $currstock = 0.00;
         foreach($shops AS $selshop)
         {
            if($selshop["shop_company_id"] == $_SESSION[$_sesmodulename]["sql_company"])
               $currstock += getItemShopCurrentStock($CON, $selshop["id"], $itemid, "item");
         }

         //----------------------------------------------------------------------------------
         $sql = " select tran_id, item_id, tran_day, tran_month, tran_year, tran_amount, tran_type, tran_number
                  from tran_data t1
                  where
                  t1.tran_st_id      > 0 and
                  t1.item_id         = {$itemid} and
                  t1.tran_company_id = {$_SESSION[$_sesmodulename]["sql_company"]}
                  order by t1.tran_year desc, t1.tran_month desc, t1.tran_day desc, t1.tran_crtdat desc";
         $itemtrans = $CON->select($sql);

         //----------------------------------------------------------------------------------
         $x = 0;
         foreach($itemtrans AS $itemtran)
         {
            if($_CONFIGTRANTYPES[$itemtran["tran_type"]] === true || $_CONFIGTRANTYPES[$itemtran["tran_type"]] === false)
               $addamount = $_CONFIGTRANTYPES[$itemtran["tran_type"]];

            $itemtrans[$x]["newstock"]    = $currstock;
            $itemtrans[$x]["addamount"]   = $addamount;
            
            if($addamount)
               $currstock -= $itemtran["tran_amount"];
            else
               $currstock += $itemtran["tran_amount"];

            $tstamp     = mktime(23, 59, 59, $itemtran["tran_month"], $itemtran["tran_day"], $itemtran["tran_year"]);
            $histavg    = getAverageCostFromHistoryLog($CON, $itemid, $_SESSION[$_sesmodulename]["sql_company"], $tstamp, $itemtran["tran_type"], $itemtran["tran_id"]);
            $histcom    = getAverageCostFromHistoryLog($CON, $itemid, $_SESSION[$_sesmodulename]["sql_company"], $tstamp, $itemtran["tran_type"], $itemtran["tran_id"], true);
            $histdocprc = getAverageCostFromHistoryLog($CON, $itemid, $_SESSION[$_sesmodulename]["sql_company"], $tstamp, $itemtran["tran_type"], $itemtran["tran_id"], false, true);
            
            if($histavg)
               $itemtrans[$x]["avgcost"] = $histavg;
            else
               $itemtrans[$x]["avgcost"] = $avgcost;
               
            $itemtrans[$x]["currstock"]   = $currstock;
            $itemtrans[$x]["histcom"]     = $histcom;
            $itemtrans[$x]["histdocprc"]  = $histdocprc;
            

            //----------------------------------------------------------------------------------
            $dyear   = (int)$itemtran["tran_year"];
            $dmonth  = (int)$itemtran["tran_month"];
            if(is_array($datearr[$dyear][$dmonth]))
            {
               if(($itemtran["tran_day"] >= $datearr[$dyear][$dmonth]["INIT"] || !(int)$datearr[$dyear][$dmonth]["INIT"]) &&
                  ($itemtran["tran_day"] <= $datearr[$dyear][$dmonth]["END"] || !(int)$datearr[$dyear][$dmonth]["END"]) )
               {
                  $itemtrans[$x]["PRINT"] = true;
                  $hasData = true;
               }
               
            }
            
            $x++;
         }
      }
      else
      {
         $itemtrans = getItemFiFoCost($CON, $_SESSION[$_sesmodulename]["sql_company"], $itemid, "data");

         //----------------------------------------------------------------------------------
         $x = 0;
         foreach($itemtrans AS $itemtran)
         {
            //----------------------------------------------------------------------------------
            $dyear   = (int)$itemtran["tran_year"];
            $dmonth  = (int)$itemtran["tran_month"];
            if(is_array($datearr[$dyear][$dmonth]))
            {
               if(($itemtran["tran_day"] >= $datearr[$dyear][$dmonth]["INIT"] || !(int)$datearr[$dyear][$dmonth]["INIT"]) &&
                  ($itemtran["tran_day"] <= $datearr[$dyear][$dmonth]["END"] || !(int)$datearr[$dyear][$dmonth]["END"]) )
               {
                  $itemtrans[$x]["PRINT"] = true;
                  $hasData = true;
               }
               
            }
            $x++;
         }
      }

      //----------------------------------------------------------------------------------
      if($hasData)
      {
         if(!$firstPage)
            $pdf->ezNewPage();
         $firstPage = false;

         unset($data);
         $data = Array();

         $pdf = doc_linedraw($pdf, 20, 565);
         $pdf->ezText("<b>TARJETA DE EXISTENCIA - {$company["company_name"]}</b>", 12);
         $pdf->ezText("<b>PRODUCTO: </b> {$itemdata["item_title"]}", 10);
         $pdf->ezText("<b>CODIGOS:     </b> {$itemdata["item_number_prod"]} {$item_code}", 10);
         $pdf = doc_linedraw($pdf, 20, 565);
   
         $counter = 0;
         $itemtrans = array_reverse($itemtrans);

         foreach($itemtrans AS $itemtran)
         {
            if($itemtran["PRINT"])
            {
               $_IGNOREDATA = false;
               $docnum = "";
               switch($itemtran["tran_type"])
               {
                  case "invoicesbuy":
                     $sql = " select invc_docnumber
                              from invoices_buy
                              where
                              id = {$itemtran["tran_id"]}";
                     $docnum = $CON->select($sql);
                     $docnum = "F/C ".$docnum[0]["invc_docnumber"];
                     break;
                  case "shipment":
                     $sql = " select shp_supplier_docnum
                              from shipment
                              where
                              id = {$itemtran["tran_id"]}";
                     $docnum = $CON->select($sql);
                     $docnum = "G/C ".$docnum[0]["shp_supplier_docnum"];
                     break;
                  case "ordersdelivery":
                     $sql = " select id, dlv_docnum, dlv_invoice_generated
                              from orders_delivery
                              where
                              id = {$itemtran["tran_id"]}";
                     $docnum = $CON->select($sql);
                     $docnum = $docnum[0];
                     if($docnum["dlv_invoice_generated"] > 0)
                     {
                        $sql = " select invc_docnumber
                                 from invoices_sell
                                 where
                                 id = {$docnum["dlv_invoice_generated"]}";
                        $docnum = $CON->select($sql);
                        $docnum = "F/V ".$docnum[0]["invc_docnumber"];
                     }
                     else
                     {
                        $sql = " select distinct invc_docnumber
                                 from invoices_sell t1
                                 INNER JOIN invoices_sell_parts t2 ON t1.id = t2.part_invc_id
                                 where
                                 t1.invc_status IN (2,3) and
                                 t2.part_dlv_id = {$itemtran["tran_id"]}";
                        $idocs = $CON->select($sql);
                        $istr  = "";
                        foreach($idocs AS $idoc)
                           $istr .= $idoc["invc_docnumber"].",";
                        $istr = substr($istr, 0, -1);
                        $docnum = "G/V ".$docnum["dlv_docnum"];
                        if($istr != "")
                           $docnum .= ", F/V {$istr}";
                     }
                     break;
                  case "sthdown":
                  case "sthup":
                     $sql = " select strc_shop_id, strc_shop_dest_id
                              from storehousechanges
                              where
                              id = {$itemtran["tran_id"]}";
                     $destdata = $CON->select($sql);
                     $destdata = $destdata[0];
                     if((int)$destdata["strc_shop_id"] == (int)$destdata["strc_shop_dest_id"])
                        $_IGNOREDATA = true;
                     break;
                  default:
                     $docnum = "AJUSTE ".$itemtran["tran_number"];
               }

               if(!$_IGNOREDATA)
               {
                  $data[$counter]["FECHA"]         = sprintf("%02s",$itemtran["tran_day"]).".".sprintf("%02s",$itemtran["tran_month"]).".".$itemtran["tran_year"];
                  $data[$counter]["NUMERO DOCTO."] = $docnum;
                  if($itemtran["histcom"] != "")
                     $data[$counter]["NUMERO DOCTO."] .= ", ".$itemtran["histcom"];

                  $data[$counter]["ENTRADA"]       = " ";
                  $data[$counter]["SALIDA"]        = " ";
                  
                  if($itemtran["addamount"])
                  {
                     $data[$counter]["ENTRADA"]    = printPrice($itemtran["tran_amount"],2);
                     $tval = printPrice($itemtran["avgcost"]*$itemtran["tran_amount"],0);
                  }
                  else
                  {
                     $data[$counter]["SALIDA"]     = printPrice($itemtran["tran_amount"],2);
                     $tval = printPrice($itemtran["avgcost"]*$itemtran["tran_amount"]*-1,0);
                    
                  }

                  $data[$counter]["SALDO"]         = printPrice($itemtran["newstock"],2);

                  if((float)$itemtran["histdocprc"])
                     $data[$counter]["COSTO/UNIDAD/DOC"] = printPrice($itemtran["histdocprc"],0);
                  else
                     $data[$counter]["COSTO/UNIDAD/DOC"] = " ";
                  $data[$counter]["COSTO/{$_SESSION["STATS"]["stock_cards"]["sql_price_calc_name"]}"]     = printPrice($itemtran["avgcost"],0);
                  $data[$counter]["TOTAL/{$_SESSION["STATS"]["stock_cards"]["sql_price_calc_name"]}"]     = $tval;
                  $data[$counter]["SALDO/{$_SESSION["STATS"]["stock_cards"]["sql_price_calc_name"]}"]     = printPrice(round($itemtran["newstock"],2) * round($itemtran["avgcost"],0),0);
                     
                  $counter++;
               }
            }
         }
         $pdf->ezTable($data,$type,$dummy,$attr);
      }
   }

   if($_REQUEST["showItem"] != "")
      return $data;

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

//----------------------------------------------------------------------------------
function doc_createStatsSellShpInvc($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellshpinvc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "CLIENTE"           => Array("width" => "220", "justification" => "left"),
                 "RUT"                => Array("width" => "70", "justification" => "left"),
                 "FECHA GUIA"         => Array("width" => "70", "justification" => "left"),
                 "NUMERO GUIA"        => Array("width" => "70", "justification" => "left"),
                 "TIPO"                => Array("width" => "70", "justification" => "left"),
                 "NUMERO FACTURA"      => Array("width" => "80", "justification" => "left"),
                 "FECHA FACTURA"      => Array("width" => "70", "justification" => "left"),
                 "ANULADO"             => Array("width" => "50", "justification" => "left")));


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["CLIENTE"]          = $sqlrow["cust_company"];
      $data[$counter]["RUT"]              = $sqlrow["cust_rut"];
      $data[$counter]["FECHA GUIA"]       = $sqlrow["dlv_delivery_date"];
      $data[$counter]["NUMERO GUIA"]      = $sqlrow["dlv_docnum"];
      $data[$counter]["TIPO"]             = $sqlrow["type"];
      $data[$counter]["NUMERO FACTURA"]   = $sqlrow["invc_docnumber"];
      $data[$counter]["FECHA FACTURA"]    = $sqlrow["invc_date"];
      $data[$counter]["ANULADO"]          = $sqlrow["ANULADO"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "GUIAS DE DESPACHO > FACTURAS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsBuyShpInvc($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbuyshpinvc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "PROVEEDOR"          => Array("width" => "220", "justification" => "left"),
                 "RUT"                => Array("width" => "100", "justification" => "left"),
                 "FECHA GUIA"         => Array("width" => "100", "justification" => "left"),
                 "NUMERO GUIA"        => Array("width" => "100", "justification" => "left"),
                 "NUMERO FACTURA"      => Array("width" => "100", "justification" => "left"),
                 "FECHA FACTURA"     => Array("width" => "100", "justification" => "left")));


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["PROVEEDOR"]        = $sqlrow["supp_company"];
      $data[$counter]["RUT"]              = $sqlrow["supp_rut"];
      $data[$counter]["FECHA GUIA"]       = $sqlrow["shp_delivery_date"];
      $data[$counter]["NUMERO GUIA"]      = $sqlrow["shp_supplier_docnum"];
      $data[$counter]["NUMERO FACTURA"]   = $sqlrow["invc_docnumber"];
      $data[$counter]["FECHA FACTURA"]    = $sqlrow["invc_date"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "GUIAS DE DESPACHO > FACTURAS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createUserLoans($CON, $loanid)
{
   global $_LANG;

   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$filedir}{$loanid}.loan.{$hash}.pdf";
   $pdf        = prepareDoc($filename, "LETTER", "landscape", "order");

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t4.company_short, t5.shop_name, t6.user_firstname, t6.user_lastname,
            t7.user_firstname 'app_firstname', t7.user_lastname 'app_lastname',
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from user_loans t1
            LEFT OUTER JOIN user t2 ON t1.loan_updusr = t2.id
            LEFT OUTER JOIN user t3 ON t1.loan_crtusr = t3.id
            LEFT OUTER JOIN company_data t4 ON t1.loan_company_id = t4.id
            LEFT OUTER JOIN company_shops t5 ON t1.loan_shop_id = t5.id
            LEFT OUTER JOIN user t6 ON t1.loan_user_id = t6.id
            LEFT OUTER JOIN user t7 ON t1.loan_user_id_approve = t7.id
            where
            t1.id = {$loanid} ";
   $loan = $CON->select($sql);
   $loan = $loan[0];

   //----------------------------------------------------------------------------------
   $sql = " select *
            from user_loans_rates
            where
            rate_loan_id = {$loanid}
            order by rate_date";
   $rates = $CON->select($sql);

   //----------------------------------------------------------------------------------
   if($loan["loan_hash"] != "")
      unlink("{$filedir}{$loanid}.loan.{$loan["loan_hash"]}.pdf");

   //----------------------------------------------------------------------------------
   $sql = " update user_loans
            set
            loan_hash   = '{$hash}',
            loan_upddat = {$currtme},
            loan_updusr = {$_SESSION["user_id"]}
            where
            id          = {$loanid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 720);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "90"),
                      "X2"  => Array("width" => "270"),
                      "X3"  => Array("width" => "90"),
                      "X4"  => Array("width" => "270")));

   $data[0]["X1"] = "<b>Fecha</b>";
   $data[0]["X2"] = "Santiago, ".date('d')." de ".$_LANG["MODULE"]["CAL"][(date('m')-1)]." ".date('Y');
   $data[0]["X3"] = "<b>Nï¿½mero</b>";
   $data[0]["X4"] = sprintf("%05s", $loan["id"]);

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $loan["company_short"];
   $data[1]["X3"] = "<b>Surcusal</b>";
   $data[1]["X4"] = $loan["shop_name"];

   $data[2]["X1"] = "<b>N. Cheques</b>";
   $data[2]["X2"] = $loan["loan_checknumber"];

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf = doc_linedraw($pdf, 30, 720);
   unset($data);
   $pdf->ezText("", 14);

   //----------------------------------------------------------------------------------719
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "120"),
                      "X2"  => Array("width" => "600")));

   $mtype = "$";
   $numberlin = 0;
   if((int)$loan["loan_price_type"])
   {
      $numberlin = 2;
      $mtype = "UF";
   }

   $data[0]["X1"] = "<b>Usuario</b>";
   $data[0]["X2"] = $loan["user_firstname"]." ".$loan["user_lastname"];

   $data[1]["X1"] = "<b>Aval</b>";
   $data[1]["X2"] = $loan["app_firstname"]." ".$loan["app_lastname"];

   $data[2]["X1"] = "<b>Fecha prï¿½stamo</b>";
   $data[2]["X2"] = date('d.m.Y',$loan["loan_date"]);

   $data[3]["X1"] = "<b>Fecha 1.Pago</b>";
   $data[3]["X2"] = date('d.m.Y',$loan["loan_init_date"]);

   $data[4]["X1"] = "<b>Valor prï¿½stamo {$mtype}</b>";
   $data[4]["X2"] = printPrice($loan["loan_price"],$numberlin);

   $data[5]["X1"] = "<b>Cant. cuotas</b>";
   $data[5]["X2"] = $loan["loan_rates"];

   $pdf->ezTable($data,$type,$dummy,$attr);
   unset($data);
   $pdf->ezText("", 14);

   //----------------------------------------------------------------------------------719
   unset($data);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 10,
                     "cols" =>   Array (
                     "CUOTAS"  => Array("width" => "100", "justification" => "center"),
                     "FECHA"   => Array("width" => "519", "justification" => "left"),
                     "VALOR"   => Array("width" => "100", "justification" => "right")
                     )
                  );

   for($x = 0; $x < count($rates) && $rates != false; $x++)
   {
      $data[$x]["CUOTAS"] = $x+1;
      $data[$x]["FECHA"]  = date("d.m.Y", $rates[$x]["rate_date"]);
      $data[$x]["VALOR"]  = printPrice($rates[$x]["rate_price"],$numberlin);
   }
   $ycomment = $pdf->ezTable($data,$type,$dummy,$attr);
//    $pdf->ezText(" ", 12);


   $pdf->ezSetY($ycomment);
   //----------------------------------------------------------------------------------
   unset($data);
   $pcounter = 0;
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "xpos" => "left", "showLines" => 0, "rowGap" => 0, "colGap" => 0, "fontSize"=> 10,
                     "protectRows" => 99, "cols" =>   Array (
                     "X1"  => Array("width" => "350", "justification" => "left"),
                     "X2"  => Array("width" => "375")
                     )
                  );
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   //----------------------------------------------------------------------------------
   if(trim($loan["loan_desc"]) != "")
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "<b>{$loan["loan_desc"]}</b>";
      $data[$pcounter]["X2"] = " ";
   }
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = "AJUSTE Y RECIBO DE DINERO MES DE ".date('m Y',$loan["loan_date"]);
   $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";

   $pcounter++;
   $data[$pcounter]["X1"] = "----------------------------------------------------------";
   $data[$pcounter]["X2"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = "               {$loan["user_firstname"]} {$loan["user_lastname"]}";
   $data[$pcounter]["X2"] = " ";

   $ty = $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $loan["loan_company_id"], "loan", sprintf("%05s", $loan["id"]));

   //----------------------------------------------------------------------------------
   $fp = fopen($filename, "w");
   if($fp)
   {
      $pdfdata = $pdf->output();
      fwrite($fp, $pdfdata);
      fclose($fp);
   }
}

//----------------------------------------------------------------------------------
function doc_createUserExpects($CON, $expid)
{
   global $_LANG;

   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$filedir}{$expid}.exp.{$hash}.pdf";
   $pdf        = prepareDoc($filename, "LETTER", "landscape", "order");

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t4.company_short, t5.shop_name, t6.user_firstname, t6.user_lastname, t7.pay_title,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from user_expects t1
            LEFT OUTER JOIN user          t2 ON t1.exp_updusr     = t2.id
            LEFT OUTER JOIN user          t3 ON t1.exp_crtusr     = t3.id
            LEFT OUTER JOIN company_data  t4 ON t1.exp_company_id = t4.id
            LEFT OUTER JOIN company_shops t5 ON t1.exp_shop_id    = t5.id
            LEFT OUTER JOIN user          t6 ON t1.exp_user_id    = t6.id
            LEFT OUTER JOIN user_payments t7 ON t1.exp_payment_id = t7.id
            where
            t1.id = {$expid} ";
   $expect = $CON->select($sql);
   $expect = $expect[0];

   //----------------------------------------------------------------------------------
   if($expect["exp_hash"] != "")
      unlink("{$filedir}{$expid}.exp.{$expect["exp_hash"]}.pdf");

   //----------------------------------------------------------------------------------
   $sql = " update user_expects
            set
            exp_hash   = '{$hash}',
            exp_upddat = {$currtme},
            exp_updusr = {$_SESSION["user_id"]}
            where
            id         = {$expid}";
   $CON->no_result($sql);

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 720);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "90"),
                      "X2"  => Array("width" => "270"),
                      "X3"  => Array("width" => "90"),
                      "X4"  => Array("width" => "270")));
   $data[0]["X1"] = "<b>Fecha</b>";
   $data[0]["X2"] = "Santiago, ".date('d')." de ".$_LANG["MODULE"]["CAL"][(date('m')-1)]." ".date('Y');
   $data[0]["X3"] = "<b>Nï¿½mero</b>";
   $data[0]["X4"] = sprintf("%05s", $expect["id"]);

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $expect["company_short"];
   $data[1]["X3"] = "<b>Surcusal</b>";
   $data[1]["X4"] = $expect["shop_name"];

   $data[2]["X1"] = "<b>N. Cheque</b>";
   $data[2]["X2"] = $expect["exp_checknumber"];

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf = doc_linedraw($pdf, 30, 720);
   unset($data);
   $pdf->ezText("", 14);

   //----------------------------------------------------------------------------------
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 12, "cols" => Array (
                      "X1"  => Array("width" => "120"),
                      "X2"  => Array("width" => "600")));

   $data[0]["X1"] = "<b>Usuario</b>";
   $data[0]["X2"] = $expect["user_firstname"]." ".$expect["user_lastname"];

   $data[1]["X1"] = "<b>Fecha anticipo</b>";
   $data[1]["X2"] = date('d.m.Y',$expect["exp_date"]);

   $data[2]["X1"] = "<b>Forma de pago</b>";
   $data[2]["X2"] = $expect["pay_title"];

   $data[3]["X1"] = "<b>Valor anticipo $</b>";
   $data[3]["X2"] = printPrice($expect["exp_price"]);

   $ycomment = $pdf->ezTable($data,$type,$dummy,$attr);

   $pdf->ezSetY($ycomment);
   //----------------------------------------------------------------------------------
   unset($data);
   $pcounter = 0;
   $attr = Array  (  "showHeadings" => 0, "shaded" => 0, "xpos" => "left", "showLines" => 0, "rowGap" => 0, "colGap" => 0, "fontSize"=> 10,
                     "protectRows" => 99, "cols" =>   Array (
                     "X1"  => Array("width" => "350", "justification" => "left"),
                     "X2"  => Array("width" => "375")
                     )
                  );
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = " ";
   $data[$pcounter]["X2"] = " ";
   //----------------------------------------------------------------------------------
   if(trim($expect["exp_desc"]) != "")
   {
      $pcounter++;
      $data[$pcounter]["X1"] = "<b>{$expect["exp_desc"]}</b>";
      $data[$pcounter]["X2"] = " ";
   }
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = "AJUSTE Y RECIBO DE DINERO MES DE ".date('m Y',$expect["exp_date"]);
   $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";
   $pcounter++; $data[$pcounter]["X1"] = " "; $data[$pcounter]["X2"] = " ";

   $pcounter++;
   $data[$pcounter]["X1"] = "----------------------------------------------------------";
   $data[$pcounter]["X2"] = " ";
   $pcounter++;
   $data[$pcounter]["X1"] = "               {$expect["user_firstname"]} {$expect["user_lastname"]}";
   $data[$pcounter]["X2"] = " ";

   $ty = $pdf->ezTable($data,$type,$dummy,$attr);

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $expect["exp_company_id"], "expect", sprintf("%05s", $expect["id"]));

   //----------------------------------------------------------------------------------
   $fp = fopen($filename, "w");
   if($fp)
   {
      $pdfdata = $pdf->output();
      fwrite($fp, $pdfdata);
      fclose($fp);
   }
}

//----------------------------------------------------------------------------------
function doc_createStatsStockValues($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockshop";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if((int)$_SESSION[$_sesmodulename]["sql_docmode"] == 1)
   {
      $attr1 = Array("showHeadings" => 0, "shaded" => 2, "shadeCol2" => Array(0.85,0.85,0.85),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "X1" => Array("width" => "50", "justification" => "left"),
                    "X2" => Array("width" => "60", "justification" => "left"),
                    "X3" => Array("width" => "360", "justification" => "left"),
                    "X4" => Array("width" => "60", "justification" => "left"),
                    "X5" => Array("width" => "60", "justification" => "right"),
                    "X6" => Array("width" => "60", "justification" => "right"),
                    "X7" => Array("width" => "60", "justification" => "right")));
   }
   else
   {
      $attr1 = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "X1" => Array("width" => "50", "justification" => "left"),
                    "X2" => Array("width" => "60", "justification" => "left"),
                    "X3" => Array("width" => "360", "justification" => "left"),
                    "X4" => Array("width" => "60", "justification" => "left"),
                    "X5" => Array("width" => "60", "justification" => "right"),
                    "X6" => Array("width" => "60", "justification" => "right"),
                    "X7" => Array("width" => "60", "justification" => "right")));
   }

   $attr2 = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "50", "justification" => "left"),
                 "X2" => Array("width" => "60", "justification" => "left"),
                 "X22" => Array("width" => "120", "justification" => "left"),
                 "X3" => Array("width" => "120", "justification" => "left"),
                 "X4" => Array("width" => "80", "justification" => "left"),
                 "X5" => Array("width" => "80", "justification" => "left"),
                 "X6" => Array("width" => "80", "justification" => "left"),
                 "X7" => Array("width" => "120", "justification" => "left")));

   $data[0]["X1"] = "<b>NUMERO</b>";
   $data[0]["X2"] = "<b>CODIGO PROV</b>";
   $data[0]["X3"] = "<b>ARTICULO</b>";
   $data[0]["X4"] = "<b>UNIDAD</b>";
   $data[0]["X5"] = "<b>COSTO PPP</b>";
   $data[0]["X6"] = "<b>STOCK/ACT</b>";
   $data[0]["X7"] = "<b>$ STOCK</b>";

   $pdf->ezTable($data,$type,$dummy,$attr1);
   unset($data);

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["HEAD"]) AS $x)
   {
      $sqlrow = $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x];

      $data[0]["X1"] = $sqlrow["item_number_prod"];
      $data[0]["X2"] = $sqlrow["item_code"];
      $data[0]["X3"] = $sqlrow["item_title"];
      $data[0]["X4"] = $sqlrow["unit_name"];
      $data[0]["X5"] = $sqlrow["avgcost"];
      $data[0]["X6"] = $sqlrow["lineges"];
      $data[0]["X7"] = $sqlrow["itemgescost"];

      $pdf->ezTable($data,$type,$dummy,$attr1);
      unset($data);

      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$x] AS $row)
      {
         $data[$counter]["X1"] = $row["invc_date"];
         $data[$counter]["X22"] = $row["supp_short"];
         $data[$counter]["X2"] = $row["invc_docnumber"];
         $data[$counter]["X3"] = $row["item_amount"];
         $data[$counter]["X4"] = $row["item_costprice_netto"];
         $data[$counter]["X5"] = $row["item_dsc"];
         $data[$counter]["X6"] = $row["item_dsc_nc"];
         $data[$counter]["X7"] = $row["item_cost_pricedsc"];
         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr2);
      unset($data);
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK POR SURCUSAL";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];

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

//----------------------------------------------------------------------------------
function doc_createStatsItemReserved($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockreserved";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if(!$_SESSION[$_sesmodulename]["sql_customer"])
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "NUMERO"                 => Array("width" => "60", "justification" => "left"),
                    "ARTICULO"               => Array("width" => "190", "justification" => "left"),
                    "UNIDAD"                 => Array("width" => "60", "justification" => "left"),
                    "CODIGO/PROV"            => Array("width" => "60", "justification" => "left"),
                    "NOTA DE VENTA"          => Array("width" => "60", "justification" => "left"),
                    "CLIENTE"                => Array("width" => "180", "justification" => "left"),
                    "FECHA"                  => Array("width" => "50", "justification" => "left"),
                    "CANTIDAD"               => Array("width" => "50", "justification" => "right")));
   else
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "NUMERO"                 => Array("width" => "60", "justification" => "left"),
                    "ARTICULO"               => Array("width" => "330", "justification" => "left"),
                    "UNIDAD"                 => Array("width" => "60", "justification" => "left"),
                    "CODIGO/PROV"            => Array("width" => "80", "justification" => "left"),
                    "NOTA DE VENTA"          => Array("width" => "60", "justification" => "left"),
                    "FECHA"                  => Array("width" => "60", "justification" => "left"),
                    "CANTIDAD"               => Array("width" => "60", "justification" => "right")));


   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO"]            = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unit_name"];
      $data[$counter]["CODIGO/PROV"]         = $sqlrow["item_code"];
      $data[$counter]["NOTA DE VENTA"]       = $sqlrow["req_number"];
      if(!$_SESSION[$_sesmodulename]["sql_customer"])
         $data[$counter]["CLIENTE"]             = $sqlrow["cust_name"];
      $data[$counter]["FECHA"]               = $sqlrow["req_crtdat"];
      $data[$counter]["CANTIDAD"]            = $sqlrow["transstock"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK POR DESPACHAR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   {
      $sql = " select *
               from customer
               where
               id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["cust_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   

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

//----------------------------------------------------------------------------------
function doc_createStatsItemInvoiced($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockinvoiced";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if(!$_SESSION[$_sesmodulename]["sql_customer"])
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "NUMERO"                 => Array("width" => "60", "justification" => "left"),
                    "ARTICULO"               => Array("width" => "190", "justification" => "left"),
                    "UNIDAD"                 => Array("width" => "60", "justification" => "left"),
                    "CODIGO/PROV"            => Array("width" => "60", "justification" => "left"),
                    "GUIA"                   => Array("width" => "60", "justification" => "left"),
                    "CLIENTE"                => Array("width" => "180", "justification" => "left"),
                    "FECHA"                  => Array("width" => "50", "justification" => "left"),
                    "CANTIDAD"               => Array("width" => "50", "justification" => "right")));
   else
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "NUMERO"                 => Array("width" => "60", "justification" => "left"),
                    "ARTICULO"               => Array("width" => "330", "justification" => "left"),
                    "UNIDAD"                 => Array("width" => "60", "justification" => "left"),
                    "CODIGO/PROV"            => Array("width" => "80", "justification" => "left"),
                    "GUIA"                   => Array("width" => "60", "justification" => "left"),
                    "FECHA"                  => Array("width" => "60", "justification" => "left"),
                    "CANTIDAD"               => Array("width" => "60", "justification" => "right")));

   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO"]            = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unit_name"];
      $data[$counter]["CODIGO/PROV"]         = $sqlrow["item_code"];
      $data[$counter]["GUIA"]                = $sqlrow["req_number"];
      if(!$_SESSION[$_sesmodulename]["sql_customer"])
         $data[$counter]["CLIENTE"]             = $sqlrow["cust_name"];
      $data[$counter]["FECHA"]               = $sqlrow["req_crtdat"];
      $data[$counter]["CANTIDAD"]            = $sqlrow["transstock"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK POR FACTURAR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_customer"])
   {
      $sql = " select *
               from customer
               where
               id = {$_SESSION[$_sesmodulename]["sql_customer"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["cust_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   

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

//----------------------------------------------------------------------------------
function doc_createStatsItemTransit($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstocktransit";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"                 => Array("width" => "60", "justification" => "left"),
                 "ARTICULO"               => Array("width" => "250", "justification" => "left"),
                 "UNIDAD"                 => Array("width" => "60", "justification" => "left"),
                 "NUMERO OC"              => Array("width" => "60", "justification" => "left"),
                 "PROVEEDOR"              => Array("width" => "160", "justification" => "left"),
                 "FECHA"                  => Array("width" => "60", "justification" => "left"),
                 "CANTIDAD"               => Array("width" => "60", "justification" => "right")));


   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO"]            = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unit_name"];
      $data[$counter]["NUMERO OC"]           = $sqlrow["sord_number"];
      $data[$counter]["PROVEEDOR"]           = $sqlrow["supp_short"];
      $data[$counter]["FECHA"]               = $sqlrow["sord_date"];
      $data[$counter]["CANTIDAD"]            = $sqlrow["transstock"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK EN TRANSITO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
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
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   

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

//----------------------------------------------------------------------------------
function doc_createUserSalarySelling($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $doctype    = "pdf_user_salary_selling";
   $currtme    = time();
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["STATS"][$_sesmodulename2]["id"]}.{$_SESSION["STATS"][$_sesmodulename2]["uid"]}.{$doctype}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["STATS"][$_sesmodulename2]["id"]}.{$_SESSION["STATS"][$_sesmodulename2]["uid"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "orderext");

   $pdf->ezText(" ", 11);
   $attr = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                 "xPos" => 300, "xpos" => "center", "showLines" => 0, "fontSize" => 12,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "520", "justification" => "left")
                 ));

   $data[0]["x1"] = "<b>LIQUIDACION TRABAJADOR</b>";
   $pdf->ezTable($data,$type,$dummy,$attr);
   unset($data);
   $pdf->ezText(" ", 11);

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 5, "protectRows"=>35,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1"  => Array("width" => "74", "justification" => "left"),
                 "X0"  => Array("width" => "40", "justification" => "left"),
                 "X2"  => Array("width" => "100", "justification" => "left"),
                 "X3"  => Array("width" => "80", "justification" => "left"),
                 "X4"  => Array("width" => "34", "justification" => "right"),
                 "X5"  => Array("width" => "30", "justification" => "right"),
                 "X6"  => Array("width" => "32", "justification" => "right"),
                 "X7"  => Array("width" => "32", "justification" => "right"),
                 "X8"  => Array("width" => "32", "justification" => "right"),
                 "X9"  => Array("width" => "32", "justification" => "right"),
                 "X10" => Array("width" => "32", "justification" => "right")
                 ));

   $counter = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA2"]) AS $sesidx)
   {
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA2"][$sesidx]) AS $sesidx2)
      {
         $row = $_SESSION["STATS"][$_sesmodulename2]["DATA2"][$sesidx][$sesidx2];
         $data[$counter]["X1"]  = $row["name"];
         $data[$counter]["X0"]  = $row["val0"];
         $data[$counter]["X2"]  = $row["val1"];
         $data[$counter]["X3"]  = $row["val2"];
         $data[$counter]["X4"]  = $row["val3"];
         $data[$counter]["X5"]  = $row["val4"];
         $data[$counter]["X6"]  = $row["val5"];
         $data[$counter]["X7"]  = $row["val6"];
         $data[$counter]["X8"]  = $row["val7"];
         $data[$counter]["X9"]  = $row["val8"];
         $data[$counter]["X10"] = $row["val9"];
         $counter++;
      }

      $pdf->ezTable($data,$type,$_SESSION["STATS"][$_sesmodulename2]["HEAD2"][$sesidx]["name"],$attr);
      $pdf->ezText(" ", 11);
      unset($data);
   }

   if(count(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA3"])) > 0)
      $pdf->ezNewPage();

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 6, "protectRows"=>10,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1"  => Array("width" => "104", "justification" => "left"),
                 "X2"  => Array("width" => "148", "justification" => "left"),
                 "X3"  => Array("width" => "104", "justification" => "right"),
                 "X4"  => Array("width" => "60", "justification" => "center"),
                 "X5"  => Array("width" => "52", "justification" => "right"),
                 "X6"  => Array("width" => "52", "justification" => "right")
                 ));

   $counter = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA3"]) AS $sesidx)
   {
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA3"][$sesidx]) AS $sesidx2)
      {
         $row = $_SESSION["STATS"][$_sesmodulename2]["DATA3"][$sesidx][$sesidx2];
         $data[$counter]["X1"] = $row["name"];
         $data[$counter]["X2"] = $row["val1"];
         $data[$counter]["X3"] = $row["val2"];
         $data[$counter]["X4"] = $row["val3"];
         $data[$counter]["X5"] = $row["val4"];
         $data[$counter]["X6"] = $row["val5"];
         $counter++;
      }
      $pdf->ezTable($data,$type,$_SESSION["STATS"][$_sesmodulename2]["HEAD3"][$sesidx]["name"],$attr);
      $pdf->ezText(" ", 11);
      unset($data);
      $counter = 0;
   }

   $pdf->ezNewPage();

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 6, "protectRows"=>22,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1"  => Array("width" => "200", "justification" => "left"),
                 "X2"  => Array("width" => "320", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename2]["DATA4"] AS $row)
   {
      $data[$counter]["X1"] = $row["name"];
      $data[$counter]["X2"] = $row["val1"];
      $counter++;
   }

   $pdf->ezTable($data,$type,"<b>TOTALES</b>",$attr);
   $pdf->ezText(" ", 11);
   unset($data);
   $counter = 0;


   $HEADER["DATA"][0]["x1"] = $_SESSION["STATS"][$_sesmodulename2]["DATA1"]["val1"];
   $HEADER["DATA"][0]["x2"] = $_SESSION["STATS"][$_sesmodulename2]["DATA1"]["val2"];
   $HEADER["DATA"][0]["x3"] = $_SESSION["STATS"][$_sesmodulename2]["DATA1"]["val3"];

   printPDFFooter($CON, $pdf, $_SESSION[$_sesmodulename]["sql_company"], "pagepic", $HEADER);

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

//----------------------------------------------------------------------------------
function doc_createUserSalaryOffice($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $doctype    = "user_salary_office";
   $currtme    = time();
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["STATS"][$_sesmodulename2]["id"]}.{$_SESSION["STATS"][$_sesmodulename2]["uid"]}.{$doctype}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["STATS"][$_sesmodulename2]["id"]}.{$_SESSION["STATS"][$_sesmodulename2]["uid"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $pdf->ezText(" ", 11);
   $attr = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                 "xPos" => 300, "xpos" => "center", "showLines" => 0, "fontSize" => 12,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "520", "justification" => "left")
                 ));

   $data[0]["x1"] = "<b>LIQUIDACION TRABAJADOR</b>";
   $pdf->ezTable($data,$type,$dummy,$attr);
   unset($data);
   $pdf->ezText(" ", 11);

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 0, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 1, "fontSize" => 8,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "140", "justification" => "left"),
                 "X2" => Array("width" => "270", "justification" => "left"),
                 "X4" => Array("width" => "110", "justification" => "left")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename2]["DATA2"] AS $row)
   {
      $data[$counter]["X1"] = $row["name"];
      $data[$counter]["X2"] = $row["val1"];
      $data[$counter]["X4"] = $row["val2"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);
   $pdf->ezText(" ", 11);
   unset($data);
   $counter = 0;

   $attr = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 0, "fontSize" => 9,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "X1" => Array("width" => "520", "justification" => "left")
                 ));

   $data[$counter]["X1"] = "Certifico que he recibido de CARLOS CONTRERAS Y CIA. LTDA. , a mi entera "
                          ."satisfacciï¿½n el saldo indicado en la presente liquidaciï¿½n, y no tengo cargos ni"
                          ."cobros posteriores que hacer.";
   $counter++;

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

   $HEADER["DATA"][0]["x1"] = $_SESSION["STATS"][$_sesmodulename2]["DATA1"]["val1"];
   $HEADER["DATA"][0]["x2"] = $_SESSION["STATS"][$_sesmodulename2]["DATA1"]["val2"];

   printPDFFooter($CON, $pdf, $_SESSION[$_sesmodulename]["sql_company"], "pagepic", $HEADER);

   $pdf->ezSetY(200);
   doc_linedraw($pdf, 460, 100, 1);
   $pdf->ezText("Recibi conforme", 9, Array("aleft" => 460));

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

//----------------------------------------------------------------------------------
function doc_createProdCatOverview($CON, $text = "", $showdel = 0)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "overviewprodcats";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $datsql = " select distinct t1.id, t1.cat_title, t1.cat_crtdat
               from productcats t1
               where ";
   if((int)$showdel)
      $datsql .= " t1.cat_status = 0 ";
   else
      $datsql .= " t1.cat_status = 1 ";

   if($text != "")
      $seasql .= " and (t1.id = ".(int)$text." or
                        t1.cat_title like '%{$text}%' ) ";
   $datsql .= " order by t1.id ";
   $cats = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"  => Array("width" => "60", "justification" => "left"),
                 "FAMILIA" => Array("width" => "450", "justification" => "left")
                 ));

   $counter = 0;
   foreach($cats AS $sqlrow)
   {
      $data[$counter]["NUMERO"]  = sprintf("%03s", $sqlrow["id"]);
      $data[$counter]["FAMILIA"] = $sqlrow["cat_title"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "RESUMEN DE FAMILIAS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   $pdf = printPDFFooter($CON, $pdf, NULL, "pageonly", $HEADER);

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

//----------------------------------------------------------------------------------
function doc_createStatsItemProducts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsitemproducts";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"              => Array("width" => "160", "justification" => "left"),
                 "ARTï¿½CULO"    => Array("width" => "290", "justification" => "left"),
                 "UNIDAD"              => Array("width" => "80", "justification" => "left"),
                 "CANTIDAD"            => Array("width" => "60", "justification" => "right"),
                 "MONTO (NETO)"          => Array("width" => "60", "justification" => "right"),
                 "P/COMPRA P"             => Array("width" => "60", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]       = $sqlrow["NUMBER"];
      $data[$counter]["ARTï¿½CULO"]     = $sqlrow["TITLE"];
      $data[$counter]["UNIDAD"]       = $sqlrow["UNIT"];
      $data[$counter]["CANTIDAD"]     = $sqlrow["AMOUNT"];
      $data[$counter]["MONTO (NETO)"] = $sqlrow["BUY"];
      $data[$counter]["P/COMPRA P"]   = $sqlrow["AVG_COST"];
      $counter++;
   }
   $data[$counter]["NUMERO"]       = "<b>TOTAL</b>";
   $data[$counter]["ARTï¿½CULO"]     = "";
   $data[$counter]["UNIDAD"]       = "";
   $data[$counter]["CANTIDAD"]     = "<b>".$_SESSION["STATS"][$_sesmodulename]["HEAD"]["AMOUNT"]."</b>";
   $data[$counter]["MONTO (NETO)"] = "<b>".$_SESSION["STATS"][$_sesmodulename]["HEAD"]["BUY"]."</b>";
   $data[$counter]["P/COMPRA P"]   = "<b>".$_SESSION["STATS"][$_sesmodulename]["HEAD"]["AVG_COST"]."</b>";

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPRAS POR PRODUCTO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
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
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $HEADER["DATA"][$pcounter]["x3"] = "<b>PERIODO</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["HEADER"][$_sesmodulename]["FROM"]." - ".$_SESSION["HEADER"][$_sesmodulename]["TO"];

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

//----------------------------------------------------------------------------------
function doc_createStatsItemProductsDetails($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_buying_products_details";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"              => Array("width" => "40", "justification" => "left"),
                 "ARTï¿½CULO"            => Array("width" => "180", "justification" => "left"),
                 "UNIDAD"              => Array("width" => "40", "justification" => "left"),
                 "COD/PROV"            => Array("width" => "60", "justification" => "left"),
                 "PROVEEDOR"           => Array("width" => "140", "justification" => "left"),
                 "TIPO"                => Array("width" => "40", "justification" => "left"),
                 "DOCTO"               => Array("width" => "70", "justification" => "left"),
                 "FECHA"               => Array("width" => "50", "justification" => "left"),
                 "CANTIDAD"            => Array("width" => "50", "justification" => "right"),
                 "NETO/TOTAL"          => Array("width" => "50", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]        = $sqlrow["item_number_prod"];
      $data[$counter]["ARTï¿½CULO"]      = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]        = $sqlrow["unit_name"];
      $data[$counter]["COD/PROV"]      = $sqlrow["item_code"];
      $data[$counter]["PROVEEDOR"]     = $sqlrow["supp_short"];
      $data[$counter]["TIPO"]          = $sqlrow["note_type"];
      $data[$counter]["DOCTO"]         = $sqlrow["invc_docnumber"];
      $data[$counter]["FECHA"]         = $sqlrow["invc_date"];
      $data[$counter]["CANTIDAD"]      = $sqlrow["item_amount"];
      $data[$counter]["NETO/TOTAL"]    = $sqlrow["item_costprice"];
      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPRAS POR PRODUCTO - DETALLE";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>ARTICULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
   {
      $sql = " select *
               from item
               where
               id = {$_SESSION[$_sesmodulename]["sql_item_id"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["item_title"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FAMILIA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_pcat"])
   {
      $sql = " select *
               from productcats
               where
               id = {$_SESSION[$_sesmodulename]["sql_pcat"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["cat_title"];
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
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $HEADER["DATA"][$pcounter]["x3"] = "<b>PERIODO</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["HEADER"][$_sesmodulename]["FROM"]." - ".$_SESSION["HEADER"][$_sesmodulename]["TO"];

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

//----------------------------------------------------------------------------------
function doc_createStatsItemSuppliers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsitemsuppliers";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "PROVEEDOR"              => Array("width" => "180", "justification" => "left"),
                 "NOMBRE"    => Array("width" => "230", "justification" => "left"),
                 "RUT"              => Array("width" => "60", "justification" => "left"),
                 "EXTENTO"            => Array("width" => "60", "justification" => "right"),
                 "NETO"          => Array("width" => "60", "justification" => "right"),
                 "IVA"             => Array("width" => "60", "justification" => "right"),
                 "TOTAL"            => Array("width" => "60", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["PROVEEDOR"] = $sqlrow["supp_company"];
      $data[$counter]["NOMBRE"]    = $sqlrow["supp_short"];
      $data[$counter]["RUT"]       = $sqlrow["supp_rut"];
      $data[$counter]["EXTENTO"]   = $sqlrow["invc_total_taxes_exclude"];
      $data[$counter]["NETO"]      = $sqlrow["invc_total_netto"];
      $data[$counter]["IVA"]       = $sqlrow["invc_total_taxes"];
      $data[$counter]["TOTAL"]     = $sqlrow["invc_total_brutto"];
      $counter++;
   }
   $data[$counter]["PROVEEDOR"] = "<b>TOTAL</b>";
   $data[$counter]["NOMBRE"]    = "";
   $data[$counter]["RUT"]       = "";
   $data[$counter]["EXTENTO"]   = "<b>".$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_taxes_exclude"]."</b>";
   $data[$counter]["NETO"]      = "<b>".$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_netto"]."</b>";
   $data[$counter]["IVA"]       = "<b>".$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_taxes"]."</b>";
   $data[$counter]["TOTAL"]     = "<b>".$_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_brutto"]."</b>";

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPRAS POR PROVEEDOR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $HEADER["DATA"][$pcounter]["x3"] = "<b>PERIODO</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["HEADER"][$_sesmodulename]["FROM"]." - ".$_SESSION["HEADER"][$_sesmodulename]["TO"];

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

//----------------------------------------------------------------------------------
function doc_createStatsItemSuppliersDetails($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_buying_supdetail";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "TIPO"             => Array("width" => "100", "justification" => "left"),
                 "NUMERO DOCTO"     => Array("width" => "100", "justification" => "left"),
                 "FECHA"            => Array("width" => "110", "justification" => "left"),
                 "EXENTO"           => Array("width" => "100", "justification" => "right"),
                 "NETO"             => Array("width" => "100", "justification" => "right"),
                 "IVA"              => Array("width" => "100", "justification" => "right"),
                 "BRUTO"            => Array("width" => "100", "justification" => "right")
                 ));

   $counter = 0;

   $_SUP = $_SESSION["STATS"][$_sesmodulename]["HEADER"]["SUPS"];
   foreach(array_keys($_SUP) AS $supid)
   {
      unset($data);
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$supid] AS $sqlrow)
      {
         $data[$counter]["TIPO"]          = $sqlrow["note_type"];
         $data[$counter]["NUMERO DOCTO"]  = $sqlrow["invc_docnumber"];
         $data[$counter]["FECHA"]         = $sqlrow["invc_date"];
         $data[$counter]["EXTENTO"]       = $sqlrow["invc_total_taxes_exclude"];
         $data[$counter]["NETO"]          = $sqlrow["invc_total_netto"];
         $data[$counter]["IVA"]           = $sqlrow["invc_total_taxes"];
         $data[$counter]["BRUTO"]         = $sqlrow["invc_total_brutto"];
         $counter++;
      }
      $pdf->ezText("<b> {$_SUP[$supid]["NAME"]}, RUT: {$_SUP[$supid]["RUT"]}</b>", 12);
      $pdf->ezText(" ", 4);
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPRAS POR PROVEEDOR - DETALLE";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["supp_company"];
   }
   else
      $HEADER["DATA"][$pcounter]["x4"] = "TODO";

   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>SURCUSAL</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_shop"])
   {
      $sql = " select *
               from company_shops
               where
               id = {$_SESSION[$_sesmodulename]["sql_shop"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x2"] = $company["shop_name"];
   }
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   $HEADER["DATA"][$pcounter]["x3"] = "<b>PERIODO</b>";
   $HEADER["DATA"][$pcounter]["x4"] = $_SESSION["HEADER"][$_sesmodulename]["FROM"]." - ".$_SESSION["HEADER"][$_sesmodulename]["TO"];

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

//----------------------------------------------------------------------------------
function doc_createStatsSellingProducts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellingprod";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"              => Array("width" => "80", "justification" => "left"),
                 "ARTICULO/FAMILIA"    => Array("width" => "200", "justification" => "left"),
                 "UNIDAD"              => Array("width" => "40", "justification" => "left"),
                 "Cï¿½DIGO CONTAB."      => Array("width" => "100", "justification" => "left"),
                 "CANTIDAD"            => Array("width" => "60", "justification" => "right"),
                 "MONTO/NETO"          => Array("width" => "60", "justification" => "right"),
                 "MONTO/IVA"           => Array("width" => "60", "justification" => "right"),
                 "MONTO/BRUTO"         => Array("width" => "60", "justification" => "right"),
                 // "MONTO/BRUTO VENTAS"  => Array("width" => "60", "justification" => "right"),
                 "% MONTO/BRUTO TOTAL" => Array("width" => "60", "justification" => "right")
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["NUMBER"];
      $data[$counter]["ARTICULO/FAMILIA"]    = $sqlrow["TITLE"];
      $data[$counter]["UNIDAD"]              = $sqlrow["UNIT"];
      $data[$counter]["Cï¿½DIGO CONTAB."]      = $sqlrow["CODE"];
      $data[$counter]["CANTIDAD"]            = $sqlrow["AMOUNT"];
      $data[$counter]["MONTO/NETO"]          = $sqlrow["SELL"];
      $data[$counter]["MONTO/IVA"]           = $sqlrow["IVA"];
      $data[$counter]["MONTO/BRUTO"]         = $sqlrow["BRUTO"];
      // $data[$counter]["MONTO/BRUTO VENTAS"]  = $sqlrow["TOT_BRUTTO"];
      $data[$counter]["% MONTO/BRUTO TOTAL"] = $sqlrow["PERC"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "VENTAS POR PRODUCTO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsSellingCustomers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellingcust";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "CLIENTE"          => Array("width" => "250", "justification" => "left"),
                 "NOMBRE"           => Array("width" => "160", "justification" => "left"),
                 "RUT"              => Array("width" => "60", "justification" => "left"),
                 "EXTENTO"          => Array("width" => "60", "justification" => "right"),
                 "NETO"             => Array("width" => "60", "justification" => "right"),
                 "IVA"              => Array("width" => "60", "justification" => "right"),
                 "TOTAL"            => Array("width" => "60", "justification" => "right")));


   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["CLIENTE"]             = $sqlrow["cust_company"];
      $data[$counter]["NOMBRE"]              = $sqlrow["cust_name"];
      $data[$counter]["RUT"]                 = $sqlrow["cust_rut"];
      $data[$counter]["EXTENTO"]             = $sqlrow["invc_total_taxes_exclude"];
      $data[$counter]["NETO"]                = $sqlrow["invc_total_netto"];
      $data[$counter]["IVA"]                 = $sqlrow["invc_total_taxes"];
      $data[$counter]["TOTAL"]               = $sqlrow["invc_total_brutto"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "VENTAS POR CLIENTE";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsItemShops($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockbyshops";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $a = "";

   $columnas =    Array (
                          "Nï¿½MERO"              => Array("width" => "15", "justification"  => "left"),
                          "ARTICULO/FAMILIA"    => Array("width" => "165", "justification" => "left"),
                          "CODIGO/PROV"         => Array("width" => "60", "justification"  => "left"),
                          "UNIDAD"              => Array("width" => "30", "justification"  => "left")
                       );

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"]); $i++)
   {
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"]}\nACTUAL"]       = Array("width" => "50", "justification" => "center");
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"]}\nRESERVA"]      = Array("width" => "50", "justification" => "center");
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"]}\nCOMPROMETIDO"] = Array("width" => "50", "justification" => "center");
   }

   $columnas["T/STOCK"]    = Array("width" => "50", "justification" => "center");
   $columnas["T/RESERVA"]  = Array("width" => "50", "justification" => "center");
   $columnas["T/DISPO"]    = Array("width" => "50", "justification" => "center");

   $storeHouses = "";

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "570", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => $columnas);

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]                  = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO/FAMILIA"]        = $sqlrow["item_title"];
      $data[$counter]["CODIGO/PROV"]             = $sqlrow["item_code"];
      $data[$counter]["UNIDAD"]                  = $sqlrow["unit_name"];
      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"];

         if($sqlrow["SHOP"][$nombre_store]["curr"] != 0)
            $data[$counter][$nombre_store."\nACTUAL"] = $sqlrow["SHOP"][$nombre_store]["curr"];
         else
            $data[$counter][$nombre_store."\nACTUAL"] = " ";

         if($sqlrow["SHOP"][$nombre_store]["resr"] != 0)
            $data[$counter][$nombre_store."\nRESERVA"] = $sqlrow["SHOP"][$nombre_store]["resr"];
         else
            $data[$counter][$nombre_store."\nRESERVA"] = " ";

         if($sqlrow["SHOP"][$nombre_store]["comp"] != 0)
            $data[$counter][$nombre_store."\nCOMPROMETIDO"] = $sqlrow["SHOP"][$nombre_store]["comp"];
         else
            $data[$counter][$nombre_store."\nCOMPROMETIDO"] = " ";
      }
      $data[$counter]["T/STOCK"]   = $sqlrow["lineges"];
      $data[$counter]["T/RESERVA"] = $sqlrow["reslges"];
      $data[$counter]["T/RESERVA"] = $sqlrow["compges"];
      $data[$counter]["T/DISPO"]   = $sqlrow["dispo"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK POR SUCURSAL";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsItemStoreHouses($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockbystorehouse";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $a = "";

   if(count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]) == 1)
   {
      $columnas =    Array (
                             "Nï¿½MERO"              => Array("width" => "20", "justification"  => "left"),
                             "ARTICULO/FAMILIA"    => Array("width" => "280", "justification" => "left"),
                             "CODIGO/PROV"         => Array("width" => "100", "justification"  => "left"),
                             "UNIDAD"              => Array("width" => "60", "justification"  => "left")
                          );
      $cols1 = 70;
      $cols2 = 80;
      $cols3 = 80;
   }
   else
   {
      $columnas =    Array (
                             "Nï¿½MERO"              => Array("width" => "20", "justification"  => "left"),
                             "ARTICULO/FAMILIA"    => Array("width" => "150", "justification" => "left"),
                             "CODIGO/PROV"         => Array("width" => "60", "justification"  => "left"),
                             "UNIDAD"              => Array("width" => "40", "justification"  => "left")
                          );
      $cols1 = 30;
      $cols2 = 40;
      $cols3 = 40;
   }

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]}\nACTUAL"] = Array("width" => $cols1, "justification" => "center");
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]}\nVALOR/U"] = Array("width" => $cols2, "justification" => "center");
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]}\nVALOR"] = Array("width" => $cols3, "justification" => "center");
   }


   if(count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]) > 1)
   {
      $columnas["TOTAL"] = Array("width" => "40", "justification" => "center");
      $columnas["TOTAL\nVALOR"] = Array("width" => "50", "justification" => "center");
   }
   $storeHouses = "";

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 2, "cols" => $columnas);
   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]                  = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO/FAMILIA"]        = $sqlrow["item_title"];
      $data[$counter]["CODIGO/PROV"]             = $sqlrow["item_code"];
      $data[$counter]["UNIDAD"]                  = $sqlrow["unitdesc"];
      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"];

         if($sqlrow["BODEGA"][$nombre_store]["curr"] != 0)
            $data[$counter][$nombre_store."\nACTUAL"] = printPrice($sqlrow["BODEGA"][$nombre_store]["curr"],2);
         else
            $data[$counter][$nombre_store."\nACTUAL"] = " ";

         if($sqlrow["BODEGA"][$nombre_store]["costu"] != 0)
            $data[$counter][$nombre_store."\nVALOR/U"] = printPrice($sqlrow["BODEGA"][$nombre_store]["costu"],2);
         else
            $data[$counter][$nombre_store."\nVALOR/U"] = " ";
            
         if($sqlrow["BODEGA"][$nombre_store]["cost"] != 0)
            $data[$counter][$nombre_store."\nVALOR"] = printPrice($sqlrow["BODEGA"][$nombre_store]["cost"],2);
         else
            $data[$counter][$nombre_store."\nVALOR"] = " ";
            
      }

      if(count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]) > 1)
      {
         $data[$counter]["TOTAL"] = printPrice($sqlrow["total"],2);
         $data[$counter]["TOTAL\nVALOR"] = printPrice($sqlrow["totalcost"],2);
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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK POR BODEGA";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsItemStoreHousesCritics($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_minimumx";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $a = "";

   $columnas =    Array (
                          "Nï¿½MERO"              => Array("width" => "20", "justification"  => "left"),
                          "ARTICULO/FAMILIA"    => Array("width" => "200", "justification" => "left"),
                          "CODIGO/PROV"         => Array("width" => "60", "justification"  => "left"),
                          "UNIDAD"              => Array("width" => "40", "justification"  => "left")
                       );
   $cols1 = 40;
   $cols2 = 40;
   $cols3 = 40;

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]}\nACTUAL"] = Array("width" => $cols1, "justification" => "center");
     $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]}\nMIN"] = Array("width" => $cols2, "justification" => "center");
   }
   $storeHouses = "";

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 2, "cols" => $columnas);
   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]                  = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO/FAMILIA"]        = $sqlrow["item_title"];
      $data[$counter]["CODIGO/PROV"]             = $sqlrow["item_code"];
      $data[$counter]["UNIDAD"]                  = $sqlrow["unitdesc"];
      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"];

         $data[$counter][$nombre_store."\nACTUAL"] = printPrice($sqlrow["BODEGA"][$nombre_store]["curr"],2);
         $data[$counter][$nombre_store."\nMIN"]    = printPrice($sqlrow["BODEGA"][$nombre_store]["min"],2);
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
   $HEADER["DATA"][$pcounter]["x2"] = "STOCK CRITICO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsItemStoreHousesMin($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_storehouses_min";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $a = "";
   $columnas =    Array (
                          "NUMERO"              => Array("width" => "60", "justification"  => "left"),
                          "ARTICULO/FAMILIA"    => Array("width" => "270", "justification" => "left"),
                          "CODIGO/PROV"         => Array("width" => "60", "justification"  => "left"),
                          "UNIDAD"              => Array("width" => "40", "justification"  => "left")
                       );
   $cols1 = 60;

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
      $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]}\nMIN"] = Array("width" => $cols1, "justification" => "center");
      $columnas["{$_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]}\nPED"] = Array("width" => $cols1, "justification" => "center");
   }

   $storeHouses = "";

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 2, "cols" => $columnas);
   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]                  = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO/FAMILIA"]        = $sqlrow["item_title"];
      $data[$counter]["CODIGO/PROV"]             = $sqlrow["item_code"];
      $data[$counter]["UNIDAD"]                  = $sqlrow["unitdesc"];
      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"];

         $data[$counter][$nombre_store."\nMIN"] = $sqlrow["BODEGA"][$nombre_store]["min"];
         $data[$counter][$nombre_store."\nPED"] = $sqlrow["BODEGA"][$nombre_store]["ped"];
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
   $HEADER["DATA"][$pcounter]["x2"] = "REGISTRO STOCK MINIMO";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsItemTrans($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstocktrans";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"              => Array("width" => "80", "justification" => "left"),
                 "ARTICULO/FAMILIA"    => Array("width" => "250", "justification" => "left"),
                 "UNIDAD"              => Array("width" => "60", "justification" => "left"),
                 "ENTRADAS"            => Array("width" => "70", "justification" => "right"),
                 "SALIDAS"             => Array("width" => "70", "justification" => "right"),
                 "S/ACT."              => Array("width" => "50", "justification" => "right"),
                 "S/RES."              => Array("width" => "50", "justification" => "right"),
                 "S/COMP."             => Array("width" => "50", "justification" => "right"),
                 "S/DISP."             => Array("width" => "50", "justification" => "right")));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO/FAMILIA"]    = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unitdesc"];
      $data[$counter]["ENTRADAS"]            = $sqlrow["plus"];
      $data[$counter]["SALIDAS"]             = $sqlrow["minus"];
      $data[$counter]["S/ACT."]              = $sqlrow["act"];
      $data[$counter]["S/RES."]              = $sqlrow["res"];
      $data[$counter]["S/COMP."]             = $sqlrow["comp"];
      $data[$counter]["S/DISP."]             = $sqlrow["disp"];

      // $data[$counter]["SALDO"]               = $sqlrow["total"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "MOVIMIENTO DEL STOCK";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = " ";
   $HEADER["DATA"][$pcounter]["x2"] = " ";
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createDepositos($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "cash_deps";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FOLIO DEPOSITO"              => Array("width" => "50", "justification" => "left"),
                 "FECHA DEPOSITO"    => Array("width" => "80", "justification" => "left"),
                 "FOLIO CIERRE"              => Array("width" => "50", "justification" => "left"),
                 "FECHA CIERRE"            => Array("width" => "80", "justification" => "left"),
                 "SUCURSAL"             => Array("width" => "100", "justification" => "left"),
                 "CAJA"              => Array("width" => "90", "justification" => "left"),
                 "USUARIO"              => Array("width" => "90", "justification" => "left"),
                 "\$ CAJA FUERTE"             => Array("width" => "60", "justification" => "right"),
                 "\$ CAJA VENTA"             => Array("width" => "60", "justification" => "right"),
                 "\$ TOTAL"             => Array("width" => "60", "justification" => "right"),
                 ));

   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["FOLIO DEPOSITO"]              = $sqlrow["depid"];
      $data[$counter]["FECHA DEPOSITO"]    = $sqlrow["dep_crtdat"];
      $data[$counter]["FOLIO CIERRE"]              = $sqlrow["cash_closeid"];
      $data[$counter]["FECHA CIERRE"]            = $sqlrow["cash_date"];
      $data[$counter]["SUCURSAL"]             = $sqlrow["shop_name"];
      $data[$counter]["CAJA"]              = $sqlrow["ca_name"];
      $data[$counter]["USUARIO"]              = $sqlrow["user_firstname"];
      $data[$counter]["\$ CAJA FUERTE"]             = $sqlrow["depcf_value"];
      $data[$counter]["\$ CAJA VENTA"]             = $sqlrow["dep_value"];
      $data[$counter]["\$ TOTAL"]             = $sqlrow["dep_total"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "DEPOSITOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = " ";
   $HEADER["DATA"][$pcounter]["x2"] = " ";
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsItemValues($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockvalues";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NUMERO"              => Array("width" => "60", "justification" => "left"),
                 "ARTICULO/FAMILIA"    => Array("width" => "100", "justification" => "left"),
                 "UNIDAD"              => Array("width" => "50", "justification" => "left"),
                 "FECHA"               => Array("width" => "75", "justification" => "left"),
                 "BODEGA"              => Array("width" => "60", "justification" => "left"),
                 "TRANSACCION"         => Array("width" => "60", "justification" => "left"),
                 "TIPO"                => Array("width" => "50", "justification" => "left"),
                 "USUARIO"             => Array("width" => "75", "justification" => "left"),
                 "TIPO AJUSTE"         => Array("width" => "50", "justification" => "left"),
                 "OBSERVACIONES"       => Array("width" => "90", "justification" => "left"),
                 "CANTIDAD"            => Array("width" => "50", "justification" => "right"),));


   
   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTICULO/FAMILIA"]    = $sqlrow["item_title"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unit_name"];
      $data[$counter]["FECHA"]               = $sqlrow["datstr"];
      $data[$counter]["BODEGA"]              = $sqlrow["st_name"];
      $data[$counter]["TRANSACCION"]         = $sqlrow["tran_number"];
      $data[$counter]["TIPO"]                = $sqlrow["tran_type"];
      $data[$counter]["USUARIO"]             = $sqlrow["user_firstname"];
      $data[$counter]["TIPO AJUSTE"]         = $sqlrow["stkis_title"];
      $data[$counter]["OBSERVACIONES"]       = $sqlrow["stk_annotation"];

      $data[$counter]["CANTIDAD"]            = $sqlrow["tran_amount"];

      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "DETALLES DEL STOCK";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = " ";
   $HEADER["DATA"][$pcounter]["x2"] = " ";
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsPaymentSellCustomer($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statscustomer";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FECHA"       => Array("width" => "50", "justification" => "left"),
                 "DOCUMENTO"   => Array("width" => "90", "justification" => "left"),
                 "NUMERO DOC." => Array("width" => "50", "justification" => "left"),
                 "MONTO"       => Array("width" => "50", "justification" => "left"),
                 "FECHA VENC"  => Array("width" => "50", "justification" => "left"),
                 "FECHA PAGO"  => Array("width" => "50", "justification" => "left"),
                 "FORMA PAGO"  => Array("width" => "70", "justification" => "left"),
                 "ESTADO PAGO" => Array("width" => "70", "justification" => "left"),
                 "DETALLES"    => Array("width" => "100", "justification" => "left"),
                 "S/DOC"       => Array("width" => "60", "justification" => "left"),
                 "S/TOTAL"     => Array("width" => "60", "justification" => "left")));

   $counter = 0;
   $data[$counter]["FECHA"]       = "S/INICIAL";
   $data[$counter]["DOCUMENTO"]   = " ";
   $data[$counter]["NUMERO DOC."] = " ";
   $data[$counter]["MONTO"]       = " ";
   $data[$counter]["FECHA VENC"]  = " ";
   $data[$counter]["FECHA PAGO"]  = " ";
   $data[$counter]["FORMA PAGO"]  = " ";
   $data[$counter]["ESTADO PAGO"] = " ";
   $data[$counter]["DETALLES"]    = " ";
   $data[$counter]["S/DOC"]       = " ";
   $data[$counter]["S/TOTAL"]     = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO"]."</b>";

   $counter++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["FECHA"]       = $sqlrow["val1"];
      $data[$counter]["DOCUMENTO"]   = $sqlrow["val2"];
      $data[$counter]["NUMERO DOC."] = $sqlrow["val3"];
      $data[$counter]["MONTO"]       = $sqlrow["val4"];
      if($sqlrow["val5"] > 0)
         $data[$counter]["FECHA VENC"] = date('d.m.Y', $sqlrow["val5"]);
      else
         $data[$counter]["FECHA VENC"] = " ";
      if($sqlrow["val6"] > 0)
         $data[$counter]["FECHA PAGO"] = date('d.m.Y', $sqlrow["val6"]);
      else
         $data[$counter]["FECHA PAGO"] = " ";
      $data[$counter]["FORMA PAGO"]  = $sqlrow["val7"];
      $data[$counter]["ESTADO PAGO"] = $sqlrow["val8"];
      $data[$counter]["DETALLES"]    = $sqlrow["val11"];
      $data[$counter]["S/DOC"]       = $sqlrow["val9"];
      $data[$counter]["S/TOTAL"]     = $sqlrow["val10"];
      $counter++;
   }

   $data[$counter]["DOCUMENTO"] = "<b>TOTAL POR PAGAR</b>";
   $data[$counter]["MONTO"]     = "<b>".$_SESSION["STATS"][$_sesmodulename]["init_saldo"]."</b>";
   $counter++;
   $data[$counter]["DOCUMENTO"] = "<b>TOTAL DE VENTAS</b>";
   $data[$counter]["MONTO"]     = "<b>".$_SESSION["STATS"][$_sesmodulename]["ges_ventas"]."</b>";
   $counter++;

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
   $HEADER["DATA"][$pcounter]["x2"] = "CUENTA CORRIENTE CLIENTES";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

// //----------------------------------------------------------------------------------
// function doc_createStatsPaymentSellCustomer($CON)
// {
//    global $_LANG;
//    global $_sesmodulename;
// 
//    //----------------------------------------------------------------------------------
//    $doctype    = "statscustomer";
//    $currtme    = time();
//    $hash       = md5(microtime());
//    $filedir    = "./docs.print/";
//    $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
//    $fileext    = ".pdf";
//    $pdffile    = "{$filedir}{$filename}{$fileext}";
// 
//    //----------------------------------------------------------------------------------
//    if ($handle = opendir($filedir))
//    {
//       while (false !== ($file = readdir($handle)))
//          if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
//             unlink("{$filedir}{$file}");
//       closedir($handle);
//    }
// 
//    //----------------------------------------------------------------------------------
//    $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");
// 
//    //----------------------------------------------------------------------------------
//    unset($data);
//    $data = Array();
// 
//    if((int)$_SESSION[$_sesmodulename]["sql_venc"])
//    {
//       $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
//                     "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
//                     "rowGap" => 2, "colGap" => 3, "cols" => Array(
//                     "FECHA"               => Array("width" => "50", "justification" => "left"),
//                     "TIPO"                => Array("width" => "90", "justification" => "left"),
//                     "NUMERO INTERNO"      => Array("width" => "80", "justification" => "left"),
//                     "COMPROBANTE"         => Array("width" => "90", "justification" => "left"),
//                     "FECHA VENC"          => Array("width" => "60", "justification" => "right"),
//                     "FACTURA REL."        => Array("width" => "60", "justification" => "right"),
//                     "EMPRESA"             => Array("width" => "80", "justification" => "right"),
//                     "DEBE"                => Array("width" => "80", "justification" => "right"),
//                     "HABER"               => Array("width" => "80", "justification" => "right"),
//                     "SALDO"               => Array("width" => "80", "justification" => "right")));
//    }
//    else
//    {
//       $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
//                     "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
//                     "rowGap" => 2, "colGap" => 3, "cols" => Array(
//                     "FECHA"               => Array("width" => "50", "justification" => "left"),
//                     "TIPO"                => Array("width" => "90", "justification" => "left"),
//                     "NUMERO INTERNO"      => Array("width" => "90", "justification" => "left"),
//                     "COMPROBANTE"         => Array("width" => "100", "justification" => "left"),
//                     "FACTURA REL."        => Array("width" => "60", "justification" => "right"),
//                     "EMPRESA"             => Array("width" => "80", "justification" => "right"),
//                     "DEBE"                => Array("width" => "90", "justification" => "right"),
//                     "HABER"               => Array("width" => "90", "justification" => "right"),
//                     "SALDO"               => Array("width" => "100", "justification" => "right")));
//    }
// 
// 
//    $counter = 0;
//    $data[$counter]["FECHA"]               = "S/INICIAL";
//    $data[$counter]["TIPO"]                = " ";
//    $data[$counter]["NUMERO INTERNO"]      = " ";
//    $data[$counter]["COMPROBANTE"]   = " ";
//    $data[$counter]["FACTURA REL."]        = " ";
//    if((int)$_SESSION[$_sesmodulename]["sql_venc"])
//       $data[$counter]["FECHA VENC"] = " ";
//    $data[$counter]["EMPRESA"]             = " ";
//    $data[$counter]["DEBE"]                = " ";
//    $data[$counter]["HABER"]               = " ";
//    $data[$counter]["SALDO"]               = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO"]."</b>";
// 
//    $counter++;
//    foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
//    {
//       $data[$counter]["FECHA"]               = $sqlrow["day"];
//       $data[$counter]["TIPO"]                = $sqlrow["desc"];
//       $data[$counter]["NUMERO INTERNO"]      = $sqlrow["invc_number"];
//       $data[$counter]["COMPROBANTE"]   = $sqlrow["invc_docnumber"];
//       $data[$counter]["FACTURA REL."]        = $sqlrow["note_invcnumber"];
//       if((int)$_SESSION[$_sesmodulename]["sql_venc"])
//       {
//          if($sqlrow["invc_estpay_date"] > 0)
//             $data[$counter]["FECHA VENC"] = date('d.m.Y', $sqlrow["invc_estpay_date"]);
//          else
//             $data[$counter]["FECHA VENC"] = " ";
//       }
//       $data[$counter]["EMPRESA"]             = $sqlrow["company_short"];
//       $data[$counter]["DEBE"]                = printPrice($sqlrow["val_haber"]);
//       $data[$counter]["HABER"]               = printPrice($sqlrow["val_debe"]);
//       $data[$counter]["SALDO"]               = printPrice($sqlrow["ges_saldo"]);
// 
//       $counter++;
//    }
// 
//    $data[$counter]["FECHA"]               = "<b>TOTAL</b>";
//    $data[$counter]["DEBE"]                = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO_HABER"]."</b>";
//    $data[$counter]["HABER"]               = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO_DEBE"]."</b>";
//    $data[$counter]["SALDO"]               = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO_LAST"]."</b>";
//    
//    
//    $pdf->ezTable($data,$type,$dummy,$attr);
// 
//    //----------------------------------------------------------------------------------
//    $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 8, 
//                    "showLines" => 0, "rowGap" => 1, "colGap" => 0,
//                    "cols" => Array (
//                    "x1" => Array("width" => "60", "justification" => "left"),
//                    "x2" => Array("width" => "300", "justification" => "left"),
//                    "x3" => Array("width" => "60", "justification" => "left"),
//                    "x4" => Array("width" => "300", "justification" => "left")));
// 
//    //----------------------------------------------------------------------------------
//    $pcounter = 0;
// 
//    //----------------------------------------------------------------------------------
//    $HEADER["DATA"][$pcounter]["x1"] = "<b>INFORME</b>";
//    $HEADER["DATA"][$pcounter]["x2"] = "CUENTA CORRIENTE CLIENTES";
//    $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
//    $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
//    $pcounter++;
// 
//    //----------------------------------------------------------------------------------
//    $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
//    $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
//       
//    //----------------------------------------------------------------------------------
//    $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
//    if((int)$_SESSION[$_sesmodulename]["sql_company"])
//    {
//       $sql = " select *
//                from company_data
//                where
//                id = {$_SESSION[$_sesmodulename]["sql_company"]}";
//       $company = $CON->select($sql);
//       $company = $company[0];
//       $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
//    }
//    else
//       $HEADER["DATA"][$pcounter]["x4"] = "TODO";
// 
//    
// 
//    $pdf = printPDFFooter($CON, $pdf, NULL, "list", $HEADER);
// 
//    //----------------------------------------------------------------------------------
//    $fp = fopen($pdffile, "w");
//    if($fp)
//    {
//       $pdfdata = $pdf->output();
//       fwrite($fp, $pdfdata);
//       fclose($fp);
// 
//       return $filename;
//    }
// 
//    return false;
// }

//----------------------------------------------------------------------------------
function doc_createStatsPaymentBuySupplier($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssupplier";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   if((int)$_SESSION[$_sesmodulename]["sql_venc"])
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "FECHA"               => Array("width" => "60", "justification" => "left"),
                    "TIPO"                => Array("width" => "90", "justification" => "left"),
                    "NUMERO INTERNO"      => Array("width" => "90", "justification" => "left"),
                    "COMPROBANTE PROV."   => Array("width" => "90", "justification" => "left"),
                    "FECHA VENC"          => Array("width" => "80", "justification" => "right"),
                    "FACTURA REL."        => Array("width" => "80", "justification" => "right"),
                    "DEBE"                => Array("width" => "80", "justification" => "right"),
                    "HABER"               => Array("width" => "80", "justification" => "right"),
                    "SALDO"               => Array("width" => "100", "justification" => "right")));
   }
   else
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "FECHA"               => Array("width" => "70", "justification" => "left"),
                    "TIPO"                => Array("width" => "90", "justification" => "left"),
                    "NUMERO INTERNO"      => Array("width" => "100", "justification" => "left"),
                    "COMPROBANTE PROV."   => Array("width" => "100", "justification" => "left"),
                    "FACTURA REL."        => Array("width" => "90", "justification" => "right"),
                    "DEBE"                => Array("width" => "100", "justification" => "right"),
                    "HABER"               => Array("width" => "100", "justification" => "right"),
                    "SALDO"               => Array("width" => "100", "justification" => "right")));
   }


   $counter = 0;
   $data[$counter]["FECHA"]               = "S/INICIAL";
   $data[$counter]["TIPO"]                = " ";
   $data[$counter]["NUMERO INTERNO"]      = " ";
   $data[$counter]["COMPROBANTE PROV."]   = " ";
   $data[$counter]["FACTURA REL."]        = " ";
   if((int)$_SESSION[$_sesmodulename]["sql_venc"])
      $data[$counter]["FECHA VENC"] = " ";
   $data[$counter]["DEBE"]                = " ";
   $data[$counter]["HABER"]               = " ";
   $data[$counter]["SALDO"]               = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO"]."</b>";

   $counter++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["FECHA"]               = $sqlrow["day"];
      $data[$counter]["TIPO"]                = $sqlrow["desc"];
      $data[$counter]["NUMERO INTERNO"]      = $sqlrow["invc_number"];
      $data[$counter]["COMPROBANTE PROV."]   = $sqlrow["invc_docnumber"];
      $data[$counter]["FACTURA REL."]        = $sqlrow["note_invcnumber"];
      if((int)$_SESSION[$_sesmodulename]["sql_venc"])
      {
         if($sqlrow["invc_estpay_date"] > 0)
            $data[$counter]["FECHA VENC"] = date('d.m.Y', $sqlrow["invc_estpay_date"]);
         else
            $data[$counter]["FECHA VENC"] = " ";
      }
      $data[$counter]["DEBE"]                = printPrice($sqlrow["val_debe"]);
      $data[$counter]["HABER"]               = printPrice($sqlrow["val_haber"]);
      $data[$counter]["SALDO"]               = printPrice($sqlrow["ges_saldo"]);

      $counter++;
   }

   $data[$counter]["FECHA"]               = "<b>TOTAL</b>";
   $data[$counter]["DEBE"]                = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO_DEBE"]."</b>";
   $data[$counter]["HABER"]               = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO_HABER"]."</b>";
   $data[$counter]["SALDO"]               = "<b>".$_SESSION["STATS"][$_sesmodulename]["SALDO_LAST"]."</b>";
   
   
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
   $HEADER["DATA"][$pcounter]["x2"] = "CUENTA CORRIENTE PROVEEDORES";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsSellInvoices($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellinvoices";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "720", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FECHA"               => Array("width" => "50", "justification" => "left"),
                 "DOCTO."              => Array("width" => "50", "justification" => "left"),
                 "CLIENTE"             => Array("width" => "170", "justification" => "left"),
                 "NUMERO DOCTO."       => Array("width" => "70", "justification" => "left"),
                 "TOTAL/VENTA"         => Array("width" => "60", "justification" => "right"),
                 "A CUENTA"            => Array("width" => "60", "justification" => "right"),
                 "TOTAL/DEUDA"         => Array("width" => "60", "justification" => "right"),
                 "DIAS"                => Array("width" => "40", "justification" => "center"),
                 "FECHA VENC"          => Array("width" => "55", "justification" => "right"),
                 "APROB"               => Array("width" => "40", "justification" => "center"),
                 "COMENTARIOS"         => Array("width" => "60", "justification" => "left")));


   $counter = 0;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $data[$counter]["FECHA"]               = $sqlrow["invc_date"];
      $data[$counter]["DOCTO."]              = $sqlrow["tran_type"];
      $data[$counter]["CLIENTE"]             = $sqlrow["cust_name"];
      $data[$counter]["NUMERO DOCTO."]       = $sqlrow["invc_docnumber"];
      $data[$counter]["TOTAL/VENTA"]         = $sqlrow["invc_total_brutto"];
      $data[$counter]["A CUENTA"]            = $sqlrow["tranpayed"];
      $data[$counter]["TOTAL/DEUDA"]         = $sqlrow["trannopayed"];
      $data[$counter]["DIAS"]                = $sqlrow["paydays"];
      $data[$counter]["FECHA VENC"]          = $sqlrow["invc_estpay_date"];
      $data[$counter]["APROB"]               = $sqlrow["transtat"];
      $data[$counter]["COMENTARIOS"]         = $sqlrow["pay_comments"];

      $counter++;
   }

   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText("", 10);

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "720", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "DOCTO."                  => Array("width" => "430", "justification" => "left"),
                 "TOTAL/VENTA"             => Array("width" => "85", "justification" => "right"),
                 "TOTAL/DEUDA"             => Array("width" => "85", "justification" => "right"),
                 "TOTAL FACTURAS VENCIDAS" => Array("width" => "115", "justification" => "right")));
   unset($data);
   $rowidx = 0;
   $_TOTAL = $_SESSION[$_sesmodulename]["_TOTALS"];
   
   $data[$rowidx]["DOCTO."]                  = "FACTURAS";
   $data[$rowidx]["TOTAL/VENTA"]             = printPrice($_TOTAL["Factura"]["TOTAL"], 2);
   $data[$rowidx]["TOTAL/DEUDA"]             = printPrice($_TOTAL["Factura"]["REALNOPAYED"], 2);
   $data[$rowidx]["TOTAL FACTURAS VENCIDAS"] = printPrice($_TOTAL["Factura"]["VENC"], 2);
   $rowidx++;
   $data[$rowidx]["DOCTO."]                  = "NOTAS DE CREDITO";
   $data[$rowidx]["TOTAL/VENTA"]             = "-".printPrice($_TOTAL["N/C"]["TOTAL"], 2);
   $data[$rowidx]["TOTAL/DEUDA"]             = "-".printPrice($_TOTAL["N/C"]["REALNOPAYED"], 2);
   $data[$rowidx]["TOTAL FACTURAS VENCIDAS"] = "-".printPrice($_TOTAL["N/C"]["VENC"], 2);
   $rowidx++;
   $data[$rowidx]["DOCTO."]                  = "NOTAS DE DEBITO";
   $data[$rowidx]["TOTAL/VENTA"]             = printPrice($_TOTAL["N/D"]["TOTAL"], 2);
   $data[$rowidx]["TOTAL/DEUDA"]             = printPrice($_TOTAL["N/D"]["REALNOPAYED"], 2);
   $data[$rowidx]["TOTAL FACTURAS VENCIDAS"] = printPrice($_TOTAL["N/D"]["VENC"], 2);
   $rowidx++;
   $data[$rowidx]["DOCTO."]                  = "<b>TOTAL</b>";
   $data[$rowidx]["TOTAL/VENTA"]             = "<b>".printPrice($_TOTAL["Factura"]["TOTAL"]       - $_TOTAL["N/C"]["TOTAL"]        + $_TOTAL["N/D"]["TOTAL"], 2)."</b>";
   $data[$rowidx]["TOTAL/DEUDA"]             = "<b>".printPrice($_TOTAL["Factura"]["REALNOPAYED"] - $_TOTAL["N/C"]["REALNOPAYED"]  + $_TOTAL["N/D"]["REALNOPAYED"], 2)."</b>";
   $data[$rowidx]["TOTAL FACTURAS VENCIDAS"] = "<b>".printPrice($_TOTAL["Factura"]["VENC"]        - $_TOTAL["N/C"]["VENC"]         + $_TOTAL["N/D"]["VENC"], 2)."</b>";
   
   $pdf->ezText("<b>TOTALES</b>", 9);
   $pdf->ezText("", 6);
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
   $HEADER["DATA"][$pcounter]["x2"] = "FACTURAS A COBRAR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>CLIENTE</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["CUSTOMER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsBuyInvoices($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbuyinvoices";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   $_SUPPNAMES    = $_SESSION["STATS"][$_sesmodulename]["_SUPNAMES"];
   $payments      = $_SESSION["STATS"][$_sesmodulename]["_PAYMENTS"];
   foreach($payments AS $payment)
      $idxpaysments[$payment["id"]] = $payment["pay_title"];
      
   foreach(array_keys($_SUPPNAMES) AS $suppid)
   {
      //----------------------------------------------------------------------------------
      $pdf->ezText("<b>{$_SUPPNAMES[$suppid]["NAME"]}, RUT: {$_SUPPNAMES[$suppid]["RUT"]}</b>", 9);
      
      $addstr = "";
      if($_SUPPNAMES[$suppid]["DSCFINANCE"])
      {
         if((int)$_SUPPNAMES[$suppid]["DSCFINANCEAPPLY"])
            $addstr = "<b>Descuento financiero:</b> SI, ";
         else
            $addstr = "<b>Descuento financiero:</b> NO, ";
      }
      if((int)$_SUPPNAMES[$suppid]["PAYMENTDEFAULT"])
         $addstr .= "<b>Forma de pago:</b> {$idxpaysments[$_SUPPNAMES[$suppid]["PAYMENTDEFAULT"]]}";
      else
         $addstr .= "<b>Forma de pago:</b> Segun Documento";
         
      if($addstr != "")
         $pdf->ezText("{$addstr}", 8);
      $pdf->ezText("", 6);
      
      unset($data);
      $data = Array();
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                    "rowGap" => 2, "colGap" => 3, "cols" => Array(
                    "FECHA"               => Array("width" => "60", "justification" => "left"),
                    "DOCTO."              => Array("width" => "65", "justification" => "left"),
                    "NUMERO DOCTO."       => Array("width" => "75", "justification" => "left"),
                    "MONTO DOCTO."        => Array("width" => "80", "justification" => "right"),
                    "VALOR REAL"          => Array("width" => "70", "justification" => "right"),
                    "FACTURA REL."        => Array("width" => "60", "justification" => "left"),
                    "OBSERVACIONES"       => Array("width" => "75", "justification" => "left"),
                    "DEUDA/MONTO"         => Array("width" => "75", "justification" => "right"),
                    "FECHA VENC"          => Array("width" => "55", "justification" => "right"),
                    "DIAS DEMORA"         => Array("width" => "60", "justification" => "center"),
                    "PAGADO"              => Array("width" => "40", "justification" => "center")));


      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid] AS $sqlrow)
      {
         $data[$counter]["FECHA"]               = $sqlrow["invc_receipt_date"];
         $data[$counter]["DOCTO."]              = $sqlrow["tran_type"];
         $data[$counter]["NUMERO DOCTO."]       = $sqlrow["invc_docnumber"];
         $data[$counter]["MONTO DOCTO."]        = $sqlrow["invc_total_brutto"];
         $data[$counter]["VALOR REAL"]          = $sqlrow["value_real"];
         $data[$counter]["FACTURA REL."]        = $sqlrow["note_invcnumber"];
         $data[$counter]["OBSERVACIONES"]       = $sqlrow["invc_desc"];
         $data[$counter]["DEUDA/MONTO"]         = $sqlrow["trannopayed"];
         $data[$counter]["FECHA VENC"]          = $sqlrow["invc_estpay_date"];
         $data[$counter]["DIAS DEMORA"]         = $sqlrow["paydays"];
         $data[$counter]["PAGADO"]              = $sqlrow["invc_payed"];

         $counter++;
      }

      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText("", 12);
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "DOCTO."              => Array("width" => "290", "justification" => "left"),
                 "MONTO DOCTO."        => Array("width" => "85", "justification" => "right"),
                 "VALOR REAL"          => Array("width" => "85", "justification" => "right"),
                 "PAGO/MONTO"          => Array("width" => "85", "justification" => "right"),
                 "DEUDA/MONTO"         => Array("width" => "85", "justification" => "right"),
                 "VENC/MONTO"          => Array("width" => "85", "justification" => "right")));
   unset($data);
   $rowidx = 0;
   $_TOTAL = $_SESSION[$_sesmodulename]["_TOTALS"];
   
   $data[$rowidx]["DOCTO."]         = "FACTURAS";
   $data[$rowidx]["MONTO DOCTO."]   = printPrice($_TOTAL["Factura"]["TOTAL"], 2);
   $data[$rowidx]["VALOR REAL"]     = printPrice($_TOTAL["Factura"]["REAL"], 2);
   $data[$rowidx]["PAGO/MONTO"]     = printPrice($_TOTAL["Factura"]["REALPAYED"], 2);
   $data[$rowidx]["DEUDA/MONTO"]    = printPrice($_TOTAL["Factura"]["REALNOPAYED"], 2);
   $data[$rowidx]["VENC/MONTO"]     = printPrice($_TOTAL["Factura"]["VENC"], 2);
   $rowidx++;
   $data[$rowidx]["DOCTO."]         = "NOTAS DE CREDITO";
   $data[$rowidx]["MONTO DOCTO."]   = "-".printPrice($_TOTAL["Nota de credito"]["TOTAL"], 2);
   $data[$rowidx]["VALOR REAL"]     = "-".printPrice($_TOTAL["Nota de credito"]["REAL"], 2);
   $data[$rowidx]["PAGO/MONTO"]     = "-".printPrice($_TOTAL["Nota de credito"]["REALPAYED"], 2);
   $data[$rowidx]["DEUDA/MONTO"]    = "-".printPrice($_TOTAL["Nota de credito"]["REALNOPAYED"], 2);
   $data[$rowidx]["VENC/MONTO"]     = "-".printPrice($_TOTAL["Nota de credito"]["VENC"], 2);
   $rowidx++;
   $data[$rowidx]["DOCTO."]         = "NOTAS DE DEBITO";
   $data[$rowidx]["MONTO DOCTO."]   = printPrice($_TOTAL["Nota de debito"]["TOTAL"], 2);
   $data[$rowidx]["VALOR REAL"]     = printPrice($_TOTAL["Nota de debito"]["REAL"], 2);
   $data[$rowidx]["PAGO/MONTO"]     = printPrice($_TOTAL["Nota de debito"]["REALPAYED"], 2);
   $data[$rowidx]["DEUDA/MONTO"]    = printPrice($_TOTAL["Nota de debito"]["REALNOPAYED"], 2);
   $data[$rowidx]["VENC/MONTO"]     = printPrice($_TOTAL["Nota de debito"]["VENC"], 2);
   $rowidx++;
   $data[$rowidx]["DOCTO."]         = "<b>TOTAL</b>";
   $data[$rowidx]["MONTO DOCTO."]   = "<b>".printPrice($_TOTAL["Factura"]["TOTAL"]       - $_TOTAL["Nota de credito"]["TOTAL"]        + $_TOTAL["Nota de debito"]["TOTAL"], 2)."</b>";
   $data[$rowidx]["VALOR REAL"]     = "<b>".printPrice($_TOTAL["Factura"]["REAL"]        - $_TOTAL["Nota de credito"]["REAL"]         + $_TOTAL["Nota de debito"]["REAL"], 2)."</b>";
   $data[$rowidx]["PAGO/MONTO"]     = "<b>".printPrice($_TOTAL["Factura"]["REALPAYED"]   - $_TOTAL["Nota de credito"]["REALPAYED"]    + $_TOTAL["Nota de debito"]["REALPAYED"], 2)."</b>";
   $data[$rowidx]["DEUDA/MONTO"]    = "<b>".printPrice($_TOTAL["Factura"]["REALNOPAYED"] - $_TOTAL["Nota de credito"]["REALNOPAYED"]  + $_TOTAL["Nota de debito"]["REALNOPAYED"], 2)."</b>";
   $data[$rowidx]["VENC/MONTO"]     = "<b>".printPrice($_TOTAL["Factura"]["VENC"]        - $_TOTAL["Nota de credito"]["VENC"]         + $_TOTAL["Nota de debito"]["VENC"], 2)."</b>";
   
   $pdf->ezText("<b>TOTALES</b>", 9);
   $pdf->ezText("", 6);
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
   $HEADER["DATA"][$pcounter]["x2"] = "FACTURAS A PAGAR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>PROVEEDOR</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION["STATS"][$_sesmodulename]["SUPPLIER"];
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createStatsBooksSelling($CON, $execmode = 0)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbooksselling";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   if(count($_SESSION["STATS"][$_sesmodulename]["BOLDAT"]))
   {
      unset($data);
      $data = Array();

      $pdf->ezText("<b>BOLETAS</b>", 11);
      $pdf->ezText(" ", 8);

      $colarr = array();
      foreach($_SESSION["STATS"][$_sesmodulename]["BOLCOL"] AS $col)
         $colarr["<b>{$col["name"]}</b>"] = Array("width" => $col["width"], "justification"  => "{$col["align"]}");

      //----------------------------------------------------------------------------------
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                    "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 8, "protectRows" => 99,
                    "rowGap" => 2, "colGap" => 3, "cols" => $colarr
                    );

      foreach($_SESSION["STATS"][$_sesmodulename]["BOLDAT"] AS $row)
      {
         $cc = 1;
         foreach($_SESSION["STATS"][$_sesmodulename]["BOLCOL"] AS $col)
         {
            $data[$counter]["<b>{$col["name"]}</b>"] = $row["val{$cc}"];
            $cc++;
         }
         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "FACTURA"             => Array("width" => "80", "justification" => "left"),
                 "FECHA"               => Array("width" => "60", "justification" => "left"),
                 "CLIENTE"             => Array("width" => "225", "justification" => "left"),
                 "RUT"                 => Array("width" => "75", "justification" => "left"),
                 "PAGADO"              => Array("width" => "50", "justification" => "center"),
                 "NETO"                => Array("width" => "75", "justification" => "right"),
                 "IVA"                 => Array("width" => "75", "justification" => "right"),
                 "TOTAL"               => Array("width" => "75", "justification" => "right")));

   if(count($_SESSION["STATS"][$_sesmodulename]["INVCDATA"]))
   {
      $counter = 0;
      $pdf->ezText("<b>FACTURAS</b>", 11);
      $pdf->ezText(" ", 8);
      foreach($_SESSION["STATS"][$_sesmodulename]["INVCDATA"] AS $sqlrow)
      {
         $data[$counter]["FACTURA"]             = $sqlrow["invc_docnumber"];
         $data[$counter]["FECHA"]               = $sqlrow["invc_date"];
         $data[$counter]["CLIENTE"]             = $sqlrow["cust_company"];
         $data[$counter]["RUT"]                 = $sqlrow["cust_rut"];
         $data[$counter]["PAGADO"]              = $sqlrow["paystate"];
         $data[$counter]["NETO"]                = $sqlrow["tran_netto"];
         $data[$counter]["IVA"]                 = $sqlrow["invc_total_taxes"];
         $data[$counter]["TOTAL"]               = $sqlrow["invc_total_brutto"];

         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
      $attr = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "715", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "x1"                  => Array("width" => "490", "justification" => "left"),
                 "NETO"                => Array("width" => "75", "justification" => "right"),
                 "IVA"                 => Array("width" => "75", "justification" => "right"),
                 "TOTAL"               => Array("width" => "75", "justification" => "right")));
                 
      $counter = 0;
      unset($data);
      $data[0]["x1"]    = "<b>TOTAL FACTURAS</b>";
      $data[0]["NETO"]  = $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["tran_netto"];
      $data[0]["IVA"]   = $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["invc_total_taxes"];
      $data[0]["TOTAL"] = $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["invc_total_brutto"];
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "NOTA"             => Array("width" => "80", "justification" => "left"),
                 "FECHA"               => Array("width" => "60", "justification" => "left"),
                 "CLIENTE"             => Array("width" => "225", "justification" => "left"),
                 "RUT"                 => Array("width" => "75", "justification" => "left"),
                 "PAGADO"              => Array("width" => "50", "justification" => "center"),
                 "NETO"                => Array("width" => "75", "justification" => "right"),
                 "IVA"                 => Array("width" => "75", "justification" => "right"),
                 "TOTAL"               => Array("width" => "75", "justification" => "right")));
   for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
   {
      if(count($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"]))
      {
         $counter = 0;

         if($xnotetype == 1)
            $addstr = "MENOS";
         else
            $addstr = "MAS";

         if((int)$execmode)
            $addstr = "";
            
         $pdf->ezText("<b>{$addstr} ".$_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][0]["sectitle"]."</b>", 11);
         $pdf->ezText(" ", 8);
      
         unset($data);
         foreach($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"] AS $sqlrow)
         {
            $data[$counter]["NOTA"]                = $sqlrow["invc_docnumber"];
            $data[$counter]["FECHA"]               = $sqlrow["invc_date"];
            $data[$counter]["CLIENTE"]             = $sqlrow["cust_company"];
            $data[$counter]["RUT"]                 = $sqlrow["cust_rut"];
            $data[$counter]["PAGADO"]              = $sqlrow["paystate"];
            $data[$counter]["NETO"]                = $sqlrow["tran_netto"];
            $data[$counter]["IVA"]                 = $sqlrow["invc_total_taxes"];
            $data[$counter]["TOTAL"]               = $sqlrow["invc_total_brutto"];

            $counter++;
         }
         $pdf->ezTable($data,$type,$dummy,$attr);
         $pdf->ezText(" ", 11);
         $attr = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                       "width" => "715", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                       "rowGap" => 2, "colGap" => 3, "cols" => Array(
                       "x1"                  => Array("width" => "490", "justification" => "left"),
                       "NETO"                => Array("width" => "75", "justification" => "right"),
                       "IVA"                 => Array("width" => "75", "justification" => "right"),
                       "TOTAL"               => Array("width" => "75", "justification" => "right")));
                       
            $counter = 0;
            unset($data);
            $data[0]["x1"]    = "<b>TOTAL {$_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][0]["sectitle"]}</b>";
            $data[0]["NETO"]  = $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["tran_netto"];
            $data[0]["IVA"]   = $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["invc_total_taxes"];
            $data[0]["TOTAL"] = $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["invc_total_brutto"];
            $pdf->ezTable($data,$type,$dummy,$attr);
            $pdf->ezText(" ", 11);
      }
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "GUIA"               => Array("width" => "80", "justification" => "left"),
                 "FECHA"               => Array("width" => "70", "justification" => "left"),
                 "CLIENTE"             => Array("width" => "245", "justification" => "left"),
                 "RUT"                 => Array("width" => "95", "justification" => "left"),
                 "NETO"                => Array("width" => "75", "justification" => "right"),
                 "NO FACTURA"          => Array("width" => "75", "justification" => "right"),
                 "FECHA FACTURA"       => Array("width" => "75", "justification" => "right")));

   if(count($_SESSION["STATS"][$_sesmodulename]["DLVDATA"]))
   {
      unset($data);
      $counter = 0;
      $pdf->ezText("<b>GUIAS DE DESPACHO</b>", 11);
      $pdf->ezText(" ", 8);
      foreach($_SESSION["STATS"][$_sesmodulename]["DLVDATA"] AS $sqlrow)
      {
         $data[$counter]["GUIA"]                = $sqlrow["invc_docnumber"];
         $data[$counter]["FECHA"]               = $sqlrow["invc_date"];
         $data[$counter]["CLIENTE"]             = $sqlrow["cust_company"];
         $data[$counter]["RUT"]                 = $sqlrow["cust_rut"];
         $data[$counter]["NETO"]                = $sqlrow["tran_netto"];
         $data[$counter]["NO FACTURA"]          = $sqlrow["invc_total_taxes"];
         $data[$counter]["FECHA FACTURA"]       = $sqlrow["invc_total_brutto"];

         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
      $attr = Array("showHeadings" => 0, "shaded" => 0, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "x1"                  => Array("width" => "640", "justification" => "left"),
                 "NETO"                => Array("width" => "75", "justification" => "right")));
                 
      $counter = 0;
      unset($data);
      $data[0]["x1"]    = "<b>TOTAL GUIAS</b>";
      $data[0]["NETO"]  = $_SESSION["STATS"][$_sesmodulename]["TDLVDATA"]["tran_netto"];
      $pdf->ezTable($data,$type,$dummy,$attr);
      $pdf->ezText(" ", 11);
   }

   if(count($_SESSION["STATS"][$_sesmodulename]["TOTALS"]) && !(int)$execmode)
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "protectRows" => 99, "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 " "                   => Array("width" => "415", "justification" => "left"),
                 "CANTIDAD"            => Array("width" => "75", "justification" => "center"),
                 "NETO"                => Array("width" => "75", "justification" => "right"),
                 "IVA"                 => Array("width" => "75", "justification" => "right"),
                 "TOTAL"               => Array("width" => "75", "justification" => "right")));
                 
      $counter = 0;
      $pdf->ezText("<b>TOTALES</b>", 11);
      $pdf->ezText(" ", 8);
      unset($data);
      foreach($_SESSION["STATS"][$_sesmodulename]["TOTALS"] AS $sqlrow)
      {
         $data[$counter][" "]          = $sqlrow["NAME"];
         $data[$counter]["CANTIDAD"]   = $sqlrow["count"];
         $data[$counter]["NETO"]       = $sqlrow["res_total_netto"];
         $data[$counter]["IVA"]        = $sqlrow["res_total_taxes"];
         $data[$counter]["TOTAL"]      = $sqlrow["res_total_brutto"];

         $counter++;
      }
      $pdf->ezTable($data,$type,$dummy,$attr);
   }

   //----------------------------------------------------------------------------------
   $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 12, 
                   "showLines" => 0, "rowGap" => 0, "colGap" => 0,
                   "cols" => Array (
                   "x1" => Array("width" => "715", "justification" => "left")));

   //----------------------------------------------------------------------------------
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
   }
   
   //----------------------------------------------------------------------------------
   $idxmonth   = (int)$_SESSION[$_sesmodulename]["sql_month1"] -1;
   $idxmonth   = strtoupper($_LANG["MODULE"]["CAL"][$idxmonth]);
   $idxyear    = (int)$_SESSION[$_sesmodulename]["sql_year1"];

   $addstr     = "";
   $idxmonth2  = (int)$_SESSION[$_sesmodulename]["sql_month2"] -1;
   $idxmonth2  = strtoupper($_LANG["MODULE"]["CAL"][$idxmonth2]);
   $idxyear2   = (int)$_SESSION[$_sesmodulename]["sql_year2"];
   if($idxmonth2 != $idxmonth || $idxyear != $idxyear2)
      $addstr = "- {$idxmonth2} {$idxyear2} ";

   if(!(int)$execmode)
      $HEADER["DATA"][$pcounter]["x1"] = "<b>LIBRO DE VENTAS {$idxmonth} {$idxyear} {$addstr}- ".$company["company_short"]."</b>";
   else
      $HEADER["DATA"][$pcounter]["x1"] = "<b>MOVIMIENTOS CLIENTES</b>";
      
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

//----------------------------------------------------------------------------------
function doc_createStatsBooksBuying($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbooksbuying";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "CORRELATIVO"         => Array("width" => "60", "justification" => "left"),
                 "FACTURA"             => Array("width" => "70", "justification" => "left"),
                 "COD. CONT."          => Array("width" => "70", "justification" => "left"),
                 "FECHA"               => Array("width" => "50", "justification" => "left"),
                 "PROVEEDOR"           => Array("width" => "145", "justification" => "left"),
                 "RUT"                 => Array("width" => "60", "justification" => "left"),
                 "EXENTO"              => Array("width" => "65", "justification" => "right"),
                 "NETO"                => Array("width" => "65", "justification" => "right"),
                 "IVA"                 => Array("width" => "65", "justification" => "right"),
                 "TOTAL"               => Array("width" => "65", "justification" => "right")));

   if(count($_SESSION["STATS"][$_sesmodulename]["INVCDATA"]))
   {
      $counter = 0;

      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["INVCDATA"]) AS $doctypetitle)
      {
         $pdf->ezText("<b>{$doctypetitle}</b>", 11);
         $pdf->ezText(" ", 8);
         unset($data);
         foreach($_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$doctypetitle] AS $sqlrow)
         {
            $data[$counter]["CORRELATIVO"]         = $sqlrow["gesnum"];
            $data[$counter]["FACTURA"]             = $sqlrow["invc_docnumber"];
            $data[$counter]["COD. CONT."]          = $sqlrow["cc_code"];
            $data[$counter]["FECHA"]               = $sqlrow["invc_date"];
            $data[$counter]["PROVEEDOR"]           = $sqlrow["supp_company"];
            $data[$counter]["RUT"]                 = $sqlrow["supp_rut"];
            $data[$counter]["EXENTO"]              = $sqlrow["invc_total_taxes_exclude"];
            $data[$counter]["NETO"]                = $sqlrow["tran_netto"];
            $data[$counter]["IVA"]                 = $sqlrow["invc_total_taxes"];
            $data[$counter]["TOTAL"]               = $sqlrow["invc_total_brutto"];

            $counter++;
         }
         $pdf->ezTable($data,$type,$dummy,$attr);
         $pdf->ezText(" ", 11);
      }
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "CORRELATIVO"         => Array("width" => "60", "justification" => "left"),
                 "NOTA"                => Array("width" => "70", "justification" => "left"),
                 "COD. CONT."          => Array("width" => "70", "justification" => "left"),
                 "FECHA"               => Array("width" => "50", "justification" => "left"),
                 "PROVEEDOR"           => Array("width" => "145", "justification" => "left"),
                 "RUT"                 => Array("width" => "60", "justification" => "left"),
                 "EXENTO"              => Array("width" => "65", "justification" => "right"),
                 "NETO"                => Array("width" => "65", "justification" => "right"),
                 "IVA"                 => Array("width" => "65", "justification" => "right"),
                 "TOTAL"               => Array("width" => "65", "justification" => "right")));
                 
   for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
   {
      if(count($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"]))
      {
         $counter = 0;

         unset($data);
         foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"]) AS $doctypetitle)
         {
            $pdf->ezText("<b>{$doctypetitle}</b>", 11);
            $pdf->ezText(" ", 8);
            foreach($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$doctypetitle] AS $sqlrow)
            {
               $data[$counter]["CORRELATIVO"]         = $sqlrow["gesnum"];
               $data[$counter]["NOTA"]                = $sqlrow["invc_docnumber"];
               $data[$counter]["COD. CONT."]          = $sqlrow["cc_code"];
               $data[$counter]["FECHA"]               = $sqlrow["invc_date"];
               $data[$counter]["PROVEEDOR"]           = $sqlrow["supp_company"];
               $data[$counter]["RUT"]                 = $sqlrow["supp_rut"];
               $data[$counter]["EXENTO"]              = $sqlrow["invc_total_taxes_exclude"];
               $data[$counter]["NETO"]                = $sqlrow["tran_netto"];
               $data[$counter]["IVA"]                 = $sqlrow["invc_total_taxes"];
               $data[$counter]["TOTAL"]               = $sqlrow["invc_total_brutto"];

               $counter++;
            }
            $pdf->ezTable($data,$type,$dummy,$attr);
            $pdf->ezText(" ", 11);
         }
      }
   }
   if(count($_SESSION["STATS"][$_sesmodulename]["TOTAL"]))
   {
      $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 " "                   => Array("width" => "455", "justification" => "left"),
                 "EXTENTO"             => Array("width" => "65", "justification" => "right"),
                 "NETO"                => Array("width" => "65", "justification" => "right"),
                 "IVA"                 => Array("width" => "65", "justification" => "right"),
                 "TOTAL"               => Array("width" => "65", "justification" => "right")));
                 
      $counter = 0;
      $pdf->ezText("<b>TOTALES</b>", 11);
      $pdf->ezText(" ", 8);
      unset($data);

      $numberlim = $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["numberlim"];
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"]) AS $factitle)
      {
         $data[$counter][" "]       = $factitle;
         $data[$counter]["EXTENTO"] = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_taxes_exclude"], $numberlim);
         $data[$counter]["NETO"]    = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_netto"], $numberlim);
         $data[$counter]["IVA"]     = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_taxes"], $numberlim);
         $data[$counter]["TOTAL"]   = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_brutto"], $numberlim);

         $counter++;
      }
      for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
      {
         foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype]) AS $factitle)
         {
            $data[$counter][" "]       = $factitle;
            $data[$counter]["EXTENTO"] = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_taxes_exclude"], $numberlim);
            $data[$counter]["NETO"]    = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_netto"], $numberlim);
            $data[$counter]["IVA"]     = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_taxes"], $numberlim);
            $data[$counter]["TOTAL"]   = printPrice($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_brutto"], $numberlim);

            $counter++;
         }
      }
      $data[$counter][" "]       = "<b>TOTAL GENERAL</b>";
      $data[$counter]["EXTENTO"] = "<b>".printPrice($_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_taxes_exclude"], $numberlim)."</b>";
      $data[$counter]["NETO"]    = "<b>".printPrice($_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_netto"], $numberlim)."</b>";
      $data[$counter]["IVA"]     = "<b>".printPrice($_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_taxes"], $numberlim)."</b>";
      $data[$counter]["TOTAL"]   = "<b>".printPrice($_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_brutto"], $numberlim)."</b>";

      $pdf->ezTable($data,$type,$dummy,$attr);
   }

   //----------------------------------------------------------------------------------
   $HEADER["ATTR"] = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 12, 
                   "showLines" => 0, "rowGap" => 0, "colGap" => 0,
                   "cols" => Array (
                   "x1" => Array("width" => "715", "justification" => "left")));


   //----------------------------------------------------------------------------------
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
   }
   
   //----------------------------------------------------------------------------------
   $idxmonth   = (int)$_SESSION[$_sesmodulename]["sql_month1"] -1;
   $idxmonth   = strtoupper($_LANG["MODULE"]["CAL"][$idxmonth]);
   $idxyear    = (int)$_SESSION[$_sesmodulename]["sql_year1"];

   $addstr     = "";
   $idxmonth2  = (int)$_SESSION[$_sesmodulename]["sql_month2"] -1;
   $idxmonth2  = strtoupper($_LANG["MODULE"]["CAL"][$idxmonth2]);
   $idxyear2   = (int)$_SESSION[$_sesmodulename]["sql_year2"];
   if($idxmonth2 != $idxmonth || $idxyear != $idxyear2)
      $addstr = "- {$idxmonth2} {$idxyear2} ";
   
   $HEADER["DATA"][$pcounter]["x1"] = "<b>LIBRO DE COMPRAS {$idxmonth} {$idxyear} {$addstr}- ".$company["company_name"]."</b>";

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

//-------------------------------------------------------------------------------------
function doc_createAdminDeposite($CON, $datasql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "createadmindeposite";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   
   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "750", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap"  => 3, "cols" => Array(
                 "USUARIO"                => Array("width" => "100", "justification" => "left"),
                 "CLIENTE"                => Array("width" => "90", "justification" => "left"),
                 "FECHA"                  => Array("width" => "45", "justification" => "left"),
                 "N. FACTURA"             => Array("width" => "130", "justification" => "left"),
                 "N. NOTA CREDITO"        => Array("width" => "85", "justification" => "left"),
                 "N. NOTA DEBITO"         => Array("width" => "85", "justification" => "left"),
                 "BANCO"                  => Array("width" => "100", "justification" => "left"),
                 "TOTAL PAGO"             => Array("width" => "55", "justification" => "right"),
                 "ESTADO"                 => Array("width" => "65", "justification" => "left")));

   $counter = 0;

   $res = $CON->select($datasql);

   $total = 0;

   foreach($res AS $sqlrow)
   {
      $data[$counter]["USUARIO"]         = strtoupper($sqlrow["user_name"]);
      $data[$counter]["CLIENTE"]         = $sqlrow["cust_name"];
      $data[$counter]["FECHA"]           = date("d.m.Y",$sqlrow["adm_deposit_status_date"]);
      $data[$counter]["N. FACTURA"]      = getAdminDepositEnvoicesPrint($sqlrow["id"]);
      $data[$counter]["N. NOTA CREDITO"] = getAdminDepositCreditPrint($sqlrow["id"]);
      $data[$counter]["N. NOTA DEBITO"]  = getAdminDepositDebitPrint($sqlrow["id"]);
      $data[$counter]["BANCO"]           = $sqlrow["bank_name"];
      $data[$counter]["TOTAL PAGO"]      = printPrice($sqlrow["adm_amount"]);
      $total += $sqlrow["adm_amount"];

      if($sqlrow["adm_deposit_status"] == 1)
         $estado = "POR DEPOSITAR";
      elseif($sqlrow["adm_deposit_status"] == 2)
         $estado = "DEPOSITADO";
      else
         $estado = "DESCONOCIDO";
      
      $data[$counter]["ESTADO"] = $estado;
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
   $HEADER["DATA"][$pcounter]["x2"] = "DEPOSITOS GESTIï¿½N";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>TOTAL $</b>";
   $HEADER["DATA"][$pcounter]["x2"] = printPrice($total).".-";
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//-------------------------------------------------------------------------------------------
function doc_createAdminPayments($CON, $datasql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "createadminpayment";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   
   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "730", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap"  => 3, "cols" => Array(
                 "ITEM"                => Array("width" => "200", "justification" => "left"),
                 "FECHA"               => Array("width" => "100", "justification" => "left"),
                 "MONTO"               => Array("width" => "100", "justification" => "right"),
                 "OBSERVACIONES"       => Array("width" => "320", "justification" => "left")));

   $counter = 0;

   $res   = $CON->select($datasql);
   $total = 0;
   foreach($res AS $sqlrow)
   {
      $data[$counter]["ITEM"]           = $sqlrow["acc_name"];
      $data[$counter]["FECHA"]          = date("d.m.Y",$sqlrow["adm_date"]);
      $data[$counter]["MONTO"]          = printPrice($sqlrow["adm_amount"]);
      $data[$counter]["OBSERVACIONES"]  = $sqlrow["adm_notes"];

      $total += $sqlrow["adm_amount"];
      $counter++;
   }
   $data[$counter]["ITEM"]  = "<b>TOTAL</b>";     
   $data[$counter]["MONTO"] = "<b>". printPrice($total). "</b>"; 

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
   $HEADER["DATA"][$pcounter]["x2"] = "GESTIï¿½N PAGOS";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "";
   $HEADER["DATA"][$pcounter]["x2"] = "";
      
   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>EMPRESA</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_company"])
   {
      $sql = " select *
               from company_data
               where
               id = {$_SESSION[$_sesmodulename]["sql_company"]}";
      $company = $CON->select($sql);
      $company = $company[0];
      $HEADER["DATA"][$pcounter]["x4"] = $company["company_short"];
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

//----------------------------------------------------------------------------------
function doc_createItemBuyPriceCompare($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "price_buying_compare";
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
                 "ARTï¿½CULO"               => Array("width" => "150", "justification" => "left"),
                 "CODIGO/PROV"            => Array("width" => "60", "justification" => "left"),
                 "UNIDAD"                 => Array("width" => "40", "justification" => "left"),
                 "PROVEEDOR"              => Array("width" => "120", "justification" => "left"),
                 "PRECIO/ACTUAL\nBASICO"     => Array("width" => "40", "justification" => "left"),
                 "PRECIO/ACTUAL\nFINAL"      => Array("width" => "40", "justification" => "left"),
                 "FECHA\nACTUALIZ."          => Array("width" => "45", "justification" => "left"),
                 "PRECIO/ANTIGUO\nBASICO"    => Array("width" => "40", "justification" => "left"),
                 "PRECIO/ANTIGUO\nFINAL"     => Array("width" => "40", "justification" => "left"),
                 "PRECIO\nACTUALIZ"       => Array("width" => "45", "justification" => "left"),
                 "DIF/$"                  => Array("width" => "30", "justification" => "left"),
                 "DIF/%"                  => Array("width" => "30", "justification" => "left")));

   $counter = 0;
   foreach($items AS $sqlrow)
   {
      $data[$counter]["NUMERO"]              = $sqlrow["item_number_prod"];
      $data[$counter]["ARTï¿½CULO"]            = $sqlrow["item_title"];
      $data[$counter]["CODIGO/PROV"]         = $sqlrow["item_code"];
      $data[$counter]["UNIDAD"]              = $sqlrow["unitdesc"];
      $data[$counter]["PROVEEDOR"]           = $sqlrow["supp_company"];
      $data[$counter]["PRECIO/ACTUAL\nBASICO"]     = $sqlrow["item_costprice_netto"];
      $data[$counter]["PRECIO/ACTUAL\nFINAL"]      = $sqlrow["item_final_netto_cur"];
      $data[$counter]["FECHA\nACTUALIZ."]          = $sqlrow["prc_crtdat_cur"];
      $data[$counter]["PRECIO/ANTIGUO\nBASICO"]    = $sqlrow["last_costprice_netto"];
      $data[$counter]["PRECIO/ANTIGUO\nFINAL"]     = $sqlrow["item_final_netto_las"];
      $data[$counter]["PRECIO\nACTUALIZ"]    = $sqlrow["prc_crtdat_las"];
      $data[$counter]["DIF/$"]               = $sqlrow["prcdiff"];            
      $data[$counter]["DIF/%"]               = $sqlrow["prcperc"];            

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
   $HEADER["DATA"][$pcounter]["x2"] = "COMPARAR PRECIOS DE COMPRA HIST.";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>ARTï¿½CULO</b>";
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

//----------------------------------------------------------------------------------
function doc_createStatsBuyInvoicesTotal($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buy_invoices_sum";
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
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "PROVEEDOR"     => Array("width" => "260", "justification" => "left"),
                 "MONTO DOCTO."  => Array("width" => "150", "justification" => "left"),
                 "MONTO REAL"    => Array("width" => "150", "justification" => "left"),
                 "DEUDA"         => Array("width" => "150", "justification" => "left")));

   $counter = 0;
   foreach($items AS $sqlrow)
   {
      $data[$counter]["PROVEEDOR"]    = $sqlrow["name"];
      $data[$counter]["MONTO DOCTO."] = $sqlrow["invc_total_brutto"];
      $data[$counter]["MONTO REAL"]   = $sqlrow["invc_real"];
      $data[$counter]["DEUDA"]        = $sqlrow["nopayed"];

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
   $HEADER["DATA"][$pcounter]["x2"] = "SUMATORIA DEUDAS POR PROVEEDOR";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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

//----------------------------------------------------------------------------------
function doc_createStatsRebatesHistory($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "rebates_hist";
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
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   //----------------------------------------------------------------------------------
   unset($data);
   $data = Array();

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "PROVEEDOR"        => Array("width" => "160", "justification" => "left"),
                 "NOMBRE REBATE"    => Array("width" => "140", "justification" => "left"),
                 "TIPO"             => Array("width" => "60", "justification" => "left"),
                 "MES"              => Array("width" => "40", "justification" => "left"),
                 "Aï¿½O"              => Array("width" => "40", "justification" => "left"),
                 "REBATE CALC"      => Array("width" => "70", "justification" => "left"),
                 "FACTURA"          => Array("width" => "70", "justification" => "left"),
                 "FECHA FACTURA"    => Array("width" => "70", "justification" => "left"),
                 "MONTO FACTURA"    => Array("width" => "70", "justification" => "left")

                 ));

   $counter = 0;
   foreach($items AS $sqlrow)
   {
      $data[$counter]["PROVEEDOR"]     = $sqlrow["supp_short"];
      $data[$counter]["NOMBRE REBATE"] = $sqlrow["mark_name"];
      $data[$counter]["TIPO"]          = $sqlrow["type"];
      $data[$counter]["MES"]           = $sqlrow["month"];
      $data[$counter]["Aï¿½O"]           = $sqlrow["mark_year"];
      $data[$counter]["REBATE CALC"]   = $sqlrow["rebate_value"];
      $data[$counter]["FACTURA"]       = $sqlrow["rebate_invc"];
      $data[$counter]["FECHA FACTURA"] = $sqlrow["invc_date"];
      $data[$counter]["MONTO FACTURA"] = $sqlrow["invc_total_netto"];

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
   $HEADER["DATA"][$pcounter]["x2"] = "HISTORIA DE REBATES";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
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

   //----------------------------------------------------------------------------------
   $pcounter++;
   $HEADER["DATA"][$pcounter]["x1"] = "<b>Aï¿½O</b>";
   $HEADER["DATA"][$pcounter]["x2"] = $_SESSION[$_sesmodulename]["sql_year1"];

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

//----------------------------------------------------------------------------------
function doc_createItemPriceHistProd($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "price_history_prodbuy";
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
                 "PROVEEDOR"        => Array("width" => "280", "justification" => "left"),
                 "FECHA"            => Array("width" => "80", "justification" => "left"),
                 "USUARIO"          => Array("width" => "110", "justification" => "left"),
                 "PRECIO/BASICO"    => Array("width" => "70", "justification" => "left"),
                 "DESC/$"           => Array("width" => "60", "justification" => "left"),
                 "DESC/%"           => Array("width" => "60", "justification" => "left"),
                 "PRECIO/FINAL"     => Array("width" => "60", "justification" => "left")));

   $counter = 0;
   foreach($items AS $sqlrow)
   {
      $data[$counter]["PROVEEDOR"]        = $sqlrow["supp_company"];
      $data[$counter]["FECHA"]            = $sqlrow["prc_crtdat"];
      $data[$counter]["USUARIO"]          = $sqlrow["user_lastname"];
      $data[$counter]["PRECIO/BASICO"]    = $sqlrow["prc_costprice_netto"];
      $data[$counter]["DESC/$"]           = $sqlrow["diffa"];
      $data[$counter]["DESC/%"]           = $sqlrow["diffp"];
      $data[$counter]["PRECIO/FINAL"]     = $sqlrow["buyval"];

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
   $HEADER["DATA"][$pcounter]["x2"] = "HISTORIA PRECIOS DE COMPRA";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x1"] = "<b>ARTï¿½CULO</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_item_id"])
      $HEADER["DATA"][$pcounter]["x2"] = "{$item["item_number_prod"]} {$item["item_title"]} ({$unitdesc})";
   else
      $HEADER["DATA"][$pcounter]["x2"] = "TODO";

   //----------------------------------------------------------------------------------
   $HEADER["DATA"][$pcounter]["x3"] = "<b>PROVEEDOR</b>";
   if((int)$_SESSION[$_sesmodulename]["sql_supplier"])
   {
      $sql = " select *
               from supplier
               where
               id = {$_SESSION[$_sesmodulename]["sql_supplier"]}";
      $supplier = $CON->select($sql);
      $supplier = $supplier[0];
      $HEADER["DATA"][$pcounter]["x4"] = $supplier["supp_company"];
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
//----------------------------------------------------------------------------------
function doc_createStatsColcacion($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodcolacion";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $attr = Array("showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                 "width" => "710", "xpos" => "left", "showLines" => 2, "fontSize" => 7,
                 "rowGap" => 2, "colGap" => 3, "cols" => Array(
                 "Inicio de Turno"         => Array("width" => "50", "justification" => "center"),
                 "Fin del Turno"           => Array("width" => "40", "justification" => "center"),
                 "Codigo Turno"            => Array("width" => "70", "justification" => "center"),
                 "Maquina"                 => Array("width" => "70", "justification" => "left"),
                 "Rut Colaborador"         => Array("width" => "70", "justification" => "center"),
                 "Codigo Colaborador"      => Array("width" => "45", "justification" => "left"),
                 "Nombre Colaborador"      => Array("width" => "80", "justification" => "left"),
                 "Inicio Colacion"         => Array("width" => "45", "justification" => "left"),
                 "Termino Colacion"        => Array("width" => "45", "justification" => "left"),
                 "Tiempo Real Colacion"    => Array("width" => "45", "justification" => "left"),
                  ));

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "landscape", "list");

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $row)
   {
      $data[$counter]["Inicio de Turno"]       = $row["FechaInicioTurno"];
      $data[$counter]["Fin del Turno"]         = $row["FechaFinTurno"];
      $data[$counter]["Codigo Turno"]          = $row["CodigoTurno"];
      $data[$counter]["Maquina"]               = $row["NombreMaquina"];
      $data[$counter]["Rut Colaborador"]       = $row["RutColaborador"];
      $data[$counter]["Codigo Colaborador"]    = $row["CodigoColaborador"];
      $data[$counter]["Nombre Colaborador"]    = $row["NombreColaborador"];
      $data[$counter]["Inicio Colacion"]       = $row["FechaInicioColacion"];
      $data[$counter]["Termino Colacion"]      = $row["FechaTerminoColacon"];
      $data[$counter]["Tiempo Real Colacion"]  = $row["TiempoReal"];
      $counter++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 11);

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
   $HEADER["DATA"][$pcounter]["x2"] = "COLACIONES";
   $HEADER["DATA"][$pcounter]["x3"] = "<b>FECHA</b>";
   $HEADER["DATA"][$pcounter]["x4"] = displaydate($currtme);
   $pcounter++;

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


//----------------------------------------------------------------------------------
function doc_createViewFacturas($CON, $orderid)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;
   //----------------------------------------------------------------------------------
   $sql = " select t1.*
                 , t4.company_short
                 , t5.shop_name
                 , t8.trans_name
                 , t9.pay_title
                 , t6.user_firstname 'upd_firstname'
                 , t6.user_lastname 'upd_lastname'
                 , t7.user_firstname 'crt_firstname'
                 , t7.user_lastname 'crt_lastname'
            from invoices_sell  t1
            LEFT OUTER JOIN company_data t4     ON t1.invc_company_id    = t4.id
            LEFT OUTER JOIN company_shops t5    ON t1.invc_shop_id       = t5.id
            LEFT OUTER JOIN user t6             ON t1.invc_updusr        = t6.id
            LEFT OUTER JOIN user t7             ON t1.invc_crtusr        = t7.id
            LEFT OUTER JOIN transports t8       ON t1.invc_transportid   = t8.id
            LEFT OUTER JOIN payments t9         ON t1.invc_paymentid     = t9.id
            where
            t1.id = {$orderid} ";

   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];

   $sql = " select t1.*, t2.country_name, t3.name, t4.nombre
            from customer t1
            LEFT OUTER JOIN country t2 ON t1.cust_countryid = t2.id
            LEFT OUTER JOIN regions t3 ON t1.cust_regionid  = t3.id
            LEFT OUTER JOIN comunas t4 ON t1.cust_comunaid  = t4.id
            where
            t1.id = {$orderheader["invc_cust_id"]}";
   $customer = $CON->select($sql);
   $customer = $customer[0];

   //----------------------------------------------------------------------------------
   $doctype    = "shipments";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".pdf";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   $pdf = prepareDoc($pdffile, "LETTER", "", "order");

   $posdata = getOrderDeliveryPos($CON, $orderid, $_REQUEST["setPosOrder"]);

   //----------------------------------------------------------------------------------
   unset($data);
   $pdf = doc_linedraw($pdf, 30, 540);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                   "fontSize"     => 10, "cols" => Array (
                      "X1"  => Array("width" => "70"),
                      "X2"  => Array("width" => "200"),
                      "X3"  => Array("width" => "70"),
                      "X4"  => Array("width" => "200")));
   $data[0]["X1"] = "<b>Número</b>";
   $data[0]["X2"] = $orderheader["invc_number"];
   $data[0]["X3"] = " ";
   $data[0]["X4"] = " ";

   $data[1]["X1"] = "<b>Empresa</b>";
   $data[1]["X2"] = $orderheader["company_short"];
   $data[1]["X3"] = "<b>Sucursal</b>";
   $data[1]["X4"] = $orderheader["shop_name"];

   $data[2]["X1"] = "<b>Cliente</b>";
   $data[2]["X2"] = $customer["cust_company"];
   $data[2]["X3"] = "<b>RUT</b>";
   $data[2]["X4"] = $customer["cust_rut"];

   $data[3]["X1"] = "<b>Dirección</b>";
   $data[3]["X2"] = $customer["cust_street"];
   $data[3]["X3"] = "<b>Teléfono</b>";
   $data[3]["X4"] = $customer["cust_phone"];

   $data[4]["X1"] = "<b>Región</b>";
   $data[4]["X2"] = $customer["name"];
   $data[4]["X3"] = "<b>Whatsapp</b>";
   $data[4]["X4"] = $customer["cust_fax"];

   $data[5]["X1"] = "<b>Comuna</b>";
   $data[5]["X2"] = $customer["nombre"];
   $data[5]["X3"] = "<b>Email</b>";
   $data[5]["X4"] = $customer["cust_email"];

   $data[6]["X1"] = "<b>País</b>";
   $data[6]["X2"] = $customer["country_name"];
   $data[6]["X3"] = $orderid;
   $data[6]["X4"] = "prueba";

   $pdf->ezTable($data,$type,$dummy,$attr);

   // $pdf->ezText($headdata["dlv_annotation"], 11);
   $pdf->ezText("", 11);

   $pdf = doc_linedraw($pdf, 30, 540);
   $pdf->ezText(" ", 11);
   //----------------------------------------------------------------------------------

   //----------------------------------------------------------------------------------
   unset($data);
   $attr = Array  (  "showHeadings" => 1, "shaded" => 1, "shadeCol" => Array(0.95,0.95,0.95),
                     "xpos" => "left", "showLines" => 2, "rowGap" => 2, "colGap" => 3, "fontSize"=> 8,
                     "cols" =>   Array (
                     "NUM."          => Array("width" => "50", "justification" => "left"),
                     "ARTICULO"      => Array("width" => "255", "justification" => "left"),
                     "PROV.\nCOD."   => Array("width" => "50", "justification" => "left"),
                     "UNIDAD"        => Array("width" => "50", "justification" => "center"),
                     "PEND."         => Array("width" => "40", "justification" => "center"),
                     "CANT."         => Array("width" => "40", "justification" => "center"),
                     "PRECIO (NETO)" => Array("width" => "55", "justification" => "right")
                     )
                  );

   $x = 0;
   foreach($posdata AS $row)
   {
      $data[$x]["NUM."]           = $row["item_number_prod"];
      $data[$x]["ARTICULO"]       = $row["item_title"];
      $data[$x]["PROV.\nCOD."]    = $row["item_code"];
      $data[$x]["UNIDAD"]         = getItemUnitDesc($CON, $row["item_id"], $row["item_type"]);
      $data[$x]["PEND."]          = printPrice($row["item_amount"]);
      $data[$x]["CANT."]          = printPrice($row["item_amount_shipped"]);
      $data[$x]["PRECIO (NETO)"]  = printPrice($row["item_sellprice_netto"],4);
      $x++;
   }
   $pdf->ezTable($data,$type,$dummy,$attr);
   $pdf->ezText(" ", 12);

   unset($data);
   $pcounter = 0;

   //----------------------------------------------------------------------------------
   $pdf = printPDFFooter($CON, $pdf, $orderheader["invc_company_id"], "vi_factura", $orderheader["invc_number"]);
   // $pdf = printPDFFooter($CON, $pdf, $orderheader["dlv_company_id"], "", 1);

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