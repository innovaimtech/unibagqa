<?php

global $_FRM;

function xls_createStatsStockchangesUnibag($CON)
{
   global $_LANG;
   global $_sesmodulename;

   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $doctype    = "item_sths_unibag";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "Folio",
      "Fecha",
      "Sucursal origen",
      "Bodega origen",
      "Sucursal destino",
      "Bodega destino",
      "Código",
      "Producto",
      "Cantidad",
      "Creador",
      "Aprobador",
      "Observaciones");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 14);
   $xls->set_column(0, 3, 20);
   $xls->set_column(0, 4, 14);
   $xls->set_column(0, 5, 20);
   $xls->set_column(0, 6, 14);
   $xls->set_column(0, 7, 25);
   $xls->set_column(0, 8, 10);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 12);
   $xls->set_column(0, 11, 20);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["strc_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["strc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shop_name_orig"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["st_name_orig"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shop_name_dest"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["st_name_dest"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, (float)getPrice($sqlrow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_crt"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_aprob"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["strc_desc"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}


function xls_createStatsConsumoMateriales($CON)
{
   global $_LANG;
   global $_sesmodulename;
   
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $doctype    = "prod_consumo";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "Material",
      "Código",
      "Unidad",
      "N° CC",
      "Cliente",
      "RUT",
      "Transacción",
      "Fecha",
      "Máquina",
      "Cantidad",
      "Descripción");
   $xls->set_column(0, 0, 40);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 35);
   $xls->set_column(0, 5, 14);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 25);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"]." ", $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"]." ", $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["stk_num"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["stk_bookdate"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["equipo_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, (float)$sqlrow["item_amount"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["stk_annotation"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsTrazaCC($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodtraza";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "N° CC",
      "Impresor",
      "Total mts/lineales programados",
      "Seudónimo clisés",
      "Fecha creación montaje",
      "Cilindro (z)",
      "Desarrollo de impresión",
      "Ancho rollo tela",
      "Metros lineales impresos",
      "% Merma",
      "Diseñador responsable");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 18);
   $xls->set_column(0, 2, 27);
   $xls->set_column(0, 3, 25);
   $xls->set_column(0, 4, 21);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 20);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 22);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 20);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["mlin_user"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["mlin_prog"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["seudonimo"]." ", $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["recep_dat"]." ", $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["corte_z"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["devprints_cc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, (int)$sqlrow["tela_width"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["mlin_impresos"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["mermaperc"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["designername"]." ", $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsSolicsCC($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodsolicscc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "N° CC",
      "Ingreso",
      "Vendedor",
      "Seudonimo",
      "Tipo bolsa",
      "Medida bolsa",
      "Tipo tela",
      "Tipo Impresión",
      "Impreso en",
      "Lados impresión",
      "Color tela",
      "Color manillas",
      "Color 1",
      "Color 2",
      "Color 3",
      "Color 4",
      "Largo manillas",
      "Pie de imprenta",
      "Código de barras",
      "Diseñador responsable");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 30);
   $xls->set_column(0, 9, 14);
   $xls->set_column(0, 10, 16);
   $xls->set_column(0, 11, 16);
   $xls->set_column(0, 12, 14);
   $xls->set_column(0, 13, 14);
   $xls->set_column(0, 14, 14);
   $xls->set_column(0, 15, 14);
   $xls->set_column(0, 16, 12);
   $xls->set_column(0, 17, 13);
   $xls->set_column(0, 18, 14);
   $xls->set_column(0, 19, 16);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_lastname"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_solic_supp_seudonimo"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fab_med_width"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fab_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fab_printtype"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["printsides"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fabric_color"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["manilla_color"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["colors1"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["colors2"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["colors3"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["colors4"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fab_manilla_length"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_dsgnchk_pieimprenta_act"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_dsgnchk_barcode_act"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["designername"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsSellingAreaComercial($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_selling_areacom";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "Nombre del cliente"
      , "RUT"
      , "Comuna"
      , "Region"
      , "F/Pago"
      , "Categoria"
      , "Vendedor"
      , "Email"
      , "Contacto Comercial"
      , "Telefono"
      , "Celular"
      ,"Transportista"
      ,"Familia", "SKU", "Nombre producto", "Gramaje", "Tipo bolsa", "Material", "Medida",
      "Tipo/Doc.",
      "Nro/Doc.",
      "F/Doc.", 
      "F/Creación", 
      "H/Creación", 
      "Cantidad", "Precio/Unit", "\$/Neto total", "IVA", "\$/Total", "Nro CC",
      "F/Entrega/Prod.", "F/Entrega/PTerm.", "Estado", "Sucursal","Canal"
   );
   $xls->set_column(0, 0, 45);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 18);
   $xls->set_column(0, 3, 18);
   $xls->set_column(0, 4, 18);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 20);
   $xls->set_column(0, 7, 15);
   $xls->set_column(0, 8, 40);
   $xls->set_column(0,  9, 15);
   $xls->set_column(0, 10, 15);
   $xls->set_column(0, 11, 40);
   $xls->set_column(0, 12, 15);
   $xls->set_column(0, 13, 12);
   $xls->set_column(0, 14, 40);
   $xls->set_column(0, 15, 10);
   $xls->set_column(0, 16, 13);
   $xls->set_column(0, 17, 10);
   $xls->set_column(0, 18, 10);
   $xls->set_column(0, 19, 12);
   $xls->set_column(0, 20, 12);
   $xls->set_column(0, 21, 12);
   $xls->set_column(0, 22, 10);

   $xls->set_column(0, 23, 12);
   $xls->set_column(0, 24, 12);

   $xls->set_column(0, 25, 12);
   $xls->set_column(0, 26, 12);
   $xls->set_column(0, 27, 12);
   $xls->set_column(0, 28, 12);
   $xls->set_column(0, 29, 12);
   $xls->set_column(0, 30, 16);
   $xls->set_column(0, 31, 16);
   $xls->set_column(0, 32, 13);
   $xls->set_column(0, 33, 16);
   $xls->set_column(0, 34, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["comuna"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["region"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["pay_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_cat_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["sellername"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_email"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_contacto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["telefono"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["celular"], $_FRM["TBLR"]);$colidx++;

      $xls->write_string($rowidx, $colidx, $sqlrow["trans_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cat_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["fab_mat_gramms"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["bolsa_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fab_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["medidas"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["_doctype"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_num"], $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["invc_dat"]))), $_FRM["FECHA"]);$colidx++;
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["creacion"]))), $_FRM["FECHA"]);$colidx++;
      $xls->write($rowidx, $colidx, duracionStrAExcel(date("H:i", $sqlrow["creacion"])), $_FRM["DURACION"]); $colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["unit_price"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["neto_price"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["tax_price"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["brto_price"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["despstamp1"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["despstamp2"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["estadox"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shop_nam"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["canal"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsImportacion($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "importacion";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "N° Contenedor", "Origen", "ETA Puerto", "ETA Unibag", "Forward", "OC", "Fecha",
      "Proveedor", "Código", "Producto", "Cant.OC", "Cant.Cont.", "Cant.Recep", "\$/Unit",
      "\$/Total", "Costo/Adic.", "Costo/Import."
   );

   $compcols = Array(
      "Factura", "Fecha", "Proveedor", "", "", "", "", "", "", "", "", "", "", "", "\$/Neto", "\$/IVA", "\$/Bruto"
   );

   $rowidx = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $contid)
   {
      $xls->set_column(0, 0, 14);
      $xls->set_column(0, 1, 12);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 12);
      $xls->set_column(0, 4, 18);
      $xls->set_column(0, 5, 12);
      $xls->set_column(0, 6, 12);
      $xls->set_column(0, 7, 40);
      $xls->set_column(0, 8, 12);
      $xls->set_column(0, 9, 45);
      $xls->set_column(0, 10, 12);
      $xls->set_column(0, 11, 12);
      $xls->set_column(0, 12, 12);
      $xls->set_column(0, 13, 12);
      $xls->set_column(0, 14, 12);
      $xls->set_column(0, 15, 12);
      $xls->set_column(0, 16, 12);

      //----------------------------------------------------------------------------------
      $counter = 0;
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

      $rowidx++;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$contid] AS $sqlrow)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $sqlrow["sord_contenedor"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["country_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["sord_eta_puerto"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["sord_eta_puertounibag"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["sord_forward"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["sord_number"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["sord_crtdat"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["sord_amount"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_RECEIVEAMT"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_PRICE_UNIT"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_PRICE_UNIT_TOTAL"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_COST_UNIT_TOTAL"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_XTOTAL"]), $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }

      if(count($_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$contid]))
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, "Facturas consideradas como compra de productos", $_FRM["TBLH"]);$colidx++;
         $xls->write_string($rowidx, 16, "", $_FRM["TBLH"]);$colidx++;
         $xls->merge_cells($rowidx, 0, $rowidx, 16);
         $rowidx++;

         //----------------------------------------------------------------------------------
         for($y = 0; $y < count($compcols); $y++)
            $xls->write_string($rowidx, $y, $compcols[$y], $_FRM["TBLH"]);
         $xls->merge_cells($rowidx, 2, $rowidx, 13);
         $rowidx++;

         foreach($_SESSION["STATS"][$_sesmodulename]["_COMPRAS"][$contid] AS $sqlrow)
         {
            $colidx = 0;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
            $xls->merge_cells($rowidx, 2, $rowidx, 13);
            $xls->write_number($rowidx, 14, getPrice($sqlrow["_INVC_NETTO"]), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, 15, getPrice($sqlrow["_INVC_TAXES"]), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, 16, getPrice($sqlrow["_INVC_BRUTTO"]), $_FRM["TBLR"]);$colidx++;
            $rowidx++;
         }
      }

      if(count($_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$contid]))
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, "Facturas consideradas como gastos", $_FRM["TBLH"]);$colidx++;
         $xls->write_string($rowidx, 16, "", $_FRM["TBLH"]);$colidx++;
         $xls->merge_cells($rowidx, 0, $rowidx, 16);
         $rowidx++;

         //----------------------------------------------------------------------------------
         for($y = 0; $y < count($compcols); $y++)
            $xls->write_string($rowidx, $y, $compcols[$y], $_FRM["TBLH"]);
         $xls->merge_cells($rowidx, 2, $rowidx, 13);
         $rowidx++;

         foreach($_SESSION["STATS"][$_sesmodulename]["_GASTOS"][$contid] AS $sqlrow)
         {
            $colidx = 0;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
            $xls->merge_cells($rowidx, 2, $rowidx, 13);
            $xls->write_number($rowidx, 14, getPrice($sqlrow["_INVC_NETTO"]), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, 15, getPrice($sqlrow["_INVC_TAXES"]), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, 16, getPrice($sqlrow["_INVC_BRUTTO"]), $_FRM["TBLR"]);$colidx++;
            $rowidx++;
         }
      }

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsSellingCCs($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_FORMATO;

   //----------------------------------------------------------------------------------
   $doctype    = "ccfms";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   // $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $formatoFecha = $workbook->add_format();  
   $formatoFecha->set_num_format('DD/MM/YYYY');

   $formatoNumero = $workbook->add_format();
   $formatoNumero->set_num_format('#,##0');

   //----------------------------------------------------------------------------------
   $cols = Array(
      "N° CC", "Estado", "N° OC", "Fecha", "RUT",   "Cliente",  "Vendedor", "Código",   "Producto",
      "Familia",  "Material", "Medidas", "Observación Despacho", "Cantidad Programada", "Fecha Despacho","Fecha Solicitud Cliche/Peliculas","Fecha Recepción Cliche/Peliculas", "Cantidad", "\$/Neto", "\$/IVA", "\$/Bruto", "Tipo Documento",  "Número Documento"
   );
   $xls->set_column(0, 0, 12); // ncc
   $xls->set_column(0, 1, 12); // noc
   $xls->set_column(0, 2, 12); // noc
   $xls->set_column(0, 3, 12); // fecha
   $xls->set_column(0, 4, 14); // rut
   $xls->set_column(0, 5, 40); // Cliente
   $xls->set_column(0, 6, 25); // Vendedor
   $xls->set_column(0, 7, 12); // Codigo
   $xls->set_column(0, 8, 40); // Producto
   $xls->set_column(0, 9, 12); // Familia
   $xls->set_column(0, 10, 12); // Material
   $xls->set_column(0, 11, 12); // Medidas
   $xls->set_column(0, 12, 50); // Observacion de Desapachos
   $xls->set_column(0, 13, 20); 
   $xls->set_column(0, 14, 20);
   
   $xls->set_column(0, 15, 27); // Fecha de solicitid de Chiclé/Peliculas
   $xls->set_column(0, 16, 27); // Fecha de Recepción de Chile/Peliculas
   
   $xls->set_column(0, 17, 12); 
   $xls->set_column(0, 18, 12); // Neto
   $xls->set_column(0, 19, 12); // Iva
   $xls->set_column(0, 20, 12); // Bruto
   $xls->set_column(0, 21, 20); // Tipo Documento
   $xls->set_column(0, 22, 20); // Número Documento


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["estado"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_oc_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["req_crtdat"]),$formatoFecha);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["sellername"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cat_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fab_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["medidas"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_despacho_desc"], $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["prodplan_amt"], $formatoNumero);$colidx++;
      if($sqlrow["prodplan_date"] == 0)
      {
          $xls->write($rowidx, $colidx, "" , $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["prodplan_date"]),$formatoFecha);$colidx++;
      }

      if($sqlrow["req_cliche_peli_solic_dat"] == 0)
      {
          $xls->write($rowidx, $colidx, "" , $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["req_cliche_peli_solic_dat"]),$_FRM["FECHAHORA"]);$colidx++;
      }

      if($sqlrow["req_cliche_peli_recep_dat"] == 0)
      {
          $xls->write($rowidx, $colidx, "" , $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["req_cliche_peli_recep_dat"]),$_FRM["FECHAHORA"]);$colidx++;
      }

      $xls->write($rowidx, $colidx, round($sqlrow["item_amount"]), $formatoNumero);$colidx++;
      $xls->write($rowidx, $colidx, round($sqlrow["item_netto"]), $formatoNumero);$colidx++;
      $xls->write($rowidx, $colidx, round($sqlrow["item_taxes"]), $formatoNumero);$colidx++;
      $xls->write($rowidx, $colidx, round($sqlrow["item_brutto"]), $formatoNumero);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["document_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsFabExt($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_fabextern";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   // echo "<pre>";
   // print_r($_SESSION["STATS"][$_sesmodulename]["DATA"]);

   $cols = Array(
      "Fecha C.C.",
      "Nº C.C.",
      "Código",
      "Producto",
      "Cliente",
      "Vendedor",
      "Cantidad",
      "Nº Guia",
      "Fecha Guia"
   );
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 50);
   $xls->set_column(0, 4, 40);
   $xls->set_column(0, 5, 20);
   $xls->set_column(0, 6, 10);
   $xls->set_column(0, 7, 14);
   $xls->set_column(0, 8, 14);

   $rowidx = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $sqlrowidx)
   {
      $data = $_SESSION["STATS"][$_sesmodulename]["DATA"][$sqlrowidx];

      $colidx = 0;
      $xls->write_string($rowidx, 0, $data[0]["_DATA"]["supp_company"], $_FRM["TBLLB"]);
      $xls->write_string($rowidx, 7, "", $_FRM["TBLLB"]);
      $xls->merge_cells($rowidx, 0, $rowidx, 7);

      $xls->write_string($rowidx, 8, $data[0]["_DATA"]["supp_rut"], $_FRM["TBLLB"]);
      $rowidx++;

      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      foreach($data AS $row)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $row["ccdate"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["dlv_externprod_ccnum"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_number_prod"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["cust_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["user_lastname"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($row["item_amount_shipped"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["dlv_docnum"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, date('d.m.Y', $row["dlv_delivery_date"]), $_FRM["TBLR"]);$colidx++;
         $rowidx++;

         foreach($row["_RECEIVES"] AS $sthchgdatarow)
         {
            $colidx = 0;
            $xls->write_string($rowidx, $colidx, "Recepción", $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sthchgdatarow["stk_num"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "Fecha: ".date('d.m.Y', $sthchgdatarow["stk_bookdate"]), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
            $xls->merge_cells($rowidx, $colidx-1, $rowidx, $colidx);
            $colidx++;

            $xls->write_string($rowidx, $colidx, $sthchgdatarow["st_name"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
            $xls->merge_cells($rowidx, $colidx-1, $rowidx, $colidx);
            $colidx++;

            $xls->write_number($rowidx, $colidx, round($sthchgdatarow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sthchgdatarow["invcnum"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, date('d.m.Y', $sthchgdatarow["stk_bookdate"]), $_FRM["TBLR"]);$colidx++;

            $rowidx++;
         }
      }
      $rowidx++;
   }

   /*
   $rowidx = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $sqlrowidx)
   {
      $data =$_SESSION["STATS"][$_sesmodulename]["DATA"][$sqlrowidx];

      $colidx = 0;
      $xls->write_string($rowidx, 0, $data[0]["_DATA"]["supp_company"], $_FRM["TBLLB"]);
      $xls->write_string($rowidx, 6, "", $_FRM["TBLLB"]);
      $xls->merge_cells($rowidx, 0, $rowidx, 6);

      $xls->write_string($rowidx, 7, $data[0]["_DATA"]["supp_rut"], $_FRM["TBLLB"]);
      $rowidx++;

      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      foreach($data AS $row)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $row["dlv_docnum"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["dlv_delivery_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["dlv_annotation"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_number_prod"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($row["item_amount_shipped"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["invc_num"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["stname"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      $rowidx++;
   }
   */

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsEntregaRepus($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_entrrespus";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "Fecha",
      "Hora",
      "Código",
      "Repuesto",
      "Cantidad",
      "Tipo",
      "Máquina",
      "Entregado por",
      "Entregado a",
      "Valor"
   );
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 40);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 20);
   $xls->set_column(0, 7, 25);
   $xls->set_column(0, 8, 25);
   $xls->set_column(0, 9, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["stk_crtdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["stk_crtdath"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["add_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["equipo_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_firstname"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["wrk_firstname"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["itemcostprice"]), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsAprobPartidas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
"N° CC",        
"N° Prod.",     
"Cliente",
"Diseño",
"Código",       
"Producto",     
"Cant./Venta",  
"Máquina",      
"Operador",     
"Inicio OT",    
"Aprob./Oper.", 
"Supervisor",   
"Aprob./Super.",
"Termino OT",   
"Estado",       
"Producido"    );
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 10);
   $xls->set_column(0, 2, 35);
   $xls->set_column(0, 3, 20);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 30);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 18);
   $xls->set_column(0, 8, 20);
   $xls->set_column(0, 9, 16);
   $xls->set_column(0, 10, 16);
   $xls->set_column(0, 11, 18);
   $xls->set_column(0, 12, 16);
   $xls->set_column(0, 13, 16);
   $xls->set_column(0, 14, 12);
   $xls->set_column(0, 15, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["prd_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["fab_design_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"]), $_FRM["TBLR"]);$colidx++;

      $xls->write_string($rowidx, $colidx, $sqlrow["equipo_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["wuser_lastname"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["wok_crtdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["wctr_ctrdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["suser_lastname"], $_FRM["TBLR"]);$colidx++;

      $xls->write_string($rowidx, $colidx, $sqlrow["sctr_ctrdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["wok_enddat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["xstate"], $_FRM["TBLR"]);$colidx++;

      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_PROD_AMOUNT"]), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsProdJornadas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodwrk";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   if((int)$_SESSION[$_sesmodulename]["sql_viewmode"] == 0)
   {
      //----------------------------------------------------------------------------------
         $cols = Array(
      "Entrada",
      "Salida",
      "Nombres",
      "Apellidos",
      "RUT",
      "Mï¿½quina",
      "Incidencia",
      "Comentarios");
         $xls->set_column(0, 0, 8);
         $xls->set_column(0, 1, 9);
         $xls->set_column(0, 2, 30);
         $xls->set_column(0, 3, 30);
         $xls->set_column(0, 4, 16);
         $xls->set_column(0, 5, 30);
         $xls->set_column(0, 6, 20);
         $xls->set_column(0, 7, 30);

         //----------------------------------------------------------------------------------
         $rowidx = 0;
         $counter = 0;
         
         for($y = 0; $y < count($cols); $y++)
            $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

         $rowidx++;

         foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
         {
            $colidx = 0;
            $xls->write_string($rowidx, $colidx, $sqlrow["ass_init_hour"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["ass_end_hour"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["wrk_firstname"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["wrk_lastname"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["wrk_rut"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["equipo_name"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["INC"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["ass_comments"], $_FRM["TBLR"]);$colidx++;
            $rowidx++;
         }
   }
   else
   {
         //----------------------------------------------------------------------------------
         $cols = Array("Fecha",
      "Dia",
      "Entrada",
      "Salida",
      "Mï¿½quina",
      "Incidencia",
      "Comentarios");
         $xls->set_column(0, 0, 12);
         $xls->set_column(0, 1, 8);
         $xls->set_column(0, 2, 9);
         $xls->set_column(0, 3, 9);
         $xls->set_column(0, 4, 30);
         $xls->set_column(0, 5, 20);
         $xls->set_column(0, 6, 40);

         //----------------------------------------------------------------------------------
         $rowidx = 0;
         $counter = 0;
         

         foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $eqname)
         {
            $xls->write_string($rowidx, 0, $eqname, $_FRM["TBLH"]);
            $xls->write_string($rowidx, 5, "", $_FRM["TBLH"]);
            $xls->merge_cells($rowidx, 0, $rowidx, 5);
            $rowidx++;
            
            for($y = 0; $y < count($cols); $y++)
               $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

            $rowidx++;
         
            foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$eqname] AS $sqlrow)
            {
               $colidx = 0;
               $xls->write_string($rowidx, $colidx, $sqlrow["datestr"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["datestm"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["ass_init_hour"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["ass_end_hour"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["equipo_name"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["inc_name"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["ass_comments"], $_FRM["TBLR"]);$colidx++;
               $rowidx++;
            }
            $rowidx++;
         }
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsProdMaquinas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodmaq";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Fecha",     
"Dia",       
"Entrada",   
"Salida",    
"Nombres",   
"Apellidos", 
"RUT",       
"Cargo",     
"Incidencia",
"Comentarios");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 8);
   $xls->set_column(0, 2, 9);
   $xls->set_column(0, 3, 9);
   $xls->set_column(0, 4, 30);
   $xls->set_column(0, 5, 30);
   $xls->set_column(0, 6, 13);
   $xls->set_column(0, 7, 30);
   $xls->set_column(0, 8, 25);
   $xls->set_column(0, 9, 30);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $eqname)
   {
      $xls->write_string($rowidx, 0, $eqname, $_FRM["TBLH"]);
      $xls->write_string($rowidx, 8, "", $_FRM["TBLH"]);
      $xls->merge_cells($rowidx, 0, $rowidx, 8);
      $rowidx++;
      
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

      $rowidx++;
   
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$eqname] AS $sqlrow)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $sqlrow["datestr"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["day"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["ass_init_hour"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["ass_end_hour"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["wrk_firstname"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["wrk_lastname"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["wrk_rut"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["cargo"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["incidencia"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["ass_comments"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsEncuestas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Fecha",           
"RUT",                 
"Nombre",              
"Email" ,              
"Monto",               
"Fecha Encuesta",
"Organizaciï¿½n",        
"Calidad producto",
"Rapidez envï¿½o",
"Cumpl. diseï¿½o",
"Experiencia compra",
"Atenciï¿½n",            
"Precio/Calidad",
"Recomendaciï¿½n",
"Como se enterï¿½",
"Comentarios",         
"Productos comprados",
"Gustaria comprar",
"Otros productos");
   $xls->set_column(0, 0, 11);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 30);
   $xls->set_column(0, 3, 20);
   $xls->set_column(0, 4, 11);
   $xls->set_column(0, 5, 13);
   $xls->set_column(0, 6, 16);
   $xls->set_column(0, 7, 14);
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 15);
   $xls->set_column(0, 12, 12);
   $xls->set_column(0, 13, 13);
   $xls->set_column(0, 14, 25);
   $xls->set_column(0, 15, 30);
   $xls->set_column(0, 16, 25);
   $xls->set_column(0, 17, 25);
   $xls->set_column(0, 18, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_email"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["invc_total_brutto"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_upddat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_1"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, (int)$sqlrow["enc_resp_2"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_3"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_4"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, (int)$sqlrow["enc_resp_5"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, (int)$sqlrow["enc_resp_6"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, (int)$sqlrow["enc_resp_7"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_8"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_9"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_10"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_11"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_12"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["enc_resp_13"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createTurnosConfig($CON, $sqlyear, $selplantas)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "turnos_cfg";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xlsx";
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
   require_once("./libs/classes/onebitxls.php");
   $workbook = new ONEBITXLS($pdffile);
   $workbook->setVersion(8);
   $xls =& $workbook->addWorksheet("Tabla1");

   //----------------------------------------------------------------------------------
   $jornadas         = $_SESSION[$_sesmodulename]["XDATA"]["jornadas"];
   $_TTYPES          = $_SESSION[$_sesmodulename]["XDATA"]["_TTYPES"];
   $ttypes           = $_SESSION[$_sesmodulename]["XDATA"]["ttypes"];
   $date_init        = mktime(0, 0, 0, 1, 1, $sqlyear);
   $date_end         = mktime(23, 59, 59, 12, 31, $sqlyear);

   //----------------------------------------------------------------------------------
   for($x = $date_init+3600; $x <= $date_end; $x += 86400)
   {
      $idx_month = date("m/Y", $x);
      $_MONTH_COLSPAN[$idx_month]++;
      $_TOTAL_COLSPAN++;
   }

   //----------------------------------------------------------------------------------
   $sql = " select *
            from turnos_config
            where
            cfg_datestamp between {$date_init} and {$date_end}";
   $cfgs = $CON->select($sql);
   foreach($cfgs AS $cfg)
      $_CFGACT[$cfg["cfg_planta_id"]][$cfg["cfg_datestr"]][$cfg["cfg_turno_id"]] = $cfg["cfg_turno_type_id"];
   
   //----------------------------------------------------------------------------------
   $rowidx  = 0;
   $counter = 0;
   $colidx  = 0;

   $xls->setcolumn(0, 0, 3);
   $xls->setcolumn(1, 1, 20);
   $xls->setcolumn(2, 2, 20);
   $xls->writestring($rowidx, $colidx, "ID", $_FRM["TBLHX"]);
   $colidx++;
   $xls->writestring($rowidx, $colidx, "PLANTA", $_FRM["TBLHX"]);
   $colidx++;
   $xls->writestring($rowidx, $colidx, "TURNOS", $_FRM["TBLHX"]);
   $colidx++;
   foreach(array_keys($_MONTH_COLSPAN) AS $midx)
   {
      $xls->writestring($rowidx, $colidx, $midx, $_FRM["TBLHX"]);
      $maxcell = ($colidx + $_MONTH_COLSPAN[$midx] -1);
      for($xx = $colidx +1; $xx <= $maxcell; $xx++)
        $xls->writestring($rowidx, $xx, " ", $_FRM["TBLHX"]);
         
      $xls->mergecells($rowidx, $colidx, $rowidx, $maxcell);
      $colidx += $_MONTH_COLSPAN[$midx];
   }
   $rowidx++;

   //----------------------------------------------------------------------------------
   $colidx = 3;
   for($x = $date_init+3600; $x <= $date_end; $x += 86400)
   {
      $xls->writestring($rowidx, $colidx, substr($_LANG["MODULE"]["CAL"][(11 + date("N", $x))],0,2), $_FRM["TBLR"]);
      $xls->setcolumn($colidx, $colidx, 1.9);
      $colidx++;
   }
   $rowidx++;

   //----------------------------------------------------------------------------------
   $colidx = 3;
   for($x = $date_init+3600; $x <= $date_end; $x += 86400)
   {
      $xls->writestring($rowidx, $colidx, date("d.m.Y", $x), $_FRM["TBLR"]);
      $colidx++;
   }
   $rowidx++;

   //----------------------------------------------------------------------------------
   foreach($selplantas AS $selplanta)
   {
      foreach($jornadas AS $jornada)
      {
         $colidx = 0;
         $xls->writestring($rowidx, $colidx, "", $_FRM["TBLLB"]);
         $colidx++;
         $xls->writestring($rowidx, $colidx, $jornada["jorn_name"], $_FRM["TBLLB"]);
         $colidx++;
         $xls->writestring($rowidx, $colidx, "", $_FRM["TBLLB"]);
         $colidx++;
         for($x = $date_init+3600; $x <= $date_end; $x += 86400)
         {
            $xls->writestring($rowidx, $colidx, "", $_FRM["TBLLB"]);
            $colidx++;
         }
         $rowidx++;

         $sql = " select *
                  from turnos
                  where
                  turn_jornada_id = {$jornada["id"]} and
                  turn_status > 0
                  order by turn_order, turn_name";
         $turnos = $CON->select($sql);
         foreach($turnos AS $turno)
         {
            $colidx = 0;
            $xls->writestring($rowidx, $colidx, $selplanta["id"]."_".$turno["id"], $_FRM["TBLR"]);
            $colidx++;
            $xls->writestring($rowidx, $colidx, $selplanta["planta_name"], $_FRM["TBLR"]);
            $colidx++;
            $xls->writestring($rowidx, $colidx, $turno["turn_name"], $_FRM["TBLR"]);
            $colidx++;

            for($x = $date_init+3600; $x <= $date_end; $x += 86400)
            {
               $cellidx = date("d.m.Y", $x);
               if((int)$_CFGACT[$selplanta["id"]][$cellidx][$turno["id"]])
               {
                  $celldata = $_TTYPES[$_CFGACT[$selplanta["id"]][$cellidx][$turno["id"]]]["type_name_short"];
                  $xls->writestring($rowidx, $colidx, $celldata, $_FRM["TBLR"]);
               }
               $colidx++;
            }
            $rowidx++;
         }
      }
   }
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSellingCompleteProd($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statscompletedocprod";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $payments = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["payments"];
   
   //----------------------------------------------------------------------------------
   $cols = Array("Tipo", "Número", "Fecha", "Hora", "Cliente", "Canal", "Fecha Creacion","Presupuesto","Permanente","Cantidad Documentos","Sucursal", "Vendedor",
                 "Código", "Producto", "Cantidad", "Neto/Unit","Descuento", "Neto/Total");
                 
   $xls->set_column(0, 0, 9);
   $xls->set_column(0, 1, 8);
   $xls->set_column(0, 2, 9);
   $xls->set_column(0, 3, 6);
   $xls->set_column(0, 4, 35);
   $xls->set_column(0, 5, 18);
   $xls->set_column(0, 6, 18);
   $xls->set_column(0, 7, 18);
   $xls->set_column(0, 8, 18);

   $xls->set_column(0, 9, 18);
   $xls->set_column(0, 10, 18);
   $xls->set_column(0, 11, 18);
   $xls->set_column(0, 12, 12);
   $xls->set_column(0, 13, 50);
   $xls->set_column(0, 14, 12);
   $xls->set_column(0, 15, 12);
   $xls->set_column(0, 16, 12);
   $xls->set_column(0, 17, 12);


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["typestr"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_upddat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["canal"], $_FRM["TBLR"]);$colidx++;
      if($sqlrow["fecha_creacion"] > 0)
      {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["fecha_creacion"]),$_FRM["FECHA"]) ;$colidx++;
      }
      else 
      {
         $xls->write($rowidx, $colidx,'',$_FRM["TBLR"]) ;$colidx++;
      }
      $xls->write_string($rowidx, $colidx, $sqlrow["presupuesto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_permanente"], $_FRM["TBLR"]);$colidx++;
      
      $xls->write_string($rowidx, $colidx, $sqlrow["ndocumentos"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shop_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_lastname"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"], 2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_netto"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_descuento"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_netto_dsc"]), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createFabPlist($CON, $plid)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "fabpllist";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from fabric_types
            where
            fabt_status > 0
            order by fabt_code";
   $fabric_types = $CON->select($sql);
   foreach($fabric_types AS $fabric_type)
   {
      $_LISTTYPES[$fabric_type["fabt_code"]] = 1;
   }

   //---------------------------------------------------------------------------------
   $sql = " select t1.*, t2.item_title, t2.item_number_prod, t3.inc_name
            from price_lists_fab_items t1
            LEFT OUTER JOIN item t2 ON t1.fab_item_id = t2.id
            LEFT OUTER JOIN price_lists_fab_increments t3 ON t1.fab_inc_id = t3.id
            where
            t1.pl_id = {$plid}
            order by t2.item_title, t1.fab_item_id, t1.id";
   $data = $CON->select($sql);
   foreach($data AS $row)
   {
      $sql = " select *
               from price_lists_fab_items_prices
               where
               prc_headerid = {$row["id"]}";
      $prices = $CON->select($sql);
      foreach($prices AS $price)
         $row["_prices"][(int)$price["prc_amount"]] = (int)$price["prc_price"];

      $idx = $row["fab_type"];
      $_RES[$idx][] = $row;
   }

   //----------------------------------------------------------------------------------
   $cols = Array("ID interno (no cambiar)", "Tipo","Cï¿½digo", "Producto", "Incremento", "Descripcion", "Ancho", "Alto", "Fuelle", "Ped.Min.", "Corte Maq.", "Ancho Rollo", "Gr.Tela",
                 "Largo Man.", "Ancho Impr.", "Alto Impr.", "Desc.SinImpr.", "Activo");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 8);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 30);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 8);
   $xls->set_column(0, 7, 8);
   $xls->set_column(0, 8, 8);
   $xls->set_column(0, 9, 8);
   $xls->set_column(0, 10, 8);
   $xls->set_column(0, 11, 8);
   $xls->set_column(0, 12, 8);
   $xls->set_column(0, 13, 8);
   $xls->set_column(0, 14, 8);
   $xls->set_column(0, 15, 8);
   $xls->set_column(0, 16, 8);
   $xls->set_column(0, 17, 8);

   $colidx = 16;

   //---------------------------------------------------------------------------------
   $sql = " select t1.*
            from price_lists_fab_amounts t1
            where
            t1.pl_id      = {$plid} and
            t1.amt_status  = 1
            order by t1.amt_val";
   $amounts = $CON->select($sql);
   foreach($amounts AS $amount)
   {
      $colidx++;
      $cols[] = printPrice($amount["amt_val"]);
      $xls->set_column(0, $colidx, 9);
   }


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach(array_keys($_LISTTYPES) AS $ltype)
   {
      $rowcount = count($_RES[$ltype]);
      for($x = 0; $x < $rowcount; $x++)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, $_RES[$ltype][$x]["id"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $ltype, $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_RES[$ltype][$x]["item_number_prod"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_RES[$ltype][$x]["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_RES[$ltype][$x]["inc_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_RES[$ltype][$x]["fab_desc"], $_FRM["TBLR"]);$colidx++;

         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_med_width"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_med_height"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_med_fuelle"], $_FRM["TBLR"]);$colidx++;

//          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//          if((int)$_RES[$ltype][$x]["fab_min_amt"])
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_min_amt"], $_FRM["TBLR"]);
         $colidx++;

//          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//          if((int)$_RES[$ltype][$x]["fab_corte_machine"])
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_corte_machine"], $_FRM["TBLR"]);
         $colidx++;

//          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//          if((int)$_RES[$ltype][$x]["fab_roll_width"])
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_roll_width"], $_FRM["TBLR"]);
         $colidx++;

//          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//          if((int)$_RES[$ltype][$x]["fab_fabric_gr"])
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_fabric_gr"], $_FRM["TBLR"]);
         $colidx++;

//          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//          if((int)$_RES[$ltype][$x]["fab_manilla_length"])
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_manilla_length"], $_FRM["TBLR"]);
         $colidx++;

//          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//          if((int)$_RES[$ltype][$x]["fab_print_width"])
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_print_width"], $_FRM["TBLR"]);
         $colidx++;

//          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//          if((int)$_RES[$ltype][$x]["fab_print_height"])
         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_print_height"], $_FRM["TBLR"]);
         $colidx++;

         $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["fab_noprint_discount"], $_FRM["TBLR"]);
         $colidx++;
         
         
         if((int)$_RES[$ltype][$x]["fab_active"])
            $xls->write_string($rowidx, $colidx, "X", $_FRM["TBLR"]);
         else
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
         $colidx++;

         foreach($amounts AS $amount)
         {
            //$xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
//             if((int)$_RES[$ltype][$x]["_prices"][(int)$amount["amt_val"]])
            $xls->write_number($rowidx, $colidx, (int)$_RES[$ltype][$x]["_prices"][(int)$amount["amt_val"]], $_FRM["TBLR"]);
            $colidx++;
         }
         
         $rowidx++;
      }
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemCharactStock($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_charact";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $payments = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["payments"];
   
   //----------------------------------------------------------------------------------
   if(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 1)
   {
      $cols = Array("Cï¿½DIGO", "PRODUCTO", "UNIDAD", "PROVEEDOR", "Cï¿½DIGO/PROV.", "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}", "ANCHO", "GSM", "KG UNIT", "LONGITUD", "STOCK");

      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);

      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 35);
      $xls->set_column(0, 4, 16);

      $xls->set_column(0, 5, 20);
      $xls->set_column(0, 6, 12);
      $xls->set_column(0, 7, 12);
      $xls->set_column(0, 8, 12);
      $xls->set_column(0, 9, 12);
      $xls->set_column(0, 10, 12);
   }
   elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 2)
   {
      $cols = Array("Cï¿½DIGO", "PRODUCTO", "UNIDAD", "PROVEEDOR", "Cï¿½DIGO/PROV.",
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}",
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][1]}",
                    "ANCHO", "GSM", "KG UNIT", "LONGITUD", "STOCK");

      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);

      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 35);
      $xls->set_column(0, 4, 16);

      $xls->set_column(0, 5, 20);
      $xls->set_column(0, 6, 20);
      $xls->set_column(0, 7, 12);
      $xls->set_column(0, 8, 12);
      $xls->set_column(0, 9, 12);
      $xls->set_column(0, 10, 12);
      $xls->set_column(0, 11, 12);
   }
   elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 3)
   {
      $cols = Array("Cï¿½DIGO", "PRODUCTO", "UNIDAD", "PROVEEDOR", "Cï¿½DIGO/PROV.",
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][0]}",
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][1]}",
                    "{$_SESSION["STATS"][$_sesmodulename]["HEADER"][2]}",
                    "ANCHO", "GSM", "KG UNIT", "LONGITUD", "STOCK");

      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);

      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 35);
      $xls->set_column(0, 4, 16);

      $xls->set_column(0, 5, 20);
      $xls->set_column(0, 6, 20);
      $xls->set_column(0, 7, 20);
      $xls->set_column(0, 8, 12);
      $xls->set_column(0, 9, 12);
      $xls->set_column(0, 10, 12);
      $xls->set_column(0, 11, 12);
      $xls->set_column(0, 12, 12);
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      if(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 1)
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["CODE"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["NAME"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["UNIT"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["SUPP"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["SCOD"], $_FRM["TBLR"]);$colidx++;

         $xls->write_string($rowidx, $colidx, $sqlrow["valid0"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xwidth"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xgsm"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xkg"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xlength"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xstock"],2), $_FRM["TBLR"]);$colidx++;
      }
      elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 2)
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["CODE"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["NAME"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["UNIT"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["SUPP"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["SCOD"], $_FRM["TBLR"]);$colidx++;

         $xls->write_string($rowidx, $colidx, $sqlrow["valid0"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["valid1"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xwidth"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xgsm"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xkg"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xlength"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xstock"],2), $_FRM["TBLR"]);$colidx++;
      }
      elseif(count($_SESSION["STATS"][$_sesmodulename]["HEADER"]) == 3)
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["CODE"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["NAME"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["UNIT"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["SUPP"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["SCOD"], $_FRM["TBLR"]);$colidx++;

         $xls->write_string($rowidx, $colidx, $sqlrow["valid0"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["valid1"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["valid2"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xwidth"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xgsm"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xkg"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xlength"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["xstock"],2), $_FRM["TBLR"]);$colidx++;
      }
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSellingComplete($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsinvoicesshopscomplete";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $payments = $_SESSION["STATS"][$_sesmodulename]["HEAD"]["payments"];
   
   //----------------------------------------------------------------------------------
   $cols = Array("Tipo/Prod.", "Nï¿½mero", "Fecha", "Hora", "Cliente", "Sucursal", "Vendedor/Cant", "Total/Neto", "Total/IVA",
                 "Total/Bruto");
                 
   $xls->set_column(0, 0, 9);
   $xls->set_column(0, 1, 8);
   $xls->set_column(0, 2, 9);
   $xls->set_column(0, 3, 6);
   $xls->set_column(0, 4, 25);
   $xls->set_column(0, 5, 18);
   $xls->set_column(0, 6, 18);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 12);

   $colidx = 10;
   foreach($payments AS $payment)
   {
      $cols[] = ucwords(strtolower($payment["pay_title"]));
      $xls->set_column(0, $colidx, 12);
      $colidx++;
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      if($sqlrow["_mode"] == "item")
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);
         $colidx++;
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
         $xls->merge_cells($rowidx, $colidx-1, $rowidx, $colidx);
         $colidx++;

         $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);
         $colidx++;
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
         $colidx++;
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
         $colidx++;
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
         $xls->merge_cells($rowidx, $colidx-3, $rowidx, $colidx);
         $colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_netto"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_taxes"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_brutto"]), $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["typestr"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_upddat"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["shop_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["user_lastname"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["invc_total_netto"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["invc_total_taxes"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["invc_total_brutto"]), $_FRM["TBLR"]);$colidx++;
         foreach($payments AS $payment)
         {
            if((float)$sqlrow["_pays"][$payment["id"]] != 0.00)
               $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_pays"][$payment["id"]]), $_FRM["TBLR"]);

            $colidx++;
         }
      }
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsStockchanges($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockchanges";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 15);
   $xls->set_column(0, 2, 35);

   $colidx = 0;
   if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
   {
      $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);$colidx++;
      $cols2 = Array("TRASPASO", "FECHA", "ORIGEN", "DESTINO", "CANTIDAD", "NETO TOTAL");
      $xls->set_column(0, 3, 35);
      $xls->set_column(0, 4, 25);
      $xls->set_column(0, 5, 25);
      $xls->set_column(0, 6, 15);
      $xls->set_column(0, 7, 15);

      //----------------------------------------------------------------------------------
      $rowidx = $rowidx + 0;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols2); $y++)
         $xls->write_string($rowidx, $y, $cols2[$y], $_FRM["TBLH"]);
      $rowidx++;
   }

//    if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
//       $rowidx++;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $idx1)
   {
      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
      {
         $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
         $cols3 = Array("TRASPASO", "FECHA", "ORIGEN", "DESTINO", "ARTÍCULO", "UNIDAD", "CANTIDAD", "NETO/U", "NETO TOTAL");
         $xls->set_column(0, 3, 35);
         $xls->set_column(0, 4, 35);
         $xls->set_column(0, 5, 15);
         $xls->set_column(0, 6, 15);
         $xls->set_column(0, 7, 15);
         $xls->set_column(0, 8, 15);

         //----------------------------------------------------------------------------------
         for($y = 0; $y < count($cols3); $y++)
            $xls->write_string($rowidx, $y, $cols3[$y], $_FRM["TBLH"]);
         $rowidx++;

         foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $row)
         {
            $colidx = 0;
            $xls->write_string($rowidx, $colidx, $row["strc_number"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["strc_date"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["from_st_name"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["st_name"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["item_title"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["unit_name"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["item_amount"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["item_costprice_netto"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $row["item_costprice_netto_total"], $_FRM["TBLR"]);$colidx++;
            $rowidx++;
         }

         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_number"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["from_st_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["st_name"], $_FRM["TBLR"]);$colidx++;
         $colidx++;
         $colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesamount"], $_FRM["TBLR"]);$colidx++;
         $colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesnetto"], $_FRM["TBLR"]);$colidx++;
         $rowidx = $rowidx + 2;
      }

      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 1)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_number"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["strc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["from_st_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["st_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesamount"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA"][$idx1]["gesnetto"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsSthPedidos($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "cpps_stats_pedidos";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("PEDIDO", "FECHA", "ESTADO", "SUCURSAL", "USUARIO", "Cï¿½DIGO", "PRODUCTO", "CANT/PEDIDO", "CANT/ENTREGA");
   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 13);
   $xls->set_column(0, 3, 25);
   $xls->set_column(0, 4, 25);
   $xls->set_column(0, 5, 18);
   $xls->set_column(0, 6, 50);
   $xls->set_column(0, 7, 13);
   $xls->set_column(0, 8, 14);


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["xid"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["strc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["xstate"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shop"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["itemnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["order_amount"],2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createDepositos($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "cash_deps";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FOLIO DEPOSITO", "FECHA DEPOSITO", "FOLIO CIERRE", "FECHA CIERRE", "SUCURSAL", "CAJA", "USUARIO", "\$ CAJA FUERTE", "\$ CAJA VENTA", "\$ TOTAL");
   $xls->set_column(0, 0, 16);
   $xls->set_column(0, 1, 16);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 22);
   $xls->set_column(0, 5, 22);
   $xls->set_column(0, 6, 22);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 16);
   $xls->set_column(0, 9, 16);
   $xls->set_column(0, 10, 16);
   $xls->set_column(0, 11, 16);


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["depid"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["dep_crtdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cash_closeid"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cash_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shop_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["ca_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_firstname"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["depcf_value"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["dep_value"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["dep_total"]), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsCashClose($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "cash_close_detail";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols[1] = Array("CIERRE DE CAJA");
   $cols[2] = Array("MEDIO DE PAGO", "SISTEMA", "CAJERA", "DIFERENCIA");
   $cols[3] = Array("EFECTIVO", "CANTIDAD", "MONTO");
   $xls->set_column(0, 0, 25);
   $xls->set_column(0, 1, 25);
   $xls->set_column(0, 2, 25);
   $xls->set_column(0, 3, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   for($x = 1; $x <= 3; $x++)
   {
      for($y = 0; $y < count($cols[$x]); $y++)
         $xls->write_string($rowidx, $y, $cols[$x][$y], $_FRM["TBLH"]);
      $rowidx++;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA{$x}"] AS $sqlrow)
      {
         $xstyle = $_FRM["TBLR"];
         if(strpos($sqlrow["val1"], "<b>") !== false)
            $xstyle = $_FRM["TBLH"];
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val1"])), $xstyle);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val2"])), $xstyle);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val3"])), $xstyle);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val4"])), $xstyle);$colidx++;
         $rowidx++;
      }
      $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
      $rowidx++;

      if($x == 2 && $_SESSION["STATS"][$_sesmodulename]["ADMINCLOSE"] != "")
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, "VALES CERRADOS POR ADMIN", $_FRM["TBLH"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["ADMINCLOSE"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
         
         $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
         $rowidx++;
      }
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsBreak($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_break";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array( "CODIGO", "DESCRIPCION", "UNIDAD", "STOCK", "VENTAS", "VALOR UNIT.", "VALOR ACUM.", "PORCENTAJE", "PROYECTADO", "QUIEBRE");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 15);
   $xls->set_column(0, 6, 15);
   $xls->set_column(0, 7, 15);
   $xls->set_column(0, 8, 15);
   $xls->set_column(0, 9, 15);

   //----------------------------------------------------------------------------------
   $rowidx  = 0;
   $counter = 0;

   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;


   foreach ($_SESSION["STATS"][$_sesmodulename]["DATA"] as $row)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $row["CODE"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["TITLE"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["UNIT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["STOCK"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["AMOUNT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["VAL_UNIT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["VAL_ACUM"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["PERC"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["PROYEC"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["BREAK"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   $xls->write_string($rowidx, 0, "TOTAL", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 3, $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["STOCK"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 4, $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["SELL"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 5, $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["VAL_UNIT"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 6, $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["VAL_ACUM"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 7, $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["PERC"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 8, $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["PROYEC"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 9, $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["BREAK"], $_FRM["TBLH"]);$colidx++;
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
//----------------------------------------------------------------------------------
function xls_createStatsStockchangesOverview($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockchanges_overview";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }
   $stockchanges = $CON->select($sql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "MOTIVO", "OBSERVACIONES", "CLIENTE", "TOTAL NETO", "FECHA", "CREADO", "ESTADO");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 30);
   $xls->set_column(0, 2, 40);
   $xls->set_column(0, 3, 30);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 14);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   for($x = 0; $x < count($stockchanges) && $stockchanges != false; $x++)
   {
      $statimg = "";
      switch((int)$stockchanges[$x]["stk_status"])
      {
         case 1: $statimg = "Pendiente"; break;
         case 2: $statimg = "Finalizado"; break;
      }
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $stockchanges[$x]["stk_num"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $stockchanges[$x]["stkis_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $stockchanges[$x]["stk_annotation"], $_FRM["TBLR"]);$colidx++;
      if((int)$stockchanges[$x]["stk_isventainterna"])
         $xls->write_string($rowidx, $colidx, $stockchanges[$x]["cust_name"], $_FRM["TBLR"]);
      else
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
      $colidx++;
      if((int)$stockchanges[$x]["stk_isventainterna"])
         $xls->write_number($rowidx, $colidx, $stockchanges[$x]["stk_total_netto"], $_FRM["TBLR"]);
      else
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
      $colidx++;
      if($stockchanges[$x]["stk_bookdate"] > 0)
         $xls->write_string($rowidx, $colidx, date('d.m.Y', $stockchanges[$x]["stk_bookdate"]), $_FRM["TBLR"]);
      else
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
      $colidx++;
      if($stockchanges[$x]["stk_crtdat"] > 0)
         $xls->write_string($rowidx, $colidx, date('d.m.Y', $stockchanges[$x]["stk_crtdat"]), $_FRM["TBLR"]);
      else
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);
      $colidx++;
      $xls->write_string($rowidx, $colidx, $statimg, $_FRM["TBLR"]);
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsPayment($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "adm_payments";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("ITEM", "CANTIDAD", "MONTO TOTAL");
   $xls->set_column(0, 0, 50);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 12);


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($sqlrow["cant"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($sqlrow["value"]), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, "TOTAL MONTO", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 1, $_SESSION["STATS"][$_sesmodulename]["amount"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 2, $_SESSION["STATS"][$_sesmodulename]["total"], $_FRM["TBLH"]);$colidx++;

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
//----------------------------------------------------------------------------------
function xls_createStatsStockPrice($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_prices";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("COD. PRODUCTO", "NOMBRE", "CANTIDAD", "PRECIO BASE/NETO", "MAYORISTA/BRUTO","DETALLE/BRUTO");
   $xls->set_column(0, 0, 20);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 17);
   $xls->set_column(0, 4, 17);
   $xls->set_column(0, 5, 17);

   //----------------------------------------------------------------------------------
   $rowidx  = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["num"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["stock"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["neto"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["bruto"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["dbruto"]), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
//----------------------------------------------------------------------------------
function xls_createStatsItemOverview($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "items";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   $seasql = "";
   $joisql = " LEFT OUTER JOIN item_units t13 ON t1.item_unit = t13.id
               LEFT OUTER JOIN item_suppliers t7 ON t1.id = t7.item_id
               LEFT OUTER JOIN item_barcodes tx    ON ( t1.id = tx.item_id AND tx.item_type = 'item') ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $joisql .= " INNER JOIN item_productcats t6 ON t1.id = t6.item_id ";
   if($_SESSION[$_sesmodulename]["sql_company"])
      $joisql .= " INNER JOIN item_shops t8 ON t1.id = t8.item_id
                   INNER JOIN company_shops t9 ON t8.shop_id = t9.id ";
   if($_SESSION[$_sesmodulename]["sql_storehouse"])
      $joisql .= " INNER JOIN item_shops_storehouses t12 ON ( t1.id = t12.item_id and t9.id = t12.shop_id )";

   //----------------------------------------------------------------------------------
   $datsql = " select distinct t1.item_released, t1.item_number_prod, t1.item_title, t1.item_sellprice_netto,
                      t13.unit_name, t1.item_unit_amount, t1.id, t1.item_unitembalaje_amount,
                      GROUP_CONCAT(distinct tx.item_barcode) 'item_barcode'
               from item t1
               {$joisql}
               where
               t1.item_status = 1 ";

   //----------------------------------------------------------------------------------
   if($_SESSION[$_sesmodulename]["sql_company"])
      $seasql .= " and t9.shop_company_id = {$_SESSION[$_sesmodulename]["sql_company"]} ";
   if($_SESSION[$_sesmodulename]["sql_shop"])
      $seasql .= " and t9.id = {$_SESSION[$_sesmodulename]["sql_shop"]} ";
   if($_SESSION[$_sesmodulename]["sql_storehouse"])
      $seasql .= " and t12.st_id = {$_SESSION[$_sesmodulename]["sql_storehouse"]} ";
   if($_SESSION[$_sesmodulename]["sql_supplier"])
      $seasql .= " and t7.supplier_id = {$_SESSION[$_sesmodulename]["sql_supplier"]} ";
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $seasql .= " and t6.cat_id = {$_SESSION[$_sesmodulename]["sql_pcat"]} ";

   if($_SESSION[$_sesmodulename]["sql_stext"] != "")
   {
      $seasql .= " and (t1.item_title        like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.item_number_prod  like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t1.item_number       like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        t7.item_code         like '%{$_SESSION[$_sesmodulename]["sql_stext"]}%' or
                        tx.item_barcode      = '{$_SESSION[$_sesmodulename]["sql_stext"]}') ";
   }
   $datsql .= $seasql;
   $datsql .= " group by t1.id";
   $datsql .= " order by {$_SESSION[$_sesmodulename]["orderBy"]} {$_SESSION[$_sesmodulename]["orderSort"]} ";
   
   $items = $CON->select($datsql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("CODIGO_AUXILIAR", "DESCRIPCION", "PRECIO_NETTO", "UNIDAD_CANTIDAD", "UNIDAD_DESCRIPCION", "EMBALAJE", "CODIGO_BARRA");
   $xls->set_column(0, 0, 18);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 11);
   $xls->set_column(0, 6, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($items AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(printPrice($sqlrow["item_sellprice_netto"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(printPrice($sqlrow["item_unit_amount"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(printPrice($sqlrow["item_unitembalaje_amount"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_barcode"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsAdminPayments($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "createadmindpayment";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("ITEM", "CANTIDAD", "TOTAL");
   $xls->set_column(0, 0, 50);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 12);


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($sqlrow["cant"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($sqlrow["value"]), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, "TOTAL MONTO", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 1, $_SESSION["STATS"][$_sesmodulename]["amount"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 2, $_SESSION["STATS"][$_sesmodulename]["total"], $_FRM["TBLH"]);$colidx++;

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
//----------------------------------------------------------------------------------
function xls_createInvoicesOrderOffer($CON, $orderid)
{
   global $_LANG;
   
   //----------------------------------------------------------------------------------
      $sql = " SELECT t1. * , t2.cust_company, t2.cust_email, t2.cust_street, t6.country_name 'cust_country', t2.cust_rut, t3.company_name, 
                              t3.company_rut, t4.shop_name, t4.shop_street, t5.country_name 'shop_country', t4.shop_email, t4.shop_phone, t7.name '
                              shop_region', t8.name 'cust_region', t9.nombre 'shop_comuna', t10.nombre 'cust_comuna', t11.giro_name, t2.cust_fax, 
                              t2.cust_phone, t12.pay_title, t1.req_shop_id, CONCAT(t13.user_firstname, ' ', t13.user_lastname) 'sellername'
               FROM offers t1
               LEFT OUTER JOIN customer t2 ON t1.req_cust_id = t2.id
               LEFT OUTER JOIN company_data t3 ON t1.req_company_id = t3.id
               LEFT OUTER JOIN company_shops t4 ON t1.req_shop_id = t4.id
               LEFT OUTER JOIN country t5 ON t4.shop_countryid = t5.id
               LEFT OUTER JOIN country t6 ON t1.req_cust_countryid = t6.id
               LEFT OUTER JOIN regions t7 ON t4.shop_regionid = t7.id
               LEFT OUTER JOIN regions t8 ON t1.req_cust_regionid = t8.id
               LEFT OUTER JOIN comunas t9 ON t4.shop_comunaid = t9.id
               LEFT OUTER JOIN comunas t10 ON t1.req_cust_comunaid = t10.id
               LEFT OUTER JOIN giros t11 ON t2.cust_giroid = t11.id
               LEFT OUTER JOIN payments t12 ON t1.req_paymentid = t12.id
               LEFT OUTER JOIN user t13 ON t1.req_crtusr = t13.id
               WHERE
               t1.id = {$orderid}";
   $orderheader = $CON->select($sql);
   $orderheader = $orderheader[0];


   //----------------------------------------------------------------------------------
   $doctype  = "createInvoicesOrderOffer";
   $currtme  = time();
   $hash     = md5(microtime());
   $filedir  = "./docs.print/";
   $filename = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext  = ".xls";
   $pdffile  = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   $colx1 = "NETO UNITARIO";
   $colx2 = "PRECIO SIN IVA";
   $fldx1 = "item_sellprice_netto";
   $fldx2 = "item_sellprice_netto_dsc";
   $fldx3 = "req_total_netto";

   if((int)$orderheader["req_isinvcbrutto"])
   {
      $colx1 = "BRUTO UNITARIO";
      $colx2 = "PRECIO CON IVA";
      $fldx1 = "item_sellprice_brutto";
      $fldx2 = "item_sellprice_brutto_dsc";
      $fldx3 = "req_total_brutto";
   }
   

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //-----------------------------HEADER-------------------------------------------------
   $rowidx = 0;
   $xls->write_string($rowidx, 0, "Cotizaciï¿½n Nï¿½", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $orderheader["req_number"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Fecha", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, date('d', $orderheader["req_date"])." de ".$_LANG["MODULE"]["CAL"][(date('m', $orderheader["req_date"])-1)]." de ".date('Y', $orderheader["req_date"]), $_FRM["TBLR"]);
   $rowidx++;
   $rowidx++;
   $xls->write_string($rowidx, 0, "Seï¿½or(es)", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $orderheader["req_cust_company"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Rut", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $orderheader["req_cust_rut"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Direcciï¿½n", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $orderheader["req_cust_street"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Telefono", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $orderheader["req_cust_phone"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Ciudad", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $orderheader["cust_comuna"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Fax", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $orderheader["req_cust_fax"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Atenciï¿½n", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $customer["req_desc_intern"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Form.Pago", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $orderheader["pay_title"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Vendedor", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $orderheader["sellername"], $_FRM["TBLR"]);
   $rowidx++;
   $rowidx++;

   //-------------------------------ITEM---------------------------------------------------
   $cols = Array("CANT.", "CODIGO", "CODIGO DE BARRA", "DESCRIPCION", "DESCTO.", $colx1, $colx2);
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 45);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 15);
   $xls->set_column(0, 6, 15);
   
   //-------------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLLB"]);
   $rowidx++;
   
   //-------------------------------------------------------------------------------------
   $posdata    = getOfferPos($CON, $orderid, "prodnumber");

   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      //----------------------------------------------------------------------------------
      $barcode = "";
      if($posdata[$x]["item_type"] == "item")
      {
         $sql = " select *
                  from item_barcodes
                  where
                  item_id     = {$posdata[$x]["item_id"]} and
                  item_type   = 'item'
                  LIMIT 0,1";
         $barcode = $CON->select($sql);
         $barcode = $barcode[0]["item_barcode"];
      }
      
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, printPrice($posdata[$x]["item_amount"],2), $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, reformatProdNumber($posdata[$x]["item_number_prod"]), $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $barcode, $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $posdata[$x]["item_title"], $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $posdata[$x]["_dsc_str"], $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($posdata[$x][$fldx1]), $_FRM["TBLHCXL"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($posdata[$x][$fldx2]), $_FRM["TBLHCXL"]);$colidx++;
      $rowidx++;
   }
   
   $rowidx++;
   $rowidx++;

   $xls->write_string($rowidx, 5, "TOTAL", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 6, printPrice($orderheader[$fldx3] + $orderheader["req_discount_amount_netto"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $xls->write_string($rowidx, 5, "DESCUENTO", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 6, printPrice($orderheader["req_discount_amount_netto"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $rowidx++;
   $xls->write_string($rowidx, 5, "NETO", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 6, printPrice($orderheader["req_total_netto"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $xls->write_string($rowidx, 5, "IVA", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 6, printPrice($orderheader["req_total_taxes"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $xls->write_string($rowidx, 5, "TOTAL", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 6, printPrice($orderheader["req_total_brutto"]), $_FRM["TBLHCXL"]);
   $rowidx++;  
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
//----------------------------------------------------------------------------------



//----------------------------------------------------------------------------------
function xls_createInvoicesOrder($CON, $orderid)
{
   global $_LANG;
   
   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.cust_company, t2.cust_email, t2.cust_street, t6.country_name 'cust_country',
                   t2.cust_rut, t3.company_name, t3.company_rut, t4.shop_name, t4.shop_street,
                   t5.country_name 'shop_country', t4.shop_email, t4.shop_phone,
                   t7.name 'shop_region', t8.name 'cust_region', t9.nombre 'shop_comuna',
                   t10.nombre 'cust_comuna', t11.giro_name, t2.cust_fax, t2.cust_phone,
                   t12.pay_title, CONCAT(t13.user_firstname, ' ', t13.user_lastname) 'sellername', t1.req_shop_id,
                   t14.trans_name
            from orders t1
            LEFT OUTER JOIN customer      t2  ON t1.req_cust_id       = t2.id
            LEFT OUTER JOIN company_data  t3  ON t1.req_company_id    = t3.id
            LEFT OUTER JOIN company_shops t4  ON t1.req_shop_id       = t4.id
            LEFT OUTER JOIN country       t5  ON t4.shop_countryid    = t5.id
            LEFT OUTER JOIN country       t6  ON t2.cust_countryid    = t6.id
            LEFT OUTER JOIN regions       t7  ON t4.shop_regionid     = t7.id
            LEFT OUTER JOIN regions       t8  ON t2.cust_regionid     = t8.id
            LEFT OUTER JOIN comunas       t9  ON t4.shop_comunaid     = t9.id
            LEFT OUTER JOIN comunas       t10 ON t2.cust_comunaid     = t10.id
            LEFT OUTER JOIN giros         t11 ON t2.cust_giroid       = t11.id
            LEFT OUTER JOIN payments      t12 ON t1.req_paymentid     = t12.id
            LEFT OUTER JOIN user          t13 ON t1.req_userid_seller = t13.id
            LEFT OUTER JOIN transports    t14 ON t1.req_transportid   = t14.id
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

   //----------------------------------------------------------------------------------
   $doctype  = "createInvoicesOrder";
   $currtme  = time();
   $hash     = md5(microtime());
   $filedir  = "./docs.print/";
   $filename = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext  = ".xls";
   $pdffile  = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   $colx1 = "NETO UNITARIO";
   $colx2 = "PRECIO SIN IVA";
   $fldx1 = "item_sellprice_netto";
   $fldx2 = "item_sellprice_netto_dsc";
   $fldx3 = "req_total_netto";

   if((int)$orderheader["req_isinvcbrutto"])
   {
      $colx1 = "BRUTO UNITARIO";
      $colx2 = "PRECIO CON IVA";
      $fldx1 = "item_sellprice_brutto";
      $fldx2 = "item_sellprice_brutto_dsc";
      $fldx3 = "req_total_brutto";
   }
   

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //-----------------------------HEADER-------------------------------------------------
   $rowidx = 0;
   $xls->write_string($rowidx, 0, "Nï¿½", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $orderheader["req_number"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Fecha", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, date('d', $orderheader["req_crtdat"])." de ".$_LANG["MODULE"]["CAL"][(date('m', $orderheader["req_crtdat"])-1)]." de ".date('Y', $orderheader["req_crtdat"]), $_FRM["TBLR"]);
   $rowidx++;
   $rowidx++;
   $xls->write_string($rowidx, 0, "Seï¿½or(es)", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $customer["cust_company"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Rut", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $customer["cust_rut"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Direcciï¿½n", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $customer["cust_street"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Telefono", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $customer["cust_phone"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Ciudad", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $customer["nombre"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Fax", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $customer["cust_fax"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Atenciï¿½n", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $customer["req_desc_intern"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Form.Pago", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $orderheader["pay_title"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Vendedor", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $orderheader["sellername"], $_FRM["TBLR"]);
   $xls->write_string($rowidx, 3, "Transporte", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 4, $orderheader["trans_name"], $_FRM["TBLR"]);
   $rowidx++;
   $rowidx++;

   //-------------------------------ITEM---------------------------------------------------
   $cols = Array("CANT.", "CODIGO", "CODIGO DE BARRA", "DESCRIPCION", "DESCTO.", $colx1, $colx2, "UBICACION", "BOD.", "REV");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 45);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 15);
   $xls->set_column(0, 6, 15);
   $xls->set_column(0, 7, 20);
   $xls->set_column(0, 8, 17);
   $xls->set_column(0, 9, 17);
   
   //-------------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLLB"]);
   $rowidx++;
   
   //-------------------------------------------------------------------------------------
   $posdata = getOrderPos($CON, $orderid, "prodnumber");

   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      //----------------------------------------------------------------------------------
      $barcode = "";
      if($posdata[$x]["item_type"] == "item")
      {
         $sql = " select *
                  from item_barcodes
                  where
                  item_id     = {$posdata[$x]["item_id"]} and
                  item_type   = 'item'
                  LIMIT 0,1";
         $barcode = $CON->select($sql);
         $barcode = $barcode[0]["item_barcode"];
      }
      
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, printPrice($posdata[$x]["item_amount"],2), $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, reformatProdNumber($posdata[$x]["item_number_prod"]), $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $barcode, $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $posdata[$x]["item_title"], $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $posdata[$x]["_dsc_str"], $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($posdata[$x][$fldx1]), $_FRM["TBLHCXL"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($posdata[$x][$fldx2]), $_FRM["TBLHCXL"]);$colidx++;

      $sts    = array();
      $tmpsts = getItemStorehouses($CON, $orderheader["req_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"], false,  0, 2);

      foreach($tmpsts AS $st)
         $sts[] = $st;

      $xls->write_string($rowidx, $colidx, $posdata[$x]["ubi_name"], $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, "[  ]", $_FRM["TBLRB"]);$colidx++;
      $xls->write_string($rowidx, $colidx, "[  ]", $_FRM["TBLRB"]);$colidx++;
      $rowidx++;
   }
   
   $rowidx++;
   $rowidx++;

   $xls->write_string($rowidx, 8, "TOTAL", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 9, printPrice($orderheader[$fldx3] + $orderheader["req_discount_amount_netto"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $xls->write_string($rowidx, 8, "DESCUENTO", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 9, printPrice($orderheader["req_discount_amount_netto"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $rowidx++;
   $xls->write_string($rowidx, 8, "NETO", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 9, printPrice($orderheader["req_total_netto"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $xls->write_string($rowidx, 8, "IVA", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 9, printPrice($orderheader["req_total_taxes"]), $_FRM["TBLHCXL"]);
   $rowidx++;
   $xls->write_string($rowidx, 8, "TOTAL", $_FRM["TBLLB"]);
   $xls->write_string($rowidx, 9, printPrice($orderheader["req_total_brutto"]), $_FRM["TBLHCXL"]);
   $rowidx++;  
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
//----------------------------------------------------------------------------------


function xls_createStatsUbicacionchange($CON, $uid)
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
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("ARTICULO", "UBICACION ORIGEN", "UBICACION DESTINO");
   $xls->set_column(0, 0, 50);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 20);

   $rowidx = 0;
   $xls->write_string($rowidx, 0, "NUMERO", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, sprintf("%05s", $headdata["id"]), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "CREADO", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, date("d.m.Y", $headdata["ubic_crtdat"]), $_FRM["TBLR"]);
   $rowidx++;
   $rowidx++;
   $rowidx++;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $row = $posdata[$x];
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $row["item_number_prod"]." - ".$row["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["ubi_name_orig"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, $row["ubi_name_dest"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsItemTransDetail($CON, $itemid)
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
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FECHA", "DOCUMENTO", "ENTRADA", "SALIDA", "SALDO", "CLIENTE", "OBSERVACIONES", "DESTINO");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 35);
   $xls->set_column(0, 6, 35);
   $xls->set_column(0, 7, 35);

   $rowidx = 0;
   $xls->write_string($rowidx, 0, "NOMBRE", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $items[$key]["item_title"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "NUMERO", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $items[$key]["item_number_prod"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "UNIDAD", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $items[$key]["unit_name"], $_FRM["TBLR"]);
   $rowidx++;
   $rowidx++;
   $rowidx++;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   $items = array_reverse($items);
   for($x = 0; $x < count($items) && $items != false; $x++)
   {
      $row = $items[$x];
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $row["FECHA"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["NUMERO DOCTO."], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, $row["ENTRADA"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, $row["SALIDA"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, $row["SALDO"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["CUSTNAME"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["OBSERV"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $row["DESTNAME"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSellingEvo($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_sell_evolution";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("MES NOMBRE", "MES");
   if((int)$_SESSION[$_sesmodulename]["sql_mode"] != 0)
      $cols = Array("DIA", "MES");
   $xls->set_column(0, 0, 18);
   $xls->set_column(0, 1, 9);

   $init_month = $_SESSION[$_sesmodulename]["sql_month1"];
   $end_month  = $_SESSION[$_sesmodulename]["sql_month2"];
   $end_year   = $_SESSION[$_sesmodulename]["sql_year1"];
   $init_year  = $end_year - $_SESSION[$_sesmodulename]["sql_yearcount"];
   
   //----------------------------------------------------------------------------------
   $colidx = 3;
   for($y = $init_year; $y <= $end_year; $y++)
   {
      $xls->write_string($rowidx, $colidx, $y, $_FRM["TBLH"]);
      $colidx += 3;
   }
   $rowidx = 1;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $colidx = 2;
   for($y = $init_year; $y <= $end_year; $y++)
   {
      $xls->write_string($rowidx, $colidx, "Cant", $_FRM["TBLH"]);
      $xls->set_column(0, $colidx, 6);
      $colidx++;
      $xls->write_string($rowidx, $colidx, "Monto", $_FRM["TBLH"]);
      $xls->set_column(0, $colidx, 12);
      $colidx++;
      $xls->write_string($rowidx, $colidx, "%", $_FRM["TBLH"]);
      $xls->set_column(0, $colidx, 6);
      $colidx++;
   }
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["MONTHNAME"])), $_FRM["TBLR"]);$colidx++;

      if((int)$_SESSION[$_sesmodulename]["sql_mode"] == 0)
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["MONTH"], $_FRM["TBLR"]);
         $colidx++;
      }
      else
      {
         $xls->write_string($rowidx, $colidx, $_SESSION[$_sesmodulename]["sql_month1"], $_FRM["TBLR"]);
         $colidx++;
      }

      for($y = $init_year; $y <= $end_year; $y++)
      {
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow[$sqlrow["MONTH"]."-".$y]["COUNT"]))), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow[$sqlrow["MONTH"]."-".$y]["VALUE"]))), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow[$sqlrow["MONTH"]."-".$y]["DIFF"])), $_FRM["TBLR"]);$colidx++;
      }

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}


function xls_createStorehousechange($CON, $sid)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.company_short, t3.company_short 'company_dest_name', t4.shop_name, t5.shop_name 'shop_dest_name',
                   t6.user_firstname 'upd_firstname', t6.user_lastname 'upd_lastname',
                   t7.user_firstname 'crt_firstname', t7.user_lastname 'crt_lastname', t8.cust_name
            from storehousechanges t1
            LEFT OUTER JOIN company_data t2 ON t1.strc_company_id = t2.id
            LEFT OUTER JOIN company_data t3 ON t1.strc_company_dest_id = t3.id
            LEFT OUTER JOIN company_shops t4 ON t1.strc_shop_id = t4.id
            LEFT OUTER JOIN company_shops t5 ON t1.strc_shop_dest_id = t5.id
            LEFT OUTER JOIN user t6 ON t1.strc_updusr = t6.id
            LEFT OUTER JOIN user t7 ON t1.strc_crtusr = t7.id
            LEFT OUTER JOIN customer t8 ON t1.strc_custid = t8.id
            where
            t1.id = {$sid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   //----------------------------------------------------------------------------------
   $sql = " select t2.*, t3.item_title, t3.item_number_prod
            from storehousechanges_items t2
            LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
            where
            t2.strc_id     = {$sid} and
            t2.item_type   = 'item'
            UNION ALL
            select t2.*, t3.item_title, t3.item_number_prod
            from storehousechanges_items t2
            LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
            where
            t2.strc_id     = {$sid} and
            t2.item_type   = 'itemlist'
            order by 3 asc";
   $posdata = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $doctype    = "storehousechanges";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$headdata["strc_number"]}.{$doctype}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$headdata["strc_number"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 55);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 25);
   $xls->set_column(0, 4, 25);
   $xls->set_column(0, 5, 12);

   $rowidx = 0;
   $colidx = 0;
   $xls->write_string($rowidx, 0, "Nï¿½mero", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["strc_number"], $_FRM["TBLR"]);
   $rowidx++;
   if((int)$headdata["strc_custid"])
   {
      $xls->write_string($rowidx, 0, "Cliente", $_FRM["TBLH"]);
      $xls->write_string($rowidx, 1, $headdata["cust_name"], $_FRM["TBLR"]);
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, "Empresa origen", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["company_short"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Sucursal origen", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["shop_name"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Empresa destino", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["company_dest_name"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Sucursal destino", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["shop_dest_name"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Fecha", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, date('d.m.Y',$headdata["strc_date"]), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Estado", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, getShipmentStatus($headdata["strc_status"], false), $_FRM["TBLR"]);
   $rowidx++;
   if((int)$headdata["strc_shopsent"])
   {
      $xls->write_string($rowidx, 0, "Nï¿½mero Guia", $_FRM["TBLH"]);
      $xls->write_string($rowidx, 1, $headdata["strc_isshoporder_dlvnumber"], $_FRM["TBLR"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, "Fecha Guia", $_FRM["TBLH"]);
      if((int)$headdata["strc_isshoporder_dlvdate"])
         $xls->write_string($rowidx, 1, date('d.m.Y',$headdata["strc_isshoporder_dlvdate"]), $_FRM["TBLR"]);
      else
         $xls->write_string($rowidx, 1, "", $_FRM["TBLR"]);
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, "Creado por", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["crt_firstname"]." ".$headdata["crt_lastname"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Creado", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, displayDate($headdata["strc_crtdat"]), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Cambiado por", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["upd_firstname"]." ".$headdata["upd_lastname"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Cambiado", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, displayDate($headdata["strc_upddat"]), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Observaciones", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["strc_desc"], $_FRM["TBLR"]);
   $rowidx++;
   $rowidx++;

   if((int)$headdata["strc_shopsent"])
      $cols = Array("Nï¿½MERO", "ARTï¿½CULO", "PEDIDO", "ENTREGA", "BODEGA ORIGEN", "BODEGA DESTINO");
   else
      $cols = Array("Nï¿½MERO", "ARTï¿½CULO", "CANTIDAD", "BODEGA ORIGEN", "BODEGA DESTINO");

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   $rowcount = count($posdata);
   for($x = 0; $x < $rowcount; $x++)
   {
      $colidx   = 0;
      $unitdesc = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);

      $xls->write_string($rowidx, $colidx, $posdata[$x]["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $posdata[$x]["item_title"]." (".$unitdesc.")", $_FRM["TBLR"]);$colidx++;

      if((int)$headdata["strc_shopsent"])
      {
         $xls->write_number($rowidx, $colidx, $posdata[$x]["item_shoporder_amt"], $_FRM["TBLR"]);
         $colidx++;
         $xls->write_number($rowidx, $colidx, $posdata[$x]["item_amount"], $_FRM["TBLR"]);
         $colidx++;
      }
      else
      {
         $xls->write_number($rowidx, $colidx, $posdata[$x]["item_amount"], $_FRM["TBLR"]);
         $colidx++;
      }
      
      unset($itemsts);
      $currstock = 0;
      if($posdata[$x]["item_type"] == "item")
         $itemsts = getItemStorehouses($CON, $headdata["strc_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
      else
      {
         $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
         $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_id"], $itemlistpos[0]["item_id"], "item");
      }
      if(count($itemsts))
      {
         foreach(array_keys($itemsts) AS $itemstid)
         {
            if($itemstid == $posdata[$x]["item_st_id"])
            {
               $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_id"], $itemstid, (int)$posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
            }
         }
      }
      $xls->write_string($rowidx, $colidx, $itemsts[$posdata[$x]["item_st_id"]]." (".$currstock.")", $_FRM["TBLR"]);$colidx++;
      unset($itemsts);
      $currstock = 0;
      
      if($posdata[$x]["item_type"] == "item")
         $itemsts = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
      else
      {
         $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
         $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $itemlistpos[0]["item_id"], "item");
      }
      if(count($itemsts))
      {
         foreach(array_keys($itemsts) AS $itemstid)
         {
            if($itemstid == $posdata[$x]["item_st_dest_id"])
            {
               $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_dest_id"], $itemstid, (int)$posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
            }
         }
      }
      $xls->write_string($rowidx, $colidx, $itemsts[$posdata[$x]["item_st_dest_id"]]." (".$currstock.")", $_FRM["TBLR"]);$colidx++;
//       $xls->write_number($rowidx, $colidx, $posdata[$x]["item_costprice_netto_total"], $_FRM["TBLR"]);$colidx++;

      $gesnetto += $posdata[$x]["item_costprice_netto_total"];
      $rowidx++;
   }
//    $xls->write_number($rowidx, 5, $gesnetto, $_FRM["TBLH"]);

   //----------------------------------------------------------------------------------
   $workbook->close();
}

function xls_createStockchanges($CON, $sid)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t4.stkis_title, t5.cust_name, t6.user_firstname, t6.user_lastname,
            t2.user_firstname 'upd_firstname', t2.user_lastname 'upd_lastname',
            t3.user_firstname 'crt_firstname', t3.user_lastname 'crt_lastname'
            from stockchanges t1
            LEFT OUTER JOIN user                t2 ON t1.stk_updusr = t2.id
            LEFT OUTER JOIN user                t3 ON t1.stk_crtusr = t3.id
            LEFT OUTER JOIN stockchanges_issues t4 ON t1.stk_issueid = t4.id
            LEFT OUTER JOIN customer            t5 ON t1.stk_custid = t5.id
            LEFT OUTER JOIN user                t6 ON t1.stk_userid = t6.id
            where
            t1.id = {$sid}";
   $headdata = $CON->select($sql);
   $headdata = $headdata[0];

   $_REQUEST["cid"] = $headdata["stk_companyid"];
   $_REQUEST["sid"] = $headdata["stk_shopid"];

   //----------------------------------------------------------------------------------
   $sql = " select *
            from company_shops
            where
            id = {$_REQUEST["sid"]}";
   $shop = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select *
            from company_data
            where
            id = {$_REQUEST["cid"]}";
   $company = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $sql = " select t2.*, t3.item_title, t3.item_number_prod, 'itemtype' 'item'
            from stockchanges_items t2
            LEFT OUTER JOIN item t3       ON t2.item_id = t3.id
            where
            t2.stk_id      = {$sid} and
            t2.item_type   = 'item'
            UNION ALL
            select t2.*, t3.item_title, t3.item_number_prod, 'itemtype' 'list'
            from stockchanges_items t2
            LEFT OUTER JOIN itemlist t3   ON t2.item_id = t3.id
            where
            t2.stk_id      = {$sid} and
            t2.item_type   = 'itemlist'
            order by 3 asc";
   $posdata = $CON->select($sql);
   for($x = 0; $x < count($posdata) && $posdata != false; $x++)
   {
      $itemtype = "itemlist";
      if($posdata[$x]["itemtype"] == "itemtypeitem")
         $itemtype = "item";
         
      $sql = " select item_barcode
               from item_barcodes
               where
               item_id = {$posdata[$x]["item_id"]} and
               item_type = '{$itemtype}'";
      $itembarcode = $CON->select($sql);
      $itembarcode = $itembarcode[0]["item_barcode"];
      $posdata[$x]["_barcode"] = $itembarcode;
   }

   //----------------------------------------------------------------------------------
   $doctype    = "stockchanges";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$headdata["stk_num"]}.{$doctype}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$headdata["stk_num"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 45);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 20);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 12);

   $rowidx = 0;
   $colidx = 0;
   $xls->write_string($rowidx, 0, "Nï¿½mero", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["stk_num"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Motivo", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["stkis_title"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Empresa", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $company[0]["company_short"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Surcusal", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $shop[0]["shop_name"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Fecha", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, date('d.m.Y',$headdata["stk_bookdate"]), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Estado", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, getShipmentStatus($headdata["stk_status"], false), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Creado por", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["crt_firstname"]." ".$headdata["crt_lastname"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Creado", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, date('d.m.Y',$headdata["stk_crtdat"]), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Cambiado por", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["upd_firstname"]." ".$headdata["upd_lastname"], $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Cambiado", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, date('d.m.Y',$headdata["stk_upddat"]), $_FRM["TBLR"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "Observaciones", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 1, $headdata["stk_annotation"], $_FRM["TBLR"]);
   $rowidx++;
   if((int)$headdata["stk_isventainterna"])
   {
      $xls->write_string($rowidx, 0, "Venta interna", $_FRM["TBLH"]);
      $xls->write_string($rowidx, 1, "SI", $_FRM["TBLR"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, "Pagado", $_FRM["TBLH"]);
      if((int)$headdata["stk_ispayed"])
         $xls->write_string($rowidx, 1, "SI", $_FRM["TBLR"]);
      else
         $xls->write_string($rowidx, 1, "NO", $_FRM["TBLR"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, "Cliente", $_FRM["TBLH"]);
      $xls->write_string($rowidx, 1, $headdata["cust_name"], $_FRM["TBLR"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, "Usuario", $_FRM["TBLH"]);
      $xls->write_string($rowidx, 1, $headdata["user_firstname"]." ".$headdata["user_lastname"], $_FRM["TBLR"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, "C.I.", $_FRM["TBLH"]);
      $xls->write_string($rowidx, 1, $headdata["stk_cinumber"], $_FRM["TBLR"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, "Numero Int.", $_FRM["TBLH"]);
      $xls->write_string($rowidx, 1, $headdata["stk_vintnumber"], $_FRM["TBLR"]);
      $rowidx++;
   }
   $rowidx++;

   if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
      $cols = Array("NUMERO", "ARTï¿½CULO", "COD. BARRA", "BODEGA", "CANTIDAD", "PRECIO/NETO", "PRECIO/TOTAL");
   else
      $cols = Array("NUMERO", "ARTï¿½CULO", "COD. BARRA", "BODEGA", "CANTIDAD");

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   $rowcount = count($posdata);
   for($x = 0; $x < $rowcount; $x++)
   {
      $colidx     = 0;
      $desc       = trim(addslashes($posdata[$x]["item_title"]));
      $unitdesc   = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);

      $xls->write_string($rowidx, $colidx, $posdata[$x]["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $desc." (".$unitdesc.")", $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $posdata[$x]["_barcode"], $_FRM["TBLR"]);$colidx++;
      if($posdata[$x]["item_type"] == "item")
         $itemsts = getItemStorehouses($CON, $headdata["stk_shopid"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
      else
      {
         $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
         $itemsts     = getItemStorehouses($CON, $headdata["stk_shopid"], $itemlistpos[0]["item_id"], "item");
      }
      if(count($itemsts))
      {
         foreach(array_keys($itemsts) AS $itemstid)
         {
            $currstock = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"], $itemstid, $posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
            if($posdata[$x]["item_st_id"] == $itemstid)
            {
               $xls->write_string($rowidx, $colidx, $itemsts[$itemstid]." (".printPrice($currstock,2).")", $_FRM["TBLR"]);$colidx++;
            }
         }
      }
      $xls->write_number($rowidx, $colidx, $posdata[$x]["item_amount"], $_FRM["TBLR"]);$colidx++;
      if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
      {
         $xls->write_number($rowidx, $colidx, $posdata[$x]["item_sellprice_brutto"], $_FRM["TBLR"]);
         $colidx++;
         $xls->write_number($rowidx, $colidx, $posdata[$x]["item_amount"] * $posdata[$x]["item_sellprice_brutto"], $_FRM["TBLR"]);
      }

      $gesnetto += $posdata[$x]["item_costprice_netto"] * $posdata[$x]["item_amount"];
      $gesbruto += $posdata[$x]["item_sellprice_brutto"];
      $rowidx++;
   }

   if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
   {
      if($headdata["stk_discount_perc"] > 0.00 || $headdata["stk_discount_amt"] > 0.00)
      {
         $xls->write_string($rowidx, 5, "SUBTOTAL", $_FRM["TBLR"]);
         $xls->write_number($rowidx, 6, ($headdata["stk_total_netto"] + $headdata["stk_discount_amount_netto"]), $_FRM["TBLR"]);
         $rowidx++;
         if($headdata["stk_discount_perc"] > 0.00)
         {
            $xls->write_string($rowidx, 5, "% DESCUENTO", $_FRM["TBLR"]);
            $xls->write_number($rowidx, 6, ($headdata["stk_discount_perc"]), $_FRM["TBLR"]);
            $rowidx++;
         }
         if($headdata["stk_discount_amt"] > 0.00)
         {
            $xls->write_string($rowidx, 5, "$ DESCUENTO", $_FRM["TBLR"]);
            $xls->write_number($rowidx, 6, ($headdata["stk_discount_amt"]), $_FRM["TBLR"]);
            $rowidx++;
         }
      }
      $xls->write_string($rowidx, 5, "TOTAL", $_FRM["TBLH"]);
      $xls->write_number($rowidx, 6, $headdata["stk_total_netto"], $_FRM["TBLH"]);
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
}

//----------------------------------------------------------------------------------
function xls_createStatsStockItems($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockitems";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   if((int)$_REQUEST["mid"] != 1215)
   {
      $cols = Array("Numero"
      , "Articulo/Familia"
      , "Unidad"
      , "Bodega"
      , "Stock Fisico."
      , "Tipo"
      , "Gramaje"
      , "Medidas"
      , "Cliente"
      , "Diseño"
      , "Cantidad"

      , "Valor Unitario Compra ($)"
      , "Valor Total Compra"
      , "Valor Unitario Ventas ($)"
      , "Valor Total Venta"
      , "Cantidad Transito"
      , "Cantidad Disponible");

      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 75);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 55);
      $xls->set_column(0, 4, 12);

      $xls->set_column(0, 5, 9);
      $xls->set_column(0, 6, 12);
      $xls->set_column(0, 7, 12);
      $xls->set_column(0, 8, 55);
      $xls->set_column(0, 9, 35);

      $xls->set_column(0, 10, 20);
      $xls->set_column(0, 11, 20);
      $xls->set_column(0, 12, 20);
      $xls->set_column(0, 13, 20);
      $xls->set_column(0, 14, 20);
      $xls->set_column(0, 15, 20);
      $xls->set_column(0, 16, 20);

      //----------------------------------------------------------------------------------
      $rowidx = 0;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      $x = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
      {
         $colidx = 0;

         if(count($_SESSION["STATS"][$_sesmodulename]["DATA"])-1 != $x)
         {
            $xls->write_string($rowidx, $colidx, $sqlrow["num"], $_FRM["TBLR"]);$colidx++;
         }
         else
         {
            $xls->write_string($rowidx, $colidx, $sqlrow["num"], $_FRM["TBLH"]);$colidx++;
         }
         $xls->write_string($rowidx, $colidx, $sqlrow["title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["unit"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["sth"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, str_replace(".", "", $sqlrow["inv"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["fab_type"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["fab_mat_gramms"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["fab_med_width"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"]." ", $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["fab_design_name"]." ", $_FRM["TBLR"]);$colidx++;

         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amount"],2), $_FRM["TBLR"]);$colidx++; 
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["price"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["totalprice"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["valor_unitario_vta"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["valor_total_vta"],2), $_FRM["TBLR"]);$colidx++;

         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_TRANSSTOCK"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_DISPOSTOCK"],2), $_FRM["TBLR"]);$colidx++;

         $rowidx++;
         $x++;
      }
   }
   else
   {
      
      $cols = Array("Numero", "Articulo/Familia", "Unidad", "Bodega", "Stock Fisico..", "Cantidad Transito", "Cantidad Disponible");
      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 75);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 30);
      $xls->set_column(0, 4, 20);
      $xls->set_column(0, 5, 20);
      $xls->set_column(0, 6, 20);

      //----------------------------------------------------------------------------------
      $rowidx = 0;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      $x = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
      {
         $colidx = 0;

         if(count($_SESSION["STATS"][$_sesmodulename]["DATA"])-1 != $x)
         {
            $xls->write_string($rowidx, $colidx, $sqlrow["num"], $_FRM["TBLR"]);$colidx++;
         }
         else
         {
            $xls->write_string($rowidx, $colidx, $sqlrow["num"], $_FRM["TBLH"]);$colidx++;
         }
         $xls->write_string($rowidx, $colidx, $sqlrow["title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["unit"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["sth"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, str_replace(".", "", $sqlrow["inv"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_TRANSSTOCK"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["_DISPOSTOCK"],2), $_FRM["TBLR"]);$colidx++;
         // $xls->write_number($rowidx, 10, getPrice($_SESSION["STATS"][$_sesmodulename]["ges_ventas"]), $_FRM["TBLH"]);
         $rowidx++;
         $x++;
      }
   }


   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSellingShops($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsinvoicesshops";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   if(!(int)$_SESSION[$_sesmodulename]["sql_reptype"])
   {
      $cols = Array("EMPRESA", "SUCURSAL", "TIPO",  "TOTAL/NETO", "TOTAL/IVA", "TOTAL/BRUTO");
      $xls->set_column(0, 0, 28);
      $xls->set_column(0, 1, 28);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 15);
      $xls->set_column(0, 4, 15);
      $xls->set_column(0, 5, 15);

      $rowidx = 0;
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      //----------------------------------------------------------------------------------
      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["compname"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["shopname"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["type"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_netto"]))), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes"]))), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"]))), $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
      $rowidx++;
   }
   else
   {
      $cols = Array("TIPO", "Nï¿½MERO", "FECHA", "CLIENTE", "VENDEDOR", "TOTAL/NETO", "TOTAL/IVA", "TOTAL/BRUTO");
      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 15);
      $xls->set_column(0, 2, 16);
      $xls->set_column(0, 3, 25);
      $xls->set_column(0, 4, 18);
      $xls->set_column(0, 5, 15);
      $xls->set_column(0, 6, 15);
      $xls->set_column(0, 7, 15);

      //----------------------------------------------------------------------------------
      $rowidx = 0;
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["HEAD"]) AS $shopid)
      {
         if(count($_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid]))
         {
            $xls->write_string($rowidx, 0, $_SESSION["STATS"][$_sesmodulename]["HEAD"][$shopid]["shop_name"], $_FRM["TBLH"]);
            $rowidx++;
            $counter = 0;
            for($y = 0; $y < count($cols); $y++)
               $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
            $rowidx++;
            foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$shopid] AS $sqlrow)
            {
               $colidx = 0;
               $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val1"])), $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["val2"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["val3"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["vala"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["valb"], $_FRM["TBLR"]);$colidx++;
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val5"]))), $_FRM["TBLR"]);$colidx++;
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val6"]))), $_FRM["TBLR"]);$colidx++;
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val7"]))), $_FRM["TBLR"]);$colidx++;
               $rowidx++;
            }
            $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
            $rowidx++;
         }
      }
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsEstadoResultado($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "estado_resultado";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   if((int)$_SESSION[$_sesmodulename]["sql_prccomp"])
      $prccol = "PRECIO/U LISTA";
   else
      $prccol = "PRECIO/U OC";

   $cols = Array("FECHA", "DIA", "HABIL", "CALCULO", "$ VENTA", "$ COSTO PMP", "$ N/C", "$ N/D", "$ GASTOS", "$ MARGEN", "% MARGEN");
   $xls->set_column(0, 0, 25);
   $xls->set_column(0, 1, 25);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 25);
   $xls->set_column(0, 6, 15);
   $xls->set_column(0, 7, 15);
   $xls->set_column(0, 8, 15);
   $xls->set_column(0, 9, 15);
   $xls->set_column(0, 10, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val1"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val2"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val3"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val4"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val5"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val6"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val7"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val8"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val9"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val10"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["val11"])),2), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;
   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["val1"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val2"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val3"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val4"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val5"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val6"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsItemProductsInvcComp($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_buying_products_invclist";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   if((int)$_SESSION[$_sesmodulename]["sql_prccomp"])
      $prccol = "PRECIO/U LISTA";
   else
      $prccol = "PRECIO/U OC";
      
   $cols = Array("NUMERO", "ARTï¿½CULO", "UNIDAD", "COD/PROV", "CANTIDAD FACTURA", "CANTIDAD OC",
                 "CANTIDAD GUIA", "PRECIO/U FACTURA", $prccol, "DIF $", "DIF %", "OBSERVACIONES");
   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 35);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 20);
   $xls->set_column(0, 5, 15);
   $xls->set_column(0, 6, 15);
   $xls->set_column(0, 7, 20);
   $xls->set_column(0, 8, 15);
   $xls->set_column(0, 9, 10);
   $xls->set_column(0, 10, 10);
   $xls->set_column(0, 11, 35);
   $xls->set_column(0, 12, 10);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;

      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"], 2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amt_oc"], 2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amt_shp"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["prc_unit"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["prc_oc"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["diffunit"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["diffperc"],0), $_FRM["TBLR"]);$colidx++;

      $xls->write_string($rowidx, $colidx, $sqlrow["comments"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createShipmentApproveList($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "ship_app_list";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("NOMBRE", "COD. CONT.", "COD. PROV", "UNIDAD", "VENTA", "STOCK", "O.C.","CLIENTES");
   $xls->set_column(0, 0, 40);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 18);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 10);
   $xls->set_column(0, 5, 10);
   $xls->set_column(0, 6, 10);
   $xls->set_column(0, 7, 80);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $catid)
   {
      $counter = 0;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$catid] AS $sqlrow)
      {
         $stck = getPrice($sqlrow["item_stock"],2);
         $occk = getPrice($sqlrow["item_oc"],2);
            
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["gesamount"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, $stck, $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, $occk, $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["custs"], $_FRM["TBLR"]);$colidx++;
         
         /*
         
         $data[$counter]["NOMBRE"]     = $sqlrow["item_title"];
         $data[$counter]["COD. CONT."] = $sqlrow["item_number_prod"];
         $data[$counter]["COD. PROV"]  = $sqlrow["item_code"];
         $data[$counter]["UNIDAD"]     = $sqlrow["unit_name"];
         $data[$counter]["VENTA"]      = $sqlrow["gesamount"];
         $data[$counter]["STOCK"]      = $stck ;
         $data[$counter]["O.C."]       = $occk;
         $data[$counter]["CLIENTES"]   = $sqlrow["custs"];
         */
         $rowidx++;
      }

      $xls->write_string($rowidx, 0, $_SESSION["STATS"][$_sesmodulename]["DATA1"][$catid]["cat_title"], $_FRM["TBLR"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
      $rowidx++;
   }
   /*
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;

      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"], 2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amt_oc"], 2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amt_shp"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["prc_unit"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["prc_oc"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["diffunit"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["diffperc"],0), $_FRM["TBLR"]);$colidx++;

      $xls->write_string($rowidx, $colidx, $sqlrow["comments"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }
   */

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsBuyNoteDiscounts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buy_discounts";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO","ARTICULO","COD/PROV","UNIDAD","CANTIDAD","P/FACTURA BASICO","P/FACTURA FINAL","NC","DESC $","DESC %","P/FINAL");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 55);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 20);
   $xls->set_column(0, 6, 20);
   $xls->set_column(0, 7, 15);
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $idx1)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["val1"], $_FRM["TBLH"]);$colidx++;
      $rowidx++;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, $sqlrow["val1"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["val2"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["val3"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["val4"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val5"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val6"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val7"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["val8"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val9"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val10"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val11"]), $_FRM["TBLR"]);$colidx++;

         $rowidx++;
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createPriceCompareSupplier($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "compare_supplier";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "ARTICULO", "CODIGO/PROV", "UNIDAD", "NOMBRE", "FORMA DE PAGO", "PRECIO BASICO", "PRECIO FINAL", "P/ULT/COMPR", "F/ULT/COMPR", "NOMBRE", "FORMA DE PAGO", "PRECIO BASICO", "PRECIO FINAL", "P/ULT/COMPR", "F/ULT/COMPR", "NOMBRE", "FORMA DE PAGO", "PRECIO BASICO", "PRECIO FINAL", "P/ULT/COMPR", "F/ULT/COMPR");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 55);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 40);
   $xls->set_column(0, 5, 40);
   $xls->set_column(0, 6, 15);
   $xls->set_column(0, 7, 15);
   $xls->set_column(0, 8, 15);
   $xls->set_column(0, 9, 15);
   $xls->set_column(0, 10, 40);
   $xls->set_column(0, 11, 40);
   $xls->set_column(0, 12, 15);
   $xls->set_column(0, 13, 15);
   $xls->set_column(0, 14, 15);
   $xls->set_column(0, 15, 15);
   $xls->set_column(0, 16, 40);
   $xls->set_column(0, 17, 40);
   $xls->set_column(0, 18, 15);
   $xls->set_column(0, 19, 15);
   $xls->set_column(0, 20, 15);
   $xls->set_column(0, 21, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $xls->write_string($rowidx, 4, "PROVEEDOR #1", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 10, "PROVEEDOR #2", $_FRM["TBLH"]);
   $xls->write_string($rowidx, 16, "PROVEEDOR #3", $_FRM["TBLH"]);
   $rowidx++;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $idx1)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["val1"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["val2"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["val3"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["val4"], $_FRM["TBLR"]);$colidx++;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $sqlrow)
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["val1"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["val2"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val3"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val4"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val5"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["val6"], $_FRM["TBLR"]);$colidx++;
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsOrderItems($CON, $mode = "")
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "order_items";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("CONFIRM. COMPRA", "FECHA", "CLIENTE", "CANTIDAD");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 15);
   $xls->set_column(0, 2, 55);
   $xls->set_column(0, 3, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA1"]) AS $idx1)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["DATA1"][$idx1]["title"], $_FRM["TBLH"]);$colidx++;
      $rowidx++;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA2"][$idx1] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["req_crtdat"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"]), $_FRM["TBLR"]);$colidx++;

         $rowidx++;
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createItemBuyPriceCompare($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "price_buying_compare";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "ARTï¿½CULO", "CODIGO/PROV", "UNIDAD", "PROVEEDOR", "PRECIO\nACTUAL\nBASICO",
                 "PRECIO\nACTUAL\nFINAL", "FECHA\nACTUALIZ.", "PRECIO\nANTIGUO\nBASICO", "PRECIO\nANTIGUO\nFINAL",
                 "PRECIO\nACTUALIZ", "DIF/$", "DIF/%");
   $xls->set_column(0, 0, 8);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 8);
   $xls->set_column(0, 4, 30);
   $xls->set_column(0, 5, 8);
   $xls->set_column(0, 6, 9);
   $xls->set_column(0, 7, 10);
   $xls->set_column(0, 8, 8);
   $xls->set_column(0, 9, 10);
   $xls->set_column(0, 10, 10);
   $xls->set_column(0, 11, 8);
   $xls->set_column(0, 12, 8);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;
   
   foreach($_SESSION["STATS"][$doctype]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_company"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["item_costprice_netto"],4), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["item_final_netto_cur"],4), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["prc_crtdat_cur"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["last_costprice_netto"],4), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["item_final_netto_las"],4), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["prc_crtdat_las"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["prcdiff"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["prcperc"],2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsBuyInvoicesTotal($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buy_invoices_sum";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("PROVEEDOR", "MONTO DOCTO.", "MONTO REAL", "DEUDA");
   $xls->set_column(0, 0, 75);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 20);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;
   
   foreach($_SESSION["STATS"][$doctype]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["invc_total_brutto"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["invc_real"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["nopayed"],2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsRebatesHistory($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "rebates_hist";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("PROVEEDOR", "NOMBRE REBATE", "TIPO", "MES", "Aï¿½O", "REBATE CALC", "FACTURA", "FECHA FACTURA", "MONTO FACTURA");
   $xls->set_column(0, 0, 35);
   $xls->set_column(0, 1, 30);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 10);
   $xls->set_column(0, 5, 14);
   $xls->set_column(0, 6, 14);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;
   
   foreach($_SESSION["STATS"][$doctype]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["mark_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["month"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["mark_year"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["rebate_value"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["rebate_invc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["invc_total_netto"],2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createItemPriceHistProd($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "price_history_prodbuy";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("PROVEEDOR", "FECHA", "USUARIO", "PRECIO/BASICO", "DESC/$", "DESC/%", "PRECIO/FINAL");
   $xls->set_column(0, 0, 55);
   $xls->set_column(0, 1, 15);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 10);
   $xls->set_column(0, 5, 10);
   $xls->set_column(0, 6, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;
   
   foreach($_SESSION["STATS"][$doctype]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_company"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["prc_crtdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_lastname"], $_FRM["TBLR"]);$colidx++;

      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["prc_costprice_netto"],4), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["diffa"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["diffp"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["buyval"],4), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsRebates($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "rebates";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "FECHA", "TOTAL/NETO");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 15);
   $xls->set_column(0, 2, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;
   
   foreach($_SESSION["STATS"]["rebatesdet"]["DATA"] AS $row)
   {
      $colidx = 0;
      $row["val3"] = str_replace("<b>", "", str_replace("</b>", "", str_replace("$", "", $row["val3"])));

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val1"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val2"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($row["val3"]), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsReferencias($CON, $mode = "")
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "invc_refs{$mode}";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FECHA", "TIPO DOC", "NUMERO DOC", "FECHA VENCIMIENTO", "DEBE", "HABER", "SALDO");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 19);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 15);
   $xls->set_column(0, 6, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $custid)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["NAME"].": ".$_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["RUT"], $_FRM["TBLH"]);$colidx++;
      $rowidx++;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;
      
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$custid] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["type"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_estpay_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["debe"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["haber"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["saldo"]), $_FRM["TBLR"]);$colidx++;

         $rowidx++;
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsProductsMovis($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prod_movis";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FECHA", "TIPO", "NUMERO", "ENTRADA", "SALIDA", "PRECIO/V", "PRECIO/C", "DESCUENTOS",
                 "DESC/%", "TOTAL", "RUT", "CLIENTE/PROVEEDOR");
   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 13);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 10);
   $xls->set_column(0, 5, 10);
   $xls->set_column(0, 6, 10);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 10);
   $xls->set_column(0, 9, 10);
   $xls->set_column(0, 10, 15);
   $xls->set_column(0, 11, 30);
   //----------------------------------------------------------------------------------
   $rowidx = 0;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["DATE"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["TYPE"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["NUMBR"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amtpos"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["negpos"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amtprc"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["negprc"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["dscges"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["dsc"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["netto"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["RUT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["NAME"], $_FRM["TBLR"]);$colidx++;
      
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createSupplierOrderGen($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "supporder_gen";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "CODIGO/PROV", "MARCADO", "VENTA", "ACTUAL", "TRANS", "MIN", "PROVEEDOR");
   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 10);
   $xls->set_column(0, 5, 8);
   $xls->set_column(0, 6, 8);
   $xls->set_column(0, 7, 8);
   $xls->set_column(0, 8, 8);
   $xls->set_column(0, 9, 20);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["amount"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["vamount"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["storehousestock"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["transstock"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["stcontent"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["thissuppname"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsCustNoPayed($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "custnopayed";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);


   //----------------------------------------------------------------------------------
   $cols = Array("FACTURA", "FECHA", "FORMA DE PAGO", "VENCIMIENTO", "ATRASO", "MONTO/FACTURA", "MONTO/NOTAS", "MONTO/FINAL");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 28);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 10);
   $xls->set_column(0, 5, 15);
   $xls->set_column(0, 6, 15);
   $xls->set_column(0, 7, 15);
   //----------------------------------------------------------------------------------
   $rowidx = 0;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA"]) AS $custid)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["NAME"].": ".$_SESSION["STATS"][$_sesmodulename]["_CUST"][$custid]["RUT"], $_FRM["TBLH"]);$colidx++;
      $rowidx++;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;
      
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$custid] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["pay_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_estpay_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["waitdays"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["invc_total_brutto"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["notesbrutto"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["invc_total_final"]), $_FRM["TBLR"]);$colidx++;

         $rowidx++;
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsCustomers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "customer";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("RUT", "NOMBRE", "RAZON SOCIAL", "DIRECCIÓN", "EMAIL", "TELÉFONO", "CELULAR", "CONTACTO",
                 "SITIO WEB", "REGION", "PROVINCIA", "COMUNA", "LISTA DE PRECIO", "VENDEDOR", "GIRO",
                 "FORMA DE PAGO",  "MONTO CREDITO", "TRANSPORTISTA","TIENE CONVENIO", "RUBRO","SUBRUBRO","CANAL"
                 ,"FECHA CREACIÓN"
                 ,"ULTIMA COTIZACIÓN"
                 ,"MONTO ULT. COTIZACIÓN"
                 ,"ULTIMA C.C."
                 ,"MONTO ULTIMA C.C."
                 ,"PRESUPUESTADO");

   $xls->set_column(0, 0, 13);
   $xls->set_column(0, 1, 35);
   $xls->set_column(0, 2, 35);
   $xls->set_column(0, 3, 35);
   $xls->set_column(0, 4, 20);
   $xls->set_column(0, 5, 18);
   $xls->set_column(0, 6, 18);
   $xls->set_column(0, 7, 18);
   $xls->set_column(0, 8, 18);
   $xls->set_column(0, 9, 20);
   $xls->set_column(0, 10, 20);
   $xls->set_column(0, 11, 20);
   $xls->set_column(0, 12, 16);
   $xls->set_column(0, 13, 25);
   $xls->set_column(0, 14, 25);
   $xls->set_column(0, 15, 16);
   $xls->set_column(0, 16, 25);
   $xls->set_column(0, 17, 25);
   $xls->set_column(0, 18, 25);
   $xls->set_column(0, 19, 25);
   $xls->set_column(0, 20, 25); // SUBRUBRO
   $xls->set_column(0, 21, 16);
   $xls->set_column(0, 22, 16);
   $xls->set_column(0, 23, 16);
   $xls->set_column(0, 24, 16);
   $xls->set_column(0, 25, 16);
   $xls->set_column(0, 26, 16);
   $xls->set_column(0, 27, 16);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   $formatoFecha = $workbook->add_format();  
   $formatoFecha->set_num_format('DD/MM/YYYY');
   $formatoNumero = $workbook->add_format();
   $formatoNumero->set_num_format('#.##0,0#');


   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_company"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_street"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_email"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_phone"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_cellphone"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_fax"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_website"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["pro_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["nombre"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["pl_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["username"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["giro_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["pay_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["cust_pricetolerance"],0), $_FRM["MONTO"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["trans_name"], $_FRM["TBLR"]);$colidx++;
      if((int)$sqlrow["cust_convenio_act"])
         $xls->write_string($rowidx, $colidx, "SI", $_FRM["TBLR"]);
      else
         $xls->write_string($rowidx, $colidx, "NO", $_FRM["TBLR"]);
      $colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cat_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["subrubro"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["canal"], $_FRM["TBLR"]);$colidx++;
      $excelDate = ($sqlrow["creacion"] / 86400) + 25569;
      if($sqlrow["creacion"]==0)
      {
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++; 
      }
      else         
      {
         $xls->write($rowidx, $colidx, $excelDate, $formatoFecha);$colidx++;
      }

      $excelDate = ($sqlrow["ultima_cotizacion"] / 86400) + 25569;
      if($sqlrow["ultima_cotizacion"]==0)
      {
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
      }
      else         
      {
         $xls->write($rowidx, $colidx, $excelDate, $formatoFecha);$colidx++;
      }

      $xls->write_number($rowidx, $colidx, $sqlrow["monto_cotizacion"], $formatoNumero);$colidx++;

      $excelDate = ($sqlrow["req_crtdat"] / 86400) + 25569;
      if($sqlrow["req_crtdat"]==0)
      {
         $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++; 
      }
      else         
      {
          $xls->write($rowidx, $colidx, $excelDate, $formatoFecha);$colidx++;
      }

      $xls->write_number($rowidx, $colidx, round($sqlrow["req_total_netto"]), $formatoNumero);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["presupuesto"],$_FRM["TBLR"]);$colidx++;
      
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsSellingRotation($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buyvssellrotation";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "STOCK/$", "STOCK/C", "COMPRA/$", "COMPRA/C", "VENTA/$", "VENTA/C", "V/C/%");
   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 45);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 10);
   $xls->set_column(0, 5, 10);
   $xls->set_column(0, 6, 10);
   $xls->set_column(0, 7, 10);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["stock_avgcost"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["stock"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["buytot"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["buyamt"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["selltot"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["sellamt"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["sellper"],2), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsBuyDiscounts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "invc_seller_stats";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("TIPO", "PROVEEDOR", "RUT", "FECHA", "DOC", "MONTO/N", "DESCUENTO $", "DESCUENTO %", "MONTO/F");
   $xls->set_column(0, 0, 13);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 13);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 13);
   $xls->set_column(0, 6, 13);
   $xls->set_column(0, 7, 13);
   $xls->set_column(0, 8, 13);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["type"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["supp_company"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["supp_rut"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["shp_delivery_date"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["shp_supplier_docnum"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["shp_total_netto_orig"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["docdscprc"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["docdscper"])),2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["shp_total_netto"]))), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsSellingSellers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "invc_seller_stats";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("TIPO DOC","NUMERO DOC", "FECHA", "VENCIMIENTO","CANAL", "CLIENTE", "RUT", "CREACION", "VENDEDOR", "MONTO TOTAL", "% COM", "$ COM","PRESUPUESTADO","PERMANENTE");
   $xls->set_column(0, 0, 20);
   $xls->set_column(0, 1, 13);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 13);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 40);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 26);
   $xls->set_column(0, 9, 13);
   $xls->set_column(0, 10, 10);
   $xls->set_column(0, 11, 10);
   $xls->set_column(0, 12, 10);
   $xls->set_column(0, 13, 25);
   $xls->set_column(0, 14, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   $formatoFecha = $workbook->add_format();  
   $formatoFecha->set_num_format('DD/MM/YYYY');

   $formatCentrado = $workbook->add_format();
   $formatCentrado->set_Align('center');
     

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["doctype"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_docnumber"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_date"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_estpay_date"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["canal"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_company"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_rut"])), $_FRM["TBLR"]);$colidx++;
      $excelDate = ($sqlrow["creacion"] / 86400) + 25569;
      if($sqlrow["creacion"]==0)
      {
          $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;   
      }
      else         
      {
          $xls->write($rowidx, $colidx, $excelDate, $formatoFecha);$colidx++;
      }
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["seller_name"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["user_comission_perc"])),2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["comamt"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["presupuesto"])), $formatCentrado);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_permanente"])), $formatCentrado);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $rowidx++;
   $cols = Array("NOMBRE VENDEDOR","VENTA TOTAL", "COMISION", "RETENCION", "MONTO PAGO");
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["OVERW"]) AS $sqlrowid)
   {
      $sqlrow = $_SESSION["STATS"][$_sesmodulename]["OVERW"][$sqlrowid];

      $colidx  = 0;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["NAME"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["VALUE"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["COMM"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["RETEN"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["PAYMENT"]))), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsSellingSellersOffer($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "invc_offer_stats";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Número"
   ,"Fecha"
   ,"RUT"
   ,"Cliente"
   ,"Rubro"
   ,"Subrubro"
   ,"Canal"
   ,'En Presupuesto'
   ,'Permanente'
   ,"Fecha creación de Cliente"
   ,"Vendedor"
   ,"Nº CC"
   ,"Tipo Producto"
   ,"Monto Cotizado"
   ,"Estado");
   $xls->set_column(0, 0, 16);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 40);
   $xls->set_column(0, 4, 30);
   $xls->set_column(0, 5, 30);
   $xls->set_column(0, 6, 20);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 16);
   $xls->set_column(0, 9, 20);
   $xls->set_column(0, 10, 50);
   $xls->set_column(0, 11, 12);
   $xls->set_column(0, 12, 12);
   $xls->set_column(0, 13, 12);
   $xls->set_column(0, 14, 12);
   
   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_docnumber"])), $_FRM["TBLR"]);$colidx++;
      /*$xls->write($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_date"])), $_FRM["TBLR"]);$colidx++;*/
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["invc_date"]))), $_FRM["FECHA"]);$colidx++;      
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_rut"])), $_FRM["TBLR"]);$colidx++;      
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_company"])), $_FRM["TBLR"]);$colidx++;
      /* $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_contacto"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_email"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_cellphone"])), $_FRM["TBLR"]);$colidx++; 
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["nombre"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["pro_name"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["name"])), $_FRM["TBLR"]);$colidx++;*/
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cat_name"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["sub_cat_name"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["canal"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_presupuesto"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_permanente"])), $_FRM["TBLR"]);$colidx++;      
      if($sqlrow["fecha_creacion"]=="")
      {
         $xls->write_string($rowidx, $colidx, "", $_FRM["FECHA"]);$colidx++;
      }
      else   
      {
         $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["fecha_creacion"]))), $_FRM["FECHA"]);$colidx++;      
      }
      /*$xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["fecha_creacion"])), $_FRM["TBLR"]);$colidx++;*/
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["seller_name"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["ccnr"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["fab_type"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", getPrice($sqlrow["req_total_netto"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["req_status"])), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $rowidx++;
   $cols = Array("NOMBRE VENDEDOR","TOTAL", "FINALIZADO", "ACEPTADO", "RECHAZADO");
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["OVERW"]) AS $sqlrowid)
   {
      $sqlrow = $_SESSION["STATS"][$_sesmodulename]["OVERW"][$sqlrowid];

      $colidx  = 0;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["NAME"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["AMT"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["STATES"]["Finalizado"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["STATES"]["Aceptado"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["STATES"]["Rechazado"]))), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createUserSalarySelling($CON)
{
   global $_LANG;
   global $_sesmodulename;
   global $_sesmodulename2;

   //----------------------------------------------------------------------------------
   $doctype    = "xls_user_salary_selling";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["STATS"][$_sesmodulename2]["id"]}.{$_SESSION["STATS"][$_sesmodulename2]["uid"]}.{$doctype}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 10);
   $xls->set_column(0, 2, 53);
   $xls->set_column(0, 3, 32);
   $xls->set_column(0, 4, 9);
   $xls->set_column(0, 5, 7);
   $xls->set_column(0, 6, 8);
   $xls->set_column(0, 7, 7);
   $xls->set_column(0, 8, 7);
   $xls->set_column(0, 9, 7);
   $xls->set_column(0, 10, 7);

   $rowidx = 0;
   $xls->write_string($rowidx, $colidx, "LIQUIDACION TRABAJADOR", $_FRM["TBLH"]);
   $rowidx++;
   $rowidx++;

   $perscc = 0;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA2"]) AS $sesidx)
   {
      $headstr = str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename2]["HEAD2"][$sesidx]["name"]));
      $colidx  = 0;
      $xls->write_string($rowidx, $colidx, $headstr, $_FRM["TBLH"]);
      $rowidx++;
      $tmp = true;
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA2"][$sesidx]) AS $sesidx2)
      {
         $row    = $_SESSION["STATS"][$_sesmodulename2]["DATA2"][$sesidx][$sesidx2];
         $colidx = 0;

         if((int)$row["merg"])
         {
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["name"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
            $xls->merge_cells($rowidx, 0, $rowidx, 2);
            $colidx++;
         }
         else
         {
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["name"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val0"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val1"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val2"])), $_FRM["TBLR"]);$colidx++;
         }

         if($tmp || (int)$row["text"])
         {
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val3"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val4"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val5"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val6"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val7"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val8"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val9"])), $_FRM["TBLR"]);$colidx++;
         }
         else
         {
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val3"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, str_replace(",", ".", (str_replace("<b>", "", str_replace("</b>", "", $row["val4"])))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val5"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val6"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val7"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val8"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val9"]))), $_FRM["TBLR"]);$colidx++;
         }

         $rowidx++;
         $tmp = false;
      }
      $rowidx++;
      $rowidx++;
      $perscc++;
   }
   $rowidx++;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA3"]) AS $sesidx)
   {
      $headstr = str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename2]["HEAD3"][$sesidx]["name"]));
      $colidx  = 0;
      $xls->write_string($rowidx, $colidx, $headstr, $_FRM["TBLH"]);
      $rowidx++;
      $tmp = true;
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename2]["DATA3"][$sesidx]) AS $sesidx2)
      {
         $row    = $_SESSION["STATS"][$_sesmodulename2]["DATA3"][$sesidx][$sesidx2];
         $colidx = 0;

         if((int)$row["merg"])
         {
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["name"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
            $xls->merge_cells($rowidx, 0, $rowidx, 2);
         }
         else
         {
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["name"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val0"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val1"])), $_FRM["TBLR"]);$colidx++;
         }

         if($tmp)
         {
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val2"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val3"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val4"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val5"])), $_FRM["TBLR"]);$colidx++;
         }
         else
         {
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val2"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, str_replace(",", ".", (str_replace("<b>", "", str_replace("</b>", "", $row["val3"])))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, str_replace(",", ".", (str_replace("<b>", "", str_replace("</b>", "", $row["val4"])))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val5"]))), $_FRM["TBLR"]);$colidx++;
         }

         $rowidx++;
         $tmp = false;
      }
      $rowidx++;
      $rowidx++;
   }
   $rowidx++;

   $colidx = 0;
   $xls->write_string($rowidx, $colidx, "TOTALES", $_FRM["TBLH"]);
   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename2]["DATA4"] AS $row)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["name"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, "", $_FRM["TBLR"]);$colidx++;

      if((int)$row["merg"])
         $xls->merge_cells($rowidx, 0, $rowidx, 2);

      $colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("$ ", "", str_replace("<b>", "", str_replace("</b>", "", $row["val1"])))), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createItemPriceMargen($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "price_itemmargen";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FACTURA", "FECHA", "CLIENTE", "NUMERO", "ARTICULO", "UNIDAD", "CANTIDAD", "$/VENTA/U",
                 "$/COMPRA/U", "$/VENTA/T", "$/COMPRA/T", "%/MARGEN", "$/MARGEN");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 10);
   $xls->set_column(0, 2, 30);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 30);
   $xls->set_column(0, 5, 8);
   $xls->set_column(0, 6, 10);
   $xls->set_column(0, 7, 10);
   $xls->set_column(0, 8, 11);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 12);
   $xls->set_column(0, 11, 10);
   $xls->set_column(0, 12, 10);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["unitsellprice"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_costprice"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_netto_dsc"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_costprice_total"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["marge_perc"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["marge_val"]), $_FRM["TBLR"]);$colidx++;


      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemStockcounts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbuyshpinvc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NÚMERO", "ARTÍCULO/FAMILIA", "UNIDAD", "COD/PROV", "RECUENTO", "FECHA", "BODEGA", "STOCK ANTERIOR", "AJUSTE", "STOCK NUEVO", "VALOR/U", "VALOR/TOTAL", "FACTURA", "FECHA/FACT");
   if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
   {
      $cols = Array("Nï¿½MERO", "FAMILIA", "RECUENTO", "FECHA", "VALOR/TOTAL");
   }
   $xls->set_column(0, 0, 16);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 16);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 16);
   $xls->set_column(0, 9, 16);
   $xls->set_column(0, 10, 16);
   $xls->set_column(0, 11, 16);
   $xls->set_column(0, 12, 12);
   $xls->set_column(0, 13, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      if($_SESSION[$_sesmodulename]["sql_dspmode"] == 2)
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["stc_num"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["value_total"], $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["stc_num"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["st_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_amount_stock"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_amount_book"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["stock_new"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_costprice_avg_netto"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["value_total"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_costprice_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_costprice_docdate"], $_FRM["TBLR"]);$colidx++;
      }

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsBuyShpInvc($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbuyshpinvc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("PROVEEDOR", "RUT", "FECHA GUIA", "NUMERO GUIA", "NUMERO FACTURA", "FECHA FACTURA");
   $xls->set_column(0, 0, 38);
   $xls->set_column(0, 1, 18);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 18);
   $xls->set_column(0, 5, 16);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["supp_company"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shp_delivery_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["shp_supplier_docnum"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSellShpInvc($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellshpinvc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("CLIENTE", "RUT", "FECHA GUIA", "NUMERO GUIA", "TIPO", "NUMERO FACTURA", "FECHA FACTURA", "ANULADO");
   $xls->set_column(0, 0, 38);
   $xls->set_column(0, 1, 18);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 18);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 16);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["cust_company"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["dlv_delivery_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["dlv_docnum"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["ANULADO"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsStockValues($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockshop";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "CODIGO PROV", "ARTICULO", "UNIDAD", "COSTO ï¿½", "STOCK/ACT", "$ STOCK");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 50);
   $xls->set_column(0, 3, 25);
   $xls->set_column(0, 4, 25);
   $xls->set_column(0, 5, 25);
   $xls->set_column(0, 6, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   //----------------------------------------------------------------------------------
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["HEAD"]) AS $x)
   {
      $sqlrow = $_SESSION["STATS"][$_sesmodulename]["HEAD"][$x];
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLH"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLH"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLH"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLH"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["avgcost"],4), $_FRM["TBLH"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["lineges"],2), $_FRM["TBLH"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["itemgescost"]), $_FRM["TBLH"]);$colidx++;
      $rowidx++;

      //----------------------------------------------------------------------------------
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$x] AS $row)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $row["invc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["supp_short"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_amount"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_costprice_netto"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_dsc"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_dsc_nc"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $row["item_cost_pricedsc"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemReserved($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsitemreserved";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   if(!$_SESSION[$_sesmodulename]["sql_customer"])
   {
      $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "CODIGO/PROV", "CONFIRM.COMPRA", "CLIENTE", "FECHA", "CANTIDAD");
      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 12);
      $xls->set_column(0, 4, 12);
      $xls->set_column(0, 5, 30);
      $xls->set_column(0, 6, 12);
      $xls->set_column(0, 7, 12);
   }
   else
   {
      $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "CODIGO/PROV", "CONFIRM.COMPRA", "FECHA", "CANTIDAD");
      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 12);
      $xls->set_column(0, 4, 12);
      $xls->set_column(0, 5, 12);
      $xls->set_column(0, 6, 12);
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["item_number_prod"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
      if(!$_SESSION[$_sesmodulename]["sql_customer"])
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);
         $colidx++;
      }
      $xls->write_string($rowidx, $colidx, $sqlrow["req_crtdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["transstock"])),2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }


   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemInvoiced($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsiteminvoiced";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   if(!$_SESSION[$_sesmodulename]["sql_customer"])
   {
      $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "CODIGO/PROV", "GUIA", "CLIENTE", "FECHA", "CANTIDAD");
      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 12);
      $xls->set_column(0, 4, 12);
      $xls->set_column(0, 5, 30);
      $xls->set_column(0, 6, 12);
      $xls->set_column(0, 7, 12);
   }
   else
   {
      $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "CODIGO/PROV", "GUIA", "FECHA", "CANTIDAD");
      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);
      $xls->set_column(0, 2, 12);
      $xls->set_column(0, 3, 12);
      $xls->set_column(0, 4, 12);
      $xls->set_column(0, 5, 12);
      $xls->set_column(0, 6, 12);
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["item_number_prod"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["req_number"], $_FRM["TBLR"]);$colidx++;
      if(!$_SESSION[$_sesmodulename]["sql_customer"])
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);
         $colidx++;
      }
      $xls->write_string($rowidx, $colidx, $sqlrow["req_crtdat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["transstock"])),2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }


   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemTransitState($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_transit_cumpl";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "SOLICITADO", "RECIBIDO", "PENDIENTE", "ESTADO");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 70);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 16);

   $_RES = $_SESSION["STATS"][$_sesmodulename]["_RES"];
   $_NUM = $_SESSION["STATS"][$_sesmodulename]["_NUM"];
   $_SUP = $_SESSION["STATS"][$_sesmodulename]["_SUP"];
   $_DAT = $_SESSION["STATS"][$_sesmodulename]["_DAT"];


   //----------------------------------------------------------------------------------
   $rowidx = 0;

   foreach(array_keys($_RES) AS $sordid)
   {
      $colidx = 1;
      $xls->write_string($rowidx, $colidx, "{$_SUP[$sordid]}, {$_NUM[$sordid]}, {$_DAT[$sordid]}", $_FRM["TBLR"]);$colidx++;
      $rowidx++;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["item_number_prod"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["item_amount"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["item_amount_shipped"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["transstock"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["state"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      $rowidx++;
   }


   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsOCCompare($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_oc_cumplc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "COD/PROV", "OC/CANT", "OC/PRECIO/U", "OC/PRECIO/F", "FA/CANT", "FA/PRECIO/U", "FA/PRECIO/F", "ESTADO");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 16);

   //----------------------------------------------------------------------------------
   $rowidx = 0;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["INCLUDEDATA"]) AS $sordid)
   {
      $colidx = 1;

      $xhead = $_SESSION["STATS"][$_sesmodulename]["INCLUDEDATA"][$sordid];
      $xls->write_string($rowidx, $colidx, "{$xhead["SUPP"]}, {$xhead["NUMB"]}, {$xhead["DATE"]}", $_FRM["TBLR"]);$colidx++;
      $rowidx++;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$sordid] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["item_number_prod"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["_item_code"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["order_amt"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["order_price"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["order_price_total"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_amt"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_price"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_price_total"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["state"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      $rowidx++;
   }


   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemTransit($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsitemtransit";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("NUMERO", "ARTICULO", "UNIDAD", "NUMERO OC", "PROVEEDOR", "FECHA", "CANTIDAD");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 30);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["item_number_prod"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["sord_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["sord_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["transstock"])),2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }


   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemProducts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsitemproducts";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("NUMERO", "ARTï¿½CULO", "UNIDAD", "CANTIDAD", "MONTO (NETO)", "P/COMPRA ï¿½");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 20);
   $xls->set_column(0, 5, 20);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["NUMBER"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["TITLE"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["UNIT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["AMOUNT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["BUY"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["AVG_COST"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $colidx = 0;
   $xls->write_string($rowidx, $colidx, "TOTAL", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, " ", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, " ", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["HEAD"]["AMOUNT"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["HEAD"]["BUY"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["HEAD"]["AVG_COST"], $_FRM["TBLH"]);$colidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemProductsDetails($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_buying_products_details";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("NUMERO", "ARTÍCULO", "UNIDAD", "COD/PROV", "PROVEEDOR", "TIPO", "DOCTO", "ORDEN DE COMPRA", "FECHA", "CANTIDAD", "NETO/TOTAL");
   $xls->set_column(0, 0, 9);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 9);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 30);
   $xls->set_column(0, 5, 11);
   $xls->set_column(0, 6, 11);
   $xls->set_column(0, 7, 15);
   $xls->set_column(0, 8, 11);
   $xls->set_column(0, 9, 11);
   $xls->set_column(0, 10, 11);
   $xls->set_column(0, 11, 11);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["note_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["sord_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_amount"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_costprice"],2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemSuppliers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsitemsuppliers";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("PROVEEDOR", "NOMBRE", "RUT", "EXTENTO", "NETO", "IVA", "TOTAL");
   $xls->set_column(0, 0, 50);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["supp_company"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_short"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_total_taxes_exclude"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_total_netto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_total_taxes"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_total_brutto"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $colidx = 0;
   $xls->write_string($rowidx, $colidx, "TOTAL", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, " ", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, " ", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_taxes_exclude"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_netto"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_taxes"], $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, $_SESSION["STATS"][$_sesmodulename]["HEAD"]["invc_total_brutto"], $_FRM["TBLH"]);$colidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemSuppliersDetails($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_buying_supdetail";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("TIPO", "NUMERO DOCTO", "FECHA", "EXENTO", "NETO", "IVA", "BRUTO");
   $xls->set_column(0, 0, 16);
   $xls->set_column(0, 1, 16);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   

   $_SUP = $_SESSION["STATS"][$_sesmodulename]["HEADER"]["SUPS"];
   foreach(array_keys($_SUP) AS $supid)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, "{$_SUP[$supid]["NAME"]}, RUT: {$_SUP[$supid]["RUT"]}", $_FRM["TBLH"]);
      $rowidx++;
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
      $rowidx++;
      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$supid] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["note_type"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_docnumber"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_date"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes_exclude"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_netto"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes"])),2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"])),2), $_FRM["TBLR"]);$colidx++;

         $rowidx++;
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createItemPricePercent($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "perc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $cols = Array("NUMERO", "ARTï¿½CULO", "UNIDAD", "COD/PROV.", "P/VENTA\nBASICO", "P/VENTA\nFINAL", "COSTO\nBASICO", "COSTO\nFINAL", "MARGEN/C", "MARGEN/V", "PROVEEDOR");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 50);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($items AS $sqlrow)
   {
      $colidx = 0;

      if($sqlrow["item_type"] == "item_typeI")
         $sqlrow["item_type"] = "item";
      else
         $sqlrow["item_type"] = "itemlist";

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["itemshop_sellprice_netto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["selval"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_costprice_netto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["buyval"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["percent"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["percent_sell"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["supp_company"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createItemSellPrice($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "pricelist";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO", "CODIGO/PROV", "UNIDAD", "PRECIO/BASICO", "PRECIO/FINAL", "FECHA/ACTUL.");

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 14);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 14);
   $xls->set_column(0, 5, 14);
   $xls->set_column(0, 6, 14);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($items AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["itemshop_sellprice_netto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["selval"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["prc_crtdat"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createItemPriceHistory($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "pricelist";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
   {
      $cols = Array("FECHA", "USUARIO", "PRECIO/BASICO\nNETO", "IVA", "PRECIO/BASICO\nBRUTO");
      $xls->set_column(0, 0, 12);
      $xls->set_column(0, 1, 50);
      $xls->set_column(0, 2, 20);
      $xls->set_column(0, 3, 12);
      $xls->set_column(0, 4, 20);
   }
   else
   {
      $cols = Array("PROVEEDOR", "FECHA", "USUARIO", "PRECIO/BASICO\nNETO", "IVA", "PRECIO/BASICO\nBRUTO");
      $xls->set_column(0, 0, 50);
      $xls->set_column(0, 1, 12);
      $xls->set_column(0, 2, 50);
      $xls->set_column(0, 3, 20);
      $xls->set_column(0, 4, 12);
      $xls->set_column(0, 5, 20);
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($items AS $sqlrow)
   {
      $colidx = 0;

      if($_SESSION[$_sesmodulename]["sql_type"] == "selling")
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_crtdat"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["user_lastname"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_sellprice_netto"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_sellprice_taxes"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_sellprice_brutto"], $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write_string($rowidx, $colidx, $sqlrow["supp_company"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_crtdat"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["user_lastname"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_costprice_netto"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_costprice_taxes_perc"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["prc_costprice_brutto"], $_FRM["TBLR"]);$colidx++;
      }

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSellingProducts($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellingprod";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "ARTICULO/FAMILIA", "UNIDAD", "CODIGO CONTAB.", "CANTIDAD", "MONTO/NETO", "MONTO/IVA", "MONTO/BRUTO",  "% MONTO/BRUTO TOTAL");
   $xls->set_column(0, 0, 15);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 25);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 18);
   $xls->set_column(0, 8, 25);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["NUMBER"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["TITLE"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["UNIT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["CODE"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["AMOUNT"])),2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["SELL"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["IVA"])),0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["BRUTO"])),0), $_FRM["TBLR"]);$colidx++;
      // $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["TOT_BRUTTO"])),0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["PERC"])),0), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSellingCustomers($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellingcust";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("CLIENTE", "NOMBRE", "RUT", "EXTENTO", "NETO", "IVA", "TOTAL");
   $xls->set_column(0, 0, 50);
   $xls->set_column(0, 1, 35);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 12);
   $xls->set_column(0, 6, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_company"])), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes_exclude"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_netto"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes"]))), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"]))), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsSernapesca($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "sernapesca";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FECHA", "Nï¿½ Doc. Tributario", "INGRESO", "", "Nï¿½ Doc. Tributario", "", "EGRESO", "", "SALDO", "FIRMA Y TIMBRE");

   $xls->set_column(0, 0, 30);
   $xls->set_column(0, 1, 21);
   $xls->set_column(0, 2, 15);
   $xls->set_column(0, 3, 15);
   $xls->set_column(0, 4, 15);
   $xls->set_column(0, 5, 15);
   $xls->set_column(0, 6, 15);
   $xls->set_column(0, 7, 15);
   $xls->set_column(0, 8, 15);
   $xls->set_column(0, 9, 42);
   

   $xls->write_string(0, 0, "INFORME EN FORMATO HORIZONTAL HOJA DE OFICIO", $_FRM["TBLHCX"]);
   $xls->merge_cells(0, 0, 0, 7);
   $xls->write_string(1, 0, "PLANILLA DE STOCK", $_FRM["TBLHCX"]);
   $xls->merge_cells(1, 0, 1, 7);

   $xls->write_string(4, 1, "EMPRESA:", $_FRM["TBLHCX"]);
   $xls->write_string(4, 2, "AQUALITY LIMITADA.", $_FRM["TBLHCXL"]);
   if($_SESSION[$_sesmodulename]["sql_pcat"])
      $xls->write_string(5, 1, "FAMILIA:", $_FRM["TBLHCX"]);
   else
      $xls->write_string(5, 1, "PRODUCTO:", $_FRM["TBLHCX"]);
   $xls->write_string(5, 2, $_SESSION["STATS"][$_sesmodulename]["TITLE"], $_FRM["TBLHCXL"]);

   $xls->insert_bitmap(0, 0, "./images/docs/sernapesca.bmp", 0, 0, 1.2, 0.9);
   $xls->insert_bitmap(0, 6, "./images/docs/sernapesca2.bmp", 0, 0, 4, 1.3);

   //----------------------------------------------------------------------------------
   $rowidx = 8;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
   {
      $fmobj = $_FRM["TBLHC"];
      if($y == 2 || $y == 3 || $y == 6 || $y == 7)
      $fmobj = $_FRM["TBLHCB"];
      
      $xls->write_string($rowidx, $y, $cols[$y], $fmobj);
   }

   //$xls->write_string($rowidx +1, 9, "Funcionario Sernapesca", $_FRM["TBLHC"]);

   $xls->merge_cells($rowidx, 0, $rowidx +1, 0);
   $xls->merge_cells($rowidx, 1, $rowidx +1, 1);
   $xls->merge_cells($rowidx, 2, $rowidx, 3);
   $xls->merge_cells($rowidx, 4, $rowidx, 5);
   $xls->merge_cells($rowidx, 6, $rowidx, 7);
   $xls->merge_cells($rowidx, 8, $rowidx +1, 8);
   $xls->merge_cells($rowidx, 9, $rowidx +1, 9);
   $xls->merge_cells($rowidx, 4, $rowidx +1, 5);
   $xls->write_string($rowidx +1, 9, "Funcionario Sernapesca", $_FRM["TBLHC"]);
   $rowidx++;

   $xls->write_string($rowidx, 0, " ", $_FRM["TBLHC"]);
   $xls->write_string($rowidx, 1, " ", $_FRM["TBLHC"]);
   $xls->write_string($rowidx, 2, "Nï¿½ Cajas", $_FRM["TBLHC"]);
   $xls->write_string($rowidx, 3, "KILOS", $_FRM["TBLHC"]);
   $xls->write_string($rowidx, 4, " ", $_FRM["TBLHC"]);
   $xls->write_string($rowidx, 6, "Nï¿½ Cajas", $_FRM["TBLHC"]);
   $xls->write_string($rowidx, 7, "KILOS", $_FRM["TBLHC"]);
   $xls->write_string($rowidx, 8, " ", $_FRM["TBLHC"]);
   //$xls->write_string($rowidx, 9, " ", $_FRM["TBLHC"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      if($sqlrow[0]["datstr"] != "")
      {
         $xls->write_string($rowidx, 0, $sqlrow[0]["datstr"], $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 1, $sqlrow[0]["invc_docnumber"], $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 2, " ", $_FRM["TBLRC"]);
         $xls->write_number($rowidx, 3, $sqlrow[0]["tran_amount"], $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 4, " ", $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 5, " ", $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 6, " ", $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 7, " ", $_FRM["TBLRC"]);
         $xls->write_number($rowidx, 8, $sqlrow[0]["saldo"], $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 9, " ", $_FRM["TBLRC"]);
      }
      else
      {
         $xls->write_string($rowidx, 0, $sqlrow[1]["datstr"], $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 1, " ", $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 2, " ", $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 3, " ", $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 4, $sqlrow[1]["invc_docnumber"], $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 5, " ", $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 6, " ", $_FRM["TBLRC"]);
         $xls->write_number($rowidx, 7, $sqlrow[1]["tran_amount"], $_FRM["TBLRC"]);
         $xls->write_number($rowidx, 8, $sqlrow[1]["saldo"], $_FRM["TBLRC"]);
         $xls->write_string($rowidx, 9, " ", $_FRM["TBLRC"]);
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemEvolution($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockbyshops";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO/FAMILIA", "UNIDAD");

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
   {
     $cols[] = $idx." (+)";
     $cols[] = $idx." (-)";
   }

   $cols[] = "Total (+)";
   $cols[] = "Total (-)";
   $cols[] = "Saldo";

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 12);

   $cindex = 3;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
   {
      $xls->set_column(0, $cindex, 10);
      $cindex++;
      $xls->set_column(0, $cindex, 10);
      $cindex++;      
   }


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;

      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
      {
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][1],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][0],2), $_FRM["TBLR"]);$colidx++;
      }

      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["plus"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["minus"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["total"],2), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsBuyVsSell($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buyvssell";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_usd"])
      $cols = Array("Nï¿½MERO", "ARTICULO", "CODIGO/PROV", "UNIDAD", "P/COMPRA BASICO", "P/COMPRA FINAL", "P/VENTA BASICO", "P/VENTA FINAL", "P/VENTA FECHA CAMBIO", "P/USD", "STOCK");
   else
      $cols = Array("Nï¿½MERO", "ARTICULO", "CODIGO/PROV", "UNIDAD", "P/COMPRA BASICO", "P/COMPRA FINAL", "P/VENTA BASICO", "P/VENTA FINAL", "P/VENTA FECHA CAMBIO", "STOCK");

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
   {
     $cols[] = $idx." CO";
     $cols[] = $idx." VE";
   }

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 14);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 20);
   $xls->set_column(0, 5, 20);
   $xls->set_column(0, 6, 20);
   $xls->set_column(0, 7, 20);
   $xls->set_column(0, 8, 20);
   $xls->set_column(0, 9, 10);
   $xls->set_column(0, 10, 10);
   $cindex = 11;
   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
   {
      $xls->set_column(0, $cindex, 10);
      $cindex++;
      $xls->set_column(0, $cindex, 10);
      $cindex++;
   }


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;

      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_costprice_netto"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["buyvalfinal"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_sellprice_netto"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["sellval"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["prc_crtdat"], $_FRM["TBLR"]);$colidx++;
      if((int)$_SESSION[$_sesmodulename]["sql_usd"])
      {
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["sellusd"],2), $_FRM["TBLR"]);
         $colidx++;
      }
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["stock"],2), $_FRM["TBLR"]);$colidx++;
      

      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
      {
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][1],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][0],2), $_FRM["TBLR"]);$colidx++;
      }

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsBuyVsSellValues($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "buyvssellmargin";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO", "UNIDAD", "STOCK", "P/COMPRA");

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
   {
     $cols[] = $idx." CANT";
     $cols[] = $idx." MONT";
     $cols[] = $idx." % MA";
     $cols[] = $idx." $ MA";
   }

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 10);
   $xls->set_column(0, 3, 10);
   $xls->set_column(0, 4, 10);

   $cindex = 5;

   foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
   {
      $xls->set_column(0, $cindex, 12);
      $cindex++;
      $xls->set_column(0, $cindex, 12);
      $cindex++;
      $xls->set_column(0, $cindex, 12);
      $cindex++;
      $xls->set_column(0, $cindex, 12);
      $cindex++;    
   }


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;

      
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["stock"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["buyprc"],2), $_FRM["TBLR"]);$colidx++;
      

      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["DATA2"]["TIME"]) AS $idx)
      {
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][0],0), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][1],0), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][2],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TIME"][$idx][3],0), $_FRM["TBLR"]);$colidx++;
      }

      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemShops($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockbyshops";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO/FAMILIA", "CODIGO/PROV", "UNIDAD");

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"]); $i++)
   {
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"]." ACTUAL";
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"]." RESERVA";
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"]." COMPROMENTIDO";
   }

   $cols[] = "T/STOCK";
   $cols[] = "T/RESERVA";
   $cols[] = "T/COMPROMENTIDO";
   $cols[] = "T/DISPO";

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 60);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 20);

   $cindex = 4;

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"]); $i++)
   {
      $xls->set_column(0, $cindex, 15);
      $cindex++;
      $xls->set_column(0, $cindex, 15);
      $cindex++;
      $xls->set_column(0, $cindex, 15);
      $cindex++;
   }
   $xls->set_column(0, $cindex, 10);
   $xls->set_column(0, $cindex+1, 10);
   $xls->set_column(0, $cindex+2, 10);
   $xls->set_column(0, $cindex+3, 10);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;

      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["shops"][$i]["shop_name"];
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["SHOP"][$nombre_store]["curr"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["SHOP"][$nombre_store]["resr"],2), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["SHOP"][$nombre_store]["comp"],2), $_FRM["TBLR"]);$colidx++;
      }

      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["lineges"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["reslges"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["compges"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["dispo"],2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemStoreHouses($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stockbystorehouse";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO/FAMILIA", "CODIGO/PROV", "UNIDAD");

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]." ACTUAL";
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]." VALOR/U";
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]." VALOR";
   }

   if(count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]) > 1)
   {
      $cols[] =  "TOTAL";
      $cols[] =  "TOTAL VALOR";
   }

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 2, 10);

   $cindex = 4;

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
      $xls->set_column(0, $cindex, 15);
      $cindex++;
      $xls->set_column(0, $cindex, 15);
      $cindex++;
      $xls->set_column(0, $cindex, 15);
      $cindex++;      
   }
   
   $xls->set_column(0, $cindex, 15);
   $cindex++;      
   $xls->set_column(0, $cindex, 15);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;

      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"];
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["BODEGA"][$nombre_store]["curr"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["BODEGA"][$nombre_store]["costu"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["BODEGA"][$nombre_store]["cost"]), $_FRM["TBLR"]);$colidx++;
         
      }

      if(count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]) > 1)
      {
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["total"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["totalcost"]), $_FRM["TBLR"]);
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemStoreHousesCritics($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_minimumx";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO/FAMILIA", "CODIGO/PROV", "UNIDAD");

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]." ACTUAL";
     $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]." MIN";
   }

   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 10);

   $cindex = 4;

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
      $xls->set_column(0, $cindex, 12);
      $cindex++;
      $xls->set_column(0, $cindex, 12);
      $cindex++;
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;

      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"];
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["BODEGA"][$nombre_store]["curr"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["BODEGA"][$nombre_store]["min"]), $_FRM["TBLR"]);$colidx++;
         
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemStoreHousesMin($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stock_storehouses_min";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO/FAMILIA", "CODIGO/PROV", "UNIDAD");

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
      $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]." MIN";
      $cols[] = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"]." PED";
   }


   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 50);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 2, 10);

   $cindex = 4;

   for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
   {
      $xls->set_column(0, $cindex, 17);
      $cindex++;
      $xls->set_column(0, $cindex, 17);
      $cindex++;
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;

      for($i = 0; $i < count($_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"]); $i++)
      {
         $nombre_store = $_SESSION["STATS"][$_sesmodulename]["DATA2"]["storehouses"][$i]["st_name"];
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["BODEGA"][$nombre_store]["min"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, sprintf("%.2f", $sqlrow["BODEGA"][$nombre_store]["ped"]), $_FRM["TBLR"]);$colidx++;
         
      }
      $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createPricelistData($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   $items = $CON->select($sql);

   //----------------------------------------------------------------------------------
   $doctype    = "pricelistdata";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO", "UNIDAD", "STOCK", "P/BASE/NETO", "P/BASE/IVA", "P/BASE/BRUTO", "P/DETALLE/NETO", "P/DETALLE/IVA", "P/DETALLE/BRUTO", "P/MAYOR/NETO", "P/MAYOR/IVA", "P/MAYOR/BRUTO");

   $xls->set_column(0, 0, 18);
   $xls->set_column(0, 1, 45);
   for($x = 2; $x < 13; $x++)
      $xls->set_column(0, $x, 10);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   $plres = $_SESSION["_STATS"]["pricelistdata"]["plres"];
   foreach($items AS $sqlrow)
   {
       $colidx = 0;

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

       $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $unitdesc, $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$stock, $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$sqlrow["item_sellprice_netto"], $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$sqlrow["item_sellprice_taxes"], $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$sqlrow["item_sellprice_brutto"], $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$pl_item_sellprice_netto, $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$pl_item_sellprice_taxes, $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$pl_item_sellprice_brutto, $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$pl_item_sellprice_netto2, $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$pl_item_sellprice_taxes2, $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, (float)$pl_item_sellprice_brutto2, $_FRM["TBLR"]);$colidx++;
       
       $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createItemBuyPrice($CON, $sql)
{
   global $_LANG;
   global $_sesmodulename;

   $items = $_SESSION["STATS"][$_sesmodulename]["DATA"];

   //----------------------------------------------------------------------------------
   $doctype    = "pricelist";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Nï¿½MERO", "ARTICULO/FAMILIA", "CODIGO/PROV", "UNIDAD", "PROVEEDOR","PRECIO\nBASICO", "DESC\n%", "PRECIO\nFINAL", "PRECIO ULT.\nCOMPRA", "FECHA ULT.\nCOMPRA", "FACTURA");

   $xls->set_column(0, 0, 9);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 9);
   $xls->set_column(0, 4, 30);
   $xls->set_column(0, 5, 10);
   $xls->set_column(0, 6, 10);
   $xls->set_column(0, 7, 10);
   $xls->set_column(0, 8, 11);
   $xls->set_column(0, 9, 10);


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;

   foreach($items AS $sqlrow)
   {
       $colidx = 0;
       $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $sqlrow["item_code"], $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $sqlrow["supp_company"], $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, getPrice($sqlrow["item_costprice_netto"],4), $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, getPrice($sqlrow["descperc"],2), $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, getPrice($sqlrow["buyval"],4), $_FRM["TBLR"]);$colidx++;
       $xls->write_number($rowidx, $colidx, getPrice($sqlrow["buycostprice"],4), $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $sqlrow["buytstamp"], $_FRM["TBLR"]);$colidx++;
       $xls->write_string($rowidx, $colidx, $sqlrow["sinvc"], $_FRM["TBLR"]);$colidx++;
       $rowidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemTrans($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstocktrans";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "ARTICULO/FAMILIA", "UNIDAD", "ENTRADAS", "SALIDAS", "S/ACT.", "S/RES.", "S/COMP.", "S/DISP.");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 16);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 16);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unitdesc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["plus"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["minus"],2), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["act"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["res"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["comp"],0), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["disp"],0), $_FRM["TBLH"]);$colidx++;
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsItemValues($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsstockvalues";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("NUMERO", "ARTICULO/FAMILIA", "UNIDAD", "FECHA", "BODEGA", "TRANSACCION", "TIPO", "USUARIO",
                 "TIPO AJUSTE", "OBSERVACIONES", "CANTIDAD");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 40);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 12);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 14);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 30);
   $xls->set_column(0, 10, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_number_prod"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["item_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["unit_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["datstr"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["st_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["tran_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["tran_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["user_firstname"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["stkis_title"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["stk_annotation"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["tran_amount"],2), $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsPaymentSellCustomer($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statscustomer";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FECHA", "DOCUMENTO", "NUMERO DOC.", "MONTO", "FECHA VENC", "FECHA PAGO", "FORMA PAGO", "ESTADO PAGO", "DETALLES", "S/DOC", "S/TOTAL");
   $xls->set_column(0, 0, 10);
   $xls->set_column(0, 1, 16);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 20);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 16);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 20);
   $xls->set_column(0, 9, 12);
   $xls->set_column(0, 10, 12);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   $xls->write_string($rowidx, 0, "S/INICIAL", $_FRM["TBLR"]);
   $xls->write_number($rowidx, count($cols) -1, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO"]), $_FRM["TBLR"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["val1"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val2"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val3"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val4"]), $_FRM["TBLR"]);$colidx++;
      if($sqlrow["val5"] > 0)
      {
         $xls->write_string($rowidx, $colidx, date('d.m.Y', $sqlrow["val5"]), $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write_string($rowidx, $colidx,  " ", $_FRM["TBLR"]);$colidx++;
      }
      if($sqlrow["val6"] > 0)
      {
         $xls->write_string($rowidx, $colidx, date('d.m.Y', $sqlrow["val6"]), $_FRM["TBLR"]);$colidx++;
      }
      else
      {
         $xls->write_string($rowidx, $colidx,  " ", $_FRM["TBLR"]);$colidx++;
      }
      $xls->write_string($rowidx, $colidx, $sqlrow["val7"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val8"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["val11"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val9"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["val10"]), $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $xls->write_string($rowidx, 0, "TOTAL POR PAGAR", $_FRM["TBLH"]);
   $xls->write_number($rowidx, 10, getPrice($_SESSION["STATS"][$_sesmodulename]["init_saldo"]), $_FRM["TBLH"]);
   $rowidx++;
   $xls->write_string($rowidx, 0, "TOTAL DE VENTAS", $_FRM["TBLH"]);
   $xls->write_number($rowidx, 10, getPrice($_SESSION["STATS"][$_sesmodulename]["ges_ventas"]), $_FRM["TBLH"]);

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
// //----------------------------------------------------------------------------------
// function xls_createStatsPaymentSellCustomer($CON)
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
//    $fileext    = ".xls";
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
//    require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
//    require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');
// 
//    //----------------------------------------------------------------------------------
//    $workbook = new Workbook($pdffile);
//    include("./libs/thirdparty/biffwriter.extended/formate.inc");
//    $xls =& $workbook->add_worksheet("Tabla1");
//    $xls->set_landscape();
//    $xls->set_margins(0.5);
//    $xls->hide_gridlines();
//    $xls->set_print_scale(90);
// 
//    //----------------------------------------------------------------------------------
//    if((int)$_SESSION[$_sesmodulename]["sql_venc"])
//    {
//       $cols = Array("FECHA", "TIPO", "NUMERO INTERNO", "COMPROBANTE PROV.", "FACTURA REL.", "FECHA VENC", "EMPRESA", "DEBE", "HABER", "SALDO");
//       $xls->set_column(0, 0, 10);
//       $xls->set_column(0, 1, 16);
//       $xls->set_column(0, 2, 16);
//       $xls->set_column(0, 3, 20);
//       $xls->set_column(0, 4, 16);
//       $xls->set_column(0, 5, 16);
//       $xls->set_column(0, 6, 16);
//       $xls->set_column(0, 7, 12);
//       $xls->set_column(0, 8, 12);
//       $xls->set_column(0, 9, 12);
//    }
//    else
//    {
//       $cols = Array("FECHA", "TIPO", "NUMERO INTERNO", "COMPROBANTE PROV.", "FACTURA REL.", "EMPRESA", "DEBE", "HABER", "SALDO");
//       $xls->set_column(0, 0, 10);
//       $xls->set_column(0, 1, 16);
//       $xls->set_column(0, 2, 16);
//       $xls->set_column(0, 3, 20);
//       $xls->set_column(0, 4, 16);
//       $xls->set_column(0, 5, 16);
//       $xls->set_column(0, 6, 12);
//       $xls->set_column(0, 7, 12);
//       $xls->set_column(0, 8, 12);
//    }
// 
//    //----------------------------------------------------------------------------------
//    $rowidx = 0;
//    //----------------------------------------------------------------------------------
//    for($y = 0; $y < count($cols); $y++)
//       $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
// 
//    $rowidx++;
// 
//    $xls->write_string($rowidx, 0, "S/INICIAL", $_FRM["TBLR"]);
//    $xls->write_number($rowidx, count($cols) -1, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO"]), $_FRM["TBLR"]);
// 
//    $rowidx++;
//    foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
//    {
//       $colidx = 0;
//       $xls->write_string($rowidx, $colidx, $sqlrow["day"], $_FRM["TBLR"]);$colidx++;
//       $xls->write_string($rowidx, $colidx, $sqlrow["desc"], $_FRM["TBLR"]);$colidx++;
//       $xls->write_string($rowidx, $colidx, $sqlrow["invc_number"], $_FRM["TBLR"]);$colidx++;
//       $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
//       $xls->write_string($rowidx, $colidx, $sqlrow["note_invcnumber"], $_FRM["TBLR"]);$colidx++;
// 
//       if((int)$_SESSION[$_sesmodulename]["sql_venc"])
//       {
//          if($sqlrow["invc_estpay_date"] > 0)
//          {
//             $xls->write_string($rowidx, $colidx, date('d.m.Y', $sqlrow["invc_estpay_date"]), $_FRM["TBLR"]);$colidx++;
//          }
//          else
//          {
//             $xls->write_string($rowidx, $colidx,  " ", $_FRM["TBLR"]);$colidx++;
//          }
//       }
// 
//       $xls->write_string($rowidx, $colidx, $sqlrow["company_short"], $_FRM["TBLR"]);$colidx++;
//       $xls->write_number($rowidx, $colidx, $sqlrow["val_haber"], $_FRM["TBLR"]);$colidx++;
//       $xls->write_number($rowidx, $colidx, $sqlrow["val_debe"], $_FRM["TBLR"]);$colidx++;
//       $xls->write_number($rowidx, $colidx, $sqlrow["ges_saldo"], $_FRM["TBLR"]);$colidx++;
// 
//       $rowidx++;
//    }
// 
//    $xls->write_string($rowidx, 0, "TOTAL", $_FRM["TBLR"]);
//    $xls->write_number($rowidx, count($cols) -1 -2, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO_HABER"]), $_FRM["TBLR"]);
//    $xls->write_number($rowidx, count($cols) -1 -1, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO_DEBE"]), $_FRM["TBLR"]);
//    $xls->write_number($rowidx, count($cols) -1, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO_LAST"]), $_FRM["TBLR"]);
//    
//    //----------------------------------------------------------------------------------
//    $workbook->close();
//    return $filename;
// }

//----------------------------------------------------------------------------------
function xls_createStatsPaymentBuySupplier($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssupplier";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   if((int)$_SESSION[$_sesmodulename]["sql_venc"])
   {
      $cols = Array("FECHA", "TIPO", "NUMERO INTERNO", "COMPROBANTE PROV.", "FACTURA REL.", "FECHA VENC", "DEBE", "HABER", "SALDO");
      $xls->set_column(0, 0, 10);
      $xls->set_column(0, 1, 16);
      $xls->set_column(0, 2, 16);
      $xls->set_column(0, 3, 20);
      $xls->set_column(0, 4, 16);
      $xls->set_column(0, 5, 16);
      $xls->set_column(0, 6, 12);
      $xls->set_column(0, 7, 12);
      $xls->set_column(0, 8, 12);
   }
   else
   {
      $cols = Array("FECHA", "TIPO", "NUMERO INTERNO", "COMPROBANTE PROV.", "FACTURA REL.", "DEBE", "HABER", "SALDO");
      $xls->set_column(0, 0, 10);
      $xls->set_column(0, 1, 16);
      $xls->set_column(0, 2, 16);
      $xls->set_column(0, 3, 20);
      $xls->set_column(0, 4, 16);
      $xls->set_column(0, 5, 12);
      $xls->set_column(0, 6, 12);
      $xls->set_column(0, 7, 12);
   }

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   $xls->write_string($rowidx, 0, "S/INICIAL", $_FRM["TBLR"]);
   $xls->write_number($rowidx, count($cols) -1, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO"]), $_FRM["TBLR"]);

   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["day"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["desc"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_number"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["note_invcnumber"], $_FRM["TBLR"]);$colidx++;

      if((int)$_SESSION[$_sesmodulename]["sql_venc"])
      {
         if($sqlrow["invc_estpay_date"] > 0)
         {
            $xls->write_string($rowidx, $colidx, date('d.m.Y', $sqlrow["invc_estpay_date"]), $_FRM["TBLR"]);$colidx++;
         }
         else
         {
            $xls->write_string($rowidx, $colidx,  " ", $_FRM["TBLR"]);$colidx++;
         }
      }

      $xls->write_number($rowidx, $colidx, $sqlrow["val_debe"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, $sqlrow["val_haber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, $sqlrow["ges_saldo"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $xls->write_string($rowidx, 0, "TOTAL", $_FRM["TBLR"]);
   $xls->write_number($rowidx, count($cols) -1 -2, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO_DEBE"]), $_FRM["TBLR"]);
   $xls->write_number($rowidx, count($cols) -1 -1, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO_HABER"]), $_FRM["TBLR"]);
   $xls->write_number($rowidx, count($cols) -1, getPrice($_SESSION["STATS"][$_sesmodulename]["SALDO_LAST"]), $_FRM["TBLR"]);
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}


//----------------------------------------------------------------------------------
function xls_createStatsSellInvoices($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statssellinvoices";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("FECHA", "DOCTO.", "CLIENTE", "NUMERO DOCTO.", "MONTO DOCTO.", "A CUENTA", "DEUDA/MONTO",
                 "DIAS", "FECHA VENC", "APROB", "COMENTARIOS");
   $xls->set_column(0, 0, 16);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 35);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 16);
   $xls->set_column(0, 7, 12);
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 14);
   $xls->set_column(0, 10, 20);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   //----------------------------------------------------------------------------------
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;

      $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["tran_type"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_total_brutto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["tranpayed"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getPrice($sqlrow["trannopayed"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["paydays"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["invc_estpay_date"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["transtat"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["pay_comments"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $_TOTAL = $_SESSION[$_sesmodulename]["_TOTALS"];
   
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "TOTALES", $_FRM["TBLH"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "DOCTO.", $_FRM["TBLH"]);$colidx++;$colidx++;$colidx++;
   $xls->write_string($rowidx, $colidx, "MONTO DOCTO.", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, "DEUDA/MONTO", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, "VENC/MONTO", $_FRM["TBLH"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "FACTURAS", $_FRM["TBLH"]);$colidx++;$colidx++;$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["TOTAL"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REALNOPAYED"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["VENC"], $_FRM["TBLR"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "NOTAS DE CREDITO", $_FRM["TBLH"]);$colidx++;$colidx++;$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["N/C"]["TOTAL"] * -1, $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["N/C"]["REALNOPAYED"] * -1, $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["N/C"]["VENC"] * -1, $_FRM["TBLR"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "NOTAS DE DEBITO", $_FRM["TBLH"]);$colidx++;$colidx++;$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["N/D"]["TOTAL"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["N/D"]["REALNOPAYED"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["N/D"]["VENC"], $_FRM["TBLR"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "TOTAL", $_FRM["TBLH"]);$colidx++;$colidx++;$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["TOTAL"]       - $_TOTAL["N/C"]["TOTAL"]        + $_TOTAL["N/D"]["TOTAL"], $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REALNOPAYED"] - $_TOTAL["N/C"]["REALNOPAYED"]  + $_TOTAL["N/D"]["REALNOPAYED"], $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["VENC"]        - $_TOTAL["N/C"]["VENC"]         + $_TOTAL["N/D"]["VENC"], $_FRM["TBLH"]);$colidx++;
   

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsBuyInvoices($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbuyinvoices";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   $_SUPPNAMES    = $_SESSION["STATS"][$_sesmodulename]["_SUPNAMES"];
   $payments      = $_SESSION["STATS"][$_sesmodulename]["_PAYMENTS"];
   foreach($payments AS $payment)
      $idxpaysments[$payment["id"]] = $payment["pay_title"];
      
   //----------------------------------------------------------------------------------
   $cols = Array("FECHA", "DOCTO.", "NUMERO DOCTO.", "MONTO DOCTO.", "VALOR REAL", "FACTURA REL.", "OBSERVACIONES", "DEUDA/MONTO", "FECHA VENC", "DIAS DEMORA", "PAGADO");
   $xls->set_column(0, 0, 18);
   $xls->set_column(0, 1, 16);
   $xls->set_column(0, 2, 16);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 20);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 16);
   $xls->set_column(0, 9, 16);
   $xls->set_column(0, 10, 16);

   
   //----------------------------------------------------------------------------------
   $rowidx = 0;

   foreach(array_keys($_SUPPNAMES) AS $suppid)
   {
      $xls->write_string($rowidx, 0,"{$_SUPPNAMES[$suppid]["NAME"]}, RUT: {$_SUPPNAMES[$suppid]["RUT"]}", $_FRM["TBLH"]);
      $rowidx++;
      $addstr = "";
      if($_SUPPNAMES[$suppid]["DSCFINANCE"])
      {
         if((int)$_SUPPNAMES[$suppid]["DSCFINANCEAPPLY"])
            $addstr = "Descuento financiero: SI, ";
         else
            $addstr = "Descuento financiero: NO, ";
      }
      if((int)$_SUPPNAMES[$suppid]["PAYMENTDEFAULT"])
         $addstr .= "Forma de pago: {$idxpaysments[$_SUPPNAMES[$suppid]["PAYMENTDEFAULT"]]}";
      else
         $addstr .= "Forma de pago: Segun Documento";
         
      if($addstr != "")
      {
         $xls->write_string($rowidx, 0, $addstr, $_FRM["TBLR"]);
         $rowidx++;
      }
      
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

      $rowidx++;

      foreach($_SESSION["STATS"][$_sesmodulename]["DATA"][$suppid] AS $sqlrow)
      {
         $colidx = 0;

         $xls->write_string($rowidx, $colidx, $sqlrow["invc_receipt_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["tran_type"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["invc_total_brutto"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["value_real"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["note_invcnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_desc"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice($sqlrow["trannopayed"]), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_estpay_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["paydays"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_payed"], $_FRM["TBLR"]);$colidx++;

         $rowidx++;
      }
      $xls->write_string($rowidx, 0, " ", $_FRM["TBLH"]);
      $rowidx++;
   }
   $_TOTAL = $_SESSION[$_sesmodulename]["_TOTALS"];
   
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "TOTALES", $_FRM["TBLH"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "DOCTO.", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, "MONTO DOCTO.", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, "VALOR REAL", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, "PAGO/MONTO", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, "DEUDA/MONTO", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, $colidx, "VENC/MONTO", $_FRM["TBLH"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "FACTURAS", $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["TOTAL"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REAL"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REALPAYED"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REALNOPAYED"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["VENC"], $_FRM["TBLR"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "NOTAS DE CREDITO", $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de credito"]["TOTAL"] * -1, $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de credito"]["REAL"] * -1, $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de credito"]["REALPAYED"] * -1, $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de credito"]["REALNOPAYED"] * -1, $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de credito"]["VENC"] * -1, $_FRM["TBLR"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "NOTAS DE DEBITO", $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de debito"]["TOTAL"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de debito"]["REAL"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de debito"]["REALPAYED"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de debito"]["REALNOPAYED"], $_FRM["TBLR"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Nota de debito"]["VENC"], $_FRM["TBLR"]);$colidx++;
   $colidx = 0;
   $rowidx++;
   $xls->write_string($rowidx, $colidx, "TOTAL", $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["TOTAL"]       - $_TOTAL["Nota de credito"]["TOTAL"]        + $_TOTAL["Nota de debito"]["TOTAL"], $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REAL"]        - $_TOTAL["Nota de credito"]["REAL"]         + $_TOTAL["Nota de debito"]["REAL"], $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REALPAYED"]   - $_TOTAL["Nota de credito"]["REALPAYED"]    + $_TOTAL["Nota de debito"]["REALPAYED"], $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["REALNOPAYED"] - $_TOTAL["Nota de credito"]["REALNOPAYED"]  + $_TOTAL["Nota de debito"]["REALNOPAYED"], $_FRM["TBLH"]);$colidx++;
   $xls->write_number($rowidx, $colidx, $_TOTAL["Factura"]["VENC"]        - $_TOTAL["Nota de credito"]["VENC"]         + $_TOTAL["Nota de debito"]["VENC"], $_FRM["TBLH"]);$colidx++;
   
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsBooksSelling($CON, $execmode = 0)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbooksselling";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

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
   
   $xls->write_string(0, 0, "LIBRO DE VENTAS {$idxmonth} {$idxyear} {$addstr}- ".$company["company_name"], $_FRM["TBLH"]);
   $xls->write_string(1, 0, "" , $_FRM["TBLH"]);

   //----------------------------------------------------------------------------------
   $cols = Array("FACTURA", "FECHA", "CLIENTE", "RUT", "PAGADO", "NETO", "IVA", "TOTAL");
   $xls->set_column(0, 0, 20);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 40);
   $xls->set_column(0, 3, 16);
   $xls->set_column(0, 4, 16);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 16);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 16);
   $xls->set_column(0, 9, 16);
   $xls->set_column(0, 10, 16);
   $xls->set_column(0, 11, 16);
   $xls->set_column(0, 12, 16);
   $xls->set_column(0, 13, 16);

   //----------------------------------------------------------------------------------
   $rowidx = 2;

   //----------------------------------------------------------------------------------
   if(count($_SESSION["STATS"][$_sesmodulename]["BOLDAT"]))
   {
      $xls->write_string($rowidx, 0, "BOLETAS", $_FRM["TBLR"]);
      $rowidx++;

      for($y = 0; $y < count($_SESSION["STATS"][$_sesmodulename]["BOLCOL"]); $y++)
         $xls->write_string($rowidx, $y, $_SESSION["STATS"][$_sesmodulename]["BOLCOL"][$y]["name"], $_FRM["TBLH"]);
      $rowidx++;

      //----------------------------------------------------------------------------------
      $x = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["BOLDAT"] AS $row)
      {
         $cc = 1;
         $colidx = 0;
         foreach($_SESSION["STATS"][$_sesmodulename]["BOLCOL"] AS $col)
         {
            $frmstl = $_FRM["TBLH"];
            if($x < count($_SESSION["STATS"][$_sesmodulename]["BOLDAT"])-1)
               $frmstl = $_FRM["TBLR"];

            if($col["align"] == "left")
               $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $row["val{$cc}"])), $frmstl);
            else
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $row["val{$cc}"]))), $frmstl);

            $colidx++;
            $cc++;
         }
         $counter++;
         $rowidx++;
         $x++;
      }
      $rowidx++;
   }

   if(count($_SESSION["STATS"][$_sesmodulename]["INVCDATA"]))
   {
      $counter = 0;
      $xls->write_string($rowidx, 0, "FACTURAS", $_FRM["TBLR"]);
      $rowidx++;
      
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
         
      $rowidx++;
      foreach($_SESSION["STATS"][$_sesmodulename]["INVCDATA"] AS $sqlrow)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_company"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["paystate"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["tran_netto"]))), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes"]))), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"]))), $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      $xls->write_string($rowidx, 0, "TOTAL FACTURAS", $_FRM["TBLH"]);
      $xls->write_number($rowidx, 5, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["tran_netto"]))), $_FRM["TBLH"]);
      $xls->write_number($rowidx, 6, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["invc_total_taxes"]))), $_FRM["TBLH"]);
      $xls->write_number($rowidx, 7, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TINVCDATA"]["invc_total_brutto"]))), $_FRM["TBLH"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
      $rowidx++;
   }

   $cols[0] = "NOTA";
   for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
   {
      if(count($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"]))
      {
         if($xnotetype == 1)
            $addstr = "MENOS";
         else
            $addstr = "MAS";

         if((int)$execmode)
            $addstr = "";
            
         $counter = 0;
         $xls->write_string($rowidx, 0, $addstr." ".$_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][0]["sectitle"], $_FRM["TBLR"]);
         $rowidx++;
      
         //----------------------------------------------------------------------------------
         for($y = 0; $y < count($cols); $y++)
            $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

         $rowidx++;
         foreach($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"] AS $sqlrow)
         {
            $colidx = 0;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_company"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["paystate"], $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["tran_netto"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"]))), $_FRM["TBLR"]);$colidx++;
            $rowidx++;
         }
         $xls->write_string($rowidx, 0, "TOTAL ".$_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][0]["sectitle"], $_FRM["TBLH"]);
         $xls->write_number($rowidx, 5, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["tran_netto"]))), $_FRM["TBLH"]);
         $xls->write_number($rowidx, 6, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["invc_total_taxes"]))), $_FRM["TBLH"]);
         $xls->write_number($rowidx, 7, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TNOTEDATA{$xnotetype}"]["invc_total_brutto"]))), $_FRM["TBLH"]);
         $rowidx++;
         $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
         $rowidx++;
      }
   }

   $cols[0] = "GUIA";
   $cols[5] = "NO FACTURA";
   $cols[6] = "FECHA FACTURA";
   if(count($_SESSION["STATS"][$_sesmodulename]["DLVDATA"]))
   {
      $counter = 0;
      $xls->write_string($rowidx, 0, "GUIAS DE DESPACHO", $_FRM["TBLR"]);
      $rowidx++;
      
      //----------------------------------------------------------------------------------
      for($y = 0; $y < count($cols); $y++)
         $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
         
      $rowidx++;
      foreach($_SESSION["STATS"][$_sesmodulename]["DLVDATA"] AS $sqlrow)
      {
         $colidx = 0;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["cust_company"])), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_rut"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["paystate"], $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["tran_netto"]))), $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_total_taxes"], $_FRM["TBLR"]);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["invc_total_brutto"], $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      $xls->write_string($rowidx, 0, "TOTAL GUIAS", $_FRM["TBLH"]);
      $xls->write_number($rowidx, 5, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TDLVDATA"]["tran_netto"]))), $_FRM["TBLH"]);
      $rowidx++;
      $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
      $rowidx++;
   }

   if(count($_SESSION["STATS"][$_sesmodulename]["TOTALS"]))
   {
      $counter = 0;
      foreach($_SESSION["STATS"][$_sesmodulename]["TOTALS"] AS $sqlrow)
      {
         $colidx = 4;
         $xls->write_string($rowidx, 0, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["NAME"])), $_FRM["TBLH"]);
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["count"]))), $_FRM["TBLH"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["res_total_netto"]))), $_FRM["TBLH"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["res_total_taxes"]))), $_FRM["TBLH"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["res_total_brutto"]))), $_FRM["TBLH"]);$colidx++;
         $rowidx++;
      }
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsBooksBuying($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "statsbooksbuying";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

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
   
   $xls->write_string(0, 0, "LIBRO DE COMPRAS {$idxmonth} {$idxyear} {$addstr}- ".$company["company_name"], $_FRM["TBLH"]);
   $xls->write_string(1, 0, "" , $_FRM["TBLH"]);

   //----------------------------------------------------------------------------------
   $cols = Array("CORRELATIVO", "FACTURA", "COD. CONT.", "FECHA", "PROVEEDOR", "RUT", "EXENTO", "NETO", "IVA", "TOTAL");
   $xls->set_column(0, 0, 16);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 20);
   $xls->set_column(0, 3, 20);
   $xls->set_column(0, 4, 40);
   $xls->set_column(0, 5, 16);
   $xls->set_column(0, 6, 16);
   $xls->set_column(0, 7, 16);
   $xls->set_column(0, 8, 16);
   $xls->set_column(0, 9, 16);
   //----------------------------------------------------------------------------------
   $rowidx = 0;

   if(count($_SESSION["STATS"][$_sesmodulename]["INVCDATA"]))
   {
      $counter = 0;

      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["INVCDATA"]) AS $doctypetitle)
      {
         $xls->write_string($rowidx, 0, $doctypetitle, $_FRM["TBLH"]);
         $rowidx++;
         $counter = 0;
         for($y = 0; $y < count($cols); $y++)
            $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLR"]);
         $rowidx++;
         
         foreach($_SESSION["STATS"][$_sesmodulename]["INVCDATA"][$doctypetitle] AS $sqlrow)
         {
            $colidx = 0;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["gesnum"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["cc_code"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["supp_company"])), $_FRM["TBLR"]);$colidx++;
            $xls->write_string($rowidx, $colidx, $sqlrow["supp_rut"], $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes_exclude"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["tran_netto"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes"]))), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"]))), $_FRM["TBLR"]);$colidx++;
            $rowidx++;
         }
         $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
         $rowidx++;
      }
   }
   
   $cols[1] = "NOTA";
   for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
   {
      if(count($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"]))
      {
         $counter = 0;

         unset($data);
         foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"]) AS $doctypetitle)
         {
            $xls->write_string($rowidx, 0, $doctypetitle, $_FRM["TBLH"]);
            $rowidx++;
            $counter = 0;
            for($y = 0; $y < count($cols); $y++)
               $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLR"]);
            $rowidx++;
            
            foreach($_SESSION["STATS"][$_sesmodulename]["NOTEDATA{$xnotetype}"][$doctypetitle] AS $sqlrow)
            {
               $colidx = 0;
               $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["gesnum"])), $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["invc_docnumber"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["cc_code"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["invc_date"], $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, str_replace("<b>", "", str_replace("</b>", "", $sqlrow["supp_company"])), $_FRM["TBLR"]);$colidx++;
               $xls->write_string($rowidx, $colidx, $sqlrow["supp_rut"], $_FRM["TBLR"]);$colidx++;
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes_exclude"]))), $_FRM["TBLR"]);$colidx++;
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["tran_netto"]))), $_FRM["TBLR"]);$colidx++;
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_taxes"]))), $_FRM["TBLR"]);$colidx++;
               $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $sqlrow["invc_total_brutto"]))), $_FRM["TBLR"]);$colidx++;
               $rowidx++;
            }
            $xls->write_string($rowidx, 0, " ", $_FRM["TBLR"]);
            $rowidx++;
         }
      }
   }

   $rowidx++;
   if(count($_SESSION["STATS"][$_sesmodulename]["TOTAL"]))
   {
      $numberlim = $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["numberlim"];
      foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"]) AS $factitle)
      {
         $colidx = 0;
         $xls->write_string($rowidx, 0, str_replace("<b>", "", str_replace("</b>", "", $factitle)), $_FRM["TBLR"]);
         $colidx = 6;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_taxes_exclude"])), $numberlim), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_netto"])), $numberlim), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_taxes"])), $numberlim), $_FRM["TBLR"]);$colidx++;
         $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["INVC"][$factitle]["res_total_brutto"])), $numberlim), $_FRM["TBLR"]);$colidx++;
         $rowidx++;
      }
      for($xnotetype = 1; $xnotetype <=2; $xnotetype++)
      {
         foreach(array_keys($_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype]) AS $factitle)
         {
            $colidx = 0;
            $xls->write_string($rowidx, 0, str_replace("<b>", "", str_replace("</b>", "", $factitle)), $_FRM["TBLR"]);
            $colidx = 6;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_taxes_exclude"])), $numberlim), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_netto"])), $numberlim), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_taxes"])), $numberlim), $_FRM["TBLR"]);$colidx++;
            $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["TOTAL"]["NOTE"][$xnotetype][$factitle]["res_total_brutto"])), $numberlim), $_FRM["TBLR"]);$colidx++;
            $rowidx++;
         }
      }
      $colidx = 0;
      $xls->write_string($rowidx, 0, "TOTAL GENERAL", $_FRM["TBLH"]);
      $colidx = 6;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_taxes_exclude"])), $numberlim), $_FRM["TBLH"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_netto"])), $numberlim), $_FRM["TBLH"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_taxes"])), $numberlim), $_FRM["TBLH"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice(str_replace("<b>", "", str_replace("</b>", "", $_SESSION["STATS"][$_sesmodulename]["GENERAL"]["res_total_brutto"])), $numberlim), $_FRM["TBLH"]);$colidx++;
   }

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}


function xls_createAdminPayments($CON, $datasql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "createadmindpayment";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("ITEM", "FECHA", "MONTO", "OBSERVACIONES");
   $xls->set_column(0, 0, 30);
   $xls->set_column(0, 1, 12);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 50);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   $total = 0;

   foreach($res AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["acc_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, date("d.m.Y",$sqlrow["adm_date"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($sqlrow["adm_amount"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["adm_notes"], $_FRM["TBLR"]);$colidx++;
      $total += $sqlrow["adm_amount"];
      $rowidx++;
   }
   $xls->write_string($rowidx, 0, "TOTAL", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 2, printPrice($total), $_FRM["TBLH"]);$colidx++;

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createAdminDeposite($CON, $datasql)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "createadmindeposite";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("USUARIO", "CLIENTE", "FECHA", "Nï¿½ FACTURA", "Nï¿½ NOTA CRï¿½DITO", "Nï¿½ NOTA Dï¿½BITO", "BANCO", "TOTAL PAGO", "ESTADO");
   $xls->set_column(0, 0, 30);
   $xls->set_column(0, 1, 20);
   $xls->set_column(0, 2, 12);
   $xls->set_column(0, 3, 30);
   $xls->set_column(0, 4, 20);
   $xls->set_column(0, 5, 20);
   $xls->set_column(0, 6, 30);
   $xls->set_column(0, 7, 20);
   $xls->set_column(0, 8, 20);

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   $total = 0;
   foreach($res AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, strtoupper($sqlrow["user_name"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, date("d.m.Y",$sqlrow["adm_deposit_status_date"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getAdminDepositEnvoicesPrint($sqlrow["id"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getAdminDepositCreditPrint($sqlrow["id"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, getAdminDepositDebitPrint($sqlrow["id"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["bank_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, printPrice($sqlrow["adm_amount"],2), $_FRM["TBLR"]);$colidx++;
      $total += $sqlrow["adm_amount"];

      if($sqlrow["adm_deposit_status"] == 1)
         $estado = "POR DEPOSITAR";
      elseif($sqlrow["adm_deposit_status"] == 2)
         $estado = "DEPOSITADO";
      else
         $estado = "DESCONOCIDO";

      
      $xls->write_string($rowidx, $colidx, $estado, $_FRM["TBLR"]);
      $rowidx++;
   }

   $rowidx++;
   
   $xls->write_string($rowidx, 0, "TOTAL ACUMULADO", $_FRM["TBLH"]);$colidx++;
   $xls->write_string($rowidx, 1, printPrice($total,2), $_FRM["TBLH"]);$colidx++;
   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
function xls_createStatsColcaciones($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   // $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
"Inicio de Turno",        
"Fin de Turno",     
"Codigo de Turno",
"Maquina",
"Rut Colaborador",       
"Codigo Colaborador",     
"Nombre Colaborador",  
"Inicio Colacion",      
"Termino Colacion",     
"Tiempo real Colacion");
   $xls->set_column(0, 0, 12);
   $xls->set_column(0, 1, 10);
   $xls->set_column(0, 2, 35);
   $xls->set_column(0, 3, 20);
   $xls->set_column(0, 4, 12);
   $xls->set_column(0, 5, 30);
   $xls->set_column(0, 6, 12);
   $xls->set_column(0, 7, 18);
   $xls->set_column(0, 8, 20);
   $xls->set_column(0, 9, 16);
   $xls->set_column(0, 10, 16);
   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["FechaInicioTurno"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["FechaFinTurno"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["CodigoTurno"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["NombreMaquina"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["RutColaborador"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["CodigoColaborador"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["NombreColaborador"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["FechaInicioColacion"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["FechaTerminoColacon"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["TiempoReal"], $_FRM["TBLR"]);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

// ******************************************************************************************************************************************
function xls_createStatsFlexoCC($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "Fecha Inicio CC",                                        /* 1 */
      "Fecha Produccion",                                       /* 2 */
      "Número de CC",                                           /* 3 */
      "Cantidad de Bolsas",                                     /* 4 */
      "Numero de OT",                                           /* 5 */
      "Cliente",                                                /* 6 */
      "Tipo Bolsa",                                             /* 7 */
      "Formato Bolsa",                                          /* 8 */
      "Codigo Producto",                                        /* 9 */
      "Descripcion Producto",                                   /* 10 */
      "Corte de Bolsa",                                         /* 11 */
      "UM",                                                     /* 12 */
      "Ancho de Bolsa",                                         /* 13 */
      "Alto Frente de Bolsa",                                   /* 14 */
      "Alto Dorso de Bolsa",                                    /* 15 */
      "Medida Doblez Superior",                                 /* 16 */
      "Medida Fuelle de Bolsa",                                 /* 17 */
      "Largo Manilla",                                          /* 18 */
      "Color Tela",                                             /* 19 */
      "Código Color Tela",                                      /* 20 */
      "Gramaje",                                                /* 21 */
      "Ancho Bobina",                                           /* 22 */
      "Código de Tela",                                         /* 23 */
      "Cantidad de Bobinas",                                    /* 24 */
      "Pie de Imprenta",                                        /* 25 */
      "Códgo de Barra",                                         /* 26 */
      "Número Código de Barra",                                 /* 27 */
      "Alarma",                                                 /* 28 */
      "Número de Alarma",                                       /* 29 */
      "ImpresionesalDesarrollo",                                /* 30 */
      "Nombre Máquina",                                         /* 31 */
      "Numero de Máquina",                                      /* 32 */
      "Nombre Supervisor",                                      /* 33 */
      "Rut supervisor",                                         /* 34 */
      "Fecha Hora Inicio (Alistamiento)",                       /* 35 */
      "Fecha Hora Término (Alistamiento)",                      /* 36 */
      "Estado de Alistamiento",                                 /* 37 */
      "Total Horas (Alistamiento)",                             /* 38 */
      "Fecha Hora Inicio (Producción)",                         /* 39 */
      "Fecha Hora Término (Producción)",                        /* 40 */
      "Estado de Producción",                                   /* 41 */
      "Total Hora Producción",                                  /* 42 */
      "Turno (mañana-tarde-noche)",                             /* 43 */
      "Horas Turno",                                            /* 44 */
      "Nombre Operador",                                        /* 45 */
      "Rut Operador",                                           /* 46 */
      "Nombre Ayudante",                                        /* 47 */
      "Rut Ayudante",                                           /* 48 */
      "% Merma Programado",                                     /* 49 */
      "Impresiones al Desarrollo Programado",                   /* 50 */
      "Total programado impresora (unidades)",                  /* 51 */
      "Total Programado Impresora (Metros/Maquina)",            /* 52 */
      "Total Programado Impresora (Metros/lineales)",           /* 53 */
      "Total Programado Impresora (Kgs)",                       /* 54 */
      "Produccion Esperadas (Unidades)",                        /* 55 */
      "Total producido impresora (unidades)",                   /* 56 */
      "Total Producido Impresora (Metros/Maquina)",             /* 57 */
      "Total Producido Impresora (Metros/Lineales)",            /* 58 */
      "Total Producido Impresora (Kgs)",                        /* 59 */
      "Eficiencia Productiva (%)",                              /* 60 */
      "Velocidad Máquina (Metros x Minuto)",                    /* 61 */
      "Peso Unitario (Kgs)",                                    /* 62 */
      "Tipo Clisé",                                             /* 63 */
      "Altura CLise",                                           /* 64 */
      "Ancho Bobina",                                           /* 65 */
      "Color Bobina",                                           /* 66 */
      "Codigo Color Bobina",                                    /* 67 */
      "Gramaje Bobina",                                         /* 68 */
      "Estado Bobiba (Nueva-Usada)",                            /* 69 */
      "N° Bobinas Procesadas (Unidades)",                       /* 70 */
      "Kilos Bobinas Procesadas (Kgs)",                         /* 71 */
      "Metros Lineales Bobinas Procesadas (Mts/Lineales)",      /* 72 */
      "Merma",                                                  /* 73 */
      "U/M",                                                    /* 74 */
      "Merma (%)",                                              /* 75 */
      "Color 1 Frente",                                         /* 76 */
      "Color 2 Frente",                                         /* 77 */
      "Color 3 Frente",                                         /* 78 */
      "Color 4 Frente",                                         /* 79 */
      "Color 5 Frente",                                         /* 80 */
      "Color 6 Frente",                                         /* 81 */
      "Color 1 Dorso",                                          /* 82 */
      "Color 2 Dorso",                                          /* 83 */
      "Color 3 Dorso",                                          /* 84 */
      "Color 4 Dorso",                                          /* 85 */
      "Color 5 Dorso",                                          /* 86 */
      "Color 6 Dorso",                                          /* 87 */

      "Kgs Tinta Utilizadas (color 1)",                         /* 88 */
      "Kgs Tinta Utilizadas (color 2)",                         /* 89 */
      "Kgs Tinta Utilizadas (color 3)",                         /* 90 */
      "Kgs Tinta Utilizadas (color 4)",                         /* 91 */
      "Kgs Tinta Utilizadas (color 5)",                         /* 92 */
      "Kgs Tinta Utilizadas (color 6)",                         /* 93 */

      "Anilox 1",                                               /* 94 */
      "Anilox 2",                                               /* 95 */
      "Anilox 3",                                               /* 96 */
      "Anilox 4",                                               /* 97 */
      "Anilox 5",                                               /* 98 */
      "Anilox 6",                                               /* 99 */

      "Bobina Sobrante (Kgs)",                                  /* 100 */
      "Scraps Unidades (Alistamiento)",                         /* 101 */
      "Scraps Kgs (Alistamiento)",                              /* 102 */
      "Scraps Malas por impresion (Unidades)",                  /* 103 */
      "Scraps Malas por Impresion (Kgs)",                       /* 104 */
      "Bobina Defectuosa (Unidades)",                           /* 105 */
      "Bobina Defectuosa (Kgs)",                                /* 106 */
      "Tiempo Productivo (Horas)",                              /* 107 */
      "Total Paros (Horas)",                                    /* 108 */
      "Tiempo Neto Productivo (Horas)",                         /* 109 */
      "Disponibilidad (%)",                                     /* 110 */
      "Rendimiento (%)",                                        /* 111 */
      "Calidad (%)",                                            /* 112 */
      "Oee (%)",                                                /* 113 */
      "$ Producidos",                                           /* 114 */
      "Observación Producción",                                 /* 115 */
   ) ;
      

   $xls->set_column(0,0,20);     /* 01  */ 
   $xls->set_column(0,1,20);     /* 02  */
   $xls->set_column(0,2,20);     /* 03  */
   $xls->set_column(0,3,20);     /* 04  */
   $xls->set_column(0,4,20);     /* 05  */ 
   $xls->set_column(0,5,100);    /* 06  */
   $xls->set_column(0,6,20);     /* 07  */
   $xls->set_column(0,7,20);     /* 08  */
   $xls->set_column(0,8,20);     /* 09  */
   $xls->set_column(0,9,100);    /* 10  */
   $xls->set_column(0,10,20);    /* 11  */
   $xls->set_column(0,11,9);     /* 12  */
   $xls->set_column(0,12,20);    /* 13  */
   $xls->set_column(0,13,20);    /* 14  */
   $xls->set_column(0,14,20);    /* 15  */
   $xls->set_column(0,15,20);    /* 16  */
   $xls->set_column(0,16,20);    /* 17  */
   $xls->set_column(0,17,20);    /* 18  */
   $xls->set_column(0,18,20);    /* 19  */
   $xls->set_column(0,19,20);    /* 20  */
   $xls->set_column(0,20,20);    /* 21  */
   $xls->set_column(0,21,20);    /* 22  */
   $xls->set_column(0,22,20);    /* 23  */
   $xls->set_column(0,23,20);    /* 24  */
   $xls->set_column(0,24,20);    /* 25  */
   $xls->set_column(0,25,50);    /* 26  */
   $xls->set_column(0,26,20);    /* 27  */
   $xls->set_column(0,27,20);    /* 28  */
   $xls->set_column(0,28,20);    /* 29  */
   $xls->set_column(0,29,20);    /* 30  */
   $xls->set_column(0,30,20);    /* 31  */
   $xls->set_column(0,31,20);    /* 32  */
   $xls->set_column(0,32,100);   /* 33  */
   $xls->set_column(0,33,20);    /* 34  */
   $xls->set_column(0,34,20);    /* 35  */
   $xls->set_column(0,35,20);    /* 36  */
   $xls->set_column(0,36,20);    /* 37  */
   $xls->set_column(0,37,20);    /* 38  */
   $xls->set_column(0,38,20);    /* 39  */
   $xls->set_column(0,39,20);    /* 40  */
   $xls->set_column(0,40,20);    /* 41  */
   $xls->set_column(0,41,20);    /* 42  */
   $xls->set_column(0,42,20);    /* 43  */
   $xls->set_column(0,43,20);    /* 44  */
   $xls->set_column(0,44,20);    /* 45  */
   $xls->set_column(0,45,20);    /* 46  */
   $xls->set_column(0,46,20);    /* 47  */
   $xls->set_column(0,47,20);    /* 48  */
   $xls->set_column(0,48,20);    /* 49  */
   $xls->set_column(0,49,20);    /* 50  */
   $xls->set_column(0,50,20);    /* 51  */
   $xls->set_column(0,51,20);    /* 52  */
   $xls->set_column(0,52,20);    /* 53  */
   $xls->set_column(0,53,20);    /* 54  */
   $xls->set_column(0,54,20);    /* 55  */
   $xls->set_column(0,55,20);    /* 56  */
   $xls->set_column(0,56,20);    /* 57  */
   $xls->set_column(0,57,20);    /* 58  */
   $xls->set_column(0,58,20);    /* 59  */
   $xls->set_column(0,59,20);    /* 60  */
   $xls->set_column(0,60,20);    /* 61  */
   $xls->set_column(0,61,20);    /* 62  */
   $xls->set_column(0,62,20);    /* 63  */
   $xls->set_column(0,63,20);    /* 64  */
   $xls->set_column(0,64,20);    /* 65  */
   $xls->set_column(0,65,20);    /* 66  */
   $xls->set_column(0,66,20);    /* 67  */
   $xls->set_column(0,67,20);    /* 68  */
   $xls->set_column(0,68,20);    /* 69  */
   $xls->set_column(0,69,20);    /* 70  */
   $xls->set_column(0,70,20);    /* 71  */
   $xls->set_column(0,71,20);    /* 72  */
   $xls->set_column(0,72,20);    /* 73  */
   $xls->set_column(0,73,20);    /* 74  */
   $xls->set_column(0,74,20);    /* 75  */
   $xls->set_column(0,75,20);    /* 76  */
   $xls->set_column(0,76,20);    /* 77  */
   $xls->set_column(0,77,20);    /* 78  */
   $xls->set_column(0,78,20);    /* 79  */
   $xls->set_column(0,79,20);    /* 80  */
   $xls->set_column(0,80,20);    /* 81  */
   $xls->set_column(0,81,20);    /* 82  */
   $xls->set_column(0,82,20);    /* 83  */
   $xls->set_column(0,83,20);    /* 84  */
   $xls->set_column(0,84,20);    /* 85  */
   $xls->set_column(0,85,20);    /* 86  */
   $xls->set_column(0,86,20);    /* 87  */

   $xls->set_column(0,87,25);    /* 88  */
   $xls->set_column(0,88,25);    /* 89  */
   $xls->set_column(0,89,25);    /* 90  */
   $xls->set_column(0,90,25);    /* 91  */
   $xls->set_column(0,91,25);    /* 92  */
   $xls->set_column(0,92,25);    /* 93  */
   
   $xls->set_column(0,93,20);    /* 94  */
   $xls->set_column(0,110,20);   /* 95  */
   $xls->set_column(0,111,20);   /* 96  */
   $xls->set_column(0,112,20);   /* 97  */
   $xls->set_column(0,113,20);   /* 98  */
   $xls->set_column(0,114,20);   /* 99  */


   $xls->set_column(0,94,20);    /* 100  */
   $xls->set_column(0,95,20);    /* 101  */
   $xls->set_column(0,96,20);    /* 102  */
   $xls->set_column(0,97,20);    /* 103  */
   $xls->set_column(0,98,20);    /* 104  */
   $xls->set_column(0,99,20);    /* 105 */
   $xls->set_column(0,100,20);   /* 106  */
   $xls->set_column(0,101,20);   /* 107  */
   $xls->set_column(0,102,20);   /* 108  */
   $xls->set_column(0,103,20);   /* 109  */
   $xls->set_column(0,104,20);   /* 110  */
   $xls->set_column(0,105,20);   /* 111  */
   $xls->set_column(0,106,20);   /* 112  */
   $xls->set_column(0,107,20);   /* 113  */
   $xls->set_column(0,108,400);  /* 114  */

   
   //-----------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

/*
   $excelDate = ($sqlrow["FechaHoraInicioAlistamiento"] / 86400) + 25569;
   $xls->write($rowidx, $colidx, $excelDate, $formatoFechaHora);$colidx++;
*/

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
             $colidx = 0;   
   /* 01 */  $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["FechaIngresoCC"]),$_FRM["FECHAHORA"]) ;$colidx++;
   /* 02 */  $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["FechaProduccion"]),$_FRM["FECHAHORA"]);$colidx++;
   /* 03 */  $xls->write_string($rowidx, $colidx, $sqlrow["NúmerodeCC"], $_FRM['TBLR']);$colidx++;
   /* 04 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddeBolsas"]),$_FRM['MONTO']);$colidx++;
   /* 05 */  $xls->write_string($rowidx, $colidx, $sqlrow["NumerodeOT"], $_FRM['TBLR']);$colidx++;
   /* 06 */  $xls->write_string($rowidx, $colidx, $sqlrow["Cliente"], $_FRM['TBLR']);$colidx++;
   /* 07 */  $xls->write_string($rowidx, $colidx, $sqlrow["TipoBolsa"], $_FRM['TBLR']);$colidx++;
   /* 08 */  $xls->write_string($rowidx, $colidx, $sqlrow["FormatoBolsa"], $_FRM['TBLR']);$colidx++;
   /* 09 */  $xls->write_string($rowidx, $colidx, $sqlrow["CodigoProducto"], $_FRM['TBLR']);$colidx++;
   /* 10 */  $xls->write_string($rowidx, $colidx, $sqlrow["DescripcionProducto"], $_FRM['TBLR']);$colidx++;
   /* 11 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CortedeBolsa"], 4), $_FRM['DECIMAL4']);$colidx++;
   /* 12 */  $xls->write_string($rowidx, $colidx, $sqlrow["UM"], $_FRM['TBLR']);$colidx++;
   /* 13 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchodeBolsa"]), $_FRM['TBLR']);$colidx++;
   /* 14 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AltoFrentedeBolsa"]), $_FRM['TBLR']);$colidx++;
   /* 15 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AltoDorsodeBolsa"]), $_FRM['TBLR']);$colidx++;
   /* 16 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MedidaDoblezSuperior"]), $_FRM['TBLR']);$colidx++;
   /* 17 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MedidaFuelledeBolsa"]), $_FRM['TBLR']);$colidx++;
   /* 18 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["LargoManilla"]), $_FRM['TBLR']);$colidx++;
   /* 19 */  $xls->write_string($rowidx, $colidx, $sqlrow["ColorTela"], $_FRM['TBLR']);$colidx++;
   /* 20 */  $xls->write_string($rowidx, $colidx, $sqlrow["CódigoColorTela"], $_FRM['TBLR']);$colidx++;
   /* 21 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Gramaje"],0), $_FRM['TBLR']);$colidx++;
   /* 22 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchoBobina"]), $_FRM['TBLR']);$colidx++;
   /* 23 */  $xls->write_string($rowidx, $colidx, $sqlrow["CódigodeTela"], $_FRM['TBLR']);$colidx++;
   /* 24 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddeBobinas"], 2), $_FRM['DECIMAL2']);$colidx++;
   /* 25 */  $xls->write_string($rowidx, $colidx, $sqlrow["PiedeImprenta"], $_FRM['TBLR']);$colidx++;
   /* 26 */  $xls->write_string($rowidx, $colidx, $sqlrow["CódgodeBarra"], $_FRM['TBLR']);$colidx++;
   /* 27 */  $xls->write_string($rowidx, $colidx, $sqlrow["NúmeroCódigodeBarra"], $_FRM['TBLR']);$colidx++;
   /* 28 */  $xls->write_string($rowidx, $colidx, $sqlrow["Alarma"], $_FRM['TBLR']);$colidx++;
   /* 29 */  $xls->write_string($rowidx, $colidx, $sqlrow["NúmerodeAlarma"], $_FRM['TBLR']);$colidx++;
   /* 30 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["ImpresionesalDesarrollo"]), $_FRM['TBLR']);$colidx++;
   /* 31 */  $xls->write_string($rowidx, $colidx, $sqlrow["NombreMáquina"], $_FRM['TBLR']);$colidx++;
   /* 32 */  $xls->write_number($rowidx, $colidx, $sqlrow["NumerodeMáquina"], $_FRM['TBLR']);$colidx++;
   /* 33 */  $xls->write_string($rowidx, $colidx, $sqlrow["NombreSupervisor"], $_FRM['TBLR']);$colidx++;
   /* 34 */  $xls->write_string($rowidx, $colidx, $sqlrow["Rutsupervisor"], $_FRM['TBLR']);$colidx++;
   /* 35 */  $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraInicioAlistamiento"]), $_FRM["FECHAHORA"]); $colidx++; 
   /* 36 */  $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraTérminoAlistamiento"]), $_FRM["FECHAHORA"]); $colidx++; 
   /* 37 */  $xls->write_string($rowidx, $colidx, $sqlrow["EstadoApertura"], $_FRM['TBLR']);$colidx++;
   /* 38 */  $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalHorasAlistamiento"]) , $_FRM['DURACION']); $colidx++;
   /* 39 */  $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraInicioProducción"]), $_FRM["FECHAHORA"]); $colidx++;
   /* 40 */  $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraTérminoProducción"]), $_FRM["FECHAHORA"]); $colidx++;
   /* 41 */  $xls->write($rowidx, $colidx, $sqlrow["EstadoProduccion"] , $_FRM['TBLR']); $colidx++;
   /* 42 */  $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalHoraProducción"]), $_FRM['DURACION']);$colidx++;
   /* 43 */  $xls->write_string($rowidx, $colidx, $sqlrow["Turnomañana-tarde-noche"], $_FRM['TBLR']);$colidx++;
   /* 44 */  $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["HorasTurno"]), $_FRM['DURACION']);$colidx++;
   /* 45 */  $xls->write_string($rowidx, $colidx, $sqlrow["NombreOperador"], $_FRM['TBLR']);$colidx++;
   /* 46 */  $xls->write_string($rowidx, $colidx, $sqlrow["RutOperador"], $_FRM['TBLR']);$colidx++;
   /* 47 */  $xls->write_string($rowidx, $colidx, $sqlrow["NombreAyudante"], $_FRM['TBLR']);$colidx++;
   /* 48 */  $xls->write_string($rowidx, $colidx, $sqlrow["RutAyudante"], $_FRM['TBLR']);$colidx++;
   /* 49 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermaProgramado"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 50 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Impresiones al Desarrollo Programado"]), $_FRM['MONTO']);$colidx++;
   /* 51 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Total programado impresora (unidades)"]), $_FRM['MONTO']);$colidx++;
   /* 52 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Total Programado Impresora (Metros/Maquina)"]), $_FRM['MONTO']);$colidx++;
   /* 53 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Total Programado Impresora (Metros/lineales)"]), $_FRM['MONTO']);$colidx++;
   /* 54 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Total Programado Impresora (Kgs)"]), $_FRM['MONTO']);$colidx++;
   /* 55   $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Produccion Esperadas (Unidades)"]), $_FRM['MONTO']);$colidx++; */
   /* 55 */  $xls->write_string($rowidx, $colidx, $sqlrow["Produccion Esperadas (Unidades)"], $_FRM['TBLR']);$colidx++; 
   /* 56 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Totalproducidoimpresoraunidades"]), $_FRM['MONTO']);$colidx++;
   /* 57 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalProducidoImpresoraContadorTurno"]), $_FRM['MONTO']);$colidx++;
   /* 58 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalProducidoImpresoraMetrosLineales"]), $_FRM['MONTO']);$colidx++;
   /* 59 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalProducidoImpresoraKgs"]), $_FRM['MONTO']);$colidx++;
   /* 60 */  $xls->write_string($rowidx, $colidx, $sqlrow["EficienciaProductiva"], $_FRM['TBLR']);$colidx++;
   /* 61 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["VelocidadMáquinaMetrosxMinuto"]), $_FRM['TBLR']);$colidx++;
   /* 62 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["PesoUnitarioGramos"], 4 ), $_FRM['DECIMAL4']);$colidx++;
   /* 63 */  $xls->write_string($rowidx, $colidx, $sqlrow["TipoClisé"], $_FRM['TBLR']);$colidx++;
   /* 64 */  $xls->write_string($rowidx, $colidx, $sqlrow["AlturaCLise"], $_FRM['TBLR']);$colidx++;
   /* 65 */  $xls->write_string($rowidx, $colidx, $sqlrow["AnchoBobina"], $_FRM['TBLR']);$colidx++;
   /* 66 */  $xls->write_string($rowidx, $colidx, $sqlrow["ColorBobina"], $_FRM['TBLR']);$colidx++;
   /* 67 */  $xls->write_string($rowidx, $colidx, $sqlrow["CodigoColorBobina"], $_FRM['TBLR']);$colidx++;
   /* 68 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["GramajeBobina"]), $_FRM['MONTO']);$colidx++;
   /* 69 */  $xls->write_string($rowidx, $colidx, $sqlrow["EstadoBobibaNueva-Usada"], $_FRM['TBLR']);$colidx++;
   /* 70 */  $xls->write_number($rowidx, $colidx, $sqlrow["N°BobinasProcesadasUnidades"], $_FRM['TBLR']);$colidx++;
   /* 71 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KilosBobinasProcesadasKgs"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 72 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MetrosLinealesBobinasProcesadasMtsLineales"],0), $_FRM['MONTO']);$colidx++;
   /* 73 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Merma"]),$_FRM['DECIMAL2'] );$colidx++;
   /* 74 */  $xls->write_string($rowidx, $colidx, $sqlrow["UM2"], $_FRM['TBLR']);$colidx++;
   /* 75 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["PorMerma"],2), $_FRM['TBLR']);$colidx++;
   /* 76 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color1Frente"], $_FRM['TBLR']);$colidx++;
   /* 77 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color2Frente"], $_FRM['TBLR']);$colidx++;
   /* 78 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color3Frente"], $_FRM['TBLR']);$colidx++;
   /* 79 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color4Frente"], $_FRM['TBLR']);$colidx++;
   /* 80 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color5Frente"], $_FRM['TBLR']);$colidx++;
   /* 81 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color6Frente"], $_FRM['TBLR']);$colidx++;
   /* 82 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color1Dorso"], $_FRM['TBLR']);$colidx++;
   /* 83 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color2Dorso"], $_FRM['TBLR']);$colidx++;
   /* 84 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color3Dorso"], $_FRM['TBLR']);$colidx++;
   /* 85 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color4Dorso"], $_FRM['TBLR']);$colidx++;
   /* 86 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color5Dorso"], $_FRM['TBLR']);$colidx++;
   /* 87 */  $xls->write_string($rowidx, $colidx, $sqlrow["Color6Dorso"], $_FRM['TBLR']);$colidx++;

   /* 88 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KgsTintaUtilizadascolor1"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 89 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KgsTintaUtilizadascolor2"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 90 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KgsTintaUtilizadascolor3"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 91 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KgsTintaUtilizadascolor4"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 92 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KgsTintaUtilizadascolor5"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 93 */  $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KgsTintaUtilizadascolor6"],2), $_FRM['DECIMAL2']);$colidx++;

   /* 94 */  $xls->write_string($rowidx, $colidx, $sqlrow["Anilox1"], $_FRM['TBLR']);$colidx++;
   /* 95 */  $xls->write_string($rowidx, $colidx, $sqlrow["Anilox2"], $_FRM['TBLR']);$colidx++;
   /* 96 */  $xls->write_string($rowidx, $colidx, $sqlrow["Anilox3"], $_FRM['TBLR']);$colidx++;
   /* 97 */  $xls->write_string($rowidx, $colidx, $sqlrow["Anilox4"], $_FRM['TBLR']);$colidx++;
   /* 98 */  $xls->write_string($rowidx, $colidx, $sqlrow["Anilox5"], $_FRM['TBLR']);$colidx++;
   /* 99 */  $xls->write_string($rowidx, $colidx, $sqlrow["Anilox6"], $_FRM['TBLR']);$colidx++;

   /* 100 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["BobinaSobranteKgs"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 101 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["ScrapsUnidadesAlistamiento"]), $_FRM['MONTO']);$colidx++;
   /* 102 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["ScrapsKgsAlistamiento"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 103 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["ScrapsMalasporImpresionKgs"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 104 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["ScrapsMalasporimpresionUnidades"]), $_FRM['MONTO']);$colidx++;
   /* 105 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["BobinaDefectuosaUnidades"]), $_FRM['MONTO']);$colidx++;
   /* 106 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["BobinaDefectuosaKgs"],2), $_FRM['DECIMAL2']);$colidx++;
   /* 107 */ $xls->write_string($rowidx, $colidx, $sqlrow["TiempoProductivoHoras"], $_FRM['TBLR']);$colidx++;

   /* 108 */ $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalParosHoras"]), $_FRM['DURACION']);$colidx++;
   /* 109 */ $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TiempoNetoProductivoHoras"]), $_FRM['DURACION']);$colidx++;

   /* 110 */ $xls->write_string($rowidx, $colidx, $sqlrow["Disponibilidad"], $_FRM['TBLR']);$colidx++;
   /* 111 */ $xls->write_string($rowidx, $colidx, $sqlrow["Rendimiento"], $_FRM['TBLR']);$colidx++;
   /* 112 */ $xls->write_string($rowidx, $colidx, $sqlrow["Calidad"], $_FRM['TBLR']);$colidx++;
   /* 113 */ $xls->write_string($rowidx, $colidx, $sqlrow["Oee"], $_FRM['TBLR']);$colidx++;
   /* 114 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Producidos"]), $_FRM['MONTO']);$colidx++;
   /* 115 */ $xls->write_string($rowidx, $colidx, $sqlrow["ObservaciónProducción"], $_FRM['TBLR']);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
// *************************************************************************************************************************************************
function xls_createStatsSerigrafia($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   // $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
         'Fecha Inicio CC',
         'Fecha Produccion',
         'Número de CC',
         'Cantidad de Bolsas',
         'Numero de OT',
         'Cliente',
         'Tipo Bolsa',
         'Formato Bolsa',
         'Codigo Producto',
         'Descripcion Producto',
         'Corte de Bolsa',
         'UM',
         'Ancho de Bolsa',
         'Alto Frente de Bolsa',
         'Alto Dorso de Bolsa',
         'Medida Doblez Superior',
         'Medida Fuelle de Bolsa',
         'Largo Manilla',
         'Color Tela',
         'Código Color Tela',
         'Gramaje',
         'Ancho Bobina',
         'Código de Tela',
         'Cantidad de Bobinas',
         'Pie de Imprenta',
         'Códgo de Barra',
         'Número Código de Barra',
         'Alarma',
         'Número de Alarma',
         'Nombre Máquina',
         'Numero de Máquina',
         'Nombre Supervisor',
         'Rut supervisor',
         'Fecha Hora Inicio (Alistamiento)',
         'Fecha Hora Término (Alistamiento)',
         'Estado de Alistamiento',
         'Total Horas (Alistamiento)',
         'Fecha Hora Inicio (Producción)',
         'Fecha Hora Término (Producción)',
         'Estado de Producción',
         'Total Hora Producción',
         'Turno (mañana-tarde-noche)',
         'Horas Turno',
         'Nombre Operador',
         'Rut Operador',
         'Nombre Ayudante',
         'Rut Ayudante',
         '% Merma Programado',
         'Impresiones al Desarrollo Programado',
         'Total programado impresora (unidades)',
         'Total Programado Impresora (Pasadas/turno)',
         'Total Programado Impresora (Metros/lineales)',
         'Total Programado Impresora (Kgs)',
         'Produccion Esperadas (Unidades)',
         'Cantidad Colores a Imprimir',
         'Color Impreso',
         'Total Impresa Terminadas (Un)',
         'Impresiones del Desarrollo Producido',
         'Total producido impresora (unidades)',
         'Total Producido Impresora (Pasadas/turno)',
         'Total Producido Impresora (Metros/Lineales)',
         'Total Producido Impresora (Kgs)',
         'Eficiencia Productiva (%)',
         'Velocidad Máquina (Metros x Minuto)',
         'Peso Unitario (Kgs)',
         'N° Bastidor Utilizado',
         'Impresiones Al Ancho',
         'Ancho Bobina',
         'Color Bobina',
         'Codigo Color Bobina',
         'Gramaje Bobina',
         'Estado Bobiba (Nueva-Usada)',
         'N° Bobinas Procesadas (Unidades)',
         'Kilos Bobinas Procesadas (Kgs)',
         'Metros Lineales Bobinas Procesadas (Mts/Lineales)',
         'Merma Total Kilos',
         'Merma (%)',
         'Color 1 Frente',
         'Color 2 Frente',
         'Color 3 Frente',
         'Color 4 Frente',
         'Color 1 Dorso',
         'Color 2 Dorso',
         'Color 3 Dorso',
         'Color 4 Dorso',
         'Kgs Tinta Utilizadas (color 1)',
         'Kgs Tinta Utilizadas (color 2)',
         'Kgs Tinta Utilizadas (color 3)',
         'Kgs Tinta Utilizadas (color 4)',
         'Bobina Sobrante (Kgs)',
         'Merma Alistamiento (UN)',
         'Merma Alistamiento (Kgs)',
         'Merma impresion (Un)',
         'Merma Impresion (Kgs)',
         'Merma Bobina Defectuosa (Un)',
         'Merma Bobina Defectuosa (Kgs)',
         'Tiempo Productivo (Horas)',
         'Total Paros (Horas)',
         'Tiempo Neto Productivo (Horas)',
         'Disponibilidad (%)',
         'Rendimiento (%)',
         'Calidad (%)',
         'Oee (%)',
         '$ Producidos',
         'Observación Producción',
   );
      

   $xls->set_column(0,0,20);
   $xls->set_column(0,1,20);
   $xls->set_column(0,2,20);
   $xls->set_column(0,3,20);
   $xls->set_column(0,4,20);
   $xls->set_column(0,5,100);
   $xls->set_column(0,6,20);
   $xls->set_column(0,7,20);
   $xls->set_column(0,8,20);
   $xls->set_column(0,9,100);
   $xls->set_column(0,10,20);
   $xls->set_column(0,11,9);
   $xls->set_column(0,12,20);
   $xls->set_column(0,13,20);
   $xls->set_column(0,14,20);
   $xls->set_column(0,15,20);
   $xls->set_column(0,16,20);
   $xls->set_column(0,17,20);
   $xls->set_column(0,18,20);
   $xls->set_column(0,19,20);
   $xls->set_column(0,20,20);
   $xls->set_column(0,21,20);
   $xls->set_column(0,22,20);
   $xls->set_column(0,23,20);
   $xls->set_column(0,24,20);
   $xls->set_column(0,25,10);
   $xls->set_column(0,26,20);
   $xls->set_column(0,27,20);
   $xls->set_column(0,28,20);
   $xls->set_column(0,29,20);
   $xls->set_column(0,30,20);
   $xls->set_column(0,31,100);
   $xls->set_column(0,32,20);
   $xls->set_column(0,33,20);
   $xls->set_column(0,34,20);
   $xls->set_column(0,35,20);
   $xls->set_column(0,36,20);
   $xls->set_column(0,37,20);
   $xls->set_column(0,38,20);
   $xls->set_column(0,39,20);
   $xls->set_column(0,40,20);
   $xls->set_column(0,41,20);
   $xls->set_column(0,42,15);
   $xls->set_column(0,43,100);
   $xls->set_column(0,44,20);
   $xls->set_column(0,45,100);
   $xls->set_column(0,46,20);
   $xls->set_column(0,47,20);
   $xls->set_column(0,48,20);
   $xls->set_column(0,49,20);
   $xls->set_column(0,50,20);
   $xls->set_column(0,51,20);
   $xls->set_column(0,52,20);
   $xls->set_column(0,53,20);
   $xls->set_column(0,54,20);
   $xls->set_column(0,55,20);
   $xls->set_column(0,56,20);
   $xls->set_column(0,57,20);
   $xls->set_column(0,58,20);
   $xls->set_column(0,59,20);
   $xls->set_column(0,60,20);
   $xls->set_column(0,61,20);
   $xls->set_column(0,62,20);
   $xls->set_column(0,63,20);
   $xls->set_column(0,64,20);
   $xls->set_column(0,65,20);
   $xls->set_column(0,66,20);
   $xls->set_column(0,67,20);
   $xls->set_column(0,68,20);
   $xls->set_column(0,69,20);
   $xls->set_column(0,70,20);
   $xls->set_column(0,71,20);
   $xls->set_column(0,72,20);
   $xls->set_column(0,73,20);
   $xls->set_column(0,74,20);
   $xls->set_column(0,75,20);
   $xls->set_column(0,76,20);
   $xls->set_column(0,77,20);
   $xls->set_column(0,78,20);
   $xls->set_column(0,79,20);
   $xls->set_column(0,80,20);
   $xls->set_column(0,81,20);
   $xls->set_column(0,82,20);
   $xls->set_column(0,83,20);
   $xls->set_column(0,84,20);
   $xls->set_column(0,85,20);
   $xls->set_column(0,86,20);
   $xls->set_column(0,87,20);
   $xls->set_column(0,88,20);
   $xls->set_column(0,89,20);
   $xls->set_column(0,90,20);
   $xls->set_column(0,91,20);
   $xls->set_column(0,92,20);
   $xls->set_column(0,93,20);
   $xls->set_column(0,94,20);
   $xls->set_column(0,95,20);
   $xls->set_column(0,96,20);
   $xls->set_column(0,97,20);
   $xls->set_column(0,98,20);
   $xls->set_column(0,99,20);
   $xls->set_column(0,100,20);
   $xls->set_column(0,101,20);
   $xls->set_column(0,102,20);
   $xls->set_column(0,103,20);
   $xls->set_column(0,104,400);
   
   
   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      
   /* 01 */     $xls->write($rowidx,$colidx, timestampToExcelDate($sqlrow["FechaIngresoCC"]),$_FRM['FECHAHORA']);$colidx++; 
   /* 02 */     $xls->write($rowidx,$colidx, timestampToExcelDate($sqlrow["FechaProduccion"]),$_FRM['FECHAHORA']);$colidx++;
   /* 03 */     $xls->write_string($rowidx,$colidx, $sqlrow["NúmerodeCC"],$_FRM['TBLR']);$colidx++;
   /* 04 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["CantidaddeBolsas"],0),$_FRM['MONTO']);$colidx++;   
   /* 05 */     $xls->write_string($rowidx,$colidx, $sqlrow["NumerodeOT"],$_FRM['TBLR']);$colidx++;
   /* 06 */     $xls->write_string($rowidx,$colidx, $sqlrow["Cliente"],$_FRM['TBLR']);$colidx++;
   /* 07 */     $xls->write_string($rowidx,$colidx, $sqlrow["TipoBolsa"],$_FRM['TBLR']);$colidx++;
   /* 08 */     $xls->write_string($rowidx,$colidx, $sqlrow["FormatoBolsa"],$_FRM['TBLR']);$colidx++;
   /* 09 */     $xls->write_string($rowidx,$colidx, $sqlrow["CodigoProducto"],$_FRM['TBLR']);$colidx++;
   /* 10 */     $xls->write_string($rowidx,$colidx, $sqlrow["DescripcionProducto"],$_FRM['TBLR']);$colidx++;
   /* 11 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["CortedeBolsa"],4),$_FRM['DECIMAL4']);$colidx++;
   /* 12 */     $xls->write_string($rowidx,$colidx, $sqlrow["UM"],$_FRM['TBLR']);$colidx++;
   /* 13 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["AnchodeBolsa"],0),$_FRM['MONTO']);$colidx++;
   /* 14 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["AltoFrentedeBolsa"],0),$_FRM['MONTO']);$colidx++;
   /* 15 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["AltoDorsodeBolsa"],0),$_FRM['MONTO']);$colidx++;
   /* 16 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MedidaDoblezSuperior"],0),$_FRM['MONTO']);$colidx++;
   /* 17 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MedidaFuelledeBolsa"],0),$_FRM['MONTO']);$colidx++;
   /* 18 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["LargoManilla"],0),$_FRM['MONTO']);$colidx++;
   /* 19 */     $xls->write_string($rowidx,$colidx, $sqlrow["ColorTela"],$_FRM['TBLR']);$colidx++;
   /* 20 */     $xls->write_string($rowidx,$colidx, $sqlrow["CódigoColorTela"],$_FRM['TBLR']);$colidx++;
   /* 21 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Gramaje"],0),$_FRM['MONTO']);$colidx++;
   /* 22 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["AnchoBobina"],0),$_FRM['MONTO']);$colidx++;
   /* 23 */     $xls->write_string($rowidx,$colidx, $sqlrow["CódigodeTela"],$_FRM['TBLR']);$colidx++;
   /* 24 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["CantidaddeBobinas"],0),$_FRM['MONTO']);$colidx++;
   /* 25 */     $xls->write_string($rowidx,$colidx, $sqlrow["PiedeImprenta"],$_FRM['TBLR']);$colidx++;
   /* 26 */     $xls->write_string($rowidx,$colidx, $sqlrow["CódgodeBarra"],$_FRM['TBLR']);$colidx++;
   /* 27 */     $xls->write_string($rowidx,$colidx, $sqlrow["NúmeroCódigodeBarra"],$_FRM['TBLR']);$colidx++;
   /* 28 */     $xls->write_string($rowidx,$colidx, $sqlrow["Alarma"],$_FRM['TBLR']);$colidx++;
   /* 29 */     $xls->write_string($rowidx,$colidx, $sqlrow["NúmerodeAlarma"],$_FRM['TBLR']);$colidx++;
   /* 30 */     $xls->write_string($rowidx,$colidx, $sqlrow["NombreMáquina"],$_FRM['TBLR']);$colidx++;
   /* 31 */     $xls->write_string($rowidx,$colidx, $sqlrow["NumerodeMáquina"],$_FRM['TBLR']);$colidx++;
   /* 32 */     $xls->write_string($rowidx,$colidx, $sqlrow["NombreSupervisor"],$_FRM['TBLR']);$colidx++;
   /* 33 */     $xls->write_string($rowidx,$colidx, $sqlrow["Rutsupervisor"],$_FRM['TBLR']);$colidx++;
   /* 34 */     $xls->write($rowidx,$colidx, fechaStrAExcel($sqlrow["FechaHoraInicioAlistamiento"]),$_FRM['FECHAHORA']);$colidx++;
   /* 35 */     $xls->write($rowidx,$colidx, fechaStrAExcel($sqlrow["FechaHoraTérminoAlistamiento"]),$_FRM['FECHAHORA']);$colidx++;
   /* 36 */     $xls->write_string($rowidx,$colidx, $sqlrow["EstadodeAlistamiento"],$_FRM['TBLR']);$colidx++;
   /* 37 */     $xls->write($rowidx,$colidx, duracionStrAExcel($sqlrow["TotalHorasAlistamiento"]),$_FRM['DURACION']);$colidx++;
   /* 38 */     $xls->write($rowidx,$colidx, fechaStrAExcel($sqlrow["FechaHoraInicioProducción"]),$_FRM['FECHAHORA']);$colidx++;
   /* 39 */     $xls->write($rowidx,$colidx, fechaStrAExcel($sqlrow["FechaHoraTérminoProducción"]),$_FRM['FECHAHORA']);$colidx++;
   /* 40 */     $xls->write_string($rowidx,$colidx, $sqlrow["EstadodeProducción"],$_FRM['TBLR']);$colidx++;
   /* 41 */     $xls->write($rowidx,$colidx, duracionStrAExcel($sqlrow["TotalHoraProducción"]),$_FRM['DURACION']);$colidx++;
   /* 42 */     $xls->write_string($rowidx,$colidx, $sqlrow["Turnomañanatardenoche"],$_FRM['TBLR']);$colidx++;
   /* 43 */     $xls->write($rowidx,$colidx, duracionStrAExcel($sqlrow["HorasTurno"]),$_FRM['DURACION']);$colidx++; 
   /* 44 */     $xls->write_string($rowidx,$colidx, $sqlrow["NombreOperador"],$_FRM['TBLR']);$colidx++;
   /* 45 */     $xls->write_string($rowidx,$colidx, $sqlrow["RutOperador"],$_FRM['TBLR']);$colidx++;
   /* 46 */     $xls->write_string($rowidx,$colidx, $sqlrow["NombreAyudante"],$_FRM['TBLR']);$colidx++;
   /* 47 */     $xls->write_string($rowidx,$colidx, $sqlrow["RutAyudante"],$_FRM['TBLR']);$colidx++;
   /* 48 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["%MermaProgramado"],2) ,$_FRM['DECIMAL2']);$colidx++;
   /* 49 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["ImpresionesalDesarrolloProgramado"],0),$_FRM['MONTO']);$colidx++;
   /* 50 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Totalprogramadoimpresoraunidades"],0),$_FRM['MONTO']);$colidx++;
   /* 51 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["TotalProgramadoImpresoraPasadasturno"],0),$_FRM['MONTO']);$colidx++;
   /* 52 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["TotalProgramadoImpresoraMetroslineales"],0),$_FRM['MONTO']);$colidx++;
   /* 53 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["TotalProgramadoImpresoraKgs"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 54 */     $xls->write_string($rowidx,$colidx, $sqlrow["ProduccionEsperadasUnidades"],$_FRM['TBLR']);$colidx++;
   /* 55 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["CantidadColoresaImprimir"],0),$_FRM['MONTO']);$colidx++;
   /* 56 */     $xls->write_string($rowidx,$colidx, $sqlrow["ColorImpreso"],$_FRM['TBLR']);$colidx++;
   /* 57 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["TotalImpresaTerminadasUn"],0),$_FRM['MONTO']);$colidx++;
   /* 58 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["ImpresionesdelDesarrolloProducido"],0),$_FRM['MONTO']);$colidx++;
   /* 59 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Totalproducidoimpresoraunidades"],0),$_FRM['MONTO']);$colidx++;
   /* 60 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["TotalProducidoImpresoraPasadasturno"],0),$_FRM['MONTO']);$colidx++;
   /* 61 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["TotalProducidoImpresoraMetrosLineales"],0),$_FRM['MONTO']);$colidx++;
   /* 62 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["TotalProducidoImpresoraKgs"],0),$_FRM['MONTO']);$colidx++;
   /* 63 */     $xls->write_string($rowidx,$colidx, $sqlrow["EficienciaProductiva%"],$_FRM['TBLR']);$colidx++;
   /* 64 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["VelocidadMáquinaMetrosxMinuto"],0),$_FRM['MONTO']);$colidx++;
   /* 65 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["PesoUnitarioKgs"],4),$_FRM['DECIMAL4']);$colidx++;
   /* 66 */     $xls->write_string($rowidx,$colidx, $sqlrow["N°BastidorUtilizado"],$_FRM['TBLR']);$colidx++;
   /* 67 */     $xls->write_string($rowidx,$colidx, $sqlrow["ImpresionesAlAncho"],$_FRM['TBLR']);$colidx++;
   /* 68 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["AnchoBobina"],0),$_FRM['MONTO']);$colidx++;
   /* 69 */     $xls->write_string($rowidx,$colidx, $sqlrow["ColorBobina"],$_FRM['TBLR']);$colidx++;
   /* 70 */     $xls->write_string($rowidx,$colidx, $sqlrow["CodigoColorBobina"],$_FRM['TBLR']);$colidx++;
   /* 71 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["GramajeBobina"],0),$_FRM['MONTO']);$colidx++;
   /* 72 */     $xls->write_string($rowidx,$colidx, $sqlrow["EstadoBobibaNuevaUsada"],$_FRM['TBLR']);$colidx++;
   /* 73 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["N°BobinasProcesadasUnidades"],0),$_FRM['MONTO']);$colidx++;
   /* 74 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["KilosBobinasProcesadasKgs"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 75 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MetrosLinealesBobinasProcesadasMtsLineales"],0) ,$_FRM['MONTO']);$colidx++;
   /* 76 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MermaTotalKilos"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 77 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Merma%"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 78 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color1Frente"],$_FRM['TBLR']);$colidx++;
   /* 79 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color2Frente"],$_FRM['TBLR']);$colidx++;
   /* 80 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color3Frente"],$_FRM['TBLR']);$colidx++;
   /* 81 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color4Frente"],$_FRM['TBLR']);$colidx++;
   /* 82 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color1Dorso"],$_FRM['TBLR']);$colidx++;
   /* 83 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color2Dorso"],$_FRM['TBLR']);$colidx++;
   /* 84 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color3Dorso"],$_FRM['TBLR']);$colidx++;
   /* 85 */     $xls->write_string($rowidx,$colidx, $sqlrow["Color4Dorso"],$_FRM['TBLR']);$colidx++;

   /* 86 */     $xls->write_string($rowidx,$colidx, $sqlrow["KgsTintaUtilizadas1"],$_FRM['TBLR']);$colidx++;
   /* 87 */     $xls->write_string($rowidx,$colidx, $sqlrow["KgsTintaUtilizadas2"],$_FRM['TBLR']);$colidx++;
   /* 88 */     $xls->write_string($rowidx,$colidx, $sqlrow["KgsTintaUtilizadas3"],$_FRM['TBLR']);$colidx++;
   /* 89 */     $xls->write_string($rowidx,$colidx, $sqlrow["KgsTintaUtilizadas4"],$_FRM['TBLR']);$colidx++;

   /* 90 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["BobinaSobranteKgs"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 91 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MermaAlistamientoUN"],0),$_FRM['MONTO']);$colidx++;
   /* 92 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MermaAlistamientoKgs"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 93 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MermaimpresionUn"],0),$_FRM['MONTO']);$colidx++;
   /* 94 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MermaImpresionKgs"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 95 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MermaBobinaDefectuosaUn"],0),$_FRM['MONTO']);$colidx++;
   /* 96 */     $xls->write_number($rowidx,$colidx, getPrice($sqlrow["MermaBobinaDefectuosaKgs"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 97 */     $xls->write($rowidx,$colidx, duracionStrAExcel($sqlrow["TiempoProductivoHoras"]),$_FRM['DURACION']);$colidx++;
   /* 98 */     $xls->write($rowidx,$colidx, duracionStrAExcel($sqlrow["TotalParosHoras"]),$_FRM['DURACION']);$colidx++;
   /* 99 */     $xls->write_string($rowidx,$colidx, $sqlrow["TiempoNetoProductivoHoras"],$_FRM['TBLR']);$colidx++;
   /* 100 */    $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Disponibilidad%"],2),$_FRM['DECIMAL2']);$colidx++;
   /* 101 */    $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Rendimiento%"],2),$_FRM['TBLR']);$colidx++;
   /* 102 */    $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Calidad%"],2),$_FRM['TBLR']);$colidx++;
   /* 103 */    $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Oee%"],2),$_FRM['TBLR']);$colidx++;
   /* 104 */    $xls->write_number($rowidx,$colidx, getPrice($sqlrow["Producidos"],0),$_FRM['MONTO']);$colidx++;
   /* 105 */    $xls->write_string($rowidx,$colidx, $sqlrow["ObservaciónProducción"],$_FRM['TBLR']);$colidx++;   

  
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

// *************************************************************************************************************************************************
function xls_createStatsCorteySellado($CON)
{
   global $_LANG;
   global $_sesmodulename;
   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');
   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
   /* 01 */   "Fecha Inicio CC",
   /* 02 */   "Fecha de Proceso",
   /* 03 */   "Numero de CC",
   /* 04 */   "Cantidad de Bolsas",
   /* 05 */   "Numero OT",
   /* 06 */   "Cliente",
   /* 07 */   "Tipo de Bolsa",
   /* 08 */   "Formato Bolsa",
   /* 09 */   "Codigo Producto",
   /* 10 */   "Descripcion de Producto",
   /* 11 */   "Corte de Bolsa",
   /* 12 */   "UM",
   /* 13 */   "Ancho Bolsa",
   /* 14 */   "Alto Frente de Bolsa",
   /* 15 */   "Alto Dorso de Bolsa",
   /* 16 */   "Medida Doblez Superio",
   /* 17 */   "Medida Fuelle de Bolsa",
   /* 18 */   "Cabezal",
   /* 19 */   "Color de Tela",
   /* 20 */   "Código Color Tela",
   /* 21 */   "Gramaje",
   /* 22 */   "Ancho Bobina",
   /* 23 */   "Codigo de Tela",
   /* 24 */   "Código de Barra",
   /* 25 */   "Dados Manillas",
   /* 26 */   "Color Manillas",
   /* 27 */   "Codigo Color Manillas",
   /* 28 */   "Ancho Manillas",
   /* 29 */   "Largo Manillas",
   /* 30 */   "Cantidad de Rollos (Manillas)",
   /* 31 */   "Cantidad de Bobinas (Unidades)",
   /* 32 */   "Código de Barras (si/no)",
   /* 33 */   "Numero de Código de Barras",
   /* 34 */   "Alarma (si-no)",
   /* 35 */   "Numero de Alarma",
   /* 36 */   "Nombre Máquina",
   /* 37 */   "N° Máquina",
   /* 38 */   "Nombre supervisor",
   /* 39 */   "Rut Supervisor",
   /* 40 */   "Fecha Hora Inicio (Alistamiento)",
   /* 41 */   "Fecha Hora Término (Alistamiento)",
   /* 42 */   "Total Horas (Alistamiento)",
   /* 43 */   "Estado Alistamiento",
   /* 44 */   "Cambio de Configuración de",
   /* 45 */   "Cambio de Configuración a",
   /* 46 */   "Fecha Hora Inicio (Producción)",
   /* 47 */   "Fecha Hora Termino (Producción)",
   /* 48 */   "Total Hora (Producción)",
   /* 49 */   "Estado Producción",
   /* 50 */   "Turno (mañana-tarde-noche)",
   /* 51 */   "Horas Turno",
   /* 52 */   "Nombre Operador",
   /* 53 */   "Rut Operador",
   /* 54 */   "Nombre Ayudante",
   /* 55 */   "Rut Ayudante",
   /* 56 */   "Unidades Programadas",
   /* 57 */   "Total Producido Contador 1 (Unidades/turno)",
   /* 58 */   "Total Producido Contador 2 (Unidades/turno)",
   /* 59 */   "Producción Esperada (Unidades)",
   /* 60 */   "Eficiencia Productiva (%)",
   /* 61 */   "Velocidad Máquina (Golpes x Minuto)",
   /* 62 */   "Peso Unitario (Gramos)",
   /* 63 */   "N° Bobinas Procesadas (Unidades)",
   /* 64 */   "Kilos Bobinas Programadas (Kgs)",
   /* 65 */   "Kilos Bobinas Procesadas (Kgs)",
   /* 66 */   "Kilos Manillas Programadas (Kgs)",
   /* 67 */   "Kilos Manillas Procesadas (Kgs)",
   /* 68 */   "Total Kilos Programadas (KGS)",
   /* 69 */   "Total Kilos Procesadas (KGS)",
   /* 70 */   "% Mermas",
   /* 71 */   "Metros Lineales Bobinas Procesadas (Mts/Lineales)",
   /* 72 */   "N° Manillas Procesadas (Unidades)",
   /* 73 */   "Ancho Manillas Procesadas (Cms)",
   /* 74 */   "Metros Lineales Manillas Procesadas (Mts/Lineales)",
   /* 75 */   "Cantidad Bolsas a Reparar (Unidades)",
   /* 76 */   "Kilos Bolsas a Repara",
   /* 77 */   "Mermas Unidades (Alistamiento)",
   /* 78 */   "Mermas KGS (Alistamiento)",
   /* 79 */   "Mermas Malas por Impresión (Unidades)",
   /* 80 */   "Mermas Malas por Impresión (Kgs)",
   /* 81 */   "Mermas Bobina Defectuosa (Unidades)",
   /* 82 */   "Mermas Bobina Defectuosa (Kgs)",
   /* 83 */   "Tiempo Productivo (Horas)",
   /* 84 */   "Total Paros (Horas)",
   );
      
/* 01 */   $xls->set_column(0,0,19);
/* 02 */   $xls->set_column(0,1,19);
/* 03 */   $xls->set_column(0,2,15);
/* 04 */   $xls->set_column(0,3,21);
/* 05 */   $xls->set_column(0,4,12);
/* 06 */   $xls->set_column(0,5,100);
/* 07 */   $xls->set_column(0,6,16);
/* 08 */   $xls->set_column(0,7,16);
/* 09 */   $xls->set_column(0,8,18);
/* 10 */   $xls->set_column(0,9,26);
/* 11 */   $xls->set_column(0,10,17);
/* 12 */   $xls->set_column(0,11,5);
/* 13 */   $xls->set_column(0,12,14);
/* 14 */   $xls->set_column(0,13,23);
/* 15 */   $xls->set_column(0,14,22);
/* 16 */   $xls->set_column(0,15,24);
/* 17 */   $xls->set_column(0,16,25);
/* 18 */   $xls->set_column(0,17,10);
/* 19 */   $xls->set_column(0,18,16);
/* 20 */   $xls->set_column(0,19,20);
/* 21 */   $xls->set_column(0,20,10);
/* 22 */   $xls->set_column(0,21,15);
/* 23 */   $xls->set_column(0,22,17);
/* 24 */   $xls->set_column(0,23,18);
/* 25 */   $xls->set_column(0,24,17);
/* 26 */   $xls->set_column(0,25,17);
/* 27 */   $xls->set_column(0,26,24);
/* 28 */   $xls->set_column(0,27,17);
/* 29 */   $xls->set_column(0,28,17);
/* 30 */   $xls->set_column(0,29,32);
/* 31 */   $xls->set_column(0,30,33);
/* 32 */   $xls->set_column(0,31,27);
/* 33 */   $xls->set_column(0,32,29);
/* 34 */   $xls->set_column(0,33,17);
/* 35 */   $xls->set_column(0,34,19);
/* 36 */   $xls->set_column(0,35,17);
/* 37 */   $xls->set_column(0,36,13);
/* 38 */   $xls->set_column(0,37,20);
/* 39 */   $xls->set_column(0,38,17);
/* 40 */   $xls->set_column(0,39,35);
/* 41 */   $xls->set_column(0,40,36);
/* 42 */   $xls->set_column(0,41,29);
/* 43 */   $xls->set_column(0,42,22);
/* 44 */   $xls->set_column(0,43,22);
/* 45 */   $xls->set_column(0,44,22);
/* 46 */   $xls->set_column(0,45,33);
/* 47 */   $xls->set_column(0,46,34);
/* 48 */   $xls->set_column(0,47,26);
/* 49 */   $xls->set_column(0,48,20);
/* 50 */   $xls->set_column(0,49,29);
/* 51 */   $xls->set_column(0,50,14);
/* 52 */   $xls->set_column(0,51,18);
/* 53 */   $xls->set_column(0,52,15);
/* 54 */   $xls->set_column(0,53,18);
/* 55 */   $xls->set_column(0,54,15);
/* 56 */   $xls->set_column(0,55,23);
/* 57 */   $xls->set_column(0,56,46);
/* 58 */   $xls->set_column(0,57,46);
/* 59 */   $xls->set_column(0,58,33);
/* 60 */   $xls->set_column(0,59,28);
/* 61 */   $xls->set_column(0,60,38);
/* 62 */   $xls->set_column(0,61,25);
/* 63 */   $xls->set_column(0,62,35);
/* 64 */   $xls->set_column(0,63,34);
/* 65 */   $xls->set_column(0,64,33);
/* 66 */   $xls->set_column(0,65,35);
/* 67 */   $xls->set_column(0,66,34);
/* 68 */   $xls->set_column(0,67,32);
/* 69 */   $xls->set_column(0,68,31);
/* 70 */   $xls->set_column(0,69,11);
/* 71 */   $xls->set_column(0,70,52);
/* 72 */   $xls->set_column(0,71,36);
/* 73 */   $xls->set_column(0,72,34);
/* 74 */   $xls->set_column(0,73,53);
/* 75 */   $xls->set_column(0,74,39);
/* 76 */   $xls->set_column(0,75,24);
/* 77 */   $xls->set_column(0,76,33);
/* 78 */   $xls->set_column(0,77,28);
/* 79 */   $xls->set_column(0,78,40);
/* 80 */   $xls->set_column(0,79,35);
/* 81 */   $xls->set_column(0,80,38);
/* 82 */   $xls->set_column(0,81,33);
/* 83 */   $xls->set_column(0,82,28);
/* 84 */   $xls->set_column(0,83,22);


   
   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;    
/* 01 */      $xls->write($rowidx, $colidx,timestampToExcelDate($sqlrow["FechaIngresoCC"])                 , $_FRM["FECHAHORA"]);$colidx++; 
/* 02 */      $xls->write($rowidx, $colidx,timestampToExcelDate($sqlrow["FechaProduccion"])                , $_FRM['FECHA']);$colidx++;
/* 03 */      $xls->write_string($rowidx, $colidx, $sqlrow["NúmerodeCC"]                                   , $_FRM['TBLR']);$colidx++;
/* 04 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddeBolsas"], 0)                , $_FRM['TBLR']);$colidx++;
/* 05 */      $xls->write_string($rowidx, $colidx, $sqlrow["NumerodeOT"]                                   , $_FRM['TBLR']);$colidx++;
/* 06 */      $xls->write_string($rowidx, $colidx, $sqlrow["Cliente"]                                      , $_FRM['TBLR']);$colidx++;
/* 07 */      $xls->write_string($rowidx, $colidx, $sqlrow["TipoBolsa"]                                    , $_FRM['TBLR']);$colidx++;
/* 08 */      $xls->write_string($rowidx, $colidx, $sqlrow["FormatoBolsa"]                                 , $_FRM['TBLR']);$colidx++;
/* 09 */      $xls->write_string($rowidx, $colidx, $sqlrow["CodigoProducto"]                               , $_FRM['TBLR']);$colidx++;
/* 10 */      $xls->write_string($rowidx, $colidx, $sqlrow["DescripcionProducto"]                          , $_FRM['TBLR']);$colidx++;
/* 11 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CortedeBolsa"], 4)                    , $_FRM['TBLR']);$colidx++;
/* 12 */      $xls->write_string($rowidx, $colidx, $sqlrow["UM"]                                           , $_FRM['TBLR']);$colidx++;
/* 13 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchodeBolsa"])                       , $_FRM['TBLR']);$colidx++;
/* 14 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AltoFrentedeBolsa"])                  , $_FRM['TBLR']);$colidx++;
/* 15 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AltoDorsodeBolsa"])                   , $_FRM['TBLR']);$colidx++;
/* 16 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MedidaDoblezSuperior"])               , $_FRM['TBLR']);$colidx++;
/* 17 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MedidaFuelledeBolsa"])                , $_FRM['TBLR']);$colidx++;
/* 18 */      $xls->write_string($rowidx, $colidx, $sqlrow["Cabezal"]                                      , $_FRM['TBLR']);$colidx++;
/* 19 */      $xls->write_string($rowidx, $colidx, $sqlrow["ColorTela"]                                    , $_FRM['TBLR']);$colidx++;
/* 20 */      $xls->write_string($rowidx, $colidx, $sqlrow["CódigoColorTela"]                              , $_FRM['TBLR']);$colidx++;
/* 21 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Gramaje"],0)                          , $_FRM['TBLR']);$colidx++;
/* 22 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchoBobina"])                        , $_FRM['TBLR']);$colidx++;
/* 23 */      $xls->write_string($rowidx, $colidx, $sqlrow["CódigodeTela"]                                 , $_FRM['TBLR']);$colidx++;
/* 24 */      $xls->write_string($rowidx, $colidx, $sqlrow["CódigodeBarra"]                                , $_FRM['TBLR']);$colidx++;
/* 25 */      $xls->write_string($rowidx, $colidx, $sqlrow["DadosManillas"]                                , $_FRM['TBLR']);$colidx++;
/* 26 */      $xls->write_string($rowidx, $colidx, $sqlrow["ColorManillas"]                                , $_FRM['TBLR']);$colidx++;
/* 27 */      $xls->write_string($rowidx, $colidx, $sqlrow["CodigoColorManilla"]                           , $_FRM['TBLR']);$colidx++;
/* 28 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchoManillas"])                      , $_FRM['TBLR']);$colidx++;
/* 29 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["LargoManillas"])                      , $_FRM['TBLR']);$colidx++;
/* 30 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadDeRollosManillas"], 0)        , $_FRM['TBLR']);$colidx++;
/* 31 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadBobinasUnidad"], 0)           , $_FRM['TBLR']);$colidx++;
/* 32 */      $xls->write_string($rowidx, $colidx, $sqlrow["CodigoBarraSINO"]                              , $_FRM['TBLR']);$colidx++;
/* 33 */      $xls->write_string($rowidx, $colidx, $sqlrow["NumeroCodigoBarra"]                            , $_FRM['TBLR']);$colidx++;
/* 34 */      $xls->write_string($rowidx, $colidx, $sqlrow["AlarmaSINO"]                                   , $_FRM['TBLR']);$colidx++;
/* 35 */      $xls->write_string($rowidx, $colidx, $sqlrow["NumeroAlarma"]                                 , $_FRM['TBLR']);$colidx++;
/* 36 */      $xls->write_string($rowidx, $colidx, $sqlrow["NombreMaquina"]                                , $_FRM['TBLR']);$colidx++;
/* 37 */      $xls->write_string($rowidx, $colidx, $sqlrow["NumeroMaquina"]                                , $_FRM['TBLR']);$colidx++;
/* 38 */      $xls->write_string($rowidx, $colidx, $sqlrow["NombreSupervisor"]                             , $_FRM['TBLR']);$colidx++;
/* 39 */      $xls->write_string($rowidx, $colidx, $sqlrow["RutSupervisor"]                                , $_FRM['TBLR']);$colidx++;
              $xls->{($v = fechaStrAExcel($sqlrow["FechaHoraInicioAlistamiento"])) > 0 ? 'write' : 'write_blank'}($rowidx, $colidx, $v, $_FRM['FECHAHORA']); $colidx++;
              $xls->{($v = fechaStrAExcel($sqlrow["FechaHoraTérminoAlistamiento"])) > 0 ? 'write' : 'write_blank'}($rowidx, $colidx, $v, $_FRM['FECHAHORA']); $colidx++;
/* 42 */      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalHorasAlistamiento"])    , $_FRM['DURACION']);$colidx++;
/* 43 */      $xls->write_string($rowidx, $colidx, $sqlrow["EstadoAlistamiento"]                    , $_FRM['TBLR']);$colidx++;
/* 44 */      $xls->write_string($rowidx, $colidx, $sqlrow["CambiodeConfiguraciónde"]               , $_FRM['TBLR']);$colidx++;
/* 45 */      $xls->write_string($rowidx, $colidx, $sqlrow["CambiodeConfiguracióna"]                , $_FRM['TBLR']);$colidx++;
              $xls->{($v = fechaStrAExcel($sqlrow["FechaHoraInicioProducción"])) > 0 ? 'write' : 'write_blank'}($rowidx, $colidx, $v, $_FRM['FECHAHORA']); $colidx++;
              $xls->{($v = fechaStrAExcel($sqlrow["FechaHoraTérminoProducción"])) > 0 ? 'write' : 'write_blank'}($rowidx, $colidx, $v, $_FRM['FECHAHORA']); $colidx++;
/* 48 */      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalHoraProducción"])           , $_FRM['DURACION']);$colidx++; 
/* 49 */      $xls->write_string($rowidx, $colidx, $sqlrow["EstadoProduccion"]                             , $_FRM['TBLR']);$colidx++;
/* 50 */      $xls->write_string($rowidx, $colidx, $sqlrow["Turnomañana-tarde-noche"]                      , $_FRM['TBLR']);$colidx++;
/* 51 */      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["HorasTurno"])                , $_FRM['DURACION']);$colidx++;
/* 52 */      $xls->write_string($rowidx, $colidx, $sqlrow["NombreOperador"]                               , $_FRM['TBLR']);$colidx++;
/* 53 */      $xls->write_string($rowidx, $colidx, $sqlrow["RutOperador"]                                  , $_FRM['TBLR']);$colidx++;
/* 54 */      $xls->write_string($rowidx, $colidx, $sqlrow["NombreAyudante"]                               , $_FRM['TBLR']);$colidx++;
/* 55 */      $xls->write_string($rowidx, $colidx, $sqlrow["RutAyudante"]                                  , $_FRM['TBLR']);$colidx++;
/* 56 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["UnidadesProgramadas"])                , $_FRM['TBLR']);$colidx++;
/* 57 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalProducidoContador1"], 2)         , $_FRM['TBLR']);$colidx++;
/* 58 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalProducidoContador2"], 2)         , $_FRM['TBLR']);$colidx++;
/* 59 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["ProduccionEsperada"], 2)              , $_FRM['TBLR']);$colidx++;
/* 60 */      $xls->write_string($rowidx, $colidx, $sqlrow["EficienciaProductiva"]                         , $_FRM['TBLR']);$colidx++;
/* 61 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["VelocidadMaquina"])                   , $_FRM['TBLR']);$colidx++;
/* 62 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["PesoUnitario"],4)                     , $_FRM['TBLR']);$colidx++;
/* 63 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["NroBobinaProcesada"])                 , $_FRM['TBLR']);$colidx++;
/* 64 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KilosBobinaProgramadas"], 4)          , $_FRM['TBLR']);$colidx++;
/* 65 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KilosBobinaProcesadas"], 4)           , $_FRM['TBLR']);$colidx++;
/* 66 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KilosManillasProgramadas"], 4)        , $_FRM['TBLR']);$colidx++;
/* 67 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KilosManillasProcesadas"], 4)         , $_FRM['TBLR']);$colidx++;
/* 68 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalKilosProgramadas"], 4)           , $_FRM['TBLR']);$colidx++;
/* 69 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalKilosProcesadas"], 4)            , $_FRM['TBLR']);$colidx++;
/* 70 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["PorcentajeMerma"], 2)                 , $_FRM['TBLR']);$colidx++;
/* 71 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MetrosLinealesBobProcesadas"], 2)     , $_FRM['TBLR']);$colidx++;
/* 72 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["NroManillasProcesadas"], 2)           , $_FRM['TBLR']);$colidx++;
/* 73 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchoManillaProcesadas"], 2)          , $_FRM['TBLR']);$colidx++;
/* 74 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MetrosLinealesManillaProcesadas"], 2) , $_FRM['TBLR']);$colidx++;
/* 75 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadBolsasRepararUn"], 0)         , $_FRM['TBLR']);$colidx++;
/* 76 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["KilosBolsasReparar"], 2)              , $_FRM['TBLR']);$colidx++;
/* 77 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasUnidadAlistamiento"], 0)        , $_FRM['TBLR']);$colidx++;
/* 78 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasKGSalistamiento"], 2)           , $_FRM['TBLR']);$colidx++;
/* 79 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasMalasImpresionUnidad"], 0)      , $_FRM['TBLR']);$colidx++;
/* 80 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasMalasImpresionKGS"], 2)         , $_FRM['TBLR']);$colidx++;
/* 81 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["BobinaDefectuosaUnidades"], 0)        , $_FRM['TBLR']);$colidx++;
/* 82 */      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["ScrapsMalasporImpresionKgs"], 2)      , $_FRM['TBLR']);$colidx++;
/* 83 */      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TiempoProductivoHrs"])              , $_FRM['DURACION']) ;$colidx++;
/* 84 */      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalParosHoras"])                  , $_FRM['DURACION']);$colidx++;
      $rowidx++;
   }


   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsEmbalaje($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
   /* 01 */   "Fecha Inicio CC",
   /* 02 */   "Fecha de Proceso",
   /* 03 */   "Numero de CC",
   /* 04 */   "Cantidad de Bolsas",
   /* 05 */   "Numero OT",
   /* 06 */   "Cliente",
   /* 07 */   "Tipo de Bolsa",
   /* 08 */   "Formato Bolsa",
   /* 09 */   "Codigo Producto",
   /* 10 */   "Descripcion de Producto",
   /* 11 */   "Corte de Bolsa",
   /* 12 */   "Ancho Bolsa",
   /* 13 */   "Alto Frente de Bolsa",
   /* 14 */   "Alto Dorso de Bolsa",
   /* 15 */   "Medida Doblez Superio",
   /* 16 */   "Medida Fuelle de Bolsa",
   /* 17 */   "Cabezal",
   /* 18 */   "Ancho de Rollo",
   /* 19 */   "Color de Tela",
   /* 20 */   "Código Color Tela",
   /* 21 */   "Grameje",
   /* 22 */   "Código de Barra",
   /* 23 */   "Dados Manillas",
   /* 24 */   "Color Manillas",
   /* 25 */   "Codigo Color Manillas",
   /* 26 */   "Ancho Manillas",
   /* 27 */   "Largo Manillas",
   /* 28 */   "Código de Barras (si/no)",
   /* 29 */   "Numero de Código de Barras",
   /* 30 */   "Alarma (si-no)",
   /* 31 */   "Numero de Alarma",
   /* 32 */   "Nombre Máquina",
   /* 33 */   "N° Máquina",
   /* 34 */   "Nombre supervisor",
   /* 35 */   "Rut Supervisor",
   /* 36 */   "Fecha Hora Inicio (Alistamiento)",
   /* 37 */   "Fecha Hora Termino (Alistamiento)",
   /* 38 */   "Total Hora (Alistamiento)",
   /* 39 */   "Estado de Alistamiento",
   /* 40 */   "Fecha Hora Inicio (Producción)",
   /* 41 */   "Fecha Hora Termino (Producción)",
   /* 42 */   "Total Hora (Producción)",
   /* 43 */   "Estado de Producción",
   /* 44 */   "Turno (mañana-tarde-noche)",
   /* 45 */   "Horas Turno",
   /* 46 */   "Nombre Operador",
   /* 47 */   "Rut Operador",
   /* 48 */   "Nombre Ayudante",
   /* 49 */   "Rut Ayudante",
   /* 50 */   "Producción Esperada (Unidades)",
   /* 51 */   "Total Programado (Unidades/turno)",
   /* 52 */   "Reversa (Si-No)",
   /* 53 */   "Impresión Externa (Si-No)",
   /* 54 */   "Taller de impresión externa",
   /* 55 */   "OTRO (Si-No)",
   /* 56 */   "Medida de caja [Programado]",
   /* 57 */   "Cantidad de bolsas por caja [Programado]",
   /* 58 */   "Cantidad de cajas por pallets [Programado]",
   /* 59 */   "Cantidad de pallets completos [Programado]",
   /* 60 */   "Numero de cajas en pallet incompleto [Programado]",
   /* 61 */   "Cantidad total de cajas completas [Programado]",
   /* 62 */   "Caja final (completa pedido) [Programado]",
   /* 63 */   "Total Producido (Unidades/turno)",
   /* 64 */   "Total Unidades Producidas",
   /* 65 */   "Pallets Etiquetados (Si-No) [Producido]",
   /* 66 */   "Medida Caja [Producido]",
   /* 67 */   "Cantidad de bolsas por caja [Producido]",
   /* 68 */   "Cantidad de cajas por pallets [Producido]",
   /* 69 */   "Cantidad de pallets completos [Producido]",
   /* 70 */   "Numero de cajas en pallet incompleto [Producido]",
   /* 71 */   "Cantidad total de cajas completas [Producido]",
   /* 72 */   "Caja final (completa pedido) [Producido]",
   /* 73 */   "Bolsa Sobrantes [Producido]",
   /* 74 */   "Cinta Embalaje Utilizada (metros) [Producido]",
   /* 75 */   "Cantidad Colores [Producido]",
   /* 76 */   "Tipo Impresión (Flexo-Seri) [Producido]",
   /* 77 */   "Eficiencia Productiva (%)",
   /* 78 */   "Peso Unitario (Gramos)",
   /* 79 */   "Peso Bruto Caja (Gramos)",
   /* 80 */   "Bolsas Reparación (Unidades)",
   /* 81 */   "Bolsas Reparación (kgs)",
   /* 82 */   "Mermas Otros (Unidades)",
   /* 83 */   "Mermas Otros (Kgs)",
   /* 84 */   "Mermas Impresión (Unidades)",
   /* 85 */   "Mermas Impresión (Kgs)",
   /* 86 */   "Mermas Configuración (Unidades)",
   /* 87 */   "Mermas Configuración (Kgs)",
   /* 88 */   "kg Neto Programado",
   /* 89 */   "Kg Neto Producido",
   /* 90 */   "kg Bruto Programado",
   /* 91 */   "Kg Bruto Producido",
   /* 92 */   "% Merma",
   /* 93 */   "Disponibilidad (%)",
   /* 94 */   "Rendimiento (%)",
   /* 95 */   "Calidad (%)",
   /* 96 */   "OEE (%)",
   /* 97 */   "$ Producidos",
   /* 98 */   "Observación",
   /* 99 */   "Supervisor",
   );
      
/* 01 "Fecha Ingreso CC" */                     $xls->set_column(0,0,19);
/* 02 "Fecha de Proceso" */                     $xls->set_column(0,1,19);
/* 03 "Numero de CC" */                         $xls->set_column(0,2,19);
/* 04 "Cantidad de Bolsas" */                   $xls->set_column(0,3,15);
/* 05 "Numero OT" */                            $xls->set_column(0,4,12);
/* 06 "Cliente" */                              $xls->set_column(0,5,100);
/* 07 "Tipo de Bolsa" */                        $xls->set_column(0,6,20);
/* 08 "Formato Bolsa" */                        $xls->set_column(0,7,20);
/* 09 "Codigo Producto" */                      $xls->set_column(0,8,20);
/* 10 "Descripcion de Producto" */              $xls->set_column(0,9,100);
/* 11 "Corte de Bolsa" */                       $xls->set_column(0,10,15);
/* 12 "Ancho Bolsa" */                          $xls->set_column(0,11,15);
/* 13 "Alto Frente de Bolsa" */                 $xls->set_column(0,12,15);
/* 14 "Alto Dorso de Bolsa" */                  $xls->set_column(0,13,15);
/* 15 "Medida Doblez Superio" */                $xls->set_column(0,14,15);
/* 16 "Medida Fuelle de Bolsa" */               $xls->set_column(0,15,15);
/* 17 "Cabezal" */                              $xls->set_column(0,16,20);
/* 18 "Ancho de Rollo" */                       $xls->set_column(0,17,15);
/* 19 "Color de Tela" */                        $xls->set_column(0,18,50);
/* 20 "Código Color Tela" */                    $xls->set_column(0,19,15);
/* 21 "Grameje" */                              $xls->set_column(0,20,15);
/* 22 "Código de Barra" */                      $xls->set_column(0,21,20);
/* 23 "Dados Manillas" */                       $xls->set_column(0,22,15);
/* 24 "Color Manillas" */                       $xls->set_column(0,23,20);
/* 25 "Codigo Color Manillas" */                $xls->set_column(0,24,15);
/* 26 "Ancho Manillas" */                       $xls->set_column(0,25,15);
/* 27 "Largo Manillas" */                       $xls->set_column(0,26,15);
/* 28 "Código de Barras (si/no)" */             $xls->set_column(0,27,15);
/* 29 "Numero de Código de Barras" */           $xls->set_column(0,28,25);
/* 30 "Alarma (si-no)" */                       $xls->set_column(0,29,15);
/* 31 "Numero de Alarma" */                     $xls->set_column(0,30,15);
/* 32 "Nombre Máquina" */                       $xls->set_column(0,31,50);
/* 33 "N° Máquina" */                           $xls->set_column(0,32,15);
/* 34 "Nombre supervisor" */                    $xls->set_column(0,33,100);
/* 35 "Rut Supervisor" */                       $xls->set_column(0,34,20);
/* 36 "Fecha Hora Inicio (Alistamiento)" */     $xls->set_column(0,35,20);
/* 37 "Fecha Hora Termino (Alistamiento)" */    $xls->set_column(0,36,20);
/* 38 "Total Hora (Alistamiento)" */            $xls->set_column(0,37,15);
/* 39 "Estado de Alistamiento" */               $xls->set_column(0,38,20);
/* 40 "Fecha Hora Inicio (Producción)" */       $xls->set_column(0,39,20);
/* 41 "Fecha Hora Termino (Producción)" */      $xls->set_column(0,40,20);
/* 42 "Total Hora (Producción)" */              $xls->set_column(0,41,15);
/* 43 "Estado de Producción" */                 $xls->set_column(0,42,20);
/* 44 "Turno (mañana-tarde-noche)" */           $xls->set_column(0,43,20);
/* 45 "Horas Turno" */                          $xls->set_column(0,44,20);
/* 46 "Nombre Operador" */                      $xls->set_column(0,45,100);
/* 47 "Rut Operador" */                         $xls->set_column(0,46,20);
/* 48 "Nombre Ayudante" */                      $xls->set_column(0,47,100);
/* 49 "Rut Ayudante" */                         $xls->set_column(0,48,20);
/* 50 "Producción Esperada (Unidades)" */       $xls->set_column(0,49,30);
/* 51 "Total Programado (Unidades/turno)" */    $xls->set_column(0,50,30);
/* 52 "Reversa (Si-No)" */                      $xls->set_column(0,51,15);
/* 53 "Impresión Externa (Si-No)" */            $xls->set_column(0,52,15);
/* 54 "Taller de impresión externa" */          $xls->set_column(0,53,15);
/* 55 "OTRO (Si-No)" */                         $xls->set_column(0,54,15);
/* 56 "Medida de caja [Programado]" */          $xls->set_column(0,55,15);
/* 57 "Cantidad de bolsas por caja [Progra */   $xls->set_column(0,56,15);
/* 58 "Cantidad de cajas por pallets [Prog */   $xls->set_column(0,57,15);
/* 59 "Cantidad de pallets completos [Prog */   $xls->set_column(0,58,15);
/* 60 "Numero de cajas en pallet incomplet */   $xls->set_column(0,59,15);
/* 61 "Cantidad total de cajas completas [ */   $xls->set_column(0,60,15);
/* 62 "Caja final (completa pedido) [Progr */   $xls->set_column(0,61,15);
/* 63 "Total Producido (Unidades/turno)" */     $xls->set_column(0,62,15);
/* 64 "Total Producido (Unidades/turno)" */     $xls->set_column(0,62,15);
/* 65 "Pallets Etiquetados (Si-No) [Produc */   $xls->set_column(0,63,15);
/* 66 "Medida Caja [Producido]" */              $xls->set_column(0,64,15);
/* 67 "Cantidad de bolsas por caja [Produc */   $xls->set_column(0,65,15);
/* 68 "Cantidad de cajas por pallets [Prod */   $xls->set_column(0,66,15);
/* 69 "Cantidad de pallets completos [Prod */   $xls->set_column(0,67,15);
/* 70 "Numero de cajas en pallet incomplet */   $xls->set_column(0,68,15);
/* 71 "Cantidad total de cajas completas [ */   $xls->set_column(0,69,15);
/* 72 "Caja final (completa pedido) [Produ */   $xls->set_column(0,70,15);
/* 73 "Bolsa Sobrantes [Producido]" */          $xls->set_column(0,71,15);
/* 74 "Cinta Embalaje Utilizada (metros) [ */   $xls->set_column(0,72,15);
/* 75 "Cantidad Colores [Producido]" */         $xls->set_column(0,73,15);
/* 76 "Tipo Impresión (Flexo-Seri) [Produc */   $xls->set_column(0,74,10);
/* 77 "Eficiencia Productiva (%)" */            $xls->set_column(0,75,15);
/* 78 "Peso Unitario (Gramos)" */               $xls->set_column(0,76,15);
/* 79 "Peso Bruto Caja (Gramos)" */             $xls->set_column(0,77,15);
/* 80 "Bolsas Reparación (Unidades)" */         $xls->set_column(0,78,15);
/* 81 "Bolsas Reparación (kgs)" */              $xls->set_column(0,79,15);
/* 82 "Mermas Otros (Unidades)" */              $xls->set_column(0,80,15);
/* 83 "Mermas Otros (Kgs)" */                   $xls->set_column(0,81,15);
/* 84 "Mermas Impresión (Unidades)" */          $xls->set_column(0,82,15);
/* 85 "Mermas Impresión (Kgs)" */               $xls->set_column(0,83,15);
/* 86 "Mermas Configuración (Unidades)" */      $xls->set_column(0,84,15);
/* 87 "Mermas Configuración (Kgs)" */           $xls->set_column(0,85,15);
/* 88 "kg Neto Programado" */                   $xls->set_column(0,86,15);
/* 89 "Kg Neto Producido" */                    $xls->set_column(0,87,15);
/* 90 "kg Bruto Programado" */                  $xls->set_column(0,88,15);
/* 91 "Kg Bruto Producido" */                   $xls->set_column(0,89,15);
/* 92 "% Merma" */                              $xls->set_column(0,90,15);
/* 93 "Disponibilidad (%)" */                   $xls->set_column(0,91,15);
/* 94 "Rendimiento (%)" */                      $xls->set_column(0,92,15);
/* 95 "Calidad (%)" */                          $xls->set_column(0,93,15);
/* 96 "OEE (%)" */                              $xls->set_column(0,94,15);
/* 97 "$ Producidos" */                         $xls->set_column(0,95,15);
/* 97 "$ Producidos" */                         $xls->set_column(0,96,15);
/* 98 "Observación", */                         $xls->set_column(0,97,100);
/* 99 "Supervisor", */                          $xls->set_column(0,98,100);
//----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
         $colidx = 0;   

/* 01 */ $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d H:i", $sqlrow["FechaIngresoCC"]))), $_FRM["FECHAHORA"]);$colidx++;
/* 02 */ $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["FechaProduccion"]))), $_FRM["FECHA"]);$colidx++;
/* 03 */ $xls->write_string($rowidx, $colidx, $sqlrow["NumerodeCC"]                                , $_FRM['TBLR']);$colidx++;
/* 04 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddeBolsas"])                , $_FRM['TBLR']);$colidx++;
/* 05 */ $xls->write_string($rowidx, $colidx, $sqlrow["NumeroOT"]                                  , $_FRM['TBLR']);$colidx++;
/* 06 */ $xls->write_string($rowidx, $colidx, $sqlrow["Cliente"]                                   , $_FRM['TBLR']);$colidx++;
/* 07 */ $xls->write_string($rowidx, $colidx, $sqlrow["TipodeBolsa"]                               , $_FRM['TBLR']);$colidx++;
/* 08 */ $xls->write_string($rowidx, $colidx, $sqlrow["FormatoBolsa"]                              , $_FRM['TBLR']);$colidx++;
/* 09 */ $xls->write_string($rowidx, $colidx, $sqlrow["CodigoProducto"]                            , $_FRM['TBLR']);$colidx++;
/* 10 */ $xls->write_string($rowidx, $colidx, $sqlrow["DescripciondeProducto"]                     , $_FRM['TBLR']);$colidx++;
/* 11 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CortedeBolsa"],4)                  , $_FRM['TBLR']);$colidx++;
/* 12 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchoBolsa"])                      , $_FRM['TBLR']);$colidx++;
/* 13 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AltoFrente de Bolsa"])             , $_FRM['TBLR']);$colidx++;
/* 14 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AltoDorsodeBolsa"])                , $_FRM['TBLR']);$colidx++;
/* 15 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MedidaDoblezSuperio"])             , $_FRM['TBLR']);$colidx++;
/* 16 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MedidaFuelledeBolsa"])             , $_FRM['TBLR']);$colidx++;
/* 17 */ $xls->write_string($rowidx, $colidx, $sqlrow["Cabezal"]                                   , $_FRM['TBLR']);$colidx++;
/* 18 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchodeRollo"])                    , $_FRM['TBLR']);$colidx++;
/* 19 */ $xls->write_string($rowidx, $colidx, $sqlrow["ColordeTela"]                               , $_FRM['TBLR']);$colidx++;
/* 20 */ $xls->write_string($rowidx, $colidx, $sqlrow["CódigoColorTela"]                           , $_FRM['TBLR']);$colidx++;
/* 21 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Grameje"])                         , $_FRM['TBLR']);$colidx++;
/* 22 */ $xls->write_string($rowidx, $colidx, $sqlrow["CódigodeBarra"]                             , $_FRM['TBLR']);$colidx++;
/* 23 */ $xls->write_string($rowidx, $colidx, $sqlrow["DadosManillas"]                             , $_FRM['TBLR']);$colidx++;
/* 24 */ $xls->write_string($rowidx, $colidx, $sqlrow["ColorManillas"]                             , $_FRM['TBLR']);$colidx++;
/* 25 */ $xls->write_string($rowidx, $colidx, $sqlrow["CodigoColorManillas"]                       , $_FRM['TBLR']);$colidx++;
/* 26 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["AnchoManillas"])                   , $_FRM['TBLR']);$colidx++;
/* 27 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["LargoManillas"])                   , $_FRM['TBLR']);$colidx++;
/* 28 */ $xls->write_string($rowidx, $colidx, $sqlrow["CódigodeBarras (si/no)"]                    , $_FRM['TBLR']);$colidx++;
/* 29 */ $xls->write_string($rowidx, $colidx, $sqlrow["NumerodeCódigo de Barras"]                  , $_FRM['TBLR']);$colidx++;
/* 30 */ $xls->write_string($rowidx, $colidx, $sqlrow["AlarmaSiNo)"]                               , $_FRM['TBLR']);$colidx++;
/* 31 */ $xls->write_string($rowidx, $colidx, $sqlrow["NumerodeAlarma"]                            , $_FRM['TBLR']);$colidx++;
/* 32 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreMáquina"]                             , $_FRM['TBLR']);$colidx++;
/* 33 */ $xls->write_string($rowidx, $colidx, $sqlrow["NMáquina"]                                  , $_FRM['TBLR']);$colidx++;
/* 34 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreSupervisor"]                          , $_FRM['TBLR']);$colidx++;
/* 35 */ $xls->write_string($rowidx, $colidx, $sqlrow["RutSupervisor"]                             , $_FRM['TBLR']);$colidx++;
/* 36 */ $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraInicioAlistamiento"]), $_FRM["FECHAHORA"]); $colidx++; 
/* 37 */ $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraTerminoAlistamiento"]), $_FRM["FECHAHORA"]); $colidx++;    
/* 38 */ $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalHoraAlistamiento"])    , $_FRM['DURACION']);$colidx++;

/* 39 */ $xls->write_string($rowidx, $colidx, $sqlrow["EstadodeAlistamiento"]                      , $_FRM['TBLR']);$colidx++;

/* 40 */ $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraInicioProducción"]), $_FRM["FECHAHORA"]); $colidx++; 
/* 41 */ $xls->write($rowidx, $colidx, fechaStrAExcel($sqlrow["FechaHoraTerminoProducción"]), $_FRM["FECHAHORA"]); $colidx++;    
/* 42 */ $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalHoraProducción"])    , $_FRM['DURACION']);$colidx++;
/* 43 */ $xls->write_string($rowidx, $colidx, $sqlrow["EstadodeProducción"]                        , $_FRM['TBLR']);$colidx++;
/* 44 */ $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TurnomañanaTardeNoche"])    , $_FRM['DURACION']);$colidx++;
/* 45 */ $xls->write_string($rowidx, $colidx, $sqlrow["HorasTurno"]                                , $_FRM['TBLR']);$colidx++;
/* 46 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreOperador"]                            , $_FRM['TBLR']);$colidx++;
/* 47 */ $xls->write_string($rowidx, $colidx, $sqlrow["RutOperador"]                               , $_FRM['TBLR']);$colidx++;
/* 48 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreAyudante"]                            , $_FRM['TBLR']);$colidx++; 
/* 49 */ $xls->write_string($rowidx, $colidx, $sqlrow["RutAyudante"]                               , $_FRM['TBLR']);$colidx++;
/* 50 */ $xls->write_string($rowidx, $colidx, $sqlrow["ProducciónEsperadaUnidades"]                , $_FRM['TBLR']);$colidx++;
/* 51 */ $xls->write_string($rowidx, $colidx, $sqlrow["TotalProgramadoUnidadesturno"]              , $_FRM['TBLR']);$colidx++;
/* 52 */ $xls->write_string($rowidx, $colidx, $sqlrow["ReversaSiNo"]                               , $_FRM['TBLR']);$colidx++;
/* 53 */ $xls->write_string($rowidx, $colidx, $sqlrow["ImpresiónExternaSiNo"]                      , $_FRM['TBLR']);$colidx++;
/* 54 */ $xls->write_string($rowidx, $colidx, $sqlrow["Tallerdeimpresiónexterna"]                  , $_FRM['TBLR']);$colidx++;
/* 55 */ $xls->write_string($rowidx, $colidx, $sqlrow["OTROSiNo"]                                  , $_FRM['TBLR']);$colidx++;
/* 56 */ $xls->write_string($rowidx, $colidx, $sqlrow["MedidadecajaProgramado"]                    , $_FRM['TBLR']);$colidx++;
/* 57 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddebolsasporcajaProgramado"])         , $_FRM['TBLR']);$colidx++;
/* 58 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddecajasporpalletsProgramado"])       , $_FRM['TBLR']);$colidx++;
/* 59 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddepalletscompletosProgramado"])      , $_FRM['TBLR']);$colidx++;
/* 60 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["NumerodecajasenpalletincompletoProgramado"]) , $_FRM['TBLR']);$colidx++;
/* 61 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadtotaldecajascompletasProgramado"])   , $_FRM['TBLR']);$colidx++;
/* 62 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CajafinalcompletapedidoProgramado"])         , $_FRM['TBLR']);$colidx++;
/* 63 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalProducidoUnidadesturno"])               , $_FRM['TBLR']);$colidx++;
/* 64 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["toatlunidadesembaladas"])               , $_FRM['TBLR']);$colidx++;
/* 65 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["PalletsEtiquetadosSiNoProducido"])           , $_FRM['TBLR']);$colidx++;
/* 66 */ $xls->write_string($rowidx, $colidx, $sqlrow["MedidaCajaProducido"]                                 , $_FRM['TBLR']);$colidx++;
/* 67 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddebolsasporcajaProducido"])          , $_FRM['TBLR']);$colidx++;
/* 68 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddecajasporpalletsProducido"])        , $_FRM['TBLR']);$colidx++;
/* 69 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidaddepalletscompletosProducido"])       , $_FRM['TBLR']);$colidx++;
/* 70 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["NumerodecajasenpalletincompletoProducido"])  , $_FRM['TBLR']);$colidx++;
/* 71 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadtotaldecajascompletasProducido"])    , $_FRM['TBLR']);$colidx++;
/* 72 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CajafinalcompletapedidoProducido"])          , $_FRM['TBLR']);$colidx++;
/* 73 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["BolsaSobrantesProducido"])                   , $_FRM['TBLR']);$colidx++;
/* 74 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CintaEmbalajeUtilizadametrosProducido"])     , $_FRM['TBLR']);$colidx++;
/* 75 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadColoresProducido"])                  , $_FRM['TBLR']);$colidx++;
/* 76 */ $xls->write_string($rowidx, $colidx, $sqlrow["TipoImpresiónFlexoSeriProducido"]           , $_FRM['TBLR']);$colidx++;
/* 77 */ $xls->write_string($rowidx, $colidx, $sqlrow["EficienciaProductiva%"]                     , $_FRM['TBLR']);$colidx++;
/* 78 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["PesoUnitarioGramos"],4)            , $_FRM['TBLR']);$colidx++;
/* 79 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["PesoBrutoCajaGramos"],4)           , $_FRM['TBLR']);$colidx++;
/* 80 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["BolsasReparaciónUnidades"])                  , $_FRM['TBLR']);$colidx++;
/* 81 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["BolsasReparaciónkgs"],2)                       , $_FRM['TBLR']);$colidx++;
/* 82 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasOtrosUnidades"])                       , $_FRM['TBLR']);$colidx++;
/* 83 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasOtrosKgs"],2)                            , $_FRM['TBLR']);$colidx++;
/* 84 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasImpresiónUnidades"])                   , $_FRM['TBLR']) ;$colidx++;
/* 85 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasImpresiónKgs"],2)                        , $_FRM['TBLR']);$colidx++;
/* 86 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasConfiguraciónUnidades"])               , $_FRM['TBLR']);$colidx++;
/* 87 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["MermasConfiguraciónKgs"],2)                    , $_FRM['TBLR']);$colidx++;
/* 88 */ $xls->write_string($rowidx, $colidx, $sqlrow["kgNetoProgramado"]                          , $_FRM['TBLR']);$colidx++;
/* 89 */ $xls->write_string($rowidx, $colidx, $sqlrow["KgNetoProducido"]                           , $_FRM['TBLR']);$colidx++;
/* 90 */ $xls->write_string($rowidx, $colidx, $sqlrow["kgBrutoProgramado"]                         , $_FRM['TBLR']);$colidx++;
/* 91 */ $xls->write_string($rowidx, $colidx, $sqlrow["KgBrutoProducido"]                          , $_FRM['TBLR']);$colidx++;
/* 92 */ $xls->write_string($rowidx, $colidx, $sqlrow["Merma"]                                     , $_FRM['TBLR']);$colidx++;
/* 93 */ $xls->write_string($rowidx, $colidx, $sqlrow["Disponibilidad"]                            , $_FRM['TBLR']);$colidx++;
/* 94 */ $xls->write_string($rowidx, $colidx, $sqlrow["Rendimiento"]                               , $_FRM['TBLR']);$colidx++;
/* 95 */ $xls->write_string($rowidx, $colidx, $sqlrow["Calidad"]                                   , $_FRM['TBLR']);$colidx++;
/* 96 */ $xls->write_string($rowidx, $colidx, $sqlrow["OEE"]                                       , $_FRM['TBLR']);$colidx++;
/* 97 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Producidos"])                      , $_FRM['TBLR']);$colidx++;
/* 98 */ $xls->write_string($rowidx, $colidx, $sqlrow["Observación"]                               , $_FRM['TBLR']);$colidx++; 
/* 98 */ $xls->write_string($rowidx, $colidx, $sqlrow["supervisor"]                               , $_FRM['TBLR']);$colidx++; 
         $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}


function xls_createStatsColacion($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
   /* 01 */   "Fecha-Hora Inicio Turno",
   /* 02 */   "Fecha-Hora Fin Turno ",
   /* 03 */   "Codigo Turno",
   /* 04 */   "Nombre Maquina",
   /* 05 */   "Rut Colaborador",
   /* 06 */   "Codigo Colaborador",
   /* 07 */   "Nombre Colaborador",
   /* 08 */   "Fecha Inicio Colación",
   /* 09 */   "Fecha Fin Colación",
   /* 10 */   "Total Minutos Colación",
   /* 11 */   "Estado",
   );
      
$xls->set_column(0,0,20);
$xls->set_column(0,1,20);
$xls->set_column(0,2,15);
$xls->set_column(0,3,100);
$xls->set_column(0,4,20);
$xls->set_column(0,5,20);
$xls->set_column(0,6,100);
$xls->set_column(0,7,20);
$xls->set_column(0,8,20);
$xls->set_column(0,9,20);
$xls->set_column(0,10,20);
    

//----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
         $colidx = 0;   
         $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d H:i", $sqlrow["FechaHoraInicioTurno"]))), $_FRM["FECHAHORA"]);$colidx++;         
         if ($sqlrow["FechaHoraFinTurno"] < strtotime('1970-01-02'))
         {
             $xls->write($rowidx, $colidx,"", $_FRM["TBLR"]);$colidx++;         
         }
         else
         {
           $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d H:i", $sqlrow["FechaHoraFinTurno"]))), $_FRM["FECHAHORA"]);$colidx++;         
         }
/* 03 */ $xls->write_string($rowidx, $colidx, $sqlrow["CódigoTurno"]                              , $_FRM['TBLR']);$colidx++;
/* 04 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreMaquina"]                            , $_FRM['TBLR']);$colidx++;
/* 05 */ $xls->write_string($rowidx, $colidx, $sqlrow["RutColaborador"]                           , $_FRM['TBLR']);$colidx++;
/* 06 */ $xls->write_string($rowidx, $colidx, $sqlrow["CodigoColaborador"]                        , $_FRM['TBLR']);$colidx++;
/* 07 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreColaborador"]                        , $_FRM['TBLR']);$colidx++;
         $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d H:i", $sqlrow["FechaHoraInicioColación"]))), $_FRM["FECHAHORA"]);$colidx++;         
         $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d H:i", $sqlrow["FechaFinInicioColacion"]))), $_FRM["FECHAHORA"]);$colidx++;         
         $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TotalTiempoColacion"]), $_FRM["DURACION"]);$colidx++;
/* 11 */ $xls->write_string($rowidx, $colidx, $sqlrow["Estado"]                                  , $_FRM['TBLR']);$colidx++;
         $rowidx++;
   }


      // $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("H:i", $sqlrow["fecha_inicio"]))), $_FRM["HORA"]);$colidx++;
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["hhmm"]), $_FRM["DURACION"]);$colidx++;





   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsConfiguracion($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
   /* 01 */   "Fecha-Hora Inicio Turno",
   /* 02 */   "Fecha-Hora Fin Turno ",
   /* 03 */   "Codigo Turno",
   /* 04 */   "Nombre Maquina",
   /* 05 */   "Rut Colaborador",
   /* 06 */   "Codigo Colaborador",
   /* 07 */   "Nombre Colaborador",
   /* 08 */   "Formato Actual",
   /* 09 */   "Formato a Configurar",
   /* 10 */   "Fecha Inicio Configuracion",
   /* 11 */   "Fecha Fin Configuracion",
   /* 12 */   "Total Minutos Configuración",
   /* 13 */   "Aplica",
   /* 14 */   "Estado",
   );
      
$xls->set_column(0,0,20);
$xls->set_column(0,1,20);
$xls->set_column(0,2,15);
$xls->set_column(0,3,100);
$xls->set_column(0,4,20);
$xls->set_column(0,5,20);
$xls->set_column(0,6,100);
$xls->set_column(0,7,20);
$xls->set_column(0,8,20);
$xls->set_column(0,9,20);
$xls->set_column(0,10,20);
$xls->set_column(0,11,20);
$xls->set_column(0,12,20);
$xls->set_column(0,13,20);

//----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
         $colidx = 0;   

/* 01 */ $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["FechaHoraInicioTurno"]))), $_FRM["FECHAHORA"]);$colidx++;
/* 02 */ $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["FechaHoraFinTurno"]))), $_FRM["FECHAHORA"]);$colidx++; 
/* 03 */ $xls->write_string($rowidx, $colidx, $sqlrow["CódigoTurno"]                              , $_FRM['TBLR']);$colidx++;
/* 04 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreMaquina"]                            , $_FRM['TBLR']);$colidx++;
/* 05 */ $xls->write_string($rowidx, $colidx, $sqlrow["RutColaborador"]                           , $_FRM['TBLR']);$colidx++;
/* 06 */ $xls->write_string($rowidx, $colidx, $sqlrow["CodigoColaborador"]                        , $_FRM['TBLR']);$colidx++;
/* 07 */ $xls->write_string($rowidx, $colidx, $sqlrow["NombreColaborador"]                        , $_FRM['TBLR']);$colidx++;
/* 08 */ $xls->write_string($rowidx, $colidx, $sqlrow["CambioDe"]                  , $_FRM['TBLR']);$colidx++;
/* 09 */ $xls->write_string($rowidx, $colidx, $sqlrow["CambioA"]                   , $_FRM['TBLR']);$colidx++;
/* 10 */ $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["FechaHoraInicioConfig"]))), $_FRM["FECHAHORA"]);$colidx++;
/* 11 */ $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["FechaHoraFinConfig"]))), $_FRM["FECHAHORA"]);$colidx++;
/* 12 */ $xls->write_number($rowidx, $colidx, getPrice($sqlrow["TotalTiempoConfig"])               , $_FRM['TBLR']);$colidx++;
/* 13 */ $xls->write_string($rowidx, $colidx, $sqlrow["Aplica"]                                    , $_FRM['TBLR']);$colidx++;
/* 14 */ $xls->write_string($rowidx, $colidx, $sqlrow["Estado"]                                    , $_FRM['TBLR']);$colidx++;
         $rowidx++;
   }


   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

//----------------------------------------------------------------------------------
function xls_createStatsDespachos($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_prodsolicscc";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
   $pdffile    = "{$filedir}{$filename}{$fileext}";

   //----------------------------------------------------------------------------------
   if ($handle = opendir($filedir))
   {
      while (false !== ($file = readdir($handle)))
         if ($file != "." && $file != ".." && strpos($file,"{$_SESSION["user_id"]}.{$doctype}.") !== false)
            unlink("{$filedir}{$file}");
      closedir($handle);
   }

   $res = $CON->select($datasql);

   //----------------------------------------------------------------------------------
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "ID",
      "Periodo",
      "Fecha",
      "N° Documento",
      "Tipo Documento",
      "Canal de Venta",
      "C.C. / N.V.",
      "Cliente",
      "Código",
      "Cantidad de Palett",
      "Cantidad de Cajas",
      "Medida Caja 1",
      "Medida Caja 2",
      "Cantidad",
      "Empresa de Transporte",
      "Patente",
      "Conductor",
      "Rut",
      "Hora Entrada",
      "Hora Salida",
      "Monto",
      "Rango",
      "Numero Sello",
      "Estado",
      "Tipo Ingreso",
      "Observación");
   $xls->set_column(0, 0, 10); /* id */
   $xls->set_column(0, 1, 15); /* Periodo */
   $xls->set_column(0, 2, 12); /* Fecha */
   $xls->set_column(0, 3, 12); /* Numero Documento */
   $xls->set_column(0, 4, 12); /* Tipo de Documento */
   $xls->set_column(0, 5, 12); /* Canal de Venta */
   $xls->set_column(0, 6, 12); /* Canal de Venta */
   $xls->set_column(0, 7, 100); /* Cliente */
   $xls->set_column(0, 8, 20); /* Codigo */
   $xls->set_column(0, 9, 12); /* Cantidad Pallets */
   $xls->set_column(0, 10, 12); /* Cantidad de Cajas */
   $xls->set_column(0, 11, 12); /* Medida caja 1 */
   $xls->set_column(0, 12, 12); /* Medida caja 2 */
   $xls->set_column(0, 13, 16); /* Cantidad */
   $xls->set_column(0, 14, 100); /* Empresa de Transposte */
   $xls->set_column(0, 15, 20); /* Patente */
   $xls->set_column(0, 16, 100); /* Conductor */
   $xls->set_column(0, 17, 20); /* Rut */
   $xls->set_column(0, 18, 15); /* Hora de Entrada */
   $xls->set_column(0, 19, 15); /* Hora de Salida */
   $xls->set_column(0, 20, 15); /* Monto */
   $xls->set_column(0, 21, 50); /* Rango */
   $xls->set_column(0, 22, 20); /* Sello */
   $xls->set_column(0, 23, 15); /* Estadop */
   $xls->set_column(0, 24, 15); /* Estadop */
   $xls->set_column(0, 25, 100 ); /* Observacion */ 

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["ID"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Periodo"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, date("d.m.Y",$sqlrow["Fecha"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Documento"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["TipoDocumento"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["CanaldeVenta"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["CCNV"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Cliente"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Codigo"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadPallets"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadCajas"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["MedidaCaja1"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["MedidaCaja2"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Cantidad"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["EmpresadeTransporte"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Patente"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Conductor"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["RUT"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["HoraEntrada"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["HoraSalida"], $_FRM["TBLR"]);$colidx++;

      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["monto"]), $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["rango"], $_FRM["TBLR"]);$colidx++;

      $xls->write_string($rowidx, $colidx, $sqlrow["Sello"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Estado"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Ingreso"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Observación"], $_FRM["TBLR"]);$colidx++;

      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}

function xls_createStatsDetenciones($CON)
{

   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "detencion";
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
      "Usuario",
      "Familia",
      "SubFamilia",
      "Código Maquina",
      "Nombre Máquina",
      "Almacen",
      "Código de Parada",
      "Motivo Detención",
      "Comentarios",
      "Clasificacion",
      "Fecha de Inico",
      "Hora de Inicio",
      "Fecha de Termino",
      "Hora de Termino",
      "Total Tiempo Detención");
      
   $xls->set_column(0, 0, 50); 
   $xls->set_column(0, 1, 50); 
   $xls->set_column(0, 2, 50); 
   $xls->set_column(0, 3, 10); 
   $xls->set_column(0, 4, 50); 
   $xls->set_column(0, 5, 10); 
   $xls->set_column(0, 6, 10); 
   $xls->set_column(0, 7, 50);
   $xls->set_column(0, 8, 250); 
   $xls->set_column(0, 9, 50); 
   $xls->set_column(0, 10, 15); 
   $xls->set_column(0, 11, 15);
   $xls->set_column(0, 12, 15);
   $xls->set_column(0, 13, 15);
   $xls->set_column(0, 14, 15); 


   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["usuario"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["familia"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["subfamilia"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["codigo_maquina"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["maquina"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["almacen"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["codigo_parada"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["motivo_parada"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["comentario_detencion"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["clasificacion_detencion"], $_FRM["TBLR"]);$colidx++;
      // $hora_decimal = date("H", $sqlrow["fecha_inicio"]) / 24 + date("i", $sqlrow["fecha_inicio"]) / 1440;
      // $xls->write($rowidx, $colidx, $hora_decimal, $_FRM["HORA"]);$colidx++;
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["fecha_inicio"]))), $_FRM["FECHA"]);$colidx++;
      $xls->write($rowidx, $colidx,(date("H", $sqlrow["fecha_inicio"]) / 24 + date("i", $sqlrow["fecha_inicio"]) / 1440), $_FRM["HORA"]);$colidx++;
      // $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("H:i", $sqlrow["fecha_inicio"]))), $_FRM["HORA"]);$colidx++;
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["fecha_termino"]))), $_FRM["FECHA"]);$colidx++;
      $xls->write($rowidx, $colidx,(date("H", $sqlrow["fecha_inicio"]) / 24 + date("i", $sqlrow["fecha_inicio"]) / 1440), $_FRM["HORA"]);$colidx++;
      // $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("H:i", $sqlrow["fecha_inicio"]))), $_FRM["HORA"]);$colidx++;
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["hhmm"]), $_FRM["DURACION"]);$colidx++;
      $rowidx++;
   }

   $rowidx++;

   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;      
   
}

function xls_createPresupuestos($CON)
{
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "detencion";
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array(
         "Rut",	
         "Razon Social",
         "Nombre",
         "Rubro",
         "Subrubro",
         "Canal",
         "Cartera de",
         "En Presupuesto",
         "Documento",
         "Numero",
         "Fecha Documento",
         "Atendido por",
         "C.C.",
         "SKU",
         "Producto",
         "Tipo Bolsa",
         "Material",
         "Impresión",
         "Color de Tela",
         "Gramaje",
         "Cantidad",
         "Precio",
         "Total Neto",
         "Fecha Ultimo Documento",
         "Fecha Ultima Cotización",
         "Cantidad de Documento Emitidas"); 

   $xls->set_column(0, 0, 15); 
   $xls->set_column(0, 1, 100); 
   $xls->set_column(0, 2, 100); 
   $xls->set_column(0, 3, 50); 
   $xls->set_column(0, 4, 50); 
   $xls->set_column(0, 5, 12); 
   $xls->set_column(0, 6, 12); 
   $xls->set_column(0, 7, 12); 
   $xls->set_column(0, 8, 12);
   $xls->set_column(0, 9, 12); 
   $xls->set_column(0, 10, 12); 
   $xls->set_column(0, 11, 100); 
   $xls->set_column(0, 12, 12); 
   $xls->set_column(0, 13, 12);
   $xls->set_column(0, 14, 100);
   $xls->set_column(0, 15, 20);
   $xls->set_column(0, 16, 12); 
   $xls->set_column(0, 17, 12); 
   $xls->set_column(0, 18, 50); 
   $xls->set_column(0, 19, 12); 
   $xls->set_column(0, 20, 12); 
   $xls->set_column(0, 21, 12); 
   $xls->set_column(0, 22, 12); 
   $xls->set_column(0, 23, 17); 
   $xls->set_column(0, 24, 17); 
   $xls->set_column(0, 25, 27); 

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;

   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["rutcliente"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["razonsocial"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["nombre"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["categoria"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["sub_cat_name"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["canal"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cartera"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_presupuesto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["documento"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Facturas"], $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["fechadefactura"]))), $_FRM["FECHA"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["vendedor"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Confirmacion"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["SKU"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Producto"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["tipodebolsa"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["material"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Impresion"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["colordetela"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["gramaje"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["precioneto"]), $_FRM["MONTO"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["cantidad"]), $_FRM["MONTO"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["totalneto"]), $_FRM["MONTO"]);$colidx++;
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["fechaultimafactura"]))), $_FRM["FECHA"]);$colidx++;
      $xls->write($rowidx, $colidx,timestampToExcelDate(strtotime(date("Y-m-d", $sqlrow["fechaultimacotizacion"]))), $_FRM["FECHA"]);$colidx++;
      $xls->write_number($rowidx, $colidx,getPrice($sqlrow["cantidaddefacturasemitidas"]), $_FRM["TBLR"]);$colidx++;         
      $rowidx++;
   }
   $rowidx++;
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;      
}
/* ------------------------------------------------------------------------------------------------------------- */
function xls_createStatsProduccion($CON)
{

   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "detencion";
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);
  
   //----------------------------------------------------------------------------------
         /* 1 */  $cols = Array("Nº CC",
         /* 2 */                "Rut Cliente",
         /* 3 */                "Cliente",
         /* 4 */                "Fecha Creación",
         /* 5 */                "Presupuesto",
         /* 6 */                "SKU",
         /* 7 */                "Cantidad",
         /* 8 */                "Monto Neto",
         /* 9 */                "Vendedor",
         /* 10 */               "Fecha Creacion C.C.",
         /* 11 */               "Fecha envío de pedido confirmado",
         /* 12 */               "Tiempo Transcurrido Hrs.",
         /* 13 */               "Fecha Inicio Produccion",
         /* 14 */               "Total Hora(s) desde el Cierre C.C. hasta Inicio Producción",
         /* 15 */               "Total Dia(s) desde el Cierre C.C. hasta Inicio Producción",
         /* 16 */               "Fecha Entrega 1",
         /* 17 */               "Cantidad Entrega 1",
         /* 18 */               "Tiempo de Entrega Dias 1",
         /* 19 */               "Fecha Entrega 2",
         /* 20 */               "Cantidad Entrega 2",
         /* 21 */               "Tiempo de Entrega Dias 2",
         /* 22 */               "Fecha Entrega 3",
         /* 23 */               "Cantidad Entrega 3",
         /* 24 */               "Tiempo de Entrega Dias 3",
         /* 25 */               "Fecha Entrega 4",
         /* 26 */               "Cantidad Entrega 4",
         /* 27 */               "Tiempo de Entrega Dias 4",
         /* 28 */               "Fecha Entrega 5",
         /* 29 */               "Cantidad Entrega 5",
         /* 30 */               "Tiempo de Entrega Dias 5",
         /* 31 */               "Fecha de Cierre C.C.",
         /* 32 */               "Fecha Solicitud de Clisé/Pelicula",
         /* 33 */               "Duración Dia(s)",
         /* 34 */               "Fecha recepción Montaje",
         /* 35 */               "Duración Dia(s)",
         /* 36 */               "Fecha Creacion OT",
         /* 37 */               "Impresora",
         /* 38 */               "Fecha Inicio Producción",
         /* 39 */               "Fecha Termino Producción",
         /* 40 */               "Duración Producción",
         /* 41 */               "Fecha inicio Rebobinadora",     
         /* 42 */               "Fecha Termino  Rebobinadora",
         /* 43 */               "Duración Rebobinadora",
         /* 44 */               "Fecha inicio Selladora",
         /* 45 */               "Fecha Termino Selladora",
         /* 46 */               "Duracion Selladora",
         /* 47 */               "Fecha Inicio Embalaje",
         /* 48 */               "Fecha Termino Embalaje",
         /* 49 */               "Duracion Embalaje",
         /* 50 */               "Fecha Cierre",
         /* 51 */               "Cantidad de Facturas",
         /* 52 */               "Fecha Facturación",
         /* 53 */               "Duración Facturacion",
         /* 54 */               "Cantidad de G/Despacho",
         /* 55 */               "Fecha de G/Despacho",
         /* 56 */               "Duración Despacho"
                ); 

   /* 1 */    $xls->set_column(0, 0, 15); 
   /* 2 */    $xls->set_column(0, 1, 15); 
   /* 3 */    $xls->set_column(0, 2, 50); 
   /* 4 */    $xls->set_column(0, 3, 15); 
   /* 5 */    $xls->set_column(0, 4, 15); 
   /* 6 */    $xls->set_column(0, 5, 15); 
   /* 7 */    $xls->set_column(0, 6, 15); 
   /* 8 */    $xls->set_column(0, 7, 15); 
   /* 9 */    $xls->set_column(0, 8, 50); 
   /* 10 */   $xls->set_column(0, 9, 20); 
   /* 11 */   $xls->set_column(0, 10, 30);
   /* 12 */   $xls->set_column(0, 11, 30); 
   /* 13 */   $xls->set_column(0, 12, 30); 
   /* 14 */   $xls->set_column(0, 13, 50); 
   /* 15 */   $xls->set_column(0, 14, 50); 
   /* 16 */   $xls->set_column(0, 15, 30);
   /* 17 */   $xls->set_column(0, 16, 30);
   /* 18 */   $xls->set_column(0, 17, 30); 
   /* 19 */   $xls->set_column(0, 18, 30); 
   /* 20 */   $xls->set_column(0, 19, 30); 
   /* 21 */   $xls->set_column(0, 20, 30); 
   /* 22 */   $xls->set_column(0, 21, 30); 
   /* 23 */   $xls->set_column(0, 22, 30); 
   /* 24 */   $xls->set_column(0, 23, 30); 
   /* 25 */   $xls->set_column(0, 24, 30); 
   /* 26 */   $xls->set_column(0, 25, 30); 
   /* 27 */   $xls->set_column(0, 26, 30); 
   /* 28 */   $xls->set_column(0, 27, 30); 
   /* 29 */   $xls->set_column(0, 28, 30); 
   /* 30 */   $xls->set_column(0, 29, 30); 
   /* 31 */   $xls->set_column(0, 30, 30); 
   /* 32 */   $xls->set_column(0, 31, 30); 
   /* 33 */   $xls->set_column(0, 32, 30); 
   /* 34 */   $xls->set_column(0, 33, 30); 
   /* 35 */   $xls->set_column(0, 34, 30); 
   /* 36 */   $xls->set_column(0, 35, 30); 
   /* 37 */   $xls->set_column(0, 36, 30); 
   /* 38 */   $xls->set_column(0, 37, 30); 
   /* 39 */   $xls->set_column(0, 38, 30); 
   /* 40 */   $xls->set_column(0, 39, 30); 
   /* 41 */   $xls->set_column(0, 40, 30); 
   /* 42 */   $xls->set_column(0, 41, 30); 
   /* 43 */   $xls->set_column(0, 42, 30); 
   /* 44 */   $xls->set_column(0, 43, 30); 
   /* 45 */   $xls->set_column(0, 44, 30); 
   /* 46 */   $xls->set_column(0, 45, 30); 
   /* 47 */   $xls->set_column(0, 46, 30); 
   /* 48 */   $xls->set_column(0, 47, 30); 
   /* 49 */   $xls->set_column(0, 48, 30); 
   /* 50 */   $xls->set_column(0, 49, 30); 
   /* 51 */   $xls->set_column(0, 50, 30); 
   /* 52 */   $xls->set_column(0, 51, 30); 
   /* 53 */   $xls->set_column(0, 52, 30); 
   /* 54 */   $xls->set_column(0, 53, 30); 
   /* 55 */   $xls->set_column(0, 54, 30); 
   /* 56 */   $xls->set_column(0, 55, 30); 

   //----------------------------------------------------------------------------------
   $rowidx = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);
   $rowidx++;
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
      $colidx = 0;
      $xls->write_string($rowidx, $colidx, $sqlrow["NumeroCC"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Rut Cliente"], $_FRM["TBLR"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["Cliente"], $_FRM["TBLR"]);$colidx++;

      $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["cust_crtdat"]), $_FRM["FECHA"]);$colidx++;
      $xls->write_string($rowidx, $colidx, $sqlrow["cust_presupuesto"], $_FRM["TBLR"]);$colidx++;


      $xls->write_string($rowidx, $colidx, $sqlrow["SKU"], $_FRM["TBLR"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["Cantidad"]), $_FRM["MONTO"]);$colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["req_total_netto"]), $_FRM["MONTO"]);$colidx++;
            

      $xls->write_string($rowidx, $colidx, $sqlrow["Vendedor"], $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["FechaCreacion"]), $_FRM["FECHAHORA"]);$colidx++;
      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["InicioFabricacion"]), $_FRM["FECHAHORA"]);$colidx++;
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["TiempoTranscurrido"]), $_FRM["DURACION"]);$colidx++;

      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["InicioProduccion"]), $_FRM["FECHAHORA"]);$colidx++;
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["horas_ejecutivo"]), $_FRM["DURACION"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["dias_ejecutivo"], $_FRM["TBLR"]);$colidx++;

      if (!empty($sqlrow["FechaEntrega1"])) {
         $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["FechaEntrega1"]), $_FRM["FECHA"]);
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);
      }
      $colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadEntrega1"]), $_FRM["MONTO"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["DuracionDiasHrs1"], $_FRM["TBLR"]);$colidx++;

      if (!empty($sqlrow["FechaEntrega2"])) {
         $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["FechaEntrega2"]), $_FRM["FECHA"]);
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);
      }
      $colidx++;      
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadEntrega2"]), $_FRM["MONTO"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["DuracionDiasHrs2"], $_FRM["TBLR"]);$colidx++;
      if (!empty($sqlrow["FechaEntrega3"])) {
         $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["FechaEntrega3"]), $_FRM["FECHA"]);
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);
      }
      $colidx++;
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadEntrega3"]), $_FRM["MONTO"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["DuracionDiasHrs3"], $_FRM["TBLR"]);$colidx++;
      if (!empty($sqlrow["FechaEntrega4"])) {
         $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["FechaEntrega4"]), $_FRM["FECHA"]);
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);
      }
      $colidx++;      
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadEntrega4"]), $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["DuracionDiasHrs4"], $_FRM["TBLR"]);$colidx++;
      if (!empty($sqlrow["FechaEntrega5"])) {
         $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["FechaEntrega5"]), $_FRM["FECHA"]);
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);
      }
      $colidx++;      
      $xls->write_number($rowidx, $colidx, getPrice($sqlrow["CantidadEntrega5"]), $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["DuracionDiasHrs5"], $_FRM["TBLR"]);$colidx++;

      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["InicioFabricacion"]), $_FRM["FECHAHORA"]);$colidx++;

      $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["SolicituddeClisePelicula"]), $_FRM["FECHA"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["DuracionMontaje"], $_FRM["TBLR"]);$colidx++;

      $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["Montaje"]), $_FRM["FECHA"]);$colidx++;
      $xls->write($rowidx, $colidx, $sqlrow["DuracionMontaje"], $_FRM["TBLR"]);$colidx++;
      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["CreacionOT"]), $_FRM["FECHAHORA"]);$colidx++;


      $xls->write_string($rowidx, $colidx, $sqlrow["Impresora"], $_FRM["TBLR"]);$colidx++;
          
      if (!empty($sqlrow["InicioProduccion"])) 
      {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["InicioProduccion"]), $_FRM["FECHAHORA"]);$colidx++;
      } else 
      {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHAHORA"]);$colidx++;
      }      
      if (!empty($sqlrow["FinProducción"])) 
      {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["FinProducción"]), $_FRM["FECHAHORA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHAHORA"]);$colidx++;
      }
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["DuracionInicio"]), $_FRM["DURACION"]);$colidx++;


      if (!empty($sqlrow["i_Rebobinadora"])) {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["i_Rebobinadora"]), $_FRM["FECHAHORA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHAHORA"]);$colidx++;
      }      
      if (!empty($sqlrow["Rebobinadora"])) {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["Rebobinadora"]), $_FRM["FECHAHORA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHAHORA"]);$colidx++;
      }      
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["horas_rebobinadora"]), $_FRM["DURACION"]);$colidx++;


      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["i_Selladora"]), $_FRM["FECHA"]);$colidx++;
      if (!empty($sqlrow["Selladora"])) {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["Selladora"]), $_FRM["FECHAHORA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHAHORA"]);$colidx++;
      }      
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["horas_selladora"]), $_FRM["DURACION"]);$colidx++;   


      $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["i_Embalaje"]), $_FRM["FECHA"]);$colidx++;
      if (!empty($sqlrow["Embalaje"])) {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["Embalaje"]), $_FRM["FECHAHORA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHAHORA"]);$colidx++;
      }
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["horas_embalaje"]), $_FRM["DURACION"]);$colidx++;


      if (!empty($sqlrow["Cierre"])) {
         $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["Cierre"]), $_FRM["FECHAHORA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHAHORA"]);;$colidx++;
      }
      $xls->write_number($rowidx, $colidx, $sqlrow["K_facturas"], $_FRM["TBLR"]);$colidx++;
      
      if (!empty($sqlrow["Facturacion"])) {
         $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["Facturacion"]), $_FRM["FECHA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);$colidx++;
      }      
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["Duracion1"]), $_FRM["DURACION"]);$colidx++;

      $xls->write_number($rowidx, $colidx, $sqlrow["K_guias"], $_FRM["TBLR"]);$colidx++;

      if (!empty($sqlrow["Despacho"])) {
         $xls->write($rowidx, $colidx, fecha_sin_hora_excel($sqlrow["Despacho"]), $_FRM["FECHA"]);$colidx++;
      } else {
         $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);$colidx++;
      }       
      $xls->write($rowidx, $colidx, duracionStrAExcel($sqlrow["Duracion2"]), $_FRM["DURACION"]);$colidx++;
      $rowidx++;
   }
   $rowidx++;
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;     
}

//---------------------------------------------------------------------------------------------------------------------------------
function timestampToExcelDate($timestamp, $timezone = 'America/Santiago') {
    // Truncar segundos: redondear hacia abajo al minuto más cercano
    $timestamp = $timestamp - ($timestamp % 60);

    $dt = new DateTime("@$timestamp"); // UTC base
    $tz = new DateTimeZone($timezone);
    $dt->setTimezone($tz);

    // Obtener el desfase horario en segundos (ej: -10800 para UTC-3)
    $offsetSeconds = $tz->getOffset($dt);

    // Ajustar el timestamp
    $adjustedTimestamp = $timestamp + $offsetSeconds;

    // Convertir a formato Excel
    $excelDate = ($adjustedTimestamp / 86400) + 25569;

    return $excelDate;
}
//---------------------------------------------------------------------------------------------------------------------------------

function tiempoTextoAExcel($tiempoStr) 
{
    list($h, $m, $s) = explode(':', $tiempoStr);
    $segundosTotales = ($h * 3600) + ($m * 60) + $s;
    return $segundosTotales / 86400;
}

//---------------------------------------------------------------------------------------------------------------------------------
function fechaStrAExcel($fechaStr) 
{
    if (empty($fechaStr)) {
        return timestampToExcelDate(time()); // fecha actual si está vacío
    }

    $formatos = [
        "d/m/Y H:i:s",
        "d/m/Y H:i",
        "d/m/Y"
    ];

    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $fechaStr);
        if ($dt !== false) {
            return timestampToExcelDate($dt->getTimestamp());
        }
    }

    // Si ningún formato coincide, usar fecha actual
    return timestampToExcelDate(time());
}

function horaStrAExcel($horaStr)
{
    if (empty($horaStr)) return 0;

    $dt = DateTime::createFromFormat("H:i", $horaStr);
    if (!$dt) return 0;

    $segundos = ($dt->format("H") * 3600) + ($dt->format("i") * 60);
    return $segundos / 86400; // Excel interpreta 1 día = 1.0
}

function duracionStrAExcel($horaStr)
{
    if (empty($horaStr)) return 0;

    // Validar formato HH:MM o HH:MM:SS con horas ilimitadas
    if (!preg_match('/^\d+:\d{2}(:\d{2})?$/', $horaStr)) return 0;

    $partes = explode(":", $horaStr);
    $horas = (int)$partes[0];
    $minutos = (int)$partes[1];
    $segundos = isset($partes[2]) ? (int)$partes[2] : 0;

    $totalSegundos = ($horas * 3600) + ($minutos * 60) + $segundos;
    return $totalSegundos / 86400; // Excel: 1 día = 1.0
}

function fecha_sin_hora_excel($timestamp) {
    if (!is_numeric($timestamp) || $timestamp <= 0) {
        return null;
    }

    // Convertir a medianoche
    $fecha_sin_hora = strtotime(date("Y-m-d", $timestamp));

    // Convertir a número Excel sin fracción horaria
    return floor(($fecha_sin_hora / 86400) + 25569);
}

function xls_createStatsPropuestas($CON)
{
   global $_LANG;
   global $_sesmodulename;

   //----------------------------------------------------------------------------------
   $doctype    = "stats_venta_encres";
   $currtme    = time();
   $hash       = md5(microtime());
   $filedir    = "./docs.print/";
   $filename   = "{$_SESSION["user_id"]}.{$doctype}.{$hash}";
   $fileext    = ".xls";
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
   require_once('./libs/thirdparty/biffwriter.extended/Workbook.php');
   require_once('./libs/thirdparty/biffwriter.extended/Worksheet.php');

   //----------------------------------------------------------------------------------
   $workbook = new Workbook($pdffile);
   include("./libs/thirdparty/biffwriter.extended/formate.inc");
   $xls =& $workbook->add_worksheet("Tabla1");
   $xls->set_landscape();
   $xls->set_margins(0.5);
   $xls->hide_gridlines();
   $xls->set_print_scale(90);

   //----------------------------------------------------------------------------------
   $cols = Array("Código",
                 "Cliente",
                 "Creado por",
                 "Estado",
                 "Codigo Propuesta",
                 "Referencia Pedido",
                 "Fecha Propuesta",
                 "Hora Propuesta",
                 "Codigo Version",
                 "Diseñador que sube Version",
                 "Fecha que Sube Version",
                 "Hora que Sube Version",
                 "Fecha de Finaliza Version",
                 "Hora de Finaliza Version",
                 "Fecha Termino",
                 "Hora Termino"
               );

   $xls->set_column(0,0,15);
   $xls->set_column(0,1,100);
   $xls->set_column(0,2,50);
   $xls->set_column(0,3,15);
   $xls->set_column(0,4,20);
   $xls->set_column(0,5,100);
   $xls->set_column(0,6,15);
   $xls->set_column(0,7,15);
   $xls->set_column(0,8,20);
   $xls->set_column(0,9,100);
   $xls->set_column(0,10,15);
   $xls->set_column(0,11,15);
   $xls->set_column(0,12,15);
   $xls->set_column(0,13,15);
   $xls->set_column(0,14,15);
   $xls->set_column(0,15,15);
   
   //----------------------------------------------------------------------------------
   $rowidx = 0;
   $counter = 0;
   for($y = 0; $y < count($cols); $y++)
      $xls->write_string($rowidx, $y, $cols[$y], $_FRM["TBLH"]);

   $rowidx++;
   
   foreach($_SESSION["STATS"][$_sesmodulename]["DATA"] AS $sqlrow)
   {
         $colidx = 0;   
         $xls->write_string($rowidx, $colidx, $sqlrow["pro_dis_codigo"]          , $_FRM['TBLR']);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["cust_name"]               , $_FRM['TBLR']);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["Usuario"]                 , $_FRM['TBLR']);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["Estado"]                  , $_FRM['TBLR']);$colidx++;         
         $xls->write_string($rowidx, $colidx, $sqlrow["Codigo Propuesta"]        , $_FRM['TBLR']);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["Descripcion Items"]       , $_FRM['TBLR']);$colidx++;

         if (!empty($sqlrow["Fecha Creación Propuesta"])) 
         {
            $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["Fecha Creación Propuesta"]), $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx,(date("H", $sqlrow["Fecha Creación Propuesta"]) / 24 + date("i", $sqlrow["Fecha Creación Propuesta"]) / 1440), $_FRM["HORA"]);$colidx++;
                   
         } 
         else 
         {
            $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx, "", $_FRM["HORA"]);$colidx++;            
         } 

         $xls->write_string($rowidx, $colidx, $sqlrow["Codigo Version"]          , $_FRM['TBLR']);$colidx++;
         $xls->write_string($rowidx, $colidx, $sqlrow["FinalizadoVersion"]       , $_FRM['TBLR']);$colidx++;

         if (!empty($sqlrow["fec_sube_verison"])) 
         {
            $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["fec_sube_verison"]), $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx,(date("H", $sqlrow["fec_sube_verison"]) / 24 + date("i", $sqlrow["fec_sube_verison"]) / 1440), $_FRM["HORA"]);$colidx++;
         }
         else 
         {
            $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx, "", $_FRM["HORA"]);$colidx++;            
         } 

         if (!empty($sqlrow["fec_fin_ver"])) 
         {
            $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["fec_fin_ver"]), $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx,(date("H", $sqlrow["fec_fin_ver"]) / 24 + date("i", $sqlrow["fec_fin_ver"]) / 1440), $_FRM["HORA"]);$colidx++;
         }
         else 
         {
            $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx, "", $_FRM["HORA"]);$colidx++;            
         } 

         if (!empty($sqlrow["fec_actualizacion"])) 
         {
            $xls->write($rowidx, $colidx, timestampToExcelDate($sqlrow["fec_actualizacion"]), $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx,(date("H", $sqlrow["fec_actualizacion"]) / 24 + date("i", $sqlrow["fec_actualizacion"]) / 1440), $_FRM["HORA"]);$colidx++;
         }
         else 
         {
            $xls->write($rowidx, $colidx, "", $_FRM["FECHA"]);$colidx++;
            $xls->write($rowidx, $colidx, "", $_FRM["HORA"]);$colidx++;            
         } 
         $rowidx++;
   }

   $rowidx++;
   //----------------------------------------------------------------------------------
   $workbook->close();
   return $filename;
}
