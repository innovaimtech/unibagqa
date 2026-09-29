<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2011 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

error_reporting(0);
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
require_once("../../../libs/lang/{$_SESSION["_CONF"]["conf_lang_filename"]}");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

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
         t1.id = {$_REQUEST["id"]}";
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
$sql = " select t2.*, t3.item_title, t3.item_number_prod
         from stockchanges_items t2
         LEFT OUTER JOIN item t3       ON t2.item_id = t3.id
         where
         t2.stk_id      = {$_REQUEST["id"]} and
         t2.item_type   = 'item'
         UNION ALL
         select t2.*, t3.item_title, t3.item_number_prod
         from stockchanges_items t2
         LEFT OUTER JOIN itemlist t3   ON t2.item_id = t3.id
         where
         t2.stk_id      = {$_REQUEST["id"]} and
         t2.item_type   = 'itemlist'
         order by 3 asc";
$posdata = $CON->select($sql);

$pdfname = "Ajuste-de-stock-".$headdata["stk_num"].".pdf";

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
                   "X1"  => Array("width" => "70"),
                   "X2"  => Array("width" => "5"),
                   "X3"  => Array("width" => "215"),
                   "X4"  => Array("width" => "70"),
                   "X5"  => Array("width" => "5"),
                   "X6"  => Array("width" => "185")));
$xc = 0;
$data[$xc]["X1"] = "<b>Numero</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["stk_num"];
$data[$xc]["X4"] = "<b>Fecha</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = date('d.m.Y',$headdata["stk_bookdate"]);
$xc++;
$data[$xc]["X1"] = "<b>Empresa</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $company[0]["company_short"];
$data[$xc]["X4"] = "<b>Sucursal</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $shop[0]["shop_name"];
$xc++;
$data[$xc]["X1"] = "<b>Motivo</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["stkis_title"];
$data[$xc]["X4"] = "<b>Estado</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = getShipmentStatus($headdata["stk_status"], false);
$xc++;
$data[$xc]["X1"] = "<b>Creado por</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["crt_firstname"]." ".$headdata["crt_lastname"];
$data[$xc]["X4"] = "<b>Creado</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = date('d.m.Y',$headdata["stk_crtdat"]);
$xc++;
$data[$xc]["X1"] = "<b>Cambiado por</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["upd_firstname"]." ".$headdata["upd_lastname"];
$data[$xc]["X4"] = "<b>Cambiado</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = date('d.m.Y',$headdata["stk_upddat"]);
$xc++;
if((int)$headdata["stk_isventainterna"])
{
   $data[$xc]["X1"] = "<b>Venta interna</b>";
   $data[$xc]["X2"] = ":";
   $data[$xc]["X3"] = "SI";
   $data[$xc]["X4"] = "<b>Pagado</b>";
   $data[$xc]["X5"] = ":";
   if((int)$headdata["stk_ispayed"])
      $data[$xc]["X6"] = "SI";
   else
      $data[$xc]["X6"] = "NO";
   $xc++;
   $data[$xc]["X1"] = "<b>Cliente</b>";
   $data[$xc]["X2"] = ":";
   $data[$xc]["X3"] = $headdata["cust_name"];
   $data[$xc]["X4"] = "<b>Usuario</b>";
   $data[$xc]["X5"] = ":";
   $data[$xc]["X6"] = $headdata["user_firstname"]." ".$headdata["user_lastname"];
   $xc++;
   $data[$xc]["X1"] = "<b>C.I.</b>";
   $data[$xc]["X2"] = ":";
   $data[$xc]["X3"] = $headdata["stk_cinumber"];
   $data[$xc]["X4"] = "<b>Número Int.</b>";
   $data[$xc]["X5"] = ":";
   $data[$xc]["X6"] = $headdata["stk_vintnumber"];
}

$pdf->ezTable($data,$type,$dummy,$attr);
$pdf = doc_linedraw($pdf, 25, 555, 1);
unset($data);

//----------------------------------------------------------------------------------
$pdf->ezText("", 12);
$pdf->ezText("<b>Artículos</b>", 12);

//-------------------------------------------------------------------------------
$attr = Array  ("showHeadings" => 1, "shaded" => 1, "xpos" => "left", 'lineCol' => Array(0.50,0.50,0.50),
                "showLines"    => 2, "rowGap" => 2, "colGap" => 2, "titleFontSize" => 8,
                "fontSize"     => 8, "shadeCol" => Array(0.95,0.95,0.95), "cols" => Array
                (
                   "Número"                          => Array("width" => "100", "justification" => "left"),
                   "Articulo"                        => Array("width" => "250", "justification" => "left"),
                   "Bodega"                          => Array("width" => "125", "justification" => "left"),
                   "Cantidad"                        => Array("width" => "75", "justification" => "right")
                )
               );

//-------------------------------------------------------------------------------
if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
{
   $attr = Array  ("showHeadings" => 1, "shaded" => 1, "xpos" => "left", 'lineCol' => Array(0.50,0.50,0.50),
                   "showLines"    => 2, "rowGap" => 2, "colGap" => 2, "titleFontSize" => 8,
                   "fontSize"     => 8, "shadeCol" => Array(0.95,0.95,0.95), "cols" => Array
                   (
                      "Número"                          => Array("width" => "60", "justification" => "left"),
                      "Articulo"                        => Array("width" => "235", "justification" => "left"),
                      "Bodega"                          => Array("width" => "100", "justification" => "left"),
                      "Cantidad"                        => Array("width" => "45", "justification" => "center"),
                      "Precio/Neto"                     => Array("width" => "55", "justification" => "right"),
                      "Precio/Total"                    => Array("width" => "55", "justification" => "right")
                   )
                  );
}

$gesnetto = 0;
$rowcount = count($posdata);
for($x = 0; $x < $rowcount; $x++)
{
   $desc       = trim(addslashes($posdata[$x]["item_title"]));
   $unitdesc   = getItemUnitDesc($CON, $posdata[$x]["item_id"], $posdata[$x]["item_type"]);

   $data[$x]["Número"]   = $posdata[$x]["item_number_prod"];
   $data[$x]["Articulo"] = $desc." (".$unitdesc.")";

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
         //$currstock = getItemShopStorehouseCurrentStock($CON, $headdata["stk_shopid"], $itemstid, $posdata[$x]["item_id"], $posdata[$x]["item_type"], true);
         if($posdata[$x]["item_st_id"] == $itemstid)
            $data[$x]["Bodega"] = $itemsts[$itemstid];
      }
   }

   $data[$x]["Cantidad"] = printPrice($posdata[$x]["item_amount"],2);
   if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
   {
      $data[$x]["Precio/Neto"] = printPrice($posdata[$x]["item_sellprice_brutto"]);
      $data[$x]["Precio/Total"] = printPrice($posdata[$x]["item_amount"] * $posdata[$x]["item_sellprice_brutto"]);
   }

   $gesnetto += $posdata[$x]["item_costprice_netto"] * $posdata[$x]["item_amount"];
   $gesbruto += $posdata[$x]["item_sellprice_brutto"];
}
$pdf->ezText(" ", 12);
$pdf->ezTable($data,$type,$dummy,$attr);
$pdf->ezText(" ", 12);
if((int)$headdata["stk_isventainterna"] || $headdata["stk_issueid"] == 3 || $headdata["stk_issueid"] == 4)
{
   unset($data);
   $attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left", 'lineCol' => Array(0.50,0.50,0.50),
                   "showLines"    => 0, "rowGap" => 2, "colGap" => 2, "titleFontSize" => 8,
                   "fontSize"     => 8, "shadeCol" => Array(0.95,0.95,0.95), "cols" => Array
                   (
                      "X1" => Array("width" => "495", "justification" => "right"),
                      "X2" => Array("width" => "55", "justification" => "right")
                   )
                  );
   if($headdata["stk_discount_perc"] > 0.00 || $headdata["stk_discount_amt"] > 0.00)
   {
      $data[$x]["X1"] = "SUBTOTAL";
      $data[$x]["X2"] = printPrice($headdata["stk_total_netto"] + $headdata["stk_discount_amount_netto"]);
      $x++;
      if($headdata["stk_discount_perc"] > 0.00)
      {
         $data[$x]["X1"] = "% DESCUENTO";
         $data[$x]["X2"] = printPrice($headdata["stk_discount_perc"],2);
         $x++;
      }
      if($headdata["stk_discount_amt"] > 0.00)
      {
         $data[$x]["X1"] = "$ DESCUENTO";
         $data[$x]["X2"] = printPrice($headdata["stk_discount_amt"],2);
         $x++;
      }
   }
   $data[$x]["X1"] = "<b>TOTAL</b>";
   $data[$x]["X2"] = "<b>".printPrice($headdata["stk_total_netto"])."</b>";
   $x++;
   $pdf->ezTable($data,$type,$dummy,$attr);
}


$pdf->ezText(" ", 12);
$pdf->ezText($headdata["stk_annotation"], 10);
unset($data);

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