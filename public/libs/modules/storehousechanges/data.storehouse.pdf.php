<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

require_once("../../../libs/thirdparty/pdfClassesAndFonts/class.ezpdf.php");
//----------------------------------------------------------------------------------
require_once("../../../libs/classes/menu.php");
require_once("../../../libs/classes/page.php");
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.pdf.php");

//----------------------------------------------------------------------------------
session_start();

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
unset($_SESSION["_CONF"]);

$sql = " select *
         from config_system";
$conf = $CON->select($sql);
$conf = $conf[0];
foreach(array_keys($conf) AS $ckey)
   $_SESSION["_CONF"][$ckey] = trim($conf[$ckey]);

$sql = " select lang_filename
         from config_lang
         where
         id = {$conf["conf_lang"]}";
$lang = $CON->select($sql);
$_SESSION["_CONF"]["conf_lang_filename"] = $lang[0]["lang_filename"];

$_SESSION["_CONF"]["conf_shop_lang"] = Array();
$sql = " select id
         from config_lang
         where
         lang_shop_active = 1";
$shoplangs = $CON->select($sql);
foreach($shoplangs AS $shoplang)
   array_push($_SESSION["_CONF"]["conf_shop_lang"], $shoplang["id"]);

require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");

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
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         id = {$headdata["strc_company_id"]}";
$company = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select t2.*, t3.item_title, t3.item_number_prod
         from storehousechanges_items t2
         LEFT OUTER JOIN item t3 ON t2.item_id = t3.id
         where
         t2.strc_id     = {$_REQUEST["id"]} and
         t2.item_type   = 'item'
         UNION ALL
         select t2.*, t3.item_title, t3.item_number_prod
         from storehousechanges_items t2
         LEFT OUTER JOIN itemlist t3 ON t2.item_id = t3.id
         where
         t2.strc_id     = {$_REQUEST["id"]} and
         t2.item_type   = 'itemlist'
         order by 3 asc";
$posdata = $CON->select($sql);

//----------------------------------------------------------------------------------
$pdfname = "Traspaso-".$headdata["strc_number"].".pdf";
if($_REQUEST["from"] == "bodegero")
   $pdfname = "Bodegero-".$headdata["strc_number"].".pdf";

header('Content-Type: application/pdf;');
header("Content-Disposition: attachment; filename={$pdfname}");

//-------------------------------------------------------------------------------
$pdf = new Cezpdf("LETTER");
$pdf->selectFont("../../../libs/thirdparty/pdfClassesAndFonts/fonts/Helvetica.afm");
$pdf->ezSetMargins(120, 35, 30, 35);


unset($data);
$pdf = doc_linedraw($pdf, 25, 555, 1);
$attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                "fontSize"     => 10, "cols" => Array (
                   "X1"  => Array("width" => "80"),
                   "X2"  => Array("width" => "5"),
                   "X3"  => Array("width" => "190"),
                   "X4"  => Array("width" => "85"),
                   "X5"  => Array("width" => "5"),
                   "X6"  => Array("width" => "185")));
$xc = 0;
$data[$xc]["X1"] = "<b>Numero</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["strc_number"];

$data[$xc]["X4"] = "<b></b> ";
$data[$xc]["X5"] = "";
$data[$xc]["X6"] = $headdata["cust_name"];
$xc++;
$data[$xc]["X1"] = "<b>Empresa origen</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["company_short"];
$data[$xc]["X4"] = "<b>Empresa destino</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["company_dest_name"];
$xc++;
$data[$xc]["X1"] = "<b>Sucursal origen</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["shop_name"];
$data[$xc]["X4"] = "<b>Sucursal destino</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["shop_dest_name"];
$xc++;
$data[$xc]["X1"] = "<b>Fecha</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = date('d.m.Y',$headdata["strc_date"]);
$data[$xc]["X4"] = "<b>Estado</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = getShipmentStatus($headdata["strc_status"], false);
$xc++;
if((int)$headdata["strc_shopsent"])
{
   $data[$xc]["X1"] = "<b>Número Guia</b>";
   $data[$xc]["X2"] = ":";
   $data[$xc]["X3"] = $headdata["strc_isshoporder_dlvnumber"];
   $data[$xc]["X4"] = "<b>Fecha Guia</b>";
   $data[$xc]["X5"] = ":";
   $data[$xc]["X6"] = "";
   if((int)$headdata["strc_isshoporder_dlvdate"])
   {
      $data[$xc]["X6"] = date('d.m.Y',$headdata["strc_isshoporder_dlvdate"]);
   }  
   $xc++;
}

$data[$xc]["X1"] = "<b>Creado por</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["crt_firstname"]." ".$headdata["crt_lastname"];
$data[$xc]["X4"] = "<b>Creado</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = displayDate($headdata["strc_crtdat"]);
$xc++;
$data[$xc]["X1"] = "<b>Cambiado por</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["upd_firstname"]." ".$headdata["upd_lastname"];
$data[$xc]["X4"] = "<b>Cambiado</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = displayDate($headdata["strc_upddat"]);
$xc++;

$pdf->ezTable($data,$type,$dummy,$attr);
$pdf = doc_linedraw($pdf, 25, 555, 1);
unset($data);

//----------------------------------------------------------------------------------
$pdf->ezText(" ", 12);
$pdf->ezText("<b>Traspaso</b>", 12);

//-------------------------------------------------------------------------------
if((int)$headdata["strc_shopsent"] && !(int)$headdata["strc_isshoptraspaso"])
{
   $attr = Array  ("showHeadings" => 1, "shaded" => 1, "xpos" => "left", 'lineCol' => Array(0.50,0.50,0.50),
                   "showLines"    => 2, "rowGap" => 2, "colGap" => 2, "titleFontSize" => 8,
                   "fontSize"     => 8, "shadeCol" => Array(0.95,0.95,0.95), "cols" => Array
                   (
                      "Número"         => Array("width" => "85", "justification" => "left"),
                      "Articulo"       => Array("width" => "215", "justification" => "left"),
                      "Pedido"         => Array("width" => "45", "justification" => "center"),
                      "Entrega"        => Array("width" => "45", "justification" => "center"),
                      "Bodega origen"  => Array("width" => "80", "justification" => "left"),
                      "Bodega destino" => Array("width" => "80", "justification" => "left")
                   ) );
}
else
{
   $attr = Array  ("showHeadings" => 1, "shaded" => 1, "xpos" => "left", 'lineCol' => Array(0.50,0.50,0.50),
                   "showLines"    => 2, "rowGap" => 2, "colGap" => 2, "titleFontSize" => 8,
                   "fontSize"     => 8, "shadeCol" => Array(0.95,0.95,0.95), "cols" => Array
                   (
                      "Número"         => Array("width" => "100", "justification" => "left"),
                      "Articulo"       => Array("width" => "230", "justification" => "left"),
                      "Cantidad"       => Array("width" => "60", "justification" => "center"),
                      "Bodega origen"  => Array("width" => "80", "justification" => "left"),
                      "Bodega destino" => Array("width" => "80", "justification" => "left")
                   ) );
}

$rowcount = count($posdata);
for($x = 0; $x < $rowcount; $x++)
{
   $unitdesc   = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);

   $data[$x]["Número"]   = $posdata[$x]["item_number_prod"];
   $data[$x]["Articulo"] = $posdata[$x]["item_title"]." (".$unitdesc.")";
   if((int)$headdata["strc_shopsent"] && !(int)$headdata["strc_isshoptraspaso"])
   {
      $data[$x]["Pedido"]  = printPrice($posdata[$x]["item_shoporder_amt"], 2);
      $data[$x]["Entrega"] = printPrice($posdata[$x]["item_amount"], 2);
   }
   else
      $data[$x]["Cantidad"] = printPrice($posdata[$x]["item_amount"], 2);
   
   unset($itemsts);
   $currstock = 0;
   if($posdata[$x]["item_type"] == "item")
      $itemsts = getItemStorehouses($CON, $headdata["strc_shop_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
   else
   {
      $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
      $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_id"], $itemlistpos[0]["item_id"], "item");
   }

   $data[$x]["Bodega origen"] = $itemsts[$posdata[$x]["item_st_id"]];
   unset($itemsts);
   $currstock = 0;
   
   if($posdata[$x]["item_type"] == "item")
      $itemsts = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $posdata[$x]["item_id"], $posdata[$x]["item_type"]);
   else
   {
      $itemlistpos = getItemListContent($CON, $posdata[$x]["item_id"]);
      $itemsts     = getItemStorehouses($CON, $headdata["strc_shop_dest_id"], $itemlistpos[0]["item_id"], "item");
   }
   /*
   if(count($itemsts))
   {
      foreach(array_keys($itemsts) AS $itemstid)
      {
         if($itemstid == $posdata[$x]["item_st_dest_id"])
         {
            //$currstock = getItemShopStorehouseCurrentStock($CON, $headdata["strc_shop_dest_id"], $itemstid, (int)$posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
            
         }
      }
   }
   */
   $data[$x]["Bodega destino"]   = $itemsts[$posdata[$x]["item_st_dest_id"]];

   $gesnetto += $posdata[$x]["item_costprice_netto_total"];
}

$pdf->ezText(" ", 12);
$pdf->ezTable($data,$type,$dummy,$attr);
$pdf->ezText(" ", 12);
$pdf->ezText(" ", 12);
$pdf->ezText($headdata["strc_desc"], 12);
unset($data);

//-------------------------------------------------------------------------------
if((int)$headdata["strc_shopsent"])
{
   $y = $pdf->ezText(" ", 12);
   $pdf->setStrokeColor(0,0,0);
   $pdf->line(30, $y -3, 250, $y -3);
   $pdf->ezText(" ", 4);
   $pdf->ezText("Recibido por: ", 10);
}

//----------------------------------------------------------------------------------
$pagearr    = $pdf->ezPages;
$pcounter   = 0;

//----------------------------------------------------------------------------------
foreach($pagearr AS $pageid)
{
   $pdf->reopenObject($pageid);
   $pdf->ezSetMargins(0, 0, 25, 10);
   $pdf->ezSetY(780);

   if($company[0]["company_img_sell"] != "" && file_exists("../../../images/companies/{$company[0]["company_img_sell"]}"))
   {
      $pdf->ezImage("../../../images/companies/s{$company[0]["company_img_sell"]}", 0, 320, 'none', 'left');
      $pdf->ezSetY(780);
      $attr = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 12, "xOrientation" => "left",
                      "xPos" => 600, "showLines" => 0, "rowGap" => 0, "colGap" => 0,
                      "cols" => Array ("x1" => Array("width" => "100", "justification" => "center")));

      $data[0]["x1"] = "<b>PAGINA ".($pcounter +1)." / ".count($pagearr)."</b>\n";
      $pdf->ezTable($data,$type,$dummy,$attr);
      unset($data);
   }
}

//----------------------------------------------------------------------------------
echo $pdf->output();
?>