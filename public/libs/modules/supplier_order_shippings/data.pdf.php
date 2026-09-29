<?php
//----------------------------------------------------------------------------------
// Author:        1BIT LTDA
// Copyright:     2020 by 1BIT LTDA. All Rights Reserved.
// Any unauthorized redistribution, reselling, modifying or reproduction of part
// or all of the contents in any form is strictly prohibited.
//----------------------------------------------------------------------------------

error_reporting(0);

//----------------------------------------------------------------------------------
session_start();

require_once("../../../libs/thirdparty/pdfClassesAndFonts/class.ezpdf.php");

//----------------------------------------------------------------------------------
require_once("../../../libs/classes/mysql.php");
require_once("../../../libs/config.php");
require_once("../../../libs/functions.php");
require_once("../../../libs/functions.pdf.php");

//----------------------------------------------------------------------------------
$CON = new CMYSQL($_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["NAME"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["HOST"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["USER"],
                  $_CONFIG[$_CONFIG["_MODUS"]]["DATABASE"]["PASS"]);
$CON->connect();

//----------------------------------------------------------------------------------
$sql = " select t1.*, t3.company_short, t4.shop_name, 
                t5.user_firstname 'upd_firstname', t5.user_lastname 'upd_lastname',
                t6.user_firstname 'crt_firstname', t6.user_lastname 'crt_lastname'
         from supplier_contenedor t1
         LEFT OUTER JOIN company_data t3  ON t1.sord_company_id   = t3.id
         LEFT OUTER JOIN company_shops t4 ON t1.sord_shop_id      = t4.id
         LEFT OUTER JOIN user t5          ON t1.sord_updusr       = t5.id
         LEFT OUTER JOIN user t6          ON t1.sord_crtusr       = t6.id
         where
         t1.id = {$_REQUEST["id"]}";
$headdata = $CON->select($sql);
$headdata = $headdata[0];

//----------------------------------------------------------------------------------
$sql = " select t1.*
         from supplier_contenedor_items t1
         INNER JOIN supplier_order_items t2 ON t1.sord_pos_id = t2.id
         where
         t1.sord_id = {$_REQUEST["id"]}
         order by t1.id asc";
$posdata = $CON->select($sql);

//----------------------------------------------------------------------------------
$sql = " select *
         from company_data
         where
         id = {$headdata["sord_company_id"]}";
$company = $CON->select($sql);

$pdfname = "Contenedor-".sprintf("%05s", $headdata["id"]).".pdf";
header('Content-Type: application/pdf;');
header("Content-Disposition: attachment; filename={$pdfname}");

//-------------------------------------------------------------------------------
$pdf = new Cezpdf("LETTER");
$pdf->selectFont("../../../libs/thirdparty/pdfClassesAndFonts/fonts/Helvetica.afm");
$pdf->ezSetMargins(120, 35, 30, 35);

$sord_eta_puerto        = "";
$sord_eta_puertounibag  = "";
$sord_limit_paydate     = "";

if((int)$headdata["sord_eta_puerto"])
   $sord_eta_puerto = date("d.m.Y", $headdata["sord_eta_puerto"]);
if((int)$headdata["sord_eta_puertounibag"])
   $sord_eta_puertounibag = date("d.m.Y", $headdata["sord_eta_puertounibag"]);
if((int)$headdata["sord_limit_paydate"])
   $sord_limit_paydate = date("d.m.Y", $headdata["sord_limit_paydate"]);
   
unset($data);
$pdf->ezText("", 4);
$pdf = doc_linedraw($pdf, 25, 555, 1);
$attr = Array  ("showHeadings" => 0, "shaded" => 0, "xpos" => "left",
                "showLines"    => 0, "rowGap" => 2, "colGap" => 0,
                "fontSize"     => 10, "cols" => Array (
                   "X1"  => Array("width" => "80"),
                   "X2"  => Array("width" => "5"),
                   "X3"  => Array("width" => "205"),
                   "X4"  => Array("width" => "75"),
                   "X5"  => Array("width" => "5"),
                   "X6"  => Array("width" => "180")));
$xc = 0;
$data[$xc]["X1"] = "<b>FOLIO</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = sprintf("%05s", $headdata["id"]);
$data[$xc]["X4"] = "<b>FECHA</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = date('d.m.Y');
$xc++;
$data[$xc]["X1"] = "<b>SUCURSAL</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["shop_name"];
$data[$xc]["X4"] = "<b>USUARIO</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["crt_firstname"]." ".$headdata["crt_lastname"];
$xc++;
$data[$xc]["X1"] = "<b>BUQUE</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["sord_buque"];
$data[$xc]["X4"] = "<b>FORWARD</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["sord_forward"];
$xc++;
$data[$xc]["X1"] = "<b>INCOTERM</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["sord_incoterm"];
$data[$xc]["X4"] = "<b>BILL/LANDING</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["sord_billoflanding"];
$xc++;
$data[$xc]["X1"] = "<b>ETA PUERTO</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $sord_eta_puerto;
$data[$xc]["X4"] = "<b>ETA UNIBAG</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $sord_eta_puertounibag;
$xc++;
$data[$xc]["X1"] = "<b>CONTENEDOR</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $headdata["sord_contenedor"];
$data[$xc]["X4"] = "<b>DIAS VIAJE</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["sord_diasviaje"];
$xc++;
$data[$xc]["X1"] = "<b>FECHA PAGO</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = $sord_limit_paydate;
$data[$xc]["X4"] = "<b>OC'S</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["sord_ocs"];
$xc++;
$data[$xc]["X1"] = "<b>CREADO</b>";
$data[$xc]["X2"] = ":";
$data[$xc]["X3"] = date('d.m.Y H:i:s',$headdata["sord_crtdat"]);
$data[$xc]["X4"] = "<b>COMENTARIO</b>";
$data[$xc]["X5"] = ":";
$data[$xc]["X6"] = $headdata["sord_desc"];
$xc++;
$pdf->ezTable($data,$type,$dummy,$attr);
unset($data);
$pdf = doc_linedraw($pdf, 25, 555, 1);

//----------------------------------------------------------------------------------
$pdf->ezText("", 8);
$pdf->ezText("<b>Contenido del contenedor</b>", 12);

//-------------------------------------------------------------------------------
$attr = Array  ("showHeadings" => 1, "shaded" => 1, "xpos" => "left", 'lineCol' => Array(0.50,0.50,0.50),
                "showLines"    => 2, "rowGap" => 2, "colGap" => 2, "titleFontSize" => 8,
                "fontSize"     => 8, "shadeCol" => Array(0.95,0.95,0.95), "cols" => Array
                (
                   "<b>OC</b>"            => Array("width" => "60", "justification" => "left"),
                   "<b>FECHA OC</b>"      => Array("width" => "50", "justification" => "left"),
                   "<b>PROVEEDOR</b>"     => Array("width" => "150", "justification" => "left"),
                   "<b>POS</b>"           => Array("width" => "25", "justification" => "center"),
                   "<b>ARTICULO</b>"      => Array("width" => "170", "justification" => "left"),
                   "<b>KG</b>"            => Array("width" => "50", "justification" => "right"),
                   "<b>CANTIDAD</b>"      => Array("width" => "50", "justification" => "right")
                )
               );

$gesnetto = 0;
$rowcount = count($posdata);
for($x = 0; $x < $rowcount; $x++)
{
   //----------------------------------------------------------------------------------
   $sql = " select *
            from supplier_order_items
            where
            id = {$posdata[$x]["sord_pos_id"]}";
   $supporderpos = $CON->select($sql);
   $supporderpos = $supporderpos[0];

   //----------------------------------------------------------------------------------
   $sql = " select t1.*, t2.supp_short
            from supplier_order t1
            LEFT OUTER JOIN supplier t2 ON t1.sord_supplier_id  = t2.id
            where
            t1.id = {$supporderpos["sord_id"]}";
   $sorddata = $CON->select($sql);
   $sorddata = $sorddata[0];

   $fullpos = getSupplierOrderPos($CON, $supporderpos["sord_id"], "", $posdata[$x]["sord_pos_id"]);
   $fullpos = $fullpos[0];
   
   $data[$x]["<b>OC</b>"]           = $sorddata["sord_number"];
   $data[$x]["<b>FECHA OC</b>"]     = date('d.m.Y', $sorddata["sord_crtdat"]);
   $data[$x]["<b>PROVEEDOR</b>"]    = $sorddata["supp_short"];
   $data[$x]["<b>POS</b>"]          = ($supporderpos["item_pos"]+1);
   $data[$x]["<b>ARTICULO</b>"]     = $fullpos["item_title"];
   $data[$x]["<b>KG</b>"]           = printPrice($posdata[$x]["sord_kgs_amount"]);
   $data[$x]["<b>CANTIDAD</b>"]     = printPrice($posdata[$x]["sord_amount"], 2);
}
$pdf->ezText(" ", 6);
$pdf->ezTable($data,$type,$dummy,$attr);
unset($data);

//----------------------------------------------------------------------------------
$pdf->ezText("", 8);
$pdf->ezText("<b>Facturas asociadas</b>", 12);

//----------------------------------------------------------------------------------
$sql = " select distinct t4.id
         from supplier_contenedor_items t1
         INNER JOIN supplier_order_items t2  ON t1.sord_pos_id = t2.id
         INNER JOIN invoices_buy_parts t3    ON t2.sord_id = t3.part_sord_id
         INNER JOIN invoices_buy t4          ON t3.part_invc_id = t4.id
         where
         t1.sord_id = {$_REQUEST["id"]} and
         t4.invc_status > 1
         order by t1.id asc";
$posdata = $CON->select($sql);

$_SQL_OCS = "";
foreach($posdata AS $posdatarow)
   $_SQL_OCS .= "{$posdatarow["id"]},";
$_SQL_OCS = substr($_SQL_OCS, 0, -1);

//----------------------------------------------------------------------------------
$sql = " select t2.*, t4.supp_company
         from invoices_buy t2
         LEFT OUTER JOIN invoices_buy_contenedores t1 ON t1.invc_id = t2.id
         LEFT OUTER JOIN supplier t4   ON t2.invc_supplier_id = t4.id
         where
         (
            t1.cont_id = {$_REQUEST["id"]} ";
if($_SQL_OCS != "")
   $sql .= " or t2.id IN ({$_SQL_OCS}) ";
$sql .= " ) and
         t2.invc_status > 1
         order by t2.invc_date, t2.id";
$cont_ass = $CON->select($sql);

//----------------------------------------------------------------------------------
unset($data);
for($x = 0; $x < count($cont_ass) && $cont_ass != false; $x++)
{
   if((int)$cont_ass[$x]["invc_importation"])
   {
      $cont_ass[$x]["invc_total_netto"]   = $cont_ass[$x]["invc_import_total"];
      $cont_ass[$x]["invc_total_brutto"]  = $cont_ass[$x]["invc_import_total"];
      $cont_ass[$x]["invc_total_taxes"]   = 0;
   }

   $data[$x]["<b>FACTURA</b>"]         = $cont_ass[$x]["invc_docnumber"];
   $data[$x]["<b>FECHA</b>"]           = date('d.m.Y',$cont_ass[$x]["invc_date"]);
   $data[$x]["<b>PROVEEDOR</b>"]       = $cont_ass[$x]["supp_company"];
   $data[$x]["<b>MONTO/NETO</b>"]      = printPrice($cont_ass[$x]["invc_total_netto"]);
   $data[$x]["<b>IVA</b>"]             = printPrice($cont_ass[$x]["invc_total_taxes"]);
   $data[$x]["<b>MONTO/BRUTO</b>"]     = printPrice($cont_ass[$x]["invc_total_brutto"]);
}

//-------------------------------------------------------------------------------
$attr = Array  ("showHeadings" => 1, "shaded" => 1, "xpos" => "left", 'lineCol' => Array(0.50,0.50,0.50),
                "showLines"    => 2, "rowGap" => 2, "colGap" => 2, "titleFontSize" => 8,
                "fontSize"     => 8, "shadeCol" => Array(0.95,0.95,0.95), "cols" => Array
                (
                   "<b>FACTURA</b>"          => Array("width" => "60", "justification" => "left"),
                   "<b>FECHA</b>"            => Array("width" => "60", "justification" => "left"),
                   "<b>PROVEEDOR</b>"        => Array("width" => "235", "justification" => "left"),
                   "<b>MONTO/NETO</b>"       => Array("width" => "70", "justification" => "right"),
                   "<b>IVA</b>"              => Array("width" => "60", "justification" => "right"),
                   "<b>MONTO/BRUTO</b>"      => Array("width" => "70", "justification" => "right")
                )
               );
$pdf->ezText(" ", 6);
$pdf->ezTable($data,$type,$dummy,$attr);
unset($data);

//----------------------------------------------------------------------------------
$pagearr    = $pdf->ezPages;
$pcounter   = 0;

//----------------------------------------------------------------------------------
foreach($pagearr AS $pageid)
{
   $pdf->reopenObject($pageid);
   $pdf->ezSetMargins(0, 0, 20, 20);
   $pdf->ezSetY(780);

   if($company[0]["company_img_sell"] != "" && file_exists("{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/companies/s{$company[0]["company_img_sell"]}"))
   {
      $pdf->ezImage("{$_SESSION["_CONF"]["conf_shopadmin_path"]}images/companies/s{$company[0]["company_img_sell"]}", 0, 300, 'none', 'left');
      $pdf->ezSetY(770);
      $attr = Array  ("showHeadings" => 0, "shaded" => 0, "fontSize" => 14, "xOrientation" => "left",
                      "xPos" => 580, "showLines" => 1, "rowGap" => 3, "colGap" => 3,
                      "outerLineThickness" => 3, "innerLineThickness" => 3,
                      "cols" => Array ("x1" => Array("width" => "180", "justification" => "center")));

      $data[0]["x1"] = "\n<b>CONTENEDOR</b>\n\n<b>N° ".sprintf("%05s", $headdata["id"])."</b>\n";
      $pdf->ezTable($data,$type,$dummy,$attr);
      unset($data);
   }
}

//----------------------------------------------------------------------------------
echo $pdf->output();
?>